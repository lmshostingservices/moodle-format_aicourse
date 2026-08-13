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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/format/topics/lib.php');
require_once($CFG->libdir . '/completionlib.php');

class format_aicourse extends format_topics {
    /**
     * Called on EVERY page where this course format is relevant.
     * Only adds body classes - hero injection is handled separately via CSS + JS.
     * Kept minimal to avoid PHP fatal errors from function calls too early.
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
    public function page_set_course(\moodle_page $page) {
        parent::page_set_course($page);
        
        // Add format body class for CSS selectors
        $page->add_body_class('format-aicourse');
        $page->add_body_class('aicourse-clean-view');
        
        // FIX-GRADER-PHPCLASS (v1.7.56): Add aicourse-is-grader via PHP so CSS rules fire
        // immediately without FOUC. Covers teachers and non-editing teachers (grade/report:viewall).
        // isloggedin() guard keeps this safe during early page setup before full auth.
        if (isloggedin() && !isguestuser()) {
            try {
                $coursecontext = \context_course::instance($page->course->id);
                if (format_aicourse_is_grader($coursecontext)) {
                    $page->add_body_class('aicourse-is-grader');
                }
            } catch (\Throwable $e) {
                // Context not ready yet — hook JS fallback will handle it
            }
        }
        
        // Moodle 4.0-4.2 fallback: Hook API doesn't exist, so enqueue JS fallback
        // The hero HTML will be set via extend_navigation_course() which runs later
        if (!class_exists('\core\hook\output\before_standard_footer_html_generation')) {
            $page->requires->js_call_amd('format_aicourse/courseformat', 'injectHeroFallback');
        }
    }

    public function get_default_blocks() {
        return array(
            BLOCK_POS_LEFT => array(),
            BLOCK_POS_RIGHT => array()
        );
    }

    public function get_default_section_name($section) {
        if ($section->section == 0) {
            return get_string('section0name', 'format_aicourse');
        }
        return get_string('sectionname', 'format_aicourse') . ' ' . $section->section;
    }

    public function course_format_options($foreditform = false) {
        $options = parent::course_format_options($foreditform);
        
        $courseformatoptions = array(
            'showherobanner' => array(
                'default' => 1,
                'type' => PARAM_INT,
            ),
            'shownavchevrons' => array(
                'default' => 1,
                'type' => PARAM_INT,
            ),
            'herobannerheight' => array(
                'default' => 180,
                'type' => PARAM_INT,
            ),
            'showcourseindex' => array(
                'default' => 7,
                'type' => PARAM_INT,
            ),
            'displayascards' => array(
                'default' => 1,
                'type' => PARAM_INT,
            ),
            'activitydisplaymode' => array(
                'default' => 1,
                'type' => PARAM_INT,
            ),
            'herobannerwidth' => array(
                'default' => 0,
                'type' => PARAM_INT,
            ),
            'herobanneralign' => array(
                'default' => 0,
                'type' => PARAM_INT,
            ),
            'cardtitlesize' => array(
                'default' => 14,
                'type' => PARAM_INT,
            ),
        );

        if ($foreditform) {
            $optionsedit = array(
                'showherobanner' => array(
                    'label' => get_string('showherobanner', 'format_aicourse'),
                    'help' => 'showherobanner',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => get_string('no'),
                            1 => get_string('yes'),
                        )
                    ),
                ),
                'shownavchevrons' => array(
                    'label' => get_string('shownavchevrons', 'format_aicourse'),
                    'help' => 'shownavchevrons',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => get_string('no'),
                            1 => get_string('yes'),
                        )
                    ),
                ),
                'herobannerheight' => array(
                    'label' => get_string('herobannerheight', 'format_aicourse'),
                    'help' => 'herobannerheight',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ),
                'showcourseindex' => array(
                    'label' => get_string('showcourseindex', 'format_aicourse'),
                    'help' => 'showcourseindex',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => get_string('courseindex_none', 'format_aicourse'),
                            1 => get_string('courseindex_home', 'format_aicourse'),
                            2 => get_string('courseindex_section', 'format_aicourse'),
                            3 => get_string('courseindex_home_section', 'format_aicourse'),
                            4 => get_string('courseindex_activity', 'format_aicourse'),
                            5 => get_string('courseindex_home_activity', 'format_aicourse'),
                            6 => get_string('courseindex_section_activity', 'format_aicourse'),
                            7 => get_string('courseindex_all', 'format_aicourse'),
                        )
                    ),
                ),
                'displayascards' => array(
                    'label' => get_string('displayascards', 'format_aicourse'),
                    'help' => 'displayascards',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => get_string('displayassections', 'format_aicourse'),
                            1 => get_string('displayascardsoption', 'format_aicourse'),
                        )
                    ),
                ),
                'activitydisplaymode' => array(
                    'label' => get_string('activitydisplaymode', 'format_aicourse'),
                    'help' => 'activitydisplaymode',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => get_string('activitydisplaystandard', 'format_aicourse'),
                            1 => get_string('activitydisplaycards', 'format_aicourse'),
                        )
                    ),
                ),
                'herobannerwidth' => array(
                    'label' => get_string('herobannerwidth', 'format_aicourse'),
                    'help' => 'herobannerwidth',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ),
                'herobanneralign' => array(
                    'label' => get_string('herobanneralign', 'format_aicourse'),
                    'help' => 'herobanneralign',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => get_string('herobanneralign_center', 'format_aicourse'),
                            1 => get_string('herobanneralign_left', 'format_aicourse'),
                        )
                    ),
                ),
                'cardtitlesize' => array(
                    'label' => get_string('cardtitlesize', 'format_aicourse'),
                    'help' => 'cardtitlesize',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ),
            );
            $courseformatoptions = array_merge_recursive($courseformatoptions, $optionsedit);
        }

        return array_merge($options, $courseformatoptions);
    }

    public function supports_components() {
        return true;
    }

    public function uses_course_index() {
        return true;
    }

    public function uses_sections() {
        return true;
    }

    public function uses_indentation(): bool {
        return true;
    }

    /**
     * Add the custom banner image filemanager to the course edit form.
     * Only shown at course level (not section level).
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        $elements = parent::create_edit_form_elements($mform, $forsection);

        if (!$forsection) {
            // ── Banner Image section ──────────────────────────────────────
            // IMPORTANT: $mform->addElement() returns the element object. We MUST capture it
            // and append the object (not the name string) to $elements. Moodle core's
            // course_edit_form::definition_after_data() iterates $elements and calls
            // ->getName() on each entry — appending strings instead of objects causes a fatal
            // "Call to a member function getName() on string" exception (course/edit_form.php ~504).
            $elements[] = $mform->addElement(
                'header',
                'bannerimageheader',
                get_string('bannerimageheader', 'format_aicourse')
            );
            $mform->setExpanded('bannerimageheader');

            // Ratio guidance notice shown inside the form.
            $guidancehtml =
                '<div class="alert alert-info aicourse-banner-guidance" role="note">' .
                '<p class="mb-1"><strong>' . get_string('bannerimage_ratio_title', 'format_aicourse') . '</strong> ' .
                get_string('bannerimage_ratio_hint', 'format_aicourse') . '</p>' .
                '<p class="mb-0 small text-muted">' . get_string('bannerimage_ratio_formats', 'format_aicourse') . '</p>' .
                '</div>';
            $elements[] = $mform->addElement('static', 'bannerimage_guidance', '', $guidancehtml);

            // File manager — one image, 5 MB max, web-safe formats only.
            $fileopts = [
                'maxbytes'       => 5 * 1024 * 1024,
                'maxfiles'       => 1,
                'subdirs'        => 0,
                'accepted_types' => ['.jpg', '.jpeg', '.png', '.webp'],
            ];
            $elements[] = $mform->addElement(
                'filemanager',
                'bannerimage',
                get_string('bannerimage', 'format_aicourse'),
                null,
                $fileopts
            );
            $mform->addHelpButton('bannerimage', 'bannerimage', 'format_aicourse');
        }

        return $elements;
    }

    /**
     * Populate the banner image filemanager with the draft area when the edit form opens.
     */
    public function set_edit_form_data($data) {
        parent::set_edit_form_data($data);

        $course = $this->get_course();
        if (!$course) {
            return;
        }

        $context    = context_course::instance($course->id);
        $draftitemid = 0;
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'format_aicourse',
            'bannerimage',
            $course->id,
            ['maxbytes' => 5 * 1024 * 1024, 'maxfiles' => 1, 'subdirs' => 0]
        );
        $data->bannerimage = $draftitemid;
    }

    /**
     * Save the banner image and update the standard format options.
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $course = $this->get_course();

        if ($course && isset($data->bannerimage)) {
            $context = context_course::instance($course->id);
            file_save_draft_area_files(
                (int) $data->bannerimage,
                $context->id,
                'format_aicourse',
                'bannerimage',
                $course->id,
                ['maxbytes' => 5 * 1024 * 1024, 'maxfiles' => 1, 'subdirs' => 0]
            );
            // Prevent parent from storing the draftitemid in course_format_options.
            unset($data->bannerimage);
        }

        return parent::update_course_format_options($data, $oldcourse);
    }
}

/**
 * Serve banner image files from the format_aicourse component.
 *
 * URL pattern: /pluginfile.php/{contextid}/format_aicourse/bannerimage/{courseid}/{filename}
 */
/**
 * FIX-GRADER-ARCHETYPE (v1.7.62): Centralised grader detection using role archetypes.
 *
 * Capability checks alone are unreliable — Moodle sites frequently customise roles and
 * strip capabilities from non-editing teachers. The ONLY truly reliable test is checking
 * the role archetype stored in the {role} table, which is set at role creation and is
 * almost never changed by site admins.
 *
 * Moodle archetypes for teacher-type roles:
 *   editingteacher  — Teacher (can edit course)
 *   teacher         — Non-editing teacher
 *   manager         — Manager
 *   coursecreator   — Course creator
 *
 * We also include a capability fallback for custom roles that may have teacher-like
 * permissions but a non-standard archetype (e.g., archetype='student' on a custom role).
 *
 * @param context_course $context  Course context to check against.
 * @param bool           $diag     When true, emits a console.log for debugging.
 * @return bool True if the current user should be treated as a grader/teacher.
 */
function format_aicourse_is_grader($context, $diag = false) {
    global $USER, $DB;

    $graderArchetypes = ['teacher', 'editingteacher', 'manager', 'coursecreator'];

    // PRIMARY: role archetype check — works with role switches and any capability config.
    // get_user_roles() respects $SESSION->role_switch so this correctly handles the
    // "Switch role to..." scenario that trips up has_capability() based checks.
    $roles = get_user_roles($context, $USER->id, true);
    $archetypeFound = '';

    if (!empty($roles)) {
        $roleids = [];
        foreach ($roles as $role) {
            $roleids[$role->roleid] = $role->roleid;
        }
        $roleids = array_values($roleids);

        [$insql, $inparams] = $DB->get_in_or_equal($roleids);
        $recs = $DB->get_records_sql("SELECT id, archetype FROM {role} WHERE id $insql", $inparams);

        foreach ($recs as $rec) {
            if (in_array($rec->archetype, $graderArchetypes, true)) {
                $archetypeFound = $rec->archetype;
                if ($diag) {
                    echo '<script>console.log("[aicourse] grader: archetype=" + ' . json_encode($rec->archetype) . ');</script>';
                }
                return true;
            }
        }
    }

    // FALLBACK: capability check for custom roles with teacher-like perms but non-standard archetype.
    $cap1 = has_capability('moodle/grade:viewall',               $context, null, false);
    $cap2 = has_capability('moodle/course:manageactivities',     $context, null, false);
    $cap3 = has_capability('moodle/course:viewhiddenactivities', $context, null, false);
    $result = $cap1 || $cap2 || $cap3;

    if ($diag) {
        $archetypeList = [];
        foreach (($recs ?? []) as $rec) {
            $archetypeList[] = $rec->archetype;
        }
        $data = json_encode([
            'role_archetypes'                   => $archetypeList,
            'moodle/grade:viewall'              => $cap1,
            'moodle/course:manageactivities'    => $cap2,
            'moodle/course:viewhiddenactivities' => $cap3,
            'isGrader'                          => $result,
        ]);
        echo '<script>console.log("[aicourse] grader check:", ' . $data . ');</script>';
    }

    return $result;
}

function format_aicourse_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel != CONTEXT_COURSE) {
        send_file_not_found();
    }

    if ($filearea !== 'bannerimage') {
        send_file_not_found();
    }

    require_login($course);

    $itemid   = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs   = get_file_storage();
    $file = $fs->get_file($context->id, 'format_aicourse', 'bannerimage', $itemid, $filepath, $filename);

    if (!$file) {
        send_file_not_found();
    }

    // 24-hour browser cache; no force-download (these are always displayed inline).
    send_stored_file($file, 86400, 0, false, $options);
}

function format_aicourse_render_hero_banner($course, $options, $sectionnum = null) {
    global $CFG, $USER, $PAGE;
    
    $height = isset($options['herobannerheight']) ? (int)$options['herobannerheight'] : 180;

    // Custom banner image takes priority; fall back to course overview image.
    // Track custom banner separately so we can show the delete button only for custom images.
    $custombanner = format_aicourse_get_banner_image_url($course);
    $imageurl = $custombanner;
    if (!$imageurl) {
        $imageurl = format_aicourse_get_course_image($course);
    }
    
    // Get section info first if viewing a section
    $modinfo = get_fast_modinfo($course, $USER->id);
    $sectioninfo = null;
    $titletext = format_string($course->fullname);
    
    if ($sectionnum !== null) {
        $sectioninfo = $modinfo->get_section_info($sectionnum);
        if ($sectioninfo) {
            $titletext = get_section_name($course, $sectioninfo);
        }
    }
    
    // Use section-specific progress when viewing a section, otherwise course progress.
    // Create one shared completion_info here so both get_section_progress() and
    // get_progress() reuse the same in-memory bulk-loaded completion data.
    $completioninfo = new completion_info($course);

    if ($sectioninfo !== null) {
        $progressdata = format_aicourse_get_section_progress($course, $sectioninfo, $USER->id, $completioninfo);
        // FIX-ACF-NAVCHEVRONS (v1.7.48): Only build nav links when the setting is enabled.
        // Previously the shownavchevrons option was stored but never consulted here, so the
        // arrows always appeared even when the teacher set "Show navigation chevrons = No".
        if (!empty($options['shownavchevrons'])) {
            $navdata = format_aicourse_get_section_nav_links($course, $sectionnum, $modinfo);
        } else {
            $navdata = array('prev' => null, 'next' => null);
        }
    } else {
        $progressdata = format_aicourse_get_progress($course, $USER->id, $completioninfo);
        // Course home has no prev/next navigation
        $navdata = array('prev' => null, 'next' => null);
    }
    
    $bannerwidth = isset($options['herobannerwidth']) ? (int)$options['herobannerwidth'] : 0;
    $banneralign = isset($options['herobanneralign']) ? (int)$options['herobanneralign'] : 0;
    $widthstyle = ($bannerwidth > 0) ? 'max-width:' . $bannerwidth . 'px;' : '';
    $alignclass = ($banneralign === 1) ? ' aicourse-hero-align-left' : '';

    // Image mode: adds class to trigger tall immersive CSS; skips max-height constraint.
    $imageclass  = $imageurl ? ' aicourse-hero-has-image' : '';
    $heightstyle = $imageurl ? '' : 'max-height: ' . $height . 'px;';

    $html = '<div class="aicourse-hero-sticky-wrap' . $alignclass . '" data-aicourse-hero="1" style="' . $widthstyle . '">';
    // Background-image lives in a dedicated child div so CSS hover-zoom only scales the image.
    $html .= '<div class="aicourse-hero-banner' . $imageclass . '" style="' . $heightstyle . '" data-height="' . $height . '">';
    if ($imageurl) {
        $html .= '<div class="aicourse-hero-bg-img" style="background-image: url(\'' . s($imageurl) . '\');"></div>';
    }
    $html .= '<div class="aicourse-hero-overlay"></div>';
    $html .= '<div class="aicourse-hero-content">';
    
    // Title only — no shortname label pill, no book icon.
    $html .= '<div class="aicourse-hero-title-wrap">';
    $html .= '<span class="aicourse-hero-title">' . $titletext . '</span>';
    $html .= '</div>';

    // In image mode, show course summary below the title on the course home page.
    // CSS handles the 2-line visual clamp; PHP just strips HTML tags.
    if ($imageurl && $sectioninfo === null) {
        $summaryclean = trim(strip_tags($course->summary));
        if (!empty($summaryclean)) {
            $html .= '<p class="aicourse-hero-summary">' . s($summaryclean) . '</p>';
        }
    }

    // Navigation and progress container (Previous | Progress+Dots | Next)
    $html .= '<div class="aicourse-hero-nav-progress">';
    
    // Previous button (only if exists)
    if (!empty($navdata['prev'])) {
        $html .= '<a href="' . $navdata['prev']['url'] . '" class="aicourse-hero-nav aicourse-hero-nav-prev" title="' . s($navdata['prev']['name']) . '">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>';
        $html .= '</a>';
    } else {
        $html .= '<div class="aicourse-hero-nav-spacer"></div>';
    }
    
    if ($progressdata['enabled']) {
        $percentage = (int) $progressdata['percentage'];
        $radius = 90;
        $strokewidth = 12;
        $circumference = 2 * M_PI * $radius;
        $offset = $circumference - ($percentage / 100) * $circumference;
        $completedclass = ($percentage >= 100) ? ' completed' : '';
        
        $html .= '<div class="aicourse-hero-progress">';
        
        // Progress ring with animated percentage (starts at 0, animates to target)
        // Using 200x200 viewBox for maximum smoothness at small display sizes
        $html .= '<div class="aicourse-progress-ring-container' . $completedclass . '" data-percentage="' . $percentage . '" data-courseid="' . $course->id . '" data-radius="' . $radius . '">';
        $html .= '<svg class="aicourse-progress-ring" viewBox="0 0 200 200" style="transform: rotate(-90deg);">';
        $html .= '<circle class="aicourse-progress-ring-bg" cx="100" cy="100" r="' . $radius . '" fill="none" stroke="rgba(0,0,0,0.1)" stroke-width="' . $strokewidth . '"/>';
        // Start with full circumference (0% visible), JS animates to target
        $html .= '<circle class="aicourse-progress-ring-fill" cx="100" cy="100" r="' . $radius . '" fill="none" stroke="#357a32" stroke-width="' . $strokewidth . '" stroke-linecap="round" stroke-dasharray="' . $circumference . '" stroke-dashoffset="' . $circumference . '" data-target-offset="' . $offset . '"/>';
        $html .= '</svg>';
        if ($percentage >= 100) {
            // Show checkmark icon when complete
            $html .= '<span class="aicourse-progress-ring-text aicourse-progress-complete" data-target="' . $percentage . '">';
            $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
            $html .= '</span>';
        } else {
            $html .= '<span class="aicourse-progress-ring-text" data-target="' . $percentage . '">0%</span>';
        }
        $html .= '</div>';
        
        // Progress bar for course home; numbered activity circles for section view
        $html .= '<div class="aicourse-progress-bar-container">';
        if ($sectioninfo !== null) {
            // Section view: numbered circles — one per visible activity in this section.
            // White = not started, amber = in progress, green = completed,
            // dimmed white = no completion tracking on this activity.
            $sectioncmids = isset($modinfo->sections[$sectioninfo->section]) ? $modinfo->sections[$sectioninfo->section] : array();
            // Build a quick lookup of cmid → status from the progress data
            $actstatusmap = array();
            foreach ($progressdata['activities'] as $act) {
                $actstatusmap[$act['id']] = $act['status'];
            }
            $circlehtml = '';
            $actnum = 1;
            foreach ($sectioncmids as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if (!$cm->uservisible) {
                    continue;
                }
                $status    = isset($actstatusmap[$cmid]) ? $actstatusmap[$cmid] : 'no_completion';
                $statuscls = 'aicourse-number-' . $status;
                $tooltip   = s(format_string($cm->name));
                $url       = ($cm->url) ? s($cm->url->out()) : '';
                if ($url) {
                    $circlehtml .= '<a href="' . $url . '" class="aicourse-activity-number ' . $statuscls . '" title="' . $tooltip . '">' . $actnum . '</a>';
                } else {
                    $circlehtml .= '<span class="aicourse-activity-number ' . $statuscls . '" title="' . $tooltip . '">' . $actnum . '</span>';
                }
                $actnum++;
            }
            if ($circlehtml !== '') {
                $html .= '<div class="aicourse-activity-number-circles">' . $circlehtml . '</div>';
            }
        } else {
            // Course home: show animated progress bar
            $html .= '<div class="aicourse-progress-bar-track">';
            $html .= '<div class="aicourse-progress-bar-fill" data-percentage="' . $percentage . '" style="width: 0%;"></div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
    }
    
    // Next button (only if exists)
    if (!empty($navdata['next'])) {
        $html .= '<a href="' . $navdata['next']['url'] . '" class="aicourse-hero-nav aicourse-hero-nav-next" title="' . s($navdata['next']['name']) . '">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';
        $html .= '</a>';
    } else {
        $html .= '<div class="aicourse-hero-nav-spacer"></div>';
    }
    
    $html .= '</div>'; // End nav-progress container
    
    // Icons container (grades + AI assistant + home)
    $html .= '<div class="aicourse-hero-icons">';
    
    // FIX-GRADES-LINK (v1.7.54): Teachers (grade/report:viewall) go to the grader report;
    // everyone else goes to their own user grade report.
    $coursecontext_hero = context_course::instance($course->id);
    if (has_capability('moodle/grade:viewall', $coursecontext_hero, null, false)) {
        $gradesurl = new moodle_url('/grade/report/grader/index.php', array('id' => $course->id));
    } else {
        $gradesurl = new moodle_url('/grade/report/user/index.php', array('id' => $course->id));
    }
    $html .= '<a href="' . $gradesurl->out() . '" class="aicourse-hero-grades" title="' . get_string('grades', 'format_aicourse') . '">';
    $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>';
    $html .= '</a>';
    
    // AI Course Assistant button - star icon for AI
    if (format_aicourse_is_tutor_enabled()) {
        $html .= '<button type="button" class="aicourse-hero-ai-btn aicourse-ai-toggle" title="' . get_string('aiassistant', 'format_aicourse') . '" data-courseid="' . $course->id . '" data-sesskey="' . sesskey() . '">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .962 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.582a.5.5 0 0 1 0 .962L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.962 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/></svg>';
        $html .= '</button>';
    }
    
    // Home button
    $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
    $html .= '<a href="' . $courseurl->out() . '" class="aicourse-hero-home" title="' . get_string('gotocourse', 'format_aicourse') . '">';
    $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>';
    $html .= '</a>';

    // AI Generate Banner button — editors only
    if (has_capability('moodle/course:update', context_course::instance($course->id))) {
        // Delete banner button — only shown when a custom banner image exists AND Moodle is in edit mode
        if ($custombanner && $PAGE->user_is_editing()) {
            $html .= '<button type="button" class="aicourse-hero-ai-btn aicourse-ai-delete-banner" '
                . 'title="Remove Banner Image" '
                . 'data-courseid="' . $course->id . '" '
                . 'data-sesskey="' . sesskey() . '">';
            $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                . '<polyline points="3 6 5 6 21 6"/>'
                . '<path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>'
                . '<path d="M10 11v6"/><path d="M14 11v6"/>'
                . '<path d="M9 6V4h6v2"/>'
                . '</svg>';
            $html .= '</button>';
        }
        $html .= '<button type="button" class="aicourse-hero-ai-btn aicourse-ai-generate-banner" '
            . 'title="Generate AI Banner Image" '
            . 'data-courseid="' . $course->id . '" '
            . 'data-coursename="' . s(format_string($course->fullname)) . '" '
            . 'data-shortname="' . s($course->shortname) . '" '
            . 'data-sesskey="' . sesskey() . '">';
        // Wand + sparkles icon
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M15 4V2"/><path d="M15 16v-2"/><path d="M8 9h2"/><path d="M20 9h2"/>'
            . '<path d="M17.8 11.8 19 13"/><path d="M15 9h.01"/><path d="M17.8 6.2 19 5"/>'
            . '<path d="m3 21 9-9"/><path d="M12.2 6.2 11 5"/>'
            . '</svg>';
        $html .= '</button>';
    }
    
    $html .= '</div>'; // End icons container
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Add AI Assistant chatbox modal
    // Chatbox HTML only - script added separately to ensure execution
    $html .= format_aicourse_render_ai_chatbox_html();
    
    return $html;
}

/**
 * Render hero banner for activity pages showing completion requirements
 */
function format_aicourse_render_activity_hero_banner($course, $options, $cm) {
    global $CFG, $USER, $PAGE, $OUTPUT;
    
    $height = isset($options['herobannerheight']) ? (int)$options['herobannerheight'] : 180;

    // Custom banner image takes priority; fall back to course overview image.
    // Track custom banner separately so we can show the delete button only for custom images.
    $custombanner = format_aicourse_get_banner_image_url($course);
    $imageurl = $custombanner;
    if (!$imageurl) {
        $imageurl = format_aicourse_get_course_image($course);
    }

    $navdata = format_aicourse_get_nav_links($course, $USER->id);
    $currentsection = format_aicourse_get_current_section($course, $USER->id);
    
    // Get completion info for this activity
    $completioninfo = format_aicourse_get_activity_completion_info($course, $cm, $USER->id);
    
    // Get the activity icon URL
    $modinfo = get_fast_modinfo($course);
    $cminfo = $modinfo->get_cm($cm->id);
    $iconurl = $cminfo->get_icon_url()->out(false);
    
    $bannerwidth = isset($options['herobannerwidth']) ? (int)$options['herobannerwidth'] : 0;
    $banneralign = isset($options['herobanneralign']) ? (int)$options['herobanneralign'] : 0;
    $widthstyle = ($bannerwidth > 0) ? 'max-width:' . $bannerwidth . 'px;' : '';
    $alignclass = ($banneralign === 1) ? ' aicourse-hero-align-left' : '';

    // Image mode: adds class to trigger tall immersive CSS; skips max-height constraint.
    $imageclass  = $imageurl ? ' aicourse-hero-has-image' : '';
    $heightstyle = $imageurl ? '' : 'max-height: ' . $height . 'px;';

    $html = '<div class="aicourse-hero-sticky-wrap' . $alignclass . '" data-aicourse-hero="1" style="' . $widthstyle . '">';
    // Background-image lives in a dedicated child div so CSS hover-zoom only scales the image.
    $html .= '<div class="aicourse-hero-banner' . $imageclass . '" style="' . $heightstyle . '" data-height="' . $height . '">';
    if ($imageurl) {
        $html .= '<div class="aicourse-hero-bg-img" style="background-image: url(\'' . s($imageurl) . '\');"></div>';
    }
    $html .= '<div class="aicourse-hero-overlay"></div>';
    $html .= '<div class="aicourse-hero-content">';
    
    // Section label + activity title combined in one column
    $html .= '<div class="aicourse-hero-title-group">';
    if (!empty($currentsection)) {
        $sectionname = !empty($currentsection['name']) ? $currentsection['name'] : get_string('sectionname', 'format_aicourse') . ' ' . $currentsection['num'];
        $html .= '<span class="aicourse-hero-section-label">' . format_string($sectionname) . '</span>';
    }
    $html .= '<div class="aicourse-hero-title-wrap">';
    $html .= '<span class="aicourse-hero-title">' . format_string($cm->name) . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Navigation and completion container
    $html .= '<div class="aicourse-hero-nav-progress">';
    
    // Previous button
    if (!empty($navdata['prev'])) {
        $html .= '<a href="' . $navdata['prev']['url'] . '" class="aicourse-hero-nav aicourse-hero-nav-prev" title="' . s($navdata['prev']['name']) . '">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>';
        $html .= '</a>';
    } else {
        $html .= '<div class="aicourse-hero-nav-spacer"></div>';
    }
    
    // Completion status display
    $html .= '<div class="aicourse-hero-progress">';
    
    // Completion tick circle (clickable for manual completion)
    $ismanual = !empty($completioninfo['ismanual']);
    $cmid = !empty($completioninfo['cmid']) ? $completioninfo['cmid'] : 0;
    $hascompletion = !empty($completioninfo['hascompletion']);
    
    if ($ismanual && $hascompletion) {
        // Manual completion - make it a clickable button
        $toggleclass = $completioninfo['completed'] ? 'aicourse-hero-completion-done' : 'aicourse-hero-completion-pending';
        $html .= '<button type="button" class="aicourse-hero-completion-toggle ' . $toggleclass . '" data-cmid="' . $cmid . '" data-completed="' . ($completioninfo['completed'] ? '1' : '0') . '" title="' . get_string('completionrequirement_manual', 'format_aicourse') . '">';
        $html .= '<div class="aicourse-completion-ring-container">';
        if ($completioninfo['completed']) {
            $html .= '<svg class="aicourse-completion-ring aicourse-completion-done" viewBox="0 0 50 50">';
            $html .= '<circle cx="25" cy="25" r="20" fill="none" stroke="#357a32" stroke-width="2.5"/>';
            $html .= '<path d="M17 25 L22 30 L33 19" fill="none" stroke="#357a32" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';
            $html .= '</svg>';
        } else {
            $html .= '<svg class="aicourse-completion-ring aicourse-completion-pending" viewBox="0 0 50 50">';
            $html .= '<circle cx="25" cy="25" r="20" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="2.5"/>';
            $html .= '<path d="M17 25 L22 30 L33 19" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';
            $html .= '</svg>';
        }
        $html .= '</div>';
        $html .= '</button>';
    } else {
        // Auto completion or no completion - display only
        $html .= '<div class="aicourse-completion-ring-container">';
        if ($completioninfo['completed']) {
            // Completed - show green tick
            $html .= '<svg class="aicourse-completion-ring aicourse-completion-done" viewBox="0 0 50 50">';
            $html .= '<circle cx="25" cy="25" r="20" fill="none" stroke="#357a32" stroke-width="2.5"/>';
            $html .= '<path d="M17 25 L22 30 L33 19" fill="none" stroke="#357a32" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';
            $html .= '</svg>';
        } else if (!empty($completioninfo['requiresgrade']) && !empty($completioninfo['gradetext'])) {
            // Requires grade but not passed - show current grade instead of grey tick
            $html .= '<div class="aicourse-grade-display">';
            $html .= '<span class="aicourse-grade-text">' . $completioninfo['gradetext'] . '</span>';
            $html .= '</div>';
        } else {
            // Other pending completion - show empty circle (no tick)
            $html .= '<svg class="aicourse-completion-ring aicourse-completion-pending" viewBox="0 0 50 50">';
            $html .= '<circle cx="25" cy="25" r="20" fill="none" stroke="rgba(255,255,255,0.4)" stroke-width="2.5"/>';
            $html .= '</svg>';
        }
        $html .= '</div>';
    }
    
    // Completion requirements text
    $html .= '<div class="aicourse-completion-requirements">';
    if ($ismanual && $hascompletion) {
        // Show clickable label for manual completion
        $labeltext = $completioninfo['completed'] ? get_string('completed', 'format_aicourse') : get_string('completionrequirement_manual', 'format_aicourse');
        $html .= '<span class="aicourse-completion-label aicourse-completion-clickable">' . $labeltext . '</span>';
    } else if (!empty($completioninfo['requirements'])) {
        $html .= '<span class="aicourse-completion-label">' . implode(' &bull; ', $completioninfo['requirements']) . '</span>';
    } else {
        $html .= '<span class="aicourse-completion-label">' . get_string('nocompletion', 'format_aicourse') . '</span>';
    }
    $html .= '</div>';
    
    $html .= '</div>';
    
    // Next button
    if (!empty($navdata['next'])) {
        $html .= '<a href="' . $navdata['next']['url'] . '" class="aicourse-hero-nav aicourse-hero-nav-next" title="' . s($navdata['next']['name']) . '">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';
        $html .= '</a>';
    } else {
        $html .= '<div class="aicourse-hero-nav-spacer"></div>';
    }
    
    $html .= '</div>'; // End nav-progress container
    
    // Icons container (back to section + grades + AI assistant + home)
    $html .= '<div class="aicourse-hero-icons">';
    
    // Back to section button with section name in tooltip
    if (!empty($currentsection)) {
        $sectionurl = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $currentsection['num']));
        $sectionname = !empty($currentsection['name']) ? $currentsection['name'] : get_string('sectionname', 'format_aicourse') . ' ' . $currentsection['num'];
        $html .= '<a href="' . $sectionurl->out() . '" class="aicourse-hero-back" title="' . get_string('returntosection', 'format_aicourse') . ' - ' . s($sectionname) . '">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>';
        $html .= '</a>';
    }
    
    // FIX-GRADES-LINK (v1.7.54): Teachers (grade/report:viewall) go to the grader report;
    // everyone else goes to their own user grade report.
    $activityctx_hero = context_course::instance($course->id);
    if (has_capability('moodle/grade:viewall', $activityctx_hero, null, false)) {
        $gradesurl = new moodle_url('/grade/report/grader/index.php', array('id' => $course->id));
    } else {
        $gradesurl = new moodle_url('/grade/report/user/index.php', array('id' => $course->id));
    }
    $html .= '<a href="' . $gradesurl->out() . '" class="aicourse-hero-grades" title="' . get_string('grades', 'format_aicourse') . '">';
    $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>';
    $html .= '</a>';
    
    // AI Course Assistant button - star icon for AI
    if (format_aicourse_is_tutor_enabled()) {
        $html .= '<button type="button" class="aicourse-hero-ai-btn aicourse-ai-toggle" title="' . get_string('aiassistant', 'format_aicourse') . '" data-courseid="' . $course->id . '" data-sesskey="' . sesskey() . '">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .962 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.582a.5.5 0 0 1 0 .962L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.962 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/></svg>';
        $html .= '</button>';
    }
    
    // Home button
    $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
    $html .= '<a href="' . $courseurl->out() . '" class="aicourse-hero-home" title="' . get_string('gotocourse', 'format_aicourse') . '">';
    $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>';
    $html .= '</a>';

    // AI Generate Banner button — editors only
    if (has_capability('moodle/course:update', context_course::instance($course->id))) {
        // Delete banner button — only shown when a custom banner image exists AND Moodle is in edit mode
        if ($custombanner && $PAGE->user_is_editing()) {
            $html .= '<button type="button" class="aicourse-hero-ai-btn aicourse-ai-delete-banner" '
                . 'title="Remove Banner Image" '
                . 'data-courseid="' . $course->id . '" '
                . 'data-sesskey="' . sesskey() . '">';
            $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                . '<polyline points="3 6 5 6 21 6"/>'
                . '<path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>'
                . '<path d="M10 11v6"/><path d="M14 11v6"/>'
                . '<path d="M9 6V4h6v2"/>'
                . '</svg>';
            $html .= '</button>';
        }
        $html .= '<button type="button" class="aicourse-hero-ai-btn aicourse-ai-generate-banner" '
            . 'title="Generate AI Banner Image" '
            . 'data-courseid="' . $course->id . '" '
            . 'data-coursename="' . s(format_string($course->fullname)) . '" '
            . 'data-shortname="' . s($course->shortname) . '" '
            . 'data-sesskey="' . sesskey() . '">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M15 4V2"/><path d="M15 16v-2"/><path d="M8 9h2"/><path d="M20 9h2"/>'
            . '<path d="M17.8 11.8 19 13"/><path d="M15 9h.01"/><path d="M17.8 6.2 19 5"/>'
            . '<path d="m3 21 9-9"/><path d="M12.2 6.2 11 5"/>'
            . '</svg>';
        $html .= '</button>';
    }

    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Add AI Assistant chatbox modal (HTML only - script added separately)
    $html .= format_aicourse_render_ai_chatbox_html();
    
    return $html;
}

/**
 * Get activity completion information with human-readable requirements
 */
function format_aicourse_get_activity_completion_info($course, $cm, $userid) {
    global $DB, $CFG;
    
    require_once($CFG->libdir . '/completionlib.php');
    
    $result = array(
        'completed' => false,
        'requirements' => array(),
        'ismanual' => false,
        'cmid' => $cm->id,
        'hascompletion' => false,
        'requiresgrade' => false,
        'currentgrade' => null,
        'maxgrade' => null,
        'gradetext' => null
    );
    
    // Re-use a single completion_info instance per course per request via static cache,
    // avoiding the cost of constructing a new object and re-checking is_enabled() on every call.
    static $infoCache = [];
    $cacheKey = $course->id . '_' . $userid;
    if (!isset($infoCache[$cacheKey])) {
        $infoCache[$cacheKey] = ['info' => new \completion_info($course), 'bulkloaded' => false];
    }
    $info         = $infoCache[$cacheKey]['info'];
    $bulkloaded   = &$infoCache[$cacheKey]['bulkloaded'];

    if (!$info->is_enabled() || $cm->completion == COMPLETION_TRACKING_NONE) {
        return $result;
    }
    
    $result['hascompletion'] = true;
    $result['ismanual'] = ($cm->completion == COMPLETION_TRACKING_MANUAL);
    
    // First call: pass $wholecourse=true to bulk-load every completion row for this user
    // in one SELECT. All subsequent calls within the same request use the in-memory cache.
    $data = $info->get_data($cm, !$bulkloaded, $userid);
    $bulkloaded = true;
    $result['completed'] = ($data->completionstate == COMPLETION_COMPLETE || $data->completionstate == COMPLETION_COMPLETE_PASS);
    
    // Build requirements list based on completion settings
    if ($cm->completion == COMPLETION_TRACKING_MANUAL) {
        $result['requirements'][] = get_string('completionrequirement_manual', 'format_aicourse');
    } else {
        // Automatic completion - check various conditions
        
        // View requirement
        if (!empty($cm->completionview)) {
            $result['requirements'][] = get_string('completionrequirement_view', 'format_aicourse');
        }
        
        // Grade requirement
        if (!empty($cm->completionusegrade) || !empty($cm->completionpassgrade)) {
            $result['requiresgrade'] = true;
            
            // Get grade item for this activity
            $gradeitem = $DB->get_record('grade_items', array(
                'itemtype' => 'mod',
                'itemmodule' => $cm->modname,
                'iteminstance' => $cm->instance,
                'courseid' => $course->id
            ));
            
            if ($gradeitem) {
                $result['maxgrade'] = round($gradeitem->grademax);
                
                // Get user's current grade
                $usergrade = $DB->get_record('grade_grades', array(
                    'itemid' => $gradeitem->id,
                    'userid' => $userid
                ));
                
                if ($usergrade && $usergrade->finalgrade !== null) {
                    $result['currentgrade'] = round($usergrade->finalgrade);
                    $result['gradetext'] = $result['currentgrade'] . '/' . $result['maxgrade'];
                } else {
                    $result['currentgrade'] = 0;
                    $result['gradetext'] = '-/' . $result['maxgrade'];
                }
            }
            
            // Check if there's a passing grade requirement
            if (!empty($cm->completionpassgrade)) {
                if ($gradeitem && $gradeitem->gradepass > 0) {
                    $passgrade = round($gradeitem->gradepass);
                    $maxgrade = round($gradeitem->grademax);
                    if ($maxgrade > 0 && $passgrade == $maxgrade) {
                        $result['requirements'][] = get_string('completionrequirement_grade100', 'format_aicourse');
                    } else if ($maxgrade > 0) {
                        $percentage = round(($passgrade / $maxgrade) * 100);
                        $result['requirements'][] = get_string('completionrequirement_gradepass', 'format_aicourse', $percentage . '%');
                    } else {
                        $result['requirements'][] = get_string('completionrequirement_gradepass', 'format_aicourse', $passgrade);
                    }
                } else {
                    $result['requirements'][] = get_string('completionrequirement_gradeany', 'format_aicourse');
                }
            } else {
                $result['requirements'][] = get_string('completionrequirement_gradeany', 'format_aicourse');
            }
        }
        
        // Module-specific completion (like SCORM tracks, lesson pages, etc.)
        // These are in the completion_criteria table
        $criteria = $DB->get_records('course_completion_criteria', array(
            'course' => $course->id,
            'moduleinstance' => $cm->id
        ));
        
        foreach ($criteria as $criterion) {
            // Add any custom criteria descriptions here if needed
        }
        
        // If no specific requirements found but completion is enabled
        if (empty($result['requirements']) && $cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
            $result['requirements'][] = get_string('completionrequirement_auto', 'format_aicourse');
        }
    }
    
    return $result;
}

/**
 * Get the current section for the activity being viewed
 */
function format_aicourse_get_current_section($course, $userid) {
    global $PAGE;
    
    // Check if we're viewing an activity
    if ($PAGE->context && $PAGE->context->contextlevel == CONTEXT_MODULE) {
        $cm = get_coursemodule_from_id('', $PAGE->context->instanceid);
        if ($cm) {
            $modinfo = get_fast_modinfo($course, $userid);
            // Use get_cm to get the course_module_info which has sectionnum property
            $cminfo = $modinfo->get_cm($cm->id);
            if ($cminfo) {
                $section = $modinfo->get_section_info($cminfo->sectionnum);
                if ($section) {
                    return array(
                        'num' => $section->section,
                        'name' => get_section_name($course, $section)
                    );
                }
            }
        }
    }
    
    return null;
}

/**
 * Get previous and next activity navigation links for the current activity.
 * @param object $course The course object
 * @param int $userid The user ID
 * @return array Array with 'prev' and 'next' keys, each containing 'name' and 'url' or null
 */
function format_aicourse_get_nav_links($course, $userid) {
    global $PAGE;
    
    $result = array('prev' => null, 'next' => null);
    
    // Get current course module from PAGE context
    $cm = null;
    if ($PAGE->context && $PAGE->context->contextlevel == CONTEXT_MODULE) {
        $cm = get_coursemodule_from_id('', $PAGE->context->instanceid);
    }
    
    if (!$cm) {
        return $result;
    }
    
    $modinfo = get_fast_modinfo($course, $userid);
    $cms = $modinfo->get_cms();
    
    // Build ordered list of visible activities
    $activities = array();
    foreach ($cms as $coursemod) {
        if ($coursemod->uservisible && $coursemod->url) {
            $activities[] = $coursemod;
        }
    }
    
    // Find current position
    $currentIndex = -1;
    foreach ($activities as $index => $activity) {
        if ($activity->id == $cm->id) {
            $currentIndex = $index;
            break;
        }
    }
    
    if ($currentIndex === -1) {
        return $result;
    }
    
    // Get previous activity
    if ($currentIndex > 0) {
        $prev = $activities[$currentIndex - 1];
        $result['prev'] = array(
            'name' => format_string($prev->name),
            'url' => $prev->url->out()
        );
    }
    
    // Get next activity
    if ($currentIndex < count($activities) - 1) {
        $next = $activities[$currentIndex + 1];
        $result['next'] = array(
            'name' => format_string($next->name),
            'url' => $next->url->out()
        );
    }
    
    return $result;
}

/**
 * Get previous and next activity navigation links for section pages.
 * Navigation crosses section boundaries - prev goes to last activity of previous section,
 * next goes to first activity of next section.
 * @param object $course The course object
 * @param int $currentsectionnum The current section number
 * @return array Array with 'prev' and 'next' keys, each containing 'name' and 'url' or null
 */
function format_aicourse_get_section_nav_links($course, $currentsectionnum, $modinfo = null) {
    global $USER;
    
    $result = array('prev' => null, 'next' => null);
    
    if ($currentsectionnum === null) {
        return $result;
    }
    
    // Accept a pre-loaded modinfo to avoid a redundant get_fast_modinfo() call
    // when the caller (render_hero_banner) already has one.
    if ($modinfo === null) {
        $modinfo = get_fast_modinfo($course, $USER->id);
    }
    
    // FIX-ACF-NAVSKIP (v1.7.48): Navigate between SECTION PAGES, not individual activity
    // URLs. The previous implementation linked to the first/last activity of the
    // adjacent section — this caused two problems:
    //   1. Clicking the arrow took the student to an activity page, not the section page.
    //   2. Sub-sections (e.g. 5.1, 5.2) with no *directly-visible* activities were skipped
    //      entirely, so the arrow jumped from section 5.1 all the way to section 6.
    // Fix: build an ordered list of all *visible* sections (skipping section 0 / General)
    // and navigate to the adjacent section's own course/view.php?section=N URL.
    $allsections = $modinfo->get_section_info_all();
    $orderedsections = array();
    foreach ($allsections as $section) {
        if ((int)$section->section === 0) {
            continue; // Skip "General" (section 0)
        }
        if (!$section->uservisible) {
            continue;
        }
        $orderedsections[] = $section;
    }
    
    if (empty($orderedsections)) {
        return $result;
    }
    
    // Find the current section's position in the ordered list.
    $currentidx = -1;
    foreach ($orderedsections as $idx => $section) {
        if ((int)$section->section === (int)$currentsectionnum) {
            $currentidx = $idx;
            break;
        }
    }
    
    if ($currentidx === -1) {
        return $result;
    }
    
    // Previous section
    if ($currentidx > 0) {
        $prevsection = $orderedsections[$currentidx - 1];
        $prevurl = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $prevsection->section));
        $result['prev'] = array(
            'name' => get_section_name($course, $prevsection),
            'url'  => $prevurl->out(),
        );
    }
    
    // Next section
    if ($currentidx < count($orderedsections) - 1) {
        $nextsection = $orderedsections[$currentidx + 1];
        $nexturl = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $nextsection->section));
        $result['next'] = array(
            'name' => get_section_name($course, $nextsection),
            'url'  => $nexturl->out(),
        );
    }
    
    return $result;
}

function format_aicourse_get_course_image($course) {
    $context = context_course::instance($course->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder DESC, id ASC', false);
    
    if ($files) {
        $file = reset($files);
        $imageurl = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            null,
            $file->get_filepath(),
            $file->get_filename()
        );
        return $imageurl->out();
    }
    return null;
}

/**
 * Return the URL of the custom banner image uploaded via the course format settings.
 * Returns null when no custom banner image has been uploaded.
 *
 * Falls back to format_aicourse_get_course_image() in the caller when this returns null,
 * so a course overview image still works if no dedicated banner has been uploaded.
 */
function format_aicourse_get_banner_image_url($course) {
    $context = context_course::instance($course->id);
    $fs      = get_file_storage();
    $files   = $fs->get_area_files(
        $context->id,
        'format_aicourse',
        'bannerimage',
        $course->id,
        'sortorder DESC, id ASC',
        false
    );

    if ($files) {
        $file = reset($files);
        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'format_aicourse',
            'bannerimage',
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out();
    }

    return null;
}

function format_aicourse_get_progress($course, $userid, $completioninfo = null) {
    $result = array(
        'enabled' => false,
        'percentage' => 0,
        'completed' => 0,
        'total' => 0,
        'activities' => array()
    );
    
    // Accept a pre-loaded completion_info to share one bulk DB read with callers
    // that also call format_aicourse_get_section_progress().
    $completioninfo = ($completioninfo !== null) ? $completioninfo : new completion_info($course);
    
    // Completion disabled at course level
    if (!$completioninfo->is_enabled()) {
        return $result;
    }
    
    $result['enabled'] = true;
    
    // Count activities manually (fallback)
    $modinfo = get_fast_modinfo($course, $userid);
    $total = 0;
    $completed = 0;
    
    // Passing $wholecourse=true on the first get_data() call triggers a single bulk SELECT
    // of every course_modules_completion row for this user, caching it inside $completioninfo.
    // All subsequent calls on this object use the in-memory cache rather than per-row queries.
    $bulkloaded = false;
    foreach ($modinfo->get_cms() as $cm) {
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
        
        $result['activities'][] = array(
            'id' => $cm->id,
            'name' => format_string($cm->name),
            'status' => $status,
            'url' => $cm->url ? $cm->url->out() : ''
        );
    }
    
    $result['total'] = $total;
    $result['completed'] = $completed;
    
    // Use Moodle's official course progress API for accurate percentage
    // This respects course completion criteria, activity dependencies, manual completion rules, hidden activities
    // The API expects: get_course_progress_percentage($course, $userid) where $course is an OBJECT
    // Note: We suppress warnings with @ because PHP warnings are NOT caught by try-catch
    $official = null;
    
    if (class_exists('\core_completion\progress') && is_object($course) && !empty($course->id)) {
        // Suppress any warnings - the API may fail for teachers/admins or if completion not enabled
        $official = @\core_completion\progress::get_course_progress_percentage($course, $userid);
        
        if ($official !== null && is_numeric($official)) {
            $result['percentage'] = (int) round($official);
            return $result;
        }
    }
    
    // Safe fallback calculation (avoid division by zero)
    $result['percentage'] = ($total > 0) ? (int) round(($completed / $total) * 100) : 0;
    
    return $result;
}

/**
 * Get section progress data for card display
 */
function format_aicourse_get_section_progress($course, $section, $userid, $completioninfo = null) {
    // Accept a pre-loaded completion_info object so the caller can share one bulk-loaded
    // instance across multiple sections, avoiding N separate DB queries.
    $info = ($completioninfo !== null) ? $completioninfo : new completion_info($course);
    $result = array('enabled' => false, 'percentage' => 0, 'completed' => 0, 'total' => 0, 'activities' => array(), 'estimatedminutes' => 0, 'activitycount' => 0);
    
    $modinfo = get_fast_modinfo($course, $userid);
    $total = 0;
    $completed = 0;
    $estimatedminutes = 0;
    $activitycount = 0;
    
    // Check if completion tracking is enabled
    $completionenabled = $info->is_enabled();
    $result['enabled'] = $completionenabled;
    
    // Get activity IDs directly from the section (safer than comparing section numbers)
    $sectionnum = $section->section;
    $sectioncmids = isset($modinfo->sections[$sectionnum]) ? $modinfo->sections[$sectionnum] : array();
    
    // When no pre-loaded $completioninfo was supplied, bulk-load all completion data for this
    // user+course on the first get_data() call (passing $wholecourse=true). If a pre-loaded
    // object was provided by render_section_cards, the cache is already warm — use false.
    $needsbulkload = ($completioninfo === null);
    $bulkloaded = false;
    
    foreach ($sectioncmids as $cmid) {
        $cm = $modinfo->get_cm($cmid);
        if (!$cm->uservisible) {
            continue;
        }
        
        $activitycount++;
        
        // Estimate time per activity type (in minutes)
        $activitytime = 5; // Default 5 minutes
        switch ($cm->modname) {
            case 'quiz': $activitytime = 15; break;
            case 'assign': $activitytime = 30; break;
            case 'forum': $activitytime = 10; break;
            case 'book': $activitytime = 20; break;
            case 'page': $activitytime = 5; break;
            case 'url': $activitytime = 3; break;
            case 'resource': $activitytime = 5; break;
            case 'lesson': $activitytime = 25; break;
            case 'scorm': $activitytime = 30; break;
            case 'h5pactivity': $activitytime = 10; break;
            case 'workshop': $activitytime = 45; break;
        }
        $estimatedminutes += $activitytime;
        
        // Only track completion if enabled
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
                    $completeddate = userdate($data->timemodified, get_string('strftimedateshort'));
                }
            } else if ($data->viewed) {
                $status = 'in_progress';
            }
            
            $result['activities'][] = array(
                'id' => $cm->id,
                'name' => format_string($cm->name),
                'status' => $status,
                'completeddate' => $completeddate,
                'url' => $cm->url ? $cm->url->out() : ''
            );
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
 * Get the icon library for section cards
 */
function format_aicourse_get_icon_library() {
    return array(
        // Numbers
        'num-1' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">1</text>',
        'num-2' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">2</text>',
        'num-3' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">3</text>',
        'num-4' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">4</text>',
        'num-5' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">5</text>',
        'num-6' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">6</text>',
        'num-7' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">7</text>',
        'num-8' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">8</text>',
        'num-9' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">9</text>',
        'num-10' => '<text x="12" y="16" text-anchor="middle" font-size="12" font-weight="600" fill="currentColor">10</text>',
        // Education
        'book' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'book-open' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'graduation' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 12v5c3 3 9 3 12 0v-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'pen' => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'clipboard' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><rect width="8" height="4" x="8" y="2" rx="1" ry="1" fill="none" stroke="currentColor" stroke-width="2"/>',
        'file-text' => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="14 2 14 8 20 8" fill="none" stroke="currentColor" stroke-width="2"/><line x1="16" x2="8" y1="13" y2="13" stroke="currentColor" stroke-width="2"/><line x1="16" x2="8" y1="17" y2="17" stroke="currentColor" stroke-width="2"/>',
        // Work & Safety
        'briefcase' => '<rect width="20" height="14" x="2" y="7" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" fill="none" stroke="currentColor" stroke-width="2"/>',
        'hard-hat' => '<path d="M2 18a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 15V6a4 4 0 0 1 4-4v0a2 2 0 0 1 2 2v0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 15V6a4 4 0 0 0-4-4v0a2 2 0 0 0-2 2v0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'shield-check' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="12" x2="12" y1="9" y2="13" stroke="currentColor" stroke-width="2"/><line x1="12" x2="12.01" y1="17" y2="17" stroke="currentColor" stroke-width="2"/>',
        // Tools & Tech
        'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'settings' => '<circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" fill="none" stroke="currentColor" stroke-width="2"/>',
        'monitor' => '<rect width="20" height="14" x="2" y="3" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="8" x2="16" y1="21" y2="21" stroke="currentColor" stroke-width="2"/><line x1="12" x2="12" y1="17" y2="21" stroke="currentColor" stroke-width="2"/>',
        'laptop' => '<path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        // General
        'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="9 22 9 12 15 12 15 22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'info' => '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 16v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M22 21v-2a4 4 0 0 0-3-3.87" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 3.13a4 4 0 0 1 0 7.75" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/>',
        'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'heart' => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'target' => '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="6" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="2" fill="none" stroke="currentColor" stroke-width="2"/>',
        'trophy' => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 22h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'flag' => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="4" x2="4" y1="22" y2="15" stroke="currentColor" stroke-width="2"/>',
        'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="22 4 12 14.01 9 11.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'lightbulb' => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 18h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 22h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'clock' => '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="12 6 12 12 16 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'calendar' => '<rect width="18" height="18" x="3" y="4" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="16" x2="16" y1="2" y2="6" stroke="currentColor" stroke-width="2"/><line x1="8" x2="8" y1="2" y2="6" stroke="currentColor" stroke-width="2"/><line x1="3" x2="21" y1="10" y2="10" stroke="currentColor" stroke-width="2"/>',
        'map-pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="10" r="3" fill="none" stroke="currentColor" stroke-width="2"/>',
        'rocket' => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'zap' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'layers' => '<polygon points="12 2 2 7 12 12 22 7 12 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="2 17 12 22 22 17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="2 12 12 17 22 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'package' => '<path d="m7.5 4.27 9 5.15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="m3.3 7 8.7 5 8.7-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 22V12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'help-circle' => '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'play-circle' => '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><polygon points="10 8 16 12 10 16 10 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'folder' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'award' => '<circle cx="12" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="2"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
    );
}

/**
 * Get section icon from course format options
 */
/**
 * OPT-ACF-ICON-CACHE (v1.7.51): Returns a reference to the shared icon cache array.
 * Using a dedicated function with a static variable lets both
 * format_aicourse_preload_section_icons() and format_aicourse_get_section_icon()
 * read/write the same in-process cache without a global variable.
 */
function &format_aicourse_icon_cache_ref() {
    static $cache = [];
    return $cache;
}

/**
 * OPT-ACF-BULK-ICONS (v1.7.51): Preload icons for multiple sections in a single
 * DB query. Call this once before iterating over sections to eliminate the N
 * individual get_field() calls that format_aicourse_get_section_icon() would
 * otherwise make — one per section.
 *
 * @param int   $courseid   Course id.
 * @param int[] $sectionids Array of section record ids.
 */
function format_aicourse_preload_section_icons($courseid, array $sectionids) {
    global $DB;
    if (empty($sectionids)) {
        return;
    }
    $cache = &format_aicourse_icon_cache_ref();

    // Only fetch rows that are not already in the cache.
    $toload = [];
    foreach ($sectionids as $sid) {
        if (!array_key_exists($courseid . '_' . $sid, $cache)) {
            $toload[] = 'sectionicon_' . (int)$sid;
        }
    }
    if (empty($toload)) {
        return;
    }

    list($insql, $params) = $DB->get_in_or_equal($toload, SQL_PARAMS_NAMED);
    $params['courseid'] = $courseid;
    $params['format']   = 'aicourse';
    $rows = $DB->get_records_select(
        'course_format_options',
        "courseid = :courseid AND format = :format AND name $insql",
        $params,
        '',
        'name, value'
    );

    // Populate cache from results.
    $found = [];
    foreach ($rows as $row) {
        if (preg_match('/^sectionicon_(\d+)$/', $row->name, $m)) {
            $sid = (int)$m[1];
            $cache[$courseid . '_' . $sid] = $row->value;
            $found[$sid] = true;
        }
    }
    // Mark sections with no saved icon so get_section_icon() skips the DB.
    foreach ($sectionids as $sid) {
        if (!isset($found[(int)$sid])) {
            $cache[$courseid . '_' . $sid] = '';
        }
    }
}

function format_aicourse_get_section_icon($courseid, $sectionid) {
    global $DB;
    $cache    = &format_aicourse_icon_cache_ref();
    $cachekey = $courseid . '_' . $sectionid;
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }
    // Cache miss — single DB query (also populates cache for next call).
    $key   = 'sectionicon_' . $sectionid;
    $value = $DB->get_field('course_format_options', 'value', array(
        'courseid' => $courseid,
        'format'   => 'aicourse',
        'name'     => $key,
    ));
    $cache[$cachekey] = $value ? $value : '';
    return $cache[$cachekey];
}

/**
 * Set section icon in course format options
 */
function format_aicourse_set_section_icon($courseid, $sectionid, $icon) {
    global $DB;
    $key = 'sectionicon_' . $sectionid;
    $existing = $DB->get_record('course_format_options', array(
        'courseid' => $courseid,
        'format' => 'aicourse',
        'name' => $key
    ));
    if ($existing) {
        $DB->update_record('course_format_options', (object)array(
            'id' => $existing->id,
            'value' => $icon
        ));
    } else {
        $DB->insert_record('course_format_options', (object)array(
            'courseid' => $courseid,
            'format' => 'aicourse',
            'sectionid' => 0,
            'name' => $key,
            'value' => $icon
        ));
    }
}

/**
 * Render beautiful section cards for card view mode
 */
function format_aicourse_render_section_cards($course, $options) {
    global $USER, $PAGE;
    
    $modinfo = get_fast_modinfo($course, $USER->id);
    $sections = $modinfo->get_section_info_all();
    $canedit = has_capability('moodle/course:update', context_course::instance($course->id));
    // FIX-ACF-EDITMODE (v1.7.70): Gate all edit controls by actual edit mode state.
    // $canedit only checks capability; $isediting also requires the teacher to have
    // turned on edit mode. This mirrors the student view when edit mode is OFF.
    // The card layout itself still always renders (fix for UX-ACF-EDITMODE-WIPE).
    $isediting = $canedit && $PAGE->user_is_editing();
    $iconlibrary = format_aicourse_get_icon_library();
    
    // Create one shared completion_info and bulk-load ALL user completion data in a single
    // DB query. Passing $wholecourse=true on the first get_data() call triggers a SELECT of
    // every completion row for this user+course and caches it inside the object. Every
    // subsequent call on the same instance hits the in-memory cache, so we avoid N×M
    // individual queries (N sections × M activities per section).
    $sharedcompletioninfo = new completion_info($course);
    if ($sharedcompletioninfo->is_enabled()) {
        $allcms = $modinfo->get_cms();
        foreach ($allcms as $firstcm) {
            if ($firstcm->completion != COMPLETION_TRACKING_NONE) {
                $sharedcompletioninfo->get_data($firstcm, true, $USER->id);
                break;
            }
        }
    }
    
    $coursecontext = context_course::instance($course->id);
    $html = '<div class="aicourse-cards-container">';

    // Always render Section 0 (General) inline above the section cards grid if it has content.
    // This matches standard Moodle behaviour: General section appears first, then module sections.
    $section0 = isset($sections[0]) ? $sections[0] : null;
    if ($section0 && $section0->uservisible) {
        $section0cmids = isset($modinfo->sections[0]) ? $modinfo->sections[0] : array();
        $section0summary = trim(strip_tags($section0->summary ?? ''));
        $section0hasactivities = false;
        foreach ($section0cmids as $s0cmid) {
            $s0cm = $modinfo->get_cm($s0cmid);
            if ($s0cm->uservisible && $s0cm->url) {
                $section0hasactivities = true;
                break;
            }
        }
        if ($section0hasactivities || !empty($section0summary)) {
            $html .= '<div class="aicourse-general-section">';
            if (!empty($section0summary)) {
                $summarytext = file_rewrite_pluginfile_urls(
                    $section0->summary,
                    'pluginfile.php',
                    $coursecontext->id,
                    'course',
                    'section',
                    $section0->id
                );
                $summarytext = format_text($summarytext, $section0->summaryformat, ['context' => $coursecontext]);
                $html .= '<div class="aicourse-general-summary">' . $summarytext . '</div>';
            }
            if ($section0hasactivities) {
                $html .= format_aicourse_render_activity_cards($course, 0, $options);
            }
            $html .= '</div>'; // aicourse-general-section
        }
    }

    // OPT-ACF-BULK-ICONS (v1.7.51): Preload ALL section icons in a single DB query
    // before entering the per-section loop. Without this, each call to
    // format_aicourse_get_section_icon() inside the loop issued its own SELECT,
    // producing N sequential queries for N sections. The preload populates the static
    // cache (format_aicourse_icon_cache_ref) so every subsequent get_section_icon()
    // call in this request hits memory at zero DB cost.
    $preload_sectionids = [];
    foreach ($sections as $s) {
        if ($s->uservisible && $s->section > 0) {
            $preload_sectionids[] = $s->id;
        }
    }
    if (!empty($preload_sectionids)) {
        format_aicourse_preload_section_icons($course->id, $preload_sectionids);
    }

    $html .= '<div class="aicourse-cards-grid">';
    
    foreach ($sections as $section) {
        if (!$section->uservisible) {
            continue;
        }

        // Section 0 (General) is always rendered inline above the grid — never as a card.
        if ($section->section == 0) {
            continue;
        }
        
        $sectionname = get_section_name($course, $section);
        $progressdata = format_aicourse_get_section_progress($course, $section, $USER->id, $sharedcompletioninfo);
        
        // Format estimated time
        $estimatedtime = '';
        if ($progressdata['estimatedminutes'] > 0) {
            if ($progressdata['estimatedminutes'] >= 60) {
                $hours = floor($progressdata['estimatedminutes'] / 60);
                $mins = $progressdata['estimatedminutes'] % 60;
                $estimatedtime = $hours . ' ' . get_string('hoursshort', 'format_aicourse');
                if ($mins > 0) {
                    $estimatedtime .= ' ' . $mins . ' ' . get_string('minutesshort', 'format_aicourse');
                }
            } else {
                $estimatedtime = $progressdata['estimatedminutes'] . ' ' . get_string('minutesshort', 'format_aicourse');
            }
        }
        
        // Section URL - section 0 uses view.php (section.php redirects), others use section.php
        if ($section->section == 0) {
            // Section 0 (general) - section.php redirects back to view.php, so link directly
            $sectionurl = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => 0));
        } else {
            // Regular sections use Moodle 4.x section.php format with section ID
            $sectionurl = new moodle_url('/course/section.php', array('id' => $section->id));
        }
        
        // Progress ring calculations
        $percentage = $progressdata['percentage'];
        $circumference = 2 * 3.14159 * 18;
        $offset = $circumference - ($percentage / 100) * $circumference;
        
        // Get saved icon for this section
        $savedicon = format_aicourse_get_section_icon($course->id, $section->id);
        
        $html .= '<div class="aicourse-card" data-section="' . $section->section . '" data-sectionid="' . $section->id . '">';
        
        // UX-ACF-EDITBTNS: Card edit buttons — only shown when edit mode is ON.
        // $isediting combines capability + $PAGE->user_is_editing() so students and
        // teachers in view mode never see these controls (edit mode OFF = student view).
        if ($isediting && $section->section > 0) {
            $html .= '<div class="aicourse-card-edit-buttons">';

            // Settings / rename button — links to section edit page
            $editsectionurl = new moodle_url('/course/editsection.php', array('id' => $section->id, 'sr' => 0));
            $html .= '<a href="' . $editsectionurl->out(false) . '" class="aicourse-card-settings" title="' . get_string('editsection', 'moodle') . '">';
            $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>';
            $html .= '</a>';

            // Duplicate section button - uses AJAX to stay on same page
            $html .= '<button type="button" class="aicourse-card-duplicate" data-sectionid="' . $section->id . '" data-courseid="' . $course->id . '" title="' . get_string('duplicatesection', 'format_aicourse') . '">';
            $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>';
            $html .= '</button>';

            // Delete section button
            $deleteurl = new moodle_url('/course/editsection.php', array(
                'id' => $section->id,
                'sr' => 0,
                'delete' => 1,
                'sesskey' => sesskey()
            ));
            $html .= '<button type="button" class="aicourse-card-delete" data-sectionid="' . $section->id . '" data-sectionnum="' . $section->section . '" data-deleteurl="' . $deleteurl->out(false) . '" title="' . get_string('deletesection', 'format_aicourse') . '">';
            $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            $html .= '</button>';

            $html .= '</div>';
        }
        
        // Card header - icon OUTSIDE link for reliable click handling
        $html .= '<div class="aicourse-card-header">';
        
        // Icon column: icon box + "Add/Change icon" label (edit mode only)
        $hasicon = !empty($savedicon) && isset($iconlibrary[$savedicon]);
        $wrapperstate = $hasicon ? ' aicourse-icon-selected' : ' aicourse-icon-empty';
        if ($isediting) {
            // Outer col wrapper carries the click trigger, data attributes, and editable class.
            // Only rendered in edit mode so students/view-mode teachers never see it as interactive.
            $html .= '<div class="aicourse-icon-col aicourse-card-icon-editable" data-courseid="' . $course->id . '" data-sectionid="' . $section->id . '">';
        }
        $html .= '<div class="aicourse-card-icon-wrap' . $wrapperstate . '">';
        if ($hasicon) {
            $html .= '<div class="aicourse-card-icon">';
            $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">' . $iconlibrary[$savedicon] . '</svg>';
            $html .= '</div>';
        } else {
            $html .= '<div class="aicourse-card-icon">';
            if ($isediting) {
                // Pencil icon as empty state placeholder — only in edit mode
                $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>';
            } else {
                $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">';
                $html .= '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="500" fill="currentColor">?</text>';
                $html .= '</svg>';
            }
            $html .= '</div>';
        }
        $html .= '</div>'; // end aicourse-card-icon-wrap
        if ($isediting) {
            $iconlabel = $hasicon ? get_string('changeicon', 'format_aicourse') : get_string('addicon', 'format_aicourse');
            $html .= '<span class="aicourse-icon-change-label">' . $iconlabel . '</span>';
            $html .= '</div>'; // end aicourse-icon-col
        }
        
        // Time badge - links to section
        if (!empty($estimatedtime)) {
            $html .= '<a href="' . $sectionurl->out() . '" class="aicourse-card-time">';
            $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';
            $html .= '<span>' . $estimatedtime . '</span>';
            $html .= '</a>';
        }
        $html .= '</div>';
        
        // Card body - title links to section
        $html .= '<a href="' . $sectionurl->out() . '" class="aicourse-card-link">';
        $html .= '<h3 class="aicourse-card-title">' . format_string($sectionname) . '</h3>';
        $html .= '</a>';
        
        // Section summary / description (truncated) shown below title on card
        if (!empty($section->summary)) {
            $summaryclean = strip_tags(format_string($section->summary));
            if (core_text::strlen($summaryclean) > 130) {
                $summaryclean = core_text::substr($summaryclean, 0, 130) . '…';
            }
            if ($summaryclean !== '') {
                $html .= '<p class="aicourse-card-summary">' . s($summaryclean) . '</p>';
            }
        }
        
        // Card footer - OUTSIDE the link so dots can be clickable
        $activitycount = isset($progressdata['activitycount']) ? $progressdata['activitycount'] : 0;
        $hasProgress = $progressdata['enabled'] && $progressdata['total'] > 0;
        
        if ($activitycount > 0 || $hasProgress) {
            $html .= '<div class="aicourse-card-footer">';
            
            // Left side: activity count + progress dots inline
            $html .= '<div class="aicourse-card-footer-left">';
            if ($activitycount > 0) {
                $html .= '<a href="' . $sectionurl->out() . '" class="aicourse-activity-count">' . $activitycount . ' ' . ($activitycount == 1 ? get_string('activity', 'format_aicourse') : get_string('activities', 'format_aicourse')) . '</a>';
            }
            // Compact activity dots (max 5 shown) - each dot links to its activity
            if ($hasProgress && !empty($progressdata['activities'])) {
                $html .= '<div class="aicourse-card-dots">';
                $dotcount = 0;
                foreach ($progressdata['activities'] as $activity) {
                    if ($dotcount >= 5) {
                        $remaining = count($progressdata['activities']) - 5;
                        $html .= '<a href="' . $sectionurl->out() . '" class="aicourse-card-dots-more" title="' . get_string('viewallactivities', 'format_aicourse') . '">+' . $remaining . '</a>';
                        break;
                    }
                    $statusclass = 'aicourse-dot-' . $activity['status'];
                    $activityurl = !empty($activity['url']) ? $activity['url'] : $sectionurl->out();
                    $html .= '<a href="' . $activityurl . '" class="aicourse-card-dot ' . $statusclass . '" title="' . s($activity['name']) . '"></a>';
                    $dotcount++;
                }
                $html .= '</div>';
            }
            $html .= '</div>';
            
            // Right side: percentage badge (links to section)
            if ($hasProgress) {
                $badgeclass = $percentage == 100 ? 'aicourse-progress-badge-complete' : 'aicourse-progress-badge';
                $html .= '<a href="' . $sectionurl->out() . '" class="' . $badgeclass . '">' . $percentage . '%</a>';
            }
            
            $html .= '</div>'; // card-footer
        }
        $html .= '</div>'; // card
    }

    // Add Section card — only rendered when edit mode is ON
    if ($isediting) {
        $html .= '<button type="button" class="aicourse-add-section-card" data-courseid="' . $course->id . '">';
        $html .= '<div class="aicourse-add-section-icon">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>';
        $html .= '</div>';
        $html .= '<span class="aicourse-add-section-label">' . get_string('addsection', 'format_aicourse') . '</span>';
        $html .= '</button>';
    }

    $html .= '</div>'; // cards-grid

    // Icon picker modal — only needed when edit mode is ON
    if ($isediting) {
        $html .= format_aicourse_render_icon_picker($iconlibrary);
    }

    $html .= '</div>'; // cards-container

    return $html;
}

/**
 * Get icon categories for the section card icon picker.
 * Returns category_label => [icon_key, ...] ordered map.
 */
function format_aicourse_get_icon_categories() {
    return array(
        'Numbers'             => ['num-1', 'num-2', 'num-3', 'num-4', 'num-5',
                                  'num-6', 'num-7', 'num-8', 'num-9', 'num-10'],
        'Education'           => ['book', 'book-open', 'graduation', 'pen',
                                  'clipboard', 'file-text', 'lightbulb', 'play-circle'],
        'Work & Industry'     => ['briefcase', 'hard-hat', 'wrench', 'settings',
                                  'monitor', 'laptop', 'layers', 'package', 'folder'],
        'Safety & Compliance' => ['shield', 'shield-check', 'alert-triangle',
                                  'check-circle', 'lock'],
        'People'              => ['users', 'user', 'message', 'help-circle'],
        'Achievement'         => ['target', 'trophy', 'flag', 'star', 'heart',
                                  'award', 'rocket', 'zap'],
        'General'             => ['home', 'info', 'clock', 'calendar', 'map-pin'],
    );
}

/**
 * Render the icon picker modal with category groupings, icon labels, search, and a Remove option.
 */
function format_aicourse_render_icon_picker($iconlibrary) {
    $categories = format_aicourse_get_icon_categories();

    $html  = '<div class="aicourse-icon-picker-modal" id="aicourse-icon-picker" style="display:none;">';
    $html .= '<div class="aicourse-icon-picker-backdrop"></div>';
    $html .= '<div class="aicourse-icon-picker-content">';

    // Header
    $html .= '<div class="aicourse-icon-picker-header">';
    $html .= '<h4>' . get_string('selecticon', 'format_aicourse') . '</h4>';
    $html .= '<button type="button" class="aicourse-icon-picker-close" aria-label="Close">&times;</button>';
    $html .= '</div>';

    // Search bar
    $html .= '<div class="aicourse-icon-picker-search">';
    $html .= '<svg class="aicourse-icon-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
    $html .= '<input type="text" class="aicourse-icon-search-input" placeholder="' . get_string('searchicons', 'format_aicourse') . '" autocomplete="off" />';
    $html .= '</div>';

    // Scrollable body
    $html .= '<div class="aicourse-icon-picker-body">';

    // "Remove icon" option — appears above the category grid
    $html .= '<div class="aicourse-icon-picker-category" data-category="__remove__">';
    $html .= '<div class="aicourse-icon-picker-grid aicourse-icon-picker-grid--remove">';
    $html .= '<button type="button" class="aicourse-icon-picker-item aicourse-icon-remove-btn" data-icon="__clear__" title="' . get_string('removeicon', 'format_aicourse') . '">';
    $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
    $html .= '<span class="aicourse-icon-picker-label">' . get_string('removeicon', 'format_aicourse') . '</span>';
    $html .= '</button>';
    $html .= '</div>';
    $html .= '</div>';

    // Icons grouped by category
    foreach ($categories as $catname => $iconkeys) {
        $html .= '<div class="aicourse-icon-picker-category" data-category="' . htmlspecialchars($catname) . '">';
        $html .= '<div class="aicourse-icon-picker-category-label">' . htmlspecialchars($catname) . '</div>';
        $html .= '<div class="aicourse-icon-picker-grid">';
        foreach ($iconkeys as $key) {
            if (!isset($iconlibrary[$key])) {
                continue;
            }
            // Human-readable label: strip num- prefix, replace hyphens, capitalise
            $label = ucfirst(str_replace('-', ' ', preg_replace('/^num-/', '', $key)));
            $html .= '<button type="button" class="aicourse-icon-picker-item" data-icon="' . $key . '" title="' . $label . '">';
            $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">' . $iconlibrary[$key] . '</svg>';
            $html .= '<span class="aicourse-icon-picker-label">' . $label . '</span>';
            $html .= '</button>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div>'; // aicourse-icon-picker-body
    $html .= '</div>'; // aicourse-icon-picker-content
    $html .= '</div>'; // aicourse-icon-picker-modal

    return $html;
}

/**
 * Render beautiful activity cards for a specific section
 */
function format_aicourse_render_activity_cards($course, $sectionnum, $options) {
    global $USER, $OUTPUT;
    
    $modinfo = get_fast_modinfo($course, $USER->id);
    $section = $modinfo->get_section_info($sectionnum);
    
    if (!$section) {
        $html = '<div class="aicourse-activity-cards-container">';
        $html .= '<div class="aicourse-empty-state">';
        $html .= '<p>' . get_string('sectionnotfound', 'format_aicourse') . '</p>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
    
    $info = new completion_info($course);
    $completionenabled = $info->is_enabled();
    
    $html = '<div class="aicourse-activity-cards-container">';
    $html .= '<div class="aicourse-activity-cards-grid">';
    
    $activitycount = 0;
    
    // Get activity IDs directly from the section (safer than comparing section numbers)
    $sectioncmids = isset($modinfo->sections[$sectionnum]) ? $modinfo->sections[$sectionnum] : array();

    // Bulk-load ALL completion rows for this user + course in one SELECT before the loop.
    // Passing $wholecourse=true on the first get_data() call triggers a single SELECT of
    // every course_modules_completion row, caching them inside $info. Every subsequent
    // get_data($cm, false, ...) call in the loop is then served from that in-memory cache
    // with zero additional DB queries — fixing the N+1 pattern that ran one SELECT per activity.
    if ($completionenabled) {
        foreach ($sectioncmids as $bulkcmid) {
            $bulkcm = $modinfo->get_cm($bulkcmid);
            if ($bulkcm->completion != COMPLETION_TRACKING_NONE) {
                $info->get_data($bulkcm, true, $USER->id); // $wholecourse=true → bulk SELECT
                break;
            }
        }
    }

    foreach ($sectioncmids as $cmid) {
        $cm = $modinfo->get_cm($cmid);
        if (!$cm->uservisible) {
            continue;
        }
        if (!$cm->url) {
            // FIX-ACF-SUBSECTION (v1.7.48): Moodle 4.4+ introduces mod_subsection — a
            // special module type that acts as a container for a child section rather than
            // a standalone activity. These cms appear in the parent section's cmids list
            // but have $cm->url === null, so the old "skip labels" guard silently dropped
            // them. This caused any section that contained only sub-sections (e.g. the
            // parent "Section 3" whose activities live in "3.1", "3.2", etc.) to appear
            // completely empty in student view even though edit mode showed content.
            // Fix: detect the 'subsection' modname, resolve the delegated child section,
            // and render it as a clickable section-navigation card.
            if ($cm->modname === 'subsection') {
                $delegated = null;
                try {
                    if (method_exists($cm, 'get_delegated_section_info')) {
                        $delegated = $cm->get_delegated_section_info();
                    }
                } catch (Throwable $e) {
                    // Older Moodle versions do not support this method — skip gracefully.
                }
                if ($delegated && $delegated->uservisible) {
                    $activitycount++;
                    $subsectionurl = new moodle_url('/course/view.php',
                        array('id' => $course->id, 'section' => $delegated->section));
                    $subsectionname = get_section_name($course, $delegated);
                    $html .= '<a href="' . $subsectionurl->out() . '" class="aicourse-activity-card aicourse-activity-status-not_started" data-cmid="' . $cm->id . '">';
                    $html .= '<div class="aicourse-activity-card-icon">';
                    $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/><path d="M14 4h7"/><path d="M14 9h7"/><path d="M14 15h7"/><path d="M14 20h7"/></svg>';
                    $html .= '</div>';
                    $html .= '<div class="aicourse-activity-card-body">';
                    $html .= '<h4 class="aicourse-activity-card-name">' . $subsectionname . '</h4>';
                    $html .= '<div class="aicourse-activity-card-footer">';
                    $html .= '<span class="aicourse-activity-card-type">' . get_string('section', 'moodle') . '</span>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</a>';
                }
            }
            continue; // Skip labels/resources without URLs (subsection handled above)
        }
        
        $activitycount++;
        
        // Get completion status
        $status = 'not_started';
        $statuslabel = get_string('notstarted', 'format_aicourse');
        $ismanual = ($cm->completion == COMPLETION_TRACKING_MANUAL);
        $iscompleted = false;
        
        if ($completionenabled && $cm->completion != COMPLETION_TRACKING_NONE) {
            $data = $info->get_data($cm, false, $USER->id);
            if ($data->completionstate == COMPLETION_COMPLETE || $data->completionstate == COMPLETION_COMPLETE_PASS) {
                $status = 'completed';
                $statuslabel = get_string('completed', 'format_aicourse');
                $iscompleted = true;
            } else if ($data->viewed) {
                $status = 'in_progress';
                $statuslabel = get_string('inprogress', 'format_aicourse');
            }
        }
        
        // Get activity icon URL
        $iconurl = $cm->get_icon_url()->out();
        
        $html .= '<a href="' . $cm->url->out() . '" class="aicourse-activity-card aicourse-activity-status-' . $status . '" data-cmid="' . $cm->id . '">';
        
        // Card icon
        $html .= '<div class="aicourse-activity-card-icon">';
        $html .= '<img src="' . $iconurl . '" alt="' . s((string)$cm->modfullname) . '" />';
        $html .= '</div>';
        
        // Card body (name + footer)
        $html .= '<div class="aicourse-activity-card-body">';
        $html .= '<h4 class="aicourse-activity-card-name">' . format_string($cm->name) . '</h4>';
        
        // Compact footer with type and status badge
        $activitytypenames = array(
            'AI Content Creator' => 'Learning Content',
            'AI Learning Activities' => 'Learning Activities',
            'AI Knowledge Check' => 'Knowledge Check',
            'Slideshow' => 'Learning Slides',
        );
        $modfullname = (string)$cm->modfullname;
        $displaytype = isset($activitytypenames[$modfullname]) ? $activitytypenames[$modfullname] : $modfullname;
        $html .= '<div class="aicourse-activity-card-footer">';
        $html .= '<span class="aicourse-activity-card-type">' . $displaytype . '</span>';
        
        // Status badge (compact pill)
        $badgeclass = 'aicourse-status-badge-' . $status;
        $html .= '<span class="aicourse-status-badge ' . $badgeclass . '">';
        if ($status === 'completed') {
            $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        }
        $html .= $statuslabel;
        $html .= '</span>';
        
        $html .= '</div>'; // footer
        $html .= '</div>'; // body
        
        $html .= '</a>';
    }
    
    $html .= '</div>'; // activity-cards-grid
    
    if ($activitycount === 0) {
        $html .= '<div class="aicourse-empty-state">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.4;margin-bottom:12px"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/><path d="M14 4h7"/><path d="M14 9h7"/><path d="M14 15h7"/><path d="M14 20h7"/></svg>';
        $html .= '<p>' . get_string('noactivitiesinsection', 'format_aicourse') . '</p>';
        $html .= '</div>';
    }
    
    $html .= '</div>'; // activity-cards-container
    
    return $html;
}

/**
 * Callback for inplace editable elements
 */
function format_aicourse_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            array($itemid, 'aicourse'), MUST_EXIST);
        
        $format = course_get_format($section->course);
        $course = $format->get_course();
        
        // Check capability
        $context = \context_course::instance($course->id);
        \core_external\external_api::validate_context($context);
        require_capability('moodle/course:update', $context);
        
        // Update section name
        $DB->set_field('course_sections', 'name', $newvalue, array('id' => $section->id));
        
        // Purge course cache
        rebuild_course_cache($course->id, true);
        
        // Get updated section info
        $section = $DB->get_record('course_sections', array('id' => $itemid), '*', MUST_EXIST);
        
        // Return inplace_editable element
        return new \core\output\inplace_editable(
            'format_aicourse',
            $itemtype,
            $section->id,
            true,
            format_string($section->name, true, array('context' => $context)),
            $section->name,
            get_string('newsectionname', 'format_topics'),
            get_string('newsectionname', 'format_topics')
        );
    }
    
    return null;
}

/**
 * Return true if the AI Tutor is enabled globally in plugin settings.
 * Defaults to enabled when the setting has never been saved.
 */
function format_aicourse_is_tutor_enabled(): bool {
    $val = get_config('format_aicourse', 'enabletutor');
    return ($val === false) || !empty($val);
}

/**
 * Render AI Course Assistant chatbox modal (HTML only, no script)
 */
function format_aicourse_render_ai_chatbox_html() {
    global $USER;

    if (!format_aicourse_is_tutor_enabled()) {
        return '';
    }

    // Get user's first name for personalized greeting
    $firstname = !empty($USER->firstname) ? $USER->firstname : 'there';
    
    $html = '<div id="aicourse-ai-chatbox" class="aicourse-ai-chatbox" style="display:none;">';
    $html .= '<div class="aicourse-ai-chatbox-header">';
    $html .= '<div class="aicourse-ai-chatbox-title">';
    $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="m9 15 2 2 4-4"/></svg>';
    $html .= '<span>' . get_string('aiassistant', 'format_aicourse') . '</span>';
    $html .= '</div>';
    $html .= '<button type="button" class="aicourse-ai-chatbox-close" id="aicourse-ai-close">&times;</button>';
    $html .= '</div>';
    $html .= '<div class="aicourse-ai-chatbox-messages" id="aicourse-ai-messages">';
    // Welcome message will be set dynamically by JS with activity context
    $html .= '<div class="aicourse-ai-message aicourse-ai-message-bot" id="aicourse-ai-welcome">';
    $html .= '<div class="aicourse-ai-message-content">' . get_string('aiassistant_welcome_name', 'format_aicourse', $firstname) . '</div>';
    $html .= '</div>';
    // Quick action buttons
    $html .= '<div class="aicourse-ai-quick-actions" id="aicourse-ai-quick-actions">';
    $html .= '<div class="aicourse-ai-quick-label">' . get_string('aiassistant_quick_label', 'format_aicourse') . '</div>';
    $html .= '<div class="aicourse-ai-quick-buttons">';
    $html .= '<button type="button" class="aicourse-ai-quick-btn" data-prompt="structure">';
    $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M3 12h18"/></svg>';
    $html .= get_string('aiassistant_quick_structure', 'format_aicourse');
    $html .= '</button>';
    $html .= '<button type="button" class="aicourse-ai-quick-btn" data-prompt="concepts">';
    $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>';
    $html .= get_string('aiassistant_quick_concepts', 'format_aicourse');
    $html .= '</button>';
    $html .= '<button type="button" class="aicourse-ai-quick-btn" data-prompt="workplace">';
    $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-9M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg>';
    $html .= get_string('aiassistant_quick_workplace', 'format_aicourse');
    $html .= '</button>';
    $html .= '<button type="button" class="aicourse-ai-quick-btn" data-prompt="practice">';
    $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>';
    $html .= get_string('aiassistant_quick_practice', 'format_aicourse');
    $html .= '</button>';
    $html .= '<button type="button" class="aicourse-ai-quick-btn" data-prompt="checklist">';
    $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/></svg>';
    $html .= get_string('aiassistant_quick_checklist', 'format_aicourse');
    $html .= '</button>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="aicourse-ai-chatbox-input">';
    $html .= '<textarea id="aicourse-ai-input" placeholder="' . get_string('aiassistant_placeholder', 'format_aicourse') . '" rows="1"></textarea>';
    $html .= '<button type="button" id="aicourse-ai-send" class="aicourse-ai-send-btn">';
    $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
    $html .= '</button>';
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}

/**
 * Render AI Course Assistant chatbox script
 */
function format_aicourse_render_ai_chatbox_script($course) {
    global $USER, $PAGE;

    if (!format_aicourse_is_tutor_enabled()) {
        return '';
    }

    $ajaxurl = new moodle_url('/course/format/aicourse/ajax.php');
    $firstname = !empty($USER->firstname) ? $USER->firstname : 'there';
    
    // Detect current activity context
    $activityid = 0;
    $activityname = '';
    $activitytype = '';
    $sectionid = 0;
    $sectionname = '';
    
    // Check if we're on an activity page
    if ($PAGE->cm) {
        $activityid = $PAGE->cm->id;
        $activityname = $PAGE->cm->name;
        $activitytype = $PAGE->cm->modname;
        $sectionid = $PAGE->cm->section;
        $sectionname = get_section_name($course, $PAGE->cm->sectionnum);
    } else {
        // Check for section view
        $sectionnum = optional_param('section', 0, PARAM_INT);
        if ($sectionnum > 0) {
            $sectionid = $sectionnum;
            $sectionname = get_section_name($course, $sectionnum);
        }
    }
    
    // Quick prompt mappings
    $quickPrompts = [
        'structure' => get_string('aiassistant_prompt_structure', 'format_aicourse'),
        'concepts' => get_string('aiassistant_prompt_concepts', 'format_aicourse'),
        'workplace' => get_string('aiassistant_prompt_workplace', 'format_aicourse'),
        'practice' => get_string('aiassistant_prompt_practice', 'format_aicourse'),
        'checklist' => get_string('aiassistant_prompt_checklist', 'format_aicourse'),
    ];
    
    // Build context-aware welcome message
    if (!empty($activityname)) {
        $welcomeMsg = get_string('aiassistant_welcome_activity', 'format_aicourse', (object)[
            'name' => $firstname,
            'activity' => $activityname
        ]);
    } else if (!empty($sectionname)) {
        $welcomeMsg = get_string('aiassistant_welcome_section', 'format_aicourse', (object)[
            'name' => $firstname,
            'section' => $sectionname
        ]);
    } else {
        $welcomeMsg = get_string('aiassistant_welcome_name', 'format_aicourse', $firstname);
    }
    
    $script = '<script>
(function () {
    var chatbox = document.getElementById("aicourse-ai-chatbox");
    var closeBtn = document.getElementById("aicourse-ai-close");
    var sendBtn = document.getElementById("aicourse-ai-send");
    var input = document.getElementById("aicourse-ai-input");
    var messages = document.getElementById("aicourse-ai-messages");
    var ajaxUrl = "' . $ajaxurl->out(false) . '";
    var courseid = ' . $course->id . ';
    var sesskey = "' . sesskey() . '";
    var isLoading = false;
    var activityid = ' . $activityid . ';
    var activityname = "' . addslashes($activityname) . '";
    var sectionid = ' . $sectionid . ';
    var quickPrompts = ' . json_encode($quickPrompts) . ';
    var isFirstMessage = true;
    var activitytype = "' . addslashes($activitytype) . '";
    var userid = ' . $USER->id . ';
    
    // Chat memory persistence using sessionStorage (keyed by course+user)
    var chatStorageKey = "aicourse_chat_" + courseid + "_" + userid;
    var chatHistory = [];
    
    function saveChatHistory() {
        try {
            // Keep only last 20 messages to save space (10 exchanges)
            var toSave = chatHistory.slice(-20);
            sessionStorage.setItem(chatStorageKey, JSON.stringify(toSave));
        } catch(e) {
            console.warn("[AI Tutor] Could not save chat history:", e);
        }
    }
    
    function loadChatHistory() {
        try {
            var saved = sessionStorage.getItem(chatStorageKey);
            if (saved) {
                chatHistory = JSON.parse(saved);
                return chatHistory;
            }
        } catch(e) {
            console.warn("[AI Tutor] Could not load chat history:", e);
        }
        return [];
    }
    
    function restoreChatMessages() {
        var history = loadChatHistory();
        if (history.length > 0) {
            isFirstMessage = false; // Not first message if we have history
            history.forEach(function (msg) {
                addMessageFromHistory(msg.content, msg.isUser, msg.chatid);
            });
            hideQuickActions();
        }
    }
    
    function addMessageFromHistory(content, isUser, chatid) {
        if (!messages) messages = document.getElementById("aicourse-ai-messages");
        if (!messages) return;
        var div = document.createElement("div");
        div.className = "aicourse-ai-message " + (isUser ? "aicourse-ai-message-user" : "aicourse-ai-message-bot");
        var html = "<div class=\"aicourse-ai-message-content\">" + content + "</div>";
        // Add rating buttons for AI responses (but no chatid for restored messages)
        if (!isUser && chatid) {
            html += "<div class=\"aicourse-ai-rating aicourse-ai-rating-done\">Restored from history</div>";
        }
        div.innerHTML = html;
        messages.appendChild(div);
    }
    
    // Restore chat history on page load
    setTimeout(restoreChatMessages, 100);
    
    // Activity context awareness - detect current question/content for quiz, aiquiz, assign, etc.
    window.AICOURSE_QUIZ_CONTEXT = null;
    window.AICOURSE_ACTIVITY_CONTEXT = null;
    
    // Fetch activity context from server (questions, instructions, etc.)
    function fetchActivityContext(slot) {
        if (!activityid) return;
        var url = ajaxUrl;
        var body = "action=getactivitycontext&courseid=" + courseid + "&activityid=" + activityid + "&sesskey=" + sesskey;
        if (slot) body += "&questionslot=" + slot;
        
        fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success && data.context) {
                window.AICOURSE_ACTIVITY_CONTEXT = data.context;
                console.log("[AI Tutor] Activity context loaded:", data.context.type, data.context.questions?.length || 0, "questions");
                
                // For assignments, set the intro as context
                if (data.context.type === "assign" && data.context.intro) {
                    window.AICOURSE_QUIZ_CONTEXT = {
                        slot: 0,
                        questionNumber: 0,
                        questionText: data.context.intro.substring(0, 500)
                    };
                }
                
                // If we have a current question from the server, use it
                if (data.context.currentQuestion) {
                    window.AICOURSE_QUIZ_CONTEXT = {
                        slot: data.context.currentQuestion.slot,
                        questionNumber: data.context.currentQuestion.slot,
                        questionText: data.context.currentQuestion.text.substring(0, 500)
                    };
                }
                
                updateWelcomeMessage();
            }
        })
        .catch(function (e) {
            console.warn("[AI Tutor] Failed to fetch activity context:", e);
        });
    }
    
    function updateQuizContext() {
        // Method 1: DOM-based detection for standard Moodle quiz
        var currentBtn = document.querySelector(".qnbutton.current");
        var questionEl = document.querySelector(".que .qtext");
        
        if (currentBtn) {
            var slot = currentBtn.getAttribute("data-slot");
            window.AICOURSE_QUIZ_CONTEXT = {
                slot: slot,
                questionNumber: slot,
                questionText: questionEl ? questionEl.innerText.trim().substring(0, 500) : ""
            };
            
            // If DOM gave us slot but no text, fetch from server
            if (!questionEl || !questionEl.innerText.trim()) {
                fetchActivityContext(slot);
            }
            return;
        }
        
        // Method 2: DOM-based detection for AI Quiz / Knowledge Check
        var aiquizQuestion = document.querySelector(".aiquiz-question-text, .knowledgecheck-question");
        if (aiquizQuestion) {
            var slotEl = document.querySelector("[data-questionslot], [data-slot]");
            var slot = slotEl ? (slotEl.getAttribute("data-questionslot") || slotEl.getAttribute("data-slot")) : "1";
            window.AICOURSE_QUIZ_CONTEXT = {
                slot: slot,
                questionNumber: slot,
                questionText: aiquizQuestion.innerText.trim().substring(0, 500)
            };
            return;
        }
        
        // Method 3: For activities without obvious DOM indicators, use cached server context
        if (window.AICOURSE_ACTIVITY_CONTEXT) {
            return; // Already have context from server
        }
        
        // Clear context if nothing found
        window.AICOURSE_QUIZ_CONTEXT = null;
    }
    
    // Initialize activity context on load for supported types
    var contextTypes = ["quiz", "aiquiz", "assign", "knowledgecheck", "practicalassessment"];
    if (contextTypes.indexOf(activitytype) !== -1 && activityid) {
        // Fetch full context from server on load
        fetchActivityContext(0);
        
        // Also try DOM-based detection
        updateQuizContext();
        
        // Update when navigating between questions (standard quiz)
        document.addEventListener("click", function (e) {
            if (e.target.closest(".qnbutton")) {
                setTimeout(function () {
                    updateQuizContext();
                    var slot = document.querySelector(".qnbutton.current")?.getAttribute("data-slot");
                    if (slot) fetchActivityContext(slot);
                }, 100);
            }
        });
    }
    
    // Update welcome message with activity/question context
    function updateWelcomeMessage() {
        var welcomeEl = document.getElementById("aicourse-ai-welcome");
        if (!welcomeEl) return;
        var contentEl = welcomeEl.querySelector(".aicourse-ai-message-content");
        if (!contentEl) return;
        
        var quizCtx = window.AICOURSE_QUIZ_CONTEXT;
        if (quizCtx && quizCtx.questionNumber) {
            var msg = "I see you\'re on <strong>Question " + quizCtx.questionNumber + "</strong>";
            if (quizCtx.questionText) {
                var topicSnippet = quizCtx.questionText.substring(0, 80);
                if (quizCtx.questionText.length > 80) topicSnippet += "...";
                msg += ". This question is about <em>" + topicSnippet + "</em>";
            }
            msg += ". How can I help you think it through?";
            contentEl.innerHTML = msg;
        } else {
            contentEl.innerHTML = "' . addslashes($welcomeMsg) . '";
        }
    }
    
    // Initial welcome message
    updateWelcomeMessage();
    
    function updateButtonState(isOpen) {
        var btns = document.querySelectorAll(".aicourse-hero-ai-btn");
        btns.forEach(function (btn) {
            if (isOpen) {
                btn.classList.add("active");
                btn.setAttribute("aria-expanded", "true");
            } else {
                btn.classList.remove("active");
                btn.setAttribute("aria-expanded", "false");
            }
        });
    }
    
    function toggleChatbox() {
        if (!chatbox) {
            chatbox = document.getElementById("aicourse-ai-chatbox");
        }
        if (!chatbox) return;
        var isOpen = chatbox.style.display === "none" || chatbox.style.display === "";
        if (isOpen) {
            chatbox.style.display = "flex";
            if (input) input.focus();
            // Refresh quiz context and welcome message when opening
            if (activitytype === "quiz") {
                updateQuizContext();
                updateWelcomeMessage();
            }
        } else {
            chatbox.style.display = "none";
        }
        updateButtonState(isOpen);
    }
    
    // Use event delegation for dynamically injected elements
    document.addEventListener("click", function (e) {
        // Toggle chatbox on AI button click
        var toggleTarget = e.target.closest(".aicourse-ai-toggle, .aicourse-hero-ai-btn");
        if (toggleTarget && !e.target.closest(".aicourse-ai-chatbox-close")) {
            e.preventDefault();
            toggleChatbox();
            return;
        }
        
        // Close chatbox on close button click
        var closeTarget = e.target.closest(".aicourse-ai-chatbox-close, #aicourse-ai-close");
        if (closeTarget) {
            e.preventDefault();
            if (!chatbox) chatbox = document.getElementById("aicourse-ai-chatbox");
            if (chatbox) chatbox.style.display = "none";
            updateButtonState(false);
        }
        
        // Quick action button click
        var quickBtn = e.target.closest(".aicourse-ai-quick-btn");
        if (quickBtn) {
            e.preventDefault();
            var promptKey = quickBtn.getAttribute("data-prompt");
            if (promptKey && quickPrompts[promptKey]) {
                var promptText = quickPrompts[promptKey];
                // Replace activity placeholder with name or fallback
                promptText = promptText.replace("{activity}", activityname || "this activity");
                if (!input) input = document.getElementById("aicourse-ai-input");
                if (input) {
                    input.value = promptText;
                    sendMessage();
                }
            }
        }
    });
    
    function hideQuickActions() {
        var quickActions = document.getElementById("aicourse-ai-quick-actions");
        if (quickActions) {
            quickActions.style.display = "none";
        }
    }
    
    function addMessage(content, isUser, chatid) {
        if (!messages) messages = document.getElementById("aicourse-ai-messages");
        if (!messages) return;
        var div = document.createElement("div");
        div.className = "aicourse-ai-message " + (isUser ? "aicourse-ai-message-user" : "aicourse-ai-message-bot");
        var html = "<div class=\"aicourse-ai-message-content\">" + content + "</div>";
        // Add rating buttons for AI responses
        if (!isUser && chatid) {
            html += "<div class=\"aicourse-ai-rating\" data-chatid=\"" + chatid + "\">";
            html += "<button type=\"button\" class=\"aicourse-ai-rate-btn\" data-rate=\"1\" title=\"Helpful\">&#128077;</button>";
            html += "<button type=\"button\" class=\"aicourse-ai-rate-btn\" data-rate=\"-1\" title=\"Not helpful\">&#128078;</button>";
            html += "</div>";
        }
        div.innerHTML = html;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        // Hide quick actions after first message
        if (isUser) hideQuickActions();
        
        // Save to chat history for persistence across page navigation
        chatHistory.push({ content: content, isUser: isUser, chatid: chatid });
        saveChatHistory();
    }
    
    function showLoading() {
        if (!messages) messages = document.getElementById("aicourse-ai-messages");
        if (!messages) return;
        var div = document.createElement("div");
        div.className = "aicourse-ai-message aicourse-ai-message-bot aicourse-ai-loading";
        div.id = "aicourse-ai-loading";
        div.innerHTML = "<div class=\"aicourse-ai-message-content\"><span class=\"aicourse-ai-dots\"><span></span><span></span><span></span></span></div>";
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }
    
    function hideLoading() {
        var loading = document.getElementById("aicourse-ai-loading");
        if (loading) loading.remove();
    }
    
    function sendMessage() {
        if (!input) input = document.getElementById("aicourse-ai-input");
        if (!messages) messages = document.getElementById("aicourse-ai-messages");
        if (!input || !messages) return;
        
        var question = input.value.trim();
        if (!question || isLoading) return;
        
        addMessage(question, true);
        input.value = "";
        input.style.height = "auto";
        isLoading = true;
        showLoading();
        
        var body = "action=aichat&courseid=" + courseid + "&sesskey=" + sesskey + "&question=" + encodeURIComponent(question);
        body += "&activityid=" + activityid + "&sectionid=" + sectionid;
        body += "&isfirstmessage=" + (isFirstMessage ? "1" : "0");
        isFirstMessage = false;
        
        // Add quiz question context if available (current question from DOM detection)
        if (window.AICOURSE_QUIZ_CONTEXT) {
            body += "&questionslot=" + (window.AICOURSE_QUIZ_CONTEXT.questionNumber || "");
            body += "&questiontext=" + encodeURIComponent(window.AICOURSE_QUIZ_CONTEXT.questionText || "");
        }
        
        // Add all questions from activity context (fetched from server)
        if (window.AICOURSE_ACTIVITY_CONTEXT && window.AICOURSE_ACTIVITY_CONTEXT.questions && window.AICOURSE_ACTIVITY_CONTEXT.questions.length > 0) {
            var allQuestions = window.AICOURSE_ACTIVITY_CONTEXT.questions.map(function (q) {
                return "Q" + q.slot + ": " + q.text.substring(0, 200);
            }).join(" | ");
            body += "&allquestions=" + encodeURIComponent(allQuestions);
        }
        
        fetch(ajaxUrl, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: body
        })
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            hideLoading();
            isLoading = false;
            if (data.success) {
                addMessage(data.answer, false, data.chatid);
            } else {
                addMessage(data.error || "' . get_string('aiassistant_error', 'format_aicourse') . '", false);
            }
        })
        .catch(function (err) {
            hideLoading();
            isLoading = false;
            addMessage("' . get_string('aiassistant_error', 'format_aicourse') . '", false);
        });
    }
    
    // Use event delegation for send button click
    document.addEventListener("click", function (e) {
        var sendTarget = e.target.closest("#aicourse-ai-send, .aicourse-ai-send-btn");
        if (sendTarget) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    // Use event delegation for keydown on input
    document.addEventListener("keydown", function (e) {
        if (e.target && e.target.id === "aicourse-ai-input") {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        }
    });
    
    // Use event delegation for auto-resize on input
    document.addEventListener("input", function (e) {
        if (e.target && e.target.id === "aicourse-ai-input") {
            e.target.style.height = "auto";
            e.target.style.height = Math.min(e.target.scrollHeight, 100) + "px";
        }
    });
    
    // Handle rating button clicks
    document.addEventListener("click", function (e) {
        var rateBtn = e.target.closest(".aicourse-ai-rate-btn");
        if (!rateBtn) return;
        
        var ratingDiv = rateBtn.closest(".aicourse-ai-rating");
        if (!ratingDiv) return;
        
        var chatid = ratingDiv.getAttribute("data-chatid");
        var rating = rateBtn.getAttribute("data-rate");
        if (!chatid || !rating) return;
        
        // Send rating to server
        fetch(ajaxUrl, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "action=ratechat&courseid=" + courseid + "&sesskey=" + sesskey + "&chatid=" + chatid + "&rating=" + rating
        });
        
        // Replace buttons with thanks message
        ratingDiv.innerHTML = "' . get_string('aiassistant_rating_thanks', 'format_aicourse') . '";
        ratingDiv.className = "aicourse-ai-rating-done";
    });
})();
</script>';
    
    return $script;
}

/**
 * Render AI Course Assistant chatbox modal (HTML + script together)
 * Used when echoing directly (e.g., format.php)
 */
function format_aicourse_render_ai_chatbox($course) {
    return format_aicourse_render_ai_chatbox_html() . format_aicourse_render_ai_chatbox_script($course);
}

/**
 * Extract text content from a file resource
 * Handles PDF, Word (.docx), text files, HTML, etc.
 */
function format_aicourse_extract_file_content($cm, $maxchars = 3000) {
    global $CFG;
    
    $fs = get_file_storage();
    $context = context_module::instance($cm->id);
    $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
    
    $content = '';
    foreach ($files as $file) {
        if ($file->is_directory()) continue;
        
        $filename = $file->get_filename();
        $mimetype = $file->get_mimetype();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Handle different file types
        if (in_array($extension, ['txt', 'md', 'csv', 'html', 'htm', 'xml', 'json'])) {
            // Plain text files - read directly
            $text = $file->get_content();
            $content .= ' [File: ' . $filename . '] ' . strip_tags($text);
        } elseif ($extension === 'pdf') {
            // PDF files - extract text using simple method
            $content .= format_aicourse_extract_pdf_text($file, $maxchars);
        } elseif ($extension === 'docx') {
            // Word documents - extract text from XML
            $content .= format_aicourse_extract_docx_text($file, $maxchars);
        } elseif (in_array($extension, ['doc', 'odt', 'rtf'])) {
            // Other document formats - just note the filename
            $content .= ' [Document: ' . $filename . '] ';
        } elseif (in_array($extension, ['pptx', 'ppt', 'odp'])) {
            // Presentations - note the filename
            $content .= ' [Presentation: ' . $filename . '] ';
        } elseif (in_array($extension, ['xlsx', 'xls', 'ods'])) {
            // Spreadsheets - note the filename  
            $content .= ' [Spreadsheet: ' . $filename . '] ';
        } else {
            // Other files - note the filename
            $content .= ' [File: ' . $filename . '] ';
        }
        
        // Limit content per file
        if (strlen($content) > $maxchars) {
            $content = substr($content, 0, $maxchars) . '...';
            break;
        }
    }
    
    return $content;
}

/**
 * Extract text content from files in a folder
 */
function format_aicourse_extract_folder_content($cm, $maxchars = 2000) {
    $fs = get_file_storage();
    $context = context_module::instance($cm->id);
    $files = $fs->get_area_files($context->id, 'mod_folder', 'content', 0, 'sortorder, filename', false);
    
    $content = '';
    $filecount = 0;
    foreach ($files as $file) {
        if ($file->is_directory()) continue;
        $filecount++;
        
        $filename = $file->get_filename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // For text files, try to read content
        if (in_array($extension, ['txt', 'md', 'csv', 'html', 'htm'])) {
            $text = $file->get_content();
            $content .= ' [' . $filename . ': ' . strip_tags(substr($text, 0, 500)) . '] ';
        } else {
            $content .= ' [File: ' . $filename . '] ';
        }
        
        if (strlen($content) > $maxchars) break;
        if ($filecount > 20) break; // Limit to 20 files
    }
    
    return $content;
}

/**
 * Extract text from PDF file (simple extraction)
 */
function format_aicourse_extract_pdf_text($file, $maxchars = 2000) {
    try {
        $content = $file->get_content();
        $text = '';
        
        // Simple PDF text extraction - look for text streams
        // This is a basic approach that works for many PDFs
        if (preg_match_all('/\(([^)]+)\)/', $content, $matches)) {
            foreach ($matches[1] as $match) {
                // Filter out binary/encoded content
                if (ctype_print($match) && strlen($match) > 2) {
                    $text .= $match . ' ';
                }
                if (strlen($text) > $maxchars) break;
            }
        }
        
        // If we got meaningful text, return it
        if (strlen(trim($text)) > 50) {
            return ' [PDF content: ' . substr(trim($text), 0, $maxchars) . '] ';
        }
        
        // Fallback - just note the filename
        return ' [PDF: ' . $file->get_filename() . '] ';
    } catch (Exception $e) {
        return ' [PDF: ' . $file->get_filename() . '] ';
    }
}

/**
 * Extract text from Word .docx file
 */
function format_aicourse_extract_docx_text($file, $maxchars = 2000) {
    try {
        $content = $file->get_content();
        $text = '';
        
        // DOCX is a ZIP file - try to extract document.xml
        $tempfile = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tempfile, $content);
        
        $zip = new ZipArchive();
        if ($zip->open($tempfile) === true) {
            $xml = $zip->getFromName('word/document.xml');
            if ($xml) {
                // Strip XML tags to get plain text
                $text = strip_tags($xml);
                // Clean up whitespace
                $text = preg_replace('/\s+/', ' ', $text);
            }
            $zip->close();
        }
        unlink($tempfile);
        
        if (strlen(trim($text)) > 50) {
            return ' [Word content: ' . substr(trim($text), 0, $maxchars) . '] ';
        }
        
        return ' [Word: ' . $file->get_filename() . '] ';
    } catch (Exception $e) {
        return ' [Word: ' . $file->get_filename() . '] ';
    }
}

/**
 * Purge the cross-request course content cache for a specific course.
 * Call this whenever course content changes (module added/deleted/updated).
 */
function format_aicourse_purge_content_cache($courseid) {
    try {
        $mucache = cache::make('format_aicourse', 'coursecontent');
        // Purge all user-keyed entries for this course by iterating possible keys.
        // Since we can't enumerate keys, purge the whole store when content changes.
        $mucache->purge();
    } catch (Exception $e) {
        // Cache may not be available yet (e.g. during install). Silently continue.
    }
}

function format_aicourse_get_course_content_for_ai($course) {
    global $DB, $USER;

    $cachekey = (int)$course->id . '_' . (int)$USER->id;

    // Layer 1: static in-request cache — zero overhead for repeated calls within one request.
    static $requestcache = [];
    if (isset($requestcache[$cachekey])) {
        return $requestcache[$cachekey];
    }

    // Layer 2: Moodle MUC cross-request cache (10-minute TTL, defined in db/caches.php).
    // This is the primary win: the first chat message builds the index; every subsequent
    // chat message in the same session (and across sessions within 10 min) pays zero DB cost.
    try {
        $mucache = cache::make('format_aicourse', 'coursecontent');
        $cached = $mucache->get($cachekey);
        if ($cached !== false && is_array($cached)) {
            $requestcache[$cachekey] = $cached;
            return $cached;
        }
    } catch (Exception $e) {
        $mucache = null; // Cache unavailable — continue without it.
    }
    
    $content = [];
    $content['course_name'] = format_string($course->fullname);
    $content['course_summary'] = format_string($course->summary);
    $content['sections'] = [];
    $content['activities'] = [];
    
    $modinfo = get_fast_modinfo($course, $USER->id);
    
    // Get sections
    foreach ($modinfo->get_section_info_all() as $section) {
        if (!$section->visible) continue;
        $sectiondata = [
            'name' => get_section_name($course, $section),
            'summary' => format_string($section->summary)
        ];
        $content['sections'][] = $sectiondata;
    }
    
    // Get activities
    foreach ($modinfo->get_cms() as $cm) {
        if (!$cm->uservisible || !$cm->url) continue;
        
        $activitydata = [
            'name' => format_string($cm->name),
            'type' => $cm->modname,
            'content' => ''
        ];
        
        // Get content from different module types
        switch ($cm->modname) {
            case 'page':
                $page = $DB->get_record('page', ['id' => $cm->instance]);
                if ($page) {
                    $activitydata['content'] = strip_tags($page->content);
                }
                break;
                
            case 'book':
                $chapters = $DB->get_records('book_chapters', ['bookid' => $cm->instance], 'pagenum ASC');
                $booktext = '';
                foreach ($chapters as $chapter) {
                    $booktext .= strip_tags($chapter->title) . ': ' . strip_tags($chapter->content) . ' ';
                }
                $activitydata['content'] = $booktext;
                break;
                
            case 'label':
                $label = $DB->get_record('label', ['id' => $cm->instance]);
                if ($label) {
                    $activitydata['content'] = strip_tags($label->intro);
                }
                break;
                
            case 'lesson':
                $pages = $DB->get_records('lesson_pages', ['lessonid' => $cm->instance]);
                $lessontext = '';
                foreach ($pages as $page) {
                    $lessontext .= strip_tags($page->title) . ': ' . strip_tags($page->contents) . ' ';
                }
                $activitydata['content'] = $lessontext;
                break;
                
            case 'contentcreator':
                $record = $DB->get_record('contentcreator', ['id' => $cm->instance]);
                if ($record) {
                    $activitydata['content'] = strip_tags($record->intro ?? '');
                    if (!empty($record->manifestjson)) {
                        $manifest = json_decode($record->manifestjson, true);
                        if (is_array($manifest)) {
                            $activitydata['content'] .= format_aicourse_extract_cc_manifest($manifest);
                        }
                    }
                }
                break;
                
            case 'aiknowledgecheck':
                $record = $DB->get_record('aiknowledgecheck', ['id' => $cm->instance]);
                if ($record) {
                    $activitydata['content'] = strip_tags($record->intro ?? '');
                    $questions = $DB->get_records('aiknowledgecheck_questions', ['aiknowledgecheckid' => $cm->instance]);
                    $qnum = 1;
                    foreach ($questions as $q) {
                        $activitydata['content'] .= "\nQ{$qnum}: " . strip_tags($q->questiontext ?? '');
                        for ($i = 1; $i <= 4; $i++) {
                            $afield = "answer{$i}";
                            if (!empty($q->$afield)) {
                                $marker = ((int)($q->correctanswer ?? 0) === $i) ? ' [CORRECT]' : '';
                                $activitydata['content'] .= " | Option {$i}: " . strip_tags($q->$afield) . $marker;
                            }
                        }
                        for ($i = 1; $i <= 4; $i++) {
                            $efield = "feedback{$i}";
                            if (!empty($q->$efield)) {
                                $activitydata['content'] .= " | Explanation {$i}: " . strip_tags($q->$efield);
                            }
                        }
                        $qnum++;
                    }
                }
                break;
                
            case 'knowledgecheck':
                $record = $DB->get_record('aiknowledgecheck', ['id' => $cm->instance]);
                if ($record) {
                    $activitydata['content'] = strip_tags($record->intro ?? '');
                    $questions = $DB->get_records('aiknowledgecheck_questions', ['aiknowledgecheckid' => $cm->instance]);
                    $qnum = 1;
                    foreach ($questions as $q) {
                        $activitydata['content'] .= "\nQ{$qnum}: " . strip_tags($q->questiontext ?? '');
                        for ($i = 1; $i <= 4; $i++) {
                            $afield = "answer{$i}";
                            if (!empty($q->$afield)) {
                                $marker = ((int)($q->correctanswer ?? 0) === $i) ? ' [CORRECT]' : '';
                                $activitydata['content'] .= " | Option {$i}: " . strip_tags($q->$afield) . $marker;
                            }
                        }
                        for ($i = 1; $i <= 4; $i++) {
                            $efield = "feedback{$i}";
                            if (!empty($q->$efield)) {
                                $activitydata['content'] .= " | Explanation {$i}: " . strip_tags($q->$efield);
                            }
                        }
                        $qnum++;
                    }
                }
                break;
                
            case 'aiactivities':
                $record = $DB->get_record('aiactivities', ['id' => $cm->instance]);
                if ($record) {
                    $activitydata['content'] = strip_tags($record->intro ?? '');
                    if (!empty($record->activitiesjson)) {
                        $activities = json_decode($record->activitiesjson, true);
                        if (is_array($activities)) {
                            $activitydata['content'] .= format_aicourse_extract_aiactivities($activities);
                        }
                    }
                }
                break;
                
            case 'aivideoactivity':
                $record = $DB->get_record('aivideoactivity', ['id' => $cm->instance]);
                if ($record) {
                    $activitydata['content'] = strip_tags($record->intro ?? '');
                    if (!empty($record->transcripttext)) {
                        $activitydata['content'] .= "\n[TRANSCRIPT]: " . substr(strip_tags($record->transcripttext), 0, 5000);
                    }
                    $vaquestions = $DB->get_records('aivideoactivity_questions', ['aivideoactivityid' => $cm->instance]);
                    $qnum = 1;
                    foreach ($vaquestions as $vq) {
                        if (!empty($vq->questiondata)) {
                            $qdata = json_decode($vq->questiondata, true);
                            if (is_array($qdata)) {
                                $activitydata['content'] .= format_aicourse_extract_va_question($qdata, $qnum);
                            }
                        } else if (!empty($vq->questiontext)) {
                            $activitydata['content'] .= "\nQ{$qnum}: " . strip_tags($vq->questiontext);
                            for ($i = 1; $i <= 4; $i++) {
                                $af = "answer{$i}";
                                if (!empty($vq->$af)) {
                                    $marker = ((int)($vq->correctanswer ?? 0) === $i) ? ' [CORRECT]' : '';
                                    $activitydata['content'] .= " | Option {$i}: " . strip_tags($vq->$af) . $marker;
                                }
                            }
                        }
                        $qnum++;
                    }
                }
                break;
                
            case 'aiquiz':
                // AI Quiz module
                $record = $DB->get_record('aiquiz', ['id' => $cm->instance]);
                if ($record) {
                    $activitydata['content'] = strip_tags($record->intro ?? '');
                    // Get quiz questions
                    $questions = $DB->get_records('aiquiz_questions', ['aiquizid' => $cm->instance]);
                    foreach ($questions as $q) {
                        $activitydata['content'] .= ' Q: ' . strip_tags($q->questiontext ?? '') . ' ';
                    }
                }
                break;
                
            case 'practicalassessment':
                // AI Practical Assessment module
                $record = $DB->get_record('practicalassessment', ['id' => $cm->instance]);
                if ($record) {
                    $activitydata['content'] = strip_tags($record->intro ?? '');
                    // Get assessment criteria
                    $criteria = $DB->get_records('practicalassessment_criteria', ['practicalassessmentid' => $cm->instance]);
                    foreach ($criteria as $c) {
                        $activitydata['content'] .= ' Criterion: ' . strip_tags($c->criteriontext ?? '') . ' ';
                    }
                }
                break;
                
            case 'assign':
                // Moodle Assignment - get instructions and activity completion settings
                $assign = $DB->get_record('assign', ['id' => $cm->instance]);
                if ($assign) {
                    $activitydata['content'] = strip_tags($assign->intro ?? '');
                    if (!empty($assign->activity)) {
                        $activitydata['content'] .= ' ' . strip_tags($assign->activity);
                    }
                }
                break;
                
            case 'resource':
                // File resource - get description and try to extract text from files
                $resource = $DB->get_record('resource', ['id' => $cm->instance]);
                if ($resource) {
                    $activitydata['content'] = strip_tags($resource->intro ?? '');
                    // Get file content if it's a text-based file
                    $activitydata['content'] .= format_aicourse_extract_file_content($cm);
                }
                break;
                
            case 'folder':
                // Folder - get description and list files
                $folder = $DB->get_record('folder', ['id' => $cm->instance]);
                if ($folder) {
                    $activitydata['content'] = strip_tags($folder->intro ?? '');
                    // Try to extract content from files in folder
                    $activitydata['content'] .= format_aicourse_extract_folder_content($cm);
                }
                break;
                
            case 'url':
                // URL resource - get name and description
                $url = $DB->get_record('url', ['id' => $cm->instance]);
                if ($url) {
                    $activitydata['content'] = strip_tags($url->intro ?? '');
                    $activitydata['content'] .= ' URL: ' . $url->externalurl;
                }
                break;
                
            case 'glossary':
                // Glossary - get terms and definitions
                $glossary = $DB->get_record('glossary', ['id' => $cm->instance]);
                if ($glossary) {
                    $activitydata['content'] = strip_tags($glossary->intro ?? '');
                    // Limit to 50 entries to avoid bloating the AI context payload on large glossaries.
                    $entries = $DB->get_records('glossary_entries', ['glossaryid' => $cm->instance], 'timemodified DESC', 'id,concept,definition', 0, 50);
                    foreach ($entries as $entry) {
                        $activitydata['content'] .= ' Term: ' . strip_tags($entry->concept) . ' - ' . strip_tags($entry->definition) . ' ';
                    }
                }
                break;
                
            case 'wiki':
                // Wiki - get all pages
                $wiki = $DB->get_record('wiki', ['id' => $cm->instance]);
                if ($wiki) {
                    $activitydata['content'] = strip_tags($wiki->intro ?? '');
                    // Get wiki subwikis and pages
                    $subwikis = $DB->get_records('wiki_subwikis', ['wikiid' => $cm->instance]);
                    foreach ($subwikis as $subwiki) {
                        $pages = $DB->get_records('wiki_pages', ['subwikiid' => $subwiki->id], '', 'id,title,cachedcontent');
                        foreach ($pages as $page) {
                            $activitydata['content'] .= ' Page: ' . strip_tags($page->title) . ' - ' . strip_tags($page->cachedcontent ?? '') . ' ';
                        }
                    }
                }
                break;
                
            case 'forum':
                // Forum - get intro and recent discussions
                $forum = $DB->get_record('forum', ['id' => $cm->instance]);
                if ($forum) {
                    $activitydata['content'] = 'Forum: ' . strip_tags($forum->intro ?? '');
                    // Get recent discussion topics (not all posts to avoid too much content)
                    $discussions = $DB->get_records('forum_discussions', ['forum' => $cm->instance], 'timemodified DESC', 'id,name', 0, 10);
                    foreach ($discussions as $disc) {
                        $activitydata['content'] .= ' Topic: ' . strip_tags($disc->name) . ' ';
                    }
                }
                break;
                
            case 'quiz':
                $quiz = $DB->get_record('quiz', ['id' => $cm->instance]);
                if ($quiz) {
                    $activitydata['content'] = strip_tags($quiz->intro ?? '');
                    // Batch load all questions for this quiz in a single JOIN query.
                    // Works for both Moodle 4.x (question_references) and 3.x (questionid column).
                    $quizcontext = context_module::instance($cm->id);
                    $quizquestions = [];
                    if ($DB->get_manager()->table_exists('question_references')) {
                        // Moodle 4.x: one query for all slots via JOIN.
                        $sql = "SELECT qs.slot, q.id, q.questiontext, q.qtype
                                  FROM {quiz_slots} qs
                                  JOIN {question_references} qr
                                    ON qr.component = 'mod_quiz'
                                   AND qr.questionarea = 'slot'
                                   AND qr.usingcontextid = :ctxid
                                   AND qr.itemid = qs.id
                                  JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                                  JOIN {question} q ON q.id = qv.questionid
                                 WHERE qs.quizid = :quizid
                              ORDER BY qs.slot ASC, qv.version DESC";
                        $quizquestions = $DB->get_records_sql($sql, [
                            'ctxid'  => $quizcontext->id,
                            'quizid' => $cm->instance,
                        ]);
                    } else {
                        // Moodle 3.x fallback: questionid column on quiz_slots.
                        $sql = "SELECT qs.slot, q.id, q.questiontext, q.qtype
                                  FROM {quiz_slots} qs
                                  JOIN {question} q ON q.id = qs.questionid
                                 WHERE qs.quizid = :quizid
                              ORDER BY qs.slot ASC";
                        $quizquestions = $DB->get_records_sql($sql, ['quizid' => $cm->instance]);
                    }
                    // Batch load multichoice answers + essay options in one query each.
                    $qids = array_column(array_values($quizquestions), 'id');
                    $allanswers = [];
                    $allessayopts = [];
                    if (!empty($qids)) {
                        list($insql, $inparams) = $DB->get_in_or_equal($qids, SQL_PARAMS_NAMED, 'qid');
                        $allanswers = $DB->get_records_select(
                            'question_answers',
                            "question $insql",
                            $inparams,
                            'question, id ASC',
                            'id,question,answer,fraction,feedback'
                        );
                        $allessayopts = $DB->get_records_select(
                            'qtype_essay_options',
                            "questionid $insql",
                            $inparams,
                            '',
                            'questionid,graderinfo'
                        );
                    }
                    // Index answers and essay opts by question id.
                    $answersbyq = [];
                    foreach ($allanswers as $ans) {
                        $answersbyq[$ans->question][] = $ans;
                    }
                    $qnum = 1;
                    // Deduplicate by slot (JOIN may return multiple versions).
                    $seenslots = [];
                    foreach ($quizquestions as $question) {
                        if (isset($seenslots[$question->slot])) {
                            continue;
                        }
                        $seenslots[$question->slot] = true;
                        $activitydata['content'] .= "\nQ{$qnum}: " . strip_tags($question->questiontext ?? '');
                        if ($question->qtype === 'essay') {
                            if (isset($allessayopts[$question->id]) && !empty($allessayopts[$question->id]->graderinfo)) {
                                $activitydata['content'] .= "\n[ESSAY MARKING GUIDE]: " . strip_tags($allessayopts[$question->id]->graderinfo);
                            }
                        } else if ($question->qtype === 'multichoice' && !empty($answersbyq[$question->id])) {
                            $optnum = 1;
                            foreach ($answersbyq[$question->id] as $ans) {
                                $marker = ((float)($ans->fraction ?? 0) > 0) ? ' [CORRECT]' : '';
                                $activitydata['content'] .= " | Option {$optnum}: " . strip_tags($ans->answer ?? '') . $marker;
                                if (!empty($ans->feedback)) {
                                    $activitydata['content'] .= " (Feedback: " . strip_tags($ans->feedback) . ")";
                                }
                                $optnum++;
                            }
                        }
                        $qnum++;
                    }
                }
                break;
                
            case 'h5pactivity':
                // H5P interactive content
                $h5p = $DB->get_record('h5pactivity', ['id' => $cm->instance]);
                if ($h5p) {
                    $activitydata['content'] = strip_tags($h5p->intro ?? '');
                }
                break;
                
            case 'scorm':
                // SCORM package
                $scorm = $DB->get_record('scorm', ['id' => $cm->instance]);
                if ($scorm) {
                    $activitydata['content'] = strip_tags($scorm->intro ?? '');
                }
                break;
                
            case 'data':
                // Database activity - get fields and some entries
                $data = $DB->get_record('data', ['id' => $cm->instance]);
                if ($data) {
                    $activitydata['content'] = strip_tags($data->intro ?? '');
                    // Get field names
                    $fields = $DB->get_records('data_fields', ['dataid' => $cm->instance], '', 'id,name,description');
                    foreach ($fields as $field) {
                        $activitydata['content'] .= ' Field: ' . strip_tags($field->name) . ' ';
                    }
                }
                break;
                
            case 'choice':
                // Choice activity - get options
                $choice = $DB->get_record('choice', ['id' => $cm->instance]);
                if ($choice) {
                    $activitydata['content'] = strip_tags($choice->intro ?? '');
                    $options = $DB->get_records('choice_options', ['choiceid' => $cm->instance]);
                    foreach ($options as $option) {
                        $activitydata['content'] .= ' Option: ' . strip_tags($option->text ?? '') . ' ';
                    }
                }
                break;
                
            case 'feedback':
                // Feedback activity - get items
                $feedback = $DB->get_record('feedback', ['id' => $cm->instance]);
                if ($feedback) {
                    $activitydata['content'] = strip_tags($feedback->intro ?? '');
                    $items = $DB->get_records('feedback_item', ['feedback' => $cm->instance], 'position', 'id,name,label');
                    foreach ($items as $item) {
                        $activitydata['content'] .= ' Question: ' . strip_tags($item->name ?? $item->label ?? '') . ' ';
                    }
                }
                break;
                
            case 'survey':
                // Survey activity
                $survey = $DB->get_record('survey', ['id' => $cm->instance]);
                if ($survey) {
                    $activitydata['content'] = strip_tags($survey->intro ?? '');
                }
                break;
                
            case 'workshop':
                // Workshop activity
                $workshop = $DB->get_record('workshop', ['id' => $cm->instance]);
                if ($workshop) {
                    $activitydata['content'] = strip_tags($workshop->intro ?? '');
                    if (!empty($workshop->instructauthors)) {
                        $activitydata['content'] .= ' Instructions: ' . strip_tags($workshop->instructauthors);
                    }
                }
                break;
                
            case 'chat':
                // Chat activity
                $chat = $DB->get_record('chat', ['id' => $cm->instance]);
                if ($chat) {
                    $activitydata['content'] = strip_tags($chat->intro ?? '');
                }
                break;
                
            case 'lti':
                // External tool (LTI)
                $lti = $DB->get_record('lti', ['id' => $cm->instance]);
                if ($lti) {
                    $activitydata['content'] = strip_tags($lti->intro ?? '');
                }
                break;
                
            default:
                // Generic intro content for other modules
                $modtable = $cm->modname;
                if ($DB->get_manager()->table_exists($modtable)) {
                    $record = $DB->get_record($modtable, ['id' => $cm->instance]);
                    if ($record && isset($record->intro)) {
                        $activitydata['content'] = strip_tags($record->intro);
                    }
                }
        }
        
        // Trim content to reasonable length (8KB per activity to capture full detail)
        if (strlen($activitydata['content']) > 8000) {
            $activitydata['content'] = substr($activitydata['content'], 0, 8000) . '...[content truncated]';
        }
        
        $content['activities'][] = $activitydata;
    }
    
    // Populate both cache layers before returning.
    $requestcache[$cachekey] = $content;
    if (isset($mucache) && $mucache !== null) {
        try {
            $mucache->set($cachekey, $content);
        } catch (Exception $e) {
            // Cache write failure is non-fatal.
        }
    }
    return $content;
}

/**
 * Extract learning content from a Content Creator manifest JSON.
 * Walks the topics → slides → cards structure to build a text summary.
 */
function format_aicourse_extract_cc_manifest($manifest) {
    $text = '';
    $topics = $manifest['topics'] ?? $manifest;
    if (!is_array($topics)) {
        return $text;
    }
    foreach ($topics as $ti => $topic) {
        if (!is_array($topic)) continue;
        $topicTitle = $topic['topicTitle'] ?? $topic['title'] ?? $topic['name'] ?? ('Topic ' . ($ti + 1));
        $text .= "\n[TOPIC]: " . strip_tags($topicTitle);
        $slides = $topic['slides'] ?? $topic['sections'] ?? $topic['cards'] ?? [];
        if (!is_array($slides)) continue;
        foreach ($slides as $si => $slide) {
            if (!is_array($slide)) continue;
            $slideTitle = $slide['title'] ?? $slide['heading'] ?? '';
            $slideType = $slide['type'] ?? $slide['slideType'] ?? 'learning';
            if (!empty($slideTitle)) {
                $text .= "\n  Slide " . ($si + 1) . " ({$slideType}): " . strip_tags($slideTitle);
            }
            // Learning card content
            $body = $slide['body'] ?? $slide['content'] ?? $slide['text'] ?? '';
            if (!empty($body) && is_string($body)) {
                $text .= " — " . substr(strip_tags($body), 0, 500);
            }
            // Key points / bullet points
            $points = $slide['keyPoints'] ?? $slide['bulletPoints'] ?? $slide['points'] ?? [];
            if (is_array($points) && !empty($points)) {
                foreach ($points as $pt) {
                    if (is_string($pt)) {
                        $text .= " • " . strip_tags($pt);
                    } else if (is_array($pt)) {
                        $text .= " • " . strip_tags($pt['text'] ?? $pt['content'] ?? '');
                    }
                }
            }
            // Activity cards (quiz-style within CC)
            $question = $slide['question'] ?? $slide['activityQuestion'] ?? '';
            if (!empty($question)) {
                $text .= "\n    Activity Q: " . strip_tags($question);
            }
            $options = $slide['options'] ?? $slide['answers'] ?? $slide['choices'] ?? [];
            if (is_array($options)) {
                foreach ($options as $oi => $opt) {
                    if (is_string($opt)) {
                        $text .= " | " . strip_tags($opt);
                    } else if (is_array($opt)) {
                        $text .= " | " . strip_tags($opt['text'] ?? $opt['label'] ?? '');
                    }
                }
            }
            $correctAnswer = $slide['correctAnswer'] ?? $slide['answer'] ?? '';
            if (!empty($correctAnswer) && is_string($correctAnswer)) {
                $text .= " [ANSWER: " . strip_tags($correctAnswer) . "]";
            }
            // Documents linked to slide
            $docs = $slide['documents'] ?? $slide['linkedDocs'] ?? [];
            if (is_array($docs)) {
                foreach ($docs as $doc) {
                    if (is_array($doc)) {
                        $docTitle = $doc['title'] ?? $doc['name'] ?? '';
                        $docContent = $doc['content'] ?? $doc['text'] ?? '';
                        if (!empty($docTitle)) {
                            $text .= "\n    Doc: " . strip_tags($docTitle);
                        }
                        if (!empty($docContent)) {
                            $text .= " — " . substr(strip_tags($docContent), 0, 300);
                        }
                    }
                }
            }
        }
    }
    return $text;
}

/**
 * Extract learning content from AI Activities JSON (all 8+ activity types).
 */
function format_aicourse_extract_aiactivities($activities) {
    $text = '';
    if (isset($activities['activities'])) {
        $activities = $activities['activities'];
    }
    if (!is_array($activities)) {
        return $text;
    }
    foreach ($activities as $ai => $activity) {
        if (!is_array($activity)) continue;
        $type = $activity['type'] ?? $activity['activityType'] ?? 'unknown';
        $title = $activity['title'] ?? $activity['name'] ?? ('Activity ' . ($ai + 1));
        $instruction = $activity['instruction'] ?? $activity['instructions'] ?? '';
        $text .= "\n[ACTIVITY {$type}]: " . strip_tags($title);
        if (!empty($instruction)) {
            $text .= " — " . strip_tags($instruction);
        }
        // Items (ordering, flashcards, fill-in-blank, etc.)
        $items = $activity['items'] ?? $activity['cards'] ?? $activity['statements'] ?? $activity['pairs'] ?? $activity['questions'] ?? [];
        if (is_array($items)) {
            foreach ($items as $ii => $item) {
                if (is_string($item)) {
                    $text .= "\n  " . ($ii + 1) . ". " . strip_tags($item);
                } else if (is_array($item)) {
                    // Flashcards: front/back
                    $front = $item['front'] ?? $item['term'] ?? $item['question'] ?? $item['text'] ?? $item['statement'] ?? '';
                    $back = $item['back'] ?? $item['definition'] ?? $item['answer'] ?? $item['explanation'] ?? '';
                    if (!empty($front)) {
                        $text .= "\n  " . ($ii + 1) . ". " . strip_tags($front);
                    }
                    if (!empty($back)) {
                        $text .= " → " . strip_tags($back);
                    }
                    // Matching pairs
                    $left = $item['left'] ?? $item['prompt'] ?? '';
                    $right = $item['right'] ?? $item['match'] ?? '';
                    if (!empty($left) && !empty($right)) {
                        $text .= "\n  " . ($ii + 1) . ". " . strip_tags($left) . " ↔ " . strip_tags($right);
                    }
                    // Card select options
                    $label = $item['label'] ?? $item['cardText'] ?? '';
                    $isCorrect = $item['isCorrect'] ?? $item['correct'] ?? null;
                    if (!empty($label)) {
                        $marker = ($isCorrect === true || $isCorrect === 1) ? ' [CORRECT]' : '';
                        $text .= "\n  " . ($ii + 1) . ". " . strip_tags($label) . $marker;
                    }
                    // True/False
                    $tf = $item['isTrue'] ?? null;
                    if ($tf !== null && !empty($front)) {
                        $text .= ($tf ? ' [TRUE]' : ' [FALSE]');
                    }
                    // Fill in blank
                    $blank = $item['blank'] ?? $item['blankAnswer'] ?? '';
                    if (!empty($blank)) {
                        $text .= " [BLANK: " . strip_tags($blank) . "]";
                    }
                }
            }
        }
        // Categories (category sort, column sort)
        $categories = $activity['categories'] ?? $activity['columns'] ?? [];
        if (is_array($categories)) {
            foreach ($categories as $cat) {
                if (!is_array($cat)) continue;
                $catName = $cat['name'] ?? $cat['title'] ?? $cat['heading'] ?? '';
                $catItems = $cat['items'] ?? $cat['entries'] ?? [];
                if (!empty($catName)) {
                    $text .= "\n  Category: " . strip_tags($catName);
                    if (is_array($catItems)) {
                        foreach ($catItems as $ci) {
                            $ciText = is_string($ci) ? $ci : ($ci['text'] ?? $ci['label'] ?? '');
                            if (!empty($ciText)) {
                                $text .= ", " . strip_tags($ciText);
                            }
                        }
                    }
                }
            }
        }
        // Correct order (ordering activities)
        $correctOrder = $activity['correctOrder'] ?? $activity['order'] ?? [];
        if (is_array($correctOrder) && !empty($correctOrder)) {
            $text .= "\n  Correct order: ";
            foreach ($correctOrder as $oi => $oItem) {
                $oText = is_string($oItem) ? $oItem : ($oItem['text'] ?? '');
                $text .= ($oi + 1) . ". " . strip_tags($oText) . " ";
            }
        }
    }
    return $text;
}

/**
 * Extract a single Video Activity question from its JSON questiondata blob.
 */
function format_aicourse_extract_va_question($qdata, $qnum) {
    $text = '';
    $type = $qdata['type'] ?? $qdata['questionType'] ?? $qdata['activityType'] ?? 'mcq';
    $qtext = $qdata['question'] ?? $qdata['questionText'] ?? $qdata['text'] ?? '';
    if (!empty($qtext)) {
        $text .= "\nQ{$qnum} ({$type}): " . strip_tags($qtext);
    }
    // MCQ options
    $options = $qdata['options'] ?? $qdata['answers'] ?? $qdata['choices'] ?? [];
    if (is_array($options)) {
        foreach ($options as $oi => $opt) {
            $optText = is_string($opt) ? $opt : ($opt['text'] ?? $opt['label'] ?? $opt['answer'] ?? '');
            $isCorrect = is_array($opt) ? ($opt['isCorrect'] ?? $opt['correct'] ?? false) : false;
            $marker = $isCorrect ? ' [CORRECT]' : '';
            if (!empty($optText)) {
                $text .= " | " . strip_tags($optText) . $marker;
            }
        }
    }
    $correctAnswer = $qdata['correctAnswer'] ?? $qdata['answer'] ?? null;
    if ($correctAnswer !== null && is_numeric($correctAnswer) && is_array($options)) {
        $idx = (int)$correctAnswer;
        if (isset($options[$idx])) {
            $caText = is_string($options[$idx]) ? $options[$idx] : ($options[$idx]['text'] ?? '');
            $text .= " [CORRECT: " . strip_tags($caText) . "]";
        }
    }
    // Explanation
    $explanation = $qdata['explanation'] ?? $qdata['feedback'] ?? '';
    if (!empty($explanation) && is_string($explanation)) {
        $text .= " [EXPLANATION: " . strip_tags($explanation) . "]";
    }
    // Matching pairs
    $pairs = $qdata['pairs'] ?? $qdata['items'] ?? [];
    if ($type === 'matching' && is_array($pairs)) {
        foreach ($pairs as $pi => $pair) {
            if (is_array($pair)) {
                $left = $pair['left'] ?? $pair['term'] ?? $pair['prompt'] ?? '';
                $right = $pair['right'] ?? $pair['match'] ?? $pair['definition'] ?? '';
                $text .= "\n  Pair " . ($pi + 1) . ": " . strip_tags($left) . " ↔ " . strip_tags($right);
            }
        }
    }
    // Statements (True/False)
    $statements = $qdata['statements'] ?? [];
    if (is_array($statements)) {
        foreach ($statements as $si => $stmt) {
            if (is_array($stmt)) {
                $stText = $stmt['statement'] ?? $stmt['text'] ?? '';
                $isTrue = $stmt['isTrue'] ?? $stmt['correct'] ?? null;
                $marker = ($isTrue === true) ? ' [TRUE]' : (($isTrue === false) ? ' [FALSE]' : '');
                $text .= "\n  " . ($si + 1) . ". " . strip_tags($stText) . $marker;
            }
        }
    }
    // Flashcards
    $cards = $qdata['cards'] ?? $qdata['flashcards'] ?? [];
    if (is_array($cards) && ($type === 'flashcards' || $type === 'flashcard')) {
        foreach ($cards as $ci => $card) {
            if (is_array($card)) {
                $front = $card['front'] ?? $card['term'] ?? '';
                $back = $card['back'] ?? $card['definition'] ?? '';
                $text .= "\n  Card " . ($ci + 1) . ": " . strip_tags($front) . " → " . strip_tags($back);
            }
        }
    }
    return $text;
}

/**
 * Universal callback for ALL Moodle 4.x/5.x versions.
 * Hook API (db/hooks.php) only works in Moodle 4.3+, so this is the fallback.
 * Called on EVERY page load where course context is relevant (including activities/sections).
 */
function format_aicourse_extend_navigation_course($navigation, $course, $context) {
    global $PAGE, $CFG, $USER;
    
    // Only process aicourse format
    if ($course->format !== 'aicourse') {
        return;
    }
    
    // Skip the main course view - format.php handles that
    $sectionParam = optional_param('section', null, PARAM_INT);
    $sectionIdParam = optional_param('id', 0, PARAM_INT);
    
    // Detect if we're on section.php (uses id param for section ID)
    $isSectionPhp = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/course/section.php') !== false;
    
    if ($PAGE->pagetype === 'course-view-aicourse' && $sectionParam === null && !$isSectionPhp) {
        return;
    }
    
    // Detect page types
    $isActivityPage = strpos($PAGE->pagetype, 'mod-') === 0;
    $isSectionPage = $PAGE->pagetype === 'course-section' || 
                     strpos($PAGE->pagetype, 'course-view-section') !== false ||
                     $isSectionPhp ||
                     ($PAGE->pagetype === 'course-view-aicourse' && $sectionParam !== null);
    
    if (!$isActivityPage && !$isSectionPage) {
        return;
    }
    
    // NOTE: Do NOT call $PAGE->add_body_class() here!
    // extend_navigation_course() runs AFTER output has started.
    // Body classes are added in page_set_course() which runs early.
    
    // Get format options
    $format = course_get_format($course);
    $options = $format->get_format_options();
    
    // Moodle 4.0-4.2 FALLBACK: Hook API doesn't exist, so we expose hero HTML via JS variable
    // The injectHeroFallback AMD function (called from page_set_course) will pick this up
    if (!class_exists('\core\hook\output\before_standard_footer_html_generation')) {
        if (!empty($options['showherobanner'])) {
            $cm = null;
            $sectionnum = null;
            
            // Get course module for activity pages
            if ($PAGE->context && $PAGE->context->contextlevel == CONTEXT_MODULE) {
                $cm = get_coursemodule_from_id('', $PAGE->context->instanceid);
            }
            
            // Get section number for section pages
            if ($isSectionPage) {
                if ($sectionParam !== null) {
                    $sectionnum = $sectionParam;
                } else if ($sectionIdParam) {
                    global $DB;
                    $section = $DB->get_record('course_sections', array('id' => $sectionIdParam), 'section');
                    if ($section) {
                        $sectionnum = $section->section;
                    }
                }
            }
            
            // FIX-GRADER-MULTICAP (v1.7.60): Use centralized format_aicourse_is_grader() helper.
            // Mirrors the hook check in before_footer_html_generation.php for Moodle 4.3+.
            $coursecontext = context_course::instance($course->id);
            $skipHero = format_aicourse_is_grader($coursecontext, false);

            if (!$skipHero) {
                // Render hero HTML
                if ($cm) {
                    $heroHtml = format_aicourse_render_activity_hero_banner($course, $options, $cm);
                } else {
                    $heroHtml = format_aicourse_render_hero_banner($course, $options, $sectionnum);
                }

                // Expose via JS variable for the fallback injector
                $PAGE->requires->js_init_code(
                    'window.AICOURSE_HERO_HTML = ' . json_encode($heroHtml) . ';',
                    true
                );
            }
        }
    }
    
    // NOTE: Do NOT call $PAGE->add_body_class() here!
    // extend_navigation_course() runs AFTER output has started.
    // Body classes are handled by page_set_course() (early) and CSS .pagetype-* selectors.
    
    // Load JS module (this is safe - it queues JS, doesn't modify output)
    $PAGE->requires->js_call_amd('format_aicourse/courseformat', 'init');
}
