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
 * Tests for the format_aicourse_db_diagnostic external function.
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
 * Tests for the format_aicourse_db_diagnostic external function.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\external\db_diagnostic
 */
final class db_diagnostic_test extends external_testcase {
    /**
     * On a correctly installed site both tables exist and nothing is missing.
     */
    public function test_execute_reports_a_healthy_schema(): void {
        $this->setUser($this->teacher);

        $result = db_diagnostic::execute($this->course->id);
        $result = external_api::clean_returnvalue(db_diagnostic::execute_returns(), $result);

        $this->assertTrue($result['chatstableexists']);
        $this->assertTrue($result['memorytableexists']);
        $this->assertSame([], $result['missingchatcolumns']);
        $this->assertSame([], $result['missingmemorycolumns']);
        $this->assertSame([], $result['errors']);
        $this->assertContains('questionslot', $result['chatscolumns']);
        $this->assertNotEmpty($result['pluginversion']);
    }

    /**
     * A student cannot read the plugin's schema state.
     */
    public function test_execute_requires_capability(): void {
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        db_diagnostic::execute($this->course->id);
    }

    /**
     * The function is deliberately not exposed to browser JavaScript.
     */
    public function test_function_is_not_available_over_ajax(): void {
        $this->setUser($this->teacher);

        $this->assert_call_fails('servicenotavailable', 'format_aicourse_db_diagnostic', [
            'courseid' => $this->course->id,
        ]);
    }

    /**
     * A courseid that is not an integer is rejected.
     */
    public function test_execute_rejects_invalid_parameters(): void {
        $this->setUser($this->teacher);

        $this->expectException(\invalid_parameter_exception::class);
        external_api::validate_parameters(db_diagnostic::execute_parameters(), [
            'courseid' => 'not-a-number',
        ]);
    }
}
