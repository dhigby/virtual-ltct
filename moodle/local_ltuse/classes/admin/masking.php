<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Show a person as a masked email: alice@example.org becomes a***@example.org (research R14).
 *
 * The administration service masks on the server, so unmasked data never crosses the network
 * unless the operator asks for it (--show-people). The rule is scripts/admin_files.py's
 * mask_email(), and tests/admin_harness.php checks the two agree.
 *
 * PURE: no Moodle call, so the harness runs it without Moodle.
 */
class masking {

    /**
     * The masked form: the first character of the address, three stars, then the domain.
     *
     * Trimmed and lowercased first, as emails are matched case-insensitively. Anything that
     * is not local@domain becomes three stars.
     *
     * @param string $email
     * @return string
     */
    public static function mask_email(string $email): string {
        $text = mb_strtolower(trim($email), 'UTF-8');
        $at = strpos($text, '@');
        if ($at === false || $at === 0 || $at === strlen($text) - 1) {
            return '***';
        }
        return mb_substr($text, 0, 1, 'UTF-8') . '***@' . substr($text, $at + 1);
    }
}
