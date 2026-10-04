<?php
namespace local_ltuse\pathway;

defined('MOODLE_INTERNAL') || die();

/**
 * Lays out one pathway for one learner (spec 006, R6; FR-003, FR-004, FR-008, FR-011).
 *
 * PURE: every fact is handed in, so tests/pathway_harness.php checks it with a bare PHP CLI
 * and the page and the Moodle app render the same context (contracts/pages.md, "Templates").
 * The caller reads the courses (catalogue), the level labels (config pathwaylevel1-4), the
 * competency url and each course's status (progress) and passes them here.
 *
 * The context never holds a level for the learner, and has no field a template could show
 * one from: a level appears only as a row heading, the level a course aims at (R13).
 */
class builder {

    /** Course statuses, as mentoring::progress_status() names them (R7). */
    const COMPLETED = 'completed';
    const IN_PROGRESS = 'inprogress';
    const NOT_STARTED = 'notstarted';

    /** The four level rows a competency pathway shows, in order (FR-003). */
    const LEVELS = [1, 2, 3, 4];

    /**
     * A competency pathway's context.
     *
     * @param array $competency 'key' (competency:<slug>), 'name' (verbatim), 'url' (its page)
     * @param array $levels level id (1-4) => label, from pathwaylevel<n>; all four required
     * @param array[] $courses each 'courseid', 'fullname', 'url' (the course page) and 'level'
     *     (1-4, the level it aims at). A course with any other level is left out; a repeated
     *     courseid is listed once.
     * @param array $statuses courseid => completed|inprogress|notstarted for the learner
     *     viewed. A missing or unknown status is notstarted: nothing is shown as completed
     *     without a completion record.
     * @return array the context of contracts/pages.md "Templates"
     */
    public static function competency(array $competency, array $levels, array $courses,
            array $statuses): array {
        $labels = self::labels($levels);

        $bylevel = array_fill_keys(self::LEVELS, []);
        $seen = [];
        foreach ($courses as $course) {
            $courseid = (int)$course['courseid'];
            $level = (int)($course['level'] ?? 0);
            if (isset($seen[$courseid]) || !isset($bylevel[$level])) {
                continue;
            }
            $seen[$courseid] = true;
            $bylevel[$level][] = [
                'courseid' => $courseid,
                'fullname' => (string)$course['fullname'],
                'url' => (string)$course['url'],
                'status' => self::status($statuses[$courseid] ?? null),
                'next' => false,
            ];
        }

        $rows = [];
        $total = 0;
        $completed = 0;
        $next = null;
        foreach (self::LEVELS as $level) {
            $list = self::by_name($bylevel[$level]);
            foreach ($list as $i => $entry) {
                $total++;
                if ($entry['status'] === self::COMPLETED) {
                    $completed++;
                } else if ($next === null) {
                    $list[$i]['next'] = true;
                    $next = $list[$i];
                }
            }
            $nocourse = !$list;
            $rows[] = [
                'level' => $level,
                'label' => $labels[$level],
                'courses' => $list,
                'nocourseyet' => $nocourse,
                'competencyurl' => $nocourse ? (string)($competency['url'] ?? '') : null,
            ];
        }

        return [
            'key' => (string)$competency['key'],
            'title' => (string)$competency['name'],
            'kind' => 'competency',
            'levels' => $rows,
            'nextcourse' => $next,
            'done' => $total > 0 && $completed === $total,
            'total' => $total,
            'completed' => $completed,
        ];
    }

    /**
     * A role pathway's context: its competencies in declared order, each a competency
     * context, and totals over distinct courses, so a course serving two of the role's
     * competencies counts once (R6).
     *
     * @param array $role 'key' (role:<key>), 'name', 'description'
     * @param array[] $competencies competency() contexts, in declared order, retired left out
     * @return array
     */
    public static function role(array $role, array $competencies): array {
        $status = [];
        foreach ($competencies as $view) {
            foreach ($view['levels'] as $row) {
                foreach ($row['courses'] as $entry) {
                    $status[$entry['courseid']] = $entry['status'];
                }
            }
        }
        $total = count($status);
        $completed = count(array_filter($status, function(string $s): bool {
            return $s === self::COMPLETED;
        }));
        return [
            'key' => (string)$role['key'],
            'title' => (string)$role['name'],
            'description' => (string)($role['description'] ?? ''),
            'kind' => 'role',
            'competencies' => array_values($competencies),
            'done' => $total > 0 && $completed === $total,
            'total' => $total,
            'completed' => $completed,
        ];
    }

    /**
     * @param array $levels
     * @return array level id => label, for exactly the four levels
     * @throws \InvalidArgumentException when a label is missing or empty
     */
    private static function labels(array $levels): array {
        $out = [];
        foreach (self::LEVELS as $level) {
            $label = trim((string)($levels[$level] ?? ''));
            if ($label === '') {
                throw new \InvalidArgumentException("No label for pathway level $level.");
            }
            $out[$level] = $label;
        }
        return $out;
    }

    /**
     * @param mixed $status
     * @return string one of the three statuses; anything else is notstarted
     */
    private static function status($status): string {
        return in_array($status, [self::COMPLETED, self::IN_PROGRESS], true) ? $status : self::NOT_STARTED;
    }

    /**
     * @param array[] $entries
     * @return array[] by full name, case-insensitively and naturally, then by course id
     */
    private static function by_name(array $entries): array {
        usort($entries, function(array $a, array $b): int {
            return strnatcasecmp($a['fullname'], $b['fullname']) ?: $a['courseid'] <=> $b['courseid'];
        });
        return $entries;
    }
}
