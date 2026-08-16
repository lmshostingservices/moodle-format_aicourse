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
 * Tests for the format_aicourse_ai_chat external function.
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
 * Tests for the format_aicourse_ai_chat external function.
 *
 * Everything except the remote HTTP call is covered. The one branch that returns an answer
 * without contacting lms-labs.com is the post-submission lockout, so that is the happy path used
 * here; every other test asserts a guard that fires before the network is touched.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\external\ai_chat
 */
final class ai_chat_test extends external_testcase {
    /**
     * Create an assignment in the fixture course and mark it submitted for the student.
     *
     * @return \stdClass The assign activity record, with cmid.
     */
    protected function create_submitted_assignment(): \stdClass {
        global $DB;

        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
            'name' => 'Workplace report',
        ]);

        $DB->insert_record('assign_submission', (object) [
            'assignment' => $assign->id,
            'userid' => $this->student->id,
            'timecreated' => time(),
            'timemodified' => time(),
            'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            'groupid' => 0,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);

        return $assign;
    }

    /**
     * Once the assignment is submitted the tutor answers with the reflection-only message and
     * logs the exchange, without contacting the remote service.
     */
    public function test_execute_returns_the_locked_answer_after_submission(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $assign = $this->create_submitted_assignment();
        $this->set_fake_credentials();
        $this->setUser($this->student);

        $result = ai_chat::execute($this->course->id, 'Can you write my report?', $assign->cmid);
        $result = external_api::clean_returnvalue(ai_chat::execute_returns(), $result);

        $this->assertSame(get_string('aiassistant_locked', 'format_aicourse'), $result['answer']);
        $this->assertGreaterThan(0, $result['chatid']);
        $this->assertSame([], $result['warnings']);

        $logged = $DB->get_record('format_aicourse_chats', ['id' => $result['chatid']], '*', MUST_EXIST);
        $this->assertEquals(1, $logged->locked);
        $this->assertEquals($this->student->id, $logged->userid);
    }

    /**
     * A user without format/aicourse:useaitutor is refused.
     */
    public function test_execute_requires_capability(): void {
        $this->prohibit_capability('format/aicourse:useaitutor', 'student');
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        ai_chat::execute($this->course->id, 'What is a hazard?');
    }

    /**
     * Guests must never spend API credits.
     */
    public function test_execute_refuses_guests(): void {
        global $DB;

        $guestrole = $DB->get_field('role', 'id', ['shortname' => 'guest'], MUST_EXIST);
        assign_capability('format/aicourse:useaitutor', CAP_ALLOW, $guestrole, $this->context->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        // Let a guest into the course, so the guard being tested is the one that fires.
        $instance = $DB->get_record(
            'enrol',
            ['courseid' => $this->course->id, 'enrol' => 'guest'],
            '*',
            MUST_EXIST
        );
        enrol_get_plugin('guest')->update_status($instance, ENROL_INSTANCE_ENABLED);

        $this->setGuestUser();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/Guest users cannot use the AI Tutor/');
        ai_chat::execute($this->course->id, 'What is a hazard?');
    }

    /**
     * An empty question is refused before the remote service is called.
     */
    public function test_execute_refuses_an_empty_question(): void {
        $this->setUser($this->student);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/enter a question/');
        ai_chat::execute($this->course->id, '   ');
    }

    /**
     * A site without credentials says so rather than making a doomed HTTP call.
     */
    public function test_execute_requires_configuration(): void {
        set_config('siteid', '', 'format_aicourse');
        set_config('apikey', '', 'format_aicourse');
        $this->setUser($this->student);

        $this->expectException(\moodle_exception::class);
        ai_chat::execute($this->course->id, 'What is a hazard?');
    }

    /**
     * The paid remote call stays rate limited: the eleventh question in the window is refused.
     */
    public function test_execute_is_rate_limited(): void {
        $this->setUser($this->student);
        // No credentials, so every allowed call fails at the configuration check instead of
        // reaching the network; the throttle runs before that check.
        set_config('siteid', '', 'format_aicourse');

        for ($i = 0; $i < throttle::AICHAT_MAX; $i++) {
            try {
                ai_chat::execute($this->course->id, 'Question ' . $i);
                $this->fail('Expected the configuration check to reject this call.');
            } catch (\moodle_exception $e) {
                $this->assertSame('aiassistant_notconfigured', $e->errorcode);
            }
        }

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/too many requests/');
        ai_chat::execute($this->course->id, 'One question too many');
    }

    /**
     * A question that is not a string is rejected before any code runs.
     */
    public function test_execute_rejects_invalid_parameters(): void {
        $this->setUser($this->student);

        $this->assert_call_fails('invalidparameter', 'format_aicourse_ai_chat', [
            'courseid' => $this->course->id,
            'question' => 'What is a hazard?',
            'activityid' => 'not-a-number',
        ]);
    }
}
