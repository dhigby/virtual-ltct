<?php
namespace local_ltuse\recognition;

defined('MOODLE_INTERNAL') || die();

/**
 * The plugin's backstop for the CBC wording rule (spec 013, R15, FR-004). PURE.
 *
 * The rule itself lives in scripts/cbc_wording.py, and nowhere else. site_config.py apply
 * sends its deny patterns with the badge template, and this class applies them to each
 * course's rendered badge text before anything is written. site_config.py validate and
 * check_moodle_payload.py have already checked the same text; this catches anything the PHP
 * side renders that Python did not see.
 *
 * A pattern that does not compile is a refusal, not a pass: the check fails closed.
 */
class wording {

    /**
     * Every rendered field that matches a deny pattern.
     *
     * @param array<string, string> $texts field => rendered text
     * @param array[] $deny [{pattern, why}], from cbc_wording.DENY_PATTERNS
     * @return string[] one message per match, "field: why"; empty when the text is clean
     */
    public static function problems(array $texts, array $deny): array {
        if (!$deny) {
            return ['no deny patterns are stored; run site_config.py apply'];
        }
        $out = [];
        foreach ($deny as $rule) {
            $rule = (array)$rule;
            $regex = '~' . str_replace('~', '\~', (string)($rule['pattern'] ?? '')) . '~iu';
            foreach ($texts as $field => $text) {
                $match = @preg_match($regex, (string)$text);
                if ($match === false) {
                    $out[] = "{$field}: deny pattern " . ($rule['pattern'] ?? '') . ' does not compile';
                } else if ($match === 1) {
                    $out[] = "{$field}: " . ($rule['why'] ?? 'matches a deny pattern');
                }
            }
        }
        return $out;
    }
}
