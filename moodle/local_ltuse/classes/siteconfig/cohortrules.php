<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

use core_plugin_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Checks and applies the cohort rules that fill each organisation cohort (spec 002, FR-010).
 *
 * A rule belongs to `tool_dynamic_cohorts` (research R4). It is found through its cohort, by
 * the cohort's `idnumber`, and compared on its condition and `enabled`. Everything is read and
 * written through the plugin's own public classes, never its tables (constitution XI):
 *
 *   read   \tool_dynamic_cohorts\rule::get_records(), ->get_condition_records(), ->is_broken()
 *   write  \tool_dynamic_cohorts\rule_manager::process_form(), which always saves a rule
 *          disabled; then, once ->is_broken() is false, `enabled = 1` and ->save() with a
 *          rule_updated event, as the plugin's own toggle_status web service does.
 *
 * The cohort itself is read from {cohort} by `idnumber`, an indexed column of a core table.
 *
 * The payload's `cohort_rules` entries are {cohort_idnumber, name, condition, field, value,
 * enabled?}, with `enabled` defaulting to 1 (data-model "Cohort rule"). The condition config
 * keys are the ones recorded at R4 Verify, in contracts/declaration.md "Condition config".
 *
 * The plugin is checked for before any of its classes is touched. When it is absent each rule
 * is `missing` with "plugin not installed", non-blocking, because the plugin's own item in
 * site.yaml already blocks apply.
 *
 * NO MEMBER IS EVER READ OR REPORTED. Matching users and their counts are the plugin's
 * business; nothing here calls get_matching_users() or get_matching_users_count().
 */
class cohortrules {

    /** The plugin that owns the rules. */
    const COMPONENT = 'tool_dynamic_cohorts';

    /** Item type, as in inspector's results. */
    const TYPE = 'cohortrule';

    /** Every rule this site owns is named with this prefix, then the cohort idnumber. */
    const NAME_PREFIX = 'ltct: ';

    /** Declared condition names to the plugin's condition classes. */
    const CONDITIONS = [
        'user_custom_profile' => 'tool_dynamic_cohorts\\local\\tool_dynamic_cohorts\\condition\\user_custom_profile',
    ];

    /** The plugin's prefix for a custom profile field in condition config. */
    const FIELD_PREFIX = 'profile_field_';

    /** condition_base::TEXT_IS_EQUAL_TO in release 2026031300 (contracts/declaration.md). */
    const OPERATOR_EQUALS = 3;

    /** Result kinds this class reports beyond inspector's. */
    const RESULT_AMBIGUOUS = 'ambiguous';

    /** The description every rule this site owns carries, so a hand edit is less likely. */
    const DESCRIPTION = 'Declared in moodle/site/organisations.yaml and applied by local_ltuse '
        . 'site_config. A change made here is reported as drift and overwritten by the next apply.';

    /**
     * Whether tool_dynamic_cohorts is installed, upgraded and its code present, so its classes
     * may be used.
     *
     * @return bool
     */
    public static function plugin_installed(): bool {
        $info = core_plugin_manager::instance()->get_plugin_info(self::COMPONENT);
        return $info && $info->versiondb !== null && $info->versiondisk !== null
            && (string)$info->versiondb === (string)$info->versiondisk
            && class_exists('\\tool_dynamic_cohorts\\rule')
            && class_exists('\\tool_dynamic_cohorts\\rule_manager');
    }

    /**
     * The report subject for a rule: `cohortrule:<cohort idnumber>`.
     *
     * @param array $rule a payload `cohort_rules` entry
     * @return string
     */
    public static function subject(array $rule): string {
        return self::TYPE . ':' . (string)($rule['cohort_idnumber'] ?? '');
    }

    /**
     * The condition config a declared rule must carry, exactly the keys recorded at R4.
     *
     * @param array $rule a payload `cohort_rules` entry
     * @return array
     */
    public static function expected_config(array $rule): array {
        $field = self::FIELD_PREFIX . (string)($rule['field'] ?? '');
        return [
            'profilefield' => $field,
            $field . '_operator' => self::OPERATOR_EQUALS,
            $field . '_value' => (string)($rule['value'] ?? ''),
            'include_missing_data' => 0,
        ];
    }

    /**
     * Compare one declared rule with the live one. WRITES NOTHING.
     *
     * Besides inspector's keys, the result carries `applicable`: whether apply can write it.
     * The report ignores that key.
     *
     * @param array $rule a payload `cohort_rules` entry
     * @return array item result
     */
    public static function check(array $rule): array {
        global $DB;
        $subject = self::subject($rule);
        $declared = self::declared_text($rule);

        if (!self::plugin_installed()) {
            return self::result($subject, inspector::RESULT_MISSING, $declared, null,
                'plugin not installed', false, false);
        }
        if (!isset(self::CONDITIONS[(string)($rule['condition'] ?? '')])) {
            return self::result($subject, inspector::RESULT_UNKNOWN, $declared, null,
                'condition must be one of: ' . implode(', ', array_keys(self::CONDITIONS)), true, false);
        }

        $cohort = $DB->get_record('cohort', ['idnumber' => (string)$rule['cohort_idnumber']],
            'id, idnumber, component');
        if (!$cohort) {
            // Apply creates cohorts before rules, so in apply this means the cohort failed.
            return self::result($subject, inspector::RESULT_MISSING, $declared, null,
                "cohort {$rule['cohort_idnumber']} does not exist yet", false, false);
        }
        if ((string)$cohort->component !== '' && $cohort->component !== self::COMPONENT) {
            return self::result($subject, inspector::RESULT_MISSING, $declared, null,
                "the cohort is managed by {$cohort->component}, so " . self::COMPONENT
                    . ' cannot add a rule to it', false, false);
        }

        $rules = \tool_dynamic_cohorts\rule::get_records(['cohortid' => $cohort->id], 'id');
        if (!$rules) {
            return self::result($subject, inspector::RESULT_MISSING, $declared, null, '', false, true);
        }
        if (count($rules) > 1) {
            $ids = array_map(function($r) {
                return $r->get('id');
            }, $rules);
            return self::result($subject, self::RESULT_AMBIGUOUS, $declared, null,
                'more than one rule fills this cohort: rule ids ' . implode(', ', $ids)
                    . '; delete all but one in the plugin', true, false);
        }

        $live = reset($rules);
        $livetext = self::live_text($live);
        $matches = self::condition_matches($live, $rule)
            && (int)$live->get('enabled') === self::declared_enabled($rule);
        return self::result($subject, $matches ? inspector::RESULT_OK : inspector::RESULT_CHANGED,
            $declared, $livetext, $live->is_broken() ? 'the rule is marked broken' : '', false, !$matches);
    }

    /**
     * Create or correct one rule, and report it. Call only once no preflight item blocks.
     *
     * @param array $rule a payload `cohort_rules` entry
     * @param report $report
     * @return void
     */
    public static function apply(array $rule, report $report): void {
        global $DB;
        $before = self::check($rule);
        $found = $before['result'] === inspector::RESULT_MISSING || $before['result'] === inspector::RESULT_CHANGED;
        if (!$found) {
            $report->add_result($before);
            return;
        }
        if (!$before['applicable']) {
            $report->add_result($before, 'fail', $before['message'] . '; nothing was written');
            return;
        }

        try {
            $cohort = $DB->get_record('cohort', ['idnumber' => (string)$rule['cohort_idnumber']], 'id', MUST_EXIST);
            $existing = \tool_dynamic_cohorts\rule::get_records(['cohortid' => $cohort->id], 'id');
            $live = $existing ? reset($existing) : null;

            // process_form() also recomputes the stored `broken` flag, so a rule marked broken
            // goes through it again even when its condition already matches: the plugin will not
            // process a rule whose flag is set, enabled or not.
            if (!$live || $live->is_broken() || !self::condition_matches($live, $rule)) {
                $live = \tool_dynamic_cohorts\rule_manager::process_form(self::form_data($rule, (int)$cohort->id,
                    $live ? (int)$live->get('id') : 0));
            }

            $wanted = self::declared_enabled($rule);
            if ((int)$live->get('enabled') !== $wanted) {
                if ($wanted === 1 && $live->is_broken()) {
                    $report->add_result($before, 'fail', 'the rule was saved but its condition is broken, '
                        . "so it cannot be enabled; check that profile field {$rule['field']} exists "
                        . "and offers '{$rule['value']}'");
                    return;
                }
                $live->set('enabled', $wanted);
                $live->save();
                \tool_dynamic_cohorts\event\rule_updated::create(['other' => ['ruleid' => $live->get('id')]])->trigger();
            }
        } catch (\Throwable $e) {
            $report->add_result($before, 'fail', self::COMPONENT . ' refused the rule: ' . $e->getMessage());
            return;
        }

        $after = self::check($rule);
        if ($after['result'] === inspector::RESULT_OK) {
            $report->add_result($before, 'changed',
                $before['result'] === inspector::RESULT_MISSING ? 'created' : 'updated');
        } else {
            $report->add_result($after, 'fail', 'written, but the server still differs');
        }
    }

    /**
     * The rules this site owns, by name prefix, for drift's `extra` scan. Empty when the
     * plugin is not installed. Reports names and cohort idnumbers only, never members.
     *
     * @return array[] each {name, cohort_idnumber}; cohort_idnumber is '' when its cohort is gone
     */
    public static function owned_rules(): array {
        global $DB;
        if (!self::plugin_installed()) {
            return [];
        }
        $owned = [];
        $select = $DB->sql_like('name', ':prefix');
        $params = ['prefix' => $DB->sql_like_escape(self::NAME_PREFIX) . '%'];
        foreach (\tool_dynamic_cohorts\rule::get_records_select($select, $params, 'id') as $live) {
            $idnumber = $DB->get_field('cohort', 'idnumber', ['id' => $live->get('cohortid')]);
            $owned[] = ['name' => $live->get('name'), 'cohort_idnumber' => $idnumber === false ? '' : (string)$idnumber];
        }
        return $owned;
    }

    /**
     * Whether a live rule has exactly the one declared condition. Extra config keys the
     * plugin's form saves alongside are ignored; the declared keys must all match.
     *
     * @param \tool_dynamic_cohorts\rule $live
     * @param array $rule a payload `cohort_rules` entry
     * @return bool
     */
    protected static function condition_matches($live, array $rule): bool {
        $conditions = $live->get_condition_records();
        if (count($conditions) !== 1) {
            return false;
        }
        $condition = reset($conditions);
        if (ltrim((string)$condition->get('classname'), '\\') !== self::CONDITIONS[$rule['condition']]) {
            return false;
        }
        $config = json_decode((string)$condition->get('configdata'), true);
        if (!is_array($config)) {
            return false;
        }
        foreach (self::expected_config($rule) as $key => $value) {
            if (!array_key_exists($key, $config) || (string)$config[$key] !== (string)$value) {
                return false;
            }
        }
        return true;
    }

    /**
     * The rule form data process_form() takes (research R4's result). The rule is saved
     * disabled; apply enables it afterwards. Any existing conditions are replaced.
     *
     * @param array $rule a payload `cohort_rules` entry
     * @param int $cohortid
     * @param int $ruleid 0 to create
     * @return \stdClass
     */
    protected static function form_data(array $rule, int $cohortid, int $ruleid): \stdClass {
        $condition = [
            'id' => 0,
            'classname' => self::CONDITIONS[$rule['condition']],
            'configdata' => json_encode(self::expected_config($rule)),
            'sortorder' => 0,
        ];
        return (object)[
            'id' => $ruleid,
            'name' => (string)($rule['name'] ?? self::NAME_PREFIX . $rule['cohort_idnumber']),
            'description' => self::DESCRIPTION,
            'cohortid' => $cohortid,
            'bulkprocessing' => 0,
            'operator' => \tool_dynamic_cohorts\rule_manager::CONDITIONS_OPERATOR_AND,
            'realtime' => 1,
            'enabled' => 0,
            'isconditionschanged' => 1,
            'conditionjson' => json_encode([$condition]),
        ];
    }

    /**
     * @param array $rule
     * @return int 1 or 0; the payload may omit it, and a rule is enabled by default
     */
    protected static function declared_enabled(array $rule): int {
        return array_key_exists('enabled', $rule) && $rule['enabled'] !== null ? ((int)(bool)$rule['enabled']) : 1;
    }

    /**
     * @param array $rule
     * @return string e.g. "ltct_org equals 'sil', enabled"
     */
    protected static function declared_text(array $rule): string {
        return ($rule['field'] ?? '') . " equals '" . ($rule['value'] ?? '') . "', "
            . (self::declared_enabled($rule) ? 'enabled' : 'disabled');
    }

    /**
     * Describe a live rule's conditions and state in the declared text's shape, or as raw
     * class and config when it is not a single custom profile condition.
     *
     * @param \tool_dynamic_cohorts\rule $live
     * @return string
     */
    protected static function live_text($live): string {
        $parts = [];
        foreach ($live->get_condition_records() as $condition) {
            $class = ltrim((string)$condition->get('classname'), '\\');
            $config = json_decode((string)$condition->get('configdata'), true);
            $field = is_array($config) ? (string)($config['profilefield'] ?? '') : '';
            if ($class === self::CONDITIONS['user_custom_profile'] && strpos($field, self::FIELD_PREFIX) === 0) {
                $operator = (int)($config[$field . '_operator'] ?? 0);
                $value = (string)($config[$field . '_value'] ?? '');
                $parts[] = substr($field, strlen(self::FIELD_PREFIX))
                    . ($operator === self::OPERATOR_EQUALS ? ' equals' : " operator {$operator}") . " '{$value}'";
            } else {
                $cut = strrpos($class, '\\');
                $parts[] = ($cut === false ? $class : substr($class, $cut + 1))
                    . ' ' . (string)$condition->get('configdata');
            }
        }
        $text = $parts ? implode(' and ', $parts) : 'no conditions';
        return $text . ', ' . ($live->is_enabled() ? 'enabled' : 'disabled');
    }

    /**
     * Build one item result, in inspector's shape plus `applicable`.
     *
     * @param string $item
     * @param string $result
     * @param mixed $declared
     * @param mixed $live
     * @param string $message
     * @param bool $blocking
     * @param bool $applicable whether apply can write this item
     * @return array
     */
    protected static function result(string $item, string $result, $declared, $live, string $message,
            bool $blocking, bool $applicable): array {
        return [
            'type' => self::TYPE,
            'item' => $item,
            'result' => $result,
            'declared' => $declared,
            'live' => $live,
            'message' => $message,
            'secret' => false,
            'blocking' => $blocking,
            'applicable' => $applicable,
        ];
    }
}
