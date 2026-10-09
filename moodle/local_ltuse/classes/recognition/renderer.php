<?php
namespace local_ltuse\recognition;

defined('MOODLE_INTERNAL') || die();

/**
 * A course's badge text, from the declared template and the course (spec 013, R16). PURE.
 *
 * The template arrives from site_config.py apply with {programme} already filled in. The
 * other three placeholders are filled here, per course, from what the publisher already
 * writes: the course's full name and spec 004's two locked course fields. So `apply` can
 * reword every badge without a republish, and no badge text is ever built from learner data.
 *
 *   {course}        the course's full name
 *   {competencies}  ltct_competencies, "[A] [B]" written as "A, B"
 *   {target_level}  ltct_target_level, a CBC label. site_config.py validate allows it only
 *                   after "designed to support progress towards", so the level describes the
 *                   course and never the learner (FR-005).
 *
 * No Moodle call is made here, so tests/recognition_harness.php runs it without a Moodle.
 */
class renderer {

    /** The badge's text fields, as the template names them. */
    const FIELDS = ['name', 'description', 'imagecaption', 'message_subject', 'message'];

    /** The placeholders filled per course. */
    const PLACEHOLDERS = ['course', 'competencies', 'target_level'];

    /**
     * The competency field's names, as a reader would write them: "[A] [B]" becomes "A, B".
     *
     * @param string $field the ltct_competencies value, each name in brackets
     * @return string
     */
    public static function competencies(string $field): string {
        preg_match_all('/\[([^\[\]]+)\]/', $field, $m);
        return implode(', ', array_map('trim', $m[1]));
    }

    /**
     * Every text field of one course's badge.
     *
     * @param array $template the stored template: FIELDS, each a string
     * @param string $course the course's full name
     * @param string $competencies the ltct_competencies field, "[A] [B]"
     * @param string $target the ltct_target_level field
     * @return array<string, string> FIELDS => rendered text
     */
    public static function render(array $template, string $course, string $competencies,
            string $target): array {
        $values = [
            '{course}' => $course,
            '{competencies}' => self::competencies($competencies),
            '{target_level}' => $target,
        ];
        $out = [];
        foreach (self::FIELDS as $field) {
            // strtr() replaces in one pass, so a course named "{target_level}" is printed as
            // written rather than substituted a second time.
            $out[$field] = trim(strtr((string)($template[$field] ?? ''), $values));
        }
        return $out;
    }

    /**
     * The issuer URL: the site's scheme, host and port, as the badge form fills it
     * (badges/classes/form/badge.php). Never a literal host: it moves with the server.
     *
     * @param string $wwwroot $CFG->wwwroot
     * @return string
     */
    public static function issuerurl(string $wwwroot): string {
        $parts = parse_url($wwwroot);
        $url = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        if (!empty($parts['port'])) {
            $url .= ':' . $parts['port'];
        }
        return $url;
    }

    /**
     * The award message as Moodle stores it: one paragraph of HTML. The text is escaped,
     * so a course name cannot add markup; core substitutes %badgename%, %username% and
     * %badgelink% when it sends the message (lib/badgeslib.php badges_notify_badge_award()).
     *
     * @param string $message the rendered message text
     * @return string
     */
    public static function message_html(string $message): string {
        return '<p>' . htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>';
    }
}
