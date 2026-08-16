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
 * Tests for the format_aicourse_get_progress external function.
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
 * Tests for the format_aicourse_get_progress external function.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\external\get_progress
 */
final class get_progress_test extends external_testcase {
    /**
     * A learner gets their own progress figures back.
     */
    public function test_execute_returns_progress(): void {
        $this->setUser($this->student);

        $result = get_progress::execute($this->course->id);
        $result = external_api::clean_returnvalue(get_progress::execute_returns(), $result);

        $this->assertArrayHasKey('percentage', $result);
        $this->assertArrayHasKey('completed', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('enabled', $result);
        $this->assertIsInt($result['percentage']);
        $this->assertGreaterThanOrEqual(0, $result['percentage']);
        $this->assertLessThanOrEqual(100, $result['percentage']);
    }

    /**
     * A user without format/aicourse:view is refused.
     */
    public function test_execute_requires_capability(): void {
        $this->prohibit_capability('format/aicourse:view', 'student');
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        get_progress::execute($this->course->id);
    }

    /**
     * A user who is not enrolled at all cannot reach the course context.
     */
    public function test_execute_requires_enrolment(): void {
        $this->setUser($this->outsider);

        $this->expectException(\require_login_exception::class);
        get_progress::execute($this->course->id);
    }

    /**
     * A courseid that is not an integer is rejected before any code runs.
     */
    public function test_execute_rejects_invalid_parameters(): void {
        $this->setUser($this->student);

        $this->assert_call_fails('invalidparameter', 'format_aicourse_get_progress', [
            'courseid' => 'not-a-number',
        ]);
    }
}
