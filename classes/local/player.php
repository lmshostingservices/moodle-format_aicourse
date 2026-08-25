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
 * Data for the course index player sidebar.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\local;

/**
 * Supplies the course index with what core does not put in the DOM.
 *
 * ACF-FIX-2.1.52. The course index is core's drawer, and core renders it as a list of links: no
 * completion state, no duration, nothing a learner can use to plan. Rather than replace it -- which
 * would mean reimplementing its collapse behaviour, its drag and drop, and the JavaScript that
 * keeps it in step with the page -- the data core omits is published here and the drawer is
 * decorated in place.
 *
 * Every figure comes from the same helpers the cards and the pills use, so the sidebar cannot
 * disagree with the page beside it.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class player {
    /**
     * Everything the sidebar needs, ready for js_call_amd().
     *
     * @param \stdClass $course The course.
     * @return array
     */
    public static function get_js_config(\stdClass $course): array {
        global $USER, $CFG, $OUTPUT;

        $modinfo = get_fast_modinfo($course);
        $completion = new \completion_info($course);
        $tracked = $completion->is_enabled() && \completion_info::is_enabled_for_site();

        // ACF-FIX-2.1.153: every grade for this course and this user, in two queries.
        // See get_user_grades() for why this is not grade_get_grades() per activity.
        $grades = $tracked ? self::get_user_grades($course, (int) $USER->id) : [];

        $activities = [];
        $totalminutes = 0;
        $done = 0;
        $trackable = 0;

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            $minutes = progress::estimate_activity_minutes($cm);
            $totalminutes += $minutes;

            $iscomplete = false;
            $hascompletion = false;
            // Declared per iteration so the completion date below can never read a previous
            // activity's record, whatever the branch above does.
            $data = null;
            if ($tracked && $cm->completion != COMPLETION_TRACKING_NONE) {
                $hascompletion = true;
                $trackable++;
                $data = $completion->get_data($cm, true, $USER->id);
                $iscomplete = in_array(
                    (int) $data->completionstate,
                    [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS],
                    true
                );
                if ($iscomplete) {
                    $done++;
                }
            }

            // ACF-FIX-2.1.139: what completion actually requires, in the learner's own words.
            //
            // A tick says "done" and an empty circle says "not done"; neither says what would make
            // it done. Core already computes that per activity and shows it on the activity page as
            // the "Done: View" badges -- the same details are read here so the course index can say
            // it too, without inventing wording that would drift from what core displays.
            //
            // Wrapped because cm_completion_details throws for an activity whose module has been
            // removed, and a broken completion lookup should cost one tooltip rather than the
            // whole sidebar.
            //
            // ACF-FIX-2.1.153: each condition now carries its OWN state, not just its wording, and
            // the tooltip that shows them adds the two facts a learner actually wants next -- what
            // they scored, and when it was marked done. A flat list of requirements answers "what
            // is being asked of me"; it does not answer "did I pass, and when".
            $requirements = [];
            $conditions = [];
            if ($hascompletion) {
                try {
                    $details = \core_completion\cm_completion_details::get_instance($cm, $USER->id);
                    foreach ($details->get_details() as $detail) {
                        $text = trim((string) ($detail->description ?? ''));
                        if ($text === '') {
                            continue;
                        }
                        // COMPLETION_COMPLETE_FAIL is a real state and is NOT "met": an activity
                        // graded below the pass mark reports complete-fail, and showing that as a
                        // satisfied condition would tell the learner the opposite of the truth.
                        $status = (int) ($detail->status ?? COMPLETION_INCOMPLETE);
                        $requirements[] = $text;
                        $conditions[] = [
                            'text' => $text,
                            'met' => in_array($status, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true),
                            'failed' => ($status === COMPLETION_COMPLETE_FAIL),
                        ];
                    }
                } catch (\Throwable $e) {
                    unset($e);
                }
            }

            $activities[(string) $cm->id] = [
                'minutes' => $minutes,
                'time' => progress::format_estimated_time($minutes),
                'complete' => $iscomplete,
                'tracked' => $hascompletion,
                'requirements' => $requirements,
                'conditions' => $conditions,
                'completedon' => $hascompletion && $iscomplete
                    ? self::format_completion_date($data)
                    : null,
                'grade' => $hascompletion
                    ? self::format_grade($grades, $cm)
                    : null,
                'type' => activityinfo::get_activity_type_name($cm),
                // ACF-FIX-2.1.58: the module's own icon, so a row is identifiable by shape before
                // it is read. Same source the activity cards use.
                'icon' => $cm->get_icon_url()->out(false),
            ];
        }

        // The percentage counts only what is tracked, matching the hero ring: a course where
        // nothing is tracked shows no percentage rather than a permanent zero, which reads as
        // "you have done none of it" when in fact nothing is being measured.
        $percent = $trackable > 0 ? (int) round(($done / $trackable) * 100) : null;

        return [
            // ACF-FIX-2.1.191: plain text, not HTML. player.js assigns this with textContent,
            // which inserts characters rather than parsing them, so format_string()'s escaping
            // arrived in the sidebar as the visible string "&amp;".
            'coursename' => text::plain($course->fullname, \context_course::instance($course->id)),
            'courseurl' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'percent' => $percent,
            'totaltime' => progress::format_estimated_time($totalminutes),
            'logourl' => self::get_logo_url(),
            'activities' => $activities,
            'nav' => [
                'home' => (new \moodle_url('/'))->out(false),
                'dashboard' => (new \moodle_url('/my/'))->out(false),
                'mycourses' => (new \moodle_url('/my/courses.php'))->out(false),
            ],
        ];
    }

    /**
     * Every graded item in the course with this user's mark against it, in two queries.
     *
     * ACF-FIX-2.1.153. The obvious call here is grade_get_grades(), which is what
     * activityinfo::get_activity_completion_info() uses -- but that helper is per module instance,
     * and the sidebar renders every activity in the course. On the test course that is fifteen
     * calls; on a real one it is a hundred, each of them several queries. Two queries for the whole
     * course is the same information at a fixed cost.
     *
     * Hidden grades are dropped for anyone without moodle/grade:viewhidden, so the tooltip cannot
     * reveal a mark the gradebook is deliberately withholding. Grades the gradebook would show are
     * shown; grades it hides stay hidden.
     *
     * @param \stdClass $course The course.
     * @param int $userid The user whose grades to read.
     * @return array Keyed "modname|instance|itemnumber" => object with grade, grademax, gradepass.
     */
    protected static function get_user_grades(\stdClass $course, int $userid): array {
        global $DB;

        $canviewhidden = has_capability(
            'moodle/grade:viewhidden',
            \context_course::instance($course->id),
            $userid
        );

        $items = $DB->get_records('grade_items', [
            'courseid' => $course->id,
            'itemtype' => 'mod',
        ], '', 'id, itemmodule, iteminstance, itemnumber, grademax, gradepass, hidden');

        if (empty($items)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($items), SQL_PARAMS_NAMED, 'gi');
        $params['userid'] = $userid;
        $rows = $DB->get_records_select(
            'grade_grades',
            "itemid $insql AND userid = :userid",
            $params,
            '',
            'id, itemid, finalgrade, hidden'
        );

        $bytimeitem = [];
        foreach ($rows as $row) {
            $bytimeitem[(int) $row->itemid] = $row;
        }

        $out = [];
        foreach ($items as $item) {
            $grade = $bytimeitem[(int) $item->id] ?? null;
            $hidden = !empty($item->hidden) || ($grade !== null && !empty($grade->hidden));
            if ($hidden && !$canviewhidden) {
                continue;
            }
            $key = $item->itemmodule . '|' . (int) $item->iteminstance . '|' . (int) $item->itemnumber;
            $out[$key] = (object) [
                'grade'     => ($grade === null || $grade->finalgrade === null)
                    ? null
                    : (float) $grade->finalgrade,
                'grademax'  => (float) $item->grademax,
                'gradepass' => (float) $item->gradepass,
            ];
        }

        return $out;
    }

    /**
     * This user's mark for one activity, as a short readable string.
     *
     * Returns null rather than a zero when there is no grade item or no mark yet: "0/20" and "not
     * attempted" are different states, and showing the first for the second is the kind of small
     * lie that costs trust in everything else the panel says.
     *
     * @param array $grades The map from get_user_grades().
     * @param \cm_info $cm The activity.
     * @return string|null
     */
    protected static function format_grade(array $grades, \cm_info $cm): ?string {
        if (empty($grades)) {
            return null;
        }

        // The completiongradeitemnumber field names the item completion depends on, and is null when
        // completion does not depend on a grade -- in which case item 0 is the activity's grade.
        $itemnumber = isset($cm->completiongradeitemnumber) && $cm->completiongradeitemnumber !== null
            ? (int) $cm->completiongradeitemnumber
            : 0;

        $key = $cm->modname . '|' . (int) $cm->instance . '|' . $itemnumber;
        $item = $grades[$key] ?? null;
        if ($item === null || $item->grademax <= 0 || $item->grade === null) {
            return null;
        }

        $percent = (int) round(($item->grade / $item->grademax) * 100);

        return get_string('player_gradeachieved', 'format_aicourse', (object) [
            'grade'   => format_float($item->grade, 1, true, true),
            'max'     => format_float($item->grademax, 1, true, true),
            'percent' => $percent,
        ]);
    }

    /**
     * When completion was recorded, in the user's own timezone and date format.
     *
     * Core stores the moment the completion row last changed. For a completed activity that is
     * when it was completed, which is what the learner is asking about.
     *
     * @param \stdClass|null $data The completion record from completion_info::get_data().
     * @return string|null
     */
    protected static function format_completion_date(?\stdClass $data): ?string {
        if ($data === null || empty($data->timemodified)) {
            return null;
        }

        return get_string(
            'player_completedon',
            'format_aicourse',
            userdate((int) $data->timemodified, get_string('strftimedaydate', 'langconfig'))
        );
    }

    /**
     * The logo uploaded for the sidebar, if an administrator has provided one.
     *
     * The URL carries the file's own timestamp, so replacing the image is picked up immediately
     * rather than being served from a stale cache for a day.
     *
     * @return string URL, or an empty string when nothing has been uploaded.
     */
    protected static function get_uploaded_logo_url(): string {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            \context_system::instance()->id,
            'format_aicourse',
            'playerlogo',
            0,
            'itemid, filepath, filename',
            false
        );
        if (empty($files)) {
            return '';
        }
        $file = reset($files);
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            null,
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    /**
     * The site logo, if the theme provides one.
     *
     * Returned rather than assumed present: a site with no logo configured gets a course name and
     * no broken image, which is better than a placeholder.
     *
     * @return string URL, or an empty string when the site has no logo.
     */
    protected static function get_logo_url(): string {
        global $OUTPUT, $PAGE;

        // ACF-FIX-2.1.63: a logo uploaded for the sidebar wins over everything else. A course
        // player is often branded differently from the site around it -- a client's mark rather
        // than the institution's -- and until now the only way to get a logo here was to change
        // the whole site's.
        $uploaded = self::get_uploaded_logo_url();
        if ($uploaded !== '') {
            return $uploaded;
        }

        // ACF-FIX-2.1.57: ask the theme as well as core.
        //
        // The first version only asked core, and on a site whose logo is configured in the
        // THEME's own settings rather than Moodle's -- which is how theme_academi and most
        // commercial themes do it -- core returns nothing and the sidebar showed no logo at all
        // while the site header two inches above it showed one.
        //
        // Four sources, in order of authority: core's compact logo, core's full logo, the theme's
        // own logo file settings under their usual names, and finally a theme's bespoke helper
        // function. Each is wrapped because a theme can define any of these differently, and a
        // fatal here would take down the page over a decoration.
        try {
            $logo = $OUTPUT->get_compact_logo_url(null, 60);
            if (!$logo) {
                $logo = $OUTPUT->get_logo_url(null, 60);
            }
            if ($logo) {
                return $logo->out(false);
            }
        } catch (\Throwable $e) {
            unset($e);
        }

        if (empty($PAGE->theme)) {
            return '';
        }

        // The names themes conventionally use for a header logo file setting.
        foreach (['logo', 'logocompact', 'headerlogo', 'sitelogo'] as $setting) {
            try {
                $url = $PAGE->theme->setting_file_url($setting, $setting);
                if (!empty($url)) {
                    return (string) $url;
                }
            } catch (\Throwable $e) {
                unset($e);
            }
        }

        // Some themes expose a bespoke helper instead; theme_academi has one.
        $helper = 'theme_' . $PAGE->theme->name . '_get_logo_url';
        if (function_exists($helper)) {
            try {
                $url = $helper('header');
                if (!empty($url)) {
                    return (string) $url;
                }
            } catch (\Throwable $e) {
                unset($e);
            }
        }

        return '';
    }

    /**
     * Whether the player sidebar is switched on for this course.
     *
     * @param \stdClass $course The course.
     * @return bool
     */
    public static function is_enabled(\stdClass $course): bool {
        $options = course_get_format($course)->get_format_options();
        $value = isset($options['playerindex']) ? (int) $options['playerindex'] : -1;
        if ($value < 0) {
            $value = (int) (get_config('format_aicourse', 'defaultplayerindex') ?: 0);
        }
        $force = get_config('format_aicourse', 'forceplayerindex');
        if ($force !== false && $force !== '' && (int) $force >= 0) {
            $value = (int) $force;
        }
        return $value === 1;
    }
}
