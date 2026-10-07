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
use format_aicourse\local\cardimage;

/**
 * Web service reporting the state of a queued card image generation.
 *
 * Polled by the browser while the adhoc task runs. Reads one config value; no remote call and no
 * credits.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_card_image_status extends external_api {
    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'targettype' => new external_value(PARAM_ALPHA, 'section or cm'),
            'targetid' => new external_value(PARAM_INT, 'course_sections.id or course_modules.id'),
        ]);
    }

    /**
     * Report the state.
     *
     * @param int $courseid Course id.
     * @param string $targettype section or cm.
     * @param int $targetid course_sections.id or course_modules.id.
     * @return array
     */
    public static function execute(int $courseid, string $targettype, int $targetid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'targettype' => $targettype,
            'targetid' => $targetid,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        // The failure text can quote the image service, which is not for learners.
        require_capability('moodle/course:update', $context);

        $target = cardimage::require_target($course, $params['targettype'], (int) $params['targetid']);
        $status = cardimage::get_status((int) $course->id, $params['targettype'], (int) $target->id);

        $imageurl = '';
        if ($status['state'] === 'done') {
            $imageurl = $status['detail'] !== '' ? $status['detail']
                : (string) cardimage::get_url((int) $course->id, $params['targettype'], (int) $target->id);
        }

        return [
            'status' => $status['state'],
            'imageurl' => $imageurl,
            'message' => $status['state'] === 'failed' ? $status['detail'] : '',
        ];
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'idle, queued, running, done or failed'),
            'imageurl' => new external_value(PARAM_URL, 'The image URL once done', VALUE_DEFAULT, ''),
            'message' => new external_value(PARAM_TEXT, 'Failure reason when failed', VALUE_DEFAULT, ''),
        ]);
    }
}
