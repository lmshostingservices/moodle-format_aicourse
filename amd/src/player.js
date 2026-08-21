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
        '<div class="aicourse-player-brand">' + logo +
            '<nav class="aicourse-player-nav" aria-label="' + strings.navlabel + '">' +
                '<a href="' + config.nav.home + '" title="' + strings.home + '">' +
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11l9-8 9 8"/>' +
                    '<path d="M5 10v10h14V10"/></svg>' +
                    '<span class="accesshide">' + strings.home + '</span></a>' +
                '<a href="' + config.nav.dashboard + '" title="' + strings.dashboard + '">' +
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/>' +
                    '<rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>' +
                    '<rect x="14" y="14" width="7" height="7"/></svg>' +
                    '<span class="accesshide">' + strings.dashboard + '</span></a>' +
                '<a href="' + config.nav.mycourses + '" title="' + strings.mycourses + '">' +
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4z"/>' +
                    '<path d="M8 5v14"/></svg>' +
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
    return header;
};

/**
 * Add the duration and the completion tick to one activity row.
 *
 * @param {Element} row A .courseindex-item element.
 * @param {Object} data That activity's data from the server.
 * @param {Object} strings Localised labels.
 * @returns {void}
 */
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
        mark.setAttribute('role', 'img');
        mark.setAttribute('aria-label', data.complete ? strings.done : strings.notdone);
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
    if (clipped && full) {
        if (label.getAttribute('title') !== full) {
            label.setAttribute('title', full);
        }
    } else if (label.dataset.aicourseTitled === '1') {
        // No longer clipped -- the drawer widened, or the name changed. Remove the tooltip rather
        // than leave a stale one on text that is fully visible.
        label.removeAttribute('title');
    }
    label.dataset.aicourseTitled = clipped ? '1' : '0';
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
const readConfig = () => {
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

    const [home, dashboard, mycourses, navlabel, progress, esttime, done, notdone] = await Promise.all([
        getString('player_home', 'format_aicourse'),
        getString('player_dashboard', 'format_aicourse'),
        getString('player_mycourses', 'format_aicourse'),
        getString('player_navlabel', 'format_aicourse'),
        getString('player_progress', 'format_aicourse'),
        getString('estimatedtimefor', 'format_aicourse'),
        getString('player_done', 'format_aicourse'),
        getString('player_notdone', 'format_aicourse'),
    ]);
    const strings = {home, dashboard, mycourses, navlabel, progress, esttime, done, notdone};

    document.body.classList.add('aicourse-player-on');

    if (!document.getElementById(HEADER_ID)) {
        index.parentNode.insertBefore(buildHeader(config, strings), index);
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
