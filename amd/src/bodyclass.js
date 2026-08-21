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
 * @param {string} [accent] Pre-validated CSS custom-property declarations for the course accent.
 * @returns {void}
 */
export const init = (classes, accent) => {
    if (Array.isArray(classes)) {
        classes.filter((name) => ALLOWED.test(name))
            .forEach((name) => document.body.classList.add(name));
    }

    // ACF-FIX-2.1.54: publish the course's accent at the document root.
    //
    // The accent was set as an inline style on the hero element, so its custom properties were
    // scoped to the hero's own subtree. Everything else the format draws -- the section cards,
    // the activity cards, the focus ring, the tour, the chat panel and now the player sidebar --
    // lives elsewhere in the DOM and therefore never saw the course's colour at all: they fell
    // back to the theme's primary. A per-course accent that only tints one banner is not really
    // a per-course accent.
    //
    // Setting the same properties on <body> lets every one of them inherit it. The hero keeps its
    // inline copy, which still wins for the hero itself, so nothing about the banner changes.
    if (accent && typeof accent === 'string') {
        // Only custom properties, and only ones this plugin owns: the value reaches here from
        // course settings, and although it is validated server-side this is a second gate.
        accent.split(';').forEach((declaration) => {
            const parts = declaration.split(':');
            if (parts.length !== 2) {
                return;
            }
            const name = parts[0].trim();
            const value = parts[1].trim();
            if (!/^--acf-[a-z0-9-]+$/.test(name) || !/^[#a-zA-Z0-9.%,()\s-]+$/.test(value)) {
                return;
            }
            // On <body>, not <html>. The stylesheet declares --acf-brand on body.format-aicourse
            // (deriving it from the theme's primary), and a declaration on body beats one
            // inherited from html -- so setting it at the root left every element inside body
            // still reading the theme colour. An inline style on body outranks the stylesheet
            // rule on the same element, which is what makes the course accent actually win.
            document.body.style.setProperty(name, value);
        });
    }
};
