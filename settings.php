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
 * format_aicourse file.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Link to the site-wide admin Q&A report.
    $reporturl = new moodle_url('/course/format/aicourse/admin_report.php');
    $settings->add(new admin_setting_description(
        'format_aicourse/adminreportlink',
        get_string('admin_report_link', 'format_aicourse'),
        html_writer::link($reporturl, get_string('admin_report_view', 'format_aicourse'),
            ['class' => 'btn btn-primary', 'target' => '_self'])
    ));

    $settings->add(new admin_setting_heading(
        'format_aicourse/aiassistant',
        get_string('aiassistant', 'format_aicourse'),
        get_string('aiassistant_settings_desc', 'format_aicourse')
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

    $settings->add(new admin_setting_heading(
        'format_aicourse/display',
        get_string('displaysettings', 'format_aicourse'),
        ''
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
