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
 * Move the course tab bar into the site header for users who can edit.
 *
 * @module     format_aicourse/coursenav
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const MOVED = 'data-aicourse-moved';

/**
 * Where the site's own navigation lives in this theme.
 *
 * Both Boost and theme_academi mark it `.primary-navigation`, which is the row carrying Home,
 * Dashboard, My courses and Site administration. Falling back through the containers those links
 * commonly sit in means a theme that names things differently still gets a sensible home for the
 * tabs rather than nothing happening.
 *
 * @returns {Element|null}
 */
const findHeaderNav = () => {
    const candidates = [
        '.primary-navigation .moremenu .nav',
        '.primary-navigation .nav',
        '.primary-navigation',
        'nav.navbar .navbar-nav.ms-auto',
        'nav.navbar .navbar-nav',
    ];
    for (const sel of candidates) {
        const el = document.querySelector(sel);
        if (el) {
            return el;
        }
    }
    return null;
};

/**
 * Move the course tabs up into the header.
 *
 * ACF-FIX-2.1.89. For a learner the tab bar is noise and is hidden. For a teacher it is how they
 * reach Settings, Participants, Grades and Reports, so hiding it outright costs them the fastest
 * route to those pages -- but leaving it as a full-width band under the banner spends a lot of
 * vertical space on links only editors use.
 *
 * Moving it beside the site's own navigation puts it where a teacher already looks for links of
 * that kind, and gives the vertical space back to the course.
 *
 * Not done while editing: a teacher arranging a course gets the normal page furniture back, which
 * is the same rule every other visibility setting in this format follows.
 *
 * @returns {void}
 */
const relocate = () => {
    const body = document.body;
    if (body.classList.contains('editing')
        || body.classList.contains('editingon')
        || body.classList.contains('editing-mode')) {
        return;
    }

    const bar = document.querySelector('.secondary-navigation');
    if (!bar || bar.hasAttribute(MOVED)) {
        return;
    }

    const target = findHeaderNav();
    if (!target) {
        // Nowhere sensible to put it: leave it exactly where the theme rendered it rather than
        // moving it somewhere it will look wrong.
        return;
    }

    bar.setAttribute(MOVED, '1');
    bar.classList.add('aicourse-coursenav-moved');
    target.appendChild(bar);
};

/**
 * Start.
 *
 * @returns {void}
 */
export const init = () => {
    if (document.readyState !== 'loading') {
        relocate();
    } else {
        document.addEventListener('DOMContentLoaded', relocate, {once: true});
    }
};
