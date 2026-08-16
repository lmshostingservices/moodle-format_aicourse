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
 * Tests for the format_aicourse_rate_chat external function.
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
 * Tests for the format_aicourse_rate_chat external function.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\external\rate_chat
 */
final class rate_chat_test extends external_testcase {
    /**
     * Store one chat row.
     *
     * @param int $userid Owner of the conversation.
     * @return int Id of the stored row.
     */
    protected function create_chat(int $userid): int {
        global $DB;

        return (int) $DB->insert_record('format_aicourse_chats', (object) [
            'courseid' => $this->course->id,
            'userid' => $userid,
            'activityid' => 0,
            'question' => 'What is a hazard?',
            'response' => 'Think about what could cause harm.',
            'rating' => 0,
            'refused' => 0,
            'locked' => 0,
            'timecreated' => time(),
        ]);
    }

    /**
     * A learner can rate their own answer.
     */
    public function test_execute_stores_the_rating(): void {
        global $DB;

        $chatid = $this->create_chat((int) $this->student->id);
        $this->setUser($this->student);

        $result = rate_chat::execute($this->course->id, $chatid, 1);
        $result = external_api::clean_returnvalue(rate_chat::execute_returns(), $result);

        $this->assertTrue($result['status']);
        $this->assertEquals(1, $DB->get_field('format_aicourse_chats', 'rating', ['id' => $chatid]));
    }

    /**
     * A learner cannot rate somebody else's conversation.
     */
    public function test_execute_refuses_another_users_chat(): void {
        global $DB;

        $chatid = $this->create_chat((int) $this->teacher->id);
        $this->setUser($this->student);

        try {
            rate_chat::execute($this->course->id, $chatid, -1);
            $this->fail('A user was allowed to rate another user\'s chat.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('could not be found', $e->getMessage());
        }

        $this->assertEquals(0, $DB->get_field('format_aicourse_chats', 'rating', ['id' => $chatid]));
    }

    /**
     * Only 1 and -1 are accepted as ratings.
     */
    public function test_execute_refuses_an_out_of_range_rating(): void {
        $chatid = $this->create_chat((int) $this->student->id);
        $this->setUser($this->student);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/not valid/');
        rate_chat::execute($this->course->id, $chatid, 5);
    }

    /**
     * A user without format/aicourse:useaitutor is refused.
     */
    public function test_execute_requires_capability(): void {
        $chatid = $this->create_chat((int) $this->student->id);
        $this->prohibit_capability('format/aicourse:useaitutor', 'student');
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        rate_chat::execute($this->course->id, $chatid, 1);
    }

    /**
     * A rating that is not an integer is rejected before any code runs.
     */
    public function test_execute_rejects_invalid_parameters(): void {
        $chatid = $this->create_chat((int) $this->student->id);
        $this->setUser($this->student);

        $this->assert_call_fails('invalidparameter', 'format_aicourse_rate_chat', [
            'courseid' => $this->course->id,
            'chatid' => $chatid,
            'rating' => 'thumbsup',
        ]);
    }
}
