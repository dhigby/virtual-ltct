<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core_user;
use stdClass;

/**
 * Bring new learners on: preview a whole intake file, apply it one row at a time (spec 008,
 * research R2, R3, R5, R15; data-model sections 1-3).
 *
 * preview() gathers what Moodle holds for each row and asks intake_rules for its outcome; it
 * changes nothing. apply_row() does one row under a per-email lock, in the order research R3
 * fixes:
 *
 *   1. create the account, with no organisation (new rows only);
 *   2. set its protection to the target, through spec 016's service, and confirm it settled;
 *   3. set ltct_org, so tool_dynamic_cohorts places it in its organisation's cohort, which
 *      enrols it wherever that cohort is enrolled;
 *   4. enrol it in the row's own courses through the Organisation enrolment (spec 002).
 *
 * A row stopped between 1 and 3 is left with no organisation, so it is in no cohort and
 * enrolled nowhere; the next run classifies it will_set_org and resumes from step 2.
 *
 * On an existing account the only field ever written is ltct_org (FR-019): names, email and
 * everything else are left exactly as they are.
 *
 * The external functions (classes/external/admin_preview_intake.php and
 * admin_apply_intake_row.php) check local/ltuse:administer and moodle/user:create first.
 * Nothing here checks a capability.
 */
class intake_service {

    /** Lock type for the per-email lock (\core\lock\lock_config::get_lock_factory()). */
    const LOCK_TYPE = 'local_ltuse';

    /** Seconds to wait for another run holding the same email's lock. */
    const LOCK_WAIT = 30;

    /**
     * Generated usernames, for a row intake_rules::username() keeps neutral: this prefix and
     * USERNAME_LENGTH characters of USERNAME_ALPHABET.
     */
    const USERNAME_PREFIX = 'ltc-';
    const USERNAME_LENGTH = 8;
    const USERNAME_ALPHABET = 'abcdefghijklmnopqrstuvwxyz234567';

    /** The organisation profile field (spec 002). */
    const ORG_FIELD = 'ltct_org';

    /** Spec 016's classes, called only when installed (research R5). */
    const PROTECTION_SERVICE = '\local_ltuse\protection\service';
    const PROTECTION_ENTITLEMENT = '\local_ltuse\protection\entitlement';

    /** Spec 002's management actions (organisation\actions), for the per-row courses. */
    const ACTIONS = '\local_ltuse\organisation\actions';

    /**
     * Preview a whole file. Changes nothing.
     *
     * @param array $rows each [row, email, firstname, lastname, organisation, country,
     *                    protection, pseudonym, courses[]]
     * @param bool $showpeople return emails as given rather than masked
     * @return array ['refusal' => string, 'rows' => [[row, key, outcome, reason, changes[]]]];
     *               a non-empty refusal comes with no rows
     */
    public static function preview(array $rows, bool $showpeople): array {
        $context = self::resolve($rows);
        if ($context['refusal'] !== '') {
            return ['refusal' => $context['refusal'], 'rows' => []];
        }
        $results = [];
        foreach ($rows as $row) {
            if (!self::valid_email($row)) {
                // The CLI refuses such a file offline; this guards every other caller of the
                // web service, because user_create_user() would store the address as given.
                $results[] = ['row' => (int)$row['row'], 'key' => masking::mask_email((string)$row['email']),
                    'outcome' => 'rejected', 'reason' => self::reason('bad_email'), 'changes' => []];
                continue;
            }
            $facts = self::facts($row, $context);
            $decision = intake_rules::classify($facts);
            $results[] = [
                'row' => (int)$row['row'],
                'key' => $showpeople ? trim((string)$row['email']) : masking::mask_email((string)$row['email']),
                'outcome' => $decision['outcome'],
                'reason' => self::reason($decision['reason'], $facts['org']),
                'changes' => $decision['changes'],
            ];
        }
        return ['refusal' => '', 'rows' => $results];
    }

    /**
     * Apply one row, if it is still what the preview said or further along its path.
     *
     * @param array $row as preview()
     * @param string $expectedoutcome the outcome the operator's preview reported for this row
     * @return array [row, outcome, status: done|already_done|refused, reason]
     */
    public static function apply_row(array $row, string $expectedoutcome): array {
        if (!self::valid_email($row)) {
            return self::answer($row, 'rejected', 'refused', self::reason('bad_email'));
        }
        $email = self::normalise_email((string)$row['email']);
        // Keyed on a hash of the lowercased email, so the lock table never holds an address.
        $lock = \core\lock\lock_config::get_lock_factory(self::LOCK_TYPE)
            ->get_lock('intake:' . sha1($email), self::LOCK_WAIT);
        if (!$lock) {
            return self::answer($row, $expectedoutcome, 'refused', self::reason('busy'));
        }
        try {
            // Everything is read again inside the lock: a second run, or a retry racing its own
            // lost first attempt, finds the account the first one made.
            $context = self::resolve([$row]);
            if ($context['refusal'] !== '') {
                return self::answer($row, $expectedoutcome, 'refused', $context['refusal']);
            }
            $facts = self::facts($row, $context);
            $decision = intake_rules::classify($facts);
            $step = intake_rules::progress($expectedoutcome, $decision['outcome']);
            if ($step === intake_rules::REFUSED) {
                return self::answer($row, $decision['outcome'], 'refused', self::reason('changed'));
            }
            [$did, $stop] = self::perform($row, $decision, $facts, $context);
            if ($stop !== '') {
                return self::answer($row, $decision['outcome'], 'refused', self::reason($stop));
            }
            return self::answer($row, $decision['outcome'], $did ? 'done' : 'already_done', '');
        } finally {
            $lock->release();
        }
    }

    // --- gathering facts ----------------------------------------------------------------------

    /**
     * Resolve every course, and every organisation cohort, the rows name. Anything missing is
     * a file-level refusal naming it (data-model section 1): no row is classified.
     *
     * @param array $rows
     * @return array ['refusal' => string, 'courses' => [idnumber => [id, category idnumber]],
     *               'available' => string[], 'protection' => bool]
     */
    protected static function resolve(array $rows): array {
        global $DB;
        $courses = [];
        $missing = [];
        $keys = [];
        foreach ($rows as $row) {
            $keys[trim((string)$row['organisation'])] = true;
            foreach (self::row_courses($row) as $idnumber) {
                if (array_key_exists($idnumber, $courses)) {
                    continue;
                }
                $course = $DB->get_record('course', ['idnumber' => $idnumber], 'id, category');
                if (!$course) {
                    $courses[$idnumber] = null;
                    $missing[] = $idnumber;
                    continue;
                }
                $courses[$idnumber] = [
                    'id' => (int)$course->id,
                    'category' => (string)$DB->get_field('course_categories', 'idnumber', ['id' => $course->category]),
                ];
            }
        }
        foreach (array_keys($keys) as $key) {
            if ($key === '' || !$DB->record_exists('cohort', ['idnumber' => enrolment_rules::ORG_PREFIX . $key])) {
                $missing[] = enrolment_rules::ORG_PREFIX . $key;
            }
        }

        $protection = class_exists(self::PROTECTION_SERVICE);
        $available = [];
        if ($protection && !$missing) {
            $service = self::PROTECTION_SERVICE;
            foreach (intake_rules::LEVELS as $level) {
                if ($service::level_available($level)) {
                    $available[] = $level;
                }
            }
        }
        return [
            'refusal' => $missing ? get_string('admin:refusal:missing', 'local_ltuse', implode(', ', $missing)) : '',
            'courses' => $courses,
            'available' => $available,
            'protection' => $protection,
        ];
    }

    /**
     * The facts intake_rules::classify() needs about one row (see its class comment), plus
     * the matched account's id as 'userid' (null when there is none or more than one).
     *
     * @param array $row
     * @param array $context resolve()'s result
     * @return array
     */
    protected static function facts(array $row, array $context): array {
        $key = trim((string)$row['organisation']);
        $courses = self::row_courses($row);
        $accounts = self::match_accounts(self::normalise_email((string)$row['email']));
        $account = count($accounts) === 1 ? reset($accounts) : null;
        $userid = $account ? (int)$account->id : null;

        $allowed = [];
        foreach ($courses as $idnumber) {
            $course = $context['courses'][$idnumber] ?? null;
            if ($course && enrolment_rules::decide(enrolment_rules::ORG_PREFIX . $key, $idnumber,
                    $course['category'])['role'] === enrolment_rules::ROLE_STUDENT) {
                $allowed[] = $idnumber;
            }
        }

        $facts = [
            'userid' => $userid,
            'accounts' => count($accounts),
            'org' => $userid ? self::organisation_of($userid) : '',
            'suspended' => $account ? (bool)$account->suspended : false,
            'effective' => 'none',
            'settled' => true,
            'protection' => $context['protection'],
            'available' => $context['available'],
            'roworg' => $key,
            'asked' => strtolower(trim((string)($row['protection'] ?? ''))) ?: 'none',
            'courses' => $courses,
            'active' => $userid ? self::active_courses($userid, $courses, $context) : [],
            'allowed' => $allowed,
            'loginclash' => !$accounts && self::username_in_use(self::normalise_email((string)$row['email'])),
        ];
        if ($userid && $context['protection']) {
            $service = self::PROTECTION_SERVICE;
            $facts['effective'] = (string)$service::effective_level($userid);
            $facts['settled'] = (bool)$service::is_settled($userid);
        }
        return $facts;
    }

    /**
     * Live accounts with this email, case-insensitively: not deleted, on this site (not an MNet
     * peer's). 008's own lookup is the guard against a second account for one email, because
     * user_create_user() never checks (research R2). The suspension, move and membership
     * services match people the same way.
     *
     * @param string $email lowercased
     * @return stdClass[] id, suspended
     */
    public static function match_accounts(string $email): array {
        global $CFG, $DB;
        if ($email === '') {
            return [];
        }
        $select = 'deleted = 0 AND mnethostid = :mnethostid AND ' . $DB->sql_equal('email', ':email', false);
        return array_values($DB->get_records_select('user', $select,
            ['mnethostid' => $CFG->mnet_localhost_id, 'email' => $email], 'id ASC', 'id, suspended'));
    }

    /**
     * Whether a live account on this site has this text as its username. Sign-in looks a
     * username up before an email (authenticate_user_login(), lib/moodlelib.php), so a new
     * account whose email is someone else's username could never sign in with it.
     *
     * @param string $email lowercased
     * @return bool
     */
    protected static function username_in_use(string $email): bool {
        global $CFG, $DB;
        return $email !== '' && $DB->record_exists('user',
            ['username' => $email, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]);
    }

    /**
     * The row's courses the account is already actively enrolled in, by any method.
     *
     * @param int $userid
     * @param string[] $courses idnumbers
     * @param array $context
     * @return string[]
     */
    protected static function active_courses(int $userid, array $courses, array $context): array {
        $active = [];
        foreach ($courses as $idnumber) {
            $course = $context['courses'][$idnumber] ?? null;
            if ($course && is_enrolled(context_course::instance($course['id']), $userid, '', true)) {
                $active[] = $idnumber;
            }
        }
        return $active;
    }

    // --- writing ------------------------------------------------------------------------------

    /**
     * Do what the row's current outcome needs, from wherever it stands now.
     *
     * @param array $row
     * @param array $decision intake_rules::classify()'s result
     * @param array $facts
     * @param array $context
     * @return array [bool something was written, string '' or the reason key it stopped on]
     */
    protected static function perform(array $row, array $decision, array $facts, array $context): array {
        $outcome = $decision['outcome'];
        $userid = $facts['userid'];
        $did = false;

        if ($outcome === 'new') {
            $userid = self::create_account($row, $decision['target']);
            $did = true;
        }
        if ($outcome === 'new' || $outcome === 'will_set_org') {
            if ($decision['target'] !== 'none') {
                [$changed, $stop] = self::protect($userid, $decision['target'], $row);
                $did = $did || $changed;
                if ($stop !== '') {
                    return [$did, $stop];       // Stopped with ltct_org unset (research R5).
                }
            }
            if (!self::set_organisation($userid, trim((string)$row['organisation']))) {
                return [$did, 'org_not_saved'];
            }
            $did = true;
        }
        if (in_array($outcome, ['new', 'will_set_org', 'will_enrol'], true)) {
            [$enrolled, $stop] = self::enrol_courses($userid, $row, $context);
            $did = $did || $enrolled;
            if ($stop !== '') {
                return [$did, $stop];
            }
        }
        return [$did, ''];
    }

    /**
     * Create the account (research R2): manual authentication, confirmed, on this site, the
     * username choose_username() gives, no password until Moodle emails one, and no
     * organisation. Fires user_created itself, as core's web service does after its own fields.
     *
     * @param array $row
     * @param string $target the row's target protection
     * @return int the new user's id
     */
    protected static function create_account(array $row, string $target): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        $user = (object)[
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => self::choose_username((string)$row['email'], $target),
            'password' => '',
            'firstname' => trim((string)$row['firstname']),
            'lastname' => trim((string)$row['lastname']),
            'email' => trim((string)$row['email']),
        ];
        $country = strtoupper(trim((string)($row['country'] ?? '')));
        if ($country !== '') {
            $user->country = $country;
        }

        $transaction = $DB->start_delegated_transaction();
        $userid = user_create_user($user, false, false);
        set_user_preference('auth_forcepasswordchange', 1, $userid);
        \core\event\user_created::create_from_userid($userid)->trigger();
        $transaction->allow_commit();

        // After the commit, so the email never names an account a rollback removed. The email
        // is sent now, not at the end of the row, so a run stopped later still leaves the
        // learner able to sign in once the rest is finished.
        setnew_password_and_mail(core_user::get_user($userid, '*', MUST_EXIST));
        return $userid;
    }

    /**
     * A new account's username: its lowercased email, or a neutral generated one when
     * intake_rules::username() says so (a firstname or pseudonym target, an email
     * PARAM_USERNAME would change or user.username cannot hold, or one already a username here).
     * The unique index is (mnethostid, username) over every row, deleted ones included, so the
     * check counts deleted accounts too.
     *
     * @param string $email the row's email
     * @param string $target the row's target protection
     * @return string
     */
    public static function choose_username(string $email, string $target): string {
        global $CFG, $DB;
        $email = self::normalise_email($email);
        $taken = $DB->record_exists('user', ['username' => $email, 'mnethostid' => $CFG->mnet_localhost_id]);
        return intake_rules::username($email, $target, clean_param($email, PARAM_USERNAME), $taken)
            ?? self::new_username();
    }

    /**
     * A username nobody has: ltc- and 8 lowercase base32 characters, never derived from the
     * person (spec 016 R13).
     *
     * @return string
     */
    protected static function new_username(): string {
        global $CFG, $DB;
        $alphabet = self::USERNAME_ALPHABET;
        do {
            $name = self::USERNAME_PREFIX;
            for ($i = 0; $i < self::USERNAME_LENGTH; $i++) {
                $name .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while ($DB->record_exists('user', ['username' => $name, 'mnethostid' => $CFG->mnet_localhost_id]));
        return $name;
    }

    /**
     * Bring the account's protection to $target through spec 016, and confirm it settled
     * (research R5, steps 2 and 3). Never called for a target of none.
     *
     * @param int $userid
     * @param string $target
     * @param array $row
     * @return array [bool protection was written, string '' or the reason key it stopped on]
     */
    protected static function protect(int $userid, string $target, array $row): array {
        global $USER;
        if (!class_exists(self::PROTECTION_SERVICE) || !class_exists(self::PROTECTION_ENTITLEMENT)) {
            return [false, 'protection_absent'];
        }
        $service = self::PROTECTION_SERVICE;
        $entitlement = self::PROTECTION_ENTITLEMENT;
        $changed = false;
        if (!self::protection_reached($userid, $target)) {
            // The service checks no permission itself, so the entitlement comes first.
            if (!$entitlement::can_manage_protection((int)$USER->id, $userid)) {
                return [false, 'protection_not_permitted'];
            }
            $options = $target === 'pseudonym' ? ['pseudonym' => trim((string)($row['pseudonym'] ?? ''))] : [];
            try {
                $service::set_protection($userid, $target, $options, (int)$USER->id);
            } catch (\moodle_exception $e) {
                return [false, 'protection_failed'];
            }
            $changed = true;
        }
        if (!$service::is_settled($userid)) {
            $service::apply($userid);
        }
        return [$changed, self::protection_reached($userid, $target) ? '' : 'protection_unsettled'];
    }

    /**
     * @param int $userid
     * @param string $target
     * @return bool settled, and the effective level at least the target
     */
    protected static function protection_reached(int $userid, string $target): bool {
        $service = self::PROTECTION_SERVICE;
        $rank = intake_rules::rank((string)$service::effective_level($userid));
        return $service::is_settled($userid) && $rank !== null && $rank >= intake_rules::rank($target);
    }

    /**
     * Set ltct_org and nothing else, then announce it, so tool_dynamic_cohorts places the
     * person in ltct:org:<key> in this same request (research R3).
     *
     * profile_save_data() saves only the fields present on the object, so this one carries
     * the id and the one field: it bypasses spec 016's before_user_updated hook, and must never
     * carry a name (016's never-send list).
     *
     * @param int $userid
     * @param string $key
     * @return bool whether the field now holds $key (a menu field stores its default instead of
     *              a value that is not one of its options)
     */
    public static function set_organisation(int $userid, string $key): bool {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        profile_save_data((object)['id' => $userid, 'profile_field_' . self::ORG_FIELD => $key]);
        if (self::organisation_of($userid) !== $key) {
            return false;
        }
        \core\event\user_updated::create_from_userid($userid)->trigger();
        return true;
    }

    /**
     * Enrol the account in each of the row's courses it is not already active in, through
     * spec 002's Organisation enrolment (do_enrol() re-checks access::may_enrol_into()).
     *
     * @param int $userid
     * @param array $row
     * @param array $context
     * @return array [bool someone was enrolled, string '' or the reason key it stopped on]
     */
    protected static function enrol_courses(int $userid, array $row, array $context): array {
        $courses = self::row_courses($row);
        if (!$courses) {
            return [false, ''];
        }
        if (!class_exists(self::ACTIONS)) {
            return [false, 'enrol_unavailable'];
        }
        $actions = self::ACTIONS;
        $enrolled = false;
        foreach ($courses as $idnumber) {
            $course = $context['courses'][$idnumber];
            if (is_enrolled(context_course::instance($course['id']), $userid, '', true)) {
                continue;   // Already active, perhaps through the cohort the organisation joined.
            }
            try {
                $actions::do_enrol($userid, $course['id']);
            } catch (\moodle_exception $e) {
                return [$enrolled, 'enrol_failed'];
            }
            $enrolled = true;
        }
        return [$enrolled, ''];
    }

    // --- helpers ------------------------------------------------------------------------------

    /**
     * The person's ltct_org value, '' when empty.
     *
     * @param int $userid
     * @return string
     */
    public static function organisation_of(int $userid): string {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        return trim((string)(profile_user_record($userid, false)->{self::ORG_FIELD} ?? ''));
    }

    /**
     * @param array $row
     * @return string[] the row's course idnumbers, trimmed, each once
     */
    protected static function row_courses(array $row): array {
        $courses = [];
        foreach ((array)($row['courses'] ?? []) as $idnumber) {
            $idnumber = trim((string)$idnumber);
            if ($idnumber !== '' && !in_array($idnumber, $courses, true)) {
                $courses[] = $idnumber;
            }
        }
        return $courses;
    }

    /**
     * @param array $row
     * @return bool the row's email is an address Moodle would send to (validate_email())
     */
    public static function valid_email(array $row): bool {
        return (bool)validate_email(trim((string)($row['email'] ?? '')));
    }

    /**
     * @param string $email
     * @return string
     */
    public static function normalise_email(string $email): string {
        return \core_text::strtolower(trim($email));
    }

    /**
     * The plain sentence for a reason key; '' for none.
     *
     * @param string $key
     * @param string $org the account's current organisation key, for other_org
     * @return string
     */
    public static function reason(string $key, string $org = ''): string {
        return $key === '' ? '' : get_string('admin:reason:' . $key, 'local_ltuse', $org);
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
