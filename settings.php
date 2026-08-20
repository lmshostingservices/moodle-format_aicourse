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
        get_string('accentcolour_desc', 'format_aicourse'),
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
        1,
        [
            0 => get_string('hidesecondarynav_show', 'format_aicourse'),
            1 => get_string('hidesecondarynav_students', 'format_aicourse'),
            2 => get_string('hidesecondarynav_all', 'format_aicourse'),
        ]
    ));

    // ACF-FIX-2.1.31: the override, deliberately placed next to the default so the difference
    // is visible. "Follow each course" is the safe value and stays the shipped behaviour.
    $settings->add(new admin_setting_configselect(
        'format_aicourse/forcehidesecondarynav',
        get_string('forcehidesecondarynav', 'format_aicourse'),
        get_string('forcehidesecondarynav_desc', 'format_aicourse'),
        -1,
        [
            -1 => get_string('forcehidesecondarynav_follow', 'format_aicourse'),
            0 => get_string('hidesecondarynav_show', 'format_aicourse'),
            1 => get_string('hidesecondarynav_students', 'format_aicourse'),
            2 => get_string('hidesecondarynav_all', 'format_aicourse'),
        ]
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
}
