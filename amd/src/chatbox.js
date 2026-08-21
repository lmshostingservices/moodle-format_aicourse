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
};

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
 * Hide the quick action buttons once the conversation has started.
 *
 * @returns {void}
 */
const hideQuickActions = () => {
    const quickactions = document.getElementById(SELECTORS.quickactions);
    if (quickactions) {
        quickactions.style.display = 'none';
    }
};

/**
 * Append one bubble to the conversation.
 *
 * SECURITY: content is untrusted (learner input, an AI answer, or something replayed out of
 * sessionStorage, which any script or the user via devtools can plant). It is handed to a
 * Mustache template that renders it with a double mustache, so any markup in it becomes text.
 *
 * @param {String} content The message text.
 * @param {Boolean} isuser True for the learner's own message.
 * @param {String|Number} [chatid] Id of the stored message, when it can be rated.
 * @param {Boolean} [restored] True when replaying a stored conversation.
 * @returns {Promise} Resolves once the bubble is in the DOM.
 */
const appendMessage = (content, isuser, chatid, restored) => enqueue(() => {
    const messages = getMessages();
    if (!messages) {
        return null;
    }

    return Templates.render('format_aicourse/chatbox_message', {
        content: content,
        isuser: !!isuser,
        rateable: !isuser && !!chatid && !restored,
        chatid: chatid ? String(chatid) : '',
        helpfullabel: strings.ratehelpful,
        nothelpfullabel: strings.ratenothelpful,
        restored: !!restored && !isuser && !!chatid,
        restoredlabel: strings.restored,
    }).then((html) => {
        Templates.appendNodeContents(messages, html, '');
        messages.scrollTop = messages.scrollHeight;

        return null;
    });
});

/**
 * Append a bubble and remember it in the stored conversation.
 *
 * @param {String} content The message text.
 * @param {Boolean} isuser True for the learner's own message.
 * @param {String|Number} [chatid] Id of the stored message, when it can be rated.
 * @returns {Promise} Resolves once the bubble is in the DOM.
 */
const addMessage = (content, isuser, chatid) => {
    if (isuser) {
        hideQuickActions();
    }
    history.push({content: content, isUser: !!isuser, chatid: chatid});
    saveHistory();

    return appendMessage(content, isuser, chatid, false);
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
        appendMessage(message.content, message.isUser, message.chatid, true);
    });
    hideQuickActions();
};

/**
 * Show the "the tutor is composing an answer" bubble.
 *
 * @returns {Promise} Resolves once the bubble is in the DOM.
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
 * Remove the "composing an answer" bubble.
 *
 * @returns {Promise} Resolves once the bubble is gone.
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
 * @returns {void}
 */
const openPanel = (trigger) => {
    const panel = getPanel();
    if (!panel) {
        return;
    }
    opener = trigger || document.activeElement;
    panel.style.display = 'flex';
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
    updateButtonState(false);
    if (opener && opener.nodeType === 1 && document.contains(opener)) {
        opener.focus();
    }
    opener = null;
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
    loading = true;
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
        loading = false;
        addMessage(data.answer, false, data.chatid);

        return data;
    }).catch((error) => {
        // The service is down, the session expired, the throttle tripped or the AI service
        // refused. Say so inside the conversation, where the learner is looking, instead of
        // throwing at the console. Moodle exceptions carry a translated .message.
        hideLoading();
        loading = false;
        addMessage((error && error.message) || strings.error, false);

        return null;
    });
};

/**
 * Handle a click anywhere in the page. Delegation is required because the panel is injected into
 * the page after load on activity and section pages.
 *
 * @param {Event} event The click event.
 * @returns {void}
 */
const handleClick = (event) => {
    const target = event.target;
    if (!target || typeof target.closest !== 'function') {
        return;
    }

    const closeTarget = target.closest(SELECTORS.close);
    if (closeTarget) {
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

    const sendTarget = target.closest(SELECTORS.sendbtn);
    if (sendTarget) {
        event.preventDefault();
        sendMessage();

        return;
    }

    const quickTarget = target.closest(SELECTORS.quickbtn);
    if (quickTarget) {
        event.preventDefault();
        const key = quickTarget.getAttribute('data-prompt');
        const prompt = key ? strings['prompt' + key] : '';
        const input = getInput();
        if (prompt && input) {
            input.value = prompt.replace('{activity}', config.activityname || strings.thisactivity);
            sendMessage();
        }

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
        closePanel();

        return;
    }

    if (event.key !== 'Tab' && event.keyCode !== 9) {
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

    document.addEventListener('input', (event) => {
        if (event.target && event.target.id === SELECTORS.input) {
            event.target.style.height = 'auto';
            event.target.style.height = Math.min(event.target.scrollHeight, 100) + 'px';
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
    const request = aliases.map((alias) => ({key: STRING_IDS[alias], component: 'format_aicourse'}));

    return getStrings(request).then((values) => {
        aliases.forEach((alias, index) => {
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
            openPanel(toggle);
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
