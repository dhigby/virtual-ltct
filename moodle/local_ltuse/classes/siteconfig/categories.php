<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

use core_course_category;

/**
 * Checks and applies the declared course categories: the shared ones (published, pilots, the
 * organisations parent) and one per partner organisation (spec 002, FR-002, FR-003, R6).
 *
 * The payload's `categories` array arrives already expanded by scripts/site_config.py, parents
 * first:
 *
 *   categories  [{idnumber, name, parent_idnumber}]   parent_idnumber null means top level
 *
 * IDENTITY AND ADOPTION. A category is found by `idnumber`. Failing that, it looks for a single
 * category with the declared name, under the declared parent, with an empty idnumber: the
 * "LTC Pilots" and "LTC Published" categories made by hand before this spec. Drift reports that
 * as `missing` with the message "adoptable"; apply sets its idnumber, so its id (and every
 * publish_moodle.py --category argument) is unchanged, and reports `[changed] ... adopted`.
 * More than one candidate, or more than one category already carrying the idnumber, is a
 * blocking `ambiguous` naming the candidates' ids: a human decides which is ours.
 *
 * Only `name` and the parent are compared. Visibility and permissions stay at Moodle's defaults
 * (R6): a new category inherits its parent's visibility, as core_course_category::create() does
 * when no `visible` is passed. Nothing is ever deleted (FR-004); an undeclared `ltct:`
 * category is drift's to report as `extra`.
 *
 * Within one run a parent may be created or adopted before its children are checked, so the
 * live id each declared idnumber resolves to is remembered as items are checked. That is why
 * check_all() and the applier take the payload in order.
 *
 * APIs, confirmed in the 5.2.3+ instance's public/course/classes/category.php: create($data),
 * get($id, MUST_EXIST, true) and ->update($data). Neither verifies access control, which the
 * CLI does not need; update() changes the parent through change_parent_raw() and purges the
 * category caches. The raw reads are course_categories by `idnumber`, and by `parent` and `name`
 * for adoption, listed in the plugin README (constitution XI). No table is written directly.
 */
class categories {

    /** More than one category could be ours. Blocks apply. */
    const RESULT_AMBIGUOUS = 'ambiguous';

    /** Report kind for a category apply gave its idnumber. */
    const KIND_ADOPTED = 'adopted';

    /** The message on a `missing` category that apply will adopt rather than create. */
    const ADOPTABLE = 'adoptable';

    /** Item type in results. */
    const TYPE = 'category';

    /**
     * The live id each declared idnumber resolved to when last checked: a real category id, an
     * adoption candidate's id, or null when it does not exist yet and apply will create it.
     *
     * @var array<string, int|null>
     */
    protected $resolved = [];

    /**
     * The adoption candidate found for each idnumber at its last check.
     *
     * @var array<string, int>
     */
    protected $adoptable = [];

    /**
     * The report subject for a category.
     *
     * @param string $idnumber
     * @return string `category:<idnumber>`
     */
    public static function subject(string $idnumber): string {
        return 'category:' . $idnumber;
    }

    /**
     * Check every declared category, parents first. Reads only.
     *
     * @param array[] $categories the payload's `categories` array
     * @return array[] item results, in declaration order
     */
    public function check_all(array $categories): array {
        $items = [];
        foreach ($categories as $category) {
            $items[] = $this->check((array)$category);
        }
        return $items;
    }

    /**
     * Compare one declared category with the live one. Reads only.
     *
     * @param array $category {idnumber, name, parent_idnumber}
     * @return array item result, the inspector's shape
     */
    public function check(array $category): array {
        $idnumber = (string)($category['idnumber'] ?? '');
        $name = (string)($category['name'] ?? '');
        $parentidnumber = isset($category['parent_idnumber']) ? (string)$category['parent_idnumber'] : '';
        $subject = self::subject($idnumber);
        unset($this->adoptable[$idnumber]);

        if ($idnumber === '' || trim($name) === '') {
            return self::result($subject, inspector::RESULT_UNKNOWN, null, null,
                'a category needs an idnumber and a name; re-render the payload', true);
        }
        $declared = self::describe($name, $parentidnumber === '' ? 'top level' : $parentidnumber);
        $parentid = $this->parent_id($parentidnumber);

        $records = $this->find($idnumber);
        if (count($records) > 1) {
            $this->resolved[$idnumber] = null;
            return self::result($subject, self::RESULT_AMBIGUOUS, $declared, null,
                'more than one category has this idnumber (ids ' . implode(', ', array_keys($records))
                    . '); clear it on all but ours by hand', true);
        }

        if ($records) {
            $record = reset($records);
            $this->resolved[$idnumber] = (int)$record->id;
            $live = self::describe((string)$record->name, $this->parent_text((int)$record->parent));
            $same = ((string)$record->name === $name) && ($parentid !== null)
                && ((int)$record->parent === $parentid);
            $message = ($parentid === null) ? "its parent {$parentidnumber} does not exist yet" : '';
            return self::result($subject, $same ? inspector::RESULT_OK : inspector::RESULT_CHANGED,
                $declared, $live, $same ? '' : $message);
        }

        // Not found by idnumber. A category under a parent that does not exist yet cannot
        // already exist, so it is created; otherwise look for one made by hand.
        $this->resolved[$idnumber] = null;
        if ($parentid === null) {
            return self::result($subject, inspector::RESULT_MISSING, $declared, null, 'apply will create it');
        }
        $candidates = $this->find_adoptable($name, $parentid);
        if (count($candidates) > 1) {
            return self::result($subject, self::RESULT_AMBIGUOUS, $declared, null,
                'more than one category could be adopted (ids ' . implode(', ', array_keys($candidates))
                    . '); give ours the idnumber by hand', true);
        }
        if ($candidates) {
            $candidate = (int)array_key_first($candidates);
            $this->resolved[$idnumber] = $candidate;
            $this->adoptable[$idnumber] = $candidate;
            return self::result($subject, inspector::RESULT_MISSING, $declared, null, self::ADOPTABLE);
        }
        return self::result($subject, inspector::RESULT_MISSING, $declared, null, 'apply will create it');
    }

    /**
     * Bring one category to its declaration and report the outcome. The caller has already run
     * the preflight and applies parents first, so a parent created in this run exists by now.
     * A blocking result is reported, never written over.
     *
     * @param array $category {idnumber, name, parent_idnumber}
     * @param report $report
     */
    public function apply(array $category, report $report): void {
        $before = $this->check($category);
        $idnumber = (string)($category['idnumber'] ?? '');
        $result = $before['result'];
        if ($result !== inspector::RESULT_MISSING && $result !== inspector::RESULT_CHANGED) {
            $report->add_result($before);
            return;
        }

        $parentidnumber = isset($category['parent_idnumber']) ? (string)$category['parent_idnumber'] : '';
        $parentid = $this->parent_id($parentidnumber);
        if ($parentid === null) {
            $report->add_result($before, 'fail', "its parent {$parentidnumber} does not exist; "
                . 'it must be declared before this category');
            return;
        }

        try {
            if ($result === inspector::RESULT_MISSING && isset($this->adoptable[$idnumber])) {
                $id = $this->adoptable[$idnumber];
                $live = core_course_category::get($id, MUST_EXIST, true);
                $oldidnumber = (string)$live->idnumber;
                $live->update((object)['idnumber' => $idnumber]);
                $after = $this->check($category);
                if ($after['result'] === inspector::RESULT_OK) {
                    $report->add('changed', self::KIND_ADOPTED, $before['item'], $before['declared'],
                        null, "adopted category id {$id}" . ($oldidnumber !== '' ? ", was idnumber {$oldidnumber}" : ''));
                } else {
                    $report->add_result($after, 'fail', 'adopted, but the server still differs');
                }
                return;
            }
            if ($result === inspector::RESULT_MISSING) {
                core_course_category::create((object)[
                    'name' => (string)$category['name'],
                    'idnumber' => $idnumber,
                    'parent' => $parentid,
                ]);
                $this->report_write($category, $before, 'created', $report);
                return;
            }
            $records = $this->find($idnumber);
            $record = reset($records);
            core_course_category::get((int)$record->id, MUST_EXIST, true)->update((object)[
                'name' => (string)$category['name'],
                'parent' => $parentid,
            ]);
            $this->report_write($category, $before, '', $report);
        } catch (\moodle_exception $e) {
            $report->add_result($before, 'fail', 'Moodle refused it: ' . $e->getMessage());
        }
    }

    /**
     * Report a write by checking again: one Moodle accepted that did not take is a failure.
     *
     * @param array $category the declaration
     * @param array $before the item checked before the write
     * @param string $message what happened, e.g. 'created'
     * @param report $report
     */
    protected function report_write(array $category, array $before, string $message, report $report): void {
        $after = $this->check($category);
        if ($after['result'] === inspector::RESULT_OK) {
            $report->add_result($before, 'changed', $message !== '' ? $message : null);
        } else {
            $report->add_result($after, 'fail', 'written, but the server still differs');
        }
    }

    /**
     * The live id a declared parent resolves to: 0 for top level, the id remembered from this
     * run, or a direct idnumber lookup. Null when the parent does not exist yet.
     *
     * @param string $parentidnumber '' for top level
     * @return int|null
     */
    protected function parent_id(string $parentidnumber): ?int {
        if ($parentidnumber === '') {
            return 0;
        }
        if (array_key_exists($parentidnumber, $this->resolved)) {
            return $this->resolved[$parentidnumber];
        }
        $records = $this->find($parentidnumber);
        return count($records) === 1 ? (int)reset($records)->id : null;
    }

    /**
     * Every category carrying an idnumber, by id.
     *
     * @param string $idnumber
     * @return \stdClass[] keyed by id
     */
    protected function find(string $idnumber): array {
        global $DB;
        return $DB->get_records('course_categories', ['idnumber' => $idnumber], 'id', 'id, name, parent');
    }

    /**
     * Categories that could be adopted: the declared name, exactly, under the declared parent,
     * with an empty idnumber. The name is compared again here, because a database collation
     * may match case-insensitively.
     *
     * @param string $name
     * @param int $parentid
     * @return \stdClass[] keyed by id
     */
    protected function find_adoptable(string $name, int $parentid): array {
        global $DB;
        // A category set up by hand may already carry its own idnumber (the build host's
        // "LTC Published" was ltc-published). Adopt it too, but never one an ltct: declaration owns.
        $records = $DB->get_records_select('course_categories',
            'parent = :parent AND name = :name', ['parent' => $parentid, 'name' => $name], 'id', 'id, name, idnumber');
        return array_filter($records, function($record) use ($name) {
            return (string)$record->name === $name && strpos((string)$record->idnumber, 'ltct:') !== 0;
        });
    }

    /**
     * Where a live category sits, for display: its parent's idnumber, or its id if it has none.
     *
     * @param int $parentid
     * @return string
     */
    protected function parent_text(int $parentid): string {
        global $DB;
        if ($parentid === 0) {
            return 'top level';
        }
        $idnumber = $DB->get_field('course_categories', 'idnumber', ['id' => $parentid]);
        return ($idnumber === false || $idnumber === null || $idnumber === '')
            ? "category id {$parentid}" : (string)$idnumber;
    }

    /**
     * A category's compared properties as display text.
     *
     * @param string $name
     * @param string $parent the parent's idnumber, 'top level', or 'category id N'
     * @return string
     */
    protected static function describe(string $name, string $parent): string {
        return "\"{$name}\" under {$parent}";
    }

    /**
     * Build one item result in the inspector's shape.
     *
     * @param string $subject
     * @param string $result
     * @param string|null $declared
     * @param string|null $live
     * @param string $message
     * @param bool $blocking
     * @return array
     */
    protected static function result(string $subject, string $result, ?string $declared, ?string $live,
            string $message = '', bool $blocking = false): array {
        return [
            'type' => self::TYPE,
            'item' => $subject,
            'result' => $result,
            'declared' => $declared,
            'live' => $live,
            'message' => $message,
            'secret' => false,
            'blocking' => $blocking,
        ];
    }
}
