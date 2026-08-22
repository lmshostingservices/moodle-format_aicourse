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
 * Section card behaviour: the progress ring counts up, and a completion tick explains itself.
 *
 * ACF-FIX-2.1.178.
 *
 * WHY THIS IS A SEPARATE MODULE. Everything here is a second consumer of things player.js already
 * built -- the tooltip panel and the per-activity completion payload. It is not a copy of them.
 * The tooltip is imported, and the payload is read from the same config the player reads, so a
 * card row and a course-index row showing the same activity cannot disagree about it: there is one
 * source and one renderer. Putting this inside player.js instead would have meant loading the
 * whole sidebar on a page that may not have one.
 *
 * The card rings are also deliberately NOT animated by courseformat.js, which owns the hero ring.
 * That module tracks a single lastPercentage on itself; a page has one hero and many cards, so a
 * shared counter would make every card animate from whatever the previous card's figure was.
 *
 * @module     format_aicourse/cards
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';
import {bindTip, readConfig} from 'format_aicourse/player';

/** @var {Number} Milliseconds. The hero ring's duration, so the two read as one system. */
const DURATION = 1800;

/** @var {Number} Circumference of an r=16 circle, which is what the template draws. */
const CIRCUMFERENCE = 100.53;

/** @var {String} Marks a card already processed, so a re-render cannot animate it twice. */
const DONEATTR = 'data-aicourse-card-ready';

/**
 * Whether this viewer has asked for less movement.
 *
 * @returns {Boolean} True when animation should be skipped.
 */
const reducedMotion = () => Boolean(window.matchMedia)
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Count one card's ring and bar up from zero.
 *
 * The stroke and the bar are CSS transitions -- the browser interpolates them off the main thread,
 * so a page of twelve cards costs twelve style changes rather than twelve animation loops. Only the
 * NUMBER needs a frame loop, because text cannot be interpolated.
 *
 * @param {Element} wrap The .aicourse-card-progress element, carrying data-acf-ring.
 * @returns {void}
 */
const animate = (wrap) => {
    const pct = Math.max(0, Math.min(100, parseInt(wrap.dataset.acfRing, 10) || 0));
    const fill = wrap.querySelector('.aicourse-card-ring-fill');
    const bar = wrap.querySelector('.aicourse-card-progress-bar-fill');
    const text = wrap.querySelector('.aicourse-card-ring-text');
    const target = (pct / 100) * CIRCUMFERENCE;

    // The template writes the final values server-side, so the card is correct with no JavaScript
    // at all. That is also why each of these has to be reset before it can be animated: there is
    // nowhere to travel from otherwise.
    if (reducedMotion() || !window.requestAnimationFrame) {
        return;
    }

    if (fill) {
        fill.style.transition = 'none';
        fill.style.strokeDasharray = '0 ' + CIRCUMFERENCE;
    }
    if (bar) {
        bar.style.transition = 'none';
        bar.style.width = '0%';
    }
    // Force the reset to be applied before the transition is put back, or the browser coalesces
    // both style changes into one and nothing moves.
    void wrap.getBoundingClientRect();

    if (fill) {
        fill.style.transition = '';
        fill.style.strokeDasharray = target + ' ' + CIRCUMFERENCE;
    }
    if (bar) {
        bar.style.transition = '';
        bar.style.width = pct + '%';
    }

    if (!text) {
        return;
    }
    // The suffix is taken from what the server already rendered rather than assumed to be "%",
    // because the string comes from the language pack and not every locale writes it that way.
    const suffix = text.textContent.replace(/[\d\s]/g, '');
    let startedAt = null;
    const step = (now) => {
        if (startedAt === null) {
            startedAt = now;
        }
        const t = Math.min(1, (now - startedAt) / DURATION);
        // The same easing curve the stroke uses, so the number and the arc stay together instead of
        // one racing ahead of the other.
        const eased = 1 - Math.pow(1 - t, 3);
        text.textContent = Math.round(pct * eased) + suffix;
        if (t < 1) {
            window.requestAnimationFrame(step);
        }
    };
    text.textContent = '0' + suffix;
    window.requestAnimationFrame(step);
};

/**
 * Start each card's count-up when that card is actually on screen.
 *
 * A course with twenty sections has twenty rings, and the ones below the fold would otherwise
 * finish their sweep before the learner ever scrolled to them -- which is the animation happening
 * and not being seen, the worst of both. IntersectionObserver defers each to its own arrival.
 *
 * @param {Array} wraps The progress elements.
 * @returns {void}
 */
const scheduleRings = (wraps) => {
    if (typeof window.IntersectionObserver !== 'function') {
        wraps.forEach(animate);
        return;
    }
    const observer = new window.IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }
            observer.unobserve(entry.target);
            animate(entry.target);
        });
    }, {threshold: 0.4});
    wraps.forEach((wrap) => observer.observe(wrap));
};

/**
 * Give every completion tick on a card the same tooltip the course index gives it.
 *
 * @param {Object} config The player config, keyed by cmid.
 * @returns {Promise} Resolves once the ticks are bound.
 */
const bindTicks = async(config) => {
    const rows = [...document.querySelectorAll('.aicourse-card-activity[data-cmid]')]
        .filter((row) => row.querySelector('.aicourse-card-activity-state'));
    if (!rows.length) {
        return;
    }

    const [done, notdone, requires] = await Promise.all([
        getString('player_done', 'format_aicourse'),
        getString('player_notdone', 'format_aicourse'),
        getString('player_requires', 'format_aicourse'),
    ]);
    const strings = {done, notdone, requires};

    rows.forEach((row) => {
        const data = config.activities[row.dataset.cmid];
        if (!data) {
            return;
        }

        // ACF-FIX-2.1.179: the WHOLE ROW opens the course index's panel, not just the tick.
        //
        // 2.1.178 bound the panel to the tick and gave the name a plain `title` summary -- type,
        // time, status. That is a different, smaller thing than what the course index shows, and
        // having two hover behaviours on one row taught the learner that hovering here means
        // something other than hovering there. The card is meant to read as the index; a summary
        // tooltip is not the index's tooltip.
        //
        // Both anchors are bound. The link is what a pointer will actually find -- it is the whole
        // row -- and the tick is kept because bindTip makes it focusable, which is what puts this
        // information within reach of a keyboard. Whichever is entered first owns the panel;
        // showTip() swaps owners rather than stacking, so crossing from one to the other inside a
        // single row does not flicker.
        //
        // The link keeps its server-rendered `title` as the no-JavaScript fallback. bindTip stashes
        // and restores it around the panel, so the browser's own tooltip never appears underneath.
        const link = row.querySelector('.aicourse-card-activity-link');
        const mark = row.querySelector('.aicourse-card-activity-state');
        if (link) {
            bindTip(link, data, strings);
        }
        if (mark) {
            bindTip(mark, data, strings);
        }
    });
};

/**
 * Wire up the section cards.
 *
 * @returns {void}
 */
export const init = () => {
    const start = () => {
        const wraps = [...document.querySelectorAll('.aicourse-card-progress[data-acf-ring]')]
            .filter((w) => !w.hasAttribute(DONEATTR));
        wraps.forEach((w) => w.setAttribute(DONEATTR, '1'));
        if (wraps.length) {
            scheduleRings(wraps);
        }

        // No config means the player is switched off for this course. The rows still render their
        // icon, time and tick from the server; they simply do not gain the hover panel, which is
        // the correct degradation -- the panel's contents come from the player's payload.
        const config = readConfig();
        if (config && config.activities) {
            bindTicks(config).catch(() => {
                // A missing language string must not take the rings down with it.
            });
        }

        // ACF-FIX-2.1.182: one line, once, saying what this module actually found.
        //
        // "Does the card progress count up?" has now been asked twice and answered twice from a
        // local harness, which is not the same as answering it from the page in front of the
        // person asking. The three things that decide it are all reported here: whether the module
        // ran at all, how many rings it found (zero means the markup is stale -- an upgrade that
        // did not purge caches, or completion tracking off so `hasprogress` is false), and whether
        // motion is suppressed (a ring with reduced-motion set is CORRECT to sit still).
        // Same diagnostic pattern as player.js, which settled the logo and the close button in one
        // round each instead of three.
        if (window.console && window.console.info) {
            const first = document.querySelector('.aicourse-card-progress[data-acf-ring]');
            window.console.info('[format_aicourse] cards:', JSON.stringify({
                build: '2.1.182',
                rings: document.querySelectorAll('.aicourse-card-progress[data-acf-ring]').length,
                rows: document.querySelectorAll('.aicourse-card-activity[data-cmid]').length,
                animated: wraps.length,
                pct: first ? first.dataset.acfRing : null,
                reducedMotion: reducedMotion(),
                io: typeof window.IntersectionObserver === 'function',
                playerConfig: Boolean(config && config.activities),
            }));
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, {once: true});
        return;
    }
    start();
};
