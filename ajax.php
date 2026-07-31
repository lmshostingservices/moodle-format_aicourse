<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once(__DIR__ . '/lib.php');

$action = required_param('action', PARAM_ALPHAEXT);
$courseid = required_param('courseid', PARAM_INT);

// Fetch the course and set up the PAGE context BEFORE require_login().
// require_login() without a course argument internally calls $PAGE->set_course($SITE),
// which resets $PAGE->context to system context — wiping out any prior set_context() call.
// Passing the course object ensures Moodle sets $PAGE->course and $PAGE->context correctly.
$course = get_course($courseid);
$context = context_course::instance($courseid);
$PAGE->set_context($context);
require_login($course);
require_sesskey();

// Release session lock before long-running API calls to prevent blocking other requests.
\core\session\manager::write_close();

header('Content-Type: application/json');

switch ($action) {
    case 'dbdiag':
        // Database diagnostic - check if AI Tutor tables exist
        require_capability('moodle/course:update', $context);
        $dbman = $DB->get_manager();
        
        $results = [
            'chats_table_exists' => $dbman->table_exists('format_aicourse_chats'),
            'memory_table_exists' => $dbman->table_exists('format_aicourse_ai_memory'),
            'chats_columns' => [],
            'memory_columns' => [],
            'plugin_version' => get_config('format_aicourse', 'version'),
            'errors' => []
        ];
        
        // Check chats table columns
        if ($results['chats_table_exists']) {
            try {
                $columns = $DB->get_columns('format_aicourse_chats');
                $results['chats_columns'] = array_keys($columns);
            } catch (Exception $e) {
                $results['errors'][] = 'chats_columns: ' . $e->getMessage();
            }
        }
        
        // Check memory table columns
        if ($results['memory_table_exists']) {
            try {
                $columns = $DB->get_columns('format_aicourse_ai_memory');
                $results['memory_columns'] = array_keys($columns);
            } catch (Exception $e) {
                $results['errors'][] = 'memory_columns: ' . $e->getMessage();
            }
        }
        
        // Check required columns
        $required_chat_cols = ['id', 'courseid', 'userid', 'activityid', 'questionslot', 'question', 'response', 'rating', 'refused', 'locked', 'timecreated'];
        $missing_chat_cols = array_diff($required_chat_cols, $results['chats_columns']);
        if (!empty($missing_chat_cols)) {
            $results['missing_chat_columns'] = array_values($missing_chat_cols);
        }
        
        $required_mem_cols = ['id', 'courseid', 'activityid', 'userid', 'memory', 'timeupdated'];
        $missing_mem_cols = array_diff($required_mem_cols, $results['memory_columns']);
        if (!empty($missing_mem_cols)) {
            $results['missing_memory_columns'] = array_values($missing_mem_cols);
        }
        
        echo json_encode(['success' => true, 'diagnostic' => $results], JSON_PRETTY_PRINT);
        break;
    
    case 'dbrepair':
        // Database repair - create missing tables and columns
        require_capability('moodle/site:config', context_system::instance());
        $dbman = $DB->get_manager();
        $repairs = [];
        
        // Create chats table if missing
        if (!$dbman->table_exists('format_aicourse_chats')) {
            $table = new xmldb_table('format_aicourse_chats');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('questionslot', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('question', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('response', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('rating', XMLDB_TYPE_INTEGER, '2', null, null, null, '0');
            $table->add_field('refused', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
            $table->add_field('locked', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
            $table->add_field('correction', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('correctedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecorrected', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
            $repairs[] = 'Created chats table';
        } else {
            // Add missing columns to chats table
            $table = new xmldb_table('format_aicourse_chats');
            
            $field = new xmldb_field('activityid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'userid');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
                $repairs[] = 'Added activityid column';
            }
            
            $field = new xmldb_field('questionslot', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'activityid');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
                $repairs[] = 'Added questionslot column';
            }
            
            $field = new xmldb_field('refused', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'rating');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
                $repairs[] = 'Added refused column';
            }
            
            $field = new xmldb_field('locked', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'refused');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
                $repairs[] = 'Added locked column';
            }
        }
        
        // Create memory table if missing
        if (!$dbman->table_exists('format_aicourse_ai_memory')) {
            $table = new xmldb_table('format_aicourse_ai_memory');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('memory', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timeupdated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('unique_memory', XMLDB_INDEX_UNIQUE, ['courseid', 'activityid', 'userid']);
            $dbman->create_table($table);
            $repairs[] = 'Created memory table';
        }
        
        echo json_encode([
            'success' => true,
            'repairs' => $repairs,
            'message' => empty($repairs) ? 'No repairs needed' : 'Repairs completed: ' . implode(', ', $repairs)
        ], JSON_PRETTY_PRINT);
        break;
        
    case 'getprogress':
        // Get course progress for AJAX updates (no sesskey required for read-only)
        $progress = format_aicourse_get_progress($course, $USER->id);
        echo json_encode([
            'success' => true,
            'percentage' => (int) $progress['percentage'],
            'completed' => (int) $progress['completed'],
            'total' => (int) $progress['total'],
            'enabled' => (bool) $progress['enabled']
        ]);
        break;
        
    case 'deletesection':
        require_capability('moodle/course:update', $context);
        $sectionid = required_param('sectionid', PARAM_INT);
        
        // Get the section info
        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $sectiontodelete = null;
        
        foreach ($sections as $section) {
            if ($section->id == $sectionid) {
                $sectiontodelete = $section;
                break;
            }
        }
        
        if (!$sectiontodelete) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Section not found']);
            exit;
        }
        
        // Cannot delete section 0
        if ($sectiontodelete->section == 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Cannot delete the general section']);
            exit;
        }
        
        // Use Moodle's course_delete_section function
        require_once($CFG->dirroot . '/course/lib.php');
        $result = course_delete_section($course, $sectiontodelete, true); // true = delete activities too
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete section']);
        }
        break;
    
    case 'duplicatesection':
        require_capability('moodle/course:update', $context);
        $sectionid = required_param('sectionid', PARAM_INT);

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $sectiontoduplicate = null;

        foreach ($sections as $section) {
            if ($section->id == $sectionid) {
                $sectiontoduplicate = $section;
                break;
            }
        }

        if (!$sectiontoduplicate) {
            echo json_encode(['success' => false, 'error' => 'Section not found']);
            exit;
        }

        if ($sectiontoduplicate->section == 0) {
            echo json_encode(['success' => false, 'error' => 'Cannot duplicate the general section']);
            exit;
        }

        require_once($CFG->dirroot . '/course/lib.php');

        $duplicated = false;
        try {
            // Try Moodle's built-in duplicate — requires backup+restore capabilities.
            // Only attempt if the user actually has those caps, otherwise fall through.
            $canbak = has_capability('moodle/backup:backupcourse', $context, null, false) &&
                      has_capability('moodle/restore:restorecourse', $context, null, false);

            if ($canbak && function_exists('course_duplicate_section')) {
                $newsection = course_duplicate_section($course, $sectiontoduplicate);
                echo json_encode(['success' => true, 'newsectionid' => $newsection->id, 'method' => 'builtin']);
                $duplicated = true;
            }
        } catch (Exception $e) {
            // Built-in failed — fall through to manual copy
            $duplicated = false;
        }

        if (!$duplicated) {
            // Fallback: create a new section with the same name/summary.
            // course_create_section() only needs moodle/course:update (already checked).
            try {
                // Insert after the source section
                $position = $sectiontoduplicate->section + 1;
                $newsection = course_create_section($course->id, $position, true);

                // Copy name and summary
                $DB->update_record('course_sections', (object)[
                    'id'            => $newsection->id,
                    'name'          => $sectiontoduplicate->name,
                    'summary'       => $sectiontoduplicate->summary ?: '',
                    'summaryformat' => $sectiontoduplicate->summaryformat ?: FORMAT_HTML,
                ]);

                rebuild_course_cache($course->id, true);
                echo json_encode(['success' => true, 'newsectionid' => $newsection->id, 'method' => 'manual']);
            } catch (Exception $e2) {
                echo json_encode(['success' => false, 'error' => 'Failed to duplicate section: ' . $e2->getMessage()]);
            }
        }
        break;

    case 'addsection':
        require_capability('moodle/course:update', $context);
        require_once($CFG->dirroot . '/course/lib.php');
        try {
            // In Moodle 4.x, course_create_section($courseid, $position, ...) inserts the new
            // section AT the given position number. Passing position=0 tries to insert at slot 0,
            // which is always occupied by the permanent "General" section, causing a unique-key
            // violation ('Duplicate entry xxx-0'). To append a section at the end we must pass
            // MAX(section)+1 so Moodle places it after all existing sections.
            $maxsection = (int)$DB->get_field_sql(
                'SELECT MAX(section) FROM {course_sections} WHERE course = ?',
                [$course->id]
            );
            $newsection = course_create_section($course->id, $maxsection + 1, true);
            rebuild_course_cache($course->id, true);
            echo json_encode(['success' => true, 'newsectionid' => $newsection->id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to add section: ' . $e->getMessage()]);
        }
        break;
        
    case 'saveicon':
        require_capability('moodle/course:update', $context);
        $sectionid = required_param('sectionid', PARAM_INT);
        // optional_param allows empty string — empty = clear/remove the icon.
        // required_param + ALPHANUMEXT would reject '' and break the Remove icon button.
        $icon = optional_param('icon', '', PARAM_ALPHANUMEXT);

        if ($icon !== '') {
            // Non-empty: validate against the icon library
            $iconlibrary = format_aicourse_get_icon_library();
            if (!isset($iconlibrary[$icon])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid icon']);
                exit;
            }
        }
        // Empty string is valid (clears the icon for this section)
        format_aicourse_set_section_icon($courseid, $sectionid, $icon);

        echo json_encode(['success' => true]);
        break;
        
    case 'aichat':
        $question = required_param('question', PARAM_TEXT);
        // Enhanced context for world-class tutor
        $activityid = optional_param('activityid', 0, PARAM_INT);
        $sectionid = optional_param('sectionid', 0, PARAM_INT);
        $isfirstmessage = optional_param('isfirstmessage', 0, PARAM_INT);
        $questionslot = optional_param('questionslot', 0, PARAM_INT);
        $questiontext = optional_param('questiontext', '', PARAM_TEXT);
        $allquestions = optional_param('allquestions', '', PARAM_TEXT);
        
        if (empty($question)) {
            echo json_encode(['success' => false, 'error' => 'Question is required']);
            exit;
        }
        
        // Get Site ID and API Key from central config or fallback
        $siteid = '';
        $apikey = '';
        
        // Try to load central config plugin library if it exists
        $aiconfiglib = $CFG->dirroot . '/local/aiconfig/lib.php';
        if (file_exists($aiconfiglib)) {
            require_once($aiconfiglib);
        }
        
        if (function_exists('local_aiconfig_get_siteid')) {
            $siteid = trim(local_aiconfig_get_siteid('format_aicourse') ?? '');
        }
        if (empty($siteid)) {
            $siteid = trim(get_config('format_aicourse', 'siteid') ?? '');
        }
        
        if (function_exists('local_aiconfig_get_apikey')) {
            $apikey = trim(local_aiconfig_get_apikey('format_aicourse') ?? '');
        }
        if (empty($apikey)) {
            $apikey = trim(get_config('format_aicourse', 'apikey') ?? '');
        }
        
        if (empty($siteid) || empty($apikey)) {
            echo json_encode([
                'success' => false,
                'error' => get_string('aiassistant_notconfigured', 'format_aicourse')
            ]);
            exit;
        }
        
        // Get activity and section context for world-class tutor
        $activityname = null;
        $activitytype = null;
        $sectionname = null;
        $cm = null;
        
        if ($activityid > 0) {
            // Use get_fast_modinfo to get cm_info which has sectionnum property
            $modinfo = get_fast_modinfo($course);
            try {
                $cminfo = $modinfo->get_cm($activityid);
                if ($cminfo) {
                    $activityname = $cminfo->name;
                    $activitytype = $cminfo->modname;
                    $sectionname = get_section_name($course, $cminfo->sectionnum);
                }
            } catch (Exception $e) {
                // Activity might not be visible to user - fall back to basic info
                $cm = get_coursemodule_from_id(null, $activityid, $courseid);
                if ($cm) {
                    $activityname = $cm->name;
                    $activitytype = $cm->modname;
                    // Look up section number from section ID
                    $section = $DB->get_record('course_sections', ['id' => $cm->section], 'section');
                    if ($section) {
                        $sectionname = get_section_name($course, $section->section);
                    }
                }
            }
        } else if ($sectionid > 0) {
            // $sectionid is actually a section number passed from JS
            $sectionname = get_section_name($course, $sectionid);
        }
        
        // AI LOCKOUT: Check if assignment is already submitted (audit-grade integrity)
        $islocked = false;
        // Use $cminfo if available, otherwise fall back to $cm
        $modname = isset($cminfo) && $cminfo ? $cminfo->modname : (isset($cm) && $cm ? $cm->modname : '');
        $cmid = isset($cminfo) && $cminfo ? $cminfo->id : (isset($cm) && $cm ? $cm->id : 0);
        if ($modname === 'assign' && $cmid > 0) {
            $cm = get_coursemodule_from_id('assign', $cmid, $courseid);
            require_once($CFG->dirroot . '/mod/assign/locallib.php');
            $assigncontext = context_module::instance($cm->id);
            $assign = new assign($assigncontext, $cm, $course);
            $submission = $assign->get_user_submission($USER->id, false);
            if ($submission && $submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                $islocked = true;
            }
        }
        
        // If locked, respond with reflection-only message
        if ($islocked) {
            $lockedanswer = get_string('aiassistant_locked', 'format_aicourse');
            // Log the locked attempt
            $chatrecord = new stdClass();
            $chatrecord->courseid = $courseid;
            $chatrecord->userid = $USER->id;
            $chatrecord->activityid = $activityid;
            $chatrecord->question = $question;
            $chatrecord->response = $lockedanswer;
            $chatrecord->rating = 0;
            $chatrecord->refused = 0;
            $chatrecord->locked = 1;
            $chatrecord->timecreated = time();
            $chatid = $DB->insert_record('format_aicourse_chats', $chatrecord);
            
            echo json_encode([
                'success' => true,
                'answer' => $lockedanswer,
                'chatid' => $chatid
            ]);
            exit;
        }
        
        // Load per-activity memory (safe, non-cheaty tutoring context)
        $memory = '';
        if ($activityid > 0) {
            try {
                $memrecord = $DB->get_record('format_aicourse_ai_memory', [
                    'courseid' => $courseid,
                    'activityid' => $activityid,
                    'userid' => $USER->id
                ]);
                if ($memrecord) {
                    $memory = $memrecord->memory;
                }
            } catch (dml_exception $e) {
                // Table might not exist yet - continue without memory
                debugging('AI Tutor memory table not found: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        
        // Get course content for AI context
        $courseContent = format_aicourse_get_course_content_for_ai($course);
        
        // Build context for AI
        $contextText = "Course: " . $courseContent['course_name'] . "\n";
        $contextText .= "Summary: " . $courseContent['course_summary'] . "\n\n";
        
        $contextText .= "Sections:\n";
        foreach ($courseContent['sections'] as $section) {
            $contextText .= "- " . $section['name'] . ": " . $section['summary'] . "\n";
        }
        
        $contextText .= "\nActivities:\n";
        foreach ($courseContent['activities'] as $activity) {
            $contextText .= "- " . $activity['name'] . " (" . $activity['type'] . "): " . $activity['content'] . "\n";
        }
        
        // Limit context — raised to 50KB to accommodate deep content extraction
        if (strlen($contextText) > 50000) {
            $contextText = substr($contextText, 0, 50000) . "\n...[content truncated]";
        }
        
        // Add all quiz questions if available (full activity awareness)
        if (!empty($allquestions)) {
            $contextText .= "\n\nQUIZ QUESTIONS IN THIS ACTIVITY:\n";
            $contextText .= $allquestions . "\n";
            $contextText .= "IMPORTANT: You know ALL the questions in this quiz. Help students understand concepts without giving direct answers.\n";
        }
        
        // Add current quiz question context if available (question-level awareness)
        if ($questionslot > 0) {
            $contextText .= "\n\nCURRENT QUIZ QUESTION:\n";
            $contextText .= "Question number: Q{$questionslot}\n";
            if (!empty($questiontext)) {
                $contextText .= "Question topic/context: {$questiontext}\n";
            }
            $contextText .= "IMPORTANT: Do NOT provide the answer to this question. Help the student think through it.\n";
        }
        
        // Pedagogical guardrails - scaffolded learning with full course knowledge
        $pedagogicalGuidelines = "CRITICAL PEDAGOGICAL GUIDELINES:
- You are an AI Tutor with COMPLETE knowledge of every activity in this course.
- You have read every learning slide, every quiz question and answer, every activity, every video transcript, every essay rubric, and every explanation in the course content provided above.
- When a student asks about a topic, USE your knowledge of the specific slides, questions, and explanations from their course materials to help them understand. Reference specific content from their actual course.
- NEVER provide sample answers, model responses, or complete solutions to assessment tasks.
- NEVER write content that could be directly submitted as the student's own work.
- NEVER reveal the exact correct answer to quiz/knowledge check questions. Instead, use the explanations and learning content to guide them toward understanding WHY the correct answer is correct.
- Instead, guide students with:
  1. Structure guidance: What sections to include, how to organize their response
  2. Concept explanations: Break down key terms and ideas using the actual course content
  3. Real workplace examples: How this applies in actual job settings, drawing from course scenarios
  4. Prompting questions: Questions that help the student think deeper about the specific topic from their course materials
  5. Checklists: What to review before submitting
  6. Cross-referencing: Point students to relevant learning slides, activities, or materials in their course that cover the topic they are asking about
- For VET/RTO compliance: Students must demonstrate THEIR OWN competency
- If a student asks for an answer directly, use the course explanations to help them UNDERSTAND the concept, do not just give them the answer
- Be encouraging but maintain academic integrity at all times
- You know the FULL content of this course — use it to give precise, relevant, contextual help rather than generic advice";
        
        // Call Essay Grader AI API for chat
        $postdata = [
            'siteUrl' => $siteid,
            'apiKey' => $apikey,
            'action' => 'ai_tutor_chat',
            'courseName' => $courseContent['course_name'],
            'courseContext' => $contextText,
            'question' => $question,
            'userId' => $USER->id,
            'courseId' => $courseid,
            // Enhanced context for world-class tutor
            'activityName' => $activityname,
            'activityType' => $activitytype,
            'sectionName' => $sectionname,
            'isFirstMessage' => $isfirstmessage == 1,
            'studentName' => $USER->firstname,
            'pedagogicalGuidelines' => $pedagogicalGuidelines,
            // Per-activity memory for continuity
            'priorTutorMemory' => $memory,
            // Mode: learning (active) vs reflection (post-submission)
            'mode' => 'learning',
            // Quiz question-level context
            'questionSlot' => $questionslot > 0 ? $questionslot : null,
            'questionText' => !empty($questiontext) ? $questiontext : null,
        ];
        
        $url = 'https://lms-labs.com/api/moodle/course-assistant/chat';
        
        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 60]);
        $curl->setHeader(['Content-Type: application/json', 'Accept: application/json']);
        $response = $curl->post($url, json_encode($postdata));
        $httpcode = $curl->info['http_code'];
        $curlerror = $curl->error;
        
        if ($httpcode !== 200) {
            $responseData = json_decode($response, true);
            $errorMsg = $responseData['error'] ?? 'API request failed';
            echo json_encode(['success' => false, 'error' => $errorMsg]);
            exit;
        }
        
        $result = json_decode($response, true);
        if (!$result || !isset($result['success']) || !$result['success']) {
            echo json_encode([
                'success' => false,
                'error' => $result['error'] ?? get_string('aiassistant_error', 'format_aicourse')
            ]);
            exit;
        }
        
        $answer = $result['answer'] ?? '';
        
        // Detect AI refusal (academic integrity enforcement evidence)
        $isrefusal = (
            stripos($answer, "I can't provide") !== false ||
            stripos($answer, "I cannot provide") !== false ||
            stripos($answer, "I cannot give") !== false ||
            stripos($answer, "I can't give you the answer") !== false ||
            stripos($answer, "I can help you think through") !== false ||
            stripos($answer, "instead of giving you the answer") !== false
        ) ? 1 : 0;
        
        // Log chat to database for reporting
        $chatid = 0;
        try {
            $chatrecord = new stdClass();
            $chatrecord->courseid = $courseid;
            $chatrecord->userid = $USER->id;
            $chatrecord->activityid = $activityid;
            $chatrecord->questionslot = $questionslot > 0 ? $questionslot : null;
            $chatrecord->question = $question;
            $chatrecord->response = $answer;
            $chatrecord->rating = 0;
            $chatrecord->refused = $isrefusal;
            $chatrecord->locked = 0;
            $chatrecord->timecreated = time();
            $chatid = $DB->insert_record('format_aicourse_chats', $chatrecord);
        } catch (dml_exception $e) {
            // Table might not exist - log but don't fail
            debugging('AI Tutor chat table error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        
        // Update per-activity memory (safe summary, not answers)
        if ($activityid > 0) {
            try {
                $summary = "Student asked about: " . substr(strip_tags($question), 0, 200);
                if (!empty($memory)) {
                    $summary = $memory . "\n" . $summary;
                    // Keep memory reasonable size
                    if (strlen($summary) > 2000) {
                        $summary = substr($summary, -2000);
                    }
                }
                
                $existingmem = $DB->get_record('format_aicourse_ai_memory', [
                    'courseid' => $courseid,
                    'activityid' => $activityid,
                    'userid' => $USER->id
                ]);
                
                if ($existingmem) {
                    $existingmem->memory = $summary;
                    $existingmem->timeupdated = time();
                    $DB->update_record('format_aicourse_ai_memory', $existingmem);
                } else {
                    $newmem = new stdClass();
                    $newmem->courseid = $courseid;
                    $newmem->activityid = $activityid;
                    $newmem->userid = $USER->id;
                    $newmem->memory = $summary;
                    $newmem->timeupdated = time();
                    $DB->insert_record('format_aicourse_ai_memory', $newmem);
                }
            } catch (dml_exception $e) {
                // Memory table might not exist - continue without error
                debugging('AI Tutor memory update error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        
        echo json_encode([
            'success' => true,
            'answer' => $answer,
            'chatid' => $chatid
        ]);
        break;
        
    case 'ratechat':
        // Students can rate their own chat responses
        $chatid = required_param('chatid', PARAM_INT);
        $rating = required_param('rating', PARAM_INT);
        
        if (!in_array($rating, [-1, 1])) {
            echo json_encode(['success' => false, 'error' => 'Invalid rating']);
            exit;
        }
        
        // Only allow rating own chats
        $chat = $DB->get_record('format_aicourse_chats', ['id' => $chatid, 'courseid' => $courseid, 'userid' => $USER->id]);
        if (!$chat) {
            echo json_encode(['success' => false, 'error' => 'Chat not found']);
            exit;
        }
        
        $DB->set_field('format_aicourse_chats', 'rating', $rating, ['id' => $chatid]);
        echo json_encode(['success' => true]);
        break;
        
    case 'correctchat':
        // FIX-CORRECTCHAT-CAP (v1.7.54): Non-editing teachers can view and correct AI responses.
        // moodle/course:viewparticipants is held by all teacher roles (editing + non-editing).
        require_capability('moodle/course:viewparticipants', $context);
        $chatid = required_param('chatid', PARAM_INT);
        $correction = required_param('correction', PARAM_TEXT);
        
        $chat = $DB->get_record('format_aicourse_chats', ['id' => $chatid, 'courseid' => $courseid]);
        if (!$chat) {
            echo json_encode(['success' => false, 'error' => 'Chat not found']);
            exit;
        }
        
        $update = new stdClass();
        $update->id = $chatid;
        $update->correction = $correction;
        $update->correctedby = $USER->id;
        $update->timecorrected = time();
        $DB->update_record('format_aicourse_chats', $update);
        
        echo json_encode(['success' => true]);
        break;
    
    case 'getactivitycontext':
        // Get detailed context for a specific activity (quiz questions, assignment instructions)
        $activityid = required_param('activityid', PARAM_INT);
        $questionslot = optional_param('questionslot', 0, PARAM_INT);
        
        $modinfo = get_fast_modinfo($course);
        try {
            $cm = $modinfo->get_cm($activityid);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Activity not found']);
            exit;
        }
        
        $activitycontext = [
            'name' => format_string($cm->name),
            'type' => $cm->modname,
            'intro' => '',
            'questions' => [],
            'currentQuestion' => null
        ];
        
        switch ($cm->modname) {
            case 'quiz':
                $quiz = $DB->get_record('quiz', ['id' => $cm->instance]);
                if ($quiz) {
                    $activitycontext['intro'] = strip_tags($quiz->intro ?? '');
                    
                    // Get all quiz questions - Moodle 4.x uses question_references table
                    // Moodle 3.x used questionid directly in quiz_slots
                    $quizcontext = context_module::instance($activityid);

                    // Check once outside the loop — not once per slot.
                    $hasquestionrefs = $DB->get_manager()->table_exists('question_references');

                    if ($hasquestionrefs) {
                        // Moodle 4.x: single JOIN query fetches ALL slot questions at once.
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
                        $allquestions = $DB->get_records_sql($sql, [
                            'contextid' => $quizcontext->id,
                            'quizid'    => $cm->instance,
                        ]);
                        $seenslots = [];
                        foreach ($allquestions as $question) {
                            if (isset($seenslots[$question->slot])) {
                                continue; // Keep only the latest version per slot.
                            }
                            $seenslots[$question->slot] = true;
                            $qdata = [
                                'slot' => (int)$question->slot,
                                'text' => strip_tags($question->questiontext ?? ''),
                                'type' => $question->qtype
                            ];
                            $activitycontext['questions'][] = $qdata;
                            if ($questionslot > 0 && (int)$question->slot === $questionslot) {
                                $activitycontext['currentQuestion'] = $qdata;
                            }
                        }
                    } else {
                        // Moodle 3.x fallback: questionid column on quiz_slots.
                        $sql = "SELECT qs.slot, q.id, q.name, q.questiontext, q.qtype
                                  FROM {quiz_slots} qs
                                  JOIN {question} q ON q.id = qs.questionid
                                 WHERE qs.quizid = :quizid
                              ORDER BY qs.slot ASC";
                        $allquestions = $DB->get_records_sql($sql, ['quizid' => $cm->instance]);
                        foreach ($allquestions as $question) {
                            $qdata = [
                                'slot' => (int)$question->slot,
                                'text' => strip_tags($question->questiontext ?? ''),
                                'type' => $question->qtype
                            ];
                            $activitycontext['questions'][] = $qdata;
                            if ($questionslot > 0 && (int)$question->slot === $questionslot) {
                                $activitycontext['currentQuestion'] = $qdata;
                            }
                        }
                    }
                }
                break;
                
            case 'assign':
                $assign = $DB->get_record('assign', ['id' => $cm->instance]);
                if ($assign) {
                    $activitycontext['intro'] = strip_tags($assign->intro ?? '');
                    if (!empty($assign->activity)) {
                        $activitycontext['intro'] .= "\n\nActivity Instructions:\n" . strip_tags($assign->activity);
                    }
                }
                break;
                
            case 'aiquiz':
                $aiquiz = $DB->get_record('aiquiz', ['id' => $cm->instance]);
                if ($aiquiz) {
                    $activitycontext['intro'] = strip_tags($aiquiz->intro ?? '');
                    $questions = $DB->get_records('aiquiz_questions', ['aiquizid' => $cm->instance], 'questionorder ASC');
                    $slot = 1;
                    foreach ($questions as $q) {
                        $qdata = [
                            'slot' => $slot,
                            'text' => strip_tags($q->questiontext ?? ''),
                            'type' => $q->questiontype ?? 'unknown'
                        ];
                        $activitycontext['questions'][] = $qdata;
                        if ($questionslot > 0 && $slot === $questionslot) {
                            $activitycontext['currentQuestion'] = $qdata;
                        }
                        $slot++;
                    }
                }
                break;
                
            case 'aiknowledgecheck':
            case 'knowledgecheck':
                $kc = $DB->get_record('aiknowledgecheck', ['id' => $cm->instance]);
                if ($kc) {
                    $activitycontext['intro'] = strip_tags($kc->intro ?? '');
                    $questions = $DB->get_records('aiknowledgecheck_questions', ['aiknowledgecheckid' => $cm->instance]);
                    $slot = 1;
                    foreach ($questions as $q) {
                        $qtext = strip_tags($q->questiontext ?? '');
                        $answers = [];
                        for ($i = 1; $i <= 4; $i++) {
                            $af = "answer{$i}";
                            if (!empty($q->$af)) {
                                $marker = ((int)($q->correctanswer ?? 0) === $i) ? ' [CORRECT]' : '';
                                $answers[] = strip_tags($q->$af) . $marker;
                            }
                        }
                        $explanations = [];
                        for ($i = 1; $i <= 4; $i++) {
                            $ef = "feedback{$i}";
                            if (!empty($q->$ef)) {
                                $explanations[] = strip_tags($q->$ef);
                            }
                        }
                        $qdata = [
                            'slot' => $slot,
                            'text' => $qtext . ' | Answers: ' . implode(', ', $answers) . ' | Explanations: ' . implode('; ', $explanations),
                            'type' => 'knowledgecheck'
                        ];
                        $activitycontext['questions'][] = $qdata;
                        if ($questionslot > 0 && $slot === $questionslot) {
                            $activitycontext['currentQuestion'] = $qdata;
                        }
                        $slot++;
                    }
                }
                break;
                
            case 'aiactivities':
                $aa = $DB->get_record('aiactivities', ['id' => $cm->instance]);
                if ($aa) {
                    $activitycontext['intro'] = strip_tags($aa->intro ?? '');
                    if (!empty($aa->activitiesjson)) {
                        $aadata = json_decode($aa->activitiesjson, true);
                        if (is_array($aadata)) {
                            $activitycontext['intro'] .= format_aicourse_extract_aiactivities($aadata);
                        }
                    }
                }
                break;
                
            case 'aivideoactivity':
                $va = $DB->get_record('aivideoactivity', ['id' => $cm->instance]);
                if ($va) {
                    $activitycontext['intro'] = strip_tags($va->intro ?? '');
                    if (!empty($va->transcripttext)) {
                        $activitycontext['intro'] .= "\n[TRANSCRIPT]: " . substr(strip_tags($va->transcripttext), 0, 5000);
                    }
                    $vaquestions = $DB->get_records('aivideoactivity_questions', ['aivideoactivityid' => $cm->instance]);
                    $slot = 1;
                    foreach ($vaquestions as $vq) {
                        $qtext = '';
                        if (!empty($vq->questiondata)) {
                            $qjson = json_decode($vq->questiondata, true);
                            if (is_array($qjson)) {
                                $qtext = format_aicourse_extract_va_question($qjson, $slot);
                            }
                        } else if (!empty($vq->questiontext)) {
                            $qtext = strip_tags($vq->questiontext);
                        }
                        $activitycontext['questions'][] = [
                            'slot' => $slot,
                            'text' => $qtext,
                            'type' => 'videoactivity'
                        ];
                        if ($questionslot > 0 && $slot === $questionslot) {
                            $activitycontext['currentQuestion'] = ['slot' => $slot, 'text' => $qtext, 'type' => 'videoactivity'];
                        }
                        $slot++;
                    }
                }
                break;
                
            case 'contentcreator':
                $cc = $DB->get_record('contentcreator', ['id' => $cm->instance]);
                if ($cc) {
                    $activitycontext['intro'] = strip_tags($cc->intro ?? '');
                    if (!empty($cc->manifestjson)) {
                        $manifest = json_decode($cc->manifestjson, true);
                        if (is_array($manifest)) {
                            $activitycontext['intro'] .= format_aicourse_extract_cc_manifest($manifest);
                        }
                    }
                }
                break;
                
            case 'practicalassessment':
                $pa = $DB->get_record('practicalassessment', ['id' => $cm->instance]);
                if ($pa) {
                    $activitycontext['intro'] = strip_tags($pa->intro ?? '');
                    $criteria = $DB->get_records('practicalassessment_criteria', ['practicalassessmentid' => $cm->instance]);
                    foreach ($criteria as $c) {
                        $activitycontext['questions'][] = [
                            'slot' => (int)$c->id,
                            'text' => 'Criterion: ' . strip_tags($c->criteriontext ?? ''),
                            'type' => 'criterion'
                        ];
                    }
                }
                break;
                
            default:
                // For other activity types, just get intro
                $record = $DB->get_record($cm->modname, ['id' => $cm->instance]);
                if ($record && isset($record->intro)) {
                    $activitycontext['intro'] = strip_tags($record->intro);
                }
        }
        
        echo json_encode(['success' => true, 'context' => $activitycontext]);
        break;
        
    case 'generate_banner_image':
        if (!has_capability('moodle/course:update', $context)) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            exit;
        }

        // Get Site ID and API Key from central config or fallback
        $siteid = '';
        $apikey = '';
        $aiconfiglib = $CFG->dirroot . '/local/aiconfig/lib.php';
        if (file_exists($aiconfiglib)) {
            require_once($aiconfiglib);
        }
        if (function_exists('local_aiconfig_get_siteid')) {
            $siteid = trim(local_aiconfig_get_siteid('format_aicourse') ?? '');
        }
        if (empty($siteid)) {
            $siteid = trim(get_config('format_aicourse', 'siteid') ?? '');
        }
        if (function_exists('local_aiconfig_get_apikey')) {
            $apikey = trim(local_aiconfig_get_apikey('format_aicourse') ?? '');
        }
        if (empty($apikey)) {
            $apikey = trim(get_config('format_aicourse', 'apikey') ?? '');
        }
        if (empty($siteid) || empty($apikey)) {
            echo json_encode(['success' => false, 'error' => 'Plugin not configured. Please add Site ID and API Key in Site administration → Plugins → Course formats → AI Course Format settings.']);
            exit;
        }

        // Call Vault API to generate the banner image
        $postdata = [
            'siteUrl'        => $siteid,
            'apiKey'         => $apikey,
            'courseName'     => format_string($course->fullname),
            'courseShortname'=> $course->shortname,
            'courseId'       => $courseid,
        ];

        $url = 'https://lms-labs.com/api/moodle/aicourse/generate-banner';
        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 90]);
        $curl->setHeader(['Content-Type: application/json', 'Accept: application/json']);
        $response  = $curl->post($url, json_encode($postdata));
        $httpcode  = $curl->info['http_code'];
        $curlerror = $curl->error;

        if ($curlerror || $httpcode !== 200) {
            $data = json_decode($response, true);
            echo json_encode(['success' => false, 'error' => ($data['error'] ?? 'Image generation failed (HTTP ' . $httpcode . ')')]);
            exit;
        }

        $result = json_decode($response, true);
        if (!$result || empty($result['success']) || empty($result['imageBase64'])) {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'No image returned from API']);
            exit;
        }

        // Decode and save to Moodle file storage as the course banner image
        $imagedata = base64_decode($result['imageBase64']);
        if (!$imagedata) {
            echo json_encode(['success' => false, 'error' => 'Failed to decode image data']);
            exit;
        }

        $fs = get_file_storage();

        // Remove any existing banner images for this course
        $fs->delete_area_files($context->id, 'format_aicourse', 'bannerimage', $courseid);

        // Create the new banner file
        $fileinfo = [
            'component' => 'format_aicourse',
            'filearea'  => 'bannerimage',
            'itemid'    => $courseid,
            'contextid' => $context->id,
            'filepath'  => '/',
            'filename'  => 'ai_banner_' . time() . '.jpg',
        ];

        try {
            $file    = $fs->create_file_from_string($fileinfo, $imagedata);
            $fileurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                'format_aicourse',
                'bannerimage',
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            )->out();

            echo json_encode([
                'success'     => true,
                'imageUrl'    => $fileurl,
                'creditsUsed' => (int)($result['creditsUsed'] ?? 5),
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to save image: ' . $e->getMessage()]);
        }
        exit;

    case 'delete_banner_image':
        require_capability('moodle/course:update', $context);

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'format_aicourse', 'bannerimage', $courseid);

        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
