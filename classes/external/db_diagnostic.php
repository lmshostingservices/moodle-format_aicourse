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
 * Web service reporting whether the plugin's own database tables are present and complete.
 *
 * Replaces the 'dbdiag' action of the plugin's deprecated ajax.php endpoint. It is a support tool
 * for course administrators diagnosing a half-applied upgrade; it reads schema metadata only and
 * never touches user data.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class db_diagnostic extends external_api {
    /** @var array Columns the chat log table must have. */
    protected const REQUIRED_CHAT_COLUMNS = ['id', 'courseid', 'userid', 'activityid', 'questionslot',
        'question', 'response', 'rating', 'refused', 'locked', 'timecreated'];

    /** @var array Columns the tutor memory table must have. */
    protected const REQUIRED_MEMORY_COLUMNS = ['id', 'courseid', 'activityid', 'userid', 'memory',
        'timeupdated'];

    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Id of the course the diagnostic is run from'),
        ]);
    }

    /**
     * Report the state of the plugin's tables.
     *
     * @param int $courseid Id of the course the diagnostic is run from.
     * @return array The diagnostic report.
     */
    public static function execute(int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        $dbman = $DB->get_manager();
        $errors = [];

        $chatsexist = $dbman->table_exists('format_aicourse_chats');
        $chatcolumns = [];
        if ($chatsexist) {
            try {
                $chatcolumns = array_keys($DB->get_columns('format_aicourse_chats'));
            } catch (\Exception $e) {
                debugging('format_aicourse db_diagnostic chats: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $errors[] = 'chats_columns';
            }
        }

        $memoryexist = $dbman->table_exists('format_aicourse_ai_memory');
        $memorycolumns = [];
        if ($memoryexist) {
            try {
                $memorycolumns = array_keys($DB->get_columns('format_aicourse_ai_memory'));
            } catch (\Exception $e) {
                debugging('format_aicourse db_diagnostic memory: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $errors[] = 'memory_columns';
            }
        }

        return [
            'chatstableexists' => $chatsexist,
            'memorytableexists' => $memoryexist,
            'chatscolumns' => array_values($chatcolumns),
            'memorycolumns' => array_values($memorycolumns),
            'missingchatcolumns' => array_values(array_diff(self::REQUIRED_CHAT_COLUMNS, $chatcolumns)),
            'missingmemorycolumns' => array_values(array_diff(self::REQUIRED_MEMORY_COLUMNS, $memorycolumns)),
            'pluginversion' => (string) get_config('format_aicourse', 'version'),
            'errors' => $errors,
        ];
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'chatstableexists' => new external_value(PARAM_BOOL, 'Whether the chat log table exists'),
            'memorytableexists' => new external_value(PARAM_BOOL, 'Whether the tutor memory table exists'),
            'chatscolumns' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Column name'),
                'Columns of the chat log table'
            ),
            'memorycolumns' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Column name'),
                'Columns of the tutor memory table'
            ),
            'missingchatcolumns' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Column name'),
                'Required chat log columns not present'
            ),
            'missingmemorycolumns' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Column name'),
                'Required memory columns not present'
            ),
            'pluginversion' => new external_value(PARAM_ALPHANUMEXT, 'Installed version of the plugin'),
            'errors' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Stable error code'),
                'Codes for metadata lookups that failed'
            ),
        ]);
    }
}
