<?php

defined('MOODLE_INTERNAL') || die;

function xmldb_connectproxymodule_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2014081503) {
        // Rename field summary on table book to intro
        $table = new xmldb_table('connectproxymodule');
        $field = new xmldb_field('course', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');

        // Conditionally launch add field revision
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // book savepoint reached
        upgrade_mod_savepoint(true, 2014081503, 'connectproxymodule');
    }

	return true;
}
