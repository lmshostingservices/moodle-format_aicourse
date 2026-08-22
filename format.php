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

use format_aicourse\local\permissions;
use format_aicourse\output\courseformat\chatbox;
use format_aicourse\output\courseformat\content;
use format_aicourse\output\courseformat\content\activitycards;
use format_aicourse\output\courseformat\hero;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/course/format/aicourse/lib.php');

// ACF-FIX-2.0: Removed the try/catch that swallowed initialisation failures. If any of these.
// Three calls throws, the code below dereferenced an undefined $format/$context and produced a.
// Guaranteed fatal or blank page. Letting the exception bubble lets core render a real error page.
$format = course_get_format($course);
$course = $format->get_course();
$context = context_course::instance($course->id);

if (($marker >= 0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    if (method_exists($format, 'set_sectionnum')) {
        $format->set_sectionnum($marker);
    } else {
        $format->set_section_number($marker);
    }
}

// Check for section parameter - use isset and !== null to handle section 0 correctly.
// PHP's empty() treats 0 as empty, but section 0 is valid.
$hassection = isset($displaysection) && $displaysection !== '' && $displaysection !== null;
if ($hassection) {
    if (method_exists($format, 'set_sectionnum')) {
        $format->set_sectionnum($displaysection);
    } else {
        $format->set_section_number($displaysection);
    }
}

// ACF-FIX-2.0: The OPT-ACF-WRITE-CLOSE session write_close() that used to sit here has been.
// Moved to the very end of this file. Closing the session before rendering discarded the.
// Session-cached completion data that the renderers below rely on and silently dropped any.
// \core\notification messages queued for this request.

$options = $format->get_format_options();

$bodyclasses = [];
if (!empty($options['displayascards']) && !$hassection) {
    $bodyclasses[] = 'aicourse-cardview';
}
// Hide course index based on current page type (bitmask: 1=home, 2=section, 4=activity).
$courseindexsetting = isset($options['showcourseindex']) ? (int)$options['showcourseindex'] : 7;
if ($hassection) {
    $showindex = ($courseindexsetting & 2) !== 0;  // Bit 1 = section pages.
} else {
    $showindex = ($courseindexsetting & 1) !== 0;  // Bit 0 = home/course page.
}
if (!$showindex && !$PAGE->user_is_editing()) {
    $bodyclasses[] = 'aicourse-hideindex';
}
// ACF-FIX-2.0: Match the renderer condition below (line ~136), which also requires.
// !$PAGE->user_is_editing(). Without the guard the body class was added for an editing.
// Teacher while the standard renderer was used, and the section page rendered blank.
if ($hassection && !empty($options['activitydisplaymode']) && !$PAGE->user_is_editing()) {
    $bodyclasses[] = 'aicourse-activitycards';
}
// ACF-FIX-2.1: the body classes computed above are now added server-side in
// format_aicourse::page_set_course(), which runs before the <body> tag is written. This file
// is included after $OUTPUT->header(), so it used to patch them on with an inline <script> —
// the last inline script in the plugin, a Content-Security-Policy problem, and a source of
// flash-of-unstyled-content. The computation is kept here only as the single source of truth
// for the comments; page_set_course() performs the identical logic.
unset($bodyclasses, $showindex, $courseindexsetting);

// Render hero OUTSIDE the container so it's not constrained by max-width.
// FIX-ACF-EDITOR-HERO (v1.7.68): Editing teachers and course creators need the hero.
// To access the AI Generate Banner button. Skip for read-only graders (non-editing.
// Teacher role) who lack moodle/course:update, but always render for editors.
// ACF-FIX-2.0: $diag was true, which echoed a <script>console.log(...)</script> dump of the.
// User's roles and capability results into every course page. Diagnostics are now developer-only.
$acfisgrader = permissions::is_grader($context, false);
$acfcanedit  = has_capability('moodle/course:update', $context);
if (!empty($options['showherobanner']) && (!$acfisgrader || $acfcanedit)) {
    $sectionnum = $hassection ? $displaysection : null;
    echo (new hero($course, $options, $sectionnum))->out();
    // Output chatbox script (HTML is included in hero banner).
    echo (new chatbox($course))->script();
}

// The teacher-configured card title size is a genuinely dynamic value, so it rides on the
// container as a custom property rather than in a separate <style> element.
$cardtitlesize = isset($options['cardtitlesize']) ? (int)$options['cardtitlesize'] : 14;
$containercss = ($cardtitlesize > 0) ? '--aicourse-card-title-size:' . $cardtitlesize . 'px;' : '';
// ACF-FIX-2.1.23: the course accent colour and hero fade ride on the container as custom
// properties, so every card, border and focus ring inside it re-derives from them. Both
// values are validated inside get_accent_style(); nothing user-supplied reaches the
// attribute unchecked. The hero is OUTSIDE this container and gets the same string of its
// own, in classes/output/courseformat/hero.php.
$containercss .= format_aicourse::get_accent_style($options);
$containerstyle = ($containercss !== '') ? ' style="' . $containercss . '"' : '';
echo '<div class="format-aicourse-container"' . $containerstyle . '>';

// Section description: shown on section page between hero banner and activity list (v1.7.41).
if ($hassection && !$PAGE->user_is_editing()) {
    try {
        $allsections = get_fast_modinfo($course)->get_section_info_all();
        if (isset($allsections[$displaysection])) {
            $sectioninfo = $allsections[$displaysection];
            // ACF-FIX-2.0: uservisible check added — without it the summary of a hidden or.
            // Restricted section leaked to students who guessed ?section=N.
            if ($sectioninfo->uservisible && !empty($sectioninfo->summary)) {
                $summarytext = file_rewrite_pluginfile_urls(
                    $sectioninfo->summary,
                    'pluginfile.php',
                    $context->id,
                    'course',
                    'section',
                    $sectioninfo->id
                );
                $summarytext = format_text($summarytext, $sectioninfo->summaryformat, ['context' => $context]);
                echo '<div class="format-aicourse-section-summary">' . $summarytext . '</div>';
            }
        }
    } catch (Throwable $e) {
        debugging('AI Course Format section summary error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

echo '<div class="course-content">';

if ($hassection) {
    // In edit mode always use the standard renderer so teachers get drag handles,.
    // "Add an activity or resource" links, and all normal Moodle edit controls.
    if (!empty($options['activitydisplaymode']) && !$PAGE->user_is_editing()) {
        echo (new activitycards($course, $displaysection, $options))->out();
    } else {
        $renderer = $PAGE->get_renderer('format_aicourse');
        $outputclass = $format->get_output_classname('content');
        $widget = new $outputclass($format);
        echo $renderer->render($widget);
    }
} else {
    // Always show the custom card view on the course home page.
    // Edit mode no longer falls back to the Topics accordion — that caused the entire.
    // Card layout to disappear when a teacher clicked Edit mode (UX-ACF-EDITMODE-WIPE).
    // Card-level edit actions (icon, rename, delete, duplicate, add section) are now.
    // Available directly on the cards regardless of edit mode state.
    if (!empty($options['displayascards'])) {
        echo (new content($format))->out();
    } else {
        $renderer = $PAGE->get_renderer('format_aicourse');
        $outputclass = $format->get_output_classname('content');
        $widget = new $outputclass($format);
        echo $renderer->render($widget);
    }
}

echo '</div>';

echo '</div>';

// Initialize AMD module for icon picker and progress animations.
$PAGE->requires->js_call_amd('format_aicourse/courseformat', 'init');

// ACF-FIX-2.1.183: the section-card module is queued HERE, beside courseformat, and not in the
// footer hook.
//
// It spent three releases never running on the page it exists for, and the reason is worth keeping:
// it was queued in before_footer_html_generation(), after that method's `$allowedpage` guard. That
// guard admits activity, section, grades, participants, enrol, badges, competency and report pages
// -- and `course-view-aicourse` counts as a "section page" there only when a section parameter is
// present. The COURSE HOME PAGE, the one page section cards are actually on, fails every branch and
// the method returns before the queue is reached. Moving it up beside heroatop in that same file
// then swapped it for a subtler version of the same fault: that block is inside
// `if (!empty($options['heroattop']))`, so the cards would have depended on an unrelated banner
// setting being on.
//
// format.php has neither problem. It is included by exactly the pages that render section cards,
// it is where courseformat is already queued for the same reason, and there is no guard between
// the top of the file and here that a working course page can fail. The lesson is not about this
// hook: it is that a module verified in a harness is only verified WHEN IT RUNS, and where it is
// queued from is part of whether it runs.
$PAGE->requires->js_call_amd('format_aicourse/cards', 'init');

// ACF-FIX-2.0: Release the Moodle session lock only now that all rendering is finished, so.
// Completion data cached in the session and any queued \core\notification messages survive.
// Concurrent AJAX requests from the same browser session still benefit from the early release.
// Relative to the end of the request.
\core\session\manager::write_close();
