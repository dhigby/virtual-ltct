<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/cohort/lib.php');

use context;
use context_system;

/**
 * Checks and applies the declared cohorts: each organisation's cohort and its managers cohort
 * (spec 002, research R1, FR-002).
 *
 * The payload's `cohorts` array arrives already expanded by scripts/site_config.py:
 *
 *   cohorts  [{idnumber, name, visible}]   visible is always 0
 *
 * A cohort is found by `idnumber` in ANY context, so one that was moved into a category is
 * found rather than duplicated. That is a blocking `wrong-context`: a category cohort cannot be
 * enrolled into a shared-category course, and moving it back would change what it can be
 * enrolled into, so a human decides. Otherwise only `name` and `visible` are compared.
 *
 * WHAT THIS CLASS NEVER TOUCHES
 *   - `component`. tool_dynamic_cohorts sets it on the cohorts it manages, which is what blocks
 *     hand edits of their membership (R4). Neither the create nor the update passes it, so it is
 *     neither set nor cleared, and a difference in it is never reported.
 *   - Members. No cohort_members row is read, counted or written, and no item names a member
 *     or gives a member count (constitution III, FR-012). A cohort is reported by `idnumber`
 *     and name only.
 *   - Deletion. Apply creates and corrects; it never deletes (FR-004).
 *
 * APIs, confirmed on MOODLE_502_STABLE `public/cohort/lib.php`: cohort_add_cohort() and
 * cohort_update_cohort(). cohort_update_cohort() writes only the properties it is given (its
 * custom-field save returns early when no `customfield_*` property is present), so the update
 * passes id, contextid, name and visible and nothing else. The one raw read is `cohort` by
 * `idnumber`, listed in the plugin README (constitution XI). That column is not indexed in core's
 * install.xml; the cohort table is small and the read is one per declared cohort.
 */
class cohorts {

    /** A declared cohort lives outside system context. Blocks apply. */
    const RESULT_WRONG_CONTEXT = 'wrong-context';

    /** More than one cohort carries the declared idnumber. Blocks apply. */
    const RESULT_AMBIGUOUS = 'ambiguous';

    /** Item type in results. */
    const TYPE = 'cohort';

    /** @var int system context id */
    protected $syscontextid;

    public function __construct() {
        $this->syscontextid = (int)context_system::instance()->id;
    }

    /**
     * The report subject for a cohort.
     *
     * @param string $idnumber
     * @return string `cohort:<idnumber>`
     */
    public static function subject(string $idnumber): string {
        return 'cohort:' . $idnumber;
    }

    /**
     * Check every declared cohort. Reads only.
     *
     * @param array[] $cohorts the payload's `cohorts` array
     * @return array[] item results, in declaration order
     */
    public function check_all(array $cohorts): array {
        $items = [];
        foreach ($cohorts as $cohort) {
            $items[] = $this->check((array)$cohort);
        }
        return $items;
    }

    /**
     * Compare one declared cohort with the live one. Reads only.
     *
     * @param array $cohort {idnumber, name, visible}
     * @return array item result, the inspector's shape
     */
    public function check(array $cohort): array {
        $idnumber = (string)($cohort['idnumber'] ?? '');
        $name = (string)($cohort['name'] ?? '');
        $subject = self::subject($idnumber);

        $visible = $cohort['visible'] ?? null;
        if ($idnumber === '' || trim($name) === '' || !in_array($visible, [0, '0'], true)) {
            return self::result($subject, inspector::RESULT_UNKNOWN, null, null,
                'a cohort needs an idnumber, a name and visible 0; re-render the payload', true);
        }
        $declared = self::describe($name, 0);

        $records = $this->find($idnumber);
        if (!$records) {
            return self::result($subject, inspector::RESULT_MISSING, $declared, null, 'apply will create it');
        }
        if (count($records) > 1) {
            return self::result($subject, self::RESULT_AMBIGUOUS, $declared, null,
                'more than one cohort has this idnumber (ids ' . implode(', ', array_keys($records))
                    . '); give all but one a different idnumber by hand', true);
        }

        $record = reset($records);
        $live = self::describe((string)$record->name, (int)$record->visible);
        if ((int)$record->contextid !== $this->syscontextid) {
            return self::result($subject, self::RESULT_WRONG_CONTEXT, $declared, $live,
                'found in ' . self::context_text((int)$record->contextid) . ', not system context; '
                    . 'it must be moved back to system context by hand', true);
        }
        $same = ((string)$record->name === $name) && ((int)$record->visible === 0);
        return self::result($subject, $same ? inspector::RESULT_OK : inspector::RESULT_CHANGED,
            $declared, $live);
    }

    /**
     * Bring one cohort to its declaration and report the outcome. The caller has already run
     * the preflight, so nothing here blocks: a blocking result is reported, never written over.
     *
     * @param array $cohort {idnumber, name, visible}
     * @param report $report
     */
    public function apply(array $cohort, report $report): void {
        $before = $this->check($cohort);
        $result = $before['result'];

        if ($result === inspector::RESULT_MISSING) {
            cohort_add_cohort((object)[
                'contextid' => $this->syscontextid,
                'idnumber' => (string)$cohort['idnumber'],
                'name' => (string)$cohort['name'],
                'visible' => 0,
                'description' => '',
                'descriptionformat' => FORMAT_HTML,
            ]);
            $this->report_write($cohort, $before, 'created', $report);
            return;
        }

        if ($result === inspector::RESULT_CHANGED) {
            $records = $this->find((string)$cohort['idnumber']);
            $record = reset($records);
            cohort_update_cohort((object)[
                'id' => (int)$record->id,
                'contextid' => (int)$record->contextid,
                'name' => (string)$cohort['name'],
                'visible' => 0,
            ]);
            $this->report_write($cohort, $before, '', $report);
            return;
        }

        $report->add_result($before);
    }

    /**
     * Report a write by checking again: one Moodle accepted that did not take is a failure.
     *
     * @param array $cohort the declaration
     * @param array $before the item checked before the write
     * @param string $message what happened, e.g. 'created'
     * @param report $report
     */
    protected function report_write(array $cohort, array $before, string $message, report $report): void {
        $after = $this->check($cohort);
        if ($after['result'] === inspector::RESULT_OK) {
            $report->add_result($before, 'changed', $message !== '' ? $message : null);
        } else {
            $report->add_result($after, 'fail', 'written, but the server still differs');
        }
    }

    /**
     * Every cohort carrying an idnumber, by id. The only columns read are the ones compared:
     * never a member, never a count.
     *
     * @param string $idnumber
     * @return \stdClass[] keyed by id
     */
    protected function find(string $idnumber): array {
        global $DB;
        return $DB->get_records('cohort', ['idnumber' => $idnumber], 'id', 'id, contextid, name, visible');
    }

    /**
     * A cohort's compared properties as display text.
     *
     * @param string $name
     * @param int $visible
     * @return string
     */
    protected static function describe(string $name, int $visible): string {
        return "\"{$name}\", " . ($visible ? 'visible' : 'hidden');
    }

    /**
     * Where a misplaced cohort is, for a human. Names a category, never a user.
     *
     * @param int $contextid
     * @return string
     */
    protected static function context_text(int $contextid): string {
        $context = context::instance_by_id($contextid, IGNORE_MISSING);
        if (!$context) {
            return "context {$contextid}, which no longer exists";
        }
        return "context {$contextid} (" . $context->get_context_name(false) . ')';
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
