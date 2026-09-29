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
 * Web service storing a teacher's own picture as a section or activity card image.
 *
 * 2.5.0. The image arrives base64 encoded in the call rather than through a draft file area.
 * The browser has already downscaled it to at most 1600px wide, so the payload is small, and one
 * call keeps the edit-mode control a single step: no repository dialogue, no second request to
 * move a draft into place. The bytes are held to exactly the rule AI images are held to
 * ({@see cardimage::validate_bytes()}): strict base64, a size ceiling, and a real image of an
 * allowed type judged by content.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_card_image extends external_api {
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
            // PARAM_BASE64, which is Moodle's PEM layout: lines of exactly 64 characters, the last
            // one shorter, joined by newlines. The browser sends it in that shape; anything else is
            // refused by parameter validation before this function runs. Decoded strictly below.
            'imagedata' => new external_value(PARAM_BASE64, 'Base64 image in 64-character lines, no data: prefix'),
        ]);
    }

    /**
     * Store the image.
     *
     * @param int $courseid Course id.
     * @param string $targettype section or cm.
     * @param int $targetid course_sections.id or course_modules.id.
     * @param string $imagedata Base64 image.
     * @return array The stored image's URL.
     */
    public static function execute(int $courseid, string $targettype, int $targetid, string $imagedata): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'imagedata' => $imagedata,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        $target = cardimage::require_target($course, $params['targettype'], (int) $params['targetid']);

        // Base64 inflates by a third, and one newline per 64 characters; refuse an oversized
        // payload before decoding it at all.
        if (strlen($params['imagedata']) > (int) ceil(cardimage::MAX_BYTES * 4 / 3 * 65 / 64) + 4) {
            throw new \moodle_exception('error_cardimagetoolarge', 'format_aicourse');
        }
        $bytes = base64_decode($params['imagedata'], true);
        if ($bytes === false) {
            throw new \moodle_exception('error_cardimageinvalid', 'format_aicourse');
        }

        $url = cardimage::store((int) $course->id, $params['targettype'], (int) $target->id, $bytes, 'upload');

        // An upload supersedes any generation still recorded for this card, so a poll left
        // running in another tab cannot later paint the older AI image over this one.
        cardimage::clear_status((int) $course->id, $params['targettype'], (int) $target->id);

        return ['imageurl' => $url];
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'imageurl' => new external_value(PARAM_URL, 'URL of the stored image'),
        ]);
    }
}
