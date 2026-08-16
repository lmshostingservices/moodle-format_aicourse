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

namespace format_aicourse\local;

use context_course;
use moodle_url;

/**
 * Resolution of the course hero image: custom banner first, course overview image second.
 *
 * Stateless service class: every method is static and none of them produce output.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class banner {
    /**
     * Return the URL of the course overview image, or null when the course has none.
     *
     * @param \stdClass $course Course record.
     * @return string|null Absolute pluginfile URL, or null.
     */
    public static function get_course_image($course) {
        $context = context_course::instance($course->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder DESC, id ASC', false);

        if ($files) {
            $file = reset($files);
            $imageurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename()
            );
            return $imageurl->out();
        }
        return null;
    }

    /**
     * Return the URL of the custom banner image uploaded via the course format settings.
     *
     * Returns null when no custom banner image has been uploaded. Callers fall back to
     * {@see self::get_course_image()} so a course overview image still works if no dedicated
     * banner has been uploaded.
     *
     * @param \stdClass $course Course record.
     * @return string|null Absolute pluginfile URL, or null.
     */
    public static function get_banner_image_url($course) {
        $context = context_course::instance($course->id);
        $fs      = get_file_storage();
        $files   = $fs->get_area_files(
            $context->id,
            'format_aicourse',
            'bannerimage',
            $course->id,
            'sortorder DESC, id ASC',
            false
        );

        if ($files) {
            $file = reset($files);
            return moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                'format_aicourse',
                'bannerimage',
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            )->out();
        }

        return null;
    }
}
