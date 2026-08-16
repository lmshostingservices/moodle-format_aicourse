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
 * One draggable activity item inside the AI Course card view.
 *
 * The component binds to any [data-for="cmitem"] element in the card region, which covers both the
 * plugin's own activity cards and the core activity rows the General section renders while a
 * teacher is editing. It extends core's dndcmitem so the drag payload, the "dropping on the next
 * activity is a no-op" rule and the drop indicators all come from core.
 *
 * @module     format_aicourse/local/activitycard
 * @class      format_aicourse/local/activitycard
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import DndCmItem from 'core_courseformat/local/courseeditor/dndcmitem';

export default class extends DndCmItem {

    /**
     * Constructor hook.
     */
    create() {
        // Optional component name for debugging.
        this.name = 'aicourse_activity_card';
        // Default query selectors. Keys are unique to this component so the parent component's
        // selectors, which are merged in by BaseComponent, cannot shadow them.
        this.selectors = {
            CMDRAGICON: `.editing_move`,
            CMNAME: `.aicourse-activity-card-name, [data-cm-name-for]`,
            CMBULKSELECT: `[data-for='cmBulkSelect']`,
            CMBULKCHECKBOX: `[data-bulkcheckbox]`,
            CMINPLACEEDITABLE: `[data-inplaceeditablelink]`,
        };
        // The drag and drop classes are merged in by core's dragdrop module in configDragDrop().
        this.classes = {
            LOCKED: 'editinprogress',
            // Bootstrap's display utility and core's bulk selection marker, both used by
            // core_courseformat/local/content/section/cmitem, which this mirrors.
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
        this.configDragDrop(this.id);
        // Core's activity rows keep their grab icon hidden until the row is hovered. The class
        // name only exists once configDragDrop() has merged core's drag and drop classes in.
        if (this.classes.DRAGICON) {
            this.getElement(this.selectors.CMDRAGICON)?.classList.add(this.classes.DRAGICON);
        }
        this._refreshBulk({state});
    }

    /**
     * Component watchers.
     *
     * @returns {Array} of watchers
     */
    getWatchers() {
        return [
            {watch: `cm[${this.id}]:deleted`, handler: this.unregister},
            {watch: `cm[${this.id}]:updated`, handler: this._refreshCm},
            {watch: `bulk:updated`, handler: this._refreshBulk},
        ];
    }

    /**
     * Show, hide and update this activity's bulk selection checkbox.
     *
     * A direct counterpart of core_courseformat/local/content/section/cmitem's own _refreshBulk().
     * While a bulk selection is being made the whole row becomes the selection target and dragging
     * is turned off, exactly as in core's course content.
     *
     * @param {Object} param
     * @param {Object} param.state the full state object
     */
    _refreshBulk({state}) {
        const bulk = state.bulk;
        if (!bulk) {
            return;
        }
        // Dragging elements in bulk is not possible.
        this.setDraggable(!bulk.enabled);
        // Make the whole row the selection target while bulk editing is on.
        if (bulk.enabled) {
            this.element.dataset.action = 'toggleSelectionCm';
            this.element.dataset.preventDefault = 1;
        } else {
            this.element.removeAttribute('data-action');
            this.element.removeAttribute('data-preventDefault');
        }

        this.getElement(this.selectors.CMBULKSELECT)?.classList.toggle(this.classes.HIDE, !bulk.enabled);
        this.getElement(this.selectors.CMINPLACEEDITABLE)?.classList.toggle(this.classes.HIDE, bulk.enabled);

        const selected = this._isSelected(bulk);
        this.element.classList.toggle(this.classes.SELECTED, selected);
        this._setCheckboxValue(selected, !this._isCmBulkEnabled(bulk));
    }

    /**
     * Set the checked and disabled state of this activity's bulk checkbox.
     *
     * @param {Boolean} checked the new checked value
     * @param {Boolean} disabled the new disabled value
     */
    _setCheckboxValue(checked, disabled) {
        const checkbox = this.getElement(this.selectors.CMBULKCHECKBOX);
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
     * Whether an activity can be added to the CURRENT bulk selection.
     *
     * @param {Object} bulk the current state bulk attribute
     * @returns {Boolean} false while sections are being selected instead
     */
    _isCmBulkEnabled(bulk) {
        if (!bulk.enabled) {
            return false;
        }
        return (bulk.selectedType === '' || bulk.selectedType === 'cm');
    }

    /**
     * Whether this activity is part of the current bulk selection.
     *
     * @param {Object} bulk the current state bulk attribute
     * @returns {Boolean} true when this activity is selected
     */
    _isSelected(bulk) {
        if (bulk.selectedType !== 'cm') {
            return false;
        }
        return bulk.selection.includes(this.id);
    }

    /**
     * Update the activity item from the cm state.
     *
     * @param {Object} param
     * @param {Object} param.element the cm state data
     */
    _refreshCm({element}) {
        this.element.classList.toggle(this.classes.DRAGGING, element.dragging ?? false);
        this.element.classList.toggle(this.classes.LOCKED, element.locked ?? false);
        this.locked = element.locked;

        const name = this.getElement(this.selectors.CMNAME);
        if (name && element.name !== undefined && name.textContent !== element.name) {
            name.textContent = element.name;
        }
    }
}
