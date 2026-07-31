<?php
require_once(__DIR__ . '/../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/course/format/aicourse/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('aireport', 'format_aicourse'));
$PAGE->set_heading(get_string('aireport', 'format_aicourse'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();

// Get all courses using AI Course Format
$courses = $DB->get_records('course', ['format' => 'aicourse'], 'fullname ASC');

?>
<style>
.aitutor-report-index {
    max-width: 1200px;
    margin: 0 auto;
}
.aitutor-report-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}
.aitutor-report-header svg {
    width: 32px;
    height: 32px;
    color: #6366f1;
}
.aitutor-report-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    color: #1e293b;
}
.aitutor-course-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}
.aitutor-course-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    transition: box-shadow 0.2s, transform 0.2s;
}
.aitutor-course-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.aitutor-course-card h3 {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}
.aitutor-course-card p {
    margin: 0 0 16px 0;
    font-size: 14px;
    color: #64748b;
}
.aitutor-course-stats {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
}
.aitutor-course-stat {
    text-align: center;
}
.aitutor-course-stat-value {
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
}
.aitutor-course-stat-label {
    font-size: 12px;
    color: #64748b;
}
.aitutor-course-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #6366f1;
    color: #fff;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.2s;
}
.aitutor-course-btn:hover {
    background: #4f46e5;
    color: #fff;
}
.aitutor-empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
}
.aitutor-empty-state svg {
    width: 64px;
    height: 64px;
    color: #cbd5e1;
    margin-bottom: 16px;
}
</style>

<div class="aitutor-report-index">
    <div class="aitutor-report-header">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="m9 15 2 2 4-4"/></svg>
        <h2><?php echo get_string('aireport', 'format_aicourse'); ?></h2>
    </div>
    
    <?php if (empty($courses)): ?>
    <div class="aitutor-empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
        <h3><?php echo get_string('aireport_nocourses', 'format_aicourse'); ?></h3>
        <p><?php echo get_string('aireport_nocourses_desc', 'format_aicourse'); ?></p>
    </div>
    <?php else: ?>
    <div class="aitutor-course-grid">
        <?php foreach ($courses as $course): 
            // Get stats for this course
            $questioncount = $DB->count_records('format_aicourse_chats', ['courseid' => $course->id]);
            $helpfulcount = $DB->count_records('format_aicourse_chats', ['courseid' => $course->id, 'rating' => 1]);
            $correctedcount = $DB->count_records_select('format_aicourse_chats', 'courseid = ? AND correction IS NOT NULL', [$course->id]);
        ?>
        <div class="aitutor-course-card">
            <h3><?php echo format_string($course->fullname); ?></h3>
            <p><?php echo format_string($course->shortname); ?></p>
            <div class="aitutor-course-stats">
                <div class="aitutor-course-stat">
                    <div class="aitutor-course-stat-value"><?php echo $questioncount; ?></div>
                    <div class="aitutor-course-stat-label"><?php echo get_string('aireport_total_questions', 'format_aicourse'); ?></div>
                </div>
                <div class="aitutor-course-stat">
                    <div class="aitutor-course-stat-value"><?php echo $helpfulcount; ?></div>
                    <div class="aitutor-course-stat-label"><?php echo get_string('aireport_helpful', 'format_aicourse'); ?></div>
                </div>
                <div class="aitutor-course-stat">
                    <div class="aitutor-course-stat-value"><?php echo $correctedcount; ?></div>
                    <div class="aitutor-course-stat-label"><?php echo get_string('aireport_corrected', 'format_aicourse'); ?></div>
                </div>
            </div>
            <a href="<?php echo (new moodle_url('/course/format/aicourse/report.php', ['id' => $course->id]))->out(); ?>" class="aitutor-course-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                <?php echo get_string('aireport_view', 'format_aicourse'); ?>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
