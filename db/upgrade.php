<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Database upgrade steps for the AI Course Format.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the AI Course Format database schema.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Always true; failures are raised as exceptions by the DDL layer.
 */
function xmldb_format_aicourse_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();
    $installxml = $CFG->dirroot . '/course/format/aicourse/db/install.xml';

    // Savepoint retained from the 1.7.x series so that sites upgrading from any 1.x release
    // pass through a known good point before the 2.0.0 schema changes below.
    if ($oldversion < 2026072300) {
        upgrade_plugin_savepoint(true, 2026072300, 'format', 'aicourse');
    }

    if ($oldversion < 2026081500) {
        // Some early releases of this plugin created their tables from ajax.php at runtime
        // rather than from install.xml, so a small number of sites can reach this point with
        // one or both tables missing. Recreate anything that is absent before touching keys
        // and indexes, otherwise the steps below would fail on those sites.
        foreach (['format_aicourse_chats', 'format_aicourse_ai_memory'] as $tablename) {
            if (!$dbman->table_exists(new xmldb_table($tablename))) {
                $dbman->install_one_table_from_xmldb_file($installxml, $tablename);
            }
        }

        $table = new xmldb_table('format_aicourse_chats');

        // The privacy provider looks up chat rows by the teacher who wrote a correction, in
        // order to remove that attribution when the teacher's data is deleted. Without an
        // index that is a full table scan on every data request.
        $key = new xmldb_key('correctedby', XMLDB_KEY_FOREIGN, ['correctedby'], 'user', ['id']);
        $dbman->add_key($table, $key);

        // The course report always filters on courseid and then either orders by timecreated
        // or filters by userid; the privacy export and delete do the same.
        $index = new xmldb_index('courseid_userid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'userid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('courseid_timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'timecreated']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // AI Course Format savepoint reached.
        upgrade_plugin_savepoint(true, 2026081500, 'format', 'aicourse');
    }

    return true;
}
