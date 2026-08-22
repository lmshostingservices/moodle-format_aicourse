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
    '<svg class="' + cls + '" viewBox="0 0 24 24" aria-hidden="true" focusable="false" '
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
    const plate = rect(0, 0, 200, 112, 'rgb(var(--c) / 0.05)', 6);
    const side = (f) => rect(0, 0, 50, 112, f, 6);
    const body = (f) => rect(58, 34, 64, 34, f) + rect(128, 34, 64, 34, f)
        + rect(58, 74, 64, 30, f) + rect(128, 74, 64, 30, f);
    const bar = (f) => rect(58, 6, 134, 22, f);

    switch (base) {
        case 'showcourseindex':
        case 'playerindex':
        case 'indexstate':
            return plate + side(on) + rect(8, 12, 34, 5, 'rgb(255 255 255 / 0.75)')
                + rect(8, 26, 28, 4, 'rgb(255 255 255 / 0.5)')
                + rect(8, 36, 34, 4, 'rgb(255 255 255 / 0.5)')
                + rect(8, 46, 30, 4, 'rgb(255 255 255 / 0.5)')
                + bar(off) + body(off);
        case 'hidegeneral':
            return plate + side(off) + bar(off)
                + rect(58, 34, 134, 14, soft) + rect(58, 54, 64, 24, on) + rect(128, 54, 64, 24, on)
                + rect(58, 84, 64, 20, on) + rect(128, 84, 64, 20, on);
        case 'showherobanner':
        case 'herobannerfade':
        case 'heroimageoverlay':
        case 'scrimstrength':
            return plate + side(off) + bar(on)
                + rect(66, 12, 58, 5, 'rgb(255 255 255 / 0.8)')
                + rect(66, 21, 36, 3, 'rgb(255 255 255 / 0.5)') + body(off);
        case 'heroattop':
            return plate + rect(0, 0, 200, 24, on, 6) + side(off) + rect(58, 30, 134, 8, soft)
                + body(off);
        case 'herosticky':
            return plate + side(off) + bar(on)
                + rect(58, 34, 134, 6, soft) + body(off)
                + '<path d="M182 44 v54 M176 92 l6 6 6-6" stroke="' + on
                + '" stroke-width="2.5" fill="none" stroke-linecap="round"/>';
        case 'displayascards':
        case 'cardlayout':
            return plate + side(off) + bar(off)
                + rect(58, 34, 64, 34, on) + rect(128, 34, 64, 34, on)
                + rect(58, 74, 64, 30, soft) + rect(128, 74, 64, 30, soft);
        case 'showactivitiesoncards':
        case 'cardactivitylimit':
            return plate + side(off) + bar(off)
                + rect(58, 34, 134, 70, soft)
                + rect(66, 42, 60, 6, on) + rect(66, 54, 118, 5, on)
                + rect(66, 64, 100, 5, on) + rect(66, 74, 110, 5, on);
        case 'cardtitlesize':
            return plate + side(off) + bar(off)
                + rect(58, 36, 90, 12, on) + rect(58, 56, 70, 7, soft)
                + rect(58, 72, 134, 30, off);
        case 'activitydisplaymode':
            return plate + side(off) + bar(off)
                + rect(58, 34, 134, 15, on) + rect(58, 54, 134, 15, soft)
                + rect(58, 74, 134, 15, soft);
        case 'shownavchevrons':
            return plate + side(off) + bar(off) + body(off)
                + rect(58, 92, 30, 12, on) + rect(162, 92, 30, 12, on);
        case 'hidesecondarynav':
        case 'coursenavplace':
            return plate + rect(0, 0, 200, 10, soft, 6) + rect(0, 14, 200, 8, on, 0)
                + side(off) + rect(58, 30, 134, 74, off);
        case 'hidebreadcrumb':
            return plate + rect(6, 6, 90, 5, on) + side(off) + bar(off) + body(off);
        case 'hidefooter':
            return plate + side(off) + bar(off) + rect(58, 34, 134, 54, off)
                + rect(0, 100, 200, 12, on, 0);
        case 'immersive':
            return plate + rect(0, 0, 200, 14, on, 6) + side(off) + bar(off) + body(off);
        case 'hidetimeindex':
        case 'hidetimetotal':
            return plate + side(off) + rect(8, 20, 22, 7, on, 3.5) + rect(8, 34, 22, 7, on, 3.5)
                + rect(8, 48, 22, 7, on, 3.5) + bar(off) + body(off);
        case 'hidetimesectioncards':
            return plate + side(off) + bar(off) + body(off)
                + rect(96, 40, 22, 7, on, 3.5) + rect(166, 40, 22, 7, on, 3.5);
        case 'hidetimeactivitycards':
            return plate + side(off) + bar(off) + rect(58, 34, 134, 15, off)
                + rect(58, 54, 134, 15, off) + rect(160, 38, 26, 7, on, 3.5)
                + rect(160, 58, 26, 7, on, 3.5);
        case 'minutes':
        case 'minutesperquestion':
        case 'minutesfallback':
            return plate + side(off) + bar(off) + rect(58, 34, 134, 15, off)
                + rect(150, 38, 36, 7, on, 3.5) + rect(58, 54, 134, 15, off)
                + rect(150, 58, 36, 7, on, 3.5) + rect(58, 74, 134, 15, off)
                + rect(150, 78, 36, 7, on, 3.5);
        case 'accentcolour':
            return plate + side(on) + bar(on) + rect(58, 34, 64, 34, on)
                + rect(128, 34, 64, 34, soft) + rect(58, 74, 134, 30, soft);
        case 'indexheadingcolour':
            return plate + side(off) + rect(4, 8, 42, 12, on, 2) + rect(4, 46, 42, 12, on, 2)
                + bar(off) + body(off);
        case 'indexiconcolour':
            return plate + side(off) + rect(8, 14, 8, 8, on, 2) + rect(8, 28, 8, 8, on, 2)
                + rect(8, 42, 8, 8, on, 2) + rect(8, 56, 8, 8, on, 2) + bar(off) + body(off);
        case 'indexcolour':
        case 'indexopacity':
        case 'playerheadercolour':
            return plate + side(on) + bar(off) + body(off);
        case 'cardcolour':
        case 'cardopacity':
            return plate + side(off) + bar(off) + body(on);
        case 'playerlogo':
            return plate + side(off) + rect(8, 10, 34, 10, on, 2) + bar(off) + body(off);
        case 'colourmode':
            return rect(0, 0, 100, 112, 'rgb(var(--c) / 0.08)', 6)
                + rect(100, 0, 100, 112, 'rgb(var(--c) / 0.55)', 6)
                + rect(10, 20, 80, 30, soft) + rect(110, 20, 80, 30, 'rgb(255 255 255 / 0.35)');
        case 'tourvoiceover':
        case 'tourvoice':
            return plate + side(off) + bar(off) + body(off)
                + '<circle cx="100" cy="56" r="24" fill="' + soft + '"/>'
                + '<circle cx="100" cy="56" r="12" fill="' + on + '"/>';
        case 'enabletutor':
        case 'apikey':
        case 'siteid':
        case 'shareassessmentanswers':
            return plate + side(off) + bar(off) + rect(58, 34, 78, 16, off, 8)
                + rect(96, 58, 96, 18, on, 9) + rect(58, 84, 62, 14, off, 7);
        default:
            return plate + side(off) + bar(off) + body(soft);
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
        + '<svg viewBox="0 0 200 112" preserveAspectRatio="xMidYMid meet" focusable="false">'
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
