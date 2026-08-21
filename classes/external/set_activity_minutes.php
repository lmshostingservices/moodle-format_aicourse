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
 * Save a teacher's estimated duration for one activity.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use format_aicourse\local\progress;

/**
 * Save a teacher's estimated duration for one activity.
 *
 * ACF-FIX-2.1.46. The badge on a section card is the sum of its activities' estimates, and those
 * estimates start from a per-type default. Nobody is better placed to correct one than the person
 * who built the activity, so this lets them do it in place rather than hunting through settings.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_activity_minutes extends external_api {
    /** @var int Anything longer than this is a typo, not a lesson plan. */
    public const MAX_MINUTES = 10000;

    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'minutes' => new external_value(
                PARAM_INT,
                'Minutes; 0 hides the badge, -1 clears the override and returns to the default'
            ),
        ]);
    }

    /**
     * Store, or clear, the override for one activity.
     *
     * @param int $cmid Course module id.
     * @param int $minutes Minutes, 0 to hide, -1 to clear.
     * @return array{minutes: int, display: string, iscustom: bool}
     */
    public static function execute(int $cmid, int $minutes): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'minutes' => $minutes,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        // Editing an activity's stated duration is editing the course.
        require_capability('moodle/course:update', $context);

        $minutes = (int) $params['minutes'];
        if ($minutes < -1 || $minutes > self::MAX_MINUTES) {
            throw new \moodle_exception('edittime_invalid', 'format_aicourse');
        }

        $existing = $DB->get_record('format_aicourse_actminutes', ['cmid' => $cm->id]);

        if ($minutes === -1) {
            // Clear: fall back to whatever the site default says today, which is what a teacher
            // who empties the field is asking for.
            if ($existing) {
                $DB->delete_records('format_aicourse_actminutes', ['id' => $existing->id]);
            }
        } else if ($existing) {
            $existing->minutes = $minutes;
            $existing->courseid = (int) $course->id;
            $existing->usermodified = (int) $USER->id;
            $existing->timemodified = time();
            $DB->update_record('format_aicourse_actminutes', $existing);
        } else {
            $DB->insert_record('format_aicourse_actminutes', (object) [
                'courseid' => (int) $course->id,
                'cmid' => (int) $cm->id,
                'minutes' => $minutes,
                'usermodified' => (int) $USER->id,
                'timemodified' => time(),
            ]);
        }

        // The value just written must be the one reported back, not the one read earlier in this
        // same request.
        progress::purge_override_cache();

        // The card's badge is a section total, so the cached section render must go.
        \format_aicourse\local\contentindex::purge_content_cache($course->id);
        rebuild_course_cache($course->id, true);

        // Report the effective value rather than what was sent: after a clear, the caller needs
        // the default that has taken over, and it cannot work that out for itself.
        $modinfo = get_fast_modinfo($course);
        $effective = ($minutes === -1)
            ? progress::estimate_activity_minutes($modinfo->get_cm($cm->id))
            : $minutes;

        return [
            'minutes' => $effective,
            'display' => progress::format_estimated_time($effective),
            'iscustom' => ($minutes !== -1),
        ];
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'minutes' => new external_value(PARAM_INT, 'Effective minutes after the change'),
            'display' => new external_value(PARAM_TEXT, 'Formatted duration, empty when hidden'),
            'iscustom' => new external_value(PARAM_BOOL, 'Whether an override is now set'),
        ]);
    }
}
