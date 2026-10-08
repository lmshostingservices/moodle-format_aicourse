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
use format_aicourse\local\tutorprompt;

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
 *  - answers for a submitted assignment are locked to a reflection-only reply, and a graded quiz
 *    attempt in progress or an activity tagged "ai-tutor-off" locks the tutor (3.2.3);
 *  - quiz question text is read on the server, never taken from the browser (3.2.3).
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_chat extends external_api {
    /** @var string Endpoint of the remote AI Tutor service. */
    protected const API_URL = 'https://lms-labs.com/api/moodle/course-assistant/chat';

    /** @var int Default maximum number of characters of course content sent as prompt context. */
    protected const MAX_CONTEXT_CHARS = 50000;

    /** @var array|null The last request body built, kept only under PHPUnit. */
    public static ?array $lastrequest = null;

    /** @var string Activity tag a teacher adds to switch the AI Tutor off for that activity. */
    public const TAG_OFF = 'ai-tutor-off';

    /** @var int How many recent exchanges are replayed to the model as conversation history. */
    protected const HISTORY_TURNS = 4;

    /** @var int How far back, in seconds, an earlier exchange still counts as the same conversation. */
    protected const HISTORY_WINDOW = 3600;

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
            'isfirstmessage' => new external_value(
                PARAM_BOOL,
                'True for the first question of the '
                    . 'conversation',
                VALUE_DEFAULT,
                false
            ),
            'questionslot' => new external_value(
                PARAM_INT,
                'Quiz question slot being attempted, or 0',
                VALUE_DEFAULT,
                0
            ),
            'questiontext' => new external_value(
                PARAM_TEXT,
                'Text of the quiz question being '
                    . 'attempted',
                VALUE_DEFAULT,
                ''
            ),
            'allquestions' => new external_value(
                PARAM_TEXT,
                'Summary of every question in the '
                    . 'activity, for whole-activity awareness',
                VALUE_DEFAULT,
                ''
            ),
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
                    $activityname = self::plain($cminfo->name);
                    $activitytype = $cminfo->modname;
                    $sectionname = self::plain(get_section_name($course, $cminfo->sectionnum));
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
            $sectionname = self::plain(get_section_name($course, $params['sectionid']));
        }

        $isteacher = has_capability('moodle/course:update', $context);

        // AI LOCKOUT (audit-grade integrity): a submitted assignment, a graded quiz attempt in
        // progress, or an activity a teacher has tagged "ai-tutor-off" gets a fixed reply and
        // nothing is sent to the service. 3.2.3 added the last two: ASQA's rules of evidence
        // (authenticity) do not allow unsupervised AI help during a summative attempt.
        $lockkey = null;
        if ($cminfo && $cminfo->modname === 'assign' && self::is_assignment_submitted($course, $cminfo)) {
            $lockkey = 'aiassistant_locked';
        } else if (
            $cminfo && !$isteacher
            && (self::is_graded_quiz_in_progress($cminfo) || self::is_tagged_off($cminfo))
        ) {
            $lockkey = 'aiassistant_locked_assessment';
        }
        if ($lockkey !== null) {
            $lockedanswer = get_string($lockkey, 'format_aicourse');
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
                'truncated' => false,
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

        // 3.2.3: the quiz question text is read on the server, not taken from the browser. Text the
        // browser sends could be anything a learner typed into the developer console, and it is
        // placed inside the course material the tutor trusts. The client parameters are kept so
        // older bundles still validate, but they are not used.
        [$allquestions, $questiontext] = self::server_question_context($cminfo, $questionslot);

        $coursecontent = contentindex::get_course_content_for_ai($course);
        $contexttext = self::build_context_text(
            $coursecontent,
            $allquestions,
            $questionslot,
            $questiontext
        );

        // ACF-FIX-2.1.96: give teachers the format's own settings reference.
        //
        // A teacher asking "how do I hide the tabs?" was getting an answer about their course
        // content, because that is all the tutor had. The settings are the part of this format
        // teachers most need help with, and the plugin already holds a plain-language explanation
        // of every one of them.
        //
        // Editors only. A learner has no use for it, it would be a large addition to every request
        // they make, and their questions are about the course rather than how it was built.
        if ($isteacher) {
            $reference = \format_aicourse\local\formathelp::get_reference();
            if ($reference !== '') {
                $contexttext = $reference . "\n\n---\n\n" . $contexttext;
            }
        }

        $audience = self::course_audience($course);
        $promptinput = [
            'coursename' => $coursecontent['course_name'],
            'context' => $contexttext,
            'question' => $params['question'],
            'activityname' => (string) $activityname,
            'activitytype' => (string) $activitytype,
            'sectionname' => (string) $sectionname,
            'isfirstmessage' => (bool) $params['isfirstmessage'],
            // Data minimisation: a primary school child's name is never sent.
            'studentname' => self::may_send_first_name($audience) ? (string) $USER->firstname : '',
            'memory' => $memory,
            'history' => self::recent_history((int) $course->id, (int) $activityid, (int) $USER->id),
            'isteacher' => $isteacher,
            'audience' => $audience,
            'shareanswers' => contentindex::may_share_assessment_answers((int) $course->id),
            'support' => self::support_contacts(),
            'courselang' => self::course_language_name($course),
            'corrections' => self::teacher_corrections((int) $course->id, (int) $activityid),
        ];

        $postdata = self::build_request($promptinput, [
            'siteUrl' => $siteid,
            'apiKey' => $apikey,
            'userId' => $USER->id,
            'courseId' => $course->id,
            'activityType' => $activitytype,
            'questionSlot' => $questionslot > 0 ? $questionslot : null,
            'questionText' => $questiontext !== '' ? $questiontext : null,
        ]);
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            // Lets the tests assert what would have been sent; never set outside PHPUnit.
            self::$lastrequest = $postdata;
        }

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
            debugging(
                'format_aicourse ai_chat HTTP ' . $httpcode . ' ' . $curl->error . ' '
                    . substr((string) $response, 0, 500),
                DEBUG_DEVELOPER
            );
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

        // 3.2.3: the tutor ends a refusal with a language-independent marker, removed here before
        // the learner sees it. Order of authority for the report's integrity counter: the marker,
        // then the service's own 'refused' flag (ACF-FIX-2.1.4), then English phrase matching.
        $stripped = tutorprompt::strip_markers((string) ($result['answer'] ?? ''));
        $answer = $stripped['answer'];
        if ($answer === '') {
            debugging('format_aicourse ai_chat empty answer from the service', DEBUG_DEVELOPER);
            throw new \moodle_exception('aiassistant_error', 'format_aicourse');
        }
        if ($stripped['refused']) {
            $refused = 1;
        } else if (array_key_exists('refused', $result)) {
            $refused = (int) (bool) $result['refused'];
        } else {
            $refused = self::detect_refusal($answer);
        }

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
            'truncated' => self::is_truncated($result, $answer),
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
            'chatid' => new external_value(
                PARAM_INT,
                'Id of the stored chat row, or 0 when it could '
                    . 'not be stored'
            ),
            'truncated' => new external_value(
                PARAM_BOOL,
                'True when the answer was cut off before it finished',
                VALUE_DEFAULT,
                false
            ),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Whether the service stopped the answer before it was finished.
     *
     * 3.2.2: a service output limit cut answers off mid-sentence, sometimes before a practice
     * question block had even started, and the learner saw "Here are three questions" and then
     * nothing. The service's own flag or stop reason is authoritative when it sends one; until
     * it does, an answer that visibly stops part-way (see answertext::looks_cut_off()) counts.
     *
     * @param array $result The decoded service response.
     * @param string $answer The answer text.
     * @return bool True when the learner should be told the answer is incomplete.
     */
    protected static function is_truncated(array $result, string $answer): bool {
        if (!empty($result['truncated'])) {
            return true;
        }
        $reason = strtolower((string) ($result['finishReason'] ?? $result['stopReason'] ?? ''));
        if (in_array($reason, ['max_tokens', 'length', 'max_output_tokens'], true)) {
            return true;
        }
        return \format_aicourse\local\answertext::looks_cut_off($answer);
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
        // 3.2.3: the index holds names as Moodle prints them, HTML-escaped ("Financial Accounting
        // &amp; Reporting"). The model reads text, not HTML, so entities are decoded.
        // Decoded only: the index is already plain text, and strip_tags() would eat a "<" in
        // something like "x < 3".
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $max = self::max_context_chars();
        if (core_text::strlen($text) > $max) {
            $text = core_text::substr($text, 0, $max) . "\n...[content truncated]";
        }

        // The tutor rules (integrity, hint ladder) govern how these are used.
        if ($allquestions !== '') {
            $text .= "\n\nQUIZ QUESTIONS IN THIS ACTIVITY:\n";
            $text .= $allquestions . "\n";
        }

        if ($questionslot > 0) {
            $text .= "\n\nCURRENT QUIZ QUESTION:\n";
            $text .= 'Question number: Q' . $questionslot . "\n";
            if ($questiontext !== '') {
                $text .= 'Question topic/context: ' . $questiontext . "\n";
            }
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
        ];

        foreach ($markers as $marker) {
            if (stripos($answer, $marker) !== false) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * The request body sent to the AI Tutor service.
     *
     * 3.2.3: the whole prompt is composed in the plugin ({@see tutorprompt}) and sent as `prompt`,
     * with `promptVersion`. The same content is also spread across the fields the service already
     * reads -- the rules in `pedagogicalGuidelines`, the fenced material, teacher corrections and
     * conversation in `courseContext` -- so every improvement reaches the learner whether or not
     * the service has been updated to use `prompt` directly.
     *
     * @param array $in The prompt input, see tutorprompt::normalise().
     * @param array $extra Identity and routing fields: siteUrl, apiKey, userId, courseId,
     *                     activityType, questionSlot, questionText.
     * @return array
     */
    protected static function build_request(array $in, array $extra): array {
        $history = [];
        foreach ($in['history'] ?? [] as [$q, $a]) {
            $history[] = ['question' => $q, 'answer' => tutorprompt::strip_markers($a)['answer']];
        }

        return [
            'siteUrl' => $extra['siteUrl'] ?? '',
            'apiKey' => $extra['apiKey'] ?? '',
            'action' => 'ai_tutor_chat',
            'courseName' => $in['coursename'],
            // Memory travels in priorTutorMemory, so it is left out here rather than sent twice.
            'courseContext' => tutorprompt::context(array_merge($in, ['memory' => ''])),
            'question' => $in['question'],
            'userId' => $extra['userId'] ?? 0,
            'courseId' => $extra['courseId'] ?? 0,
            'activityName' => $in['activityname'] !== '' ? $in['activityname'] : null,
            'activityType' => $extra['activityType'] ?? null,
            'sectionName' => $in['sectionname'] !== '' ? $in['sectionname'] : null,
            'isFirstMessage' => (bool) $in['isfirstmessage'],
            'studentName' => $in['studentname'] !== '' ? $in['studentname'] : null,
            'pedagogicalGuidelines' => tutorprompt::guidelines($in),
            'priorTutorMemory' => $in['memory'],
            'mode' => $in['isteacher'] ? 'teaching' : 'learning',
            // 3.2.0: tells the service the panel renders Markdown and ```quiz blocks.
            'responseFormat' => 'markdown',
            'questionSlot' => $extra['questionSlot'] ?? null,
            'questionText' => $extra['questionText'] ?? null,
            // 3.2.3 additions.
            'promptVersion' => tutorprompt::VERSION,
            'prompt' => tutorprompt::build($in),
            'conversationHistory' => $history,
            'audience' => $in['audience'],
            'isTeacher' => (bool) $in['isteacher'],
            'courseLanguage' => $in['courselang'],
            'markers' => [
                'refused' => tutorprompt::REFUSAL_MARKER,
                'wellbeing' => tutorprompt::WELLBEING_MARKER,
            ],
        ];
    }

    /**
     * Text as the model should read it: tags removed and HTML entities decoded.
     *
     * The prompt is never rendered as HTML, so "&amp;" would reach the model, and be copied into
     * its answer, as five literal characters.
     *
     * @param string $text Text that may hold markup or entities.
     * @return string
     */
    protected static function plain(string $text): string {
        return html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * The most course content, in characters, that is sent with each question.
     *
     * @return int
     */
    protected static function max_context_chars(): int {
        $value = (int) get_config('format_aicourse', 'tutormaxcontext');
        if ($value <= 0) {
            return self::MAX_CONTEXT_CHARS;
        }
        return max(2000, min(200000, $value));
    }

    /**
     * Who a learner should contact for help with their wellbeing.
     *
     * @return string
     */
    protected static function support_contacts(): string {
        $value = get_config('format_aicourse', 'tutorsupportcontacts');
        if ($value === false) {
            return get_string('tutorsupportcontacts_default', 'format_aicourse');
        }
        return trim((string) $value);
    }

    /**
     * The learner's last few exchanges in the same course and activity, oldest first.
     *
     * Only recent exchanges about the same activity count, and a locked reply is never replayed.
     *
     * @param int $courseid The course.
     * @param int $activityid The course module, or 0.
     * @param int $userid The learner.
     * @return array List of [question, answer] pairs.
     */
    protected static function recent_history(int $courseid, int $activityid, int $userid): array {
        global $DB;

        try {
            $rows = $DB->get_records_select(
                'format_aicourse_chats',
                'courseid = :courseid AND userid = :userid AND activityid = :activityid
                    AND locked = 0 AND timecreated > :since',
                [
                    'courseid' => $courseid,
                    'userid' => $userid,
                    'activityid' => $activityid,
                    'since' => time() - self::HISTORY_WINDOW,
                ],
                'timecreated DESC, id DESC',
                'id, question, response',
                0,
                self::HISTORY_TURNS
            );
        } catch (\dml_exception $e) {
            return [];
        }

        $pairs = [];
        foreach (array_reverse($rows) as $row) {
            $pairs[] = [
                core_text::substr((string) $row->question, 0, 600),
                core_text::substr((string) $row->response, 0, 1500),
            ];
        }
        return $pairs;
    }

    /**
     * The quiz questions of the current activity, read on the server.
     *
     * Question text only, never answers: the same data and safety rules as get_activity_context.
     *
     * @param \cm_info|null $cminfo The visibility-checked course module, or null.
     * @param int $questionslot The slot the learner is on, or 0.
     * @return array [string summary of every question, string text of the current question]
     */
    protected static function server_question_context(?\cm_info $cminfo, int $questionslot): array {
        if (!$cminfo) {
            return ['', ''];
        }
        try {
            $questions = get_activity_context::questions_for($cminfo);
        } catch (\Throwable $e) {
            return ['', ''];
        }
        $summary = [];
        $current = '';
        foreach ($questions as $q) {
            $text = core_text::substr(trim((string) $q['text']), 0, 200);
            $summary[] = 'Q' . (int) $q['slot'] . ': ' . $text;
            if ($questionslot > 0 && (int) $q['slot'] === $questionslot) {
                $current = $text;
            }
        }
        return [implode(' | ', $summary), $current];
    }

    /**
     * Whether the learner's first name may be sent to the service.
     *
     * Off for primary school audiences always, and site-wide when the administrator says so.
     *
     * @param string $audience The course's tutor audience.
     * @return bool
     */
    protected static function may_send_first_name(string $audience): bool {
        if ($audience === 'primary') {
            return false;
        }
        $value = get_config('format_aicourse', 'tutorsendfirstname');
        return $value === false || !empty($value);
    }

    /**
     * The course's tutor audience: adult, secondary or primary.
     *
     * @param \stdClass $course The course.
     * @return string
     */
    protected static function course_audience(\stdClass $course): string {
        $value = (string) (course_get_format($course)->get_format_options()['tutoraudience'] ?? 'adult');
        return in_array($value, tutorprompt::AUDIENCES, true) ? $value : 'adult';
    }

    /**
     * The display name of the course's language, e.g. "English".
     *
     * @param \stdClass $course The course.
     * @return string
     */
    protected static function course_language_name(\stdClass $course): string {
        global $CFG;
        $code = !empty($course->lang) ? $course->lang : ($CFG->lang ?? 'en');
        $names = get_string_manager()->get_list_of_translations();
        $name = $names[$code] ?? 'English';
        // Strip the "(en)" code suffix Moodle adds.
        return trim(preg_replace('/[\s\x{200E}\x{200F}]*\(.*$/u', '', $name));
    }

    /**
     * Up to five teacher corrections for this activity (or the course page), newest first.
     *
     * Teachers correct tutor answers in the AI Tutor report. Feeding those corrections back in
     * closes the loop: the next learner who asks about the same thing gets the teacher's version.
     *
     * @param int $courseid The course.
     * @param int $activityid The course module, or 0 for the course and section pages.
     * @return array List of [question, correction] pairs.
     */
    protected static function teacher_corrections(int $courseid, int $activityid): array {
        global $DB;

        try {
            $rows = $DB->get_records_select(
                'format_aicourse_chats',
                'courseid = :courseid AND activityid = :activityid AND '
                    . $DB->sql_isnotempty('format_aicourse_chats', 'correction', true, true),
                ['courseid' => $courseid, 'activityid' => $activityid],
                'timecorrected DESC, id DESC',
                'id, question, correction',
                0,
                5
            );
        } catch (\dml_exception $e) {
            return [];
        }
        $pairs = [];
        foreach ($rows as $row) {
            $pairs[] = [
                core_text::substr(trim((string) $row->question), 0, 300),
                core_text::substr(trim((string) $row->correction), 0, 300),
            ];
        }
        return $pairs;
    }

    /**
     * Whether the learner is part-way through a graded attempt at this quiz.
     *
     * Practice quizzes (maximum grade 0) and teacher previews stay open.
     *
     * @param \cm_info $cminfo The visibility-checked course module.
     * @return bool
     */
    protected static function is_graded_quiz_in_progress(\cm_info $cminfo): bool {
        global $DB, $USER;

        if ($cminfo->modname !== 'quiz') {
            return false;
        }
        try {
            $grade = (float) $DB->get_field('quiz', 'grade', ['id' => $cminfo->instance]);
            if ($grade <= 0) {
                return false;
            }
            return $DB->record_exists('quiz_attempts', [
                'quiz' => $cminfo->instance,
                'userid' => $USER->id,
                'state' => 'inprogress',
                'preview' => 0,
            ]);
        } catch (\dml_exception $e) {
            return false;
        }
    }

    /**
     * Whether a teacher has switched the tutor off for this activity with the tag "ai-tutor-off".
     *
     * @param \cm_info $cminfo The course module.
     * @return bool
     */
    public static function is_tagged_off(\cm_info $cminfo): bool {
        try {
            return \core_tag_tag::is_item_tagged_with('core', 'course_modules', $cminfo->id, self::TAG_OFF);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
