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

    // Mentor relationship (spec 003, research R5): the message contacts this plugin makes
    // between a mentor and their learner, so ending the relationship removes only those.
    if ($oldversion < 2026100301) {
        $table = new xmldb_table('local_ltuse_mentor_contact');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('mentorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('learnerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('contactid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('mentorid', XMLDB_KEY_FOREIGN, ['mentorid'], 'user', ['id']);
        $table->add_key('learnerid', XMLDB_KEY_FOREIGN, ['learnerid'], 'user', ['id']);
        $table->add_index('mentorlearner', XMLDB_INDEX_UNIQUE, ['mentorid', 'learnerid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026100301, 'local', 'ltuse');
    }

    // Events and office hours (spec 011, research R20): each office-hours booking's last
    // notified time, because core's calendar_event_updated carries no old time.
    if ($oldversion < 2026100400) {
        $table = new xmldb_table('local_ltuse_booking');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('slotid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('learnerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('mentorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timestart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeduration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('learnerid', XMLDB_KEY_FOREIGN, ['learnerid'], 'user', ['id']);
        $table->add_index('eventid', XMLDB_INDEX_UNIQUE, ['eventid']);
        $table->add_index('slotid', XMLDB_INDEX_NOTUNIQUE, ['slotid']);
        $table->add_index('mentorid', XMLDB_INDEX_NOTUNIQUE, ['mentorid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026100400, 'local', 'ltuse');
    }

    // Identity protection (spec 016, research R5, R12): a protected user's real identity, each
    // organisation's minimum level, and the change log. All three are Moodle data, never the
    // repo's. Then ltct_certname, the certificate's name field, is filled for every existing
    // user (R10), so no learner's certificate prints the field's label instead of a name.
    if ($oldversion < 2026100500) {
        $table = new xmldb_table('local_ltuse_protection');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ownlevel', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none');
        $table->add_field('effectivelevel', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none');
        $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'own');
        $table->add_field('pseudonym', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('realfirstname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('reallastname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('realfields', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN_UNIQUE, ['userid'], 'user', ['id']);
        $table->add_index('usermodified', XMLDB_INDEX_NOTUNIQUE, ['usermodified']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_ltuse_org_protection');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('orgkey', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('minlevel', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none');
        $table->add_field('managers_see_identity', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('orgkey', XMLDB_INDEX_UNIQUE, ['orgkey']);
        $table->add_index('usermodified', XMLDB_INDEX_NOTUNIQUE, ['usermodified']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_ltuse_protection_log');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('actorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('fromlevel', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none');
        $table->add_field('tolevel', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none');
        $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'own');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('actorid', XMLDB_INDEX_NOTUNIQUE, ['actorid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // The field itself is made by site_config.py apply (profile-fields.yaml). Where it does
        // not exist yet this fills nothing, and the reconcile task fills it once it does.
        \local_ltuse\protection\service::backfill_certnames();

        upgrade_plugin_savepoint(true, 2026100500, 'local', 'ltuse');
    }

    return true;
}
