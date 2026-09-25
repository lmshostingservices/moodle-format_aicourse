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
 * Collapses and restores the top band.
 *
 * The band is the hero banner plus, in merged course index mode, the drawer header beside it.
 * Both are sized from --acf-topblock, so all this module does is toggle one body class; the
 * geometry is entirely in styles.css (see the 2.4.0 block).
 *
 * WHAT THIS MODULE DELIBERATELY DOES NOT DO. It does not apply the collapsed state on load.
 * That is done server-side in page_set_course(), so the class is in the <body> tag before the
 * first paint. If this module set it instead, every page load for a user who prefers the band
 * shut would paint the full band and then snap it closed.
 *
 * The click handler is delegated from the document rather than bound to the button, because
 * heroatop.js MOVES the banner -- and the button inside it -- to the top of the page after DOM
 * ready, and on activity pages heroinject.js inserts the whole banner later still. A handler
 * bound directly to the node would have to be rebound after every one of those, or bound too
 * early to find anything.
 *
 * @module     format_aicourse/herocollapse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/** @type {string} The toggle button. */
const TOGGLE = '.aicourse-hero-collapse';

/** @type {string} Body class that collapses the band. */
const CLASS = 'aicourse-hero-collapsed';

/** @type {string} The user preference, which must match herocollapse::PREF in PHP. */
const PREF = 'format_aicourse_herocollapsed';

/** @type {boolean} Guards against a second init on a page that requires the module twice. */
let initialised = false;

/**
 * Store the state, so the next page opens the way this one was left.
 *
 * Failure is swallowed on purpose. The band is already in the state the user asked for; a
 * rejected write means only that the next page forgets, which is not worth an error dialog
 * over. The one failure that matters -- the preference not being declared in
 * format_aicourse_user_preferences() -- shows up as the band reopening on every page, which
 * is exactly the symptom ACF-FIX-2.1.43 documents.
 *
 * @param {boolean} collapsed The state to remember.
 * @returns {Promise} Resolves when the write settles, never rejects.
 */
const remember = (collapsed) => {
    return Ajax.call([{
        methodname: 'core_user_update_user_preferences',
        args: {preferences: [{type: PREF, value: collapsed ? '1' : '0'}]},
    }])[0].catch(() => null);
};

/**
 * Put the button's labels in step with the state.
 *
 * @param {HTMLElement} button The toggle.
 * @param {boolean} collapsed The state just applied.
 * @returns {void}
 */
const relabel = (button, collapsed) => {
    const label = collapsed
        ? button.getAttribute('data-expandlabel')
        : button.getAttribute('data-collapselabel');
    button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    if (label) {
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    }
};

/**
 * Toggle the band.
 *
 * @param {HTMLElement} button The toggle that was pressed.
 * @returns {void}
 */
const toggle = (button) => {
    const collapsed = document.body.classList.toggle(CLASS);
    relabel(button, collapsed);
    remember(collapsed);
    // Anything measuring the viewport needs to know the page just changed height. The sticky
    // hero publishes --acf-hero-sticky-top from a resize listener (see heroatop.js), and a
    // collapse is a height change that no resize event would otherwise announce.
    window.dispatchEvent(new Event('resize'));
};

/**
 * Wire up the toggle.
 *
 * @returns {void}
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }
        const button = target.closest(TOGGLE);
        if (!button) {
            return;
        }
        event.preventDefault();
        toggle(button);
    });
};
