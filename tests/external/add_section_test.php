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
 * Tests for the format_aicourse_add_section external function.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\external;

use core_external\external_api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/format/aicourse/tests/external/external_testcase.php');

/**
 * Tests for the format_aicourse_add_section external function.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\external\add_section
 */
final class add_section_test extends external_testcase {
    /**
     * A new section is appended after the last existing one, never at slot 0.
     */
    public function test_execute_appends_a_section(): void {
        global $DB;

        $this->setUser($this->teacher);
        $before = (int) $DB->get_field_sql(
            'SELECT MAX(section) FROM {course_sections} WHERE course = ?',
            [$this->course->id]
        );

        $result = add_section::execute($this->course->id);
        $result = external_api::clean_returnvalue(add_section::execute_returns(), $result);

        $section = $DB->get_record('course_sections', ['id' => $result['sectionid']], '*', MUST_EXIST);
        $this->assertEquals($this->course->id, $section->course);
        $this->assertSame($before + 1, (int) $section->section);
    }

    /**
     * A student cannot add a section.
     */
    public function test_execute_requires_capability(): void {
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        add_section::execute($this->course->id);
    }

    /**
     * A courseid that is not an integer is rejected before any code runs.
     */
    public function test_execute_rejects_invalid_parameters(): void {
        $this->setUser($this->teacher);

        $this->assert_call_fails('invalidparameter', 'format_aicourse_add_section', [
            'courseid' => 'not-a-number',
        ]);
    }
}
