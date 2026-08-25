<?php
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
 * Plain-text helpers for values that leave PHP and are re-inserted by JavaScript.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\local;

/**
 * Plain-text helpers.
 *
 * ACF-FIX-2.1.191. format_string() returns markup: it runs the site's filters and then escapes the
 * result for insertion into HTML, so a course called "Research Design & Industry Context" comes
 * back as "Research Design &amp; Industry Context". That is correct for a mustache {{...}} and
 * wrong for every consumer that does NOT parse HTML -- and this plugin has three of them:
 *
 *   1. the player sidebar header, whose course name is assigned with textContent;
 *   2. the AI banner modal, whose course name is assigned with jQuery .text();
 *   3. the banner generation request, whose course name is JSON sent to the image service.
 *
 * All three showed, or sent, the literal characters "&amp;". Escaping is a property of the
 * destination, not of the string, so the fix is to ask for the un-escaped filtered string at the
 * point where the destination is not HTML -- never to strip entities back out afterwards, which
 * would also decode entities the author typed deliberately.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text {
    /**
     * A name run through the site's filters but NOT escaped for HTML.
     *
     * Use for any value that will be written with textContent, jQuery .text(), a JSON payload or
     * anything else that inserts characters rather than parsing markup. For a mustache {{value}}
     * or any other HTML destination, keep using format_string().
     *
     * @param string $name The raw name, e.g. $course->fullname.
     * @param \context|null $context Context to filter in, or null for the current one.
     * @return string Filtered plain text with real "&", "<" and quote characters.
     */
    public static function plain(string $name, ?\context $context = null): string {
        $options = ['escape' => false];
        if ($context !== null) {
            $options['context'] = $context;
        }

        $filtered = format_string($name, true, $options);

        // The 'escape' option is honoured by format_string() from Moodle 4.3 onwards and this
        // plugin requires 4.4, so the branch below is normally not taken. It exists because the
        // cost of the option NOT being honoured is the exact bug this class fixes, silently.
        //
        // The fallback is guarded by a probe rather than applied unconditionally, and that
        // matters: decoding a string that was never escaped turns a course name containing a
        // deliberate literal "&amp;" into "&", which is a different name. The probe asks the
        // installed format_string() one question -- does escape=false actually work here -- and
        // only the site where the answer is no pays for the decode.
        if (!self::escape_option_works()) {
            $filtered = html_entity_decode($filtered, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $filtered;
    }

    /**
     * Whether format_string()'s 'escape' => false option is honoured on this site.
     *
     * @return bool
     */
    protected static function escape_option_works(): bool {
        static $works = null;

        if ($works === null) {
            // A bare ampersand is escaped by every version of format_string() that escapes at all,
            // and left alone by every version that honours the option.
            $works = (format_string('&', true, ['escape' => false, 'filter' => false]) === '&');
        }

        return $works;
    }
}
