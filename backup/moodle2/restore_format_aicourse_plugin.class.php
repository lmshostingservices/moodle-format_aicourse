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
 * Restore support for the AI Course Format.
 *
 * Counterpart to backup_format_aicourse_plugin. Its only job is to put the course banner image
 * back into the restored course's file area; the format's settings are restored by core from
 * {course_format_options}.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore plugin class for the AI Course Format.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_format_aicourse_plugin extends restore_format_plugin {
    /**
     * Define the paths this plugin handles inside the course element.
     *
     * The element carries no data worth restoring, but registering a path is not optional:
     * restore_plugin::define_plugin_structure() only records a processing object when the
     * plugin returns at least one path, and restore_structure_step::launch_after_restore_methods()
     * walks those recorded objects. Return nothing here and after_restore_course() below is
     * never called, so the banner is never restored.
     *
     * @return array Array of restore_path_element.
     */
    protected function define_course_plugin_structure() {
        return [
            new restore_path_element('aicourse_banner', $this->get_pathfor('/banner')),
        ];
    }

    /**
     * Process the banner element.
     *
     * Intentionally a no-op. See define_course_plugin_structure() for why the element exists.
     *
     * @param array|stdClass $data The parsed element.
     * @return void
     */
    public function process_aicourse_banner($data) {
        return;
    }

    /**
     * Restore the banner image once the course itself has been restored.
     *
     * The file area is added with a null item id mapping, which makes
     * restore_dbops::send_files_to_pool() reuse the stored item id verbatim. That is correct
     * because the banner is always filed under format_aicourse\local\banner::BANNER_ITEMID (0),
     * which does not change between courses.
     *
     * @return void
     */
    public function after_restore_course() {
        $this->add_related_files('format_aicourse', 'bannerimage', null);
    }
}
