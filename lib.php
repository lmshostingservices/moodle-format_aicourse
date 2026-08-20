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
 * Library functions and the format class for the AI Course format.
 *
 * Everything that is not the format class or a callback Moodle discovers by name in lib.php
 * lives in classes/local/ (services) or classes/output/courseformat/ (renderers). Deprecated
 * wrappers for the global functions that used to live here are in deprecatedlib.php.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use format_aicourse\local\callbacks;
use format_aicourse\local\permissions;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/format/topics/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/format/aicourse/deprecatedlib.php');

/**
 * The AI Course format.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_aicourse extends format_topics {
    /**
     * Read a site-level default for a course format option.
     *
     * Site defaults are stored as format_aicourse/default<optionname>. An unset setting, or one
     * saved as an empty string, falls back to the value passed in -- NOT to zero, which is what
     * a bare (int) cast of an absent setting would have produced and would silently have turned
     * every checkbox off.
     *
     * @param string $name The course option name, without the 'default' prefix.
     * @param mixed $fallback Value to use when the site setting is absent or empty.
     * @return mixed The site default, cast to the same type as $fallback.
     */
    protected static function site_default(string $name, $fallback) {
        $value = get_config('format_aicourse', 'default' . $name);
        if ($value === false || $value === '') {
            return $fallback;
        }
        return is_int($fallback) ? (int) $value : (string) $value;
    }

    /**
     * Build the inline custom properties that carry the course's accent colour
     * and hero fade.
     *
     * Returned as a style-attribute fragment rather than a <style> element on
     * purpose: this format already publishes dynamic values that way (the hero
     * height, the card title size), and a <style> element would need
     * style-src 'unsafe-inline' from any site running a strict CSP.
     *
     * SECURITY: the colour is matched against a strict #rrggbb / #rgb regex and
     * dropped entirely if it does not match, and the fade is cast to int and
     * clamped, so neither value can carry a `;` out of the declaration it sits
     * in. Never relax this to a plain s_() or PARAM_TEXT pass-through.
     *
     * @param array $options The course format options.
     * @return string Style declarations, or '' when neither option is set.
     */
    public static function get_accent_style(array $options): string {
        $css = '';
        $colour = isset($options['accentcolour']) ? trim((string) $options['accentcolour']) : '';
        if ($colour !== '' && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $colour)) {
            $css .= '--acf-brand:' . $colour . ';';
        }
        if (isset($options['herobannerfade']) && $options['herobannerfade'] !== '') {
            $fade = max(0, min(100, (int) $options['herobannerfade']));
            $css .= '--acf-hero-wash-tint:' . $fade . '%;';
        }
        // The default of -1 means "not set" -- leave the property absent so the site-wide
        // Banner overlay strength class keeps control. 0 is a real value: no overlay at all.
        if (isset($options['heroimageoverlay']) && (int) $options['heroimageoverlay'] >= 0) {
            $overlay = max(0, min(100, (int) $options['heroimageoverlay']));
            $css .= '--acf-hero-scrim-a:' . number_format($overlay / 100, 2, '.', '') . ';';
        }
        return $css;
    }

    /**
     * Called on EVERY page where this course format is relevant.
     *
     * Only adds body classes - hero injection is handled separately via CSS + JS.
     * Kept minimal to avoid PHP fatal errors from function calls too early.
     *
     * @param \moodle_page $page The page being set up.
     * @return void
     */
    public function page_set_course(\moodle_page $page) {
        parent::page_set_course($page);

        // Add format body class for CSS selectors.
        $page->add_body_class('format-aicourse');
        $page->add_body_class('aicourse-clean-view');

        // FIX-GRADER-PHPCLASS (v1.7.56): Add aicourse-is-grader via PHP so CSS rules fire
        // immediately without FOUC. Covers teachers and non-editing teachers (grade/report:viewall).
        // isloggedin() guard keeps this safe during early page setup before full auth.
        if (isloggedin() && !isguestuser()) {
            try {
                $coursecontext = \context_course::instance($page->course->id);
                if (permissions::is_grader($coursecontext)) {
                    $page->add_body_class('aicourse-is-grader');
                }
                // ACF-FIX-2.1.4: aicourse-is-grader is true for every teacher, manager and
                // admin, and styles.css used it to hide the hero banner outright. That
                // contradicted format.php, which deliberately renders the hero for anyone who
                // can edit so they can reach the "Generate banner" button (FIX-ACF-EDITOR-HERO,
                // v1.7.68) -- so the banner was rendered and then hidden again by CSS, for every
                // editing user, in and out of edit mode. CSS cannot ask about capabilities, so
                // the distinction the PHP already makes is published as its own class and the
                // hide rule is scoped with :not(.aicourse-can-edit).
                if (has_capability('moodle/course:update', $coursecontext)) {
                    $page->add_body_class('aicourse-can-edit');
                }
                // ACF-FIX-2.1.9: colour mode, see callbacks::get_colour_mode_class().
                $colourclass = \format_aicourse\local\callbacks::get_colour_mode_class();
                if ($colourclass !== '') {
                    $page->add_body_class($colourclass);
                }
                // ACF-FIX-2.1.12: hero banner overlay strength.
                $scrimclass = \format_aicourse\local\callbacks::get_scrim_class();
                if ($scrimclass !== '') {
                    $page->add_body_class($scrimclass);
                }
                // ACF-FIX-2.1.23: secondary-nav visibility and card layout. Read here, with
                // the other body classes, so they are written into the <body> tag rather than
                // patched on after render — no flash of the layout the teacher turned off.
                // get_format_options() is already loaded at this point in the page lifecycle;
                // a fresh course being created has no options row yet, hence the defaults.
            } catch (\Throwable $e) {
                // Context not ready yet — hook JS fallback will handle it.
                unset($e);
            }
        }

        // ACF-FIX-2.1.25 (settings audit): these two are LAYOUT, not permission, and they were
        // being computed inside the isloggedin() && !isguestuser() branch above -- so a guest,
        // or anyone viewing a course that allows guest access, got neither class and saw the
        // navigation the teacher had hidden and the grid they had switched to a list. They need
        // no capability check and no context, so they sit outside that branch.
        try {
            $navopts = $this->get_format_options();
            $hidenav = isset($navopts['hidesecondarynav']) ? (int) $navopts['hidesecondarynav'] : 1;
            // ACF-FIX-2.1.31: a site-wide OVERRIDE, distinct from the site DEFAULT above it.
            // The default only seeds new courses; an existing course that has ever saved its
            // settings form has its own stored value and ignores it forever. This forces the
            // behaviour on every course at once, which is what an administrator actually wants
            // when they decide the tabs should not be shown to learners anywhere. -1 (the
            // default) means "leave each course alone".
            $forcenav = get_config('format_aicourse', 'forcehidesecondarynav');
            if ($forcenav !== false && $forcenav !== '' && (int) $forcenav >= 0) {
                $hidenav = (int) $forcenav;
            }
            if ($hidenav === 1) {
                $page->add_body_class('aicourse-hidenav-students');
            } else if ($hidenav === 2) {
                $page->add_body_class('aicourse-hidenav-all');
            }
            if (!empty($navopts['cardlayout'])) {
                $page->add_body_class('aicourse-cardlist');
            }
            // Published as a class so the CSS can stop reserving space at the top of the
            // content column when the banner is about to be moved out of it, and so the
            // JS has a server-rendered signal rather than re-reading the option.
            if (!empty($navopts['heroattop'])) {
                $page->add_body_class('aicourse-hero-attop');
            }
        } catch (\Throwable $e) {
            unset($e);
        }

        // ACF-FIX-2.1: section.php renders the standard Moodle section layout, which this format
        // replaces with activity cards. The redirect to course/view.php used to be an inline
        // <script>window.location.replace(...)</script> emitted from the FOOTER hook — i.e. after
        // the entire page had already been generated and streamed. Every section click therefore
        // cost two full page renders and flashed the wrong layout, and it did nothing at all
        // without JavaScript. Doing it here, during require_login(), means one render and no
        // flash. The lookup is scoped to this course so a section id belonging to another course
        // cannot redirect here with a foreign section number.
        if (!$page->user_is_editing() && $this->is_section_php_request()) {
            $sectionid = optional_param('id', 0, PARAM_INT);
            if ($sectionid > 0) {
                global $DB;
                $sectionnum = $DB->get_field('course_sections', 'section', [
                    'id' => $sectionid,
                    'course' => $page->course->id,
                ]);
                if ($sectionnum !== false) {
                    redirect(new \moodle_url('/course/view.php', [
                        'id' => $page->course->id,
                        'section' => $sectionnum,
                    ]));
                }
            }
        }

        // ACF-FIX-2.1: body classes are added here, not from format.php. format.php is included
        // after $OUTPUT->header() has already written the <body> tag, so it could only patch
        // these on with an inline <script> — which any Content-Security-Policy that forbids
        // inline script rejects, and which caused a flash of unstyled content. page_set_course()
        // runs during require_login(), well before output starts. The section parameter is read
        // straight from the request rather than from $PAGE->url so this does not depend on
        // course/view.php having called set_url() first.
        try {
            $options = $this->get_format_options();
            $sectionparam = optional_param('section', null, PARAM_INT);
            $hassection = ($sectionparam !== null);

            if (!empty($options['displayascards']) && !$hassection) {
                $page->add_body_class('aicourse-cardview');
            }

            // Course index visibility is a bitmask: bit 0 = course home, bit 1 = section pages.
            $courseindexsetting = isset($options['showcourseindex']) ? (int) $options['showcourseindex'] : 7;
            $showindex = $hassection
                ? (($courseindexsetting & 2) !== 0)
                : (($courseindexsetting & 1) !== 0);
            if (!$showindex && !$page->user_is_editing()) {
                $page->add_body_class('aicourse-hideindex');
            }

            // Must match the renderer choice in format.php, which also requires !user_is_editing().
            // Without that guard the class was added for an editing teacher while the standard
            // renderer was used, and the section page rendered blank.
            if ($hassection && !empty($options['activitydisplaymode']) && !$page->user_is_editing()) {
                $page->add_body_class('aicourse-activitycards');
            }
        } catch (\Throwable $e) {
            // Format options are unavailable this early on some pages; the classes are cosmetic.
            unset($e);
        }

        // Moodle 4.0-4.2 fallback: Hook API doesn't exist, so enqueue JS fallback
        // The hero HTML will be set via extend_navigation_course() which runs later.
        if (!class_exists('\core\hook\output\before_standard_footer_html_generation')) {
            $page->requires->js_call_amd('format_aicourse/courseformat', 'injectHeroFallback');
        }
    }

    /**
     * Whether the current request is for /course/section.php.
     *
     * Kept as a separate method so it can be overridden in tests without touching superglobals.
     *
     * @return bool True when the running script is course/section.php.
     */
    protected function is_section_php_request(): bool {
        return \format_aicourse\local\callbacks::is_section_php_request();
    }

    /**
     * Blocks added to a new course by default: none.
     *
     * @return array Empty block lists for both regions.
     */
    public function get_default_blocks() {
        return [
            BLOCK_POS_LEFT => [],
            BLOCK_POS_RIGHT => [],
        ];
    }

    /**
     * Default name for a section that the teacher has not renamed.
     *
     * @param \stdClass|\section_info $section The section.
     * @return string Localised section name.
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            return get_string('section0name', 'format_aicourse');
        }
        // ACF-FIX-2.0: i18n — build the name from a single placeholder string instead of
        // concatenating a translated fragment with the number (fixed English word order).
        return get_string('sectionnumber', 'format_aicourse', $section->section);
    }

    /**
     * Definitions of this format's course-level options.
     *
     * @param bool $foreditform Whether the definitions are for the course edit form.
     * @return array Option definitions.
     */
    public function course_format_options($foreditform = false) {
        $options = parent::course_format_options($foreditform);

        // ACF-FIX-2.0: The site-level admin defaults were registered in settings.php but never
        // read, so "Show hero banner" / "Display as cards" defaults were ignored for new courses.
        // ACF-FIX-2.1.25 (settings audit). Every course option now has a matching site-level
        // default, read through one helper instead of a growing pile of ad-hoc get_config()
        // pairs. Before this, only "Show hero banner" and "Display as cards" had site defaults
        // and the other twelve options ignored whatever an administrator set -- the admin page
        // and the course form disagreed about what the default even was.
        $d = fn(string $name, $fallback) => self::site_default($name, $fallback);

        $courseformatoptions = [
            'showherobanner' => [
                'default' => $d('showherobanner', 1),
                'type' => PARAM_INT,
            ],
            'shownavchevrons' => [
                'default' => $d('shownavchevrons', 1),
                'type' => PARAM_INT,
            ],
            'showcourseindex' => [
                'default' => $d('showcourseindex', 7),
                'type' => PARAM_INT,
            ],
            'displayascards' => [
                'default' => $d('displayascards', 1),
                'type' => PARAM_INT,
            ],
            // Off by default. With it off the section cards render exactly as they always have;
            // the card renderer emits no activity list at all, so a default course is unchanged.
            'showactivitiesoncards' => [
                'default' => $d('showactivitiesoncards', 0),
                'type' => PARAM_INT,
            ],
            'activitydisplaymode' => [
                'default' => $d('activitydisplaymode', 1),
                'type' => PARAM_INT,
            ],
            'cardtitlesize' => [
                'default' => $d('cardtitlesize', 14),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.23: hide Moodle's secondary navigation (Course / Settings /
            // Participants / Grades / Reports / More) on this course's pages.
            // 0 = show, 1 = hide from users who cannot edit the course, 2 = hide from everyone.
            // Enforcement is CSS-only and deliberately so: this is a decluttering preference,
            // NOT an access control. Every tab it hides remains reachable by URL and is still
            // guarded by its own capability check, exactly as before.
            'hidesecondarynav' => [
                // ACF-FIX-2.1.30: defaults to 1 (hide from students). The tabs are chrome a learner
                // never needs -- most sites hide Participants anyway -- and on this format they push the
                // course cards a long way down the page for no gain. Teachers keep them.
                'default' => $d('hidesecondarynav', 1),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.23: 0 = responsive grid (unchanged), 1 = one full-width card per row.
            'cardlayout' => [
                'default' => $d('cardlayout', 0),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.23: per-course accent colour, '#rrggbb' or '' to follow the site
            // setting (which in turn falls back to the theme's primary). PARAM_TEXT rather
            // than an unfiltered type, because the value is written into a style attribute; it
            // is additionally regex-validated at the point of use, in get_accent_style(), so a
            // value restored from an old backup or edited in the database cannot inject CSS.
            'accentcolour' => [
                'default' => (string) $d('accentcolour', ''),
                'type' => PARAM_TEXT,
            ],
            // Percentage of the accent colour mixed into the hero's background in gradient
            // mode. 0 = the plain card surface, 100 = the accent at full strength. Clamped
            // to 0-100 on read.
            'herobannerfade' => [
                'default' => $d('herobannerfade', 3),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.23: opacity of the dark overlay between the banner IMAGE and the
            // text, as a percentage. 0 = no overlay, 100 = solid. Empty/0 is a real choice,
            // so -1 is used as "not set, follow the site's Banner overlay strength".
            'heroimageoverlay' => [
                'default' => $d('heroimageoverlay', -1),
                'type' => PARAM_INT,
            ],
            // Per-course opt in to sending assessment answer keys to the external AI service.
            // The SITE setting is the ceiling: this value is only ever consulted when the site
            // setting is "let each course decide", so a course can never opt into something the
            // site has not permitted. That check lives in exactly one place,
            // \format_aicourse\local\contentindex::may_share_assessment_answers(), which is where
            // the value is read -- deliberately NOT here, where it is merely stored.
            // ACF-FIX-2.1.26: lift the hero above the page header and the secondary navigation.
            // See amd/src/heroatop.js for why this cannot be done in CSS or server-side.
            // 1 = on (default), 0 = leave the banner where the server rendered it.
            'heroattop' => [
                'default' => $d('heroattop', 1),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.25: 0 = list every activity and let the card grow to fit, which is
            // the point of switching the list on. Any positive number caps the list and shows
            // a "+N" chip for the rest.
            'cardactivitylimit' => [
                'default' => $d('cardactivitylimit', 0),
                'type' => PARAM_INT,
            ],
            'shareassessmentanswers' => [
                'default' => $d('shareassessmentanswers', 0),
                'type' => PARAM_INT,
            ],
        ];

        if ($foreditform) {
            $optionsedit = [
                'showherobanner' => [
                    'label' => get_string('showherobanner', 'format_aicourse'),
                    'help' => 'showherobanner',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => get_string('no'),
                            1 => get_string('yes'),
                        ],
                    ],
                ],
                'shownavchevrons' => [
                    'label' => get_string('shownavchevrons', 'format_aicourse'),
                    'help' => 'shownavchevrons',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => get_string('no'),
                            1 => get_string('yes'),
                        ],
                    ],
                ],
                'showcourseindex' => [
                    'label' => get_string('showcourseindex', 'format_aicourse'),
                    'help' => 'showcourseindex',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => get_string('courseindex_none', 'format_aicourse'),
                            1 => get_string('courseindex_home', 'format_aicourse'),
                            2 => get_string('courseindex_section', 'format_aicourse'),
                            3 => get_string('courseindex_home_section', 'format_aicourse'),
                            4 => get_string('courseindex_activity', 'format_aicourse'),
                            5 => get_string('courseindex_home_activity', 'format_aicourse'),
                            6 => get_string('courseindex_section_activity', 'format_aicourse'),
                            7 => get_string('courseindex_all', 'format_aicourse'),
                        ],
                    ],
                ],
                'displayascards' => [
                    'label' => get_string('displayascards', 'format_aicourse'),
                    'help' => 'displayascards',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => get_string('displayassections', 'format_aicourse'),
                            1 => get_string('displayascardsoption', 'format_aicourse'),
                        ],
                    ],
                ],
                'showactivitiesoncards' => [
                    'label' => get_string('showactivitiesoncards', 'format_aicourse'),
                    'help' => 'showactivitiesoncards',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => get_string('no'),
                            1 => get_string('yes'),
                        ],
                    ],
                ],
                'activitydisplaymode' => [
                    'label' => get_string('activitydisplaymode', 'format_aicourse'),
                    'help' => 'activitydisplaymode',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => get_string('activitydisplaystandard', 'format_aicourse'),
                            1 => get_string('activitydisplaycards', 'format_aicourse'),
                        ],
                    ],
                ],
                'cardtitlesize' => [
                    'label' => get_string('cardtitlesize', 'format_aicourse'),
                    'help' => 'cardtitlesize',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ],
                'hidesecondarynav' => [
                    'label' => get_string('hidesecondarynav', 'format_aicourse'),
                    'help' => 'hidesecondarynav',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => get_string('hidesecondarynav_show', 'format_aicourse'),
                            1 => get_string('hidesecondarynav_students', 'format_aicourse'),
                            2 => get_string('hidesecondarynav_all', 'format_aicourse'),
                        ],
                    ],
                ],
                'accentcolour' => [
                    'label' => get_string('accentcolour', 'format_aicourse'),
                    'help' => 'accentcolour',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ],
                'herobannerfade' => [
                    'label' => get_string('herobannerfade', 'format_aicourse'),
                    'help' => 'herobannerfade',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ],
                'heroimageoverlay' => [
                    'label' => get_string('heroimageoverlay', 'format_aicourse'),
                    'help' => 'heroimageoverlay',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ],
                'heroattop' => [
                    'label' => get_string('heroattop', 'format_aicourse'),
                    'help' => 'heroattop',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => get_string('no'),
                            1 => get_string('yes'),
                        ],
                    ],
                ],
                'cardactivitylimit' => [
                    'label' => get_string('cardactivitylimit', 'format_aicourse'),
                    'help' => 'cardactivitylimit',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ],
                'cardlayout' => [
                    'label' => get_string('cardlayout', 'format_aicourse'),
                    'help' => 'cardlayout',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => get_string('cardlayout_grid', 'format_aicourse'),
                            1 => get_string('cardlayout_list', 'format_aicourse'),
                        ],
                    ],
                ],
                // Always offered, on every site, so that the value can be set, imported and
                // restored regardless of what the site setting happens to be today -- core's
                // validate_format_options() filters submitted data against THIS array, so an
                // option omitted here could never be stored at all. Nothing is weakened by that:
                // the ceiling is enforced in exactly one place, when the value is READ, by
                // contentindex::may_share_assessment_answers(). The help string says plainly that
                // the setting does nothing until an administrator allows per-course control.
                'shareassessmentanswers' => [
                    'label' => get_string('shareassessmentanswers', 'format_aicourse'),
                    'help' => 'shareassessmentanswers',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => get_string('no'),
                            1 => get_string('yes'),
                        ],
                    ],
                ],
            ];

            $courseformatoptions = array_merge_recursive($courseformatoptions, $optionsedit);
        }

        return array_merge($options, $courseformatoptions);
    }

    /**
     * Whether the format uses Moodle's reactive course editor components.
     *
     * This is a promise to core: returning true makes core load core_courseformat's reactive state
     * manager and stop emitting the legacy non-reactive editing controls. The promise is kept by
     * amd/src/local/cardcontent.js, which registers the section cards and the activity items with
     * the course editor returned by core_courseformat/courseeditor, so that:
     *
     *  - section cards are dragged and dropped through core's sectionMoveAfter mutation;
     *  - activities are dragged within and between sections through core's cmMove mutation;
     *  - the grab handle's data-action="moveSection", and every core action menu inside the card
     *    region, are dispatched by core_courseformat/local/content/actions, which supplies the
     *    keyboard accessible move dialogues;
     *  - watchers on course.sectionlist, section.cmlist and each section keep the card order, the
     *    card names and the card visibility in step with the state without a page reload.
     *
     * @return bool True.
     */
    public function supports_components() {
        return true;
    }

    /**
     * Whether the format shows the course index drawer.
     *
     * @return bool True.
     */
    public function uses_course_index() {
        return true;
    }

    /**
     * Whether the format is section based.
     *
     * @return bool True.
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Whether activities can be indented inside a section.
     *
     * @return bool True.
     */
    public function uses_indentation(): bool {
        return true;
    }

    /**
     * Add the custom banner image filemanager to the course edit form.
     *
     * Only shown at course level (not section level).
     *
     * @param \MoodleQuickForm $mform The form being built.
     * @param bool $forsection Whether the form is the section edit form.
     * @return array The elements added to the form.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        $elements = parent::create_edit_form_elements($mform, $forsection);

        if (!$forsection) {
            // Banner Image section.
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

            // ACF-FIX-2.0: Prepare the draft file area HERE. The plugin previously did this in a
            // set_edit_form_data() method, which is NOT a core API — \core_courseformat\base
            // defines no such method and nothing ever calls it. The filemanager therefore always
            // submitted draftitemid 0, and update_course_format_options() saved that empty draft
            // over the stored file, deleting the teacher's banner every time they saved the
            // course settings. create_edit_form_elements() is called from
            // course_edit_form::definition_after_data(), so setDefault() applies to the element
            // that was just added.
            $course = $this->get_course();
            if ($course && !empty($course->id)) {
                $draftitemid = 0;
                file_prepare_draft_area(
                    $draftitemid,
                    context_course::instance($course->id)->id,
                    'format_aicourse',
                    'bannerimage',
                    \format_aicourse\local\banner::BANNER_ITEMID,
                    ['maxbytes' => 5 * 1024 * 1024, 'maxfiles' => 1, 'subdirs' => 0]
                );
                $mform->setDefault('bannerimage', $draftitemid);
            }
        }

        return $elements;
    }

    /**
     * Save the banner image and update the standard format options.
     *
     * @param stdClass|array $data Submitted form data.
     * @param stdClass|null $oldcourse Previous course record when the format is being changed.
     * @return bool Whether any option was updated.
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $course = $this->get_course();

        if (is_array($data)) {
            $data = (object) $data;
        }

        // ACF-FIX-2.0: Only touch the file area when a REAL draft item id was submitted.
        // A missing or zero bannerimage means "this save did not come from the banner form"
        // (course creation, restore, "change format", web service, ...). Calling
        // file_save_draft_area_files() with 0 wipes the stored banner, which is exactly the
        // data-loss bug this guard prevents.
        if ($course && !empty($course->id) && isset($data->bannerimage)) {
            $draftitemid = (int) $data->bannerimage;
            if ($draftitemid > 0) {
                $context = context_course::instance($course->id);
                file_save_draft_area_files(
                    $draftitemid,
                    $context->id,
                    'format_aicourse',
                    'bannerimage',
                    \format_aicourse\local\banner::BANNER_ITEMID,
                    ['maxbytes' => 5 * 1024 * 1024, 'maxfiles' => 1, 'subdirs' => 0]
                );
            }
            // Prevent parent from storing the draftitemid in course_format_options.
            unset($data->bannerimage);
        }

        return parent::update_course_format_options($data, $oldcourse);
    }
}

/**
 * Serve banner image files from the format_aicourse component.
 *
 * Moodle looks this function up by name, so it must stay in lib.php. The implementation lives
 * in {@see \format_aicourse\local\callbacks::pluginfile()}.
 *
 * @param stdClass $course Course record.
 * @param cm_info|null $cm Course module, unused for this file area.
 * @param context $context Context the file lives in.
 * @param string $filearea File area name.
 * @param array $args Remaining URL path components.
 * @param bool $forcedownload Whether the file must be sent as a download.
 * @param array $options Additional options affecting file serving.
 * @return void Always sends the file or a "not found" response.
 */
function format_aicourse_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    callbacks::pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options);
}

/**
 * Callback for inplace editable elements.
 *
 * Moodle looks this function up by name, so it must stay in lib.php. The implementation lives
 * in {@see \format_aicourse\local\callbacks::inplace_editable()}.
 *
 * @param string $itemtype Type of the edited item.
 * @param int $itemid Id of the edited item.
 * @param string $newvalue The new value submitted by the user.
 * @return \core\output\inplace_editable|null The refreshed element, or null when unsupported.
 */
function format_aicourse_inplace_editable($itemtype, $itemid, $newvalue) {
    return callbacks::inplace_editable($itemtype, $itemid, $newvalue);
}

/**
 * Section card fragment renderer.
 *
 * Moodle's fragment API looks this function up by name (component "format_aicourse", fragment
 * "sectioncard"), so it must stay in lib.php. It re-renders one section card of the course home
 * grid, which is how amd/src/local/cardcontent.js keeps the "N activities" count, the percentage
 * badge and the progress dots correct after an activity has been moved into or out of a section:
 * none of those values exist in the reactive state, so they can only come from the server. This
 * mirrors core_courseformat_output_fragment_section(), which reloads a whole section for the same
 * reason. The implementation, including the access checks, lives in
 * {@see \format_aicourse\output\courseformat\content::render_section_card_fragment()}.
 *
 * @param array $args The fragment arguments: courseid and id (the section record id).
 * @return string The rendered section card.
 */
function format_aicourse_output_fragment_sectioncard($args): string {
    return \format_aicourse\output\courseformat\content::render_section_card_fragment((array) $args);
}

/**
 * Universal navigation callback for ALL Moodle 4.x/5.x versions.
 *
 * Moodle looks this function up by name, so it must stay in lib.php. The implementation lives
 * in {@see \format_aicourse\local\callbacks::extend_navigation_course()}.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @return void
 */
function format_aicourse_extend_navigation_course($navigation, $course, $context) {
    callbacks::extend_navigation_course($navigation, $course, $context);
}
