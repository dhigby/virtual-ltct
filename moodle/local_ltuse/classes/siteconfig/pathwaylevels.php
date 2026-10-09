<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

/**
 * Checks and applies the four CBC level labels a pathway shows (spec 006, FR-003, R6).
 *
 * The declaration arrives as the payload's `levels` array, rendered by scripts/site_config.py
 * from the repo-root outcome-levels.yaml, one entry per id in `course_target_levels`
 * (specs/006-learning-pathways/contracts/declaration.md "Level labels"):
 *
 *   levels [{level: 1, label: "1 - Has Knowledge"}, ... {level: 4, label: "4 - Expert"}]
 *
 * Each label is stored in the plugin config `local_ltuse/pathwaylevel<n>`, through core's
 * set_config(), and the pathway pages read a level's heading from there and nowhere else. The
 * labels are copied verbatim: this class never re-types or reformats one. site_config.py
 * validate refuses a level list that is not exactly 1-4, or a label that is not a CBC label;
 * invalid() is the plugin's own guard.
 *
 * Lifecycle (subject `pathway level <n>`):
 *   unset                inserted; reported changed/missing 'created';
 *   differs              set back, reported `changed`.
 * A level label carries no course, cohort or user (constitution III). Setting one fires no
 * event (contracts/declaration.md "What apply does").
 */
class pathwaylevels {

    /** Item type. */
    const TYPE = 'pathwaylevel';

    /** The plugin whose config holds the labels. */
    const COMPONENT = 'local_ltuse';

    /** Config name prefix: pathwaylevel1 ... pathwaylevel4. */
    const CONFIG_PREFIX = 'pathwaylevel';

    /** The levels a pathway has a row for (outcome-levels.yaml course_target_levels). */
    const LEVELS = [1, 2, 3, 4];

    /** The longest label a config value is allowed to hold here. */
    const MAX_LENGTH = 255;

    /** @var array[] the declared levels */
    protected $declared;

    /**
     * @param array $levels the payload's `levels` array
     */
    public function __construct(array $levels) {
        $this->declared = array_values(array_map(function($level) {
            return (array)$level;
        }, $levels));
    }

    /**
     * The subject of a level's item.
     *
     * @param mixed $level
     * @return string
     */
    public static function subject($level): string {
        return 'pathway level ' . (is_scalar($level) ? (string)$level : '?');
    }

    /**
     * The config name for a level.
     *
     * @param int $level
     * @return string
     */
    public static function config_name(int $level): string {
        return self::CONFIG_PREFIX . $level;
    }

    /**
     * The stored label for a level, or null when none is stored or it is not text. This is the
     * one read the pathway pages make; a missing or malformed value shows an error there,
     * never a guessed label (data-model "Plugin config").
     *
     * @param int $level
     * @return string|null
     */
    public static function stored(int $level): ?string {
        if (!in_array($level, self::LEVELS, true)) {
            return null;
        }
        $value = get_config(self::COMPONENT, self::config_name($level));
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        return $value;
    }

    // --- checking (read only) ------------------------------------------------------------

    /**
     * One item per declared level, in declaration order. WRITES NOTHING.
     *
     * @return array[] item results in inspector's shape
     */
    public function check(): array {
        $items = [];
        foreach ($this->declared as $index => $level) {
            $items[] = $this->check_level($level, $index);
        }
        return $items;
    }

    /**
     * Compare one declared level with the stored config.
     *
     * @param array $level {level, label}
     * @param int|null $index its position in the declaration, for the duplicate check
     * @return array item result
     */
    public function check_level(array $level, ?int $index = null): array {
        $subject = self::subject($level['level'] ?? null);
        $problem = self::invalid($level);
        if ($problem === null && $index !== null && $this->declared_twice((int)$level['level'], $index)) {
            $problem = 'declared more than once';
        }
        if ($problem !== null) {
            return self::result($subject, 'unknown', self::display($level['label'] ?? null), null, $problem, true);
        }

        $label = (string)$level['label'];
        $live = get_config(self::COMPONENT, self::config_name((int)$level['level']));
        if ($live === false || $live === null) {
            return self::result($subject, 'missing', $label, null, 'apply will set it');
        }
        if ((string)$live !== $label) {
            return self::result($subject, 'changed', $label, (string)$live, 'differs: label');
        }
        return self::result($subject, 'ok', $label, (string)$live);
    }

    // --- applying ------------------------------------------------------------------------

    /**
     * Set each label that is unset or differs. The caller runs the preflight first; a
     * blocking item that reaches here is still reported and never written.
     *
     * @param report $report
     * @return void
     */
    public function apply(report $report): void {
        foreach ($this->declared as $index => $level) {
            $item = $this->check_level($level, $index);
            if ($item['result'] !== 'missing' && $item['result'] !== 'changed') {
                $report->add_result($item);
                continue;
            }
            set_config(self::config_name((int)$level['level']), (string)$level['label'], self::COMPONENT);
            $after = $this->check_level($level, $index);
            if ($after['result'] !== 'ok') {
                $report->add_result($after, 'fail', 'written, but the server still differs');
            } else if ($item['result'] === 'missing') {
                $report->add('changed', 'missing', $item['item'], $item['declared'], null, 'created');
            } else {
                $report->add_result($item, 'changed');
            }
        }
    }

    // --- pure checks (no database) -------------------------------------------------------

    /**
     * Why a declared level cannot be applied, or null when it can.
     *
     * @param array $level
     * @return string|null
     */
    public static function invalid(array $level): ?string {
        $id = $level['level'] ?? null;
        $label = $level['label'] ?? null;
        if (!is_int($id) || !in_array($id, self::LEVELS, true)) {
            return 'level must be one of ' . implode(', ', self::LEVELS);
        }
        if (!is_string($label) || trim($label) === '') {
            return 'a level needs its CBC label';
        }
        if (\core_text::strlen($label) > self::MAX_LENGTH) {
            return 'label is longer than ' . self::MAX_LENGTH . ' characters';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $label)) {
            return 'label holds a control character';
        }
        return null;
    }

    /**
     * @param int $level
     * @param int $index
     * @return bool whether the level is declared at another position too
     */
    protected function declared_twice(int $level, int $index): bool {
        foreach ($this->declared as $other => $declared) {
            if ($other !== $index && ($declared['level'] ?? null) === $level) {
                return true;
            }
        }
        return false;
    }

    // --- results -------------------------------------------------------------------------

    /**
     * Build one item result, in inspector's shape.
     *
     * @param string $item the subject
     * @param string $result ok, changed, missing or unknown
     * @param mixed $declared
     * @param mixed $live
     * @param string $message
     * @param bool $blocking
     * @return array
     */
    protected static function result(string $item, string $result, $declared = null, $live = null,
            string $message = '', bool $blocking = false): array {
        return [
            'type' => self::TYPE,
            'item' => $item,
            'result' => $result,
            'declared' => $declared,
            'live' => $live,
            'message' => $message,
            'secret' => false,
            'blocking' => $blocking,
        ];
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected static function display($value): string {
        if ($value === null) {
            return '(none)';
        }
        return is_scalar($value) ? (string)$value : json_encode($value);
    }
}
