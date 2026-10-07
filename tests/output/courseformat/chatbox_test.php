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
 * Tests for the AI Tutor panel's exported context and markup.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\output\courseformat;

/**
 * Tests for the AI Tutor panel (3.2.0).
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\output\courseformat\chatbox
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_aicourse\output\courseformat\chatbox::class)]
final class chatbox_test extends \advanced_testcase {
    /**
     * Build a course with an activity whose names carry characters that HTML escapes.
     *
     * @return array [course, cm_info]
     */
    protected function make_course(): array {
        $course = $this->getDataGenerator()->create_course([
            'format' => 'aicourse',
            'numsections' => 2,
            'fullname' => 'Health & Safety "Level 1"',
        ], ['createsections' => true]);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Notes & tips',
        ]);
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);
        return [$course, $cm];
    }

    /**
     * Names reach the double-mustache fields as plain text, so they are escaped exactly once.
     */
    public function test_context_names_are_plain_text(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$course, $cm] = $this->make_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $PAGE->set_cm($cm, $course);

        $chatbox = new chatbox($course);
        $output = $PAGE->get_renderer('core');
        $data = $chatbox->export_for_template($output);

        $this->assertSame('Notes & tips', $data->contexttitle);
        $this->assertSame('Health & Safety "Level 1"', $data->coursename);
        $this->assertSame(get_string('aiassistant_context_activity', 'format_aicourse'), $data->contextkind);

        $html = $output->render_from_template('format_aicourse/chatbox', $data);
        $this->assertStringContainsString('Notes &amp; tips', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('Notes & tips<', $html);
    }

    /**
     * The five study tools are exported with labels, descriptions and one icon flag each, and
     * every one maps to a canned prompt the JavaScript can send.
     */
    public function test_tools_are_exported(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$course] = $this->make_course();
        $PAGE->set_course($course);

        $output = $PAGE->get_renderer('core');
        $data = (new chatbox($course))->export_for_template($output);

        $this->assertCount(5, $data->tools);
        $keys = array_column($data->tools, 'key');
        $this->assertSame(['practice', 'concepts', 'structure', 'workplace', 'checklist'], $keys);
        foreach ($data->tools as $tool) {
            $this->assertTrue($tool['is' . $tool['key']]);
            $this->assertNotSame('', $tool['label']);
            $this->assertNotSame('', $tool['desc']);
            $this->assertTrue(get_string_manager()->string_exists('aiassistant_prompt_' . $tool['key'], 'format_aicourse'));
        }
    }

    /**
     * Without an activity or section the rail shows the course itself.
     */
    public function test_course_context_without_activity(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$course] = $this->make_course();
        $PAGE->set_course($course);

        $output = $PAGE->get_renderer('core');
        $data = (new chatbox($course))->export_for_template($output);

        $this->assertSame(get_string('aiassistant_context_course', 'format_aicourse'), $data->contextkind);
        $this->assertSame('Health & Safety "Level 1"', $data->contexttitle);
        $this->assertSame('', $data->coursename);
    }

    /**
     * The activity name handed to JavaScript is plain text, because it is substituted into the
     * question the learner sends.
     */
    public function test_js_config_activity_name_is_plain_text(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$course, $cm] = $this->make_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $PAGE->set_cm($cm, $course);

        $method = new \ReflectionMethod(chatbox::class, 'get_js_config');
        $method->setAccessible(true);
        $config = $method->invoke(new chatbox($course));

        $this->assertSame('Notes & tips', $config['activityname']);
        $this->assertSame((int) $cm->id, $config['activityid']);
    }

    /**
     * The new markup carries the hooks the JavaScript needs, and every attribute string is
     * rendered with cleanstr so a translation containing quotes cannot break an attribute.
     */
    public function test_template_has_study_view_controls(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$course] = $this->make_course();
        $PAGE->set_course($course);

        $output = $PAGE->get_renderer('core');
        $data = (new chatbox($course))->export_for_template($output);
        $html = $output->render_from_template('format_aicourse/chatbox', $data);

        $needles = [
            'id="aicourse-ai-backdrop"',
            'data-view="compact"',
            'aicourse-ai-expand',
            'aicourse-ai-newchat',
            'id="aicourse-ai-suggest"',
            'aicourse-ai-rail',
            'id="aicourse-ai-quick-actions"',
        ];
        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertSame(5, substr_count($html, 'aicourse-ai-tool-card'));
        $this->assertSame(5, substr_count($html, 'aicourse-ai-chip'));
        $this->assertStringNotContainsString('{{', $html);
    }
}
