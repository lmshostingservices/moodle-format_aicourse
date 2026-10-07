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
 * Tests that the course index focus module is queued on the right pages (3.2.0).
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\hook;

use core\hook\output\before_standard_footer_html_generation;

/**
 * Tests that the course index focus module is queued on the right pages.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\hook\before_footer_html_generation
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_aicourse\hook\before_footer_html_generation::class)]
final class indexfocus_test extends \advanced_testcase {
    /**
     * Run the footer hook for the current $PAGE and return the queued JavaScript.
     *
     * @return string The end-of-page JS.
     */
    protected function run_hook(): string {
        global $PAGE;
        $hook = new before_standard_footer_html_generation($PAGE->get_renderer('core'));
        before_footer_html_generation::callback($hook);
        return $PAGE->requires->get_end_code();
    }

    /**
     * Build a course with a page in section 2.
     *
     * @return array [course, cm_info]
     */
    protected function make_course(): array {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 2]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        return [$course, get_fast_modinfo($course)->get_cm($page->cmid)];
    }

    /**
     * An activity page focuses the index on the activity's own section. This is the case the
     * empty($PAGE->cm) test used to skip, because moodle_page has no __isset().
     */
    public function test_activity_page_focuses_its_section(): void {
        global $PAGE;
        [$course, $cm] = $this->make_course();
        $PAGE->set_url('/mod/page/view.php', ['id' => $cm->id]);
        $PAGE->set_cm($cm, $course);
        $PAGE->set_pagetype('mod-page-view');

        $js = $this->run_hook();

        $this->assertStringContainsString('format_aicourse/indexfocus', $js);
        $this->assertMatchesRegularExpression('~format_aicourse/indexfocus[^;]*\b' . (int) $cm->section . '\b~', $js);
    }

    /**
     * A single-section page focuses that section.
     */
    public function test_section_page_focuses_that_section(): void {
        global $PAGE, $DB;
        [$course] = $this->make_course();
        $PAGE->set_url('/course/view.php', ['id' => $course->id, 'section' => 3]);
        $PAGE->set_course($course);
        $PAGE->set_pagetype('course-view-aicourse');

        $js = $this->run_hook();

        $sectionid = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 3]);
        $this->assertMatchesRegularExpression('~format_aicourse/indexfocus[^;]*\b' . (int) $sectionid . '\b~', $js);
    }

    /**
     * The course home page leaves the index alone.
     */
    public function test_course_home_does_not_focus(): void {
        global $PAGE;
        [$course] = $this->make_course();
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);
        $PAGE->set_pagetype('course-view-aicourse');

        $this->assertStringNotContainsString('format_aicourse/indexfocus', $this->run_hook());
    }

    /**
     * The section-by-id form of the section page (view.php?sectionid=N) focuses that section too.
     */
    public function test_sectionid_page_focuses_that_section(): void {
        global $PAGE, $DB;
        [$course] = $this->make_course();
        $sectionid = (int) $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 2]);
        $PAGE->set_url('/course/view.php', ['id' => $course->id, 'sectionid' => $sectionid]);
        $PAGE->set_course($course);
        $PAGE->set_pagetype('course-view-aicourse');

        $js = $this->run_hook();

        $this->assertMatchesRegularExpression('~format_aicourse/indexfocus[^;]*\b' . $sectionid . '\b~', $js);
    }

    /**
     * A course in another format is left alone.
     */
    public function test_other_formats_are_untouched(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(
            ['format' => 'topics', 'numsections' => 2],
            ['createsections' => true]
        );
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);
        $PAGE->set_url('/mod/page/view.php', ['id' => $cm->id]);
        $PAGE->set_cm($cm, $course);
        $PAGE->set_pagetype('mod-page-view');

        $this->assertStringNotContainsString('format_aicourse/indexfocus', $this->run_hook());
    }
}
