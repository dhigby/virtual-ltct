<?php
namespace local_ltuse\recognition;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\siteconfig\certtemplate;
use local_ltuse\util;
use moodle_exception;
use stdClass;

/**
 * A delivered course's certificate activity (spec 013, R6-R9, R14).
 *
 * One mod_customcert activity per delivered course, created on its first delivery publish
 * through util::upsert_module() (so add_moduleinfo(), as for every other module the publisher
 * makes), with course-module idnumber `ltct:<slug>:certificate`. It is never created on a
 * pilot publish: a pilot gets no certificate.
 *
 *   verifyany     1, so anyone can check a code without logging in (R9), together with the
 *                 site setting customcert/verifyallcertificates
 *   availability  availability_json(): unlocked by the course completion record, which a
 *                 republish never wipes, so adding a lesson never re-locks a learner who has
 *                 already completed the course (R8)
 *   completion    0, never a course completion criterion, or the course could never complete;
 *                 set_course_completion leaves this idnumber out of its wanted set
 *   section       the course's last lesson section, never the hidden Retired section
 *   pages         copied from the site template whenever they differ (certtemplate::copy_into())
 *
 * NEVER DELETED. Deleting the activity deletes every issued certificate code (R14). The
 * publisher never even offers it for hiding: its idnumber is in every delivery payload.
 */
class certificate {

    /**
     * The activity's availability: the course is completed, and the condition is shown while
     * locked, so the learner sees "Not available unless: You completed this course" (US2-2).
     *
     * @return string the JSON core stores in course_modules.availability
     */
    public static function availability_json(): string {
        return json_encode(['op' => '&', 'c' => [['type' => 'coursecompleted', 'id' => '1']], 'showc' => [true]]);
    }

    /**
     * Create or update the course's certificate activity, then bring its pages to the site template.
     *
     * @param stdClass $course
     * @param string $idnumber ltct:<slug>:certificate
     * @return string created, updated or unchanged
     * @throws moodle_exception error:recognitionnotapplied, error:availabilityoff, error:modulemissing
     */
    public static function sync(stdClass $course, string $idnumber): string {
        global $DB;
        $fields = self::fields(self::require_ready());
        $cm = util::cm_by_idnumber((int)$course->id, $idnumber);
        $outcome = 'unchanged';
        if ($cm === null) {
            util::upsert_module($course, 'customcert', $idnumber, self::last_lesson_section($course), $fields);
            $cm = util::cm_by_idnumber((int)$course->id, $idnumber);
            $outcome = 'created';
        } else if ($cm->modname !== 'customcert') {
            throw new moodle_exception('Module ' . $idnumber . ' exists as a ' . $cm->modname
                . ', not a certificate. Rename its idnumber in Moodle and republish.');
        } else {
            // Hidden, or sitting in the Retired section (moved there by hand, or hidden by an
            // older publisher), the certificate is out of a learner's reach: bring it back
            // even when every setting already matches. upsert_module() makes it visible.
            $section = (int)$DB->get_field('course_sections', 'section', ['id' => $cm->section]);
            $retired = util::retired_section($course);
            $inretired = $retired && (int)$retired->section === $section;
            if ($inretired || empty($cm->visible) || self::differs($cm, $fields)) {
                $target = $inretired ? self::last_lesson_section($course) : $section;
                util::upsert_module($course, 'customcert', $idnumber, $target, $fields);
                $outcome = 'updated';
            }
        }

        $templateid = (int)$DB->get_field('customcert', 'templateid', ['id' => $cm->instance], MUST_EXIST);
        if (certtemplate::copy_into($templateid) && $outcome === 'unchanged') {
            $outcome = 'updated';
        }
        return $outcome;
    }

    /**
     * The course's last lesson section: the highest numbered ordinary section, never the
     * hidden Retired section the publisher moves dropped modules into (util::RETIRED_SECTION_NAME),
     * and never a delegated section.
     *
     * @param stdClass $course
     * @return int the section number
     */
    protected static function last_lesson_section(stdClass $course): int {
        $retired = util::retired_section($course);
        $last = 0;
        foreach (get_fast_modinfo($course)->get_section_info_all() as $section) {
            if (empty($section->component) && (!$retired || $section->id != $retired->id)) {
                $last = max($last, (int)$section->section);
            }
        }
        return $last;
    }

    /**
     * Refuse unless a certificate can be made right. The web service calls this before it
     * writes the badge, so a refusal writes nothing at all.
     *
     * @return array the stored certificate declaration
     * @throws moodle_exception error:recognitionnotapplied, error:availabilityoff, error:modulemissing
     */
    public static function require_ready(): array {
        global $CFG;
        $stored = certtemplate::plugin_installed() ? certtemplate::stored() : null;
        if ($stored === null || count(certtemplate::find_site_templates((string)$stored['name'])) !== 1) {
            throw new moodle_exception('error:recognitionnotapplied', 'local_ltuse');
        }
        // add_moduleinfo() drops the availability without a word while it is off, and the
        // certificate would then be open to everyone before they complete. Refuse instead.
        if (empty($CFG->enableavailability)) {
            throw new moodle_exception('error:availabilityoff', 'local_ltuse');
        }
        // Without the condition plugin the availability JSON names a type nobody evaluates.
        if (\core_plugin_manager::instance()->get_plugin_info('availability_coursecompleted') === null) {
            throw new moodle_exception('error:modulemissing', 'local_ltuse', '', 'availability_coursecompleted');
        }
        return $stored;
    }

    /**
     * The activity's settings, as moduleinfo fields.
     *
     * @param array $stored the stored certificate declaration
     * @return array
     */
    protected static function fields(array $stored): array {
        return [
            'name' => (string)$stored['activity_name'],
            // update_moduleinfo() takes the intro from introeditor and ignores intro, as
            // create_page and create_quiz also allow for.
            'introeditor' => ['text' => self::intro($stored), 'format' => FORMAT_HTML, 'itemid' => 0],
            'showdescription' => 1,                 // beside the restriction on the course page
            'visible' => 1,                         // on update too: a hidden certificate is unreachable
            'visibleoncoursepage' => 1,
            'verifyany' => 1,
            'requiredtime' => 0,
            'deliveryoption' => \mod_customcert\service\pdf_generation_service::DELIVERY_OPTION_INLINE,
            'usecustomfilename' => 0,
            'customfilenamepattern' => '',
            'emailstudents' => 0,
            'emailteachers' => 0,
            'emailothers' => '',
            'issueautomatically' => 0,
            'protection_print' => 0,
            'protection_modify' => 0,
            'protection_copy' => 0,
            'language' => '',
            'completion' => COMPLETION_TRACKING_NONE,
            'availabilityconditionsjson' => self::availability_json(),
        ];
    }

    /**
     * @param array $stored
     * @return string the intro as stored HTML
     */
    protected static function intro(array $stored): string {
        return '<p>' . s((string)$stored['intro']) . '</p>';
    }

    /**
     * The customcert columns fields() sets, as they appear in mod_customcert v5.2.9's
     * db/install.xml (the version moodle/site/site.yaml pins). The protection_* flags are
     * not columns: customcert_update_instance() folds them into `protection`.
     */
    const INT_COLUMNS = ['requiredtime', 'verifyany', 'usecustomfilename', 'emailstudents',
        'emailteachers', 'issueautomatically'];
    /** Nullable char and text columns; a null compares as ''. */
    const TEXT_COLUMNS = ['deliveryoption', 'customfilenamepattern', 'emailothers', 'language'];
    /** The course_modules columns update_moduleinfo() rewrites from fields(). */
    const CM_INT_COLUMNS = ['showdescription', 'visibleoncoursepage'];

    /**
     * Whether the live activity differs from its settings, so a republish writes nothing
     * (and bumps no revision) when it already matches.
     *
     * @param stdClass $cm the course_modules record
     * @param array $fields
     * @return bool
     */
    protected static function differs(stdClass $cm, array $fields): bool {
        global $DB;
        $instance = $DB->get_record('customcert', ['id' => $cm->instance], '*', MUST_EXIST);
        // The plugin's own encoding, the one customcert_update_instance() stores.
        $protection = \mod_customcert\service\form_service::set_protection((object)$fields);
        return self::differences($instance, $cm, $fields, $protection) !== [];
    }

    /**
     * Every setting fields() writes that the live activity does not match, so a hand change to
     * any of them (requiredtime, the protection flags, the email settings, the delivery option)
     * is put back on the next publish. Completion is not compared: update_moduleinfo() writes
     * it only with completionunlocked, which util::upsert_module() sends only for a rule.
     *
     * @param stdClass $instance the customcert record
     * @param stdClass $cm the course_modules record
     * @param array $fields
     * @param string $protection the protection column fields() encodes to
     * @return string[] the names that differ, empty when the activity matches
     */
    public static function differences(stdClass $instance, stdClass $cm, array $fields,
                                       string $protection): array {
        $differ = [];
        if ((string)$instance->name !== $fields['name']) {
            $differ[] = 'name';
        }
        if ((string)$instance->intro !== $fields['introeditor']['text']) {
            $differ[] = 'intro';
        }
        if ((int)$instance->introformat !== (int)$fields['introeditor']['format']) {
            $differ[] = 'introformat';
        }
        foreach (self::INT_COLUMNS as $column) {
            if ((int)$instance->$column !== (int)$fields[$column]) {
                $differ[] = $column;
            }
        }
        foreach (self::TEXT_COLUMNS as $column) {
            if ((string)$instance->$column !== (string)$fields[$column]) {
                $differ[] = $column;
            }
        }
        if ((string)$instance->protection !== $protection) {
            $differ[] = 'protection';
        }
        foreach (self::CM_INT_COLUMNS as $column) {
            if ((int)$cm->$column !== (int)$fields[$column]) {
                $differ[] = $column;
            }
        }
        if ((string)$cm->availability !== $fields['availabilityconditionsjson']) {
            $differ[] = 'availability';
        }
        return $differ;
    }
}
