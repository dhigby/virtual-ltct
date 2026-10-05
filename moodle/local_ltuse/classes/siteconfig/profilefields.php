<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/profile/definelib.php');

use stdClass;

/**
 * Checks and applies the declared profile field category and its fields (FR-008, FR-009, R5).
 *
 * The declaration arrives as the payload's `profile_fields` array, already expanded by
 * scripts/site_config.py (specs/002-org-structure-cohorts/data-model.md "Rendered payload"):
 *
 *   profile_fields [{category, shortname, name, datatype, visible, locked, required, options}]
 *
 * `options_from` is already resolved to the organisation keys, so this class sees one plain
 * `options` list per menu. Every field names its category; the categories to check are the
 * distinct `category` values, in the order they first appear.
 *
 * Identity: a category by its `name`, because user_info_category has no idnumber; a field by
 * its `shortname`. Both are compared case-sensitively, as core's define_validate_common()
 * compares shortnames. Reading user_info_category and user_info_field is a read of stable core
 * tables; writing goes only through profile_save_category() and profile_save_field()
 * (public/user/profile/definelib.php, MOODLE_502_STABLE), so core's events fire and its caches
 * are purged (constitution XI).
 *
 * What it compares on a field: name, category, visible, locked, required and, for a menu, the
 * options. A field of another datatype is a blocking `wrong-datatype`: changing the type would
 * discard every learner's value. A live menu option that is no longer declared is `extra` and
 * is kept, because learners may hold that value (FR-004). The description and default value
 * are never compared and, on an update, are written back unchanged.
 *
 * LEARNER DATA IS NEVER TOUCHED. Nothing here reads or writes user_info_data, and no item names
 * a user or a profile value: subjects are `profilecategory:<name>` and
 * `profilefield:<shortname>` only (constitution III).
 */
class profilefields {

    /** Declared visibility words to core's PROFILE_VISIBLE_* values (contracts/declaration.md). */
    const VISIBILITY = [
        'none' => '0',
        'private' => '1',
        'all' => '2',
        'teachers' => '3',
    ];

    /** The datatypes the declaration contract allows. */
    const DATATYPES = ['menu', 'checkbox'];

    /** @var array[] the declared fields */
    protected $fields;

    /**
     * @param array $fields the payload's `profile_fields` array
     */
    public function __construct(array $fields) {
        $this->fields = array_map(function($field) {
            return (array)$field;
        }, $fields);
    }

    /**
     * The declared category names, in the order the fields first name them.
     *
     * @return string[]
     */
    public function category_names(): array {
        $names = [];
        foreach ($this->fields as $field) {
            $name = (string)($field['category'] ?? '');
            if (!in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        return $names;
    }

    // --- checking (read only) ------------------------------------------------------------

    /**
     * Every item result, in apply order: each category, then each field and its extra options.
     * WRITES NOTHING.
     *
     * @return array[] item results in inspector's shape
     */
    public function check(): array {
        $items = [];
        foreach ($this->category_names() as $name) {
            $items[] = $this->check_category($name);
        }
        foreach ($this->fields as $field) {
            $items = array_merge($items, $this->check_field($field));
        }
        return $items;
    }

    /**
     * Is the declared category present, once?
     *
     * @param string $name
     * @return array item result; ambiguous blocks apply
     */
    public function check_category(string $name): array {
        $subject = "profilecategory:{$name}";
        if ($name === '') {
            return self::result('profilecategory', $subject, 'unknown', null, null,
                'a profile field declares no category', true);
        }
        $live = $this->find_categories($name);
        if (!$live) {
            return self::result('profilecategory', $subject, 'missing', $name, null, 'apply will create it');
        }
        if (count($live) > 1) {
            return self::result('profilecategory', $subject, 'ambiguous', $name, null,
                'more than one profile field category has this name: ids '
                . implode(', ', array_keys($live)), true);
        }
        return self::result('profilecategory', $subject, 'ok', $name, $name);
    }

    /**
     * Compare one declared field with the live one.
     *
     * @param array $field {category, shortname, name, datatype, visible, locked, required, options}
     * @return array[] the field's item result, followed by one `extra` per undeclared live option
     */
    public function check_field(array $field): array {
        $shortname = (string)($field['shortname'] ?? '');
        $subject = "profilefield:{$shortname}";
        $datatype = (string)($field['datatype'] ?? '');

        if (!in_array($datatype, self::DATATYPES, true)) {
            return [self::result('profilefield', $subject, 'unknown', $datatype, null,
                'datatype must be ' . implode(' or ', self::DATATYPES), true)];
        }
        if (self::visible_code($field['visible'] ?? null) === null) {
            return [self::result('profilefield', $subject, 'unknown', self::display($field['visible'] ?? null),
                null, 'visible must be ' . implode(', ', array_keys(self::VISIBILITY)), true)];
        }

        $live = $this->find_field($shortname);
        if ($live === null) {
            return [self::result('profilefield', $subject, 'missing', self::summary($field), null,
                'apply will create it')];
        }
        if ((string)$live->datatype !== $datatype) {
            return [self::result('profilefield', $subject, 'wrong-datatype', $datatype,
                (string)$live->datatype, 'changing the type would discard every value; change it by hand', true)];
        }

        $categories = $this->find_categories((string)$field['category']);
        $categoryid = count($categories) === 1 ? (int)array_key_first($categories) : null;
        $differences = self::differences($field, $live, $categoryid);
        if (isset($differences['category'])) {
            $differences['category'][1] = $this->category_name((int)$live->categoryid);
        }

        $items = [];
        if ($differences) {
            $items[] = self::result('profilefield', $subject, 'changed',
                self::difference_text($differences, 0), self::difference_text($differences, 1),
                'differs: ' . implode(', ', array_keys($differences)));
        } else {
            $items[] = self::result('profilefield', $subject, 'ok', self::summary($field), self::summary($field));
        }

        if ($datatype === 'menu') {
            $extra = self::extra_options(self::declared_options($field), self::menu_options((string)$live->param1));
            foreach ($extra as $option) {
                $items[] = self::result('profilefield', $subject, 'extra', null, $option,
                    'menu option no longer declared; kept, because learners may hold it');
            }
        }
        return $items;
    }

    // --- applying ------------------------------------------------------------------------

    /**
     * Bring the categories and fields to the declaration, writing only what differs.
     *
     * The caller runs the preflight first: apply writes nothing while anything blocks. A
     * blocking item that reaches here is still reported and never written.
     *
     * In apply, an extra menu option is reported as `[skip]`: it is kept on purpose, so it is
     * not a failure of the run.
     *
     * @param report $report
     * @return void
     */
    public function apply(report $report): void {
        foreach ($this->category_names() as $name) {
            $this->apply_category($name, $report);
        }
        foreach ($this->fields as $field) {
            $this->apply_field($field, $report);
        }
    }

    /**
     * @param string $name
     * @param report $report
     * @return void
     */
    protected function apply_category(string $name, report $report): void {
        $item = $this->check_category($name);
        if ($item['result'] !== 'missing') {
            $report->add_result($item);
            return;
        }
        profile_save_category((object)['name' => $name]);
        $after = $this->check_category($name);
        if ($after['result'] === 'ok') {
            $report->add('changed', 'missing', $item['item'], $name, null, 'created');
        } else {
            $report->add_result($after, 'fail', 'written, but the server still differs');
        }
    }

    /**
     * @param array $field
     * @param report $report
     * @return void
     */
    protected function apply_field(array $field, report $report): void {
        $items = $this->check_field($field);
        $item = array_shift($items);

        if ($item['result'] === 'missing' || $item['result'] === 'changed') {
            $categories = $this->find_categories((string)$field['category']);
            if (count($categories) !== 1) {
                $report->add_result($item, 'fail', 'its category ' . "'{$field['category']}'"
                    . ' is not on the server exactly once');
            } else {
                $live = $this->find_field((string)$field['shortname']);
                profile_save_field(self::save_data($field, $live, (int)array_key_first($categories)), []);
                $after = $this->check_field($field)[0];
                if ($after['result'] !== 'ok') {
                    $report->add_result($after, 'fail', 'written, but the server still differs');
                } else if ($item['result'] === 'missing') {
                    $report->add('changed', 'missing', $item['item'], $item['declared'], null, 'created');
                } else {
                    $report->add_result($item, 'changed');
                }
            }
        } else {
            $report->add_result($item);
        }

        foreach ($items as $extra) {
            $report->add_result($extra, 'skip');
        }
    }

    /**
     * The record profile_save_field() takes: the live record with the declared columns laid
     * over it, or a new record. The description and default value are carried through
     * unchanged on an update; a new field has neither. A menu keeps its extra live options.
     *
     * @param array $field the declared field
     * @param stdClass|null $live the live user_info_field record, or null to create
     * @param int $categoryid the declared category's id
     * @return stdClass
     */
    public static function save_data(array $field, ?stdClass $live, int $categoryid): stdClass {
        if ($live !== null) {
            $data = clone $live;
            $data->description = ['text' => (string)$live->description, 'format' => (int)$live->descriptionformat];
        } else {
            $data = new stdClass();
            $data->id = 0;
            $data->shortname = (string)$field['shortname'];
            $data->datatype = (string)$field['datatype'];
            $data->description = ['text' => '', 'format' => FORMAT_HTML];
            $data->defaultdata = $field['datatype'] === 'checkbox' ? '0' : '';
            $data->forceunique = 0;
            $data->signup = 0;
        }
        $data->categoryid = $categoryid;
        $data->name = (string)$field['name'];
        $data->visible = self::visible_code($field['visible']);
        $data->locked = (int)$field['locked'];
        $data->required = (int)($field['required'] ?? 0);
        if ($field['datatype'] === 'menu') {
            $liveoptions = $live !== null ? self::menu_options((string)$live->param1) : [];
            $data->param1 = implode("\n", self::merged_options(self::declared_options($field), $liveoptions));
        }
        return $data;
    }

    // --- pure comparisons (no database) --------------------------------------------------

    /**
     * Core's stored visibility for a declared word. A stored value ('0'..'3') is accepted too.
     *
     * @param mixed $visible
     * @return string|null null when it is neither
     */
    public static function visible_code($visible): ?string {
        if (!is_scalar($visible)) {
            return null;
        }
        $visible = (string)$visible;
        if (isset(self::VISIBILITY[$visible])) {
            return self::VISIBILITY[$visible];
        }
        return in_array($visible, self::VISIBILITY, true) ? $visible : null;
    }

    /**
     * The declaration word for a stored visibility, for display.
     *
     * @param mixed $code
     * @return string the word, or the raw value when core has no such constant
     */
    public static function visible_word($code): string {
        $word = array_search((string)$code, self::VISIBILITY, true);
        return $word === false ? (string)$code : $word;
    }

    /**
     * A menu's options from its param1: one per line, carriage returns and empty lines dropped.
     *
     * @param string $param1
     * @return string[]
     */
    public static function menu_options(string $param1): array {
        $options = explode("\n", str_replace("\r", '', $param1));
        return array_values(array_filter($options, function($option) {
            return trim($option) !== '';
        }));
    }

    /**
     * The declared options of a field, as strings.
     *
     * @param array $field
     * @return string[]
     */
    public static function declared_options(array $field): array {
        return array_values(array_map('strval', (array)($field['options'] ?? [])));
    }

    /**
     * Live options that are no longer declared, in live order.
     *
     * @param string[] $declared
     * @param string[] $live
     * @return string[]
     */
    public static function extra_options(array $declared, array $live): array {
        return array_values(array_filter($live, function($option) use ($declared) {
            return !in_array($option, $declared, true);
        }));
    }

    /**
     * The options a menu should hold: the declared ones in declaration order, then any extra
     * live ones, kept in live order.
     *
     * @param string[] $declared
     * @param string[] $live
     * @return string[]
     */
    public static function merged_options(array $declared, array $live): array {
        return array_merge($declared, self::extra_options($declared, $live));
    }

    /**
     * The properties of a live field that differ from the declaration, each as
     * [declared, live] display text. The datatype is checked before this, not here.
     *
     * @param array $field the declared field
     * @param stdClass $live the live user_info_field record
     * @param int|null $categoryid the declared category's id, or null when it is not on the
     *     server exactly once (the category is then reported by its own item)
     * @return array<string, string[]> keyed by property name, in a fixed order
     */
    public static function differences(array $field, stdClass $live, ?int $categoryid): array {
        $diff = [];
        if ((string)$field['name'] !== (string)$live->name) {
            $diff['name'] = [(string)$field['name'], (string)$live->name];
        }
        if ($categoryid === null || $categoryid !== (int)$live->categoryid) {
            $diff['category'] = [(string)$field['category'], (string)$live->categoryid];
        }
        if (self::visible_code($field['visible']) !== (string)$live->visible) {
            $diff['visible'] = [self::visible_word(self::visible_code($field['visible'])),
                self::visible_word($live->visible)];
        }
        foreach (['locked', 'required'] as $property) {
            $declared = (int)($field[$property] ?? 0);
            if ($declared !== (int)$live->$property) {
                $diff[$property] = [(string)$declared, (string)(int)$live->$property];
            }
        }
        if ((string)$field['datatype'] === 'menu') {
            $liveoptions = self::menu_options((string)$live->param1);
            $want = self::merged_options(self::declared_options($field), $liveoptions);
            if ($want !== $liveoptions) {
                $diff['options'] = [implode(' | ', $want), implode(' | ', $liveoptions)];
            }
        }
        return $diff;
    }

    /**
     * One side of a set of differences as text: `name 'X', visible 'all'`.
     *
     * @param array<string, string[]> $differences
     * @param int $side 0 for declared, 1 for live
     * @return string
     */
    public static function difference_text(array $differences, int $side): string {
        $parts = [];
        foreach ($differences as $property => $values) {
            $parts[] = "{$property} {$values[$side]}";
        }
        return implode('; ', $parts);
    }

    /**
     * A field's declared definition as one line, for an ok or missing item.
     *
     * @param array $field
     * @return string
     */
    public static function summary(array $field): string {
        $text = "{$field['datatype']}, visible " . self::visible_word(self::visible_code($field['visible']))
            . ', locked ' . (int)$field['locked'] . ', required ' . (int)($field['required'] ?? 0);
        if ($field['datatype'] === 'menu') {
            $text .= ', options ' . implode(' | ', self::declared_options($field));
        }
        return $text;
    }

    // --- reads ---------------------------------------------------------------------------

    /**
     * Profile field categories whose name is exactly this one, keyed by id.
     *
     * @param string $name
     * @return stdClass[]
     */
    protected function find_categories(string $name): array {
        global $DB;
        $records = $DB->get_records('user_info_category', ['name' => $name], 'id ASC', 'id, name');
        return array_filter($records, function($record) use ($name) {
            return (string)$record->name === $name;
        });
    }

    /**
     * The live field with exactly this shortname, or null.
     *
     * @param string $shortname
     * @return stdClass|null
     */
    protected function find_field(string $shortname): ?stdClass {
        global $DB;
        foreach ($DB->get_records('user_info_field', ['shortname' => $shortname], 'id ASC') as $record) {
            if ((string)$record->shortname === $shortname) {
                return $record;
            }
        }
        return null;
    }

    /**
     * A category's name for display, or its id when it has gone.
     *
     * @param int $id
     * @return string
     */
    protected function category_name(int $id): string {
        global $DB;
        $name = $DB->get_field('user_info_category', 'name', ['id' => $id]);
        return $name === false ? "id {$id}" : (string)$name;
    }

    // --- results -------------------------------------------------------------------------

    /**
     * Build one item result, in inspector's shape.
     *
     * @param string $type 'profilecategory' or 'profilefield'
     * @param string $item the subject
     * @param string $result ok, changed, missing, extra, unknown, ambiguous or wrong-datatype
     * @param mixed $declared
     * @param mixed $live
     * @param string $message
     * @param bool $blocking
     * @return array
     */
    protected static function result(string $type, string $item, string $result, $declared = null,
            $live = null, string $message = '', bool $blocking = false): array {
        return [
            'type' => $type,
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
     * @return string|null
     */
    protected static function display($value): ?string {
        if ($value === null) {
            return null;
        }
        return is_scalar($value) ? (string)$value : json_encode($value);
    }
}
