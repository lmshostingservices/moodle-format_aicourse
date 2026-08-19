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
 * One AI Course section card, bound to the reactive course editor.
 *
 * The card plays the same role core_courseformat/local/content/section plays in the standard
 * course content: it is the drop zone, and an inner item (the grab handle) is the draggable
 * element. Extending core's dndsection means the drop-zone bookkeeping, the drag payload and the
 * autoscroll all come from core.
 *
 * Section 0 is deliberately never given one of these components: it is rendered above the grid by
 * format_aicourse/general_section, so it can be neither dragged nor dropped on and stays pinned
 * first.
 *
 * @module     format_aicourse/local/sectioncard
 * @class      format_aicourse/local/sectioncard
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import DndSection from 'core_courseformat/local/courseeditor/dndsection';
import SectionCardHandle from 'format_aicourse/local/sectioncardhandle';

export default class extends DndSection {

    /**
     * Constructor hook.
     */
    create() {
        // Optional component name for debugging.
        this.name = 'aicourse_section_card';
        // Default query selectors. Keys are unique to this component so the parent component's
        // selectors, which are merged in by BaseComponent, cannot shadow them.
        this.selectors = {
            CARDHANDLE: `[data-drag-type='section']`,
            CARDTITLE: `[data-for='section_title']`,
            CARDLINK: `.aicourse-card-link`,
            CARDBULKSELECT: `[data-for='sectionBulkSelect']`,
            CARDBULKCHECKBOX: `[data-bulkcheckbox]`,
        };
        // The drag and drop classes (DRAGGING, DROPZONE, DROPUP, DROPDOWN) are merged in by
        // core's dragdrop module in configDragDrop().
        this.classes = {
            LOCKED: 'editinprogress',
            // The core dimmed_text class gives an immediate, correct "hidden from
            // students" rendering.
            CARDHIDDEN: 'aicourse-card-hidden',
            DIMMED: 'dimmed_text',
            // Bootstrap's display utility and core's bulk selection marker, both used by
            // core_courseformat/local/content/section/header, which this mirrors.
            HIDE: 'd-none',
            SELECTED: 'selected',
        };
        // We need our id to watch specific events.
        this.id = this.element.dataset.id;
    }

    /**
     * Initial state ready method.
     *
     * @param {Object} state the initial state
     */
    stateReady(state) {
        this.configState(state);
        // Drag and drop is only available for components compatible course formats in edit mode.
        if (this.reactive.isEditing && this.reactive.supportComponents) {
            const handle = this.getElement(this.selectors.CARDHANDLE);
            if (handle) {
                const handleComponent = new SectionCardHandle({
                    ...this,
                    element: handle,
                    fullregion: this.element,
                });
                this.configDragDrop(handleComponent);
            }
        }
        this._refreshCard({element: this.section});
        this._refreshBulk({state});
    }

    /**
     * Component watchers.
     *
     * @returns {Array} of watchers
     */
    getWatchers() {
        return [
            {watch: `section[${this.id}]:updated`, handler: this._refreshCard},
            {watch: `bulk:updated`, handler: this._refreshBulk},
        ];
    }

    /**
     * Show, hide and update the card's bulk selection checkbox.
     *
     * This is the card equivalent of core_courseformat/local/content/section/header's own
     * _refreshBulk(). The only structural difference is that the draggable element of a card is
     * the grab handle, not the card itself, so setDraggable() is forwarded to the handle
     * subcomponent core's dndsection stored in this.sectionitem.
     *
     * @param {Object} param
     * @param {Object} param.state the full state object
     */
    _refreshBulk({state}) {
        const bulk = state.bulk;
        if (!bulk || !this._isSectionBulkEditable()) {
            return;
        }
        // Dragging a card while a bulk selection is being made is not possible, exactly as in
        // core's own course content.
        this.sectionitem?.setDraggable(!bulk.enabled);
        this.getElement(this.selectors.CARDBULKSELECT)?.classList.toggle(this.classes.HIDE, !bulk.enabled);

        const disabled = !this._isSectionBulkEnabled(bulk);
        const selected = this._isSelected(bulk);
        this.element.classList.toggle(this.classes.SELECTED, selected);
        this._setCheckboxValue(selected, disabled);
    }

    /**
     * Set the checked and disabled state of the card's bulk checkbox.
     *
     * @param {Boolean} checked the new checked value
     * @param {Boolean} disabled the new disabled value
     */
    _setCheckboxValue(checked, disabled) {
        const checkbox = this.getElement(this.selectors.CARDBULKCHECKBOX);
        if (!checkbox) {
            return;
        }
        checkbox.checked = checked;
        checkbox.disabled = disabled;
        // The data-is-selectable marker is how core's bulk edit tools find the selectable
        // checkboxes of the page for "Select all", so it has to be kept in sync here too.
        if (disabled) {
            checkbox.removeAttribute('data-is-selectable');
        } else {
            checkbox.dataset.isSelectable = 1;
        }
    }

    /**
     * Whether this section can take part in a bulk selection at all.
     *
     * @returns {Boolean} the section's bulkeditable state flag
     */
    _isSectionBulkEditable() {
        return this.reactive.get('section', this.id)?.bulkeditable ?? false;
    }

    /**
     * Whether a section can be added to the CURRENT bulk selection.
     *
     * @param {Object} bulk the current state bulk attribute
     * @returns {Boolean} false while activities are being selected instead
     */
    _isSectionBulkEnabled(bulk) {
        if (!bulk.enabled) {
            return false;
        }
        return (bulk.selectedType === '' || bulk.selectedType === 'section');
    }

    /**
     * Whether this section is part of the current bulk selection.
     *
     * @param {Object} bulk the current state bulk attribute
     * @returns {Boolean} true when this card is selected
     */
    _isSelected(bulk) {
        if (bulk.selectedType !== 'section') {
            return false;
        }
        return bulk.selection.includes(this.id);
    }

    /**
     * Update the card from the section state.
     *
     * Covers everything the card can show without asking the server again: the drag and lock
     * classes, the section number, the section name (in the heading and in the card link's
     * accessible name) and whether the section is hidden from students.
     *
     * @param {Object} param
     * @param {Object} param.element the section state data
     */
    _refreshCard({element}) {
        if (!element) {
            return;
        }
        this.element.classList.toggle(this.classes.DRAGGING, element.dragging ?? false);
        this.element.classList.toggle(this.classes.LOCKED, element.locked ?? false);
        this.locked = element.locked;

        const hidden = !(element.visible ?? true);
        this.element.classList.toggle(this.classes.CARDHIDDEN, hidden);
        this.element.classList.toggle(this.classes.DIMMED, hidden);

        if (element.number !== undefined) {
            this.element.dataset.number = element.number;
            this.element.dataset.section = element.number;
        }

        this._refreshTitle(element.title);
    }

    /**
     * Rename the card heading and keep the card link's accessible name in sync.
     *
     * The link's accessible name is "name, N activities, Estimated time X, Y% complete"; only the
     * leading name changes here, the rest is server data this component cannot recompute.
     *
     * section.title is the section name as the server stored it, so it can legitimately contain
     * HTML entities — a section called "Data &amp; Features" arrives as the six characters
     * "&amp;". Writing it with textContent printed those characters literally on every card in
     * edit mode, so it is written the same way core writes the very same value into the course
     * index (core_courseformat/local/courseindex/section), and the resulting TEXT is what the
     * accessible name is rebuilt from.
     *
     * @param {String} title the new section title
     */
    _refreshTitle(title) {
        if (title === undefined || title === null || title === this.rawtitle) {
            return;
        }
        const titleElement = this.getElement(this.selectors.CARDTITLE);
        if (!titleElement) {
            return;
        }
        const oldrawtitle = this.rawtitle;
        this.rawtitle = title;
        if (titleElement.textContent !== title) {
            titleElement.innerHTML = title;
        }
        // Nothing to re-sync on the first pass: the server rendered this very name.
        if (oldrawtitle === undefined || oldrawtitle === title) {
            return;
        }

        // The accessible name was built server side from the same unrendered value, so it is that
        // value, not the rendered text, that prefixes it.
        const link = this.getElement(this.selectors.CARDLINK);
        const label = link?.getAttribute('aria-label');
        if (label && label.indexOf(oldrawtitle) === 0) {
            link.setAttribute('aria-label', title + label.slice(oldrawtitle.length));
        }
    }

    /**
     * Validate if the drop data can be dropped over the card.
     *
     * Unlike core's dndsection the card is not a file upload target: it does not render an
     * activity list, so there is nowhere for an uploaded file to appear.
     *
     * @param {Object} dropdata the exported drop data.
     * @returns {boolean} true when the card accepts this payload
     */
    validateDropData(dropdata) {
        if (dropdata?.type === 'cm') {
            // Never let a subsection be dropped inside a delegated section.
            if (this.section?.component && dropdata?.delegatesection === true) {
                return false;
            }
            return true;
        }
        if (dropdata?.type === 'section') {
            // Any section but this one and the one that already follows it.
            return dropdata?.id != this.id && dropdata?.number != this.section.number + 1;
        }
        return false;
    }

    /**
     * Display the card drop zone.
     *
     * A dragged section drops *after* this card, so the indicator is core's trailing edge marker.
     * A dragged activity lands inside the section, so the whole card is outlined instead.
     *
     * @param {Object} dropdata the accepted drop data
     */
    showDropZone(dropdata) {
        if (dropdata.type === 'section') {
            this.element.classList.add(this.classes.DROPDOWN);
            return;
        }
        if (dropdata.type === 'cm') {
            this.element.classList.add(this.classes.DROPZONE);
        }
    }

    /**
     * Hide the card drop zone.
     */
    hideDropZone() {
        this.element.classList.remove(this.classes.DROPZONE);
        this.element.classList.remove(this.classes.DROPUP);
        this.element.classList.remove(this.classes.DROPDOWN);
    }

    /**
     * Drop event handler.
     *
     * @param {Object} dropdata the accepted drop data
     * @param {Event} event the drop event
     */
    drop(dropdata, event) {
        if (dropdata.type === 'cm') {
            const mutation = (event.altKey) ? 'cmDuplicate' : 'cmMove';
            this.reactive.dispatch(mutation, [dropdata.id], this.id);
            return;
        }
        if (dropdata.type === 'section') {
            this.reactive.dispatch('sectionMoveAfter', [dropdata.id], this.id);
        }
    }
}
