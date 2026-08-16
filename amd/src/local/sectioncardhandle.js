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
 * The draggable grab handle of an AI Course section card.
 *
 * This is the card equivalent of core_courseformat/local/content/section/header: the card root is
 * the drop zone (see format_aicourse/local/sectioncard) while this small inner control is the
 * element the user actually grabs. It extends core's dndsectionitem so the plugin inherits core's
 * drag payload, autoscroll and "topic zero is never draggable" rule instead of reimplementing them.
 *
 * The same element also carries data-action="moveSection", which core's
 * core_courseformat/local/content/actions dispatcher turns into the keyboard accessible
 * "Move section" dialogue.
 *
 * @module     format_aicourse/local/sectioncardhandle
 * @class      format_aicourse/local/sectioncardhandle
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import DndSectionItem from 'core_courseformat/local/courseeditor/dndsectionitem';

export default class extends DndSectionItem {

    /**
     * Constructor hook.
     *
     * @param {Object} descriptor the component descriptor, built by the parent section card
     */
    create(descriptor) {
        // Optional component name for debugging.
        this.name = 'aicourse_section_card_handle';
        // Main info comes from the parent card, exactly as core's section header does.
        this.id = descriptor.id;
        this.section = descriptor.section;
        this.course = descriptor.course;
        this.fullregion = descriptor.fullregion;
    }

    /**
     * Initial state ready method.
     *
     * @param {Object} state the initial state
     */
    stateReady(state) {
        this.configDragDrop(this.id, state, this.fullregion);
    }

    /**
     * The handle is never a drop zone; the whole card is.
     *
     * Returning false (rather than removing the method) keeps core's dragdrop listeners quiet:
     * because nothing is accepted here the events are neither consumed nor stopped, so they bubble
     * up to the card root, which is the element that shows the drop indicator.
     *
     * @returns {boolean} always false
     */
    validateDropData() {
        return false;
    }
}
