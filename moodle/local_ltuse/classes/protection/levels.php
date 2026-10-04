<?php
namespace local_ltuse\protection;

defined('MOODLE_INTERNAL') || die();

/**
 * The pure rules of identity protection (spec 016).
 *
 * PURE: no Moodle call and no database read, so tests/protection_harness.php tests every rule
 * with plain PHP. The service, the entitlement class, the pages and the tasks gather the facts
 * and ask this class; none of them decides a rule itself.
 *
 *   levels       none < email < firstname < pseudonym, each including the ones before it (FR-001)
 *   effective    the stricter of a user's own level and their organisation's minimum (FR-001a)
 *   withheld     which account fields a level withholds, from the declared config (R6)
 *   pseudonym    unique among protected users and not containing the real name, after NFC and
 *                case folding (data-model)
 *   username     must not contain the real first name or surname at firstname+ (R13)
 *   cohorts      an organisation's member cohort is exactly ltct:org:<key> for a declared key,
 *                never ltct:org:<key>:managers (R2)
 *   courses      a course mentor counts only in a published or pilot ltct:<slug> course, never
 *                the office-hours course, which enrols every mentor (R7 path 4)
 */
class levels {

    /** The four levels, loosest first. */
    const NONE = 'none';
    const EMAIL = 'email';
    const FIRSTNAME = 'firstname';
    const PSEUDONYM = 'pseudonym';
    const ORDER = [self::NONE, self::EMAIL, self::FIRSTNAME, self::PSEUDONYM];

    /** The strictest level an organisation minimum may take: a pseudonym is chosen per person (R12). */
    const ORG_MAX = self::FIRSTNAME;

    /** The levels that need ltct_org private and a cohort-scoped report first (R11). */
    const NEED_ORGSCOPE = [self::FIRSTNAME, self::PSEUDONYM];

    /** Where an effective level comes from (data-model). */
    const SOURCE_OWN = 'own';
    const SOURCE_ORG = 'organisation';
    const SOURCE_KEPT = 'organisation-kept';
    const SOURCE_CORRECTION = 'correction';

    /** The account's alternate-name columns, blanked from firstname (R1). */
    const ALTNAMES = ['firstnamephonetic', 'lastnamephonetic', 'middlename', 'alternatename'];

    /** Core user columns a level may withhold besides the names (R6). */
    const CORE_FIELDS = ['country', 'city', 'url', 'institution', 'department', 'phone1', 'phone2',
        'address', 'idnumber'];

    /** Fields handled specially, never blanked as text. */
    const MAILDISPLAY = 'maildisplay';
    const PICTURE = 'picture';
    const FIRSTNAME_FIELD = 'firstname';
    const LASTNAME_FIELD = 'lastname';

    /** Custom profile fields we own, by shortname; ltct_org and ltct_certname never withheld (R10, R11). */
    const PROFILE_FIELD = '/^ltct_[a-z0-9_]+$/';
    const NEVER_WITHHELD = ['ltct_org', 'ltct_certname', 'description', 'interests'];

    /** The pseudonym column's width (local_ltuse_protection.pseudonym). */
    const PSEUDONYM_MAX = 100;

    /** A surname shorter than this is not searched for inside a pseudonym (data-model). */
    const SURNAME_MIN = 3;

    /** The office-hours course: every mentor is a teacher there (spec 011 R16). */
    const OFFICEHOURS_COURSE = 'ltct:officehours';

    /**
     * @param mixed $level
     * @return bool one of the four levels
     */
    public static function is_level($level): bool {
        return is_string($level) && in_array($level, self::ORDER, true);
    }

    /**
     * @param string $level
     * @return int 0 for none to 3 for pseudonym; -1 when not a level
     */
    public static function rank(string $level): int {
        $i = array_search($level, self::ORDER, true);
        return $i === false ? -1 : (int)$i;
    }

    /**
     * The stricter of two levels. An unknown level counts as none.
     *
     * @param string $a
     * @param string $b
     * @return string
     */
    public static function stricter(string $a, string $b): string {
        $a = self::is_level($a) ? $a : self::NONE;
        $b = self::is_level($b) ? $b : self::NONE;
        return self::rank($a) >= self::rank($b) ? $a : $b;
    }

    /**
     * A user's effective level and where it comes from (FR-001a, R13).
     *
     * @param string $own the user's own level
     * @param string $orgminimum their current organisation's minimum, none when there is none
     * @param bool $kept the row was marked organisation-kept after leaving a protected organisation
     * @return array [level, source]
     */
    public static function effective(string $own, string $orgminimum, bool $kept = false): array {
        $own = self::is_level($own) ? $own : self::NONE;
        $orgminimum = self::is_level($orgminimum) ? $orgminimum : self::NONE;
        if (self::rank($orgminimum) > self::rank($own)) {
            return [$orgminimum, self::SOURCE_ORG];
        }
        return [$own, $kept ? self::SOURCE_KEPT : self::SOURCE_OWN];
    }

    /**
     * May an own level be set, given the organisation minimum? Never looser (US3-4).
     *
     * @param string $level
     * @param string $orgminimum
     * @return bool
     */
    public static function allowed_own(string $level, string $orgminimum): bool {
        return self::is_level($level) && self::rank($level) >= self::rank(self::is_level($orgminimum) ? $orgminimum : self::NONE);
    }

    /**
     * Is this level available yet? firstname and pseudonym need the organisation hidden (R11).
     *
     * @param string $level
     * @param bool $orgscopeready
     * @return bool
     */
    public static function available(string $level, bool $orgscopeready): bool {
        return self::is_level($level) && ($orgscopeready || !in_array($level, self::NEED_ORGSCOPE, true));
    }

    /**
     * The fields a level withholds, from the declared withhold lists (protection.yaml). none
     * withholds nothing, and a level missing from the lists withholds what the strictest level
     * below it does, so a short config fails towards withholding.
     *
     * @param array $withhold level => field names, as stored
     * @param string $level
     * @return string[] field names, unique, in declared order
     */
    public static function withheld(array $withhold, string $level): array {
        if (!self::is_level($level) || $level === self::NONE) {
            return [];
        }
        $out = [];
        foreach (self::ORDER as $candidate) {
            if (self::rank($candidate) > self::rank($level)) {
                break;
            }
            foreach ((array)($withhold[$candidate] ?? []) as $field) {
                $field = (string)$field;
                if (self::is_withholdable($field) && !in_array($field, $out, true)) {
                    $out[] = $field;
                }
            }
        }
        return $out;
    }

    /**
     * Is this a field the plugin knows how to withhold? The fixed set of data-model.md.
     *
     * @param string $field
     * @return bool
     */
    public static function is_withholdable(string $field): bool {
        if (in_array($field, self::NEVER_WITHHELD, true)) {
            return false;
        }
        return in_array($field, [self::MAILDISPLAY, self::PICTURE, self::FIRSTNAME_FIELD, self::LASTNAME_FIELD], true)
            || in_array($field, self::ALTNAMES, true)
            || in_array($field, self::CORE_FIELDS, true)
            || (bool)preg_match(self::PROFILE_FIELD, $field);
    }

    /**
     * Split withheld fields by how they are written: user columns blanked as text, custom
     * profile fields, and the special ones (names, maildisplay, picture).
     *
     * @param string[] $fields from withheld()
     * @return array ['columns' => string[], 'profile' => string[], 'names' => bool, 'firstname' => bool,
     *               'maildisplay' => bool, 'picture' => bool]
     */
    public static function split(array $fields): array {
        $out = ['columns' => [], 'profile' => [], 'names' => false, 'firstname' => false,
            'maildisplay' => false, 'picture' => false];
        foreach ($fields as $field) {
            if ($field === self::MAILDISPLAY) {
                $out['maildisplay'] = true;
            } else if ($field === self::PICTURE) {
                $out['picture'] = true;
            } else if ($field === self::LASTNAME_FIELD) {
                $out['names'] = true;
            } else if ($field === self::FIRSTNAME_FIELD) {
                $out['firstname'] = true;
            } else if (in_array($field, self::ALTNAMES, true) || in_array($field, self::CORE_FIELDS, true)) {
                $out['columns'][] = $field;
            } else if (preg_match(self::PROFILE_FIELD, $field)) {
                $out['profile'][] = $field;
            }
        }
        return $out;
    }

    /**
     * The names others see (R1, R4).
     *
     * @param string $level the effective level
     * @param string $realfirst
     * @param string $reallast
     * @param string $pseudonym
     * @param string $neutral the declared neutral surname, '' or one character
     * @return array [firstname, lastname]
     */
    public static function display(string $level, string $realfirst, string $reallast, string $pseudonym,
            string $neutral): array {
        switch ($level) {
            case self::PSEUDONYM:
                return [$pseudonym, $neutral];
            case self::FIRSTNAME:
                return [$realfirst, $neutral];
            default:
                return [$realfirst, $reallast];
        }
    }

    /**
     * Fold a name for comparison: Unicode NFC, then case folding, trimmed and with inner
     * whitespace collapsed. Any script (VIII).
     *
     * @param string $text
     * @return string
     */
    public static function fold(string $text): string {
        if (class_exists('\Normalizer')) {
            $normal = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if ($normal !== false && $normal !== null) {
                $text = $normal;
            }
        }
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * What is wrong with a pseudonym, as error codes (data-model "Validation").
     *
     * @param string $pseudonym
     * @param string $realfirst
     * @param string $reallast
     * @param string[] $others the pseudonyms of every other protected user
     * @return string[] empty, toolong, isrealname, hasrealname, taken
     */
    public static function pseudonym_problems(string $pseudonym, string $realfirst, string $reallast,
            array $others): array {
        $p = self::fold($pseudonym);
        if ($p === '') {
            return ['empty'];
        }
        $problems = [];
        $length = function_exists('mb_strlen') ? mb_strlen(trim($pseudonym), 'UTF-8') : strlen(trim($pseudonym));
        if ($length > self::PSEUDONYM_MAX) {
            $problems[] = 'toolong';
        }
        $first = self::fold($realfirst);
        $last = self::fold($reallast);
        if ($first !== '' && $p === $first) {
            $problems[] = 'isrealname';
        }
        $lastlength = function_exists('mb_strlen') ? mb_strlen($last, 'UTF-8') : strlen($last);
        if ($last !== '' && $lastlength >= self::SURNAME_MIN && strpos($p, $last) !== false) {
            $problems[] = 'hasrealname';
        }
        foreach ($others as $other) {
            if (self::fold((string)$other) === $p) {
                $problems[] = 'taken';
                break;
            }
        }
        return $problems;
    }

    /**
     * Does a username give away the real name (R13)? A name of three characters or more is
     * looked for anywhere in it; a shorter one only as a whole token, so "li" does not match
     * every username that happens to contain those letters.
     *
     * @param string $username
     * @param string $realfirst
     * @param string $reallast
     * @return bool
     */
    public static function username_reveals(string $username, string $realfirst, string $reallast): bool {
        $u = self::fold($username);
        if ($u === '') {
            return false;
        }
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $u, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ([$realfirst, $reallast] as $name) {
            foreach (preg_split('/[\s\-\']+/u', self::fold($name), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                $length = function_exists('mb_strlen') ? mb_strlen($part, 'UTF-8') : strlen($part);
                if ($length >= self::SURNAME_MIN ? strpos($u, $part) !== false : in_array($part, $tokens, true)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Does a change of effective level need the "history" acknowledgement (R13)? Any change
     * for a user who already has activity, raise or lowering, because a rename links the two
     * identities either way.
     *
     * @param string $from
     * @param string $to
     * @param bool $hasactivity
     * @return bool
     */
    public static function needs_acknowledgement(string $from, string $to, bool $hasactivity): bool {
        return $hasactivity && self::rank($from) !== self::rank($to);
    }

    /**
     * The organisation key of a member cohort, or null. Exactly ltct:org:<key> for a declared
     * key; ltct:org:<key>:managers and undeclared keys are not member cohorts (R2).
     *
     * @param string $idnumber the cohort's idnumber
     * @param string[] $declaredkeys the organisation keys declared in organisations.yaml
     * @return string|null
     */
    public static function member_cohort_key(string $idnumber, array $declaredkeys): ?string {
        $prefix = 'ltct:org:';
        if (strpos($idnumber, $prefix) !== 0) {
            return null;
        }
        $key = substr($idnumber, strlen($prefix));
        if ($key === '' || strpos($key, ':') !== false) {
            return null;
        }
        return in_array($key, array_map('strval', $declaredkeys), true) ? $key : null;
    }

    /**
     * Does a course count for the course-mentor path (R7 path 4)? One published or pilot
     * course, ltct:<slug>, never the office-hours course.
     *
     * @param string $idnumber the course's idnumber
     * @return bool
     */
    public static function course_counts(string $idnumber): bool {
        return (bool)preg_match('/^ltct:[^:]+$/', $idnumber) && $idnumber !== self::OFFICEHOURS_COURSE;
    }

    /**
     * What others see of a user at a level, for the learner's own preview (FR-012), as string
     * keys the page turns into words. The picture is listed apart because it is not restored.
     *
     * @param string $level
     * @return string[] name, email, details, picture: each 'shown' or 'hidden', in that order
     */
    public static function preview(string $level): array {
        $rank = self::rank(self::is_level($level) ? $level : self::NONE);
        return [
            'name' => $rank >= 3 ? 'pseudonym' : ($rank >= 2 ? 'firstname' : 'full'),
            'email' => $rank >= 1 ? 'hidden' : 'shown',
            'details' => $rank >= 2 ? 'hidden' : 'shown',
            'picture' => $rank >= 2 ? 'hidden' : 'shown',
        ];
    }
}
