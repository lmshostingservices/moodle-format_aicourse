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
 * Report the state of a queued banner generation.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Report the state of a queued banner generation.
 *
 * ACF-FIX-2.1.42. Companion to generate_banner_image, which now queues the work instead of doing
 * it inline. This is what the page polls while the adhoc task runs. It is deliberately trivial:
 * one config read and, when the task reports success, a lookup of the stored file. Nothing here
 * calls the remote service or spends credits, so polling it costs nothing but a database read.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_banner_status extends external_api {
    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * Return the current state of this course's banner generation.
     *
     * @param int $courseid Course to report on.
     * @return array{status: string, imageurl: string, message: string}
     */
    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        // Same capability as starting a generation: this reports on an editing action and can
        // surface the service's failure text, which is not for learners.
        require_capability('moodle/course:update', $context);

        $status = generate_banner_image::get_status((int) $course->id);

        $imageurl = '';
        if ($status['state'] === 'done') {
            // Prefer the URL the task recorded; fall back to whatever is in the file area, so a
            // banner that landed while the status write failed is still reported as present.
            $imageurl = $status['detail'];
            if ($imageurl === '') {
                $imageurl = (string) \format_aicourse\local\banner::get_banner_image_url($course->id);
            }
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
            // ACF-FIX-2.1.115: declared as a URL -- see generate_banner_image for why.
            'imageurl' => new external_value(PARAM_URL, 'The stored image URL once done'),
            'message' => new external_value(PARAM_TEXT, 'Failure reason when status is failed'),
        ]);
    }
}
