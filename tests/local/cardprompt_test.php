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
 * Tests for the plugin-written card image prompt.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\local\cardprompt
 */
final class cardprompt_test extends \advanced_testcase {
    /**
     * Card images and colours are cached per request; each test starts clean.
     */
    protected function setUp(): void {
        parent::setUp();
        cardimage::reset_cache();
    }

    /**
     * An activity card's prompt carries its name, description, style, colour and the teacher's words last.
     */
    public function test_activity_prompt_is_complete_and_ordered(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'aicourse',
            'fullname' => 'Year 10 Biology',
            'numsections' => 1,
        ]);
        course_get_format($course)->update_course_format_options([
            'id' => $course->id,
            'cardimagestyle' => 'photo',
            'accentcolour' => '#0F766E',
        ]);
        $page = $this->getDataGenerator()->create_module(
            'page',
            [
                'course' => $course->id,
                'section' => 1,
                'name' => 'Photosynthesis quiz',
                'intro' => '<p>How plants turn <b>light</b> into&nbsp;energy.</p>'
                    . '<p>Includes <a href="https://x.test">links</a>.</p>',
            ]
        );
        $cm = get_fast_modinfo($course->id)->get_cm($page->cmid);

        $out = cardprompt::compose(get_course($course->id), cardimage::TYPE_CM, $cm, 'sunlight through leaves');
        $prompt = $out['prompt'];

        $this->assertSame('card-1', $out['promptVersion']);
        $this->assertStringContainsString('text', $out['negativePrompt']);
        $this->assertStringStartsWith('Subject: Photosynthesis quiz. How plants turn light into energy. Includes links.', $prompt);
        $this->assertStringContainsString('"Year 10 Biology"', $prompt);
        $this->assertStringContainsString('editorial photograph', $prompt);
        $this->assertStringContainsString('teal tones (#0F766E)', $prompt);
        $this->assertStringContainsString('16:9', $prompt);
        $this->assertStringEndsWith("Teacher's direction: sunlight through leaves.", $prompt);
        $this->assertStringNotContainsString('<', $prompt);
    }

    /**
     * The card's own colour wins over the accent; an unnamed section falls back to its summary.
     */
    public function test_section_prompt_uses_card_colour_and_summary(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['format' => 'aicourse', 'numsections' => 1]);
        course_get_format($course)->update_course_format_options([
            'id' => $course->id,
            'cardimagestyle' => 'flat',
            'accentcolour' => '#0F766E',
        ]);
        $section = get_fast_modinfo($course->id)->get_section_info(1);
        $DB->set_field('course_sections', 'summary', '<p>Managing risk on a building site</p>', ['id' => $section->id]);
        $DB->set_field('course_sections', 'name', null, ['id' => $section->id]);
        cardimage::set_colour((int) $course->id, cardimage::TYPE_SECTION, (int) $section->id, '#B91C1C');
        rebuild_course_cache($course->id, true);
        cardimage::reset_cache();
        $section = get_fast_modinfo($course->id)->get_section_info(1);

        $prompt = cardprompt::compose(get_course($course->id), cardimage::TYPE_SECTION, $section, '')['prompt'];

        $this->assertStringStartsWith('Subject: Managing risk on a building site.', $prompt);
        $this->assertStringContainsString('flat vector illustration', $prompt);
        $this->assertStringContainsString('(#B91C1C)', $prompt);
        $this->assertStringNotContainsString('#0F766E', $prompt);
        $this->assertStringNotContainsString("Teacher's direction", $prompt);
    }

    /**
     * Long names and descriptions never push the brief out, and the prompt stays within the limit.
     */
    public function test_prompt_stays_within_limit(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['format' => 'aicourse', 'numsections' => 1]);
        $page = $this->getDataGenerator()->create_module(
            'page',
            [
                'course' => $course->id,
                'section' => 1,
                'name' => str_repeat('Long name ', 25),
                'intro' => str_repeat('Detail sentence here. ', 200),
            ]
        );
        $cm = get_fast_modinfo($course->id)->get_cm($page->cmid);
        $prompt = cardprompt::compose(get_course($course->id), cardimage::TYPE_CM, $cm, str_repeat('x ', 200))['prompt'];

        $this->assertLessThanOrEqual(cardprompt::PROMPT_MAX, \core_text::strlen($prompt));
        $this->assertStringContainsString('Composition:', $prompt);
        $this->assertStringContainsString("Teacher's direction:", $prompt);
    }

    /**
     * Colour words.
     */
    public function test_colour_name(): void {
        $this->assertSame('deep teal', cardprompt::colour_name('#0F766E'));
        $this->assertSame('teal', cardprompt::colour_name('#14B8A6'));
        $this->assertSame('red', cardprompt::colour_name('#DC2626'));
        $this->assertSame('blue', cardprompt::colour_name('#2563EB'));
        $this->assertSame('deep blue', cardprompt::colour_name('#172554'));
        $this->assertSame('pale golden yellow', cardprompt::colour_name('#FEF08A'));
        $this->assertSame('slate grey', cardprompt::colour_name('#64748B'));
        $this->assertSame('charcoal', cardprompt::colour_name('#111'));
    }
}
