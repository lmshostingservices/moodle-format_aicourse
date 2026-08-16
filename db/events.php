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
 * Event observer definitions for the AI Course Format.
 *
 * These keep the `coursecontent` cache (the snapshot of course content the AI Tutor answers
 * from) in step with the real course, and remove per course personal data when a course is
 * deleted.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\format_aicourse\observer::course_module_created',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\format_aicourse\observer::course_module_updated',
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\format_aicourse\observer::course_module_deleted',
    ],
    [
        'eventname' => '\core\event\course_section_updated',
        'callback' => '\format_aicourse\observer::course_section_updated',
    ],
    [
        'eventname' => '\core\event\course_updated',
        'callback' => '\format_aicourse\observer::course_updated',
    ],
    [
        'eventname' => '\core\event\course_deleted',
        'callback' => '\format_aicourse\observer::course_deleted',
    ],
];
