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
}
