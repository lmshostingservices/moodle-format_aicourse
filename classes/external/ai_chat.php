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
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_text;
use format_aicourse\local\contentindex;
use format_aicourse\local\permissions;

/**
 * Web service asking the AI Tutor a question about a course.
 *
 * Replaces the 'aichat' action of the plugin's deprecated ajax.php endpoint.
 *
 * SECURITY NOTES, all carried over from ajax.php and all load bearing:
 *  - guests are refused outright, because every call spends purchased API credits;
 *  - the call is rate limited per user per course (see \format_aicourse\external\throttle);
 *  - the activity the question is about is resolved through modinfo and its uservisible flag is
 *    honoured, so a hidden or availability-restricted activity contributes no context;
 *  - answers for a submitted assignment are locked to a reflection-only reply.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_chat extends external_api {
    /** @var string Endpoint of the remote AI Tutor service. */
    protected const API_URL = 'https://lms-labs.com/api/moodle/course-assistant/chat';

    /** @var int Maximum number of characters of course content sent as prompt context. */
    protected const MAX_CONTEXT_CHARS = 50000;

    /** @var int Maximum number of characters of per-activity tutor memory retained. */
    protected const MAX_MEMORY_CHARS = 2000;

    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Id of the course being studied'),
            'question' => new external_value(PARAM_TEXT, 'The learner\'s question'),
            'activityid' => new external_value(
                PARAM_INT,
                'Course module id being viewed, or 0',
                VALUE_DEFAULT,
                0
            ),
            'sectionid' => new external_value(
                PARAM_INT,
                'Section number being viewed, or 0',
                VALUE_DEFAULT,
                0
            ),
            'isfirstmessage' => new external_value(PARAM_BOOL, 'True for the first question of the '
                . 'conversation', VALUE_DEFAULT, false),
            'questionslot' => new external_value(
                PARAM_INT,
                'Quiz question slot being attempted, or 0',
                VALUE_DEFAULT,
                0
            ),
            'questiontext' => new external_value(PARAM_TEXT, 'Text of the quiz question being '
                . 'attempted', VALUE_DEFAULT, ''),
            'allquestions' => new external_value(PARAM_TEXT, 'Summary of every question in the '
                . 'activity, for whole-activity awareness', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Ask the AI Tutor a question.
     *
     * @param int $courseid Id of the course.
     * @param string $question The learner's question.
     * @param int $activityid Course module id being viewed, or 0.
     * @param int $sectionid Section number being viewed, or 0.
     * @param bool $isfirstmessage True for the first question of the conversation.
     * @param int $questionslot Quiz question slot being attempted, or 0.
     * @param string $questiontext Text of the quiz question being attempted.
     * @param string $allquestions Summary of every question in the activity.
     * @return array The answer, the id of the stored chat row and any warnings.
     */
    public static function execute(
        int $courseid,
        string $question,
        int $activityid = 0,
        int $sectionid = 0,
        bool $isfirstmessage = false,
        int $questionslot = 0,
        string $questiontext = '',
        string $allquestions = ''
    ): array {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'question' => $question,
            'activityid' => $activityid,
            'sectionid' => $sectionid,
            'isfirstmessage' => $isfirstmessage,
            'questionslot' => $questionslot,
            'questiontext' => $questiontext,
            'allquestions' => $allquestions,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('format/aicourse:useaitutor', $context);

        // ACF-FIX-2.0: Guests must never spend API credits.
        if (isguestuser()) {
            throw new \moodle_exception('error_guestnotallowed', 'format_aicourse');
        }

        // ACF-FIX-2.1.4: the site setting is a kill switch, not a display preference. It used to
        // be consulted only by the output classes that draw the chat panel, so unticking it hid
        // the bubble while leaving this function fully callable through core/ajax -- anyone
        // holding format/aicourse:useaitutor could still spend purchased API credits. Enforce it
        // where the credits are actually spent.
        if (!permissions::is_tutor_enabled()) {
            throw new \moodle_exception('error_tutordisabled', 'format_aicourse');
        }

        // ACF-FIX-2.0: Each call ships up to 50KB of course context to a paid external API with a
        // 60 second timeout and holds a PHP worker for the duration. Throttle per user per course.
        throttle::check('aichat', $course->id, (int) $USER->id, throttle::AICHAT_MAX, throttle::AICHAT_WINDOW);

        if (trim($params['question']) === '') {
            throw new \moodle_exception('error_questionrequired', 'format_aicourse');
        }

        [$siteid, $apikey] = credentials::require_configured();

        $warnings = [];
        $activityid = $params['activityid'];
        $questionslot = $params['questionslot'];

        // Resolve the activity through modinfo so hidden and availability-restricted modules are
        // never used as context. ACF-FIX-2.0: the old fallback to get_coursemodule_from_id()
        // performed no visibility check at all.
        $activityname = null;
        $activitytype = null;
        $sectionname = null;
        $cminfo = null;
        if ($activityid > 0) {
            $modinfo = get_fast_modinfo($course);
            try {
                $candidate = $modinfo->get_cm($activityid);
                if ($candidate && $candidate->uservisible) {
                    $cminfo = $candidate;
                    $activityname = $cminfo->name;
                    $activitytype = $cminfo->modname;
                    $sectionname = get_section_name($course, $cminfo->sectionnum);
                }
            } catch (\Exception $e) {
                $warnings[] = [
                    'item' => 'activity',
                    'itemid' => $activityid,
                    'warningcode' => 'activitynotfound',
                    'message' => get_string('error_activitynotfound', 'format_aicourse'),
                ];
            }
        } else if ($params['sectionid'] > 0) {
            // The client sends a section NUMBER here, not a section id.
            $sectionname = get_section_name($course, $params['sectionid']);
        }

        // AI LOCKOUT: a submitted assignment gets a reflection-only reply (audit-grade integrity).
        if ($cminfo && $cminfo->modname === 'assign' && self::is_assignment_submitted($course, $cminfo)) {
            $lockedanswer = get_string('aiassistant_locked', 'format_aicourse');
            $chatid = self::log_chat(
                $course->id,
                $activityid,
                null,
                $params['question'],
                $lockedanswer,
                0,
                1,
                $warnings
            );

            return [
                'answer' => $lockedanswer,
                'chatid' => $chatid,
                'warnings' => $warnings,
            ];
        }

        // Load per-activity memory (safe, non-cheaty tutoring context).
        $memory = '';
        if ($activityid > 0) {
            try {
                $memrecord = $DB->get_record('format_aicourse_ai_memory', [
                    'courseid' => $course->id,
                    'activityid' => $activityid,
                    'userid' => $USER->id,
                ]);
                if ($memrecord) {
                    $memory = $memrecord->memory;
                }
            } catch (\dml_exception $e) {
                debugging('format_aicourse ai_chat memory read failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $warnings[] = [
                    'item' => 'memory',
                    'itemid' => $activityid,
                    'warningcode' => 'memoryunavailable',
                    'message' => get_string('error_memoryunavailable', 'format_aicourse'),
                ];
            }
        }

        $coursecontent = contentindex::get_course_content_for_ai($course);
        $contexttext = self::build_context_text(
            $coursecontent,
            $params['allquestions'],
            $questionslot,
            $params['questiontext']
        );

        $postdata = [
            'siteUrl' => $siteid,
            'apiKey' => $apikey,
            'action' => 'ai_tutor_chat',
            'courseName' => $coursecontent['course_name'],
            'courseContext' => $contexttext,
            'question' => $params['question'],
            'userId' => $USER->id,
            'courseId' => $course->id,
            'activityName' => $activityname,
            'activityType' => $activitytype,
            'sectionName' => $sectionname,
            'isFirstMessage' => (bool) $params['isfirstmessage'],
            'studentName' => $USER->firstname,
            'pedagogicalGuidelines' => self::get_pedagogical_guidelines(),
            'priorTutorMemory' => $memory,
            'mode' => 'learning',
            'questionSlot' => $questionslot > 0 ? $questionslot : null,
            'questionText' => $params['questiontext'] !== '' ? $params['questiontext'] : null,
        ];

        // ACF-FIX-2.1.4: never POST the result of a failed encode. json_encode() returns false
        // (not a string) if any value is not valid UTF-8, and curl->post(false) sends an empty
        // body, which the remote service answers with an opaque error. Fail loudly instead.
        $payload = json_encode($postdata);
        if ($payload === false) {
            debugging(
                'format_aicourse ai_chat could not encode request: ' . json_last_error_msg(),
                DEBUG_DEVELOPER
            );
            throw new \moodle_exception('aiassistant_error', 'format_aicourse');
        }

        // ACF-FIX-2.1.10: raise the script limit to sit above the cURL timeout, and fail fast on
        // connect. Without the first, a slow answer is killed by PHP rather than by cURL and the
        // user gets a blank failure instead of a handled one.
        \core_php_time_limit::raise(120);

        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 60,
            'CURLOPT_CONNECTTIMEOUT' => 15,
        ]);
        $curl->setHeader(['Content-Type: application/json', 'Accept: application/json']);
        $response = $curl->post(self::API_URL, $payload);
        $httpcode = $curl->info['http_code'] ?? 0;

        if ((int) $httpcode !== 200) {
            // ACF-FIX-2.0: the remote error body is logged, never returned to the browser.
            debugging('format_aicourse ai_chat HTTP ' . $httpcode . ' ' . $curl->error . ' '
                . substr((string) $response, 0, 500), DEBUG_DEVELOPER);
            $key = self::error_key_for_status((int) $httpcode);
            throw new \moodle_exception($key ?? 'aiassistant_error', 'format_aicourse');
        }

        $result = json_decode($response, true);
        if (!$result || empty($result['success'])) {
            debugging(
                'format_aicourse ai_chat invalid response ' . substr((string) $response, 0, 500),
                DEBUG_DEVELOPER
            );
            throw new \moodle_exception('aiassistant_error', 'format_aicourse');
        }

        $answer = (string) ($result['answer'] ?? '');

        // ACF-FIX-2.1.4: prefer an explicit flag from the service. detect_refusal() below matches
        // English phrases only, so on a site running the tutor in any other language the report's
        // academic-integrity counters silently read zero. The flag is authoritative when present;
        // the phrase matching remains as a fallback for service versions that do not send it.
        $refused = array_key_exists('refused', $result)
            ? (int) (bool) $result['refused']
            : self::detect_refusal($answer);

        $chatid = self::log_chat(
            $course->id,
            $activityid,
            $questionslot > 0 ? $questionslot : null,
            $params['question'],
            $answer,
            $refused,
            0,
            $warnings
        );

        if ($activityid > 0) {
            self::update_memory($course->id, $activityid, $memory, $params['question'], $warnings);
        }

        return [
            'answer' => $answer,
            'chatid' => $chatid,
            'warnings' => $warnings,
        ];
    }

    /**
     * Translate an HTTP status from the LMS-Labs service into a specific error string.
     *
     * ACF-FIX-2.1.34: both integrations used to collapse every non-200 into a single generic
     * message, so "you have run out of credits" and "your API key is wrong" were indistinguishable
     * from "the service is down" -- for the student, the teacher and the administrator alike. The
     * real status went to debugging() only, which is off on production sites, so the one place the
     * answer existed was the one place nobody looks. The service returns 401 for a bad key or a
     * mismatched site URL and 402 for insufficient credits.
     *
     * Unknown statuses still fall through to the caller's generic message.
     *
     * @param int $httpcode The HTTP status returned by the service.
     * @return string|null A language string key, or null when the status has no specific message.
     */
    protected static function error_key_for_status(int $httpcode): ?string {
        switch ($httpcode) {
            case 401:
            case 403:
                return 'error_apiunauthorized';
            case 402:
                return 'error_apinocredits';
            case 429:
                return 'error_apiratelimited';
            default:
                return null;
        }
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            // Model-generated prose, returned verbatim. It routinely contains angle brackets in
            // code samples and mathematics, so PARAM_TEXT / PARAM_NOTAGS would silently corrupt
            // correct answers. It is never parsed as HTML: the client writes it with textContent
            // (amd/src/chatbox.js), and an XSS probe asserting that is part of the suite.
            // phpcs:disable moodle.Commenting.InlineComment.NotCapital -- Release pipeline marker.
            'answer' => new external_value(PARAM_RAW, 'Answer'), // pipeline-ignore: PARAM_RAW — prose, textContent.
            // phpcs:enable moodle.Commenting.InlineComment.NotCapital
            'chatid' => new external_value(PARAM_INT, 'Id of the stored chat row, or 0 when it could '
                . 'not be stored'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Whether the calling user has already submitted the given assignment.
     *
     * @param \stdClass $course The course.
     * @param \cm_info $cminfo The visibility-checked course module.
     * @return bool True when a submitted submission exists.
     */
    protected static function is_assignment_submitted(\stdClass $course, \cm_info $cminfo): bool {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $assign = new \assign(\context_module::instance($cminfo->id), $cminfo, $course);
        $submission = $assign->get_user_submission($USER->id, false);

        return $submission && $submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED;
    }

    /**
     * Store one question and answer in the chat log.
     *
     * A failure here must not lose the learner their answer, so it is reported as a warning.
     *
     * @param int $courseid Id of the course.
     * @param int $activityid Course module id, or 0.
     * @param int|null $questionslot Quiz question slot, or null.
     * @param string $question The question asked.
     * @param string $answer The answer given.
     * @param int $refused 1 when the tutor refused to answer.
     * @param int $locked 1 when the answer was a post-submission reflection reply.
     * @param array $warnings Warning list, appended to by reference.
     * @return int Id of the stored row, or 0 when it could not be stored.
     */
    protected static function log_chat(
        int $courseid,
        int $activityid,
        ?int $questionslot,
        string $question,
        string $answer,
        int $refused,
        int $locked,
        array &$warnings
    ): int {
        global $DB, $USER;

        try {
            $record = new \stdClass();
            $record->courseid = $courseid;
            $record->userid = $USER->id;
            $record->activityid = $activityid;
            $record->questionslot = $questionslot;
            $record->question = $question;
            $record->response = $answer;
            $record->rating = 0;
            $record->refused = $refused;
            $record->locked = $locked;
            $record->timecreated = time();

            return (int) $DB->insert_record('format_aicourse_chats', $record);
        } catch (\dml_exception $e) {
            debugging('format_aicourse ai_chat log failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $warnings[] = [
                'item' => 'chat',
                'itemid' => $courseid,
                'warningcode' => 'chatlogunavailable',
                'message' => get_string('error_chatlogunavailable', 'format_aicourse'),
            ];

            return 0;
        }
    }

    /**
     * Update the per-activity tutor memory with a safe summary of what was asked.
     *
     * The memory stores only what the learner asked about, never any answer.
     *
     * @param int $courseid Id of the course.
     * @param int $activityid Course module id.
     * @param string $memory The memory as it was before this question.
     * @param string $question The question asked.
     * @param array $warnings Warning list, appended to by reference.
     * @return void
     */
    protected static function update_memory(
        int $courseid,
        int $activityid,
        string $memory,
        string $question,
        array &$warnings
    ): void {
        global $DB, $USER;

        try {
            // ACF-FIX-2.1.4: core_text, not substr(). A byte-wise cut can land in the middle of
            // a multibyte character and store invalid UTF-8, which then breaks json_encode() on
            // the next request that sends this memory to the remote service.
            $summary = 'Student asked about: ' . core_text::substr(strip_tags($question), 0, 200);
            if ($memory !== '') {
                $summary = $memory . "\n" . $summary;
                if (core_text::strlen($summary) > self::MAX_MEMORY_CHARS) {
                    $summary = core_text::substr($summary, -self::MAX_MEMORY_CHARS);
                }
            }

            $existing = $DB->get_record('format_aicourse_ai_memory', [
                'courseid' => $courseid,
                'activityid' => $activityid,
                'userid' => $USER->id,
            ]);

            if ($existing) {
                $existing->memory = $summary;
                $existing->timeupdated = time();
                $DB->update_record('format_aicourse_ai_memory', $existing);
            } else {
                $new = new \stdClass();
                $new->courseid = $courseid;
                $new->activityid = $activityid;
                $new->userid = $USER->id;
                $new->memory = $summary;
                $new->timeupdated = time();
                $DB->insert_record('format_aicourse_ai_memory', $new);
            }
        } catch (\dml_exception $e) {
            debugging('format_aicourse ai_chat memory write failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $warnings[] = [
                'item' => 'memory',
                'itemid' => $activityid,
                'warningcode' => 'memoryunavailable',
                'message' => get_string('error_memoryunavailable', 'format_aicourse'),
            ];
        }
    }

    /**
     * Build the course context block sent to the remote tutor.
     *
     * This text never reaches the browser: it is a server to server prompt and may legitimately
     * contain answer keys extracted by \format_aicourse\local\contentindex.
     *
     * @param array $coursecontent Output of contentindex::get_course_content_for_ai().
     * @param string $allquestions Summary of every question in the activity.
     * @param int $questionslot Quiz question slot being attempted, or 0.
     * @param string $questiontext Text of the quiz question being attempted.
     * @return string The prompt context.
     */
    protected static function build_context_text(
        array $coursecontent,
        string $allquestions,
        int $questionslot,
        string $questiontext
    ): string {
        $text = 'Course: ' . $coursecontent['course_name'] . "\n";
        $text .= 'Summary: ' . $coursecontent['course_summary'] . "\n\n";

        $text .= "Sections:\n";
        foreach ($coursecontent['sections'] as $section) {
            $text .= '- ' . $section['name'] . ': ' . $section['summary'] . "\n";
        }

        $text .= "\nActivities:\n";
        foreach ($coursecontent['activities'] as $activity) {
            $text .= '- ' . $activity['name'] . ' (' . $activity['type'] . '): ' . $activity['content'] . "\n";
        }

        // ACF-FIX-2.1.4: core_text, not substr(). This is the severe case: a byte-wise cut here
        // produces invalid UTF-8, json_encode() below then returns false rather than a string,
        // and the plugin POSTs an empty body -- so the tutor failed with an opaque error on any
        // course containing accented characters, CJK or emoji.
        if (core_text::strlen($text) > self::MAX_CONTEXT_CHARS) {
            $text = core_text::substr($text, 0, self::MAX_CONTEXT_CHARS) . "\n...[content truncated]";
        }

        if ($allquestions !== '') {
            $text .= "\n\nQUIZ QUESTIONS IN THIS ACTIVITY:\n";
            $text .= $allquestions . "\n";
            $text .= 'IMPORTANT: You know ALL the questions in this quiz. Help students understand '
                . "concepts without giving direct answers.\n";
        }

        if ($questionslot > 0) {
            $text .= "\n\nCURRENT QUIZ QUESTION:\n";
            $text .= 'Question number: Q' . $questionslot . "\n";
            if ($questiontext !== '') {
                $text .= 'Question topic/context: ' . $questiontext . "\n";
            }
            $text .= 'IMPORTANT: Do NOT provide the answer to this question. Help the student think '
                . "through it.\n";
        }

        return $text;
    }

    /**
     * Whether an answer looks like the tutor refused to hand over a solution.
     *
     * Refusals are counted as academic-integrity enforcement evidence in the AI Tutor report.
     *
     * FALLBACK ONLY. These markers are English, so this cannot work on a site running the tutor
     * in another language. execute() uses the 'refused' flag from the service response whenever
     * the service sends one, and only falls back to this when it does not.
     *
     * @param string $answer The tutor's answer.
     * @return int 1 when the answer reads as a refusal, 0 otherwise.
     */
    protected static function detect_refusal(string $answer): int {
        $markers = [
            "I can't provide",
            'I cannot provide',
            'I cannot give',
            "I can't give you the answer",
            'I can help you think through',
            'instead of giving you the answer',
        ];

        foreach ($markers as $marker) {
            if (stripos($answer, $marker) !== false) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * The pedagogical guardrails sent with every request.
     *
     * @return string The guidelines.
     */
    protected static function get_pedagogical_guidelines(): string {
        $lines = [
            'CRITICAL PEDAGOGICAL GUIDELINES:',
            '- You are an AI Tutor with COMPLETE knowledge of every activity in this course.',
            '- You have read every learning slide, every quiz question and answer, every activity, '
                . 'every video transcript, every essay rubric, and every explanation in the course '
                . 'content provided above.',
            '- When a student asks about a topic, USE your knowledge of the specific slides, '
                . 'questions, and explanations from their course materials to help them understand. '
                . 'Reference specific content from their actual course.',
            '- NEVER provide sample answers, model responses, or complete solutions to assessment tasks.',
            '- NEVER write content that could be directly submitted as the student\'s own work.',
            '- NEVER reveal the exact correct answer to quiz/knowledge check questions. Instead, use '
                . 'the explanations and learning content to guide them toward understanding WHY the '
                . 'correct answer is correct.',
            '- Instead, guide students with:',
            '  1. Structure guidance: What sections to include, how to organize their response',
            '  2. Concept explanations: Break down key terms and ideas using the actual course content',
            '  3. Real workplace examples: How this applies in actual job settings, drawing from '
                . 'course scenarios',
            '  4. Prompting questions: Questions that help the student think deeper about the '
                . 'specific topic from their course materials',
            '  5. Checklists: What to review before submitting',
            '  6. Cross-referencing: Point students to relevant learning slides, activities, or '
                . 'materials in their course that cover the topic they are asking about',
            '- For VET/RTO compliance: Students must demonstrate THEIR OWN competency',
            '- If a student asks for an answer directly, use the course explanations to help them '
                . 'UNDERSTAND the concept, do not just give them the answer',
            '- Be encouraging but maintain academic integrity at all times',
            '- You know the FULL content of this course — use it to give precise, relevant, '
                . 'contextual help rather than generic advice',
        ];

        return implode("\n", $lines);
    }
}
