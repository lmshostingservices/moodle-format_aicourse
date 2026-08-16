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
 * Web service appending a new section to the end of an AI Course Format course.
 *
 * Replaces the 'addsection' action of the plugin's deprecated ajax.php endpoint.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_section extends external_api {
    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Id of the course to add a section to'),
        ]);
    }

    /**
     * Append one section at the end of the course.
     *
     * @param int $courseid Id of the course.
     * @return array The new section id.
     */
    public static function execute(int $courseid): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        require_once($CFG->dirroot . '/course/lib.php');

        // In Moodle 4.x, course_create_section($courseid, $position, ...) inserts the new section
        // AT the given position number. Passing position 0 tries to insert at slot 0, which is
        // always occupied by the permanent "General" section, causing a unique key violation. To
        // append at the end we must pass MAX(section) + 1.
        try {
            $maxsection = (int) $DB->get_field_sql(
                'SELECT MAX(section) FROM {course_sections} WHERE course = ?',
                [$course->id]
            );
            $newsection = course_create_section($course->id, $maxsection + 1, true);
            rebuild_course_cache($course->id, true);
        } catch (\Exception $e) {
            // ACF-FIX-2.0: DML exception text embeds SQL and table names. Log it, return a code.
            debugging('format_aicourse add_section failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('error_addsectionfailed', 'format_aicourse');
        }

        return ['sectionid' => (int) $newsection->id];
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sectionid' => new external_value(PARAM_INT, 'Id of the newly created section'),
        ]);
    }
}
