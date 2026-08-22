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
 * Turn core's course index drawer into a course player sidebar.
 *
 * @module     format_aicourse/player
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';

const HEADER_ID = 'aicourse-player-header';

/**
 * Pull a course module id out of an index row.
 *
 * Core marks rows with data-id on some versions and not others, and the id in the link is the one
 * thing that has been stable across every release this format supports. The dataset is tried
 * first because it is cheaper and unambiguous; the href is the fallback.
 *
 * @param {Element} row A .courseindex-item element.
 * @returns {String|null} The cmid, or null when the row is a section rather than an activity.
 */
const cmidOf = (row) => {
    if (row.dataset && row.dataset.id && row.dataset.for === 'cm') {
        return row.dataset.id;
    }
    const link = row.querySelector('a[href*="id="]');
    if (!link) {
        return null;
    }
    // Section links point at course/view.php; only module links carry a cmid.
    if (link.href.indexOf('/mod/') === -1) {
        return null;
    }
    const match = link.href.match(/[?&]id=(\d+)/);
    return match ? match[1] : null;
};

/**
 * Build the header that sits above the section list.
 *
 * @param {Object} config Course data from the server.
 * @param {Object} strings Localised labels.
 * @returns {Element}
 */
const buildHeader = (config, strings) => {
    const header = document.createElement('div');
    header.id = HEADER_ID;
    header.className = 'aicourse-player-header';

    const logo = config.logourl
        ? '<img class="aicourse-player-logo" src="' + config.logourl + '" alt="">'
        : '';

    // The ring is an SVG rather than a CSS conic-gradient so it animates smoothly and reads
    // correctly when a page is printed.
    const pct = config.percent;
    const ring = (pct === null || pct === undefined) ? '' :
        '<div class="aicourse-player-ring" role="img" aria-label="' + strings.progress.replace('{$a}', pct) + '">' +
            '<svg viewBox="0 0 44 44" aria-hidden="true">' +
                '<circle class="aicourse-player-ring-bg" cx="22" cy="22" r="19"></circle>' +
                '<circle class="aicourse-player-ring-fill" cx="22" cy="22" r="19"' +
                    ' style="stroke-dasharray: ' + (pct * 1.194) + ' 200"></circle>' +
            '</svg>' +
            '<span class="aicourse-player-ring-text" aria-hidden="true">' + pct + '%</span>' +
        '</div>';

    const meta = [];
    if (config.totaltime) {
        meta.push('<span class="aicourse-player-meta-item">' + config.totaltime + '</span>');
    }

    header.innerHTML =
        // ACF-FIX-2.1.165: one link, not three.
        //
        // Home, Dashboard and My courses were three icons competing for a 310px row, and for the
        // person this panel is built for they are not three destinations -- they are one. A student
        // leaving a course is going back to their courses; from My courses every other page on the
        // site is one click away, including the two that were here. Three icons bought nothing and
        // cost ~66px, which is the width the logo was missing.
        '<div class="aicourse-player-brand">' + logo +
            '<nav class="aicourse-player-nav" aria-label="' + strings.navlabel + '">' +
                '<a href="' + config.nav.mycourses + '" title="' + strings.mycourses + '">' +
                    '<svg viewBox="0 0 24 24" aria-hidden="true">' +
                    '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>' +
                    '<path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>' +
                    '<span class="accesshide">' + strings.mycourses + '</span></a>' +
            '</nav>' +
        '</div>' +
        '<div class="aicourse-player-title-row">' +
            '<div class="aicourse-player-titles">' +
                '<a class="aicourse-player-coursename" href="' + config.courseurl + '"></a>' +
                '<p class="aicourse-player-meta">' + meta.join('') + '</p>' +
            '</div>' + ring +
        '</div>';

    // Assigned rather than interpolated: a course name can contain anything.
    header.querySelector('.aicourse-player-coursename').textContent = config.coursename;

    // ACF-FIX-2.1.166: the sidebar ring fills and counts up, on the same curve and over the same
    // 1.8s as the banner's. Two rings showing the same course on the same screen behaving
    // differently is worse than neither being animated.
    animateRing(header, pct);

    return header;
};

const RING_DURATION = 1800;

/**
 * Fill the sidebar's progress ring and count its number up.
 *
 * Reduced motion is honoured by setting the end state immediately -- the information is the point,
 * the movement is not. The accessible name on the ring already carries the final figure, so the
 * count-up is written to the visible text only and assistive technology is told the answer once.
 *
 * @param {Element} header The header block.
 * @param {Number|null} pct The percentage, or null when nothing is tracked.
 * @returns {void}
 */
const animateRing = (header, pct) => {
    if (pct === null || pct === undefined) {
        return;
    }
    const fill = header.querySelector('.aicourse-player-ring-fill');
    const text = header.querySelector('.aicourse-player-ring-text');
    if (!fill) {
        return;
    }

    const target = pct * 1.194;
    const reduced = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduced || !window.requestAnimationFrame) {
        fill.style.strokeDasharray = target + ' 200';
        if (text) {
            text.textContent = pct + '%';
        }
        return;
    }

    // Start empty, then fill. The template writes the final dasharray, so without the reset the
    // transition has nowhere to travel from.
    fill.style.transition = 'none';
    fill.style.strokeDasharray = '0 200';
    void fill.getBoundingClientRect();
    fill.style.transition = 'stroke-dasharray ' + (RING_DURATION / 1000)
        + 's cubic-bezier(0.22, 1, 0.36, 1)';
    fill.style.strokeDasharray = target + ' 200';

    if (!text) {
        return;
    }
    text.textContent = '0%';
    let startedAt = null;
    const step = (now) => {
        if (startedAt === null) {
            startedAt = now;
        }
        const t = Math.min(1, (now - startedAt) / RING_DURATION);
        const eased = 1 - Math.pow(1 - t, 3);
        text.textContent = Math.round(pct * eased) + '%';
        if (t < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
};

/**
 * Add the duration and the completion tick to one activity row.
 *
 * @param {Element} row A .courseindex-item element.
 * @param {Object} data That activity's data from the server.
 * @param {Object} strings Localised labels.
 * @returns {void}
 */
/**
 * Describe what completion requires, and whether it is met.
 *
 * ACF-FIX-2.1.139. A tick says "done" and an empty circle says "not done"; neither says what would
 * make it done. The conditions come from core's own completion details, so the wording matches what
 * Moodle already shows on the activity page rather than being invented here.
 *
 * @param {Element} mark The tick element.
 * @param {Object} data That activity's data.
 * @param {Object} strings Localised labels.
 * @returns {void}
 */
const describeCompletion = (mark, data, strings) => {
    if (!data.tracked) {
        // Nothing is required, so there is nothing to explain. Saying "not completed" about an
        // activity that cannot be completed would be worse than saying nothing at all.
        mark.removeAttribute('title');
        return;
    }
    const status = data.complete ? strings.done : strings.notdone;
    const conditions = (data.requirements || []).join(', ');
    // ACF-FIX-2.1.153: the title attribute is kept as the FALLBACK only -- it is what a browser
    // with JavaScript disabled, a screen reader in browse mode, or a touch device with no hover
    // will use. The rich panel below replaces it wherever it can actually be shown, and clears it
    // while doing so, because a native tooltip fading in over a styled one is the worst of both.
    mark.setAttribute('title', conditions ? conditions + ' \u2014 ' + status : status);
};

/* --------------------------------------------------------------------------
   The completion tooltip (ACF-FIX-2.1.153)

   The title attribute could say what completion requires, but it could not say it well: no
   per-condition state, no way to show which ones are already met, ~1s before the browser shows it,
   no styling, and it disappears the moment the pointer moves. A learner looking at a green tick is
   asking three questions -- what was required, did I pass, and when did I finish -- and the answer
   is a small panel, not a sentence.

   One panel is built for the whole page and moved, rather than one per row: a course index can hold
   a hundred ticks, and a hundred detached DOM subtrees waiting for a hover is a hundred too many.
   -------------------------------------------------------------------------- */

const TIP_ID = 'aicourse-completion-tip';
const TIP_GAP = 10;

let tipEl = null;
let tipOwner = null;
let tipHideTimer = null;

/**
 * The single tooltip element, created on first use.
 *
 * @returns {Element}
 */
const getTip = () => {
    if (tipEl && tipEl.isConnected) {
        return tipEl;
    }
    tipEl = document.createElement('div');
    tipEl.id = TIP_ID;
    tipEl.className = 'aicourse-tip';
    tipEl.setAttribute('role', 'tooltip');
    // Hidden from the accessibility tree until it is shown AND owned: an empty tooltip announced
    // as a live region would be noise on every page load.
    tipEl.hidden = true;
    document.body.appendChild(tipEl);
    return tipEl;
};

/**
 * One condition row: a state marker and the wording core supplies.
 *
 * @param {Object} condition {text, met, failed}
 * @returns {Element}
 */
const buildCondition = (condition) => {
    const li = document.createElement('li');
    li.className = 'aicourse-tip-item '
        + (condition.met ? 'aicourse-tip-met' : (condition.failed ? 'aicourse-tip-failed' : 'aicourse-tip-unmet'));

    const marker = document.createElement('span');
    marker.className = 'aicourse-tip-marker';
    marker.setAttribute('aria-hidden', 'true');
    // Shape as well as colour, for the same reason the tick itself carries one: a tick for met, a
    // cross for failed, an empty ring for simply not done yet.
    if (condition.met) {
        marker.innerHTML = '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
    } else if (condition.failed) {
        marker.innerHTML = '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/>'
            + '<line x1="6" y1="6" x2="18" y2="18"/></svg>';
    }

    const text = document.createElement('span');
    text.className = 'aicourse-tip-text';
    text.textContent = condition.text;

    li.appendChild(marker);
    li.appendChild(text);
    return li;
};

/**
 * Fill the panel for one activity.
 *
 * @param {Element} tip The panel.
 * @param {Object} data That activity's data.
 * @param {Object} strings Localised labels.
 * @returns {boolean} False when there is nothing worth showing.
 */
const fillTip = (tip, data, strings) => {
    const conditions = data.conditions || [];
    const hasfacts = Boolean(data.grade) || Boolean(data.completedon);
    if (!conditions.length && !hasfacts) {
        return false;
    }

    tip.textContent = '';

    const status = document.createElement('p');
    status.className = 'aicourse-tip-status '
        + (data.complete ? 'aicourse-tip-status-done' : 'aicourse-tip-status-pending');
    status.textContent = data.complete ? strings.done : strings.notdone;
    tip.appendChild(status);

    if (conditions.length) {
        const head = document.createElement('p');
        head.className = 'aicourse-tip-head';
        head.textContent = strings.requires;
        tip.appendChild(head);

        const list = document.createElement('ul');
        list.className = 'aicourse-tip-list';
        conditions.forEach((condition) => list.appendChild(buildCondition(condition)));
        tip.appendChild(list);
    }

    if (hasfacts) {
        const facts = document.createElement('div');
        facts.className = 'aicourse-tip-facts';
        [data.grade, data.completedon].forEach((fact) => {
            if (!fact) {
                return;
            }
            const p = document.createElement('p');
            p.className = 'aicourse-tip-fact';
            p.textContent = fact;
            facts.appendChild(p);
        });
        tip.appendChild(facts);
    }

    return true;
};

/**
 * Place the panel beside its tick, kept inside the viewport.
 *
 * Fixed positioning, measured after the content is in place. The sidebar is a narrow column at the
 * inline-start edge, so the panel opens to its inline-end by default and only flips when there is
 * genuinely no room -- a panel that flips on every row reads as jitter.
 *
 * @param {Element} tip The panel.
 * @param {Element} anchor The tick.
 * @returns {void}
 */
const placeTip = (tip, anchor) => {
    const a = anchor.getBoundingClientRect();
    const t = tip.getBoundingClientRect();
    const vw = document.documentElement.clientWidth;
    const vh = document.documentElement.clientHeight;
    const rtl = document.documentElement.dir === 'rtl';

    let left = rtl ? (a.left - TIP_GAP - t.width) : (a.right + TIP_GAP);
    const flipped = rtl ? (left < TIP_GAP) : (left + t.width > vw - TIP_GAP);
    if (flipped) {
        left = rtl ? (a.right + TIP_GAP) : (a.left - TIP_GAP - t.width);
    }
    left = Math.max(TIP_GAP, Math.min(left, vw - t.width - TIP_GAP));

    // Centred on the tick, then pulled back inside the viewport rather than allowed to run off the
    // bottom of a row near the end of a long index.
    let top = a.top + (a.height / 2) - (t.height / 2);
    top = Math.max(TIP_GAP, Math.min(top, vh - t.height - TIP_GAP));

    tip.style.left = Math.round(left) + 'px';
    tip.style.top = Math.round(top) + 'px';
};

/**
 * Hide the panel and release the tick that owned it.
 *
 * @returns {void}
 */
const hideTip = () => {
    window.clearTimeout(tipHideTimer);
    if (!tipEl) {
        return;
    }
    tipEl.hidden = true;
    tipEl.classList.remove('aicourse-tip-in');
    if (tipOwner) {
        tipOwner.removeAttribute('aria-describedby');
        // The native tooltip comes back the moment ours is not on screen, so the information is
        // never unavailable -- only ever shown one way at a time.
        if (tipOwner.dataset.aicourseTitle) {
            tipOwner.setAttribute('title', tipOwner.dataset.aicourseTitle);
        }
        tipOwner = null;
    }
};

/**
 * Show the panel for one tick.
 *
 * @param {Element} anchor The tick.
 * @param {Object} data That activity's data.
 * @param {Object} strings Localised labels.
 * @returns {void}
 */
const showTip = (anchor, data, strings) => {
    window.clearTimeout(tipHideTimer);
    if (tipOwner === anchor && tipEl && !tipEl.hidden) {
        return;
    }
    hideTip();

    const tip = getTip();
    if (!fillTip(tip, data, strings)) {
        return;
    }

    tipOwner = anchor;
    // Suppress the browser's own tooltip while ours is up, remembering it so hideTip() can restore
    // it. Removing it permanently would strip the fallback from anyone who never triggers hover.
    const native = anchor.getAttribute('title');
    if (native !== null) {
        anchor.dataset.aicourseTitle = native;
        anchor.removeAttribute('title');
    }
    anchor.setAttribute('aria-describedby', TIP_ID);

    // Measured while visible but transparent: a hidden element has no box, so it cannot be placed.
    tip.hidden = false;
    placeTip(tip, anchor);
    // Next frame, so the transition has a start state to animate from rather than appearing at its
    // end state on the first paint.
    window.requestAnimationFrame(() => {
        if (tipOwner === anchor) {
            tip.classList.add('aicourse-tip-in');
        }
    });
};

/**
 * Bind the tooltip to one tick.
 *
 * Pointer and keyboard both open it; Escape, blur, scroll and any pointer leaving close it. The
 * short close delay lets the pointer cross the gap between the tick and the panel without the panel
 * vanishing underneath it.
 *
 * @param {Element} mark The tick element.
 * @param {Object} data That activity's data.
 * @param {Object} strings Localised labels.
 * @returns {void}
 */
export const bindTip = (mark, data, strings) => {
    if (!data.tracked) {
        return;
    }
    // Reachable by keyboard: the tick carries information nothing else on the row does, so it has
    // to be focusable for that information to be available without a mouse.
    mark.setAttribute('tabindex', '0');

    const open = () => showTip(mark, data, strings);
    const close = () => {
        window.clearTimeout(tipHideTimer);
        tipHideTimer = window.setTimeout(hideTip, 120);
    };

    mark.addEventListener('mouseenter', open);
    mark.addEventListener('focus', open);
    mark.addEventListener('mouseleave', close);
    mark.addEventListener('blur', close);

    // ACF-FIX-2.1.165: touch. A phone has no hover, so on a phone this panel did not exist -- the
    // completion conditions, the grade and the date were desktop-only features and nothing said so.
    //
    // A tap opens it and a tap anywhere else closes it. `pointerdown` rather than `click` so the
    // panel is up before the browser's ~300ms click delay, and `preventDefault` so the same tap does
    // not also fire the row's link underneath. Pointer type is checked rather than sniffing the
    // device: a hybrid laptop with a touchscreen gets hover AND tap, which is correct for both.
    mark.addEventListener('pointerdown', (e) => {
        if (e.pointerType === 'mouse') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (tipOwner === mark && tipEl && !tipEl.hidden) {
            hideTip();
        } else {
            open();
        }
    });
    mark.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            hideTip();
        }
    });
};

const decorateRow = (row, data, strings) => {
    if (row.dataset.aicoursePlayer === '1') {
        return;
    }
    row.dataset.aicoursePlayer = '1';
    row.classList.add('aicourse-player-row');

    if (data.complete) {
        row.classList.add('aicourse-player-row-done');
    }

    // ACF-FIX-2.1.58: the activity's own icon, at the head of the row.
    //
    // Core puts a completion circle here, which duplicates the tick this row already carries at
    // its end and tells a learner nothing about what the activity IS. The icon does: a quiz, a
    // page and an assignment are distinguishable at a glance without reading, which is the whole
    // point of an index you scan rather than read.
    if (data.icon) {
        const icon = document.createElement('span');
        icon.className = 'aicourse-player-row-icon';
        const img = document.createElement('img');
        img.src = data.icon;
        img.alt = '';
        img.loading = 'lazy';
        // The stylesheet masks with this, so the accent shows through a monochrome module icon.
        img.style.setProperty('--acf-player-icon', 'url("' + data.icon + '")');
        icon.appendChild(img);
        row.insertBefore(icon, row.firstChild);
    }

    if (data.time) {
        const time = document.createElement('span');
        time.className = 'aicourse-player-row-time';
        time.title = strings.esttime.replace('{$a}', data.time);
        // The same clock the activity cards carry, so the two pills are the same object.
        time.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true">'
            + '<circle cx="12" cy="12" r="10"></circle>'
            + '<polyline points="12 6 12 12 16 14"></polyline></svg>';
        time.appendChild(document.createTextNode(data.time));
        row.appendChild(time);
    }

    if (data.tracked) {
        const mark = document.createElement('span');
        mark.className = 'aicourse-player-tick'
            + (data.complete ? ' aicourse-player-tick-done' : '');
        describeCompletion(mark, data, strings);
        mark.setAttribute('role', 'img');
        mark.setAttribute('aria-label', data.complete ? strings.done : strings.notdone);
        bindTip(mark, data, strings);
        mark.innerHTML = data.complete
            ? '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>'
            : '';
        row.appendChild(mark);
    }
};

/**
 * Give a truncated label its full text on hover.
 *
 * ACF-FIX-2.1.82. The sidebar is a fixed width and names are not, so a long activity or section
 * title is cut off with an ellipsis and a learner has no way to read the rest of it. Adding the
 * full text as a title attribute lets them hover, and gives assistive technology the whole name
 * rather than the visible fragment.
 *
 * Only applied when the text is ACTUALLY clipped -- scrollWidth exceeding clientWidth is the
 * browser telling us so, which is more reliable than guessing from character counts and means a
 * short name never gets a tooltip repeating what is already on screen.
 *
 * Re-checked whenever the drawer changes, because the same name can be clipped at one drawer
 * width and not another.
 *
 * @param {Element} row The row to inspect.
 * @returns {void}
 */
const titleIfClipped = (row) => {
    const label = row.querySelector('.courseindex-link, .courseindex-name, .media-body');
    if (!label) {
        return;
    }
    // A couple of pixels of slack: sub-pixel text metrics report a 1px overflow on text that is
    // not visibly cut, which would put a tooltip on almost every row.
    const clipped = label.scrollWidth > label.clientWidth + 2;
    const full = (label.textContent || '').trim();
    // ACF-FIX-2.1.138: the activity's TYPE goes in the tooltip as well as its name.
    //
    // "Learning" and "Final Quiz" say what a thing is called, not what it is -- and in a course
    // built from custom activity types, knowing that one is an AI Content Creator and another an
    // Assignment changes what a learner expects before they click. The cards already show it under
    // the title; the index had no room for a second line, and a tooltip is that room.
    //
    // The type comes from the same config the row is built from, so it is the same wording the
    // cards use rather than a second source that could disagree.
    const type = (row.dataset.aicourseType || '').trim();
    let tip = '';
    if (clipped && full && type) {
        // Both, when the name is cut off: the tooltip is the only place the whole name appears, so
        // dropping it in favour of the type would lose more than it gives.
        tip = full + ' — ' + type;
    } else if (type) {
        tip = type;
    } else if (clipped && full) {
        tip = full;
    }
    if (tip) {
        if (label.getAttribute('title') !== tip) {
            label.setAttribute('title', tip);
        }
        label.dataset.aicourseTitled = '1';
        return;
    }
    if (label.dataset.aicourseTitled === '1') {
        // No longer clipped -- the drawer widened, or the name changed. Remove the tooltip rather
        // than leave a stale one on text that is fully visible.
        label.removeAttribute('title');
    }
    label.dataset.aicourseTitled = '0';
};

/**
 * Decorate every activity row currently in the drawer.
 *
 * @param {Object} config Course data.
 * @param {Object} strings Localised labels.
 * @returns {void}
 */
const decorate = (config, strings) => {
    document.querySelectorAll('.courseindex-item').forEach((row) => {
        // Stash the type before measuring, so titleIfClipped has it whichever order they run in.
        const id = cmidOf(row);
        if (id && config.activities[id] && config.activities[id].type) {
            row.dataset.aicourseType = config.activities[id].type;
        }

        // Section headings get the tooltip treatment too: they are the longest names in the panel
        // and the first thing clipped.
        titleIfClipped(row);

        const cmid = cmidOf(row);
        if (!cmid || !config.activities[cmid]) {
            return;
        }
        decorateRow(row, config.activities[cmid], strings);
    });
};

/**
 * Start the player sidebar.
 *
 * @param {Object} config Course data from the server.
 * @returns {void}
 */
/**
 * Read the config the hook placed in the page.
 *
 * ACF-FIX-2.1.113: this used to arrive as an argument to init(). It carries a row per activity, so
 * a course of any size pushed the argument string past the 1024-character limit js_call_amd()
 * warns about -- 21 activities was enough. Moodle's own advice for this case is to pass the data
 * through the page instead, which is what the hook now does.
 *
 * @returns {Object|null} The config, or null when absent or unreadable.
 */
export const readConfig = () => {
    const node = document.getElementById('aicourse-player-config');
    if (!node) {
        return null;
    }
    try {
        return JSON.parse(node.textContent || '');
    } catch (e) {
        // Malformed config: leave the course index as core rendered it rather than decorating it
        // with half the data.
        return null;
    }
};

/* --------------------------------------------------------------------------
   Merging the drawer's own header into the brand row (ACF-FIX-2.1.154)

   Core gives the course index a 44px strip of its own carrying two controls -- a close button and
   the collapse/expand dropdown -- directly above this plugin's header band. Three rows of chrome
   before the first section is one more than the panel needs, and the banner is sized to match the
   whole block, so the cost is paid twice: once in the sidebar and once across the top of the page.

   Two previous attempts moved the BRAND INTO core's strip and failed -- the strip's own flex rules
   collapsed the logo and the nav icons to nothing. This goes the other way: core's two controls are
   adopted INTO the brand row, which this plugin owns outright, and the emptied strip is collapsed.
   Nothing of core's layout has to cooperate.

   The controls keep their own markup, their data attributes and their event bindings. Boost
   delegates drawer toggling and Bootstrap delegates the dropdown from `document`, so both keep
   working from anywhere in the tree -- what matters is that the nodes are moved intact rather than
   recreated.

   Measured on the live site before writing this: `.drawer-left` matches TWO drawers stacked at the
   same coordinates -- `theme_boost-drawers-primary` and `theme_boost-drawers-courseindex`, both
   342x851 at 0,60, each with a 341x44 `.drawerheader`. Only the second one is the course index, so
   every selector here names it by id. The primary drawer's own close button measured 0x0 and its
   header content is empty; reaching for `.drawer-left` finds that one first.
   -------------------------------------------------------------------------- */

const CI_DRAWER = '#theme_boost-drawers-courseindex';

/**
 * Move the drawer's close button and options menu into the brand row.
 *
 * @param {Element} header The plugin's own header block.
 * @param {Object} strings Localised labels.
 * @returns {void}
 */
const adoptDrawerControls = (header, strings) => {
    const drawer = document.querySelector(CI_DRAWER);
    const brand = header.querySelector('.aicourse-player-brand');
    if (!drawer || !brand) {
        return;
    }
    const strip = drawer.querySelector('.drawerheader');
    if (!strip || brand.querySelector('.aicourse-player-drawerctl')) {
        return;
    }

    const slot = document.createElement('div');
    slot.className = 'aicourse-player-drawerctl';

    // The options menu is MOVED. It is a Bootstrap dropdown with a rendered menu beside its
    // trigger, and rebuilding that faithfully would mean copying markup that is core's to change.
    const menu = strip.querySelector('.drawerheadercontent');
    if (menu) {
        slot.appendChild(menu);
    }

    // ACF-FIX-2.1.156: the close button is REBUILT, not moved.
    //
    // 2.1.154 moved core's own `.drawertoggle` here and it did not survive the trip -- the merged
    // row rendered with the menu and no close control at all, which left the panel with no way to
    // shut. Core's drawer JS owns that button: it toggles `hidden`, rewrites `tabindex` and
    // `data-aria-hidden-tab-index` on it as the drawer opens and closes, and it does that by
    // querying inside the drawer's own header -- which this code had just emptied.
    //
    // Fighting that with CSS is fighting core for control of an element core believes it owns.
    // Making our own button instead ends the argument: Boost's toggle handler is delegated from
    // `document` and dispatches on the data attributes alone, so a button this plugin creates with
    // the same three attributes closes the drawer exactly as core's does, and core has no reason to
    // touch it. The original stays in the collapsed strip, untouched and hidden by its overflow, so
    // core's own bookkeeping still finds what it expects.
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'aicourse-player-close';
    close.setAttribute('data-toggler', 'drawers');
    close.setAttribute('data-action', 'closedrawer');
    close.setAttribute('data-target', 'theme_boost-drawers-courseindex');
    // ACF-FIX-2.1.157: the label must never be able to stop the button existing. A string that is
    // missing from a stale language cache resolves to "[[player_closeindex]]" rather than throwing,
    // but a rejected getString would take the whole init with it -- and the close control is the
    // one thing on this panel that must not depend on anything else working.
    const label = (typeof strings.closeindex === 'string' && strings.closeindex.indexOf('[[') !== 0)
        ? strings.closeindex
        : 'Close course index';
    close.setAttribute('aria-label', label);
    close.setAttribute('title', label);
    close.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        + '<line x1="18" y1="6" x2="6" y2="18"></line>'
        + '<line x1="6" y1="6" x2="18" y2="18"></line></svg>';
    slot.appendChild(close);

    brand.appendChild(slot);
    // The class is what the stylesheet keys the collapsed strip and the shorter top block off, so
    // it is set only once the move has actually succeeded. A failed adopt leaves three rows, which
    // is the old layout -- not a broken one.
    document.body.classList.add('aicourse-index-merged');
};

export const init = async(passed) => {
    // The argument is still honoured so an older cached page, or anything else calling this
    // directly, keeps working.
    const config = passed || readConfig();
    if (!config || !config.activities) {
        return;
    }

    const index = document.querySelector('.courseindex');
    if (!index) {
        return;
    }

    // ACF-FIX-2.1.153: the body class is set BEFORE the strings are awaited. It changes the drawer
    // width by 57px and the content offset by 342px, and those are layout properties -- applying it
    // after a network round-trip made the whole page jump once the strings landed. Nothing about the
    // class depends on them.
    document.body.classList.add('aicourse-player-on');

    const [home, dashboard, mycourses, navlabel, progress, esttime, done, notdone, requires, closeindex]
        = await Promise.all([
        getString('player_home', 'format_aicourse'),
        getString('player_dashboard', 'format_aicourse'),
        getString('player_mycourses', 'format_aicourse'),
        getString('player_navlabel', 'format_aicourse'),
        getString('player_progress', 'format_aicourse'),
        getString('estimatedtimefor', 'format_aicourse'),
        getString('player_done', 'format_aicourse'),
        getString('player_notdone', 'format_aicourse'),
        getString('player_requires', 'format_aicourse'),
        getString('player_closeindex', 'format_aicourse'),
    ]);
    const strings = {home, dashboard, mycourses, navlabel, progress, esttime, done, notdone, requires,
        closeindex};

    if (!document.getElementById(HEADER_ID)) {
        index.parentNode.insertBefore(buildHeader(config, strings), index);
    }

    const header = document.getElementById(HEADER_ID);
    if (header) {
        adoptDrawerControls(header, strings);
    }

    // ACF-FIX-2.1.157: one line, once, saying what the merge actually did. The close button went
    // missing in 2.1.154 and there was no way to tell from the page whether the code had run, run
    // and failed, or not been loaded at all -- which cost a round trip to find out. It is a single
    // console line on a course page, not a logging framework.
    // ACF-FIX-2.1.163: the diagnostic now reports the row's GEOMETRY, not just whether the parts
    // exist. Three rounds were spent guessing why the logo looked small and the icons looked far
    // apart, against a local harness whose numbers turned out not to match the real panel. The
    // measurements the fix depends on are printed by the page itself, once, so the next change is
    // made from this site's numbers rather than a reconstruction of them.
    if (window.console && window.console.info) {
        const box = (sel) => {
            const e = document.querySelector(sel);
            if (!e) {
                return null;
            }
            const r = e.getBoundingClientRect();
            return {w: Math.round(r.width), h: Math.round(r.height),
                l: Math.round(r.left), r: Math.round(r.right)};
        };
        const brand = document.querySelector('.aicourse-player-brand');
        const logo = document.querySelector('.aicourse-player-logo');
        const nav = document.querySelector('.aicourse-player-nav');
        const ctl = document.querySelector('.aicourse-player-drawerctl');
        const hero = document.querySelector('.aicourse-hero-sticky-wrap');
        const page = document.getElementById('page');
        window.console.info('[format_aicourse] merge:', JSON.stringify({
            build: '2.1.163',
            merged: document.body.classList.contains('aicourse-index-merged'),
            close: !!document.querySelector('.aicourse-player-close'),
            brand: box('.aicourse-player-brand'),
            logo: box('.aicourse-player-logo'),
            logoNatural: logo ? logo.naturalWidth + 'x' + logo.naturalHeight : null,
            nav: box('.aicourse-player-nav'),
            ctl: box('.aicourse-player-drawerctl'),
            gapLogoNav: (logo && nav)
                ? Math.round(nav.getBoundingClientRect().left - logo.getBoundingClientRect().right)
                : null,
            gapNavCtl: (nav && ctl)
                ? Math.round(ctl.getBoundingClientRect().left - nav.getBoundingClientRect().right)
                : null,
            brandOverflow: brand ? brand.scrollWidth - brand.clientWidth : null,
            justify: brand ? window.getComputedStyle(brand).justifyContent : null,
            heroTop: hero ? Math.round(hero.getBoundingClientRect().top) : null,
            pageTop: page ? Math.round(page.getBoundingClientRect().top) : null,
            heroMarginTop: hero ? window.getComputedStyle(hero).marginBlockStart : null
        }));
    }

    decorate(config, strings);

    // Core rebuilds parts of the index as sections collapse, activities are edited, or completion
    // changes -- and every rebuild drops the decoration, because core does not know about it.
    // Watching the subtree puts it straight back. decorateRow() marks what it has already done, so
    // a rebuild of one section does not re-walk the whole tree.
    if (typeof window.MutationObserver === 'function') {
        const observer = new window.MutationObserver(() => decorate(config, strings));
        observer.observe(index, {childList: true, subtree: true});
    }

    // The tooltip is anchored to a fixed position, so anything that moves its tick underneath it
    // has to close it rather than leave it pointing at empty space. Passive listeners: neither
    // handler can cancel the scroll it is reacting to.
    // Any tap outside a tick dismisses the panel — the touch equivalent of the pointer leaving.
    document.addEventListener('pointerdown', (e) => {
        if (e.pointerType !== 'mouse' && !(e.target.closest && e.target.closest('.aicourse-player-tick'))) {
            hideTip();
        }
    }, true);

    window.addEventListener('scroll', hideTip, {passive: true, capture: true});
    window.addEventListener('resize', hideTip, {passive: true});
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            hideTip();
        }
    });

    // Whether a name is clipped depends on the drawer's width, so it is re-checked when that
    // changes -- a window resize, or the drawer itself being resized by the theme.
    if (typeof window.ResizeObserver === 'function') {
        let pending = null;
        const recheck = new window.ResizeObserver(() => {
            window.clearTimeout(pending);
            pending = window.setTimeout(() => decorate(config, strings), 150);
        });
        recheck.observe(index);
    }
};
