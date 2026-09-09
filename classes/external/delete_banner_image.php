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
use format_aicourse\local\banner;

/**
 * Web service removing the AI generated banner image from a course.
 *
 * Replaces the 'delete_banner_image' action of the plugin's deprecated ajax.php endpoint.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_banner_image extends external_api {
    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Id of the course to remove the banner from'),
            // 2.2.0: 0, the default, means the course banner -- so an older cached bundle that
            // does not send this keeps removing course banners, which is what it intends.
            'sectionid' => new external_value(
                PARAM_INT,
                'course_sections.id to remove a section banner from, or 0 for the course banner',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Delete every file in the target's banner image file area.
     *
     * @param int $courseid Id of the course.
     * @param int $sectionid course_sections.id for a section banner, 0 for the course banner.
     * @return array Status report.
     */
    public static function execute(int $courseid, int $sectionid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sectionid' => $sectionid,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        // Without this a teacher of this course could pass another course's section id and delete
        // its banner: the capability check above would still pass, because it is checked against
        // THIS course's context.
        $sectioninfo = banner::require_section_in_course($course, (int) $params['sectionid']);

        // Scoped by item id, so removing a section's banner cannot take the course banner with
        // it -- the course banner is what that section then falls back to.
        [$filearea, $itemid] = banner::target($sectioninfo ? (int) $sectioninfo->id : 0);

        get_file_storage()->delete_area_files(
            $context->id,
            'format_aicourse',
            $filearea,
            $itemid
        );

        // 2.2.0: report what is on screen NOW. Removing a section's banner does not leave the
        // section with no image -- it leaves it inheriting the course banner, which is the whole
        // point of the fallback. The browser used to respond to a successful delete by hiding
        // the hero image outright; doing that here would blank a banner that is still there, and
        // the teacher would only discover it was fine after reloading.
        $resolved = banner::resolve($course, $sectioninfo ? (int) $sectioninfo->id : null);

        return [
            'status' => true,
            'imageurl' => (string) ($resolved['url'] ?? ''),
        ];
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'True when the banner was removed'),
            // Defaulted so a browser running the previous bundle, which does not read this key,
            // is unaffected by its arrival.
            'imageurl' => new external_value(
                PARAM_URL,
                'The banner that applies after the removal, empty when there is none',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }
}
