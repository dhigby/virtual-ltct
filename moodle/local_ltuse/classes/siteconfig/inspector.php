<?php
namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->libdir . '/filterlib.php');

use admin_category;
use admin_setting;
use admin_setting_configmulticheckbox;
use admin_setting_configpasswordunmask;
use admin_settingpage;
use context_system;
use core_plugin_manager;

/**
 * Reads the live state of a site and compares it with a declaration. WRITES NOTHING.
 *
 * The applier and the drift report both start here, so "what differs" is decided in one
 * place and an apply can never disagree with the drift run that preceded it. Every
 * method is a read: no write_setting(), set_config(), enable_plugin(), create_role() or
 * capability change happens in this class (data-model "Run report", US2-4).
 *
 * The declaration arrives as the decoded JSON payload from scripts/site_config.py (see
 * specs/001-site-config-as-code/data-model.md, "Rendered payload"):
 *
 *   moodle     {requires, release}
 *   plugins    [{component, enabled?, version?}]
 *   roles      [{shortname, name?, description?, archetype?, contextlevels?, capabilities?,
 *                allowassign?}]   allowassign: spec 003, the roles this one may assign
 *   settings   [{name, value?, secret?}]   value is absent for a secret in drift mode
 *   failed_env [{name, env}, ...]         settings whose env: variable was not set (each also carries env_missing)
 *
 * and, from spec 002 (specs/002-org-structure-cohorts/data-model.md "Rendered payload"), four
 * arrays that are handed to their own classes for checking:
 *
 *   categories     [{idnumber, name, parent_idnumber}]          categories, parents first
 *   cohorts        [{idnumber, name, visible}]                  cohorts
 *   profile_fields [{category, shortname, name, datatype, ...}] profilefields
 *   cohort_rules   [{cohort_idnumber, name, condition, ...}]    cohortrules
 *
 * and, from spec 004 (specs/004-progress-reporting/contracts/declaration.md "Output additions"),
 * four more, checked after spec 002's in the same apply order:
 *
 *   course_field_category  "<name>"                                  coursefields
 *   course_fields          [{shortname, name, type, locked, visibility}] coursefields
 *   competencies           [{name, category, sortorder}]             competencies
 *   reports                [{area, name, source, columns, ...}]      reports
 *
 * and, from spec 013 (specs/013-certificates-badges/contracts/declaration.md "Payload arrays"),
 * two templates, checked after reports:
 *
 *   badge_template         {name, description, ..., image, deny}       badgetemplate
 *   certificate_template   {name, activity_name, intro, font, pages}   certtemplate
 *
 * A spec 004 result may carry `blocking` => report::BLOCKS_REPORT, which leaves only its own
 * report unwritten; has_blocking() delegates to report::has_blocking() so such a result does
 * not stop the run.
 *
 * Each check returns item results of this shape, which the report class renders:
 *
 *   type      'release' | 'plugin' | 'role' | 'setting', or a spec 002 type: 'category',
 *             'cohort', 'profilecategory', 'profilefield', 'cohortrule'
 *   item      setting key, plugin component, 'role:<shortname>' or '<shortname>:<capability>',
 *             or a spec 002 subject: 'category:<idnumber>', 'cohort:<idnumber>',
 *             'profilecategory:<name>', 'profilefield:<shortname>', 'cohortrule:<idnumber>'
 *   result    one of the RESULT_* constants
 *   declared  what the declaration says, as display text (null when not applicable)
 *   live      what the server holds, as display text (null when absent)
 *   message   extra detail for a human, or ''
 *   secret    true when declared and live must be shown as <secret>
 *   blocking  true when apply must write nothing at all (data-model "Run report")
 *   warning   (discussion items only) true for something to look at that apply cannot fix
 *
 * APIs used, all confirmed on MOODLE_502_STABLE (research.md R4, R6, R7, R8):
 * admin_get_root(), admin_setting::get_setting()/is_readonly(), core_plugin_manager,
 * plugininfo::get_enabled_plugin(), get_default_capabilities(), get_capability_info(),
 * get_role_contextlevels(). The raw reads are role_capabilities by roleid and contextid,
 * and role_allow_assign by its unique (roleid, allowassign) key (spec 003; core offers no
 * reader for one pair), both listed in the plugin README (constitution XI).
 *
 * Course discussions (spec 012, R5): groups_get_activity_groupmode()'s rule (lib/grouplib.php:
 * a course's groupmodeforce overrides the activity). Courses and their forum are looked up by
 * idnumber, as local_ltuse\util does. No discussion, post or user is ever read.
 *
 * Open courses (spec 002, amended 2026-10-02, research R2 and R3): every ltct: course has
 * group mode 0, and no organisation's managers cohort is synced into a shared course. The
 * second is a COUNT over enrol, cohort, course and course_categories, naming nothing; an
 * enrolment instance is course configuration, not learner data.
 */
class inspector {

    /** Live state matches the declaration. */
    const RESULT_OK = 'ok';
    /** Live value differs from the declared one; apply would write it. */
    const RESULT_CHANGED = 'changed';
    /** A declared plugin or role is not on the server. */
    const RESULT_MISSING = 'missing';
    /** A declared setting or capability is not known to this server. */
    const RESULT_UNKNOWN = 'unknown';
    /** The setting is overridden in config.php, so a database write would do nothing. */
    const RESULT_FORCED = 'forced';
    /** The installed plugin is not the version the declaration pins. */
    const RESULT_WRONG_RELEASE = 'wrong-release';
    /** Plugin code on disk is newer than the database: an upgrade has not been run. */
    const RESULT_PENDING_UPGRADE = 'pending-upgrade';
    /** The setting's env: variable was not set on the operator's machine. */
    const RESULT_ENV_MISSING = 'env-missing';
    /** The server's Moodle is older than the declaration's minimum release. */
    const RESULT_BELOW_MINIMUM = 'below-minimum';
    /** A course discussion's live group mode is not no groups (spec 002 R3, R14). */
    const RESULT_DIFFERS = 'differs';
    /** A managers cohort is synced into a shared course (spec 002 R2). Blocking. */
    const RESULT_SHARED_MANAGERS = 'shared-managers';

    /** Course-module idnumber suffix of a course's discussion forum (spec 012, R7). */
    const DISCUSSION_SUFFIX = ':discussion';

    /** Shown in place of every secret value. */
    const SECRET = '<secret>';

    /** Declaration permission words to Moodle's CAP_* constants. */
    const PERMISSIONS = [
        'allow' => CAP_ALLOW,
        'prevent' => CAP_PREVENT,
        'prohibit' => CAP_PROHIBIT,
        'inherit' => CAP_INHERIT,
    ];

    /** Declaration context-level words to Moodle's CONTEXT_* constants. */
    const CONTEXTLEVELS = [
        'system' => CONTEXT_SYSTEM,
        'user' => CONTEXT_USER,
        'coursecat' => CONTEXT_COURSECAT,
        'course' => CONTEXT_COURSE,
        'module' => CONTEXT_MODULE,
        'block' => CONTEXT_BLOCK,
    ];

    /** Filter states as written in site.yaml (filterlib.php TEXTFILTER_*). */
    const FILTER_STATES = [
        'on' => TEXTFILTER_ON,
        'off' => TEXTFILTER_OFF,
        'disabled' => TEXTFILTER_DISABLED,
    ];

    /** @var array the decoded payload */
    protected $declaration;

    /** @var admin_setting[] every setting in the full admin tree, keyed by setting key */
    protected $settings = null;

    /** @var int system context id */
    protected $syscontextid;

    /** @var categories|null checks the payload's categories; remembers ids resolved in order */
    protected $categories = null;

    /** @var cohorts|null checks the payload's cohorts */
    protected $cohorts = null;

    /** @var profilefields|null checks the payload's profile field category and fields */
    protected $profilefields = null;

    /** @var coursefields|null checks the payload's course field category and course fields */
    protected $coursefields = null;

    /** @var competencies|null checks the payload's competency list */
    protected $competencies = null;

    /** @var reports|null checks the payload's custom reports */
    protected $reports = null;

    /** @var badgetemplate|null checks the payload's badge template (spec 013) */
    protected $badgetemplate = null;

    /** @var certtemplate|null checks the payload's certificate template (spec 013) */
    protected $certtemplate = null;

    /**
     * @param array $declaration the decoded JSON payload (associative arrays throughout)
     */
    public function __construct(array $declaration) {
        $this->declaration = $declaration;
        $this->syscontextid = context_system::instance()->id;
    }

    /**
     * @return array the decoded payload this inspector compares with
     */
    public function declaration(): array {
        return $this->declaration;
    }

    // --- spec 002 checkers -----------------------------------------------------------------

    /**
     * The category checker this inspector uses. It remembers the live id each declared
     * idnumber resolved to, so the applier can apply through the same instance.
     *
     * @return categories
     */
    public function categories(): categories {
        if ($this->categories === null) {
            $this->categories = new categories();
        }
        return $this->categories;
    }

    /**
     * @return cohorts the cohort checker this inspector uses
     */
    public function cohorts(): cohorts {
        if ($this->cohorts === null) {
            $this->cohorts = new cohorts();
        }
        return $this->cohorts;
    }

    /**
     * @return profilefields the checker for the payload's `profile_fields`
     */
    public function profilefields(): profilefields {
        if ($this->profilefields === null) {
            $this->profilefields = new profilefields(self::entries($this->declaration['profile_fields'] ?? []));
        }
        return $this->profilefields;
    }

    // --- spec 004 checkers -----------------------------------------------------------------

    /**
     * Whether the payload declares course fields at all. An empty category with fields is
     * still a declaration, so coursefields reports the missing category as blocking.
     *
     * @return bool
     */
    public function declares_course_fields(): bool {
        return trim((string)($this->declaration['course_field_category'] ?? '')) !== ''
            || self::entries($this->declaration['course_fields'] ?? []);
    }

    /**
     * @return coursefields the checker for `course_field_category` and `course_fields`
     */
    public function coursefields(): coursefields {
        if ($this->coursefields === null) {
            $this->coursefields = new coursefields((string)($this->declaration['course_field_category'] ?? ''),
                self::entries($this->declaration['course_fields'] ?? []));
        }
        return $this->coursefields;
    }

    /**
     * Whether the payload declares a competency list. Only a non-empty list is managed:
     * apply retires every live row a list leaves out, so an absent or empty list (a payload
     * from before spec 004) must never be read as "retire them all". Check, apply and drift
     * all use this one gate, so drift never reports what apply would not do.
     *
     * @return bool
     */
    public function declares_competencies(): bool {
        return (bool)self::entries($this->declaration['competencies'] ?? []);
    }

    /**
     * @return competencies the checker for the payload's `competencies`
     */
    public function competencies(): competencies {
        if ($this->competencies === null) {
            $this->competencies = new competencies(self::entries($this->declaration['competencies'] ?? []));
        }
        return $this->competencies;
    }

    /**
     * The reports checker. Always present: with no `reports`, it checks nothing, and every
     * `local_ltuse` report is undeclared, which is what drift should say.
     *
     * @return reports the checker for the payload's `reports`
     */
    public function reports(): reports {
        if ($this->reports === null) {
            $this->reports = new reports(self::entries($this->declaration['reports'] ?? []));
        }
        return $this->reports;
    }

    // --- spec 013 checkers -----------------------------------------------------------------

    /**
     * The badge template checker, or null when the payload declares none (a payload from
     * before spec 013, or a site with no badges.yaml).
     *
     * @return badgetemplate|null
     */
    public function badgetemplate(): ?badgetemplate {
        if ($this->badgetemplate === null && is_array($this->declaration['badge_template'] ?? null)) {
            $this->badgetemplate = new badgetemplate($this->declaration['badge_template']);
        }
        return $this->badgetemplate;
    }

    /**
     * The certificate template checker, or null when the payload declares none.
     *
     * @return certtemplate|null
     */
    public function certtemplate(): ?certtemplate {
        if ($this->certtemplate === null && is_array($this->declaration['certificate_template'] ?? null)) {
            $this->certtemplate = new certtemplate($this->declaration['certificate_template']);
        }
        return $this->certtemplate;
    }

    /**
     * One payload array as a list of associative arrays. Anything that is not an array is
     * treated as empty; site_config.py's validate rejects such a payload before it gets here.
     *
     * @param mixed $entries
     * @return array[]
     */
    public static function entries($entries): array {
        if (!is_array($entries)) {
            return [];
        }
        return array_values(array_map(function($entry) {
            return (array)$entry;
        }, $entries));
    }

    // --- admin tree ----------------------------------------------------------------------

    /**
     * Every admin_setting on the site, keyed by its declaration key.
     *
     * The tree is built as the site admin, because settings.php files only add pages the
     * current user may see, and a CLI script has no user until one is set (R4). Setting
     * the session user is in-memory only; nothing is written.
     *
     * @return admin_setting[] keyed by 'name' for core, 'plugin/name' for a plugin
     */
    public function all_settings(): array {
        if ($this->settings === null) {
            \core\session\manager::set_user(get_admin());
            $root = admin_get_root(true, true);
            $this->settings = [];
            $this->collect_settings($root);
        }
        return $this->settings;
    }

    /**
     * Walk one node of the admin tree, the way admin_find_write_settings() does.
     *
     * @param mixed $node a part_of_admin_tree
     */
    protected function collect_settings($node): void {
        if ($node instanceof admin_category) {
            foreach ($node->get_children() as $child) {
                $this->collect_settings($child);
            }
        } else if ($node instanceof admin_settingpage) {
            foreach ($node->settings as $setting) {
                $key = self::setting_key($setting);
                // A setting added to two pages is one setting; the first page wins.
                if (!isset($this->settings[$key])) {
                    $this->settings[$key] = $setting;
                }
            }
        }
    }

    /**
     * The declaration key of an admin setting: 'name' for core, 'plugin/name' otherwise.
     *
     * @param admin_setting $setting
     * @return string
     */
    public static function setting_key(admin_setting $setting): string {
        if (empty($setting->plugin) || $setting->plugin === 'moodle') {
            return $setting->name;
        }
        return $setting->plugin . '/' . $setting->name;
    }

    /**
     * The admin setting a declaration key names, or null when the tree has no such setting.
     *
     * @param string $key
     * @return admin_setting|null
     */
    public function find_setting(string $key): ?admin_setting {
        $all = $this->all_settings();
        return $all[$key] ?? null;
    }

    /**
     * Whether a setting's value must never be shown, declared secret or not (R3, fails closed).
     *
     * @param admin_setting $setting
     * @return bool
     */
    public static function is_password_setting(admin_setting $setting): bool {
        return $setting instanceof admin_setting_configpasswordunmask;
    }

    // --- whole declaration ---------------------------------------------------------------

    /**
     * Every declared item's comparison result, in report order: release, plugins, roles,
     * settings (data-model "Run report"), then spec 002's categories, cohorts, profile field
     * category and fields, and cohort rules, in the order the contract applies them
     * (specs/002-org-structure-cohorts/contracts/declaration.md "Output additions").
     * Undeclared state (extra plugins, unmanaged settings, undeclared `ltct:` items) is the
     * drift class's job, not this one.
     *
     * @return array[] item results
     */
    public function inspect(): array {
        $items = [$this->check_release()];
        foreach ($this->declaration['plugins'] ?? [] as $plugin) {
            $items[] = $this->check_plugin($plugin);
        }
        foreach ($this->declaration['roles'] ?? [] as $role) {
            $items = array_merge($items, $this->check_role($role));
        }
        foreach ($this->declaration['roles'] ?? [] as $role) {
            $items = array_merge($items, $this->check_allowassign($role));
        }
        foreach ($this->declaration['settings'] ?? [] as $setting) {
            $items[] = $this->check_setting($setting);
        }
        // In the preflight, so apply writes nothing while a managers cohort reaches a shared
        // course (spec 002 R2). Run the open-courses migration CLI first.
        $items = array_merge($items, $this->check_shared_managers());
        return array_merge($items, $this->inspect_structure());
    }

    /**
     * Spec 002's and spec 004's item results, in apply order: categories (parents first),
     * cohorts, the profile field category and fields, cohort rules, then the course field
     * category and course fields, the competency list, and reports last. WRITES NOTHING.
     *
     * The order matters because a later check reads what an earlier one resolved: a category
     * whose parent does not exist yet is plain `missing`, and a rule whose cohort does not
     * exist yet is a non-blocking `missing`, since apply creates the parent and the cohort
     * first. A report reads custom fields, profile fields and cohorts, so it comes last, and a
     * column on a field this run will create blocks only that report.
     *
     * @return array[] item results
     */
    public function inspect_structure(): array {
        $items = $this->categories()->check_all(self::entries($this->declaration['categories'] ?? []));
        $items = array_merge($items,
            $this->cohorts()->check_all(self::entries($this->declaration['cohorts'] ?? [])));
        if (self::entries($this->declaration['profile_fields'] ?? [])) {
            $items = array_merge($items, $this->profilefields()->check());
        }
        foreach (self::entries($this->declaration['cohort_rules'] ?? []) as $rule) {
            $items[] = cohortrules::check($rule);
        }
        if ($this->declares_course_fields()) {
            $items = array_merge($items, $this->coursefields()->check());
        }
        if ($this->declares_competencies()) {
            $items = array_merge($items, $this->competencies()->check());
        }
        $items = array_merge($items, $this->reports()->check());
        // Spec 013, after reports (contracts/declaration.md "Payload arrays").
        if ($this->badgetemplate()) {
            $items = array_merge($items, $this->badgetemplate()->check());
        }
        if ($this->certtemplate()) {
            $items = array_merge($items, $this->certtemplate()->check());
        }
        return $items;
    }

    /**
     * Whether any item means apply must write nothing.
     *
     * It counts every item inspect() returns, so a blocking result from any class stops apply
     * before its first write, whichever array it came from: a release below the minimum, a
     * missing plugin, a forced setting, an `ambiguous` category, a `wrong-context` cohort, a
     * `wrong-datatype` profile field or an unreadable cohort rule.
     *
     * A report-scoped result (`blocking` => report::BLOCKS_REPORT, spec 004) does not count
     * here: it leaves only its own report unwritten, and reports::apply() enforces that. The
     * rule lives once, in report::has_blocking().
     *
     * @param array[] $items
     * @return bool
     */
    public static function has_blocking(array $items): bool {
        return report::has_blocking($items);
    }

    /**
     * Build one item result.
     *
     * @param string $type
     * @param string $item
     * @param string $result
     * @param mixed $declared
     * @param mixed $live
     * @param string $message
     * @param bool $secret
     * @param bool $blocking
     * @return array
     */
    protected static function result(string $type, string $item, string $result, $declared = null,
            $live = null, string $message = '', bool $secret = false, bool $blocking = false): array {
        return [
            'type' => $type,
            'item' => $item,
            'result' => $result,
            'declared' => $declared,
            'live' => $live,
            'message' => $message,
            'secret' => $secret,
            'blocking' => $blocking,
        ];
    }

    // --- release -------------------------------------------------------------------------

    /**
     * Is this server at or above the declared minimum release (R10, US1-4)?
     *
     * @return array item result; below-minimum blocks apply
     */
    public function check_release(): array {
        global $CFG;
        $moodle = $this->declaration['moodle'] ?? [];
        $requires = $moodle['requires'] ?? null;
        $release = (string)($moodle['release'] ?? '');
        $live = $CFG->version . ' (' . $CFG->release . ')';
        $declared = $requires === null ? null : $requires . ($release !== '' ? " ({$release})" : '');

        if ($requires === null || !is_numeric($requires)) {
            return self::result('release', 'moodle', self::RESULT_UNKNOWN, $declared, $live,
                'the declaration has no numeric moodle.requires', false, true);
        }
        if ((float)$CFG->version < (float)$requires) {
            return self::result('release', 'moodle', self::RESULT_BELOW_MINIMUM, $declared, $live,
                "this server runs {$CFG->release}; the declaration needs {$release} or later",
                false, true);
        }
        return self::result('release', 'moodle', self::RESULT_OK, $declared, $live);
    }

    // --- plugins -------------------------------------------------------------------------

    /**
     * Compare one declared plugin with what is installed (R7, data-model "Plugin declaration").
     *
     * @param array $plugin {component, enabled?, version?}
     * @return array item result
     */
    public function check_plugin(array $plugin): array {
        $component = (string)($plugin['component'] ?? '');
        $info = core_plugin_manager::instance()->get_plugin_info($component);

        if (!$info || $info->versiondb === null) {
            return self::result('plugin', $component, self::RESULT_MISSING,
                $plugin['version'] ?? null, null, 'not installed', false, true);
        }
        if ($info->versiondisk === null) {
            return self::result('plugin', $component, self::RESULT_MISSING,
                $plugin['version'] ?? null, (string)$info->versiondb,
                'installed in the database but its code is not on disk', false, true);
        }
        if ((string)$info->versiondisk !== (string)$info->versiondb) {
            return self::result('plugin', $component, self::RESULT_PENDING_UPGRADE,
                (string)$info->versiondisk, (string)$info->versiondb,
                "code on disk is {$info->versiondisk} but the database is at {$info->versiondb}; "
                    . 'run admin/cli/upgrade.php', false, true);
        }
        if (isset($plugin['version']) && (string)$plugin['version'] !== (string)$info->versiondb) {
            return self::result('plugin', $component, self::RESULT_WRONG_RELEASE,
                (string)$plugin['version'], (string)$info->versiondb,
                "pinned to {$plugin['version']} but {$info->versiondb} is installed", false, true);
        }

        if (!array_key_exists('enabled', $plugin) || $plugin['enabled'] === null) {
            return self::result('plugin', $component, self::RESULT_OK, null, (string)$info->versiondb,
                'enabled state not managed');
        }

        $class = core_plugin_manager::resolve_plugininfo_class($info->type);
        if ($info->type !== 'filter' && $class::get_enabled_plugins() === null) {
            return self::result('plugin', $component, self::RESULT_UNKNOWN,
                self::enabled_text($info->type, $plugin['enabled']), null,
                "plugins of type {$info->type} cannot be enabled or disabled", false, true);
        }

        $wanted = self::enabled_state($info->type, $plugin['enabled']);
        if ($wanted === null) {
            return self::result('plugin', $component, self::RESULT_UNKNOWN,
                (string)json_encode($plugin['enabled']), null,
                'enabled must be 1 or 0, or on, off or disabled for a filter', false, true);
        }
        $live = $class::get_enabled_plugin($info->name);
        $result = ((int)$live === $wanted) ? self::RESULT_OK : self::RESULT_CHANGED;
        return self::result('plugin', $component, $result,
            self::enabled_text($info->type, $wanted), self::enabled_text($info->type, $live));
    }

    /**
     * The integer state enable_plugin() takes for a declared `enabled` value, or null if invalid.
     *
     * @param string $type plugin type
     * @param mixed $enabled as declared
     * @return int|null
     */
    public static function enabled_state(string $type, $enabled): ?int {
        if ($type === 'filter') {
            if (is_string($enabled) && isset(self::FILTER_STATES[strtolower($enabled)])) {
                return self::FILTER_STATES[strtolower($enabled)];
            }
            if (is_int($enabled) && in_array($enabled, self::FILTER_STATES, true)) {
                return $enabled;
            }
            return null;
        }
        if ($enabled === true || $enabled === 1 || $enabled === '1') {
            return 1;
        }
        if ($enabled === false || $enabled === 0 || $enabled === '0') {
            return 0;
        }
        return null;
    }

    /**
     * A plugin's enabled state as display text.
     *
     * @param string $type plugin type
     * @param mixed $state integer state, or a declared value
     * @return string
     */
    protected static function enabled_text(string $type, $state): string {
        if ($type === 'filter') {
            $name = array_search((int)$state, self::FILTER_STATES, true);
            if (is_string($state) && !is_numeric($state)) {
                return strtolower($state);
            }
            return $name === false ? (string)$state : $name;
        }
        return ((int)$state === 1 || $state === true) ? 'enabled' : 'disabled';
    }

    // --- roles ---------------------------------------------------------------------------

    /**
     * Compare one declared role with the live role at system context (R8).
     *
     * Produces one item for the role itself when it is missing; otherwise one item for its
     * context levels (when declared) and one per capability where expected or live has a
     * permission.
     *
     * @param array $role {shortname, name?, description?, archetype?, contextlevels?, capabilities?}
     * @return array[] item results
     */
    public function check_role(array $role): array {
        global $DB;
        $shortname = (string)($role['shortname'] ?? '');
        $items = [];

        // An unknown capability blocks apply whether or not the role exists yet. It is
        // reported once here and left out of the per-capability comparison below.
        $unknown = [];
        foreach (array_keys($role['capabilities'] ?? []) as $cap) {
            if (!get_capability_info($cap, false)) {
                $unknown[$cap] = true;
                $items[] = self::result('role', "{$shortname}:{$cap}", self::RESULT_UNKNOWN,
                    (string)$role['capabilities'][$cap], null,
                    'no such capability on this server', false, true);
            }
            $word = $role['capabilities'][$cap];
            if (!is_string($word) || !isset(self::PERMISSIONS[$word])) {
                $items[] = self::result('role', "{$shortname}:{$cap}", self::RESULT_UNKNOWN,
                    (string)$role['capabilities'][$cap], null,
                    'permission must be allow, prevent, prohibit or inherit', false, true);
            }
        }

        $record = $DB->get_record('role', ['shortname' => $shortname]);
        if (!$record) {
            $creatable = !empty($role['name']) && !empty($role['contextlevels']);
            $items[] = self::result('role', "role:{$shortname}", self::RESULT_MISSING,
                $shortname, null,
                $creatable ? 'apply will create it'
                    : 'not on this server, and the declaration lacks name or contextlevels to create it',
                false, !$creatable);
            return $items;
        }

        if (isset($role['contextlevels'])) {
            $declared = self::contextlevel_ids($role['contextlevels']);
            if ($declared === null) {
                $items[] = self::result('role', "role:{$shortname}:contextlevels", self::RESULT_UNKNOWN,
                    implode(', ', (array)$role['contextlevels']), null,
                    'context levels must be system, user, coursecat, course, module or block',
                    false, true);
            } else {
                $live = array_map('intval', array_values(get_role_contextlevels($record->id)));
                sort($live);
                $result = ($declared === $live) ? self::RESULT_OK : self::RESULT_CHANGED;
                $items[] = self::result('role', "role:{$shortname}:contextlevels", $result,
                    self::contextlevel_text($declared), self::contextlevel_text($live));
            }
        }

        $expected = $this->expected_role_capabilities($role, $record->archetype);
        $live = $this->live_role_capabilities((int)$record->id);
        $caps = self::managed_capabilities($role, $record->archetype, $expected, $live);
        $caps = array_diff($caps, array_keys($unknown));
        sort($caps);
        foreach ($caps as $cap) {
            $want = $expected[$cap] ?? CAP_INHERIT;
            $have = $live[$cap] ?? CAP_INHERIT;
            $result = ($want === $have) ? self::RESULT_OK : self::RESULT_CHANGED;
            $items[] = self::result('role', "{$shortname}:{$cap}", $result,
                self::permission_text($want), self::permission_text($have));
        }
        return $items;
    }

    /**
     * Compare a role's declared allow-assign pairs with role_allow_assign (spec 003, R6).
     *
     * Additive: one item per declared pair, `ok` when the row exists and `changed` when apply
     * would add it. Pairs nobody declared are never reported, because the archetypes carry
     * default pairs this repo does not manage. A pair whose role does not exist yet is
     * `missing` but not blocking: apply creates roles first, then adds the pair.
     *
     * @param array $role the role declaration
     * @return array[] item results, keyed 'role:<from>:allowassign:<to>'
     */
    public function check_allowassign(array $role): array {
        global $DB;
        $from = (string)($role['shortname'] ?? '');
        $items = [];
        foreach ($role['allowassign'] ?? [] as $to) {
            $item = "role:{$from}:allowassign:{$to}";
            $fromid = $DB->get_field('role', 'id', ['shortname' => $from]);
            $toid = $DB->get_field('role', 'id', ['shortname' => (string)$to]);
            if (!$fromid || !$toid) {
                $items[] = self::result('role', $item, self::RESULT_MISSING, 'allowed', null,
                    'a role in this pair is not on the server yet; apply adds the pair after creating it');
                continue;
            }
            $exists = $DB->record_exists('role_allow_assign', ['roleid' => $fromid, 'allowassign' => $toid]);
            $items[] = self::result('role', $item, $exists ? self::RESULT_OK : self::RESULT_CHANGED,
                'allowed', $exists ? 'allowed' : null);
        }
        return $items;
    }

    /**
     * The capabilities this declaration manages for a role.
     *
     * A role with no archetype is ours (ltcpublisher): every capability it holds is managed,
     * so one granted by hand shows up as drift. A role with an archetype is Moodle's: only the
     * capabilities it declares are managed. Its other permissions come from the install, and
     * get_default_capabilities() cannot reproduce them, because capabilities that use
     * clonepermissionsfrom are granted at install without being in the archetype (R8).
     *
     * @param array $role the role declaration
     * @param string|null $livearchetype the existing role's archetype
     * @param int[] $expected from expected_role_capabilities()
     * @param int[] $live from live_role_capabilities()
     * @return string[] sorted capability names
     */
    public static function managed_capabilities(array $role, ?string $livearchetype, array $expected,
            array $live): array {
        $archetype = array_key_exists('archetype', $role) ? (string)$role['archetype'] : (string)$livearchetype;
        if ($archetype === '') {
            $caps = array_unique(array_merge(array_keys($expected), array_keys($live)));
        } else {
            $caps = array_keys($role['capabilities'] ?? []);
        }
        sort($caps);
        return $caps;
    }

    /**
     * The system-context permissions a role should hold: its archetype's defaults with the
     * declared overrides laid over them. `inherit` removes a permission. A role with no
     * archetype starts empty (R8). Only managed_capabilities() are compared.
     *
     * @param array $role the role declaration
     * @param string|null $livearchetype the existing role's archetype, used when the
     *     declaration does not name one
     * @return int[] capability => CAP_* (never CAP_INHERIT)
     */
    public function expected_role_capabilities(array $role, ?string $livearchetype = null): array {
        $archetype = array_key_exists('archetype', $role) ? (string)$role['archetype'] : (string)$livearchetype;
        $expected = [];
        foreach (get_default_capabilities($archetype) as $cap => $permission) {
            if ((int)$permission !== CAP_INHERIT) {
                $expected[$cap] = (int)$permission;
            }
        }
        foreach ($role['capabilities'] ?? [] as $cap => $word) {
            if (!is_string($word) || !isset(self::PERMISSIONS[$word])) {
                continue; // Reported as unknown by check_role().
            }
            if (self::PERMISSIONS[$word] === CAP_INHERIT) {
                unset($expected[$cap]);
            } else {
                $expected[$cap] = self::PERMISSIONS[$word];
            }
        }
        ksort($expected);
        return $expected;
    }

    /**
     * The permissions a role holds at system context, read straight from role_capabilities.
     *
     * role_context_capabilities() would merge parent contexts, which is not what drift is
     * comparing. This raw read by the indexed (roleid, contextid) columns is listed in the
     * plugin README (R8, constitution XI).
     *
     * @param int $roleid
     * @return int[] capability => CAP_* (never CAP_INHERIT)
     */
    public function live_role_capabilities(int $roleid): array {
        global $DB;
        $rows = $DB->get_records_menu('role_capabilities',
            ['roleid' => $roleid, 'contextid' => $this->syscontextid], 'capability', 'capability, permission');
        $live = [];
        foreach ($rows as $cap => $permission) {
            if ((int)$permission !== CAP_INHERIT) {
                $live[$cap] = (int)$permission;
            }
        }
        return $live;
    }

    /**
     * Declared context-level words as sorted CONTEXT_* ids, or null if any word is unknown.
     *
     * @param mixed $levels
     * @return int[]|null
     */
    public static function contextlevel_ids($levels): ?array {
        $ids = [];
        foreach ((array)$levels as $level) {
            if (!isset(self::CONTEXTLEVELS[$level])) {
                return null;
            }
            $ids[] = self::CONTEXTLEVELS[$level];
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        return $ids;
    }

    /**
     * @param int[] $ids CONTEXT_* ids
     * @return string
     */
    protected static function contextlevel_text(array $ids): string {
        $names = array_flip(self::CONTEXTLEVELS);
        return implode(', ', array_map(function($id) use ($names) {
            return $names[$id] ?? (string)$id;
        }, $ids));
    }

    /**
     * @param int $permission CAP_*
     * @return string
     */
    protected static function permission_text(int $permission): string {
        $word = array_search($permission, self::PERMISSIONS, true);
        return $word === false ? (string)$permission : $word;
    }

    // --- settings ------------------------------------------------------------------------

    /**
     * Compare one declared setting with its live value (R4, R6, data-model "Item states").
     *
     * @param array $declared {name, value?, secret?}
     * @return array item result
     */
    public function check_setting(array $declared): array {
        $key = (string)($declared['name'] ?? '');
        $secret = !empty($declared['secret']);
        $hasvalue = array_key_exists('value', $declared);
        $value = $hasvalue ? $declared['value'] : null;
        $shown = function($v) use (&$secret) {
            return $secret ? self::SECRET : self::display($v);
        };

        // site_config.py marks the setting itself with env_missing and lists {name, env} in failed_env.
        if (!empty($declared['env_missing'])) {
            return self::result('setting', $key, self::RESULT_ENV_MISSING, null, null,
                "{$declared['env_missing']} is not set; nothing is written for it", $secret);
        }

        $setting = $this->find_setting($key);
        if (!$setting) {
            return self::result('setting', $key, self::RESULT_UNKNOWN, $hasvalue ? $shown($value) : null,
                null, 'not in the admin tree: its plugin is missing, or an upgrade renamed it',
                $secret, true);
        }
        $secret = $secret || self::is_password_setting($setting);

        $live = $setting->get_setting();
        if ($setting->is_readonly()) {
            return self::result('setting', $key, self::RESULT_FORCED, $hasvalue ? $shown($value) : null,
                $shown($live), 'set in config.php, so a database write would do nothing', $secret, true);
        }

        if (!$hasvalue) {
            // A secret in drift mode: compare only set versus empty (R3).
            $empty = ($live === null || $live === '' || $live === []);
            return self::result('setting', $key, $empty ? self::RESULT_CHANGED : self::RESULT_OK,
                self::SECRET, $empty ? '' : self::SECRET, $empty ? 'declared but empty on the server' : '',
                true);
        }

        $want = $this->normalise_declared($setting, $value);
        $have = self::normalise_live($setting, $live);
        $result = ($live !== null && $want === $have) ? self::RESULT_OK : self::RESULT_CHANGED;
        return self::result('setting', $key, $result, $shown($want), $live === null ? null : $shown($have),
            $live === null ? 'never set on this server' : '', $secret);
    }

    /**
     * A declared value in the canonical form compared with the live one: a string, or a
     * sorted list of strings for a multi-value setting.
     *
     * For a multi-value setting whose choices are role ids (tool_dataprivacy/dporoles), a
     * declared role shortname is resolved to its id, so the declaration can name roles
     * portably rather than by a per-server database id.
     *
     * @param admin_setting $setting
     * @param mixed $value as declared
     * @return string|string[]
     */
    public function normalise_declared(admin_setting $setting, $value) {
        if (!is_array($value) && !self::is_multi($setting)) {
            return (string)$value;
        }
        $values = [];
        foreach ((array)$value as $v) {
            if ((string)$v !== '') { // An empty declared value means an empty list.
                $values[] = $this->resolve_choice($setting, (string)$v);
            }
        }
        $values = array_values(array_unique($values));
        sort($values, SORT_STRING);
        return $values;
    }

    /**
     * A declared value in the form write_setting() expects for this setting. Pure: the
     * applier passes the result to write_setting(); this class does not.
     *
     * @param admin_setting $setting
     * @param mixed $value as declared
     * @return string|array
     */
    public function write_data(admin_setting $setting, $value) {
        $canonical = $this->normalise_declared($setting, $value);
        if (!is_array($canonical)) {
            return $canonical;
        }
        if ($setting instanceof admin_setting_configmulticheckbox) {
            return array_fill_keys($canonical, 1);
        }
        return $canonical;
    }

    /**
     * A live value in canonical form.
     *
     * @param admin_setting $setting
     * @param mixed $live from get_setting()
     * @return string|string[]
     */
    public static function normalise_live(admin_setting $setting, $live) {
        if (is_bool($live)) {
            $live = $live ? '1' : '0'; // A checkbox default is often false; it is stored as '0'.
        }
        if ($live === '' && $setting instanceof \admin_setting_configcheckbox) {
            $live = '0'; // An unchecked checkbox, as a default of '' and as stored.
        }
        if (!self::is_multi($setting) && !is_array($live)) {
            return (string)$live;
        }
        if ($setting instanceof admin_setting_configmulticheckbox) {
            // get_setting() returns option => 1 for each checked option.
            $live = array_keys(array_filter((array)$live));
        }
        $values = array_values(array_map('strval', (array)$live));
        $values = array_values(array_unique($values));
        sort($values, SORT_STRING);
        return $values;
    }

    /**
     * Whether a setting stores a list of values.
     *
     * @param admin_setting $setting
     * @return bool
     */
    protected static function is_multi(admin_setting $setting): bool {
        return $setting instanceof admin_setting_configmulticheckbox
            || $setting instanceof \admin_setting_configmultiselect;
    }

    /**
     * Map a declared role shortname to the role id a multi-value setting's choices use.
     * Any other value is returned unchanged.
     *
     * @param admin_setting $setting
     * @param string $value
     * @return string
     */
    protected function resolve_choice(admin_setting $setting, string $value): string {
        global $DB;
        if (method_exists($setting, 'load_choices')) {
            $setting->load_choices(); // Lazily filled on some setting classes; a read.
        }
        if (is_numeric($value) || !property_exists($setting, 'choices') || !is_array($setting->choices)
                || array_key_exists($value, $setting->choices)) {
            return $value;
        }
        $roleid = $DB->get_field('role', 'id', ['shortname' => $value]);
        if ($roleid && array_key_exists($roleid, $setting->choices)) {
            return (string)$roleid;
        }
        return $value;
    }

    /**
     * A value as display text.
     *
     * @param mixed $value
     * @return string|null
     */
    protected static function display($value): ?string {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return '[' . implode(', ', array_map('strval', $value)) . ']';
        }
        return (string)$value;
    }

    // --- course discussions (spec 012, R5) -------------------------------------------------

    /**
     * Every publisher-owned course, with its discussion forum and the group mode the
     * declaration implies for it.
     *
     * A course is publisher-owned when its idnumber is ltct:<slug> (util::IDNUMBER_PREFIX).
     * The course read is a lookup by that indexed column, as util::course_by_idnumber()
     * does; the forum is found by its course-module idnumber through util::cm_by_idnumber().
     *
     * @return array[] each {slug, course (id, idnumber, groupmode, groupmodeforce), cm|null,
     *     groupmode}
     */
    public function discussion_targets(): array {
        global $DB;
        $prefix = \local_ltuse\util::IDNUMBER_PREFIX;
        $courses = $DB->get_records_select('course', $DB->sql_like('idnumber', ':prefix'),
            ['prefix' => $DB->sql_like_escape($prefix) . '%'], 'idnumber',
            'id, idnumber, groupmode, groupmodeforce');
        $targets = [];
        foreach ($courses as $course) {
            $slug = substr($course->idnumber, strlen($prefix));
            if ($slug === '' || strpos($slug, ':') !== false) {
                continue; // Not a course identity (ltct:<slug>), so not ours to judge.
            }
            $targets[] = [
                'slug' => $slug,
                'course' => $course,
                'cm' => \local_ltuse\util::cm_by_idnumber((int)$course->id,
                    $course->idnumber . self::DISCUSSION_SUFFIX),
                'groupmode' => \local_ltuse\external\ensure_discussion::wanted_groupmode(),
            ];
        }
        return $targets;
    }

    /**
     * Every ltct: course whose own "Show activity reports" is on (spec 003, research R2).
     *
     * With reports on, a mentor sees the learner's assignment submissions and logs through
     * core's reports. moodle/site/settings/mentoring.yaml turns the course default off, and
     * the publisher resets each course on publish, but any editing teacher can turn a course's
     * own setting on in between. One `changed` item per such course; apply turns it off again.
     * Courses with reports off produce no item. Read only.
     *
     * @return array[] item results, keyed 'course:<idnumber>:showreports'; none is blocking
     */
    public function check_course_reports(): array {
        global $DB;
        $prefix = \local_ltuse\util::IDNUMBER_PREFIX;
        $courses = $DB->get_records_select('course',
            $DB->sql_like('idnumber', ':prefix') . ' AND showreports <> 0',
            ['prefix' => $DB->sql_like_escape($prefix) . '%'], 'idnumber', 'id, idnumber, showreports');
        $items = [];
        foreach ($courses as $course) {
            $slug = substr($course->idnumber, strlen($prefix));
            if ($slug === '' || strpos($slug, ':') !== false) {
                continue; // Not a course identity (ltct:<slug>).
            }
            $item = self::result('course', "course:{$course->idnumber}:showreports", self::RESULT_CHANGED,
                '0', (string)$course->showreports,
                'activity reports are on, so a mentor would see submissions and logs');
            $item['courseid'] = (int)$course->id;
            $items[] = $item;
        }
        return $items;
    }

    /**
     * Every ltct: course whose group mode is not 0 (spec 002 R3, amended 2026-10-02).
     *
     * Shared courses are open across organisations, so a course in separate or visible groups
     * was published before the amendment or changed by hand. One `changed` item per such
     * course; apply sets 0 through update_course(), as for showreports. An activity's own
     * group mode is not checked: a course may use groups in one activity for teaching
     * (FR-011). Read only.
     *
     * @return array[] item results, keyed 'course:<idnumber>:groupmode'; none is blocking
     */
    public function check_course_groupmodes(): array {
        global $DB;
        $prefix = \local_ltuse\util::IDNUMBER_PREFIX;
        $courses = $DB->get_records_select('course',
            $DB->sql_like('idnumber', ':prefix') . ' AND groupmode <> 0',
            ['prefix' => $DB->sql_like_escape($prefix) . '%'], 'idnumber', 'id, idnumber, groupmode');
        $items = [];
        foreach ($courses as $course) {
            $slug = substr($course->idnumber, strlen($prefix));
            if ($slug === '' || strpos($slug, ':') !== false) {
                continue; // Not a course identity (ltct:<slug>).
            }
            $item = self::result('course', "course:{$course->idnumber}:groupmode", self::RESULT_CHANGED,
                self::groupmode_text(NOGROUPS, null), self::groupmode_text((int)$course->groupmode, null),
                'shared courses are open across organisations; apply sets no groups');
            $item['courseid'] = (int)$course->id;
            $items[] = $item;
        }
        return $items;
    }

    /**
     * Any organisation's managers cohort synced into a shared course (spec 002 R2). Blocking.
     *
     * With no groups, orgmanager's course-wide capabilities would show that manager every
     * organisation's participants and completion in the course. A managers cohort belongs only
     * in its organisation's own courses, under ltct:org:<key>. A COUNT only: the result names
     * no course, cohort or person (constitution III). cohort.idnumber and
     * course_categories.idnumber are not indexed in core; the README lists this read.
     *
     * @return array[] none, or one blocking item
     */
    public function check_shared_managers(): array {
        global $DB;
        $params = [
            'cohort' => 'cohort',
            'managers' => $DB->sql_like_escape('ltct:org:') . '%' . $DB->sql_like_escape(':managers'),
            'course' => $DB->sql_like_escape(\local_ltuse\util::IDNUMBER_PREFIX) . '%',
            'orgcat' => $DB->sql_like_escape('ltct:org:') . '%',
        ];
        $sql = "SELECT COUNT(1)
                  FROM {enrol} e
                  JOIN {cohort} h ON h.id = e.customint1
                  JOIN {course} c ON c.id = e.courseid
             LEFT JOIN {course_categories} cc ON cc.id = c.category
                 WHERE e.enrol = :cohort
                   AND " . $DB->sql_like('h.idnumber', ':managers') . "
                   AND " . $DB->sql_like('c.idnumber', ':course') . "
                   AND (cc.idnumber IS NULL OR " . $DB->sql_like('cc.idnumber', ':orgcat', true, true, true) . ")";
        $count = (int)$DB->count_records_sql($sql, $params);
        if ($count === 0) {
            return [];
        }
        return [self::result('enrol', 'enrol:shared-managers', self::RESULT_SHARED_MANAGERS, '0',
            (string)$count,
            'a managers cohort is synced into a course organisations share, which would show its '
                . 'managers every organisation\'s people; run local/ltuse/cli/open_courses.php, '
                . 'or remove the cohort sync by hand', false, true)];
    }

    /**
     * Compare one course's discussion forum with the declaration (contracts/site-declaration.md).
     *
     * Kinds: differs (apply sets no groups), missing (the publisher creates it, apply never
     * does), forced (warning: the course forces a group mode, which would wall the forum).
     * Nothing here reads a discussion, a post or an author (constitution III).
     *
     * @param array $target one entry of discussion_targets()
     * @return array[] item results; none is blocking
     */
    public function check_discussion(array $target): array {
        $course = $target['course'];
        // Subjects are idnumbers: the forum's for its group mode, the course's when the course
        // forces one.
        $item = $course->idnumber . self::DISCUSSION_SUFFIX;
        $cm = $target['cm'];
        $want = self::groupmode_text((int)$target['groupmode'], 0);

        if ($cm === null) {
            return [self::result('discussion', $item, self::RESULT_MISSING, $want, null,
                'the course has no discussion forum; republish it to create one (apply never does)')];
        }
        if ($cm->modname !== 'forum') {
            return [self::result('discussion', $item, self::RESULT_UNKNOWN, $want, $cm->modname,
                $course->idnumber . self::DISCUSSION_SUFFIX . ' is a ' . $cm->modname
                    . ', not a forum; remove it in Moodle and republish')];
        }

        $items = [];
        if (!empty($course->groupmodeforce)) {
            $items[] = self::warning(self::result('discussion', $course->idnumber, self::RESULT_FORCED,
                $want, 'course forces ' . self::groupmode_text((int)$course->groupmode, null),
                'the course forces a group mode, which would wall the course forum '
                    . '(Course settings > Groups > Force); turn the force off'));
        }

        $have = self::groupmode_text((int)$cm->groupmode, (int)$cm->groupingid);
        $same = (int)$cm->groupmode === (int)$target['groupmode'] && (int)$cm->groupingid === 0;
        $items[] = self::result('discussion', $item, $same ? self::RESULT_OK : self::RESULT_DIFFERS,
            $want, $have, $same ? '' : 'not open to the whole course; apply or the next publish sets no groups');
        return $items;
    }

    /**
     * Mark an item as a warning: reported, never written, and never a failure of apply.
     *
     * @param array $result
     * @return array
     */
    protected static function warning(array $result): array {
        $result['warning'] = true;
        return $result;
    }

    /**
     * A group mode, and optionally a grouping, as display text.
     *
     * @param int $groupmode NOGROUPS, SEPARATEGROUPS or VISIBLEGROUPS
     * @param int|null $groupingid null to leave the grouping out
     * @return string
     */
    public static function groupmode_text(int $groupmode, ?int $groupingid): string {
        $names = [NOGROUPS => 'no groups', SEPARATEGROUPS => 'separate groups', VISIBLEGROUPS => 'visible groups'];
        $text = $names[$groupmode] ?? (string)$groupmode;
        if ($groupingid !== null) {
            $text .= $groupingid === 0 ? ', no grouping' : ", grouping {$groupingid}";
        }
        return $text;
    }
}
