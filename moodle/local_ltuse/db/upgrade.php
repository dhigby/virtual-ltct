<?php
// Upgrade steps for local_ltuse.

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade local_ltuse.
 *
 * @param int $oldversion the version being upgraded from
 * @return bool
 */
function xmldb_local_ltuse_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026100204) {
        // Spec 003 (research R5): the message contacts this plugin makes between a mentor and
        // their learner. Same definition as db/install.xml.
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
        upgrade_plugin_savepoint(true, 2026100204, 'local', 'ltuse');
    }

    return true;
}
