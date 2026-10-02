<?php
// Upgrade steps for local_ltuse.
//
// A fresh install builds its tables from db/install.xml and never runs this file; an
// upgrade runs only this file. So every table here must match install.xml field for field
// -- the XMLDB editor's "Get PHP code" generates these blocks from it.

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade local_ltuse from $oldversion.
 *
 * @param int $oldversion the version installed before this upgrade
 * @return bool
 */
function xmldb_local_ltuse_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Progress reporting (spec 004): the competency framework and the course-to-competency
    // map that the per-competency report reads. Neither holds user data; both stay empty
    // until the site declaration and the publisher fill them.
    if ($oldversion < 2026100204) {
        $table = new xmldb_table('local_ltuse_competency');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('category', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('retired', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('name', XMLDB_INDEX_UNIQUE, ['name']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_ltuse_course_comp');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('competencyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('competencyid', XMLDB_KEY_FOREIGN, ['competencyid'], 'local_ltuse_competency', ['id']);
        $table->add_index('courseid-competencyid', XMLDB_INDEX_UNIQUE, ['courseid', 'competencyid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026100204, 'local', 'ltuse');
    }

    // Completion badges and certificates (spec 013): the map from a course to its badge. A
    // badge has no idnumber, and a restore or a course copy duplicates names, so this table
    // is the badge's identity (research R1). It holds no user data.
    if ($oldversion < 2026100300) {
        $table = new xmldb_table('local_ltuse_course_badge');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('badgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('imagehash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN_UNIQUE, ['courseid'], 'course', ['id']);
        $table->add_key('badgeid', XMLDB_KEY_FOREIGN_UNIQUE, ['badgeid'], 'badge', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026100300, 'local', 'ltuse');
    }

    return true;
}
