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

use context_course;
use format_aicourse\output\courseformat\activityhero;
use format_aicourse\output\courseformat\hero;

/**
 * Bodies of the three callbacks Moodle requires to live in lib.php by name.
 *
 * lib.php keeps `format_aicourse_pluginfile()`, `format_aicourse_inplace_editable()` and
 * `format_aicourse_extend_navigation_course()` as thin delegations to this class, because Moodle
 * discovers them by function name and they cannot be moved out of lib.php entirely.
 *
 * Stateless service class: every method is static.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class callbacks {
    /**
     * Serve banner image files from the format_aicourse component.
     *
     * URL pattern: /pluginfile.php/{contextid}/format_aicourse/bannerimage/{courseid}/{filename}
     *
     * @param \stdClass $course Course record.
     * @param \cm_info|null $cm Course module, unused for this file area.
     * @param \context $context Context the file lives in.
     * @param string $filearea File area name.
     * @param array $args Remaining URL path components.
     * @param bool $forcedownload Whether the file must be sent as a download.
     * @param array $options Additional options affecting file serving.
     * @return void Always sends the file or a "not found" response.
     */
    public static function pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
        if ($context->contextlevel != CONTEXT_COURSE) {
            send_file_not_found();
        }

        if ($filearea !== 'bannerimage') {
            send_file_not_found();
        }

        require_login($course);

        // The item id is shifted off the path but deliberately not used for the lookup. There is
        // exactly one banner per course and it is always stored under banner::BANNER_ITEMID; a
        // URL cached by a browser before the 2.1.5 item id migration still carries the old course
        // id, and serving it from the canonical item id keeps those links working.
        array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

        $fs   = get_file_storage();
        $file = $fs->get_file(
            $context->id,
            'format_aicourse',
            'bannerimage',
            banner::BANNER_ITEMID,
            $filepath,
            $filename
        );

        if (!$file) {
            send_file_not_found();
        }

        // 24-hour browser cache; no force-download (these are always displayed inline).
        send_stored_file($file, 86400, 0, false, $options);
    }

    /**
     * Callback for inplace editable elements.
     *
     * @param string $itemtype Type of the edited item.
     * @param int $itemid Id of the edited item.
     * @param string $newvalue The new value submitted by the user.
     * @return \core\output\inplace_editable|null The refreshed element, or null when unsupported.
     */
    public static function inplace_editable($itemtype, $itemid, $newvalue) {
        global $DB, $CFG;

        if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
            require_once($CFG->dirroot . '/course/lib.php');

            $section = $DB->get_record_sql(
                'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
                [$itemid, 'aicourse'],
                MUST_EXIST
            );

            $format = course_get_format($section->course);
            $course = $format->get_course();

            // Check capability.
            // ACF-FIX-2.0: \core_external\external_api only exists from Moodle 4.2. On 4.0/4.1 this
            // call raised "Class not found" and inline section renaming was fatal. Branch on
            // class_exists() and fall back to the legacy external_api.
            $context = \context_course::instance($course->id);
            if (class_exists('\core_external\external_api')) {
                \core_external\external_api::validate_context($context);
            } else if (class_exists('\external_api')) {
                \external_api::validate_context($context);
            }
            require_capability('moodle/course:update', $context);

            // ACF-FIX-2.0: clean the value before it reaches the database (core's format_topics
            // equivalent does the same) and write it through course_update_section() so the
            // \core\event\course_section_updated event fires and the course cache is rebuilt by
            // core rather than by an ad-hoc set_field + rebuild_course_cache pair.
            $newvalue = clean_param($newvalue, PARAM_TEXT);
            course_update_section($course, $section, ['name' => $newvalue]);

            // Get updated section info.
            $section = $DB->get_record('course_sections', ['id' => $itemid], '*', MUST_EXIST);

            // ACF-FIX-2.0: a11y/i18n — 'newsectionname' is 'New name for section {$a}', so without
            // the $a argument the control's accessible name was the literal token
            // "New name for section {$a}". It also lives in CORE (lang/en/moodle.php), not in
            // format_topics, so the old get_string(..., 'format_topics') call did not even resolve.
            // This now matches \core_courseformat\base::inplace_editable_render_section_name()
            // exactly, including its separate edit HINT string.
            $title = format_string($section->name, true, ['context' => $context]);
            $edithint = get_string('editsectionname');
            $editlabel = get_string('newsectionname', '', $title);

            // Return inplace_editable element.
            return new \core\output\inplace_editable(
                'format_aicourse',
                $itemtype,
                $section->id,
                true,
                $title,
                $section->name,
                $edithint,
                $editlabel
            );
        }

        return null;
    }

    /**
     * Body class that tells styles.css which colour mode to paint in, or '' for none.
     *
     * ACF-FIX-2.1.9. The plugin ships a full dark token set, but CSS cannot see whether the
     * surrounding theme is actually dark -- and that is the only thing that matters, because
     * dark plugin tokens on a light page are unreadable. Two signals exist and neither is
     * sufficient alone:
     *
     *   - html[data-bs-theme] / body.dark-mode: authoritative when present, but a theme is
     *     free never to set it. theme_academi sets it nowhere.
     *   - prefers-color-scheme: reports the DEVICE, not the page. Honouring it on a
     *     light-only theme is what made course index titles vanish for dark-mode users.
     *
     * So the site decides. 'theme' (the default) trusts the first signal only and is safe
     * everywhere; 'device' restores the media-query behaviour for sites whose theme really does
     * follow the OS; 'light' and 'dark' pin it for themes that give no signal at all.
     *
     * @return string One of '', 'aicourse-force-light', 'aicourse-force-dark',
     *                'aicourse-follow-os'.
     */
    public static function get_colour_mode_class(): string {
        $mode = get_config('format_aicourse', 'colourmode');

        switch ($mode) {
            case 'light':
                return 'aicourse-force-light';
            case 'dark':
                return 'aicourse-force-dark';
            case 'device':
                return 'aicourse-follow-os';
            default:
                // Covers 'theme', unset, and anything unrecognised: follow the theme's declaration.
                return '';
        }
    }

    /**
     * Whether the running script is /course/section.php.
     *
     * ACF-FIX-2.1.5: one implementation, three former call sites. Uses the global $SCRIPT that
     * setup.php derives for exactly this purpose, rather than reading $_SERVER directly -- $SCRIPT
     * is normalised to a Moodle-relative path, and reviewers are right to flag raw superglobals.
     * Falls back to $_SERVER only if $SCRIPT is unavailable (CLI, some test bootstraps).
     *
     * @return bool True when the running script is course/section.php.
     */
    public static function is_section_php_request(): bool {
        global $SCRIPT;

        $script = $SCRIPT ?? ($_SERVER['SCRIPT_NAME'] ?? '');

        return strpos($script, '/course/section.php') !== false;
    }

    /**
     * Universal callback for ALL Moodle 4.x/5.x versions.
     *
     * Hook API (db/hooks.php) only works in Moodle 4.3+, so this is the fallback.
     * Called on EVERY page load where course context is relevant (including activities/sections).
     *
     * @param \navigation_node $navigation The course navigation node.
     * @param \stdClass $course Course record.
     * @param \context_course $context Course context.
     * @return void
     */
    public static function extend_navigation_course($navigation, $course, $context) {
        global $PAGE;

        // Only process aicourse format.
        if ($course->format !== 'aicourse') {
            return;
        }

        // Skip the main course view - format.php handles that.
        $sectionparam = optional_param('section', null, PARAM_INT);
        $sectionidparam = optional_param('id', 0, PARAM_INT);

        // Detect if we're on section.php (uses id param for section ID).
        $issectionphp = self::is_section_php_request();

        if ($PAGE->pagetype === 'course-view-aicourse' && $sectionparam === null && !$issectionphp) {
            return;
        }

        // Detect page types.
        $isactivitypage = strpos($PAGE->pagetype, 'mod-') === 0;
        $issectionpage = $PAGE->pagetype === 'course-section' ||
                         strpos($PAGE->pagetype, 'course-view-section') !== false ||
                         $issectionphp ||
                         ($PAGE->pagetype === 'course-view-aicourse' && $sectionparam !== null);

        if (!$isactivitypage && !$issectionpage) {
            return;
        }

        // NOTE: Do NOT call $PAGE->add_body_class() here!
        // extend_navigation_course() runs AFTER output has started.
        // Body classes are added in page_set_course() which runs early.

        // Get format options.
        $format = course_get_format($course);
        $options = $format->get_format_options();

        // Moodle 4.0-4.2 FALLBACK: Hook API doesn't exist, so we expose hero HTML via JS variable
        // The injectHeroFallback AMD function (called from page_set_course) will pick this up.
        if (!class_exists('\core\hook\output\before_standard_footer_html_generation')) {
            if (!empty($options['showherobanner'])) {
                $cm = null;
                $sectionnum = null;

                // Get course module for activity pages.
                if ($PAGE->context && $PAGE->context->contextlevel == CONTEXT_MODULE) {
                    $cm = get_coursemodule_from_id('', $PAGE->context->instanceid);
                }

                // Get section number for section pages.
                if ($issectionpage) {
                    if ($sectionparam !== null) {
                        $sectionnum = $sectionparam;
                    } else if ($sectionidparam) {
                        global $DB;
                        $section = $DB->get_record('course_sections', ['id' => $sectionidparam], 'section');
                        if ($section) {
                            $sectionnum = $section->section;
                        }
                    }
                }

                // FIX-GRADER-MULTICAP (v1.7.60): Use centralized permissions::is_grader() helper.
                // Mirrors the hook check in before_footer_html_generation.php for Moodle 4.3+.
                $coursecontext = context_course::instance($course->id);
                $skiphero = permissions::is_grader($coursecontext, false);

                if (!$skiphero) {
                    // Render hero HTML.
                    if ($cm) {
                        $herohtml = (new activityhero($course, $options, $cm))->out();
                    } else {
                        $herohtml = (new hero($course, $options, $sectionnum))->out();
                    }

                    // Expose via JS variable for the fallback injector.
                    $PAGE->requires->js_init_code(
                        'window.AICOURSE_HERO_HTML = ' . json_encode($herohtml) . ';',
                        true
                    );
                }
            }
        }

        // NOTE: Do NOT call $PAGE->add_body_class() here!
        // extend_navigation_course() runs AFTER output has started.
        // Body classes are handled by page_set_course() (early) and CSS .pagetype-* selectors.

        // Load JS module (this is safe - it queues JS, doesn't modify output).
        $PAGE->requires->js_call_amd('format_aicourse/courseformat', 'init');
    }
}
