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
 * AI Tutor report index — every course using this format, with a link to its report.
 *
 * Access:  Site administrators only (moodle/site:config).
 * URL:     /course/format/aicourse/index.php
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use format_aicourse\output\report\indexpage;

require_login();
$syscontext = context_system::instance();
require_capability('moodle/site:config', $syscontext);

$PAGE->set_url(new moodle_url('/course/format/aicourse/index.php'));
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('aireport', 'format_aicourse'));
$PAGE->set_heading(get_string('aireport', 'format_aicourse'));
$PAGE->set_pagelayout('admin');

$index = new indexpage();

echo $OUTPUT->header();
echo $OUTPUT->render($index);
echo $OUTPUT->footer();
