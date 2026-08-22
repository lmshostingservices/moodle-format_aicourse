/**
 * AI Course Format - Enhanced Course Navigation
 * Adds keyboard navigation, current activity highlighting, and icon picker
 *
 * @module     format_aicourse/courseformat
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/ajax', 'core/notification'], function($, Str, Ajax, Notification) {

    'use strict';

    // ACF-FIX-2.0 (bug 1a): init() is queued from format.php, the before_footer_html_generation
    // hook and format_aicourse_extend_navigation_course(). Moodle does not de-duplicate
    // js_call_amd() requests, so init() used to run 2-3 times per page and every delegated
    // handler was bound again (one click => 2-3 paid banner generations). This module-level
    // guard makes init() idempotent. Every binding below is ALSO namespaced and .off()'d
    // first, so a stray re-entry can never stack handlers either.
    var initialised = false;

    // ACF-FIX-2.0 (bug 4): every retry/poll/animation timer is registered here so it can be
    // cancelled on teardown instead of leaking past navigation.
    var timers = {};
    var timerSeq = 0;
    var polls = {};

    // ACF-FIX-2.0 (a11y): stack of currently-open dialogs, used by the shared focus trap.
    var openDialogs = [];

    var FOCUSABLE_SELECTOR = 'a[href], area[href], button:not([disabled]), ' +
        'input:not([disabled]):not([type="hidden"]), select:not([disabled]), ' +
        'textarea:not([disabled]), iframe, [tabindex]:not([tabindex="-1"]), ' +
        '[contenteditable="true"]';

    var SR_ONLY_CSS = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;' +
        'overflow:hidden;clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap;border:0;';

    // Credit cost of one banner generation. Kept in one place so the lang string can use {$a}.
    var BANNER_CREDIT_COST = 5;

    // ACF-FIX-2.0 (i18n): every user-visible literal now comes from core/str. The values here
    // are last-resort fallbacks only - they are overwritten by loadStrings() before any of the
    // UI that uses them is built. Keys marked "existing" already live in the plugin lang file.
    var STR = {
        addicon: 'Add icon', // Existing
        changeicon: 'Change icon', // Existing
        iconsaved: 'Icon saved', // Existing
        iconsaveerror: 'Error saving icon', // Existing
        removeicon: 'Remove icon', // Existing
        selecticon: 'Select icon', // Existing
        searchicons: 'Search icons', // Existing
        addsection: 'Add section', // Existing
        completed: 'Completed', // Existing
        markasdone: 'Mark as done', // Existing (completionrequirement_manual)
        deletesection: 'Delete section', // Existing
        deletesectionconfirm: 'Are you sure you want to delete this section?', // Existing
        cancel: 'Cancel', // Core
        deletelabel: 'Delete', // Core
        close: 'Close', // Core (closebuttontitle)
        jsDone: 'Done',
        jsCompletionerror: 'Failed to update completion status',
        jsSectionduplicated: 'Section duplicated successfully',
        jsSectionduplicateerror: 'Failed to duplicate section',
        jsSectionadded: 'Section added',
        jsSectionadderror: 'Failed to add section',
        jsSectiondeleted: 'Section deleted successfully',
        jsSectiondeleteerror: 'Failed to delete section',
        jsIconremoved: 'Icon removed',
        bannergenTitle: 'Generate AI banner',
        bannergenSubtitle: 'AI image generation',
        bannergenDesc: 'AI reads your course name and generates a cinematic, full-width banner ' +
            'image tailored to your course subject. The image is automatically cropped and ' +
            'optimised for your course header, then saved directly to your course.',
        bannergenCost: '5 credits',
        bannergenCostdetail: 'One-time generation cost',
        bannergenGenerate: 'Generate banner',
        bannergenLoadingtitle: 'Generating your banner',
        bannergenLoadingsub: 'AI is crafting a cinematic banner for your course. ' +
            'This usually takes one to two minutes - please leave this window open.',
        bannergenPreviewalt: 'Generated course banner',
        bannergenApplied: 'Banner applied to your course',
        bannergenSuccess: 'Your AI banner has been saved to the course.',
        bannergenFailedtitle: 'Generation failed',
        bannergenFailed: 'Generation failed. Please try again.',
        bannergenRetry: 'Try again',
        bannerdelTitle: 'Remove banner image?',
        bannerdelDesc: 'The banner image will be permanently removed from this course. ' +
            'You can generate or upload a new one at any time.',
        bannerdelConfirm: 'Remove image',
        bannerdelRemoving: 'Removing',
        bannerdelError: 'Failed to remove banner. Please try again.',
        bannerdelRemoved: 'Banner image removed'
    };

    // [local key, string request] - order is preserved by Str.get_strings().
    var STRING_MAP = [
        ['addicon', {key: 'addicon', component: 'format_aicourse'}],
        ['changeicon', {key: 'changeicon', component: 'format_aicourse'}],
        ['iconsaved', {key: 'iconsaved', component: 'format_aicourse'}],
        ['iconsaveerror', {key: 'iconsaveerror', component: 'format_aicourse'}],
        ['removeicon', {key: 'removeicon', component: 'format_aicourse'}],
        ['selecticon', {key: 'selecticon', component: 'format_aicourse'}],
        ['searchicons', {key: 'searchicons', component: 'format_aicourse'}],
        ['addsection', {key: 'addsection', component: 'format_aicourse'}],
        ['completed', {key: 'completed', component: 'format_aicourse'}],
        ['markasdone', {key: 'completionrequirement_manual', component: 'format_aicourse'}],
        ['deletesection', {key: 'deletesection', component: 'format_aicourse'}],
        ['deletesectionconfirm', {key: 'deletesectionconfirm', component: 'format_aicourse'}],
        ['cancel', {key: 'cancel', component: 'moodle'}],
        ['deletelabel', {key: 'delete', component: 'moodle'}],
        ['close', {key: 'closebuttontitle', component: 'moodle'}],
        ['jsDone', {key: 'js_done', component: 'format_aicourse'}],
        ['jsCompletionerror', {key: 'js_completionerror', component: 'format_aicourse'}],
        ['jsSectionduplicated', {key: 'js_sectionduplicated', component: 'format_aicourse'}],
        ['jsSectionduplicateerror', {key: 'js_sectionduplicateerror', component: 'format_aicourse'}],
        ['jsSectionadded', {key: 'js_sectionadded', component: 'format_aicourse'}],
        ['jsSectionadderror', {key: 'js_sectionadderror', component: 'format_aicourse'}],
        ['jsSectiondeleted', {key: 'js_sectiondeleted', component: 'format_aicourse'}],
        ['jsSectiondeleteerror', {key: 'js_sectiondeleteerror', component: 'format_aicourse'}],
        ['jsIconremoved', {key: 'js_iconremoved', component: 'format_aicourse'}],
        ['bannergenTitle', {key: 'bannergen_title', component: 'format_aicourse'}],
        ['bannergenSubtitle', {key: 'bannergen_subtitle', component: 'format_aicourse'}],
        ['bannergenDesc', {key: 'bannergen_desc', component: 'format_aicourse'}],
        ['bannergenCost', {key: 'bannergen_cost', component: 'format_aicourse', param: BANNER_CREDIT_COST}],
        ['bannergenCostdetail', {key: 'bannergen_costdetail', component: 'format_aicourse'}],
        ['bannergenGenerate', {key: 'bannergen_generate', component: 'format_aicourse'}],
        ['bannergenLoadingtitle', {key: 'bannergen_loadingtitle', component: 'format_aicourse'}],
        ['bannergenLoadingsub', {key: 'bannergen_loadingsub', component: 'format_aicourse'}],
        ['bannergenPreviewalt', {key: 'bannergen_previewalt', component: 'format_aicourse'}],
        ['bannergenApplied', {key: 'bannergen_applied', component: 'format_aicourse'}],
        ['bannergenSuccess', {key: 'bannergen_success', component: 'format_aicourse'}],
        ['bannergenFailedtitle', {key: 'bannergen_failedtitle', component: 'format_aicourse'}],
        ['bannergenFailed', {key: 'bannergen_failed', component: 'format_aicourse'}],
        ['bannergenRetry', {key: 'bannergen_retry', component: 'format_aicourse'}],
        ['bannerdelTitle', {key: 'bannerdel_title', component: 'format_aicourse'}],
        ['bannerdelDesc', {key: 'bannerdel_desc', component: 'format_aicourse'}],
        ['bannerdelConfirm', {key: 'bannerdel_confirm', component: 'format_aicourse'}],
        ['bannerdelRemoving', {key: 'bannerdel_removing', component: 'format_aicourse'}],
        ['bannerdelError', {key: 'bannerdel_error', component: 'format_aicourse'}],
        ['bannerdelRemoved', {key: 'bannerdel_removed', component: 'format_aicourse'}]
    ];

    /**
     * Fetch every string this module can display. Resolves even when the request fails,
     * in which case the English fallbacks in STR are used.
     *
     * @returns {Promise} jQuery promise, always resolved.
     */
    function loadStrings() {
        var requests = STRING_MAP.map(function(entry) {
            return entry[1];
        });
        var deferred = $.Deferred();
        Str.get_strings(requests).done(function(values) {
            STRING_MAP.forEach(function(entry, index) {
                if (typeof values[index] === 'string' && values[index].length) {
                    STR[entry[0]] = values[index];
                }
            });
        }).always(function() {
            deferred.resolve();
        });
        return deferred.promise();
    }

    // ------------------------------------------------------------------
    // Small shared helpers.
    // ------------------------------------------------------------------

    /**
     * ACF-FIX-2.0 (bug 4): setTimeout wrapper that registers the timer so teardown() can
     * cancel it. Never call setTimeout() directly in this module.
     *
     * @param {Function} callback
     * @param {Number} delay
     * @returns {Number} handle
     */
    function schedule(callback, delay) {
        var handle = ++timerSeq;
        timers[handle] = window.setTimeout(function() {
            delete timers[handle];
            callback();
        }, delay);
        return handle;
    }

    /**
     * Cancel every pending timer and poll.
     */
    function clearTimers() {
        Object.keys(timers).forEach(function(handle) {
            window.clearTimeout(timers[handle]);
            delete timers[handle];
        });
        Object.keys(polls).forEach(function(name) {
            stopPoll(name);
        });
    }

    /**
     * ACF-FIX-2.0 (bug 4): cancel a named poll.
     *
     * @param {String} name
     */
    function stopPoll(name) {
        var poll = polls[name];
        if (!poll) {
            return;
        }
        poll.cancelled = true;
        if (poll.timer) {
            window.clearTimeout(poll.timer);
        }
        delete polls[name];
    }

    /**
     * ACF-FIX-2.0 (bug 4): bounded, cancellable, non-overlapping retry loop. The hero is
     * injected out-of-band by a footer hook, so these polls have to stay - but starting a
     * poll that is already running now cancels the previous one instead of running two
     * interleaved loops, the attempt count is hard-capped, and every timer is registered
     * for teardown.
     *
     * @param {String} name Unique poll name; starting it again cancels the running one.
     * @param {Function} attempt Return true when the work is done and the poll should stop.
     * @param {Number} maxAttempts Hard cap on attempts.
     * @param {Number} delay Milliseconds between attempts.
     * @param {Number} initialDelay Milliseconds before the first attempt.
     */
    function startPoll(name, attempt, maxAttempts, delay, initialDelay) {
        stopPoll(name);
        var poll = {cancelled: false, timer: null, count: 0};
        polls[name] = poll;

        var tick = function() {
            poll.timer = null;
            if (poll.cancelled || polls[name] !== poll) {
                return;
            }
            poll.count++;
            var done = false;
            try {
                done = attempt() === true;
            } catch (error) {
                done = true;
            }
            if (done || poll.count >= maxAttempts) {
                stopPoll(name);
                return;
            }
            poll.timer = window.setTimeout(tick, delay);
        };

        poll.timer = window.setTimeout(tick, typeof initialDelay === 'number' ? initialDelay : delay);
    }

    /**
     * ACF-FIX-2.0 (bug 7): escape a value before it reaches any innerHTML sink. Note that
     * core/notification renders {{{message}}} unescaped, so every server-supplied error
     * string has to go through here before addNotification().
     *
     * @param {*} value
     * @returns {String}
     */
    function escapeHtml(value) {
        if (value === null || typeof value === 'undefined') {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * ACF-FIX-2.0 (bug 7): only allow http(s) or root-relative image URLs and neutralise the
     * characters that could break out of a CSS url("...") context.
     *
     * @param {String} raw
     * @returns {String} Empty string when the URL is not usable.
     */
    function safeImageUrl(raw) {
        if (typeof raw !== 'string') {
            return '';
        }
        var url = raw.trim();
        if (!url || !(/^(https?:\/\/|\/)/i).test(url)) {
            return '';
        }
        return url.replace(/["'()\\\s<>]/g, function(character) {
            return encodeURIComponent(character);
        });
    }

    /**
     * Show a Moodle notification with an escaped message.
     *
     * @param {String} message Plain text.
     * @param {String} type success|error|warning|info
     */
    function notify(message, type) {
        Notification.addNotification({
            message: escapeHtml(message),
            type: type || 'info'
        });
    }

    /**
     * Call one of the plugin's own external functions.
     *
     * ACF-FIX-2.1: this module used to POST to the plugin's hand-rolled ajax.php endpoint. Every
     * one of those actions is now a proper web service declared in db/services.php, so the session
     * key, the transport and the error shape are all core's. Failures reject with a Moodle
     * exception object carrying an already-translated .message.
     *
     * @param {String} shortname External function name without the format_aicourse_ prefix.
     * @param {Object} args Arguments for the function.
     * @returns {Promise} The single promise core/ajax returns for this call.
     */
    function callExternal(shortname, args) {
        return Ajax.call([{
            methodname: 'format_aicourse_' + shortname,
            args: args
        }])[0];
    }

    /**
     * The user-facing message of a rejected external function call.
     *
     * @param {Object} error The rejection value handed over by core/ajax.
     * @param {String} fallback Message to use when the rejection carries none.
     * @returns {String} Plain text.
     */
    function errorMessage(error, fallback) {
        if (!error) {
            return fallback;
        }

        if (typeof error.message === 'string' && error.message) {
            return error.message;
        }

        // ACF-FIX-2.1.5: core/ajax does not always populate .message. A rejection carrying only
        // an errorcode used to fall straight through to the generic fallback, which is how a
        // banner failure with six distinct server-side causes reached the user as one
        // undiagnosable "Generation failed. Please try again." Surface whatever the server did
        // send, so the cause is at least identifiable from the dialog.
        if (typeof error.errorcode === 'string' && error.errorcode) {
            return error.errorcode;
        }

        if (typeof error.debuginfo === 'string' && error.debuginfo) {
            return error.debuginfo;
        }

        if (typeof error.exception === 'string' && error.exception) {
            return error.exception;
        }

        return fallback;
    }

    // ------------------------------------------------------------------
    // ACF-FIX-2.0 (a11y): shared polite live region + announce() helper.
    // ------------------------------------------------------------------

    /**
     * Lazily create the single visually-hidden status region.
     *
     * @returns {Element}
     */
    function liveRegion() {
        var region = document.getElementById('aicourse-sr-status');
        if (!region) {
            region = document.createElement('div');
            region.id = 'aicourse-sr-status';
            region.setAttribute('role', 'status');
            region.setAttribute('aria-live', 'polite');
            region.setAttribute('aria-atomic', 'true');
            region.className = 'sr-only visually-hidden';
            region.setAttribute('style', SR_ONLY_CSS);
            document.body.appendChild(region);
        }
        return region;
    }

    /**
     * Announce a message to screen readers. Called from every mutation path
     * (section added/duplicated/deleted, completion toggled, progress change,
     * banner applied/removed, icon changed).
     *
     * @param {String} message
     */
    function announce(message) {
        if (!message) {
            return;
        }
        var region = liveRegion();
        region.textContent = '';
        // Clearing then setting in a later tick forces AT to re-read identical messages.
        schedule(function() {
            region.textContent = message;
        }, 60);
    }

    /**
     * Announce a parametrised lang string.
     *
     * @param {String} key
     * @param {String} component
     * @param {*} param
     */
    function announceString(key, component, param) {
        Str.get_string(key, component || 'format_aicourse', param).done(announce);
    }

    // ------------------------------------------------------------------
    // Event binding helpers - every binding is namespaced and .off() first.
    // ------------------------------------------------------------------

    /**
     * ACF-FIX-2.0 (bug 1b): namespaced delegated binding. .off(events, selector) removes only
     * this module's handler for this selector, so re-entry can never stack handlers.
     *
     * @param {jQuery|Element} root
     * @param {String} events Namespaced event string, e.g. 'click.aicourse-banner'.
     * @param {String} selector
     * @param {Function} handler
     */
    function bindDelegated(root, events, selector, handler) {
        $(root).off(events, selector).on(events, selector, handler);
    }

    /**
     * ACF-FIX-2.0 (bug 1b): namespaced direct binding.
     *
     * @param {jQuery|Element} root
     * @param {String} events Namespaced event string.
     * @param {Function} handler
     */
    function bindDirect(root, events, handler) {
        $(root).off(events).on(events, handler);
    }

    /**
     * ACF-FIX-2.0 (a11y): bind an activation handler that responds to click AND to
     * Enter/Space. Real <button>/<a> elements fire a synthetic click for those keys, so the
     * key handler skips them to avoid double-firing - this works whether lib.php renders the
     * trigger as a div or as a real button.
     *
     * @param {String} ns Namespace suffix including the dot.
     * @param {String} selector
     * @param {Function} handler
     */
    function bindActivate(ns, selector, handler) {
        bindDelegated(document, 'click' + ns, selector, handler);
        bindDelegated(document, 'keydown' + ns, selector, function(e) {
            var tag = (this.tagName || '').toLowerCase();
            if (tag === 'button' || tag === 'a' || tag === 'input') {
                // Native activation already dispatches a click.
                return;
            }
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar' ||
                    e.keyCode === 13 || e.keyCode === 32) {
                e.preventDefault();
                handler.call(this, e);
            }
        });
    }

    /**
     * ACF-FIX-2.0 (a11y): give non-native activation targets button semantics. No-op when
     * lib.php already emits a real <button> (preferred - see the report).
     *
     * @param {String} selector
     * @param {String} [label] Accessible name to apply when the element has none.
     */
    function ensureActivatable(selector, label) {
        $(selector).each(function() {
            var tag = (this.tagName || '').toLowerCase();
            if (tag === 'button' || tag === 'a') {
                return;
            }
            var $el = $(this);
            if (!$el.attr('role')) {
                $el.attr('role', 'button');
            }
            if (typeof $el.attr('tabindex') === 'undefined') {
                $el.attr('tabindex', '0');
            }
            if (label && !$el.attr('aria-label') && !$.trim($el.text())) {
                $el.attr('aria-label', label);
            }
        });
    }

    // ------------------------------------------------------------------
    // ACF-FIX-2.0 (a11y): shared accessible-dialog behaviour for the two
    // string-built modals and the server-rendered icon picker.
    // ------------------------------------------------------------------

    /**
     * Visible focusable descendants of a dialog.
     *
     * @param {Element} el
     * @returns {jQuery}
     */
    function dialogFocusable(el) {
        return $(el).find(FOCUSABLE_SELECTOR).filter(':visible');
    }

    /**
     * Open a dialog: apply dialog semantics, remember the trigger, move focus in.
     *
     * @param {Element} el The overlay element that the focus trap covers.
     * @param {Object} options {ariaTarget, labelledby, trigger, initialFocus, show, close}
     */
    function openDialog(el, options) {
        options = options || {};
        var $el = $(el);
        var $aria = options.ariaTarget ? $el.find(options.ariaTarget).first() : $();
        if (!$aria.length) {
            $aria = $el;
        }
        $aria.attr('role', 'dialog').attr('aria-modal', 'true');
        if (options.labelledby && !$aria.attr('aria-labelledby')) {
            $aria.attr('aria-labelledby', options.labelledby);
        }
        $el.data('aicourse-trigger', options.trigger || document.activeElement);
        if (typeof options.close === 'function') {
            $el.data('aicourse-closer', options.close);
        }
        if (openDialogs.indexOf(el) === -1) {
            openDialogs.push(el);
        }
        if (typeof options.show === 'function') {
            options.show();
        } else {
            $el.show();
        }
        schedule(function() {
            var $target = options.initialFocus ?
                $el.find(options.initialFocus).filter(':visible').first() : $();
            if (!$target.length) {
                $target = dialogFocusable(el).first();
            }
            if (!$target.length) {
                $el.attr('tabindex', '-1');
                $target = $el;
            }
            $target.trigger('focus');
        }, 80);
    }

    /**
     * Close a dialog and restore focus to whatever opened it.
     *
     * @param {Element} el
     * @param {Object} options {hide}
     */
    function closeDialog(el, options) {
        options = options || {};
        var $el = $(el);
        var index = openDialogs.indexOf(el);
        if (index !== -1) {
            openDialogs.splice(index, 1);
        }
        if (typeof options.hide === 'function') {
            options.hide();
        } else {
            $el.hide();
        }
        var trigger = $el.data('aicourse-trigger');
        $el.removeData('aicourse-trigger');
        if (trigger && trigger.nodeType === 1 && document.contains(trigger) && $(trigger).is(':visible')) {
            $(trigger).trigger('focus');
        }
    }

    /**
     * One keydown handler serves Escape + the Tab focus trap for whichever dialog is on top.
     */
    function initDialogKeys() {
        bindDirect(document, 'keydown.aicourse-dialog', function(e) {
            if (!openDialogs.length) {
                return;
            }
            var top = openDialogs[openDialogs.length - 1];
            if (e.key === 'Escape' || e.keyCode === 27) {
                e.preventDefault();
                var closer = $(top).data('aicourse-closer');
                if (typeof closer === 'function') {
                    closer();
                }
                return;
            }
            if (e.key !== 'Tab' && e.keyCode !== 9) {
                return;
            }
            var $focusable = dialogFocusable(top);
            if (!$focusable.length) {
                e.preventDefault();
                return;
            }
            var first = $focusable[0];
            var last = $focusable[$focusable.length - 1];
            if (top !== e.target && !$.contains(top, e.target)) {
                // Focus escaped the dialog - pull it back in.
                e.preventDefault();
                $(e.shiftKey ? last : first).trigger('focus');
            } else if (e.shiftKey && e.target === first) {
                e.preventDefault();
                $(last).trigger('focus');
            } else if (!e.shiftKey && e.target === last) {
                e.preventDefault();
                $(first).trigger('focus');
            }
        });
    }

    // ------------------------------------------------------------------
    // ACF-FIX-2.0 (bug 6): completion-ring markup is authored by lib.php. The JS used to
    // re-author it and had drifted to stroke-width 4/3 against lib.php's 2.5. It now clones
    // the server-rendered SVG. The fallbacks below are only used when no ring exists on the
    // page yet, and are copies of the lib.php hero completion-ring markup (stroke-width 2.5).
    // ------------------------------------------------------------------

    var ringMarkupCache = {done: null, pending: null};

    var RING_FALLBACK = {
        done: '<svg aria-hidden="true" focusable="false" ' +
            'class="aicourse-completion-ring aicourse-completion-done" viewBox="0 0 50 50">' +
            '<circle cx="25" cy="25" r="20" fill="none" stroke="#357a32" stroke-width="2.5"/>' +
            '<path d="M17 25 L22 30 L33 19" fill="none" stroke="#357a32" stroke-width="2.5" ' +
            'stroke-linecap="round" stroke-linejoin="round"/></svg>',
        pending: '<svg aria-hidden="true" focusable="false" ' +
            'class="aicourse-completion-ring aicourse-completion-pending" viewBox="0 0 50 50">' +
            '<circle cx="25" cy="25" r="20" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="2.5"/>' +
            '<path d="M17 25 L22 30 L33 19" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="2.5" ' +
            'stroke-linecap="round" stroke-linejoin="round"/></svg>'
    };

    /**
     * Snapshot any server-rendered ring so the JS can re-use the exact same markup.
     */
    function cacheRingMarkup() {
        $('.aicourse-completion-ring').each(function() {
            var key = $(this).hasClass('aicourse-completion-done') ? 'done' : 'pending';
            if (!ringMarkupCache[key]) {
                ringMarkupCache[key] = this.outerHTML;
            }
        });
    }

    /**
     * Markup for a completion ring in the requested state.
     *
     * @param {Boolean} done
     * @returns {String}
     */
    function completionRingHtml(done) {
        var key = done ? 'done' : 'pending';
        return ringMarkupCache[key] || RING_FALLBACK[key];
    }

    /**
     * Swap a ring container to the given state, caching whatever markup it held first.
     *
     * @param {jQuery} $container
     * @param {Boolean} done
     */
    function setCompletionRing($container, done) {
        var $existing = $container.find('.aicourse-completion-ring').first();
        if ($existing.length) {
            var existingKey = $existing.hasClass('aicourse-completion-done') ? 'done' : 'pending';
            ringMarkupCache[existingKey] = $existing[0].outerHTML;
        }
        $container.html(completionRingHtml(done));
        // The ring is decorative - the adjacent label carries the state as text.
        $container.find('svg').attr('aria-hidden', 'true').attr('focusable', 'false');
    }

    /**
     * Bind the card view to Moodle's reactive course editor.
     *
     * format_aicourse::supports_components() returns true, so core loads its reactive state
     * manager for this format. This is what actually attaches to it: it registers the section
     * cards and the activity items with the course editor so drag and drop, the keyboard move
     * dialogues and the state watchers all work through core's own mutations.
     *
     * Nothing is loaded unless the teacher has edit mode on, so a student never downloads the
     * module, never gets a drag handle and never gets a listener.
     *
     * @returns {void}
     */
    function initReactiveCards() {
        if (!document.body.classList.contains('editing')) {
            return;
        }
        if (!document.querySelector('.aicourse-cards-container')) {
            return;
        }
        require(['format_aicourse/local/cardcontent'], function(CardContent) {
            CardContent.init('.aicourse-cards-container');
        });
    }

    return {

        /**
         * Entry point. Called from format.php, the footer hook and
         * format_aicourse_extend_navigation_course() - hence the guard.
         */
        init: function() {
            var self = this;

            // BUG-ACF-SCROLL-DELAY (v1.7.40): scrollToTop() was called here on every page
            // load, causing up to 1 second of smooth-scrolling during which all click
            // targets moved under the user's cursor - causing missed clicks on edit controls.
            // ACF-FIX-2.0 (bug 5): scrollToTop() and the empty initAITutor() stub are now
            // deleted outright - both had zero callers.

            // ACF-FIX-2.0 (bug 1a): idempotency guard.
            if (initialised) {
                return;
            }
            initialised = true;

            // Reactive binding first: it does not depend on this module's strings and the sooner
            // the cards are registered with the course editor the sooner they can be dragged.
            initReactiveCards();

            // ACF-FIX-2.0 (i18n): build nothing until the strings are available; every
            // handler is delegated on document so the small async delay is harmless.
            loadStrings().always(function() {
                liveRegion();
                cacheRingMarkup();
                initDialogKeys();

                self.initKeyboardNav();
                self.highlightCurrentActivity();
                self.enhanceCourseIndex();
                self.animateProgress();
                self.initIconPicker();
                self.initCompletionToggle();
                self.initSectionDuplicate();
                self.initSectionDelete();
                self.initAddSection();
                self.initGenerateBanner();
                self.initDeleteBanner();

                // Lets the inline chat script in lib.php announce its responses through the
                // same live region: $(document).trigger('aicourse:announce', [message]).
                bindDirect(document, 'aicourse:announce.aicourse-live', function(e, message) {
                    announce(message);
                });

                // ACF-FIX-2.0 (bug 4): never leave a poll running past navigation.
                bindDirect(window, 'pagehide.aicourse-teardown', function() {
                    clearTimers();
                });
            });
        },

        /**
         * Remove every namespaced handler and cancel every timer this module owns.
         * Exposed so the module can be safely re-initialised (e.g. from tests).
         */
        teardown: function() {
            clearTimers();
            openDialogs = [];
            $(document).off('.aicourse-keynav .aicourse-iconpicker .aicourse-completion ' +
                '.aicourse-section .aicourse-banner .aicourse-bannerdel .aicourse-dialog ' +
                '.aicourse-courseindex .aicourse-progress .aicourse-live');
            $(window).off('.aicourse-teardown');
            $('#aicourse-banner-gen-modal, #aicourse-bdel-modal, #aicourse-icon-picker')
                .off('.aicourse-banner .aicourse-banner-backdrop .aicourse-bannerdel ' +
                    '.aicourse-bannerdel-backdrop .aicourse-iconpicker');
            initialised = false;
        },

        /**
         * Moodle 4.0-4.2 fallback for hero injection.
         * Hook API doesn't exist in these versions, so we inject via JS.
         * The hero HTML is exposed via window.AICOURSE_HERO_HTML from extend_navigation_course().
         * Uses a retry mechanism since extend_navigation_course() may run after DOMContentLoaded.
         */
        injectHeroFallback: function() {
            /**
             * Try once to inject the hero banner, and report whether it landed.
             *
             * @returns {Boolean} True once the hero is in the page.
             */
            function attemptInjection() {
                // Prevent duplicate injection - check both possible wrapper classes.
                if (document.querySelector('.aicourse-hero-sticky-wrap') ||
                    document.querySelector('[data-aicourse-hero]')) {
                    return true;
                }

                // Only inject on aicourse format pages - check body class OR pagetype.
                var isAiCourse = document.body.classList.contains('format-aicourse') ||
                                 document.body.className.indexOf('format-aicourse') !== -1 ||
                                 document.body.className.indexOf('course-view-aicourse') !== -1;

                if (!isAiCourse) {
                    return true;
                }

                // Hero HTML must be exposed via window.AICOURSE_HERO_HTML. If it is not there
                // yet, returning false asks the poll for another (bounded) attempt.
                if (!window.AICOURSE_HERO_HTML) {
                    return false;
                }

                var wrapper = document.createElement('div');
                // Server-generated markup from extend_navigation_course(); not user input.
                wrapper.innerHTML = window.AICOURSE_HERO_HTML;

                if (!wrapper.firstElementChild) {
                    return true;
                }

                // Insert at start of #region-main for consistent positioning.
                var target = document.getElementById('region-main') ||
                             document.querySelector('main') ||
                             document.querySelector('#page-content');

                if (target) {
                    // Insert ALL children (hero + chatbox) - chatbox is a sibling of hero.
                    var children = [];
                    while (wrapper.firstElementChild) {
                        children.push(wrapper.firstElementChild);
                        wrapper.removeChild(wrapper.firstElementChild);
                    }
                    // Insert in REVERSE order at firstChild so they end up in original order.
                    for (var i = children.length - 1; i >= 0; i--) {
                        var child = children[i];
                        if (child.classList.contains('aicourse-hero-sticky-wrap')) {
                            child.setAttribute('data-aicourse-hero', '1');
                        }
                        target.insertBefore(child, target.firstChild);
                    }
                    cacheRingMarkup();
                }
                return true;
            }

            // ACF-FIX-2.0 (bug 4): bounded (20 x 100ms), cancellable and non-overlapping -
            // calling injectHeroFallback() twice restarts one poll instead of running two.
            var start = function() {
                startPoll('herofallback', attemptInjection, 20, 100, 0);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', start, {once: true});
            } else {
                start();
            }
        },

        lastPercentage: null,

        animateProgress: function() {
            var self = this;

            /**
             * Try once to animate the progress ring, and report whether it was there.
             *
             * @returns {Boolean} True once the ring has been found and animated.
             */
            function attemptAnimate() {
                var $container = $('.aicourse-progress-ring-container');

                if (!$container.length) {
                    return false;
                }

                // Prevent duplicate animation.
                if ($container.data('animated')) {
                    return true;
                }
                $container.data('animated', true);

                var targetPercentage = parseInt($container.data('percentage'), 10) || 0;
                var courseid = $container.data('courseid');

                // ACF-FIX-2.0 (a11y): the ring conveys progress visually only - expose it as
                // a progressbar with a numeric value so it is not colour/shape alone.
                $container.attr({
                    'role': 'progressbar',
                    'aria-valuemin': '0',
                    'aria-valuemax': '100',
                    'aria-valuenow': targetPercentage,
                    'aria-valuetext': targetPercentage + '%'
                });

                // ACF-FIX-2.1.166: start empty. The template renders the arc at its final offset,
                // so without this the "animation" was a transition from the answer to the answer.
                var $fill = $container.find('.aicourse-progress-ring-fill');
                if ($fill.length) {
                    var r0 = $container.data('radius') || 90;
                    $fill[0].style.transition = 'none';
                    $fill[0].style.strokeDashoffset = (2 * Math.PI * r0) + 'px';
                    // Force the reset to apply before the animated value is written over it.
                    void $fill[0].getBoundingClientRect();
                }

                // Animate to initial value.
                self.animateRing($container, targetPercentage, false);

                // BUG-ACF-LISTENER-STACK (v1.7.40): namespaced so re-init doesn't stack
                // multiple AJAX calls per completion event.
                bindDirect(document, 'completionchange.aicourse-progress', function() {
                    self.fetchAndUpdateProgress(courseid, $container);
                });
                bindDirect(document, 'aicourse:completion_updated.aicourse-progress', function() {
                    self.fetchAndUpdateProgress(courseid, $container);
                });

                // Animate horizontal progress bar (if present).
                var progressBar = $('.aicourse-progress-bar-fill');
                if (progressBar.length && !progressBar.data('animated')) {
                    progressBar.data('animated', true);
                    var targetWidth = progressBar.data('percentage') + '%';
                    progressBar.css('width', '0%');

                    schedule(function() {
                        progressBar.css({
                            'transition': 'width 1.8s cubic-bezier(0.22, 1, 0.36, 1)',
                            'width': targetWidth
                        });
                    }, 100);
                }
                return true;
            }

            // ACF-FIX-2.0 (bug 4): bounded (5 x 200ms) cancellable poll, first try after 50ms.
            startPoll('progressring', attemptAnimate, 5, 200, 50);
        },

        animateRing: function($container, newPercentage, triggerPulse) {
            var self = this;
            var $circle = $container.find('.aicourse-progress-ring-fill');
            var $text = $container.find('.aicourse-progress-ring-text');

            if (!$circle.length) {
                return;
            }

            var radius = $container.data('radius') || 90;
            var circumference = 2 * Math.PI * radius;
            var targetOffset = circumference - (newPercentage / 100) * circumference;

            // Detect increase for pulse effect.
            var increased = triggerPulse && self.lastPercentage !== null && newPercentage > self.lastPercentage;

            // ACF-FIX-2.1.166: the ring fills slowly and the number counts up with it.
            //
            // The stroke already animated, over 0.8s, which is quick enough to be over before the eye
            // has found it -- and the percentage was written straight to its final value, so the two
            // halves of the same indicator behaved differently. 1.8s is slow enough to read as the
            // ring FILLING rather than as a state change, and the number is stepped over the same
            // duration so they arrive together.
            var DURATION = 1800;
            var reduced = window.matchMedia
                && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            $circle[0].style.transition = reduced
                ? 'none'
                : 'stroke-dashoffset ' + (DURATION / 1000) + 's cubic-bezier(0.22, 1, 0.36, 1)';
            $circle[0].style.strokeDashoffset = targetOffset + 'px';

            $container.attr('aria-valuenow', newPercentage).attr('aria-valuetext', newPercentage + '%');

            // The count-up. Written only into the visible text node: aria-valuetext above already
            // carries the final figure, so a screen reader is told the answer once instead of being
            // read a hundred intermediate numbers.
            if ($text.length && newPercentage < 100) {
                // ACF-FIX-2.1.175: always count from zero.
                //
                // This started at self.lastPercentage, so the count-up only ever showed the DELTA.
                // On a hard reload lastPercentage is null and it ran 0 -> 63, which is what it was
                // meant to do. But animateRing also runs on every progress refresh -- completing an
                // activity fires completionchange, which refetches and calls this again -- and by
                // then lastPercentage is 60, so the ring "counted up" 61, 62, 63. Three numbers.
                // That is the "only counting the last couple of numbers" report, and it is why it
                // looked right on first load and wrong every time after.
                //
                // Counting the delta is correct for a figure being CORRECTED in place. It is wrong
                // for this one: the hero ring is a reading of how far the learner has got, and the
                // sweep from empty to that point is the whole point of the animation. The stroke
                // below already redraws from zero on every call -- the number now matches the ring
                // instead of contradicting it.
                //
                // lastPercentage is still tracked: it drives the pulse-on-increase above, which is
                // the one thing here that genuinely needs the previous value.
                var from = 0;
                // Set before the first animation frame, so the server-rendered figure cannot paint
                // for one frame and then visibly jump back to zero.
                $text.text('0%');
                if (reduced || from === newPercentage) {
                    $text.text(newPercentage + '%');
                } else {
                    var startedAt = null;
                    var step = function(now) {
                        if (startedAt === null) {
                            startedAt = now;
                        }
                        var t = Math.min(1, (now - startedAt) / DURATION);
                        // Same easing curve as the stroke, so the number and the arc stay together
                        // rather than one racing ahead of the other.
                        var eased = 1 - Math.pow(1 - t, 3);
                        $text.text(Math.round(from + (newPercentage - from) * eased) + '%');
                        if (t < 1 && window.requestAnimationFrame) {
                            window.requestAnimationFrame(step);
                        }
                    };
                    if (window.requestAnimationFrame) {
                        window.requestAnimationFrame(step);
                    } else {
                        $text.text(newPercentage + '%');
                    }
                }
            }

            // Glow + pulse on increase.
            if (increased) {
                $container.addClass('pulse');
                $circle.addClass('glow');

                schedule(function() {
                    $container.removeClass('pulse');
                    $circle.removeClass('glow');
                }, 900);
            }

            // Completed state at 100% - show green tick instead of text.
            if (newPercentage >= 100) {
                $container.addClass('completed');
                // ACF-FIX-2.0 (bug 6): markup matched to lib.php:552 (20x20, stroke-width 3,
                // currentColor) instead of the drifted 18x18/#357a32/2.5 copy.
                // ACF-FIX-2.0 (a11y): the tick is decorative; "100%" is kept as text for AT so
                // completion is never signalled by the green tick alone.
                $text.addClass('aicourse-progress-complete').html(
                    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" ' +
                    'fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" ' +
                    'stroke-linejoin="round" aria-hidden="true" focusable="false">' +
                    '<polyline points="20 6 9 17 4 12"></polyline></svg>' +
                    '<span class="sr-only visually-hidden" style="' + SR_ONLY_CSS + '">100%</span>'
                );
                self.lastPercentage = newPercentage;
                return;
            }

            $container.removeClass('completed');
            $text.removeClass('aicourse-progress-complete');

            // Animate number counter.
            var start = parseInt($text.text(), 10) || 0;
            var duration = 800;
            var startTime = performance.now();

            /**
             * One frame of the percentage counter animation.
             *
             * @param {Number} now Timestamp handed over by requestAnimationFrame.
             * @returns {void}
             */
            function update(now) {
                var progress = Math.min((now - startTime) / duration, 1);
                var value = Math.round(start + (newPercentage - start) * progress);
                $text.text(value + '%');

                if (progress < 1) {
                    requestAnimationFrame(update);
                }
            }

            requestAnimationFrame(update);
            self.lastPercentage = newPercentage;
        },

        fetchAndUpdateProgress: function(courseid, $container) {
            var self = this;

            callExternal('get_progress', {
                courseid: parseInt(courseid, 10)
            }).done(function(response) {
                if (response && typeof response.percentage !== 'undefined') {
                    var previous = self.lastPercentage;
                    self.animateRing($container, response.percentage, true);
                    // ACF-FIX-2.0 (a11y): announce progress changes.
                    if (previous !== response.percentage) {
                        announceString('js_progressannounce', 'format_aicourse', response.percentage);
                    }
                }
            }).fail(function() {
                // A failed progress poll is silent: the ring simply keeps its last value.
                return null;
            });
        },

        initKeyboardNav: function() {
            // BUG-ACF-KEYNAV-STACK (v1.7.40): Added namespace so re-init doesn't stack handlers.
            // BUG-ACF-KEYNAV-DROPDOWN (v1.7.40): Added checks so arrow keys don't fire nav
            // when a Moodle dropdown/action-menu/modal is open or focus is in a focusable control.
            // ACF-FIX-2.0: also bail out while one of this module's dialogs is open.
            bindDirect(document, 'keydown.aicourse-keynav', function(e) {
                if (openDialogs.length) {
                    return;
                }
                // Never intercept when focus is inside any interactive control.
                if ($(e.target).is('input, textarea, select, button, a, [contenteditable]')) {
                    return;
                }
                // Never intercept when a Moodle dropdown, modal, or dialog is open.
                if ($('.dropdown-menu.show, .moodle-dialogue, [role="dialog"], [aria-expanded="true"]').length) {
                    return;
                }
                // Left arrow - previous activity.
                if (e.keyCode === 37) {
                    var prevBtn = $('.aicourse-nav-prev');
                    if (prevBtn.length) {
                        prevBtn[0].click();
                    }
                }
                // Right arrow - next activity.
                if (e.keyCode === 39) {
                    var nextBtn = $('.aicourse-nav-next');
                    if (nextBtn.length) {
                        nextBtn[0].click();
                    }
                }
            });
        },

        /**
         * ACF-FIX-2.0 (bug 2): the old test was currentUrl.indexOf(linkUrl) !== -1, so viewing
         * /mod/quiz/view.php?id=57 also matched ?id=5 and set aria-current="page" on both.
         * URLs are now normalised to pathname + sorted query and compared for equality, with a
         * secondary same-path/same-id rule so extra params (forceview=1, &section=) still match.
         */
        highlightCurrentActivity: function() {
            var current = this.normaliseUrl(window.location.href);
            if (!current) {
                return;
            }
            var self = this;

            $('.courseindex-link, [data-for="cm"] a').each(function() {
                var $link = $(this);
                var candidate = self.normaliseUrl($link.attr('href'));
                var matches = false;

                if (candidate) {
                    matches = candidate.key === current.key ||
                        (candidate.path === current.path && !!candidate.id && candidate.id === current.id);
                }

                if (matches) {
                    $link.addClass('active');
                    $link.closest('[data-for="cm"]').addClass('active');
                    $link.attr('aria-current', 'page');

                    // Scroll into view in course index.
                    var container = $link.closest('.courseindex, [data-region="courseindex"]');
                    if (container.length) {
                        var offset = $link.position().top - container.height() / 2;
                        container.scrollTop(container.scrollTop() + offset);
                    }
                } else if ($link.attr('aria-current') === 'page') {
                    // Clear stale markers from a previous (over-broad) pass.
                    $link.removeClass('active').removeAttr('aria-current');
                    $link.closest('[data-for="cm"]').removeClass('active');
                }
            });
        },

        /**
         * Normalise a URL for comparison.
         *
         * @param {String} raw
         * @returns {Object|null} {path, key, id}
         */
        normaliseUrl: function(raw) {
            if (!raw || typeof raw !== 'string' || raw.charAt(0) === '#') {
                return null;
            }
            var parsed;
            try {
                parsed = new URL(raw, window.location.href);
            } catch (error) {
                return null;
            }
            if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
                return null;
            }
            var path = parsed.pathname.replace(/\/+$/, '');
            var pairs = [];
            var id = '';
            parsed.search.replace(/^\?/, '').split('&').forEach(function(pair) {
                if (!pair) {
                    return;
                }
                pairs.push(pair);
                if (pair.indexOf('id=') === 0) {
                    id = pair.substring(3);
                }
            });
            pairs.sort();
            return {
                path: path,
                key: path + '?' + pairs.join('&'),
                id: id
            };
        },

        enhanceCourseIndex: function() {
            // Add smooth transitions to all course index items.
            $('.courseindex-item, [data-for="cm"]').each(function(index) {
                $(this).css({
                    'animation-delay': (index * 20) + 'ms'
                });
            });

            // ACF-FIX-2.0 (bug 1b): was a direct .on('focus'/'blur') on every link, re-bound on
            // every init. Now one namespaced delegated pair.
            bindDelegated(document, 'focusin.aicourse-courseindex', '.courseindex-link, [data-for="cm"] a',
                function() {
                    $(this).addClass('focused');
                });
            bindDelegated(document, 'focusout.aicourse-courseindex', '.courseindex-link, [data-for="cm"] a',
                function() {
                    $(this).removeClass('focused');
                });

            // Chevron directions are handled natively by Moodle.
        },

        /**
         * Initialize the icon picker modal using Moodle core/ajax.
         * ICON-UX-v1.7.46: click target is the .aicourse-icon-col outer column wrapper
         * (which carries data-courseid/data-sectionid) rather than the inner icon-wrap.
         */
        initIconPicker: function() {
            var iconPicker = $('#aicourse-icon-picker');
            var currentIconCol = null;

            if (!iconPicker.length) {
                return;
            }

            var pickerEl = iconPicker[0];

            // ACF-FIX-2.0 (a11y): label the dialog from its own heading.
            var $title = iconPicker.find('.aicourse-icon-picker-header h4').first();
            if ($title.length && !$title.attr('id')) {
                $title.attr('id', 'aicourse-icon-picker-title');
            }
            // The search field has no visible <label>.
            iconPicker.find('.aicourse-icon-search-input').each(function() {
                if (!$(this).attr('aria-label')) {
                    $(this).attr('aria-label', STR.searchicons);
                }
            });
            iconPicker.find('.aicourse-icon-picker-backdrop').attr('aria-hidden', 'true');

            var closeIconPicker = function() {
                closeDialog(pickerEl, {
                    hide: function() {
                        iconPicker.css('display', 'none');
                        $('body').css('overflow', '');
                    }
                });
                currentIconCol = null;
            };

            // ACF-FIX-2.0 (a11y): works whether lib.php renders the trigger as a <div> or as a
            // real <button> (preferred). Non-native triggers get role/tabindex here.
            ensureActivatable('.aicourse-icon-col.aicourse-card-icon-editable', STR.selecticon);

            bindActivate('.aicourse-iconpicker', '.aicourse-icon-col.aicourse-card-icon-editable',
                function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    currentIconCol = $(this);
                    // Reset search and show all icons.
                    iconPicker.find('.aicourse-icon-search-input').val('');
                    iconPicker.find('.aicourse-icon-picker-item').show();
                    iconPicker.find('.aicourse-icon-picker-category').show();

                    openDialog(pickerEl, {
                        ariaTarget: '.aicourse-icon-picker-content',
                        labelledby: 'aicourse-icon-picker-title',
                        trigger: this,
                        initialFocus: '.aicourse-icon-search-input',
                        close: closeIconPicker,
                        show: function() {
                            iconPicker.css('display', 'flex');
                            $('body').css('overflow', 'hidden');
                        }
                    });
                });

            bindDelegated(iconPicker, 'click.aicourse-iconpicker',
                '.aicourse-icon-picker-close, .aicourse-icon-picker-backdrop', closeIconPicker);

            // Live search filter - show/hide icons and category headings.
            bindDelegated(iconPicker, 'input.aicourse-iconpicker', '.aicourse-icon-search-input', function() {
                var query = $(this).val().toLowerCase().trim();
                if (!query) {
                    iconPicker.find('.aicourse-icon-picker-item').show();
                    iconPicker.find('.aicourse-icon-picker-category').show();
                    return;
                }
                var visibleCount = 0;
                iconPicker.find('.aicourse-icon-picker-category').each(function() {
                    var $cat = $(this);
                    if ($cat.attr('data-category') === '__remove__') {
                        $cat.hide();
                        return;
                    }
                    var anyVisible = false;
                    $cat.find('.aicourse-icon-picker-item').each(function() {
                        var key = ($(this).attr('data-icon') || '').toLowerCase();
                        var label = $(this).find('.aicourse-icon-picker-label').text().toLowerCase();
                        var vis = key.indexOf(query) !== -1 || label.indexOf(query) !== -1;
                        $(this).toggle(vis);
                        if (vis) {
                            anyVisible = true;
                            visibleCount++;
                        }
                    });
                    $cat.toggle(anyVisible);
                });
                // ACF-FIX-2.0 (a11y): announce how many icons the filter left.
                announceString('js_iconsfound', 'format_aicourse', visibleCount);
            });

            // ACF-FIX-2.0 (a11y): arrow-key navigation across the icon grid.
            bindDelegated(iconPicker, 'keydown.aicourse-iconpicker', '.aicourse-icon-picker-item',
                function(e) {
                    var keys = ['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp', 'Home', 'End'];
                    if (keys.indexOf(e.key) === -1) {
                        return;
                    }
                    e.preventDefault();
                    var items = iconPicker.find('.aicourse-icon-picker-item').filter(':visible').toArray();
                    var index = items.indexOf(this);
                    if (index === -1) {
                        return;
                    }

                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                        var step = e.key === 'ArrowDown' ? 1 : -1;
                        var top = this.offsetTop;
                        var left = this.offsetLeft;
                        var rowTop = null;
                        var best = null;
                        var bestDelta = Infinity;
                        for (var i = index + step; i >= 0 && i < items.length; i += step) {
                            if (items[i].offsetTop === top) {
                                continue;
                            }
                            if (rowTop === null) {
                                rowTop = items[i].offsetTop;
                            }
                            if (items[i].offsetTop !== rowTop) {
                                break;
                            }
                            var delta = Math.abs(items[i].offsetLeft - left);
                            if (delta < bestDelta) {
                                bestDelta = delta;
                                best = items[i];
                            }
                        }
                        if (best) {
                            $(best).trigger('focus');
                        }
                        return;
                    }

                    var next = index;
                    if (e.key === 'ArrowRight') {
                        next = index + 1;
                    } else if (e.key === 'ArrowLeft') {
                        next = index - 1;
                    } else if (e.key === 'Home') {
                        next = 0;
                    } else if (e.key === 'End') {
                        next = items.length - 1;
                    }
                    next = Math.max(0, Math.min(items.length - 1, next));
                    $(items[next]).trigger('focus');
                });

            // Handle icon selection.
            bindDelegated(iconPicker, 'click.aicourse-iconpicker', '.aicourse-icon-picker-item', function() {
                if (!currentIconCol) {
                    return;
                }

                var iconKey = $(this).attr('data-icon');
                var courseId = currentIconCol.attr('data-courseid');
                var sectionId = currentIconCol.attr('data-sectionid');

                var iconDiv = currentIconCol.find('.aicourse-card-icon');
                var wrapDiv = currentIconCol.find('.aicourse-card-icon-wrap');
                var labelEl = currentIconCol.find('.aicourse-icon-change-label');

                if (iconKey === '__clear__') {
                    // Remove icon: show pencil placeholder, update state + label.
                    // Markup matches lib.php:1799 (the edit-mode empty-state pencil).
                    iconDiv.html('<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" ' +
                        'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" ' +
                        'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
                        '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>');
                    wrapDiv.removeClass('aicourse-icon-selected').addClass('aicourse-icon-empty');
                    labelEl.text(STR.addicon);
                    announce(STR.jsIconremoved);
                } else {
                    // Set icon: clone just the SVG element from the picker (server-rendered).
                    var iconSvg = $(this).find('svg').prop('outerHTML') || '';
                    iconDiv.html(iconSvg);
                    iconDiv.find('svg').attr('aria-hidden', 'true').attr('focusable', 'false');
                    wrapDiv.removeClass('aicourse-icon-empty').addClass('aicourse-icon-selected');
                    labelEl.text(STR.changeicon);
                    announce(STR.iconsaved);
                }

                // Save through the plugin's web service.
                callExternal('save_icon', {
                    courseid: parseInt(courseId, 10),
                    sectionid: parseInt(sectionId, 10),
                    icon: iconKey === '__clear__' ? '' : iconKey
                }).fail(function(error) {
                    notify(errorMessage(error, STR.iconsaveerror), 'error');
                    announce(STR.iconsaveerror);
                });

                closeIconPicker();
            });
        },

        initCompletionToggle: function() {
            // Activity-card manual completion toggle.
            bindActivate('.aicourse-completion', '.aicourse-completion-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var btn = $(this);
                if (btn.hasClass('aicourse-loading')) {
                    return;
                }
                var cmid = btn.attr('data-cmid');
                var isCompleted = btn.attr('data-completed') === '1';
                var newState = isCompleted ? 0 : 1;

                btn.addClass('aicourse-loading').attr('aria-busy', 'true');

                Ajax.call([{
                    methodname: 'core_completion_update_activity_completion_status_manually',
                    args: {
                        cmid: parseInt(cmid, 10),
                        completed: newState === 1
                    }
                }])[0].done(function() {
                    btn.removeClass('aicourse-loading').removeAttr('aria-busy');
                    btn.attr('data-completed', newState === 1 ? '1' : '0');
                    // ACF-FIX-2.0 (a11y): state is exposed as aria-pressed and as a text label,
                    // never by colour alone.
                    btn.attr('aria-pressed', newState === 1 ? 'true' : 'false');

                    if (newState === 1) {
                        btn.removeClass('aicourse-completion-toggle-pending')
                            .addClass('aicourse-completion-toggle-done');
                        btn.find('.aicourse-toggle-check').html(
                            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" ' +
                            'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" ' +
                            'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" ' +
                            'focusable="false"><polyline points="20 6 9 17 4 12"></polyline></svg>');
                        btn.find('.aicourse-toggle-label').text(STR.completed);
                        btn.attr('title', STR.completed);
                        btn.closest('.aicourse-activity-card')
                            .removeClass('aicourse-activity-status-not_started aicourse-activity-status-in_progress')
                            .addClass('aicourse-activity-status-completed');
                        announce(STR.completed);
                    } else {
                        btn.removeClass('aicourse-completion-toggle-done')
                            .addClass('aicourse-completion-toggle-pending');
                        btn.find('.aicourse-toggle-check').html(
                            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" ' +
                            'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
                            'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" ' +
                            'focusable="false"><rect width="18" height="18" x="3" y="3" rx="2"/></svg>');
                        btn.find('.aicourse-toggle-label').text(STR.markasdone);
                        btn.attr('title', STR.markasdone);
                        btn.closest('.aicourse-activity-card')
                            .removeClass('aicourse-activity-status-completed')
                            .addClass('aicourse-activity-status-not_started');
                        announce(STR.markasdone);
                    }

                    $(document).trigger('aicourse:completion_updated');
                }).fail(function() {
                    btn.removeClass('aicourse-loading').removeAttr('aria-busy');
                    notify(STR.jsCompletionerror, 'error');
                    announce(STR.jsCompletionerror);
                });
            });

            // Hero banner completion toggle.
            bindActivate('.aicourse-completion', '.aicourse-hero-completion-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var btn = $(this);
                if (btn.hasClass('aicourse-loading')) {
                    return;
                }
                var cmid = btn.attr('data-cmid');
                var isCompleted = btn.attr('data-completed') === '1';
                var newState = isCompleted ? 0 : 1;

                btn.addClass('aicourse-loading').attr('aria-busy', 'true');

                Ajax.call([{
                    methodname: 'core_completion_update_activity_completion_status_manually',
                    args: {
                        cmid: parseInt(cmid, 10),
                        completed: newState === 1
                    }
                }])[0].done(function() {
                    btn.removeClass('aicourse-loading').removeAttr('aria-busy');
                    btn.attr('data-completed', newState === 1 ? '1' : '0');
                    btn.attr('aria-pressed', newState === 1 ? 'true' : 'false');

                    var ringContainer = btn.find('.aicourse-completion-ring-container');
                    var labelEl = btn.closest('.aicourse-hero-progress').find('.aicourse-completion-label');
                    var labelText = newState === 1 ? STR.completed : STR.markasdone;

                    btn.toggleClass('aicourse-hero-completion-done', newState === 1);
                    btn.toggleClass('aicourse-hero-completion-pending', newState !== 1);
                    // ACF-FIX-2.0 (bug 6): re-use the server-rendered ring markup.
                    setCompletionRing(ringContainer, newState === 1);
                    labelEl.text(labelText);
                    // The static title used to always read "Mark as done" - keep it in sync.
                    btn.attr('title', labelText);
                    announce(labelText);

                    $(document).trigger('aicourse:completion_updated');
                }).fail(function() {
                    btn.removeClass('aicourse-loading').removeAttr('aria-busy');
                    notify(STR.jsCompletionerror, 'error');
                    announce(STR.jsCompletionerror);
                });
            });

            // Seed aria-pressed on first load so the control is not silent before interaction.
            $('.aicourse-completion-toggle, .aicourse-hero-completion-toggle').each(function() {
                if (!$(this).attr('aria-pressed')) {
                    $(this).attr('aria-pressed', $(this).attr('data-completed') === '1' ? 'true' : 'false');
                }
            });
        },

        initSectionDuplicate: function() {
            bindActivate('.aicourse-section', '.aicourse-card-duplicate', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var btn = $(this);
                if (btn.prop('disabled')) {
                    return;
                }
                var sectionId = btn.attr('data-sectionid');
                var courseId = btn.attr('data-courseid') ||
                               $('[data-courseid]').first().attr('data-courseid') ||
                               $('.aicourse-card-icon-wrap').first().attr('data-courseid');

                btn.prop('disabled', true).attr('aria-busy', 'true');
                btn.css('opacity', '0.5');

                callExternal('duplicate_section', {
                    courseid: parseInt(courseId, 10),
                    sectionid: parseInt(sectionId, 10)
                }).done(function() {
                    btn.prop('disabled', false).removeAttr('aria-busy');
                    btn.css('opacity', '1');
                    notify(STR.jsSectionduplicated, 'success');
                    announce(STR.jsSectionduplicated);
                    // Brief delay to show the notification, then reload.
                    schedule(function() {
                        window.location.reload();
                    }, 500);
                }).fail(function(error) {
                    btn.prop('disabled', false).removeAttr('aria-busy');
                    btn.css('opacity', '1');
                    notify(errorMessage(error, STR.jsSectionduplicateerror), 'error');
                    announce(STR.jsSectionduplicateerror);
                });
            });
        },

        initAddSection: function() {
            ensureActivatable('.aicourse-add-section-card', STR.addsection);

            bindActivate('.aicourse-section', '.aicourse-add-section-card', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var btn = $(this);
                if (btn.hasClass('aicourse-add-section-loading')) {
                    return;
                }
                var courseId = btn.attr('data-courseid');

                btn.addClass('aicourse-add-section-loading').attr('aria-busy', 'true');

                callExternal('add_section', {
                    courseid: parseInt(courseId, 10)
                }).done(function() {
                    notify(STR.jsSectionadded, 'success');
                    announce(STR.jsSectionadded);
                    schedule(function() {
                        window.location.reload();
                    }, 400);
                }).fail(function(error) {
                    btn.removeClass('aicourse-add-section-loading').removeAttr('aria-busy');
                    notify(errorMessage(error, STR.jsSectionadderror), 'error');
                    announce(STR.jsSectionadderror);
                });
            });
        },

        initSectionDelete: function() {
            bindActivate('.aicourse-section', '.aicourse-card-delete', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var btn = $(this);
                if (btn.prop('disabled')) {
                    return;
                }
                var card = btn.closest('.aicourse-card');
                var sectionId = btn.attr('data-sectionid');
                var courseId = $('[data-courseid]').first().attr('data-courseid') ||
                               $('.aicourse-card-icon-wrap').first().attr('data-courseid');

                // ACF-FIX-2.0 (i18n): strings are already loaded, so the confirm opens
                // synchronously - no second Str round trip, no chance of a duplicate dialog.
                Notification.confirm(
                    STR.deletesection,
                    STR.deletesectionconfirm,
                    STR.deletelabel,
                    STR.cancel,
                    function() {
                        btn.prop('disabled', true).attr('aria-busy', 'true');
                        card.css('opacity', '0.5');

                        callExternal('delete_section', {
                            courseid: parseInt(courseId, 10),
                            sectionid: parseInt(sectionId, 10)
                        }).done(function() {
                            // ACF-FIX-2.0 (a11y): move focus to a surviving neighbour before the
                            // focused button is destroyed, then announce the removal.
                            var $next = card.nextAll('.aicourse-card').first();
                            if (!$next.length) {
                                $next = card.prevAll('.aicourse-card').first();
                            }
                            var $focus = $next.find(FOCUSABLE_SELECTOR).filter(':visible').first();
                            if (!$focus.length) {
                                $focus = $('.aicourse-add-section-card').filter(':visible').first();
                            }
                            if ($focus.length) {
                                $focus.trigger('focus');
                            }
                            card.animate({opacity: 0, height: 0}, 300, function() {
                                card.remove();
                            });
                            notify(STR.jsSectiondeleted, 'success');
                            announce(STR.jsSectiondeleted);
                        }).fail(function(error) {
                            card.css('opacity', '1');
                            btn.prop('disabled', false).removeAttr('aria-busy');
                            notify(errorMessage(error, STR.jsSectiondeleteerror), 'error');
                            announce(STR.jsSectiondeleteerror);
                        });
                    }
                );
            });
        },

        /**
         * ACF-FIX-2.0 (bug 3): apply a freshly generated banner in-page.
         * The old code targeted .aicourse-hero-bg-img (which lib.php only renders when an
         * image is ALREADY set, so on first generation there was nothing to update) and added
         * the has-image class to .aicourse-hero (the rendered class is .aicourse-hero-banner).
         * Now the background element is created when absent and the class goes on the right node.
         *
         * @param {String} imageUrl
         * @returns {Boolean} True when the page was updated in place.
         */
        applyHeroBanner: function(imageUrl) {
            var safe = safeImageUrl(imageUrl);
            if (!safe) {
                return false;
            }
            var $banner = $('.aicourse-hero-banner');
            if (!$banner.length) {
                return false;
            }
            var cacheBusted = safe + (safe.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();

            $banner.each(function() {
                var $b = $(this);
                var $bg = $b.find('.aicourse-hero-bg-img').first();
                if (!$bg.length) {
                    $bg = $('<div class="aicourse-hero-bg-img"></div>');
                    $b.prepend($bg);
                }
                $bg.css('background-image', 'url("' + cacheBusted + '")').show();
                $b.addClass('aicourse-hero-has-image');
                // Drop the max-height cap lib.php applies in no-image mode.
                $b.css('max-height', '');
            });
            // The delete control is only relevant once an image exists.
            $('.aicourse-ai-delete-banner').show();
            return true;
        },

        /**
         * AI Banner Image Generation
         * Shows a cost-confirmation modal then calls format_aicourse_generate_banner_image -> API -> file storage.
         */
        initGenerateBanner: function() {
            var self = this;
            var modalId = 'aicourse-banner-gen-modal';
            var titleId = 'aicourse-bgen-title';

            // ACF-FIX-2.0 (i18n + a11y + bug 7): every literal now comes from STR and every
            // interpolated value is escaped. Full port to core/modal_factory is out of scope.
            if (!document.getElementById(modalId)) {
                var modalHtml = '<div id="' + modalId + '" class="aicourse-bgen-overlay" ' +
                        'style="display:none" role="dialog" aria-modal="true" aria-labelledby="' + titleId + '">'
                    + '<div class="aicourse-bgen-card">'
                    + '<div class="aicourse-bgen-header">'
                    + '<div class="aicourse-bgen-header-icon" aria-hidden="true">'
                    + '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" '
                    + 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" '
                    + 'stroke-linejoin="round" aria-hidden="true" focusable="false">'
                    + '<path d="M15 4V2"/><path d="M15 16v-2"/><path d="M8 9h2"/><path d="M20 9h2"/>'
                    + '<path d="M17.8 11.8 19 13"/><path d="M15 9h.01"/><path d="M17.8 6.2 19 5"/>'
                    + '<path d="m3 21 9-9"/><path d="M12.2 6.2 11 5"/>'
                    + '</svg>'
                    + '</div>'
                    + '<div class="aicourse-bgen-header-text">'
                    + '<h2 class="aicourse-bgen-title" id="' + titleId + '">'
                    + escapeHtml(STR.bannergenTitle) + '</h2>'
                    + '<p class="aicourse-bgen-subtitle">' + escapeHtml(STR.bannergenSubtitle) + '</p>'
                    + '</div>'
                    + '<button type="button" class="aicourse-bgen-close" aria-label="'
                    + escapeHtml(STR.close) + '">'
                    + '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" '
                    + 'fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" '
                    + 'focusable="false"><line x1="18" y1="6" x2="6" y2="18"/>'
                    + '<line x1="6" y1="6" x2="18" y2="18"/></svg>'
                    + '</button>'
                    + '</div>'
                    + '<div class="aicourse-bgen-body">'

                    // State: confirm.
                    + '<div id="aicourse-bgen-confirm">'
                    + '<div class="aicourse-bgen-course-name" id="aicourse-bgen-cname"></div>'
                    + '<p class="aicourse-bgen-desc">' + escapeHtml(STR.bannergenDesc) + '</p>'
                    + '<div class="aicourse-bgen-cost-box">'
                    + '<div class="aicourse-bgen-cost-amount">' + escapeHtml(STR.bannergenCost) + '</div>'
                    + '<div class="aicourse-bgen-cost-detail">'
                    + escapeHtml(STR.bannergenCostdetail) + '</div>'
                    + '</div>'
                    + '<div class="aicourse-bgen-actions">'
                    + '<button type="button" class="aicourse-bgen-btn aicourse-bgen-btn-cancel">'
                    + escapeHtml(STR.cancel) + '</button>'
                    + '<button type="button" class="aicourse-bgen-btn aicourse-bgen-btn-generate">'
                    + '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" '
                    + 'fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" '
                    + 'focusable="false"><path d="M15 4V2"/><path d="M15 16v-2"/><path d="M8 9h2"/>'
                    + '<path d="M20 9h2"/><path d="M17.8 11.8 19 13"/><path d="M15 9h.01"/>'
                    + '<path d="M17.8 6.2 19 5"/><path d="m3 21 9-9"/><path d="M12.2 6.2 11 5"/></svg> '
                    + escapeHtml(STR.bannergenGenerate)
                    + '</button>'
                    + '</div>'
                    + '</div>'

                    // State: loading.
                    + '<div id="aicourse-bgen-loading" style="display:none" '
                    + 'class="aicourse-bgen-state-center" role="status" aria-live="polite">'
                    + '<div class="aicourse-bgen-spinner" aria-hidden="true"></div>'
                    + '<p class="aicourse-bgen-loading-title">'
                    + escapeHtml(STR.bannergenLoadingtitle) + '&hellip;</p>'
                    + '<p class="aicourse-bgen-loading-sub">' + escapeHtml(STR.bannergenLoadingsub) + '</p>'
                    + '</div>'

                    // State: success.
                    + '<div id="aicourse-bgen-success" style="display:none" role="status" aria-live="polite">'
                    + '<div class="aicourse-bgen-preview-wrap">'
                    + '<img id="aicourse-bgen-preview-img" src="" alt="'
                    + escapeHtml(STR.bannergenPreviewalt) + '" class="aicourse-bgen-preview-img" />'
                    + '<div class="aicourse-bgen-preview-badge">'
                    + '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" '
                    + 'fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true" '
                    + 'focusable="false"><polyline points="20 6 9 17 4 12"/></svg> '
                    + escapeHtml(STR.bannergenApplied)
                    + '</div>'
                    + '</div>'
                    + '<p class="aicourse-bgen-success-msg">' + escapeHtml(STR.bannergenSuccess) + '</p>'
                    + '<div class="aicourse-bgen-actions">'
                    + '<button type="button" class="aicourse-bgen-btn aicourse-bgen-btn-done">'
                    + escapeHtml(STR.jsDone) + '</button>'
                    + '</div>'
                    + '</div>'

                    // State: error.
                    + '<div id="aicourse-bgen-error" style="display:none" class="aicourse-bgen-state-center">'
                    + '<div class="aicourse-bgen-error-icon" aria-hidden="true">'
                    + '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" '
                    + 'fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" '
                    + 'focusable="false"><circle cx="12" cy="12" r="10"/>'
                    + '<line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
                    + '</div>'
                    + '<p class="aicourse-bgen-error-title">' + escapeHtml(STR.bannergenFailedtitle) + '</p>'
                    + '<p class="aicourse-bgen-error-msg" id="aicourse-bgen-errmsg" role="alert"></p>'
                    + '<div class="aicourse-bgen-actions">'
                    + '<button type="button" class="aicourse-bgen-btn aicourse-bgen-btn-cancel">'
                    + escapeHtml(STR.close) + '</button>'
                    + '<button type="button" class="aicourse-bgen-btn aicourse-bgen-btn-retry">'
                    + escapeHtml(STR.bannergenRetry) + '</button>'
                    + '</div>'
                    + '</div>'

                    + '</div>' // .aicourse-bgen-body
                    + '</div>' // .aicourse-bgen-card
                    + '</div>'; // .aicourse-bgen-overlay

                $(document.body).append(modalHtml);
            }

            var overlay = $('#' + modalId);
            var overlayEl = overlay[0];
            var currentBtn = null;

            /**
             * Show exactly one of the modal's four panes.
             *
             * @param {String} state confirm|loading|success|error
             * @returns {void}
             */
            function setState(state) {
                $('#aicourse-bgen-confirm, #aicourse-bgen-loading, #aicourse-bgen-success, ' +
                    '#aicourse-bgen-error').hide();
                $('#aicourse-bgen-' + state).show();
            }

            /**
             * Close the banner generation modal and restore focus to its trigger.
             *
             * @returns {void}
             */
            function closeModal() {
                closeDialog(overlayEl, {
                    hide: function() {
                        overlay.fadeOut(180);
                    }
                });
                currentBtn = null;
            }

            /**
             * Open the banner generation modal for one Generate button.
             *
             * @param {Object} btn The jQuery-wrapped button that was activated.
             * @returns {void}
             */
            function openModal(btn) {
                currentBtn = btn;
                // .text() - the course name is user-controlled.
                $('#aicourse-bgen-cname').text(btn.data('coursename') || '');
                setState('confirm');
                openDialog(overlayEl, {
                    labelledby: titleId,
                    trigger: btn[0],
                    initialFocus: '.aicourse-bgen-btn-generate',
                    close: closeModal,
                    show: function() {
                        overlay.fadeIn(180);
                    }
                });
            }

            /**
             * Extract a clean human-readable error message from a server error string.
             * The Gemini SDK sometimes propagates raw JSON blobs (e.g. quota exhaustion)
             * as the error value - this helper unwraps the inner message string so the
             * modal never shows a raw JSON blob to the course admin.
             *
             * @param {String|null} raw
             * @returns {String}
             */
            function extractBannerError(raw) {
                if (!raw) {
                    return STR.bannergenFailed;
                }
                try {
                    var parsed = JSON.parse(raw);
                    var inner = (parsed && parsed.error && parsed.error.message) ||
                                (parsed && parsed.message);
                    if (inner && typeof inner === 'string') {
                        return inner;
                    }
                } catch (ignore) {
                    // Not JSON - fall through and use the raw string.
                }
                return String(raw);
            }

            /**
             * Show the modal's error pane with a message.
             *
             * @param {String} message Plain text.
             * @returns {void}
             */
            function showError(message) {
                // .text() - server-controlled content must never reach innerHTML.
                $('#aicourse-bgen-errmsg').text(message);
                setState('error');
                announce(STR.bannergenFailedtitle + '. ' + message);
                schedule(function() {
                    overlay.find('.aicourse-bgen-btn-retry').filter(':visible').first().trigger('focus');
                }, 80);
            }

            /**
             * Ask the server to generate and store a banner, then preview it.
             *
             * @returns {void}
             */
            function doGenerate() {
                if (!currentBtn) {
                    return;
                }
                // Guard against a double activation of the Generate button itself.
                var $generate = overlay.find('.aicourse-bgen-btn-generate');
                if ($generate.prop('disabled')) {
                    return;
                }
                $generate.prop('disabled', true);

                var courseid = currentBtn.data('courseid');

                setState('loading');
                announce(STR.bannergenLoadingtitle);

                // ACF-FIX-2.1.42: the call queues the work and returns at once; the result is
                // collected by polling.
                //
                // Generation takes about 110 seconds, and holding one AJAX request open for that
                // long meant it had to outlive every intermediary between here and PHP. The
                // shortest timeout won -- a reverse proxy at 60s, Cloudflare's fixed 100s on its
                // lower tiers -- and the browser was handed a 504 for a generation that was in
                // fact succeeding on the server. Polling asks a cheap question repeatedly instead
                // of one expensive question once, so no single request is ever long enough to be
                // cut, whatever the hosting stack does.
                var pollAttempts = 0;
                var POLL_EVERY = 4000;
                // 90 polls at 4s is six minutes: comfortably past the ~110s the service takes,
                // with room for the adhoc task to be picked up by the next cron run.
                var POLL_LIMIT = 90;

                var succeed = function(imageurl) {
                    $generate.prop('disabled', false);
                    var safe = safeImageUrl(imageurl);
                    $('#aicourse-bgen-preview-img').attr('src', safe);
                    setState('success');
                    announce(STR.bannergenApplied);
                    schedule(function() {
                        overlay.find('.aicourse-bgen-btn-done').filter(':visible').first().trigger('focus');
                    }, 80);
                    // ACF-FIX-2.0 (bug 3): actually show the new banner in-page. If the hero
                    // is not on this page (it is injected by a footer hook) fall back to the
                    // reload the Done button performs anyway.
                    self.applyHeroBanner(imageurl);
                };

                var poll = function() {
                    pollAttempts++;
                    if (pollAttempts > POLL_LIMIT) {
                        $generate.prop('disabled', false);
                        showError(STR.bannergenFailed);
                        return;
                    }
                    callExternal('get_banner_status', {
                        courseid: parseInt(courseid, 10)
                    }).done(function(status) {
                        if (!status) {
                            schedule(poll, POLL_EVERY);
                            return;
                        }
                        if (status.status === 'done' && status.imageurl) {
                            succeed(status.imageurl);
                        } else if (status.status === 'failed') {
                            $generate.prop('disabled', false);
                            showError(extractBannerError(status.message || STR.bannergenFailed));
                        } else {
                            schedule(poll, POLL_EVERY);
                        }
                    }).fail(function() {
                        // A dropped poll is not a failed generation -- the task carries on
                        // regardless of whether this browser is listening. Try again.
                        schedule(poll, POLL_EVERY);
                    });
                };

                callExternal('generate_banner_image', {
                    courseid: parseInt(courseid, 10)
                }).done(function(response) {
                    if (response && response.imageurl) {
                        // A server still running the synchronous version: use the image directly.
                        succeed(response.imageurl);
                        return;
                    }
                    schedule(poll, POLL_EVERY);
                }).fail(function(error) {
                    $generate.prop('disabled', false);
                    showError(extractBannerError(errorMessage(error, STR.bannergenFailed)));
                });
            }

            // --- Event delegation (ACF-FIX-2.0 bug 1b: all namespaced, all .off() first) ---

            bindActivate('.aicourse-banner', '.aicourse-ai-generate-banner', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openModal($(this));
            });

            bindDelegated(overlay, 'click.aicourse-banner',
                '.aicourse-bgen-close, .aicourse-bgen-btn-cancel', function() {
                    closeModal();
                });

            // Click outside the card to close. NOTE: a distinct namespace - a namespace-only
            // .off() on the overlay would otherwise also drop the delegated handlers above.
            bindDirect(overlay, 'click.aicourse-banner-backdrop', function(e) {
                if ($(e.target).is(overlay)) {
                    closeModal();
                }
            });

            bindDelegated(overlay, 'click.aicourse-banner', '.aicourse-bgen-btn-generate', function() {
                doGenerate();
            });

            bindDelegated(overlay, 'click.aicourse-banner', '.aicourse-bgen-btn-retry', function() {
                setState('confirm');
                overlay.find('.aicourse-bgen-btn-generate').prop('disabled', false).trigger('focus');
            });

            // Done - refresh the page to fully update the banner.
            bindDelegated(overlay, 'click.aicourse-banner', '.aicourse-bgen-btn-done', function() {
                closeModal();
                window.location.reload();
            });

            // Escape is handled centrally by initDialogKeys().
        },

        /**
         * Delete Banner Image
         * Confirms then calls format_aicourse_delete_banner_image, then updates the page in-place.
         */
        initDeleteBanner: function() {
            var modalId = 'aicourse-bdel-modal';
            var titleId = 'aicourse-bdel-title';

            if (!document.getElementById(modalId)) {
                var modalHtml = '<div id="' + modalId + '" class="aicourse-bdel-overlay" '
                        + 'style="display:none" role="dialog" aria-modal="true" aria-labelledby="'
                        + titleId + '">'
                    + '<div class="aicourse-bdel-card">'
                    + '<div class="aicourse-bdel-icon" aria-hidden="true">'
                    + '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" '
                    + 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" '
                    + 'stroke-linejoin="round" aria-hidden="true" focusable="false">'
                    + '<polyline points="3 6 5 6 21 6"/>'
                    + '<path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>'
                    + '<path d="M10 11v6"/><path d="M14 11v6"/>'
                    + '<path d="M9 6V4h6v2"/>'
                    + '</svg>'
                    + '</div>'
                    + '<h2 class="aicourse-bdel-title" id="' + titleId + '">'
                    + escapeHtml(STR.bannerdelTitle) + '</h2>'
                    + '<p class="aicourse-bdel-desc">' + escapeHtml(STR.bannerdelDesc) + '</p>'
                    + '<div id="aicourse-bdel-error" class="aicourse-bdel-errmsg" role="alert" '
                    + 'style="display:none"></div>'
                    + '<div class="aicourse-bdel-actions">'
                    + '<button type="button" class="aicourse-bgen-btn aicourse-bgen-btn-cancel '
                    + 'aicourse-bdel-btn-cancel">' + escapeHtml(STR.cancel) + '</button>'
                    + '<button type="button" class="aicourse-bgen-btn aicourse-bdel-btn-confirm">'
                    + escapeHtml(STR.bannerdelConfirm) + '</button>'
                    + '</div>'
                    + '</div>'
                    + '</div>';
                $(document.body).append(modalHtml);
            }

            var delOverlay = $('#' + modalId);
            var delOverlayEl = delOverlay[0];
            var currentDeleteBtn = null;

            /**
             * Close the banner deletion modal and restore focus to its trigger.
             *
             * @returns {void}
             */
            function closeDeleteModal() {
                closeDialog(delOverlayEl, {
                    hide: function() {
                        delOverlay.fadeOut(180);
                    }
                });
                currentDeleteBtn = null;
            }

            /**
             * Open the banner deletion modal for one Delete button.
             *
             * @param {Object} btn The jQuery-wrapped button that was activated.
             * @returns {void}
             */
            function openDeleteModal(btn) {
                currentDeleteBtn = btn;
                $('#aicourse-bdel-error').hide().text('');
                delOverlay.find('.aicourse-bdel-btn-confirm')
                    .prop('disabled', false).text(STR.bannerdelConfirm);
                openDialog(delOverlayEl, {
                    labelledby: titleId,
                    trigger: btn[0],
                    // Destructive action: start on Cancel, not on Remove.
                    initialFocus: '.aicourse-bdel-btn-cancel',
                    close: closeDeleteModal,
                    show: function() {
                        delOverlay.fadeIn(180);
                    }
                });
            }

            bindActivate('.aicourse-bannerdel', '.aicourse-ai-delete-banner', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openDeleteModal($(this));
            });

            bindDelegated(delOverlay, 'click.aicourse-bannerdel', '.aicourse-bdel-btn-cancel', function() {
                closeDeleteModal();
            });

            // Distinct namespace - see the note in initGenerateBanner().
            bindDirect(delOverlay, 'click.aicourse-bannerdel-backdrop', function(e) {
                if ($(e.target).is(delOverlay)) {
                    closeDeleteModal();
                }
            });

            bindDelegated(delOverlay, 'click.aicourse-bannerdel', '.aicourse-bdel-btn-confirm', function() {
                if (!currentDeleteBtn) {
                    return;
                }
                var confirmBtn = $(this);
                if (confirmBtn.prop('disabled')) {
                    return;
                }
                var courseid = currentDeleteBtn.data('courseid');

                confirmBtn.prop('disabled', true).text(STR.bannerdelRemoving + '…');
                $('#aicourse-bdel-error').hide().text('');

                callExternal('delete_banner_image', {
                    courseid: parseInt(courseid, 10)
                }).done(function() {
                    // ACF-FIX-2.0 (bug 3): selectors kept consistent with applyHeroBanner().
                    $('.aicourse-hero-bg-img').css('background-image', 'none').hide();
                    $('.aicourse-hero-banner').removeClass('aicourse-hero-has-image');
                    // Hide the delete button(s) - they are no longer relevant.
                    $('.aicourse-ai-delete-banner').hide();
                    closeDeleteModal();
                    announce(STR.bannerdelRemoved);
                }).fail(function(error) {
                    // .text() - server-controlled content must never reach innerHTML.
                    var msg = errorMessage(error, STR.bannerdelError);
                    $('#aicourse-bdel-error').text(msg).show();
                    announce(msg);
                    confirmBtn.prop('disabled', false).text(STR.bannerdelConfirm);
                });
            });

            // Escape is handled centrally by initDialogKeys().
        }
    };
});
