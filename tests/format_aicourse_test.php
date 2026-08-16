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
 * Unit tests for the AI Course Format helper functions.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/format/aicourse/lib.php');

use format_aicourse\local\icons;

/**
 * Unit tests for the AI Course Format helper functions.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\local\icons
 */
final class format_aicourse_test extends \advanced_testcase {
    /**
     * A section with no icon set must report an empty icon.
     */
    public function test_get_section_icon_defaults_to_empty(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);

        $this->assertSame('', icons::get_section_icon($course->id, $section->id));
    }

    /**
     * Setting an icon and reading it back must round trip, including overwriting and clearing.
     */
    public function test_set_and_get_section_icon_round_trip(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $section1 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        $section2 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 2]);

        // Set.
        $this->assertTrue(icons::set_section_icon($course->id, $section1->id, 'book-open'));
        $this->assertSame('book-open', icons::get_section_icon($course->id, $section1->id));

        // A different section is not affected.
        $this->assertSame('', icons::get_section_icon($course->id, $section2->id));

        // Overwrite. This also proves the in-process icon cache is invalidated on write.
        $this->assertTrue(icons::set_section_icon($course->id, $section1->id, 'hard-hat'));
        $this->assertSame('hard-hat', icons::get_section_icon($course->id, $section1->id));

        // Clear. The remove-icon button sends an empty string.
        $this->assertTrue(icons::set_section_icon($course->id, $section1->id, ''));
        $this->assertSame('', icons::get_section_icon($course->id, $section1->id));

        // Exactly one option row is kept for the section throughout.
        $this->assertEquals(1, $DB->count_records('course_format_options', [
            'courseid' => $course->id,
            'format' => 'aicourse',
            'name' => 'sectionicon_' . $section1->id,
        ]));
    }

    /**
     * An icon must not be settable on a section that belongs to a different course.
     */
    public function test_set_section_icon_rejects_foreign_section(): void {
        global $DB;

        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 2],
            ['createsections' => true]
        );
        $course2 = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 2],
            ['createsections' => true]
        );
        $foreignsection = $DB->get_record(
            'course_sections',
            ['course' => $course2->id, 'section' => 1]
        );

        $this->assertFalse(
            icons::set_section_icon($course1->id, $foreignsection->id, 'book-open')
        );
        $this->assertEquals(0, $DB->count_records('course_format_options', [
            'courseid' => $course1->id,
            'name' => 'sectionicon_' . $foreignsection->id,
        ]));
    }
}
