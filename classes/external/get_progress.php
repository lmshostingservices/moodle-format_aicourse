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
use format_aicourse\local\progress;

/**
 * Web service returning the calling user's completion progress for a course.
 *
 * Replaces the 'getprogress' action of the plugin's deprecated ajax.php endpoint.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_progress extends external_api {
    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Id of the course to report progress for'),
        ]);
    }

    /**
     * Return the calling user's progress through the course.
     *
     * The figures are always the calling user's own; no user id is accepted from the client.
     *
     * @param int $courseid Id of the course.
     * @return array Progress figures.
     */
    public static function execute(int $courseid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('format/aicourse:view', $context);

        $progressdata = progress::get_progress($course, $USER->id);

        return [
            'percentage' => (int) $progressdata['percentage'],
            'completed' => (int) $progressdata['completed'],
            'total' => (int) $progressdata['total'],
            'enabled' => (bool) $progressdata['enabled'],
        ];
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'percentage' => new external_value(PARAM_INT, 'Percentage of the course completed, 0-100'),
            'completed' => new external_value(PARAM_INT, 'Number of completed activities'),
            'total' => new external_value(PARAM_INT, 'Number of activities that count towards completion'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether completion tracking is enabled for this course'),
        ]);
    }
}
