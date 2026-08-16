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

namespace format_aicourse\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service recreating the plugin's tables after a half-applied upgrade.
 *
 * Replaces the 'dbrepair' action of the plugin's deprecated ajax.php endpoint. It is a last
 * resort support tool, restricted to site administrators (moodle/site:config in the system
 * context) and deliberately not exposed to any browser JavaScript: db/services.php registers it
 * with 'ajax' => false. The supported way to fix the schema remains
 * admin/cli/upgrade.php; this function exists only because sites in the field have run the
 * plugin with a partially applied db/upgrade.php step.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class db_repair extends external_api {
    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Create any missing table or column of the plugin's own schema.
     *
     * @return array The repairs performed and a translated summary.
     */
    public static function execute(): array {
        global $DB;

        self::validate_parameters(self::execute_parameters(), []);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $dbman = $DB->get_manager();
        $repairs = [];

        if (!$dbman->table_exists('format_aicourse_chats')) {
            $table = new \xmldb_table('format_aicourse_chats');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('questionslot', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('question', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('response', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('rating', XMLDB_TYPE_INTEGER, '2', null, null, null, '0');
            $table->add_field('refused', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
            $table->add_field('locked', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
            $table->add_field('correction', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('correctedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecorrected', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
            $repairs[] = 'Created chats table';
        } else {
            $table = new \xmldb_table('format_aicourse_chats');
            $fields = [
                'activityid' => new \xmldb_field(
                    'activityid',
                    XMLDB_TYPE_INTEGER,
                    '10',
                    null,
                    null,
                    null,
                    '0',
                    'userid'
                ),
                'questionslot' => new \xmldb_field(
                    'questionslot',
                    XMLDB_TYPE_INTEGER,
                    '10',
                    null,
                    null,
                    null,
                    null,
                    'activityid'
                ),
                'refused' => new \xmldb_field(
                    'refused',
                    XMLDB_TYPE_INTEGER,
                    '1',
                    null,
                    null,
                    null,
                    '0',
                    'rating'
                ),
                'locked' => new \xmldb_field(
                    'locked',
                    XMLDB_TYPE_INTEGER,
                    '1',
                    null,
                    null,
                    null,
                    '0',
                    'refused'
                ),
            ];
            foreach ($fields as $name => $field) {
                if (!$dbman->field_exists($table, $field)) {
                    $dbman->add_field($table, $field);
                    $repairs[] = 'Added ' . $name . ' column';
                }
            }
        }

        if (!$dbman->table_exists('format_aicourse_ai_memory')) {
            $table = new \xmldb_table('format_aicourse_ai_memory');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('memory', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timeupdated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('unique_memory', XMLDB_INDEX_UNIQUE, ['courseid', 'activityid', 'userid']);
            $dbman->create_table($table);
            $repairs[] = 'Created memory table';
        }

        // The $repairs entries are untranslated technical identifiers describing what changed;
        // the user visible summary comes from the language pack.
        $message = empty($repairs)
            ? get_string('dbrepair_norepairs', 'format_aicourse')
            : get_string('dbrepair_completed', 'format_aicourse', implode(', ', $repairs));

        return [
            'repairs' => $repairs,
            'message' => $message,
        ];
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'repairs' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Description of one repair performed'),
                'Every repair performed, empty when the schema was already correct'
            ),
            'message' => new external_value(PARAM_TEXT, 'Translated summary of the repair run'),
        ]);
    }
}
