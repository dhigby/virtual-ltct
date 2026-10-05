<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_ltuse\event\pathway_courses_changed;
use local_ltuse\pathway\catalogue;
use local_ltuse\util;
use moodle_exception;

/**
 * Record a published course's two pathway facts, and announce what it joined or left
 * (spec 006, contracts/publish.md, R2).
 *
 * The publisher sends whether this publish is a delivery (course_stage.py at stage 8) and the
 * course's target level (the leading digit of its target_outcome_level, 0 when it has none).
 * Which pathways that puts the course on is decided by \local_ltuse\pathway\catalogue, the one
 * place the membership rule lives, from this row, the course's visibility and its
 * local_ltuse_course_comp rows; so the publisher calls this straight after
 * set_course_competencies.
 *
 * The row keeps `pathwaykeys`, the set last announced. The difference between it and the set
 * now is fired as one pathway_courses_changed per key, after the commit, so a course hidden or
 * shown in Moodle since its last publish is announced on its next one.
 *
 * Idempotent: the same values, with no change to the course's competencies or visibility,
 * write nothing and fire nothing. Reads and writes no user data, and never enrols anyone.
 */
class set_course_pathway extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'delivery' => new external_value(PARAM_INT,
                '1 when this publish is a delivery (course_stage.py stage 8), else 0'),
            'targetlevel' => new external_value(PARAM_INT,
                'The course target_outcome_level, 1-4; 0 when it declares none'),
        ]);
    }

    public static function execute(int $courseid, int $delivery, int $targetlevel): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'delivery' => $delivery,
            'targetlevel' => $targetlevel,
        ]);
        $delivery = (int)$params['delivery'];
        $targetlevel = (int)$params['targetlevel'];

        $course = $DB->get_record('course', ['id' => $params['courseid']], 'id, idnumber',
            MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/ltuse:publish', $context);

        $idnumber = (string)$course->idnumber;
        if (strpos($idnumber, util::IDNUMBER_PREFIX) !== 0) {
            throw new moodle_exception('error:notltctcourse', 'local_ltuse', '', $idnumber);
        }
        if (strpos($idnumber, ':', strlen(util::IDNUMBER_PREFIX)) !== false) {
            throw new invalid_parameter_exception(
                "'{$idnumber}' names a module, not a course");
        }
        if ($delivery !== 0 && $delivery !== 1) {
            throw new invalid_parameter_exception("delivery must be 0 or 1, not {$delivery}");
        }
        if ($targetlevel < 0 || $targetlevel > 4) {
            throw new invalid_parameter_exception(
                "targetlevel must be 0-4, not {$targetlevel}");
        }
        if ($delivery === 1 && $targetlevel === 0) {
            throw new invalid_parameter_exception(
                'a delivered course must declare a target_outcome_level');
        }

        $row = $DB->get_record('local_ltuse_course_pathway', ['courseid' => $course->id]);
        $before = $row ? self::decode_keys($row->pathwaykeys) : [];
        $now = time();

        $transaction = $DB->start_delegated_transaction();
        if (!$row) {
            $row = (object)[
                'courseid' => $course->id,
                'delivery' => $delivery,
                'targetlevel' => $targetlevel,
                'pathwaykeys' => json_encode([]),
                'timemodified' => $now,
            ];
            $row->id = $DB->insert_record('local_ltuse_course_pathway', $row);
        } else if ((int)$row->delivery !== $delivery || (int)$row->targetlevel !== $targetlevel) {
            $DB->update_record('local_ltuse_course_pathway', (object)[
                'id' => $row->id,
                'delivery' => $delivery,
                'targetlevel' => $targetlevel,
                'timemodified' => $now,
            ]);
        }
        // Read inside the transaction, so the row just written decides it.
        $after = catalogue::pathways_for_course((int)$course->id);
        if (self::decode_keys($row->pathwaykeys) !== $after) {
            $DB->update_record('local_ltuse_course_pathway', (object)[
                'id' => $row->id,
                'pathwaykeys' => json_encode($after),
                'timemodified' => $now,
            ]);
        }
        $transaction->allow_commit();

        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));
        $courseidint = (int)$course->id;
        foreach ($added as $key) {
            self::announce($key, [$courseidint], []);
        }
        foreach ($removed as $key) {
            self::announce($key, [], [$courseidint]);
        }

        // Read back from the tables after the commit.
        $stored = $DB->get_record('local_ltuse_course_pathway', ['courseid' => $course->id],
            'id, delivery, targetlevel', MUST_EXIST);
        $visible = (int)$DB->get_field('course', 'visible', ['id' => $course->id], MUST_EXIST);
        return [
            'delivery' => (int)$stored->delivery,
            'targetlevel' => (int)$stored->targetlevel,
            'visible' => $visible,
            'pathways' => self::competency_pathways(catalogue::pathways_for_course($courseidint)),
            'added' => $added,
            'removed' => $removed,
        ];
    }

    /**
     * The keys a row last announced, as a list of strings.
     *
     * @param string|null $json
     * @return string[]
     */
    protected static function decode_keys(?string $json): array {
        $keys = $json === null || $json === '' ? [] : json_decode($json, true);
        return is_array($keys) ? array_values(array_map('strval', $keys)) : [];
    }

    /**
     * The competency pathways among $keys, each with its competency's name. Role keys are
     * not listed (contracts/publish.md).
     *
     * @param string[] $keys
     * @return array[] [['key' => string, 'competency' => string]]
     */
    protected static function competency_pathways(array $keys): array {
        global $DB;
        $slugs = [];
        foreach ($keys as $key) {
            $parsed = catalogue::parse_key($key);
            if ($parsed !== null && $parsed['kind'] === catalogue::KIND_COMPETENCY) {
                $slugs[$key] = $parsed['id'];
            }
        }
        if (!$slugs) {
            return [];
        }
        $names = [];
        foreach ($DB->get_records_list('local_ltuse_competency', 'slug', array_values($slugs),
                '', 'id, slug, name, retired') as $competency) {
            // A retired competency is on no pathway, so a live row is the one named.
            if (!isset($names[$competency->slug]) || !(int)$competency->retired) {
                $names[$competency->slug] = $competency->name;
            }
        }
        $out = [];
        foreach ($slugs as $key => $slug) {
            $out[] = ['key' => $key, 'competency' => $names[$slug] ?? ''];
        }
        return $out;
    }

    /**
     * Fire pathway_courses_changed for one key. The event sets its own system context.
     *
     * @param string $key
     * @param int[] $added
     * @param int[] $removed
     * @return void
     */
    protected static function announce(string $key, array $added, array $removed): void {
        pathway_courses_changed::create([
            'other' => [
                'pathwaykey' => $key,
                'added' => $added,
                'removed' => $removed,
            ],
        ])->trigger();
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'delivery' => new external_value(PARAM_INT, 'Delivery, as stored'),
            'targetlevel' => new external_value(PARAM_INT, 'Target level, as stored'),
            'visible' => new external_value(PARAM_INT,
                'The course visibility; a hidden course is on no pathway'),
            'pathways' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_RAW, 'Competency pathway key'),
                    'competency' => new external_value(PARAM_TEXT, 'Its competency name'),
                ]), 'Each competency pathway the course is on now'),
            'added' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Pathway key the course joined on this call')),
            'removed' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Pathway key the course left on this call')),
        ]);
    }
}
