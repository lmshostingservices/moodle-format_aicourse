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
 * Lifts the hero banner to the very top of the page.
 *
 * WHY THIS IS JAVASCRIPT AND NOT CSS. format.php is included by core AFTER
 * $OUTPUT->header() has already written the navbar, #page-header and the secondary
 * navigation, so a course format has no server-side insertion point above them. CSS
 * cannot help either: the banner and the nav are in different, non-sibling subtrees, so
 * there is no `order` or `flex-direction` that reaches across them. Relocating the node
 * is the only way, and it is what the format already does on section and activity pages
 * (see heroinject.js).
 *
 * The move is deliberately conservative:
 *   - it runs once, on DOM ready, and marks the node so a second call is a no-op;
 *   - it never runs in edit mode (a teacher needs the page header controls in place);
 *   - if the expected containers are missing -- a theme that does not use #page, say --
 *     it does nothing at all and the banner stays where the server put it.
 *
 * @module     format_aicourse/heroatop
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {string} Marks the banner once lifted, so the move happens exactly once. */
const DONEATTR = 'data-aicourse-atop';

/**
 * Move the hero banner to the top of the page container.
 *
 * @returns {void}
 */
const lift = () => {
    const hero = document.querySelector('.aicourse-hero-sticky-wrap');
    if (!hero || hero.hasAttribute(DONEATTR)) {
        return;
    }

    // Edit mode keeps the normal page furniture; body classes are written server-side.
    const body = document.body;
    if (body.classList.contains('editing')
        || body.classList.contains('editingon')
        || body.classList.contains('editing-mode')) {
        return;
    }

    const page = document.getElementById('page');
    if (!page) {
        return;
    }

    // Find the landmark to sit above. querySelector searches DESCENDANTS, so the node it
    // returns is usually nested several levels inside #page -- and insertBefore() requires a
    // DIRECT child, which is what threw
    //     NotFoundError: The node before which the new node is to be inserted is not a
    //     child of this node
    // on themes that wrap the header. Walk back up to the ancestor that IS a child of #page.
    let before = page.querySelector('#page-header, .secondary-navigation, #page-content');
    while (before && before.parentElement && before.parentElement !== page) {
        before = before.parentElement;
    }
    if (!before || before.parentElement !== page) {
        before = page.firstElementChild;
    }
    if (!before || before === hero || before.contains(hero)) {
        // A theme where the banner is already inside the node we would insert before: moving it
        // would either be a no-op or would detach it from its own ancestor mid-walk. Leave it.
        if (!before || before === hero) {
            return;
        }
    }
    if (before.parentElement !== page) {
        return;
    }

    hero.setAttribute(DONEATTR, '1');
    page.insertBefore(hero, before);
};

/**
 * Initialise the module.
 *
 * @returns {void}
 */
export const init = () => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', lift, {once: true});
        return;
    }
    lift();
};
