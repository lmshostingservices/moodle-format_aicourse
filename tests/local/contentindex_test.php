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
 * Tests for the AI Tutor course content index.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\local;

/**
 * Tests for the AI Tutor course content index.
 *
 * The index built here is transmitted to an external AI service, so what it may and may not
 * contain is a security property, not a formatting preference.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\local\contentindex
 */
final class contentindex_test extends \advanced_testcase {
    /**
     * Empty the in-request index cache before every test.
     *
     * It is a private static, so resetAfterTest() cannot reach it and entries built by one test
     * would otherwise still be there when the next one counted them.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->clear_request_cache();
    }

    /**
     * Empty contentindex's in-request index cache.
     *
     * @return void
     */
    protected function clear_request_cache(): void {
        $property = (new \ReflectionClass(contentindex::class))->getProperty('requestcache');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    /**
     * Build a course whose own answer-sharing option is set to a known value.
     *
     * @param int $courseoptin 1 to opt the course in, 0 to leave it opted out.
     * @return \stdClass The course record.
     */
    protected function make_course(int $courseoptin): \stdClass {
        $course = $this->getDataGenerator()->create_course([
            'format' => 'aicourse',
            'shareassessmentanswers' => $courseoptin,
        ]);

        // The option has to be genuinely stored for the matrix below to mean anything: a fixture
        // that silently failed to record the opt in would make every "does not share" assertion
        // pass for the wrong reason.
        $this->assertEquals(
            $courseoptin,
            course_get_format($course)->get_format_options()['shareassessmentanswers'],
            'The per-course option was not stored, so this fixture proves nothing.'
        );

        return $course;
    }

    /**
     * Add a quiz to a course holding one multiple choice question and one essay question.
     *
     * The multiple choice question gets a distinctive correct option, a distinctive wrong option
     * and per-option feedback; the essay gets an "information for graders" marking guide. Between
     * them they cover every branch of the index builder that the answer-sharing gate controls.
     *
     * @param \stdClass $course The course to add the quiz to.
     * @return void
     */
    protected function add_assessment(\stdClass $course): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $generator = $this->getDataGenerator();
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'name' => 'Assessment']);

        $qgenerator = $generator->get_plugin_generator('core_question');
        $category = $qgenerator->create_question_category([
            'contextid' => \context_module::instance($quiz->cmid)->id,
        ]);

        $multichoice = $qgenerator->create_question('multichoice', 'one_of_four', [
            'category' => $category->id,
            'questiontext' => ['text' => 'What is the capital of France?', 'format' => FORMAT_HTML],
        ]);
        $index = 0;
        foreach ($DB->get_records('question_answers', ['question' => $multichoice->id], 'id ASC') as $answer) {
            $answer->answer = ($index === 0) ? 'ZEBRAPARIS' : ('WRONGOPTION' . $index);
            $answer->fraction = ($index === 0) ? 1.0 : 0.0;
            $answer->feedback = ($index === 0) ? 'FEEDBACKRIGHT' : 'FEEDBACKWRONG';
            $DB->update_record('question_answers', $answer);
            $index++;
        }

        $essay = $qgenerator->create_question('essay', null, [
            'category' => $category->id,
            'questiontext' => ['text' => 'Discuss the Seine.', 'format' => FORMAT_HTML],
        ]);
        $DB->set_field('qtype_essay_options', 'graderinfo', 'GRADERSECRET', ['questionid' => $essay->id]);

        $quizrecord = $DB->get_record('quiz', ['id' => $quiz->id]);
        quiz_add_quiz_question($multichoice->id, $quizrecord);
        quiz_add_quiz_question($essay->id, $quizrecord);

        rebuild_course_cache($course->id, true);
    }

    /**
     * The answer-sharing gate defaults to off on a site that has never configured it.
     */
    public function test_answer_sharing_is_off_by_default(): void {
        $this->resetAfterTest();

        $this->assertFalse(
            contentindex::may_share_assessment_answers(),
            'Assessment answer keys must never be shared with the external AI service by default.'
        );
    }

    /**
     * The gate follows the site setting in both directions.
     */
    public function test_answer_sharing_follows_the_site_setting(): void {
        $this->resetAfterTest();

        set_config('shareassessmentanswers', 1, 'format_aicourse');
        $this->assertTrue(contentindex::may_share_assessment_answers());

        set_config('shareassessmentanswers', 0, 'format_aicourse');
        $this->assertFalse(contentindex::may_share_assessment_answers());
    }

    /**
     * Every site/course combination and the value it must resolve to.
     *
     * Deliberately a plain helper rather than a PHPUnit data provider: a provider has to be
     * declared with doc-comment metadata, which PHPUnit 11 on Moodle 5 reports as deprecated,
     * and no other file in this plugin's suite carries doc-comment metadata beyond the per-file
     * coverage declaration. Looping still names the row in every failure message.
     *
     * @return array[] Rows of [sitesetting, courseoptin, expected, label].
     */
    protected function resolution_matrix(): array {
        return [
            [contentindex::SHARE_NEVER, 0, false, 'never + course off'],
            [contentindex::SHARE_NEVER, 1, false, 'never + course on'],
            [contentindex::SHARE_ALWAYS, 0, true, 'always + course off'],
            [contentindex::SHARE_ALWAYS, 1, true, 'always + course on'],
            [contentindex::SHARE_PERCOURSE, 0, false, 'percourse + course off'],
            [contentindex::SHARE_PERCOURSE, 1, true, 'percourse + course on'],
        ];
    }

    /**
     * The full site/course resolution matrix.
     *
     * The site setting is the CEILING: a course may never opt into something the site has not
     * permitted, and the per-course value is only ever consulted at SHARE_PERCOURSE.
     */
    public function test_the_site_setting_is_the_ceiling(): void {
        $this->resetAfterTest();

        foreach ($this->resolution_matrix() as [$sitesetting, $courseoptin, $expected, $label]) {
            // The course is built FIRST, while nothing constrains what may be stored, and the
            // site setting is applied afterwards, so every row holds an identical course value
            // and only the ceiling moves.
            $course = $this->make_course($courseoptin);
            set_config('shareassessmentanswers', $sitesetting, 'format_aicourse');

            $this->assertSame(
                $expected,
                contentindex::may_share_assessment_answers((int) $course->id),
                "{$label} resolved the wrong way."
            );
        }
    }

    /**
     * With the site delegating the decision, a caller that cannot name a course never shares.
     *
     * Some static helpers (extract_aiactivities() and friends, called from outside the index
     * build) genuinely have no course in hand. The safe answer there is "do not share", never
     * "assume the most permissive course".
     */
    public function test_a_caller_without_a_course_never_shares(): void {
        $this->resetAfterTest();

        set_config('shareassessmentanswers', contentindex::SHARE_PERCOURSE, 'format_aicourse');
        // A course that HAS opted in exists, so this cannot pass by there being nothing to find.
        $this->make_course(1);

        $this->assertFalse(contentindex::may_share_assessment_answers());
        $this->assertFalse(contentindex::may_share_assessment_answers(null));
    }

    /**
     * The same matrix, asserted on a REAL built index rather than on the gate alone.
     *
     * The index is what actually leaves the site, so this is the assertion that matters: for every
     * site/course combination, either every answer-bearing marker is present or none of them is —
     * and the question wording survives in all six, because withholding the key must never cost
     * the tutor its ability to discuss the topic.
     *
     */
    public function test_the_built_index_follows_the_matrix(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        foreach ($this->resolution_matrix() as [$sitesetting, $courseoptin, $expected, $label]) {
            // Store the course opt in first, then apply the site setting under test, so that the
            // stored course value is identical in every row and only the ceiling moves.
            $course = $this->make_course($courseoptin);
            $this->add_assessment($course);
            set_config('shareassessmentanswers', $sitesetting, 'format_aicourse');
            $this->clear_request_cache();

            $index = json_encode(contentindex::get_course_content_for_ai($course));

            // Always present: the tutor knows what was asked, in every combination.
            $this->assertStringContainsString('What is the capital of France?', $index, $label);
            $this->assertStringContainsString('Discuss the Seine.', $index, $label);

            $assert = $expected ? 'assertStringContainsString' : 'assertStringNotContainsString';
            $this->$assert('[CORRECT', $index, $label);
            $this->$assert('ESSAY MARKING GUIDE', $index, $label);
            $this->$assert('GRADERSECRET', $index, $label);
            $this->$assert('ZEBRAPARIS', $index, $label);
            $this->$assert('FEEDBACKRIGHT', $index, $label);
        }
    }

    /**
     * The per-course opt in really does change what a built index contains, in both directions.
     *
     * This is the end-to-end proof: two courses, identical content, different course settings,
     * one site setting of "let each course decide".
     */
    public function test_the_per_course_option_changes_the_built_index(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('shareassessmentanswers', contentindex::SHARE_PERCOURSE, 'format_aicourse');

        $optedin = $this->make_course(1);
        $optedout = $this->make_course(0);

        $qdata = [
            'type' => 'multichoice',
            'question' => 'Which planet is closest to the Sun?',
            'options' => [
                ['text' => 'Mercury', 'isCorrect' => true],
                ['text' => 'Venus', 'isCorrect' => false],
            ],
            'correctAnswer' => 0,
        ];

        $sharing = contentindex::extract_va_question($qdata, 1, (int) $optedin->id);
        $this->assertStringContainsString('[CORRECT', $sharing);
        $this->assertStringContainsString('Mercury', $sharing);

        $withholding = contentindex::extract_va_question($qdata, 1, (int) $optedout->id);
        $this->assertStringContainsString('Which planet is closest to the Sun?', $withholding);
        $this->assertStringNotContainsString('[CORRECT', $withholding);
        $this->assertStringNotContainsString('Mercury', $withholding);
    }

    /**
     * Two courses with different per-course settings never share a cache entry.
     *
     * The suffix in the key is the EFFECTIVE resolved value, not the site value, so the index
     * built for an opted-in course can never be served to a course that has not opted in.
     */
    public function test_the_cache_key_carries_the_effective_value(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('shareassessmentanswers', contentindex::SHARE_PERCOURSE, 'format_aicourse');

        $optedin = $this->make_course(1);
        $optedout = $this->make_course(0);

        contentindex::get_course_content_for_ai($optedin);
        contentindex::get_course_content_for_ai($optedout);

        $reflection = new \ReflectionClass(contentindex::class);
        $property = $reflection->getProperty('requestcache');
        $property->setAccessible(true);
        $keys = array_keys($property->getValue());

        $this->assertCount(2, $keys);
        $this->assertContains((int) $optedin->id . '_' . (int) $USER->id . '_a1', $keys);
        $this->assertContains((int) $optedout->id . '_' . (int) $USER->id . '_a0', $keys);
    }

    /**
     * With the gate off, a question's wording is indexed but its answer key is not.
     *
     * extract_va_question() is the shared question formatter, so it is the tightest place to
     * assert the rule that the tutor learns what was asked but never what the answer is.
     */
    public function test_question_text_is_indexed_without_the_answer_key(): void {
        $this->resetAfterTest();
        set_config('shareassessmentanswers', 0, 'format_aicourse');

        $qdata = [
            'type' => 'multichoice',
            'question' => 'Which planet is closest to the Sun?',
            'options' => [
                ['text' => 'Mercury', 'isCorrect' => true],
                ['text' => 'Venus', 'isCorrect' => false],
                ['text' => 'Mars', 'isCorrect' => false],
            ],
            'correctAnswer' => 0,
        ];

        $text = contentindex::extract_va_question($qdata, 1);

        // The tutor still knows what was asked, so it can discuss the topic.
        $this->assertStringContainsString('Which planet is closest to the Sun?', $text);

        // It does not learn the answer, by marker, by option list, or by resolved value.
        $this->assertStringNotContainsString('[CORRECT', $text);
        $this->assertStringNotContainsString('Mercury', $text);
        $this->assertStringNotContainsString('Venus', $text);
    }

    /**
     * With the gate explicitly enabled, the answer key is included.
     */
    public function test_answer_key_is_included_once_opted_in(): void {
        $this->resetAfterTest();
        set_config('shareassessmentanswers', 1, 'format_aicourse');

        $qdata = [
            'type' => 'multichoice',
            'question' => 'Which planet is closest to the Sun?',
            'options' => [
                ['text' => 'Mercury', 'isCorrect' => true],
                ['text' => 'Venus', 'isCorrect' => false],
            ],
            'correctAnswer' => 0,
        ];

        $text = contentindex::extract_va_question($qdata, 1);

        $this->assertStringContainsString('Which planet is closest to the Sun?', $text);
        $this->assertStringContainsString('Mercury', $text);
        $this->assertStringContainsString('[CORRECT', $text);
    }

    /**
     * The cached index is keyed by the setting, so flipping it cannot serve stale content.
     *
     * This is the dangerous direction: without it, turning the setting OFF would keep serving a
     * previously built index that still contained answer keys until the cache TTL expired.
     */
    public function test_toggling_the_setting_does_not_serve_a_stale_index(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'aicourse']);

        set_config('shareassessmentanswers', 1, 'format_aicourse');
        $withanswers = contentindex::get_course_content_for_ai($course);

        set_config('shareassessmentanswers', 0, 'format_aicourse');
        $withoutanswers = contentindex::get_course_content_for_ai($course);

        // Both are well formed indexes for the same course...
        $this->assertSame($course->fullname, $withanswers['course_name']);
        $this->assertSame($course->fullname, $withoutanswers['course_name']);

        // ...and they occupy DIFFERENT cache entries, which is what stops the second call
        // being served the first call's answer-bearing index. Read the in-request cache
        // directly: asserting on the keys is what actually proves the fix.
        $reflection = new \ReflectionClass(contentindex::class);
        $property = $reflection->getProperty('requestcache');
        $property->setAccessible(true);
        $keys = array_keys($property->getValue());

        $this->assertCount(2, $keys, 'Each setting state must occupy its own cache entry.');
        $this->assertContains((int) $course->id . '_' . (int) $USER->id . '_a1', $keys);
        $this->assertContains((int) $course->id . '_' . (int) $USER->id . '_a0', $keys);
    }

    /**
     * No indexed activity may carry an answer-key marker while the gate is off.
     */
    public function test_no_answer_markers_survive_anywhere_in_a_built_index(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('shareassessmentanswers', 0, 'format_aicourse');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'aicourse', 'numsections' => 2]);
        $generator->create_module('page', ['course' => $course->id, 'name' => 'Reading']);
        $generator->create_module('quiz', ['course' => $course->id, 'name' => 'Check']);

        $index = contentindex::get_course_content_for_ai($course);

        $serialised = json_encode($index);
        $this->assertStringNotContainsString('[CORRECT', $serialised);
        $this->assertStringNotContainsString('[ANSWER:', $serialised);
        $this->assertStringNotContainsString('ESSAY MARKING GUIDE', $serialised);
    }
}
