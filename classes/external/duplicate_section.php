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
 * Web service duplicating a section, including every activity inside it.
 *
 * Replaces the 'duplicatesection' action of the plugin's deprecated ajax.php endpoint.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class duplicate_section extends external_api {
    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Id of the course the section belongs to'),
            'sectionid' => new external_value(PARAM_INT, 'Id (not number) of the section to duplicate'),
        ]);
    }

    /**
     * Duplicate one section of the course.
     *
     * @param int $courseid Id of the course.
     * @param int $sectionid Id of the section to duplicate.
     * @return array The new section id.
     */
    public static function execute(int $courseid, int $sectionid): array {
        global $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sectionid' => $sectionid,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        require_once($CFG->dirroot . '/course/lib.php');

        // The lookup runs against this course's modinfo, so a section id belonging to another
        // course simply does not resolve.
        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info_by_id($params['sectionid']);
        if (!$section) {
            throw new \moodle_exception('error_sectionnotfound', 'format_aicourse');
        }

        if ((int) $section->section === 0) {
            throw new \moodle_exception('error_cannotduplicategeneral', 'format_aicourse');
        }

        // ACF-FIX-2.0: core_courseformat\base::duplicate_section() is the real API. It shuffles
        // the following sections, copies the name, summary, visibility, availability and format
        // options, copies the section files and duplicates every module in the section.
        try {
            $newsection = course_get_format($course)->duplicate_section($section);
        } catch (\Exception $e) {
            debugging('format_aicourse duplicate_section failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('error_duplicatesectionfailed', 'format_aicourse');
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
