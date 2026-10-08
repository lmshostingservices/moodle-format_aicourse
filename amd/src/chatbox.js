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
 * Behaviour of the AI Tutor chat panel.
 *
 * This replaces the ~600 line inline <script> the format used to build by string concatenation in
 * PHP. Nothing here is generated server side: every server value arrives as the config object
 * passed to init(), which Moodle JSON encodes for us, so no PHP value is ever interpolated into
 * JavaScript source. Every user visible string comes from core/str, and every piece of untrusted
 * text (what the learner typed, what the AI service answered, what was replayed out of
 * sessionStorage) is rendered either with textContent or through a Mustache template that escapes
 * it. No innerHTML is assigned anywhere in this module.
 *
 * @module     format_aicourse/chatbox
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import {render as renderRich, plain as plainText} from 'format_aicourse/local/richtext';

/**
 * Element ids and selectors the panel is built from.
 *
 * @type {Object}
 */
const SELECTORS = {
    panel: 'aicourse-ai-chatbox',
    messages: 'aicourse-ai-messages',
    input: 'aicourse-ai-input',
    welcome: 'aicourse-ai-welcome',
    quickactions: 'aicourse-ai-quick-actions',
    loading: 'aicourse-ai-loading',
    welcomebody: '#aicourse-ai-welcome .aicourse-ai-message-content',
    // ACF-FIX-2.1.51: was '.aicourse-ai-toggle, .aicourse-hero-ai-btn'. The second half was
    // wrong: .aicourse-hero-ai-btn is the shared LAYOUT class on every round button in the hero
    // pill, so it matched the Generate banner image button as well -- clicking that opened the
    // tutor on top of the generation dialogue. Only the button that actually is the tutor toggle
    // should open the tutor.
    toggle: '.aicourse-ai-toggle',
    close: '.aicourse-ai-chatbox-close, #aicourse-ai-close',
    quickbtn: '.aicourse-ai-quick-btn',
    sendbtn: '#aicourse-ai-send, .aicourse-ai-send-btn',
    ratebtn: '.aicourse-ai-rate-btn',
    rating: '.aicourse-ai-rating',
    // ACF-FIX-2.1.51: the tutor's own button, not every button in the hero pill. This drives
    // aria-expanded, and setting that on the Generate banner button told a screen reader it
    // controlled the tutor panel, which it does not.
    herobtn: '.aicourse-ai-toggle',
    backdrop: 'aicourse-ai-backdrop',
    suggest: 'aicourse-ai-suggest',
    expand: '.aicourse-ai-expand',
    newchat: '.aicourse-ai-newchat',
    copybtn: '.aicourse-ai-copy-btn',
    quizoption: '.aicourse-ai-quiz-option',
    quizhint: '.aicourse-ai-quiz-hintbtn',
    followup: '.aicourse-ai-followup',
    checkbox: '.aicourse-ai-checklist-box',
};

/** @type {String} localStorage key remembering whether the learner prefers the Study view. */
const VIEW_KEY = 'format_aicourse_tutor_view';

/** @type {String} Option letters, matching format_aicourse/local/richtext. */
const LETTERS = 'ABCDEFGH';

/**
 * Everything inside the panel that can hold focus, for the Tab trap.
 *
 * @type {String}
 */
const FOCUSABLE = 'a[href], area[href], button:not([disabled]), ' +
    'input:not([disabled]):not([type="hidden"]), select:not([disabled]), ' +
    'textarea:not([disabled]), iframe, [tabindex]:not([tabindex="-1"]), ' +
    '[contenteditable="true"]';

/**
 * Language strings used by the module, as short camel free aliases mapped to their string ids.
 *
 * @type {Object}
 */
const STRING_IDS = {
    error: 'aiassistant_error',
    thanks: 'aiassistant_rating_thanks',
    restored: 'aiassistant_restored',
    thisactivity: 'aiassistant_thisactivity',
    ratehelpful: 'aiassistant_rate_helpful',
    ratenothelpful: 'aiassistant_rate_nothelpful',
    thinking: 'aiassistant_thinking',
    promptstructure: 'aiassistant_prompt_structure',
    promptconcepts: 'aiassistant_prompt_concepts',
    promptworkplace: 'aiassistant_prompt_workplace',
    promptpractice: 'aiassistant_prompt_practice',
    promptchecklist: 'aiassistant_prompt_checklist',
    copy: 'aiassistant_copy',
    copied: 'aiassistant_copied',
    quizlabel: 'aiassistant_quiz_label',
    quizhint: 'aiassistant_quiz_hint',
    quizcorrect: 'aiassistant_quiz_correct',
    quiztryagain: 'aiassistant_quiz_tryagain',
    quizsent: 'aiassistant_quiz_sent',
    quizexplain: 'aiassistant_quiz_explain',
    quizanother: 'aiassistant_quiz_another',
    quizanotherprompt: 'aiassistant_quiz_another_prompt',
    quizincomplete: 'aiassistant_quiz_incomplete',
    cutoff: 'aiassistant_cutoff',
    askagain: 'aiassistant_askagain',
};

/**
 * Language strings that take parameters. They are fetched once with {name} tokens in place of the
 * values and filled in synchronously by fmt(), so a card can be labelled while it is being built.
 *
 * @type {Object}
 */
const PARAM_STRING_IDS = {
    quizcounter: ['aiassistant_quiz_counter', {num: '{num}', total: '{total}'}],
    quizincorrect: ['aiassistant_quiz_incorrect', '{answer}'],
    quizscore: ['aiassistant_quiz_score', {score: '{score}', total: '{total}'}],
    quizchoose: ['aiassistant_quiz_choose', {question: '{question}', letter: '{letter}', option: '{option}'}],
    quizexplainprompt: ['aiassistant_quiz_explain_prompt', {question: '{question}', letter: '{letter}', option: '{option}'}],
    checklistprogress: ['aiassistant_checklist_progress', {done: '{done}', total: '{total}'}],
};

/** @type {Number} How many characters of a question are quoted back in the greeting. */
const TOPIC_LENGTH = 80;

/** @type {Number} How many characters of each question are sent along as context. */
const QUESTION_LENGTH = 200;

/** @type {Number} How many characters of an assignment intro are used as context. */
const INTRO_LENGTH = 500;

/** @type {Number} Number of messages kept in sessionStorage. */
const HISTORY_LIMIT = 20;

/** @type {Boolean} Guard making init() idempotent; it is queued from several places. */
let initialised = false;

/** @type {Object} The config object handed over by chatbox::script(). */
let config = {};

/** @type {Object} Resolved language strings, keyed as in STRING_IDS. */
const strings = {};

/** @type {Promise} Resolves once STRING_IDS have been fetched. */
let stringsReady = Promise.resolve();

/** @type {Promise} Serialises every append to the message list so bubbles stay in order. */
let renderQueue = Promise.resolve();

/** @type {Array} The conversation, mirrored into sessionStorage. */
let history = [];

/** @type {Boolean} True while an answer is in flight. */
let loading = false;

/** @type {Boolean} True until the learner's first question of this page view is sent. */
let firstmessage = true;

/** @type {Element|null} The element that opened the panel, so focus can be restored to it. */
let opener = null;

/** @type {Node|null} Clone of the server rendered greeting, used to put it back. */
let welcomeSnapshot = null;

/**
 * Call one of the plugin's external functions.
 *
 * THIS IS THE ONLY PLACE IN THE MODULE THAT TALKS TO THE SERVER. It goes through core/ajax, so
 * the session key, the endpoint and the transport are all core's problem, and the server side is
 * a proper web service with declared parameters, declared return values and a capability check.
 * The courseid every function needs is added here so no caller has to know about it.
 *
 * @param {String} shortname Name of the external function without the format_aicourse_ prefix.
 * @param {Object} args Extra arguments. Null and undefined values are dropped.
 * @returns {Promise<Object>} Resolves with the function's return value, rejects with a Moodle
 *                            exception object carrying a translated .message.
 */
const callExternal = (shortname, args) => {
    const payload = {courseid: config.courseid};
    Object.keys(args || {}).forEach((name) => {
        if (args[name] !== null && args[name] !== undefined) {
            payload[name] = args[name];
        }
    });

    return Ajax.call([{
        methodname: 'format_aicourse_' + shortname,
        args: payload,
    }])[0];
};

/**
 * The panel root, or null while the hero banner has not been injected yet.
 *
 * @returns {Element|null} The panel.
 */
const getPanel = () => document.getElementById(SELECTORS.panel);

/**
 * The message list, or null while the hero banner has not been injected yet.
 *
 * @returns {Element|null} The message list.
 */
const getMessages = () => document.getElementById(SELECTORS.messages);

/**
 * The question textarea, or null while the hero banner has not been injected yet.
 *
 * @returns {Element|null} The textarea.
 */
const getInput = () => document.getElementById(SELECTORS.input);

/**
 * Whether the panel is currently open.
 *
 * @returns {Boolean} True when the panel is visible.
 */
const isOpen = () => {
    const panel = getPanel();

    return !!panel && panel.style.display !== 'none' && panel.style.display !== '';
};

/**
 * Queue work that touches the message list, so bubbles are appended in the order they were asked
 * for even though template rendering is asynchronous.
 *
 * @param {Function} task Returns a promise, or nothing.
 * @returns {Promise} The tail of the queue.
 */
const enqueue = (task) => {
    renderQueue = renderQueue.then(task).catch((error) => {
        Notification.exception(error);
    });

    return renderQueue;
};

/**
 * Persist the tail of the conversation so it survives navigating within the course.
 *
 * @returns {void}
 */
const saveHistory = () => {
    try {
        sessionStorage.setItem(
            'aicourse_chat_' + config.courseid + '_' + config.userid,
            JSON.stringify(history.slice(-HISTORY_LIMIT))
        );
    } catch (error) {
        // Private browsing, a full quota or a blocked storage partition. Chat memory is a
        // convenience, so losing it must never interrupt the conversation.
    }
};

/**
 * Read the stored conversation back.
 *
 * @returns {Array} The stored messages, or an empty array.
 */
const loadHistory = () => {
    try {
        const saved = sessionStorage.getItem('aicourse_chat_' + config.courseid + '_' + config.userid);
        if (saved) {
            const parsed = JSON.parse(saved);

            return Array.isArray(parsed) ? parsed : [];
        }
    } catch (error) {
        return [];
    }

    return [];
};

/**
 * Fill a parameterised string fetched with {name} tokens.
 *
 * @param {String} alias Key in PARAM_STRING_IDS.
 * @param {Object} values Token values.
 * @returns {String} The finished string.
 */
const fmt = (alias, values) => String(strings[alias] || '').replace(/\{(\w+)\}/g, (match, name) => (
    values && values[name] !== undefined ? String(values[name]) : match
));

/**
 * Shorten text for quoting back to the tutor.
 *
 * @param {String} text Text.
 * @param {Number} length Maximum length.
 * @returns {String} The text, with an ellipsis when cut.
 */
const truncate = (text, length) => {
    const value = String(text || '').replace(/\s+/g, ' ').trim();
    return value.length > length ? value.substring(0, length - 1) + '…' : value;
};

/** @type {WeakMap} The original answer text behind each rendered tutor turn, for Copy. */
const rawText = new WeakMap();

/** @type {WeakMap} The stored history entry behind each rendered tutor turn. */
const turnOf = new WeakMap();

/**
 * Hide the welcome study tools once the conversation has started, and offer the compact
 * suggestion chips above the composer instead.
 *
 * @returns {void}
 */
const hideQuickActions = () => {
    const quickactions = document.getElementById(SELECTORS.quickactions);
    if (quickactions) {
        quickactions.style.display = 'none';
    }
    const suggest = document.getElementById(SELECTORS.suggest);
    if (suggest) {
        suggest.hidden = false;
    }
};

/**
 * Bring the welcome study tools back for a fresh conversation.
 *
 * @returns {void}
 */
const showQuickActions = () => {
    const quickactions = document.getElementById(SELECTORS.quickactions);
    if (quickactions) {
        quickactions.style.display = '';
    }
    const suggest = document.getElementById(SELECTORS.suggest);
    if (suggest) {
        suggest.hidden = true;
    }
};

/**
 * Keep a checklist card's progress meter in step with its boxes.
 *
 * @param {Element} card The .aicourse-ai-checklist card.
 * @returns {void}
 */
const updateChecklist = (card) => {
    if (!card) {
        return;
    }
    const boxes = card.querySelectorAll(SELECTORS.checkbox);
    let done = 0;
    boxes.forEach((box) => {
        if (box.checked) {
            done++;
        }
        const item = box.closest('.aicourse-ai-checklist-item');
        if (item) {
            item.classList.toggle('is-done', box.checked);
        }
    });
    const total = boxes.length;
    const bar = card.querySelector('.aicourse-ai-checklist-bar > span');
    if (bar) {
        bar.style.inlineSize = (total ? Math.round((done / total) * 100) : 0) + '%';
    }
    const count = card.querySelector('.aicourse-ai-checklist-count');
    if (count) {
        count.textContent = fmt('checklistprogress', {done: done, total: total});
    }
    card.classList.toggle('is-complete', total > 0 && done === total);
};

/**
 * Finish a freshly rendered answer: number the quiz cards and prime the checklist meters.
 *
 * @param {Element} body The rendered .aicourse-ai-message-content.
 * @returns {void}
 */
const decorate = (body) => {
    body.querySelectorAll('.aicourse-ai-quizset').forEach((set) => {
        const cards = set.querySelectorAll('.aicourse-ai-quiz');
        if (cards.length < 2) {
            return;
        }
        cards.forEach((card, index) => {
            const badge = card.querySelector('.aicourse-ai-quiz-badge');
            if (badge) {
                badge.textContent = fmt('quizcounter', {num: index + 1, total: cards.length});
            }
        });
    });
    body.querySelectorAll('.aicourse-ai-checklist').forEach(updateChecklist);
};

/**
 * Scroll the conversation after a turn is added. A learner's own message pins the view to the
 * bottom; a tutor answer is scrolled to its START, because a long answer (a quiz, a checklist)
 * would otherwise open on its last line and the learner would have to scroll back up to read it.
 *
 * @param {Element|null} bubble The turn just added.
 * @param {Boolean} tobottom True to pin to the bottom.
 * @returns {void}
 */
const scrollToTurn = (bubble, tobottom) => {
    const messages = getMessages();
    if (!messages) {
        return;
    }
    if (tobottom || !bubble) {
        messages.scrollTop = messages.scrollHeight;
        return;
    }
    messages.scrollTop = Math.max(0, bubble.offsetTop - 12);
};

/**
 * Append one turn to the conversation.
 *
 * SECURITY: content is untrusted (learner input, an AI answer, or something replayed out of
 * sessionStorage, which any script or the user via devtools can plant). It is handed to a
 * Mustache template that renders it with a double mustache, so any markup in it becomes text. A
 * tutor answer is then re-rendered by format_aicourse/local/richtext, which builds nodes with
 * createElement / createTextNode and never parses HTML either.
 *
 * @param {String} content The message text.
 * @param {Boolean} isuser True for the learner's own message.
 * @param {String|Number} [chatid] Id of the stored message, when it can be rated.
 * @param {Boolean} [restored] True when replaying a stored conversation.
 * @param {Boolean} [iserror] True when this is an error shown in place of an answer.
 * @param {Object} [turn] The stored history entry, so state inside the answer can be kept.
 * @returns {Promise} Resolves once the turn is in the DOM.
 */
const appendMessage = (content, isuser, chatid, restored, iserror, turn) => enqueue(() => {
    const messages = getMessages();
    if (!messages) {
        return null;
    }
    const isbot = !isuser && !iserror;

    return Templates.render('format_aicourse/chatbox_message', {
        content: content,
        isuser: !!isuser,
        iserror: !!iserror,
        rateable: isbot && !!chatid && !restored,
        chatid: chatid ? String(chatid) : '',
        helpfullabel: strings.ratehelpful,
        nothelpfullabel: strings.ratenothelpful,
        copylabel: strings.copy,
        restored: !!restored && isbot && !!chatid,
        restoredlabel: strings.restored,
    }).then((html) => {
        Templates.appendNodeContents(messages, html, '');
        const bubble = messages.lastElementChild;
        if (isbot && bubble) {
            const body = bubble.querySelector('.aicourse-ai-message-content');
            if (body) {
                // Build off-DOM first: if rendering ever throws on an unexpected answer, the
                // escaped plain text the template already put there stays, with its line breaks,
                // instead of an empty bubble and an exception dialog on every page that replays it.
                try {
                    const rendered = renderRich(content, {
                        quizlabel: strings.quizlabel,
                        hint: strings.quizhint,
                        incomplete: strings.quizincomplete,
                    });
                    body.textContent = '';
                    body.appendChild(rendered);
                    body.classList.add('aicourse-ai-prose');
                    decorate(body);
                    if (turn) {
                        turnOf.set(bubble, turn);
                        replayTurnState(bubble, turn);
                    }
                } catch (error) {
                    body.textContent = content;
                    body.classList.add('aicourse-ai-plain');
                }
            }
            rawText.set(bubble, content);
            if (body && turn && turn.cut) {
                body.appendChild(cutOffNotice(turn.ask));
            }
        }
        scrollToTurn(bubble, !!isuser || !!restored);

        return null;
    });
});

/**
 * The note under an answer the service cut off, with a button that asks the question again.
 *
 * 3.2.2: the service stopped answers part-way, sometimes right after "Here are three practice
 * questions", and the learner was left with a sentence that went nowhere.
 *
 * @param {String} [question] The question that produced the answer.
 * @returns {Element} The notice.
 */
const cutOffNotice = (question) => {
    const notice = document.createElement('div');
    notice.className = 'aicourse-ai-notice aicourse-ai-cutoff';
    notice.setAttribute('role', 'status');
    const text = document.createElement('span');
    text.textContent = strings.cutoff;
    notice.appendChild(text);
    if (question) {
        notice.appendChild(makeFollowup(strings.askagain, question, true));
    }
    return notice;
};

/**
 * Append a turn and remember it in the stored conversation.
 *
 * @param {String} content The message text.
 * @param {Boolean} isuser True for the learner's own message.
 * @param {String|Number} [chatid] Id of the stored message, when it can be rated.
 * @param {Boolean} [iserror] True when this is an error shown in place of an answer.
 * @param {Object} [extra] cut: true when the answer was cut off; ask: the question asked.
 * @returns {Promise} Resolves once the turn is in the DOM.
 */
const addMessage = (content, isuser, chatid, iserror, extra) => {
    if (isuser) {
        hideQuickActions();
    }
    // Errors are shown once, where they happened; replaying "the service is down" on every page
    // for the rest of the session would only mislead.
    let turn = null;
    if (!iserror) {
        turn = {content: content, isUser: !!isuser, chatid: chatid};
        if (extra && extra.cut) {
            turn.cut = true;
            turn.ask = extra.ask || '';
        }
        history.push(turn);
        saveHistory();
    }

    return appendMessage(content, isuser, chatid, false, iserror, turn);
};

/**
 * Replay the stored conversation into the panel.
 *
 * @returns {void}
 */
const restoreHistory = () => {
    const stored = loadHistory();
    if (!stored.length) {
        return;
    }
    history = stored;
    firstmessage = false;
    stored.forEach((message) => {
        if (message.isError) {
            return;
        }
        appendMessage(message.content, message.isUser, message.chatid, true, false, message);
    });
    hideQuickActions();
};

/**
 * Start a fresh conversation: forget the stored turns and bring the study tools back. The
 * server-side tutor memory for the activity is deliberately left alone.
 *
 * @returns {void}
 */
const newConversation = () => {
    if (loading) {
        return;
    }
    history = [];
    saveHistory();
    firstmessage = true;
    enqueue(() => {
        const messages = getMessages();
        if (messages) {
            messages.querySelectorAll('.aicourse-ai-message').forEach((node) => {
                if (node.id !== SELECTORS.welcome) {
                    node.remove();
                }
            });
            messages.scrollTop = 0;
        }
        showQuickActions();
        const input = getInput();
        if (input) {
            input.focus();
        }

        return null;
    });
};

/**
 * Show the "the tutor is composing an answer" turn.
 *
 * @returns {Promise} Resolves once the turn is in the DOM.
 */
const showLoading = () => enqueue(() => {
    const messages = getMessages();
    if (!messages) {
        return null;
    }

    return Templates.render('format_aicourse/chatbox_loading', {thinkinglabel: strings.thinking})
        .then((html) => {
            Templates.appendNodeContents(messages, html, '');
            messages.setAttribute('aria-busy', 'true');
            messages.scrollTop = messages.scrollHeight;

            return null;
        });
});

/**
 * Remove the "composing an answer" turn.
 *
 * @returns {Promise} Resolves once the turn is gone.
 */
const hideLoading = () => enqueue(() => {
    const bubble = document.getElementById(SELECTORS.loading);
    if (bubble) {
        bubble.remove();
    }
    const messages = getMessages();
    if (messages) {
        messages.setAttribute('aria-busy', 'false');
    }

    return null;
});

/**
 * Remember the server rendered greeting so it can be put back when the question context goes away.
 *
 * @returns {void}
 */
const snapshotWelcome = () => {
    const body = document.querySelector(SELECTORS.welcomebody);
    if (body && !welcomeSnapshot) {
        welcomeSnapshot = body.cloneNode(true);
    }
};

/**
 * Put the server rendered greeting back, node by node so no HTML string is ever parsed.
 *
 * @returns {void}
 */
const restoreWelcome = () => {
    const body = document.querySelector(SELECTORS.welcomebody);
    if (!body || !welcomeSnapshot) {
        return;
    }
    while (body.firstChild) {
        body.removeChild(body.firstChild);
    }
    const clone = welcomeSnapshot.cloneNode(true);
    while (clone.firstChild) {
        body.appendChild(clone.firstChild);
    }
};

/**
 * Refresh the greeting so it names the question the learner is currently looking at.
 *
 * The greeting is written with textContent, so the question text lifted out of the page can never
 * inject markup.
 *
 * @returns {Promise} Resolves once the greeting is up to date.
 */
const updateWelcomeMessage = () => {
    const body = document.querySelector(SELECTORS.welcomebody);
    if (!body) {
        return Promise.resolve();
    }
    snapshotWelcome();

    const context = window.AICOURSE_QUIZ_CONTEXT;
    if (!context || !context.questionNumber) {
        restoreWelcome();

        return Promise.resolve();
    }

    let pending;
    if (context.questionText) {
        let topic = context.questionText.substring(0, TOPIC_LENGTH);
        if (context.questionText.length > TOPIC_LENGTH) {
            topic += '...';
        }
        pending = getString('aiassistant_welcome_question', 'format_aicourse', {
            num: context.questionNumber,
            topic: topic,
        });
    } else {
        pending = getString('aiassistant_welcome_questionnotopic', 'format_aicourse', context.questionNumber);
    }

    return pending.then((message) => {
        body.textContent = message;

        return message;
    }).catch(Notification.exception);
};

/**
 * Ask the server for the questions or instructions of the current activity.
 *
 * @param {String|Number} [slot] The question slot the learner is looking at.
 * @returns {Promise} Resolves once the context has been stored.
 */
const fetchActivityContext = (slot) => {
    if (!config.activityid) {
        return Promise.resolve();
    }

    return callExternal('get_activity_context', {
        activityid: config.activityid,
        questionslot: parseInt(slot, 10) || 0,
    }).then((data) => {
        if (!data || !data.context) {
            return null;
        }
        window.AICOURSE_ACTIVITY_CONTEXT = data.context;

        if (data.context.type === 'assign' && data.context.intro) {
            window.AICOURSE_QUIZ_CONTEXT = {
                slot: 0,
                questionNumber: 0,
                questionText: data.context.intro.substring(0, INTRO_LENGTH),
            };
        }
        // The external function omits currentquestion entirely when no slot matched.
        if (data.context.currentquestion) {
            window.AICOURSE_QUIZ_CONTEXT = {
                slot: data.context.currentquestion.slot,
                questionNumber: data.context.currentquestion.slot,
                questionText: data.context.currentquestion.text.substring(0, INTRO_LENGTH),
            };
        }

        return updateWelcomeMessage();
    }).catch(() => {
        // Activity context is an enhancement: without it the tutor simply answers with less
        // context. A failure here must never surface to the learner or break the panel.
        return null;
    });
};

/**
 * Work out which question the learner is looking at, from the page itself.
 *
 * @returns {void}
 */
const updateQuizContext = () => {
    const currentButton = document.querySelector('.qnbutton.current');
    const questionElement = document.querySelector('.que .qtext');

    if (currentButton) {
        const slot = currentButton.getAttribute('data-slot');
        const text = questionElement ? questionElement.innerText.trim() : '';
        window.AICOURSE_QUIZ_CONTEXT = {
            slot: slot,
            questionNumber: slot,
            questionText: text.substring(0, INTRO_LENGTH),
        };
        if (!text) {
            fetchActivityContext(slot);
        }

        return;
    }

    const aiquizQuestion = document.querySelector('.aiquiz-question-text, .knowledgecheck-question');
    if (aiquizQuestion) {
        const slotElement = document.querySelector('[data-questionslot], [data-slot]');
        const slot = slotElement
            ? (slotElement.getAttribute('data-questionslot') || slotElement.getAttribute('data-slot'))
            : '1';
        window.AICOURSE_QUIZ_CONTEXT = {
            slot: slot,
            questionNumber: slot,
            questionText: aiquizQuestion.innerText.trim().substring(0, INTRO_LENGTH),
        };

        return;
    }

    if (window.AICOURSE_ACTIVITY_CONTEXT) {
        return;
    }

    window.AICOURSE_QUIZ_CONTEXT = null;
};

/**
 * Whether the learner last chose the Study view.
 *
 * @returns {Boolean} True for the Study view.
 */
const prefersExpanded = () => {
    try {
        return window.localStorage.getItem(VIEW_KEY) === 'expanded';
    } catch (error) {
        return false;
    }
};

/**
 * Whether the panel is showing the Study view.
 *
 * @returns {Boolean} True when expanded.
 */
const isExpanded = () => {
    const panel = getPanel();
    return !!panel && panel.getAttribute('data-view') === 'expanded';
};

/**
 * Switch between the compact panel and the Study view.
 *
 * Only data-view changes; the conversation, focus and scroll position are untouched. The backdrop
 * and the page scroll lock exist only while the Study view is open.
 *
 * @param {Boolean} expanded True for the Study view.
 * @param {Boolean} remember True to remember the choice for the next time the tutor opens.
 * @returns {void}
 */
const setView = (expanded, remember) => {
    const panel = getPanel();
    if (!panel) {
        return;
    }
    const messages = getMessages();
    let anchor = null;
    let anchoroffset = 0;
    if (messages && isOpen()) {
        anchor = Array.prototype.find.call(messages.children,
            (child) => child.offsetTop + child.offsetHeight > messages.scrollTop) || null;
        anchoroffset = anchor ? anchor.offsetTop - messages.scrollTop : 0;
    }
    panel.setAttribute('data-view', expanded ? 'expanded' : 'compact');
    // Only the Study view covers the page. The compact panel sits beside the course, so it is a
    // non-modal dialog and Tab may leave it.
    panel.setAttribute('aria-modal', expanded ? 'true' : 'false');
    const showing = expanded && isOpen();
    const backdrop = document.getElementById(SELECTORS.backdrop);
    if (backdrop) {
        backdrop.hidden = !showing;
    }
    document.documentElement.classList.toggle('aicourse-ai-locked', showing);
    panel.querySelectorAll(SELECTORS.expand).forEach((button) => {
        const label = button.getAttribute(expanded ? 'data-collapselabel' : 'data-expandlabel') || '';
        button.setAttribute('aria-pressed', expanded ? 'true' : 'false');
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    });
    if (remember) {
        try {
            window.localStorage.setItem(VIEW_KEY, expanded ? 'expanded' : 'compact');
        } catch (error) {
            // Storage blocked: the choice simply is not remembered.
        }
    }
    // Keep the turn the learner was reading in place: the column width changes, so a raw
    // scrollTop would land somewhere else entirely.
    if (anchor && messages) {
        messages.scrollTop = Math.max(0, anchor.offsetTop - anchoroffset);
    }
};

/**
 * Reflect the open state on every AI Tutor button in the hero banner.
 *
 * @param {Boolean} open True when the panel is open.
 * @returns {void}
 */
const updateButtonState = (open) => {
    document.querySelectorAll(SELECTORS.herobtn).forEach((button) => {
        button.classList.toggle('active', open);
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
};

/**
 * Open the panel and move focus into it.
 *
 * @param {Element|null} trigger The element that opened it, so focus can be restored later.
 * @param {Boolean} [compact] True to open in the compact view whatever the saved preference.
 * @returns {void}
 */
const openPanel = (trigger, compact) => {
    const panel = getPanel();
    if (!panel) {
        return;
    }
    opener = trigger || document.activeElement;
    panel.style.display = 'flex';
    // Lets the first-visit tour offer step aside instead of covering the composer.
    document.body.classList.add('aicourse-ai-open');
    // The first-visit introduction always uses the small panel: a full-screen overlay is not a
    // fair surprise on someone's first look at a new course.
    setView(compact ? false : prefersExpanded(), false);
    updateButtonState(true);
    const input = getInput();
    if (input) {
        input.focus();
    }
    if (config.activitytype === 'quiz') {
        updateQuizContext();
        updateWelcomeMessage();
    }
};

/**
 * Close the panel and restore focus to whatever opened it.
 *
 * @returns {void}
 */
const closePanel = () => {
    const panel = getPanel();
    if (!panel) {
        return;
    }
    panel.style.display = 'none';
    document.body.classList.remove('aicourse-ai-open');
    const backdrop = document.getElementById(SELECTORS.backdrop);
    if (backdrop) {
        backdrop.hidden = true;
    }
    document.documentElement.classList.remove('aicourse-ai-locked');
    updateButtonState(false);
    if (opener && opener.nodeType === 1 && document.contains(opener)) {
        opener.focus();
    }
    opener = null;
};

/**
 * Mark the panel busy while an answer is in flight, so controls that would send another question
 * (follow-ups, study tools, unkeyed quiz options, New conversation) look and act unavailable
 * instead of silently doing nothing.
 *
 * @param {Boolean} busy True while waiting for an answer.
 * @returns {void}
 */
const setBusy = (busy) => {
    loading = busy;
    const panel = getPanel();
    if (panel) {
        panel.classList.toggle('aicourse-ai-busy', busy);
    }
};

/**
 * Send whatever is in the textarea to the AI Tutor.
 *
 * @returns {void}
 */
const sendMessage = () => {
    const input = getInput();
    if (!input || !getMessages()) {
        return;
    }

    const question = input.value.trim();
    if (!question || loading) {
        return;
    }

    addMessage(question, true);
    input.value = '';
    input.style.height = 'auto';
    setBusy(true);
    showLoading();

    const params = {
        question: question,
        activityid: config.activityid,
        sectionid: config.sectionid,
        isfirstmessage: firstmessage,
    };
    firstmessage = false;

    const questioncontext = window.AICOURSE_QUIZ_CONTEXT;
    if (questioncontext) {
        params.questionslot = parseInt(questioncontext.questionNumber, 10) || 0;
        params.questiontext = questioncontext.questionText || '';
    }

    const activitycontext = window.AICOURSE_ACTIVITY_CONTEXT;
    if (activitycontext && activitycontext.questions && activitycontext.questions.length) {
        params.allquestions = activitycontext.questions.map(
            (question2) => 'Q' + question2.slot + ': ' + question2.text.substring(0, QUESTION_LENGTH)
        ).join(' | ');
    }

    callExternal('ai_chat', params).then((data) => {
        hideLoading();
        setBusy(false);
        addMessage(data.answer, false, data.chatid, false, {cut: !!data.truncated, ask: question});

        return data;
    }).catch((error) => {
        // The service is down, the session expired, the throttle tripped or the AI service
        // refused. Say so inside the conversation, where the learner is looking, instead of
        // throwing at the console. Moodle exceptions carry a translated .message.
        hideLoading();
        setBusy(false);
        addMessage((error && error.message) || strings.error, false, null, true);

        return null;
    });
};

/**
 * Send a prepared question as though the learner had typed it.
 *
 * @param {String} text The question.
 * @returns {Boolean} False when nothing was sent because an answer is still in flight.
 */
const sendText = (text) => {
    const input = getInput();
    if (!input || loading || !text) {
        return false;
    }
    input.value = text;
    sendMessage();
    return true;
};

/**
 * Make a follow-up button for a quiz card.
 *
 * @param {String} label Visible label.
 * @param {String} prompt The question it sends.
 * @param {Boolean} primary True for the emphasised button.
 * @returns {Element} The button.
 */
const makeFollowup = (label, prompt, primary) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'aicourse-ai-followup' + (primary ? ' is-primary' : '');
    button.setAttribute('data-followup', prompt);
    button.textContent = label;
    return button;
};

/**
 * Show the running score once every question in a set has been answered.
 *
 * @param {Element|null} set The .aicourse-ai-quizset.
 * @returns {void}
 */
const updateScore = (set) => {
    if (!set) {
        return;
    }
    const cards = set.querySelectorAll('.aicourse-ai-quiz');
    const score = set.querySelector('.aicourse-ai-quizset-score');
    if (!score || cards.length < 2) {
        return;
    }
    let answered = 0;
    let right = 0;
    cards.forEach((card) => {
        const state = card.getAttribute('data-state');
        if (state === 'correct' || state === 'incorrect') {
            answered++;
        }
        if (state === 'correct') {
            right++;
        }
    });
    if (answered < cards.length) {
        return;
    }
    score.textContent = fmt('quizscore', {score: right, total: cards.length});
    score.setAttribute('data-perfect', right === cards.length ? 'true' : 'false');
    score.hidden = false;
};

/**
 * Move focus to a quiz card's feedback. The option the learner pressed has just been disabled,
 * which would otherwise drop focus to the page body and send the next Tab back to the top.
 *
 * @param {Element} feedback The .aicourse-ai-quiz-feedback element.
 * @returns {void}
 */
const focusFeedback = (feedback) => {
    feedback.setAttribute('tabindex', '-1');
    feedback.focus({preventScroll: true});
};

/**
 * Put a practice question card into its answered state.
 *
 * Used when the learner answers, and silently when a stored conversation is replayed, so a card
 * answered on one page is still answered on the next.
 *
 * @param {Element} card The .aicourse-ai-quiz card.
 * @param {Number} chosen Index of the option chosen.
 * @param {Boolean} silent True when replaying: no focus move, no message sent.
 * @returns {void}
 */
const markQuiz = (card, chosen, silent) => {
    const answer = parseInt(card.getAttribute('data-answer'), 10);
    const options = card.querySelectorAll(SELECTORS.quizoption);
    const button = options[chosen];
    if (!button) {
        return;
    }
    const optiontext = (index) => {
        const text = options[index] && options[index].querySelector('.aicourse-ai-quiz-text');
        return text ? text.textContent.trim() : '';
    };
    const questionnode = card.querySelector('.aicourse-ai-quiz-question');
    const question = truncate(plainText(questionnode ? questionnode.textContent : ''), 180);

    options.forEach((option) => {
        option.disabled = true;
    });
    const retrynote = card.querySelector('.aicourse-ai-quiz-retry');
    if (retrynote) {
        retrynote.remove();
    }
    button.classList.add('is-chosen');
    button.setAttribute('aria-pressed', 'true');

    const feedback = card.querySelector('.aicourse-ai-quiz-feedback');
    const verdict = card.querySelector('.aicourse-ai-quiz-verdict');
    const actions = card.querySelector('.aicourse-ai-quiz-actions');
    const hintwrap = card.querySelector('.aicourse-ai-quiz-hintwrap');
    if (hintwrap) {
        hintwrap.hidden = true;
    }

    if (isNaN(answer) || answer < 0 || answer >= options.length) {
        card.setAttribute('data-state', 'sent');
        verdict.textContent = strings.quizsent;
        feedback.hidden = false;
        if (!silent) {
            focusFeedback(feedback);
            sendText(fmt('quizchoose', {question: question, letter: LETTERS[chosen], option: optiontext(chosen)}));
        }
        return;
    }

    const correct = chosen === answer;
    card.setAttribute('data-state', correct ? 'correct' : 'incorrect');
    options[answer].classList.add('is-answer');
    if (!correct) {
        button.classList.add('is-wrong');
    }
    verdict.textContent = correct ? strings.quizcorrect : fmt('quizincorrect', {answer: LETTERS[answer]});
    while (actions.firstChild) {
        actions.removeChild(actions.firstChild);
    }
    actions.appendChild(makeFollowup(strings.quizexplain, fmt('quizexplainprompt', {
        question: question,
        letter: LETTERS[answer],
        option: optiontext(answer),
    }), false));
    const set = card.closest('.aicourse-ai-quizset');
    const cards = set ? set.querySelectorAll('.aicourse-ai-quiz') : [];
    if (!cards.length || cards[cards.length - 1] === card) {
        actions.appendChild(makeFollowup(strings.quizanother, strings.quizanotherprompt, true));
    }
    feedback.hidden = false;
    if (!silent) {
        focusFeedback(feedback);
    }
    updateScore(set);
};

/**
 * The learner picked an option on a practice question.
 *
 * When the tutor supplied the answer (a fenced "quiz" block, or "Answer: B" after plain-text
 * options) the card marks itself and reveals the explanation at once. When it did not, the choice
 * is sent to the tutor as the next message so the tutor can mark it. Either way the choice is kept
 * in the stored conversation.
 *
 * @param {Element} button The option pressed.
 * @returns {void}
 */
const answerQuiz = (button) => {
    const card = button.closest('.aicourse-ai-quiz');
    if (!card || card.getAttribute('data-state') !== 'open') {
        return;
    }
    const answer = parseInt(card.getAttribute('data-answer'), 10);
    if (answer < 0 && loading) {
        return;
    }
    const chosen = parseInt(button.getAttribute('data-index'), 10);
    const cardindex = (bubble) => Array.prototype.indexOf.call(bubble.querySelectorAll('.aicourse-ai-quiz'), card);

    // 3.2.3: one retry before the answer is revealed. Trying again after a nudge is where most of
    // the learning in a practice question happens. The first wrong choice is struck out and the
    // learner is told to have another go (the hint stays open to them); only a second wrong choice
    // reveals the answer. The try is stored with the conversation, so a reload does not hand the
    // learner a free second attempt -- or take one away.
    if (canRetry(card, answer) && chosen !== answer) {
        rememberInTurn(card, (turn, bubble) => {
            turn.tried = turn.tried || {};
            turn.tried[cardindex(bubble)] = chosen;
        });
        markTry(card, chosen, false);
        return;
    }
    rememberInTurn(card, (turn, bubble) => {
        turn.quiz = turn.quiz || {};
        turn.quiz[cardindex(bubble)] = chosen;
    });
    markQuiz(card, chosen, false);
};

/**
 * Whether a practice question still has its one retry.
 *
 * Only when the tutor supplied the answer, the card has more than two options (with two, a retry
 * is the answer), and no wrong choice has been made yet.
 *
 * @param {Element} card The .aicourse-ai-quiz card.
 * @param {Number} answer Index of the correct option, or -1.
 * @returns {Boolean}
 */
const canRetry = (card, answer) => !isNaN(answer) && answer >= 0
    && card.querySelectorAll(SELECTORS.quizoption).length > 2
    && !card.hasAttribute('data-tried');

/**
 * Strike out a first wrong choice and ask the learner to try again.
 *
 * @param {Element} card The .aicourse-ai-quiz card.
 * @param {Number} chosen Index of the option chosen.
 * @param {Boolean} silent True when replaying a stored conversation: no focus move.
 * @returns {void}
 */
const markTry = (card, chosen, silent) => {
    const options = card.querySelectorAll(SELECTORS.quizoption);
    const button = options[chosen];
    if (!button) {
        return;
    }
    card.setAttribute('data-tried', String(chosen));
    button.disabled = true;
    button.classList.add('is-tried');
    button.setAttribute('aria-pressed', 'true');
    let note = card.querySelector('.aicourse-ai-quiz-retry');
    if (!note) {
        note = document.createElement('p');
        note.className = 'aicourse-ai-quiz-retry';
        note.setAttribute('role', 'status');
        const feedback = card.querySelector('.aicourse-ai-quiz-feedback');
        if (feedback && feedback.parentNode) {
            feedback.parentNode.insertBefore(note, feedback);
        } else {
            card.appendChild(note);
        }
    }
    note.textContent = strings.quiztryagain;
    if (!silent) {
        // The pressed option is now disabled; keep keyboard users on the remaining choices.
        const next = Array.prototype.find.call(options, (option) => !option.disabled);
        if (next) {
            next.focus();
        }
    }
};

/**
 * Record something the learner did inside a tutor answer on that answer's stored turn.
 *
 * @param {Element} element An element inside the answer.
 * @param {Function} change Called with (turn, bubble) to update the stored turn.
 * @returns {void}
 */
const rememberInTurn = (element, change) => {
    const bubble = element.closest('.aicourse-ai-message');
    const turn = bubble ? turnOf.get(bubble) : null;
    if (!turn) {
        return;
    }
    change(turn, bubble);
    saveHistory();
};

/**
 * Replay what the learner had done inside a stored answer: answered questions, ticked boxes.
 *
 * @param {Element} bubble The answer's .aicourse-ai-message.
 * @param {Object} turn The stored turn.
 * @returns {void}
 */
const replayTurnState = (bubble, turn) => {
    if (turn.tried && typeof turn.tried === 'object') {
        const cards = bubble.querySelectorAll('.aicourse-ai-quiz');
        Object.keys(turn.tried).forEach((index) => {
            const card = cards[parseInt(index, 10)];
            const chosen = parseInt(turn.tried[index], 10);
            if (card && !isNaN(chosen)) {
                markTry(card, chosen, true);
            }
        });
    }
    if (turn.quiz && typeof turn.quiz === 'object') {
        const cards = bubble.querySelectorAll('.aicourse-ai-quiz');
        Object.keys(turn.quiz).forEach((index) => {
            const card = cards[parseInt(index, 10)];
            const chosen = parseInt(turn.quiz[index], 10);
            if (card && !isNaN(chosen)) {
                markQuiz(card, chosen, true);
            }
        });
    }
    if (Array.isArray(turn.checks)) {
        const boxes = bubble.querySelectorAll(SELECTORS.checkbox);
        turn.checks.forEach((checked, index) => {
            if (boxes[index]) {
                boxes[index].checked = !!checked;
            }
        });
        bubble.querySelectorAll('.aicourse-ai-checklist').forEach(updateChecklist);
    }
};

/**
 * Copy a tutor answer's original text.
 *
 * @param {Element} button The copy button.
 * @returns {void}
 */
const copyAnswer = (button) => {
    const bubble = button.closest('.aicourse-ai-message');
    const content = bubble && bubble.querySelector('.aicourse-ai-message-content');
    const text = (bubble && rawText.get(bubble)) || (content ? content.innerText : '');
    const label = button.querySelector('.aicourse-ai-copy-text');
    const done = () => {
        button.classList.add('is-copied');
        if (label) {
            label.textContent = strings.copied;
        }
        window.setTimeout(() => {
            button.classList.remove('is-copied');
            if (label) {
                label.textContent = strings.copy;
            }
        }, 1800);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(() => null);
        return;
    }
    const scratch = document.createElement('textarea');
    scratch.value = text;
    scratch.setAttribute('readonly', '');
    scratch.style.position = 'fixed';
    scratch.style.opacity = '0';
    document.body.appendChild(scratch);
    scratch.select();
    try {
        if (document.execCommand('copy')) {
            done();
        }
    } catch (error) {
        // Clipboard unavailable; nothing else to do.
    }
    scratch.remove();
    button.focus();
};

/**
 * Handle a click anywhere in the page. Delegation is required because the panel is injected into
 * the page after load on activity and section pages, and answers are added after that.
 *
 * @param {Event} event The click event.
 * @returns {void}
 */
const handleClick = (event) => {
    const target = event.target;
    if (!target || typeof target.closest !== 'function') {
        return;
    }

    if (target.closest(SELECTORS.close)) {
        event.preventDefault();
        closePanel();
        return;
    }

    const toggleTarget = target.closest(SELECTORS.toggle);
    if (toggleTarget) {
        event.preventDefault();
        if (isOpen()) {
            closePanel();
        } else {
            openPanel(toggleTarget);
        }
        return;
    }

    if (target.id === SELECTORS.backdrop) {
        event.preventDefault();
        setView(false, true);
        return;
    }

    if (target.closest(SELECTORS.expand)) {
        event.preventDefault();
        setView(!isExpanded(), true);
        return;
    }

    if (target.closest(SELECTORS.newchat)) {
        event.preventDefault();
        newConversation();
        return;
    }

    if (target.closest(SELECTORS.sendbtn)) {
        event.preventDefault();
        sendMessage();
        return;
    }

    const quickTarget = target.closest(SELECTORS.quickbtn);
    if (quickTarget) {
        event.preventDefault();
        const key = quickTarget.getAttribute('data-prompt');
        const prompt = key ? strings['prompt' + key] : '';
        if (prompt) {
            // A function replacement, so "$&" or "$1" in an activity name is not interpreted.
            const activity = config.activityname || strings.thisactivity;
            sendText(prompt.replace('{activity}', () => activity));
        }
        return;
    }

    const optionTarget = target.closest(SELECTORS.quizoption);
    if (optionTarget) {
        event.preventDefault();
        answerQuiz(optionTarget);
        return;
    }

    const hintTarget = target.closest(SELECTORS.quizhint);
    if (hintTarget) {
        event.preventDefault();
        const hint = hintTarget.parentNode.querySelector('.aicourse-ai-quiz-hint');
        const open = hintTarget.getAttribute('aria-expanded') === 'true';
        hintTarget.setAttribute('aria-expanded', open ? 'false' : 'true');
        if (hint) {
            hint.hidden = open;
        }
        return;
    }

    const followTarget = target.closest(SELECTORS.followup);
    if (followTarget) {
        event.preventDefault();
        sendText(followTarget.getAttribute('data-followup'));
        return;
    }

    const copyTarget = target.closest(SELECTORS.copybtn);
    if (copyTarget) {
        event.preventDefault();
        copyAnswer(copyTarget);
        return;
    }

    const rateTarget = target.closest(SELECTORS.ratebtn);
    if (rateTarget) {
        rateChat(rateTarget);
    }
};

/**
 * Submit a rating for one answer and replace the buttons with a thank you.
 *
 * @param {Element} button The rating button that was pressed.
 * @returns {void}
 */
const rateChat = (button) => {
    const rating = button.closest(SELECTORS.rating);
    if (!rating) {
        return;
    }
    const chatid = rating.getAttribute('data-chatid');
    const rate = button.getAttribute('data-rate');
    if (!chatid || !rate) {
        return;
    }

    callExternal('rate_chat', {
        chatid: parseInt(chatid, 10),
        rating: parseInt(rate, 10),
    }).catch(() => {
        // Ratings are fire and forget telemetry. A failed rating is not worth interrupting the
        // conversation for, but it must still be caught so it never reaches the console.
        return null;
    });

    rating.textContent = strings.thanks;
    rating.className = 'aicourse-ai-rating-done';
};

/**
 * Escape closes the panel; Tab is trapped inside it while it is open.
 *
 * @param {KeyboardEvent} event The keydown event.
 * @returns {void}
 */
const handleDialogKeys = (event) => {
    if (!isOpen()) {
        return;
    }

    if (event.key === 'Escape' || event.keyCode === 27) {
        event.preventDefault();
        // Escape steps out of the Study view first, then closes, as full-screen views do.
        if (isExpanded()) {
            setView(false, true);
            return;
        }
        closePanel();

        return;
    }

    if (event.key !== 'Tab' && event.keyCode !== 9) {
        return;
    }
    if (!isExpanded()) {
        return;
    }

    const panel = getPanel();
    const focusable = Array.prototype.filter.call(
        panel.querySelectorAll(FOCUSABLE),
        (element) => element.offsetWidth > 0 || element.offsetHeight > 0 || element === document.activeElement
    );
    if (!focusable.length) {
        event.preventDefault();

        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (!panel.contains(event.target)) {
        event.preventDefault();
        (event.shiftKey ? last : first).focus();
    } else if (event.shiftKey && event.target === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && event.target === last) {
        event.preventDefault();
        first.focus();
    }
};

/**
 * Enter sends, Shift + Enter inserts a newline, and the textarea grows with its content.
 *
 * @returns {void}
 */
const registerInputHandlers = () => {
    document.addEventListener('keydown', (event) => {
        if (event.target && event.target.id === SELECTORS.input && event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });

    document.addEventListener('change', (event) => {
        if (event.target && event.target.matches && event.target.matches(SELECTORS.checkbox)) {
            updateChecklist(event.target.closest('.aicourse-ai-checklist'));
            rememberInTurn(event.target, (turn, bubble) => {
                turn.checks = Array.prototype.map.call(bubble.querySelectorAll(SELECTORS.checkbox), (box) => box.checked);
            });
        }
    });

    document.addEventListener('input', (event) => {
        if (event.target && event.target.id === SELECTORS.input) {
            event.target.style.height = 'auto';
            event.target.style.height = Math.min(event.target.scrollHeight, 180) + 'px';
        }
    });
};

/**
 * Watch for the learner moving between quiz questions.
 *
 * @returns {void}
 */
const registerQuestionNavigation = () => {
    document.addEventListener('click', (event) => {
        if (!event.target || typeof event.target.closest !== 'function') {
            return;
        }
        if (!event.target.closest('.qnbutton')) {
            return;
        }
        window.setTimeout(() => {
            updateQuizContext();
            const current = document.querySelector('.qnbutton.current');
            const slot = current ? current.getAttribute('data-slot') : null;
            if (slot) {
                fetchActivityContext(slot);
            }
        }, 100);
    });
};

/**
 * Run a callback once the panel is in the DOM. On activity and section pages the hero banner, and
 * with it the panel, is injected after the page has loaded, so it may not be there yet.
 *
 * @param {Function} callback Called with no arguments once the panel exists.
 * @param {Number} [attempts] Remaining polls before giving up.
 * @returns {void}
 */
const whenPanelReady = (callback, attempts) => {
    const remaining = attempts === undefined ? 40 : attempts;
    if (getPanel()) {
        callback();

        return;
    }
    if (remaining <= 0) {
        return;
    }
    window.setTimeout(() => whenPanelReady(callback, remaining - 1), 100);
};

/**
 * Fetch every language string the module needs.
 *
 * @returns {Promise} Resolves once strings is populated.
 */
const loadStrings = () => {
    const aliases = Object.keys(STRING_IDS);
    const paramaliases = Object.keys(PARAM_STRING_IDS);
    const request = aliases.map((alias) => ({key: STRING_IDS[alias], component: 'format_aicourse'}))
        .concat(paramaliases.map((alias) => ({
            key: PARAM_STRING_IDS[alias][0],
            component: 'format_aicourse',
            param: PARAM_STRING_IDS[alias][1],
        })));

    return getStrings(request).then((values) => {
        aliases.concat(paramaliases).forEach((alias, index) => {
            strings[alias] = values[index];
        });

        return strings;
    });
};

/**
 * Start the AI Tutor chat panel.
 *
 * Safe to call more than once: format.php and the before_footer_html_generation hook both queue
 * it, and Moodle does not de-duplicate js_call_amd() requests.
 *
 * @param {Object} initconfig Server supplied configuration.
 * @param {Number} initconfig.courseid Id of the course the panel belongs to.
 * @param {Number} initconfig.userid Id of the current user, used to key the stored conversation.
 * @param {Number} initconfig.activityid Id of the course module being viewed, or 0.
 * @param {String} initconfig.activityname Name of the course module being viewed, or ''.
 * @param {String} initconfig.activitytype Module name of the course module being viewed, or ''.
 * @param {Number} initconfig.sectionid Id or number of the section being viewed, or 0.
 * @param {Boolean} initconfig.contextaware True when the server can supply question context for
 *                  this activity type.
 * @returns {void}
 */
export const init = (initconfig) => {
    if (initialised) {
        return;
    }
    initialised = true;
    config = initconfig || {};
    window.AICOURSE_QUIZ_CONTEXT = null;
    window.AICOURSE_ACTIVITY_CONTEXT = null;

    stringsReady = loadStrings();
    renderQueue = stringsReady.catch((error) => {
        Notification.exception(error);

        return null;
    });

    document.addEventListener('click', handleClick);
    document.addEventListener('keydown', handleDialogKeys);
    registerInputHandlers();

    whenPanelReady(() => {
        snapshotWelcome();
        restoreHistory();
    });

    if (config.contextaware && config.activityid) {
        fetchActivityContext(0);
        updateQuizContext();
        registerQuestionNavigation();
    }

    // ACF-FIX-2.1.51: introduce the tutor once, on a user's first visit to the course.
    //
    // A round icon in a banner is easy to miss, and a tutor nobody opens is a tutor nobody
    // benefits from. Opening the panel once, the first time someone lands on the course, is the
    // cheapest way to say "this exists" -- and because it is the panel itself rather than a
    // notice about the panel, they can simply start typing.
    //
    // Once per course per user, recorded in a user preference exactly like the tour, so it does
    // not reappear on every visit. Never while editing: a teacher arranging a course does not
    // want a chat panel opening over it. Never on top of the tour either, which is doing the same
    // introducing job more thoroughly.
    if (config.introduce && !document.body.classList.contains('editing')
            && !document.querySelector('.aicourse-tour-offer, .aicourse-tour')) {
        window.setTimeout(() => {
            if (document.querySelector('.aicourse-tour-offer, .aicourse-tour')) {
                return;
            }
            const toggle = document.querySelector(SELECTORS.toggle);
            openPanel(toggle, true);
            Ajax.call([{
                methodname: 'core_user_update_user_preferences',
                args: {
                    preferences: [{
                        type: 'format_aicourse_tutor_seen_' + config.courseid,
                        value: '1',
                    }],
                },
            }])[0].catch(() => null);
        }, 1200);
    }
};
