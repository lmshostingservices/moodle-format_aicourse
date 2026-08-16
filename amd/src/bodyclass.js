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
 * Applies AI Course body classes on pages rendered by core rather than by format.php.
 *
 * Course pages set these server-side in format_aicourse::page_set_course(). Activity,
 * section and report pages are rendered by core, and the only plugin hook available there
 * runs after the <body> tag has been written, so $PAGE->add_body_class() is too late.
 *
 * ACF-FIX-2.1: this replaces two inline <script> blocks. Inline script is blocked by any
 * Content-Security-Policy that forbids it.
 *
 * @module     format_aicourse/bodyclass
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {RegExp} Only this plugin's own body classes may be applied. */
const ALLOWED = /^aicourse-[a-z-]+$/;

/**
 * Add the supplied body classes.
 *
 * @param {string[]} classes Body classes to add.
 * @returns {void}
 */
export const init = (classes) => {
    if (!Array.isArray(classes)) {
        return;
    }
    classes.filter((name) => ALLOWED.test(name))
        .forEach((name) => document.body.classList.add(name));
};
