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
 * Capability definitions for the AI Course Format.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // See a course that uses the AI Course Format. This is a read-only capability held by
    // every enrolled role; it exists so a site can hide the format's enhanced course view
    // (hero banner, section cards) from a particular role without changing the course format.
    'format/aicourse:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'guest' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Use the AI Tutor. Asking the tutor a question transmits the user's id, first name,
    // the course name, the question text and the course content to the external LMS-Labs
    // service, and stores the question and answer against the user, so this is a personal
    // data risk and must be grantable per role.
    'format/aicourse:useaitutor' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // View the AI Tutor report for a course. The report shows every question every student
    // in the course has asked, together with the AI's answers, so it exposes other people's
    // personal data and must never be granted to students by default.
    'format/aicourse:viewreport' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Write a teacher correction onto another user's AI Tutor response. The correction is
    // free text stored in the database and rendered back into the report, hence RISK_XSS,
    // and it modifies a record that belongs to another user, hence RISK_PERSONAL.
    'format/aicourse:correctresponses' => [
        'riskbitmask' => RISK_XSS | RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
