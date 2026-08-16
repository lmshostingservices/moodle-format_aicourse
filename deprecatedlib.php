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
 * Deprecated global functions kept so third-party themes and blocks do not break.
 *
 * Every function here moved to a service class under classes/local/ or an output class under
 * classes/output/courseformat/ in v2.1. The wrappers forward to the new home and emit a
 * DEBUG_DEVELOPER notice. They will be removed in a future release.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use format_aicourse\local\activityinfo;
use format_aicourse\local\banner;
use format_aicourse\local\contentindex;
use format_aicourse\local\icons;
use format_aicourse\local\navigation;
use format_aicourse\local\permissions;
use format_aicourse\local\progress;
use format_aicourse\output\courseformat\activityhero;
use format_aicourse\output\courseformat\chatbox;
use format_aicourse\output\courseformat\content;
use format_aicourse\output\courseformat\content\activitycards;
use format_aicourse\output\courseformat\content\generalsection;
use format_aicourse\output\courseformat\hero;
use format_aicourse\output\courseformat\iconpicker;

/**
 * Detect whether the current user should be treated as a grader/teacher.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\permissions::is_grader() instead.
 * @param context_course $context Course context to check against.
 * @param bool $diag When true, emits developer debugging output.
 * @return bool True if the current user should be treated as a grader/teacher.
 */
function format_aicourse_is_grader($context, $diag = false) {
    debugging('format_aicourse_is_grader() is deprecated, '
        . 'use \format_aicourse\local\permissions::is_grader() instead.', DEBUG_DEVELOPER);
    return permissions::is_grader($context, $diag);
}

/**
 * Return true if the AI Tutor is enabled globally in plugin settings.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\permissions::is_tutor_enabled() instead.
 * @return bool True when the AI Tutor should be offered to users.
 */
function format_aicourse_is_tutor_enabled(): bool {
    debugging('format_aicourse_is_tutor_enabled() is deprecated, '
        . 'use \format_aicourse\local\permissions::is_tutor_enabled() instead.', DEBUG_DEVELOPER);
    return permissions::is_tutor_enabled();
}

/**
 * Translate an internal completion status key into a user-visible label.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\activityinfo::get_status_label() instead.
 * @param string $status One of completed|in_progress|not_started|no_completion.
 * @return string Localised label.
 */
function format_aicourse_get_status_label($status) {
    debugging('format_aicourse_get_status_label() is deprecated, '
        . 'use \format_aicourse\local\activityinfo::get_status_label() instead.', DEBUG_DEVELOPER);
    return activityinfo::get_status_label($status);
}

/**
 * Return the sections that should be drawn as top-level items.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\activityinfo::get_listed_sections() instead.
 * @param course_modinfo $modinfo Course modinfo.
 * @return section_info[] Sections to list.
 */
function format_aicourse_get_listed_sections($modinfo) {
    debugging('format_aicourse_get_listed_sections() is deprecated, '
        . 'use \format_aicourse\local\activityinfo::get_listed_sections() instead.', DEBUG_DEVELOPER);
    return activityinfo::get_listed_sections($modinfo);
}

/**
 * Single predicate for "does this course module count as content in a section?".
 *
 * @deprecated since v2.1 — use \format_aicourse\local\activityinfo::cm_counts_as_content() instead.
 * @param cm_info $cm Course module.
 * @return bool True when the module should be both counted and rendered.
 */
function format_aicourse_cm_counts_as_content($cm) {
    debugging('format_aicourse_cm_counts_as_content() is deprecated, '
        . 'use \format_aicourse\local\activityinfo::cm_counts_as_content() instead.', DEBUG_DEVELOPER);
    return activityinfo::cm_counts_as_content($cm);
}

/**
 * Resolve the section delegated to a mod_subsection course module, if any.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\activityinfo::get_delegated_section() instead.
 * @param cm_info $cm Course module.
 * @return section_info|null The delegated section, or null on older Moodle versions.
 */
function format_aicourse_get_delegated_section($cm) {
    debugging('format_aicourse_get_delegated_section() is deprecated, '
        . 'use \format_aicourse\local\activityinfo::get_delegated_section() instead.', DEBUG_DEVELOPER);
    return activityinfo::get_delegated_section($cm);
}

/**
 * Friendly display name for an activity's module type.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\activityinfo::get_activity_type_name() instead.
 * @param cm_info $cm Course module.
 * @return string Localised type label.
 */
function format_aicourse_get_activity_type_name($cm) {
    debugging('format_aicourse_get_activity_type_name() is deprecated, '
        . 'use \format_aicourse\local\activityinfo::get_activity_type_name() instead.', DEBUG_DEVELOPER);
    return activityinfo::get_activity_type_name($cm);
}

/**
 * Get activity completion information with human-readable requirements.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\activityinfo::get_activity_completion_info() instead.
 * @param stdClass $course Course record.
 * @param cm_info $cm Course module.
 * @param int $userid User to report on.
 * @return array Completion detail.
 */
function format_aicourse_get_activity_completion_info($course, $cm, $userid) {
    debugging('format_aicourse_get_activity_completion_info() is deprecated, '
        . 'use \format_aicourse\local\activityinfo::get_activity_completion_info() instead.', DEBUG_DEVELOPER);
    return activityinfo::get_activity_completion_info($course, $cm, $userid);
}

/**
 * Estimated time in minutes for a single activity.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\progress::estimate_activity_minutes() instead.
 * @param cm_info $cm Course module.
 * @return int Minutes.
 */
function format_aicourse_estimate_activity_minutes($cm) {
    debugging('format_aicourse_estimate_activity_minutes() is deprecated, '
        . 'use \format_aicourse\local\progress::estimate_activity_minutes() instead.', DEBUG_DEVELOPER);
    return progress::estimate_activity_minutes($cm);
}

/**
 * Format a duration in minutes for display.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\progress::format_estimated_time() instead.
 * @param int $minutes Duration in minutes.
 * @return string Empty string when there is nothing to show.
 */
function format_aicourse_format_estimated_time($minutes) {
    debugging('format_aicourse_format_estimated_time() is deprecated, '
        . 'use \format_aicourse\local\progress::format_estimated_time() instead.', DEBUG_DEVELOPER);
    return progress::format_estimated_time($minutes);
}

/**
 * Get whole-course progress for a user.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\progress::get_progress() instead.
 * @param stdClass $course Course record.
 * @param int $userid User to report on.
 * @param completion_info|null $completioninfo Optional pre-loaded completion_info to share.
 * @param bool $needactivities Set false to skip building the per-activity array.
 * @return array Progress data.
 */
function format_aicourse_get_progress($course, $userid, $completioninfo = null, $needactivities = true) {
    debugging('format_aicourse_get_progress() is deprecated, '
        . 'use \format_aicourse\local\progress::get_progress() instead.', DEBUG_DEVELOPER);
    return progress::get_progress($course, $userid, $completioninfo, $needactivities);
}

/**
 * Get section progress data for card display.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\progress::get_section_progress() instead.
 * @param stdClass $course Course record.
 * @param section_info $section Section to report on.
 * @param int $userid User to report on.
 * @param completion_info|null $completioninfo Optional pre-loaded completion_info to share.
 * @return array Progress data.
 */
function format_aicourse_get_section_progress($course, $section, $userid, $completioninfo = null) {
    debugging('format_aicourse_get_section_progress() is deprecated, '
        . 'use \format_aicourse\local\progress::get_section_progress() instead.', DEBUG_DEVELOPER);
    return progress::get_section_progress($course, $section, $userid, $completioninfo);
}

/**
 * Return the URL of the course overview image.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\banner::get_course_image() instead.
 * @param stdClass $course Course record.
 * @return string|null Absolute pluginfile URL, or null.
 */
function format_aicourse_get_course_image($course) {
    debugging('format_aicourse_get_course_image() is deprecated, '
        . 'use \format_aicourse\local\banner::get_course_image() instead.', DEBUG_DEVELOPER);
    return banner::get_course_image($course);
}

/**
 * Return the URL of the custom banner image uploaded via the course format settings.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\banner::get_banner_image_url() instead.
 * @param stdClass $course Course record.
 * @return string|null Absolute pluginfile URL, or null.
 */
function format_aicourse_get_banner_image_url($course) {
    debugging('format_aicourse_get_banner_image_url() is deprecated, '
        . 'use \format_aicourse\local\banner::get_banner_image_url() instead.', DEBUG_DEVELOPER);
    return banner::get_banner_image_url($course);
}

/**
 * Get the current section for the activity being viewed.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\navigation::get_current_section() instead.
 * @param stdClass $course Course record.
 * @param int $userid User whose modinfo should be used.
 * @return array|null Array with 'num' and 'name', or null.
 */
function format_aicourse_get_current_section($course, $userid) {
    debugging('format_aicourse_get_current_section() is deprecated, '
        . 'use \format_aicourse\local\navigation::get_current_section() instead.', DEBUG_DEVELOPER);
    return navigation::get_current_section($course, $userid);
}

/**
 * Get previous and next activity navigation links for the current activity.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\navigation::get_nav_links() instead.
 * @param stdClass $course The course object.
 * @param int $userid The user ID.
 * @return array Array with 'prev' and 'next' keys.
 */
function format_aicourse_get_nav_links($course, $userid) {
    debugging('format_aicourse_get_nav_links() is deprecated, '
        . 'use \format_aicourse\local\navigation::get_nav_links() instead.', DEBUG_DEVELOPER);
    return navigation::get_nav_links($course, $userid);
}

/**
 * Get previous and next navigation links for section pages.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\navigation::get_section_nav_links() instead.
 * @param stdClass $course The course object.
 * @param int|null $currentsectionnum The current section number.
 * @param course_modinfo|null $modinfo Optional pre-loaded modinfo.
 * @return array Array with 'prev' and 'next' keys.
 */
function format_aicourse_get_section_nav_links($course, $currentsectionnum, $modinfo = null) {
    debugging('format_aicourse_get_section_nav_links() is deprecated, '
        . 'use \format_aicourse\local\navigation::get_section_nav_links() instead.', DEBUG_DEVELOPER);
    return navigation::get_section_nav_links($course, $currentsectionnum, $modinfo);
}

/**
 * Get the icon library for section cards.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\icons::get_library() instead.
 * @return array Icon key => inline SVG body.
 */
function format_aicourse_get_icon_library() {
    debugging('format_aicourse_get_icon_library() is deprecated, '
        . 'use \format_aicourse\local\icons::get_library() instead.', DEBUG_DEVELOPER);
    return icons::get_library();
}

/**
 * Get icon categories for the section card icon picker.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\icons::get_categories() instead.
 * @return array Category slug => ordered list of icon keys.
 */
function format_aicourse_get_icon_categories() {
    debugging('format_aicourse_get_icon_categories() is deprecated, '
        . 'use \format_aicourse\local\icons::get_categories() instead.', DEBUG_DEVELOPER);
    return icons::get_categories();
}

/**
 * Localised, human-readable label for an icon key.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\icons::get_label() instead.
 * @param string $key Icon key.
 * @return string Localised label.
 */
function format_aicourse_get_icon_label($key) {
    debugging('format_aicourse_get_icon_label() is deprecated, '
        . 'use \format_aicourse\local\icons::get_label() instead.', DEBUG_DEVELOPER);
    return icons::get_label($key);
}

/**
 * Preload icons for multiple sections in a single DB query.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\icons::preload_section_icons() instead.
 * @param int $courseid Course id.
 * @param int[] $sectionids Array of section record ids.
 * @return void
 */
function format_aicourse_preload_section_icons($courseid, array $sectionids) {
    debugging('format_aicourse_preload_section_icons() is deprecated, '
        . 'use \format_aicourse\local\icons::preload_section_icons() instead.', DEBUG_DEVELOPER);
    icons::preload_section_icons($courseid, $sectionids);
}

/**
 * Get the icon key saved against a section.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\icons::get_section_icon() instead.
 * @param int $courseid Course id.
 * @param int $sectionid course_sections.id of the section.
 * @return string Icon key, or '' when the section has no icon.
 */
function format_aicourse_get_section_icon($courseid, $sectionid) {
    debugging('format_aicourse_get_section_icon() is deprecated, '
        . 'use \format_aicourse\local\icons::get_section_icon() instead.', DEBUG_DEVELOPER);
    return icons::get_section_icon($courseid, $sectionid);
}

/**
 * Set section icon in course format options.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\icons::set_section_icon() instead.
 * @param int $courseid Course id.
 * @param int $sectionid course_sections.id of the section.
 * @param string $icon Icon key, or '' to clear.
 * @return bool True on success.
 */
function format_aicourse_set_section_icon($courseid, $sectionid, $icon) {
    debugging('format_aicourse_set_section_icon() is deprecated, '
        . 'use \format_aicourse\local\icons::set_section_icon() instead.', DEBUG_DEVELOPER);
    return icons::set_section_icon($courseid, $sectionid, $icon);
}

/**
 * Build (or fetch from cache) the text index of a course for the AI Tutor.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\contentindex::get_course_content_for_ai() instead.
 * @param stdClass $course Course record.
 * @return array Index of the course content.
 */
function format_aicourse_get_course_content_for_ai($course) {
    debugging('format_aicourse_get_course_content_for_ai() is deprecated, '
        . 'use \format_aicourse\local\contentindex::get_course_content_for_ai() instead.', DEBUG_DEVELOPER);
    return contentindex::get_course_content_for_ai($course);
}

/**
 * Purge the cross-request course content cache for a specific course.
 *
 * @deprecated since v2.1 — use \format_aicourse\local\contentindex::purge_content_cache() instead.
 * @param int $courseid Course id whose cached index is now stale.
 * @return void
 */
function format_aicourse_purge_content_cache($courseid) {
    debugging('format_aicourse_purge_content_cache() is deprecated, '
        . 'use \format_aicourse\local\contentindex::purge_content_cache() instead.', DEBUG_DEVELOPER);
    contentindex::purge_content_cache($courseid);
}

/**
 * Render the course / section hero banner.
 *
 * @deprecated since v2.1 — use \format_aicourse\output\courseformat\hero instead.
 * @param stdClass $course Course record.
 * @param array $options Course format options.
 * @param int|null $sectionnum Section number, or null for the course home page.
 * @return string HTML.
 */
function format_aicourse_render_hero_banner($course, $options, $sectionnum = null) {
    debugging('format_aicourse_render_hero_banner() is deprecated, '
        . 'use \format_aicourse\output\courseformat\hero instead.', DEBUG_DEVELOPER);
    return (new hero($course, $options, $sectionnum))->out();
}

/**
 * Render the activity page hero banner.
 *
 * @deprecated since v2.1 — use \format_aicourse\output\courseformat\activityhero instead.
 * @param stdClass $course Course record.
 * @param array $options Course format options.
 * @param cm_info $cm Course module the hero describes.
 * @return string HTML.
 */
function format_aicourse_render_activity_hero_banner($course, $options, $cm) {
    debugging('format_aicourse_render_activity_hero_banner() is deprecated, '
        . 'use \format_aicourse\output\courseformat\activityhero instead.', DEBUG_DEVELOPER);
    return (new activityhero($course, $options, $cm))->out();
}

/**
 * Render the General section (section 0) above the card grid.
 *
 * @deprecated since v2.1 — use \format_aicourse\output\courseformat\content\generalsection instead.
 * @param stdClass $course Course record.
 * @param section_info $section0 The General section.
 * @param array $options Course format options.
 * @param course_modinfo $modinfo Pre-loaded modinfo for the current user.
 * @param context_course $coursecontext Course context.
 * @return string HTML, or '' when there is nothing to show.
 */
function format_aicourse_render_general_section($course, $section0, $options, $modinfo, $coursecontext) {
    debugging('format_aicourse_render_general_section() is deprecated, '
        . 'use \format_aicourse\output\courseformat\content\generalsection instead.', DEBUG_DEVELOPER);
    return (new generalsection($course, $section0, $options, $modinfo, $coursecontext))->out();
}

/**
 * Render the section cards for the course home page.
 *
 * The replacement class takes its options from the course format object, so the $options
 * argument is ignored by this wrapper.
 *
 * @deprecated since v2.1 — use \format_aicourse\output\courseformat\content instead.
 * @param stdClass $course Course record.
 * @param array $options Course format options. Ignored; read from the course format instead.
 * @return string HTML.
 */
function format_aicourse_render_section_cards($course, $options) {
    debugging('format_aicourse_render_section_cards() is deprecated, '
        . 'use \format_aicourse\output\courseformat\content instead.', DEBUG_DEVELOPER);
    unset($options);
    return (new content(course_get_format($course)))->out();
}

/**
 * Render the activity card grid for a single section.
 *
 * @deprecated since v2.1 — use \format_aicourse\output\courseformat\content\activitycards instead.
 * @param stdClass $course Course record.
 * @param int $sectionnum Section number.
 * @param array $options Course format options.
 * @param bool $showheading Render the region heading.
 * @return string HTML.
 */
function format_aicourse_render_activity_cards($course, $sectionnum, $options, $showheading = true) {
    debugging('format_aicourse_render_activity_cards() is deprecated, '
        . 'use \format_aicourse\output\courseformat\content\activitycards instead.', DEBUG_DEVELOPER);
    return (new activitycards($course, $sectionnum, $options, $showheading))->out();
}

/**
 * Render the section card icon picker modal.
 *
 * @deprecated since v2.1 — use \format_aicourse\output\courseformat\iconpicker instead.
 * @param array $iconlibrary Icon library as returned by icons::get_library().
 * @return string HTML.
 */
function format_aicourse_render_icon_picker($iconlibrary) {
    debugging('format_aicourse_render_icon_picker() is deprecated, '
        . 'use \format_aicourse\output\courseformat\iconpicker instead.', DEBUG_DEVELOPER);
    return (new iconpicker($iconlibrary))->out();
}

/**
 * Render the AI Course Assistant chat panel (HTML only, no script).
 *
 * @deprecated since v2.1 — use \format_aicourse\output\courseformat\chatbox::out() instead.
 * @return string HTML, or '' when the AI Tutor is disabled site-wide.
 */
function format_aicourse_render_ai_chatbox_html() {
    debugging('format_aicourse_render_ai_chatbox_html() is deprecated, '
        . 'use \format_aicourse\output\courseformat\chatbox::out() instead.', DEBUG_DEVELOPER);
    return (new chatbox())->out();
}

/**
 * Render the inline script that drives the AI Course Assistant chat panel.
 *
 * @deprecated since v2.1 — use \format_aicourse\output\courseformat\chatbox::script() instead.
 * @param stdClass $course Course record.
 * @return string A script block, or '' when the AI Tutor is disabled site-wide.
 */
function format_aicourse_render_ai_chatbox_script($course) {
    debugging('format_aicourse_render_ai_chatbox_script() is deprecated, '
        . 'use \format_aicourse\output\courseformat\chatbox::script() instead.', DEBUG_DEVELOPER);
    return (new chatbox($course))->script();
}
