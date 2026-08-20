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
 * format_aicourse file.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\hook;

use core\hook\output\before_standard_footer_html_generation;
use format_aicourse\local\callbacks;
use format_aicourse\local\permissions;
use format_aicourse\output\courseformat\activityhero;
use format_aicourse\output\courseformat\chatbox;
use format_aicourse\output\courseformat\hero;

/**
 * Injects the hero banner and the AI Tutor script into pages that format.php does not render.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class before_footer_html_generation {
    /**
     * Hook callback.
     *
     * @param before_standard_footer_html_generation $hook The hook being dispatched.
     * @return void
     */
    public static function callback(before_standard_footer_html_generation $hook): void {
        global $COURSE, $PAGE, $CFG;

        if (empty($COURSE->id) || $COURSE->id <= 1) {
            return;
        }

        if ($COURSE->format !== 'aicourse') {
            return;
        }

        // OPT-ACF-HOOK-PAGETYPE (v1.7.51): Fast early exit for page types that can never.
        // Need hero injection or tracker JS (admin, login, calendar, home dashboard, etc.).
        // This avoids loading format options from the DB on every page of the site.
        $pagetype = $PAGE->pagetype;
        $iscoursepage = (
            strpos($pagetype, 'course-') === 0 ||
            strpos($pagetype, 'mod-') === 0 ||
            strpos($pagetype, 'grade-') === 0 ||
            strpos($pagetype, 'enrol-') === 0 ||
            strpos($pagetype, 'badges-') === 0 ||
            strpos($pagetype, 'competency-') === 0 ||
            strpos($pagetype, 'report-') === 0 ||
            $pagetype === 'user-index'
        );
        if (!$iscoursepage) {
            return;
        }

        // Check if we're on the main course page (all sections) vs single section view.
        $sectionparam = optional_param('section', null, PARAM_INT);
        $sectionidparam = optional_param('id', 0, PARAM_INT);

        // Detect if we're on section.php (uses id param for section ID).
        $issectionphp = \format_aicourse\local\callbacks::is_section_php_request();

        // ACF-FIX-2.1: the section.php -> view.php redirect used to happen HERE, in the footer,
        // with an inline <script>window.location.replace(...)</script>. By the time a footer hook
        // runs the whole page has already been generated and streamed, so every section click cost
        // two full page renders and showed a visible flash of the standard Moodle section layout
        // before jumping to the card layout. It also broke without JavaScript, and the section
        // lookup was not scoped to the current course, so a section id from another course
        // resolved and redirected here with a foreign section number.
        // It is now a real server-side redirect() issued from format_aicourse::page_set_course(),
        // before any output starts. Nothing to do in the footer.

        // ACF-FIX-2.1: skip EVERY course/view.php page, not just the all-sections one.
        // format.php renders the hero itself on both the course home page and single-section
        // views, so the previous guard let this hook render a SECOND hero -- a full banner
        // render with its own progress calculation and modinfo work -- on every section page,
        // only for the browser to discard it because one was already present. Rendering it and
        // throwing it away cost a duplicate render on the most-visited page in the course.
        // section.php never reaches here: page_set_course() redirects it to course/view.php
        // before any output starts.
        if ($PAGE->pagetype === 'course-view-aicourse') {
            return;
        }

        // Allow injection on various course-related pages.
        $isactivitypage = strpos($PAGE->pagetype, 'mod-') === 0;
        $issectionpage = $PAGE->pagetype === 'course-section' ||
                         strpos($PAGE->pagetype, 'course-view-section') !== false ||
                         $issectionphp ||
                         ($PAGE->pagetype === 'course-view-aicourse' && $sectionparam !== null);
        $isgradespage = strpos($PAGE->pagetype, 'grade-') === 0;
        $isparticipantspage = $PAGE->pagetype === 'user-index' || strpos($PAGE->pagetype, 'course-user') === 0;
        $isenrolpage = strpos($PAGE->pagetype, 'enrol-') === 0;
        $isbadgespage = strpos($PAGE->pagetype, 'badges-') === 0;
        $iscompetencypage = strpos($PAGE->pagetype, 'competency-') === 0 || strpos($PAGE->pagetype, 'report-competency') === 0;
        $isreportpage = strpos($PAGE->pagetype, 'report-') === 0;

        $allowedpage = $isactivitypage || $issectionpage || $isgradespage ||
                       $isparticipantspage || $isenrolpage || $isbadgespage ||
                       $iscompetencypage || $isreportpage;

        if (!$allowedpage) {
            return;
        }

        require_once($CFG->dirroot . '/course/format/aicourse/lib.php');

        $format = course_get_format($COURSE);
        $options = $format->get_format_options();

        // NOTE: Do NOT call $PAGE->add_body_class() here!
        // Body classes added in hooks are unreliable - <body> has already been output.
        // Use JS to add body classes instead.

        // Ensure JS runs on section/activity/grade pages too.
        $PAGE->requires->js_call_amd('format_aicourse/courseformat', 'init');

        // Check course index visibility setting (bitmask: 1=home, 2=section, 4=activity).
        $courseindexsetting = isset($options['showcourseindex']) ? (int)$options['showcourseindex'] : 7;
        $hideindex = false;

        if ($issectionpage && ($courseindexsetting & 2) === 0) {
            $hideindex = true;
        } else if ($isactivitypage && ($courseindexsetting & 4) === 0) {
            $hideindex = true;
        }

        // ACF-FIX-2.1: body classes are applied by an AMD module rather than an inline
        // <script>. They cannot be added with $PAGE->add_body_class() here because the
        // <body> tag has already been written by the time a footer hook runs; the course
        // pages themselves handle it server-side in format_aicourse::page_set_course().
        // Collecting them and making one js_call_amd removes the last inline scripts, which
        // any Content-Security-Policy that forbids inline script would otherwise block.
        $lateclasses = [];
        if ($hideindex && !$PAGE->user_is_editing()) {
            $lateclasses[] = 'aicourse-hideindex';
        }

        // FIX-GRADER-MULTICAP (v1.7.60): Use permissions::is_grader() — checks.
        // Grade/report:viewall + manageactivities + viewhiddenactivities.
        // Using only grade/report:viewall was unreliable: sites can strip that cap from.
        // Non-editing teacher roles. The helper catches all teacher archetypes.
        $coursecontext = \context_course::instance($PAGE->course->id);
        $isgrader = permissions::is_grader($coursecontext, false);

        // The page_set_course() callback already adds this server-side; this covers the edge case
        // where it ran before full authentication was available.
        if ($isgrader) {
            $lateclasses[] = 'aicourse-is-grader';
            // ACF-FIX-2.1.4: paired with aicourse-is-grader; see the note in lib.php.
            if (has_capability('moodle/course:update', $coursecontext)) {
                $lateclasses[] = 'aicourse-can-edit';
            }
        }

        // ACF-FIX-2.1.9: colour mode must reach activity, section and report pages too, which
        // are rendered by core rather than by format.php.
        $colourclass = callbacks::get_colour_mode_class();
        if ($colourclass !== '') {
            $lateclasses[] = $colourclass;
        }

        $scrimclass = callbacks::get_scrim_class();
        if ($scrimclass !== '') {
            $lateclasses[] = $scrimclass;
        }

        if (!empty($lateclasses)) {
            $PAGE->requires->js_call_amd('format_aicourse/bodyclass', 'init', [$lateclasses]);
        }

        // FIX-ACF-EDITOR-HERO (v1.7.68): Editing teachers and course creators need the.
        // Hero to reach the AI Generate Banner button. Only skip for non-editing graders.
        // (non-editing teacher role) who lack moodle/course:update.
        $caneditcourse = has_capability('moodle/course:update', $coursecontext);
        if ($isgrader && !$caneditcourse) {
            return;
        }

        if (empty($options['showherobanner'])) {
            return;
        }

        // Get the current course module for activity-specific hero banner.
        $cm = null;
        $sectionnum = null;

        if ($PAGE->context && $PAGE->context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id('', $PAGE->context->instanceid);
        }

        // Get section number for section pages.
        if ($issectionpage) {
            // First check for section= parameter (used on course view with single section).
            if ($sectionparam !== null) {
                $sectionnum = $sectionparam;
            } else if ($sectionidparam) {
                // Fallback to id= parameter for course-section pagetype.
                global $DB;
                $section = $DB->get_record('course_sections', ['id' => $sectionidparam], 'section');
                if ($section) {
                    $sectionnum = $section->section;
                }
            }
        }

        // Use activity-specific hero banner on activity pages.
        if ($cm) {
            $html = (new activityhero($COURSE, $options, $cm))->out();
        } else {
            $html = (new hero($COURSE, $options, $sectionnum))->out();
        }

        // ACF-FIX-2.1: the hero markup is parked in an inert <template> and moved into place by
        // an AMD module.
        //
        // Two earlier approaches were both wrong. An inline <script> that string-concatenated the
        // markup is blocked by any Content-Security-Policy forbidding inline script. Passing the
        // markup as a js_call_amd() argument trips Moodle's own 1024 character guard in
        // outputrequirementslib, which emitted a debugging warning on every section and activity
        // page of any site running with developer debugging on.
        //
        // <template> is the right primitive: its contents are parsed but inert -- not rendered,
        // no scripts run, no images fetched -- until the module adopts them.
        $hook->add_html(
            \html_writer::tag('template', $html, ['id' => 'aicourse-hero-source'])
        );
        $PAGE->requires->js_call_amd('format_aicourse/heroinject', 'init');

        // Add chatbox script separately (innerHTML doesn't execute scripts).
        // BUT: Skip for section pages in course view - format.php already outputs the script.
        $iscourseviewsection = ($PAGE->pagetype === 'course-view-aicourse' && $sectionparam !== null);
        if (!$iscourseviewsection) {
            $chatboxscript = (new chatbox($COURSE))->script();
            $hook->add_html($chatboxscript);
        }
    }
}
