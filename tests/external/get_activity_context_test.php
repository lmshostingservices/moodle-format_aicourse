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
 * Tests for the format_aicourse_get_activity_context external function.
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
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Tests for the format_aicourse_get_activity_context external function.
 *
 * SECURITY REGRESSION COVER. The action this function replaces had no capability check and no
 * visibility check, and it shipped answer options annotated ' [CORRECT]', per-answer explanations
 * and marking criteria straight to the browser as JSON. The tests below assert that a hidden
 * activity is refused and that no correctness marker can ever appear in the payload.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\external\get_activity_context
 */
final class get_activity_context_test extends external_testcase {
    /**
     * Build a quiz in the fixture course with one multiple choice question.
     *
     * @param array $options Extra module options, e.g. ['visible' => 0].
     * @return \stdClass The quiz activity record, with cmid.
     */
    protected function create_quiz(array $options = []): \stdClass {
        $quiz = $this->getDataGenerator()->create_module('quiz', array_merge([
            'course' => $this->course->id,
            'name' => 'Hazard identification quiz',
            'intro' => '<p>Answer every question.</p>',
            'introformat' => FORMAT_HTML,
        ], $options));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category([
            'contextid' => \context_module::instance($quiz->cmid)->id,
        ]);
        $question = $questiongenerator->create_question('multichoice', 'one_of_four', [
            'category' => $category->id,
            'name' => 'Hazard question',
            'questiontext' => ['text' => '<p>Which of these is a hazard?</p>', 'format' => FORMAT_HTML],
        ]);
        quiz_add_quiz_question($question->id, $quiz);

        return $quiz;
    }

    /**
     * A learner gets the activity intro and the bare question prompts.
     */
    public function test_execute_returns_the_public_context(): void {
        $quiz = $this->create_quiz();
        $this->setUser($this->student);

        $result = get_activity_context::execute($this->course->id, $quiz->cmid, 1);
        $result = external_api::clean_returnvalue(get_activity_context::execute_returns(), $result);

        $this->assertSame('quiz', $result['context']['type']);
        $this->assertSame('Hazard identification quiz', $result['context']['name']);
        $this->assertStringContainsString('Answer every question.', $result['context']['intro']);
        $this->assertCount(1, $result['context']['questions']);
        $this->assertSame(1, $result['context']['questions'][0]['slot']);
        $this->assertStringContainsString(
            'Which of these is a hazard?',
            $result['context']['questions'][0]['text']
        );
        $this->assertSame(1, $result['context']['currentquestion']['slot']);
    }

    /**
     * SECURITY REGRESSION: no correctness marker, explanation or feedback may reach the browser.
     */
    public function test_execute_never_returns_a_correct_marker(): void {
        $quiz = $this->create_quiz();
        $this->setUser($this->student);

        $result = get_activity_context::execute($this->course->id, $quiz->cmid, 1);
        $result = external_api::clean_returnvalue(get_activity_context::execute_returns(), $result);
        $payload = json_encode($result);

        $this->assertStringNotContainsString('[CORRECT]', $payload);
        $this->assertStringNotContainsString('[CORRECT:', $payload);
        $this->assertStringNotContainsString('[EXPLANATION:', $payload);
        $this->assertStringNotContainsString('[ANSWER:', $payload);
        // The generated question's correct answer is 'One'; it must not be in the payload either.
        $this->assertArrayNotHasKey('answers', $result['context']['questions'][0]);
        $this->assertSame(['slot', 'text', 'type'], array_keys($result['context']['questions'][0]));
    }

    /**
     * SECURITY REGRESSION: a hidden activity must not leak its intro or questions.
     */
    public function test_execute_refuses_an_activity_the_student_cannot_see(): void {
        $quiz = $this->create_quiz(['visible' => 0]);

        // The teacher can still see it.
        $this->setUser($this->teacher);
        $teacherresult = get_activity_context::execute($this->course->id, $quiz->cmid, 0);
        $this->assertSame('quiz', $teacherresult['context']['type']);

        // The student must not.
        $this->setUser($this->student);
        $this->assert_throws_errorcode('error_activitynotvisible', function () use ($quiz): void {
            get_activity_context::execute($this->course->id, $quiz->cmid, 0);
        });
    }

    /**
     * An activity id that is not in this course is not found.
     */
    public function test_execute_refuses_an_unknown_activity(): void {
        $this->setUser($this->student);

        $this->assert_throws_errorcode('error_activitynotfound', function (): void {
            get_activity_context::execute($this->course->id, 999999, 0);
        });
    }

    /**
     * A user without format/aicourse:useaitutor is refused.
     */
    public function test_execute_requires_capability(): void {
        $quiz = $this->create_quiz();
        $this->prohibit_capability('format/aicourse:useaitutor', 'student');
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        get_activity_context::execute($this->course->id, $quiz->cmid, 0);
    }

    /**
     * An activityid that is not an integer is rejected before any code runs.
     */
    public function test_execute_rejects_invalid_parameters(): void {
        $this->setUser($this->student);

        $this->assert_call_fails('invalidparameter', 'format_aicourse_get_activity_context', [
            'courseid' => $this->course->id,
            'activityid' => 'not-a-number',
        ]);
    }
}
