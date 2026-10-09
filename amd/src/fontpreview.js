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
 * Live preview under the site font settings.
 *
 * Each preview names its select by id and carries every font's CSS stack in data-stacks. The
 * heading preview follows the body font while it is set to "Same as the font above".
 *
 * @module     format_aicourse/fontpreview
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The CSS stack for a select's current value, or '' for the theme's font.
 *
 * @param {HTMLSelectElement} select The font select.
 * @param {Object} stacks Key => CSS stack.
 * @returns {String}
 */
const stackFor = (select, stacks) => (select && stacks[select.value]) || '';

/**
 * Wire every preview on the page.
 *
 * @returns {void}
 */
export const init = () => {
    const previews = Array.from(document.querySelectorAll('[data-aicourse-fontpreview]'));
    if (!previews.length) {
        return;
    }
    const body = document.getElementById('id_s_format_aicourse_font');
    const update = () => {
        previews.forEach((preview) => {
            let stacks = {};
            try {
                stacks = JSON.parse(preview.getAttribute('data-stacks') || '{}');
            } catch (error) {
                return;
            }
            const select = document.getElementById(preview.getAttribute('data-aicourse-fontpreview'));
            let stack = stackFor(select, stacks);
            if (!stack && select && select !== body) {
                // "Same as the font above".
                stack = stackFor(body, stacks);
            }
            preview.style.fontFamily = stack;
        });
    };
    previews.forEach((preview) => {
        const select = document.getElementById(preview.getAttribute('data-aicourse-fontpreview'));
        if (select) {
            select.addEventListener('change', update);
        }
    });
    if (body) {
        body.addEventListener('change', update);
    }
    update();
};
