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
 * A reference to this format's own settings, for the AI Tutor.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\local;

/**
 * Describes this format's settings so the AI Tutor can answer questions about them.
 *
 * ACF-FIX-2.1.96. A teacher asking "how do I hide the tabs?" or "why has my colour not changed?"
 * was getting an answer about their course content, because that is all the tutor was given. The
 * settings are the part of this format teachers most need help with, and the plugin already holds
 * a plain-language explanation of every one of them.
 *
 * Built from the language strings rather than written out again here, so the reference cannot
 * drift from what the settings screens actually say: change a description once and the tutor's
 * answer changes with it, in whatever language the site runs in.
 *
 * Sent only to users who can edit the course. A learner has no use for it, it would be a large
 * addition to every request they make, and their questions are about the course rather than how it
 * was built.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class formathelp {
    /**
     * Every course setting this format defines, discovered rather than listed.
     *
     * ACF-FIX-2.1.103. This was a hand-maintained array, which meant every new setting had to be
     * remembered here as well -- and the one that was forgotten would be the one a teacher asked
     * about, with the tutor confidently unaware it existed.
     *
     * The list is now taken from the format's own options, so a setting is included the moment it
     * is defined. A setting is described if it has a label and a help string; anything without
     * them is skipped, because there would be nothing useful to say about it.
     *
     * @return string[] Setting names, in the order the format declares them.
     */
    protected static function discover_settings(): array {
        global $COURSE;
        try {
            $format = course_get_format($COURSE);
            $options = $format->course_format_options(true);
        } catch (\Throwable $e) {
            unset($e);
            return [];
        }
        $names = [];
        foreach (array_keys($options) as $name) {
            // Both strings must exist: the label names it, the help explains it. A setting with a
            // label and no help would be listed with nothing under it, which reads as an omission.
            if (
                get_string_manager()->string_exists($name, 'format_aicourse')
                    && get_string_manager()->string_exists($name . '_help', 'format_aicourse')
            ) {
                $names[] = $name;
            }
        }
        return $names;
    }

    /**
     * Build the reference.
     *
     * @return string Plain text, or an empty string if nothing could be assembled.
     */
    public static function get_reference(): string {
        $lines = [];
        $lines[] = 'ABOUT THE AI COURSE FORMAT SETTINGS';
        $lines[] = '';
        $lines[] = 'The person asking can edit this course, so they may ask how to change how the '
            . 'course looks or behaves. Below is what each setting does. Answer from this rather '
            . 'than guessing, and tell them where to find the setting.';
        $lines[] = '';
        $lines[] = 'WHERE THE SETTINGS ARE';
        $lines[] = '- Per course: Course settings, then the "Course format" section.';
        $lines[] = '- Site-wide: Site administration > Plugins > Course formats > AI Course Format.';
        $lines[] = '';
        $lines[] = 'TWO RULES THAT CATCH PEOPLE OUT';
        $lines[] = '1. A site "default" only applies to BRAND NEW courses. A course that has ever '
            . 'saved its settings keeps its own value and will not pick up a changed default. To '
            . 'change courses that already exist, use the matching "force" or "override all '
            . 'courses" setting instead.';
        $lines[] = '2. Anything this format hides -- the tabs, the footer, the breadcrumb, the logo '
            . 'band -- always comes back while Edit mode is on. If a teacher says something did '
            . 'not hide, check whether they had Edit mode switched on.';
        $lines[] = '';
        $lines[] = 'THE SETTINGS';

        foreach (self::discover_settings() as $setting) {
            $name = self::string_or_null($setting);
            $text = self::string_or_null($setting . '_help');
            if ($name === null || $text === null) {
                continue;
            }
            $lines[] = '';
            $lines[] = '### ' . $name;
            $lines[] = self::flatten($text);
        }

        if (count($lines) < 15) {
            return '';
        }
        return implode("\n", $lines);
    }

    /**
     * Fetch a language string, or null if this build does not have it.
     *
     * Missing strings are skipped rather than allowed to appear as [[stringname]] in the reference,
     * which would be worse than the setting simply being absent: the tutor would repeat the
     * placeholder back to the teacher as though it meant something.
     *
     * @param string $key The string identifier.
     * @return string|null
     */
    protected static function string_or_null(string $key): ?string {
        if (!get_string_manager()->string_exists($key, 'format_aicourse')) {
            return null;
        }
        return get_string($key, 'format_aicourse');
    }

    /**
     * Reduce a help string to plain text.
     *
     * The help strings carry Markdown and a little HTML for the settings screens. Neither helps a
     * language model and both cost tokens, so they are stripped here: bold markers, code markers
     * and tags all go, leaving the words.
     *
     * @param string $text The raw string.
     * @return string
     */
    protected static function flatten(string $text): string {
        $text = str_replace(['<br />', '<br>', '<br/>'], "\n", $text);
        $text = strip_tags($text);
        $text = str_replace(['**', chr(96)], '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
