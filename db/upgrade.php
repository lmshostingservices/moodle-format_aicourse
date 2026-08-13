<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_format_aicourse_upgrade($oldversion) {
    if ($oldversion < 2026072300) {
        upgrade_plugin_savepoint(true, 2026072300, 'format', 'aicourse');
    }
    return true;
}
