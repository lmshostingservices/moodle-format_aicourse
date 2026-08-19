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
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service writing a teacher correction onto an AI Tutor answer.
 *
 * Replaces the 'correctchat' action of the plugin's deprecated ajax.php endpoint.
 *
 * SECURITY — DO NOT WEAKEN THIS CAPABILITY CHECK. The original action was gated on
 * moodle/course:viewparticipants, which is CAP_ALLOW for the student archetype in
 * /lib/db/access.php, so every enrolled student could forge a teacher correction on any chat row
 * in the course. format/aicourse:viewreport is granted to teacher, editingteacher and manager
 * only (see the plugin's db/access.php) and is the correct gate. The record lookup stays scoped
 * by courseid so a chat row from another course cannot be reached.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class correct_chat extends external_api {
    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Id of the course the chat belongs to'),
            'chatid' => new external_value(PARAM_INT, 'Id of the chat row to correct'),
            'correction' => new external_value(PARAM_TEXT, 'The teacher\'s correction'),
        ]);
    }

    /**
     * Store a teacher correction against one chat row.
     *
     * @param int $courseid Id of the course.
     * @param int $chatid Id of the chat row.
     * @param string $correction The correction text.
     * @return array Status report.
     */
    public static function execute(int $courseid, int $chatid, string $correction): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'chatid' => $chatid,
            'correction' => $correction,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);

        // ACF-FIX-2.0: NOT moodle/course:viewparticipants — students hold that capability.
        // Reading the report is the precondition for writing onto a row in it.
        require_capability('format/aicourse:viewreport', $context);

        // ACF-FIX-2.1.4: format/aicourse:correctresponses is declared in db/access.php with
        // RISK_XSS | RISK_PERSONAL precisely because it lets one user write free text onto
        // another user's record. It was declared but never enforced anywhere, which made it an
        // orphan. Enforce it here, which is the only place a correction is written. Its default
        // archetypes (teacher, editingteacher, manager) are identical to those of viewreport, so
        // no existing site loses access on upgrade -- but a site that wants to let a role read
        // the report without editing it can now revoke this one capability alone.
        require_capability('format/aicourse:correctresponses', $context);

        $chat = $DB->get_record('format_aicourse_chats', [
            'id' => $params['chatid'],
            'courseid' => $course->id,
        ]);
        if (!$chat) {
            throw new \moodle_exception('error_chatnotfound', 'format_aicourse');
        }

        $update = new \stdClass();
        $update->id = $chat->id;
        $update->correction = $params['correction'];
        $update->correctedby = $USER->id;
        $update->timecorrected = time();
        $DB->update_record('format_aicourse_chats', $update);

        return ['status' => true];
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'True when the correction was stored'),
        ]);
    }
}
