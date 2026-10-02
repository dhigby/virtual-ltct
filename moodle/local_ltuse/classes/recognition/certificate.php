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
 *   section       the course's last section, when the activity is created
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
            $section = (int)$DB->get_field('course_sections', 'MAX(section)', ['course' => $course->id]);
            util::upsert_module($course, 'customcert', $idnumber, $section, $fields);
            $cm = util::cm_by_idnumber((int)$course->id, $idnumber);
            $outcome = 'created';
        } else if ($cm->modname !== 'customcert') {
            throw new moodle_exception('Module ' . $idnumber . ' exists as a ' . $cm->modname
                . ', not a certificate. Rename its idnumber in Moodle and republish.');
        } else if (self::differs($cm, $fields)) {
            $section = (int)$DB->get_field('course_sections', 'section', ['id' => $cm->section]);
            util::upsert_module($course, 'customcert', $idnumber, $section, $fields);
            $outcome = 'updated';
        }

        $templateid = (int)$DB->get_field('customcert', 'templateid', ['id' => $cm->instance], MUST_EXIST);
        if (certtemplate::copy_into($templateid) && $outcome === 'unchanged') {
            $outcome = 'updated';
        }
        return $outcome;
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
     * Whether the live activity differs from its settings, so a republish writes nothing
     * (and bumps no revision) when it already matches.
     *
     * @param stdClass $cm the course_modules record
     * @param array $fields
     * @return bool
     */
    protected static function differs(stdClass $cm, array $fields): bool {
        global $DB;
        $instance = $DB->get_record('customcert', ['id' => $cm->instance], 'name, intro, verifyany', MUST_EXIST);
        return $instance->name !== $fields['name'] || $instance->intro !== $fields['introeditor']['text']
            || (int)$instance->verifyany !== 1 || (string)$cm->availability !== $fields['availabilityconditionsjson'];
    }
}
