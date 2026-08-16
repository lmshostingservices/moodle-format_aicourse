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
 * Tests for the format_aicourse_db_repair external function.
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
 * Tests for the format_aicourse_db_repair external function.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\external\db_repair
 */
final class db_repair_test extends external_testcase {
    /**
     * On a correctly installed site there is nothing to repair.
     */
    public function test_execute_reports_nothing_to_do(): void {
        $this->setAdminUser();

        $result = db_repair::execute();
        $result = external_api::clean_returnvalue(db_repair::execute_returns(), $result);

        $this->assertSame([], $result['repairs']);
        $this->assertSame(get_string('dbrepair_norepairs', 'format_aicourse'), $result['message']);
    }

    /**
     * A missing column is recreated.
     */
    public function test_execute_restores_a_missing_column(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table('format_aicourse_chats');
        $field = new \xmldb_field('locked', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'refused');
        $dbman->drop_field($table, $field);
        $this->assertFalse($dbman->field_exists($table, $field));

        $this->setAdminUser();
        $result = db_repair::execute();
        $result = external_api::clean_returnvalue(db_repair::execute_returns(), $result);

        $this->assertContains('Added locked column', $result['repairs']);
        $this->assertTrue($dbman->field_exists($table, $field));
    }

    /**
     * An editing teacher is not a site administrator and cannot touch the schema.
     */
    public function test_execute_requires_capability(): void {
        $this->setUser($this->teacher);

        $this->expectException(\required_capability_exception::class);
        db_repair::execute();
    }

    /**
     * The function is deliberately not exposed to browser JavaScript.
     */
    public function test_function_is_not_available_over_ajax(): void {
        $this->setAdminUser();

        $this->assert_call_fails('servicenotavailable', 'format_aicourse_db_repair', []);
    }

    /**
     * The function declares no parameters, so an unexpected one is rejected.
     */
    public function test_execute_rejects_invalid_parameters(): void {
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        external_api::validate_parameters(db_repair::execute_parameters(), ['unexpected' => 1]);
    }
}
