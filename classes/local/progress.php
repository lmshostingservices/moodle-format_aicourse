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

use completion_info;

/**
 * Course and section completion progress, plus estimated study time.
 *
 * Stateless service class: every method is static and none of them produce output.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress {
    /**
     * @var string Shipped default minutes per module type, one pair per line.
     *
     * These are the figures the format used before they were configurable, kept as the starting
     * point so upgrading changes nothing until an administrator decides otherwise. quiz is absent
     * deliberately: it is calculated from its question count instead.
     */
    public const DEFAULT_MINUTES_MAP = "assign=30
book=20
forum=10
h5pactivity=10
lesson=25
page=10
resource=5
scorm=30
url=3
workshop=45";

    /** @var array Per-request cache of duration overrides, keyed by course id then cm id. */
    private static $overridecache = [];

    /**
     * Get whole-course progress for a user.
     *
     * ACF-FIX-2.0: $needactivities added. Most callers (the hero ring, the course-home progress bar,
     * the AJAX progress refresh) only read 'percentage'/'completed'/'total' and throw the
     * activities[] array away, yet building it called format_string() and ->url->out() once per
     * activity in the course.
     *
     * @param \stdClass $course Course record.
     * @param int $userid User to report on.
     * @param completion_info|null $completioninfo Optional pre-loaded completion_info to share.
     * @param bool $needactivities Set false to skip building the per-activity array.
     * @return array Progress data with keys enabled, percentage, completed, total, activities.
     */
    public static function get_progress($course, $userid, $completioninfo = null, $needactivities = true) {
        $result = [
            'enabled' => false,
            'percentage' => 0,
            'completed' => 0,
            'total' => 0,
            'activities' => [],
        ];

        // Accept a pre-loaded completion_info to share one bulk DB read with callers
        // that also call self::get_section_progress().
        $completioninfo = ($completioninfo !== null) ? $completioninfo : new completion_info($course);

        // Completion disabled at course level.
        if (!$completioninfo->is_enabled()) {
            return $result;
        }

        $result['enabled'] = true;

        // Count activities manually (fallback).
        $modinfo = get_fast_modinfo($course, $userid);
        $total = 0;
        $completed = 0;

        // Passing $wholecourse=true on the first get_data() call triggers a single bulk SELECT
        // of every course_modules_completion row for this user, caching it inside $completioninfo.
        // All subsequent calls on this object use the in-memory cache rather than per-row queries.
        $bulkloaded = false;
        foreach ($modinfo->get_cms() as $cm) {
            // ACF-FIX-2.0: skip activities the user cannot see. Without this, hidden and
            // availability-restricted activities inflated the denominator, so a student could never
            // reach 100%. The sibling self::get_section_progress() already did this, so the
            // course ring and the section rings disagreed.
            if (!$cm->uservisible) {
                continue;
            }
            if ($cm->completion == COMPLETION_TRACKING_NONE) {
                continue;
            }

            $total++;
            $data = $completioninfo->get_data($cm, !$bulkloaded, $userid);
            $bulkloaded = true;

            $status = 'not_started';
            if ($data->completionstate == COMPLETION_COMPLETE || $data->completionstate == COMPLETION_COMPLETE_PASS) {
                $completed++;
                $status = 'completed';
            } else if (!empty($data->viewed) || $data->completionstate == COMPLETION_INCOMPLETE) {
                if (!empty($data->viewed)) {
                    $status = 'in_progress';
                }
            }

            if ($needactivities) {
                $result['activities'][] = [
                    'id' => $cm->id,
                    'name' => format_string($cm->name),
                    'status' => $status,
                    'url' => $cm->url ? $cm->url->out() : '',
                ];
            }
        }

        $result['total'] = $total;
        $result['completed'] = $completed;

        // Use Moodle's official course progress API for accurate percentage.
        // This respects course completion criteria, activity dependencies, manual completion rules
        // and hidden activities.
        // ACF-FIX-2.0: dropped the class_exists() guard (\core_completion\progress has shipped in
        // every supported Moodle release) and the '@' error-suppression operator, which hid genuine
        // faults from the developer log.
        if (is_object($course) && !empty($course->id)) {
            $official = \core_completion\progress::get_course_progress_percentage($course, $userid);

            if ($official !== null && is_numeric($official)) {
                $result['percentage'] = (int) round($official);
                return $result;
            }
        }

        // Safe fallback calculation (avoid division by zero).
        $result['percentage'] = ($total > 0) ? (int) round(($completed / $total) * 100) : 0;

        return $result;
    }

    /**
     * Get section progress data for card display.
     *
     * @param \stdClass $course Course record.
     * @param \section_info $section Section to report on.
     * @param int $userid User to report on.
     * @param completion_info|null $completioninfo Optional pre-loaded completion_info to share.
     * @return array Progress data with keys enabled, percentage, completed, total, activities,
     *               estimatedminutes and activitycount.
     */
    public static function get_section_progress($course, $section, $userid, $completioninfo = null) {
        // Accept a pre-loaded completion_info object so the caller can share one bulk-loaded
        // instance across multiple sections, avoiding N separate DB queries.
        $info = ($completioninfo !== null) ? $completioninfo : new completion_info($course);
        $result = [
            'enabled' => false,
            'percentage' => 0,
            'completed' => 0,
            'total' => 0,
            'activities' => [],
            'estimatedminutes' => 0,
            'activitycount' => 0,
        ];

        $modinfo = get_fast_modinfo($course, $userid);
        $total = 0;
        $completed = 0;
        $estimatedminutes = 0;
        $activitycount = 0;

        // Check if completion tracking is enabled.
        $completionenabled = $info->is_enabled();
        $result['enabled'] = $completionenabled;

        // Get activity IDs directly from the section (safer than comparing section numbers).
        $sectionnum = $section->section;
        $sectioncmids = isset($modinfo->sections[$sectionnum]) ? $modinfo->sections[$sectionnum] : [];

        // When no pre-loaded $completioninfo was supplied, bulk-load all completion data for this
        // user+course on the first get_data() call (passing $wholecourse=true). If a pre-loaded
        // object was provided by the card renderer, the cache is already warm — use false.
        $needsbulkload = ($completioninfo === null);
        $bulkloaded = false;

        foreach ($sectioncmids as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if (!$cm->uservisible) {
                continue;
            }

            // ACF-FIX-2.0: only count what the card renderer will actually show, so the
            // "N activities" badge always matches the number of rendered items.
            if (activityinfo::cm_counts_as_content($cm)) {
                $activitycount++;
            }

            // ACF-FIX-2.0: shared model — see self::estimate_activity_minutes().
            $estimatedminutes += self::estimate_activity_minutes($cm);

            // Only track completion if enabled.
            if ($completionenabled && $cm->completion != COMPLETION_TRACKING_NONE) {
                $total++;
                $wholecourse = ($needsbulkload && !$bulkloaded);
                $data = $info->get_data($cm, $wholecourse, $userid);
                $bulkloaded = true;

                $status = 'not_started';
                $completeddate = null;
                if ($data->completionstate == COMPLETION_COMPLETE || $data->completionstate == COMPLETION_COMPLETE_PASS) {
                    $completed++;
                    $status = 'completed';
                    if ($data->timemodified) {
                        // ACF-FIX-2.0: strftime* strings live in core_langconfig, not core moodle.php.
                        // Without the component the lookup produced a literal [[strftimedateshort]].
                        $completeddate = userdate(
                            $data->timemodified,
                            get_string('strftimedateshort', 'core_langconfig')
                        );
                    }
                } else if ($data->viewed) {
                    $status = 'in_progress';
                }

                $result['activities'][] = [
                    'id' => $cm->id,
                    'name' => format_string($cm->name),
                    'status' => $status,
                    'completeddate' => $completeddate,
                    'url' => $cm->url ? $cm->url->out() : '',
                ];
            }
        }

        $result['total'] = $total;
        $result['completed'] = $completed;
        $result['percentage'] = $total > 0 ? round(($completed / $total) * 100) : 0;
        $result['estimatedminutes'] = $estimatedminutes;
        $result['activitycount'] = $activitycount;

        return $result;
    }

    /**
     * Estimated time in minutes for a single activity.
     *
     * ACF-FIX-2.0: extracted so the section cards, the section progress helper and the course-home
     * hero meta row all use one model. Previously this switch existed only inside
     * self::get_section_progress(), so any other caller had to duplicate it.
     *
     * @param \cm_info $cm Course module.
     * @return int Minutes.
     */
    public static function estimate_activity_minutes($cm) {
        // ACF-FIX-2.1.46: three sources, most specific first.
        //
        // 1. A teacher's override for this exact activity, set by clicking the badge in edit mode.
        // Nothing else can be as accurate as the person who wrote the activity, so it wins
        // outright -- including a deliberate 0, which hides the badge rather than falling back.
        // 2. The site's per-module-type defaults, editable under the format's settings, so an
        // administrator can say what an assignment is worth on THEIR site rather than inheriting
        // a number chosen here.
        // 3. For a quiz, a per-question figure multiplied by the question count, because "a quiz"
        // is not a duration: a five-question check and a fifty-question exam differ by an order
        // of magnitude and a single default is wrong for both.
        $override = self::get_activity_override($cm);
        if ($override !== null) {
            return $override;
        }

        $defaults = self::get_default_minutes();

        if ($cm->modname === 'quiz') {
            $perquestion = (int) (get_config('format_aicourse', 'minutesperquestion') ?: 1);
            $questions = self::count_quiz_questions($cm);
            if ($questions > 0) {
                return max(1, $perquestion * $questions);
            }
        }

        if (isset($defaults[$cm->modname])) {
            return (int) $defaults[$cm->modname];
        }
        return (int) (get_config('format_aicourse', 'minutesfallback') ?: 5);
    }

    /**
     * A teacher's per-activity duration override, if one has been set.
     *
     * Cached per request: a course-home render asks for every activity in the course, and one
     * query for the lot beats one query per activity.
     *
     * @param \cm_info $cm Course module.
     * @return int|null Minutes, or null when no override exists.
     */
    public static function get_activity_override($cm): ?int {
        global $DB;
        $courseid = (int) $cm->course;
        // ACF-FIX-2.1.46: a class property rather than a function static, so a save in the same
        // request can genuinely clear it. With a function static it could not be reached, and the
        // external function reported the value from BEFORE the change -- a teacher who cleared an
        // override was told the old number was still in force.
        if (!array_key_exists($courseid, self::$overridecache)) {
            self::$overridecache[$courseid] = $DB->get_records_menu(
                'format_aicourse_actminutes',
                ['courseid' => $courseid],
                '',
                'cmid, minutes'
            );
        }
        $cmid = (int) $cm->id;
        return array_key_exists($cmid, self::$overridecache[$courseid])
            ? (int) self::$overridecache[$courseid][$cmid]
            : null;
    }

    /**
     * Clear the per-request override cache.
     *
     * Called after a save so the value just written is the one rendered back, rather than the
     * value read earlier in the same request.
     *
     * @return void
     */
    public static function purge_override_cache(): void {
        self::$overridecache = [];
    }

    /**
     * Site-configured default minutes per module type.
     *
     * Stored as one "modname=minutes" pair per line rather than a fixed set of fields, so a site
     * running third-party activity modules can give those a sensible figure too instead of having
     * every one of them fall to the catch-all.
     *
     * @return array modname => minutes.
     */
    public static function get_default_minutes(): array {
        $raw = (string) get_config('format_aicourse', 'defaultminutes');
        if (trim($raw) === '') {
            $raw = self::DEFAULT_MINUTES_MAP;
        }
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }
            [$name, $minutes] = explode('=', $line, 2);
            $name = trim($name);
            $minutes = (int) trim($minutes);
            if ($name !== '' && $minutes >= 0) {
                $out[$name] = $minutes;
            }
        }
        return $out;
    }

    /**
     * How many questions a quiz contains.
     *
     * Counts slots rather than questions in the bank: a random slot draws one question at attempt
     * time, so a quiz of ten random slots is ten questions to the learner even though the bank
     * holds fifty.
     *
     * @param \cm_info $cm Course module, expected to be a quiz.
     * @return int Question count, 0 when it cannot be determined.
     */
    public static function count_quiz_questions($cm): int {
        global $DB;
        static $cache = [];
        $cmid = (int) $cm->id;
        if (array_key_exists($cmid, $cache)) {
            return $cache[$cmid];
        }
        $count = 0;
        try {
            if ($DB->get_manager()->table_exists('quiz_slots')) {
                $count = (int) $DB->count_records('quiz_slots', ['quizid' => $cm->instance]);
            }
        } catch (\Throwable $e) {
            // A quiz subplugin schema we do not recognise: fall back to the type default.
            $count = 0;
        }
        $cache[$cmid] = $count;
        return $count;
    }

    /**
     * Format a duration in minutes for display.
     *
     * ACF-FIX-2.0: i18n — this was built by concatenation ("2" . " " . "hr" . " " . "30" . " " .
     * "min"), which hardcodes English number-then-unit word order and is bidi-hostile in RTL.
     * Single placeholder strings let each language pack choose its own duration format.
     *
     * @param int $minutes Duration in minutes.
     * @return string Empty string when there is nothing to show.
     */
    public static function format_estimated_time($minutes) {
        $minutes = (int) $minutes;
        if ($minutes <= 0) {
            return '';
        }
        if ($minutes < 60) {
            return get_string('esttime_m', 'format_aicourse', $minutes);
        }
        $hours = (int) floor($minutes / 60);
        $mins = $minutes % 60;
        if ($mins > 0) {
            return get_string('esttime_hm', 'format_aicourse', (object) [
                'hours' => $hours,
                'mins' => $mins,
            ]);
        }
        return get_string('esttime_h', 'format_aicourse', $hours);
    }
}
