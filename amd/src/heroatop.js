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
// ACF-FIX-2.1.158: marks a banner the measuring half has already been attached to, so a page that
// both lifts AND announces placement does not run it twice.
const ATTACHEDATTR = 'data-aicourse-hero-attached';

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

/**
 * The column the banner has to line its edges up with, on whichever page type this is.
 *
 * ACF-FIX-2.1.170. This used to be the single selector `.format-aicourse-container`, and that
 * element is emitted by format.php -- the course format's own renderer. format.php runs on course
 * and section pages. It does NOT run on an activity page, which is served by mod/<mod>/view.php,
 * so on every activity page this lookup returned null.
 *
 * Two things followed from that null, and both are faults that were reported from live pages:
 *
 *   1. measure() returned on its first line, so the banner never received
 *      --acf-hero-align-start/end and never lined up with the content beneath it.
 *   2. More seriously, the ResizeObserver below observed nothing but the banner's own parent, and
 *      even when it did fire, measure() bailed at the same line. The course index drawer opening
 *      or closing changes the content column's width without changing the window's, so that
 *      observer is the ONLY thing that re-measures on a drawer toggle. With it inert, the banner
 *      kept whatever width it had at load: close the course index on an activity page and the
 *      banner did not expand to fill the space. That is the "still not going full width" report,
 *      and it was never a CSS problem.
 *
 * The fallbacks are the containers Moodle actually uses for the main column on an activity page,
 * most specific first. #region-main is Boost's; [role="main"] and <main> are the standards-based
 * ones a custom theme is most likely to keep; #page-content is the last resort.
 *
 * @returns {HTMLElement|null} The content column, or null if nothing recognisable is present.
 */
const contentColumn = () => document.querySelector('.format-aicourse-container')
    || document.getElementById('region-main')
    || document.querySelector('[role="main"]')
    || document.querySelector('main')
    || document.querySelector('#page-content');

const align = (hero) => {
    const measure = () => {
        const content = contentColumn();
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
        // ACF-FIX-2.1.170: same resolver as measure() uses. Observing an element that does not
        // exist on this page type is how the drawer toggle stopped re-measuring the banner.
        const content = contentColumn();
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
        const page = document.querySelector('#page.drawers') || document.querySelector('#page');
        // An activity page draws a different hero element from a course page, so both are looked
        // for. Querying only the course one meant this returned early on every activity page --
        // measured as a 19px gap surviving there while the course page corrected to 0.
        const hero = document.querySelector('.aicourse-hero-banner')
            || document.querySelector('.aicourse-activity-hero')
            || document.querySelector('.aicourse-activity-hero-banner')
            || document.querySelector('.aicourse-hero-sticky-wrap');
        if (!page || !hero) {
            return;
        }

        // The lowest edge of whatever fixed or sticky band is genuinely at the top of the page.
        let headerBottom = 0;
        document.querySelectorAll('nav, header, .navbar, #header, .header-main').forEach((el) => {
            const style = window.getComputedStyle(el);
            if (style.position !== 'fixed' && style.position !== 'sticky') {
                return;
            }
            if (style.display === 'none' || style.visibility === 'hidden') {
                return;
            }
            const box = el.getBoundingClientRect();
            if (box.height > 0 && box.top <= 4 && box.bottom > headerBottom) {
                headerBottom = box.bottom;
            }
        });
        if (headerBottom <= 0) {
            return;
        }

        // ACF-FIX-2.1.119: correct by the MEASURED difference, do not assume where the offset
        // lives.
        //
        // The earlier version set this margin to the header's height, on the assumption that the
        // margin was what cleared the header. On theme_academi it is not: reported from a live
        // page, the header ends at 61px, #page carries only 20px of margin, and the banner lands
        // at 80px -- so the clearance comes from somewhere else and that 20px is an extra on top
        // of it. Setting the margin to 61 would have pushed the banner further down, not less.
        //
        // Taking the difference between where the banner is and where it should be works whatever
        // is producing the offset, on any theme, without needing to know which element owns it.
        // ACF-FIX-2.1.144: the sticky banner needs the same offset, so it pins directly beneath the
    // header rather than under it or below a gap. Published as a variable so the CSS can use the
    // measured value rather than a number that would be wrong on any theme but this one.
    document.documentElement.style.setProperty(
        '--acf-hero-sticky-top', Math.round(headerBottom) + 'px'
    );

    const current = parseFloat(window.getComputedStyle(page).marginBlockStart) || 0;
        const gap = Math.round(hero.getBoundingClientRect().top - headerBottom);
        if (gap === 0) {
            return;
        }
        // Never negative: pulling the banner above the header would slide it underneath.
        const corrected = Math.max(0, Math.round(current - gap));
        page.style.marginBlockStart = corrected + 'px';
    };

    fitHeader();

    // Run again once the page has settled. The first pass happens as soon as the module loads,
    // which can be before web fonts land or before a theme's own script finishes adjusting its
    // header -- either of which changes the header's height after we have measured it, leaving a
    // strip of the old reservation behind. Re-measuring costs nothing and removes the race.
    if (document.readyState !== 'complete') {
        window.addEventListener('load', fitHeader, {once: true});
    }
    const refit = () => {
        fitHeader();
        fitFullWidth();
    };
    window.setTimeout(refit, 250);
    window.setTimeout(refit, 1000);

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
        fitFullWidth();
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
// ACF-FIX-2.1.127: pull the banner out to the page edges when its container is narrower.
//
// Reported from a live activity page: the wrap measured 342-1381 with no padding, no margin and
// no max-width of its own -- so nothing was insetting it. Its CONTAINER was simply narrower
// than the page. On a stock Moodle #region-main and #page are the same width, which is why this
// never appeared in testing; a theme or an activity plugin can constrain the main region and
// then the banner inherits that width.
//
// Measuring the difference between the wrap's box and the page's box gives the exact pull,
// whatever is causing the difference and whatever the drawer is doing.
//
// An earlier attempt at this made things worse -- the wrap had `inline-size: auto` at the time,
// so it shrank to its content and the pull chased a moving edge. The width is stated at 100%
// now, and the measurement is taken with the previous correction cleared so runs cannot
// compound.
const fitFullWidth = () => {
    const wrap = document.querySelector('.aicourse-hero-sticky-wrap');
    const page = document.querySelector('#page');
    if (!wrap || !page) {
        return;
    }
    // Zero the margins before measuring, not merely clear the inline ones. The stylesheet also
    // sets a negative margin here, so removing only the inline value left that in place and the
    // measurement was taken from an already-pulled position -- the correction then REPLACED the
    // stylesheet's pull instead of completing it, and the banner came up exactly that much short.
    wrap.style.setProperty('margin-left', '0px', 'important');
    wrap.style.setProperty('margin-right', '0px', 'important');
    const box = wrap.getBoundingClientRect();
    const target = page.getBoundingClientRect();
    const left = Math.round(box.left - target.left);
    const right = Math.round(target.right - box.right);
    // A couple of pixels of slack: sub-pixel geometry reports a 1px difference on boxes that
    // already line up, and correcting that would set a margin on every page for nothing.
    if (left > 2) {
        wrap.style.setProperty('margin-left', '-' + left + 'px', 'important');
    }
    if (right > 2) {
        wrap.style.setProperty('margin-right', '-' + right + 'px', 'important');
    }
    // A box that already lines up keeps its zeroed margins rather than the stylesheet's, so the
    // two cannot fight on a page that needs no correction at all.
};


/**
 * Pull the banner flush to the top of its container on an activity page.
 *
 * ACF-FIX-2.1.158. On a course page lift() moves the banner to be a direct child of #page, above
 * all the page furniture, so nothing sits between the site header and the banner. An activity page
 * keeps the banner inside #region-main -- and #region-main, #page-content and the theme's own
 * wrappers each contribute block padding above it. The result is a strip of page background between
 * the header and the banner that does not exist on any other page of the same course.
 *
 * The inline axis already had this treatment (styles.css, "An ACTIVITY page keeps the banner inside
 * #region-main"): a negative margin equal to the padding the banner sits inside, stated relative to
 * its own surroundings rather than to a named ancestor, so it works wherever it is nested. This is
 * the same correction on the block axis, measured rather than assumed because the value is the sum
 * of several themes' paddings and no constant would survive a theme change.
 *
 * Capped, and only ever a pull upward. A measurement that comes back large means the banner is not
 * where this code thinks it is, and in that case doing nothing is correct.
 *
 * @param {Element} hero The banner wrapper.
 * @returns {void}
 */
const fitActivityTop = (hero) => {
    const region = hero.closest('#region-main, #region-main-box, #page-content');
    if (!region) {
        // A lifted banner is already a child of #page; there is nothing above it to cancel.
        return;
    }

    // Zeroed before measuring, so a second run measures the true gap rather than the result of the
    // first one -- otherwise the correction compounds on every resize.
    hero.style.marginBlockStart = '0px';
    void hero.offsetWidth;

    // ACF-FIX-2.1.163: measured against #page, not against #region-main.
    //
    // 2.1.158 measured the gap between the banner and its nearest content wrapper. That found
    // nothing, because the space is not INSIDE that wrapper -- it is above it. #page-content, the
    // theme's own header region and #region-main's margin all sit between the site header and the
    // banner, and a measurement taken from the bottom of that stack reports zero while a visible
    // strip of page background sits above it.
    //
    // #page is the box the drawer is flush with -- the sidebar starts at its top edge with no gap,
    // which is exactly the alignment the banner is supposed to share. So that is what it is
    // measured against, and the same element the inline correction uses, so the two axes cannot
    // disagree about where the page begins.
    const page = document.getElementById('page');
    const anchor = page || region;
    const astyles = window.getComputedStyle(anchor);
    const apad = parseFloat(astyles.paddingBlockStart || astyles.paddingTop) || 0;
    const gap = Math.round(
        hero.getBoundingClientRect().top - (anchor.getBoundingClientRect().top + apad)
    );

    // Only ever a pull upward, and only a plausible one: a large number means the banner is not
    // where this code thinks it is, and doing nothing is then the right answer.
    if (gap > 0 && gap <= 160) {
        hero.style.marginBlockStart = (-gap) + 'px';
    }
};

/**
 * Pull the banner out to the full width of the page column on an activity page.
 *
 * ACF-FIX-2.1.161. On a course page the banner is lifted to be a direct child of #page, so it spans
 * whatever #page spans -- the full window with the drawer closed, the area beside it when open. An
 * activity page keeps the banner inside #region-main, and #region-main is centred with a max-width
 * by the theme. So closing the course index widened the page and the banner stayed where it was,
 * marooned in the middle with a margin either side that nothing else on the page had.
 *
 * The correction is measured against #page rather than the window, and that choice is the whole
 * trick: #page already carries the drawer's offset as a margin, so aligning to its content box
 * gives the right answer in BOTH states without either being special-cased. Drawer open, the banner
 * starts where the drawer ends. Drawer closed, it spans the window.
 *
 * Re-measured on resize and whenever the drawer's state changes, because both move the edge this is
 * measured against.
 *
 * @param {Element} hero The banner wrapper.
 * @returns {void}
 */
const fitActivityWidth = (hero) => {
    const region = hero.closest('#region-main, #region-main-box, #page-content');
    if (!region) {
        // Already lifted; #page IS its parent and there is nothing to pull against.
        return;
    }
    const page = document.getElementById('page');
    if (!page) {
        return;
    }

    // Zeroed before measuring so a second run measures the true offset rather than compounding on
    // the result of the first -- the same reset align() takes for the same reason.
    hero.style.marginInlineStart = '0px';
    hero.style.marginInlineEnd = '0px';
    void hero.offsetWidth;

    // ACF-FIX-2.1.174: the page's horizontal overflow with this function's margins OFF. The check
    // at the end of this function compares against it, so a page that already scrolled sideways on
    // its own is not blamed on the banner. Read after the reset above, which is why it is here and
    // not at the top of the function.
    const overflowbefore = document.documentElement.scrollWidth;

    // ACF-FIX-2.1.165: the edges are the WINDOW and the DRAWER, stated directly.
    //
    // Measuring against #page was the wrong anchor twice over. On one theme #page is 100% wide and
    // its right edge sits beyond the viewport, so aligning to it pulled the banner off-screen and
    // gave the page a horizontal scrollbar. On another #page is itself centred with a max-width, so
    // aligning to it left the banner inset with the drawer closed -- the "not full width" report.
    //
    // "Full width beside the course index" is the actual requirement, so it is what is measured: the
    // start edge is the drawer's right edge when the drawer is on screen, and zero when it is not;
    // the end edge is the window. Neither depends on a theme's choice of wrapper.
    const drawer = document.querySelector('#theme_boost-drawers-courseindex');
    let left = 0;
    if (drawer) {
        const d = drawer.getBoundingClientRect();
        // Only when it is actually occupying space: a closed drawer is translated off-screen and
        // its right edge is then at or left of zero.
        if (d.width > 0 && d.right > 0 && window.getComputedStyle(drawer).display !== 'none') {
            left = d.right;
        }
    }
    const right = document.documentElement.clientWidth;
    const h = hero.getBoundingClientRect();

    const start = Math.round(h.left - left);
    const end = Math.round(right - h.right);

    // Only ever a pull outward, and only a plausible one. A large number means the banner is not
    // where this code thinks it is, and doing nothing is then the correct answer.
    if (start > 0 && start <= 600) {
        hero.style.marginInlineStart = (-start) + 'px';
    }
    if (end > 0 && end <= 600) {
        hero.style.marginInlineEnd = (-end) + 'px';
    }

    // ACF-FIX-2.1.168: verify, and back off if the result is wrong.
    //
    // Everything above is measured, and a measurement taken while the drawer is still sliding is a
    // measurement of a moving edge. Opening the course index in teacher view produced exactly that:
    // the banner was pulled against a drawer that was only half way out, ended up wider than the
    // window, and gave the page a horizontal scrollbar it never recovered from.
    //
    // Rather than trust the arithmetic, the outcome is checked. If the banner now sticks out past
    // either edge, the offending margin is removed — a banner that is merely inset is a cosmetic
    // problem; a page that scrolls sideways is a broken one.
    const after = hero.getBoundingClientRect();
    if (after.left < left - 1) {
        hero.style.marginInlineStart = '0px';
    }
    if (after.right > right + 1) {
        hero.style.marginInlineEnd = '0px';
    }

    // ACF-FIX-2.1.174: and then check the only thing that actually matters.
    //
    // The two tests above check the BANNER's own box against the edges this function measured. That
    // is not the same question as "does the page now scroll sideways", and the difference is where
    // the horizontal scrollbar kept coming from: with the course index open the drawer is an
    // overlay and the theme offsets #page to sit beside it, so a banner whose right edge lands
    // exactly on documentElement.clientWidth passes both tests above while the document as a whole
    // is 342px wider than the window. The banner was measured, approved, and the page scrolled
    // anyway -- reported as "the course index opening is pushing the whole page to the right".
    //
    // scrollWidth against clientWidth is the direct test for that, so it is the one used. It is
    // taken as a BEFORE/AFTER comparison rather than an absolute: a page that already scrolled
    // sideways for some other reason must not make this function permanently give up, or the
    // banner would be inset on every such theme for a reason that has nothing to do with it.
    //
    // Reverting to zero leaves the banner sitting inside the content column, which is a cosmetic
    // shortfall. A page that pans sideways is a broken one. When the two cannot both be had, this
    // takes the cosmetic loss every time.
    const de = document.documentElement;
    if (de.scrollWidth > de.clientWidth + 1 && de.scrollWidth > overflowbefore + 1) {
        hero.style.marginInlineStart = '0px';
        hero.style.marginInlineEnd = '0px';
    }
};

/**
 * Run the measuring half of this module against a banner that is already in place.
 *
 * ACF-FIX-2.1.158. init() calls lift(), and lift() returns at its first line when the banner is not
 * in the DOM yet. On an activity page it never is: heroinject places the banner from its <template>
 * and the hook queues heroinject AFTER heroatop. So lift() returned on a race -- not on a condition
 * -- and everything it gates went with it: align(), and inside align() the header fit that publishes
 * --acf-hero-sticky-top, the ResizeObserver on the content column, the drawer MutationObserver and
 * the re-measure on drawer clicks.
 *
 * With --acf-hero-sticky-top never published it falls back to 0, so a banner whose minimum height is
 * --acf-topblock stuck to the very top of the viewport, over the top-left of the content column
 * where Boost puts the course-index toggle. That is the "sticky scroll is terrible on activity
 * pages" report and the "the open button is hidden" report: one cause, two symptoms.
 *
 * heroinject now announces placement and this runs then. align() only measures and sets custom
 * properties -- it does not move anything -- so it is safe to run on a banner that was never lifted.
 *
 * @returns {void}
 */
export const attach = () => {
    const hero = document.querySelector('.aicourse-hero-sticky-wrap');
    if (!hero || hero.hasAttribute(ATTACHEDATTR)) {
        return;
    }
    hero.setAttribute(ATTACHEDATTR, '1');

    const refit = () => {
        fitActivityTop(hero);
        fitActivityWidth(hero);
    };

    refit();
    align(hero);
    fitFullWidth();

    window.addEventListener('resize', refit);

    // The drawer's open state is the other thing that moves #page's edge. Core toggles classes on
    // #page and on <body> when it opens and closes and then animates the width over ~250ms.
    //
    // ACF-FIX-2.1.168: the immediate re-measure is gone. It ran while the drawer was still sliding,
    // against an edge that was moving, and wrote a wrong correction which the later pass then had to
    // undo -- visible as the banner jumping and, in teacher view, staying wrong. The measurement now
    // waits for the drawer to stop: `transitionend` when the browser reports one, and a timer as the
    // backstop for a theme that animates with something other than a CSS transition.
    let settleTimer = null;
    const settle = () => {
        window.clearTimeout(settleTimer);
        settleTimer = window.setTimeout(refit, 360);
    };
    if (typeof window.MutationObserver === 'function') {
        const page = document.getElementById('page');
        if (page) {
            new window.MutationObserver(settle)
                .observe(page, {attributes: true, attributeFilter: ['class']});
        }
        new window.MutationObserver(settle)
            .observe(document.body, {attributes: true, attributeFilter: ['class']});
    }
    // A click on any drawer toggle, in case a theme moves the state somewhere unobserved.
    document.addEventListener('click', (e) => {
        if (e.target && e.target.closest && e.target.closest('[data-toggler="drawers"]')) {
            settle();
        }
    }, true);

    // The authoritative signal: the drawer itself telling us it has finished moving.
    const drawernode = document.querySelector('#theme_boost-drawers-courseindex');
    if (drawernode) {
        drawernode.addEventListener('transitionend', (e) => {
            if (e.propertyName === 'transform' || e.propertyName === 'width'
                || e.propertyName === 'left' || e.propertyName === 'margin-left') {
                refit();
            }
        });
    }
};

/**
 * Draw the activity banner's completion ring, rather than having it simply be there.
 *
 * ACF-FIX-2.1.182. "Check the progress ring animates on each hero banner" -- on the activity banner
 * it did not, and could not: that banner has no percentage ring. hero.mustache draws
 * `.aicourse-progress-ring-*`, a real 0-100 arc that courseformat.js counts up; activity_hero.mustache
 * draws `.aicourse-completion-ring`, which is a binary state -- a full circle with a check, or an
 * empty circle. There is no number to count, so nothing was wired, and nothing moved.
 *
 * The equivalent motion for a binary state is the ring DRAWING ITSELF: the circle sweeps from empty
 * to closed over the same duration and the same easing the hero ring uses, and the check strokes in
 * behind it. The learner sees the same gesture on both banners, which is what was being asked for --
 * not the same arithmetic, which does not exist here.
 *
 * Everything is measured from the element rather than assumed: the circle's own `r` gives the
 * circumference, and the check's own `getTotalLength()` gives its dash length, so a future change to
 * the template's geometry cannot silently leave a half-drawn ring behind.
 *
 * @returns {void}
 */
const drawCompletionRing = () => {
    const rings = document.querySelectorAll('.aicourse-completion-ring:not([data-acf-drawn])');
    if (!rings.length) {
        return;
    }
    const reduced = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    rings.forEach((svg) => {
        svg.setAttribute('data-acf-drawn', '1');
        // The template renders the finished state, which is correct with no JavaScript at all --
        // so reduced motion needs no work here, only restraint.
        if (reduced || !window.requestAnimationFrame) {
            return;
        }

        const circle = svg.querySelector('circle');
        const check = svg.querySelector('path');
        const DUR = 1800;

        if (circle) {
            const r = parseFloat(circle.getAttribute('r')) || 20;
            const c = 2 * Math.PI * r;
            circle.style.transition = 'none';
            circle.style.strokeDasharray = c + ' ' + c;
            circle.style.strokeDashoffset = c + 'px';
            // 12 o'clock, clockwise -- the same orientation as every other ring in this plugin.
            circle.style.transformOrigin = '50% 50%';
            circle.style.transform = 'rotate(-90deg)';
        }
        if (check) {
            const len = typeof check.getTotalLength === 'function' ? check.getTotalLength() : 30;
            check.style.transition = 'none';
            check.style.strokeDasharray = len + ' ' + len;
            check.style.strokeDashoffset = len + 'px';
        }

        // One forced layout for both, before either transition is put back: setting and clearing in
        // the same frame without this is coalesced by the browser and nothing moves.
        void svg.getBoundingClientRect();

        window.requestAnimationFrame(() => {
            if (circle) {
                circle.style.transition = 'stroke-dashoffset ' + (DUR / 1000)
                    + 's cubic-bezier(0.22, 1, 0.36, 1)';
                circle.style.strokeDashoffset = '0px';
            }
            if (check) {
                // Held back until the ring is most of the way round, so the check reads as the
                // conclusion of the sweep rather than as a second thing happening at the same time.
                check.style.transition = 'stroke-dashoffset 0.45s ease-out ' + (DUR * 0.6 / 1000) + 's';
                check.style.strokeDashoffset = '0px';
            }
        });
    });
};

export const init = () => {
    // ACF-FIX-2.1.127: the pull runs whether or not the banner is lifted.
    //
    // lift() moves the banner to be a direct child of #page, and returns early when it cannot or
    // need not -- which is every activity page, where the banner is already where it belongs.
    // Everything nested inside lift() therefore never ran there, including the width correction.
    // On a stock Moodle that went unnoticed because #region-main and #page are the same width;
    // it only shows on a site where the main region is narrower.
    const start = () => {
        lift();
        drawCompletionRing();
        // ACF-FIX-2.1.158: if the banner is already on the page -- a course page, or an activity
        // page whose modules happened to load in the other order -- attach now rather than waiting
        // for an announcement that has already been made.
        attach();
        fitFullWidth();
        // Again once the page has settled: a container's width can change after first paint.
        window.setTimeout(fitFullWidth, 300);
        window.setTimeout(fitFullWidth, 1200);
        window.addEventListener('resize', fitFullWidth);
    };

    // ACF-FIX-2.1.158: heroinject announces the banner once it has been moved out of its template.
    // Not {once: true} -- a page can replace the banner (core re-renders parts of an activity page
    // through the reactive state manager), and the second placement needs the same treatment.
    // ACF-FIX-2.1.174: lift() FIRST, then attach. This one missing call is why the activity page
    // never matched the course and section pages.
    //
    // Compare the two, which is exactly the right comparison to make:
    //
    //   course / section : format.php renders the banner into the page, lift() moves it to be a
    //                      DIRECT CHILD of #page. When the course index opens, the theme offsets
    //                      #page to sit beside the drawer and the banner moves with its parent.
    //                      Nothing has to be measured or corrected. It is simply right.
    //
    //   activity         : heroinject places the banner from its <template> AFTER this module has
    //                      already run, because the hook queues heroatop early and heroinject last.
    //                      lift() had therefore already returned at its first line -- no banner in
    //                      the DOM yet -- and the only thing listening for the placement was
    //                      attach(), which measures but never moves. So the banner stayed inside
    //                      #region-main, which is confirmed by a live probe reporting
    //                      heroParent: "region-main".
    //
    // Everything that has gone wrong with the activity banner follows from that one difference. A
    // banner stuck inside the content column cannot be full width, so fitActivityWidth() fakes it
    // with negative margins; those margins are measured against the drawer, so every open and close
    // re-runs the measurement; and a measurement taken against a moving overlay is what produced
    // the banner not expanding on close, the nav icons disappearing behind the open drawer, and the
    // horizontal scrollbar.
    //
    // Lifting the activity banner does not paper over those symptoms, it removes the condition that
    // creates them: the banner becomes a child of #page like the other two page types, and inherits
    // the behaviour that already works there. fitActivityWidth() then finds no #region-main ancestor
    // and returns at its own first line, which is the correct outcome -- there is nothing left for
    // it to correct.
    document.addEventListener('format_aicourse:heroplaced', () => {
        lift();
        attach();
        drawCompletionRing();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, {once: true});
        return;
    }
    start();
};
