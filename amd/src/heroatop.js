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
 * Lifts the hero banner to the very top of the page.
 *
 * WHY THIS IS JAVASCRIPT AND NOT CSS. format.php is included by core AFTER
 * $OUTPUT->header() has already written the navbar, #page-header and the secondary
 * navigation, so a course format has no server-side insertion point above them. CSS
 * cannot help either: the banner and the nav are in different, non-sibling subtrees, so
 * there is no `order` or `flex-direction` that reaches across them. Relocating the node
 * is the only way, and it is what the format already does on section and activity pages
 * (see heroinject.js).
 *
 * The move is deliberately conservative:
 *   - it runs once, on DOM ready, and marks the node so a second call is a no-op;
 *   - it never runs in edit mode (a teacher needs the page header controls in place);
 *   - if the expected containers are missing -- a theme that does not use #page, say --
 *     it does nothing at all and the banner stays where the server put it.
 *
 * @module     format_aicourse/heroatop
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {string} Marks the banner once lifted, so the move happens exactly once. */
const DONEATTR = 'data-aicourse-atop';

/**
 * Move the hero banner to the top of the page container.
 *
 * @returns {void}
 */
const lift = () => {
    const hero = document.querySelector('.aicourse-hero-sticky-wrap');
    if (!hero || hero.hasAttribute(DONEATTR)) {
        return;
    }

    // Edit mode keeps the normal page furniture; body classes are written server-side.
    const body = document.body;
    if (body.classList.contains('editing')
        || body.classList.contains('editingon')
        || body.classList.contains('editing-mode')) {
        return;
    }

    const page = document.getElementById('page');
    if (!page) {
        return;
    }

    // Find the landmark to sit above. querySelector searches DESCENDANTS, so the node it
    // returns is usually nested several levels inside #page -- and insertBefore() requires a
    // DIRECT child, which is what threw
    //     NotFoundError: The node before which the new node is to be inserted is not a
    //     child of this node
    // on themes that wrap the header. Walk back up to the ancestor that IS a child of #page.
    let before = page.querySelector('#page-header, .secondary-navigation, #page-content');
    while (before && before.parentElement && before.parentElement !== page) {
        before = before.parentElement;
    }
    if (!before || before.parentElement !== page) {
        before = page.firstElementChild;
    }
    if (!before || before === hero || before.contains(hero)) {
        // A theme where the banner is already inside the node we would insert before: moving it
        // would either be a no-op or would detach it from its own ancestor mid-walk. Leave it.
        if (!before || before === hero) {
            return;
        }
    }
    if (before.parentElement !== page) {
        return;
    }

    hero.setAttribute(DONEATTR, '1');
    page.insertBefore(hero, before);
    align(hero);
};

/**
 * Match the lifted banner's inline edges to the course content below it.
 *
 * ACF-FIX-2.1.37. Moving the banner to be a direct child of #page is what puts it above the
 * page furniture, but it also takes it out of the box the content lives in: the content sits
 * inside #region-main and inherits that column's own padding, while the banner now sits in the
 * page's wider box. The two therefore have different inline edges, and the banner reads as wider
 * than the cards beneath it. Measured on Boost the gap is 23px a side; on other themes it differs,
 * because it is the sum of whatever wrappers that theme puts around its content column.
 *
 * There is no CSS expression for "the left edge of that other element", and the offset is not a
 * constant that could be hard-coded, so it is measured once from the content container and
 * published as a custom property the stylesheet consumes. Re-measured on resize because the
 * theme's column width is itself responsive.
 *
 * @param {HTMLElement} hero The banner wrapper, already moved.
 * @returns {void}
 */
const align = (hero) => {
    const measure = () => {
        const content = document.querySelector('.format-aicourse-container');
        if (!content) {
            return;
        }
        // ACF-FIX-2.1.41: measure against the BANNER's own box, not its parent's.
        //
        // The first version compared the content column to the parent element. That is wrong
        // whenever the banner does not begin at its parent's content edge -- and it usually does
        // not, because the banner carries its own max-inline-size and auto margins and is
        // therefore centred inside a wider parent. The parent-relative offset then included the
        // centring margin that the browser had ALREADY applied, so the padding double-counted it
        // and the banner visibly jumped inward about a second after load, ending up far narrower
        // than the cards instead of level with them.
        //
        // Measuring from the banner's own edges cannot double-count: whatever centring is already
        // in effect is baked into those edges. The offsets are zeroed first so the measurement is
        // taken against the unpadded box rather than the result of the previous run, which would
        // otherwise compound on every resize.
        hero.style.setProperty('--acf-hero-align-start', '0px');
        hero.style.setProperty('--acf-hero-align-end', '0px');
        // Read a layout property to force the reset to apply before measuring.
        void hero.offsetWidth;

        const c = content.getBoundingClientRect();
        const h = hero.getBoundingClientRect();
        const styles = window.getComputedStyle(hero);
        const padStart = parseFloat(styles.paddingInlineStart || styles.paddingLeft) || 0;
        const padEnd = parseFloat(styles.paddingInlineEnd || styles.paddingRight) || 0;

        // Align to where the CARDS sit, which is the container's content box, not its border box.
        // The container carries the same gutter padding the banner does, so comparing border
        // boxes made the two look level when the cards were still a gutter further in.
        const contentstyles = window.getComputedStyle(content);
        const cpadstart = parseFloat(contentstyles.paddingInlineStart || contentstyles.paddingLeft) || 0;
        const cpadend = parseFloat(contentstyles.paddingInlineEnd || contentstyles.paddingRight) || 0;
        const targetstart = c.left + cpadstart;
        const targetend = c.right - cpadend;

        // Clamped at zero so a theme whose content column is WIDER than the banner never pulls
        // the banner outward past its own box.
        const start = Math.max(0, Math.round(targetstart - (h.left + padStart)));
        const end = Math.max(0, Math.round((h.right - padEnd) - targetend));

        // Nothing to correct: leave the properties at zero rather than writing a no-op that
        // triggers another style recalculation.
        if (start === 0 && end === 0) {
            return;
        }
        hero.style.setProperty('--acf-hero-align-start', start + 'px');
        hero.style.setProperty('--acf-hero-align-end', end + 'px');
    };

    // ACF-FIX-2.1.41: measure on the next animation frame rather than synchronously, so the
    // first measurement happens before the browser paints and any correction is not seen as a
    // jump. The old code measured immediately, and on a theme where a correction WAS needed the
    // banner was painted at one width and then visibly resized.
    if (typeof window.requestAnimationFrame === 'function') {
        window.requestAnimationFrame(measure);
    } else {
        measure();
    }

    let pending = null;
    const remeasure = () => {
        window.clearTimeout(pending);
        pending = window.setTimeout(measure, 120);
    };

    // ACF-FIX-2.1.44: watch the content column, not just the window.
    //
    // A single measurement at load is only correct until something changes the content width, and
    // on this format the commonest such change is not a window resize at all: it is the course
    // index drawer opening or closing. That narrows the content column while the window stays
    // exactly the same size, so a resize listener never fires, and the banner keeps padding
    // calculated for the width the column USED to have -- which is why it sat inset from the
    // cards with the drawer open.
    //
    // A ResizeObserver on the column catches every cause at once: the drawer, a block drawer, a
    // late web font, a responsive breakpoint, a theme animating its layout. Debounced, because a
    // drawer transition fires it on every frame.
    if (typeof window.ResizeObserver === 'function') {
        const content = document.querySelector('.format-aicourse-container');
        const observer = new window.ResizeObserver(remeasure);
        if (content) {
            observer.observe(content);
        }
        if (hero.parentElement) {
            observer.observe(hero.parentElement);
        }
    }

    // ACF-FIX-2.1.107: reserve the header's ACTUAL height, not its assumed one.
    //
    // Moodle reserves a fixed offset above the page for the fixed site header. That figure assumes
    // the header the theme normally draws -- so on a theme with two bands, or when the logo band is
    // hidden by this format's own setting, the reservation is taller than the header that is
    // actually on screen and the difference shows as blank space above the banner.
    //
    // Measuring the bottom edge of whatever fixed header is really there removes the guesswork:
    // whatever the theme draws and whatever has been hidden, the banner sits directly beneath it.
    //
    // Only in full-width mode. Everywhere else the banner is meant to sit inside the content column
    // with its normal spacing, and changing the page offset there would move the whole layout.
    const fitHeader = () => {
        if (!document.body.classList.contains('aicourse-hero-fullwidth')) {
            return;
        }
        const page = document.querySelector('#page.drawers') || document.querySelector('#page');
        if (!page) {
            return;
        }
        let lowest = 0;
        document.querySelectorAll('nav, header, .navbar, #header, .header-main').forEach((el) => {
            const style = window.getComputedStyle(el);
            if (style.position !== 'fixed' && style.position !== 'sticky') {
                return;
            }
            if (style.display === 'none' || style.visibility === 'hidden') {
                return;
            }
            const box = el.getBoundingClientRect();
            // Only bands across the top: a fixed element further down the page is something else.
            if (box.height > 0 && box.top <= 4 && box.bottom > lowest) {
                lowest = box.bottom;
            }
        });
        page.style.marginBlockStart = Math.round(lowest) + 'px';
    };

    fitHeader();

    // Run again once the page has settled. The first pass happens as soon as the module loads,
    // which can be before web fonts land or before a theme's own script finishes adjusting its
    // header -- either of which changes the header's height after we have measured it, leaving a
    // strip of the old reservation behind. Re-measuring costs nothing and removes the race.
    if (document.readyState !== 'complete') {
        window.addEventListener('load', fitHeader, {once: true});
    }
    window.setTimeout(fitHeader, 250);
    window.setTimeout(fitHeader, 1000);

    // And whenever the header itself changes size, which a theme can do without a window resize.
    if (typeof window.ResizeObserver === 'function') {
        const header = document.querySelector('nav.navbar, #header, header.navbar');
        if (header) {
            new window.ResizeObserver(fitHeader).observe(header);
        }
    }

    window.addEventListener('resize', () => {
        remeasure();
        fitHeader();
    });

    window.addEventListener('resize', remeasure);

    // ACF-FIX-2.1.90: re-measure when the drawer opens or closes.
    //
    // Closing the course index widens the content column without resizing the window, and the
    // ResizeObserver above only fires if the observed element's own box changes -- which it does
    // not if the theme animates the offset on an ancestor instead. The banner then keeps the
    // inset it calculated while the drawer was open, so the content stays pushed to the right
    // instead of recentring.
    //
    // Moodle toggles `drawer-open-index` on <body>, so watching that class covers every route to
    // opening or closing it: the toggle button, the close button, a keyboard shortcut, or the
    // preference being applied on load. The delay lets the drawer's transition finish before
    // anything is measured, since measuring mid-animation records a width that is about to change.
    if (typeof window.MutationObserver === 'function') {
        let last = document.body.className;
        const watcher = new window.MutationObserver(() => {
            const now = document.body.className;
            if (now === last) {
                return;
            }
            const wasOpen = last.indexOf('drawer-open-index') !== -1;
            const isOpen = now.indexOf('drawer-open-index') !== -1;
            last = now;
            if (wasOpen !== isOpen) {
                window.setTimeout(measure, 420);
            }
        });
        watcher.observe(document.body, {attributes: true, attributeFilter: ['class']});
    }
    // A drawer toggle finishes with a transition; measure again once it has settled, for browsers
    // without ResizeObserver.
    document.addEventListener('click', (e) => {
        if (e.target && typeof e.target.closest === 'function'
                && e.target.closest('[data-toggler="drawers"], .drawertoggle, [data-action="toggle"]')) {
            window.setTimeout(measure, 420);
        }
    }, true);
};

/**
 * Initialise the module.
 *
 * @returns {void}
 */
export const init = () => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', lift, {once: true});
        return;
    }
    lift();
};
