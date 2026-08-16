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
 * Web service recording a thumbs up or thumbs down on an AI Tutor answer.
 *
 * Replaces the 'ratechat' action of the plugin's deprecated ajax.php endpoint.
 *
 * SECURITY: the chat row lookup is scoped to both the course AND the calling user, so a learner
 * can only ever rate their own conversation. That scoping is load bearing and is preserved here.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_chat extends external_api {
    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Id of the course the chat belongs to'),
            'chatid' => new external_value(PARAM_INT, 'Id of the chat row to rate'),
            'rating' => new external_value(PARAM_INT, 'Rating: 1 for helpful, -1 for not helpful'),
        ]);
    }

    /**
     * Rate one of the calling user's own AI Tutor answers.
     *
     * @param int $courseid Id of the course.
     * @param int $chatid Id of the chat row.
     * @param int $rating 1 or -1.
     * @return array Status report.
     */
    public static function execute(int $courseid, int $chatid, int $rating): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'chatid' => $chatid,
            'rating' => $rating,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('format/aicourse:useaitutor', $context);

        if (!in_array($params['rating'], [-1, 1], true)) {
            throw new \moodle_exception('error_invalidrating', 'format_aicourse');
        }

        // Only the owner of the conversation may rate it.
        $chat = $DB->get_record('format_aicourse_chats', [
            'id' => $params['chatid'],
            'courseid' => $course->id,
            'userid' => $USER->id,
        ]);
        if (!$chat) {
            throw new \moodle_exception('error_chatnotfound', 'format_aicourse');
        }

        $DB->set_field('format_aicourse_chats', 'rating', $params['rating'], ['id' => $chat->id]);

        return ['status' => true];
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'True when the rating was stored'),
        ]);
    }
}
