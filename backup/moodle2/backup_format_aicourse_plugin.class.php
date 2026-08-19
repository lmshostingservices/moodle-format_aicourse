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
 * Backup support for the AI Course Format.
 *
 * The format's own settings live in {course_format_options} and are backed up by core without
 * any help from this class. The one thing core cannot know about is the banner image, which the
 * plugin stores as a file in the course context under the 'bannerimage' file area. Without the
 * annotation below the banner is silently dropped from every backup, so a restored or duplicated
 * course loses it while keeping every setting that refers to it.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup plugin class for the AI Course Format.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_format_aicourse_plugin extends backup_format_plugin {
    /**
     * Define the plugin structure attached to the course element.
     *
     * A wrapper element is emitted even though the plugin stores no extra tables, for two
     * reasons. Files can only be annotated against a nested element that actually produces a
     * row, and on the restore side a plugin is only asked to run its after_restore_course()
     * hook if it registered at least one path element -- which it can only do if this element
     * exists in the backup file.
     *
     * @return backup_plugin_element The plugin element.
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element();

        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $banner = new backup_nested_element('banner', ['id'], ['courseid']);
        $pluginwrapper->add_child($banner);

        $banner->set_source_array([
            (object) [
                'id' => 1,
                'courseid' => $this->task->get_courseid(),
            ],
        ]);

        // Item id is passed as null on purpose: backup_structure_dbops::annotate_files() then
        // omits the itemid clause entirely and picks up every banner file in the course context.
        // That keeps backups correct on a site that has not yet run the 2.1.5 upgrade step, where
        // the file may still sit under itemid = courseid rather than 0.
        $banner->annotate_files('format_aicourse', 'bannerimage', null);

        return $plugin;
    }
}
