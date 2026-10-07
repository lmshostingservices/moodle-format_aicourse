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

namespace format_aicourse\local;

/**
 * Plain-text previews of AI Tutor answers for the reports.
 *
 * 3.2.0: tutor answers are Markdown with fenced "quiz" JSON blocks. The learner sees them
 * rendered; the report tables used to show the first 300 raw characters, which for a practice
 * question was mostly JSON punctuation. A preview reads like the answer: Markdown markers removed,
 * a quiz block summarised as "[Practice questions: 3]", whitespace folded. The full answer is
 * rendered in the browser by format_aicourse/local/richtext, exactly as the learner saw it.
 *
 * The result is plain text and is always escaped by whatever outputs it.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answertext {
    /**
     * A one-paragraph plain-text preview of an answer.
     *
     * @param string $answer The stored answer (Markdown).
     * @param int $length Maximum length in characters; 0 for no limit.
     * @return string Plain text.
     */
    public static function preview(string $answer, int $length = 0): string {
        $text = str_replace(["\r\n", "\r"], "\n", $answer);
        $fence = str_repeat(chr(96), 3);

        // Quiz blocks, including one cut off before its closing fence.
        $text = preg_replace_callback(
            '/' . $fence . '[ \t]*(?:quiz|mcq)[ \t]*\n(.*?)(?:' . $fence . '|$)/si',
            static function (array $match): string {
                $count = max(1, preg_match_all('/"question"\s*:/', $match[1]));
                return "\n" . get_string('aireport_practicequestions', 'format_aicourse', $count) . "\n";
            },
            $text
        );
        // Other code fences: keep the code, drop the markers.
        $text = preg_replace('/' . $fence . '[\w-]*/', '', $text);
        // Headings, quotes and callout labels.
        $text = preg_replace('/^\s{0,3}#{1,6}\s+/m', '', $text);
        $text = preg_replace('/^\s*>\s?/m', '', $text);
        // Lists: checklists, bullets.
        $text = preg_replace('/^(\s*)[-*+]\s+\[[ xX]\]\s+/m', '$1- ', $text);
        $text = preg_replace('/^(\s*)[-*+•]\s+/m', '$1- ', $text);
        // Table delimiter rows, then cell separators.
        $text = preg_replace('/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/m', '', $text);
        $text = preg_replace('/^\s*\|\s*|\s*\|\s*$/m', '', $text);
        $text = preg_replace('/\s*\|\s*/', ' / ', $text);
        // Inline emphasis, code, strike, links.
        $text = preg_replace('/(\*\*|__)(?=\S)(.+?)(?<=\S)\1/', '$2', $text);
        $text = preg_replace('/(?<![\w*])\*(?=[^\s*])([^*\n]*?[^\s*])\*(?![\w*])/', '$1', $text);
        $text = preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/', '$1', $text);
        $tick = chr(96);
        $text = preg_replace('/' . $tick . '([^' . $tick . '\n]+)' . $tick . '/', '$1', $text);
        $text = preg_replace('/\[([^\]\n]+)\]\(([^)\s]+)\)/', '$1', $text);
        // Fold whitespace.
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        if ($length > 0 && \core_text::strlen($text) > $length) {
            $text = rtrim(\core_text::substr($text, 0, $length - 1)) . '…';
        }
        return $text;
    }

    /**
     * Whether the full answer shows more than the preview: it was shortened, or it has formatting.
     *
     * @param string $answer The stored answer.
     * @param string $preview The preview shown for it.
     * @return bool True when a "Show full answer" control is worth offering.
     */
    public static function has_more(string $answer, string $preview): bool {
        $plain = trim(preg_replace('/\s+/u', ' ', $answer));
        return $plain !== $preview;
    }
}
