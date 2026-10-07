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
 * Tests for the General section rule and the shared course totals.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\local\activityinfo
 * @covers     \format_aicourse\local\progress
 * @covers     \format_aicourse
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_aicourse\local\activityinfo::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\format_aicourse\local\progress::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\format_aicourse::class)]
final class generalsection_test extends \advanced_testcase {
    /**
     * A course in this format that hides General from everyone, with an Announcements forum.
     *
     * @return \stdClass
     */
    private function make_course(): \stdClass {
        $course = $this->getDataGenerator()->create_course(['format' => 'aicourse', 'numsections' => 1]);
        course_get_format($course)->update_course_format_options(['id' => $course->id, 'hidegeneral' => 2]);
        $this->getDataGenerator()->create_module('forum', ['course' => $course->id, 'section' => 0, 'type' => 'news']);
        $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Lesson']);
        return $course;
    }

    /**
     * The body classes a course page gets, as the current user.
     *
     * @param \stdClass $course The course.
     * @return string
     */
    private function body_classes(\stdClass $course): string {
        $page = new \moodle_page();
        $page->set_course(get_course($course->id));
        return $page->bodyclasses;
    }

    /**
     * General holding only Announcements is hidden as the setting asks.
     */
    public function test_announcements_only_general_is_hidden(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        $this->setAdminUser();

        $this->assertFalse(activityinfo::general_has_content(get_fast_modinfo($course->id)));
        $this->assertStringContainsString('aicourse-hidegeneral-all', $this->body_classes($course));
    }

    /**
     * General holding a real activity is never hidden, whatever the setting says.
     */
    public function test_general_with_content_is_never_hidden(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 0, 'name' => 'Start here']);
        $this->setAdminUser();

        $this->assertTrue(activityinfo::general_has_content(get_fast_modinfo($course->id)));
        $this->assertStringNotContainsString('aicourse-hidegeneral', $this->body_classes($course));
    }

    /**
     * An activity in General the learner cannot see does not stop General being hidden for them.
     */
    public function test_hidden_activity_in_general_does_not_count(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 0, 'visible' => 0]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->assertFalse(activityinfo::general_has_content(get_fast_modinfo($course->id)));
    }

    /**
     * Only the course's news forum is Announcements; an ordinary forum is content.
     */
    public function test_is_announcements(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id, 'section' => 0]);
        $this->setAdminUser();
        $modinfo = get_fast_modinfo($course->id);

        $kinds = [];
        foreach ($modinfo->sections[0] as $cmid) {
            $kinds[$cmid] = activityinfo::is_announcements($modinfo->get_cm($cmid));
        }
        $this->assertSame(1, count(array_filter($kinds)));
        $this->assertFalse($kinds[$forum->cmid]);
        $this->assertTrue(activityinfo::general_has_content($modinfo));
    }

    /**
     * Totals count General and subsection contents, and leave out stealth activities for learners.
     */
    public function test_course_totals(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('allowstealth', 1);
        $course = $this->make_course();
        $gen = $this->getDataGenerator();
        $gen->create_module('page', ['course' => $course->id, 'section' => 0]);
        $gen->create_module('page', ['course' => $course->id, 'section' => 1, 'visibleoncoursepage' => 0]);
        $student = $gen->create_and_enrol($course, 'student');
        $this->setUser($student);

        // Announcements, the General page and the lesson; not the stealth page.
        $totals = progress::course_totals(get_fast_modinfo($course->id));
        $this->assertSame(3, $totals['activities']);
        $this->assertGreaterThan(0, $totals['minutes']);

        if (file_exists($CFG->dirroot . '/mod/subsection/version.php')) {
            \core\plugininfo\mod::enable_plugin('subsection', 1);
            $sub = $gen->create_module('subsection', ['course' => $course->id, 'section' => 1]);
            $section = get_fast_modinfo($course->id)->get_cm($sub->cmid)->get_delegated_section_info();
            $gen->create_module('page', ['course' => $course->id, 'section' => $section->section]);
            $totals = progress::course_totals(get_fast_modinfo($course->id));
            // The page inside the subsection is counted; the subsection itself is not an activity.
            $this->assertSame(4, $totals['activities']);
        }
    }
}
