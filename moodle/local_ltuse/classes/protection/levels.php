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
 *   withheld     which account fields a level withholds, from the declared config (R6)
 *   pseudonym    unique among protected users and not containing the real name, after NFC and
 *                case folding (data-model)
 *   username     must not contain the real first name or surname at firstname+ (R13); the
 *                service replaces one that does with a neutral one
 *   email        a warning when the address before the @ holds the real name, or its domain
 *                the organisation's key (scope review change 2); the granter confirms
 *   managers     an organisation's manager grants only at intake: a raise, for someone with no
 *                activity, with no correction; the rest is the site team's (change 14)
 *   courses      a course mentor counts only in a published or pilot ltct:<slug> course, never
 *                the office-hours course, which enrols every mentor (R7 path 4)
 *
 * Every level is available once protection.yaml is stored: the organisation stays visible at
 * every level, so no level waits for it (Doug, 2026-10-05 (scope review), decision 2 option a).
 */
class levels {

    /** The four levels, loosest first. */
    const NONE = 'none';
    const EMAIL = 'email';
    const FIRSTNAME = 'firstname';
    const PSEUDONYM = 'pseudonym';
    const ORDER = [self::NONE, self::EMAIL, self::FIRSTNAME, self::PSEUDONYM];

    /** A log row's source (data-model): a change of level, or a correction at the same level. */
    const SOURCE_OWN = 'own';
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

    /** Custom profile fields we own, by shortname; ltct_org is never withheld (R11). */
    const PROFILE_FIELD = '/^ltct_[a-z0-9_]+$/';
    const NEVER_WITHHELD = ['ltct_org', 'description', 'interests'];

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
     * Is a change from one level to another a raise?
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function is_raise(string $from, string $to): bool {
        return self::rank($to) > self::rank($from);
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
     * What an email address may give away, for the granting page's warning (scope review
     * change 2). Others in a course still see a protected person's address (core's
     * showuseridentity), so the granter confirms it identifies no one. A heuristic: it only
     * warns, and an organisation with a neutral key gives no signal.
     *
     *   name          the part before the @ holds the real first name or surname, by the
     *                 username rule (username_reveals)
     *   organisation  the domain holds a part of three characters or more of the person's
     *                 organisation key
     *
     * @param string $email
     * @param string $realfirst
     * @param string $reallast
     * @param string $orgkey the person's ltct_org, '' when none
     * @return string[] the codes that apply, in that order
     */
    public static function email_reveals(string $email, string $realfirst, string $reallast, string $orgkey): array {
        $at = strrpos($email, '@');
        $local = $at === false ? $email : substr($email, 0, $at);
        $domain = $at === false ? '' : self::fold(substr($email, $at + 1));
        $out = [];
        if (self::username_reveals($local, $realfirst, $reallast)) {
            $out[] = 'name';
        }
        foreach (preg_split('/[^\p{L}\p{N}]+/u', self::fold($orgkey), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $length = function_exists('mb_strlen') ? mb_strlen($part, 'UTF-8') : strlen($part);
            if ($domain !== '' && $length >= self::SURNAME_MIN && strpos($domain, $part) !== false) {
                $out[] = 'organisation';
                break;
            }
        }
        return $out;
    }

    /**
     * May a manager of the person's own organisation make this change (scope review change
     * 14)? Managers grant at intake: a raise, for someone with no activity yet, with no
     * correction to the real name or a held value. Corrections, raises after activity,
     * lowering and removal are the site team's.
     *
     * @param string $from the level applied now
     * @param string $to
     * @param bool $hasactivity
     * @param bool $corrects the change corrects the real name or a held value
     * @return bool
     */
    public static function manager_may(string $from, string $to, bool $hasactivity, bool $corrects): bool {
        return self::is_raise($from, $to) && !$hasactivity && !$corrects;
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
