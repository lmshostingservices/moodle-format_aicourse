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
 * format_aicourse file.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$filteruser = optional_param('filteruser', 0, PARAM_INT);
$filtergroup = optional_param('filtergroup', 0, PARAM_INT);
$filterrating = optional_param('filterrating', '', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);
$tab = optional_param('tab', 'content', PARAM_ALPHA);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
// FIX-REPORT-CAP (v1.7.54): Non-editing teachers have mod/assign:viewallsubmissions and
// grade/report:viewall but NOT moodle/course:update. They need access to the AI Chat Report
// to monitor student AI Tutor interactions. Use viewparticipants as the threshold — anyone
// who can see the participant list (all teacher roles) can view the AI chat report.
require_capability('moodle/course:viewparticipants', $context);

$PAGE->set_url(new moodle_url('/course/format/aicourse/report.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('aireport', 'format_aicourse'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Add CSS
$PAGE->requires->css(new moodle_url('/course/format/aicourse/styles.css'));

echo $OUTPUT->header();

// Get course content for AI
$courseContent = format_aicourse_get_course_content_for_ai($course);

// Get groups for filter
$groups = groups_get_all_groups($courseid);

// Get enrolled users for filter
$enrolledusers = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email', 'u.lastname, u.firstname');

?>
<style>
.aicourse-report-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}
.aicourse-report-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.aicourse-report-title {
    font-size: 24px;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 12px;
}
.aicourse-report-title svg {
    color: #6366f1;
}
.aicourse-tabs {
    display: flex;
    gap: 4px;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 8px;
    margin-bottom: 24px;
}
.aicourse-tab {
    padding: 10px 20px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: #64748b;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.aicourse-tab:hover {
    color: #1e293b;
}
.aicourse-tab.active {
    background: #fff;
    color: #6366f1;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.aicourse-content-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.aicourse-content-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    transition: box-shadow 0.2s;
}
.aicourse-content-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.aicourse-content-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}
.aicourse-content-card-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #f0f9ff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
}
.aicourse-content-card-title {
    font-weight: 600;
    color: #1e293b;
    font-size: 14px;
}
.aicourse-content-card-type {
    font-size: 12px;
    color: #64748b;
    text-transform: capitalize;
}
.aicourse-content-card-status {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #16a34a;
    margin-top: 8px;
}
.aicourse-content-card-status svg {
    width: 14px;
    height: 14px;
}
.aicourse-content-card-preview {
    font-size: 13px;
    color: #64748b;
    line-height: 1.5;
    margin-top: 8px;
    max-height: 60px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.aicourse-stats-row {
    display: flex;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.aicourse-stat-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    flex: 1;
    min-width: 200px;
}
.aicourse-stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #1e293b;
}
.aicourse-stat-label {
    font-size: 14px;
    color: #64748b;
    margin-top: 4px;
}
.aicourse-filters {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: center;
}
.aicourse-filter-select, .aicourse-filter-input {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    min-width: 150px;
}
.aicourse-filter-input {
    min-width: 250px;
}
.aicourse-chat-table {
    width: 100%;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}
.aicourse-chat-table th {
    background: #f8fafc;
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #475569;
    font-size: 13px;
    border-bottom: 1px solid #e2e8f0;
}
.aicourse-chat-table td {
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    vertical-align: top;
}
.aicourse-chat-table tr:last-child td {
    border-bottom: none;
}
.aicourse-chat-question {
    font-weight: 500;
    color: #1e293b;
    max-width: 300px;
}
.aicourse-chat-response {
    color: #475569;
    max-width: 400px;
    max-height: 100px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.aicourse-chat-user {
    display: flex;
    align-items: center;
    gap: 8px;
}
.aicourse-chat-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}
.aicourse-chat-rating {
    display: flex;
    gap: 4px;
}
.aicourse-rating-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.aicourse-rating-btn:hover {
    background: #f1f5f9;
}
.aicourse-rating-btn.helpful {
    border-color: #16a34a;
    color: #16a34a;
}
.aicourse-rating-btn.not-helpful {
    border-color: #dc2626;
    color: #dc2626;
}
.aicourse-correction-btn {
    padding: 6px 12px;
    background: #6366f1;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: background 0.2s;
}
.aicourse-correction-btn:hover {
    background: #4f46e5;
}
.aicourse-correction-form {
    display: none;
    margin-top: 12px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 8px;
}
.aicourse-correction-form.active {
    display: block;
}
.aicourse-correction-textarea {
    width: 100%;
    min-height: 80px;
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    resize: vertical;
    font-size: 14px;
}
.aicourse-correction-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}
.aicourse-empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
}
.aicourse-empty-state svg {
    width: 64px;
    height: 64px;
    color: #cbd5e1;
    margin-bottom: 16px;
}
.aicourse-pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 24px;
}
.aicourse-pagination a, .aicourse-pagination span {
    padding: 8px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    text-decoration: none;
    color: #475569;
    font-size: 14px;
}
.aicourse-pagination a:hover {
    background: #f1f5f9;
}
.aicourse-pagination span.current {
    background: #6366f1;
    color: #fff;
    border-color: #6366f1;
}
.aicourse-section-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
}
.aicourse-section-title {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>

<div class="aicourse-report-container">
    <div class="aicourse-report-header">
        <h1 class="aicourse-report-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="m9 15 2 2 4-4"/></svg>
            <?php echo get_string('aireport', 'format_aicourse'); ?>
        </h1>
    </div>
    
    <div class="aicourse-tabs">
        <a href="?id=<?php echo $courseid; ?>&tab=content" class="aicourse-tab <?php echo $tab === 'content' ? 'active' : ''; ?>">
            <?php echo get_string('aireport_content', 'format_aicourse'); ?>
        </a>
        <a href="?id=<?php echo $courseid; ?>&tab=history" class="aicourse-tab <?php echo $tab === 'history' ? 'active' : ''; ?>">
            <?php echo get_string('aireport_history', 'format_aicourse'); ?>
        </a>
    </div>

<?php if ($tab === 'content'): ?>
    <!-- Course Content Tab -->
    <div class="aicourse-stats-row">
        <div class="aicourse-stat-card">
            <div class="aicourse-stat-value"><?php echo count($courseContent['sections']); ?></div>
            <div class="aicourse-stat-label"><?php echo get_string('aireport_sections', 'format_aicourse'); ?></div>
        </div>
        <div class="aicourse-stat-card">
            <div class="aicourse-stat-value"><?php echo count($courseContent['activities']); ?></div>
            <div class="aicourse-stat-label"><?php echo get_string('aireport_activities', 'format_aicourse'); ?></div>
        </div>
        <div class="aicourse-stat-card">
            <div class="aicourse-stat-value"><?php 
                $totalChars = strlen($courseContent['course_summary']);
                foreach ($courseContent['activities'] as $act) {
                    $totalChars += strlen($act['content']);
                }
                echo number_format($totalChars);
            ?></div>
            <div class="aicourse-stat-label"><?php echo get_string('aireport_characters', 'format_aicourse'); ?></div>
        </div>
    </div>

    <div class="aicourse-section-card">
        <h3 class="aicourse-section-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
            <?php echo get_string('aireport_course_summary', 'format_aicourse'); ?>
        </h3>
        <p style="color: #475569; line-height: 1.6;">
            <?php echo !empty($courseContent['course_summary']) ? format_text($courseContent['course_summary'], FORMAT_HTML) : '<em style="color:#94a3b8;">No course summary</em>'; ?>
        </p>
    </div>

    <h3 style="font-size: 18px; font-weight: 600; color: #1e293b; margin-bottom: 16px;">
        <?php echo get_string('aireport_learned_content', 'format_aicourse'); ?>
    </h3>
    
    <div class="aicourse-content-grid">
        <?php foreach ($courseContent['activities'] as $activity): ?>
        <div class="aicourse-content-card">
            <div class="aicourse-content-card-header">
                <div class="aicourse-content-card-icon">
                    <?php echo format_aicourse_get_activity_icon($activity['type']); ?>
                </div>
                <div>
                    <div class="aicourse-content-card-title"><?php echo s($activity['name']); ?></div>
                    <div class="aicourse-content-card-type"><?php echo s($activity['type']); ?></div>
                </div>
            </div>
            <div class="aicourse-content-card-status">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                <?php echo get_string('aireport_learned', 'format_aicourse'); ?>
            </div>
            <?php if (!empty($activity['content'])): ?>
            <div class="aicourse-content-card-preview">
                <?php echo s(substr(strip_tags($activity['content']), 0, 150)) . (strlen($activity['content']) > 150 ? '...' : ''); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>
    <!-- Chat History Tab -->
    <?php
    // Build query for chat history
    $params = ['courseid' => $courseid];
    $where = 'courseid = :courseid';
    
    if ($filteruser > 0) {
        $where .= ' AND userid = :userid';
        $params['userid'] = $filteruser;
    }
    
    if ($filterrating === 'helpful') {
        $where .= ' AND rating = 1';
    } elseif ($filterrating === 'nothelpful') {
        $where .= ' AND rating = -1';
    } elseif ($filterrating === 'corrected') {
        $where .= ' AND correction IS NOT NULL';
    }
    
    if (!empty($search)) {
        $where .= ' AND (question LIKE :search1 OR response LIKE :search2)';
        $params['search1'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
    }
    
    // Group filter - get users in the group
    if ($filtergroup > 0) {
        $groupmembers = groups_get_members($filtergroup, 'u.id');
        if (!empty($groupmembers)) {
            $memberids = array_keys($groupmembers);
            list($insql, $inparams) = $DB->get_in_or_equal($memberids, SQL_PARAMS_NAMED);
            $where .= ' AND userid ' . $insql;
            $params = array_merge($params, $inparams);
        } else {
            $where .= ' AND 1=0'; // No members, no results
        }
    }
    
    $totalcount = $DB->count_records_select('format_aicourse_chats', $where, $params);
    $chats = $DB->get_records_select('format_aicourse_chats', $where, $params, 'timecreated DESC', '*', $page * $perpage, $perpage);
    
    // Get user info for chats
    $userids = array_unique(array_column($chats, 'userid'));
    $users = [];
    if (!empty($userids)) {
        list($insql, $inparams) = $DB->get_in_or_equal($userids);
        $users = $DB->get_records_select('user', "id $insql", $inparams, '', 'id,firstname,lastname,email');
    }
    
    // Stats
    $totalquestions = $DB->count_records('format_aicourse_chats', ['courseid' => $courseid]);
    $helpfulcount = $DB->count_records('format_aicourse_chats', ['courseid' => $courseid, 'rating' => 1]);
    $correctedcount = $DB->count_records_select('format_aicourse_chats', 'courseid = ? AND correction IS NOT NULL', [$courseid]);
    ?>
    
    <div class="aicourse-stats-row">
        <div class="aicourse-stat-card">
            <div class="aicourse-stat-value"><?php echo $totalquestions; ?></div>
            <div class="aicourse-stat-label"><?php echo get_string('aireport_total_questions', 'format_aicourse'); ?></div>
        </div>
        <div class="aicourse-stat-card">
            <div class="aicourse-stat-value"><?php echo $helpfulcount; ?></div>
            <div class="aicourse-stat-label"><?php echo get_string('aireport_helpful', 'format_aicourse'); ?></div>
        </div>
        <div class="aicourse-stat-card">
            <div class="aicourse-stat-value"><?php echo $correctedcount; ?></div>
            <div class="aicourse-stat-label"><?php echo get_string('aireport_corrected', 'format_aicourse'); ?></div>
        </div>
    </div>

    <form method="get" action="">
        <input type="hidden" name="id" value="<?php echo $courseid; ?>">
        <input type="hidden" name="tab" value="history">
        <div class="aicourse-filters">
            <select name="filteruser" class="aicourse-filter-select">
                <option value="0"><?php echo get_string('aireport_all_students', 'format_aicourse'); ?></option>
                <?php foreach ($enrolledusers as $user): ?>
                <option value="<?php echo $user->id; ?>" <?php echo $filteruser == $user->id ? 'selected' : ''; ?>>
                    <?php echo fullname($user); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="filtergroup" class="aicourse-filter-select">
                <option value="0"><?php echo get_string('aireport_all_groups', 'format_aicourse'); ?></option>
                <?php foreach ($groups as $group): ?>
                <option value="<?php echo $group->id; ?>" <?php echo $filtergroup == $group->id ? 'selected' : ''; ?>>
                    <?php echo s($group->name); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="filterrating" class="aicourse-filter-select">
                <option value=""><?php echo get_string('aireport_all_ratings', 'format_aicourse'); ?></option>
                <option value="helpful" <?php echo $filterrating === 'helpful' ? 'selected' : ''; ?>><?php echo get_string('aireport_filter_helpful', 'format_aicourse'); ?></option>
                <option value="nothelpful" <?php echo $filterrating === 'nothelpful' ? 'selected' : ''; ?>><?php echo get_string('aireport_filter_nothelpful', 'format_aicourse'); ?></option>
                <option value="corrected" <?php echo $filterrating === 'corrected' ? 'selected' : ''; ?>><?php echo get_string('aireport_filter_corrected', 'format_aicourse'); ?></option>
            </select>
            
            <input type="text" name="search" class="aicourse-filter-input" placeholder="<?php echo get_string('aireport_search', 'format_aicourse'); ?>" value="<?php echo s($search); ?>">
            
            <button type="submit" class="aicourse-correction-btn"><?php echo get_string('aireport_apply', 'format_aicourse'); ?></button>
        </div>
    </form>

    <?php if (empty($chats)): ?>
    <div class="aicourse-empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <h3><?php echo get_string('aireport_no_chats', 'format_aicourse'); ?></h3>
        <p><?php echo get_string('aireport_no_chats_desc', 'format_aicourse'); ?></p>
    </div>
    <?php else: ?>
    <table class="aicourse-chat-table">
        <thead>
            <tr>
                <th><?php echo get_string('aireport_student', 'format_aicourse'); ?></th>
                <th><?php echo get_string('aireport_question', 'format_aicourse'); ?></th>
                <th><?php echo get_string('aireport_response', 'format_aicourse'); ?></th>
                <th><?php echo get_string('aireport_date', 'format_aicourse'); ?></th>
                <th><?php echo get_string('aireport_rating', 'format_aicourse'); ?></th>
                <th><?php echo get_string('aireport_actions', 'format_aicourse'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($chats as $chat): 
                $user = $users[$chat->userid] ?? null;
                $initials = $user ? strtoupper(substr($user->firstname, 0, 1) . substr($user->lastname, 0, 1)) : '??';
            ?>
            <tr>
                <td>
                    <div class="aicourse-chat-user">
                        <div class="aicourse-chat-avatar"><?php echo $initials; ?></div>
                        <div>
                            <div style="font-weight:500;"><?php echo $user ? fullname($user) : 'Unknown'; ?></div>
                        </div>
                    </div>
                </td>
                <td class="aicourse-chat-question"><?php echo s(substr($chat->question, 0, 200)) . (strlen($chat->question) > 200 ? '...' : ''); ?></td>
                <td class="aicourse-chat-response">
                    <?php echo s(substr($chat->response, 0, 300)) . (strlen($chat->response) > 300 ? '...' : ''); ?>
                    <?php if (!empty($chat->correction)): ?>
                    <div style="margin-top:8px;padding:8px;background:#fef2f2;border-radius:6px;border-left:3px solid #dc2626;">
                        <strong style="color:#dc2626;font-size:12px;"><?php echo get_string('aireport_correction', 'format_aicourse'); ?>:</strong><br>
                        <?php echo s(substr($chat->correction, 0, 200)); ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap;">
                    <?php echo userdate($chat->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')); ?>
                </td>
                <td>
                    <div class="aicourse-chat-rating">
                        <button type="button" class="aicourse-rating-btn <?php echo $chat->rating == 1 ? 'helpful' : ''; ?>" 
                                onclick="rateChat(<?php echo $chat->id; ?>, 1)" title="Helpful">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>
                        </button>
                        <button type="button" class="aicourse-rating-btn <?php echo $chat->rating == -1 ? 'not-helpful' : ''; ?>" 
                                onclick="rateChat(<?php echo $chat->id; ?>, -1)" title="Not Helpful">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"/></svg>
                        </button>
                    </div>
                </td>
                <td>
                    <button type="button" class="aicourse-correction-btn" onclick="toggleCorrection(<?php echo $chat->id; ?>)">
                        <?php echo get_string('aireport_correct', 'format_aicourse'); ?>
                    </button>
                    <div id="correction-form-<?php echo $chat->id; ?>" class="aicourse-correction-form">
                        <textarea class="aicourse-correction-textarea" id="correction-text-<?php echo $chat->id; ?>" placeholder="<?php echo get_string('aireport_correction_placeholder', 'format_aicourse'); ?>"><?php echo s($chat->correction ?? ''); ?></textarea>
                        <div class="aicourse-correction-actions">
                            <button type="button" class="aicourse-correction-btn" onclick="saveCorrection(<?php echo $chat->id; ?>)"><?php echo get_string('aireport_save', 'format_aicourse'); ?></button>
                            <button type="button" class="aicourse-correction-btn" style="background:#64748b;" onclick="toggleCorrection(<?php echo $chat->id; ?>)"><?php echo get_string('aireport_cancel', 'format_aicourse'); ?></button>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php
    // Pagination
    $totalpages = ceil($totalcount / $perpage);
    if ($totalpages > 1):
        $baseurl = new moodle_url('/course/format/aicourse/report.php', [
            'id' => $courseid,
            'tab' => 'history',
            'filteruser' => $filteruser,
            'filtergroup' => $filtergroup,
            'filterrating' => $filterrating,
            'search' => $search
        ]);
    ?>
    <div class="aicourse-pagination">
        <?php for ($i = 0; $i < $totalpages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="current"><?php echo $i + 1; ?></span>
            <?php else: ?>
                <a href="<?php echo $baseurl->out(true, ['page' => $i]); ?>"><?php echo $i + 1; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
<?php endif; ?>
</div>

<script>
var ajaxUrl = '<?php echo (new moodle_url('/course/format/aicourse/ajax.php'))->out(false); ?>';
var sesskey = '<?php echo sesskey(); ?>';
var courseid = <?php echo $courseid; ?>;

function toggleCorrection(chatId) {
    var form = document.getElementById('correction-form-' + chatId);
    form.classList.toggle('active');
}

function rateChat(chatId, rating) {
    fetch(ajaxUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=ratechat&courseid=' + courseid + '&sesskey=' + sesskey + '&chatid=' + chatId + '&rating=' + rating
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to save rating');
        }
    });
}

function saveCorrection(chatId) {
    var correction = document.getElementById('correction-text-' + chatId).value;
    fetch(ajaxUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=correctchat&courseid=' + courseid + '&sesskey=' + sesskey + '&chatid=' + chatId + '&correction=' + encodeURIComponent(correction)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to save correction');
        }
    });
}
</script>

<?php
echo $OUTPUT->footer();

// Helper function for activity icons
function format_aicourse_get_activity_icon($type) {
    $icons = [
        'page' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'assign' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>',
        'quiz' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>',
        'forum' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        'resource' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>',
        'folder' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
        'book' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>',
        'glossary' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>',
        'wiki' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4Z"/></svg>',
        'contentcreator' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
    ];
    return $icons[$type] ?? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>';
}
