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
 * External function definitions for the AI Course Format.
 *
 * These replace the plugin's former hand-rolled ajax.php endpoint. That file survives for one
 * release as a thin deprecated shim that forwards to the functions declared here.
 *
 * The 'capabilities' entry of each definition documents the capability the function itself
 * enforces with require_capability(); it is advisory metadata used by the web service
 * administration screens and is NOT a substitute for the check inside execute().
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'format_aicourse_get_progress' => [
        'classname' => 'format_aicourse\external\get_progress',
        'methodname' => 'execute',
        'description' => 'Get the calling user\'s completion progress for a course.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'format/aicourse:view',
        'readonlysession' => true,
    ],

    'format_aicourse_save_icon' => [
        'classname' => 'format_aicourse\external\save_icon',
        'methodname' => 'execute',
        'description' => 'Set or clear the decorative icon shown on a section card.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:update',
    ],

    'format_aicourse_add_section' => [
        'classname' => 'format_aicourse\external\add_section',
        'methodname' => 'execute',
        'description' => 'Append a new section to the end of the course.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:update',
    ],

    'format_aicourse_duplicate_section' => [
        'classname' => 'format_aicourse\external\duplicate_section',
        'methodname' => 'execute',
        'description' => 'Duplicate a section, including every activity inside it.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:update',
    ],

    'format_aicourse_delete_section' => [
        'classname' => 'format_aicourse\external\delete_section',
        'methodname' => 'execute',
        'description' => 'Delete a section and the activities inside it.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:update',
    ],

    'format_aicourse_ai_chat' => [
        'classname' => 'format_aicourse\external\ai_chat',
        'methodname' => 'execute',
        'description' => 'Ask the AI Tutor a question about the course.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'format/aicourse:useaitutor',
        // The remote call takes up to 60 seconds; do not hold the session lock for its duration.
        'readonlysession' => true,
    ],

    'format_aicourse_rate_chat' => [
        'classname' => 'format_aicourse\external\rate_chat',
        'methodname' => 'execute',
        'description' => 'Rate one of the calling user\'s own AI Tutor answers.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'format/aicourse:useaitutor',
    ],

    'format_aicourse_correct_chat' => [
        'classname' => 'format_aicourse\external\correct_chat',
        'methodname' => 'execute',
        'description' => 'Write a teacher correction onto an AI Tutor answer.',
        'type' => 'write',
        'ajax' => true,
        // NOT moodle/course:viewparticipants, which students hold by default.
        'capabilities' => 'format/aicourse:viewreport',
    ],

    'format_aicourse_get_activity_context' => [
        'classname' => 'format_aicourse\external\get_activity_context',
        'methodname' => 'execute',
        'description' => 'Get the public introduction and question prompts of an activity, with '
            . 'every answer key removed.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'format/aicourse:useaitutor',
        'readonlysession' => true,
    ],

    'format_aicourse_generate_banner_image' => [
        'classname' => 'format_aicourse\external\generate_banner_image',
        'methodname' => 'execute',
        'description' => 'Generate an AI course banner image and store it against the course.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:update',
        // The remote call takes up to 90 seconds; do not hold the session lock for its duration.
        'readonlysession' => true,
    ],

    'format_aicourse_delete_banner_image' => [
        'classname' => 'format_aicourse\external\delete_banner_image',
        'methodname' => 'execute',
        'description' => 'Remove the AI generated banner image from a course.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:update',
    ],

    'format_aicourse_db_diagnostic' => [
        'classname' => 'format_aicourse\external\db_diagnostic',
        'methodname' => 'execute',
        'description' => 'Report whether the plugin\'s own database tables are present and complete.',
        'type' => 'read',
        // Support tool only: no JavaScript in the plugin calls it.
        'ajax' => false,
        'capabilities' => 'moodle/course:update',
    ],

    'format_aicourse_db_repair' => [
        'classname' => 'format_aicourse\external\db_repair',
        'methodname' => 'execute',
        'description' => 'Recreate any missing table or column of the plugin\'s own schema.',
        'type' => 'write',
        // Site administrator support tool only: deliberately never exposed to a browser.
        'ajax' => false,
        'capabilities' => 'moodle/site:config',
    ],
];
