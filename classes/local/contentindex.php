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

namespace format_aicourse\local;

use cache;
use context_module;
use core_text;
use Exception;
use ZipArchive;

/**
 * Builds the plain-text index of a course that the AI Tutor answers from.
 *
 * Stateless service class: every method is static and none of them produce output.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contentindex {
    /** @var int Site setting: assessment answer keys are never sent, in any course. */
    public const SHARE_NEVER = 0;

    /** @var int Site setting: assessment answer keys are sent for every course on the site. */
    public const SHARE_ALWAYS = 1;

    /** @var int Site setting: each course decides for itself, through its own course setting. */
    public const SHARE_PERCOURSE = 2;

    /**
     * Largest file, in bytes, that will be read into memory for text extraction.
     *
     * ACF-FIX-2.1.4: every extraction path below calls stored_file::get_content(), which loads
     * the WHOLE file into a PHP string. Without a ceiling a single large course resource
     * exhausts memory_limit and fatals the AI Tutor request that happened to trigger the index
     * build. Files above this size contribute a filename placeholder instead of their text.
     *
     * @var int
     */
    public const MAX_EXTRACT_BYTES = 10485760;

    /**
     * In-request cache of built course indexes, keyed on "courseid_userid".
     *
     * @var array<string, array>
     */
    private static $requestcache = [];

    /**
     * Whether assessment answer keys may be included in the index sent to the AI service.
     *
     * ACF-FIX-2.1: the index the AI Tutor answers from is transmitted to an external service.
     * It used to include, unconditionally, the correct-answer marker for every multiple choice
     * question in the course, the per-option feedback that usually gives the answer away, and
     * the essay "information for graders" marking guide -- which is teacher-only data that
     * Moodle never shows a student.
     *
     * Sending an assessment's answer key to a third party cannot be a default. It is opt in,
     * defaults to OFF, and the setting says plainly what it does. With it off the tutor still
     * receives every question's TEXT, so it can discuss the topic and point a learner at the
     * right material -- it simply does not hold the key.
     *
     * ACF-FIX-2.1.1: a single course (a revision course, say) can now opt in without the whole
     * site doing so. THE SITE SETTING IS THE CEILING and the resolution is deliberately
     * one-directional:
     *
     *  - SHARE_NEVER (0, the default)  -> never share, whatever the course says;
     *  - SHARE_ALWAYS (1)              -> always share, whatever the course says. This is the
     *                                     value stored by a site that ticked the old checkbox,
     *                                     so no site's behaviour changes on upgrade;
     *  - SHARE_PERCOURSE (2)           -> follow the course's own shareassessmentanswers option.
     *
     * A course can therefore never opt into something the site has not permitted, and a caller
     * that cannot name a course gets the safe answer: do not share.
     *
     * @param int|null $courseid The course whose own setting applies, or null when the caller has
     *                           no course in hand -- in which case answers are never shared.
     * @return bool True when answer keys may be included for this course.
     */
    public static function may_share_assessment_answers(?int $courseid = null): bool {
        $sitesetting = (int) get_config('format_aicourse', 'shareassessmentanswers');

        if ($sitesetting === self::SHARE_ALWAYS) {
            return true;
        }
        if ($sitesetting !== self::SHARE_PERCOURSE) {
            // SHARE_NEVER, and any unrecognised stored value, means never.
            return false;
        }
        if (empty($courseid)) {
            // The site has delegated the decision to each course and no course was supplied.
            // There is no course to ask, so the answer is the safe one.
            return false;
        }

        try {
            // Core caches its format instances per course id for the request, and each instance
            // caches its own options, so this does not repeat a query on every call.
            $options = course_get_format($courseid)->get_format_options();
        } catch (Exception $e) {
            // A course that no longer exists, or one whose format cannot be loaded, is not a
            // course that has opted in.
            return false;
        }

        return !empty($options['shareassessmentanswers']);
    }

    /**
     * Purge the cross-request course content cache for a specific course.
     *
     * Call this whenever course content changes (module added/deleted/updated).
     *
     * @param int $courseid Course id whose cached index is now stale.
     * @return void
     */
    public static function purge_content_cache($courseid) {
        try {
            $mucache = cache::make('format_aicourse', 'coursecontent');
            // Purge all user-keyed entries for this course by iterating possible keys.
            // Since we can't enumerate keys, purge the whole store when content changes.
            $mucache->purge();
        } catch (Exception $e) {
            // Cache may not be available yet (e.g. during install). Silently continue.
            $mucache = null;
        }
    }

    /**
     * Build (or fetch from cache) the text index of a course for the AI Tutor.
     *
     * @param \stdClass $course Course record.
     * @return array Index with keys course_name, course_summary, sections and activities.
     */
    public static function get_course_content_for_ai($course) {
        global $DB, $USER;

        // Resolved once per build, for THIS course. Assessment answer keys are only ever included
        // when the site setting permits it and, where the site delegates the decision, the course
        // has opted in as well -- see may_share_assessment_answers().
        $courseid = (int) $course->id;
        $shareanswers = self::may_share_assessment_answers($courseid);

        // The setting changes what the index CONTAINS, so it has to change the cache key too.
        // Without this, flipping the setting would keep serving the previously built index for
        // up to the cache TTL -- which in the dangerous direction means continuing to send
        // answer keys after an administrator has turned the setting off. The value baked into the
        // key is the EFFECTIVE one (site setting resolved against this course's own setting), not
        // the site value, so a course that opts in and a course that does not can never be served
        // each other's index.
        $cachekey = $courseid . '_' . (int)$USER->id . '_' . ($shareanswers ? 'a1' : 'a0');

        // Layer 1: static in-request cache — zero overhead for repeated calls within one request.
        if (isset(self::$requestcache[$cachekey])) {
            return self::$requestcache[$cachekey];
        }

        // Layer 2: Moodle MUC cross-request cache (10-minute TTL, defined in db/caches.php).
        // This is the primary win: the first chat message builds the index; every subsequent
        // chat message in the same session (and across sessions within 10 min) pays zero DB cost.
        try {
            $mucache = cache::make('format_aicourse', 'coursecontent');
            $cached = $mucache->get($cachekey);
            if ($cached !== false && is_array($cached)) {
                self::$requestcache[$cachekey] = $cached;
                return $cached;
            }
        } catch (Exception $e) {
            // Cache unavailable — continue without it.
            $mucache = null;
        }

        $content = [];
        $content['course_name'] = format_string($course->fullname);
        $content['course_summary'] = format_string($course->summary);
        $content['sections'] = [];
        $content['activities'] = [];

        $modinfo = get_fast_modinfo($course, $USER->id);

        // Get sections.
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->visible) {
                continue;
            }
            $sectiondata = [
                'name' => get_section_name($course, $section),
                'summary' => format_string($section->summary),
            ];
            $content['sections'][] = $sectiondata;
        }

        // Get activities.
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || !$cm->url) {
                continue;
            }

            $activitydata = [
                'name' => format_string($cm->name),
                'type' => $cm->modname,
                'content' => '',
            ];

            // Get content from different module types.
            switch ($cm->modname) {
                case 'page':
                    $page = $DB->get_record('page', ['id' => $cm->instance]);
                    if ($page) {
                        $activitydata['content'] = strip_tags($page->content);
                    }
                    break;

                case 'book':
                    $chapters = $DB->get_records('book_chapters', ['bookid' => $cm->instance], 'pagenum ASC');
                    $booktext = '';
                    foreach ($chapters as $chapter) {
                        $booktext .= strip_tags($chapter->title) . ': ' . strip_tags($chapter->content) . ' ';
                    }
                    $activitydata['content'] = $booktext;
                    break;

                case 'label':
                    $label = $DB->get_record('label', ['id' => $cm->instance]);
                    if ($label) {
                        $activitydata['content'] = strip_tags($label->intro);
                    }
                    break;

                case 'lesson':
                    $pages = $DB->get_records('lesson_pages', ['lessonid' => $cm->instance]);
                    $lessontext = '';
                    foreach ($pages as $page) {
                        $lessontext .= strip_tags($page->title) . ': ' . strip_tags($page->contents) . ' ';
                    }
                    $activitydata['content'] = $lessontext;
                    break;

                case 'contentcreator':
                    $record = $DB->get_record('contentcreator', ['id' => $cm->instance]);
                    if ($record) {
                        $activitydata['content'] = strip_tags($record->intro ?? '');
                        if (!empty($record->manifestjson)) {
                            $manifest = json_decode($record->manifestjson, true);
                            if (is_array($manifest)) {
                                $activitydata['content'] .= self::extract_cc_manifest($manifest, $courseid);
                            }
                        }
                    }
                    break;

                // ACF-FIX-2.0: merged two byte-identical case blocks into one
                // fallthrough. Both read from the same {aiknowledgecheck} table.
                case 'knowledgecheck':
                case 'aiknowledgecheck':
                    $record = $DB->get_record('aiknowledgecheck', ['id' => $cm->instance]);
                    if ($record) {
                        $activitydata['content'] = strip_tags($record->intro ?? '');
                        $questions = $DB->get_records(
                            'aiknowledgecheck_questions',
                            ['aiknowledgecheckid' => $cm->instance]
                        );
                        $qnum = 1;
                        foreach ($questions as $q) {
                            $activitydata['content'] .= "\nQ{$qnum}: " . strip_tags($q->questiontext ?? '');
                            if ($shareanswers) {
                                for ($i = 1; $i <= 4; $i++) {
                                    $afield = "answer{$i}";
                                    if (!empty($q->$afield)) {
                                        $marker = ((int)($q->correctanswer ?? 0) === $i) ? ' [CORRECT]' : '';
                                        $activitydata['content'] .= " | Option {$i}: "
                                            . strip_tags($q->$afield) . $marker;
                                    }
                                }
                                for ($i = 1; $i <= 4; $i++) {
                                    $efield = "feedback{$i}";
                                    if (!empty($q->$efield)) {
                                        $activitydata['content'] .= " | Explanation {$i}: "
                                            . strip_tags($q->$efield);
                                    }
                                }
                            }
                            $qnum++;
                        }
                    }
                    break;

                case 'aiactivities':
                    $record = $DB->get_record('aiactivities', ['id' => $cm->instance]);
                    if ($record) {
                        $activitydata['content'] = strip_tags($record->intro ?? '');
                        if (!empty($record->activitiesjson)) {
                            $activities = json_decode($record->activitiesjson, true);
                            if (is_array($activities)) {
                                $activitydata['content'] .= self::extract_aiactivities($activities, $courseid);
                            }
                        }
                    }
                    break;

                case 'aivideoactivity':
                    $record = $DB->get_record('aivideoactivity', ['id' => $cm->instance]);
                    if ($record) {
                        $activitydata['content'] = strip_tags($record->intro ?? '');
                        if (!empty($record->transcripttext)) {
                            $activitydata['content'] .= "\n[TRANSCRIPT]: "
                                . core_text::substr(strip_tags($record->transcripttext), 0, 5000);
                        }
                        $vaquestions = $DB->get_records(
                            'aivideoactivity_questions',
                            ['aivideoactivityid' => $cm->instance]
                        );
                        $qnum = 1;
                        foreach ($vaquestions as $vq) {
                            if (!empty($vq->questiondata)) {
                                $qdata = json_decode($vq->questiondata, true);
                                if (is_array($qdata)) {
                                    $activitydata['content'] .= self::extract_va_question($qdata, $qnum, $courseid);
                                }
                            } else if (!empty($vq->questiontext)) {
                                $activitydata['content'] .= "\nQ{$qnum}: " . strip_tags($vq->questiontext);
                                if ($shareanswers) {
                                    for ($i = 1; $i <= 4; $i++) {
                                        $af = "answer{$i}";
                                        if (!empty($vq->$af)) {
                                            $marker = ((int)($vq->correctanswer ?? 0) === $i)
                                                ? ' [CORRECT]' : '';
                                            $activitydata['content'] .= " | Option {$i}: "
                                                . strip_tags($vq->$af) . $marker;
                                        }
                                    }
                                }
                            }
                            $qnum++;
                        }
                    }
                    break;

                case 'aiquiz':
                    // AI Quiz module.
                    $record = $DB->get_record('aiquiz', ['id' => $cm->instance]);
                    if ($record) {
                        $activitydata['content'] = strip_tags($record->intro ?? '');
                        // Get quiz questions.
                        $questions = $DB->get_records('aiquiz_questions', ['aiquizid' => $cm->instance]);
                        foreach ($questions as $q) {
                            $activitydata['content'] .= ' Q: ' . strip_tags($q->questiontext ?? '') . ' ';
                        }
                    }
                    break;

                case 'practicalassessment':
                    // AI Practical Assessment module.
                    $record = $DB->get_record('practicalassessment', ['id' => $cm->instance]);
                    if ($record) {
                        $activitydata['content'] = strip_tags($record->intro ?? '');
                        // Get assessment criteria.
                        $criteria = $DB->get_records(
                            'practicalassessment_criteria',
                            ['practicalassessmentid' => $cm->instance]
                        );
                        foreach ($criteria as $c) {
                            $activitydata['content'] .= ' Criterion: ' . strip_tags($c->criteriontext ?? '') . ' ';
                        }
                    }
                    break;

                case 'assign':
                    // Moodle Assignment - get instructions and activity completion settings.
                    $assign = $DB->get_record('assign', ['id' => $cm->instance]);
                    if ($assign) {
                        $activitydata['content'] = strip_tags($assign->intro ?? '');
                        if (!empty($assign->activity)) {
                            $activitydata['content'] .= ' ' . strip_tags($assign->activity);
                        }
                    }
                    break;

                case 'resource':
                    // File resource - get description and try to extract text from files.
                    $resource = $DB->get_record('resource', ['id' => $cm->instance]);
                    if ($resource) {
                        $activitydata['content'] = strip_tags($resource->intro ?? '');
                        // Get file content if it's a text-based file.
                        $activitydata['content'] .= self::extract_file_content($cm);
                    }
                    break;

                case 'folder':
                    // Folder - get description and list files.
                    $folder = $DB->get_record('folder', ['id' => $cm->instance]);
                    if ($folder) {
                        $activitydata['content'] = strip_tags($folder->intro ?? '');
                        // Try to extract content from files in folder.
                        $activitydata['content'] .= self::extract_folder_content($cm);
                    }
                    break;

                case 'url':
                    // URL resource - get name and description.
                    $url = $DB->get_record('url', ['id' => $cm->instance]);
                    if ($url) {
                        $activitydata['content'] = strip_tags($url->intro ?? '');
                        $activitydata['content'] .= ' URL: ' . $url->externalurl;
                    }
                    break;

                case 'glossary':
                    // Glossary - get terms and definitions.
                    $glossary = $DB->get_record('glossary', ['id' => $cm->instance]);
                    if ($glossary) {
                        $activitydata['content'] = strip_tags($glossary->intro ?? '');
                        // Limit to 50 entries to avoid bloating the AI context payload on large glossaries.
                        $entries = $DB->get_records(
                            'glossary_entries',
                            ['glossaryid' => $cm->instance],
                            'timemodified DESC',
                            'id,concept,definition',
                            0,
                            50
                        );
                        foreach ($entries as $entry) {
                            $activitydata['content'] .= ' Term: ' . strip_tags($entry->concept)
                                . ' - ' . strip_tags($entry->definition) . ' ';
                        }
                    }
                    break;

                case 'wiki':
                    // Wiki - get all pages.
                    $wiki = $DB->get_record('wiki', ['id' => $cm->instance]);
                    if ($wiki) {
                        $activitydata['content'] = strip_tags($wiki->intro ?? '');
                        // Get wiki subwikis and pages.
                        $subwikis = $DB->get_records('wiki_subwikis', ['wikiid' => $cm->instance]);
                        foreach ($subwikis as $subwiki) {
                            $pages = $DB->get_records(
                                'wiki_pages',
                                ['subwikiid' => $subwiki->id],
                                '',
                                'id,title,cachedcontent'
                            );
                            foreach ($pages as $page) {
                                $activitydata['content'] .= ' Page: ' . strip_tags($page->title)
                                    . ' - ' . strip_tags($page->cachedcontent ?? '') . ' ';
                            }
                        }
                    }
                    break;

                case 'forum':
                    // Forum - get intro and recent discussions.
                    $forum = $DB->get_record('forum', ['id' => $cm->instance]);
                    if ($forum) {
                        $activitydata['content'] = 'Forum: ' . strip_tags($forum->intro ?? '');
                        // Get recent discussion topics (not all posts to avoid too much content).
                        $discussions = $DB->get_records(
                            'forum_discussions',
                            ['forum' => $cm->instance],
                            'timemodified DESC',
                            'id,name',
                            0,
                            10
                        );
                        foreach ($discussions as $disc) {
                            $activitydata['content'] .= ' Topic: ' . strip_tags($disc->name) . ' ';
                        }
                    }
                    break;

                case 'quiz':
                    $quiz = $DB->get_record('quiz', ['id' => $cm->instance]);
                    if ($quiz) {
                        $activitydata['content'] = strip_tags($quiz->intro ?? '');
                        // Batch load all questions for this quiz in a single JOIN query.
                        // ACF-FIX-2.0: dropped the table_exists('question_references') probe and its
                        // Moodle 3.x quiz_slots.questionid fallback. question_references exists in
                        // every Moodle release this plugin supports, so the probe only cost a schema
                        // round-trip per quiz and the fallback referenced a column that is long gone.
                        $quizcontext = context_module::instance($cm->id);
                        $sql = "SELECT qs.slot, q.id, q.questiontext, q.qtype
                                  FROM {quiz_slots} qs
                                  JOIN {question_references} qr
                                    ON qr.component = 'mod_quiz'
                                   AND qr.questionarea = 'slot'
                                   AND qr.usingcontextid = :ctxid
                                   AND qr.itemid = qs.id
                                  JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                                  JOIN {question} q ON q.id = qv.questionid
                                 WHERE qs.quizid = :quizid
                              ORDER BY qs.slot ASC, qv.version DESC";
                        $quizquestions = $DB->get_records_sql($sql, [
                            'ctxid'  => $quizcontext->id,
                            'quizid' => $cm->instance,
                        ]);
                        // Batch load multichoice answers + essay options in one query each.
                        $qids = array_column(array_values($quizquestions), 'id');
                        $allanswers = [];
                        $allessayopts = [];
                        if (!empty($qids)) {
                            [$insql, $inparams] = $DB->get_in_or_equal($qids, SQL_PARAMS_NAMED, 'qid');
                            $allanswers = $DB->get_records_select(
                                'question_answers',
                                "question $insql",
                                $inparams,
                                'question, id ASC',
                                'id,question,answer,fraction,feedback'
                            );
                            $allessayopts = $DB->get_records_select(
                                'qtype_essay_options',
                                "questionid $insql",
                                $inparams,
                                '',
                                'questionid,graderinfo'
                            );
                        }
                        // Index answers and essay opts by question id.
                        $answersbyq = [];
                        foreach ($allanswers as $ans) {
                            $answersbyq[$ans->question][] = $ans;
                        }
                        $qnum = 1;
                        // Deduplicate by slot (JOIN may return multiple versions).
                        $seenslots = [];
                        foreach ($quizquestions as $question) {
                            if (isset($seenslots[$question->slot])) {
                                continue;
                            }
                            $seenslots[$question->slot] = true;
                            $activitydata['content'] .= "\nQ{$qnum}: " . strip_tags($question->questiontext ?? '');
                            if ($question->qtype === 'essay') {
                                if (
                                    $shareanswers
                                        && isset($allessayopts[$question->id])
                                        && !empty($allessayopts[$question->id]->graderinfo)
                                ) {
                                    // The graderinfo field is Moodle's "information for graders": teacher
                                    // only text a student is never shown. Opt in required.
                                    $activitydata['content'] .= "\n[ESSAY MARKING GUIDE]: "
                                        . strip_tags($allessayopts[$question->id]->graderinfo);
                                }
                            } else if (
                                $shareanswers
                                && $question->qtype === 'multichoice'
                                && !empty($answersbyq[$question->id])
                            ) {
                                $optnum = 1;
                                foreach ($answersbyq[$question->id] as $ans) {
                                    $marker = ((float)($ans->fraction ?? 0) > 0) ? ' [CORRECT]' : '';
                                    $activitydata['content'] .= " | Option {$optnum}: "
                                        . strip_tags($ans->answer ?? '') . $marker;
                                    if (!empty($ans->feedback)) {
                                        $activitydata['content'] .= " (Feedback: "
                                            . strip_tags($ans->feedback) . ")";
                                    }
                                    $optnum++;
                                }
                            }
                            $qnum++;
                        }
                    }
                    break;

                case 'h5pactivity':
                    // H5P interactive content.
                    $h5p = $DB->get_record('h5pactivity', ['id' => $cm->instance]);
                    if ($h5p) {
                        $activitydata['content'] = strip_tags($h5p->intro ?? '');
                    }
                    break;

                case 'scorm':
                    // SCORM package.
                    $scorm = $DB->get_record('scorm', ['id' => $cm->instance]);
                    if ($scorm) {
                        $activitydata['content'] = strip_tags($scorm->intro ?? '');
                    }
                    break;

                case 'data':
                    // Database activity - get fields and some entries.
                    $data = $DB->get_record('data', ['id' => $cm->instance]);
                    if ($data) {
                        $activitydata['content'] = strip_tags($data->intro ?? '');
                        // Get field names.
                        $fields = $DB->get_records(
                            'data_fields',
                            ['dataid' => $cm->instance],
                            '',
                            'id,name,description'
                        );
                        foreach ($fields as $field) {
                            $activitydata['content'] .= ' Field: ' . strip_tags($field->name) . ' ';
                        }
                    }
                    break;

                case 'choice':
                    // Choice activity - get options.
                    $choice = $DB->get_record('choice', ['id' => $cm->instance]);
                    if ($choice) {
                        $activitydata['content'] = strip_tags($choice->intro ?? '');
                        $options = $DB->get_records('choice_options', ['choiceid' => $cm->instance]);
                        foreach ($options as $option) {
                            $activitydata['content'] .= ' Option: ' . strip_tags($option->text ?? '') . ' ';
                        }
                    }
                    break;

                case 'feedback':
                    // Feedback activity - get items.
                    $feedback = $DB->get_record('feedback', ['id' => $cm->instance]);
                    if ($feedback) {
                        $activitydata['content'] = strip_tags($feedback->intro ?? '');
                        $items = $DB->get_records(
                            'feedback_item',
                            ['feedback' => $cm->instance],
                            'position',
                            'id,name,label'
                        );
                        foreach ($items as $item) {
                            $activitydata['content'] .= ' Question: '
                                . strip_tags($item->name ?? $item->label ?? '') . ' ';
                        }
                    }
                    break;

                case 'survey':
                    // Survey activity.
                    $survey = $DB->get_record('survey', ['id' => $cm->instance]);
                    if ($survey) {
                        $activitydata['content'] = strip_tags($survey->intro ?? '');
                    }
                    break;

                case 'workshop':
                    // Workshop activity.
                    $workshop = $DB->get_record('workshop', ['id' => $cm->instance]);
                    if ($workshop) {
                        $activitydata['content'] = strip_tags($workshop->intro ?? '');
                        if (!empty($workshop->instructauthors)) {
                            $activitydata['content'] .= ' Instructions: '
                                . strip_tags($workshop->instructauthors);
                        }
                    }
                    break;

                case 'chat':
                    // Chat activity.
                    $chat = $DB->get_record('chat', ['id' => $cm->instance]);
                    if ($chat) {
                        $activitydata['content'] = strip_tags($chat->intro ?? '');
                    }
                    break;

                case 'lti':
                    // External tool (LTI).
                    $lti = $DB->get_record('lti', ['id' => $cm->instance]);
                    if ($lti) {
                        $activitydata['content'] = strip_tags($lti->intro ?? '');
                    }
                    break;

                default:
                    // Generic intro content for other modules.
                    $modtable = $cm->modname;
                    if ($DB->get_manager()->table_exists($modtable)) {
                        $record = $DB->get_record($modtable, ['id' => $cm->instance]);
                        if ($record && isset($record->intro)) {
                            $activitydata['content'] = strip_tags($record->intro);
                        }
                    }
            }

            // Trim content to reasonable length (8KB per activity to capture full detail).
            if (core_text::strlen($activitydata['content']) > 8000) {
                $activitydata['content'] = core_text::substr($activitydata['content'], 0, 8000)
                    . '...[content truncated]';
            }

            $content['activities'][] = $activitydata;
        }

        // Populate both cache layers before returning.
        self::$requestcache[$cachekey] = $content;
        if (isset($mucache) && $mucache !== null) {
            try {
                $mucache->set($cachekey, $content);
            } catch (Exception $e) {
                // Cache write failure is non-fatal.
                unset($e);
            }
        }
        return $content;
    }

    /**
     * Extract text content from a file resource.
     *
     * Handles PDF, Word (.docx), text files, HTML, etc.
     *
     * @param \cm_info $cm Course module of the mod_resource instance.
     * @param int $maxchars Maximum characters to return.
     * @return string Extracted text.
     */
    public static function extract_file_content($cm, $maxchars = 3000) {
        $fs = get_file_storage();
        $context = context_module::instance($cm->id);
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);

        $content = '';
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }

            $filename = $file->get_filename();
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // ACF-FIX-2.1.4: refuse to read an oversized file into memory. Note the filename so
            // the tutor still knows the resource exists.
            if ($file->get_filesize() > self::MAX_EXTRACT_BYTES) {
                $content .= ' [File: ' . $filename . '] ';
                continue;
            }

            // Handle different file types.
            if (in_array($extension, ['txt', 'md', 'csv', 'html', 'htm', 'xml', 'json'])) {
                // Plain text files - read directly.
                $text = $file->get_content();
                $content .= ' [File: ' . $filename . '] ' . strip_tags($text);
            } else if ($extension === 'pdf') {
                // PDF files - extract text using simple method.
                $content .= self::extract_pdf_text($file, $maxchars);
            } else if ($extension === 'docx') {
                // Word documents - extract text from XML.
                $content .= self::extract_docx_text($file, $maxchars);
            } else if (in_array($extension, ['doc', 'odt', 'rtf'])) {
                // Other document formats - just note the filename.
                $content .= ' [Document: ' . $filename . '] ';
            } else if (in_array($extension, ['pptx', 'ppt', 'odp'])) {
                // Presentations - note the filename.
                $content .= ' [Presentation: ' . $filename . '] ';
            } else if (in_array($extension, ['xlsx', 'xls', 'ods'])) {
                // Spreadsheets - note the filename.
                $content .= ' [Spreadsheet: ' . $filename . '] ';
            } else {
                // Other files - note the filename.
                $content .= ' [File: ' . $filename . '] ';
            }

            // Limit content per file.
            if (core_text::strlen($content) > $maxchars) {
                $content = core_text::substr($content, 0, $maxchars) . '...';
                break;
            }
        }

        return $content;
    }

    /**
     * Extract text content from files in a folder.
     *
     * @param \cm_info $cm Course module of the mod_folder instance.
     * @param int $maxchars Maximum characters to return.
     * @return string Extracted text.
     */
    public static function extract_folder_content($cm, $maxchars = 2000) {
        $fs = get_file_storage();
        $context = context_module::instance($cm->id);
        $files = $fs->get_area_files($context->id, 'mod_folder', 'content', 0, 'sortorder, filename', false);

        $content = '';
        $filecount = 0;
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $filecount++;

            $filename = $file->get_filename();
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // For text files, try to read content, unless the file is too large to hold in memory.
            if (
                in_array($extension, ['txt', 'md', 'csv', 'html', 'htm'])
                && $file->get_filesize() <= self::MAX_EXTRACT_BYTES
            ) {
                $text = $file->get_content();
                $content .= ' [' . $filename . ': ' . strip_tags(core_text::substr($text, 0, 500)) . '] ';
            } else {
                $content .= ' [File: ' . $filename . '] ';
            }

            if (core_text::strlen($content) > $maxchars) {
                break;
            }
            if ($filecount > 20) {
                // Limit to 20 files.
                break;
            }
        }

        return $content;
    }

    /**
     * Extract text from a PDF file (simple extraction).
     *
     * @param \stored_file $file The stored PDF file.
     * @param int $maxchars Maximum characters to return.
     * @return string Extracted text, or a filename placeholder.
     */
    public static function extract_pdf_text($file, $maxchars = 2000) {
        if ($file->get_filesize() > self::MAX_EXTRACT_BYTES) {
            return ' [PDF: ' . $file->get_filename() . '] ';
        }

        try {
            $content = $file->get_content();
            $text = '';

            // Simple PDF text extraction - look for text streams.
            // This is a basic approach that works for many PDFs.
            if (preg_match_all('/\(([^)]+)\)/', $content, $matches)) {
                foreach ($matches[1] as $match) {
                    // Filter out binary/encoded content.
                    if (ctype_print($match) && strlen($match) > 2) {
                        $text .= $match . ' ';
                    }
                    if (strlen($text) > $maxchars) {
                        break;
                    }
                }
            }

            // If we got meaningful text, return it.
            if (strlen(trim($text)) > 50) {
                return ' [PDF content: ' . core_text::substr(trim($text), 0, $maxchars) . '] ';
            }

            // Fallback - just note the filename.
            return ' [PDF: ' . $file->get_filename() . '] ';
        } catch (Exception $e) {
            return ' [PDF: ' . $file->get_filename() . '] ';
        }
    }

    /**
     * Extract text from a Word .docx file.
     *
     * @param \stored_file $file The stored .docx file.
     * @param int $maxchars Maximum characters to return.
     * @return string Extracted text, or a filename placeholder.
     */
    public static function extract_docx_text($file, $maxchars = 2000) {
        if ($file->get_filesize() > self::MAX_EXTRACT_BYTES) {
            return ' [Word: ' . $file->get_filename() . '] ';
        }

        // ACF-FIX-2.1.4: make_request_directory() replaces tempnam(sys_get_temp_dir(), ...).
        // Two reasons. Moodle requires plugin temp files to live under $CFG->tempdir, the only
        // path guaranteed writable and correctly shared on clustered hosting. And the directory
        // is removed automatically at the end of the request, so the file cannot leak -- the
        // previous code called unlink() on the happy path only, so any throw between tempnam()
        // and unlink() left the file behind permanently.
        $tempfile = make_request_directory() . '/extract.docx';
        $zip = null;

        try {
            file_put_contents($tempfile, $file->get_content());
            $text = '';

            // DOCX is a ZIP file - try to extract document.xml.
            $zip = new ZipArchive();
            if ($zip->open($tempfile) === true) {
                $xml = $zip->getFromName('word/document.xml');
                if ($xml) {
                    // Strip XML tags to get plain text.
                    $text = strip_tags($xml);
                    // Clean up whitespace.
                    $text = preg_replace('/\s+/', ' ', $text);
                }
                $zip->close();
                $zip = null;
            }

            if (core_text::strlen(trim($text)) > 50) {
                return ' [Word content: ' . core_text::substr(trim($text), 0, $maxchars) . '] ';
            }

            return ' [Word: ' . $file->get_filename() . '] ';
        } catch (Exception $e) {
            return ' [Word: ' . $file->get_filename() . '] ';
        } finally {
            // An archive left open would hold a file handle until the request ended.
            if ($zip instanceof ZipArchive) {
                $zip->close();
            }
        }
    }

    /**
     * Extract learning content from a Content Creator manifest JSON.
     *
     * Walks the topics → slides → cards structure to build a text summary.
     *
     * @param array $manifest Decoded manifest JSON.
     * @param int|null $courseid Course the manifest belongs to, resolved against the answer
     *                           sharing gate. Null means "no course in hand", i.e. do not share.
     * @return string Text summary.
     */
    public static function extract_cc_manifest($manifest, ?int $courseid = null) {
        $shareanswers = self::may_share_assessment_answers($courseid);
        $text = '';
        $topics = $manifest['topics'] ?? $manifest;
        if (!is_array($topics)) {
            return $text;
        }
        foreach ($topics as $ti => $topic) {
            if (!is_array($topic)) {
                continue;
            }
            $topictitle = $topic['topicTitle'] ?? $topic['title'] ?? $topic['name'] ?? ('Topic ' . ($ti + 1));
            $text .= "\n[TOPIC]: " . strip_tags($topictitle);
            $slides = $topic['slides'] ?? $topic['sections'] ?? $topic['cards'] ?? [];
            if (!is_array($slides)) {
                continue;
            }
            foreach ($slides as $si => $slide) {
                if (!is_array($slide)) {
                    continue;
                }
                $slidetitle = $slide['title'] ?? $slide['heading'] ?? '';
                $slidetype = $slide['type'] ?? $slide['slideType'] ?? 'learning';
                if (!empty($slidetitle)) {
                    $text .= "\n  Slide " . ($si + 1) . " ({$slidetype}): " . strip_tags($slidetitle);
                }
                // Learning card content.
                $body = $slide['body'] ?? $slide['content'] ?? $slide['text'] ?? '';
                if (!empty($body) && is_string($body)) {
                    $text .= " — " . core_text::substr(strip_tags($body), 0, 500);
                }
                // Key points / bullet points.
                $points = $slide['keyPoints'] ?? $slide['bulletPoints'] ?? $slide['points'] ?? [];
                if (is_array($points) && !empty($points)) {
                    foreach ($points as $pt) {
                        if (is_string($pt)) {
                            $text .= " • " . strip_tags($pt);
                        } else if (is_array($pt)) {
                            $text .= " • " . strip_tags($pt['text'] ?? $pt['content'] ?? '');
                        }
                    }
                }
                // Activity cards (quiz-style within CC).
                $question = $slide['question'] ?? $slide['activityQuestion'] ?? '';
                if (!empty($question)) {
                    $text .= "\n    Activity Q: " . strip_tags($question);
                }
                $options = $slide['options'] ?? $slide['answers'] ?? $slide['choices'] ?? [];
                if (is_array($options)) {
                    foreach ($options as $oi => $opt) {
                        if (is_string($opt)) {
                            $text .= " | " . strip_tags($opt);
                        } else if (is_array($opt)) {
                            $text .= " | " . strip_tags($opt['text'] ?? $opt['label'] ?? '');
                        }
                    }
                }
                $correctanswer = $slide['correctAnswer'] ?? $slide['answer'] ?? '';
                if ($shareanswers && !empty($correctanswer) && is_string($correctanswer)) {
                    $text .= " [ANSWER: " . strip_tags($correctanswer) . "]";
                }
                // Documents linked to slide.
                $docs = $slide['documents'] ?? $slide['linkedDocs'] ?? [];
                if (is_array($docs)) {
                    foreach ($docs as $doc) {
                        if (is_array($doc)) {
                            $doctitle = $doc['title'] ?? $doc['name'] ?? '';
                            $doccontent = $doc['content'] ?? $doc['text'] ?? '';
                            if (!empty($doctitle)) {
                                $text .= "\n    Doc: " . strip_tags($doctitle);
                            }
                            if (!empty($doccontent)) {
                                $text .= " — " . core_text::substr(strip_tags($doccontent), 0, 300);
                            }
                        }
                    }
                }
            }
        }
        return $text;
    }

    /**
     * Extract learning content from AI Activities JSON (all 8+ activity types).
     *
     * @param array $activities Decoded activities JSON.
     * @param int|null $courseid Course the activities belong to, resolved against the answer
     *                           sharing gate. Null means "no course in hand", i.e. do not share.
     * @return string Text summary.
     */
    public static function extract_aiactivities($activities, ?int $courseid = null) {
        $shareanswers = self::may_share_assessment_answers($courseid);
        $text = '';
        if (isset($activities['activities'])) {
            $activities = $activities['activities'];
        }
        if (!is_array($activities)) {
            return $text;
        }
        foreach ($activities as $ai => $activity) {
            if (!is_array($activity)) {
                continue;
            }
            $type = $activity['type'] ?? $activity['activityType'] ?? 'unknown';
            $title = $activity['title'] ?? $activity['name'] ?? ('Activity ' . ($ai + 1));
            $instruction = $activity['instruction'] ?? $activity['instructions'] ?? '';
            $text .= "\n[ACTIVITY {$type}]: " . strip_tags($title);
            if (!empty($instruction)) {
                $text .= " — " . strip_tags($instruction);
            }
            // Items (ordering, flashcards, fill-in-blank, etc.).
            $items = $activity['items'] ?? $activity['cards'] ?? $activity['statements']
                ?? $activity['pairs'] ?? $activity['questions'] ?? [];
            if (is_array($items)) {
                foreach ($items as $ii => $item) {
                    if (is_string($item)) {
                        $text .= "\n  " . ($ii + 1) . ". " . strip_tags($item);
                    } else if (is_array($item)) {
                        // Flashcards: front/back.
                        $front = $item['front'] ?? $item['term'] ?? $item['question']
                            ?? $item['text'] ?? $item['statement'] ?? '';
                        $back = $item['back'] ?? $item['definition'] ?? $item['answer']
                            ?? $item['explanation'] ?? '';
                        if (!empty($front)) {
                            $text .= "\n  " . ($ii + 1) . ". " . strip_tags($front);
                        }
                        if (!empty($back)) {
                            $text .= " → " . strip_tags($back);
                        }
                        // Matching pairs.
                        $left = $item['left'] ?? $item['prompt'] ?? '';
                        $right = $item['right'] ?? $item['match'] ?? '';
                        if (!empty($left) && !empty($right)) {
                            $text .= "\n  " . ($ii + 1) . ". " . strip_tags($left) . " ↔ " . strip_tags($right);
                        }
                        // Card select options.
                        $label = $item['label'] ?? $item['cardText'] ?? '';
                        $iscorrect = $item['isCorrect'] ?? $item['correct'] ?? null;
                        if (!empty($label)) {
                            $marker = ($shareanswers
                                && ($iscorrect === true || $iscorrect === 1)) ? ' [CORRECT]' : '';
                            $text .= "\n  " . ($ii + 1) . ". " . strip_tags($label) . $marker;
                        }
                        // True or false flag on a statement item.
                        $tf = $item['isTrue'] ?? null;
                        if ($shareanswers && $tf !== null && !empty($front)) {
                            $text .= ($tf ? ' [TRUE]' : ' [FALSE]');
                        }
                        // Fill in blank.
                        $blank = $item['blank'] ?? $item['blankAnswer'] ?? '';
                        if (!empty($blank)) {
                            $text .= " [BLANK: " . strip_tags($blank) . "]";
                        }
                    }
                }
            }
            // Categories (category sort, column sort).
            $categories = $activity['categories'] ?? $activity['columns'] ?? [];
            if (is_array($categories)) {
                foreach ($categories as $cat) {
                    if (!is_array($cat)) {
                        continue;
                    }
                    $catname = $cat['name'] ?? $cat['title'] ?? $cat['heading'] ?? '';
                    $catitems = $cat['items'] ?? $cat['entries'] ?? [];
                    if (!empty($catname)) {
                        $text .= "\n  Category: " . strip_tags($catname);
                        if (is_array($catitems)) {
                            foreach ($catitems as $ci) {
                                $citext = is_string($ci) ? $ci : ($ci['text'] ?? $ci['label'] ?? '');
                                if (!empty($citext)) {
                                    $text .= ", " . strip_tags($citext);
                                }
                            }
                        }
                    }
                }
            }
            // Correct order (ordering activities).
            $correctorder = $activity['correctOrder'] ?? $activity['order'] ?? [];
            if (is_array($correctorder) && !empty($correctorder)) {
                $text .= "\n  Correct order: ";
                foreach ($correctorder as $oi => $oitem) {
                    $otext = is_string($oitem) ? $oitem : ($oitem['text'] ?? '');
                    $text .= ($oi + 1) . ". " . strip_tags($otext) . " ";
                }
            }
        }
        return $text;
    }

    /**
     * Extract a single Video Activity question from its JSON questiondata blob.
     *
     * @param array $qdata Decoded questiondata JSON.
     * @param int $qnum 1-based question number used in the generated text.
     * @param int|null $courseid Course the question belongs to, resolved against the answer
     *                           sharing gate. Null means "no course in hand", i.e. do not share.
     * @return string Text summary.
     */
    public static function extract_va_question($qdata, $qnum, ?int $courseid = null) {
        $text = '';
        $type = $qdata['type'] ?? $qdata['questionType'] ?? $qdata['activityType'] ?? 'mcq';
        $qtext = $qdata['question'] ?? $qdata['questionText'] ?? $qdata['text'] ?? '';
        if (!empty($qtext)) {
            $text .= "\nQ{$qnum} ({$type}): " . strip_tags($qtext);
        }
        // MCQ options.
        $shareanswers = self::may_share_assessment_answers($courseid);
        $options = $qdata['options'] ?? $qdata['answers'] ?? $qdata['choices'] ?? [];
        if ($shareanswers && is_array($options)) {
            foreach ($options as $oi => $opt) {
                $opttext = is_string($opt) ? $opt : ($opt['text'] ?? $opt['label'] ?? $opt['answer'] ?? '');
                $iscorrect = is_array($opt) ? ($opt['isCorrect'] ?? $opt['correct'] ?? false) : false;
                $marker = $iscorrect ? ' [CORRECT]' : '';
                if (!empty($opttext)) {
                    $text .= " | " . strip_tags($opttext) . $marker;
                }
            }
        }
        $correctanswer = $qdata['correctAnswer'] ?? $qdata['answer'] ?? null;
        if ($shareanswers && $correctanswer !== null && is_numeric($correctanswer) && is_array($options)) {
            $idx = (int)$correctanswer;
            if (isset($options[$idx])) {
                $catext = is_string($options[$idx]) ? $options[$idx] : ($options[$idx]['text'] ?? '');
                $text .= " [CORRECT: " . strip_tags($catext) . "]";
            }
        }
        // Explanation.
        $explanation = $qdata['explanation'] ?? $qdata['feedback'] ?? '';
        if (!empty($explanation) && is_string($explanation)) {
            $text .= " [EXPLANATION: " . strip_tags($explanation) . "]";
        }
        // Matching pairs.
        $pairs = $qdata['pairs'] ?? $qdata['items'] ?? [];
        if ($type === 'matching' && is_array($pairs)) {
            foreach ($pairs as $pi => $pair) {
                if (is_array($pair)) {
                    $left = $pair['left'] ?? $pair['term'] ?? $pair['prompt'] ?? '';
                    $right = $pair['right'] ?? $pair['match'] ?? $pair['definition'] ?? '';
                    $text .= "\n  Pair " . ($pi + 1) . ": " . strip_tags($left) . " ↔ " . strip_tags($right);
                }
            }
        }
        // Statements, each with a true or false flag.
        $statements = $qdata['statements'] ?? [];
        if (is_array($statements)) {
            foreach ($statements as $si => $stmt) {
                if (is_array($stmt)) {
                    $sttext = $stmt['statement'] ?? $stmt['text'] ?? '';
                    $istrue = $stmt['isTrue'] ?? $stmt['correct'] ?? null;
                    $marker = ($istrue === true) ? ' [TRUE]' : (($istrue === false) ? ' [FALSE]' : '');
                    $text .= "\n  " . ($si + 1) . ". " . strip_tags($sttext) . $marker;
                }
            }
        }
        // Flashcards.
        $cards = $qdata['cards'] ?? $qdata['flashcards'] ?? [];
        if (is_array($cards) && ($type === 'flashcards' || $type === 'flashcard')) {
            foreach ($cards as $ci => $card) {
                if (is_array($card)) {
                    $front = $card['front'] ?? $card['term'] ?? '';
                    $back = $card['back'] ?? $card['definition'] ?? '';
                    $text .= "\n  Card " . ($ci + 1) . ": " . strip_tags($front) . " → " . strip_tags($back);
                }
            }
        }
        return $text;
    }
}
