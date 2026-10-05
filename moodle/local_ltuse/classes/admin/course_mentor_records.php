<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Records and removes one-course and cohort mentors (spec 008, US5; research R10, data-model
 * sections 1 and 3).
 *
 * A course-mentor row names a course (ltct:<slug>), a mentor, and exactly one of a learner (a
 * one-course mentor: replaces that learner's default mentor in that course) or a cohort (the
 * mentors of a cohort: replace the default mentor for the learners that cohort enrols in that
 * course). Each is one row of local_ltuse_course_mentor; default mentors are never copied
 * there. Recording does no enrolling: course_mentor_sync::sync_course() does, straight after,
 * and does nothing while local_ltuse/coursementorsync is 0, so the table can be filled before
 * the sync is switched on.
 *
 * Inserts are get-or-create, and a duplicate-key error from a racing retry counts as success
 * (data-model section 3). A mentor must be in ltct:mentors when recorded; removing a record
 * never needs that.
 *
 * The external functions check local/ltuse:administer, then moodle/role:assign and
 * moodle/course:managegroups in each course's context. Nothing here checks a capability.
 */
class course_mentor_records {

    /**
     * Find every course and cohort the rows name, and ltct:mentors. Any missing is a
     * file-level refusal naming it.
     *
     * @param array $rows each with courseidnumber and, optionally, cohortidnumber
     * @return array ['refusal' => string, 'courses' => [idnumber => stdClass id, idnumber],
     *               'cohorts' => [idnumber => int id], 'mentorscohortid' => int]
     */
    public static function resolve(array $rows): array {
        global $DB;
        $courses = [];
        $cohorts = [];
        $missing = [];
        $mentors = (int)$DB->get_field('cohort', 'id', ['idnumber' => enrolment_rules::MENTORS_COHORT]);
        if (!$mentors) {
            $missing[] = enrolment_rules::MENTORS_COHORT;
        }
        foreach ($rows as $row) {
            $course = trim((string)($row['courseidnumber'] ?? ''));
            if (!array_key_exists($course, $courses) && !in_array($course, $missing, true)) {
                $record = $course === '' ? false : $DB->get_record('course', ['idnumber' => $course], 'id, idnumber');
                if ($record) {
                    $courses[$course] = $record;
                } else {
                    $missing[] = $course === '' ? '(no course)' : $course;
                }
            }
            $cohort = trim((string)($row['cohortidnumber'] ?? ''));
            if ($cohort !== '' && !array_key_exists($cohort, $cohorts) && !in_array($cohort, $missing, true)) {
                $id = (int)$DB->get_field('cohort', 'id', ['idnumber' => $cohort]);
                if ($id) {
                    $cohorts[$cohort] = $id;
                } else {
                    $missing[] = $cohort;
                }
            }
        }
        return [
            'refusal' => $missing ? get_string('admin:refusal:missing', 'local_ltuse', implode(', ', $missing)) : '',
            'courses' => $courses,
            'cohorts' => $cohorts,
            'mentorscohortid' => $mentors,
        ];
    }

    /**
     * Preview a whole course-mentors file. Changes nothing.
     *
     * @param array $rows each [row, courseidnumber, mentoremail, learneremail, cohortidnumber]
     * @param array $found resolve()'s result
     * @param bool $remove remove the records rather than add them
     * @param bool $showpeople
     * @return array ['refusal' => '', 'rows' => [[row, key (the mentor), outcome, reason, changes[]]]]
     */
    public static function preview(array $rows, array $found, bool $remove, bool $showpeople): array {
        $results = [];
        foreach ($rows as $row) {
            $state = self::classify($row, $found, $remove);
            $email = (string)($row['mentoremail'] ?? '');
            $what = trim((string)($row['learneremail'] ?? '')) !== '' ? 'onecourse' : 'cohort';
            $results[] = [
                'row' => (int)$row['row'],
                'key' => $showpeople ? trim($email) : masking::mask_email($email),
                'outcome' => $state['outcome'],
                'reason' => intake_service::reason($state['reason']),
                'changes' => $state['outcome'] === 'would_change'
                    ? [($remove ? 'remove' : 'record') . ':' . $what . ':' . trim((string)$row['courseidnumber'])] : [],
            ];
        }
        return ['refusal' => '', 'rows' => $results];
    }

    /**
     * Record or remove one row, if it is still what the preview said or already done, then
     * bring the course's course mentors into step.
     *
     * @param array $row
     * @param array $found resolve()'s result for this row
     * @param bool $remove
     * @param string $expectedoutcome
     * @return array [row, outcome, status: done|already_done|refused, reason]
     */
    public static function apply_row(array $row, array $found, bool $remove, string $expectedoutcome): array {
        global $DB, $USER;
        $state = self::classify($row, $found, $remove);
        $step = intake_rules::change_progress($expectedoutcome, $state['outcome']);
        if ($step === intake_rules::REFUSED) {
            $reason = $state['outcome'] === 'rejected' ? $state['reason'] : 'changed';
            return self::answer($row, $state['outcome'], 'refused', intake_service::reason($reason));
        }
        $status = 'already_done';
        if ($step === intake_rules::APPLY) {
            $status = 'done';
            if ($remove) {
                $DB->delete_records(course_mentor_sync::TABLE, $state['record']);
            } else {
                $now = time();
                $record = array_merge($state['record'], ['usermodified' => (int)$USER->id, 'timecreated' => $now,
                    'timemodified' => $now]);
                try {
                    $DB->insert_record(course_mentor_sync::TABLE, (object)$record);
                } catch (\dml_write_exception $e) {
                    // A retry racing its own first attempt: the unique index refused the twin.
                    if (!$DB->record_exists(course_mentor_sync::TABLE, $state['record'])) {
                        throw $e;
                    }
                    $status = 'already_done';
                }
            }
        }
        // Also on already_done: a first attempt whose answer was lost may have recorded the row
        // and stopped before the sync.
        course_mentor_sync::sync_course((int)$state['record']['courseid']);
        $note = course_mentor_sync::enabled() ? '' : get_string('admin:reason:coursementorsync_off', 'local_ltuse');
        return self::answer($row, $state['outcome'], $status, $note);
    }

    /**
     * Where one row stands now.
     *
     * @param array $row
     * @param array $found
     * @param bool $remove
     * @return array ['outcome' => would_change|unchanged|rejected, 'reason' => key (a note on a
     *               row that proceeds, or why it is rejected), 'record' => the table row's key
     *               fields, or null]
     */
    protected static function classify(array $row, array $found, bool $remove): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $state = ['outcome' => 'rejected', 'reason' => '', 'record' => null];
        $courseidnumber = trim((string)($row['courseidnumber'] ?? ''));
        $learneremail = trim((string)($row['learneremail'] ?? ''));
        $cohortidnumber = trim((string)($row['cohortidnumber'] ?? ''));
        $course = $found['courses'][$courseidnumber] ?? null;
        if (!$course) {
            return array_merge($state, ['reason' => 'facts']);
        }
        if (!course_mentor_sync::is_ltct_course($courseidnumber)) {
            return array_merge($state, ['reason' => 'course_not_ltct']);
        }
        if (($learneremail === '') === ($cohortidnumber === '')) {
            return array_merge($state, ['reason' => 'learner_or_cohort']);
        }

        $mentoremail = trim((string)($row['mentoremail'] ?? ''));
        if (!validate_email($mentoremail) || ($learneremail !== '' && !validate_email($learneremail))) {
            return array_merge($state, ['reason' => 'bad_email']);
        }
        $mentors = intake_service::match_accounts(intake_service::normalise_email($mentoremail));
        if (count($mentors) !== 1) {
            return array_merge($state, ['reason' => $mentors ? 'mentor_duplicate_accounts' : 'mentor_no_account']);
        }
        $mentorid = (int)reset($mentors)->id;

        $learnerid = 0;
        $cohortid = 0;
        if ($learneremail !== '') {
            $learners = intake_service::match_accounts(intake_service::normalise_email($learneremail));
            if (count($learners) !== 1) {
                return array_merge($state, ['reason' => $learners ? 'duplicate_accounts' : 'no_account']);
            }
            $learnerid = (int)reset($learners)->id;
            if ($learnerid === $mentorid) {
                return array_merge($state, ['reason' => 'own_mentor']);
            }
        } else {
            $cohortid = (int)($found['cohorts'][$cohortidnumber] ?? 0);
            if (!$cohortid) {
                return array_merge($state, ['reason' => 'facts']);
            }
        }
        if (!$remove && !cohort_is_member((int)$found['mentorscohortid'], $mentorid)) {
            return array_merge($state, ['reason' => 'not_a_mentor']);
        }

        $record = ['courseid' => (int)$course->id, 'mentorid' => $mentorid, 'learnerid' => $learnerid,
            'cohortid' => $cohortid];
        $exists = $DB->record_exists(course_mentor_sync::TABLE, $record);
        $changes = $remove ? $exists : !$exists;
        $reason = '';
        if ($changes && !$remove && $cohortid && !cohort_enrolment::find_instance($cohortid, (int)$course->id)) {
            // Allowed, and worth saying: the record waits until the cohort is enrolled there.
            $reason = 'cohort_not_enrolled';
        }
        return ['outcome' => $changes ? 'would_change' : 'unchanged', 'reason' => $reason, 'record' => $record];
    }

    /**
     * @param array $row
     * @param string $outcome
     * @param string $status
     * @param string $reason
     * @return array
     */
    protected static function answer(array $row, string $outcome, string $status, string $reason): array {
        return ['row' => (int)$row['row'], 'outcome' => $outcome, 'status' => $status, 'reason' => $reason];
    }
}
