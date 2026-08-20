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
 * Tests for the format_aicourse_correct_chat external function.
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
 * Tests for the format_aicourse_correct_chat external function.
 *
 * SECURITY REGRESSION COVER. The action this function replaces was gated on
 * moodle/course:viewparticipants, which Moodle grants to the student archetype by default, so any
 * enrolled student could forge a teacher correction on any classmate's AI Tutor answer. The gate
 * is now format/aicourse:viewreport. test_execute_refuses_a_student() is the regression test for
 * that fix and must never be weakened.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\external\correct_chat
 */
final class correct_chat_test extends external_testcase {
    /**
     * Store one chat row owned by the fixture student.
     *
     * @param int|null $courseid Course the row belongs to, defaulting to the fixture course.
     * @return int Id of the stored row.
     */
    protected function create_chat(?int $courseid = null): int {
        global $DB;

        $record = (object) [
            'courseid' => $courseid ?? $this->course->id,
            'userid' => $this->student->id,
            'activityid' => 0,
            'question' => 'What is a hazard?',
            'response' => 'Think about what could cause harm.',
            'rating' => 0,
            'refused' => 0,
            'locked' => 0,
            'timecreated' => time(),
        ];

        return (int) $DB->insert_record('format_aicourse_chats', $record);
    }

    /**
     * A teacher can write a correction, and it is attributed to them.
     */
    public function test_execute_stores_the_correction(): void {
        global $DB;

        $chatid = $this->create_chat();
        $this->setUser($this->teacher);

        $result = correct_chat::execute($this->course->id, $chatid, 'A hazard is a source of harm.');
        $result = external_api::clean_returnvalue(correct_chat::execute_returns(), $result);

        $this->assertTrue($result['status']);
        $stored = $DB->get_record('format_aicourse_chats', ['id' => $chatid], '*', MUST_EXIST);
        $this->assertSame('A hazard is a source of harm.', $stored->correction);
        $this->assertEquals($this->teacher->id, $stored->correctedby);
        $this->assertNotEmpty($stored->timecorrected);
    }

    /**
     * SECURITY REGRESSION: a student must never be able to forge a teacher correction.
     *
     * A student holds moodle/course:viewparticipants, the capability the old ajax.php action was
     * gated on. They do not hold format/aicourse:viewreport, which is what this function requires.
     */
    public function test_execute_refuses_a_student(): void {
        global $DB;

        $chatid = $this->create_chat();
        $this->setUser($this->student);

        // The student really does hold the capability the vulnerable version checked.
        $this->assertTrue(has_capability('moodle/course:viewparticipants', $this->context, $this->student));
        // And really does not hold the one this function checks.
        $this->assertFalse(has_capability('format/aicourse:viewreport', $this->context, $this->student));

        try {
            correct_chat::execute($this->course->id, $chatid, 'I mark my own homework.');
            $this->fail('A student was allowed to correct a chat response.');
        } catch (\required_capability_exception $e) {
            // The exception reports the human readable name of the capability it checked.
            $this->assertSame(get_capability_string('format/aicourse:viewreport'), $e->a);
        }

        $stored = $DB->get_record('format_aicourse_chats', ['id' => $chatid], '*', MUST_EXIST);
        $this->assertNull($stored->correction);
        $this->assertNull($stored->correctedby);
    }

    /**
     * A teacher who can read the report but has had format/aicourse:correctresponses revoked
     * cannot write a correction.
     *
     * ACF-FIX-2.1.4: format/aicourse:correctresponses was declared in db/access.php with
     * RISK_XSS | RISK_PERSONAL but never enforced anywhere, so it was an orphan. This function
     * now requires it in addition to format/aicourse:viewreport. Both capabilities default to
     * the same archetypes, so a stock site is unaffected; this test covers the case a site
     * creates deliberately, namely read-only access to the report.
     */
    public function test_execute_refuses_a_reader_without_correctresponses(): void {
        global $DB;

        $chatid = $this->create_chat();
        $this->prohibit_capability('format/aicourse:correctresponses', 'editingteacher');
        $this->setUser($this->teacher);

        // The teacher can still read the report.
        $this->assertTrue(has_capability('format/aicourse:viewreport', $this->context, $this->teacher));
        // But may no longer write onto it.
        $this->assertFalse(
            has_capability('format/aicourse:correctresponses', $this->context, $this->teacher)
        );

        try {
            correct_chat::execute($this->course->id, $chatid, 'Read only, so this must not land.');
            $this->fail('A user without correctresponses was allowed to correct a chat response.');
        } catch (\required_capability_exception $e) {
            $this->assertSame(get_capability_string('format/aicourse:correctresponses'), $e->a);
        }

        $stored = $DB->get_record('format_aicourse_chats', ['id' => $chatid], '*', MUST_EXIST);
        $this->assertNull($stored->correction);
        $this->assertNull($stored->correctedby);
    }

    /**
     * A chat row belonging to another course cannot be reached.
     */
    public function test_execute_refuses_a_chat_from_another_course(): void {
        $othercourse = $this->getDataGenerator()->create_course(['format' => 'aicourse']);
        $chatid = $this->create_chat((int) $othercourse->id);

        $this->setUser($this->teacher);

        $this->assert_throws_errorcode('error_chatnotfound', function () use ($chatid): void {
            correct_chat::execute($this->course->id, $chatid, 'Not mine to correct.');
        });
    }

    /**
     * A chatid that is not an integer is rejected before any code runs.
     */
    public function test_execute_rejects_invalid_parameters(): void {
        $this->setUser($this->teacher);

        $this->assert_call_fails('invalidparameter', 'format_aicourse_correct_chat', [
            'courseid' => $this->course->id,
            'chatid' => 'not-a-number',
            'correction' => 'Anything.',
        ]);
    }
}
