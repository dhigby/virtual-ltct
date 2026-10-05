<?php
namespace local_ltuse\pathway;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\siteconfig\pathwaylevels;
use moodle_url;

/**
 * One pathway, or a list of them, for one learner (spec 006, R6; contracts/pages.md).
 *
 * The glue between the database and the pure builder: it reads the competency or role row,
 * the delivered courses catalogue::courses() lists with their full name and target level, the
 * learner's status in each (progress) and the four level labels (pathwaylevels::stored()),
 * and hands them to builder. The page, the Moodle app and spec 006's Assign page all read
 * through here, so none of them can lay a pathway out differently (R10).
 *
 * It checks no access: any userid is answered. Deciding who may see whose pathway is the
 * page's job (viewer::may_view(), R8). Read only, and it reads no level for the learner, since
 * none exists (FR-011).
 */
class view {

    /**
     * The four level labels, or null when any is not applied on this site. The pages then show
     * an error notice: a guessed label is never shown (data-model "Plugin config").
     *
     * @return array|null level id (1-4) => label
     */
    public static function levels(): ?array {
        $labels = [];
        foreach (builder::LEVELS as $level) {
            $label = pathwaylevels::stored($level);
            if ($label === null) {
                return null;
            }
            $labels[$level] = $label;
        }
        return $labels;
    }

    /**
     * One pathway for one learner, as builder lays it out.
     *
     * A competency key resolves while its competency is live, even before its first course is
     * published (all four rows then say "No course yet"), so a key catalogue::is_assignable()
     * accepted never turns into "does not exist" on the page. A role key resolves while the role
     * is live. Anything else, including a key that is not a key, is null.
     *
     * @param string $key a pathway key
     * @param int $userid the learner whose statuses are shown
     * @return array|null the builder context, or null for an unknown or retired key
     * @throws \moodle_exception pathway:nolevels when the level labels are not applied
     */
    public static function for_learner(string $key, int $userid): ?array {
        global $DB;
        $parsed = catalogue::parse_key($key);
        if ($parsed === null) {
            return null;
        }
        $levels = self::levels();
        if ($levels === null) {
            throw new \moodle_exception('pathway:nolevels', 'local_ltuse');
        }

        if ($parsed['kind'] === catalogue::KIND_COMPETENCY) {
            $competency = $DB->get_record('local_ltuse_competency', ['slug' => $parsed['id'], 'retired' => 0],
                'id, name, slug, url');
            if (!$competency) {
                return null;
            }
            $ids = catalogue::courses($key);
            $facts = self::course_facts($ids);
            return self::competency_view($competency, $levels, $ids, $facts,
                progress::for_courses($userid, $ids));
        }

        $role = $DB->get_record('local_ltuse_role_pathway', ['rolekey' => $parsed['id'], 'retired' => 0],
            'id, rolekey, name, description');
        if (!$role) {
            return null;
        }
        // The role's live competencies in declared order; each course is read, and its status
        // looked up, once for the whole role.
        $competencies = $DB->get_records_sql(
            "SELECT comp.id, comp.name, comp.slug, comp.url
               FROM {local_ltuse_role_pathway_comp} rpc
               JOIN {local_ltuse_competency} comp ON comp.id = rpc.competencyid
              WHERE rpc.roleid = :roleid AND comp.retired = 0 AND comp.slug <> ''
           ORDER BY rpc.sortorder, rpc.id", ['roleid' => $role->id]);
        $idsby = [];
        foreach ($competencies as $competency) {
            $idsby[$competency->id] = catalogue::courses(catalogue::competency_key($competency->slug));
        }
        $all = $idsby ? array_values(array_unique(array_merge(...array_values($idsby)))) : [];
        $facts = self::course_facts($all);
        $statuses = progress::for_courses($userid, $all);

        $views = [];
        foreach ($competencies as $competency) {
            $views[] = self::competency_view($competency, $levels, $idsby[$competency->id], $facts, $statuses);
        }
        return builder::role([
            'key' => catalogue::role_key($role->rolekey),
            'name' => $role->name,
            'description' => $role->description,
        ], $views);
    }

    /**
     * A short line per pathway for the list views: its title, "N of M courses completed", the
     * next course and whether it is done. Keys that do not resolve are left out.
     *
     * @param int $userid the learner
     * @param string[] $keys pathway keys, in the order to show them
     * @return array[] each key, title, kind, url, total, completed, done, progresstext,
     *                 nextcourse (a builder course entry, or null)
     * @throws \moodle_exception pathway:nolevels when the level labels are not applied
     */
    public static function summaries(int $userid, array $keys): array {
        $out = [];
        foreach ($keys as $key) {
            $context = self::for_learner((string)$key, $userid);
            if ($context === null) {
                continue;
            }
            $out[] = [
                'key' => $context['key'],
                'title' => $context['title'],
                'kind' => $context['kind'],
                'url' => self::url($context['key'], $userid)->out(false),
                'total' => $context['total'],
                'completed' => $context['completed'],
                'done' => $context['done'],
                'progresstext' => self::progress_text($context),
                'nextcourse' => self::next_course($context),
            ];
        }
        return $out;
    }

    /**
     * Every pathway's title, for browsing: no course or progress is read.
     *
     * @return array[] in catalogue::all() order, each key, kind, title and, for a competency,
     *                 its framework category (verbatim); for a role, category is ''
     */
    public static function browse(): array {
        global $DB;
        $competencies = $DB->get_records_select('local_ltuse_competency', "retired = 0 AND slug <> ''", [],
            'sortorder, id', 'id, slug, name, category');
        $byslug = [];
        foreach ($competencies as $competency) {
            $byslug[$competency->slug] = $competency;
        }
        $roles = $DB->get_records_menu('local_ltuse_role_pathway', ['retired' => 0], 'sortorder, id', 'rolekey, name');
        $out = [];
        foreach (catalogue::all() as $key) {
            $parsed = catalogue::parse_key($key);
            if ($parsed['kind'] === catalogue::KIND_ROLE) {
                if (isset($roles[$parsed['id']])) {
                    $out[] = ['key' => $key, 'kind' => $parsed['kind'], 'title' => $roles[$parsed['id']],
                        'category' => ''];
                }
            } else if (isset($byslug[$parsed['id']])) {
                $out[] = ['key' => $key, 'kind' => $parsed['kind'], 'title' => $byslug[$parsed['id']]->name,
                    'category' => (string)$byslug[$parsed['id']]->category];
            }
        }
        return $out;
    }

    /**
     * Adds what a template needs to say a context in words, to a builder context: each
     * course's status text, the "N of M courses completed" line, `nextcourse` (for a role,
     * across its competencies: next_course()) and, in `parts`, the
     * competency sections to draw (the pathway itself, or a role's competencies). Adds no
     * level for the learner; the level a course aims at stays its row's label.
     *
     * @param array $context a builder context
     * @return array the same context with the display fields added
     */
    public static function display(array $context): array {
        $context['progresstext'] = self::progress_text($context);
        $context['nextcourse'] = self::next_course($context);
        $context['isrole'] = $context['kind'] === catalogue::KIND_ROLE;
        if ($context['isrole']) {
            $context['hasdescription'] = trim((string)$context['description']) !== '';
            // One "Next" per role: the builder marks each competency's own next course, so keep
            // only the role's, wherever it appears (a course may serve several competencies).
            $nextid = $context['nextcourse'] ? (int)$context['nextcourse']['courseid'] : 0;
            foreach ($context['competencies'] as $c => $competency) {
                foreach ($competency['levels'] as $l => $row) {
                    foreach ($row['courses'] as $i => $entry) {
                        $context['competencies'][$c]['levels'][$l]['courses'][$i]['next'] =
                            (int)$entry['courseid'] === $nextid;
                    }
                }
            }
            $context['competencies'] = array_map([self::class, 'display_levels'], $context['competencies']);
            $parts = [];
            foreach ($context['competencies'] as $competency) {
                $parts[] = $competency + ['showtitle' => true];
            }
            $context['parts'] = $parts;
        } else {
            $context = self::display_levels($context);
            $context['parts'] = [[
                'key' => $context['key'],
                'title' => $context['title'],
                'levels' => $context['levels'],
                'showtitle' => false,
            ]];
        }
        return $context;
    }

    /**
     * The Pathways page for a key, and for another learner when the userid is not the viewer.
     *
     * @param string $key
     * @param int $userid
     * @return moodle_url
     */
    public static function url(string $key, int $userid): moodle_url {
        global $USER;
        $params = ['key' => $key];
        if ($userid > 0 && $userid !== (int)($USER->id ?? 0)) {
            $params['userid'] = $userid;
        }
        return new moodle_url('/local/ltuse/pathways.php', $params);
    }

    /**
     * The next course of a context: the competency pathway's own, or for a role the first
     * course not completed across its competencies, in level then full-name order (R6).
     *
     * @param array $context a builder context
     * @return array|null a builder course entry, with its statustext
     */
    public static function next_course(array $context): ?array {
        if ($context['kind'] !== catalogue::KIND_ROLE) {
            return empty($context['nextcourse']) ? null : self::display_course($context['nextcourse']);
        }
        $candidates = [];
        foreach ($context['competencies'] as $competency) {
            foreach ($competency['levels'] as $row) {
                foreach ($row['courses'] as $entry) {
                    if ($entry['status'] !== builder::COMPLETED && !isset($candidates[$entry['courseid']])) {
                        $candidates[$entry['courseid']] = [$row['level'], $entry];
                    }
                }
            }
        }
        if (!$candidates) {
            return null;
        }
        uasort($candidates, function(array $a, array $b): int {
            return ($a[0] <=> $b[0]) ?: (strnatcasecmp($a[1]['fullname'], $b[1]['fullname'])
                ?: $a[1]['courseid'] <=> $b[1]['courseid']);
        });
        $first = reset($candidates)[1];
        $first['next'] = true;
        return self::display_course($first);
    }

    /**
     * "N of M courses completed", counting a course once (pathway:roletotal).
     *
     * @param array $context a builder context
     * @return string
     */
    protected static function progress_text(array $context): string {
        return get_string('pathway:roletotal', 'local_ltuse',
            (object)['done' => (int)$context['completed'], 'total' => (int)$context['total']]);
    }

    /**
     * @param array $context a competency context
     * @return array the same, with each course's status text and "Aims at <its row's label>"
     */
    protected static function display_levels(array $context): array {
        foreach ($context['levels'] as $i => $row) {
            $aimsat = get_string('pathway:aimsat', 'local_ltuse', $row['label']);
            $context['levels'][$i]['courses'] = array_map(function(array $entry) use ($aimsat): array {
                return self::display_course($entry) + ['aimsattext' => $aimsat];
            }, $row['courses']);
            $context['levels'][$i]['hascompetencyurl'] = !empty($row['competencyurl']);
        }
        return $context;
    }

    /**
     * @param array $entry a builder course entry
     * @return array the same, with statustext and the completed flag a template can test
     */
    protected static function display_course(array $entry): array {
        $entry['statustext'] = get_string('pathway:' . $entry['status'], 'local_ltuse');
        $entry['iscompleted'] = $entry['status'] === builder::COMPLETED;
        return $entry;
    }

    /**
     * One competency's builder context from facts already read.
     *
     * @param \stdClass $competency name, slug, url
     * @param array $levels level id => label
     * @param int[] $ids its course ids, from catalogue::courses()
     * @param array $facts course id => ['fullname', 'level']
     * @param array $statuses course id => status
     * @return array
     */
    protected static function competency_view(\stdClass $competency, array $levels, array $ids,
            array $facts, array $statuses): array {
        $courses = [];
        foreach ($ids as $id) {
            if (!isset($facts[$id])) {
                continue;
            }
            $courses[] = [
                'courseid' => $id,
                'fullname' => $facts[$id]['fullname'],
                'url' => (new moodle_url('/course/view.php', ['id' => $id]))->out(false),
                'level' => $facts[$id]['level'],
            ];
        }
        return builder::competency([
            'key' => catalogue::competency_key($competency->slug),
            'name' => $competency->name,
            'url' => (string)$competency->url,
        ], $levels, $courses, $statuses);
    }

    /**
     * Each course's full name, through format_string() in its own context as the Mentoring
     * page shows it, and the level it aims at.
     *
     * @param int[] $ids
     * @return array course id => ['fullname' => string, 'level' => int]
     */
    protected static function course_facts(array $ids): array {
        global $DB;
        if (!$ids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'pvc');
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.fullname, cp.targetlevel
               FROM {course} c
               JOIN {local_ltuse_course_pathway} cp ON cp.courseid = c.id
              WHERE c.id {$insql}", $params);
        $facts = [];
        foreach ($rows as $row) {
            $facts[(int)$row->id] = [
                'fullname' => format_string($row->fullname, true,
                    ['context' => \context_course::instance((int)$row->id)]),
                'level' => (int)$row->targetlevel,
            ];
        }
        return $facts;
    }
}
