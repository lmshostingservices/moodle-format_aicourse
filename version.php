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
 * Version details for the AI Course Format.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'format_aicourse';
$plugin->version      = 2026082154;
// Moodle 4.4 (2024042200) is the true minimum: db/hooks.php registers a callback for
// \core\hook\output\before_standard_footer_html_generation, which was only introduced in
// Moodle 4.4 (see lib/upgrade.txt, "=== 4.4 ==="). The plugin's hero banner and AI Tutor
// injection depend entirely on that hook. \core_external\external_api (used in lib.php)
// arrived earlier, in Moodle 4.2, so 4.4 covers it as well.
$plugin->requires     = 2024042200;
$plugin->supported    = [404, 500];
$plugin->maturity     = MATURITY_STABLE;
$plugin->release      = '2.1.190';
$plugin->dependencies = [];
