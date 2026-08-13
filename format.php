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
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/course/format/aicourse/lib.php');

try {
    $format = course_get_format($course);
    $course = $format->get_course();
    $context = context_course::instance($course->id);
} catch (Throwable $e) {
    debugging('AI Course Format Init Error: ' . $e->getMessage(), DEBUG_DEVELOPER);
}

if (($marker >= 0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    if (method_exists($format, 'set_sectionnum')) {
        $format->set_sectionnum($marker);
    } else {
        $format->set_section_number($marker);
    }
}

// Check for section parameter - use isset and !== null to handle section 0 correctly
// PHP's empty() treats 0 as empty, but section 0 is valid
$hassection = isset($displaysection) && $displaysection !== '' && $displaysection !== null;
if ($hassection) {
    if (method_exists($format, 'set_sectionnum')) {
        $format->set_sectionnum($displaysection);
    } else {
        $format->set_section_number($displaysection);
    }
}

// OPT-ACF-WRITE-CLOSE (v1.7.51): Release the Moodle session lock before the
// rendering phase. All session-dependent work (require_login, confirm_sesskey,
// set_sectionnum) is already done above. Releasing early allows concurrent AJAX
// and resource requests from the same browser session to proceed without blocking.
\core\session\manager::write_close();

$options = $format->get_format_options();

$bodyclasses = array();
if (!empty($options['displayascards']) && !$hassection) {
    $bodyclasses[] = 'aicourse-cardview';
}
// Hide course index based on current page type (bitmask: 1=home, 2=section, 4=activity)
$courseindexsetting = isset($options['showcourseindex']) ? (int)$options['showcourseindex'] : 7;
if ($hassection) {
    $showIndex = ($courseindexsetting & 2) !== 0;  // bit 1 = section pages
} else {
    $showIndex = ($courseindexsetting & 1) !== 0;  // bit 0 = home/course page
}
if (!$showIndex && !$PAGE->user_is_editing()) {
    $bodyclasses[] = 'aicourse-hideindex';
}
if ($hassection && !empty($options['activitydisplaymode'])) {
    $bodyclasses[] = 'aicourse-activitycards';
}
if (!empty($bodyclasses)) {
    $classesjs = json_encode($bodyclasses);
    echo '<script>(function (){var c=' . $classesjs . ';for(var i=0;i<c.length;i++){document.body.classList.add(c[i]);}})();</script>';
}
// Inject card title size CSS variable
$cardtitlesize = isset($options['cardtitlesize']) ? (int)$options['cardtitlesize'] : 14;
if ($cardtitlesize > 0) {
    echo '<style>body.format-aicourse{--aicourse-card-title-size:' . $cardtitlesize . 'px;}</style>';
}

// Render hero OUTSIDE the container so it's not constrained by max-width
// FIX-ACF-EDITOR-HERO (v1.7.68): Editing teachers and course creators need the hero
// to access the AI Generate Banner button. Skip for read-only graders (non-editing
// teacher role) who lack moodle/course:update, but always render for editors.
$_acf_isGrader = format_aicourse_is_grader($context, true);
$_acf_canEdit  = has_capability('moodle/course:update', $context);
if (!empty($options['showherobanner']) && (!$_acf_isGrader || $_acf_canEdit)) {
    $sectionnum = $hassection ? $displaysection : null;
    echo format_aicourse_render_hero_banner($course, $options, $sectionnum);
    // Output chatbox script (HTML is included in hero banner)
    echo format_aicourse_render_ai_chatbox_script($course);
}

echo '<div class="format-aicourse-container">';

// Section description: shown on section page between hero banner and activity list (v1.7.41)
if ($hassection && !$PAGE->user_is_editing()) {
    try {
        $allsections = get_fast_modinfo($course)->get_section_info_all();
        if (isset($allsections[$displaysection])) {
            $sectioninfo = $allsections[$displaysection];
            if (!empty($sectioninfo->summary)) {
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
    // In edit mode always use the standard renderer so teachers get drag handles,
    // "Add an activity or resource" links, and all normal Moodle edit controls.
    if (!empty($options['activitydisplaymode']) && !$PAGE->user_is_editing()) {
        echo format_aicourse_render_activity_cards($course, $displaysection, $options);
    } else {
        $renderer = $PAGE->get_renderer('format_aicourse');
        $outputclass = $format->get_output_classname('content');
        $widget = new $outputclass($format);
        echo $renderer->render($widget);
    }
} else {
    // Always show the custom card view on the course home page.
    // Edit mode no longer falls back to the Topics accordion — that caused the entire
    // card layout to disappear when a teacher clicked Edit mode (UX-ACF-EDITMODE-WIPE).
    // Card-level edit actions (icon, rename, delete, duplicate, add section) are now
    // available directly on the cards regardless of edit mode state.
    if (!empty($options['displayascards'])) {
        echo format_aicourse_render_section_cards($course, $options);
    } else {
        $renderer = $PAGE->get_renderer('format_aicourse');
        $outputclass = $format->get_output_classname('content');
        $widget = new $outputclass($format);
        echo $renderer->render($widget);
    }
}

echo '</div>';

echo '</div>';

// Initialize AMD module for icon picker and progress animations
$PAGE->requires->js_call_amd('format_aicourse/courseformat', 'init');
