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
 * Places the AI Course hero banner on pages that format.php does not render.
 *
 * Activity pages, section pages and the course reports are rendered by core, not by this
 * format's format.php, so there is no server-side insertion point at the top of the main
 * region. The before_footer_html_generation hook renders the hero into an inert <template>
 * at the end of the document, and this module moves it into place.
 *
 * ACF-FIX-2.1: this replaces a ~50 line inline <script> that string-concatenated the hero
 * HTML into the page. Inline script is blocked by any Content-Security-Policy that forbids
 * it. Passing the markup as a js_call_amd() argument instead was also wrong: Moodle's
 * outputrequirementslib warns when an argument string exceeds 1024 characters, which a
 * rendered hero always does, so every section and activity page logged a debugging notice.
 * A <template> carries the markup with no script, no size limit, and no rendering cost --
 * its contents are parsed but inert until adopted.
 *
 * @module     format_aicourse/heroinject
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {string} Id of the template element the footer hook writes the hero into. */
const SOURCEID = 'aicourse-hero-source';

/** @type {string} Marks the hero once placed, so a second call cannot duplicate it. */
const PLACEDATTR = 'data-aicourse-hero';

/**
 * Find the element the hero should be inserted into.
 *
 * @returns {{target: (HTMLElement|null), isregionmain: boolean}} The insertion target.
 */
const findTarget = () => {
    const regionmain = document.getElementById('region-main');
    if (regionmain) {
        return {target: regionmain, isregionmain: true};
    }
    const fallback = document.getElementById('page-header')
        || document.querySelector('main')
        || document.querySelector('#page-content');
    return {target: fallback, isregionmain: false};
};

/**
 * Move the hero out of its template and into the top of the main region.
 *
 * @returns {void}
 */
const placeHero = () => {
    const source = document.getElementById(SOURCEID);
    if (!source || !source.content) {
        return;
    }

    // A hero is already on the page (format.php renders its own on course/view.php). Drop the
    // spare rather than leaving an orphan <template> in the DOM.
    if (document.querySelector('.aicourse-hero-sticky-wrap') || document.querySelector(`[${PLACEDATTR}]`)) {
        source.remove();
        return;
    }

    const children = Array.from(source.content.children);
    if (!children.length) {
        source.remove();
        return;
    }

    const {target, isregionmain} = findTarget();
    if (!target) {
        return;
    }

    // Walk backwards inserting at the first child, so the nodes end up in source order.
    for (let i = children.length - 1; i >= 0; i--) {
        const child = children[i];
        if (child.classList && child.classList.contains('aicourse-hero-sticky-wrap')) {
            child.setAttribute(PLACEDATTR, '1');
        }
        if (isregionmain) {
            target.insertBefore(child, target.firstChild);
        } else if (target.parentNode) {
            target.parentNode.insertBefore(child, target.nextSibling);
        }
    }

    source.remove();

    // ACF-FIX-2.1.158: announce it. heroatop's measuring half runs on this rather than on module
    // load, because on an activity page the banner is still inside its <template> when heroatop
    // initialises -- the hook queues heroatop early and heroinject last. heroatop's lift() bailed on
    // that race and took --acf-hero-sticky-top, the header fit and three observers with it, which is
    // why the sticky banner and the course-index toggle both misbehaved on activity pages only.
    //
    // A DOM event rather than a direct call: this module is legacy AMD and heroatop is an ES module,
    // and an event costs nothing while a cross-format import invites the double-wrapping problem the
    // build has been bitten by before.
    try {
        document.dispatchEvent(new CustomEvent('format_aicourse:heroplaced'));
    } catch (e) {
        // CustomEvent is available everywhere this plugin supports; if it somehow is not, the page
        // keeps the layout it has rather than throwing during page setup.
        window.console && window.console.debug && window.console.debug(e);
    }
};

/**
 * Initialise the module.
 *
 * @returns {void}
 */
export const init = () => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', placeHero, {once: true});
        return;
    }
    placeHero();
};
