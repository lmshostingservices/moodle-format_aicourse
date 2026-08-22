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
        desc: 'How long each activity is estimated to take, and which of the four places show it.',
        match: [/minutes/, /timing/, /hidetime/],
    },
    {
        id: 'tutor', order: 8, label: 'AI Tutor', icon: '✦', hue: 'tutor',
        desc: 'The external AI service, its credentials, and what course content is sent to it.',
        match: [/tutor/, /apikey/, /siteid/, /assessmentanswers/, /aiassistant/, /externalservice/,
            /adminreportlink/],
    },
    {
        id: 'tour', order: 9, label: 'Guided tour', icon: '◎', hue: 'tour',
        desc: 'The first-run tour and its spoken narration.',
        match: [/tour/],
    },
    {
        id: 'colour', order: 6, label: 'Colours & branding', icon: '◐', hue: 'colour',
        desc: 'Every colour the format publishes, plus the sidebar logo and light/dark mode.',
        match: [/colour/, /opacity/, /scrim/, /fade/, /overlay/, /playerlogo/, /colourmode/],
    },
    {
        id: 'index', order: 1, label: 'Course index', icon: '☰', hue: 'index',
        desc: 'Where the course index appears, how it opens, and the player sidebar.',
        match: [/playerindex/, /indexstate/, /playerheader/, /showcourseindex/, /^index/,
            /forceindex/, /defaultindex/, /hidegeneral/],
    },
    {
        id: 'banner', order: 2, label: 'Hero banner', icon: '▭', hue: 'banner',
        desc: 'The banner at the top of a course: whether it shows, where it sits, how it scrolls.',
        match: [/herobanner/, /heroattop/, /heroimage/, /herosticky/, /showherobanner/, /hero/],
    },
    {
        id: 'cards', order: 3, label: 'Section cards', icon: '▦', hue: 'cards',
        desc: 'How sections render on the course home page.',
        match: [/displayascards/, /cardlayout/, /cardtitlesize/, /cardactivitylimit/,
            /showactivities/, /card/],
    },
    {
        id: 'activity', order: 4, label: 'Activity display', icon: '▤', hue: 'activity',
        desc: 'How activities render inside a section, and next/previous navigation.',
        match: [/activitydisplaymode/, /navchevrons/],
    },
    {
        id: 'nav', order: 5, label: 'Navigation & chrome', icon: '⇱', hue: 'nav',
        desc: "Moodle's own tabs, breadcrumb, footer and logo band on course pages.",
        match: [/secondarynav/, /coursenavplace/, /immersive/, /hidefooter/, /hidebreadcrumb/],
    },
    {
        id: 'other', order: 10, label: 'Other', icon: '⚙', hue: 'other',
        desc: 'Anything not claimed by a category above.',
        match: [],
    }
];

/**
 * The setting's own name, as Moodle prints it under each label.
 *
 * @param {Element} item A rendered .form-item.
 * @returns {String} The bare name, or '' for a heading or description block.
 */
const nameOf = (item) => {
    const shortname = item.querySelector('.form-shortname');
    if (shortname) {
        return shortname.textContent.replace(/.*\|\s*/, '').trim();
    }
    // Fallback for themes that do not print the short name: Moodle's own row id.
    const id = item.getAttribute('id') || '';
    return id.indexOf('admin-') === 0 ? id.slice('admin-'.length) : '';
};

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
    '<svg class="' + cls + '" viewBox="0 0 24 24" aria-hidden="true" focusable="false" '
    + 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
    + 'stroke-linejoin="round">' + d + '</svg>';

/* --------------------------------------------------------------------------
   The previews (ACF-FIX-2.1.159)

   A settings page for a course format has a hard problem: the names are abstract. "Activity display
   mode", "Banner at top of page", "Course index on first entry" — a reader who has not already seen
   the difference cannot tell what any of them will do, and the description underneath is a sentence
   trying to describe a layout.

   So each card shows the layout. These are schematic wireframes of the course page with the part
   this category governs picked out in the category's own colour and everything else greyed back:
   the sidebar for Course index, the band across the top for Hero banner, the grid of tiles for
   Section cards. Not screenshots — a screenshot would be stale the moment the format changed, would
   need hosting, and would carry one site's colours. These are drawn from the same tokens the cards
   use, weigh a few hundred bytes each, stay sharp at any zoom, and follow light and dark.

   Everything is `aria-hidden`: the card's title and description already say what it is, and a
   screen reader gaining "rectangle rectangle rectangle" is a loss, not a gain.
   -------------------------------------------------------------------------- */

const PREVIEW_BASE = 'rgb(var(--c) / 0.16)';

/**
 * One wireframe, as inline SVG markup.
 *
 * The viewBox is a 200x112 page. `fill="currentColor"` picks up the muted page furniture; anything
 * the category actually controls is filled with the hue instead, so the eye lands on it first.
 *
 * @param {String} id A category id.
 * @returns {String} SVG markup.
 */
const preview = (id) => {
    const hue = 'rgb(var(--c) / 0.85)';
    const soft = 'rgb(var(--c) / 0.32)';
    const mute = PREVIEW_BASE;
    const r = (x, y, w, h, f, rd) => '<rect x="' + x + '" y="' + y + '" width="' + w + '" height="'
        + h + '" rx="' + (rd === undefined ? 2 : rd) + '" fill="' + f + '"/>';

    // Shared furniture: the page plate and a header strip.
    const plate = r(0, 0, 200, 112, 'rgb(var(--c) / 0.05)', 6);
    const grid = (f1, f2) => r(58, 34, 64, 34, f1) + r(128, 34, 64, 34, f1)
        + r(58, 74, 64, 30, f2) + r(128, 74, 64, 30, f2);

    switch (id) {
        case 'index':
            // The sidebar, picked out; the content beside it greyed.
            return plate + r(0, 0, 50, 112, hue, 6) + r(8, 10, 34, 6, 'rgb(255 255 255 / 0.75)')
                + r(8, 24, 26, 4, 'rgb(255 255 255 / 0.5)') + r(8, 34, 34, 4, 'rgb(255 255 255 / 0.5)')
                + r(8, 44, 30, 4, 'rgb(255 255 255 / 0.5)') + r(8, 54, 34, 4, 'rgb(255 255 255 / 0.5)')
                + r(8, 64, 24, 4, 'rgb(255 255 255 / 0.5)')
                + r(58, 8, 134, 18, mute) + grid(mute, mute);
        case 'banner':
            // The band across the top, picked out.
            return plate + r(0, 0, 50, 112, mute, 6) + r(58, 6, 134, 24, hue)
                + r(66, 13, 60, 5, 'rgb(255 255 255 / 0.8)')
                + r(66, 22, 38, 3, 'rgb(255 255 255 / 0.55)')
                + grid(mute, mute);
        case 'cards':
            // The grid of section tiles.
            return plate + r(0, 0, 50, 112, mute, 6) + r(58, 8, 134, 16, mute)
                + r(58, 32, 64, 34, hue) + r(128, 32, 64, 34, hue)
                + r(58, 72, 64, 32, soft) + r(128, 72, 64, 32, soft);
        case 'activity':
            // Rows inside a section, with a next/previous chevron pair.
            return plate + r(0, 0, 50, 112, mute, 6) + r(58, 8, 134, 16, mute)
                + r(58, 32, 134, 14, hue) + r(58, 50, 134, 14, soft)
                + r(58, 68, 134, 14, soft) + r(58, 90, 30, 12, hue) + r(162, 90, 30, 12, hue);
        case 'nav':
            // The chrome: header band, tab strip, breadcrumb, footer.
            return plate + r(0, 0, 200, 12, hue, 0) + r(0, 16, 200, 8, soft, 0)
                + r(6, 28, 60, 4, soft) + r(0, 0, 50, 112, mute, 6)
                + r(58, 38, 134, 44, mute) + r(0, 102, 200, 10, hue, 0);
        case 'colour':
            // Swatches over a plate: the one category whose subject is colour itself.
            return plate + r(0, 0, 50, 112, hue, 6)
                + r(58, 8, 134, 18, soft) + r(58, 34, 40, 34, hue) + r(104, 34, 40, 34, soft)
                + r(150, 34, 42, 34, mute) + r(58, 74, 134, 30, soft);
        case 'time':
            // The four places a duration pill can appear, as pills.
            return plate + r(0, 0, 50, 112, mute, 6) + r(8, 24, 22, 7, hue, 3.5)
                + r(8, 40, 22, 7, hue, 3.5) + r(58, 8, 134, 16, mute)
                + r(58, 32, 64, 34, mute) + r(128, 32, 64, 34, mute)
                + r(96, 38, 22, 7, hue, 3.5) + r(166, 38, 22, 7, hue, 3.5)
                + r(58, 74, 134, 30, mute);
        case 'tutor':
            // A conversation: the tutor is the only category that is not a layout.
            return plate + r(0, 0, 50, 112, mute, 6) + r(58, 8, 134, 16, mute)
                + r(58, 32, 78, 16, mute, 8) + r(96, 56, 96, 18, hue, 9)
                + r(58, 82, 62, 14, mute, 7)
                + '<circle cx="176" cy="98" r="10" fill="' + hue + '"/>';
        case 'tour':
            // A spotlight moving across the page.
            return plate + r(0, 0, 50, 112, mute, 6) + r(58, 8, 134, 16, mute)
                + grid(mute, mute)
                + '<circle cx="90" cy="51" r="22" fill="' + soft + '"/>'
                + '<circle cx="90" cy="51" r="11" fill="' + hue + '"/>';
        default:
            return plate + r(0, 0, 50, 112, mute, 6) + r(58, 8, 134, 16, mute) + grid(soft, mute);
    }
};

/**
 * The preview element for a card.
 *
 * @param {String} id A category id.
 * @returns {Element}
 */
const buildPreview = (id) => {
    const wrap = document.createElement('div');
    wrap.className = 'acfs-preview';
    wrap.setAttribute('aria-hidden', 'true');
    wrap.innerHTML = '<svg viewBox="0 0 200 112" preserveAspectRatio="xMidYMid meet" '
        + 'focusable="false">' + preview(id) + '</svg>';
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
        + svg('<path d="m6 9 6 6 6-6"/>', 'acfs-chev');
    head.querySelector('.acfs-title').textContent = cat.label;
    head.querySelector('.acfs-sub').textContent = cat.desc;
    head.querySelector('.acfs-count').textContent =
        count + ' ' + (count === 1 ? t.setting : t.settings);
    panel.appendChild(head);

    const body = document.createElement('div');
    body.className = 'acfs-body';
    body.id = bodyid;
    body.appendChild(buildPreview(cat.id));
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
        hint: 'Choose an area to open it. Opening one closes the others.'
    }, strings || {});

    const root = document.getElementById('adminsettings');
    if (!root || root.dataset.acfsDone === '1') {
        return;
    }

    const items = Array.from(root.querySelectorAll('.form-item'));
    if (items.length < 4) {
        return;
    }
    root.dataset.acfsDone = '1';

    // A heading has no setting name. It is a divider, not a setting, and counting it as one is why
    // the old page's totals never matched what a reader could actually change.
    const settings = [];
    const notes = [];
    items.forEach((item) => {
        const name = nameOf(item);
        if (name === '') {
            notes.push(item);
            return;
        }
        settings.push({item, name, cat: categoryOf(name),
            text: (name + ' ' + item.textContent).toLowerCase()});
    });

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
        mine.forEach((s) => rec.body.appendChild(s.item));
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

    const notesWrap = document.createDocumentFragment();
    notes.forEach((n) => {
        n.classList.add('acfs-note');
        notesWrap.appendChild(n);
    });

    root.appendChild(controls);
    root.appendChild(notesWrap);
    root.appendChild(hint);
    root.appendChild(empty);
    root.appendChild(list);

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
