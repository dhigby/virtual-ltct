<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

use core_course\customfield\course_handler;
use core_customfield\category_controller;
use core_customfield\field_controller;
use stdClass;

/**
 * Checks and applies the declared course custom field category and its fields (spec 004, R11).
 *
 * The declaration arrives as two payload keys, rendered by scripts/site_config.py from
 * moodle/site/course-fields.yaml (specs/004-progress-reporting/data-model.md "Course fields"):
 *
 *   course_field_category  "LTC curriculum"
 *   course_fields          [{shortname, name, type, locked, visibility}]
 *
 * `visibility` is core's course_handler value (2 everyone, 1 teachers, 0 nobody); the words
 * are accepted too.
 *
 * Identity: the category by its `name`, because customfield_category has no idnumber, among
 * the categories with component `core_course`, area `course` and itemid 0 only. The course
 * handler also returns enabled shared categories (handler.php:548), and a shared category of
 * the same name must never be adopted. A field by its `shortname`, looked up across the course
 * fields and the shared ones, as core's api::is_shortname_unique() does (api.php:499), because
 * save_field_configuration() does no uniqueness check and a second apply would otherwise create
 * a duplicate. Both are compared case-sensitively in PHP. Reading customfield_category and
 * customfield_field is a read of stable core tables; writing goes only through
 * course_handler::create_category(), move_field() and save_field_configuration()
 * (customfield/classes/handler.php, MOODLE_502_STABLE), so core's events fire and its caches
 * are cleared (constitution XI).
 *
 * What it compares on a field: name, category, and the course handler's `locked` and
 * `visibility`. A field of another type is a blocking `wrong-datatype`: changing the type
 * would discard every course's value. On create, every text-field configdata key is written,
 * because core's text data controller reads some unguarded (data_controller.php:43-46); on
 * update, the declared keys are merged into the live configdata, so keys this repo does not
 * manage are kept. The description is never compared or changed.
 *
 * A live `ltct_` course field that is not declared is `extra` (extras()) and is kept: courses
 * hold values in it. Nothing is ever deleted.
 *
 * COURSE VALUES ARE NEVER TOUCHED. Nothing here reads or writes customfield_data; the values
 * are the publisher's (contracts/publish.md). Subjects are `course field category <name>` and
 * `course field <shortname>` only.
 */
class coursefields {

    /** Item types. */
    const TYPE_CATEGORY = 'coursefieldcategory';
    const TYPE_FIELD = 'coursefield';

    /** The handler's component, area and itemid (course_handler; handler.php:99). */
    const COMPONENT = 'core_course';
    const AREA = 'course';

    /** Shared custom fields, which share the shortname space with course fields. */
    const SHARED_COMPONENT = 'core_customfield';
    const SHARED_AREA = 'shared';

    /** The shortname prefix of every course field this site owns. */
    const OWNED_PREFIX = 'ltct_';

    /** The field types the declaration contract allows. */
    const TYPES = ['text'];

    /** Declared visibility words to course_handler's VISIBLE* values (course_handler.php:38-42). */
    const VISIBILITY = [
        'nobody' => 0,
        'teachers' => 1,
        'everyone' => 2,
    ];

    /**
     * Every configdata key core's generator writes for a text field (customfield/tests/generator
     * /lib.php:128-144), with the text form's defaults for the sizes (text field_controller.php
     * :59, :66). `locked` and `visibility` are replaced by the declaration.
     */
    const TEXT_CONFIGDATA = [
        'required' => 0,
        'uniquevalues' => 0,
        'locked' => 0,
        'visibility' => 2,
        'defaultvalue' => '',
        'defaultvalueformat' => 0, // FORMAT_MOODLE
        'displaysize' => 50,
        'maxlength' => 1333,
        'ispassword' => 0,
        'link' => '',
        'linktarget' => '',
    ];

    /** @var string the declared category name */
    protected $category;

    /** @var array[] the declared fields */
    protected $fields;

    /**
     * @param string $category the payload's `course_field_category`
     * @param array $fields the payload's `course_fields` array
     */
    public function __construct(string $category, array $fields) {
        $this->category = $category;
        $this->fields = array_values(array_map(function($field) {
            return (array)$field;
        }, $fields));
    }

    /**
     * @param string $name
     * @return string the subject of the category's item
     */
    public static function category_subject(string $name): string {
        return "course field category {$name}";
    }

    /**
     * @param string $shortname
     * @return string the subject of a field's item
     */
    public static function field_subject(string $shortname): string {
        return "course field {$shortname}";
    }

    // --- checking (read only) ------------------------------------------------------------

    /**
     * Every item result, in apply order: the category, then each field. WRITES NOTHING.
     *
     * @return array[] item results in inspector's shape
     */
    public function check(): array {
        $items = [$this->check_category()];
        foreach ($this->fields as $field) {
            $items[] = $this->check_field($field);
        }
        return $items;
    }

    /**
     * One `extra` item per live `ltct_` course field the declaration does not name. WRITES
     * NOTHING. For drift's undeclared-items pass; apply keeps them and does not report them,
     * as spec 002's profile fields.
     *
     * @return array[] item results in inspector's shape
     */
    public function extras(): array {
        $declared = [];
        foreach ($this->fields as $field) {
            $declared[(string)($field['shortname'] ?? '')] = true;
        }
        $items = [];
        foreach ($this->owned_fields() as $record) {
            if (!isset($declared[(string)$record->shortname])) {
                $items[] = self::result(self::TYPE_FIELD, self::field_subject((string)$record->shortname), 'extra',
                    null, (string)$record->name, 'no longer declared in course-fields.yaml; kept, never deleted');
            }
        }
        return $items;
    }

    /**
     * Is the declared category present, once?
     *
     * @return array item result; ambiguous blocks apply
     */
    public function check_category(): array {
        $name = $this->category;
        $subject = self::category_subject($name);
        if (trim($name) === '') {
            return self::result(self::TYPE_CATEGORY, $subject, 'unknown', null, null,
                'course-fields.yaml declares no category', true);
        }
        $live = $this->find_categories($name);
        if (!$live) {
            return self::result(self::TYPE_CATEGORY, $subject, 'missing', $name, null, 'apply will create it');
        }
        if (count($live) > 1) {
            return self::result(self::TYPE_CATEGORY, $subject, 'ambiguous', $name, null,
                'more than one course custom field category has this name: ids '
                . implode(', ', array_keys($live)), true);
        }
        return self::result(self::TYPE_CATEGORY, $subject, 'ok', $name, $name);
    }

    /**
     * Compare one declared field with the live one.
     *
     * @param array $field {shortname, name, type, locked, visibility}
     * @return array item result
     */
    public function check_field(array $field): array {
        $shortname = (string)($field['shortname'] ?? '');
        $subject = self::field_subject($shortname);

        $problem = self::invalid($field);
        if ($problem !== null) {
            return self::result(self::TYPE_FIELD, $subject, 'unknown', self::display($field['type'] ?? null),
                null, $problem, true);
        }

        $live = $this->find_fields($shortname);
        if (!$live) {
            return self::result(self::TYPE_FIELD, $subject, 'missing', self::summary($field), null,
                'apply will create it');
        }
        if (count($live) > 1) {
            return self::result(self::TYPE_FIELD, $subject, 'ambiguous', self::summary($field), null,
                'more than one course or shared custom field has this shortname: ids '
                . implode(', ', array_keys($live)), true);
        }
        $record = reset($live);
        if ((string)$record->component !== self::COMPONENT || (string)$record->area !== self::AREA) {
            return self::result(self::TYPE_FIELD, $subject, 'ambiguous', self::summary($field),
                "shared field in '{$record->categoryname}'",
                'a shared custom field already uses this shortname; rename it by hand', true);
        }
        if ((string)$record->type !== (string)$field['type']) {
            return self::result(self::TYPE_FIELD, $subject, 'wrong-datatype', (string)$field['type'],
                (string)$record->type, 'changing the type would discard every course\'s value; change it by hand',
                true);
        }

        $categories = $this->find_categories($this->category);
        $categoryid = count($categories) === 1 ? (int)array_key_first($categories) : null;
        $differences = self::differences($field, $record, $categoryid, $this->category);
        if ($differences) {
            return self::result(self::TYPE_FIELD, $subject, 'changed',
                self::difference_text($differences, 0), self::difference_text($differences, 1),
                'differs: ' . implode(', ', array_keys($differences)));
        }
        return self::result(self::TYPE_FIELD, $subject, 'ok', self::summary($field), self::summary($field));
    }

    // --- applying ------------------------------------------------------------------------

    /**
     * Bring the category and fields to the declaration, writing only what differs.
     *
     * The caller runs the preflight first: apply writes nothing while anything blocks. A
     * blocking item that reaches here is still reported and never written. Undeclared fields
     * are left alone; drift reports them.
     *
     * @param report $report
     * @return void
     */
    public function apply(report $report): void {
        $this->apply_category($report);
        foreach ($this->fields as $field) {
            $this->apply_field($field, $report);
        }
    }

    /**
     * @param report $report
     * @return void
     */
    protected function apply_category(report $report): void {
        $item = $this->check_category();
        if ($item['result'] !== 'missing') {
            $report->add_result($item);
            return;
        }
        self::handler()->create_category($this->category);
        $after = $this->check_category();
        if ($after['result'] === 'ok') {
            $report->add('changed', 'missing', $item['item'], $this->category, null, 'created');
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
        $item = $this->check_field($field);
        if ($item['result'] !== 'missing' && $item['result'] !== 'changed') {
            $report->add_result($item);
            return;
        }

        $categories = $this->find_categories($this->category);
        if (count($categories) !== 1) {
            $report->add_result($item, 'fail', "its category '{$this->category}' is not on the server exactly once");
            return;
        }
        $categoryid = (int)array_key_first($categories);
        $handler = self::handler();

        if ($item['result'] === 'missing') {
            $category = self::category_controller($handler, $categoryid);
            if ($category === null) {
                $report->add_result($item, 'fail', "the course handler does not list category id {$categoryid}");
                return;
            }
            $controller = field_controller::create(0, (object)['type' => (string)$field['type']], $category);
            $handler->save_field_configuration($controller, self::save_data($field, null));
        } else {
            $live = $this->find_fields((string)$field['shortname']);
            $record = reset($live);
            if ((int)$record->categoryid !== $categoryid) {
                $controller = self::field_controller($handler, (int)$record->categoryid, (int)$record->id);
                if ($controller === null) {
                    $report->add_result($item, 'fail', 'the course handler does not list it; is its type enabled?');
                    return;
                }
                $handler->move_field($controller, $categoryid);
            }
            $controller = self::field_controller($handler, $categoryid, (int)$record->id);
            if ($controller === null) {
                $report->add_result($item, 'fail', 'the course handler does not list it; is its type enabled?');
                return;
            }
            $handler->save_field_configuration($controller, self::save_data($field, self::configdata($record)));
        }

        $after = $this->check_field($field);
        if ($after['result'] !== 'ok') {
            $report->add_result($after, 'fail', 'written, but the server still differs');
        } else if ($item['result'] === 'missing') {
            $report->add('changed', 'missing', $item['item'], $item['declared'], null, 'created');
        } else {
            $report->add_result($item, 'changed');
        }
    }

    /**
     * The data save_field_configuration() takes. A new field gets every text configdata key
     * and an empty description; an update sends the name and the live configdata with the
     * declared keys merged over it, and leaves the description alone.
     *
     * @param array $field the declared field
     * @param array|null $liveconfig the live field's decoded configdata, or null to create
     * @return stdClass
     */
    public static function save_data(array $field, ?array $liveconfig): stdClass {
        $data = new stdClass();
        $data->name = (string)$field['name'];
        if ($liveconfig === null) {
            $data->shortname = (string)$field['shortname'];
            $data->description = '';
            $data->descriptionformat = 1; // FORMAT_HTML
            $data->configdata = self::merged_configdata($field, []);
        } else {
            $data->configdata = self::merged_configdata($field, $liveconfig);
        }
        return $data;
    }

    // --- pure comparisons (no database) --------------------------------------------------

    /**
     * Why a declared field cannot be applied, or null when it can. site_config.py validate
     * refuses these first; this is the plugin's own guard.
     *
     * @param array $field
     * @return string|null
     */
    public static function invalid(array $field): ?string {
        $shortname = $field['shortname'] ?? null;
        if (!is_string($shortname) || !preg_match('/^ltct_[a-z0-9_]+$/', $shortname)) {
            return 'shortname must match ^ltct_[a-z0-9_]+$';
        }
        if (strlen($shortname) > 100) {
            return 'shortname is longer than 100 characters';
        }
        if (!is_string($field['name'] ?? null) || trim($field['name']) === '') {
            return 'a course field needs a name';
        }
        if (!in_array($field['type'] ?? null, self::TYPES, true)) {
            return 'type must be ' . implode(' or ', self::TYPES);
        }
        if (!in_array((string)($field['locked'] ?? ''), ['0', '1'], true)) {
            return 'locked must be 0 or 1';
        }
        if (self::visibility_code($field['visibility'] ?? null) === null) {
            return 'visibility must be ' . implode(', ', array_keys(self::VISIBILITY));
        }
        return null;
    }

    /**
     * course_handler's visibility for a declared word or value.
     *
     * @param mixed $visibility
     * @return int|null null when it is neither
     */
    public static function visibility_code($visibility): ?int {
        if (!is_scalar($visibility) || is_bool($visibility)) {
            return null;
        }
        $visibility = (string)$visibility;
        if (isset(self::VISIBILITY[$visibility])) {
            return self::VISIBILITY[$visibility];
        }
        return in_array($visibility, ['0', '1', '2'], true) ? (int)$visibility : null;
    }

    /**
     * The declaration word for a stored visibility, for display.
     *
     * @param mixed $code
     * @return string
     */
    public static function visibility_word($code): string {
        $word = array_search((int)$code, self::VISIBILITY, true);
        return $word === false ? (string)$code : $word;
    }

    /**
     * The configdata to write: the text defaults, then the live keys, then the declared ones.
     *
     * @param array $field
     * @param array $liveconfig
     * @return array
     */
    public static function merged_configdata(array $field, array $liveconfig): array {
        $config = array_merge(self::TEXT_CONFIGDATA, $liveconfig);
        $config['locked'] = (int)$field['locked'];
        $config['visibility'] = self::visibility_code($field['visibility']);
        return $config;
    }

    /**
     * The properties of a live field that differ from the declaration, each as
     * [declared, live] display text. The type is checked before this, not here.
     *
     * @param array $field the declared field
     * @param stdClass $record the live field: customfield_field columns plus `categoryname`
     * @param int|null $categoryid the declared category's id, or null when it is not on the
     *     server exactly once (the category is then reported by its own item)
     * @param string $categoryname the declared category's name
     * @return array<string, string[]> keyed by property name, in a fixed order
     */
    public static function differences(array $field, stdClass $record, ?int $categoryid, string $categoryname): array {
        $diff = [];
        if ((string)$field['name'] !== (string)$record->name) {
            $diff['name'] = [(string)$field['name'], (string)$record->name];
        }
        if ($categoryid === null || $categoryid !== (int)$record->categoryid) {
            $diff['category'] = [$categoryname, (string)$record->categoryname];
        }
        $config = self::configdata($record);
        $locked = (int)($config['locked'] ?? 0);
        if ((int)$field['locked'] !== $locked) {
            $diff['locked'] = [(string)(int)$field['locked'], (string)$locked];
        }
        // A missing visibility reads as everyone (course_handler.php:85).
        $visibility = (int)($config['visibility'] ?? course_handler::VISIBLETOALL);
        if (self::visibility_code($field['visibility']) !== $visibility) {
            $diff['visibility'] = [self::visibility_word(self::visibility_code($field['visibility'])),
                self::visibility_word($visibility)];
        }
        return $diff;
    }

    /**
     * A live field's configdata, decoded. Empty when it holds none or is not JSON.
     *
     * @param stdClass $record
     * @return array
     */
    public static function configdata(stdClass $record): array {
        $config = json_decode((string)($record->configdata ?? ''), true);
        return is_array($config) ? $config : [];
    }

    /**
     * One side of a set of differences as text: `name X; visibility everyone`.
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
        return "{$field['type']}, name {$field['name']}, locked " . (int)$field['locked']
            . ', visibility ' . self::visibility_word(self::visibility_code($field['visibility']));
    }

    // --- reads ---------------------------------------------------------------------------

    /**
     * Course custom field categories (core_course/course/0 only) whose name is exactly this
     * one, keyed by id.
     *
     * @param string $name
     * @return stdClass[]
     */
    protected function find_categories(string $name): array {
        global $DB;
        $records = $DB->get_records('customfield_category',
            ['component' => self::COMPONENT, 'area' => self::AREA, 'itemid' => 0, 'name' => $name],
            'id ASC', 'id, name');
        return array_filter($records, function($record) use ($name) {
            return (string)$record->name === $name;
        });
    }

    /**
     * Course and shared custom fields with exactly this shortname, keyed by id, with their
     * category's name, component and area.
     *
     * @param string $shortname
     * @return stdClass[]
     */
    protected function find_fields(string $shortname): array {
        global $DB;
        $sql = "SELECT f.id, f.shortname, f.name, f.type, f.categoryid, f.configdata,
                       c.name AS categoryname, c.component, c.area
                  FROM {customfield_field} f
                  JOIN {customfield_category} c ON c.id = f.categoryid
                 WHERE f.shortname = :shortname
                   AND c.itemid = 0
                   AND ((c.component = :component AND c.area = :area)
                        OR (c.component = :sharedcomponent AND c.area = :sharedarea))
              ORDER BY f.id ASC";
        $records = $DB->get_records_sql($sql, [
            'shortname' => $shortname,
            'component' => self::COMPONENT,
            'area' => self::AREA,
            'sharedcomponent' => self::SHARED_COMPONENT,
            'sharedarea' => self::SHARED_AREA,
        ]);
        return array_filter($records, function($record) use ($shortname) {
            return (string)$record->shortname === $shortname;
        });
    }

    /**
     * Every course field (core_course/course/0) whose shortname starts with `ltct_`, matched
     * case-sensitively, keyed by id. The prefix is checked again in PHP, as
     * drift::owned_records() does, because a collation may still fold case.
     *
     * @return stdClass[]
     */
    protected function owned_fields(): array {
        global $DB;
        $like = $DB->sql_like('f.shortname', ':prefix', true, true);
        $sql = "SELECT f.id, f.shortname, f.name
                  FROM {customfield_field} f
                  JOIN {customfield_category} c ON c.id = f.categoryid
                 WHERE c.component = :component AND c.area = :area AND c.itemid = 0
                   AND {$like}
              ORDER BY f.shortname ASC, f.id ASC";
        $records = $DB->get_records_sql($sql, [
            'component' => self::COMPONENT,
            'area' => self::AREA,
            'prefix' => $DB->sql_like_escape(self::OWNED_PREFIX) . '%',
        ]);
        return array_filter($records, function($record) {
            return strpos((string)$record->shortname, self::OWNED_PREFIX) === 0;
        });
    }

    /**
     * @return course_handler the course handler, one cached instance per request
     */
    protected static function handler(): course_handler {
        return course_handler::create();
    }

    /**
     * The handler's own controller for a course category, or null. Shared categories are
     * never returned.
     *
     * @param course_handler $handler
     * @param int $categoryid
     * @return category_controller|null
     */
    protected static function category_controller(course_handler $handler, int $categoryid): ?category_controller {
        foreach ($handler->get_categories_with_fields() as $category) {
            if ((int)$category->get('id') === $categoryid && $category->get('component') === self::COMPONENT
                    && $category->get('area') === self::AREA) {
                return $category;
            }
        }
        return null;
    }

    /**
     * The handler's own controller for a field, or null when the handler does not list it
     * (its category is not the course handler's, or its type plugin is disabled).
     *
     * @param course_handler $handler
     * @param int $categoryid
     * @param int $fieldid
     * @return field_controller|null
     */
    protected static function field_controller(course_handler $handler, int $categoryid, int $fieldid): ?field_controller {
        $category = self::category_controller($handler, $categoryid);
        if ($category === null) {
            return null;
        }
        return $category->get_fields()[$fieldid] ?? null;
    }

    // --- results -------------------------------------------------------------------------

    /**
     * Build one item result, in inspector's shape.
     *
     * @param string $type coursefieldcategory or coursefield
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
