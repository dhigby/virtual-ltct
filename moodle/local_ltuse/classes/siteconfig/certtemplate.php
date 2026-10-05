<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

use context_system;
use mod_customcert\element_helper;
use mod_customcert\service\element_factory;
use mod_customcert\service\element_layout;
use mod_customcert\service\element_repository;
use mod_customcert\service\page_repository;
use mod_customcert\service\template_load_service;
use mod_customcert\service\template_repository;
use mod_customcert\service\template_service;
use mod_customcert\template;
use moodle_exception;
use stdClass;

/**
 * The one certificate design: a mod_customcert site template, copied into every published
 * course's certificate activity (spec 013, R7).
 *
 * The declaration arrives as the payload's `certificate_template`, rendered by
 * scripts/site_config.py from moodle/site/certificate/template.yaml, {programme} filled in and
 * the date already mapped to its element value:
 *
 *   certificate_template {name, activity_name, intro, font,
 *                         pages [{width, height, leftmargin, rightmargin,
 *                                 elements [{type, x, y, size?, align?, width?, height?,
 *                                            text?, dateitem?, dateformat?,
 *                                            image? {filename, sha256, content}}]}]}
 *
 * It is stored in the plugin's config, `local_ltuse/certificate_template`, without image
 * bytes, so the publisher's certificate step knows the activity's name and intro. Its images
 * go into mod_customcert's own `image` file area at system context, where the image element
 * looks for a site template's files.
 *
 * IDENTITY. customcert_templates has only a name and a context, and its import always inserts,
 * so the site template is the one system-context template with exactly the declared name. Two
 * of them is `ambiguous`, which blocks the run. A course's activity is the course module with
 * idnumber `ltct:<slug>:certificate`.
 *
 * PRINCIPLE XI. These are mod_customcert's own classes, not a published API: template,
 * template_repository, template_service (delete_page), page_repository (create, list_by_template),
 * element_factory and element_repository (create, list_by_page), element_layout and
 * template_load_service (replace). Every call is listed in the local_ltuse README, and
 * quickstart V6 is re-run on every mod_customcert re-pin. Its tables are never written with SQL.
 *
 * Items: `certificate template` (missing, changed, ambiguous) and, per activity,
 * `certificate <course idnumber>` (changed when its pages differ from the site template's).
 * Apply never deletes a site template or an activity: deleting an activity deletes every
 * issued code (R14).
 */
class certtemplate {

    /** Item type. */
    const TYPE = 'certificate';

    /** The config key that holds the stored template. */
    const CONFIG = 'certificate_template';

    /** The template's own subject. */
    const SUBJECT = 'certificate template';

    /** The certificate activity's idnumber suffix: ltct:<slug>:certificate. */
    const IDNUMBER_SUFFIX = ':certificate';

    /** mod_customcert's image file area, which the image element reads. */
    const FILEAREA = 'image';

    /** Text colour, as the element form defaults it. */
    const COLOUR = '#000000';

    /** Font size when the declaration gives none, as the element form defaults it. */
    const FONTSIZE = 12;

    /** The keys of an image element's data that say what it shows, not where its file is. */
    const IMAGE_KEYS = ['filename', 'width', 'height', 'alphachannel'];

    /** @var array the declared template, as the payload carries it */
    protected $declared;

    /**
     * @param array $declared the payload's `certificate_template`
     */
    public function __construct(array $declared) {
        $this->declared = $declared;
    }

    /**
     * The stored declaration, or null when site_config.py apply has never stored one.
     *
     * @return array|null
     */
    public static function stored(): ?array {
        $json = get_config('local_ltuse', self::CONFIG);
        $template = $json ? json_decode($json, true) : null;
        return is_array($template) ? $template : null;
    }

    /**
     * Whether mod_customcert is installed. Without it there is nothing to check or build.
     *
     * @return bool
     */
    public static function plugin_installed(): bool {
        return class_exists(template_load_service::class);
    }

    /**
     * The declaration in its stored form: no image bytes.
     *
     * @return array
     */
    public function storable(): array {
        $out = $this->declared;
        foreach ($out['pages'] ?? [] as $p => $page) {
            foreach ($page['elements'] ?? [] as $e => $element) {
                if (isset($element['image'])) {
                    unset($out['pages'][$p]['elements'][$e]['image']['content']);
                }
            }
        }
        return $out;
    }

    // --- checking (read only) ------------------------------------------------------------

    /**
     * The site template's item, then one per certificate activity. WRITES NOTHING.
     *
     * @return array[] item results in inspector's shape
     */
    public function check(): array {
        if (!self::plugin_installed()) {
            return [self::result(self::SUBJECT, 'unknown', $this->declared['name'] ?? '', null,
                'mod_customcert is not installed; install it at its pin in site.yaml first', true)];
        }
        $items = [$this->check_template()];
        $site = self::find_site_templates((string)($this->declared['name'] ?? ''));
        $want = count($site) === 1 ? self::live_signature((int)reset($site)->id) : self::expected_signature($this->storable());
        foreach (self::activities() as $activity) {
            $items[] = self::check_activity($activity, $want);
        }
        return $items;
    }

    /**
     * @return array the site template's item
     */
    public function check_template(): array {
        $name = (string)($this->declared['name'] ?? '');
        $site = self::find_site_templates($name);
        if (count($site) > 1) {
            return self::result(self::SUBJECT, 'ambiguous', $name, count($site) . ' site templates',
                "more than one system-context template is named '{$name}'; delete or rename the extra by hand", true);
        }
        $images = $this->image_differences();
        if (!$site) {
            return self::result(self::SUBJECT, 'missing', $name, null, 'never applied');
        }
        $differs = [];
        if (self::live_signature((int)reset($site)->id) !== self::expected_signature($this->storable())) {
            $differs[] = 'pages';
        }
        if ($images) {
            $differs[] = 'images ' . implode(', ', $images);
        }
        if (self::stored() != $this->storable()) {
            $differs[] = 'stored declaration';
        }
        if ($differs) {
            return self::result(self::SUBJECT, 'changed', $name, $name, 'differs: ' . implode('; ', $differs));
        }
        return self::result(self::SUBJECT, 'ok', $name, $name);
    }

    /**
     * @param stdClass $activity {cmid, idnumber, templateid}
     * @param array $want the site template's signature
     * @return array
     */
    public static function check_activity(stdClass $activity, array $want): array {
        $subject = self::subject((string)$activity->courseidnumber);
        if (self::live_signature((int)$activity->templateid) !== $want) {
            return self::result($subject, 'changed', 'the site template', 'differs',
                'its pages differ from the site template; apply copies it in');
        }
        return self::result($subject, 'ok', 'the site template', 'the site template');
    }

    // --- applying ------------------------------------------------------------------------

    /**
     * Store the declaration and images, build the site template if it differs, then copy it
     * into every activity whose pages differ.
     *
     * @param report $report
     */
    public function apply(report $report): void {
        $item = $this->check_template();
        if ($item['result'] === 'unknown' || $item['result'] === 'ambiguous') {
            $report->add_result($item);
            return;
        }
        if ($item['result'] === 'ok') {
            $report->add_result($item);
        } else {
            try {
                $this->build();
            } catch (moodle_exception $e) {
                $report->add_result($item, 'fail', 'not built: ' . $e->getMessage());
                return;
            }
            $report->add_result($item, 'changed', $item['result'] === 'missing' ? 'created' : null);
        }

        $site = self::find_site_templates((string)$this->declared['name']);
        $site = reset($site);
        $want = self::live_signature((int)$site->id);
        foreach (self::activities() as $activity) {
            $check = self::check_activity($activity, $want);
            if ($check['result'] === 'ok') {
                $report->add_result($check);
                continue;
            }
            template_load_service::create()->replace((int)$activity->templateid, (int)$site->id);
            $report->add_result($check, 'changed', 'copied from the site template');
        }
    }

    /**
     * Copy the site template into one activity's template if its pages differ. For the
     * publisher's certificate step.
     *
     * @param int $templateid the activity's customcert.templateid
     * @return bool whether it was copied
     * @throws moodle_exception error:recognitionnotapplied when there is no single site template
     */
    public static function copy_into(int $templateid): bool {
        $stored = self::stored();
        $site = $stored ? self::find_site_templates((string)$stored['name']) : [];
        if (count($site) !== 1) {
            throw new moodle_exception('error:recognitionnotapplied', 'local_ltuse');
        }
        $site = reset($site);
        if (self::live_signature($templateid) === self::live_signature((int)$site->id)) {
            return false;
        }
        template_load_service::create()->replace($templateid, (int)$site->id);
        return true;
    }

    /**
     * Write the images, the site template's pages and elements, then the stored declaration,
     * in one transaction. The site template is kept and its pages replaced; it is never deleted.
     */
    protected function build(): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $this->store_images();

        $syscontextid = context_system::instance()->id;
        $name = (string)$this->declared['name'];
        $site = self::find_site_templates($name);
        $template = $site ? template::from_record(reset($site)) : template::create($name, $syscontextid);

        $service = template_service::create();
        $pages = new page_repository();
        foreach ($pages->list_by_template($template->get_id()) as $page) {
            $service->delete_page($template, (int)$page->id, false);
        }

        $factory = element_factory::build_with_defaults();
        $elements = new element_repository($factory);
        $stored = $this->storable();
        foreach ($stored['pages'] as $sequence => $page) {
            $pageid = $pages->create((object)['templateid' => $template->get_id(),
                'width' => (int)$page['width'], 'height' => (int)$page['height'],
                'leftmargin' => (int)($page['leftmargin'] ?? 0), 'rightmargin' => (int)($page['rightmargin'] ?? 0),
                'sequence' => $sequence + 1]);
            foreach (self::element_records($page, (string)$stored['font'], $syscontextid) as $record) {
                $record->pageid = $pageid;
                $elements->create($factory->create($record->element, $record), element_layout::from_record($record));
            }
        }
        set_config(self::CONFIG, json_encode($stored), 'local_ltuse');
        $transaction->allow_commit();
    }

    /**
     * Write each declared image into mod_customcert's file area at system context, replacing
     * one with the same name whose content differs. Other files there are left alone.
     */
    protected function store_images(): void {
        $fs = get_file_storage();
        $contextid = context_system::instance()->id;
        foreach ($this->images() as $image) {
            $content = base64_decode((string)($image['content'] ?? ''), true);
            if ($content === false || hash('sha256', $content) !== (string)$image['sha256']) {
                throw new moodle_exception('error:badimage', 'local_ltuse', '', (string)$image['filename']);
            }
            $filename = self::stored_name($image);
            $file = $fs->get_file($contextid, 'mod_customcert', self::FILEAREA, 0, '/', $filename);
            if ($file && hash('sha256', $file->get_content()) === $image['sha256']) {
                continue;
            }
            if ($file) {
                $file->delete();
            }
            $fs->create_file_from_string(['contextid' => $contextid, 'component' => 'mod_customcert',
                'filearea' => self::FILEAREA, 'itemid' => 0, 'filepath' => '/', 'filename' => $filename], $content);
        }
    }

    /**
     * @return string[] the declared images whose stored file is absent or differs
     */
    protected function image_differences(): array {
        $fs = get_file_storage();
        $contextid = context_system::instance()->id;
        $out = [];
        foreach ($this->images() as $image) {
            $file = $fs->get_file($contextid, 'mod_customcert', self::FILEAREA, 0, '/', self::stored_name($image));
            if (!$file || hash('sha256', $file->get_content()) !== (string)$image['sha256']) {
                $out[] = (string)$image['filename'];
            }
        }
        return $out;
    }

    /**
     * @return array[] every declared image, {filename, sha256, content}
     */
    protected function images(): array {
        $out = [];
        foreach ($this->declared['pages'] ?? [] as $page) {
            foreach ($page['elements'] ?? [] as $element) {
                if (isset($element['image'])) {
                    $out[$element['image']['filename']] = (array)$element['image'];
                }
            }
        }
        return array_values($out);
    }

    /**
     * The name an image is stored under: its content's hash, then its declared name. The
     * image element reuses a course's copy of a file with the same name and never refreshes
     * it, so a changed image must also change its name to reach courses already copied.
     *
     * @param array $image {filename, sha256}
     * @return string
     */
    public static function stored_name(array $image): string {
        return substr((string)$image['sha256'], 0, 12) . '-' . clean_filename((string)$image['filename']);
    }

    // --- signatures: what a template shows, comparable across copies --------------------------

    /**
     * The customcert_elements records one declared page makes, before they have a page id.
     *
     * @param array $page a declared page
     * @param string $font the template's font
     * @param int $syscontextid where the images are stored
     * @return stdClass[]
     */
    public static function element_records(array $page, string $font, int $syscontextid): array {
        $out = [];
        foreach (array_values($page['elements'] ?? []) as $index => $e) {
            $type = (string)$e['type'];
            $text = ['font' => $font, 'fontsize' => (int)($e['size'] ?? self::FONTSIZE),
                'colour' => self::COLOUR, 'width' => (int)($e['width'] ?? 0)];
            switch ($type) {
                case 'text':
                    $data = ['text' => (string)$e['text']] + $text;
                    break;
                case 'coursename':
                    $data = ['coursenamedisplay' => \customcertelement_coursename\element::COURSE_FULL_NAME] + $text;
                    break;
                case 'date':
                    // A string, as the element's form stores it: its restore step calls
                    // str_starts_with() on it under strict_types, which an int would break.
                    $data = ['dateitem' => (string)$e['dateitem'], 'dateformat' => (string)$e['dateformat']] + $text;
                    break;
                case 'qrcode':
                    // Square unless a height is declared: a height of 0 makes TCPDF draw no code at all.
                    $data = ['width' => (int)$e['width'], 'height' => (int)($e['height'] ?? $e['width'])];
                    break;
                case 'image':
                case 'bgimage':
                    $data = ['width' => (int)($e['width'] ?? 0), 'height' => (int)($e['height'] ?? 0),
                        'contextid' => $syscontextid, 'filearea' => self::FILEAREA, 'itemid' => 0,
                        'filepath' => '/', 'filename' => self::stored_name((array)$e['image'])];
                    break;
                default:   // studentname, code
                    $data = $text;
            }
            $align = (string)($e['align'] ?? 'L');
            $out[] = (object)[
                'id' => 0,
                'element' => $type,
                'name' => ucfirst($type) . ' ' . ($index + 1),
                'data' => json_encode($data),
                'posx' => $type === 'bgimage' ? null : (int)($e['x'] ?? 0),
                'posy' => $type === 'bgimage' ? null : (int)($e['y'] ?? 0),
                'refpoint' => self::refpoint($align),
                'alignment' => $align,
            ];
        }
        return $out;
    }

    /**
     * The reference point that makes x the left edge, centre or right edge, as the alignment says.
     *
     * @param string $align L, C or R
     * @return int
     */
    public static function refpoint(string $align): int {
        if ($align === 'C') {
            return element_helper::CUSTOMCERT_REF_POINT_TOPCENTER;
        }
        if ($align === 'R') {
            return element_helper::CUSTOMCERT_REF_POINT_TOPRIGHT;
        }
        return element_helper::CUSTOMCERT_REF_POINT_TOPLEFT;
    }

    /**
     * What a stored declaration shows, in signature form.
     *
     * @param array $stored
     * @return array
     */
    public static function expected_signature(array $stored): array {
        $out = [];
        foreach ($stored['pages'] ?? [] as $page) {
            $elements = [];
            foreach (self::element_records($page, (string)($stored['font'] ?? ''), 0) as $record) {
                $elements[] = self::element_signature($record);
            }
            $out[] = [(int)$page['width'], (int)$page['height'], (int)($page['leftmargin'] ?? 0),
                (int)($page['rightmargin'] ?? 0), $elements];
        }
        return $out;
    }

    /**
     * What a live template shows, in signature form. READ ONLY.
     *
     * @param int $templateid
     * @return array
     */
    public static function live_signature(int $templateid): array {
        $elements = new element_repository(element_factory::build_with_defaults());
        $out = [];
        foreach ((new page_repository())->list_by_template($templateid) as $page) {
            $sig = [];
            foreach ($elements->list_by_page((int)$page->id) as $record) {
                $sig[] = self::element_signature($record);
            }
            $out[] = [(int)$page->width, (int)$page->height, (int)$page->leftmargin, (int)$page->rightmargin, $sig];
        }
        return $out;
    }

    /**
     * One element, as what it shows: an image by its file's name and size, never by where the
     * file is stored, because copying into a course moves the file to the course's context.
     *
     * @param stdClass $record a customcert_elements record
     * @return array
     */
    public static function element_signature(stdClass $record): array {
        $data = json_decode((string)$record->data, true);
        $data = is_array($data) ? $data : ['value' => $record->data];
        if (in_array($record->element, ['image', 'bgimage'], true)) {
            $data = array_intersect_key($data, array_flip(self::IMAGE_KEYS));
        }
        ksort($data);
        $int = function($v) {
            return ($v === null || $v === '') ? null : (int)$v;
        };
        return [(string)$record->element, (string)$record->name, $int($record->posx), $int($record->posy),
            $int($record->refpoint), (string)$record->alignment, $data];
    }

    // --- lookups (read only) -------------------------------------------------------------

    /**
     * System-context templates with exactly this name, compared in PHP because a collation may
     * fold case.
     *
     * @param string $name
     * @return stdClass[]
     */
    public static function find_site_templates(string $name): array {
        $records = (new template_repository())->list_by_context(context_system::instance()->id);
        return array_values(array_filter($records, function($r) use ($name) {
            return (string)$r->name === $name;
        }));
    }

    /**
     * Every certificate activity the publisher made: a customcert module whose idnumber is
     * `ltct:<slug>:certificate`, with its course's idnumber and its template.
     *
     * @return stdClass[] {cmid, idnumber, courseidnumber, templateid}
     */
    public static function activities(): array {
        global $DB;
        $sql = "SELECT cm.id AS cmid, cm.idnumber, c.idnumber AS courseidnumber, cc.templateid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'customcert'
                  JOIN {customcert} cc ON cc.id = cm.instance
                  JOIN {course} c ON c.id = cm.course
                 WHERE " . $DB->sql_like('cm.idnumber', ':pattern') . "
              ORDER BY c.idnumber";
        $records = $DB->get_records_sql($sql, ['pattern' => $DB->sql_like_escape(\local_ltuse\util::IDNUMBER_PREFIX) . '%'
            . $DB->sql_like_escape(self::IDNUMBER_SUFFIX)]);
        return array_values(array_filter($records, function($r) {
            return $r->idnumber === $r->courseidnumber . self::IDNUMBER_SUFFIX;
        }));
    }

    /**
     * @param string $idnumber a course idnumber
     * @return string
     */
    public static function subject(string $idnumber): string {
        return self::TYPE . " {$idnumber}";
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
