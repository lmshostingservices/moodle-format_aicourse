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

namespace format_aicourse\hook;

use core\hook\output\before_standard_head_html_generation;
use format_aicourse\local\fonts;

/**
 * Adds the course's Google Fonts to the page head.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class before_head_html_generation {
    /**
     * Hook callback.
     *
     * Only on pages of a course using this format, never the front page. A failure here must never
     * break a page, so it is reported to developers and the theme's fonts stay.
     *
     * @param before_standard_head_html_generation $hook The hook being dispatched.
     * @return void
     */
    public static function callback(before_standard_head_html_generation $hook): void {
        global $COURSE;

        try {
            if (empty($COURSE->id) || $COURSE->id <= SITEID || ($COURSE->format ?? '') !== 'aicourse') {
                return;
            }
            $html = fonts::head_html(course_get_format($COURSE)->get_format_options());
            if ($html !== '') {
                $hook->add_html($html);
            }
        } catch (\Throwable $e) {
            debugging('format_aicourse fonts: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
