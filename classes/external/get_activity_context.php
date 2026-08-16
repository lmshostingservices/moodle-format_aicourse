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

namespace format_aicourse\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service returning the public context of an activity for the AI Tutor panel.
 *
 * Replaces the 'getactivitycontext' action of the plugin's deprecated ajax.php endpoint.
 *
 * SECURITY, all load bearing and all carried over from ajax.php:
 *  - the old action had NO capability check at all; it now requires format/aicourse:useaitutor;
 *  - the old action had NO visibility check, so any enrolled user could read the intro and the
 *    questions of a hidden or availability-restricted activity. $cm->uservisible is now enforced;
 *  - the old action returned answer options annotated ' [CORRECT]', per-answer explanations,
 *    feedback and marking criteria. Nothing in this class emits a correctness marker: only the
 *    public activity intro and the bare question prompts are returned. The
 *    \format_aicourse\local\contentindex extractors, which DO embed answer keys, are for the
 *    server-to-server AI prompt only and must never be called from here.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_activity_context extends external_api {
    /** @var int Maximum number of characters of a video transcript exposed to the browser. */
    protected const MAX_TRANSCRIPT_CHARS = 5000;

    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Id of the course the activity belongs to'),
            'activityid' => new external_value(PARAM_INT, 'Course module id of the activity'),
            'questionslot' => new external_value(PARAM_INT, 'Question slot the learner is looking at, '
                . 'or 0', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Return the public context of one activity.
     *
     * @param int $courseid Id of the course.
     * @param int $activityid Course module id.
     * @param int $questionslot Question slot the learner is looking at, or 0.
     * @return array The activity context.
     */
    public static function execute(int $courseid, int $activityid, int $questionslot = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'activityid' => $activityid,
            'questionslot' => $questionslot,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('format/aicourse:useaitutor', $context);

        $modinfo = get_fast_modinfo($course);
        try {
            $cm = $modinfo->get_cm($params['activityid']);
        } catch (\Exception $e) {
            throw new \moodle_exception('error_activitynotfound', 'format_aicourse');
        }

        // ACF-FIX-2.0: get_cm() returns hidden and availability-restricted modules too. Without
        // this check any enrolled user could read the intro and questions of an activity they are
        // not allowed to see.
        if (!$cm->uservisible) {
            throw new \moodle_exception('error_activitynotvisible', 'format_aicourse');
        }

        $questions = self::get_questions($cm);
        $current = null;
        if ($params['questionslot'] > 0) {
            foreach ($questions as $question) {
                if ($question['slot'] === $params['questionslot']) {
                    $current = $question;
                    break;
                }
            }
        }

        $activitycontext = [
            'name' => format_string($cm->name),
            'type' => $cm->modname,
            'intro' => self::get_intro($cm),
            'questions' => $questions,
        ];
        if ($current !== null) {
            $activitycontext['currentquestion'] = $current;
        }

        return ['context' => $activitycontext];
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'context' => new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Name of the activity'),
                'type' => new external_value(PARAM_PLUGIN, 'Module name of the activity'),
                'intro' => new external_value(PARAM_TEXT, 'Public introduction text of the activity, '
                    . 'tags stripped'),
                'questions' => new external_multiple_structure(
                    self::question_structure(),
                    'Every question prompt in the activity, answer keys removed'
                ),
                'currentquestion' => self::question_structure(
                    'The question the learner is looking at',
                    VALUE_OPTIONAL
                ),
            ]),
        ]);
    }

    /**
     * Structure of one question prompt.
     *
     * The structure deliberately has no field for correct answers, explanations or feedback.
     *
     * @param string $description Description of the structure.
     * @param int $required One of the VALUE_* constants.
     * @return external_single_structure
     */
    protected static function question_structure(
        string $description = 'A question prompt',
        int $required = VALUE_REQUIRED
    ): external_single_structure {
        return new external_single_structure([
            'slot' => new external_value(PARAM_INT, 'Position of the question in the activity, 1 based'),
            'text' => new external_value(PARAM_TEXT, 'The question prompt, tags stripped'),
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Question type'),
        ], $description, $required);
    }

    /**
     * The public introduction text of an activity.
     *
     * @param \cm_info $cm The visibility-checked course module.
     * @return string Plain text intro, possibly empty.
     */
    protected static function get_intro(\cm_info $cm): string {
        global $DB;

        // The 'knowledgecheck' module stores its instances in the aiknowledgecheck table.
        $table = $cm->modname === 'knowledgecheck' ? 'aiknowledgecheck' : $cm->modname;
        if (!$DB->get_manager()->table_exists($table)) {
            return '';
        }

        $record = $DB->get_record($table, ['id' => $cm->instance]);
        if (!$record) {
            return '';
        }

        $intro = isset($record->intro) ? strip_tags((string) $record->intro) : '';

        if ($cm->modname === 'assign' && !empty($record->activity)) {
            $intro .= "\n\nActivity Instructions:\n" . strip_tags((string) $record->activity);
        }

        if ($cm->modname === 'aivideoactivity' && !empty($record->transcripttext)) {
            $intro .= "\n[TRANSCRIPT]: " . substr(
                strip_tags((string) $record->transcripttext),
                0,
                self::MAX_TRANSCRIPT_CHARS
            );
        }

        return $intro;
    }

    /**
     * The question prompts of an activity, with every answer key removed.
     *
     * @param \cm_info $cm The visibility-checked course module.
     * @return array List of question prompt arrays.
     */
    protected static function get_questions(\cm_info $cm): array {
        switch ($cm->modname) {
            case 'quiz':
                return self::get_quiz_questions($cm);
            case 'aiquiz':
                return self::get_simple_questions(
                    'aiquiz_questions',
                    'aiquizid',
                    $cm->instance,
                    'questionorder ASC',
                    'questiontype'
                );
            case 'aiknowledgecheck':
            case 'knowledgecheck':
                // ACF-FIX-2.0: this used to concatenate every answer option annotated ' [CORRECT]'
                // plus the per-answer explanations. Only the question text is returned now.
                return self::get_simple_questions(
                    'aiknowledgecheck_questions',
                    'aiknowledgecheckid',
                    $cm->instance,
                    '',
                    '',
                    'knowledgecheck'
                );
            case 'aivideoactivity':
                return self::get_video_questions($cm);
            default:
                // Every other module type, including aiactivities, contentcreator and
                // practicalassessment, exposes its intro only. Their question banks embed
                // ' [CORRECT]', '[ANSWER: ...]' or the marking criteria.
                return [];
        }
    }

    /**
     * The question prompts of a quiz.
     *
     * @param \cm_info $cm The visibility-checked course module.
     * @return array List of question prompt arrays.
     */
    protected static function get_quiz_questions(\cm_info $cm): array {
        global $DB;

        $questions = [];

        // Moodle 4.x resolves quiz slots through question_references; 3.x had quiz_slots.questionid.
        if ($DB->get_manager()->table_exists('question_references')) {
            $sql = "SELECT qs.slot, qs.id AS slotid, q.id, q.name, q.questiontext, q.qtype
                      FROM {quiz_slots} qs
                      JOIN {question_references} qr
                        ON qr.component = 'mod_quiz'
                       AND qr.questionarea = 'slot'
                       AND qr.usingcontextid = :contextid
                       AND qr.itemid = qs.id
                      JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                      JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                      JOIN {question} q ON q.id = qv.questionid
                     WHERE qs.quizid = :quizid
                  ORDER BY qs.slot ASC, qv.version DESC";
            $records = $DB->get_records_sql($sql, [
                'contextid' => \context_module::instance($cm->id)->id,
                'quizid' => $cm->instance,
            ]);
        } else {
            $sql = "SELECT qs.slot, q.id, q.name, q.questiontext, q.qtype
                      FROM {quiz_slots} qs
                      JOIN {question} q ON q.id = qs.questionid
                     WHERE qs.quizid = :quizid
                  ORDER BY qs.slot ASC";
            $records = $DB->get_records_sql($sql, ['quizid' => $cm->instance]);
        }

        $seenslots = [];
        foreach ($records as $record) {
            if (isset($seenslots[$record->slot])) {
                // Keep only the latest version per slot.
                continue;
            }
            $seenslots[$record->slot] = true;
            $questions[] = [
                'slot' => (int) $record->slot,
                'text' => strip_tags((string) ($record->questiontext ?? '')),
                'type' => $record->qtype,
            ];
        }

        return $questions;
    }

    /**
     * The question prompts of a plugin that stores one row per question.
     *
     * Only the questiontext column is ever read; the answer, explanation and feedback columns of
     * these tables are answer keys and are deliberately not touched.
     *
     * @param string $table Name of the questions table.
     * @param string $foreignkey Name of the column holding the activity instance id.
     * @param int $instance The activity instance id.
     * @param string $sort Sort clause, or '' for the natural order.
     * @param string $typefield Column holding the question type, or '' when there is none.
     * @param string $defaulttype Question type to report when there is no type column.
     * @return array List of question prompt arrays.
     */
    protected static function get_simple_questions(
        string $table,
        string $foreignkey,
        int $instance,
        string $sort = '',
        string $typefield = '',
        string $defaulttype = 'unknown'
    ): array {
        global $DB;

        if (!$DB->get_manager()->table_exists($table)) {
            return [];
        }

        $questions = [];
        $slot = 1;
        foreach ($DB->get_records($table, [$foreignkey => $instance], $sort) as $record) {
            $questions[] = [
                'slot' => $slot,
                'text' => strip_tags((string) ($record->questiontext ?? '')),
                'type' => $typefield !== '' ? ($record->{$typefield} ?? $defaulttype) : $defaulttype,
            ];
            $slot++;
        }

        return $questions;
    }

    /**
     * The question prompts of a video activity.
     *
     * ACF-FIX-2.0: contentindex::extract_va_question() emits ' [CORRECT]', '[CORRECT: ...]' and
     * '[EXPLANATION: ...]' markers, so it is not used here. Only the bare prompt is exposed.
     *
     * @param \cm_info $cm The visibility-checked course module.
     * @return array List of question prompt arrays.
     */
    protected static function get_video_questions(\cm_info $cm): array {
        global $DB;

        if (!$DB->get_manager()->table_exists('aivideoactivity_questions')) {
            return [];
        }

        $questions = [];
        $slot = 1;
        $records = $DB->get_records('aivideoactivity_questions', ['aivideoactivityid' => $cm->instance]);
        foreach ($records as $record) {
            $text = '';
            if (!empty($record->questiondata)) {
                $decoded = json_decode($record->questiondata, true);
                if (is_array($decoded)) {
                    $prompt = $decoded['question'] ?? $decoded['questionText'] ?? $decoded['text'] ?? '';
                    $text = is_string($prompt) ? strip_tags($prompt) : '';
                }
            } else if (!empty($record->questiontext)) {
                $text = strip_tags((string) $record->questiontext);
            }
            $questions[] = [
                'slot' => $slot,
                'text' => $text,
                'type' => 'videoactivity',
            ];
            $slot++;
        }

        return $questions;
    }
}
