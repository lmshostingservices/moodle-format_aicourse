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
 * Keep the course index focused on the section the learner is in.
 *
 * 3.2.0. Moodle's course index remembers every section a learner has ever expanded, so moving from
 * one section to the next (by the index, the banner arrows or a card) left the previous section
 * open above the new one and the index grew longer with every step. On a section page or an
 * activity page this collapses every other section in the index and makes sure the current one is
 * open: an accordion that follows the learner.
 *
 * It goes through the course editor's own sectionIndexCollapsed mutation rather than the DOM, so
 * the index re-renders through core's reactive components and the collapsed state is saved to the
 * user's section preferences exactly as if they had clicked the chevrons themselves.
 *
 * @module     format_aicourse/indexfocus
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import Storage from 'core/sessionstorage';

/** @type {Number} How long to wait for the course index to draw before acting anyway. */
const WAIT_MS = 15000;

/** @type {Boolean} Guard: the hook can queue init() more than once. */
let initialised = false;

/**
 * The section being viewed plus every section it sits inside.
 *
 * Moodle 4.5 subsections are sections of their own nested in a parent section. Collapsing the
 * parent would hide the page the learner is on, so the whole chain stays open. The parent comes
 * from parentsectionid (4.5+), or from the subsection module's own section as a fallback.
 *
 * @param {Object} state The course editor state.
 * @param {String} current Id of the section being viewed.
 * @returns {Set} Ids (as strings) that must stay expanded.
 */
const sectionChain = (state, current) => {
    const keep = new Set();
    let section = state.section.get(current) || state.section.get(parseInt(current, 10));
    while (section && !keep.has(String(section.id))) {
        keep.add(String(section.id));
        let parentid = section.parentsectionid;
        if (!parentid && section.component && section.itemid && state.cm) {
            const cm = state.cm.get(section.itemid);
            parentid = cm ? cm.sectionid : null;
        }
        section = parentid ? state.section.get(parentid) : null;
    }
    return keep;
};

/**
 * Collapse every section but the current one and its parents.
 *
 * @param {Object} editor The course editor.
 * @param {String} current Id of the section being viewed.
 * @returns {void}
 */
const focusSection = (editor, current) => {
    const state = editor.state;
    if (!state || !state.section) {
        return;
    }
    const keep = sectionChain(state, current);
    if (!keep.size) {
        return;
    }
    const collapse = [];
    const expand = [];
    state.section.forEach((section) => {
        if (keep.has(String(section.id))) {
            if (section.indexcollapsed) {
                expand.push(section.id);
            }
        } else if (!section.indexcollapsed) {
            collapse.push(section.id);
        }
    });
    // Each dispatch saves the preference once; nothing is sent when nothing changes.
    if (collapse.length) {
        editor.dispatch('sectionIndexCollapsed', collapse, true);
    }
    if (expand.length) {
        editor.dispatch('sectionIndexCollapsed', expand, false);
    }
    if (collapse.length || expand.length) {
        forgetCachedState(editor);
    }
};

/**
 * Drop Moodle's browser copy of the course state after the preferences change.
 *
 * Moodle 4.4 keeps the course state in session storage and reuses it on the next page when the
 * page's state key matches. That copy still holds the preferences from before this page's change,
 * and the mutation only sends sections whose value differs from the state it is given, so a stale
 * copy let the saved preference drift: the section being viewed could end up saved as collapsed.
 * Without the stored key the next page always loads the real state from the server.
 *
 * @param {Object} editor The course editor.
 * @returns {void}
 */
const forgetCachedState = (editor) => {
    try {
        Storage.set('course/' + editor.courseId + '/stateKey', '');
    } catch (error) {
        // Storage unavailable: there is no cached copy to go stale either.
    }
};

/**
 * Start.
 *
 * @param {Number} sectionid Id of the section the page belongs to.
 * @returns {void}
 */
export const init = (sectionid) => {
    if (initialised || !sectionid) {
        return;
    }
    initialised = true;

    // No course index on this page (hidden by the format's settings, or a theme without the
    // drawer): nothing to tidy, and no reason to save preferences for it.
    if (document.body.classList.contains('aicourse-hideindex')
            || !document.querySelector('#courseindex, [data-region="courseindex"], .courseindex')) {
        return;
    }

    let editor;
    try {
        editor = getCurrentCourseEditor();
    } catch (error) {
        return;
    }
    // Editing: a teacher may open several sections at once to move activities between them, so
    // the index is left exactly as they arranged it. The accordion is for learners moving on.
    if (!editor || editor.isEditing) {
        return;
    }
    const ready = typeof editor.getInitialStatePromise === 'function'
        ? editor.getInitialStatePromise()
        : Promise.resolve();
    ready.then(() => {
        // Update the state (and the saved preference) straight away, then bring the drawn index
        // in line once it is on the page.
        focusSection(editor, String(sectionid));
        return whenIndexRendered();
    }).then(() => syncIndex(editor)).catch(() => null);
};

/**
 * Make the drawn course index match the state.
 *
 * Moodle 4.4 keeps a copy of the index HTML in session storage and replays it on the next page,
 * open and closed sections included, before the state has been applied. The state and the saved
 * preference were right, but the index on screen showed the previous page's sections open. So the
 * rows are set to match the state here, and the cached copy is dropped so the next page draws a
 * fresh index from the saved preferences instead of replaying a stale one.
 *
 * @param {Object} editor The course editor.
 * @returns {void}
 */
const syncIndex = (editor) => {
    const state = editor.state;
    if (!state || !state.section) {
        return;
    }
    let changed = false;
    state.section.forEach((section) => {
        const row = document.querySelector('#courseindex [data-for="section"][data-id="' + section.id + '"]');
        const toggler = row ? row.querySelector('.courseindex-chevron') : null;
        const content = row ? row.querySelector('.courseindex-item-content') : null;
        if (!toggler || !content) {
            return;
        }
        const collapsed = !!section.indexcollapsed;
        if (toggler.classList.contains('collapsed') === collapsed && content.classList.contains('show') === !collapsed) {
            return;
        }
        changed = true;
        toggler.classList.toggle('collapsed', collapsed);
        toggler.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        content.classList.toggle('show', !collapsed);
    });
    if (changed && typeof editor.setStorageValue === 'function') {
        editor.setStorageValue('courseIndex', {});
    }
};

/**
 * Resolve once the course index has drawn its sections.
 *
 * Moodle fetches the index contents after the page has loaded (the placeholder component), drawn
 * from the preferences saved at that moment. A collapse dispatched before then is saved but not
 * shown until the next page, so the index lagged one page behind the learner. Waiting for the
 * section rows means core's components are listening when the state changes, and the index
 * updates in place. If the index never draws (a closed drawer that loads lazily), the change is
 * still made after a short wait, so it is at least saved for the next page.
 *
 * @returns {Promise} Resolves when the rows exist or after the wait.
 */
const whenIndexRendered = () => new Promise((resolve) => {
    const rows = () => document.querySelector('#courseindex [data-for="section"]');
    if (rows()) {
        resolve();
        return;
    }
    const index = document.querySelector('#courseindex, [data-region="courseindex"], .courseindex') || document.body;
    let timer = null;
    const observer = new MutationObserver(() => {
        if (rows()) {
            observer.disconnect();
            window.clearTimeout(timer);
            resolve();
        }
    });
    observer.observe(index, {childList: true, subtree: true});
    timer = window.setTimeout(() => {
        observer.disconnect();
        resolve();
    }, WAIT_MS);
});
