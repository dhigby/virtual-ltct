<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

/**
 * The run report for site_config.php: what `apply` and `drift` print, and how they exit.
 *
 * The shape is specs/001-site-config-as-code/contracts/output.md. Line output streams as
 * items are added, so the target line is on the terminal before anything is written to
 * the server; `--json` buffers and prints one document at the end, for evidence.
 *
 * REDACTION IS NEVER RELAXED. A setting declared `secret: true` (an `env:` secret) and any
 * setting whose admin class is admin_setting_configpasswordunmask, or a subclass, shows
 * `<secret>` for both the declared and the live value, whether or not it was declared. If
 * the value itself turns up in an item's message, it is scrubbed there too. The decision
 * is made here, in one place, so a caller cannot forget it: callers pass raw values and
 * the admin setting they came from, and the report decides what may be shown.
 *
 * No item may name a user. Subjects are setting keys, plugin components and
 * role:capability pairs only (FR-009).
 *
 * Exit codes (FR-010, research R11): 0 clean or applied, 1 drift found or a step failed,
 * 2 usage or configuration error.
 */
class report {

    /** Placeholder printed in place of any secret value. */
    const SECRET = '<secret>';

    /** Item statuses, printed as [ok], [changed], [fail], [skip]. */
    const STATUSES = ['ok', 'changed', 'fail', 'skip'];

    /** Item kinds (data-model.md "Item result"). Empty means none, for an ok item. */
    const KINDS = ['', 'changed', 'missing', 'extra', 'unmanaged', 'wrong-release',
        'pending-upgrade', 'forced', 'unknown', 'env-missing', 'below-minimum'];

    /** Kinds that carry no declared value. */
    const NO_DECLARED = ['extra', 'unmanaged'];

    /** Kinds that carry no live value. */
    const NO_LIVE = ['missing'];

    const EXIT_OK = 0;
    const EXIT_FAIL = 1;
    const EXIT_USAGE = 2;

    /** @var string apply or drift */
    protected $mode;

    /** @var bool print one JSON document instead of lines */
    protected $json;

    /** @var callable receives each chunk of output */
    protected $out;

    /** @var string */
    protected $target = '';

    /** @var string */
    protected $release = '';

    /** @var bool whether the target line has been emitted */
    protected $started = false;

    /** @var bool whether the summary has been emitted */
    protected $finished = false;

    /** @var array<string, true> subjects declared secret */
    protected $secrets = [];

    /** @var array<int, array> item results, already redacted */
    protected $items = [];

    /** @var string|null set once a usage or configuration error is reported */
    protected $usageerror = null;

    /**
     * @param string $mode 'apply' or 'drift'
     * @param bool $json print a JSON document instead of lines
     * @param callable|null $out output sink; defaults to writing to stdout
     */
    public function __construct(string $mode, bool $json = false, ?callable $out = null) {
        if ($mode !== 'apply' && $mode !== 'drift') {
            throw new \coding_exception("report mode must be apply or drift, not '{$mode}'");
        }
        $this->mode = $mode;
        $this->json = $json;
        $this->out = $out ?? function(string $text): void {
            echo $text;
        };
    }

    /**
     * Mark every setting the payload declares `secret: true` as one to redact.
     *
     * @param array $settings the payload's `settings` list, each with `name` and `secret`
     * @return void
     */
    public function mark_secrets(array $settings): void {
        foreach ($settings as $setting) {
            $setting = (array) $setting;
            if (!empty($setting['secret']) && isset($setting['name'])) {
                $this->mark_secret((string) $setting['name']);
            }
        }
    }

    /**
     * Mark one subject (a setting key, `name` or `plugin/name`) as secret.
     *
     * @param string $subject
     * @return void
     */
    public function mark_secret(string $subject): void {
        $this->secrets[$subject] = true;
    }

    /**
     * Whether an admin setting holds a password, so its value must never be shown.
     *
     * Fails closed: this is decided by class, so a password setting nobody declared, or
     * one a plugin subclasses, is still redacted.
     *
     * @param mixed $setting an admin_setting, or null
     * @return bool
     */
    public static function is_password_setting($setting): bool {
        return is_object($setting) && $setting instanceof \admin_setting_configpasswordunmask;
    }

    /**
     * Whether a subject's values must be shown as `<secret>`.
     *
     * @param string $subject
     * @param mixed $setting the admin_setting it came from, if any
     * @return bool
     */
    public function is_secret(string $subject, $setting = null): bool {
        return isset($this->secrets[$subject]) || self::is_password_setting($setting)
            || self::has_secret_name($subject);
    }

    /**
     * Whether a setting's name says it holds a secret: a password, key, token or salt that is
     * stored in a plain text setting (airnotifieraccesskey, calendar_exportsalt). Fails closed:
     * a few harmless names are hidden too. The same pattern as site_config.py validate.
     *
     * @param string $subject a setting key, `name` or `plugin/name`
     * @return bool
     */
    public static function has_secret_name(string $subject): bool {
        $name = substr($subject, (int)strrpos($subject, '/'));
        return (bool)preg_match('/pass|secret|token|key|salt/i', $name);
    }

    /**
     * Emit the first line: the target and its release. Nothing may be written to the
     * server before this has been called.
     *
     * @param string $target $CFG->wwwroot
     * @param string $release $CFG->release
     * @return void
     */
    public function start(string $target, string $release): void {
        if ($this->started) {
            return;
        }
        $this->target = $target;
        $this->release = $release;
        $this->started = true;
        if (!$this->json) {
            $this->emit("Target: {$target} (Moodle {$release}), mode {$this->mode}\n");
        }
    }

    /**
     * Add one item result. Values are redacted here, never by the caller.
     *
     * @param string $status ok, changed, fail or skip
     * @param string $kind one of KINDS; '' for an ok item
     * @param string $subject setting key, plugin component, or role:capability
     * @param mixed $declared the declared value, or null for none
     * @param mixed $live the live value, or null for none
     * @param string $message Moodle's error, a variable name, or the two releases
     * @param mixed $setting the admin_setting the value belongs to, if any
     * @return void
     */
    public function add(string $status, string $kind, string $subject, $declared = null,
            $live = null, string $message = '', $setting = null): void {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \coding_exception("unknown report status '{$status}'");
        }
        if (!in_array($kind, self::KINDS, true)) {
            throw new \coding_exception("unknown report kind '{$kind}'");
        }
        if ($status !== 'ok' && $kind === '' && $status !== 'skip') {
            throw new \coding_exception("a '{$status}' item needs a kind: {$subject}");
        }
        if (in_array($kind, self::NO_DECLARED, true)) {
            $declared = null;
        }
        if (in_array($kind, self::NO_LIVE, true)) {
            $live = null;
        }

        $declared = self::as_text($declared);
        $live = self::as_text($live);
        if ($this->is_secret($subject, $setting)) {
            // Scrub the raw values out of the message before they are replaced, so a
            // Moodle error that echoes the value cannot carry it out.
            foreach ([$declared, $live] as $raw) {
                if ($raw !== null && $raw !== '') {
                    $message = str_replace($raw, self::SECRET, $message);
                }
            }
            $declared = $declared === null ? null : self::SECRET;
            $live = $live === null ? null : self::SECRET;
        }

        $item = ['status' => $status, 'kind' => $kind, 'item' => $subject];
        if ($declared !== null) {
            $item['declared'] = $declared;
        }
        if ($live !== null) {
            $item['live'] = $live;
        }
        $item['message'] = $message;
        $this->items[] = $item;

        if (!$this->json) {
            $this->start_if_needed();
            $this->emit(self::format_line($item) . "\n");
        }
    }

    /**
     * Add one inspector result. An ok result is `[ok]`; anything else is `[fail]` with the
     * result as its kind, unless the caller passes the status it ended in (an apply write).
     *
     * @param array $result an item result from inspector
     * @param string|null $status override, e.g. 'changed' after a successful write
     * @param string|null $message override for the result's message
     * @return void
     */
    public function add_result(array $result, ?string $status = null, ?string $message = null): void {
        if (!empty($result['secret'])) {
            $this->mark_secret($result['item']);
        }
        $ok = $result['result'] === 'ok';
        $this->add($status ?? ($ok ? 'ok' : 'fail'), $ok ? '' : $result['result'], $result['item'],
            $result['declared'], $result['live'], $message ?? (string)$result['message']);
    }

    /**
     * Record a usage or configuration error. The run exits 2 and changes nothing.
     *
     * @param string $message
     * @return void
     */
    public function usage_error(string $message): void {
        $this->usageerror = $message;
    }

    /**
     * @return bool whether a usage or configuration error has been recorded
     */
    public function has_usage_error(): bool {
        return $this->usageerror !== null;
    }

    /**
     * @return bool whether any item failed (a blocking problem or a failed step)
     */
    public function has_failures(): bool {
        foreach ($this->items as $item) {
            if ($item['status'] === 'fail') {
                return true;
            }
        }
        return false;
    }

    /**
     * Counts for the summary line and the JSON `summary`.
     *
     * `differences` counts every item that is not `ok` and carries a kind: in drift,
     * each is a way the server differs from the declaration.
     *
     * @return array{changed: int, failed: int, differences: int, ok: int, skipped: int}
     */
    public function summary(): array {
        $counts = ['changed' => 0, 'failed' => 0, 'differences' => 0, 'ok' => 0, 'skipped' => 0];
        foreach ($this->items as $item) {
            switch ($item['status']) {
                case 'ok':
                    $counts['ok']++;
                    break;
                case 'changed':
                    $counts['changed']++;
                    break;
                case 'fail':
                    $counts['failed']++;
                    break;
                case 'skip':
                    $counts['skipped']++;
                    break;
            }
            if ($item['status'] !== 'ok' && $item['kind'] !== '') {
                $counts['differences']++;
            }
        }
        return $counts;
    }

    /**
     * The exit code for this run.
     *
     * @return int 0, 1 or 2
     */
    public function exit_code(): int {
        if ($this->usageerror !== null) {
            return self::EXIT_USAGE;
        }
        if ($this->has_failures()) {
            return self::EXIT_FAIL;
        }
        if ($this->mode === 'drift' && $this->summary()['differences'] > 0) {
            return self::EXIT_FAIL;
        }
        return self::EXIT_OK;
    }

    /**
     * The items recorded so far, already redacted.
     *
     * @return array
     */
    public function items(): array {
        return $this->items;
    }

    /**
     * The whole report as the JSON document in contracts/output.md.
     *
     * @return array
     */
    public function to_array(): array {
        $summary = $this->summary();
        $doc = [
            'target' => $this->target,
            'release' => $this->release,
            'mode' => $this->mode,
            'items' => $this->items,
            'summary' => [
                'changed' => $summary['changed'],
                'failed' => $summary['failed'],
                'differences' => $summary['differences'],
            ],
        ];
        if ($this->usageerror !== null) {
            $doc['error'] = $this->usageerror;
        }
        return $doc;
    }

    /**
     * The final summary line.
     *
     * @return string
     */
    public function summary_line(): string {
        if ($this->usageerror !== null) {
            return "Error: {$this->usageerror}. Nothing was changed.";
        }
        $s = $this->summary();
        if ($this->mode === 'drift') {
            if ($s['differences'] === 0 && $s['failed'] === 0) {
                return 'No differences.';
            }
            return "{$s['differences']} " . ($s['differences'] === 1 ? 'difference' : 'differences')
                . ", {$s['ok']} ok, {$s['failed']} failed.";
        }
        if ($s['failed'] > 0) {
            return "{$s['changed']} changed, {$s['ok']} ok, {$s['failed']} failed.";
        }
        if ($s['changed'] === 0) {
            return "Nothing changed ({$s['ok']} ok).";
        }
        return "{$s['changed']} changed, {$s['ok']} ok, 0 failed.";
    }

    /**
     * Emit the summary (lines) or the whole document (JSON), and return the exit code.
     * Call once, at the end of the run.
     *
     * @return int the exit code
     */
    public function finish(): int {
        if (!$this->finished) {
            $this->finished = true;
            if ($this->json) {
                $this->emit(json_encode($this->to_array(),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
            } else {
                $this->start_if_needed();
                $this->emit($this->summary_line() . "\n");
            }
        }
        return $this->exit_code();
    }

    /**
     * Format one item as a line: `[status] subject (kind): declared X, live Y - message`.
     *
     * @param array $item
     * @return string
     */
    public static function format_line(array $item): string {
        $line = "[{$item['status']}] {$item['item']}";
        if ($item['kind'] !== '') {
            $line .= " ({$item['kind']})";
        }
        $parts = [];
        if (array_key_exists('declared', $item)) {
            $parts[] = 'declared ' . self::quote($item['declared']);
        }
        if (array_key_exists('live', $item)) {
            $parts[] = 'live ' . self::quote($item['live']);
        }
        if ($parts) {
            $line .= ': ' . implode(', ', $parts);
        }
        if ($item['message'] !== '') {
            $line .= ($parts ? ' - ' : ': ') . $item['message'];
        }
        return $line;
    }

    /**
     * Quote a value for a line, leaving the secret placeholder bare.
     *
     * @param string $value
     * @return string
     */
    protected static function quote(string $value): string {
        return $value === self::SECRET ? $value : "'{$value}'";
    }

    /**
     * Normalise a value to text, or null for none.
     *
     * @param mixed $value
     * @return string|null
     */
    protected static function as_text($value): ?string {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Emit a target line if none has been emitted, so the first line always names the
     * target even when a caller adds an item first.
     *
     * @return void
     */
    protected function start_if_needed(): void {
        if (!$this->started) {
            $this->started = true;
            $this->emit("Target: (unknown), mode {$this->mode}\n");
        }
    }

    /**
     * @param string $text
     * @return void
     */
    protected function emit(string $text): void {
        ($this->out)($text);
    }
}
