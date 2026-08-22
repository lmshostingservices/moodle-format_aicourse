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
        // ACF-FIX-2.1.61: a course's own colour first, then the site's, then a site-wide force.
        //
        // Previously the accent existed only per course, so a site wanting one palette across
        // every course had to set it course by course and remember to do it again on each new one.
        // The site default seeds courses that have not chosen; the force overrides every course at
        // once, which is what an administrator setting a brand actually wants.
        $colour = isset($options['accentcolour']) ? trim((string) $options['accentcolour']) : '';
        if ($colour === '') {
            $colour = trim((string) get_config('format_aicourse', 'defaultaccentcolour'));
        }
        $forced = trim((string) get_config('format_aicourse', 'forceaccentcolour'));
        if ($forced !== '') {
            $colour = $forced;
        }
        if ($colour !== '' && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $colour)) {
            $css .= '--acf-brand:' . $colour . ';';
        }

        // ACF-FIX-2.1.85: the sidebar header band, same three-step resolution as the accent.
        $band = isset($options['playerheadercolour']) ? trim((string) $options['playerheadercolour']) : '';
        if ($band === '') {
            $band = trim((string) get_config('format_aicourse', 'defaultplayerheadercolour'));
        }
        $forceband = trim((string) get_config('format_aicourse', 'forceplayerheadercolour'));
        if ($forceband !== '') {
            $band = $forceband;
        }
        if ($band !== '' && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $band)) {
            $css .= '--acf-player-header-bg:' . $band . ';';
        }

        // ACF-FIX-2.1.124: the card surface, colour and opacity, same three-step resolution.
        $cardcolour = isset($options['cardcolour']) ? trim((string) $options['cardcolour']) : '';
        if ($cardcolour === '') {
            $cardcolour = trim((string) get_config('format_aicourse', 'defaultcardcolour'));
        }
        $forcecard = trim((string) get_config('format_aicourse', 'forcecardcolour'));
        if ($forcecard !== '') {
            $cardcolour = $forcecard;
        }

        $cardopacity = isset($options['cardopacity']) ? (int) $options['cardopacity'] : -1;
        if ($cardopacity < 0) {
            $cardopacity = (int) get_config('format_aicourse', 'defaultcardopacity');
        }
        $forceopacity = get_config('format_aicourse', 'forcecardopacity');
        if ($forceopacity !== false && $forceopacity !== '' && (int) $forceopacity >= 0) {
            $cardopacity = (int) $forceopacity;
        }
        // Clamped rather than rejected: a value outside 0-100 is a typo, and the nearest valid
        // shade is a better answer than silently ignoring what was asked for.
        $cardopacity = max(0, min(100, $cardopacity));

        // ACF-FIX-2.1.126: the section heading band, and the activity icon colour. Both default
        // to the accent when unset, so they are published only when someone chooses otherwise.
        foreach (
            ['indexheadingcolour' => '--acf-index-heading-bg',
                  'indexiconcolour' => '--acf-index-icon'] as $optname => $var
        ) {
            $val = isset($options[$optname]) ? trim((string) $options[$optname]) : '';
            if ($val === '') {
                $val = trim((string) get_config('format_aicourse', 'default' . $optname));
            }
            $forced = trim((string) get_config('format_aicourse', 'force' . $optname));
            if ($forced !== '') {
                $val = $forced;
            }
            if ($val !== '' && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $val)) {
                $css .= $var . ':' . $val . ';';
            }
        }

        // ACF-FIX-2.1.125: the course index surface, resolved the same way. Left unset it is not
        // published at all, and the stylesheet falls through to the card colour -- so the two match
        // by default and only diverge if someone asks them to.
        $indexcolour = isset($options['indexcolour']) ? trim((string) $options['indexcolour']) : '';
        if ($indexcolour === '') {
            $indexcolour = trim((string) get_config('format_aicourse', 'defaultindexcolour'));
        }
        $forceindex = trim((string) get_config('format_aicourse', 'forceindexcolour'));
        if ($forceindex !== '') {
            $indexcolour = $forceindex;
        }
        $indexopacity = isset($options['indexopacity']) ? (int) $options['indexopacity'] : -1;
        if ($indexopacity < 0) {
            $indexopacity = (int) get_config('format_aicourse', 'defaultindexopacity');
        }
        $forceindexop = get_config('format_aicourse', 'forceindexopacity');
        if ($forceindexop !== false && $forceindexop !== '' && (int) $forceindexop >= 0) {
            $indexopacity = (int) $forceindexop;
        }
        $indexopacity = max(0, min(100, $indexopacity));
        if ($indexcolour !== '' && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $indexcolour)) {
            $css .= '--acf-index-bg:color-mix(in srgb,' . $indexcolour . ' '
                . $indexopacity . '%,#fff);';
        }

        if ($cardcolour !== '' && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $cardcolour)) {
            // Mixing with color-mix rather than an alpha channel: the card sits on the page background, and an
            // alpha would let whatever is behind it show through -- including the banner where they
            // overlap on scroll. Mixing toward white keeps the card opaque at any strength.
            $css .= '--acf-surface-card:color-mix(in srgb,' . $cardcolour . ' '
                . $cardopacity . '%,#fff);';
        }
        if (isset($options['herobannerfade']) && $options['herobannerfade'] !== '') {
            $fade = max(0, min(100, (int) $options['herobannerfade']));
            $css .= '--acf-hero-wash-tint:' . $fade . '%;';
        }
        // The default of -1 means "not set" -- leave the property absent so the site-wide
        // Banner overlay strength class keeps control. 0 is a real value: no overlay at all.
        // ACF-FIX-2.1.40: a site-wide override, distinct from the site DEFAULT. The default only
        // seeds new courses; an existing course keeps its own stored value and ignores it, which
        // is why changing the default looks like it does nothing. -1 means "leave each course
        // alone" and is the shipped value.
        $overlayvalue = isset($options['heroimageoverlay']) ? (int) $options['heroimageoverlay'] : -1;
        $forceoverlay = get_config('format_aicourse', 'forceheroimageoverlay');
        if ($forceoverlay !== false && $forceoverlay !== '' && (int) $forceoverlay >= 0) {
            $overlayvalue = (int) $forceoverlay;
        }
        // A value of -1 still means "not set": leave the property absent so the site-wide Banner
        // overlay strength class keeps control. 0 is a real value meaning no overlay at all.
        if ($overlayvalue >= 0) {
            $overlay = max(0, min(100, $overlayvalue));
            $css .= '--acf-hero-scrim-a:' . number_format($overlay / 100, 2, '.', '') . ';';
        }
        return $css;
    }

    /**
     * Set the course index drawer's starting state, once, on a user's first visit to a course.
     *
     * ACF-FIX-2.1.55. Moodle opens the course index by default and remembers whatever the user
     * last chose, site-wide. On a format that already carries the course in its banner and cards,
     * a drawer covering a third of the screen on arrival is often not what a teacher wants their
     * learners to meet first.
     *
     * This writes core's own `drawer-open-index` preference, so the drawer renders in the chosen
     * state server-side and there is no flash of it opening and closing. It is written ONCE per
     * user per course: after that the user's own toggle is respected, because a setting that
     * reimposed itself on every page load would be fighting the person using it.
     *
     * @param \stdClass $course The course being entered.
     * @return void
     */
    protected static function apply_initial_index_state(\stdClass $course): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }

        $marker = 'format_aicourse_indexstate_' . (int) $course->id;
        if (get_user_preferences($marker, 0)) {
            return;
        }

        $options = course_get_format($course)->get_format_options();
        $state = isset($options['indexstate']) ? (int) $options['indexstate'] : -1;
        if ($state < 0) {
            $state = (int) (get_config('format_aicourse', 'defaultindexstate') ?: 0);
        }
        $force = get_config('format_aicourse', 'forceindexstate');
        if ($force !== false && $force !== '' && (int) $force >= 0) {
            $state = (int) $force;
        }

        // Mark the visit whichever way it goes, so "leave it alone" is also decided only once and
        // a later change to the setting does not reach back into courses people have already
        // opened and had a preference in.
        set_user_preference($marker, 1);

        // 0 means leave Moodle's own behaviour alone.
        if ($state === 1) {
            set_user_preference('drawer-open-index', false);
        } else if ($state === 2) {
            set_user_preference('drawer-open-index', true);
        }
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

        // ACF-FIX-2.1.55: set the course index drawer's starting state on a user's first visit.
        self::apply_initial_index_state($this->get_course());

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
            // ACF-FIX-2.1.102: the General section. Same three states as its neighbours.
            $gen = isset($navopts['hidegeneral']) ? (int) $navopts['hidegeneral'] : -1;
            if ($gen < 0) {
                $gen = (int) (get_config('format_aicourse', 'defaulthidegeneral') ?: 0);
            }
            $forcegen = get_config('format_aicourse', 'forcehidegeneral');
            if ($forcegen !== false && $forcegen !== '' && (int) $forcegen >= 0) {
                $gen = (int) $forcegen;
            }
            if ($gen === 1) {
                $page->add_body_class('aicourse-hidegeneral-students');
            } else if ($gen === 2) {
                $page->add_body_class('aicourse-hidegeneral-all');
            }
            // ACF-FIX-2.1.130: the estimated-time pills, four places, resolved in one loop rather
            // than four near-identical blocks.
            foreach (
                [
                'hidetimeindex' => 'aicourse-notime-index',
                'hidetimesectioncards' => 'aicourse-notime-sectioncards',
                'hidetimeactivitycards' => 'aicourse-notime-activitycards',
                'hidetimetotal' => 'aicourse-notime-total',
                ] as $timeopt => $timeclass
            ) {
                $tv = isset($navopts[$timeopt]) ? (int) $navopts[$timeopt] : -1;
                if ($tv < 0) {
                    $tv = (int) (get_config('format_aicourse', 'default' . $timeopt) ?: 0);
                }
                $tf = get_config('format_aicourse', 'force' . $timeopt);
                if ($tf !== false && $tf !== '' && (int) $tf >= 0) {
                    $tv = (int) $tf;
                }
                if ($tv === 1) {
                    $page->add_body_class($timeclass);
                }
            }
            // ACF-FIX-2.1.144: the sticky banner. On unless switched off.
            $sticky = isset($navopts['herosticky']) ? (int) $navopts['herosticky'] : -1;
            if ($sticky < 0) {
                $stickydefault = get_config('format_aicourse', 'defaultherosticky');
                $sticky = ($stickydefault === false || $stickydefault === '') ? 1 : (int) $stickydefault;
            }
            $forcesticky = get_config('format_aicourse', 'forceherosticky');
            if ($forcesticky !== false && $forcesticky !== '' && (int) $forcesticky >= 0) {
                $sticky = (int) $forcesticky;
            }
            if ($sticky === 1) {
                $page->add_body_class('aicourse-hero-sticky');
            }
            // ACF-FIX-2.1.80: the logo band. Same three states as the settings around it.
            $imm = isset($navopts['immersive']) ? (int) $navopts['immersive'] : -1;
            if ($imm < 0) {
                $imm = (int) (get_config('format_aicourse', 'defaultimmersive') ?: 0);
            }
            $forceimm = get_config('format_aicourse', 'forceimmersive');
            if ($forceimm !== false && $forceimm !== '' && (int) $forceimm >= 0) {
                $imm = (int) $forceimm;
            }
            if ($imm === 1) {
                $page->add_body_class('aicourse-hidelogoband-students');
            } else if ($imm === 2) {
                $page->add_body_class('aicourse-hidelogoband-all');
            }
            // ACF-FIX-2.1.50: footer, same three states and the same site-wide override.
            $hidefooter = isset($navopts['hidefooter']) ? (int) $navopts['hidefooter'] : 0;
            $forcefooter = get_config('format_aicourse', 'forcehidefooter');
            if ($forcefooter !== false && $forcefooter !== '' && (int) $forcefooter >= 0) {
                $hidefooter = (int) $forcefooter;
            }
            if ($hidefooter === 1) {
                $page->add_body_class('aicourse-hidefooter-students');
            } else if ($hidefooter === 2) {
                $page->add_body_class('aicourse-hidefooter-all');
            }
            // ACF-FIX-2.1.36: breadcrumb, same three states and the same site-wide override.
            $hidecrumb = isset($navopts['hidebreadcrumb']) ? (int) $navopts['hidebreadcrumb'] : 0;
            $forcecrumb = get_config('format_aicourse', 'forcehidebreadcrumb');
            if ($forcecrumb !== false && $forcecrumb !== '' && (int) $forcecrumb >= 0) {
                $hidecrumb = (int) $forcecrumb;
            }
            if ($hidecrumb === 1) {
                $page->add_body_class('aicourse-hidecrumb-students');
            } else if ($hidecrumb === 2) {
                $page->add_body_class('aicourse-hidecrumb-all');
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

            // Course index visibility is a bitmask: bit 0 = course home, bit 1 = section pages,
            // bit 2 = activity pages.
            //
            // ACF-FIX-2.1.155: bit 2 was never read here. page_set_course() runs on EVERY page that
            // sets a course, activity pages included, and the only branch was "is there a section
            // parameter" -- so an activity page fell into the course-home arm and was judged on
            // bit 0, the wrong bit entirely.
            //
            // The other half of the decision lives in the footer hook, which reads bit 2 correctly
            // and pushes the class through bodyclass.js. That module can only ADD a class, never
            // remove one, so the two halves could not correct each other -- they could only ever
            // add up:
            //
            //   showcourseindex = 4 (activity only):  bit 0 is clear, so THIS code hid the index on
            //     an activity page. The hook wanted it shown and therefore added nothing. The index
            //     was hidden on precisely the pages the setting exists to show it on.
            //   showcourseindex = 1 (home only):      bit 0 is set, so nothing was added here. The
            //     hook wanted it hidden and added the class AFTER the page had rendered -- the index
            //     appeared, then vanished, taking the whole content column through a relayout.
            //
            // Values 3 and 7 agree by coincidence, which is why this survived: 7 is the default.
            //
            // Detecting a module page: $page->cm and the page context are both authoritative when
            // set, but require_login() reaches set_course() through several routes and neither is
            // guaranteed to be populated yet at this point. The script path is the backstop. All
            // three are inside the existing try/catch, so a context that is not yet set costs the
            // body class rather than the page.
            $courseindexsetting = isset($options['showcourseindex']) ? (int) $options['showcourseindex'] : 7;

            $isactivitypage = false;
            if (!empty($page->cm)) {
                $isactivitypage = true;
            } else {
                $pagecontext = $page->context;
                if ($pagecontext instanceof \context_module) {
                    $isactivitypage = true;
                } else {
                    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
                    $isactivitypage = (strpos($script, '/mod/') !== false
                        && substr($script, -strlen('/view.php')) === '/view.php');
                }
            }

            if ($isactivitypage) {
                $showindex = (($courseindexsetting & 4) !== 0);
            } else if ($hassection) {
                $showindex = (($courseindexsetting & 2) !== 0);
            } else {
                $showindex = (($courseindexsetting & 1) !== 0);
            }

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
            // ACF-FIX-2.1.89: 0 leaves the tab bar where the theme puts it, 1 moves it into the
            // site header for users who can edit.
            'coursenavplace' => [
                'default' => $d('coursenavplace', 0),
                'type' => PARAM_INT,
            ],
            'hidesecondarynav' => [
                // ACF-FIX-2.1.30: defaults to 1 (hide from students). The tabs are chrome a learner
                // never needs -- most sites hide Participants anyway -- and on this format they push the
                // course cards a long way down the page for no gain. Teachers keep them.
                // ACF-FIX-2.1.86: defaults to 2, hidden for everyone outside edit mode. The bar
                // duplicates what the hero and the course index already provide, and it returns
                // automatically the moment editing is switched on, so a teacher never loses it
                // while working.
                'default' => $d('hidesecondarynav', 2),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.102: hide section 0 ("General") from the course index.
            'hidegeneral' => [
                'default' => $d('hidegeneral', 0),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.130: where the estimated-time pills appear. 1 hides.
            'hidetimeindex' => [
                'default' => $d('hidetimeindex', 0),
                'type' => PARAM_INT,
            ],
            'hidetimesectioncards' => [
                'default' => $d('hidetimesectioncards', 0),
                'type' => PARAM_INT,
            ],
            'hidetimeactivitycards' => [
                'default' => $d('hidetimeactivitycards', 0),
                'type' => PARAM_INT,
            ],
            'hidetimetotal' => [
                'default' => $d('hidetimetotal', 0),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.144: the banner follows the page as it scrolls. 1 = sticky, 0 = not.
            'herosticky' => [
                'default' => $d('herosticky', 1),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.126: the section heading band and the activity icon colour.
            'indexheadingcolour' => [
                'default' => $d('indexheadingcolour', ''),
                'type' => PARAM_TEXT,
            ],
            'indexiconcolour' => [
                'default' => $d('indexiconcolour', ''),
                'type' => PARAM_TEXT,
            ],
            // ACF-FIX-2.1.125: the course index surface. Empty follows the card colour.
            'indexcolour' => [
                'default' => $d('indexcolour', ''),
                'type' => PARAM_TEXT,
            ],
            'indexopacity' => [
                'default' => $d('indexopacity', -1),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.124: the card surface colour and how strongly it is applied.
            'cardcolour' => [
                'default' => $d('cardcolour', ''),
                'type' => PARAM_TEXT,
            ],
            'cardopacity' => [
                'default' => $d('cardopacity', -1),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.85: the course index header band colour, '#rrggbb' or '' to follow the
            // site setting. Validated where it is used, as accentcolour is.
            'playerheadercolour' => [
                'default' => $d('playerheadercolour', ''),
                'type' => PARAM_TEXT,
            ],
            // ACF-FIX-2.1.80: hide the theme's logo band. -1 follows the site default.
            'immersive' => [
                'default' => $d('immersive', -1),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.55: the course index drawer's starting state. -1 follows the site default.
            'indexstate' => [
                'default' => $d('indexstate', -1),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.52: the player sidebar. -1 follows the site default.
            'playerindex' => [
                'default' => $d('playerindex', -1),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.50: the site footer. Same three states as the two settings above it.
            // On a course page it is the last thing a learner needs and the first thing that gets
            // in the way of the content.
            'hidefooter' => [
                'default' => $d('hidefooter', 0),
                'type' => PARAM_INT,
            ],
            // ACF-FIX-2.1.36: the breadcrumb trail Moodle renders above the content. Same three
            // states as hidesecondarynav, and the same reasoning: on this format the activity
            // hero already names the course, the section and the activity, so the breadcrumb
            // repeats all three immediately below it. NOT an access control -- it hides a trail,
            // it does not restrict anything, and every destination stays reachable.
            'hidebreadcrumb' => [
                'default' => $d('hidebreadcrumb', 0),
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
                'coursenavplace' => [
                    'label' => get_string('coursenavplace', 'format_aicourse'),
                    'help' => 'coursenavplace',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => get_string('coursenavplace_default', 'format_aicourse'),
                            1 => get_string('coursenavplace_header', 'format_aicourse'),
                        ],
                    ],
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
                'hidegeneral' => [
                    'label' => get_string('hidegeneral', 'format_aicourse'),
                    'help' => 'hidegeneral',
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
                'hidetimeindex' => [
                    'label' => get_string('hidetimeindex', 'format_aicourse'),
                    'help' => 'hidetimeindex',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [[
                        0 => get_string('hidesecondarynav_show', 'format_aicourse'),
                        1 => get_string('hidetime_hide', 'format_aicourse'),
                    ]],
                ],
                'hidetimesectioncards' => [
                    'label' => get_string('hidetimesectioncards', 'format_aicourse'),
                    'help' => 'hidetimesectioncards',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [[
                        0 => get_string('hidesecondarynav_show', 'format_aicourse'),
                        1 => get_string('hidetime_hide', 'format_aicourse'),
                    ]],
                ],
                'hidetimeactivitycards' => [
                    'label' => get_string('hidetimeactivitycards', 'format_aicourse'),
                    'help' => 'hidetimeactivitycards',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [[
                        0 => get_string('hidesecondarynav_show', 'format_aicourse'),
                        1 => get_string('hidetime_hide', 'format_aicourse'),
                    ]],
                ],
                'hidetimetotal' => [
                    'label' => get_string('hidetimetotal', 'format_aicourse'),
                    'help' => 'hidetimetotal',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [[
                        0 => get_string('hidesecondarynav_show', 'format_aicourse'),
                        1 => get_string('hidetime_hide', 'format_aicourse'),
                    ]],
                ],
                'herosticky' => [
                    'label' => get_string('herosticky', 'format_aicourse'),
                    'help' => 'herosticky',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [[
                        0 => get_string('herosticky_no', 'format_aicourse'),
                        1 => get_string('herosticky_yes', 'format_aicourse'),
                    ]],
                ],
                'indexheadingcolour' => [
                    'label' => get_string('indexheadingcolour', 'format_aicourse'),
                    'help' => 'indexheadingcolour',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ],
                'indexiconcolour' => [
                    'label' => get_string('indexiconcolour', 'format_aicourse'),
                    'help' => 'indexiconcolour',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ],
                'indexcolour' => [
                    'label' => get_string('indexcolour', 'format_aicourse'),
                    'help' => 'indexcolour',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ],
                'indexopacity' => [
                    'label' => get_string('indexopacity', 'format_aicourse'),
                    'help' => 'indexopacity',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ],
                'cardcolour' => [
                    'label' => get_string('cardcolour', 'format_aicourse'),
                    'help' => 'cardcolour',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ],
                'cardopacity' => [
                    'label' => get_string('cardopacity', 'format_aicourse'),
                    'help' => 'cardopacity',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ],
                'playerheadercolour' => [
                    'label' => get_string('playerheadercolour', 'format_aicourse'),
                    'help' => 'playerheadercolour',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'text',
                ],
                'immersive' => [
                    'label' => get_string('immersive', 'format_aicourse'),
                    'help' => 'immersive',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            -1 => get_string('playerindex_site', 'format_aicourse'),
                            0 => get_string('hidesecondarynav_show', 'format_aicourse'),
                            1 => get_string('hidesecondarynav_students', 'format_aicourse'),
                            2 => get_string('hidesecondarynav_all', 'format_aicourse'),
                        ],
                    ],
                ],
                'indexstate' => [
                    'label' => get_string('indexstate', 'format_aicourse'),
                    'help' => 'indexstate',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            -1 => get_string('playerindex_site', 'format_aicourse'),
                            0 => get_string('indexstate_remember', 'format_aicourse'),
                            1 => get_string('indexstate_collapsed', 'format_aicourse'),
                            2 => get_string('indexstate_open', 'format_aicourse'),
                        ],
                    ],
                ],
                'playerindex' => [
                    'label' => get_string('playerindex', 'format_aicourse'),
                    'help' => 'playerindex',
                    'help_component' => 'format_aicourse',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            -1 => get_string('playerindex_site', 'format_aicourse'),
                            0 => get_string('playerindex_off', 'format_aicourse'),
                            1 => get_string('playerindex_on', 'format_aicourse'),
                        ],
                    ],
                ],
                'hidefooter' => [
                    'label' => get_string('hidefooter', 'format_aicourse'),
                    'help' => 'hidefooter',
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
                'hidebreadcrumb' => [
                    'label' => get_string('hidebreadcrumb', 'format_aicourse'),
                    'help' => 'hidebreadcrumb',
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
    // ACF-FIX-2.1.63: the sidebar logo is a SITE-level setting, stored against the system context,
    // so it is served here before the course-scoped handler below -- which expects a course and
    // would reject it. Login is not required: this is site branding, shown on the login page's
    // sibling pages and no more sensitive than the site logo itself.
    if ($filearea === 'playerlogo') {
        $fs = get_file_storage();
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
        $file = $fs->get_file(
            \context_system::instance()->id,
            'format_aicourse',
            'playerlogo',
            0,
            $filepath,
            $filename
        );
        if (!$file || $file->is_directory()) {
            send_file_not_found();
        }
        // Long cache: the file only changes when an administrator replaces it, and the URL carries
        // a revision so a replacement is picked up immediately.
        send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
        return;
    }

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

/**
 * Declare user preferences this plugin writes from JavaScript.
 *
 * ACF-FIX-2.1.43. core_user_update_user_preferences refuses any preference a plugin has not
 * declared here, so without this the tour would run once per page load forever.
 *
 * @return array Preference definitions.
 */
function format_aicourse_user_preferences(): array {
    return [
        // ACF-FIX-2.1.51: one per course, so the tutor introduces itself once in each course
        // rather than once ever. The wildcard is how core allows a family of preferences.
        // The key is run through preg_match() by core, so it must be a DELIMITED pattern. Written
        // as a bare string it silently matches nothing, the AJAX write is rejected, and the tutor
        // introduces itself on every visit instead of once.
        // ACF-FIX-2.1.55: records that a course's initial drawer state has been applied once.
        '/^format_aicourse_indexstate_\d+$/' => [
            'isregex' => true,
            'type' => PARAM_INT,
            'null' => NULL_NOT_ALLOWED,
            'default' => 0,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        '/^format_aicourse_tutor_seen_\d+$/' => [
            'isregex' => true,
            'type' => PARAM_INT,
            'null' => NULL_NOT_ALLOWED,
            'default' => 0,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        \format_aicourse\local\tour::SEEN_PREF => [
            'type' => PARAM_INT,
            'null' => NULL_NOT_ALLOWED,
            'default' => 0,
            // A user may only ever set their own: this records whether THEY have seen the tour.
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}
