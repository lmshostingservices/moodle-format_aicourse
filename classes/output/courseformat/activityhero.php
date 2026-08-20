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

namespace format_aicourse\output\courseformat;

use context_course;
use core\output\named_templatable;
use format_aicourse\local\activityinfo;
use format_aicourse\local\banner;
use format_aicourse\local\navigation;
use format_aicourse\local\permissions;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;

/**
 * The activity page hero banner, showing completion requirements for one activity.
 *
 * Renders format_aicourse/activity_hero. The banner shows the owning section name, the activity
 * name, previous/next activity chevrons, and a completion indicator that is either a clickable
 * manual-completion toggle, a static tick, the current grade, or an empty ring.
 *
 * Pre-escaped values in the exported context — these are the ONLY values the template may render
 * with a triple mustache, and each is documented again in templates/activity_hero.mustache:
 *
 *  - cmname, sectionlabel  {@see format_string()} output.
 *  - prevurl, nexturl, backurl, gradesurl, homeurl  {@see moodle_url::out()} output (ampersands
 *    already entity-encoded).
 *  - completionlabel, requirementstext, gradetext  {@see get_string()} output, joined for display.
 *    They contain no caller-supplied data — only language pack text and numbers formatted with
 *    {@see format_float()}.
 *
 * Everything else in the context is plain text or an integer and is escaped by the template with
 * a double mustache, which is exactly the `s()` call the pre-template string builder applied.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activityhero implements named_templatable, renderable {
    /** @var stdClass Course record. */
    protected $course;

    /** @var array Course format options. */
    protected $options;

    /** @var \cm_info|stdClass Course module the hero describes. */
    protected $cm;

    /**
     * Constructor.
     *
     * @param stdClass $course Course record.
     * @param array $options Course format options.
     * @param \cm_info|stdClass $cm Course module the hero describes.
     */
    public function __construct(stdClass $course, array $options, $cm) {
        $this->course = $course;
        $this->options = $options;
        $this->cm = $cm;
    }

    /**
     * Name of the template this renderable renders with.
     *
     * @param renderer_base $renderer The renderer requesting the template name.
     * @return string Template name.
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_aicourse/activity_hero';
    }

    /**
     * Export the activity hero banner data for the template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Template context.
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER;

        $course = $this->course;
        $options = $this->options;
        $cm = $this->cm;

        // Custom banner image takes priority; fall back to course overview image.
        // Track custom banner separately so we can show the delete button only for custom images.
        $custombanner = banner::get_banner_image_url($course);
        $imageurl = $custombanner;
        if (!$imageurl) {
            $imageurl = banner::get_course_image($course);
        }

        $navdata = navigation::get_nav_links($course, $USER->id);
        $currentsection = navigation::get_current_section($course, $USER->id);

        // Get completion info for this activity.
        $completioninfo = activityinfo::get_activity_completion_info($course, $cm, $USER->id);

        // ACF-FIX-2.0: dead code removed — $iconurl was computed here (costing a get_fast_modinfo()
        // and a get_icon_url()) and never used anywhere in this function.

        // Image mode: adds a class to trigger tall immersive CSS and skips the max-height constraint.
        // ACF-FIX-2.0: a11y — named landmark region, same as the course/section hero.
        $data = (object) [
            // ACF-FIX-2.1.23: see the note in hero.php - the banner carries its own copy of
            // the accent custom properties because it sits outside the content container.
            'accentstyle' => \format_aicourse::get_accent_style($options),
            'cmname' => format_string($cm->name),
            'hasimage' => !empty($imageurl),
            'imageurl' => (string) $imageurl,
            'hassection' => !empty($currentsection),
            'sectionlabel' => '',
        ];

        if (!empty($currentsection)) {
            // ACF-FIX-2.0: i18n — single placeholder string instead of "Section" . ' ' . number.
            $data->sectionlabel = format_string($this->section_name($currentsection));
        }

        $this->export_nav($data, $navdata);
        $this->export_completion($data, $completioninfo);
        $this->export_icons($data, $currentsection, $custombanner);

        return $data;
    }

    /**
     * Display name of the section an activity belongs to.
     *
     * @param array $currentsection Section descriptor with 'num' and 'name' keys.
     * @return string Section name, or the "Section N" fallback when the section is unnamed.
     */
    protected function section_name(array $currentsection): string {
        return !empty($currentsection['name'])
            ? $currentsection['name']
            : get_string('sectionnumber', 'format_aicourse', $currentsection['num']);
    }

    /**
     * Add the previous/next activity chevrons to the context.
     *
     * ACF-FIX-2.0: a11y — direction-aware accessible name (previousactivity/nextactivity were
     * defined in the lang pack for exactly this and never used).
     *
     * @param stdClass $data Context being built, modified in place.
     * @param array $navdata Navigation links with 'prev' and 'next' keys.
     * @return void
     */
    protected function export_nav(stdClass $data, array $navdata): void {
        $data->hasprev = !empty($navdata['prev']);
        $data->prevurl = '';
        $data->prevname = '';
        $data->prevlabel = '';
        if ($data->hasprev) {
            $data->prevurl = $navdata['prev']['url'];
            $data->prevname = $navdata['prev']['name'];
            $data->prevlabel = get_string('previousactivitynamed', 'format_aicourse', $navdata['prev']['name']);
        }

        $data->hasnext = !empty($navdata['next']);
        $data->nexturl = '';
        $data->nextname = '';
        $data->nextlabel = '';
        if ($data->hasnext) {
            $data->nexturl = $navdata['next']['url'];
            $data->nextname = $navdata['next']['name'];
            $data->nextlabel = get_string('nextactivitynamed', 'format_aicourse', $navdata['next']['name']);
        }
    }

    /**
     * Add the completion tick, the manual completion toggle and the requirements text.
     *
     * ACF-FIX-2.0: a11y — the toggle previously always read "Mark as done", even when the activity
     * was already complete, and had no pressed state. aria-pressed now reflects the real state and
     * the accessible name includes the activity name.
     *
     * @param stdClass $data Context being built, modified in place.
     * @param array $completioninfo Completion detail from activityinfo::get_activity_completion_info().
     * @return void
     */
    protected function export_completion(stdClass $data, array $completioninfo): void {
        $ismanual = !empty($completioninfo['ismanual']);
        $hascompletion = !empty($completioninfo['hascompletion']);
        $iscompleted = !empty($completioninfo['completed']);

        $data->ismanualtoggle = ($ismanual && $hascompletion);
        $data->iscompleted = $iscompleted;
        $data->cmid = !empty($completioninfo['cmid']) ? (int) $completioninfo['cmid'] : 0;
        $data->completedflag = $iscompleted ? '1' : '0';
        $data->pressed = $iscompleted ? 'true' : 'false';
        $data->togglelabel = $iscompleted
            ? get_string('markasdoneundo', 'format_aicourse', $data->cmname)
            : get_string('markasdonefor', 'format_aicourse', $data->cmname);
        $data->manualtitle = get_string('completionrequirement_manual', 'format_aicourse');

        // Requires a grade but has not passed yet — show the current grade instead of a grey tick.
        $data->showgrade = (!$iscompleted
            && !empty($completioninfo['requiresgrade'])
            && !empty($completioninfo['gradetext']));
        $data->gradetext = (string) $completioninfo['gradetext'];

        if ($data->ismanualtoggle) {
            $data->completionlabel = $iscompleted
                ? get_string('completed', 'format_aicourse')
                : get_string('completionrequirement_manual', 'format_aicourse');
        } else if (!empty($completioninfo['requirements'])) {
            // ACF-FIX-2.0: i18n — the hardcoded ' • ' join is bidi-hostile in RTL. Use a
            // translatable list separator so language packs can choose their own.
            $sep = get_string('listseparator', 'format_aicourse');
            $data->completionlabel = implode($sep, $completioninfo['requirements']);
        } else {
            $data->completionlabel = get_string('nocompletion', 'format_aicourse');
        }
    }

    /**
     * Add the icon rail: back to section, grades, AI assistant, home and the banner buttons.
     *
     * FIX-GRADES-LINK (v1.7.54): Teachers (grade/report:viewall) go to the grader report; everyone
     * else goes to their own user grade report.
     *
     * @param stdClass $data Context being built, modified in place.
     * @param array|null $currentsection Section descriptor with 'num' and 'name', or null.
     * @param string|null $custombanner URL of the uploaded custom banner, or null when there is none.
     * @return void
     */
    protected function export_icons(stdClass $data, $currentsection, $custombanner): void {
        global $PAGE;

        $course = $this->course;
        $context = context_course::instance($course->id);

        $data->hasback = !empty($currentsection);
        $data->backurl = '';
        $data->backlabel = '';
        if ($data->hasback) {
            // ACF-FIX-2.0: i18n — one placeholder string instead of a translated fragment,
            // ' - ' and data.
            $sectionurl = new moodle_url('/course/view.php', [
                'id' => $course->id,
                'section' => $currentsection['num'],
            ]);
            $data->backurl = $sectionurl->out();
            $data->backlabel = get_string(
                'returntosectionnamed',
                'format_aicourse',
                format_string($this->section_name($currentsection))
            );
        }

        if (has_capability('moodle/grade:viewall', $context, null, false)) {
            $gradesurl = new moodle_url('/grade/report/grader/index.php', ['id' => $course->id]);
        } else {
            $gradesurl = new moodle_url('/grade/report/user/index.php', ['id' => $course->id]);
        }

        $data->courseid = (int) $course->id;
        $data->sesskey = sesskey();
        $data->gradesurl = $gradesurl->out();
        $data->gradeslabel = get_string('grades', 'format_aicourse');
        $data->showtutor = permissions::is_tutor_enabled();
        $data->aiassistantlabel = get_string('aiassistant', 'format_aicourse');
        $data->homeurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out();
        $data->homelabel = get_string('gotocourse', 'format_aicourse');

        // AI Generate Banner button — editors only. The delete button additionally needs an
        // uploaded custom banner AND Moodle to be in edit mode.
        $data->canedit = has_capability('moodle/course:update', $context);
        $data->showremovebanner = ($data->canedit && !empty($custombanner) && $PAGE->user_is_editing());
        $data->removebannerlabel = get_string('removebannerimage', 'format_aicourse');
        $data->generatebannerlabel = get_string('generatebannerimage', 'format_aicourse');
        $data->coursename = format_string($course->fullname);
        $data->shortname = $course->shortname;
    }

    /**
     * Render the activity hero banner.
     *
     * The AI Assistant chatbox markup is appended after the banner, exactly where the pre-template
     * string builder emitted it. It is a sibling of the hero <section>, not a child, so it stays
     * out of the hero template and keeps its own renderable.
     *
     * @return string HTML.
     */
    public function out(): string {
        global $OUTPUT;

        return $OUTPUT->render($this) . (new chatbox())->out();
    }
}
