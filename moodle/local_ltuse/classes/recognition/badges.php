<?php
namespace local_ltuse\recognition;

defined('MOODLE_INTERNAL') || die();

use core_badges\badge;
use moodle_exception;
use stdClass;

/**
 * One completion badge per published course: create, reword, activate (spec 013, R1, R4, R5).
 *
 * Core has no web service that creates or updates a badge, or sets its criteria, so this
 * class does it through the classes the badge pages use: \core_badges\badge (create_badge(),
 * save(), set_status()), award_criteria::build()->save() and badges_process_badge_image().
 *
 * IDENTITY. A badge has no idnumber, and a restore or a course copy duplicates its name, so
 * local_ltuse_course_badge maps each course to its badge. A badge in the course that the map
 * does not name is `extra`: reported, never adopted.
 *
 * CRITERIA. An overall criterion with ALL, and one course-completion criterion,
 * `course_<id>`. Nothing else: a competency criterion would read as a competency awarded
 * (spec Assumptions, Principle V). Core's observer awards the badge the moment the course
 * completion is recorded (R3).
 *
 * LIFECYCLE. Created INACTIVE. Activated only on a delivery publish, so a pilot issues
 * nothing (R4). Once active it is reworded in place and NEVER deactivated, archived or
 * deleted: an INACTIVE_LOCKED or ARCHIVED badge makes the BadgeClass JSON return 410 to
 * everyone who already holds it (R5).
 *
 * Writes no user data and reads none: the warnings it returns name the course, never a
 * learner, and nothing here counts awards (FR-008).
 */
class badges {

    /** The map from a course to its badge. */
    const TABLE = 'local_ltuse_course_badge';

    /** Where site_config.py apply stores the template's image (system context). */
    const FILEAREA = 'badgetemplate';

    /**
     * Bring one course's badge to the template, and activate it on a delivery publish.
     *
     * @param stdClass $course the course record
     * @param bool $delivery whether course_stage.py reports the course at stage 8
     * @param array $template the stored template (badgetemplate::stored())
     * @return array{badge: string, status: string, warnings: array[]}
     * @throws moodle_exception error:badgewording when the rendered text breaks the CBC rule
     */
    public static function sync(stdClass $course, bool $delivery, array $template): array {
        global $DB;
        self::load_libraries();
        $expected = self::expected($course, $template);
        self::require_wording($course, $expected, $template);

        $map = $DB->get_record(self::TABLE, ['courseid' => $course->id]);
        $badge = $map ? self::load((int)$map->badgeid) : null;
        if ($badge === null) {
            $badge = self::create($course, $expected, $template, $map);
            $outcome = 'created';
        } else {
            $outcome = self::reword($badge, $expected, $template, $map) ? 'updated' : 'unchanged';
        }

        $warnings = [];
        if ($delivery) {
            if ($badge->is_active()) {
                $status = 'active';
            } else if ((int)$badge->status === BADGE_STATUS_ARCHIVED) {
                // Only a course deletion archives a course badge; this one is not ours to revive.
                $status = 'inactive';
            } else {
                // As the badge page's "Enable access" does: a badge that has awards stays locked.
                $badge->set_status($badge->is_locked() ? BADGE_STATUS_ACTIVE_LOCKED : BADGE_STATUS_ACTIVE);
                $status = 'activated';
            }
        } else {
            $status = $badge->is_active() ? 'active' : 'inactive';
            if ($badge->is_active()) {
                // Left active: deactivating it would break verification for every holder (R5).
                $warnings[] = ['code' => 'active-not-delivery', 'message' =>
                    'the badge is active but the course is not at stage 8; it was left active'];
            }
        }

        if ((int)$course->startdate > time()) {
            $warnings[] = ['code' => 'startdate-future', 'message' =>
                'the course starts ' . userdate((int)$course->startdate) . '; core awards no badge before then'];
        }
        if ($delivery && empty($course->visible)) {
            $warnings[] = ['code' => 'course-hidden', 'message' =>
                'the course is hidden, so the badge cron skips it; awards still follow each completion'];
        }
        foreach (self::unmapped((int)$course->id) as $extra) {
            $warnings[] = ['code' => 'badge-extra', 'message' =>
                "badge {$extra->id} in this course is not the published badge; left alone"];
        }
        return ['badge' => $outcome, 'status' => $status, 'warnings' => $warnings];
    }

    /**
     * Reword one mapped badge from the template, as site_config.py apply does for every
     * mapped badge (US4-2). The badge stays as active as it was.
     *
     * @param stdClass $course
     * @param array $template
     * @return string changed, unchanged or missing (the map names a badge that is gone)
     * @throws moodle_exception error:badgewording
     */
    public static function reword_course(stdClass $course, array $template): string {
        global $DB;
        self::load_libraries();
        $map = $DB->get_record(self::TABLE, ['courseid' => $course->id], '*', MUST_EXIST);
        $badge = self::load((int)$map->badgeid);
        if ($badge === null) {
            return 'missing';
        }
        $expected = self::expected($course, $template);
        self::require_wording($course, $expected, $template);
        return self::reword($badge, $expected, $template, $map) ? 'changed' : 'unchanged';
    }

    /**
     * How one mapped badge differs from its rendering. READ ONLY.
     *
     * @param stdClass $course
     * @param array $template
     * @return string[]|null the differing field names, or null when the map's badge is gone
     */
    public static function differences(stdClass $course, array $template): ?array {
        global $DB;
        self::load_libraries();
        $map = $DB->get_record(self::TABLE, ['courseid' => $course->id], '*', MUST_EXIST);
        $badge = self::load((int)$map->badgeid);
        if ($badge === null) {
            return null;
        }
        return self::differing($badge, self::expected($course, $template), $template, $map);
    }

    /**
     * Every mapped course, with its course record. READ ONLY.
     *
     * @return stdClass[] course records, keyed by course id; a map row whose course is gone is left out
     */
    public static function mapped_courses(): array {
        global $DB;
        $sql = "SELECT c.*
                  FROM {" . self::TABLE . "} m
                  JOIN {course} c ON c.id = m.courseid
              ORDER BY c.idnumber";
        return $DB->get_records_sql($sql);
    }

    /**
     * Course badges in a course that the map does not name, never archived ones. READ ONLY.
     *
     * @param int $courseid
     * @return stdClass[] badge records (id, name)
     */
    public static function unmapped(int $courseid): array {
        global $DB;
        self::load_libraries();
        $sql = "SELECT b.id, b.name
                  FROM {badge} b
             LEFT JOIN {" . self::TABLE . "} m ON m.badgeid = b.id
                 WHERE b.courseid = :courseid AND b.type = :type AND b.status <> :archived
                   AND m.id IS NULL";
        return $DB->get_records_sql($sql, ['courseid' => $courseid, 'type' => BADGE_TYPE_COURSE,
            'archived' => BADGE_STATUS_ARCHIVED]);
    }

    /**
     * Every `ltct:` course's unmapped badges, for drift's `extra`. READ ONLY.
     *
     * @return stdClass[] records (id, name, courseid, idnumber)
     */
    public static function all_unmapped(): array {
        global $DB;
        self::load_libraries();
        $sql = "SELECT b.id, b.name, b.courseid, c.idnumber
                  FROM {badge} b
                  JOIN {course} c ON c.id = b.courseid
             LEFT JOIN {" . self::TABLE . "} m ON m.badgeid = b.id
                 WHERE b.type = :type AND b.status <> :archived AND m.id IS NULL
                   AND " . $DB->sql_like('c.idnumber', ':prefix');
        return $DB->get_records_sql($sql, ['type' => BADGE_TYPE_COURSE,
            'archived' => BADGE_STATUS_ARCHIVED,
            'prefix' => $DB->sql_like_escape(\local_ltuse\util::IDNUMBER_PREFIX) . '%']);
    }

    /**
     * The badge record's fields for one course, rendered from the template.
     *
     * @param stdClass $course
     * @param array $template
     * @return array field => value, as the badge table holds them
     */
    public static function expected(stdClass $course, array $template): array {
        global $CFG;
        $fields = self::course_fields((int)$course->id);
        $texts = renderer::render($template, (string)$course->fullname,
            $fields['ltct_competencies'] ?? '', $fields['ltct_target_level'] ?? '');
        return [
            'name' => $texts['name'],
            'description' => $texts['description'],
            'imagecaption' => $texts['imagecaption'],
            'messagesubject' => $texts['message_subject'],
            'message' => clean_text(renderer::message_html($texts['message']), FORMAT_HTML),
            'version' => trim((string)($template['version'] ?? '')),
            'language' => (string)($template['language'] ?? ''),
            'issuername' => (string)($CFG->badges_defaultissuername ?? ''),
            'issuerurl' => renderer::issuerurl($CFG->wwwroot),
            'issuercontact' => (string)($CFG->badges_defaultissuercontact ?? ''),
            // The award email stays light: no PNG attached (R5). Notices to the badge's
            // creator are off; the learner is always messaged.
            'attachment' => 0,
            'notification' => BADGE_MESSAGE_NEVER,
            // Training evidence does not expire.
            'expiredate' => null,
            'expireperiod' => null,
        ];
    }

    // --- internals ------------------------------------------------------------------------

    /**
     * badgeslib defines the BADGE_* constants and requires award_criteria.php, which defines
     * BADGE_CRITERIA_*. The badge class requires badgeslib too, but only once it autoloads.
     */
    protected static function load_libraries(): void {
        global $CFG;
        require_once($CFG->libdir . '/badgeslib.php');
    }

    /**
     * @param int $badgeid
     * @return badge|null null when the badge is gone
     */
    protected static function load(int $badgeid): ?badge {
        global $DB;
        if (!$DB->record_exists('badge', ['id' => $badgeid])) {
            return null;
        }
        return new badge($badgeid);
    }

    /**
     * Raw values of the course's two `ltct_` fields (spec 004, R11), hidden ones included.
     *
     * @param int $courseid
     * @return array<string, string> shortname => value
     */
    protected static function course_fields(int $courseid): array {
        $out = [];
        foreach (\core_course\customfield\course_handler::create()->get_instance_data($courseid, true) as $data) {
            $out[$data->get_field()->get('shortname')] = (string)$data->get_value();
        }
        return $out;
    }

    /**
     * Refuse, writing nothing, when the rendered text breaks the CBC rule (R15).
     *
     * @param stdClass $course
     * @param array $expected
     * @param array $template
     * @throws moodle_exception error:badgewording
     */
    protected static function require_wording(stdClass $course, array $expected, array $template): void {
        $texts = array_intersect_key($expected, array_flip(['name', 'description', 'imagecaption',
            'messagesubject', 'message', 'issuername']));
        $problems = wording::problems($texts, (array)($template['deny'] ?? []));
        if ($problems) {
            throw new moodle_exception('error:badgewording', 'local_ltuse', '',
                $course->idnumber . ': ' . implode('; ', $problems));
        }
    }

    /**
     * Create the course's badge, its two criteria and its image, and map it.
     *
     * @param stdClass $course
     * @param array $expected
     * @param array $template
     * @param stdClass|false $map a map row whose badge is gone, to point at the new one
     * @return badge
     */
    protected static function create(stdClass $course, array $expected, array $template, $map): badge {
        global $DB;
        self::require_gd();
        $transaction = $DB->start_delegated_transaction();

        // create_badge() reads every one of these without isset(), and sets the message,
        // attachment and status itself; reword() corrects the first two below.
        $data = (object)array_intersect_key($expected, array_flip(['name', 'version', 'language',
            'description', 'imagecaption', 'issuername', 'issuerurl', 'issuercontact']));
        $data->expiry = 0;
        $data->tags = [];
        $badge = badge::create_badge($data, (int)$course->id);

        // The overall criterion first, as the criteria page saves it, then the course one.
        // award_criteria::save() raises its events in $PAGE->context, which the web service
        // has set to the course.
        \award_criteria::build(['criteriatype' => BADGE_CRITERIA_TYPE_OVERALL, 'badgeid' => $badge->id])
            ->save(['agg' => BADGE_CRITERIA_AGGREGATION_ALL]);
        \award_criteria::build(['criteriatype' => BADGE_CRITERIA_TYPE_COURSE, 'badgeid' => $badge->id])
            ->save(['course_' . $course->id => $course->id, 'agg' => BADGE_CRITERIA_AGGREGATION_ALL]);

        if ($map) {
            $map->badgeid = $badge->id;
            $map->imagehash = '';
            $DB->update_record(self::TABLE, $map);
        } else {
            $map = (object)['courseid' => $course->id, 'badgeid' => $badge->id, 'imagehash' => '',
                'timecreated' => time()];
            $map->id = $DB->insert_record(self::TABLE, $map);
        }

        // Criteria are loaded in the constructor, so reload before anything reads them.
        $badge = new badge($badge->id);
        self::reword($badge, $expected, $template, $map);
        $transaction->allow_commit();
        return $badge;
    }

    /**
     * Write every field and the image that differ from the rendering. The status is never
     * touched: save() writes the row as it is, active or not.
     *
     * @param badge $badge
     * @param array $expected
     * @param array $template
     * @param stdClass $map the badge's map row
     * @return bool whether anything was written
     */
    protected static function reword(badge $badge, array $expected, array $template, stdClass $map): bool {
        global $DB, $USER;
        $differing = self::differing($badge, $expected, $template, $map);
        if (!$differing) {
            return false;
        }
        $fields = array_diff($differing, ['image']);
        if ($fields) {
            foreach ($fields as $field) {
                $badge->$field = $expected[$field];
            }
            // update_message() would recompute this; with notifications off there is none.
            $badge->nextcron = null;
            $badge->usermodified = $USER->id;
            $badge->save();
        }
        if (in_array('image', $differing, true)) {
            self::write_image($badge, $template);
            $DB->set_field(self::TABLE, 'imagehash', (string)$template['image']['sha256'], ['id' => $map->id]);
        }
        return true;
    }

    /**
     * @param badge $badge
     * @param array $expected
     * @param array $template
     * @param stdClass $map
     * @return string[] field names that differ, plus 'image' when the image is older
     */
    protected static function differing(badge $badge, array $expected, array $template, stdClass $map): array {
        $out = [];
        foreach ($expected as $field => $value) {
            $live = $badge->$field;
            if ($value === null ? !($live === null || $live === '' || (int)$live === 0) : (string)$live !== (string)$value) {
                $out[] = $field;
            }
        }
        if ((string)$map->imagehash !== (string)($template['image']['sha256'] ?? '')) {
            $out[] = 'image';
        }
        return $out;
    }

    /**
     * Process the stored template image into the badge's image. badges_process_badge_image()
     * deletes the file it is given, so it gets a temporary copy, never the stored original.
     *
     * @param badge $badge
     * @param array $template
     */
    protected static function write_image(badge $badge, array $template): void {
        self::require_gd();
        $file = get_file_storage()->get_file(\context_system::instance()->id, 'local_ltuse',
            self::FILEAREA, 0, '/', clean_filename((string)$template['image']['filename']));
        if (!$file) {
            throw new moodle_exception('error:recognitionnotapplied', 'local_ltuse');
        }
        $tmp = make_request_directory() . '/' . clean_filename($file->get_filename());
        $file->copy_content_to($tmp);
        badges_process_badge_image($badge, $tmp);
    }

    /**
     * badges_process_badge_image() does nothing at all without GD, which would leave a badge
     * with no image and no error. Fail loudly instead (R2).
     */
    protected static function require_gd(): void {
        global $CFG;
        if (empty($CFG->gdversion)) {
            throw new moodle_exception('error:nogd', 'local_ltuse');
        }
    }
}
