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
 * Shared fixture for the format_aicourse external function tests.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\external;

/**
 * Shared fixture for the format_aicourse external function tests.
 *
 * Provides a course in the AI Course Format with an editing teacher, a non-editing teacher, a
 * student and a user enrolled in nothing, plus helpers for capability manipulation and for
 * driving a function the way lib/ajax/service.php does.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class external_testcase extends \advanced_testcase {
    /** @var \stdClass Course using the AI Course Format. */
    protected $course;

    /** @var \context_course Context of the course. */
    protected $context;

    /** @var \stdClass An editing teacher in the course. */
    protected $teacher;

    /** @var \stdClass A student in the course. */
    protected $student;

    /** @var \stdClass A user enrolled in nothing. */
    protected $outsider;

    /**
     * Build the shared fixture.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $this->context = \context_course::instance($this->course->id);
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->outsider = $this->getDataGenerator()->create_user();
    }

    /**
     * Prohibit a capability for an archetype role inside the fixture course.
     *
     * @param string $capability Capability name.
     * @param string $shortname Role shortname, e.g. 'student'.
     * @return void
     */
    protected function prohibit_capability(string $capability, string $shortname): void {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
        assign_capability($capability, CAP_PROHIBIT, $roleid, $this->context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * Call an external function the way lib/ajax/service.php does, so parameter validation, the
     * 'ajax' => true flag and the session key check all run.
     *
     * @param string $function Name of the external function.
     * @param array $args Raw arguments, exactly as a browser would send them.
     * @return array The ['error' => bool, 'data' => mixed, 'exception' => stdClass] response.
     */
    protected function call_function(string $function, array $args): array {
        global $USER;

        // Moodle's call_external_function() invokes require_sesskey() for any function with
        // loginrequired set, and confirm_sesskey() reads the key from the request. Rather than
        // forging a request, use the bypass Moodle provides for exactly this situation:
        // confirm_sesskey() returns true immediately when $USER->ignoresesskey is set (see
        // lib/sessionlib.php). That is the same mechanism the web service layer itself uses, so
        // no superglobal is touched and the rest of the call path is unchanged.
        $previous = $USER->ignoresesskey ?? null;
        $USER->ignoresesskey = true;
        try {
            return \core_external\external_api::call_external_function($function, $args, true);
        } finally {
            if ($previous === null) {
                unset($USER->ignoresesskey);
            } else {
                $USER->ignoresesskey = $previous;
            }
        }
    }

    /**
     * Assert that calling an external function over AJAX fails with a given error code.
     *
     * @param string $expectederrorcode The errorcode the client should receive.
     * @param string $function Name of the external function.
     * @param array $args Raw arguments, exactly as a browser would send them.
     * @return void
     */
    protected function assert_call_fails(string $expectederrorcode, string $function, array $args): void {
        $result = $this->call_function($function, $args);

        $this->assertTrue($result['error'], 'Expected ' . $function . ' to fail.');
        $this->assertSame($expectederrorcode, $result['exception']->errorcode);
    }

    /**
     * Fake the LMS-Labs credentials so a function gets past its configuration check.
     *
     * @return void
     */
    protected function set_fake_credentials(): void {
        set_config('siteid', 'https://example.invalid', 'format_aicourse');
        set_config('apikey', 'test-api-key', 'format_aicourse');
    }
}
