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
 * Turns an AI Tutor answer (Markdown plus a few tutor-specific blocks) into DOM nodes.
 *
 * 3.2.0. Answers used to be written into the bubble as one escaped text node with no line-break
 * handling, so a multiple-choice question, a checklist or a numbered list all collapsed into a
 * single paragraph with the raw Markdown symbols showing.
 *
 * SECURITY: the answer is untrusted. Nothing here parses HTML. Every node is made with
 * document.createElement and every piece of answer text lands in the page through
 * document.createTextNode / textContent, so markup in an answer is shown as text, exactly as it was
 * before. Links are only produced for http, https and mailto URLs.
 *
 * What is understood:
 *  - paragraphs (single newlines kept as line breaks), headings, horizontal rules
 *  - **bold**, *italic*, ~~strike~~, `code`, [links](https://...), bare https:// URLs
 *  - bulleted and numbered lists (nested by indentation); numbered lists render as step cards
 *  - task lists (- [ ] item) render as an interactive checklist card with a progress meter
 *  - > quotes render as callout cards; a leading "Tip:", "Note:", "Warning:", "Example:" or
 *    "Key point:" picks the callout's tone
 *  - pipe tables, fenced code blocks
 *  - fenced "quiz" JSON blocks render as interactive multiple-choice cards with instant feedback
 *  - a plain-text multiple-choice question (a question followed by "A) ... B) ..." lines, with an
 *    optional "Answer: B" and "Explanation: ..." after it) is recognised and rendered as the same
 *    card, so the layout holds even when the model ignores the requested format
 *
 * Interaction (choosing an option, ticking a checklist) is handled by format_aicourse/chatbox via
 * delegated events; this module only builds the nodes and records state in data attributes.
 *
 * @module     format_aicourse/local/richtext
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {Number} Upper bound on options per question; anything beyond is not a real MCQ. */
const MAX_OPTIONS = 8;

/** @type {String} Option letters in order. */
const LETTERS = 'ABCDEFGH';

/** @type {RegExp} A fence line: ``` or ~~~ with an optional info string. */
const FENCE = /^\s*(`{3,}|~{3,})\s*([\w-]*)\s*$/;

/** @type {RegExp} An ATX heading. */
const HEADING = /^\s{0,3}(#{1,6})\s+(.+?)\s*#*\s*$/;

/** @type {RegExp} A thematic break. */
const RULE = /^\s{0,3}([-*_])(\s*\1){2,}\s*$/;

/** @type {RegExp} A list item: indentation, marker, content. */
const LISTITEM = /^(\s*)([-*+•]|\d{1,3}[.)])\s+(.*)$/;

/**
 * A multiple-choice option line: "A) text", "(b) text", "C. text". Not "A: text", which is how
 * role-play transcripts label speakers.
 *
 * @type {RegExp}
 */
const OPTION = /^\s*(?:[-*+]\s+)?(?:\*\*)?\(?([A-Ha-h])(?:\)|\.)(?:\*\*)?\s+(.+)$/;

/**
 * The answer line that may follow plain-text options: "Answer: B", "**Correct answer:** B) ...",
 * "The answer is C." It needs "is" or a separator after the word, and the letter must stand alone
 * (followed by a bracket, full stop, colon, dash or the end of the line), so ordinary sentences such
 * as "Answer A, B or C and I will tell you" or "Answer a few more questions" never match.
 *
 * @type {RegExp}
 */
const ANSWERLINE = new RegExp('^\\s*(?:[*_]{1,2})?\\s*(?:the\\s+)?(?:correct\\s+)?answer\\s*(?:[*_]{1,2})?\\s*'
    + '(?:is\\b\\s*:?|[:\\-\\u2013])\\s*(?:[*_]{1,2})?\\s*(?:option\\s+)?\\(?([A-Ha-h])'
    + '(?=[).:]|\\s*[-\\u2013\\u2014]|\\s*(?:[*_]{1,2})?\\s*$)\\)?(.*)$', 'i');


/** @type {RegExp} The explanation line that may follow the answer line. */
const EXPLAINLINE = /^\s*(?:[*_]{1,2})?\s*(?:explanation|rationale)\s*[:\-–]\s*(?:[*_]{1,2})?\s*(.*)$/i;

/**
 * Wording that says the answer is a quiz. A plain-text question without an answer key only becomes
 * a card when the answer says so, because tutors routinely list lettered options in explanations
 * ("So, what can you do? a) talk to your trainer b) ...").
 *
 * @type {RegExp}
 */
const QUIZCUE = new RegExp('\\b(?:practi[cs]e (?:questions?|quiz(?:zes)?|mcqs?)|quiz(?:zes)?|multiple[- ]choice|mcqs?'
    + '|test (?:you|your|yourself)|check (?:your|yourself)|quick (?:check|quiz)|knowledge check|true or false'
    + '|which (?:of the following|option|one of these|statement|answer)|choose (?:the|one|an?)|select (?:the|one|an?)'
    + '|pick (?:the|one)|correct (?:answer|option))\\b', 'i');

/** @type {Number} An option longer than this is an explanation, not a choice. */
const OPTION_MAX = 160;

/** @type {RegExp} A table delimiter row. */
const TABLEDELIM = /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/;

/** @type {String} Words that introduce a callout. */
const CALLOUTWORDS = 'tip|hint|remember|key point|key idea|key|important|note|warning|caution|careful|example|scenario'
    + '|try this|summary';

/** @type {RegExp} "**Tip:** ..." or "**Tip**: ..." - a bold label. */
const CALLOUTBOLD = new RegExp('^(?:\\*\\*|__)\\s*(' + CALLOUTWORDS + ')\\b'
    + '\\s*[:!\\-\\u2013]?\\s*(?:\\*\\*|__)\\s*[:\\-\\u2013]?\\s*', 'i');

/** @type {RegExp} "Tip: ..." - a plain label, which must be followed by a colon or dash. */
const CALLOUTPLAIN = new RegExp('^(' + CALLOUTWORDS + ')\\s*[:\\-\\u2013]\\s+', 'i');

/** @type {Object} Callout tones keyed by the word that introduces them. */
const CALLOUTS = {
    tip: 'tip', hint: 'tip', remember: 'key', key: 'key', important: 'key', note: 'note',
    warning: 'warning', caution: 'warning', careful: 'warning', example: 'example',
    scenario: 'example', 'try this': 'tip', summary: 'key', 'key point': 'key', 'key idea': 'key',
};

/**
 * Create an element with an optional class.
 *
 * @param {String} tag Tag name.
 * @param {String} [className] Class attribute.
 * @returns {HTMLElement} The element.
 */
const el = (tag, className) => {
    const node = document.createElement(tag);
    if (className) {
        node.className = className;
    }
    return node;
};

/**
 * Whether a URL is safe to link to.
 *
 * @param {String} url Candidate URL.
 * @returns {Boolean} True for http, https and mailto.
 */
const safeUrl = (url) => /^(https?:\/\/|mailto:)/i.test(url.trim());

/**
 * Inline patterns. Each is a global regex scanned from the current position; the earliest match
 * wins. Matches are cached and only re-run once the scan has moved past them, so a long answer is
 * parsed in close to linear time rather than re-scanning the remainder for every token.
 *
 * @type {Array}
 */
const INLINE = [
    {type: 'code', re: /`([^`\n]+)`/g},
    {type: 'link', re: /\[([^\]\n]+)\]\(([^()\s]*(?:\([^()\s]*\)[^()\s]*)*)\)/g},
    {type: 'strong', re: /\*\*(?=\S)([^\n]{0,500}?\S)\*\*/g},
    {type: 'strong', re: /__(?=\S)([^\n]{0,500}?\S)__(?!\w)/g},
    {type: 'strike', re: /~~(?=\S)([^\n]{0,500}?\S)~~/g},
    // Not inside a word: "2*3*4" stays arithmetic.
    {type: 'em', re: /\*(?=[^\s*])([^*\n]*?[^\s*])\*(?![\w*])/g, wordstart: true},
    {type: 'url', re: /\bhttps?:\/\/[^\s<>()]+[^\s<>().,;:!?'"]/g},
];

/**
 * Append inline Markdown to a parent node.
 *
 * @param {Node} parent Where to append.
 * @param {String} text Inline Markdown.
 * @param {Boolean} [inlink] True inside a link's label, where no further link may start.
 * @returns {Node} The parent.
 */
export const appendInline = (parent, text, inlink) => {
    const source = String(text || '');
    const patterns = INLINE.filter((pattern) => !inlink || (pattern.type !== 'link' && pattern.type !== 'url'));
    const cache = patterns.map(() => null);
    let pos = 0;

    const find = (index) => {
        const cached = cache[index];
        if (cached && (cached.none || cached.index >= pos)) {
            return cached;
        }
        const pattern = patterns[index];
        pattern.re.lastIndex = pos;
        let match = pattern.re.exec(source);
        while (match && pattern.wordstart && match.index > 0 && /\w/.test(source.charAt(match.index - 1))) {
            pattern.re.lastIndex = match.index + 1;
            match = pattern.re.exec(source);
        }
        cache[index] = match || {none: true};
        return cache[index];
    };

    while (pos < source.length) {
        let best = null;
        let besttype = '';
        patterns.forEach((pattern, index) => {
            const match = find(index);
            if (!match.none && (!best || match.index < best.index)) {
                best = match;
                besttype = pattern.type;
            }
        });
        if (!best) {
            parent.appendChild(document.createTextNode(source.slice(pos)));
            break;
        }
        if (best.index > pos) {
            parent.appendChild(document.createTextNode(source.slice(pos, best.index)));
        }
        let node;
        if (besttype === 'code') {
            node = el('code', 'aicourse-ai-code-inline');
            node.textContent = best[1];
        } else if (besttype === 'link' || besttype === 'url') {
            const href = besttype === 'link' ? best[2] : best[0];
            if (safeUrl(href)) {
                node = el('a');
                node.href = href;
                node.target = '_blank';
                node.rel = 'noopener noreferrer';
                if (besttype === 'link') {
                    appendInline(node, best[1], true);
                } else {
                    node.textContent = best[0];
                }
            } else {
                // Unsafe scheme: keep the label, drop the link.
                node = document.createTextNode(besttype === 'link' ? best[1] : best[0]);
            }
        } else {
            node = el(besttype === 'strike' ? 's' : besttype);
            appendInline(node, best[1], inlink);
        }
        parent.appendChild(node);
        pos = best.index + best[0].length;
    }
    return parent;
};

/**
 * Append text that may contain single newlines, keeping them as line breaks.
 *
 * @param {Node} parent Where to append.
 * @param {String} text Inline Markdown, possibly multi-line.
 * @returns {Node} The parent.
 */
const appendLines = (parent, text) => {
    String(text).split('\n').forEach((line, index) => {
        if (index > 0) {
            parent.appendChild(el('br'));
        }
        appendInline(parent, line);
    });
    return parent;
};

/**
 * Strip inline Markdown down to plain text, for labels and follow-up prompts.
 *
 * @param {String} text Inline Markdown.
 * @returns {String} Plain text.
 */
export const plain = (text) => String(text || '')
    .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
    .replace(/(\*\*|__|~~|`)/g, '')
    .replace(/(^|\s)[*_](\S[^*_]*\S|\S)[*_](?=\s|$|[.,;:!?])/g, '$1$2')
    .replace(/\s+/g, ' ')
    .trim();

/**
 * Normalise a quiz object from whatever shape the model produced.
 *
 * @param {Object} raw Parsed JSON.
 * @returns {Object|null} {question, options[], answer (index or -1), explanation, hint}.
 */
const normaliseQuiz = (raw) => {
    if (!raw || typeof raw !== 'object') {
        return null;
    }
    const question = String(raw.question || raw.q || raw.prompt || raw.stem || '').trim();
    if (typeof raw.question === 'object' && raw.question !== null) {
        return null;
    }
    let options = raw.options || raw.choices || raw.answers || [];
    if (!Array.isArray(options)) {
        // A keyed object ({"A": "...", "B": "..."}) is usable; a string or a number is not.
        options = options && typeof options === 'object'
            ? Object.keys(options).sort().map((key) => options[key])
            : [];
    }
    options = options.map((option) => {
        const text = typeof option === 'object' && option !== null
            ? (option.text || option.label || option.value || '')
            : option;
        // Drop a leading "A) " the model may have added itself.
        return String(text).replace(/^\s*\(?[A-Ha-h][).:]\s+/, '').trim();
    }).filter((text) => text !== '');
    if (!question || options.length < 2 || options.length > MAX_OPTIONS) {
        return null;
    }
    let answer = raw.answer !== undefined ? raw.answer : (raw.correct !== undefined ? raw.correct : raw.correctIndex);
    if (typeof answer === 'string') {
        const trimmed = answer.trim();
        if (/^[A-Ha-h]$/.test(trimmed)) {
            answer = LETTERS.indexOf(trimmed.toUpperCase());
        } else if (/^\d+$/.test(trimmed)) {
            answer = parseInt(trimmed, 10);
        } else {
            answer = options.findIndex((option) => option.toLowerCase() === trimmed.toLowerCase());
        }
    }
    if (!Number.isInteger(answer) || answer < 0 || answer >= options.length) {
        answer = -1;
    }
    return {
        question: question,
        options: options,
        answer: answer,
        explanation: String(raw.explanation || raw.rationale || raw.feedback || '').trim(),
        hint: String(raw.hint || '').trim(),
    };
};

/**
 * Parse a fenced "quiz" block body into one or more questions.
 *
 * @param {String} body The fenced block's contents.
 * @returns {Array} Normalised questions; empty when the body is not usable.
 */
const parseQuizBlock = (body) => {
    let data;
    try {
        data = JSON.parse(body);
    } catch (error) {
        // Forgive the most common model slip, a trailing comma.
        try {
            data = JSON.parse(body.replace(/,\s*([\]}])/g, '$1'));
        } catch (error2) {
            // An answer cut off by the service's output limit ends mid-JSON. Keep every question
            // that arrived whole and flag the block as incomplete rather than losing all of it.
            const salvaged = completeObjects(body).map(normaliseQuiz).filter(Boolean);
            salvaged.incomplete = true;
            return salvaged;
        }
    }
    if (data && !Array.isArray(data) && Array.isArray(data.questions)) {
        data = data.questions;
    }
    return (Array.isArray(data) ? data : [data]).map(normaliseQuiz).filter(Boolean);
};

/**
 * Every complete, parseable {...} object at the top level of a JSON array that may be cut off.
 * Braces inside strings are ignored.
 *
 * @param {String} body JSON text, possibly truncated.
 * @returns {Array} The parsed objects.
 */
const completeObjects = (body) => {
    const found = [];
    let depth = 0;
    let start = -1;
    let instring = false;
    let escaped = false;
    for (let index = 0; index < body.length; index++) {
        const char = body.charAt(index);
        if (instring) {
            if (escaped) {
                escaped = false;
            } else if (char === '\\') {
                escaped = true;
            } else if (char === '"') {
                instring = false;
            }
            continue;
        }
        if (char === '"') {
            instring = true;
        } else if (char === '{') {
            if (depth === 0) {
                start = index;
            }
            depth++;
        } else if (char === '}' && depth > 0) {
            depth--;
            if (depth === 0 && start >= 0) {
                try {
                    found.push(JSON.parse(body.slice(start, index + 1)));
                } catch (error) {
                    // Not valid on its own; skip it.
                }
                start = -1;
            }
        }
    }
    return found;
};

/**
 * Block-level parser: turns lines into a flat list of block descriptors.
 *
 * @param {Array} lines Source lines.
 * @returns {Array} Blocks.
 */
const parseBlocks = (lines) => {
    const blocks = [];
    let i = 0;

    const isBlank = (line) => line === undefined || /^\s*$/.test(line);
    const startsBlock = (line, next) => FENCE.test(line) || HEADING.test(line) || RULE.test(line)
        || /^\s*>/.test(line) || LISTITEM.test(line) || OPTION.test(line)
        || (line.indexOf('|') !== -1 && next !== undefined && TABLEDELIM.test(next));

    while (i < lines.length) {
        const line = lines[i];

        if (isBlank(line)) {
            i++;
            continue;
        }

        // Fenced code, including fenced "quiz".
        const fence = FENCE.exec(line);
        if (fence) {
            const marker = fence[1];
            const lang = (fence[2] || '').toLowerCase();
            const body = [];
            i++;
            while (i < lines.length && !(lines[i].trim().indexOf(marker) === 0 && FENCE.test(lines[i]))) {
                body.push(lines[i]);
                i++;
            }
            i++;
            const text = body.join('\n');
            const looksLikeQuiz = /"options"\s*:/.test(text) && /"question"\s*:/.test(text);
            // An untagged or "json" block only counts when the answer is about a quiz: an ICT unit
            // teaching JSON may well show objects with "question" and "options" keys.
            if (lang === 'quiz' || lang === 'mcq' || ((lang === 'json' || lang === '') && looksLikeQuiz && quizcue)) {
                const quizzes = parseQuizBlock(text);
                quizzes.forEach((quiz) => blocks.push({type: 'quiz', quiz: quiz}));
                if (quizzes.incomplete) {
                    // Never show a learner raw, broken JSON.
                    blocks.push({type: 'notice'});
                    continue;
                }
                if (quizzes.length) {
                    continue;
                }
            }
            blocks.push({type: 'code', lang: lang, text: text});
            continue;
        }

        const heading = HEADING.exec(line);
        if (heading) {
            blocks.push({type: 'heading', level: heading[1].length, text: heading[2]});
            i++;
            continue;
        }

        if (RULE.test(line)) {
            blocks.push({type: 'rule'});
            i++;
            continue;
        }

        if (/^\s*>/.test(line)) {
            const body = [];
            while (i < lines.length && /^\s*>/.test(lines[i])) {
                body.push(lines[i].replace(/^\s*>\s?/, ''));
                i++;
            }
            blocks.push({type: 'quote', lines: body});
            continue;
        }

        if (line.indexOf('|') !== -1 && TABLEDELIM.test(lines[i + 1] || '')) {
            const split = (row) => row.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map((c) => c.trim());
            const head = split(line);
            const rows = [];
            i += 2;
            while (i < lines.length && lines[i].indexOf('|') !== -1 && !isBlank(lines[i])) {
                rows.push(split(lines[i]));
                i++;
            }
            blocks.push({type: 'table', head: head, rows: rows});
            continue;
        }

        // Plain-text options: A) .. B) .. in sequence, blank lines allowed between. They become a
        // question card only when they really are a question: the stem ends in "?" or carries a
        // "Question 2:" label, or an answer / explanation line follows. Otherwise ("There are two
        // kinds of hazard: a) physical b) chemical") they are rendered as a lettered list.
        const firstoption = OPTION.exec(line);
        if (firstoption && firstoption[1].toUpperCase() === 'A') {
            const options = [];
            let j = i;
            while (j < lines.length && options.length < MAX_OPTIONS) {
                if (isBlank(lines[j])) {
                    j++;
                    continue;
                }
                const option = OPTION.exec(lines[j]);
                if (!option || option[1].toUpperCase() !== LETTERS[options.length]) {
                    break;
                }
                options.push(option[2].replace(/\s*(\*\*)?\s*$/, ''));
                j++;
            }
            if (options.length >= 2) {
                let answer = -1;
                const explain = [];
                let k = j;
                const skipBlank = () => {
                    while (k < lines.length && isBlank(lines[k])) {
                        k++;
                    }
                };
                skipBlank();
                const answerline = ANSWERLINE.exec(lines[k] || '');
                let restofline = '';
                if (answerline) {
                    answer = LETTERS.indexOf(answerline[1].toUpperCase());
                    // "Answer: B - because ..." carries its explanation on the same line.
                    const sameline = /(?:because|since|\s[\u2013\u2014-])\s+(.+)$/i.exec(answerline[2]);
                    restofline = sameline ? sameline[0].replace(/^\s*[\u2013\u2014-]\s*/, '') : '';
                    k++;
                    skipBlank();
                }
                const explainline = EXPLAINLINE.exec(lines[k] || '');
                if (explainline) {
                    explain.push(explainline[1]);
                    k++;
                    while (k < lines.length && !isBlank(lines[k]) && !startsBlock(lines[k], lines[k + 1])) {
                        explain.push(lines[k].trim());
                        k++;
                    }
                } else if (restofline) {
                    explain.push(restofline);
                }
                // Only an answer line ("Answer: B") is a key. An explanation on its own is not
                // evidence of a quiz: "Why:" and "Explanation:" follow ordinary lists too.
                const keyed = !!answerline;

                // The stem: the paragraph or heading just before the options (from its "Question"
                // line, or its last line, so an introduction above stays outside the card), or the
                // last item of a list ("1. What is PPE?" followed by indented options).
                const previous = blocks[blocks.length - 1];
                let stem = '';
                let takeStem = () => null;
                if (previous && (previous.type === 'para' || previous.type === 'heading')) {
                    const qlines = previous.text.split('\n');
                    let start = qlines.length - 1;
                    qlines.forEach((qline, index) => {
                        if (/^\s*(?:[*_]{1,2})?\s*(?:question|q)\s*\d*\s*[:.)]/i.test(qline)) {
                            start = index;
                        }
                    });
                    stem = qlines.slice(start).join('\n');
                    takeStem = () => {
                        if (start > 0) {
                            previous.text = qlines.slice(0, start).join('\n');
                        } else {
                            blocks.pop();
                        }
                    };
                } else if (previous && previous.type === 'list' && previous.items.length) {
                    stem = previous.items[previous.items.length - 1].text;
                    takeStem = () => {
                        previous.items.pop();
                        if (!previous.items.length) {
                            blocks.pop();
                        }
                    };
                }

                // Without a key, a card needs every sign of a real practice question: a stem that
                // is a paragraph or list item (not a heading) asking a question or labelled
                // "Question 2:", at least three short options, and the answer talking about a
                // quiz unless the stem is labelled. Anything less stays a lettered list, which is
                // still well formatted, rather than turning an explanation into a quiz.
                const labelled = /^\s*(?:[*_]{1,2})?\s*question\s*\d+\s*(?:[*_]{1,2})?\s*[:.)]/i.test(stem);
                const asks = /\?\s*(?:[*_]{1,2})?\s*$/.test(stem);
                const short = options.every((option) => option.length <= OPTION_MAX && !/[.!?]\s+[A-Z]/.test(option));
                const fromheading = !!previous && previous.type === 'heading';
                // "Hazards must be reported. A) True B) False" is a statement, not a "?" question.
                const truefalse = options.length === 2 && /^true\.?$/i.test(options[0]) && /^false\.?$/i.test(options[1]);
                // Two options (True / False) only for a labelled question or a quiz answer.
                const enough = options.length >= ((quizcue || (labelled && asks)) ? 2 : 3);
                const isquiz = keyed || (!!stem && !fromheading && enough && short
                    && (asks || labelled || truefalse) && (labelled || quizcue));

                if (isquiz) {
                    takeStem();
                    blocks.push({type: 'quiz', quiz: {
                        question: stem,
                        options: options,
                        answer: answer >= options.length ? -1 : answer,
                        explanation: explain.join(' ').trim(),
                        hint: '',
                    }});
                    i = (answerline || explainline) ? k : j;
                } else {
                    blocks.push({type: 'letters', upper: firstoption[1] === firstoption[1].toUpperCase(), items: options});
                    i = j;
                }
                continue;
            }
        }

        if (LISTITEM.test(line)) {
            const items = [];
            while (i < lines.length) {
                const current = lines[i];
                if (isBlank(current)) {
                    // A blank line ends the list unless another item follows it.
                    if (i + 1 < lines.length && LISTITEM.test(lines[i + 1])) {
                        i++;
                        continue;
                    }
                    break;
                }
                const item = LISTITEM.exec(current);
                if (item) {
                    items.push({
                        indent: item[1].replace(/\t/g, '    ').length,
                        ordered: /\d/.test(item[2]),
                        start: parseInt(item[2], 10) || 1,
                        text: item[3],
                    });
                    i++;
                    continue;
                }
                if (/^\s+\S/.test(current) && items.length && !startsBlock(current.trim(), lines[i + 1])) {
                    items[items.length - 1].text += '\n' + current.trim();
                    i++;
                    continue;
                }
                break;
            }
            blocks.push({type: 'list', items: items});
            continue;
        }

        // Paragraph: runs until a blank line or the start of another block.
        const para = [line.trim()];
        i++;
        while (i < lines.length && !isBlank(lines[i]) && !startsBlock(lines[i], lines[i + 1])) {
            para.push(lines[i].trim());
            i++;
        }
        blocks.push({type: 'para', text: para.join('\n')});
    }

    return blocks;
};

/**
 * Build a nested list tree out of flat, indented items.
 *
 * @param {Array} items Flat items with indent.
 * @returns {Array} Tree of {ordered, start, items: [{text, children}]}.
 */
const buildListTree = (items) => {
    const root = {children: []};
    const stack = [{indent: -1, node: root}];
    items.forEach((item) => {
        while (stack.length > 1 && item.indent <= stack[stack.length - 1].indent) {
            stack.pop();
        }
        const parent = stack[stack.length - 1].node;
        const node = {text: item.text, ordered: item.ordered, start: item.start, children: []};
        parent.children.push(node);
        stack.push({indent: item.indent, node: node});
    });
    return root.children;
};

/**
 * Render one list level.
 *
 * @param {Array} nodes Sibling list nodes.
 * @param {Number} depth Nesting depth.
 * @returns {HTMLElement} The list element.
 */
const renderList = (nodes, depth) => {
    const ordered = nodes[0].ordered;
    const tasks = nodes.every((node) => /^\[[ xX]\]\s+/.test(node.text));

    if (tasks) {
        const card = el('div', 'aicourse-ai-checklist');
        const list = el('ul', 'aicourse-ai-checklist-items');
        nodes.forEach((node) => {
            const li = el('li', 'aicourse-ai-checklist-item');
            const label = el('label');
            const box = el('input');
            box.type = 'checkbox';
            box.className = 'aicourse-ai-checklist-box';
            box.checked = /^\[[xX]\]/.test(node.text);
            const text = el('span', 'aicourse-ai-checklist-text');
            appendLines(text, node.text.replace(/^\[[ xX]\]\s+/, ''));
            label.appendChild(box);
            label.appendChild(text);
            li.appendChild(label);
            if (node.children.length) {
                li.appendChild(renderList(node.children, depth + 1));
            }
            list.appendChild(li);
        });
        const meter = el('div', 'aicourse-ai-checklist-meter');
        const bar = el('div', 'aicourse-ai-checklist-bar');
        bar.appendChild(el('span'));
        const count = el('span', 'aicourse-ai-checklist-count');
        count.setAttribute('aria-live', 'polite');
        meter.appendChild(bar);
        meter.appendChild(count);
        card.appendChild(meter);
        card.appendChild(list);
        return card;
    }

    const list = el(ordered ? 'ol' : 'ul', ordered && depth === 0 ? 'aicourse-ai-steps' : 'aicourse-ai-list');
    if (ordered && nodes[0].start > 1) {
        list.setAttribute('start', String(nodes[0].start));
        list.style.counterReset = 'acf-step ' + (nodes[0].start - 1);
    }
    nodes.forEach((node) => {
        const li = el('li');
        const body = el('div', 'aicourse-ai-li-body');
        appendLines(body, node.text);
        li.appendChild(body);
        if (node.children.length) {
            li.appendChild(renderList(node.children, depth + 1));
        }
        list.appendChild(li);
    });
    return list;
};

/**
 * Render a quote as a callout card.
 *
 * @param {Array} lines The quote's lines without the '>' marker.
 * @returns {HTMLElement} The callout.
 */
const renderCallout = (lines) => {
    let tone = 'note';
    let label = '';
    const first = (lines[0] || '').trim();
    // A label only counts when it is bold or followed by a colon: "> Tips for success" and
    // "> Note that ..." are ordinary quotes.
    const lead = CALLOUTBOLD.exec(first) || CALLOUTPLAIN.exec(first);
    if (lead) {
        tone = CALLOUTS[lead[1].toLowerCase()] || 'note';
        label = lead[1];
        lines = [first.slice(lead[0].length)].concat(lines.slice(1));
    }
    const box = el('aside', 'aicourse-ai-callout');
    box.setAttribute('data-tone', tone);
    if (label) {
        const title = el('div', 'aicourse-ai-callout-title');
        title.textContent = label.charAt(0).toUpperCase() + label.slice(1).toLowerCase();
        box.appendChild(title);
    }
    const body = el('div', 'aicourse-ai-callout-body');
    body.appendChild(renderBlocks(parseBlocks(lines)));
    box.appendChild(body);
    return box;
};

/**
 * Render a multiple-choice card.
 *
 * @param {Object} quiz Normalised question.
 * @param {Object} labels Resolved strings: quizlabel, hint.
 * @returns {HTMLElement} The card.
 */
const renderQuiz = (quiz, labels) => {
    const card = el('section', 'aicourse-ai-quiz');
    card.setAttribute('data-answer', String(quiz.answer));
    card.setAttribute('data-state', 'open');

    const head = el('div', 'aicourse-ai-quiz-head');
    const badge = el('span', 'aicourse-ai-quiz-badge');
    badge.textContent = labels.quizlabel || '';
    head.appendChild(badge);
    card.appendChild(head);

    const questionid = 'aicourse-ai-q-' + Math.random().toString(36).slice(2, 10);
    const question = el('div', 'aicourse-ai-quiz-question');
    question.id = questionid;
    appendLines(question, quiz.question.replace(/^(?:\*\*)?\s*(?:question\s*\d*\s*[:.)-]?)\s*(?:\*\*)?\s*/i, '')
        || quiz.question);
    card.appendChild(question);

    const group = el('div', 'aicourse-ai-quiz-options');
    group.setAttribute('role', 'group');
    group.setAttribute('aria-labelledby', questionid);
    quiz.options.forEach((option, index) => {
        const button = el('button', 'aicourse-ai-quiz-option');
        button.type = 'button';
        button.setAttribute('data-index', String(index));
        button.setAttribute('data-letter', LETTERS[index]);
        button.setAttribute('aria-pressed', 'false');
        const letter = el('span', 'aicourse-ai-quiz-letter');
        letter.setAttribute('aria-hidden', 'true');
        letter.textContent = LETTERS[index];
        const text = el('span', 'aicourse-ai-quiz-text');
        appendInline(text, option);
        const mark = el('span', 'aicourse-ai-quiz-mark');
        mark.setAttribute('aria-hidden', 'true');
        button.appendChild(letter);
        button.appendChild(text);
        button.appendChild(mark);
        group.appendChild(button);
    });
    card.appendChild(group);

    if (quiz.hint) {
        const hintwrap = el('div', 'aicourse-ai-quiz-hintwrap');
        const toggle = el('button', 'aicourse-ai-quiz-hintbtn');
        toggle.type = 'button';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.textContent = labels.hint || '';
        const hint = el('div', 'aicourse-ai-quiz-hint');
        hint.hidden = true;
        appendLines(hint, quiz.hint);
        hintwrap.appendChild(toggle);
        hintwrap.appendChild(hint);
        card.appendChild(hintwrap);
    }

    const feedback = el('div', 'aicourse-ai-quiz-feedback');
    feedback.hidden = true;
    feedback.setAttribute('role', 'status');
    const verdict = el('div', 'aicourse-ai-quiz-verdict');
    feedback.appendChild(verdict);
    if (quiz.explanation) {
        const explanation = el('div', 'aicourse-ai-quiz-explanation');
        appendLines(explanation, quiz.explanation);
        feedback.appendChild(explanation);
    }
    const actions = el('div', 'aicourse-ai-quiz-actions');
    feedback.appendChild(actions);
    card.appendChild(feedback);

    return card;
};

/**
 * Render a table, wrapped so it scrolls sideways on a narrow panel.
 *
 * @param {Object} block Table block.
 * @returns {HTMLElement} The wrapper.
 */
const renderTable = (block) => {
    // Focusable so a keyboard user can scroll a wide table; a named region so they know why.
    const wrap = el('div', 'aicourse-ai-table-wrap');
    wrap.setAttribute('tabindex', '0');
    wrap.setAttribute('role', 'region');
    wrap.setAttribute('aria-label', block.head.map(plain).join(', '));
    const table = el('table', 'aicourse-ai-table');
    const thead = el('thead');
    const headrow = el('tr');
    block.head.forEach((cell) => {
        const th = el('th');
        th.setAttribute('scope', 'col');
        appendInline(th, cell);
        headrow.appendChild(th);
    });
    thead.appendChild(headrow);
    table.appendChild(thead);
    const tbody = el('tbody');
    block.rows.forEach((row) => {
        const tr = el('tr');
        block.head.forEach((unused, index) => {
            const td = el('td');
            appendInline(td, row[index] || '');
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    wrap.appendChild(table);
    return wrap;
};

/** @type {Object} Labels injected by render(); module-level so nested renders see them. */
let currentLabels = {};

/** @type {Boolean} True when the answer being rendered talks about a quiz (QUIZCUE). */
let quizcue = false;

/**
 * Turn block descriptors into DOM.
 *
 * @param {Array} blocks Parsed blocks.
 * @returns {DocumentFragment} The rendered blocks.
 */
const renderBlocks = (blocks) => {
    const fragment = document.createDocumentFragment();
    let quizset = null;
    let quizcount = 0;

    blocks.forEach((block) => {
        if (block.type !== 'quiz') {
            quizset = null;
        }
        switch (block.type) {
            case 'heading': {
                // Answers live inside a page that already has h1/h2; keep the outline sane.
                const level = Math.min(6, Math.max(3, block.level + 2));
                const heading = el('h' + level, 'aicourse-ai-h aicourse-ai-h' + Math.min(block.level, 3));
                appendInline(heading, block.text);
                fragment.appendChild(heading);
                break;
            }
            case 'rule':
                fragment.appendChild(el('hr', 'aicourse-ai-rule'));
                break;
            case 'quote':
                fragment.appendChild(renderCallout(block.lines));
                break;
            case 'table':
                fragment.appendChild(renderTable(block));
                break;
            case 'code': {
                const figure = el('div', 'aicourse-ai-codeblock');
                if (block.lang) {
                    const lang = el('div', 'aicourse-ai-codeblock-lang');
                    lang.textContent = block.lang;
                    figure.appendChild(lang);
                }
                const pre = el('pre');
                const code = el('code');
                code.textContent = block.text;
                pre.appendChild(code);
                figure.appendChild(pre);
                fragment.appendChild(figure);
                break;
            }
            case 'notice': {
                const notice = el('p', 'aicourse-ai-notice');
                notice.textContent = currentLabels.incomplete || '';
                fragment.appendChild(notice);
                break;
            }
            case 'letters': {
                const list = el('ol', 'aicourse-ai-list aicourse-ai-letters');
                list.setAttribute('type', block.upper ? 'A' : 'a');
                block.items.forEach((item) => {
                    const li = el('li');
                    const body = el('div', 'aicourse-ai-li-body');
                    appendLines(body, item);
                    li.appendChild(body);
                    list.appendChild(li);
                });
                fragment.appendChild(list);
                break;
            }
            case 'list': {
                // Split the top level wherever the kind changes, so a numbered list followed by
                // a checklist renders as two cards rather than one muddled list.
                const kind = (node) => (/^\[[ xX]\]\s+/.test(node.text) ? 'task' : (node.ordered ? 'ol' : 'ul'));
                let run = [];
                buildListTree(block.items).forEach((node) => {
                    if (run.length && kind(run[0]) !== kind(node)) {
                        fragment.appendChild(renderList(run, 0));
                        run = [];
                    }
                    run.push(node);
                });
                if (run.length) {
                    fragment.appendChild(renderList(run, 0));
                }
                break;
            }
            case 'quiz': {
                if (!quizset) {
                    quizset = el('div', 'aicourse-ai-quizset');
                    fragment.appendChild(quizset);
                }
                quizcount++;
                quizset.appendChild(renderQuiz(block.quiz, currentLabels));
                break;
            }
            default: {
                const p = el('p');
                appendLines(p, block.text);
                fragment.appendChild(p);
            }
        }
    });

    if (quizcount) {
        fragment.querySelectorAll('.aicourse-ai-quizset').forEach((set) => {
            const cards = set.querySelectorAll('.aicourse-ai-quiz');
            set.setAttribute('data-total', String(cards.length));
            if (cards.length > 1) {
                const score = el('div', 'aicourse-ai-quizset-score');
                score.setAttribute('aria-live', 'polite');
                score.hidden = true;
                set.appendChild(score);
            }
        });
    }

    return fragment;
};

/**
 * Render a tutor answer.
 *
 * @param {String} text The raw answer.
 * @param {Object} [labels] Resolved strings: quizlabel, hint.
 * @returns {DocumentFragment} Nodes ready to append to the bubble.
 */
export const render = (text, labels) => {
    currentLabels = labels || {};
    const source = String(text || '').replace(/\r\n?/g, '\n').replace(/\u00a0/g, ' ');
    quizcue = QUIZCUE.test(source);
    const lines = [];
    source.split('\n').forEach((line) => {
        lines.push(...splitInlineOptions(line));
    });
    return renderBlocks(parseBlocks(lines));
};

/**
 * Split a multiple-choice question written on ONE line into a stem line and one line per option:
 * "Which control is best? A) PPE B) Elimination C) Signage D) Training" (and "A." / "(A)").
 *
 * Only when there are at least three options lettered in order from A, and the text before them
 * is empty or ends in "?" or ":", so prose such as "Plan A) is cheaper than B) and C)" is left
 * alone. An "Answer: B" at the end of the last option moves to its own line too.
 *
 * @param {String} line One source line.
 * @returns {Array} The line, or the split lines.
 */
const splitInlineOptions = (line) => {
    if (line.length < 12 || /^\s*(?:>|\|)/.test(line)) {
        return [line];
    }
    const marker = /(^|\s)\(?([A-Ha-h])[).]\s+/g;
    const found = [];
    let match = marker.exec(line);
    while (match) {
        const letter = match[2];
        const isupper = letter === letter.toUpperCase();
        // Next letter in sequence, in the same case as "A" was written.
        const insequence = letter.toUpperCase() === LETTERS.charAt(found.length)
            && (found.length === 0 || isupper === found[0].isupper);
        if (insequence) {
            found.push({letter: letter, isupper: isupper, start: match.index + match[1].length,
                end: match.index + match[0].length});
        }
        match = marker.exec(line);
    }
    if (found.length < 3) {
        return [line];
    }
    const stem = line.slice(0, found[0].start).trim();
    if (stem && !/[?:]\s*(?:[*_]{1,2})?$/.test(stem)) {
        return [line];
    }
    const out = stem ? [stem] : [];
    found.forEach((option, index) => {
        const next = found[index + 1];
        let text = line.slice(option.end, next ? next.start : line.length).trim();
        if (!next) {
            const answer = /\s+((?:[*_]{1,2})?\s*(?:the\s+)?(?:correct\s+)?answer\b.*)$/i.exec(text);
            if (answer) {
                text = text.slice(0, answer.index).trim();
                out.push(option.letter + ') ' + text);
                out.push(answer[1]);
                return;
            }
        }
        out.push(option.letter + ') ' + text);
    });
    return out;
};
