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

        // ACF-FIX-2.1.26: lift the banner to the very top of the page.
        //
        // This runs before the hero-injection branch below and independently of it, because the
        // two cases are different: course/view.php renders its own banner in format.php and gets
        // no injected one, but it still needs lifting. The module is a no-op when there is no
        // banner on the page, when one has already been lifted, or in edit mode, so loading it
        // unconditionally on aicourse pages costs one cached AMD request and nothing else.
        //
        // Why JavaScript at all: format.php is included AFTER $OUTPUT->header() has written the
        // navbar, #page-header and the secondary navigation, so a course format has no
        // server-side insertion point above them, and the banner and the nav are in different
        // non-sibling subtrees so no CSS ordering reaches across. See amd/src/heroatop.js.
        try {
            $atopoptions = course_get_format($COURSE)->get_format_options();
            if (!empty($atopoptions['heroattop']) && !$PAGE->user_is_editing()) {
                // ACF-FIX-2.1.95: none of this belongs on a settings or admin page.
                //
                // The hook fires wherever $COURSE is set, which includes /course/edit.php -- so the
                // banner, the player sidebar, the tab relocator and the first-run tour were all being
                // loaded onto the course settings form. Measured: five modules initialised on that page,
                // every one of them reaching into the DOM for elements that are not there.
                //
                // That is how the Course format section came to open only intermittently: the form's own
                // collapse behaviour was competing with scripts that had no business running.
                //
                // Restricted to the pages this format actually draws: the course itself, its
                // sections, and activity pages within it.
                //
                // ACF-FIX-2.1.148: grade pages join that list. They are inside the course and they
                // render the same course index, so leaving them out meant a learner opening their
                // grades saw the panel revert to Moodle's plain list -- no activity icons, no
                // durations, no completion ticks -- and then get them back on returning to the
                // course. The panel should not change shape depending on which page of a course
                // someone is on.
                //
                // Narrow on purpose. This guard exists because the hook fires wherever $COURSE is
                // set, which put five modules on the course settings form and made the Course
                // format section open only intermittently. `grade-report-` is added rather than
                // anything broader for that reason.
                $pagetype = (string) $PAGE->pagetype;
                $isformatpage = strpos($pagetype, 'course-view') === 0
                    || strpos($pagetype, 'mod-') === 0
                    || strpos($pagetype, 'grade-report-') === 0;
                if (!$isformatpage) {
                    return;
                }

                $PAGE->requires->js_call_amd('format_aicourse/heroatop', 'init');
            }
        } catch (\Throwable $e) {
            unset($e);
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
        // ACF-FIX-2.1.54: publish the course accent at the document root.
        //
        // It was inlined on the hero element, so its custom properties were scoped to the hero's
        // own subtree and nothing else the format draws ever saw the course's colour -- the
        // sidebar, the tour, the chat panel and the focus ring all fell back to the theme's
        // primary. A per-course accent that tints one banner is not really a per-course accent.
        //
        // Emitted here, above the course-view early return below, because that return skips
        // everything after it on the very page the accent matters most.
        try {
            $accent = \format_aicourse::get_accent_style(
                course_get_format($COURSE)->get_format_options()
            );
            if ($accent !== '') {
                $PAGE->requires->js_call_amd('format_aicourse/bodyclass', 'init', [[], $accent]);
            }
        } catch (\Throwable $e) {
            unset($e);
        }

        // ACF-FIX-2.1.89: move the course tab bar into the site header, for users who can edit
        // and only when the course asks for it.
        try {
            $navctx = \context_course::instance($COURSE->id);
            $navopts = course_get_format($COURSE)->get_format_options();
            if (
                !empty($navopts['coursenavplace'])
                    && has_capability('moodle/course:update', $navctx)
            ) {
                $PAGE->requires->js_call_amd('format_aicourse/coursenav', 'init');
            }
        } catch (\Throwable $e) {
            unset($e);
        }

        // ACF-FIX-2.1.52: the player sidebar. Loaded from the footer so core's course index has
        // already been rendered and can be decorated in place. Above the course-view early return
        // for the same reason the tour is: that return exists to avoid re-rendering the banner,
        // and would otherwise skip this on the very page it matters most.
        try {
            if (\format_aicourse\local\player::is_enabled($COURSE)) {
                // ACF-FIX-2.1.113: the config goes in the page, not in the JS call arguments.
                //
                // It carries a row per activity, so a course of any size pushes the argument
                // string past the 1024-character limit js_call_amd() warns about -- 21 activities
                // is enough. Moodle's own advice for this is to pass the data through the page
                // rather than the call, which is what this does.
                //
                // A script element of type application/json is inert: the browser does not execute
                // it, and JSON_HEX_TAG means a "</script>" appearing inside any course or activity
                // name cannot close the element early.
                $playerconfig = \format_aicourse\local\player::get_js_config($COURSE);
                $encoded = json_encode(
                    $playerconfig,
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
                );
                if ($encoded !== false) {
                    $hook->add_html(
                        '<script type="application/json" id="aicourse-player-config">'
                        . $encoded . '</script>'
                    );
                    $PAGE->requires->js_call_amd('format_aicourse/player', 'init');
                }
            }
        } catch (\Throwable $e) {
            // The sidebar is an enhancement; never let it break a page render.
            unset($e);
        }

        // ACF-FIX-2.1.43: offer the first-run tour.
        //
        // Deliberately ABOVE the course-view early return below. That return exists because
        // format.php has already rendered the banner on the course page and re-rendering it here
        // would duplicate it -- but it also meant anything added after it never ran on the very
        // page the tour is mostly about. The tour only queues a JS module, so it is safe on both
        // sides of that boundary.
        try {
            $tourcontext = \context_course::instance($COURSE->id);
            if (\format_aicourse\local\tour::should_offer($tourcontext)) {
                $PAGE->requires->js_call_amd(
                    'format_aicourse/tour',
                    'init',
                    [\format_aicourse\local\tour::get_js_config($tourcontext)]
                );
            }
        } catch (\Throwable $e) {
            // The tour is a nicety; never let it break a page render.
            unset($e);
        }

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

        // ACF-FIX-2.1.155: the activity-page branch is gone. lib.php::page_set_course() now reads
        // bit 2 correctly and adds the class server-side, in the <body> tag, before a pixel is
        // painted. Deciding it here as well meant the class arrived after the whole page had
        // streamed, and applying it then flips `display` on the drawer and four ancestor boxes'
        // margins at once -- properties core animates, so the entire content column visibly
        // reflowed a beat after load. That was the jerk on activity pages.
        //
        // It also could not be undone: bodyclass.js only ever ADDS. When the two halves disagreed
        // -- which they did for every value of the mask except 3 and 7 -- whichever one said "hide"
        // won permanently.
        //
        // Section pages return before this point, and the remaining page types below (grade,
        // participants, enrol, badges, competency, report) have never had a bit of their own; they
        // are judged by lib.php on bit 0, as they always were.
        if ($issectionpage && ($courseindexsetting & 2) === 0) {
            $hideindex = true;
        }

        // ACF-FIX-2.1: body classes are applied by an AMD module rather than an inline
        // <script>. They cannot be added with $PAGE->add_body_class() here because the
        // <body> tag has already been written by the time a footer hook runs; the course
        // pages themselves handle it server-side in format_aicourse::page_set_course().
        // (ACF-FIX-2.1.155: "the course pages" now includes activity pages -- see above. Anything
        // added here is applied a full page-load late, so nothing that affects layout belongs in
        // this list.)
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
