<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

use context_system;
use local_ltuse\recognition\badges;
use moodle_exception;

/**
 * Stores the badge template and rewords every published badge from it (spec 013, R2, R5, R16).
 *
 * The declaration arrives as the payload's `badge_template`, rendered by scripts/site_config.py
 * from moodle/site/badges.yaml with {programme} filled in:
 *
 *   badge_template {name, description, imagecaption, message_subject, message, version,
 *                   language, image {filename, sha256, content (base64)}, deny [{pattern, why}]}
 *
 * The template is kept in the plugin's config, `local_ltuse/badge_template`, as JSON without
 * the image bytes, and the image in the plugin's file area at system context. The publisher's
 * local_ltuse_set_course_recognition renders each course's badge from that stored copy, so a
 * publish never needs the repo's YAML.
 *
 * Items (subjects, contracts/declaration.md "Payload arrays"):
 *   badge template              missing (never applied) or changed (text, deny list or image)
 *   badge <course idnumber>     changed (its text or image differs from its rendering) or
 *                               missing (the map names a badge that is gone; the next publish
 *                               of that course creates it again)
 *   badge <course idnumber>: unmapped <id>   extra, from extras(): a badge in an ltct: course
 *                               that is not the published one, as a restore or a copy makes
 *
 * Badges are named by their course's idnumber, never by an award, a count or a learner (FR-008).
 * Nothing here judges whether a badge should be active: that needs the course's stage, which
 * only the publisher knows. Apply never deactivates, archives or deletes a badge.
 */
class badgetemplate {

    /** Item type. */
    const TYPE = 'badge';

    /** The config key that holds the stored template. */
    const CONFIG = 'badge_template';

    /** The template's own subject. */
    const SUBJECT = 'badge template';

    /** @var array the declared template, as the payload carries it */
    protected $declared;

    /**
     * @param array $declared the payload's `badge_template`
     */
    public function __construct(array $declared) {
        $this->declared = $declared;
    }

    /**
     * The stored template, as local_ltuse_set_course_recognition reads it, or null when
     * site_config.py apply has never stored one.
     *
     * @return array|null
     */
    public static function stored(): ?array {
        $json = get_config('local_ltuse', self::CONFIG);
        $template = $json ? json_decode($json, true) : null;
        return is_array($template) ? $template : null;
    }

    /**
     * The declared template in its stored form: no image bytes.
     *
     * @return array
     */
    public function storable(): array {
        $out = $this->declared;
        $out['image'] = ['filename' => (string)($this->declared['image']['filename'] ?? ''),
            'sha256' => (string)($this->declared['image']['sha256'] ?? '')];
        $out['deny'] = array_values(array_map(function($rule) {
            $rule = (array)$rule;
            return ['pattern' => (string)($rule['pattern'] ?? ''), 'why' => (string)($rule['why'] ?? '')];
        }, (array)($this->declared['deny'] ?? [])));
        ksort($out);
        return $out;
    }

    // --- checking (read only) ------------------------------------------------------------

    /**
     * The template's item, then one item per mapped course. WRITES NOTHING.
     *
     * @return array[] item results in inspector's shape
     */
    public function check(): array {
        if (!self::table_exists()) {
            return [self::result(self::SUBJECT, 'unknown', null, null,
                'the installed local_ltuse has no ' . badges::TABLE . ' table; run the plugin upgrade first', true)];
        }
        $items = [$this->check_template()];
        foreach (badges::mapped_courses() as $course) {
            $items[] = $this->check_course($course);
        }
        return $items;
    }

    /**
     * One `extra` item per unmapped badge in an `ltct:` course. WRITES NOTHING.
     *
     * @return array[] item results in inspector's shape
     */
    public static function extras(): array {
        if (!self::table_exists()) {
            return [];
        }
        $items = [];
        foreach (badges::all_unmapped() as $badge) {
            $items[] = self::result(self::subject((string)$badge->idnumber) . ": unmapped {$badge->id}", 'extra',
                null, (string)$badge->name, 'not the published badge (a restore or a copy makes these); left alone');
        }
        return $items;
    }

    /**
     * @return array the template's item
     */
    public function check_template(): array {
        $declared = $this->storable();
        $stored = self::stored();
        $summary = 'version ' . ($declared['version'] ?? '') . ', image ' . substr($declared['image']['sha256'], 0, 12);
        if ($stored === null) {
            return self::result(self::SUBJECT, 'missing', $summary, null, 'never applied');
        }
        ksort($stored);
        $differs = [];
        foreach ($declared as $key => $value) {
            if (($stored[$key] ?? null) != $value) {
                $differs[] = $key;
            }
        }
        if (!self::stored_file($declared['image'])) {
            $differs[] = 'image file';
        }
        if ($differs) {
            return self::result(self::SUBJECT, 'changed', $summary,
                'version ' . ($stored['version'] ?? '') . ', image ' . substr((string)($stored['image']['sha256'] ?? ''), 0, 12),
                'differs: ' . implode(', ', array_unique($differs)));
        }
        return self::result(self::SUBJECT, 'ok', $summary, $summary);
    }

    /**
     * @param \stdClass $course a mapped course
     * @return array its badge's item, rendered from the declared template
     */
    public function check_course(\stdClass $course): array {
        $subject = self::subject((string)$course->idnumber);
        $differences = badges::differences($course, $this->storable());
        if ($differences === null) {
            return self::result($subject, 'missing', 'rendered from the template', null,
                'its badge is gone; the next publish of this course creates it again');
        }
        if ($differences) {
            return self::result($subject, 'changed', 'rendered from the template', 'differs',
                'differs: ' . implode(', ', $differences));
        }
        return self::result($subject, 'ok', 'rendered from the template', 'rendered from the template');
    }

    // --- applying ------------------------------------------------------------------------

    /**
     * Store the template if it differs, then reword every mapped badge from it (US4-2).
     *
     * A badge whose rendered text breaks the CBC rule is refused and left as it was, and the
     * rest go ahead (R15).
     *
     * @param report $report
     */
    public function apply(report $report): void {
        if (!self::table_exists()) {
            $report->add_result($this->check()[0]);
            return;
        }
        $item = $this->check_template();
        if ($item['result'] === 'ok') {
            $report->add_result($item);
        } else {
            try {
                $this->store();
            } catch (moodle_exception $e) {
                // Nothing is reworded from a template that was not stored.
                $report->add_result($item, 'fail', 'not stored: ' . $e->getMessage());
                return;
            }
            $report->add_result($item, 'changed');
        }

        $template = self::stored();
        foreach (badges::mapped_courses() as $course) {
            $subject = self::subject((string)$course->idnumber);
            try {
                $outcome = badges::reword_course($course, $template);
            } catch (moodle_exception $e) {
                $report->add('fail', 'changed', $subject, 'rendered from the template', null,
                    'refused, left as it was: ' . $e->getMessage());
                continue;
            }
            if ($outcome === 'missing') {
                // Not a failed apply: the next publish of the course makes its badge again.
                $report->add_result($this->check_course($course), 'skip');
            } else if ($outcome === 'changed') {
                $report->add('changed', 'changed', $subject, 'rendered from the template', 'differed', 'reworded in place');
            } else {
                $report->add('ok', '', $subject, 'rendered from the template', 'rendered from the template');
            }
        }
    }

    /**
     * Write the image to the file area, replacing an older one, then the config.
     */
    protected function store(): void {
        $image = (array)($this->declared['image'] ?? []);
        $content = base64_decode((string)($image['content'] ?? ''), true);
        if ($content === false || hash('sha256', $content) !== (string)($image['sha256'] ?? '')) {
            throw new moodle_exception('error:badimage', 'local_ltuse', '', (string)($image['filename'] ?? ''));
        }
        $fs = get_file_storage();
        $contextid = context_system::instance()->id;
        $fs->delete_area_files($contextid, 'local_ltuse', badges::FILEAREA, 0);
        $fs->create_file_from_string(['contextid' => $contextid, 'component' => 'local_ltuse',
            'filearea' => badges::FILEAREA, 'itemid' => 0, 'filepath' => '/',
            'filename' => clean_filename((string)$image['filename'])], $content);
        set_config(self::CONFIG, json_encode($this->storable()), 'local_ltuse');
    }

    /**
     * Whether the stored image file is the declared one.
     *
     * @param array $image {filename, sha256}
     * @return bool
     */
    protected static function stored_file(array $image): bool {
        $file = get_file_storage()->get_file(context_system::instance()->id, 'local_ltuse', badges::FILEAREA, 0, '/',
            clean_filename((string)$image['filename']));
        return $file && hash('sha256', $file->get_content()) === (string)$image['sha256'];
    }

    /**
     * @param string $idnumber a course idnumber
     * @return string
     */
    public static function subject(string $idnumber): string {
        return self::TYPE . " {$idnumber}";
    }

    /**
     * @return bool whether the plugin upgrade that adds the badge map has run
     */
    protected static function table_exists(): bool {
        global $DB;
        return $DB->get_manager()->table_exists(badges::TABLE);
    }

    /**
     * @param string $item
     * @param string $result
     * @param mixed $declared
     * @param mixed $live
     * @param string $message
     * @param bool $blocking
     * @return array
     */
    protected static function result(string $item, string $result, $declared = null, $live = null,
            string $message = '', bool $blocking = false): array {
        return ['type' => self::TYPE, 'item' => $item, 'result' => $result, 'declared' => $declared,
            'live' => $live, 'message' => $message, 'secret' => false, 'blocking' => $blocking];
    }
}
