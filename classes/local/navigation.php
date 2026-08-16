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

use moodle_url;

/**
 * Previous/next navigation links and current-section resolution.
 *
 * Stateless service class: every method is static and none of them produce output.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation {
    /**
     * Get the current section for the activity being viewed.
     *
     * @param \stdClass $course Course record.
     * @param int $userid User whose modinfo should be used.
     * @return array|null Array with 'num' and 'name', or null when not on an activity page.
     */
    public static function get_current_section($course, $userid) {
        global $PAGE;

        // Check if we're viewing an activity.
        if ($PAGE->context && $PAGE->context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id('', $PAGE->context->instanceid);
            if ($cm) {
                $modinfo = get_fast_modinfo($course, $userid);
                // Use get_cm to get the course_module_info which has sectionnum property.
                $cminfo = $modinfo->get_cm($cm->id);
                if ($cminfo) {
                    $section = $modinfo->get_section_info($cminfo->sectionnum);
                    if ($section) {
                        return [
                            'num' => $section->section,
                            'name' => get_section_name($course, $section),
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get previous and next activity navigation links for the current activity.
     *
     * @param \stdClass $course The course object.
     * @param int $userid The user ID.
     * @return array Array with 'prev' and 'next' keys, each containing 'name' and 'url' or null.
     */
    public static function get_nav_links($course, $userid) {
        global $PAGE;

        $result = ['prev' => null, 'next' => null];

        // Get current course module from PAGE context.
        $cm = null;
        if ($PAGE->context && $PAGE->context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id('', $PAGE->context->instanceid);
        }

        if (!$cm) {
            return $result;
        }

        $modinfo = get_fast_modinfo($course, $userid);
        $cms = $modinfo->get_cms();

        // Build ordered list of visible activities.
        $activities = [];
        foreach ($cms as $coursemod) {
            if ($coursemod->uservisible && $coursemod->url) {
                $activities[] = $coursemod;
            }
        }

        // Find current position.
        $currentindex = -1;
        foreach ($activities as $index => $activity) {
            if ($activity->id == $cm->id) {
                $currentindex = $index;
                break;
            }
        }

        if ($currentindex === -1) {
            return $result;
        }

        // Get previous activity.
        if ($currentindex > 0) {
            $prev = $activities[$currentindex - 1];
            $result['prev'] = [
                'name' => format_string($prev->name),
                'url' => $prev->url->out(),
            ];
        }

        // Get next activity.
        if ($currentindex < count($activities) - 1) {
            $next = $activities[$currentindex + 1];
            $result['next'] = [
                'name' => format_string($next->name),
                'url' => $next->url->out(),
            ];
        }

        return $result;
    }

    /**
     * Get previous and next navigation links for section pages.
     *
     * Navigation crosses section boundaries — prev goes to the previous section page, next goes
     * to the following section page.
     *
     * @param \stdClass $course The course object.
     * @param int|null $currentsectionnum The current section number.
     * @param \course_modinfo|null $modinfo Optional pre-loaded modinfo for the current user.
     * @return array Array with 'prev' and 'next' keys, each containing 'name' and 'url' or null.
     */
    public static function get_section_nav_links($course, $currentsectionnum, $modinfo = null) {
        global $USER;

        $result = ['prev' => null, 'next' => null];

        if ($currentsectionnum === null) {
            return $result;
        }

        // Accept a pre-loaded modinfo to avoid a redundant get_fast_modinfo() call
        // when the caller (the hero renderer) already has one.
        if ($modinfo === null) {
            $modinfo = get_fast_modinfo($course, $USER->id);
        }

        // FIX-ACF-NAVSKIP (v1.7.48): Navigate between SECTION PAGES, not individual activity
        // URLs. The previous implementation linked to the first/last activity of the
        // adjacent section — this caused two problems:
        // 1. Clicking the arrow took the student to an activity page, not the section page.
        // 2. Sub-sections (e.g. 5.1, 5.2) with no *directly-visible* activities were skipped
        // entirely, so the arrow jumped from section 5.1 all the way to section 6.
        // Fix: build an ordered list of all *visible* sections (skipping section 0 / General)
        // and navigate to the adjacent section's own course/view.php?section=N URL.
        $allsections = $modinfo->get_section_info_all();
        $orderedsections = [];
        foreach ($allsections as $section) {
            if ((int)$section->section === 0) {
                // Skip "General" (section 0).
                continue;
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

        // Previous section.
        if ($currentidx > 0) {
            $prevsection = $orderedsections[$currentidx - 1];
            $prevurl = new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $prevsection->section]);
            $result['prev'] = [
                'name' => get_section_name($course, $prevsection),
                'url'  => $prevurl->out(),
            ];
        }

        // Next section.
        if ($currentidx < count($orderedsections) - 1) {
            $nextsection = $orderedsections[$currentidx + 1];
            $nexturl = new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $nextsection->section]);
            $result['next'] = [
                'name' => get_section_name($course, $nextsection),
                'url'  => $nexturl->out(),
            ];
        }

        return $result;
    }
}
