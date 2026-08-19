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
 * Rating and correction controls of the per-course AI Tutor report.
 *
 * ACF-2.1: replaces the ~40 line inline bootstrap that
 * format_aicourse\output\report\historytab::require_js() used to register with
 * $PAGE->requires->js_amd_inline(). Besides moving the code into a real module, this version:
 *
 *  - updates the row in place instead of calling window.location.reload(), so the reader keeps
 *    their page number, their filters and their scroll position;
 *  - manages focus on the correction disclosure — into the textarea when it opens, back onto the
 *    disclosure button when it closes — and restores focus to the control that was clicked after
 *    a request that had to disable it;
 *  - announces every outcome in an aria-live region;
 *  - wraps each request in core/pending so Behat waits for it.
 *
 * All markup this module writes is built with createElement + textContent. The question, response
 * and correction are raw student and AI generated text: assigning any of them to innerHTML would
 * be a cross-site scripting hole.
 *
 * @module     format_aicourse/report
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';
import {get_strings as getStrings} from 'core/str';

/** @type {Object} The DOM contract, as emitted by format_aicourse/report_history_tab. */
const SELECTORS = {
    table: '.aicourse-chat-table',
    trigger: '[data-action][data-chatid]',
    rateButton: '[data-action="rate"]',
    ratingGroup: '.aicourse-chat-rating',
    responseCell: '.aicourse-chat-response',
    correction: '.aicourse-chat-correction',
    row: 'tr',
};

/** @type {Object} Class names this module toggles. */
const CLASSES = {
    helpful: 'helpful',
    notHelpful: 'not-helpful',
    correction: 'aicourse-chat-correction',
    live: 'aicourse-report-live',
    updated: 'aicourse-row-updated',
};

/** @type {Number} Course the report is showing. */
let courseId = 0;

/** @type {Promise|null} The strings this module announces, requested once at init time. */
let stringsPromise = null;

/** @type {HTMLElement|null} The polite live region announcements are written to. */
let liveRegion = null;

/**
 * Build the visually hidden live region every outcome is announced in.
 *
 * @param {HTMLElement} table The results table.
 * @returns {HTMLElement} The live region.
 */
const createLiveRegion = (table) => {
    const region = document.createElement('div');
    region.className = CLASSES.live;
    region.setAttribute('role', 'status');
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'true');
    table.parentNode.insertBefore(region, table);
    return region;
};

/**
 * Announce an outcome to assistive technology.
 *
 * The region is cleared first so that the same message twice in a row is still announced.
 *
 * @param {String} message Already translated message.
 * @returns {void}
 */
const announce = (message) => {
    if (!liveRegion || !message) {
        return;
    }
    liveRegion.textContent = '';
    window.setTimeout(() => {
        liveRegion.textContent = message;
    }, 50);
};

/**
 * Confirm an in-place update visually, for the sighted reader who is not looking at the cell.
 *
 * @param {HTMLElement|null} row The table row that changed.
 * @returns {void}
 */
const flashRow = (row) => {
    if (!row) {
        return;
    }
    row.classList.remove(CLASSES.updated);
    // Force a reflow so the animation restarts when the same row is updated twice.
    row.getBoundingClientRect();
    row.classList.add(CLASSES.updated);
    row.addEventListener('animationend', () => {
        row.classList.remove(CLASSES.updated);
    }, {once: true});
};

/**
 * Disable or re-enable controls for the duration of a request.
 *
 * @param {HTMLElement[]} controls Controls to lock.
 * @param {Boolean} disabled Whether they should be disabled.
 * @returns {void}
 */
const setDisabled = (controls, disabled) => {
    controls.forEach((control) => {
        control.disabled = disabled;
    });
};

/**
 * Call one of the plugin's web services.
 *
 * @param {String} methodname External function to call.
 * @param {Object} args Its arguments.
 * @param {HTMLElement[]} controls Controls to disable while the request is in flight.
 * @returns {Promise} Resolved with the web service response.
 */
const request = async(methodname, args, controls) => {
    const pending = new Pending('format_aicourse/report:' + methodname);
    setDisabled(controls, true);
    try {
        return await Ajax.call([{methodname: methodname, args: args}])[0];
    } finally {
        setDisabled(controls, false);
        pending.resolve();
    }
};

/**
 * Show the stored rating on the pair of buttons of one row.
 *
 * @param {HTMLElement[]} buttons The two rating buttons of the row.
 * @param {Number} rating 1 for helpful, -1 for not helpful.
 * @returns {void}
 */
const applyRating = (buttons, rating) => {
    buttons.forEach((button) => {
        const value = parseInt(button.dataset.rating, 10);
        const pressed = (value === rating);
        button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
        button.classList.toggle(CLASSES.helpful, pressed && value === 1);
        button.classList.toggle(CLASSES.notHelpful, pressed && value === -1);
    });
};

/**
 * Show the stored correction in the response cell of one row, without a page reload.
 *
 * @param {HTMLElement|null} row The row that was corrected.
 * @param {String} correction The correction, or an empty string when it was cleared.
 * @param {String} label The translated "Correction" label.
 * @returns {void}
 */
const applyCorrection = (row, correction, label) => {
    const cell = row ? row.querySelector(SELECTORS.responseCell) : null;
    if (!cell) {
        return;
    }

    let block = cell.querySelector(SELECTORS.correction);
    if (correction === '') {
        if (block) {
            block.remove();
        }
        return;
    }

    if (!block) {
        block = document.createElement('div');
        block.className = CLASSES.correction;
        const heading = document.createElement('strong');
        heading.textContent = label;
        block.appendChild(heading);
        block.appendChild(document.createElement('div'));
        cell.appendChild(block);
    }
    block.lastElementChild.textContent = correction;
};

/**
 * The disclosure button that owns a correction panel — the one carrying aria-expanded, not the
 * Cancel button, which only carries aria-controls.
 *
 * @param {Number} chatid Id of the chat row.
 * @returns {HTMLElement|null}
 */
const getDisclosure = (chatid) => document.querySelector(
    '[data-action="togglecorrection"][data-chatid="' + chatid + '"][aria-expanded]'
);

/**
 * Open or close a correction panel, moving focus with it.
 *
 * @param {Number} chatid Id of the chat row.
 * @param {Boolean} open Whether the panel should end up open.
 * @returns {void}
 */
const setPanelOpen = (chatid, open) => {
    const panel = document.getElementById('aicourse-correction-' + chatid);
    if (!panel) {
        return;
    }

    panel.hidden = !open;
    const disclosure = getDisclosure(chatid);
    if (disclosure) {
        disclosure.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    if (open) {
        const field = panel.querySelector('[data-region="correctiontext"]');
        if (field) {
            field.focus();
        }
    } else if (disclosure) {
        disclosure.focus();
    }
};

/**
 * Record a rating, then show it on the row.
 *
 * @param {HTMLElement} button The rating button that was activated.
 * @returns {Promise} Resolved once the row reflects the outcome.
 */
const rate = async(button) => {
    const chatid = parseInt(button.dataset.chatid, 10);
    const rating = parseInt(button.dataset.rating, 10);
    const row = button.closest(SELECTORS.row);
    const group = button.closest(SELECTORS.ratingGroup);
    const buttons = group ? Array.from(group.querySelectorAll(SELECTORS.rateButton)) : [button];
    const strings = await stringsPromise;

    try {
        await request('format_aicourse_rate_chat', {courseid: courseId, chatid: chatid, rating: rating}, buttons);
        applyRating(buttons, rating);
        flashRow(row);
        announce(strings.ratingsaved);
    } catch (error) {
        announce(strings.ratingfailed);
        Notification.exception(error);
    }

    // The request disabled the button, which moved focus to the document; give it back.
    button.focus();
};

/**
 * Store a correction, then show it on the row and close the panel.
 *
 * @param {HTMLElement} button The Save button that was activated.
 * @returns {Promise} Resolved once the row reflects the outcome.
 */
const saveCorrection = async(button) => {
    const chatid = parseInt(button.dataset.chatid, 10);
    const row = button.closest(SELECTORS.row);
    const field = document.querySelector('[data-region="correctiontext"][data-chatid="' + chatid + '"]');
    const correction = field ? field.value.trim() : '';
    const strings = await stringsPromise;

    try {
        await request(
            'format_aicourse_correct_chat',
            {courseid: courseId, chatid: chatid, correction: correction},
            [button]
        );
        applyCorrection(row, correction, strings.correctionlabel);
        setPanelOpen(chatid, false);
        flashRow(row);
        announce(strings.correctionsaved);
    } catch (error) {
        announce(strings.correctionfailed);
        Notification.exception(error);
        button.focus();
    }
};

/**
 * The one delegated listener.
 *
 * @param {Event} event The click event.
 * @returns {void}
 */
const handleClick = (event) => {
    const trigger = event.target.closest(SELECTORS.trigger);
    if (!trigger || trigger.disabled) {
        return;
    }

    const chatid = parseInt(trigger.dataset.chatid, 10);
    if (trigger.dataset.action === 'togglecorrection') {
        const panel = document.getElementById('aicourse-correction-' + chatid);
        setPanelOpen(chatid, panel ? panel.hidden : false);
    } else if (trigger.dataset.action === 'rate') {
        rate(trigger);
    } else if (trigger.dataset.action === 'savecorrection') {
        saveCorrection(trigger);
    }
};

/**
 * Wire up the chat history table.
 *
 * Safe to call on a report page showing the empty state, or the Course Content tab: there is no
 * table there, and the module then does nothing at all.
 *
 * @param {Number} courseid Course the report is showing.
 * @returns {void}
 */
export const init = (courseid) => {
    const table = document.querySelector(SELECTORS.table);
    if (!table) {
        return;
    }

    courseId = parseInt(courseid, 10);
    liveRegion = createLiveRegion(table);
    // Core's changessaved ("Changes saved") is deliberate: it says the right thing, it is
    // already translated in every language pack, and it keeps this module free of any
    // dependency on a plugin-specific string for a phrase core already owns. Plugin-specific
    // alternatives would be a refinement, not a requirement.
    stringsPromise = getStrings([
        {key: 'changessaved', component: 'core'},
        {key: 'aireport_correction', component: 'format_aicourse'},
        {key: 'error_ratingfailed', component: 'format_aicourse'},
        {key: 'error_correctionfailed', component: 'format_aicourse'},
    ]).then(([saved, correctionlabel, ratingfailed, correctionfailed]) => {
        return {
            ratingsaved: saved,
            correctionsaved: saved,
            correctionlabel: correctionlabel,
            ratingfailed: ratingfailed,
            correctionfailed: correctionfailed,
        };
    }).catch((error) => {
        Notification.exception(error);
        return {};
    });

    table.addEventListener('click', handleClick);
};
