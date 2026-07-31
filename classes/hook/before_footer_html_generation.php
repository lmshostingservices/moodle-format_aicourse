<?php
namespace format_aicourse\hook;

defined('MOODLE_INTERNAL') || die();

use core\hook\output\before_standard_footer_html_generation;

class before_footer_html_generation {
    public static function callback(before_standard_footer_html_generation $hook): void {
        global $COURSE, $PAGE, $CFG, $USER;

        if (empty($COURSE->id) || $COURSE->id <= 1) {
            return;
        }

        if ($COURSE->format !== 'aicourse') {
            return;
        }

        // OPT-ACF-HOOK-PAGETYPE (v1.7.51): Fast early exit for page types that can never
        // need hero injection or tracker JS (admin, login, calendar, home dashboard, etc.).
        // This avoids loading format options from the DB on every page of the site.
        $pagetype = $PAGE->pagetype;
        $isCoursePage = (
            strpos($pagetype, 'course-') === 0 ||
            strpos($pagetype, 'mod-')    === 0 ||
            strpos($pagetype, 'grade-')  === 0 ||
            strpos($pagetype, 'enrol-')  === 0 ||
            strpos($pagetype, 'badges-') === 0 ||
            strpos($pagetype, 'competency-') === 0 ||
            strpos($pagetype, 'report-') === 0 ||
            $pagetype === 'user-index'
        );
        if (!$isCoursePage) {
            return;
        }

        // Check if we're on the main course page (all sections) vs single section view
        $sectionParam = optional_param('section', null, PARAM_INT);
        $sectionIdParam = optional_param('id', 0, PARAM_INT);
        
        // Detect if we're on section.php (uses id param for section ID)
        $isSectionPhp = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/course/section.php') !== false;
        
        // Redirect section.php to course view with section parameter for proper activity cards rendering
        if ($isSectionPhp && !empty($sectionIdParam) && !$PAGE->user_is_editing()) {
            global $DB;
            $section = $DB->get_record('course_sections', array('id' => $sectionIdParam), 'section');
            if ($section) {
                $courseUrl = new \moodle_url('/course/view.php', array(
                    'id' => $COURSE->id,
                    'section' => $section->section
                ));
                $hook->add_html('<script>window.location.replace("' . $courseUrl->out(false) . '");</script>');
                return;
            }
        }
        
        // Skip ONLY the main course view (all sections) - format.php handles that
        // But allow single-section views (section=X parameter OR section.php) - they need hero injection
        if ($PAGE->pagetype === 'course-view-aicourse' && $sectionParam === null && !$isSectionPhp) {
            return;
        }

        // Allow injection on various course-related pages
        $isActivityPage = strpos($PAGE->pagetype, 'mod-') === 0;
        $isSectionPage = $PAGE->pagetype === 'course-section' || 
                         strpos($PAGE->pagetype, 'course-view-section') !== false ||
                         $isSectionPhp ||
                         ($PAGE->pagetype === 'course-view-aicourse' && $sectionParam !== null);
        $isGradesPage = strpos($PAGE->pagetype, 'grade-') === 0;
        $isParticipantsPage = $PAGE->pagetype === 'user-index' || strpos($PAGE->pagetype, 'course-user') === 0;
        $isEnrolPage = strpos($PAGE->pagetype, 'enrol-') === 0;
        $isBadgesPage = strpos($PAGE->pagetype, 'badges-') === 0;
        $isCompetencyPage = strpos($PAGE->pagetype, 'competency-') === 0 || strpos($PAGE->pagetype, 'report-competency') === 0;
        $isReportPage = strpos($PAGE->pagetype, 'report-') === 0;
        
        $allowedPage = $isActivityPage || $isSectionPage || $isGradesPage || 
                       $isParticipantsPage || $isEnrolPage || $isBadgesPage || 
                       $isCompetencyPage || $isReportPage;
        
        if (!$allowedPage) {
            return;
        }

        require_once($CFG->dirroot . '/course/format/aicourse/lib.php');

        $format = course_get_format($COURSE);
        $options = $format->get_format_options();

        // NOTE: Do NOT call $PAGE->add_body_class() here!
        // Body classes added in hooks are unreliable - <body> has already been output.
        // Use JS to add body classes instead.

        // Ensure JS runs on section/activity/grade pages too
        $PAGE->requires->js_call_amd('format_aicourse/courseformat', 'init');
        
        // Check course index visibility setting (bitmask: 1=home, 2=section, 4=activity)
        $courseindexsetting = isset($options['showcourseindex']) ? (int)$options['showcourseindex'] : 7;
        $hideIndex = false;
        
        if ($isSectionPage && ($courseindexsetting & 2) === 0) {
            $hideIndex = true;
        } else if ($isActivityPage && ($courseindexsetting & 4) === 0) {
            $hideIndex = true;
        }
        
        // Add body class via JS if course index should be hidden
        if ($hideIndex && !$PAGE->user_is_editing()) {
            $hook->add_html('<script>document.body.classList.add("aicourse-hideindex");</script>');
        }

        // FIX-GRADER-MULTICAP (v1.7.60): Use format_aicourse_is_grader() — checks
        // grade/report:viewall + manageactivities + viewhiddenactivities.
        // Using only grade/report:viewall was unreliable: sites can strip that cap from
        // non-editing teacher roles. The helper catches all teacher archetypes.
        $coursecontext = \context_course::instance($PAGE->course->id);
        $isGrader = format_aicourse_is_grader($coursecontext, false);

        // Add aicourse-is-grader body class via JS fallback (PHP already adds it in page_set_course,
        // but this covers any edge case where that ran before full auth).
        if ($isGrader) {
            $hook->add_html('<script>document.body.classList.add("aicourse-is-grader");</script>');
        }

        // FIX-ACF-EDITOR-HERO (v1.7.68): Editing teachers and course creators need the
        // hero to reach the AI Generate Banner button. Only skip for non-editing graders
        // (non-editing teacher role) who lack moodle/course:update.
        $canEditCourse = has_capability('moodle/course:update', $coursecontext);
        if ($isGrader && !$canEditCourse) {
            return;
        }

        if (empty($options['showherobanner'])) {
            return;
        }

        // Get the current course module for activity-specific hero banner
        $cm = null;
        $sectionnum = null;
        
        if ($PAGE->context && $PAGE->context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id('', $PAGE->context->instanceid);
        }
        
        // Get section number for section pages
        if ($isSectionPage) {
            // First check for section= parameter (used on course view with single section)
            if ($sectionParam !== null) {
                $sectionnum = $sectionParam;
            } else if ($sectionIdParam) {
                // Fallback to id= parameter for course-section pagetype
                global $DB;
                $section = $DB->get_record('course_sections', array('id' => $sectionIdParam), 'section');
                if ($section) {
                    $sectionnum = $section->section;
                }
            }
        }

        // Use activity-specific hero banner on activity pages
        if ($cm) {
            $html = format_aicourse_render_activity_hero_banner($COURSE, $options, $cm);
        } else {
            $html = format_aicourse_render_hero_banner($COURSE, $options, $sectionnum);
        }

        $script = '<script>
        (function() {
            function injectHero() {
                // Prevent duplicate injection
                if (document.querySelector(".aicourse-hero-sticky-wrap") || 
                    document.querySelector("[data-aicourse-hero]")) return;
                
                var heroHTML = ' . json_encode($html) . ';
                var wrapper = document.createElement("div");
                wrapper.innerHTML = heroHTML;
                if (!wrapper.firstElementChild) return;
                
                // Collect all children (hero + chatbox) to maintain order
                var children = [];
                while (wrapper.firstElementChild) {
                    children.push(wrapper.firstElementChild);
                    wrapper.removeChild(wrapper.firstElementChild);
                }
                
                // Insert at start of #region-main for consistent positioning
                var regionMain = document.getElementById("region-main");
                var target = regionMain || document.getElementById("page-header") ||
                             document.querySelector("main") ||
                             document.querySelector("#page-content");
                
                if (target) {
                    // Insert in REVERSE order at firstChild so they end up in original order
                    for (var i = children.length - 1; i >= 0; i--) {
                        var child = children[i];
                        // Add data attribute to hero for reliable detection
                        if (child.classList && child.classList.contains("aicourse-hero-sticky-wrap")) {
                            child.setAttribute("data-aicourse-hero", "1");
                        }
                        if (regionMain) {
                            regionMain.insertBefore(child, regionMain.firstChild);
                        } else if (target.parentNode) {
                            target.parentNode.insertBefore(child, target.nextSibling);
                        }
                    }
                }
            }
            
            // Run immediately if DOM ready, otherwise wait
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", injectHero);
            } else {
                injectHero();
            }
        })();
        </script>';

        $hook->add_html($script);
        
        // Add chatbox script separately (innerHTML doesn't execute scripts)
        // BUT: Skip for section pages in course view - format.php already outputs the script
        $isCourseViewSection = ($PAGE->pagetype === 'course-view-aicourse' && $sectionParam !== null);
        if (!$isCourseViewSection) {
            $chatboxScript = format_aicourse_render_ai_chatbox_script($COURSE);
            $hook->add_html($chatboxScript);
        }
    }
}
