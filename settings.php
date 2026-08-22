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
 * Site administration settings for the AI Course Format.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Link to the site-wide admin Q&A report.
    $reporturl = new moodle_url('/course/format/aicourse/admin_report.php');
    $settings->add(new admin_setting_description(
        'format_aicourse/adminreportlink',
        get_string('admin_report_link', 'format_aicourse'),
        html_writer::link(
            $reporturl,
            get_string('admin_report_view', 'format_aicourse'),
            ['class' => 'btn btn-primary', 'target' => '_self']
        )
    ));

    $settings->add(new admin_setting_heading(
        'format_aicourse/aiassistant',
        get_string('aiassistant', 'format_aicourse'),
        get_string('aiassistant_settings_desc', 'format_aicourse')
    ));

    // Mandatory disclosure of the third party service the AI Tutor talks to, shown in the
    // settings page itself so an administrator cannot enable the tutor without seeing it.
    $settings->add(new admin_setting_description(
        'format_aicourse/externalservicenotice',
        get_string('externalservice', 'format_aicourse'),
        get_string('externalservice_desc', 'format_aicourse')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'format_aicourse/enabletutor',
        get_string('enabletutor', 'format_aicourse'),
        get_string('enabletutor_desc', 'format_aicourse'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/siteid',
        get_string('siteid', 'format_aicourse'),
        get_string('siteid_desc', 'format_aicourse'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'format_aicourse/apikey',
        get_string('apikey', 'format_aicourse'),
        get_string('apikey_desc', 'format_aicourse'),
        ''
    ));

    // ACF-FIX-2.1: assessment answer keys are opt in, and default to OFF.
    //
    // The AI Tutor answers from an index of the course that is transmitted to an external
    // service. That index used to include, unconditionally, the correct-answer marker for every
    // multiple choice question, the per-option feedback that usually gives the answer away, and
    // the essay "information for graders" marking guide -- teacher-only text Moodle never shows
    // a student. Sending an assessment's answer key to a third party cannot be a default, so it
    // requires a deliberate decision by a site administrator.
    //
    // ACF-FIX-2.1.1: the checkbox became a three-value select so that a single course can opt in
    // without the whole site doing so. The two values the checkbox could store keep their exact
    // meanings -- 0 is still "never" and is still the default, 1 is still "always" -- so a site
    // that had ticked the box behaves identically after the upgrade and nothing needs migrating.
    // This setting is the CEILING: the new per-course option is only consulted at value 2.
    $settings->add(new admin_setting_configselect(
        'format_aicourse/shareassessmentanswers',
        get_string('shareassessmentanswers', 'format_aicourse'),
        get_string('shareassessmentanswers_desc', 'format_aicourse'),
        \format_aicourse\local\contentindex::SHARE_NEVER,
        [
            \format_aicourse\local\contentindex::SHARE_NEVER =>
                get_string('shareanswers_never', 'format_aicourse'),
            \format_aicourse\local\contentindex::SHARE_ALWAYS =>
                get_string('shareanswers_always', 'format_aicourse'),
            \format_aicourse\local\contentindex::SHARE_PERCOURSE =>
                get_string('shareanswers_percourse', 'format_aicourse'),
        ]
    ));

    $settings->add(new admin_setting_heading(
        'format_aicourse/display',
        get_string('displaysettings', 'format_aicourse'),
        get_string('displaysettings_desc', 'format_aicourse')
    ));

    $settings->add(new admin_setting_configselect(
        'format_aicourse/scrimstrength',
        get_string('scrimstrength', 'format_aicourse'),
        get_string('scrimstrength_desc', 'format_aicourse'),
        // ACF-FIX-2.1.25: superseded by "Hero image overlay opacity", which expresses the same
        // thing as a number and can be set per course. Kept because existing sites have a value
        // stored here and it is still the fallback for any course whose own overlay is -1.
        'medium',
        [
            'light' => get_string('scrimstrength_light', 'format_aicourse'),
            'medium' => get_string('scrimstrength_medium', 'format_aicourse'),
            'strong' => get_string('scrimstrength_strong', 'format_aicourse'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'format_aicourse/colourmode',
        get_string('colourmode', 'format_aicourse'),
        get_string('colourmode_desc', 'format_aicourse'),
        'theme',
        [
            'theme' => get_string('colourmode_theme', 'format_aicourse'),
            'light' => get_string('colourmode_light', 'format_aicourse'),
            'dark' => get_string('colourmode_dark', 'format_aicourse'),
            'device' => get_string('colourmode_device', 'format_aicourse'),
        ]
    ));

    // ACF-FIX-2.1.23: site-wide accent colour. Everything the format tints —
    // the hero background, card borders, icon wells, the focus ring — derives
    // from --acf-brand, which normally inherits the theme's primary. This
    // overrides it for aicourse pages only. Empty = keep following the theme.
    // admin_setting_configcolourpicker gives the real picker-and-swatches UI;
    // it stores '' or a #rrggbb string and validates that itself.
    $settings->add(new admin_setting_configcolourpicker(
        'format_aicourse/defaultaccentcolour',
        get_string('accentcolour', 'format_aicourse'),
        get_string('defaultaccentcolour_desc', 'format_aicourse'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/defaultherobannerfade',
        get_string('herobannerfade', 'format_aicourse'),
        get_string('herobannerfade_desc', 'format_aicourse'),
        3,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'format_aicourse/defaultshowherobanner',
        get_string('showherobanner', 'format_aicourse'),
        get_string('showherobanner_desc', 'format_aicourse'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'format_aicourse/defaultdisplayascards',
        get_string('displayascards', 'format_aicourse'),
        get_string('displayascards_desc', 'format_aicourse'),
        1
    ));

    // ACF-FIX-2.1.25 (settings audit). Every course format option now has a site-level default
    // here, so what an administrator sets on this page is genuinely what a new course starts
    // with. Twelve of these did not exist before: the course form fell back to a value hard
    // coded in lib.php and this page had no say at all.
    //
    // Naming contract, relied on by format_aicourse::site_default(): the setting is always
    // 'default' . <the course option name>. Do not rename one side without the other.
    //
    // Each reuses the course option's own label string, so the admin page and the course
    // settings form can never drift apart in wording.

    $settings->add(new admin_setting_configselect(
        'format_aicourse/defaulthidesecondarynav',
        get_string('hidesecondarynav', 'format_aicourse'),
        get_string('sitedefault_desc', 'format_aicourse'),
        2,
        [
            0 => get_string('hidesecondarynav_show', 'format_aicourse'),
            1 => get_string('hidesecondarynav_students', 'format_aicourse'),
            2 => get_string('hidesecondarynav_all', 'format_aicourse'),
        ]
    ));

    // ACF-FIX-2.1.31: the override, deliberately placed next to the default so the difference
    // ACF-FIX-2.1.91: ships as "hide from everyone" rather than "follow each course".
    //
    // The default above only seeds courses that have never saved the option, so on any site with
    // existing courses it changes nothing -- which is exactly the confusion it caused. The
    // override is the control that reaches courses already created, so it is the one that has to
    // carry the intent.
    //
    // This does remove per-course control out of the box, which is a real cost: an administrator
    // who wants some courses to keep the tabs must set this back to "Follow each course". It is a
    // deliberate trade in favour of the format's own navigation, and the tabs are still never
    // hidden while edit mode is on.
    $settings->add(new admin_setting_configselect(
        'format_aicourse/forcehidesecondarynav',
        get_string('forcehidesecondarynav', 'format_aicourse'),
        get_string('forcehidesecondarynav_desc', 'format_aicourse'),
        2,
        [
            -1 => get_string('forcehidesecondarynav_follow', 'format_aicourse'),
            0 => get_string('hidesecondarynav_show', 'format_aicourse'),
            1 => get_string('hidesecondarynav_students', 'format_aicourse'),
            2 => get_string('hidesecondarynav_all', 'format_aicourse'),
        ]
    ));

    // ACF-FIX-2.1.63: a logo for the player sidebar, separate from the site logo.
    $settings->add(new admin_setting_configstoredfile(
        'format_aicourse/playerlogo',
        get_string('playerlogo', 'format_aicourse'),
        get_string('playerlogo_desc', 'format_aicourse'),
        'playerlogo',
        0,
        [
            'maxfiles' => 1,
            'accepted_types' => ['web_image'],
        ]
    ));

    global $PAGE;

    // ACF-FIX-2.1.159: the settings page UI.
    //
    // Sixty-five settings in one column is a scroll, not a page. The module puts a hub of feature
    // cards above them -- each with a wireframe of the part of the course page it governs, and
    // direct links into the settings people actually arrive looking for -- then groups the rest by
    // category and colour-codes them.
    //
    // Only the four labels it cannot derive are passed. The "recently added" filter is gone with
    // the rest of the old UI: it depended on settingsmeta, which does not parse this file's three
    // different ways of declaring a setting and was never trusted enough to be used without a
    // hand-kept fallback list beside it.
    $PAGE->requires->js_call_amd('format_aicourse/settingsui', 'init', [[
        'all' => get_string('settingsui_all', 'format_aicourse'),
        'search' => get_string('settingsui_search', 'format_aicourse'),
        'nomatches' => get_string('settingsui_nomatches', 'format_aicourse'),
        'settings' => get_string('settingsui_settings', 'format_aicourse'),
        'setting' => get_string('settingsui_setting', 'format_aicourse'),
    ]]);

    // ACF-FIX-2.1.120: say plainly which themes this is built against.
    //
    // The format overrides parts of core's course index, navigation and header. Boost and
    // theme_academi are the two it is developed and measured against; another theme may position
    // those differently and produce a layout fault that looks like a plugin bug. Saying so, and
    // giving people somewhere to write, is more useful than letting them guess.
    $settings->add(new admin_setting_heading(
        'format_aicourse/themesupport',
        get_string('themesupport', 'format_aicourse'),
        get_string('themesupport_desc', 'format_aicourse')
    ));

    // ACF-FIX-2.1.102: hide the General section.
    $settings->add(new admin_setting_configselect(
        'format_aicourse/defaulthidegeneral',
        get_string('hidegeneral', 'format_aicourse'),
        get_string('hidegeneral_desc', 'format_aicourse'),
        0,
        [
            0 => get_string('hidesecondarynav_show', 'format_aicourse'),
            1 => get_string('hidesecondarynav_students', 'format_aicourse'),
            2 => get_string('hidesecondarynav_all', 'format_aicourse'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'format_aicourse/forcehidegeneral',
        get_string('forcehidegeneral', 'format_aicourse'),
        get_string('forcehidegeneral_desc', 'format_aicourse'),
        -1,
        [
            -1 => get_string('forcehidebreadcrumb_leave', 'format_aicourse'),
            0 => get_string('hidesecondarynav_show', 'format_aicourse'),
            1 => get_string('hidesecondarynav_students', 'format_aicourse'),
            2 => get_string('hidesecondarynav_all', 'format_aicourse'),
        ]
    ));

    // ACF-FIX-2.1.130: the estimated-time pills, four places.
    $acftimes = ['hidetimeindex', 'hidetimesectioncards', 'hidetimeactivitycards', 'hidetimetotal'];
    foreach ($acftimes as $acftime) {
        $settings->add(new admin_setting_configselect(
            'format_aicourse/default' . $acftime,
            get_string($acftime, 'format_aicourse'),
            get_string('default' . $acftime . '_desc', 'format_aicourse'),
            0,
            [
                0 => get_string('hidesecondarynav_show', 'format_aicourse'),
                1 => get_string('hidetime_hide', 'format_aicourse'),
            ]
        ));
        $settings->add(new admin_setting_configselect(
            'format_aicourse/force' . $acftime,
            get_string('force' . $acftime, 'format_aicourse'),
            get_string('force' . $acftime . '_desc', 'format_aicourse'),
            -1,
            [
                -1 => get_string('forcehidebreadcrumb_leave', 'format_aicourse'),
                0 => get_string('hidesecondarynav_show', 'format_aicourse'),
                1 => get_string('hidetime_hide', 'format_aicourse'),
            ]
        ));
    }

    // ACF-FIX-2.1.144: the sticky banner.
    $settings->add(new admin_setting_configselect(
        'format_aicourse/defaultherosticky',
        get_string('herosticky', 'format_aicourse'),
        get_string('defaultherosticky_desc', 'format_aicourse'),
        1,
        [
            0 => get_string('herosticky_no', 'format_aicourse'),
            1 => get_string('herosticky_yes', 'format_aicourse'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'format_aicourse/forceherosticky',
        get_string('forceherosticky', 'format_aicourse'),
        get_string('forceherosticky_desc', 'format_aicourse'),
        -1,
        [
            -1 => get_string('forcehidebreadcrumb_leave', 'format_aicourse'),
            0 => get_string('herosticky_no', 'format_aicourse'),
            1 => get_string('herosticky_yes', 'format_aicourse'),
        ]
    ));

    // ACF-FIX-2.1.126: the section heading band and the activity icon colour.
    foreach (['indexheadingcolour', 'indexiconcolour'] as $acfcolour) {
        $settings->add(new admin_setting_configtext(
            'format_aicourse/default' . $acfcolour,
            get_string($acfcolour, 'format_aicourse'),
            get_string('default' . $acfcolour . '_desc', 'format_aicourse'),
            '',
            PARAM_TEXT
        ));
        $settings->add(new admin_setting_configtext(
            'format_aicourse/force' . $acfcolour,
            get_string('force' . $acfcolour, 'format_aicourse'),
            get_string('force' . $acfcolour . '_desc', 'format_aicourse'),
            '',
            PARAM_TEXT
        ));
    }

    // ACF-FIX-2.1.125: the course index surface.
    $settings->add(new admin_setting_configtext(
        'format_aicourse/defaultindexcolour',
        get_string('indexcolour', 'format_aicourse'),
        get_string('defaultindexcolour_desc', 'format_aicourse'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/defaultindexopacity',
        get_string('indexopacity', 'format_aicourse'),
        get_string('defaultindexopacity_desc', 'format_aicourse'),
        100,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/forceindexcolour',
        get_string('forceindexcolour', 'format_aicourse'),
        get_string('forceindexcolour_desc', 'format_aicourse'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/forceindexopacity',
        get_string('forceindexopacity', 'format_aicourse'),
        get_string('forceindexopacity_desc', 'format_aicourse'),
        -1,
        PARAM_INT
    ));

    // ACF-FIX-2.1.124: the card surface.
    $settings->add(new admin_setting_configtext(
        'format_aicourse/defaultcardcolour',
        get_string('cardcolour', 'format_aicourse'),
        get_string('defaultcardcolour_desc', 'format_aicourse'),
        '#fafbfc',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/defaultcardopacity',
        get_string('cardopacity', 'format_aicourse'),
        get_string('defaultcardopacity_desc', 'format_aicourse'),
        100,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/forcecardcolour',
        get_string('forcecardcolour', 'format_aicourse'),
        get_string('forcecardcolour_desc', 'format_aicourse'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/forcecardopacity',
        get_string('forcecardopacity', 'format_aicourse'),
        get_string('forcecardopacity_desc', 'format_aicourse'),
        -1,
        PARAM_INT
    ));

    // ACF-FIX-2.1.85: the course index header band colour.
    $settings->add(new admin_setting_configtext(
        'format_aicourse/defaultplayerheadercolour',
        get_string('playerheadercolour', 'format_aicourse'),
        get_string('defaultplayerheadercolour_desc', 'format_aicourse'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/forceplayerheadercolour',
        get_string('forceplayerheadercolour', 'format_aicourse'),
        get_string('forceplayerheadercolour_desc', 'format_aicourse'),
        '',
        PARAM_TEXT
    ));

    // ACF-FIX-2.1.158: this block used to re-register 'format_aicourse/defaultaccentcolour' as a
    // plain text box. admin_settingpage::add() keys by name, so the LATER registration silently
    // replaced the colour picker declared above at ACF-FIX-2.1.23 -- the picker has been dead code
    // ever since, and administrators have been typing hex codes into a text field with no swatches
    // and no validation. One name, one control: the picker above is the one that is kept.
    //
    // The description string this block used, 'defaultaccentcolour_desc', is left in the language
    // file: it is the more specific of the two and is now used by the picker.

    $settings->add(new admin_setting_configtext(
        'format_aicourse/forceaccentcolour',
        get_string('forceaccentcolour', 'format_aicourse'),
        get_string('forceaccentcolour_desc', 'format_aicourse'),
        '',
        PARAM_TEXT
    ));

    // ACF-FIX-2.1.80: hide the theme's logo band.
    $settings->add(new admin_setting_configselect(
        'format_aicourse/defaultimmersive',
        get_string('immersive', 'format_aicourse'),
        get_string('immersive_desc', 'format_aicourse'),
        0,
        [
            0 => get_string('hidesecondarynav_show', 'format_aicourse'),
            1 => get_string('hidesecondarynav_students', 'format_aicourse'),
            2 => get_string('hidesecondarynav_all', 'format_aicourse'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'format_aicourse/forceimmersive',
        get_string('forceimmersive', 'format_aicourse'),
        get_string('forceimmersive_desc', 'format_aicourse'),
        -1,
        [
            -1 => get_string('forcehidebreadcrumb_leave', 'format_aicourse'),
            0 => get_string('hidesecondarynav_show', 'format_aicourse'),
            1 => get_string('hidesecondarynav_students', 'format_aicourse'),
            2 => get_string('hidesecondarynav_all', 'format_aicourse'),
        ]
    ));

    // ACF-FIX-2.1.55: the course index drawer's starting state.
    $settings->add(new admin_setting_configselect(
        'format_aicourse/defaultindexstate',
        get_string('indexstate', 'format_aicourse'),
        get_string('indexstate_desc', 'format_aicourse'),
        0,
        [
            0 => get_string('indexstate_remember', 'format_aicourse'),
            1 => get_string('indexstate_collapsed', 'format_aicourse'),
            2 => get_string('indexstate_open', 'format_aicourse'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'format_aicourse/forceindexstate',
        get_string('forceindexstate', 'format_aicourse'),
        get_string('forceindexstate_desc', 'format_aicourse'),
        -1,
        [
            -1 => get_string('forcehidebreadcrumb_leave', 'format_aicourse'),
            0 => get_string('indexstate_remember', 'format_aicourse'),
            1 => get_string('indexstate_collapsed', 'format_aicourse'),
            2 => get_string('indexstate_open', 'format_aicourse'),
        ]
    ));

    // ACF-FIX-2.1.52: the player sidebar.
    $settings->add(new admin_setting_configselect(
        'format_aicourse/defaultplayerindex',
        get_string('playerindex', 'format_aicourse'),
        get_string('playerindex_desc', 'format_aicourse'),
        0,
        [
            0 => get_string('playerindex_off', 'format_aicourse'),
            1 => get_string('playerindex_on', 'format_aicourse'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'format_aicourse/forceplayerindex',
        get_string('forceplayerindex', 'format_aicourse'),
        get_string('forceplayerindex_desc', 'format_aicourse'),
        -1,
        [
            -1 => get_string('forcehidebreadcrumb_leave', 'format_aicourse'),
            0 => get_string('playerindex_off', 'format_aicourse'),
            1 => get_string('playerindex_on', 'format_aicourse'),
        ]
    ));

    // ACF-FIX-2.1.50: the site footer, default and override, same shape as the pair below.
    $settings->add(new admin_setting_configselect(
        'format_aicourse/defaulthidefooter',
        get_string('hidefooter', 'format_aicourse'),
        get_string('sitedefault_desc', 'format_aicourse'),
        0,
        [
            0 => get_string('hidesecondarynav_show', 'format_aicourse'),
            1 => get_string('hidesecondarynav_students', 'format_aicourse'),
            2 => get_string('hidesecondarynav_all', 'format_aicourse'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'format_aicourse/forcehidefooter',
        get_string('forcehidefooter', 'format_aicourse'),
        get_string('forcehidefooter_desc', 'format_aicourse'),
        -1,
        [
            -1 => get_string('forcehidebreadcrumb_leave', 'format_aicourse'),
            0 => get_string('hidesecondarynav_show', 'format_aicourse'),
            1 => get_string('hidesecondarynav_students', 'format_aicourse'),
            2 => get_string('hidesecondarynav_all', 'format_aicourse'),
        ]
    ));

    // ACF-FIX-2.1.36: the site default for the breadcrumb, and its override, placed together for
    // the same reason as the pair above: the default only seeds new courses, the override applies
    // everywhere at once.
    $settings->add(new admin_setting_configselect(
        'format_aicourse/defaulthidebreadcrumb',
        get_string('hidebreadcrumb', 'format_aicourse'),
        get_string('sitedefault_desc', 'format_aicourse'),
        0,
        [
            0 => get_string('hidesecondarynav_show', 'format_aicourse'),
            1 => get_string('hidesecondarynav_students', 'format_aicourse'),
            2 => get_string('hidesecondarynav_all', 'format_aicourse'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'format_aicourse/forcehidebreadcrumb',
        get_string('forcehidebreadcrumb', 'format_aicourse'),
        get_string('forcehidebreadcrumb_desc', 'format_aicourse'),
        -1,
        [
            -1 => get_string('forcehidebreadcrumb_leave', 'format_aicourse'),
            0 => get_string('hidesecondarynav_show', 'format_aicourse'),
            1 => get_string('hidesecondarynav_students', 'format_aicourse'),
            2 => get_string('hidesecondarynav_all', 'format_aicourse'),
        ]
    ));

    // ACF-FIX-2.1.46: estimated activity durations.
    $settings->add(new admin_setting_heading(
        'format_aicourse/timingheading',
        get_string('timingheading', 'format_aicourse'),
        get_string('timingheading_desc', 'format_aicourse')
    ));

    $settings->add(new admin_setting_configtextarea(
        'format_aicourse/defaultminutes',
        get_string('defaultminutes', 'format_aicourse'),
        get_string('defaultminutes_desc', 'format_aicourse'),
        \format_aicourse\local\progress::DEFAULT_MINUTES_MAP,
        // ACF-FIX-2.1.115: plain text rather than an untyped value. The field holds
        // "modname=minutes" lines and nothing else; the parser already ignores anything not
        // matching that shape, so there is no case where markup here would be wanted.
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/minutesperquestion',
        get_string('minutesperquestion', 'format_aicourse'),
        get_string('minutesperquestion_desc', 'format_aicourse'),
        1,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/minutesfallback',
        get_string('minutesfallback', 'format_aicourse'),
        get_string('minutesfallback_desc', 'format_aicourse'),
        5,
        PARAM_INT
    ));

    // ACF-FIX-2.1.43: first-run tour.
    $settings->add(new admin_setting_configcheckbox(
        'format_aicourse/tourvoiceover',
        get_string('tourvoiceover', 'format_aicourse'),
        get_string('tourvoiceover_desc', 'format_aicourse'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/tourvoice',
        get_string('tourvoice', 'format_aicourse'),
        get_string('tourvoice_desc', 'format_aicourse'),
        'en-AU',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'format_aicourse/defaultheroattop',
        get_string('heroattop', 'format_aicourse'),
        get_string('sitedefault_desc', 'format_aicourse'),
        1
    ));

    $settings->add(new admin_setting_configselect(
        'format_aicourse/defaultcardlayout',
        get_string('cardlayout', 'format_aicourse'),
        get_string('sitedefault_desc', 'format_aicourse'),
        0,
        [
            0 => get_string('cardlayout_grid', 'format_aicourse'),
            1 => get_string('cardlayout_list', 'format_aicourse'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'format_aicourse/defaultactivitydisplaymode',
        get_string('activitydisplaymode', 'format_aicourse'),
        get_string('sitedefault_desc', 'format_aicourse'),
        1,
        [
            0 => get_string('activitydisplaystandard', 'format_aicourse'),
            1 => get_string('activitydisplaycards', 'format_aicourse'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'format_aicourse/defaultshowactivitiesoncards',
        get_string('showactivitiesoncards', 'format_aicourse'),
        get_string('sitedefault_desc', 'format_aicourse'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'format_aicourse/defaultshownavchevrons',
        get_string('shownavchevrons', 'format_aicourse'),
        get_string('sitedefault_desc', 'format_aicourse'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/defaultcardactivitylimit',
        get_string('cardactivitylimit', 'format_aicourse'),
        get_string('cardactivitylimit_desc', 'format_aicourse'),
        0,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/defaultcardtitlesize',
        get_string('cardtitlesize', 'format_aicourse'),
        get_string('sitedefault_desc', 'format_aicourse'),
        14,
        PARAM_INT
    ));




    // ACF-FIX-2.1.158: the missing site default.
    //
    // lib.php's course_format_options() calls $d('coursenavplace', 0) -- i.e. it reads
    // 'format_aicourse/defaultcoursenavplace' -- but that setting was never registered here, so the
    // helper fell through to its hard-coded fallback on every site and the option's own comment
    // ("every course format option now has a site-level default") was not true. Registered with the
    // same two choices the course settings form offers, so the two cannot describe it differently.
    $settings->add(new admin_setting_configselect(
        'format_aicourse/defaultcoursenavplace',
        get_string('coursenavplace', 'format_aicourse'),
        get_string('sitedefault_desc', 'format_aicourse'),
        0,
        [
            0 => get_string('coursenavplace_default', 'format_aicourse'),
            1 => get_string('coursenavplace_header', 'format_aicourse'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'format_aicourse/defaultshowcourseindex',
        get_string('showcourseindex', 'format_aicourse'),
        get_string('sitedefault_desc', 'format_aicourse'),
        7,
        [
            0 => get_string('courseindex_none', 'format_aicourse'),
            1 => get_string('courseindex_home', 'format_aicourse'),
            2 => get_string('courseindex_section', 'format_aicourse'),
            3 => get_string('courseindex_home_section', 'format_aicourse'),
            4 => get_string('courseindex_activity', 'format_aicourse'),
            5 => get_string('courseindex_home_activity', 'format_aicourse'),
            6 => get_string('courseindex_section_activity', 'format_aicourse'),
            7 => get_string('courseindex_all', 'format_aicourse'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'format_aicourse/defaultheroimageoverlay',
        get_string('heroimageoverlay', 'format_aicourse'),
        get_string('heroimageoverlay_desc', 'format_aicourse'),
        45,
        PARAM_INT
    ));

    // ACF-FIX-2.1.40: the override. The default above only seeds NEW courses -- an existing
    // course has its own stored value and ignores it forever, which is why changing the default
    // appears to do nothing. This applies to every course at once. -1 leaves each course alone.
    $settings->add(new admin_setting_configtext(
        'format_aicourse/forceheroimageoverlay',
        get_string('forceheroimageoverlay', 'format_aicourse'),
        get_string('forceheroimageoverlay_desc', 'format_aicourse'),
        -1,
        PARAM_INT
    ));
}
