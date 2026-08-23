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
 * The plugin's settings page: a feature hub, colour-coded categories, and search.
 *
 * ACF-FIX-2.1.159. Rebuilt. The previous version filtered a flat list in place; this one restructures
 * the page into sections and puts a hub of jump links above them.
 *
 * The design problem is that sixty-five settings in one column is not a page, it is a scroll — and
 * the thing an administrator arrives wanting is almost never "read all the settings". It is "turn off
 * the course index on activity pages", and the fastest possible route to that is a link that says so.
 * The hub is that: every area of the plugin as a card, each listing the settings people actually come
 * for, each one an anchor straight to the control.
 *
 * Moodle already gives every settings row `id="admin-<name>"`, so those anchors are ordinary links.
 * They work with JavaScript disabled, they survive filtering, and they can be bookmarked and shared —
 * which is worth more than any scripted scroll.
 *
 * @module     format_aicourse/settingsui
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The categories, in the order they appear on the page.
 *
 * `match` is a list of patterns tested against the setting's own NAME, never its text: a setting must
 * not be filed under Cards because its description happens to mention a card. First match wins, so
 * the order below is part of the design — Timing comes first because `hidetimesectioncards` and
 * `hidetimeindex` would otherwise be claimed by Cards and Course index, and both are timing settings.
 *
 * `jump` names the settings that get a link on the hub card. They are the ones people arrive looking
 * for, not simply the first few in the category — a jump list that is just "the first four" teaches
 * the reader nothing.
 */
const CATEGORIES = [
    {
        id: 'time', order: 7, label: 'Estimated time', icon: '◷', hue: 'time',
        desc: 'The little clock badges. How long each activity is assumed to take, and which of the four places show it.',
        match: [/minutes/, /timing/, /hidetime/],
    },
    {
        id: 'tutor', order: 8, label: 'AI Tutor', icon: '✦', hue: 'tutor',
        desc: 'The chat bubble that answers learners\' questions. Its connection to the external AI service, and what course content is sent there.',
        match: [/tutor/, /apikey/, /siteid/, /assessmentanswers/, /aiassistant/, /externalservice/,
            /adminreportlink/],
    },
    {
        id: 'tour', order: 9, label: 'Guided tour', icon: '◎', hue: 'tour',
        desc: 'The guided walkthrough a person sees the first time they open a course, and whether it speaks.',
        match: [/tour/],
    },
    {
        id: 'colour', order: 6, label: 'Colours & branding', icon: '◐', hue: 'colour',
        desc: 'Every colour the format paints — headings, icons, cards, the side menu — plus your logo and light/dark mode.',
        match: [/colour/, /opacity/, /scrim/, /fade/, /overlay/, /playerlogo/, /colourmode/],
    },
    {
        id: 'index', order: 1, label: 'Course index', icon: '☰', hue: 'index',
        desc: 'The menu that slides out from the side of a course listing every section and activity. Where it shows, how it opens, and whether it becomes a progress tracker.',
        match: [/playerindex/, /indexstate/, /playerheader/, /showcourseindex/, /^index/,
            /forceindex/, /defaultindex/, /hidegeneral/],
    },
    {
        id: 'banner', order: 2, label: 'Hero banner', icon: '▭', hue: 'banner',
        desc: 'The wide image strip across the top of a course carrying its name and the learner\'s progress.',
        match: [/herobanner/, /heroattop/, /heroimage/, /herosticky/, /showherobanner/, /hero/],
    },
    {
        id: 'cards', order: 3, label: 'Section cards', icon: '▦', hue: 'cards',
        desc: 'The tiles on the course home page, one per section, each showing its own progress and estimated time.',
        match: [/displayascards/, /cardlayout/, /cardtitlesize/, /cardactivitylimit/,
            /showactivities/, /card/],
    },
    {
        id: 'activity', order: 4, label: 'Activity display', icon: '▤', hue: 'activity',
        desc: 'How the activities inside a section are laid out, and the arrows that move a learner from one to the next.',
        match: [/activitydisplaymode/, /navchevrons/],
    },
    {
        id: 'nav', order: 5, label: 'Navigation & chrome', icon: '⇱', hue: 'nav',
        desc: 'Moodle\'s own furniture around your course — the tabs, the breadcrumb trail, '
            + 'the footer and the site logo band.',
        match: [/secondarynav/, /coursenavplace/, /immersive/, /hidefooter/, /hidebreadcrumb/],
    },
    {
        id: 'other', order: 10, label: 'Other', icon: '⚙', hue: 'other',
        desc: 'Anything not claimed by an area above.',
        match: [],
    }
];

/**
 * Which category a row belongs to.
 *
 * @param {String} name The setting's own name.
 * @returns {Object} A category from CATEGORIES; never null.
 */
const categoryOf = (name) => {
    for (const cat of CATEGORIES) {
        for (const pattern of cat.match) {
            if (pattern.test(name)) {
                return cat;
            }
        }
    }
    return CATEGORIES[CATEGORIES.length - 1];
};

/**
 * A small inline SVG, so nothing depends on an icon font being present.
 *
 * @param {String} d The path data.
 * @param {String} cls A class for the element.
 * @returns {String} Markup.
 */
const svg = (d, cls) =>
    // ACF-FIX-2.1.187: width and height on the element, not only in the stylesheet.
    // An SVG carrying a viewBox but no dimensions has no intrinsic size, so with the plugin's CSS
    // absent the browser sizes it from its container -- measured at 1100px in that state. The CSS
    // still governs the real layout; these are the floor beneath it, so no stylesheet condition can
    // produce a giant icon. Same reasoning as the wireframes below.
    '<svg class="' + cls + '" viewBox="0 0 24 24" width="18" height="18" '
    + 'style="width:1em;height:1em" aria-hidden="true" focusable="false" '
    + 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
    + 'stroke-linejoin="round">' + d + '</svg>';

/* --------------------------------------------------------------------------
   Per-setting wireframes (ACF-FIX-2.1.167)

   A category wireframe says "this area is the sidebar". A per-setting wireframe can say what THIS
   control does to it — sidebar shown versus hidden, banner at the top versus below the tabs, cards
   in a grid versus a list. For a page whose settings are almost all layout choices, that is the
   difference between reading a sentence and seeing the answer.

   Two shapes are drawn:

     * the setting's own diagram, chosen by name; and
     * for every `force…` setting, that same diagram repeated three times labelled Course 1, 2, 3 —
       because the one thing people get wrong about this plugin is the difference between a site
       DEFAULT (seeds new courses) and an OVERRIDE (changes every course that already exists). The
       three little courses say "all of them" in a way the sentence underneath has never managed.
   -------------------------------------------------------------------------- */

/**
 * A rounded rectangle, as SVG markup.
 *
 * @param {Number} x Left.
 * @param {Number} y Top.
 * @param {Number} w Width.
 * @param {Number} h Height.
 * @param {String} f Fill.
 * @param {Number} rd Corner radius.
 * @returns {String}
 */
const rect = (x, y, w, h, f, rd) =>
    '<rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h
    + '" rx="' + (rd === undefined ? 2 : rd) + '" fill="' + f + '"/>';

/* ---------------------------------------------------------------------------
   ACF-FIX-2.1.180: FURNITURE, NOT BLOCKS.
   ---------------------------------------------------------------------------
   Every diagram on this page was assembled from plain rectangles. A rectangle
   in the top-left is a sidebar only if you already know that; a reader who is
   trying to work out what a setting does gets a grey grid and has to read the
   prose anyway, which is the job the picture was added to do.

   These helpers draw the things the product actually has -- a sidebar with
   activity rows, a hero with a progress ring, a card with a tick and a footer
   bar -- at 200x112. The miniature then LOOKS like the page it is describing,
   so "hide the General section" is answered by seeing a band disappear rather
   than by decoding which grey box moved.

   They compose, so a diagram stays one readable expression: chrome() + sidebar()
   + hero() + card(). Every part takes a state so the same helper can draw the
   thing switched on, switched off, or absent.
   --------------------------------------------------------------------------- */

/** @var {String} Ink for anything drawn on top of a filled accent surface. */
const INK = 'rgb(255 255 255 / 0.85)';

/** @var {String} The same, one step quieter -- secondary lines inside a filled panel. */
const INK2 = 'rgb(255 255 255 / 0.5)';

/**
 * A line of text.
 *
 * @param {Number} x Left edge.
 * @param {Number} y Top edge.
 * @param {Number} w Width.
 * @param {String} f Fill.
 * @param {Number} h Height; 3.5 by default, which is a body line at this scale.
 * @returns {String} SVG.
 */
const line = (x, y, w, f, h) => rect(x, y, w, h === undefined ? 3.5 : h, f, 1.75);

/**
 * A small square standing in for a module icon.
 *
 * @param {Number} x Left edge.
 * @param {Number} y Top edge.
 * @param {String} f Fill.
 * @returns {String} SVG.
 */
const icon = (x, y, f) => rect(x, y, 7, 7, f, 2);

/**
 * A completion tick: a filled disc with a check, or an empty ring.
 *
 * @param {Number} cx Centre x.
 * @param {Number} cy Centre y.
 * @param {Boolean} done Whether it is complete.
 * @param {String} f The accent fill.
 * @returns {String} SVG.
 */
const tick = (cx, cy, done, f) => (done
    ? '<circle cx="' + cx + '" cy="' + cy + '" r="4" fill="' + f + '"/>'
        + '<path d="M' + (cx - 1.9) + ' ' + cy + ' l1.4 1.5 l2.6-3" stroke="#fff" stroke-width="1.2"'
        + ' fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
    : '<circle cx="' + cx + '" cy="' + cy + '" r="3.6" fill="none" stroke="' + f
        + '" stroke-width="1.4"/>');

/**
 * A progress ring with a filled arc.
 *
 * @param {Number} cx Centre x.
 * @param {Number} cy Centre y.
 * @param {Number} r Radius.
 * @param {Number} pct How much of it is filled, 0-100.
 * @param {String} f Arc colour.
 * @param {String} track Track colour.
 * @returns {String} SVG.
 */
const ring = (cx, cy, r, pct, f, track) => {
    const c = 2 * Math.PI * r;
    return '<circle cx="' + cx + '" cy="' + cy + '" r="' + r + '" fill="none" stroke="' + track
        + '" stroke-width="2.4"/>'
        + '<circle cx="' + cx + '" cy="' + cy + '" r="' + r + '" fill="none" stroke="' + f
        + '" stroke-width="2.4" stroke-linecap="round" stroke-dasharray="'
        + ((pct / 100) * c).toFixed(2) + ' ' + c.toFixed(2)
        + '" transform="rotate(-90 ' + cx + ' ' + cy + ')"/>';
};

/**
 * A progress bar with its trough.
 *
 * @param {Number} x Left edge.
 * @param {Number} y Top edge.
 * @param {Number} w Width.
 * @param {Number} pct Fill proportion, 0-100.
 * @param {String} f Fill colour.
 * @param {String} track Trough colour.
 * @returns {String} SVG.
 */
const meter = (x, y, w, pct, f, track) => rect(x, y, w, 3, track, 1.5)
    + rect(x, y, Math.max(2, (w * pct) / 100), 3, f, 1.5);


/**
 * The diagram for one setting, on a 200x112 page.
 *
 * Matched on the setting's own name with the `default`/`force` prefix stripped, so a pair shares one
 * diagram and only the framing differs.
 *
 * @param {String} base The setting name without its default/force prefix.
 * @returns {String} SVG child markup.
 */
const settingDiagram = (base) => {
    const on = 'rgb(var(--c) / 0.85)';
    const soft = 'rgb(var(--c) / 0.3)';
    const off = 'rgb(var(--c) / 0.14)';
    const faint = 'rgb(var(--c) / 0.07)';
    const plate = rect(0, 0, 200, 112, 'rgb(var(--c) / 0.05)', 6);

    /* The site's own top bar. Present on every page of the product, so present in
       every diagram -- it is the fixed point the rest is positioned against. */
    const topbar = (f) => rect(0, 0, 200, 9, f === undefined ? faint : f, 6)
        + rect(0, 5, 200, 4, f === undefined ? faint : f, 0);

    /* The course index: a logo mark, the course name, a progress ring, then the
       activity rows -- icon, name, tick -- which is exactly what the panel holds. */
    const sidebar = (state) => {
        const shown = state !== 'off';
        if (!shown) {
            return '';
        }
        const solid = state === 'on';
        const bg = solid ? on : off;
        const fg = solid ? INK : soft;
        const fg2 = solid ? INK2 : off;
        let out = rect(0, 9, 50, 103, bg, 0)
            + rect(5, 14, 16, 5, fg, 1.5)
            + line(5, 25, 26, fg, 4)
            + ring(41, 27, 5, 63, fg, fg2);
        [36, 48, 60, 72, 84].forEach((y, i) => {
            out += icon(5, y, fg2) + line(15, y + 2, 20 - (i % 2) * 5, fg)
                + tick(44, y + 3.5, i < 2, fg);
        });
        return out;
    };

    /* The hero: an image band carrying the course name, its meta line and the
       progress ring at the inline-end. */
    const hero = (state, x, w) => {
        if (state === 'off') {
            return '';
        }
        const solid = state === 'on';
        const bg = solid ? on : off;
        const fg = solid ? INK : soft;
        const fg2 = solid ? INK2 : off;
        return rect(x, 13, w, 26, bg, 3)
            + line(x + 7, 20, w * 0.42, fg, 5)
            + line(x + 7, 29, w * 0.28, fg2, 3)
            + ring(x + w - 15, 26, 7, 63, fg, fg2);
    };

    /* A section card: header icon and title, activity rows with ticks, and the
       footer that carries the count, the bar and the ring. */
    const card = (x, y, w, h, state, rows) => {
        const solid = state === 'on';
        const bg = solid ? soft : faint;
        const fg = solid ? on : soft;
        let out = rect(x, y, w, h, bg, 3) + icon(x + 5, y + 5, fg)
            + line(x + 15, y + 6, w * 0.5, fg, 4);
        // ACF-FIX-2.1.180b: the footer's height is reserved BEFORE the rows are laid out.
        // The first version stopped rows 10px from the bottom and then drew a 10px-tall ring at
        // the same edge, so on a card with four rows the ring sat on top of the last row's tick.
        // Rendering the whole sheet is what showed it; the arithmetic looked fine.
        const FOOT = 17;
        const n = rows === undefined ? 3 : rows;
        for (let i = 0; i < n; i++) {
            const ry = y + 18 + i * 9;
            if (ry + 7 > y + h - FOOT) {
                break;
            }
            out += icon(x + 5, ry, fg) + line(x + 15, ry + 2, w * 0.42, fg)
                + tick(x + w - 9, ry + 3.5, i === 0, fg);
        }
        const fy = y + h - FOOT + 6;
        return out + meter(x + 5, fy + 1, w * 0.4, 63, fg, faint)
            + ring(x + w - 11, fy + 2, 5, 63, fg, faint);
    };

    /* The two-up card grid, which is what a course actually renders. */
    const grid = (state, rows) => card(56, 45, 68, 62, state, rows)
        + card(130, 45, 62, 62, state, rows);

    switch (base) {
        case 'showcourseindex':
        case 'playerindex':
        case 'indexstate':
            return plate + topbar() + sidebar('on') + hero('soft', 56, 136) + grid('off', 2);
        case 'hidegeneral':
            // The General band, present and then not: the thing the setting removes is the thing
            // the picture shows, so the two cards below simply move up.
            return plate + topbar() + sidebar('off') + hero('soft', 56, 136)
                + rect(56, 45, 136, 12, soft, 3) + line(61, 49, 40, on, 4)
                + card(56, 62, 68, 45, 'on', 2) + card(130, 62, 62, 45, 'on', 2);
        case 'showherobanner':
        case 'herobannerfade':
        case 'heroimageoverlay':
        case 'scrimstrength':
            return plate + topbar() + sidebar('off') + hero('on', 56, 136) + grid('off', 2);
        case 'heroattop':
            return plate + hero('on', 0, 200) + topbar(off) + sidebar('off') + grid('off', 2);
        case 'herosticky':
            return plate + topbar() + sidebar('off') + hero('on', 56, 136) + grid('off', 2)
                + '<path d="M196 46 v50 M191 90 l5 6 5-6" stroke="' + on
                + '" stroke-width="2.2" fill="none" stroke-linecap="round"/>';
        case 'displayascards':
        case 'cardlayout':
            return plate + topbar() + sidebar('off') + hero('soft', 56, 136) + grid('on', 3);
        case 'showactivitiesoncards':
        case 'cardactivitylimit':
            // One wide card so the ROWS are the subject: this setting is about what is inside a
            // card, not about how many cards there are.
            return plate + topbar() + sidebar('off') + hero('soft', 56, 136)
                + card(56, 45, 136, 62, 'on', 4);
        case 'cardtitlesize':
            return plate + topbar() + sidebar('off') + hero('soft', 56, 136)
                + rect(56, 45, 136, 62, faint, 3) + line(61, 51, 78, on, 7)
                + line(61, 64, 56, soft, 4) + line(61, 74, 62, off) + line(61, 83, 50, off);
        case 'activitydisplaymode':
            return plate + topbar() + sidebar('off') + hero('soft', 56, 136)
                + card(56, 45, 136, 29, 'on', 1) + card(56, 78, 136, 29, 'off', 1);
        case 'shownavchevrons':
            return plate + topbar() + sidebar('off') + hero('soft', 56, 136) + grid('off', 2)
                + '<circle cx="49" cy="76" r="8" fill="' + on + '"/>'
                + '<path d="M51.5 72.5 l-3.5 3.5 l3.5 3.5" stroke="#fff" stroke-width="1.6"'
                + ' fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
                + '<circle cx="192" cy="76" r="8" fill="' + on + '"/>'
                + '<path d="M189.5 72.5 l3.5 3.5 l-3.5 3.5" stroke="#fff" stroke-width="1.6"'
                + ' fill="none" stroke-linecap="round" stroke-linejoin="round"/>';
        case 'hidesecondarynav':
        case 'coursenavplace':
            return plate + topbar(on) + rect(0, 11, 200, 8, soft, 0)
                + line(6, 13, 22, on, 4) + line(34, 13, 26, on, 4) + line(66, 13, 20, on, 4)
                + sidebar('off')
                + rect(56, 22, 136, 26, off, 3) + line(63, 29, 56, soft, 5)
                + line(63, 38, 38, off, 3) + ring(177, 35, 7, 63, soft, off)
                + card(56, 54, 68, 53, 'off', 2) + card(130, 54, 62, 53, 'off', 2);
        case 'hidebreadcrumb':
            return plate + topbar() + line(6, 13, 26, on) + line(38, 13, 30, on)
                + line(74, 13, 22, on) + sidebar('off') + hero('soft', 56, 136) + grid('off', 2);
        case 'hidefooter':
            return plate + topbar() + sidebar('off') + hero('soft', 56, 136)
                + card(56, 45, 136, 46, 'off', 3) + rect(0, 98, 200, 14, on, 0)
                + line(8, 103, 34, INK) + line(50, 103, 26, INK2);
        case 'immersive':
            return plate + topbar(on) + sidebar('off') + hero('soft', 56, 136) + grid('off', 2);
        case 'hidetimeindex':
        case 'hidetimetotal':
            // The time pills are the subject, so the sidebar is drawn open and they are the only
            // thing in it carrying the accent.
            return plate + topbar() + rect(0, 9, 50, 103, off, 0)
                + [36, 48, 60, 72].map((y) => icon(5, y, soft) + line(15, y + 2, 14, soft)
                    + rect(33, y + 1, 13, 5, on, 2.5)).join('')
                + line(5, 25, 26, soft, 4)
                + hero('soft', 56, 136) + grid('off', 2);
        case 'hidetimesectioncards':
            return plate + topbar() + sidebar('off') + hero('soft', 56, 136) + grid('off', 2)
                + rect(103, 50, 16, 5, on, 2.5) + rect(174, 50, 16, 5, on, 2.5);
        case 'hidetimeactivitycards':
            return plate + topbar() + sidebar('off') + hero('soft', 56, 136)
                + card(56, 45, 136, 62, 'off', 4)
                + [63, 72, 81].map((y) => rect(168, y, 16, 5, on, 2.5)).join('');
        case 'minutes':
        case 'minutesperquestion':
        case 'minutesfallback':
            return plate + topbar() + sidebar('off') + hero('soft', 56, 136)
                + card(56, 45, 136, 62, 'off', 4)
                + [63, 72, 81, 90].map((y) => rect(162, y, 22, 5, on, 2.5)).join('');
        case 'accentcolour':
            return plate + topbar() + sidebar('on') + hero('on', 56, 136) + grid('on', 2);
        case 'indexheadingcolour':
            return plate + topbar() + rect(0, 9, 50, 103, off, 0)
                + rect(3, 22, 44, 10, on, 2) + line(7, 25, 30, INK, 4)
                + rect(3, 62, 44, 10, on, 2) + line(7, 65, 26, INK, 4)
                + [36, 48].map((y) => icon(5, y, soft) + line(15, y + 2, 22, soft)).join('')
                + [76, 88].map((y) => icon(5, y, soft) + line(15, y + 2, 20, soft)).join('')
                + hero('soft', 56, 136) + grid('off', 2);
        case 'enabletutor':
        case 'apikey':
        case 'siteid':
        case 'shareassessmentanswers':
            // A conversation, which is what the tutor is.
            return plate + topbar() + sidebar('off') + hero('soft', 56, 136)
                + rect(56, 47, 74, 16, off, 8) + line(62, 53, 50, soft)
                + rect(112, 68, 80, 16, on, 8) + line(118, 74, 56, INK)
                + rect(56, 89, 62, 14, off, 7) + line(62, 94, 40, soft);
        default:
            return plate + topbar() + sidebar('off') + hero('soft', 56, 136) + grid('off', 2);
    }
};

/**
 * The illustration element for one setting.
 *
 * @param {String} name The setting's own name.
 * @param {Object} t Localised labels.
 * @returns {Element|null}
 */
const buildSettingPreview = (name, t) => {
    const isforce = /^force/.test(name);
    const base = name.replace(/^(default|force)/, '');
    const diagram = settingDiagram(base);

    const wrap = document.createElement('div');
    wrap.className = 'acfs-sfig' + (isforce ? ' acfs-sfig-all' : '');
    wrap.setAttribute('aria-hidden', 'true');

    const one = (label) =>
        '<figure class="acfs-sfigone">'
        // ACF-FIX-2.1.187: width and height stated on the element itself.
        // With only a viewBox, an SVG is a replaced element with no intrinsic size, and a browser
        // falls back to 300x112 -- which is what produced the "large black rectangle" on pages
        // where this plugin's stylesheet was not loaded to size it. The CSS still governs the
        // layout here; these attributes are the floor beneath it, so the element can never render
        // at the browser default no matter what stylesheet is or is not present.
        + '<svg viewBox="0 0 200 112" width="200" height="112" '
        + 'preserveAspectRatio="xMidYMid meet" focusable="false" '
        + 'style="max-width:100%;height:auto;display:block" role="presentation">'
        + diagram + '</svg>'
        + (label ? '<figcaption>' + label + '</figcaption>' : '')
        + '</figure>';

    if (isforce) {
        // Three courses, so "every course that already exists" is shown rather than asserted.
        wrap.innerHTML = one(t.course + ' 1') + one(t.course + ' 2') + one(t.course + ' 3');
        const note = document.createElement('p');
        note.className = 'acfs-sfignote';
        note.textContent = t.appliestoall;
        wrap.appendChild(note);
    } else {
        wrap.innerHTML = one('');
    }
    return wrap;
};

/**
 * Build one collapsed category row.
 *
 * The header is a real <button> with aria-expanded and aria-controls, so the panel is operable and
 * announced correctly from the keyboard. A div with a click handler would look identical and be
 * unusable without a mouse.
 *
 * @param {Object} cat The category.
 * @param {Number} count How many settings it holds.
 * @param {Object} t Localised labels.
 * @returns {Object} {panel, head, body}
 */
const buildPanel = (cat, count, t) => {
    const panel = document.createElement('section');
    panel.className = 'acfs-panel';
    panel.id = 'acfs-' + cat.id;
    panel.style.setProperty('--c', 'var(--acfs-c-' + cat.hue + ')');

    const bodyid = 'acfs-body-' + cat.id;

    const head = document.createElement('button');
    head.type = 'button';
    head.className = 'acfs-head';
    head.setAttribute('aria-expanded', 'false');
    head.setAttribute('aria-controls', bodyid);
    head.innerHTML =
        '<span class="acfs-icon" aria-hidden="true">' + cat.icon + '</span>'
        + '<span class="acfs-headtext">'
        + '<span class="acfs-title"></span>'
        + '<span class="acfs-sub"></span>'
        + '</span>'
        + '<span class="acfs-count"></span>'
        + '<span class="acfs-toggleword" data-open="' + t.hide + '">' + t.show + '</span>'
        + '<span class="acfs-chevwrap" aria-hidden="true">'
        + svg('<path d="m6 9 6 6 6-6"/>', 'acfs-chev') + '</span>';
    head.querySelector('.acfs-title').textContent = cat.label;
    head.querySelector('.acfs-sub').textContent = cat.desc;
    head.querySelector('.acfs-count').textContent =
        count + ' ' + (count === 1 ? t.setting : t.settings);
    panel.appendChild(head);

    // ACF-FIX-2.1.167: no category wireframe here any more. Every setting inside now carries its
    // own diagram, so a second, vaguer picture of the same area at the top of the panel was
    // duplicating the answer and taking the first screenful to do it.
    const body = document.createElement('div');
    body.className = 'acfs-body';
    body.id = bodyid;
    panel.appendChild(body);

    return {panel, head, body};
};

/**
 * Build the search box.
 *
 * @param {Function} onchange Called with the lower-cased query.
 * @param {Object} t Localised labels.
 * @returns {Element}
 */
const buildControls = (onchange, t) => {
    const bar = document.createElement('div');
    bar.className = 'acfs-controls';

    const search = document.createElement('div');
    search.className = 'acfs-search';
    search.innerHTML = svg('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>', '');
    const input = document.createElement('input');
    input.type = 'search';
    input.className = 'acfs-searchinput';
    input.placeholder = t.search;
    input.setAttribute('aria-label', t.search);
    search.appendChild(input);
    bar.appendChild(search);

    let debounce = null;
    input.addEventListener('input', () => {
        window.clearTimeout(debounce);
        debounce = window.setTimeout(() => onchange(input.value.trim().toLowerCase()), 120);
    });

    return bar;
};

/**
 * Build the page.
 *
 * @param {Object} strings Localised labels from PHP.
 * @returns {void}
 */
export const init = (strings) => {
    // Defaults are English so a stale language cache costs a word rather than the page — the lesson
    // the sidebar's close button taught in 2.1.157.
    const t = Object.assign({
        search: 'Search settings…',
        nomatches: 'No settings match that search.',
        settings: 'settings',
        setting: 'setting',
        hint: 'Choose an area to open it. Opening one closes the others.',
        about: 'About this plugin',
        show: 'Show',
        hide: 'Hide',
        course: 'Course',
        appliestoall: 'Applies to every course that already exists'
    }, strings || {});

    // ACF-FIX-2.1.187: this module only ever touches THIS PLUGIN'S settings page.
    //
    // `#adminsettings` is an id Moodle core puts on EVERY admin settings page, so finding it proves
    // nothing about which page this is. Combined with the module being queued from settings.php --
    // a file Moodle includes on every admin page while building the admin tree -- this module was
    // restructuring core's settings pages and unrelated plugins' settings pages on a live site.
    // Reported by the Wombat LMS platform team; the mechanism is written up in settings.php.
    //
    // settings.php now only queues this on the right section. This second check is here because
    // that one is invisible in the rendered page: anything that loads this module by another route
    // (a cached page, an aggregated JS bundle, a future caller) must still be unable to alter a
    // page this plugin does not own. A guard for a site-wide fault belongs on both sides.
    if (document.body.id !== 'page-admin-setting-formatsettingaicourse') {
        return;
    }

    const root = document.getElementById('adminsettings');
    if (!root || root.dataset.acfsDone === '1') {
        return;
    }

    // ACF-FIX-2.1.164: a setting is a row that PRINTS ITS OWN NAME. Everything else is prose.
    //
    // The previous test was "a .form-item with no name is a heading", with the row's `id` as a
    // fallback when `.form-shortname` was absent. Both halves were wrong. Moodle gives headings an
    // `id="admin-<name>"` too, so the fallback classified every heading as a setting; and some of
    // this plugin's introductory blocks are not `.form-item` at all -- they render as a bare <h3>
    // and a <div class="form-description">, which the `.form-item` query never saw, so they were
    // left exactly where they were: above the search box, above the save button, unlabelled.
    //
    // `.form-shortname` is the honest test. It is printed for every real setting and for nothing
    // else, because it exists to show the config name you would put in a config.php override.
    const items = Array.from(root.querySelectorAll('.form-item'));
    const settings = [];
    items.forEach((item) => {
        const shortname = item.querySelector('.form-shortname');
        if (!shortname) {
            return;
        }
        const name = shortname.textContent.replace(/.*\|\s*/, '').trim();
        if (name === '') {
            return;
        }
        settings.push({item, name, cat: categoryOf(name),
            text: (name + ' ' + item.textContent).toLowerCase()});
    });

    if (settings.length < 4) {
        // Not this plugin's settings page, or a page that failed to render. Leave it alone.
        return;
    }
    root.dataset.acfsDone = '1';

    // Everything else the form already contained: headings, descriptions, and any block a future
    // release adds. Captured by exclusion rather than by a list of class names, so a markup change
    // in core cannot leave one of them stranded at the top of the page again. The submit button is
    // excluded here and repositioned separately below.
    const notes = Array.from(root.children).filter((el) =>
        !settings.some((s) => s.item === el || s.item.contains(el) || el.contains(s.item))
        && !el.querySelector('input[type="submit"], button[type="submit"]')
        && !el.matches('input[type="submit"], button[type="submit"], script, style')
        && el.textContent.trim() !== '');

    // Matching order and reading order are different problems. `match` must test Timing before Cards
    // or `hidetimesectioncards` files itself wrongly; `order` is what the reader sees.
    const display = CATEGORIES.slice().sort((a, b) => a.order - b.order);

    const list = document.createElement('div');
    list.className = 'acfs-list';
    const panels = [];

    display.forEach((cat) => {
        const mine = settings.filter((s) => s.cat.id === cat.id);
        if (!mine.length) {
            return;
        }
        const rec = buildPanel(cat, mine.length, t);
        mine.forEach((s, i) => {
            // ACF-FIX-2.1.167: numbered, because the header promises "7 settings" and a reader
            // should be able to see which of the seven they are looking at without counting.
            const num = document.createElement('span');
            num.className = 'acfs-num';
            num.setAttribute('aria-hidden', 'true');
            num.textContent = (i + 1) + '/' + mine.length;
            const label = s.item.querySelector('.form-label');
            if (label) {
                label.insertBefore(num, label.firstChild);
            }

            // The illustration goes in the column beside the setting, which was empty.
            const fig = buildSettingPreview(s.name, t);
            if (fig) {
                s.item.appendChild(fig);
            }
            s.item.classList.add('acfs-setting');
            rec.body.appendChild(s.item);
        });
        rec.rows = mine;
        panels.push(rec);
        list.appendChild(rec.panel);
    });

    /**
     * Open one panel and close the rest.
     *
     * The exclusivity is the design, not a preference: there is no arrangement of sixty-five
     * settings that is calm with all of them on screen.
     *
     * @param {Object|null} which The panel record to open, or null to close everything.
     * @param {Boolean} scroll Whether to bring it into view.
     * @returns {void}
     */
    const open = (which, scroll) => {
        panels.forEach((rec) => {
            const on = rec === which;
            rec.panel.classList.toggle('is-open', on);
            rec.head.setAttribute('aria-expanded', on ? 'true' : 'false');
            const word = rec.head.querySelector('.acfs-toggleword');
            if (word) {
                word.textContent = on ? word.dataset.open : t.show;
            }
        });
        if (which && scroll) {
            // Deferred a frame: the panel has to be open before its position is worth measuring.
            window.requestAnimationFrame(() =>
                which.panel.scrollIntoView({block: 'start', behavior: 'smooth'}));
        }
    };

    panels.forEach((rec) => {
        rec.head.addEventListener('click', () => {
            const wasopen = rec.panel.classList.contains('is-open');
            open(wasopen ? null : rec, !wasopen);
        });
    });

    const empty = document.createElement('p');
    empty.className = 'acfs-empty';
    empty.textContent = t.nomatches;
    empty.hidden = true;

    const hint = document.createElement('p');
    hint.className = 'acfs-hint';
    hint.textContent = t.hint;

    /**
     * Filter by the search query.
     *
     * Searching suspends the one-at-a-time rule: someone who has typed a word is looking for a
     * specific setting and does not know which area it is in, so every panel with a match opens.
     * Clearing the box collapses everything back to the ten rows.
     *
     * @param {String} query Lower-cased search text.
     * @returns {void}
     */
    const apply = (query) => {
        let shown = 0;
        panels.forEach((rec) => {
            let hits = 0;
            rec.rows.forEach((s) => {
                const on = !query || s.text.indexOf(query) !== -1;
                s.item.hidden = !on;
                if (on) {
                    hits++;
                }
            });
            shown += hits;
            rec.panel.hidden = Boolean(query) && hits === 0;
            if (query) {
                rec.panel.classList.toggle('is-open', hits > 0);
                rec.head.setAttribute('aria-expanded', hits > 0 ? 'true' : 'false');
            }
        });
        if (!query) {
            open(null, false);
        }
        empty.hidden = shown !== 0 || !query;
        hint.hidden = Boolean(query);
    };

    const controls = buildControls(apply, t);

    // ACF-FIX-2.1.164: the notes get a home and a name.
    //
    // Moodle renders admin_setting_heading and admin_setting_description as .form-item rows with no
    // control in them. Left loose at the top of the page they read as an unlabelled preamble --
    // three headings and four paragraphs with nothing saying what they are or why they come first.
    // They are collected into one titled block instead, so the page opens with "About this plugin"
    // and then the areas, rather than with prose the reader has to classify for themselves.
    let intro = null;
    if (notes.length) {
        intro = document.createElement('section');
        intro.className = 'acfs-intro';

        const ihead = document.createElement('h2');
        ihead.className = 'acfs-introtitle';
        ihead.textContent = t.about;
        intro.appendChild(ihead);

        notes.forEach((n) => {
            n.classList.add('acfs-note');
            intro.appendChild(n);
        });
    }

    root.appendChild(controls);
    if (intro) {
        root.appendChild(intro);
    }
    root.appendChild(hint);
    root.appendChild(empty);
    root.appendChild(list);

    // ACF-FIX-2.1.164: Save goes to the bottom, where it belongs.
    //
    // #adminsettings IS the form, and Moodle's submit button is one of its children. Everything this
    // module builds is APPENDED to that form -- so the button, which was already in the DOM, ended
    // up above all of it: directly under the introduction and above every setting it saves. A save
    // control positioned before the things it saves is worse than no styling at all.
    //
    // Moved last and made sticky. Sixty-five settings is a long page, and a button that scrolls away
    // means scrolling back to the bottom to commit a change made at the top.
    const submit = root.querySelector('input[type="submit"], button[type="submit"]');
    if (submit) {
        const holder = submit.closest('.form-buttons, .felement, div') || submit;
        holder.classList.add('acfs-save');
        root.appendChild(holder);
    }

    /**
     * Follow a #admin-<name> link: open the panel that holds it, then flash the row.
     *
     * Scrolling someone to a setting and leaving them to work out which one it was is half a
     * feature — and with the panels collapsed, the target is not even on screen until its own panel
     * is opened first.
     *
     * @returns {void}
     */
    const jump = () => {
        const hash = window.location.hash;
        if (!hash || hash.indexOf('#admin-') !== 0) {
            return;
        }
        const name = hash.slice('#admin-'.length);
        const rec = panels.find((r) => r.rows.some((s) => s.name === name));
        if (!rec) {
            return;
        }
        const row = rec.rows.find((s) => s.name === name);
        open(rec, false);
        row.item.hidden = false;
        row.item.classList.remove('acfs-flash');
        void row.item.offsetWidth;
        row.item.classList.add('acfs-flash');
        window.requestAnimationFrame(() =>
            row.item.scrollIntoView({block: 'center', behavior: 'smooth'}));
    };

    window.addEventListener('hashchange', jump);
    if (window.location.hash) {
        window.setTimeout(jump, 60);
    }
};
