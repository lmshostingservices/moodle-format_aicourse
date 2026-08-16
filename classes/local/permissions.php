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

/**
 * Capability and site setting helpers for the AI Course format.
 *
 * Stateless service class: every method is static and none of them produce output.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permissions {
    /**
     * Memoised results of {@see self::is_grader()}, keyed on "contextid_userid".
     *
     * @var array<string, bool>
     */
    private static $gradercache = [];

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
     * @param \context_course $context Course context to check against.
     * @param bool $diag When true, emits developer debugging output.
     * @return bool True if the current user should be treated as a grader/teacher.
     */
    public static function is_grader($context, $diag = false) {
        global $USER, $DB;

        // ACF-FIX-2.0: Memoise per contextid+userid. This function runs two uncached DB queries
        // (get_user_roles + the {role} lookup) and is called 3-4 times per page render
        // (page_set_course, format.php, the footer hook, extend_navigation_course).
        $cachekey = $context->id . '_' . $USER->id;
        if (array_key_exists($cachekey, self::$gradercache)) {
            return self::$gradercache[$cachekey];
        }

        $graderarchetypes = ['teacher', 'editingteacher', 'manager', 'coursecreator'];

        // PRIMARY: role archetype check — works with role switches and any capability config.
        // get_user_roles() respects $SESSION->role_switch so this correctly handles the
        // "Switch role to..." scenario that trips up has_capability() based checks.
        $roles = get_user_roles($context, $USER->id, true);
        $recs = [];

        if (!empty($roles)) {
            $roleids = [];
            foreach ($roles as $role) {
                $roleids[$role->roleid] = $role->roleid;
            }
            $roleids = array_values($roleids);

            [$insql, $inparams] = $DB->get_in_or_equal($roleids);
            $recs = $DB->get_records_sql("SELECT id, archetype FROM {role} WHERE id $insql", $inparams);

            foreach ($recs as $rec) {
                if (in_array($rec->archetype, $graderarchetypes, true)) {
                    // ACF-FIX-2.0: was an unconditional echo of a raw <script>console.log(...)</script>
                    // block into the page. Diagnostics now go through debugging() and only fire for
                    // developers.
                    if ($diag) {
                        debugging('[aicourse] grader: archetype=' . $rec->archetype, DEBUG_DEVELOPER);
                    }
                    self::$gradercache[$cachekey] = true;
                    return true;
                }
            }
        }

        // FALLBACK: capability check for custom roles with teacher-like perms but non-standard archetype.
        $cap1 = has_capability('moodle/grade:viewall', $context, null, false);
        $cap2 = has_capability('moodle/course:manageactivities', $context, null, false);
        $cap3 = has_capability('moodle/course:viewhiddenactivities', $context, null, false);
        $result = $cap1 || $cap2 || $cap3;

        if ($diag && debugging('', DEBUG_DEVELOPER)) {
            $archetypelist = [];
            foreach ($recs as $rec) {
                $archetypelist[] = $rec->archetype;
            }
            debugging('[aicourse] grader check: ' . json_encode([
                'role_archetypes'                    => $archetypelist,
                'moodle/grade:viewall'               => $cap1,
                'moodle/course:manageactivities'     => $cap2,
                'moodle/course:viewhiddenactivities' => $cap3,
                'isgrader'                           => $result,
            ]), DEBUG_DEVELOPER);
        }

        self::$gradercache[$cachekey] = $result;
        return $result;
    }

    /**
     * Return true if the AI Tutor is enabled globally in plugin settings.
     *
     * Defaults to enabled when the setting has never been saved.
     *
     * @return bool True when the AI Tutor should be offered to users.
     */
    public static function is_tutor_enabled(): bool {
        $val = get_config('format_aicourse', 'enabletutor');
        return ($val === false) || !empty($val);
    }
}
