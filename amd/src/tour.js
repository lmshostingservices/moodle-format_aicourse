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
 * First-run guided tour: spotlight, step controls and narration.
 *
 * @module     format_aicourse/tour
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {get_string as getString} from 'core/str';

const MUTE_KEY = 'aicourse_tour_muted';

/**
 * Record that this user has seen the tour, so it is not offered again.
 *
 * @param {Number} version The tour version they have seen.
 * @returns {Promise} Resolves whether or not the write succeeded.
 */
const markSeen = (version) => {
    return Ajax.call([{
        methodname: 'core_user_update_user_preferences',
        args: {
            preferences: [{
                type: 'format_aicourse_tour_seen',
                value: String(version),
            }],
        },
    }])[0].catch(() => {
        // If the preference cannot be saved the tour reappears next time, which is a nuisance
        // rather than a fault.
        return null;
    });
};

/**
 * Narration.
 *
 * Two providers, tried in order. A pre-generated audio file for the step is used when one is
 * present, which is how a specific voice -- a Google Chirp HD voice, for instance -- is delivered:
 * the narration text is fixed, so the audio can be produced once and shipped rather than
 * synthesised on every page view at a per-character cost. When no file exists the browser's own
 * speech synthesis reads the same text, preferring an Australian English voice, so narration works
 * out of the box on a site that has not supplied audio.
 *
 * Nothing here autoplays without a user gesture: browsers block that, and a tour that silently
 * fails to speak is worse than one that waits to be asked. The tour begins on a click, which is
 * the gesture, so playback from step one onwards is permitted.
 */
class Narrator {
    /**
     * @param {String} base URL prefix for pre-generated audio files.
     * @param {String} lang BCP-47 language preference for speech synthesis.
     */
    constructor(base, lang) {
        this.base = base;
        this.lang = lang || 'en-AU';
        this.audio = null;
        this.muted = window.localStorage
            ? window.localStorage.getItem(MUTE_KEY) === '1'
            : false;
    }

    /**
     * Whether narration is currently muted.
     *
     * @returns {Boolean}
     */
    isMuted() {
        return this.muted;
    }

    /**
     * Toggle mute, remembering the choice for next time.
     *
     * @returns {Boolean} The new muted state.
     */
    toggleMute() {
        this.muted = !this.muted;
        try {
            if (window.localStorage) {
                window.localStorage.setItem(MUTE_KEY, this.muted ? '1' : '0');
            }
        } catch (e) {
            // Private browsing: the preference simply does not persist.
        }
        if (this.muted) {
            this.stop();
        }
        return this.muted;
    }

    /**
     * Stop whatever is currently speaking.
     *
     * @returns {void}
     */
    stop() {
        if (this.audio) {
            this.audio.pause();
            this.audio = null;
        }
        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }
    }

    /**
     * Choose the closest available synthesis voice for the requested language.
     *
     * @returns {SpeechSynthesisVoice|null}
     */
    pickVoice() {
        if (!window.speechSynthesis) {
            return null;
        }
        const voices = window.speechSynthesis.getVoices() || [];
        if (!voices.length) {
            return null;
        }
        const exact = voices.find(v => v.lang === this.lang);
        if (exact) {
            return exact;
        }
        const prefix = this.lang.split('-')[0];
        return voices.find(v => v.lang && v.lang.indexOf(prefix) === 0) || null;
    }

    /**
     * Speak a step: shipped audio if present, browser synthesis otherwise.
     *
     * @param {String} key Step key, used as the audio filename.
     * @param {String} text The narration text.
     * @returns {void}
     */
    speak(key, text) {
        this.stop();
        if (this.muted || !text) {
            return;
        }
        const src = this.base + key + '.mp3';
        const probe = new Audio();
        probe.src = src;
        probe.addEventListener('canplaythrough', () => {
            if (this.muted) {
                return;
            }
            this.audio = probe;
            probe.play().catch(() => {
                this.synthesise(text);
            });
        }, {once: true});
        probe.addEventListener('error', () => {
            this.synthesise(text);
        }, {once: true});
    }

    /**
     * Read text with the browser's speech synthesis.
     *
     * @param {String} text The narration text.
     * @returns {void}
     */
    synthesise(text) {
        if (this.muted || !window.speechSynthesis || !window.SpeechSynthesisUtterance) {
            return;
        }
        const utter = new window.SpeechSynthesisUtterance(text);
        utter.lang = this.lang;
        utter.rate = 0.98;
        utter.pitch = 1;
        const voice = this.pickVoice();
        if (voice) {
            utter.voice = voice;
        }
        window.speechSynthesis.speak(utter);
    }
}

/**
 * The tour itself.
 */
class Tour {
    /**
     * @param {Object} config Server-provided steps and settings.
     */
    constructor(config) {
        // A step whose target is not on this page is dropped rather than shown pointing at
        // nothing: a course with no banner, or a site with the tutor switched off, gets a
        // shorter tour instead of a broken one.
        this.steps = (config.steps || []).filter(step => {
            return !step.target || document.querySelector(step.target);
        });
        this.version = config.version || 1;
        this.index = 0;
        this.narrator = new Narrator(config.audiobase, config.voice);
        this.autoplay = config.autoplay !== 0;
        this.root = null;
        this.onKey = this.onKey.bind(this);
        this.reposition = this.reposition.bind(this);
    }

    /**
     * Build the overlay and show the first step.
     *
     * @returns {void}
     */
    async start() {
        if (!this.steps.length) {
            return;
        }
        const strings = await Promise.all([
            getString('tour_next', 'format_aicourse'),
            getString('tour_back', 'format_aicourse'),
            getString('tour_skip', 'format_aicourse'),
            getString('tour_finish', 'format_aicourse'),
            getString('tour_mute', 'format_aicourse'),
            getString('tour_unmute', 'format_aicourse'),
            getString('tour_progress', 'format_aicourse'),
        ]);
        [this.sNext, this.sBack, this.sSkip, this.sFinish, this.sMute, this.sUnmute, this.sProgress] = strings;

        this.root = document.createElement('div');
        this.root.className = 'aicourse-tour';
        this.root.setAttribute('role', 'dialog');
        this.root.setAttribute('aria-modal', 'true');
        this.root.setAttribute('aria-label', this.steps[0].title);
        this.root.innerHTML =
            '<div class="aicourse-tour-scrim"></div>' +
            '<div class="aicourse-tour-spot" aria-hidden="true"></div>' +
            '<div class="aicourse-tour-arrow" aria-hidden="true">' +
                '<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">' +
                    '<path d="M6 32 H50" />' +
                    '<path d="M36 16 L54 32 L36 48" />' +
                '</svg>' +
            '</div>' +
            '<div class="aicourse-tour-card" tabindex="-1">' +
                '<button type="button" class="aicourse-tour-mute" aria-pressed="false"></button>' +
                '<p class="aicourse-tour-count"></p>' +
                '<h2 class="aicourse-tour-title"></h2>' +
                '<p class="aicourse-tour-body"></p>' +
                '<div class="aicourse-tour-dots" aria-hidden="true"></div>' +
                '<div class="aicourse-tour-actions">' +
                    '<button type="button" class="aicourse-tour-skip"></button>' +
                    '<span class="aicourse-tour-spacer"></span>' +
                    '<button type="button" class="aicourse-tour-back"></button>' +
                    '<button type="button" class="aicourse-tour-next"></button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(this.root);
        document.body.classList.add('aicourse-tour-open');

        this.card = this.root.querySelector('.aicourse-tour-card');
        this.spot = this.root.querySelector('.aicourse-tour-spot');
        this.arrow = this.root.querySelector('.aicourse-tour-arrow');

        this.root.querySelector('.aicourse-tour-next').addEventListener('click', () => this.next());
        this.root.querySelector('.aicourse-tour-back').addEventListener('click', () => this.back());
        this.root.querySelector('.aicourse-tour-skip').addEventListener('click', () => this.end(true));
        this.root.querySelector('.aicourse-tour-mute').addEventListener('click', () => this.setMute());
        this.root.querySelector('.aicourse-tour-scrim').addEventListener('click', () => this.end(true));
        document.addEventListener('keydown', this.onKey);
        window.addEventListener('resize', this.reposition);
        window.addEventListener('scroll', this.reposition, {passive: true});

        this.setMute(this.narrator.isMuted());
        this.render();
        this.card.focus();
    }

    /**
     * Keyboard control: arrows move, Escape leaves.
     *
     * @param {KeyboardEvent} e The key event.
     * @returns {void}
     */
    onKey(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            this.end(true);
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            this.next();
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            this.back();
        } else if (e.key === 'Tab') {
            // Keep focus inside the card: the page behind is inert for the duration.
            const focusable = this.root.querySelectorAll('button');
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }

    /**
     * Apply or report the mute state.
     *
     * @param {Boolean} [force] Set explicitly instead of toggling.
     * @returns {void}
     */
    setMute(force) {
        const muted = (force === undefined) ? this.narrator.toggleMute() : force;
        const btn = this.root.querySelector('.aicourse-tour-mute');
        btn.setAttribute('aria-pressed', muted ? 'true' : 'false');
        btn.setAttribute('aria-label', muted ? this.sUnmute : this.sMute);
        btn.title = muted ? this.sUnmute : this.sMute;
        btn.classList.toggle('aicourse-tour-muted', muted);
        if (!muted && force === undefined) {
            this.narrate();
        }
    }

    /**
     * Speak the current step.
     *
     * @returns {void}
     */
    narrate() {
        if (!this.autoplay) {
            return;
        }
        const step = this.steps[this.index];
        this.narrator.speak(step.key, step.title + '. ' + step.body);
    }

    /**
     * Move the spotlight and the card onto the current step's target.
     *
     * @returns {void}
     */
    reposition() {
        if (!this.root) {
            return;
        }
        const step = this.steps[this.index];
        const target = step.target ? document.querySelector(step.target) : null;
        if (!target) {
            this.spot.style.display = 'none';
            this.arrow.style.display = 'none';
            this.card.classList.add('aicourse-tour-card-centre');
            this.card.style.insetInlineStart = '';
            this.card.style.insetBlockStart = '';
            return;
        }
        const r = target.getBoundingClientRect();
        const pad = 8;
        this.spot.style.display = 'block';
        this.spot.style.insetInlineStart = (r.left - pad) + 'px';
        this.spot.style.insetBlockStart = (r.top - pad) + 'px';
        this.spot.style.inlineSize = (r.width + pad * 2) + 'px';
        this.spot.style.blockSize = (r.height + pad * 2) + 'px';

        this.card.classList.remove('aicourse-tour-card-centre');
        const cw = this.card.offsetWidth;
        const ch = this.card.offsetHeight;
        // Prefer below the target; flip above when there is not room.
        let top = r.bottom + 16;
        if (top + ch > window.innerHeight - 12) {
            top = Math.max(12, r.top - ch - 16);
        }
        let left = r.left + (r.width / 2) - (cw / 2);
        left = Math.max(12, Math.min(left, window.innerWidth - cw - 12));
        this.card.style.insetInlineStart = left + 'px';
        this.card.style.insetBlockStart = top + 'px';

        this.placeArrow(r, left, top, cw, ch);
    }

    /**
     * Point the arrow from the card at the highlighted element.
     *
     * A ring around something tells you where to look but not what the card is talking about, and
     * on a busy page -- a course index full of links, a row of cards -- the connection between the
     * two is not obvious. The arrow draws the line explicitly, and it approaches from whichever
     * side the card is on so it never crosses over the card or points off-screen.
     *
     * @param {DOMRect} r The target's rectangle.
     * @param {Number} cardLeft The card's left edge.
     * @param {Number} cardTop The card's top edge.
     * @param {Number} cw The card's width.
     * @param {Number} ch The card's height.
     * @returns {void}
     */
    placeArrow(r, cardLeft, cardTop, cw, ch) {
        const SIZE = 56;
        const GAP = 10;
        const tx = r.left + r.width / 2;
        const ty = r.top + r.height / 2;
        const cx = cardLeft + cw / 2;
        const cy = cardTop + ch / 2;

        // Approach along whichever axis separates the card from the target most, so the arrow
        // travels the shortest visually sensible path.
        const dx = tx - cx;
        const dy = ty - cy;
        let angle;
        let ax;
        let ay;

        if (Math.abs(dy) >= Math.abs(dx)) {
            // Card is above or below: come in vertically.
            const fromAbove = dy > 0;
            angle = fromAbove ? 90 : -90;
            ax = Math.min(Math.max(tx, 12), window.innerWidth - SIZE - 12);
            ay = fromAbove ? (r.top - SIZE - GAP) : (r.bottom + GAP);
        } else {
            const fromLeft = dx > 0;
            angle = fromLeft ? 0 : 180;
            ax = fromLeft ? (r.left - SIZE - GAP) : (r.right + GAP);
            ay = Math.min(Math.max(ty - SIZE / 2, 12), window.innerHeight - SIZE - 12);
        }

        // ACF-FIX-2.1.49: a very tall or very wide target -- the course index runs nearly the
        // full height, a card grid nearly the full width -- leaves no room outside it on the
        // chosen axis, and the first version pushed the arrow past the edge of the window where
        // it could not be seen at all. When the preferred side does not fit, try the opposite
        // side, then the other axis, and only then give up and hide the arrow rather than
        // leave it pointing from off-screen.
        const fitsX = (v) => v >= 4 && v <= window.innerWidth - SIZE - 4;
        const fitsY = (v) => v >= 4 && v <= window.innerHeight - SIZE - 4;
        // ACF-FIX-2.1.49: reject any position that lands under the card.
        //
        // The arrow is placed between the card and the target, which is right in principle and
        // wrong when the two are close: with only a few pixels between them the arrow ended up
        // behind the card and was invisible for the whole step. Overlap with the card is now a
        // disqualifier, not just being off-screen.
        const clearsCard = (x, y) => !(x < cardLeft + cw && x + SIZE > cardLeft
            && y < cardTop + ch && y + SIZE > cardTop);
        const usable = (x, y) => fitsX(x) && fitsY(y) && clearsCard(x, y);

        if (!usable(ax, ay)) {
            // Every side of the target, nearest first, then the far corners for a target the card
            // is sitting almost on top of.
            const candidates = [
                {x: r.left - SIZE - GAP, y: ty - SIZE / 2, a: 0},
                {x: r.right + GAP, y: ty - SIZE / 2, a: 180},
                {x: tx - SIZE / 2, y: r.top - SIZE - GAP, a: 90},
                {x: tx - SIZE / 2, y: r.bottom + GAP, a: -90},
                {x: r.left - SIZE - GAP, y: r.top - SIZE - GAP, a: 45},
                {x: r.right + GAP, y: r.top - SIZE - GAP, a: 135},
                {x: r.left - SIZE - GAP, y: r.bottom + GAP, a: -45},
                {x: r.right + GAP, y: r.bottom + GAP, a: -135},
            ];
            const fit = candidates.find(c => usable(c.x, c.y));
            if (fit) {
                ax = fit.x;
                ay = fit.y;
                angle = fit.a;
            } else {
                // Nowhere sensible to put it: the ring alone carries the highlight.
                this.arrow.style.display = 'none';
                return;
            }
        }

        this.arrow.style.display = 'block';
        this.arrow.style.insetInlineStart = Math.round(ax) + 'px';
        this.arrow.style.insetBlockStart = Math.round(ay) + 'px';
        this.arrow.style.setProperty('--acf-tour-arrow-angle', angle + 'deg');
        // Restart the entrance animation on every step, or it plays once and never again.
        this.arrow.classList.remove('aicourse-tour-arrow-in');
        void this.arrow.offsetWidth;
        this.arrow.classList.add('aicourse-tour-arrow-in');
    }

    /**
     * Paint the current step.
     *
     * @returns {void}
     */
    render() {
        const step = this.steps[this.index];
        const last = this.index === this.steps.length - 1;
        this.root.querySelector('.aicourse-tour-title').textContent = step.title;
        this.root.querySelector('.aicourse-tour-body').textContent = step.body;
        this.root.querySelector('.aicourse-tour-count').textContent =
            this.sProgress.replace('{$a->current}', this.index + 1).replace('{$a->total}', this.steps.length);
        this.root.querySelector('.aicourse-tour-next').textContent = last ? this.sFinish : this.sNext;
        this.root.querySelector('.aicourse-tour-skip').textContent = this.sSkip;
        const back = this.root.querySelector('.aicourse-tour-back');
        back.textContent = this.sBack;
        back.style.display = this.index === 0 ? 'none' : '';
        this.root.setAttribute('aria-label', step.title);

        const dots = this.root.querySelector('.aicourse-tour-dots');
        dots.innerHTML = '';
        this.steps.forEach((s, i) => {
            const dot = document.createElement('span');
            dot.className = 'aicourse-tour-dot' + (i === this.index ? ' aicourse-tour-dot-on' : '');
            dots.appendChild(dot);
        });

        const target = step.target ? document.querySelector(step.target) : null;
        if (target && typeof target.scrollIntoView === 'function') {
            target.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
        // Let the smooth scroll settle before measuring, or the spotlight lands where the
        // target used to be.
        window.setTimeout(() => this.reposition(), 320);
        this.narrate();
    }

    /**
     * Advance, or finish on the last step.
     *
     * @returns {void}
     */
    next() {
        if (this.index >= this.steps.length - 1) {
            this.end(false);
            return;
        }
        this.index++;
        this.render();
    }

    /**
     * Step back.
     *
     * @returns {void}
     */
    back() {
        if (this.index === 0) {
            return;
        }
        this.index--;
        this.render();
    }

    /**
     * Close the tour and remember that it has been seen.
     *
     * @param {Boolean} skipped True when the user left early.
     * @returns {void}
     */
    end(skipped) {
        this.narrator.stop();
        document.removeEventListener('keydown', this.onKey);
        window.removeEventListener('resize', this.reposition);
        window.removeEventListener('scroll', this.reposition);
        if (this.root) {
            this.root.remove();
            this.root = null;
        }
        document.body.classList.remove('aicourse-tour-open');
        // Recorded whether finished or skipped: someone who dismissed it does not want it again.
        markSeen(this.version);
        if (skipped && window.console && window.console.debug) {
            window.console.debug('format_aicourse tour skipped at step ' + (this.index + 1));
        }
    }
}

/**
 * Offer the tour, and run it when accepted.
 *
 * @param {Object} config Steps and settings from the server.
 * @returns {void}
 */
export const init = (config) => {
    if (!config || !config.steps || !config.steps.length) {
        return;
    }
    // Started from a click rather than on load: it is the user gesture browsers require before
    // audio may play, and it means nobody is ambushed by a talking overlay.
    const offer = document.createElement('div');
    offer.className = 'aicourse-tour-offer';
    offer.setAttribute('role', 'region');
    Promise.all([
        getString('tour_offer_title', 'format_aicourse'),
        getString('tour_offer_body', 'format_aicourse'),
        getString('tour_offer_start', 'format_aicourse'),
        getString('tour_offer_dismiss', 'format_aicourse'),
    ]).then(([title, body, start, dismiss]) => {
        offer.setAttribute('aria-label', title);
        offer.innerHTML =
            '<h2 class="aicourse-tour-offer-title"></h2>' +
            '<p class="aicourse-tour-offer-body"></p>' +
            '<div class="aicourse-tour-offer-actions">' +
                '<button type="button" class="aicourse-tour-offer-dismiss"></button>' +
                '<button type="button" class="aicourse-tour-offer-start"></button>' +
            '</div>';
        offer.querySelector('.aicourse-tour-offer-title').textContent = title;
        offer.querySelector('.aicourse-tour-offer-body').textContent = body;
        offer.querySelector('.aicourse-tour-offer-start').textContent = start;
        offer.querySelector('.aicourse-tour-offer-dismiss').textContent = dismiss;
        document.body.appendChild(offer);

        offer.querySelector('.aicourse-tour-offer-start').addEventListener('click', () => {
            offer.remove();
            new Tour(config).start();
        });
        offer.querySelector('.aicourse-tour-offer-dismiss').addEventListener('click', () => {
            offer.remove();
            markSeen(config.version || 1);
        });
        return null;
    }).catch(() => null);
};
