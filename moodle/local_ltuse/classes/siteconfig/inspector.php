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
 *   roles      [{shortname, name?, description?, archetype?, contextlevels?, capabilities?}]
 *   settings   [{name, value?, secret?}]   value is absent for a secret in drift mode
 *   failed_env [{name, env}, ...]         settings whose env: variable was not set (each also carries env_missing)
 *
 * Each check returns item results of this shape, which the report class renders:
 *
 *   type      'release' | 'plugin' | 'role' | 'setting'
 *   item      setting key, plugin component, 'role:<shortname>' or '<shortname>:<capability>'
 *   result    one of the RESULT_* constants
 *   declared  what the declaration says, as display text (null when not applicable)
 *   live      what the server holds, as display text (null when absent)
 *   message   extra detail for a human, or ''
 *   secret    true when declared and live must be shown as <secret>
 *   blocking  true when apply must write nothing at all (data-model "Run report")
 *
 * APIs used, all confirmed on MOODLE_502_STABLE (research.md R4, R6, R7, R8):
 * admin_get_root(), admin_setting::get_setting()/is_readonly(), core_plugin_manager,
 * plugininfo::get_enabled_plugin(), get_default_capabilities(), get_capability_info(),
 * get_role_contextlevels(). The one raw read is role_capabilities by roleid and
 * contextid, which is listed in the plugin README (constitution XI).
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
     * settings (data-model "Run report"). Undeclared state (extra plugins, unmanaged
     * settings) is the drift class's job, not this one.
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
        foreach ($this->declaration['settings'] ?? [] as $setting) {
            $items[] = $this->check_setting($setting);
        }
        return $items;
    }

    /**
     * Whether any item means apply must write nothing.
     *
     * @param array[] $items
     * @return bool
     */
    public static function has_blocking(array $items): bool {
        foreach ($items as $item) {
            if (!empty($item['blocking'])) {
                return true;
            }
        }
        return false;
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
}
