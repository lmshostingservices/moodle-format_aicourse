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
 * Tests for the format_aicourse_save_icon external function.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\external;

use core_external\external_api;
use format_aicourse\local\icons;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/format/aicourse/tests/external/external_testcase.php');

/**
 * Tests for the format_aicourse_save_icon external function.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\external\save_icon
 */
final class save_icon_test extends external_testcase {
    /**
     * The id of section 1 of the fixture course.
     *
     * @return int Section id.
     */
    protected function get_section_id(): int {
        global $DB;

        return (int) $DB->get_field(
            'course_sections',
            'id',
            ['course' => $this->course->id, 'section' => 1],
            MUST_EXIST
        );
    }

    /**
     * An editing teacher can set and then clear a section icon.
     */
    public function test_execute_sets_and_clears_the_icon(): void {
        $this->setUser($this->teacher);
        $sectionid = $this->get_section_id();
        $iconkey = array_key_first(icons::get_library());

        $result = save_icon::execute($this->course->id, $sectionid, $iconkey);
        $result = external_api::clean_returnvalue(save_icon::execute_returns(), $result);

        $this->assertTrue($result['status']);
        $this->assertSame($iconkey, icons::get_section_icon($this->course->id, $sectionid));

        save_icon::execute($this->course->id, $sectionid, '');
        $this->assertSame('', icons::get_section_icon($this->course->id, $sectionid));
    }

    /**
     * A student cannot rewrite a section icon.
     */
    public function test_execute_requires_capability(): void {
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        save_icon::execute($this->course->id, $this->get_section_id(), 'rocket');
    }

    /**
     * A section belonging to another course is refused even for a teacher of this one.
     */
    public function test_execute_rejects_a_section_from_another_course(): void {
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

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/not valid/');
        save_icon::execute($this->course->id, $othersection, 'rocket');
    }

    /**
     * An icon key that is not in the library is refused.
     */
    public function test_execute_rejects_an_unknown_icon(): void {
        $this->setUser($this->teacher);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/not available/');
        save_icon::execute($this->course->id, $this->get_section_id(), 'definitelynotanicon');
    }

    /**
     * An icon key with characters outside PARAM_ALPHANUMEXT is rejected by the parameter check.
     */
    public function test_execute_rejects_invalid_parameters(): void {
        $this->setUser($this->teacher);

        $this->assert_call_fails('invalidparameter', 'format_aicourse_save_icon', [
            'courseid' => $this->course->id,
            'sectionid' => $this->get_section_id(),
            'icon' => '<script>alert(1)</script>',
        ]);
    }
}
