<?php
namespace local_ltuse\protection;

defined('MOODLE_INTERNAL') || die();

use core_user;
use moodle_exception;
use stdClass;

/**
 * The one place a protection level is applied (spec 016, contracts/protection-service.md).
 *
 * Protection is per person and only for people who ask (Doug, 2026-10-05 (scope review)):
 * there is no organisation minimum, so a person's level is the one set for them and nothing
 * else ever raises it.
 *
 * For a protected user, the plugin writes the protected display into the account itself
 * (research R1): core renders every name live from the user record, on the web and in the app,
 * and no Moodle 5.2 hook overrides a display name. The real values move to
 * local_ltuse_protection first, field by field (R5), and come back on a lowering, except the
 * picture, which core deletes (R6).
 *
 * The granting page, the web service, the hook, the observer and both tasks call this class, so
 * a level is applied one way whatever triggered it:
 *
 *   set_protection()      one person's level, pseudonym and corrections (callers check
 *                         entitlement::can_manage_protection() first; this checks only what
 *                         a manager who is not the site team may change)
 *   apply()               re-apply one protected user's level after drift: the adhoc and
 *                         reconcile tasks
 *   sync_log_blocks()     the course-log block, in the courses of those who asked for it
 *   effective_level(), is_settled(), is_protected(), real_identity(): read only, named for
 *                         specs 006 and 008
 *
 * Every write to one user runs under a per-user lock and one delegated transaction, with the
 * user in a private bypass set so this plugin's own hook and observer leave the write alone
 * (R2). The set is emptied in a finally block, so an exception never leaves it open.
 *
 * Core writes go through core APIs: user_update_user(), profile_save_data(),
 * core_user::update_picture(), message_send(). mod_scheduler's slot class re-saves calendar
 * names (R15); the plugin README lists it as a Principle XI exception.
 */
class service {

    /** Tables. */
    const TABLE = 'local_ltuse_protection';
    const LOGTABLE = 'local_ltuse_protection_log';

    /** Where site_config.py apply stores protection.yaml (siteconfig\protection). */
    const CONFIG = 'protection';

    /** The profile field this spec reads by name: the organisation, for entitlement path 3. */
    const ORGFIELD = 'ltct_org';

    /** The lock type; the resource is the user id. */
    const LOCKTYPE = 'local_ltuse_protection';
    const LOCKWAIT = 10;

    /**
     * The login methods a protected account may have (scope review change 4, R3): the site's
     * own, or none. Any other is an outside login that may write the real name back, so write()
     * and the hook set it to AUTH, and drifted() reports it.
     */
    const AUTH = 'manual';
    const SAFE_AUTH = ['manual', 'nologin'];

    /**
     * The course-log block a protected person may ask for (scope review change 5, R14): these
     * roles are prohibited these capabilities in each course the person is enrolled in.
     * local_ltuse owns every course-level prohibit of them for these roles (sync_log_blocks()).
     */
    const LOG_ROLES = ['editingteacher', 'teacher'];
    const LOG_CAPABILITIES = ['report/log:view', 'report/log:viewtoday', 'report/loglive:view'];

    /** @var array<int, true> users this class is writing now: the hook and observer skip them */
    private static $bypass = [];

    /** @var array<int, bool> is_protected() for this request; emptied by every write */
    private static $protected = [];

    // --- reading -----------------------------------------------------------------------------

    /**
     * Is this user being written by the service right now? The hook and observer ask.
     *
     * @param int $userid
     * @return bool
     */
    public static function in_bypass(int $userid): bool {
        return isset(self::$bypass[$userid]);
    }

    /**
     * The stored protection.yaml, or null when site_config.py apply has never stored it. Until
     * then nothing but none can be applied.
     *
     * @return array|null {levels, withhold, neutral_surname, reconcile_minutes}
     */
    public static function config(): ?array {
        $json = get_config('local_ltuse', self::CONFIG);
        $config = $json ? json_decode($json, true) : null;
        return is_array($config) && isset($config['withhold']) ? $config : null;
    }

    /**
     * Can this level be applied on this site now? Read only and needs no user, so spec 008's
     * intake preview can say "waits" before any account exists. False for every level until
     * apply has stored protection.yaml, except none; then true for every level.
     *
     * @param string $level
     * @return bool
     */
    public static function level_available(string $level): bool {
        if ($level === levels::NONE) {
            return true;
        }
        return self::config() !== null && levels::is_level($level);
    }

    /**
     * @param int $userid
     * @return stdClass|null the user's protection row
     */
    public static function row(int $userid): ?stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['userid' => $userid]) ?: null;
    }

    /**
     * Is the user protected: is a level above none applied to their account?
     *
     * @param int $userid
     * @return bool
     */
    public static function is_protected(int $userid): bool {
        if (!array_key_exists($userid, self::$protected)) {
            $row = self::table_exists() ? self::row($userid) : null;
            self::$protected[$userid] = $row && $row->effectivelevel !== levels::NONE;
        }
        return self::$protected[$userid];
    }

    /**
     * The real identity. Callers ask entitlement::can_view_identity() first; nothing here checks.
     *
     * @param int $userid
     * @return array|null {firstname, lastname, level}, null when not protected
     */
    public static function real_identity(int $userid): ?array {
        $row = self::row($userid);
        if (!$row || $row->effectivelevel === levels::NONE) {
            return null;
        }
        return ['firstname' => (string)$row->realfirstname, 'lastname' => (string)$row->reallastname,
            'level' => (string)$row->effectivelevel];
    }

    /**
     * The user's organisation key, as stored in ltct_org; '' when none.
     *
     * @param int $userid
     * @return string
     */
    public static function user_org(int $userid): string {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $fields = profile_user_record($userid, false);
        return isset($fields->{self::ORGFIELD}) ? trim((string)$fields->{self::ORGFIELD}) : '';
    }

    /**
     * Forget every cached read: protection flags and entitlements. Cheap; the next read goes to the
     * database. Cron runs many tasks in one process, so apply() calls it first.
     */
    public static function reset_caches(): void {
        self::$protected = [];
        entitlement::reset_cache();
    }

    /**
     * The user's level: the one set for them, none when they have no row. Spec 008 gates
     * enrolment on it.
     *
     * @param int $userid
     * @return string
     */
    public static function effective_level(int $userid): string {
        $row = self::table_exists() ? self::row($userid) : null;
        return $row && levels::is_level($row->effectivelevel) ? (string)$row->effectivelevel : levels::NONE;
    }

    /**
     * Does the account show its protected state? Spec 008 gates enrolment on it; when false,
     * call apply(). Always true for someone with no protection.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_settled(int $userid): bool {
        return !self::table_exists() || !self::drifted($userid, self::row($userid));
    }

    /**
     * Might others have seen the user under their current name (R13)? True once they have
     * logged in (user.firstaccess) or are enrolled in any course, active or not. A cheap,
     * cautious signal (scope review change 16): it reads no log and no message.
     *
     * @param int $userid
     * @return bool
     */
    public static function has_activity(int $userid): bool {
        // Not cached, like mentoring::role_id(): an enrolment made earlier in the same request,
        // or a user id reused after a PHPUnit reset, would otherwise read as no activity.
        $user = core_user::get_user($userid, 'id, firstaccess');
        return ($user && (int)$user->firstaccess > 0)
            || (bool)enrol_get_all_users_courses($userid, false, 'id');
    }

    // --- changing one person -------------------------------------------------------------------

    /**
     * Set one user's level (contracts/protection-service.md, "local_ltuse_set_protection").
     * NO PERMISSION CHECK: callers check entitlement::can_manage_protection(), and accept
     * corrections only from someone who can_view_identity(). What the actor may do once there
     * is checked here, so the page, the web service and spec 008 agree: anyone but the site
     * team may only grant at intake (levels::manager_may(), scope review change 14).
     *
     * A raise needs two recorded facts (scope review changes 13 and 2): `requested`, the person
     * asked for it, and `emailchecked`, the granter confirmed the account's email address
     * identifies neither the person nor their organisation. Both go on the log row.
     *
     * At firstname or pseudonym a username that holds the real name is replaced with a neutral
     * one (neutral_username()); the person still signs in with their email (change 15).
     *
     * `hidelogs`, when the person asks for their location to be hidden from course staff,
     * blocks course logs in the courses they take (sync_log_blocks(), change 5). Absent, the
     * current choice is kept. Changing it at the same level is a correction, so the site team's.
     *
     * @param int $userid
     * @param string $level none, email, firstname or pseudonym
     * @param array $options requested, emailchecked (both required for a raise), pseudonym,
     *                       realfirstname, reallastname, realfields (field => value),
     *                       acknowledgehistory, hidelogs
     * @param int|null $actorid who is making the change; the current user when null
     * @return array {effectivelevel, warnings: string[]}
     * @throws moodle_exception protection:err:* when refused
     */
    public static function set_protection(int $userid, string $level, array $options = [], ?int $actorid = null): array {
        global $DB, $USER;
        $actorid = $actorid ?? (int)$USER->id;
        self::require_config();
        if (!levels::is_level($level)) {
            throw new moodle_exception('protection:err:level', 'local_ltuse');
        }
        $user = core_user::get_user($userid);
        if (!$user || $user->deleted || isguestuser($user)) {
            throw new moodle_exception('protection:err:nouser', 'local_ltuse');
        }
        $row = self::row($userid);
        $from = $row ? (string)$row->effectivelevel : levels::NONE;
        $requested = !empty($options['requested']);
        $emailchecked = !empty($options['emailchecked']);
        if (levels::is_raise($from, $level) && !$requested) {
            throw new moodle_exception('protection:err:notrequested', 'local_ltuse');
        }
        if (levels::is_raise($from, $level) && !$emailchecked) {
            throw new moodle_exception('protection:err:emailnotchecked', 'local_ltuse');
        }

        [$basefirst, $baselast] = self::base_real_names($user, $row);
        $realfirst = self::option_text($options, 'realfirstname') ?? $basefirst;
        $reallast = self::option_text($options, 'reallastname') ?? $baselast;
        $corrections = isset($options['realfields']) && is_array($options['realfields']) ? $options['realfields'] : [];
        if (!entitlement::is_site_team($actorid, $userid) && !levels::manager_may($from, $level,
                self::has_activity($userid), $realfirst !== $basefirst || $reallast !== $baselast || $corrections)) {
            throw new moodle_exception('protection:err:siteteam', 'local_ltuse');
        }
        $pseudonym = self::option_text($options, 'pseudonym') ?? ($row ? (string)$row->pseudonym : '');
        if ($level === levels::PSEUDONYM) {
            $others = $DB->get_fieldset_select(self::TABLE, 'pseudonym', "userid <> :userid AND pseudonym <> ''",
                ['userid' => $userid]);
            $problems = levels::pseudonym_problems($pseudonym, $realfirst, $reallast, $others);
            if ($problems) {
                throw new moodle_exception('protection:err:pseudonym', 'local_ltuse', '',
                    implode(', ', array_map(function($p) {
                        return get_string('protection:pseudonym:' . $p, 'local_ltuse');
                    }, $problems)));
            }
        }
        if (levels::needs_acknowledgement($from, $level, self::has_activity($userid)) &&
                empty($options['acknowledgehistory'])) {
            throw new moodle_exception('protection:err:needsack', 'local_ltuse');
        }
        $newusername = levels::rank($level) >= levels::rank(levels::FIRSTNAME) &&
            levels::username_reveals((string)$user->username, $realfirst, $reallast) ? self::neutral_username() : null;
        $hadlogblock = $row && !empty($row->hidelogs);
        $hidelogs = $level !== levels::NONE
            && (array_key_exists('hidelogs', $options) ? !empty($options['hidelogs']) : $hadlogblock);

        $target = [
            'ownlevel' => $level,
            'effectivelevel' => $level,
            'pseudonym' => trim($pseudonym),
            'realfirstname' => $realfirst,
            'reallastname' => $reallast,
            'corrections' => $corrections,
            'hidelogs' => $hidelogs,
            'requested' => $requested,
            'emailchecked' => $emailchecked,
        ];
        // FR-008: every change is recorded. Same level, but a corrected name or held value, a new
        // pseudonym, a changed log block or a new username, is a correction.
        $corrected = $row && ($realfirst !== $basefirst || $reallast !== $baselast || $corrections
            || trim($pseudonym) !== (string)$row->pseudonym || $hidelogs !== $hadlogblock) || $newusername !== null;
        $logsource = $from !== $level ? levels::SOURCE_OWN : ($corrected ? levels::SOURCE_CORRECTION : null);
        self::write($user, $row, $target, $actorid, $logsource, $newusername);

        $warnings = [];
        if ($from !== $level && self::has_activity($userid)) {
            $warnings[] = get_string('protection:warn:norecall', 'local_ltuse');
        }
        if ($hidelogs || $hadlogblock) {
            try {
                self::sync_log_blocks();
            } catch (\Throwable $e) {
                // The level is saved; the hourly reconcile applies the block.
                debugging('local_ltuse: could not sync course-log blocks: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $warnings[] = get_string('protection:warn:logblock', 'local_ltuse');
            }
        }
        return ['effectivelevel' => $level, 'warnings' => $warnings];
    }

    /**
     * Re-apply one protected user's level where the account has drifted from it (R2). Changes
     * no level, so it never raises or lowers anyone, and logs nothing: it is a repair.
     *
     * @param int $userid
     * @return string the effective level applied
     */
    public static function apply(int $userid): string {
        self::reset_caches();   // Cron runs tasks back to back in one process.
        $user = core_user::get_user($userid);
        if (!$user || $user->deleted || isguestuser($user) || !self::table_exists()) {
            return levels::NONE;
        }
        $row = self::row($userid);
        if (!$row || $row->effectivelevel === levels::NONE) {
            return levels::NONE;
        }
        $level = (string)$row->effectivelevel;
        if (!self::config() || !self::drifted($userid, $row)) {
            return $level; // Nothing can be applied until apply has stored protection.yaml.
        }
        self::write($user, $row, self::target($row), (int)$row->usermodified, null, null);
        return $level;
    }

    // --- the account ----------------------------------------------------------------------------

    /**
     * The values the hook re-applies on every user_update_user() for a protected user (R2):
     * names, alternate names, maildisplay and the withheld user columns.
     *
     * @param stdClass $row the user's protection row
     * @return array column => value
     */
    public static function protected_columns(stdClass $row): array {
        $config = self::config();
        if (!$config || $row->effectivelevel === levels::NONE) {
            return [];
        }
        $split = levels::split(levels::withheld((array)$config['withhold'], (string)$row->effectivelevel));
        $out = [];
        if ($split['names'] || $split['firstname']) {
            [$first, $last] = levels::display((string)$row->effectivelevel, (string)$row->realfirstname,
                (string)$row->reallastname, (string)$row->pseudonym, (string)($config['neutral_surname'] ?? ''));
            $out['firstname'] = $first;
            if ($split['names']) {
                $out['lastname'] = $last;
            }
        }
        if ($split['maildisplay']) {
            $out['maildisplay'] = core_user::MAILDISPLAY_HIDE;
        }
        foreach ($split['columns'] as $column) {
            $out[$column] = '';
        }
        return $out;
    }

    /**
     * Does the account differ from its protected state? Names and user columns, the login
     * guard (R3), the withheld profile fields, and the picture.
     *
     * @param int $userid
     * @param stdClass|null $row
     * @return bool
     */
    public static function drifted(int $userid, ?stdClass $row): bool {
        global $CFG;
        if (!$row || $row->effectivelevel === levels::NONE) {
            return false;
        }
        $user = core_user::get_user($userid);
        if (!$user) {
            return false;
        }
        foreach (self::protected_columns($row) as $column => $value) {
            if ((string)($user->$column ?? '') !== (string)$value) {
                return true;
            }
        }
        if (!in_array((string)$user->auth, self::SAFE_AUTH, true) || self::linked_logins($userid)) {
            return true;
        }
        $config = self::config();
        $split = levels::split(levels::withheld((array)($config['withhold'] ?? []), (string)$row->effectivelevel));
        if ($split['picture'] && !empty($user->picture)) {
            return true;
        }
        if ($split['profile']) {
            require_once($CFG->dirroot . '/user/profile/lib.php');
            $profile = profile_user_record($userid, false);
            foreach ($split['profile'] as $field) {
                if (isset($profile->$field) && (string)$profile->$field !== '' && (string)$profile->$field !== '0') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Write one user's protected state: snapshot, account, picture, row, log, caches, notice.
     *
     * @param stdClass $user the account as it is now
     * @param stdClass|null $row the protection row, null when the user has none
     * @param array $target ownlevel, effectivelevel, pseudonym, realfirstname, reallastname, corrections,
     *                      hidelogs, and for the log, requested and emailchecked
     * @param int $actorid
     * @param string|null $logsource null to write no log row (a repair)
     * @param string|null $newusername a neutral username to apply, or null to keep the username
     */
    protected static function write(stdClass $user, ?stdClass $row, array $target, int $actorid,
            ?string $logsource, ?string $newusername): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $userid = (int)$user->id;
        $config = self::require_config();

        $lock = \core\lock\lock_config::get_lock_factory(self::LOCKTYPE)->get_lock('user' . $userid, self::LOCKWAIT);
        if (!$lock) {
            throw new moodle_exception('protection:err:busy', 'local_ltuse');
        }
        $level = (string)$target['effectivelevel'];
        $from = levels::NONE;
        try {
            $transaction = $DB->start_delegated_transaction();
            // Fresh, under the lock: another write may have run while this one waited, so the
            // starting level, the held values and the real names all come from here.
            $user = core_user::get_user($userid, '*', MUST_EXIST);
            $row = self::row($userid);
            $from = $row ? (string)$row->effectivelevel : levels::NONE;
            if ($logsource === null && $from !== $level) {
                $logsource = levels::SOURCE_OWN;
            }
            $profile = profile_user_record($userid, false);
            $datatypes = $DB->get_records_menu('user_info_field', null, '', 'shortname, datatype');

            // 1. The real names: from the account while it still shows them, else from the
            // row; a correction wins, and an empty value never overwrites a held one.
            [$basefirst, $baselast] = self::base_real_names($user, $row);
            $realfirst = trim((string)($target['realfirstname'] ?? '')) ?: $basefirst;
            $reallast = trim((string)($target['reallastname'] ?? '')) ?: $baselast;

            // 2. Snapshot every field that becomes withheld now and is not already held (R5);
            // restore every held field that is no longer withheld. Never the picture.
            $held = $row && $row->realfields ? (array)json_decode($row->realfields, true) : [];
            $withheld = levels::withheld((array)$config['withhold'], $level);
            $split = levels::split($withheld);
            $now = time();
            foreach (array_merge($split['columns'], $split['profile'], $split['maildisplay'] ? [levels::MAILDISPLAY] : []) as $field) {
                if (array_key_exists($field, $held)) {
                    continue;
                }
                $value = in_array($field, $split['profile'], true) ? ($profile->$field ?? '') : ($user->$field ?? '');
                $held[$field] = ['value' => (string)$value, 'taken' => $now];
            }
            foreach ((array)($target['corrections'] ?? []) as $field => $value) {
                if (array_key_exists($field, $held) && trim((string)$value) !== '') {
                    $held[$field] = ['value' => (string)$value, 'taken' => $now];
                }
            }
            $restore = [];
            foreach ($held as $field => $entry) {
                if (!in_array($field, $withheld, true)) {
                    $restore[$field] = (string)($entry['value'] ?? '');
                    unset($held[$field]);
                }
            }

            // 3. The account, with the user in the bypass set.
            [$first, $last] = levels::display($level, $realfirst, $reallast, (string)$target['pseudonym'],
                (string)($config['neutral_surname'] ?? ''));
            $update = (object)['id' => $userid];
            if ($split['names'] || $split['firstname'] || $from !== levels::NONE) {
                $update->firstname = $split['firstname'] ? $first : $realfirst;
                $update->lastname = $split['names'] ? $last : $reallast;
            }
            $update->maildisplay = $split['maildisplay'] ? core_user::MAILDISPLAY_HIDE
                : (int)($restore[levels::MAILDISPLAY] ?? $user->maildisplay);
            foreach ($split['columns'] as $column) {
                $update->$column = '';
            }
            $profilewrite = (object)['id' => $userid];
            foreach ($split['profile'] as $field) {
                if (isset($datatypes[$field])) {
                    $profilewrite->{'profile_field_' . $field} = $datatypes[$field] === 'checkbox' ? 0 : '';
                }
            }
            foreach ($restore as $field => $value) {
                if ($field === levels::MAILDISPLAY) {
                    continue;
                } else if (in_array($field, levels::ALTNAMES, true) || in_array($field, levels::CORE_FIELDS, true)) {
                    $update->$field = $value;
                } else if (isset($datatypes[$field])) {
                    $profilewrite->{'profile_field_' . $field} = $value;
                }
            }
            if ($newusername !== null) {
                $update->username = $newusername;
            }
            // The login guard (R3): a protected account signs in only through the site itself.
            if ($level !== levels::NONE && !in_array((string)$user->auth, self::SAFE_AUTH, true)) {
                $update->auth = self::AUTH;
            }
            self::$bypass[$userid] = true;
            try {
                user_update_user($update, false, true);
                profile_save_data($profilewrite);
                // 4. The picture, only when entering firstname or pseudonym (R6).
                if ($split['picture'] && !empty($user->picture)) {
                    core_user::update_picture((object)['id' => $userid, 'deletepicture' => 1]);
                }
                if ($level !== levels::NONE) {
                    foreach (self::linked_logins($userid) as $login) {
                        $login->delete();
                    }
                }
            } finally {
                unset(self::$bypass[$userid]);
            }

            // 5. The row: kept while a level is applied; gone once back at none.
            $record = (object)[
                'userid' => $userid,
                'ownlevel' => (string)$target['ownlevel'],
                'effectivelevel' => $level,
                'pseudonym' => (string)$target['pseudonym'],
                'realfirstname' => \core_text::substr($realfirst, 0, 100),
                'reallastname' => \core_text::substr($reallast, 0, 100),
                'realfields' => $held ? json_encode($held) : null,
                'hidelogs' => (int)!empty($target['hidelogs']),
                'timemodified' => $now,
                'usermodified' => $actorid,
            ];
            if ($level === levels::NONE) {
                if ($row) {
                    $DB->delete_records(self::TABLE, ['id' => $row->id]);
                }
            } else if ($row) {
                $record->id = $row->id;
                $DB->update_record(self::TABLE, $record);
            } else {
                $record->timecreated = $now;
                $DB->insert_record(self::TABLE, $record);
            }

            // 6. The log (FR-008). A repair writes none.
            if ($logsource !== null) {
                $DB->insert_record(self::LOGTABLE, (object)['userid' => $userid, 'actorid' => $actorid,
                    'fromlevel' => $from, 'tolevel' => $level, 'source' => $logsource,
                    'requested' => (int)!empty($target['requested']),
                    'emailchecked' => (int)!empty($target['emailchecked']), 'timecreated' => $now]);
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            // A rollback deeper down (inside a core call) disposes this transaction too, and
            // rollback() on a disposed one throws "Transactions already disposed"
            // (lib/dml/moodle_transaction.php), hiding the real error. Only roll back a live one.
            if (isset($transaction) && !$transaction->is_disposed()) {
                $transaction->rollback($e);   // Rethrows $e.
            }
            throw $e;
        } finally {
            $lock->release();
            self::$protected = [];
        }

        // 7. After the commit: caches, calendar names, the learner's notice.
        \cache_helper::purge_by_definition('core', 'coursecontacts');
        self::resave_scheduler_slots($userid);
        if ($from !== $level) {
            self::notify($userid, $level, $from !== levels::NONE || self::has_activity($userid));
        }
    }

    /**
     * At a level that withholds no name (email), the account still shows the real names, and
     * the user or the site team may change them there. Copy them into the row, so entitled
     * viewers see the current name.
     *
     * @param int $userid
     */
    public static function refresh_real_names(int $userid): void {
        global $DB;
        $row = self::row($userid);
        $config = self::config();
        if (!$row || !$config || $row->effectivelevel === levels::NONE) {
            return;
        }
        $split = levels::split(levels::withheld((array)$config['withhold'], (string)$row->effectivelevel));
        if ($split['names'] || $split['firstname']) {
            return;
        }
        $user = core_user::get_user($userid, 'id, firstname, lastname');
        if (!$user || ((string)$user->firstname === (string)$row->realfirstname
                && (string)$user->lastname === (string)$row->reallastname)) {
            return;
        }
        if (trim((string)$user->firstname) === '' || trim((string)$user->lastname) === '') {
            return; // Never overwrite a held name with an empty one.
        }
        $DB->update_record(self::TABLE, (object)['id' => $row->id,
            'realfirstname' => \core_text::substr((string)$user->firstname, 0, 100),
            'reallastname' => \core_text::substr((string)$user->lastname, 0, 100), 'timemodified' => time()]);
    }

    /**
     * Apply now, or queue it when the caller is inside a transaction of its own: a nested
     * rollback here would force the caller's rollback too, and the lock wait would hold its
     * transaction open.
     *
     * @param int $userid
     */
    public static function apply_or_queue(int $userid): void {
        global $DB;
        if ($DB->is_transaction_started()) {
            \local_ltuse\task\apply_protection::queue($userid);
            return;
        }
        try {
            self::apply($userid);
        } catch (moodle_exception $e) {
            \local_ltuse\task\apply_protection::queue($userid);   // Busy: the task retries.
        }
    }

    // --- helpers -------------------------------------------------------------------------------

    /**
     * The state apply() repairs a user to: the level already applied, unchanged.
     *
     * @param stdClass $row
     * @return array ownlevel, effectivelevel, pseudonym, realfirstname, reallastname, corrections, hidelogs
     */
    protected static function target(stdClass $row): array {
        // No real names here: write() takes them from the account or the row.
        return ['ownlevel' => (string)$row->ownlevel, 'effectivelevel' => (string)$row->effectivelevel,
            'pseudonym' => (string)$row->pseudonym,
            'realfirstname' => '', 'reallastname' => '', 'corrections' => [],
            'hidelogs' => !empty($row->hidelogs)];
    }

    /**
     * The real names as they stand: from the account while the applied level withholds no
     * name (so a name the site team corrects in the admin editor is kept), else from the row.
     *
     * @param stdClass $user
     * @param stdClass|null $row
     * @return string[] [first, last]
     */
    protected static function base_real_names(stdClass $user, ?stdClass $row): array {
        $config = self::config();
        $applied = $row ? (string)$row->effectivelevel : levels::NONE;
        $split = levels::split(levels::withheld((array)($config['withhold'] ?? []), $applied));
        if (!$row || !($split['names'] || $split['firstname'])) {
            return [(string)$user->firstname, (string)$user->lastname];
        }
        return [(string)$row->realfirstname, (string)$row->reallastname];
    }

    /**
     * @param string $first
     * @param string $last
     * @return string the real full name, as the entitled surfaces show it
     */
    public static function real_fullname(string $first, string $last): string {
        return trim(trim($first) . ' ' . trim($last));
    }

    /**
     * A username nobody has: ltc- and 8 lowercase base32 characters, never derived from the
     * person (R13). Spec 008's intake_service::new_username() is the one generator, so an
     * account made at intake and one renamed here look alike (merged 2026-10-05; the format is
     * 008's).
     *
     * @return string
     */
    public static function neutral_username(): string {
        return \local_ltuse\admin\intake_service::new_username();
    }

    /**
     * What the account's email address may give away, for the granting page's warning
     * (levels::email_reveals(), scope review change 2). Callers show it only to someone who
     * may see the real identity.
     *
     * @param int $userid
     * @return string[] name, organisation
     */
    public static function email_warnings(int $userid): array {
        $user = core_user::get_user($userid, 'id, email, firstname, lastname');
        if (!$user) {
            return [];
        }
        $row = self::table_exists() ? self::row($userid) : null;
        [$first, $last] = self::base_real_names($user, $row);
        return levels::email_reveals((string)$user->email, $first, $last, self::user_org($userid));
    }

    /**
     * The levels that would delete this user's profile picture (R6), for the granting page's
     * warning: none when they have no picture, or their applied level already withholds it.
     *
     * @param int $userid
     * @return string[] levels, in order
     */
    public static function picture_levels(int $userid): array {
        $config = self::config();
        $user = core_user::get_user($userid, 'id, picture');
        if (!$config || !$user || empty($user->picture)) {
            return [];
        }
        $withhold = (array)$config['withhold'];
        if (levels::split(levels::withheld($withhold, self::effective_level($userid)))['picture']) {
            return [];
        }
        return array_values(array_filter(levels::ORDER, function($level) use ($withhold) {
            return levels::split(levels::withheld($withhold, $level))['picture'];
        }));
    }

    /**
     * The account's links to an outside login (R3). Linked logins work whatever the account's
     * auth is (auth/oauth2/classes/auth.php complete_login() on MOODLE_502_STABLE), so a
     * protected account keeps none. Read and deleted through auth_oauth2's own persistent
     * class (auth/oauth2/classes/linked_login.php), never the table directly.
     *
     * @param int $userid
     * @return \auth_oauth2\linked_login[]
     */
    protected static function linked_logins(int $userid): array {
        if (!class_exists('\auth_oauth2\linked_login')) {
            return [];
        }
        return \auth_oauth2\linked_login::get_records(['userid' => $userid]);
    }

    /**
     * Put the course-log block exactly where it is asked for (scope review change 5, R14):
     * LOG_ROLES are prohibited LOG_CAPABILITIES in every course where a protected person who
     * asked (hidelogs) is enrolled, active or not, and nowhere else. local_ltuse owns every
     * course-level prohibit of those capabilities for those roles, so one set by hand where
     * nobody asked is removed. Called after a change and by the hourly reconcile, which also
     * covers a course the person joins later.
     *
     * Core APIs only: assign_capability() and unassign_capability() (lib/accesslib.php on
     * MOODLE_502_STABLE), which clear the role cache themselves.
     */
    public static function sync_log_blocks(): void {
        global $DB;
        if (!self::table_exists()) {
            return;
        }
        $roleids = array_map('intval', array_keys($DB->get_records_list('role', 'shortname', self::LOG_ROLES, '', 'id')));
        if (!$roleids) {
            return;
        }
        $want = array_map('intval', $DB->get_fieldset_sql("
            SELECT DISTINCT e.courseid
              FROM {" . self::TABLE . "} p
              JOIN {user_enrolments} ue ON ue.userid = p.userid
              JOIN {enrol} e ON e.id = ue.enrolid
             WHERE p.hidelogs = 1 AND p.effectivelevel <> :none", ['none' => levels::NONE]));
        [$capsql, $capparams] = $DB->get_in_or_equal(self::LOG_CAPABILITIES, SQL_PARAMS_NAMED, 'cap');
        [$rolesql, $roleparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'role');
        $have = array_map('intval', $DB->get_fieldset_sql("
            SELECT DISTINCT ctx.instanceid
              FROM {role_capabilities} rc
              JOIN {context} ctx ON ctx.id = rc.contextid AND ctx.contextlevel = :courselevel
             WHERE rc.capability $capsql AND rc.roleid $rolesql AND rc.permission = :prohibit",
            ['courselevel' => CONTEXT_COURSE, 'prohibit' => CAP_PROHIBIT] + $capparams + $roleparams));

        foreach ($want as $courseid) {
            $context = \context_course::instance($courseid, IGNORE_MISSING);
            if (!$context) {
                continue;
            }
            foreach ($roleids as $roleid) {
                foreach (self::LOG_CAPABILITIES as $capability) {
                    $now = $DB->get_field('role_capabilities', 'permission',
                        ['contextid' => $context->id, 'roleid' => $roleid, 'capability' => $capability]);
                    if ((int)$now !== CAP_PROHIBIT) {
                        assign_capability($capability, CAP_PROHIBIT, $roleid, $context->id, true);
                    }
                }
            }
        }
        foreach (array_diff($have, $want) as $courseid) {
            $context = \context_course::instance($courseid, IGNORE_MISSING);
            if (!$context) {
                continue;
            }
            foreach ($roleids as $roleid) {
                foreach (self::LOG_CAPABILITIES as $capability) {
                    if ($DB->record_exists('role_capabilities', ['contextid' => $context->id, 'roleid' => $roleid,
                            'capability' => $capability, 'permission' => CAP_PROHIBIT])) {
                        unassign_capability($capability, $roleid, $context->id);
                    }
                }
            }
        }
    }

    /**
     * Re-save the user's office-hours slots, so the calendar events mod_scheduler names from
     * fullname() take the protected display (R15). mod_scheduler's own model class; listed in
     * the plugin README, and quickstart V17 is re-run on every re-pin.
     *
     * @param int $userid
     */
    protected static function resave_scheduler_slots(int $userid): void {
        global $DB;
        if (!class_exists('\mod_scheduler\model\scheduler') || !$DB->get_manager()->table_exists('scheduler_slots')) {
            return;
        }
        try {
            $slots = $DB->get_records_sql("
                SELECT DISTINCT s.id, s.schedulerid
                  FROM {scheduler_slots} s
             LEFT JOIN {scheduler_appointment} a ON a.slotid = s.id
                 WHERE (s.teacherid = :teacher OR a.studentid = :student) AND s.starttime >= :now",
                ['teacher' => $userid, 'student' => $userid, 'now' => time() - DAYSECS]);
            $schedulers = [];
            foreach ($slots as $slot) {
                $sid = (int)$slot->schedulerid;
                $schedulers[$sid] = $schedulers[$sid] ?? \mod_scheduler\model\scheduler::load_by_id($sid);
                \mod_scheduler\model\slot::load_by_id((int)$slot->id, $schedulers[$sid])->save();
            }
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not re-save scheduler slots: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Tell the learner what others now see (US3-1). No real name, no actor, and no username:
     * the learner signs in with their email whatever the username is.
     *
     * @param int $userid
     * @param string $level
     * @param bool $history copies already sent cannot be recalled
     */
    protected static function notify(int $userid, string $level, bool $history): void {
        try {
            $user = core_user::get_user($userid);
            $a = (object)['level' => get_string('protection:level:' . $level, 'local_ltuse'),
                'others' => get_string('protection:others:' . $level, 'local_ltuse')];
            $body = get_string('protection:notice:body', 'local_ltuse', $a);
            if ($history) {
                $body .= "\n\n" . get_string('protection:notice:history', 'local_ltuse');
            }
            $message = new \core\message\message();
            $message->component = 'local_ltuse';
            $message->name = 'protectionchanged';
            $message->userfrom = core_user::get_noreply_user();
            $message->userto = $user;
            $message->subject = get_string('protection:notice:subject', 'local_ltuse');
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = text_to_html(s($body), false, false, true);
            $message->smallmessage = get_string('protection:notice:subject', 'local_ltuse');
            $message->notification = 1;
            $message->contexturl = (new \moodle_url('/user/profile.php', ['id' => $userid]))->out(false);
            $message->contexturlname = get_string('protection:yourprofile', 'local_ltuse');
            message_send($message);
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not send protectionchanged: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * @return array the stored config
     * @throws moodle_exception when site_config.py apply has not stored protection.yaml
     */
    protected static function require_config(): array {
        $config = self::config();
        if (!$config || !self::table_exists()) {
            throw new moodle_exception('protection:err:noconfig', 'local_ltuse');
        }
        return $config;
    }

    /**
     * @param array $options
     * @param string $key
     * @return string|null the trimmed value, or null when absent or empty
     */
    protected static function option_text(array $options, string $key): ?string {
        if (!isset($options[$key]) || !is_scalar($options[$key])) {
            return null;
        }
        $value = trim((string)$options[$key]);
        return $value === '' ? null : $value;
    }

    /**
     * @return bool the protection tables exist (the plugin upgrade has run)
     */
    public static function table_exists(): bool {
        global $DB;
        static $exists = null;
        if ($exists === null) {
            $exists = $DB->get_manager()->table_exists(self::TABLE);
        }
        return $exists;
    }

    /**
     * Delete one user's protection data, and clear them as an actor on everyone else's rows.
     * Shared by the user_deleted observer and the privacy provider (FR-013).
     *
     * @param int $userid
     */
    public static function delete_user_data(int $userid): void {
        global $DB;
        if (!self::table_exists()) {
            return;
        }
        $hadlogblock = $DB->record_exists(self::TABLE, ['userid' => $userid, 'hidelogs' => 1]);
        $DB->delete_records(self::TABLE, ['userid' => $userid]);
        $DB->delete_records(self::LOGTABLE, ['userid' => $userid]);
        $DB->set_field(self::LOGTABLE, 'actorid', 0, ['actorid' => $userid]);
        $DB->set_field(self::TABLE, 'usermodified', 0, ['usermodified' => $userid]);
        if ($hadlogblock) {
            self::sync_log_blocks();   // Their courses no longer need the block.
        }
    }
}
