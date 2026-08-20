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
 * Tests for the format_aicourse_delete_section external function.
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
 * Tests for the format_aicourse_delete_section external function.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\external\delete_section
 */
final class delete_section_test extends external_testcase {
    /**
     * The id of a section of the fixture course.
     *
     * @param int $number Section number.
     * @return int Section id.
     */
    protected function get_section_id(int $number): int {
        global $DB;

        return (int) $DB->get_field(
            'course_sections',
            'id',
            ['course' => $this->course->id, 'section' => $number],
            MUST_EXIST
        );
    }

    /**
     * A section and its activities are removed.
     */
    public function test_execute_deletes_a_section(): void {
        global $DB;

        $module = $this->getDataGenerator()->create_module(
            'page',
            ['course' => $this->course->id, 'section' => 1]
        );
        $sectionid = $this->get_section_id(1);

        $this->setUser($this->teacher);
        $result = delete_section::execute($this->course->id, $sectionid);
        $result = external_api::clean_returnvalue(delete_section::execute_returns(), $result);

        $this->assertTrue($result['status']);
        $this->assertFalse($DB->record_exists('course_sections', ['id' => $sectionid]));
        $this->assertFalse($DB->record_exists('course_modules', ['id' => $module->cmid]));
    }

    /**
     * The General section can never be deleted.
     */
    public function test_execute_refuses_the_general_section(): void {
        $this->setUser($this->teacher);

        $this->assert_throws_errorcode('error_cannotdeletegeneral', function (): void {
            delete_section::execute($this->course->id, $this->get_section_id(0));
        });
    }

    /**
     * A section id that does not belong to this course is not found.
     */
    public function test_execute_refuses_a_section_from_another_course(): void {
        global $DB;

        $othercourse = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 2],
            ['createsections' => true]
        );
        $othersection = (int) $DB->get_field(
            'course_sections',
            'id',
            ['course' => $othercourse->id, 'section' => 1],
            MUST_EXIST
        );

        $this->setUser($this->teacher);

        $this->assert_throws_errorcode('error_sectionnotfound', function () use ($othersection): void {
            delete_section::execute($this->course->id, $othersection);
        });
    }

    /**
     * A student cannot delete a section.
     */
    public function test_execute_requires_capability(): void {
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        delete_section::execute($this->course->id, $this->get_section_id(1));
    }

    /**
     * A sectionid that is not an integer is rejected before any code runs.
     */
    public function test_execute_rejects_invalid_parameters(): void {
        $this->setUser($this->teacher);

        $this->assert_call_fails('invalidparameter', 'format_aicourse_delete_section', [
            'courseid' => $this->course->id,
            'sectionid' => 'not-a-number',
        ]);
    }
}
