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
 * Web service setting or clearing the colour of a section or activity card.
 *
 * 2.5.0. The colour fills the card's image area when it has no picture. Cleared, the card falls
 * back to the course accent colour.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_card_colour extends external_api {
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
            // PARAM_TEXT keeps the '#'; cardimage::clean_colour() then accepts only a hex colour.
            'colour' => new external_value(PARAM_TEXT, '#rrggbb, or empty to clear', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Store the colour.
     *
     * @param int $courseid Course id.
     * @param string $targettype section or cm.
     * @param int $targetid course_sections.id or course_modules.id.
     * @param string $colour Hex colour or ''.
     * @return array
     */
    public static function execute(int $courseid, string $targettype, int $targetid, string $colour = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'colour' => $colour,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        $target = cardimage::require_target($course, $params['targettype'], (int) $params['targetid']);
        $stored = cardimage::set_colour((int) $course->id, $params['targettype'], (int) $target->id, $params['colour']);

        return ['colour' => $stored];
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'colour' => new external_value(PARAM_TEXT, 'The stored colour, empty when cleared'),
        ]);
    }
}
