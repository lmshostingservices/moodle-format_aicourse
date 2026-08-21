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
 * Event observers used by the AI Course Format.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse;

/**
 * Event observer for format_aicourse.
 *
 * The AI Tutor answers from a cached snapshot of the course content (the `coursecontent`
 * MUC definition, TTL 600 seconds). Without invalidation the tutor would keep answering from
 * content that is up to ten minutes out of date, and there would be no way for a teacher to
 * force a refresh after editing the course.
 *
 * These observers invalidate that cache whenever the underlying course content changes.
 *
 * Note on granularity: the cache is keyed "courseid_userid", and the MUC application store
 * offers no way to enumerate or pattern match keys, so a targeted delete is not possible
 * without a separate key index maintained on the write side. The observers therefore purge
 * the whole `coursecontent` definition. This is always correct (it can never leave stale
 * content behind); it is simply broader than strictly necessary. See README.md for the
 * recommended follow up.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Triggered via \core\event\course_module_created.
     *
     * @param \core\event\course_module_created $event The event.
     */
    public static function course_module_created(\core\event\course_module_created $event): void {
        self::purge_course_content($event->courseid);
    }

    /**
     * Triggered via \core\event\course_module_updated.
     *
     * @param \core\event\course_module_updated $event The event.
     */
    public static function course_module_updated(\core\event\course_module_updated $event): void {
        self::purge_course_content($event->courseid);
    }

    /**
     * Triggered via \core\event\course_module_deleted.
     *
     * @param \core\event\course_module_deleted $event The event.
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;

        self::purge_course_content($event->courseid);

        // ACF-FIX-2.1.46: drop this activity's duration override with it. Nothing in core knows
        // about that table, and a course module id is reused eventually, so a row left behind
        // would one day attach a stale estimate to an unrelated activity.
        try {
            $DB->delete_records('format_aicourse_actminutes', ['cmid' => $event->objectid]);
        } catch (\dml_exception $e) {
            debugging('format_aicourse: could not remove activity duration override: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Triggered via \core\event\course_section_updated.
     *
     * @param \core\event\course_section_updated $event The event.
     */
    public static function course_section_updated(\core\event\course_section_updated $event): void {
        self::purge_course_content($event->courseid);
    }

    /**
     * Triggered via \core\event\course_updated.
     *
     * @param \core\event\course_updated $event The event.
     */
    public static function course_updated(\core\event\course_updated $event): void {
        self::purge_course_content($event->courseid);
    }

    /**
     * Triggered via \core\event\course_deleted.
     *
     * @param \core\event\course_deleted $event The event.
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;

        self::purge_course_content($event->courseid);

        // The course is gone, so its chat history and tutoring memory must go with it.
        // Both tables are keyed by courseid and are not covered by any core cleanup.
        try {
            $DB->delete_records('format_aicourse_chats', ['courseid' => $event->courseid]);
            $DB->delete_records('format_aicourse_ai_memory', ['courseid' => $event->courseid]);
            // ACF-FIX-2.1.46: duration overrides are keyed by courseid for exactly this.
            $DB->delete_records('format_aicourse_actminutes', ['courseid' => $event->courseid]);
        } catch (\dml_exception $e) {
            debugging(
                'format_aicourse: could not purge course data on course deletion: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Invalidate the cached AI course-content snapshot.
     *
     * This must be as fast and as failure tolerant as possible: it runs on every module and
     * course edit across the whole site, including sites where no course uses this format,
     * and including installation and upgrade time when the cache may not exist yet.
     *
     * @param int $courseid The course whose content changed.
     */
    protected static function purge_course_content(int $courseid): void {
        if (empty($courseid) || $courseid <= SITEID) {
            return;
        }

        try {
            \cache::make('format_aicourse', 'coursecontent')->purge();
        } catch (\Throwable $e) {
            // The cache may be unavailable during install or upgrade. Never break the
            // triggering action because of a cache invalidation failure.
            debugging(
                'format_aicourse: could not purge coursecontent cache: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }
}
