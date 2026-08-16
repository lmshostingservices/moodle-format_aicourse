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
 * The AI Course card view, bound to the reactive course editor.
 *
 * This is the card-view counterpart of core_courseformat/local/content. It cannot simply BE that
 * component: core's version repaints the whole section list from core fragments, which would
 * replace every AI Course card with core's own section markup, and it assumes every section in
 * course.sectionlist has an element inside [data-for="course_sectionlist"] — not true here,
 * because section 0 is rendered above the grid and the "Add section" control lives inside it.
 *
 * What it does reuse from core, unchanged:
 *  - core_courseformat/local/courseeditor/dndsection + dndsectionitem, through
 *    format_aicourse/local/sectioncard and format_aicourse/local/sectioncardhandle.
 *  - core_courseformat/local/courseeditor/dndcmitem, through format_aicourse/local/activitycard.
 *  - core_courseformat/local/content/actions, which turns every data-action attribute in the
 *    region into the matching core mutation and provides the keyboard accessible move dialogues.
 *
 * @module     format_aicourse/local/cardcontent
 * @class      format_aicourse/local/cardcontent
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import DispatchActions from 'core_courseformat/local/content/actions';
import SectionCard from 'format_aicourse/local/sectioncard';
import GeneralSection from 'format_aicourse/local/generalsection';
import ActivityCard from 'format_aicourse/local/activitycard';
import Config from 'core/config';
import Fragment from 'core/fragment';
import Pending from 'core/pending';
import Templates from 'core/templates';
import {debounce} from 'core/utils';

export default class Component extends BaseComponent {

    /**
     * Constructor hook.
     */
    create() {
        // Optional component name for debugging.
        this.name = 'aicourse_card_content';
        // Default query selectors.
        this.selectors = {
            SECTIONLIST: `.aicourse-cards-grid`,
            SECTIONCARD: `.aicourse-cards-grid > [data-for='section']`,
            GENERALSECTION: `.aicourse-general-section [data-for='section']`,
            SECTION: `[data-for='section']`,
            CM: `[data-for='cmitem']`,
            CMLIST: `[data-for='cmlist']`,
            ADDSECTIONCARD: `.aicourse-add-section-card`,
        };
        this.classes = {
            STATEREADY: 'stateready',
        };
        // Indexes of the section card and activity components, keyed by state id.
        this.sections = {};
        this.generalsections = {};
        this.cms = {};
        // Activity elements taken out of the DOM by a move, kept in case they come back.
        this.dettachedCms = {};
        // One debounced card reload per section id, so a single mutation that touches the same
        // section several times only costs one fragment request.
        this.debouncedReloads = new Map();
    }

    /**
     * Static method to create a component instance.
     *
     * @param {String} selector CSS selector of the card region
     * @return {Component|null} the component, or null when the region is not on this page
     */
    static init(selector) {
        const element = document.querySelector(selector);
        if (!element) {
            return null;
        }
        return new Component({
            element,
            reactive: getCurrentCourseEditor(),
        });
    }

    /**
     * Initial state ready method.
     */
    stateReady() {
        // Everything below binds editing affordances. A student, or a teacher with edit mode off,
        // must get no drag handles and no listeners at all.
        if (!this.reactive.isEditing || !this.reactive.supportComponents) {
            return;
        }
        this._indexContents();
        // Core's dispatcher: data-action="moveSection" / "moveCm" and every core section and
        // activity action menu entry inside the region now work.
        new DispatchActions(this);
        this.element.classList.add(this.classes.STATEREADY);
    }

    /**
     * Component watchers.
     *
     * @returns {Array} of watchers
     */
    getWatchers() {
        if (!this.reactive.supportComponents) {
            return [];
        }
        return [
            // Section and activity sorting.
            {watch: `course.sectionlist:updated`, handler: this._refreshSectionList},
            {watch: `section.cmlist:updated`, handler: this._refreshSectionCmlist},
            // Bind any card that appeared since the last state change.
            {watch: `state:updated`, handler: this._indexContents},
        ];
    }

    /**
     * Bind every card that is not bound yet.
     */
    _indexContents() {
        if (!this.reactive.isEditing || !this.reactive.supportComponents) {
            return;
        }
        this._scanIndex(
            this.selectors.SECTIONCARD,
            this.sections,
            (item) => new SectionCard(item)
        );
        // Section 0 is not a card, but it still has to accept activities dropped back into it.
        this._scanIndex(
            this.selectors.GENERALSECTION,
            this.generalsections,
            (item) => new GeneralSection(item)
        );
        this._scanIndex(
            this.selectors.CM,
            this.cms,
            (item) => new ActivityCard(item)
        );
    }

    /**
     * Bind every element matching a selector that carries no data-indexed marker yet.
     *
     * @param {String} selector the DOM selector to scan
     * @param {Object} index the index to update
     * @param {Function} creationhandler builds the component for one element
     */
    _scanIndex(selector, index, creationhandler) {
        const items = this.element.querySelectorAll(`${selector}:not([data-indexed])`);
        items.forEach((item) => {
            if (!item?.dataset?.id) {
                return;
            }
            if (index[item.dataset.id] !== undefined) {
                index[item.dataset.id].unregister();
            }
            index[item.dataset.id] = creationhandler({
                ...this,
                element: item,
            });
            item.dataset.indexed = true;
        });
    }

    /**
     * Reorder the section cards to match the course section list.
     *
     * Only cards that are actually in the grid are moved. Section ids with no card — section 0,
     * which is pinned above the grid, and any delegated section — are skipped, and the
     * "Add section" control is kept last.
     *
     * @param {Object} param
     * @param {Object} param.state the full state object
     */
    _refreshSectionList({state}) {
        const listparent = this.element.querySelector(this.selectors.SECTIONLIST);
        if (!listparent) {
            return;
        }
        const sectionlist = this.reactive.getExporter().listedSectionIds(state);
        const cards = [];
        sectionlist.forEach((sectionid) => {
            const card = listparent.querySelector(`${this.selectors.SECTION}[data-id='${sectionid}']`);
            if (card) {
                cards.push(card);
            }
        });
        const current = [...listparent.querySelectorAll(this.selectors.SECTION)];
        const samesorting = cards.length === current.length
            && cards.every((card, index) => card === current[index]);
        if (samesorting) {
            return;
        }
        cards.forEach((card) => listparent.appendChild(card));
        const addcard = listparent.querySelector(this.selectors.ADDSECTIONCARD);
        if (addcard) {
            listparent.appendChild(addcard);
        }
    }

    /**
     * Bring one section's activities back in step with the state.
     *
     * A section that is drawn as a CARD shows no activity list at all: it shows the "N activities"
     * count, the completion percentage badge and the compact progress dots, none of which exist in
     * the reactive state — they are computed on the server from modinfo and completion data. So a
     * card cannot be patched from the state and is instead re-rendered through the plugin's
     * sectioncard fragment, which is what core does for a whole section in the standard course
     * content. Every other section (section 0, drawn inline above the grid) does render its
     * activities, and those are simply reordered in place.
     *
     * @param {Object} param
     * @param {Object} param.element the section state data
     */
    _refreshSectionCmlist({element}) {
        const card = this.element.querySelector(
            `${this.selectors.SECTIONCARD}[data-id='${element.id}']`
        );
        if (card) {
            this._getDebouncedReloadCard(element.id)();
            return;
        }
        // Core's own cmlist markup carries no data-id, so it has to be reached through its
        // section; the plugin's activity grid is not wrapped in a section element but does carry
        // the id itself. Both shapes can appear in the card region, so both are looked up.
        const section = this.element.querySelector(
            `${this.selectors.SECTION}[data-id='${element.id}']`
        );
        const listparent = section?.querySelector(this.selectors.CMLIST)
            ?? this.element.querySelector(`${this.selectors.CMLIST}[data-id='${element.id}']`);
        if (!listparent) {
            return;
        }
        this._fixCmOrder(listparent, element.cmlist ?? []);
    }

    /**
     * Sort the activity items of a list into the state order.
     *
     * An id with no element anywhere in the page belongs to an activity that has just been moved
     * INTO this section from somewhere the card region does not draw (a section card, or the
     * course index). Those are fetched from core's own cmitem fragment, exactly as core's course
     * content does, so an activity dropped into the General section appears immediately instead of
     * only after a page reload.
     *
     * @param {Element} container the activity list element
     * @param {Array} neworder the ordered list of cm ids
     */
    _fixCmOrder(container, neworder) {
        const items = [];
        neworder.forEach((cmid) => {
            const item = container.querySelector(`${this.selectors.CM}[data-id='${cmid}']`)
                ?? this.dettachedCms[cmid]
                ?? this._createCmItem(container, cmid);
            if (item) {
                items.push(item);
                delete this.dettachedCms[cmid];
            }
        });
        items.forEach((item, index) => {
            const currentitem = container.children[index];
            if (currentitem === undefined) {
                container.append(item);
                return;
            }
            if (currentitem !== item) {
                container.insertBefore(item, currentitem);
            }
        });
        // Anything left over has moved to a section this region does not render.
        while (container.children.length > items.length) {
            const lastchild = container.lastChild;
            if (lastchild?.dataset?.id) {
                this.dettachedCms[lastchild.dataset.id] = lastchild;
            }
            container.removeChild(lastchild);
        }
    }

    /**
     * Add a placeholder activity item to a list and fill it from core's cmitem fragment.
     *
     * The placeholder gets the id and classes core's own legacy edit actions expect, so the
     * activity is a valid drop target and a valid action menu host from the moment it appears.
     *
     * @param {Element} container the activity list element
     * @param {Number} cmid the course module id
     * @returns {Element} the placeholder element, replaced once the fragment arrives
     */
    _createCmItem(container, cmid) {
        // Match whatever the list already holds; core's activity lists are <ul> of <li>.
        const tagname = container.firstElementChild?.tagName ?? 'LI';
        const newitem = document.createElement(tagname);
        newitem.dataset.for = 'cmitem';
        newitem.dataset.id = cmid;
        newitem.id = `module-${cmid}`;
        newitem.classList.add('activity');
        container.append(newitem);
        this._reloadCmItem(cmid, newitem);
        return newitem;
    }

    /**
     * Replace one activity item with a freshly rendered one from core's cmitem fragment.
     *
     * @param {Number} cmid the course module id
     * @param {Element} cmitem the element to replace
     * @returns {Pending} the pending promise of this reload
     */
    _reloadCmItem(cmid, cmitem) {
        const pendingreload = new Pending(`aicourse/cardcontent:reloadCm_${cmid}`);
        Fragment.loadFragment(
            'core_courseformat',
            'cmitem',
            Config.courseContextId,
            {
                id: cmid,
                courseid: Config.courseId,
                sr: this.reactive.sectionReturn ?? null,
            }
        ).then((html, js) => {
            if (!document.contains(cmitem)) {
                pendingreload.resolve();
                return false;
            }
            Templates.replaceNode(cmitem, html, js);
            this._indexContents();
            pendingreload.resolve();
            return true;
        }).catch(() => {
            pendingreload.resolve();
        });
        return pendingreload;
    }

    /**
     * The debounced card reloader for one section, created on first use.
     *
     * Moving an activity between two sections updates both section.cmlist values in the same
     * mutation, and a multi-activity move updates the same list several times. Debouncing on the
     * section id collapses those into one fragment request per affected card, exactly as core
     * debounces its own activity reloads.
     *
     * @param {Number} sectionid the section record id
     * @returns {Function} the debounced reload function for this section
     */
    _getDebouncedReloadCard(sectionid) {
        const pendingkey = `aicourse/cardcontent:reloadCard_${sectionid}`;
        let debouncedreload = this.debouncedReloads.get(pendingkey);
        if (debouncedreload) {
            return debouncedreload;
        }
        debouncedreload = debounce(
            () => this._reloadCard(sectionid, pendingkey),
            200,
            {cancel: true, pending: true}
        );
        this.debouncedReloads.set(pendingkey, debouncedreload);
        return debouncedreload;
    }

    /**
     * Replace one section card with a freshly rendered one.
     *
     * @param {Number} sectionid the section record id
     * @param {String} pendingkey the core/pending key of this reload
     * @returns {Pending} the pending promise of this reload
     */
    _reloadCard(sectionid, pendingkey) {
        const pendingreload = new Pending(pendingkey);
        this.debouncedReloads.delete(pendingkey);
        const card = this.element.querySelector(
            `${this.selectors.SECTIONCARD}[data-id='${sectionid}']`
        );
        if (!card) {
            pendingreload.resolve();
            return pendingreload;
        }
        Fragment.loadFragment(
            'format_aicourse',
            'sectioncard',
            Config.courseContextId,
            {
                id: sectionid,
                courseid: Config.courseId,
            }
        ).then((html, js) => {
            // Another state change may have removed the card while the request was in flight.
            if (!document.contains(card)) {
                pendingreload.resolve();
                return false;
            }
            Templates.replaceNode(card, html, js);
            // The replacement carries no data-indexed marker, so this binds a new section card
            // component to it and unregisters the one bound to the node that has just gone.
            this._indexContents();
            pendingreload.resolve();
            return true;
        }).catch(() => {
            pendingreload.resolve();
        });
        return pendingreload;
    }
}
