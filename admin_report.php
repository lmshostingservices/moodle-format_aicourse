<?php
/**
 * Site-wide admin report — all AI Tutor Q&A across every course.
 *
 * Access:  Site administrators only (moodle/site:config).
 * URL:     /course/format/aicourse/admin_report.php
 *
 * @package    format_aicourse
 * @copyright  2025 EssayGraderAI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

// ── Parameters ───────────────────────────────────────────────────────────────
$page          = optional_param('page',        0,  PARAM_INT);
$perpage       = optional_param('perpage',     25, PARAM_INT);
$filtercourseid = optional_param('filtercourseid', 0, PARAM_INT);
$filteruserid  = optional_param('filteruserid', 0, PARAM_INT);
$filterrating  = optional_param('filterrating', '', PARAM_ALPHA);
$filterrefused = optional_param('filterrefused', '', PARAM_ALPHA);
$datefromstr   = optional_param('datefrom',  '', PARAM_TEXT);
$datetostr     = optional_param('dateto',    '', PARAM_TEXT);
$search        = optional_param('search',    '', PARAM_TEXT);
$export        = optional_param('export',    0,  PARAM_INT);

$datefrom = $datefromstr ? strtotime($datefromstr . ' 00:00:00') : 0;
$dateto   = $datetostr   ? strtotime($datetostr   . ' 23:59:59') : 0;

// ── Auth ─────────────────────────────────────────────────────────────────────
require_login();
$syscontext = context_system::instance();
require_capability('moodle/site:config', $syscontext);

// ── Page setup ───────────────────────────────────────────────────────────────
$PAGE->set_url(new moodle_url('/course/format/aicourse/admin_report.php'));
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('admin_report_title', 'format_aicourse'));
$PAGE->set_heading(get_string('admin_report_title', 'format_aicourse'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->css(new moodle_url('/course/format/aicourse/styles.css'));

// ── Build WHERE clause ────────────────────────────────────────────────────────
$where  = '1=1';
$params = [];

if ($filtercourseid > 0) {
    $where .= ' AND c.courseid = :courseid';
    $params['courseid'] = $filtercourseid;
}
if ($filteruserid > 0) {
    $where .= ' AND c.userid = :userid';
    $params['userid'] = $filteruserid;
}
if ($filterrating === 'helpful') {
    $where .= ' AND c.rating = 1';
} elseif ($filterrating === 'nothelpful') {
    $where .= ' AND c.rating = -1';
} elseif ($filterrating === 'unrated') {
    $where .= ' AND c.rating = 0';
}
if ($filterrefused === 'refused') {
    $where .= ' AND c.refused = 1';
} elseif ($filterrefused === 'answered') {
    $where .= ' AND c.refused = 0';
}
if ($datefrom > 0) {
    $where .= ' AND c.timecreated >= :datefrom';
    $params['datefrom'] = $datefrom;
}
if ($dateto > 0) {
    $where .= ' AND c.timecreated <= :dateto';
    $params['dateto'] = $dateto;
}
if ($search !== '') {
    $where .= ' AND (' . $DB->sql_like('c.question', ':search1', false)
            . ' OR '  . $DB->sql_like('c.response',  ':search2', false) . ')';
    $params['search1'] = '%' . $DB->sql_like_escape($search) . '%';
    $params['search2'] = '%' . $DB->sql_like_escape($search) . '%';
}

$basesql = "FROM {format_aicourse_chats} c
            JOIN {user}   u  ON u.id   = c.userid
            JOIN {course} co ON co.id  = c.courseid
           WHERE $where";

// ── Stats (always site-wide, ignoring filters for the headline numbers) ───────
$totalqs      = $DB->count_records('format_aicourse_chats');
$helpfulqs    = $DB->count_records('format_aicourse_chats', ['rating' => 1]);
$refusedqs    = $DB->count_records('format_aicourse_chats', ['refused' => 1]);
$activecourses = $DB->count_records_sql("SELECT COUNT(DISTINCT courseid) FROM {format_aicourse_chats}");
$activestudents = $DB->count_records_sql("SELECT COUNT(DISTINCT userid) FROM {format_aicourse_chats}");

// ── Filtered count ────────────────────────────────────────────────────────────
$filteredtotal = $DB->count_records_sql("SELECT COUNT(*) $basesql", $params);

// ── For CSV export — fetch all (no paging) ───────────────────────────────────
if ($export) {
    $allchats = $DB->get_records_sql(
        "SELECT c.id, c.courseid, c.userid, c.activityid, c.question, c.response,
                c.rating, c.refused, c.timecreated, c.correction,
                u.firstname, u.lastname, u.email,
                co.fullname AS coursefullname
         $basesql
         ORDER BY c.timecreated DESC",
        $params
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="aitutor_qa_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Course', 'Student', 'Email', 'Activity ID', 'Question', 'AI Response', 'Rating', 'Refused', 'Correction']);
    foreach ($allchats as $c) {
        $rating = $c->rating == 1 ? 'Helpful' : ($c->rating == -1 ? 'Not Helpful' : 'Unrated');
        fputcsv($out, [
            date('Y-m-d H:i', $c->timecreated),
            $c->coursefullname,
            $c->firstname . ' ' . $c->lastname,
            $c->email,
            $c->activityid ?: '',
            $c->question,
            $c->response,
            $rating,
            $c->refused ? 'Yes' : 'No',
            $c->correction ?: '',
        ]);
    }
    fclose($out);
    exit;
}

// ── Fetch paged results ───────────────────────────────────────────────────────
$chats = $DB->get_records_sql(
    "SELECT c.id, c.courseid, c.userid, c.activityid, c.question, c.response,
            c.rating, c.refused, c.timecreated, c.correction,
            u.firstname, u.lastname,
            co.fullname AS coursefullname, co.id AS courseid2
     $basesql
     ORDER BY c.timecreated DESC",
    $params,
    $page * $perpage,
    $perpage
);

// ── Build activity name lookup from returned records ─────────────────────────
$activitynames = [];
$activityids = array_filter(array_unique(array_column((array)$chats, 'activityid')));
if ($activityids) {
    [$insql, $inparams] = $DB->get_in_or_equal(array_values($activityids), SQL_PARAMS_NAMED);
    $cms = $DB->get_records_sql(
        "SELECT cm.id, m.name AS modtype, cm.instance
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
          WHERE cm.id $insql",
        $inparams
    );
    foreach ($cms as $cm) {
        $table = 'mod_' . $cm->modtype;
        // Try to get display name from mod table
        $activitynames[$cm->id] = ucfirst($cm->modtype) . ' #' . $cm->instance;
        try {
            $modrecord = $DB->get_field($cm->modtype, 'name', ['id' => $cm->instance]);
            if ($modrecord) {
                $activitynames[$cm->id] = s($modrecord);
            }
        } catch (Exception $e) {
            // Leave fallback name
        }
    }
}

// ── Dropdown data: courses and users that have chat records ──────────────────
$courseoptions = $DB->get_records_sql(
    "SELECT DISTINCT co.id, co.fullname
       FROM {format_aicourse_chats} c
       JOIN {course} co ON co.id = c.courseid
      ORDER BY co.fullname"
);
$useroptions = $DB->get_records_sql(
    "SELECT DISTINCT u.id, u.firstname, u.lastname
       FROM {format_aicourse_chats} c
       JOIN {user} u ON u.id = c.userid
      ORDER BY u.lastname, u.firstname"
);

// ── Current filter URL (base for pager) ──────────────────────────────────────
$filterparams = [
    'filtercourseid' => $filtercourseid,
    'filteruserid'   => $filteruserid,
    'filterrating'   => $filterrating,
    'filterrefused'  => $filterrefused,
    'datefrom'       => $datefromstr,
    'dateto'         => $datetostr,
    'search'         => $search,
    'perpage'        => $perpage,
];
$baseurl = new moodle_url('/course/format/aicourse/admin_report.php', $filterparams);

// ── Output ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
?>
<style>
.aicadmin-wrap {
    max-width: 1500px;
    margin: 0 auto;
    padding: 24px;
}
.aicadmin-heading {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
    flex-wrap: wrap;
}
.aicadmin-heading h1 {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}
.aicadmin-heading svg {
    color: #6366f1;
    flex-shrink: 0;
}
.aicadmin-stats {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 28px;
}
.aicadmin-stat {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px 22px;
    min-width: 130px;
    flex: 1 1 130px;
}
.aicadmin-stat-val {
    font-size: 26px;
    font-weight: 700;
    color: #6366f1;
    line-height: 1.1;
}
.aicadmin-stat-lbl {
    font-size: 12px;
    color: #64748b;
    margin-top: 4px;
}
.aicadmin-filter-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 18px 20px;
    margin-bottom: 24px;
}
.aicadmin-filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
}
.aicadmin-filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1 1 160px;
    min-width: 130px;
}
.aicadmin-filter-group label {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.aicadmin-filter-group select,
.aicadmin-filter-group input {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 7px 10px;
    font-size: 13px;
    background: #fff;
    color: #1e293b;
    height: 36px;
}
.aicadmin-filter-group input[type="text"],
.aicadmin-filter-group input[type="search"] {
    flex: 2 1 240px;
    min-width: 200px;
}
.aicadmin-filter-btns {
    display: flex;
    gap: 8px;
    align-items: flex-end;
    padding-bottom: 1px;
}
.aicadmin-btn {
    padding: 8px 18px;
    border-radius: 6px;
    border: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 36px;
    white-space: nowrap;
}
.aicadmin-btn-primary { background: #6366f1; color: #fff; }
.aicadmin-btn-primary:hover { background: #4f46e5; color: #fff; }
.aicadmin-btn-outline { background: #fff; color: #374151; border: 1px solid #d1d5db; }
.aicadmin-btn-outline:hover { background: #f9fafb; color: #374151; }
.aicadmin-btn-csv { background: #16a34a; color: #fff; }
.aicadmin-btn-csv:hover { background: #15803d; color: #fff; }
.aicadmin-result-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}
.aicadmin-result-bar-count {
    font-size: 13px;
    color: #64748b;
}
.aicadmin-table-wrap {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 24px;
}
.aicadmin-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.aicadmin-table th {
    background: #f1f5f9;
    color: #374151;
    font-weight: 600;
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.aicadmin-table td {
    padding: 10px 12px;
    vertical-align: top;
    border-bottom: 1px solid #f1f5f9;
    color: #374151;
}
.aicadmin-table tr:last-child td {
    border-bottom: none;
}
.aicadmin-table tr:hover td {
    background: #fafafa;
}
.aicadmin-text-truncated {
    max-width: 280px;
}
.aicadmin-text-short { display: block; }
.aicadmin-text-full  { display: none; }
.aicadmin-text-expanded .aicadmin-text-short { display: none; }
.aicadmin-text-expanded .aicadmin-text-full  { display: block; }
.aicadmin-toggle-link {
    color: #6366f1;
    cursor: pointer;
    font-size: 11px;
    text-decoration: none;
    display: inline-block;
    margin-top: 3px;
}
.aicadmin-badge {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}
.aicadmin-badge-helpful    { background: #dcfce7; color: #16a34a; }
.aicadmin-badge-nothelpful { background: #fee2e2; color: #dc2626; }
.aicadmin-badge-unrated    { background: #f1f5f9; color: #94a3b8; }
.aicadmin-badge-refused    { background: #fef3c7; color: #d97706; }
.aicadmin-badge-answered   { background: #f0fdf4; color: #16a34a; }
.aicadmin-course-link { color: #6366f1; text-decoration: none; font-weight: 500; }
.aicadmin-course-link:hover { text-decoration: underline; }
.aicadmin-user-link   { color: #1e293b; text-decoration: none; }
.aicadmin-user-link:hover { text-decoration: underline; }
.aicadmin-activity-pill {
    display: inline-block;
    background: #f0f9ff;
    color: #0369a1;
    border-radius: 4px;
    padding: 2px 7px;
    font-size: 11px;
}
.aicadmin-no-results {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
}
.aicadmin-no-results svg {
    margin-bottom: 12px;
    color: #cbd5e1;
}
</style>

<div class="aicadmin-wrap">

    <div class="aicadmin-heading">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
        <h1><?php echo get_string('admin_report_title', 'format_aicourse'); ?></h1>
    </div>

    <!-- Stats -->
    <div class="aicadmin-stats">
        <div class="aicadmin-stat">
            <div class="aicadmin-stat-val"><?php echo number_format($totalqs); ?></div>
            <div class="aicadmin-stat-lbl"><?php echo get_string('admin_report_stat_total', 'format_aicourse'); ?></div>
        </div>
        <div class="aicadmin-stat">
            <div class="aicadmin-stat-val"><?php echo $totalqs > 0 ? round(($helpfulqs / $totalqs) * 100) . '%' : '—'; ?></div>
            <div class="aicadmin-stat-lbl"><?php echo get_string('admin_report_stat_helpful', 'format_aicourse'); ?></div>
        </div>
        <div class="aicadmin-stat">
            <div class="aicadmin-stat-val"><?php echo number_format($refusedqs); ?></div>
            <div class="aicadmin-stat-lbl"><?php echo get_string('admin_report_stat_refused', 'format_aicourse'); ?></div>
        </div>
        <div class="aicadmin-stat">
            <div class="aicadmin-stat-val"><?php echo number_format($activecourses); ?></div>
            <div class="aicadmin-stat-lbl"><?php echo get_string('admin_report_stat_courses', 'format_aicourse'); ?></div>
        </div>
        <div class="aicadmin-stat">
            <div class="aicadmin-stat-val"><?php echo number_format($activestudents); ?></div>
            <div class="aicadmin-stat-lbl"><?php echo get_string('admin_report_stat_students', 'format_aicourse'); ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="aicadmin-filter-card">
        <form method="get" action="">
            <div class="aicadmin-filter-row">

                <div class="aicadmin-filter-group" style="flex: 2 1 240px;">
                    <label for="ag-search"><?php echo get_string('admin_report_search', 'format_aicourse'); ?></label>
                    <input id="ag-search" type="text" name="search"
                           value="<?php echo s($search); ?>"
                           placeholder="<?php echo get_string('aireport_search', 'format_aicourse'); ?>">
                </div>

                <div class="aicadmin-filter-group">
                    <label for="ag-course"><?php echo get_string('admin_report_filter_course', 'format_aicourse'); ?></label>
                    <select id="ag-course" name="filtercourseid">
                        <option value="0"><?php echo get_string('aireport_all_students', 'format_aicourse'); ?> (<?php echo get_string('admin_report_all_courses', 'format_aicourse'); ?>)</option>
                        <?php foreach ($courseoptions as $co): ?>
                            <option value="<?php echo $co->id; ?>" <?php echo $filtercourseid == $co->id ? 'selected' : ''; ?>>
                                <?php echo s(shorten_text($co->fullname, 50)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="aicadmin-filter-group">
                    <label for="ag-user"><?php echo get_string('admin_report_filter_student', 'format_aicourse'); ?></label>
                    <select id="ag-user" name="filteruserid">
                        <option value="0"><?php echo get_string('aireport_all_students', 'format_aicourse'); ?></option>
                        <?php foreach ($useroptions as $u): ?>
                            <option value="<?php echo $u->id; ?>" <?php echo $filteruserid == $u->id ? 'selected' : ''; ?>>
                                <?php echo s($u->lastname . ', ' . $u->firstname); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="aicadmin-filter-group">
                    <label for="ag-rating"><?php echo get_string('admin_report_filter_rating', 'format_aicourse'); ?></label>
                    <select id="ag-rating" name="filterrating">
                        <option value=""><?php echo get_string('aireport_all_ratings', 'format_aicourse'); ?></option>
                        <option value="helpful"    <?php echo $filterrating === 'helpful'    ? 'selected' : ''; ?>><?php echo get_string('aireport_filter_helpful',    'format_aicourse'); ?></option>
                        <option value="nothelpful" <?php echo $filterrating === 'nothelpful' ? 'selected' : ''; ?>><?php echo get_string('aireport_filter_nothelpful', 'format_aicourse'); ?></option>
                        <option value="unrated"    <?php echo $filterrating === 'unrated'    ? 'selected' : ''; ?>><?php echo get_string('admin_report_filter_unrated', 'format_aicourse'); ?></option>
                    </select>
                </div>

                <div class="aicadmin-filter-group">
                    <label for="ag-refused"><?php echo get_string('admin_report_filter_refused', 'format_aicourse'); ?></label>
                    <select id="ag-refused" name="filterrefused">
                        <option value=""><?php echo get_string('admin_report_all', 'format_aicourse'); ?></option>
                        <option value="refused"  <?php echo $filterrefused === 'refused'  ? 'selected' : ''; ?>><?php echo get_string('admin_report_refused_only',  'format_aicourse'); ?></option>
                        <option value="answered" <?php echo $filterrefused === 'answered' ? 'selected' : ''; ?>><?php echo get_string('admin_report_answered_only', 'format_aicourse'); ?></option>
                    </select>
                </div>

                <div class="aicadmin-filter-group">
                    <label for="ag-datefrom"><?php echo get_string('admin_report_filter_datefrom', 'format_aicourse'); ?></label>
                    <input id="ag-datefrom" type="date" name="datefrom" value="<?php echo s($datefromstr); ?>">
                </div>

                <div class="aicadmin-filter-group">
                    <label for="ag-dateto"><?php echo get_string('admin_report_filter_dateto', 'format_aicourse'); ?></label>
                    <input id="ag-dateto" type="date" name="dateto" value="<?php echo s($datetostr); ?>">
                </div>

                <div class="aicadmin-filter-btns">
                    <button type="submit" class="aicadmin-btn aicadmin-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <?php echo get_string('aireport_apply', 'format_aicourse'); ?>
                    </button>
                    <a href="<?php echo (new moodle_url('/course/format/aicourse/admin_report.php'))->out(false); ?>"
                       class="aicadmin-btn aicadmin-btn-outline"><?php echo get_string('admin_report_reset', 'format_aicourse'); ?></a>
                </div>

            </div>
        </form>
    </div>

    <!-- Results bar -->
    <div class="aicadmin-result-bar">
        <span class="aicadmin-result-bar-count">
            <?php echo get_string('admin_report_showing', 'format_aicourse',
                ['from' => $filteredtotal > 0 ? ($page * $perpage + 1) : 0,
                 'to'   => min(($page + 1) * $perpage, $filteredtotal),
                 'total' => number_format($filteredtotal)]); ?>
        </span>
        <?php if ($filteredtotal > 0): ?>
        <a href="<?php
            $csvparams = array_merge($filterparams, ['export' => 1]);
            echo (new moodle_url('/course/format/aicourse/admin_report.php', $csvparams))->out(false);
        ?>" class="aicadmin-btn aicadmin-btn-csv">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <?php echo get_string('admin_report_export_csv', 'format_aicourse'); ?>
        </a>
        <?php endif; ?>
    </div>

    <!-- Table -->
    <div class="aicadmin-table-wrap">
        <?php if (empty($chats)): ?>
        <div class="aicadmin-no-results">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
            <p><?php echo $totalqs > 0 ? get_string('admin_report_no_filtered', 'format_aicourse') : get_string('aireport_no_chats', 'format_aicourse'); ?></p>
        </div>
        <?php else: ?>
        <table class="aicadmin-table">
            <thead>
                <tr>
                    <th><?php echo get_string('aireport_date', 'format_aicourse'); ?></th>
                    <th><?php echo get_string('admin_report_col_course', 'format_aicourse'); ?></th>
                    <th><?php echo get_string('aireport_student', 'format_aicourse'); ?></th>
                    <th><?php echo get_string('admin_report_col_activity', 'format_aicourse'); ?></th>
                    <th><?php echo get_string('aireport_question', 'format_aicourse'); ?></th>
                    <th><?php echo get_string('aireport_response', 'format_aicourse'); ?></th>
                    <th><?php echo get_string('aireport_rating', 'format_aicourse'); ?></th>
                    <th><?php echo get_string('admin_report_col_refused', 'format_aicourse'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($chats as $chat):
                $profileurl = (new moodle_url('/user/view.php', ['id' => $chat->userid]))->out(false);
                $courseurl  = (new moodle_url('/course/view.php',  ['id' => $chat->courseid]))->out(false);
                $actname    = isset($activitynames[$chat->activityid]) ? $activitynames[$chat->activityid] : null;
                $shortq     = shorten_text(strip_tags($chat->question), 160);
                $shortr     = shorten_text(strip_tags($chat->response),  220);
                $fullq      = s($chat->question);
                $fullr      = s($chat->response);
                $needtoggleq = strlen(strip_tags($chat->question)) > 160;
                $needtoggler = strlen(strip_tags($chat->response))  > 220;
            ?>
            <tr>
                <td style="white-space:nowrap; font-size:12px; color:#64748b;">
                    <?php echo userdate($chat->timecreated, '%d %b %Y'); ?><br>
                    <?php echo userdate($chat->timecreated, '%H:%M'); ?>
                </td>
                <td>
                    <a href="<?php echo $courseurl; ?>" class="aicadmin-course-link">
                        <?php echo s(shorten_text($chat->coursefullname, 40)); ?>
                    </a>
                </td>
                <td>
                    <a href="<?php echo $profileurl; ?>" class="aicadmin-user-link">
                        <?php echo s($chat->firstname . ' ' . $chat->lastname); ?>
                    </a>
                </td>
                <td>
                    <?php if ($actname): ?>
                        <span class="aicadmin-activity-pill"><?php echo $actname; ?></span>
                    <?php else: ?>
                        <span style="color:#94a3b8; font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td class="aicadmin-text-truncated">
                    <div class="aicadmin-text-expandable" id="q-<?php echo $chat->id; ?>">
                        <span class="aicadmin-text-short"><?php echo s($shortq); ?></span>
                        <?php if ($needtoggleq): ?>
                        <span class="aicadmin-text-full"><?php echo $fullq; ?></span>
                        <a class="aicadmin-toggle-link" onclick="toggleExpand('q-<?php echo $chat->id; ?>', this)" href="#">
                            <?php echo get_string('admin_report_show_more', 'format_aicourse'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="aicadmin-text-truncated">
                    <div class="aicadmin-text-expandable" id="r-<?php echo $chat->id; ?>">
                        <span class="aicadmin-text-short"><?php echo s($shortr); ?></span>
                        <?php if ($needtoggler): ?>
                        <span class="aicadmin-text-full"><?php echo $fullr; ?></span>
                        <a class="aicadmin-toggle-link" onclick="toggleExpand('r-<?php echo $chat->id; ?>', this)" href="#">
                            <?php echo get_string('admin_report_show_more', 'format_aicourse'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <?php if ($chat->rating == 1): ?>
                        <span class="aicadmin-badge aicadmin-badge-helpful">&#10003; <?php echo get_string('aireport_filter_helpful', 'format_aicourse'); ?></span>
                    <?php elseif ($chat->rating == -1): ?>
                        <span class="aicadmin-badge aicadmin-badge-nothelpful">&#8722; <?php echo get_string('aireport_filter_nothelpful', 'format_aicourse'); ?></span>
                    <?php else: ?>
                        <span class="aicadmin-badge aicadmin-badge-unrated"><?php echo get_string('admin_report_filter_unrated', 'format_aicourse'); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($chat->refused): ?>
                        <span class="aicadmin-badge aicadmin-badge-refused"><?php echo get_string('admin_report_refused', 'format_aicourse'); ?></span>
                    <?php else: ?>
                        <span class="aicadmin-badge aicadmin-badge-answered"><?php echo get_string('admin_report_answered', 'format_aicourse'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Pager -->
    <?php if ($filteredtotal > $perpage): ?>
    <div style="margin-top: 16px;">
        <?php echo $OUTPUT->paging_bar($filteredtotal, $page, $perpage, $baseurl); ?>
    </div>
    <?php endif; ?>

</div>

<script>
function toggleExpand(id, link) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('aicadmin-text-expanded');
    var expanded = el.classList.contains('aicadmin-text-expanded');
    link.textContent = expanded
        ? '<?php echo get_string('admin_report_show_less', 'format_aicourse'); ?>'
        : '<?php echo get_string('admin_report_show_more', 'format_aicourse'); ?>';
    if (link.href !== undefined) { link.href = '#'; }
    return false;
}
document.querySelectorAll('.aicadmin-toggle-link').forEach(function(a) {
    a.addEventListener('click', function(e) {
        e.preventDefault();
        toggleExpand(this.closest('.aicadmin-text-expandable').id, this);
    });
});
</script>

<?php
echo $OUTPUT->footer();
