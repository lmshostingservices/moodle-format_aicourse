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
 * Category tabs and search for the plugin's settings page.
 *
 * @module     format_aicourse/settingsui
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Which category each setting belongs to.
 *
 * ACF-FIX-2.1.134. Fifty-nine settings in one column is a scroll, not a page: finding the card
 * colour means either knowing roughly where it sits or reading past forty things that are not it.
 *
 * Matching is by the setting's own name rather than a hand-kept list of every setting, so a new
 * one lands in the right place on the strength of what it is called -- `defaultcardpadding` would
 * file itself under Cards without anyone editing this. Order matters: the first pattern that
 * matches wins, so the specific ones come before the general.
 *
 * Anything unmatched goes to "Other" rather than disappearing, because a setting the reader cannot
 * reach is worse than one in the wrong group.
 */
const CATEGORIES = [
    // ACF-FIX-2.1.147: order is the whole design here, because the first match wins.
    //
    // Timing comes first. `hidetimesectioncards` was landing in Cards and `hidetimeindex` in Course
    // index -- both match those patterns before reaching Timing, and both are timing settings. That
    // is why Timing showed 3 when it holds far more.
    {id: 'time', label: 'Timing', icon: '\u25F7',
        match: [/minutes/, /timing/, /hidetime/]},
    // The tutor next: its settings name themselves clearly and nothing else should claim them.
    {id: 'tutor', label: 'AI Tutor', icon: '\u2728',
        match: [/tutor/, /apikey/, /siteid/, /assessmentanswers/, /aiassistant/, /externalservice/,
            /adminreportlink/]},
    {id: 'tour', label: 'Guided tour', icon: '\u25CE',
        match: [/tour/]},
    // Colour before the areas it applies to, so every colour is in one place -- someone theming a
    // site wants them together rather than scattered across four tabs.
    {id: 'colour', label: 'Colour', icon: '\u25D0',
        match: [/colour/, /opacity/, /scrim/, /fade/, /overlay/]},
    {id: 'index', label: 'Course index', icon: '\u2630',
        match: [/playerindex/, /indexstate/, /playerlogo/, /playerheader/, /showcourseindex/,
            /^index/, /forceindex/, /defaultindex/]},
    {id: 'banner', label: 'Banner', icon: '\u25AD',
        match: [/herobanner/, /heroattop/, /heroimage/, /herosticky/, /showherobanner/, /hero/]},
    {id: 'cards', label: 'Cards', icon: '\u25A6',
        match: [/card/, /displayascards/, /activitydisplaymode/, /showactivities/]},
    {id: 'nav', label: 'Navigation', icon: '\u21F1',
        match: [/secondarynav/, /coursenavplace/, /immersive/, /hidefooter/, /hidebreadcrumb/,
            /hidegeneral/, /navchevrons/]},
];

/**
 * The second filter axis: what kind of setting this is.
 *
 * ACF-FIX-2.1.135. Categories answer "where does this apply"; these answer "what does it do to my
 * site", which is a different question an administrator asks constantly:
 *
 * - **Overrides** is the one that earns its place. Every `force…` setting changes courses that
 *   already exist, and every `default…` only seeds new ones. That distinction has caused more
 *   confusion than anything else in this plugin, and being able to list exactly the settings that
 *   reach existing courses answers it directly.
 * - **New** marks what arrived recently, so someone returning to the page can see what changed
 *   without reading the changelog.
 *
 * The first two are derived from the setting's name and cannot drift. `New` is the exception -- it
 * is a list, and a list goes stale. It is passed in from PHP rather than hard-coded here so it sits
 * beside the settings it describes, and it is deliberately short: a permanent "new" badge on
 * everything would mean nothing.
 */
const KINDS = [
    {id: 'override', label: 'Affects existing courses', icon: '⚠',
        test: (name) => /^force/.test(name)},
    {id: 'default', label: 'New courses only', icon: '＋',
        test: (name) => /^default/.test(name)},
];

/**
 * The setting's own name, as the plugin prints it under each label.
 *
 * @param {Element} item The rendered setting.
 * @returns {string}
 */
const nameOf = (item) => {
    const shortname = item.querySelector('.form-shortname');
    return shortname ? shortname.textContent.replace(/.*\|\s*/, '').trim() : '';
};

/**
 * Work out which category a settings row belongs to.
 *
 * @param {Element} item The rendered setting.
 * @returns {string} A category id, or 'other'.
 */
const categoryOf = (item) => {
    // ACF-FIX-2.1.147: only real settings are categorised, and only on their own name.
    //
    // A section heading renders as a `.form-item` with no setting name. Those were being matched
    // against their whole text, so a heading landed in whichever category its prose happened to
    // mention -- and they were counted as settings, which is why the totals never matched the
    // number of things a reader can actually change.
    //
    // Matching on the name alone also stops a setting being filed by a word in its description:
    // "the accent colour is used for card headings" should not put an accent setting under Cards.
    const name = nameOf(item);
    if (name === '') {
        return null;
    }
    for (const cat of CATEGORIES) {
        for (const pattern of cat.match) {
            if (pattern.test(name)) {
                return cat.id;
            }
        }
    }
    return 'other';
};

/**
 * Build the tab bar and the search box.
 *
 * @param {Object} strings Localised labels.
 * @param {Element[]} items Every settings row on the page.
 * @param {Object} counts How many settings each category holds.
 * @returns {Element}
 */
const buildControls = (strings, items, counts) => {
    const bar = document.createElement('div');
    bar.className = 'aicourse-settings-controls';

    const search = document.createElement('div');
    search.className = 'aicourse-settings-search';
    const input = document.createElement('input');
    input.type = 'search';
    input.className = 'aicourse-settings-search-input';
    input.setAttribute('placeholder', strings.searchplaceholder);
    input.setAttribute('aria-label', strings.searchplaceholder);
    search.appendChild(input);

    const tabs = document.createElement('div');
    tabs.className = 'aicourse-settings-tabs';
    tabs.setAttribute('role', 'tablist');

    const makeTab = (id, label, icon, count) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'aicourse-settings-tab';
        b.dataset.category = id;
        b.setAttribute('role', 'tab');
        b.setAttribute('aria-selected', id === 'all' ? 'true' : 'false');
        b.innerHTML = '';
        const ic = document.createElement('span');
        ic.className = 'aicourse-settings-tab-icon';
        ic.setAttribute('aria-hidden', 'true');
        ic.textContent = icon;
        const tx = document.createElement('span');
        tx.textContent = label;
        const ct = document.createElement('span');
        ct.className = 'aicourse-settings-tab-count';
        ct.textContent = String(count);
        b.appendChild(ic);
        b.appendChild(tx);
        b.appendChild(ct);
        if (id === 'all') {
            b.classList.add('is-active');
        }
        return b;
    };

    tabs.appendChild(makeTab('all', strings.all, '◈', items.length));
    CATEGORIES.forEach((c) => {
        if (counts[c.id]) {
            tabs.appendChild(makeTab(c.id, c.label, c.icon, counts[c.id]));
        }
    });
    if (counts.other) {
        tabs.appendChild(makeTab('other', strings.other, '·', counts.other));
    }

    // The second row. Separated from the categories by a label, because two rows of chips with no
    // explanation reads as one long list of unrelated filters.
    const kinds = document.createElement('div');
    kinds.className = 'aicourse-settings-kinds';
    const kindLabel = document.createElement('span');
    kindLabel.className = 'aicourse-settings-kinds-label';
    kindLabel.textContent = strings.filterby;
    kinds.appendChild(kindLabel);

    const makeChip = (id, label, icon, count) => {
        const c = document.createElement('button');
        c.type = 'button';
        c.className = 'aicourse-settings-chip';
        c.dataset.kind = id;
        c.setAttribute('aria-pressed', 'false');
        const ic = document.createElement('span');
        ic.setAttribute('aria-hidden', 'true');
        ic.textContent = icon + ' ';
        const tx = document.createElement('span');
        tx.textContent = label + ' (' + count + ')';
        c.appendChild(ic);
        c.appendChild(tx);
        return c;
    };

    KINDS.forEach((k) => {
        const count = items.filter((i) => k.test(nameOf(i))).length;
        if (count) {
            kinds.appendChild(makeChip(k.id, k.label, k.icon, count));
        }
    });
    const newCount = items.filter((i) => (strings.recent || []).indexOf(nameOf(i)) !== -1).length;
    if (newCount) {
        kinds.appendChild(makeChip('new', strings.newlabel, '●', newCount));
    }

    bar.appendChild(search);
    bar.appendChild(tabs);
    if (kinds.children.length > 1) {
        bar.appendChild(kinds);
    }
    return bar;
};

/**
 * Apply the current category and search term.
 *
 * @param {Element[]} items Every settings row.
 * @param {string} category The selected category id.
 * @param {string} term The search term, lower case.
 * @param {Element} empty The "nothing matched" message.
 * @param {string} kind The selected kind chip, or '' for none.
 * @param {string[]} recent Setting names counted as recently added.
 * @returns {void}
 */
const applyFilter = (items, category, term, empty, kind, recent) => {
    let shown = 0;
    items.forEach((item) => {
        const inCategory = category === 'all' || item.dataset.aicourseCat === category;
        // The kind chips are a toggle, not a second tab bar: with none pressed everything passes.
        let inKind = true;
        if (kind === 'override') {
            inKind = /^force/.test(item.dataset.aicourseName || '');
        } else if (kind === 'default') {
            inKind = /^default/.test(item.dataset.aicourseName || '');
        } else if (kind === 'new') {
            inKind = (recent || []).indexOf(item.dataset.aicourseName || '') !== -1;
        }
        // Searching the whole row rather than only the label, so a term that appears in a
        // description finds its setting -- someone looking for "answer key" should reach the
        // sharing ceiling even though those words are not in its name.
        const matches = term === '' || (item.textContent || '').toLowerCase().indexOf(term) !== -1;
        const show = inCategory && inKind && matches;
        item.style.display = show ? '' : 'none';
        if (show) {
            shown++;
        }
    });
    empty.style.display = shown === 0 ? '' : 'none';

    // Section headings only make sense above settings that are visible.
    document.querySelectorAll('#adminsettings h3').forEach((h) => {
        let next = h.nextElementSibling;
        let any = false;
        while (next && next.tagName !== 'H3') {
            if (next.classList && next.classList.contains('form-item') && next.style.display !== 'none') {
                any = true;
                break;
            }
            next = next.nextElementSibling;
        }
        h.style.display = any ? '' : 'none';
    });
};

/**
 * Start.
 *
 * @param {Object} strings Localised labels.
 * @returns {void}
 */
export const init = (strings) => {
    const root = document.querySelector('#adminsettings');
    if (!root) {
        return;
    }
    const items = Array.from(root.querySelectorAll('.form-item'));
    if (items.length < 8) {
        // Too few to be worth filtering; the controls would cost more than they save.
        return;
    }

    const counts = {};
    const settings = [];
    items.forEach((item) => {
        const cat = categoryOf(item);
        if (cat === null) {
            // A heading, not a setting. Left visible under "All settings" but never counted and
            // never filtered into a category.
            item.dataset.aicourseHeading = '1';
            return;
        }
        item.dataset.aicourseCat = cat;
        item.dataset.aicourseName = nameOf(item);
        counts[cat] = (counts[cat] || 0) + 1;
        settings.push(item);
    });

    const controls = buildControls(strings, settings, counts);
    root.parentNode.insertBefore(controls, root);

    const empty = document.createElement('p');
    empty.className = 'aicourse-settings-empty';
    empty.textContent = strings.nomatches;
    empty.style.display = 'none';
    root.parentNode.insertBefore(empty, root);

    let category = 'all';
    let term = '';
    let kind = '';

    controls.querySelector('.aicourse-settings-search-input').addEventListener('input', (e) => {
        term = e.target.value.trim().toLowerCase();
        applyFilter(items, category, term, empty, kind, strings.recent);
    });

    controls.querySelectorAll('.aicourse-settings-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            // Pressing the active chip clears it, so the filter can always be undone without
            // hunting for an "all" option that does not exist on this row.
            kind = kind === chip.dataset.kind ? '' : chip.dataset.kind;
            controls.querySelectorAll('.aicourse-settings-chip').forEach((c) => {
                const on = c.dataset.kind === kind;
                c.classList.toggle('is-active', on);
                c.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            applyFilter(items, category, term, empty, kind, strings.recent);
        });
    });

    controls.querySelectorAll('.aicourse-settings-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            category = tab.dataset.category;
            controls.querySelectorAll('.aicourse-settings-tab').forEach((t) => {
                t.classList.toggle('is-active', t === tab);
                t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
            });
            applyFilter(items, category, term, empty, kind, strings.recent);
            // Back to the top of the list, or switching to a short category leaves the reader
            // looking at blank space where the previous one used to be.
            controls.scrollIntoView({block: 'start', behavior: 'smooth'});
        });
    });
};
