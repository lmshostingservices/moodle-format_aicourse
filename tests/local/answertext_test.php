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
 * Tests for the report previews of AI Tutor answers (3.2.0).
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\local;

/**
 * Tests for the report previews of AI Tutor answers.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\local\answertext
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_aicourse\local\answertext::class)]
final class answertext_test extends \advanced_testcase {
    /**
     * Markdown markers are removed and the text reads as a sentence.
     */
    public function test_markdown_is_removed(): void {
        $tick = chr(96);
        $answer = "## Key ideas\n\nThe **hierarchy of controls** puts *elimination* first.\n\n"
            . "1. Identify\n2. Assess\n\n- [ ] Review slide 3\n\n> **Tip:** ask your {$tick}HSR{$tick}. "
            . "See [the guide](https://example.com).";

        $preview = answertext::preview($answer);

        $this->assertSame(
            'Key ideas The hierarchy of controls puts elimination first. 1. Identify 2. Assess '
                . '- Review slide 3 Tip: ask your HSR. See the guide.',
            $preview
        );
    }

    /**
     * A quiz block is summarised, never shown as JSON, even when cut off.
     */
    public function test_quiz_block_is_summarised(): void {
        $fence = str_repeat(chr(96), 3);
        $answer = "Let's practise.\n\n{$fence}quiz\n[{\"question\": \"A?\", \"options\": [\"x\", \"y\"], \"answer\": \"A\"},"
            . "{\"question\": \"B?\", \"options\": [\"x\", \"y\"], \"answer\": \"B\"}]\n{$fence}\n\nGood luck!";
        $cut = "Here:\n\n{$fence}quiz\n[{\"question\": \"A?\", \"options\": [\"x\"";

        $this->assertSame(
            "Let's practise. " . get_string('aireport_practicequestions', 'format_aicourse', 2) . ' Good luck!',
            answertext::preview($answer)
        );
        $summary = get_string('aireport_practicequestions', 'format_aicourse', 1);
        $this->assertSame('Here: ' . $summary, answertext::preview($cut));
        $this->assertStringNotContainsString('"options"', answertext::preview($answer));
    }

    /**
     * The preview is limited in length with an ellipsis, and plain answers need no "full answer".
     */
    public function test_length_and_has_more(): void {
        $long = str_repeat('word ', 100);
        $preview = answertext::preview($long, 50);

        $this->assertSame(50, \core_text::strlen($preview));
        $this->assertStringEndsWith('…', $preview);
        $this->assertTrue(answertext::has_more($long, $preview));

        $plain = 'Start by reading the brief.';
        $this->assertFalse(answertext::has_more($plain, answertext::preview($plain, 300)));
        $this->assertTrue(answertext::has_more('Start **here**.', answertext::preview('Start **here**.', 300)));
    }

    /**
     * Markup typed by a student or returned by the service stays text; the preview is escaped on
     * output by the report, so it must not be turned into anything else here.
     */
    public function test_markup_is_left_as_text(): void {
        $this->assertSame('<b>not html</b> & more', answertext::preview('<b>not html</b> & more'));
    }

    /**
     * Answers that stop part-way are recognised: the two from the live report (3.2.2), and an
     * unclosed quiz block.
     *
     * @return array Answers that are cut off.
     */
    public static function cut_off_provider(): array {
        $fence = str_repeat(chr(96), 3);
        return [
            'mid-sentence' => ["Hello there!\n\nHere are three multiple-choice practice questions, designed to test"],
            'after bold term' => ["You'll find these settings by navigating to **Course settings**, and"],
            'trailing comma' => ['The three statements are the balance sheet,'],
            'inside emphasis' => ['Let me know if you have *any questions*'],
            'unclosed quiz block' => ["Try these.\n\n{$fence}quiz\n[{\"question\": \"Wh"],
        ];
    }

    /**
     * An answer that stops part-way is reported as cut off.
     *
     * @dataProvider cut_off_provider
     * @param string $answer The answer.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('cut_off_provider')]
    public function test_cut_off_answers_are_recognised(string $answer): void {
        $this->assertTrue(answertext::looks_cut_off($answer));
    }

    /**
     * Complete answers are left alone, including the shapes that end without a full stop by
     * design. A false alarm would tell a learner a good answer is broken.
     *
     * @return array Answers that are complete.
     */
    public static function complete_provider(): array {
        $fence = str_repeat(chr(96), 3);
        return [
            'full stop' => ['Good luck!'],
            'question' => ['Would you like to try a practice question?'],
            'colon' => ['Ask me anything:'],
            'bullet' => ["Steps:\n- Review slide 3"],
            'numbered' => ["Steps:\n1. Identify the hazard"],
            'checklist' => ["- [ ] Read the brief"],
            'table row' => ["| Term | Meaning |"],
            'heading' => ['## Summary'],
            'number' => ['The answer is 42'],
            'emoji' => ['Nice work 🎉'],
            'closed quiz block' => ["{$fence}quiz\n[]\n{$fence}\n\nGood luck!"],
            'empty' => [''],
        ];
    }

    /**
     * A complete answer is not reported as cut off.
     *
     * @dataProvider complete_provider
     * @param string $answer The answer.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('complete_provider')]
    public function test_complete_answers_are_not_flagged(string $answer): void {
        $this->assertFalse(answertext::looks_cut_off($answer));
    }
}
