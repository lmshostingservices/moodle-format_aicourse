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

use Throwable;

/**
 * Facts about a single course module: visibility, type name, status label and completion detail.
 *
 * Stateless service class: every method is static and none of them produce output.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activityinfo {
    /**
     * Per request completion_info instances, keyed on "courseid_userid".
     *
     * Each entry is ['info' => completion_info, 'bulkloaded' => bool]. Re-using a single
     * completion_info per course per request avoids re-checking is_enabled() on every call and
     * lets the first get_data() call bulk-load every completion row for the user.
     *
     * @var array<string, array>
     */
    private static $completioninfocache = [];

    /**
     * Return the sections that should be drawn as top-level items.
     *
     * ACF-FIX-2.0: prefer get_listed_section_info_all(), which excludes sections delegated to a
     * component (Moodle 4.4+ mod_subsection). get_section_info_all() returns them too, so every
     * sub-section was ALSO drawn as a duplicate top-level card next to its parent.
     *
     * @param \course_modinfo $modinfo Course modinfo.
     * @return \section_info[] Sections to list.
     */
    public static function get_listed_sections($modinfo) {
        if (method_exists($modinfo, 'get_listed_section_info_all')) {
            return $modinfo->get_listed_section_info_all();
        }
        return $modinfo->get_section_info_all();
    }

    /**
     * ACF-FIX-2.0: Single predicate for "does this course module count as content in a section?".
     *
     * progress::get_section_progress() counted every user-visible cm (including labels) toward
     * the "N activities" badge, while the activity card renderer silently dropped every cm without
     * a URL. The two therefore disagreed: a section could advertise "3 activities" and render one
     * card. Both now call this method, so the count and the render can never diverge.
     *
     * @param \cm_info $cm Course module.
     * @return bool True when the module should be both counted and rendered.
     */
    public static function cm_counts_as_content($cm) {
        // Not visible to this user, or a stealth activity that is deliberately kept off the
        // course page. Note: $cm->url is NOT used — mod_label and mod_subsection have none.
        if (!$cm->uservisible || !$cm->is_visible_on_course_page()) {
            return false;
        }

        // A subsection only counts when its delegated section is itself visible to the user.
        if ($cm->modname === 'subsection') {
            $delegated = self::get_delegated_section($cm);
            return ($delegated !== null && $delegated->uservisible);
        }

        return true;
    }

    /**
     * ACF-FIX-2.0: Resolve the section delegated to a mod_subsection course module, if any.
     *
     * @param \cm_info $cm Course module.
     * @return \section_info|null The delegated section, or null on older Moodle versions.
     */
    public static function get_delegated_section($cm) {
        if (!method_exists($cm, 'get_delegated_section_info')) {
            return null;
        }
        try {
            $delegated = $cm->get_delegated_section_info();
        } catch (Throwable $e) {
            // Older Moodle versions do not support this method — degrade gracefully.
            return null;
        }
        return $delegated ?: null;
    }

    /**
     * ACF-FIX-2.0: Friendly display name for an activity's module type.
     *
     * The previous implementation keyed a map on $cm->modfullname — a string Moodle has ALREADY
     * translated — and mapped it to a hardcoded English value. On any non-English site the lookup
     * never matched, so the mapping silently stopped working; on English sites it substituted an
     * untranslatable literal. The map is now keyed on $cm->modname (the untranslated component name)
     * and resolves the label through get_string(), falling back to the module's own translated name.
     *
     * @param \cm_info $cm Course module.
     * @return string Localised type label.
     */
    public static function get_activity_type_name($cm) {
        static $map = [
            'aicontentcreator'  => 'activitytype_content',
            'aiactivities'      => 'activitytype_activities',
            'aiknowledgecheck'  => 'activitytype_knowledgecheck',
            'knowledgecheck'    => 'activitytype_knowledgecheck',
            'slideshow'         => 'activitytype_slides',
        ];

        $modname = (string) $cm->modname;
        if (isset($map[$modname])) {
            return get_string($map[$modname], 'format_aicourse');
        }

        return (string) $cm->modfullname;
    }

    /**
     * ACF-FIX-2.0: Translate an internal completion status key into a user-visible label.
     *
     * Added so that every place which used to convey status through a CSS colour class alone
     * (progress dots, numbered activity circles, status badges) can put the same wording into an
     * accessible name. Keeping it in one method guarantees the visible badge and the ARIA text
     * never drift apart.
     *
     * @param string $status One of completed|in_progress|not_started|no_completion.
     * @return string Localised label.
     */
    public static function get_status_label($status) {
        switch ($status) {
            case 'completed':
                return get_string('completed', 'format_aicourse');
            case 'in_progress':
                return get_string('inprogress', 'format_aicourse');
            case 'not_started':
                return get_string('notstarted', 'format_aicourse');
            default:
                return get_string('nocompletion', 'format_aicourse');
        }
    }

    /**
     * Get activity completion information with human-readable requirements.
     *
     * @param \stdClass $course Course record.
     * @param \cm_info $cm Course module.
     * @param int $userid User to report on.
     * @return array Completion detail: completed, requirements, ismanual, cmid, hascompletion,
     *               requiresgrade, currentgrade, maxgrade and gradetext.
     */
    public static function get_activity_completion_info($course, $cm, $userid) {
        global $CFG;

        require_once($CFG->libdir . '/completionlib.php');
        // ACF-FIX-2.0: gradelib is not auto-loaded on every request; grade_get_grades() lives here.
        require_once($CFG->libdir . '/gradelib.php');

        $result = [
            'completed' => false,
            'requirements' => [],
            'ismanual' => false,
            'cmid' => $cm->id,
            'hascompletion' => false,
            'requiresgrade' => false,
            'currentgrade' => null,
            'maxgrade' => null,
            'gradetext' => null,
        ];

        // Re-use a single completion_info instance per course per request via static cache,
        // avoiding the cost of constructing a new object and re-checking is_enabled() on every call.
        $cachekey = $course->id . '_' . $userid;
        if (!isset(self::$completioninfocache[$cachekey])) {
            self::$completioninfocache[$cachekey] = [
                'info' => new \completion_info($course),
                'bulkloaded' => false,
            ];
        }
        $info       = self::$completioninfocache[$cachekey]['info'];
        $bulkloaded = &self::$completioninfocache[$cachekey]['bulkloaded'];

        if (!$info->is_enabled() || $cm->completion == COMPLETION_TRACKING_NONE) {
            return $result;
        }

        $result['hascompletion'] = true;
        $result['ismanual'] = ($cm->completion == COMPLETION_TRACKING_MANUAL);

        // First call: pass $wholecourse=true to bulk-load every completion row for this user
        // in one SELECT. All subsequent calls within the same request use the in-memory cache.
        $data = $info->get_data($cm, !$bulkloaded, $userid);
        $bulkloaded = true;
        $result['completed'] = ($data->completionstate == COMPLETION_COMPLETE
            || $data->completionstate == COMPLETION_COMPLETE_PASS);

        // Build requirements list based on completion settings.
        if ($cm->completion == COMPLETION_TRACKING_MANUAL) {
            $result['requirements'][] = get_string('completionrequirement_manual', 'format_aicourse');
        } else {
            // Automatic completion - check various conditions.

            // View requirement.
            if (!empty($cm->completionview)) {
                $result['requirements'][] = get_string('completionrequirement_view', 'format_aicourse');
            }

            // Grade requirement.
            // ACF-FIX-2.0: $cm->completionusegrade is NOT a cm_info property — it only exists on the
            // module settings form, so this branch never fired for grade-based completion. cm_info
            // exposes completiongradeitemnumber, which is the item number when completion depends on
            // a grade and null otherwise (see lib/modinfolib.php).
            $usesgrade = (isset($cm->completiongradeitemnumber) && $cm->completiongradeitemnumber !== null);
            if ($usesgrade || !empty($cm->completionpassgrade)) {
                $result['requiresgrade'] = true;

                // ACF-FIX-2.0: the old $DB->get_record('grade_items', ...) had no IGNORE_MULTIPLE and
                // threw for modules with several grade items (workshop, lesson). grade_get_grades()
                // is the supported API: it returns the items keyed by itemnumber together with this
                // user's grade, honouring hidden/locked grades.
                $itemnumber = $usesgrade ? (int) $cm->completiongradeitemnumber : 0;
                $gradeitem = null;
                $usergradevalue = null;
                $grades = grade_get_grades($course->id, 'mod', $cm->modname, $cm->instance, $userid);
                if (!empty($grades->items[$itemnumber])) {
                    $gradeitem = $grades->items[$itemnumber];
                } else if (!empty($grades->items)) {
                    $gradeitem = reset($grades->items);
                }

                if ($gradeitem) {
                    $result['maxgrade'] = round($gradeitem->grademax);

                    if (!empty($gradeitem->grades[$userid]) && $gradeitem->grades[$userid]->grade !== null) {
                        $usergradevalue = $gradeitem->grades[$userid]->grade;
                    }

                    if ($usergradevalue !== null) {
                        $result['currentgrade'] = round($usergradevalue);
                        $result['gradetext'] = get_string('gradefraction', 'format_aicourse', (object) [
                            'current' => format_float($result['currentgrade'], 0),
                            'max'     => format_float($result['maxgrade'], 0),
                        ]);
                    } else {
                        $result['currentgrade'] = 0;
                        $result['gradetext'] = get_string(
                            'gradefractionnone',
                            'format_aicourse',
                            format_float($result['maxgrade'], 0)
                        );
                    }
                }

                // Check if there's a passing grade requirement.
                if (!empty($cm->completionpassgrade)) {
                    if ($gradeitem && $gradeitem->gradepass > 0) {
                        $passgrade = round($gradeitem->gradepass);
                        $maxgrade = round($gradeitem->grademax);
                        if ($maxgrade > 0 && $passgrade == $maxgrade) {
                            $result['requirements'][] = get_string('completionrequirement_grade100', 'format_aicourse');
                        } else if ($maxgrade > 0) {
                            // ACF-FIX-2.0: i18n — the '%' now lives inside the translatable string
                            // rather than being concatenated onto the placeholder value.
                            $percentage = round(($passgrade / $maxgrade) * 100);
                            $result['requirements'][] = get_string(
                                'completionrequirement_gradepasspct',
                                'format_aicourse',
                                format_float($percentage, 0)
                            );
                        } else {
                            $result['requirements'][] = get_string(
                                'completionrequirement_gradepass',
                                'format_aicourse',
                                format_float($passgrade, 0)
                            );
                        }
                    } else {
                        $result['requirements'][] = get_string('completionrequirement_gradeany', 'format_aicourse');
                    }
                } else {
                    $result['requirements'][] = get_string('completionrequirement_gradeany', 'format_aicourse');
                }
            }

            // ACF-FIX-2.0: dead code removed — a {course_completion_criteria} query whose result was
            // fed into an empty foreach body. It cost one DB query per activity render and produced
            // nothing.

            // If no specific requirements found but completion is enabled.
            if (empty($result['requirements']) && $cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
                $result['requirements'][] = get_string('completionrequirement_auto', 'format_aicourse');
            }
        }

        return $result;
    }
}
