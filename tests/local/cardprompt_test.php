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
#[\PHPUnit\Framework\Attributes\CoversClass(\format_aicourse\local\cardprompt::class)]
final class cardprompt_test extends \advanced_testcase {
    /**
     * Card images and colours are cached per request; each test starts clean.
     */
    protected function setUp(): void {
        parent::setUp();
        cardimage::reset_cache();
    }

    /**
     * An activity card's prompt is a described scene, then the shared tail, with the teacher's words.
     */
    public function test_activity_prompt_is_a_scene_with_tail(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'aicourse',
            'fullname' => 'BSB50420 Diploma of Leadership and Management',
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
                'name' => 'Leading change',
                'intro' => '<p>How leaders guide <b>teams</b> through&nbsp;change.</p>'
                    . '<p>Includes <a href="https://x.test">links</a>.</p>',
            ]
        );
        $cm = get_fast_modinfo($course->id)->get_cm($page->cmid);

        $out = cardprompt::compose(get_course($course->id), cardimage::TYPE_CM, $cm, 'sunlight through windows');
        $prompt = $out['prompt'];

        $this->assertSame('card-2', $out['promptVersion']);
        $this->assertStringStartsWith(
            'A realistic professional photograph representing Leading change, a Page '
                . 'activity in an online course in Diploma of Leadership and Management.',
            $prompt
        );
        // The activity type gives the scene when the title is not a common one.
        $this->assertSame('page', $out['brief']['sceneKey']);
        $this->assertStringContainsString('an adult learner reading an engaging lesson on a tablet', $prompt);
        $this->assertStringContainsString(
            'The topic covers: How leaders guide teams through change. Includes links.',
            $prompt
        );
        $this->assertStringContainsString('The teacher asks for: sunlight through windows.', $prompt);
        $this->assertStringNotContainsString('BSB50420', $prompt);
        $this->assertStringNotContainsString('TEAMS', $prompt);
        $this->assertStringNotContainsString('"', $prompt);
        $this->assertStringNotContainsString('<', $prompt);
        // The tail ends the prompt, whole, and travels on its own too.
        $this->assertStringEndsWith("\n\n" . $out['promptTail'], $prompt);
        $this->assertStringContainsString('deep teal (#0F766E)', $out['promptTail']);
        $this->assertStringContainsString('16:9', $out['promptTail']);
        $this->assertStringContainsString('No visible text', $out['promptTail']);
        // The 3.0.0 bans that emptied every scene are gone.
        $this->assertStringNotContainsString('laptops', $prompt);
        $this->assertStringNotContainsString('single focal point', $prompt);
        $this->assertStringNotContainsString('screens with content', $out['negativePrompt']);
        $this->assertStringContainsString('cartoon', $out['negativePrompt']);
        // The brief carries every fact the scene writer needs.
        $this->assertSame('Leading change', $out['brief']['topic']);
        $this->assertSame('page', $out['brief']['activityType']);
        $this->assertSame('Diploma of Leadership and Management', $out['brief']['courseTopic']);
        $this->assertSame('adult learners', $out['brief']['audience']);
        $this->assertSame('#0F766E', $out['brief']['colourHex']);
        $this->assertSame('sunlight through windows', $out['brief']['teacherDirection']);
    }

    /**
     * A common section title gets its own scene; "Student Instructions" gets the instructions scene.
     */
    public function test_common_title_gets_its_scene(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['format' => 'aicourse', 'numsections' => 1]);
        $section = get_fast_modinfo($course->id)->get_section_info(1);
        $DB->set_field('course_sections', 'name', 'Student Instructions', ['id' => $section->id]);
        rebuild_course_cache($course->id, true);
        $section = get_fast_modinfo($course->id)->get_section_info(1);

        $out = cardprompt::compose(get_course($course->id), cardimage::TYPE_SECTION, $section, '');

        $this->assertSame('instructions', $out['brief']['sceneKey']);
        $this->assertStringContainsString('checklist', $out['prompt']);
        $this->assertStringContainsString('course handbook', $out['prompt']);
        $this->assertStringNotContainsString('The teacher asks for', $out['prompt']);
    }

    /**
     * The scene table: common titles, activity types, and topics that must not be caught.
     */
    public function test_scene_matching(): void {
        $this->assertSame('welcome', cardprompt::scene('Welcome to the course', '')[0]);
        $this->assertSame('quiz', cardprompt::scene('Knowledge check', 'page')[0]);
        $this->assertSame('assessment', cardprompt::scene('Assessment 2 - Written questions', '')[0]);
        $this->assertSame('forum', cardprompt::scene('Introduce yourself', '')[0]);
        $this->assertSame('certificate', cardprompt::scene('Certificate of completion', '')[0]);
        $this->assertSame('resources', cardprompt::scene('Learning materials', '')[0]);
        // The title wins over the type; the type is used when the title says nothing common.
        $this->assertSame('quiz', cardprompt::scene('Leading change', 'quiz')[0]);
        $this->assertSame('live', cardprompt::scene('Leading change', 'zoom')[0]);
        // Topic words are not housekeeping words.
        $this->assertSame('general', cardprompt::scene('Construction materials', '')[0]);
        $this->assertSame('general', cardprompt::scene('Providing client support', '')[0]);
        $this->assertSame('general', cardprompt::scene('Working in community services', '')[0]);
        $this->assertSame('general', cardprompt::scene('Testing and tagging', '')[0]);
        $this->assertSame('general', cardprompt::scene('Leading effective workplace relationships', '')[0]);
        $this->assertSame('general', cardprompt::scene('Practical first aid', '')[0]);
        $this->assertSame('workplace', cardprompt::scene('Workplace assessment', '')[0]);
        $this->assertSame('workplace', cardprompt::scene('Practical tasks', '')[0]);
        // School courses get school students.
        $this->assertStringContainsString('a secondary school student', cardprompt::scene('Quiz', '', true)[1]);
    }

    /**
     * The card's own colour wins over the accent; a section named only by number uses its summary.
     */
    public function test_section_prompt_uses_card_colour_and_summary(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'aicourse',
            'fullname' => 'Year 10 Biology',
            'numsections' => 1,
        ]);
        course_get_format($course)->update_course_format_options([
            'id' => $course->id,
            'cardimagestyle' => 'flat',
            'accentcolour' => '#0F766E',
        ]);
        $section = get_fast_modinfo($course->id)->get_section_info(1);
        $DB->set_field(
            'course_sections',
            'summary',
            '<p>Managing risk on a building site. More here.</p>',
            ['id' => $section->id]
        );
        $DB->set_field('course_sections', 'name', 'Week 3', ['id' => $section->id]);
        cardimage::set_colour((int) $course->id, cardimage::TYPE_SECTION, (int) $section->id, '#B91C1C');
        rebuild_course_cache($course->id, true);
        cardimage::reset_cache();
        $section = get_fast_modinfo($course->id)->get_section_info(1);

        $out = cardprompt::compose(get_course($course->id), cardimage::TYPE_SECTION, $section, '');
        $prompt = $out['prompt'];

        $this->assertStringStartsWith(
            'A flat vector illustration representing Managing risk on a building site, a '
                . 'section in an online course in Year 10 Biology.',
            $prompt
        );
        $this->assertStringContainsString('a secondary school student actively engaged in Managing risk', $prompt);
        $this->assertStringNotContainsString('Week 3', $prompt);
        $this->assertStringContainsString('flat vector illustration', $out['promptTail']);
        $this->assertStringContainsString('(#B91C1C)', $prompt);
        $this->assertStringNotContainsString('#0F766E', $prompt);
        $this->assertStringNotContainsString('cartoon', $out['negativePrompt']);
        $this->assertSame('school students', $out['brief']['audience']);
    }

    /**
     * Banners: the course banner is about the course, a section banner about its section.
     */
    public function test_banner_prompts(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'aicourse',
            'fullname' => 'CPC30220 - Certificate III in Carpentry',
            'summary' => '<p>Build <strong>framing</strong>, stairs and formwork.</p>',
            'numsections' => 1,
        ]);
        course_get_format($course)->update_course_format_options([
            'id' => $course->id,
            'cardimagestyle' => 'render3d',
            'accentcolour' => '#2563EB',
        ]);

        $out = cardprompt::compose_banner(get_course($course->id), null, 'golden hour');
        $this->assertSame('banner-1', $out['promptVersion']);
        $this->assertSame('banner', $out['brief']['imageKind']);
        $this->assertStringStartsWith(
            'A polished 3D render for the banner of an online course in Certificate III '
                . 'in Carpentry. It shows adult learners putting what they learn in Certificate III in Carpentry '
                . 'into practice',
            $out['prompt']
        );
        $this->assertStringContainsString('The course covers: Build framing, stairs and formwork.', $out['prompt']);
        $this->assertStringContainsString('The teacher asks for: golden hour.', $out['prompt']);
        $this->assertStringContainsString('left third quieter', $out['promptTail']);
        $this->assertStringContainsString('blue (#2563EB)', $out['promptTail']);

        $section = get_fast_modinfo($course->id)->get_section_info(1);
        $DB->set_field('course_sections', 'name', 'Welcome', ['id' => $section->id]);
        rebuild_course_cache($course->id, true);
        $section = get_fast_modinfo($course->id)->get_section_info(1);
        $out = cardprompt::compose_banner(get_course($course->id), $section, '');
        $this->assertSame('welcome', $out['brief']['sceneKey']);
        $this->assertStringContainsString('representing Welcome, a section in', $out['prompt']);
    }

    /**
     * Long names and descriptions never push the tail out, and the prompt stays within the limit.
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
        $out = cardprompt::compose(get_course($course->id), cardimage::TYPE_CM, $cm, str_repeat('x ', 200));

        $this->assertLessThanOrEqual(cardprompt::PROMPT_MAX, \core_text::strlen($out['prompt']));
        $this->assertStringEndsWith($out['promptTail'], $out['prompt']);
    }

    /**
     * Title helpers.
     */
    public function test_title_helpers(): void {
        $this->assertTrue(cardprompt::is_numbered_only('Week 3'));
        $this->assertTrue(cardprompt::is_numbered_only('Topic 12:'));
        $this->assertFalse(cardprompt::is_numbered_only('Week 3: Safety'));
        $this->assertSame('Risk management', cardprompt::strip_number_prefix('Module 3: Risk management'));
        $this->assertSame('Week 3', cardprompt::strip_number_prefix('Week 3'));
        $this->assertSame('Diploma of Leadership', cardprompt::course_topic('BSB50420 Diploma of Leadership'));
        $this->assertSame('Year 10 Biology', cardprompt::course_topic('Year 10 Biology'));
        $this->assertSame('Bold and plain', cardprompt::html_plain('<p><b>Bold</b>&nbsp;and</p><p>plain</p>'));
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
