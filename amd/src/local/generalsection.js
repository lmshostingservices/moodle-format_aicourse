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
 * The General section (section 0) as an activity-only drop target.
 *
 * Section 0 is rendered above the card grid by format_aicourse/general_section and is pinned
 * there: it is never a card, never draggable and never reorderable. Without a component it was
 * also not a mouse drop target, so an activity could be dragged OUT of General but only put back
 * with the keyboard "Move activity" dialogue.
 *
 * This component closes that dead end. It extends core's dndsection, exactly like
 * format_aicourse/local/sectioncard, but:
 *
 *  - configDragDrop() is called with NO section item, so core registers the element as a drop
 *    zone only and never sets draggable="true" on it (core's DragDrop only makes an element
 *    draggable when its component exposes getDraggableData(), which dndsection does not);
 *  - validateDropData() accepts activities and rejects sections, so a section card dragged over
 *    General is refused and General can never be reordered into the grid.
 *
 * @module     format_aicourse/local/generalsection
 * @class      format_aicourse/local/generalsection
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import DndSection from 'core_courseformat/local/courseeditor/dndsection';

export default class extends DndSection {

    /**
     * Constructor hook.
     */
    create() {
        // Optional component name for debugging.
        this.name = 'aicourse_general_section';
        // Default query selectors. Keys are unique to this component so the parent component's
        // selectors, which are merged in by BaseComponent, cannot shadow them.
        this.selectors = {
            GENERALCMLIST: `[data-for='cmlist']`,
            GENERALCM: `[data-for='cmitem']`,
        };
        // The drag and drop classes (DRAGGING, DROPZONE, DROPUP, DROPDOWN) are merged in by
        // core's dragdrop module in configDragDrop().
        this.classes = {};
        // We need our id to find our own section data.
        this.id = this.element.dataset.id;
    }

    /**
     * Initial state ready method.
     *
     * @param {Object} state the initial state
     */
    stateReady(state) {
        this.configState(state);
        if (this.reactive.isEditing && this.reactive.supportComponents) {
            // No section item is passed: General accepts drops but is never itself draggable.
            this.configDragDrop();
        }
    }

    /**
     * The last activity of the General section, used by core as the "drop at the end" marker.
     *
     * @returns {Element|null} the last activity element, or null when the section is empty
     */
    getLastCm() {
        const cmlist = this.getElement(this.selectors.GENERALCMLIST);
        const cms = cmlist?.querySelectorAll(this.selectors.GENERALCM);
        if (!cms?.length) {
            return null;
        }
        return cms[cms.length - 1];
    }

    /**
     * Validate if the drop data can be dropped over the General section.
     *
     * Activities only. A section is always refused: General is pinned first and moving another
     * section "after" it is already offered by every card in the grid.
     *
     * @param {Object} dropdata the exported drop data
     * @returns {boolean} true when General accepts this payload
     */
    validateDropData(dropdata) {
        if (dropdata?.type !== 'cm') {
            return false;
        }
        // Never let a subsection be dropped inside a delegated section.
        if (this.section?.component && dropdata?.delegatesection === true) {
            return false;
        }
        // Dropping an activity that is already the last one in General is a no-op.
        return dropdata.id != this.section?.cmlist?.[this.section.cmlist.length - 1];
    }

    /**
     * Display the drop zone.
     *
     * The activity lands at the end of General, so core's trailing edge marker is put on the last
     * activity and the whole block is outlined.
     *
     * @param {Object} dropdata the accepted drop data
     */
    showDropZone(dropdata) {
        if (dropdata.type !== 'cm') {
            return;
        }
        this.getLastCm()?.classList.add(this.classes.DROPDOWN);
        this.element.classList.add(this.classes.DROPZONE);
    }

    /**
     * Hide the drop zone.
     */
    hideDropZone() {
        this.getLastCm()?.classList.remove(this.classes.DROPDOWN);
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
        if (dropdata.type !== 'cm') {
            return;
        }
        const mutation = (event.altKey) ? 'cmDuplicate' : 'cmMove';
        this.reactive.dispatch(mutation, [dropdata.id], this.id);
    }
}
