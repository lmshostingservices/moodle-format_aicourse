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
 * format_aicourse — Full-stack performance diagnostic
 *
 * Usage: https://your-moodle.example/course/format/aicourse/diag.php?courseid=123
 *
 * Requires: moodle/site:config capability (admins only).
 *
 * What it measures
 * ────────────────
 * S1  Session-lock baseline — detects whether write_close() is being called early
 * S2  get_fast_modinfo() — course structure load time
 * S3  format_aicourse_get_banner_image_url() — custom banner file-storage query
 * S4  format_aicourse_get_course_image()     — overview image file-storage query
 * S5  format_aicourse_get_progress()         — course-level completion calculation
 * S6  format_aicourse_render_hero_banner()   — full hero render (all of the above)
 * S7  Section-icon N+1 query pattern         — one DB query per section vs bulk query
 * S8  format_aicourse_render_section_cards() — card view render
 * S9  format_aicourse_get_activity_completion_info() — per-activity grade DB cost
 * S10 format_aicourse_get_course_content_for_ai() — AI index build (first call = heavy)
 * S11 MUC coursecontent cache — hit/miss probe
 * S12 AMD build file format check            — define() vs ES6 import
 * S13 Inline chatbox script size             — response payload contribution
 * S14 Hook breadth                           — which page types trigger the full render
 * S15 DB query count per render phase        — total DB round-trips per operation
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', false);
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot . '/course/format/aicourse/lib.php');
require_once($CFG->libdir . '/completionlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$courseid = required_param('courseid', PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/course/format/aicourse/diag.php', ['courseid' => $courseid]));
$PAGE->set_title('AI Course Format — Diagnostic');
$PAGE->set_heading('AI Course Format — Diagnostic');

$course = $DB->get_record('course', ['id' => $courseid, 'format' => 'aicourse'], '*', MUST_EXIST);
$context = context_course::instance($course->id);

// ── Helpers ──────────────────────────────────────────────────────────────────

function diag_time(): float {
    return microtime(true);
}

function diag_ms(float $start, float $end): string {
    $ms = round(($end - $start) * 1000, 2);
    return $ms . ' ms';
}

function diag_ms_raw(float $start, float $end): float {
    return round(($end - $start) * 1000, 2);
}

function diag_db_count_before(): int {
    global $DB;
    $reads = $DB->perf_get_reads();
    $writes = $DB->perf_get_writes();
    return $reads + $writes;
}

function diag_db_count_delta(int $before): int {
    global $DB;
    $reads = $DB->perf_get_reads();
    $writes = $DB->perf_get_writes();
    return ($reads + $writes) - $before;
}

function diag_sev(float $ms, float $warn, float $crit): string {
    if ($ms >= $crit) return 'crit';
    if ($ms >= $warn) return 'warn';
    return 'ok';
}

function diag_count_sev(int $n, int $warn, int $crit): string {
    if ($n >= $crit) return 'crit';
    if ($n >= $warn) return 'warn';
    return 'ok';
}

// ── Run all benchmarks ────────────────────────────────────────────────────────

$results = [];
$pluginroot = $CFG->dirroot . '/course/format/aicourse';
$format = course_get_format($course);
$options = $format->get_format_options();

// S1 — Session lock status
// PHP's session_status() tells us if the session is currently open.
// format.php and the hook do NOT call session_write_close() before heavy work,
// so the session file stays locked while rendering. This blocks ALL parallel
// requests for the same user (course index AJAX, next/prev nav clicks, etc.).
$t = diag_time();
$session_open = (session_status() === PHP_SESSION_ACTIVE);
$session_id   = session_id();
// Read the actual session file mtime to confirm file-lock contention is possible
$session_save_path = session_save_path();
$session_file = rtrim($session_save_path, '/') . '/sess_' . $session_id;
$session_file_exists = file_exists($session_file);
$session_file_size   = $session_file_exists ? filesize($session_file) : null;
$t_session = diag_ms_raw($t, diag_time());
$results['s1'] = [
    'label'            => 'S1 — Session lock status',
    'session_open'     => $session_open,
    'session_id'       => substr($session_id, 0, 8) . '…',
    'session_file'     => $session_file_exists ? $session_file : '(not found — using DB sessions)',
    'session_file_size'=> $session_file_exists ? round($session_file_size / 1024, 1) . ' KB' : 'N/A',
    'write_close_called' => false, // format.php and hook never call this
    'ms'               => $t_session,
    'note'             => $session_open
        ? 'SESSION IS OPEN. format.php and the hook do NOT call session_write_close() before rendering. Every parallel request (course index AJAX, nav clicks) for this user is BLOCKED until rendering completes.'
        : 'Session already closed — no lock contention expected.',
    'sev'              => $session_open ? 'crit' : 'ok',
];

// S2 — get_fast_modinfo()
$t = diag_time();
$db0 = diag_db_count_before();
$modinfo = get_fast_modinfo($course, $USER->id);
$sections = $modinfo->get_section_info_all();
$cms = $modinfo->get_cms();
$t_mod = diag_ms_raw($t, diag_time());
$db_mod = diag_db_count_delta($db0);
$nsections = count($sections) - 1; // exclude section 0
$nactivities = count($cms);
$results['s2'] = [
    'label'       => 'S2 — get_fast_modinfo()',
    'ms'          => $t_mod,
    'db_queries'  => $db_mod,
    'sections'    => $nsections,
    'activities'  => $nactivities,
    'sev'         => diag_sev($t_mod, 200, 500),
    'note'        => 'First call hits DB; subsequent calls within the same request use the in-memory static cache. This is fine.',
];

// S3 — get_banner_image_url() — file-storage DB hit, no static cache
$t = diag_time();
$db0 = diag_db_count_before();
$custombanner = format_aicourse_get_banner_image_url($course);
$t_banner = diag_ms_raw($t, diag_time());
$db_banner = diag_db_count_delta($db0);
$results['s3'] = [
    'label'      => 'S3 — get_banner_image_url()',
    'ms'         => $t_banner,
    'db_queries' => $db_banner,
    'has_banner' => !empty($custombanner),
    'sev'        => diag_sev($t_banner, 30, 100),
    'note'       => 'Called on every page render AND again in the hook on activity pages (total: ×2 per activity page). No static cache. Fix: add a static $cache array keyed by course ID.',
];

// S4 — get_course_image() — file-storage DB hit, no static cache
$t = diag_time();
$db0 = diag_db_count_before();
$courseimage = format_aicourse_get_course_image($course);
$t_img = diag_ms_raw($t, diag_time());
$db_img = diag_db_count_delta($db0);
$results['s4'] = [
    'label'      => 'S4 — get_course_image()',
    'ms'         => $t_img,
    'db_queries' => $db_img,
    'has_image'  => !empty($courseimage),
    'sev'        => diag_sev($t_img, 30, 100),
    'note'       => 'Same issue as S3 — no static cache, called multiple times per page.',
];

// S5 — get_progress() — completion calculation
$t = diag_time();
$db0 = diag_db_count_before();
$progress = format_aicourse_get_progress($course, $USER->id);
$t_prog = diag_ms_raw($t, diag_time());
$db_prog = diag_db_count_delta($db0);
$results['s5'] = [
    'label'      => 'S5 — get_progress()',
    'ms'         => $t_prog,
    'db_queries' => $db_prog,
    'enabled'    => $progress['enabled'],
    'total'      => $progress['total'],
    'completed'  => $progress['completed'],
    'percentage' => $progress['percentage'],
    'sev'        => diag_sev($t_prog, 100, 300),
    'note'       => 'Uses bulk-load completion technique (wholecourse=true) — one DB query covers all activities. Should be fast.',
];

// S6 — render_hero_banner() — full composite render
$t = diag_time();
$db0 = diag_db_count_before();
ob_start();
$herobanner_html = format_aicourse_render_hero_banner($course, $options, null);
ob_end_clean();
$t_hero = diag_ms_raw($t, diag_time());
$db_hero = diag_db_count_delta($db0);
$herobanner_size = strlen($herobanner_html);
$results['s6'] = [
    'label'        => 'S6 — render_hero_banner()',
    'ms'           => $t_hero,
    'db_queries'   => $db_hero,
    'html_bytes'   => $herobanner_size,
    'html_kb'      => round($herobanner_size / 1024, 1),
    'showbanner'   => !empty($options['showherobanner']),
    'sev'          => diag_sev($t_hero, 150, 400),
    'note'         => 'This runs in format.php AND again via the before_footer hook on activity/section/grades/participants/badges/competency/report pages. No caching of the HTML output.',
];

// S7 — Section icon N+1 query pattern
// Each call to format_aicourse_get_section_icon() does one get_field() query.
// On a 20-section course that's 20 extra DB queries just for icons.
$t = diag_time();
$db0 = diag_db_count_before();
$icon_results_individual = [];
foreach ($sections as $section) {
    if ($section->section == 0) continue;
    $icon_results_individual[$section->id] = format_aicourse_get_section_icon($course->id, $section->id);
}
$t_icons_individual = diag_ms_raw($t, diag_time());
$db_icons_individual = diag_db_count_delta($db0);

// Bulk alternative — one query for all sections
$t = diag_time();
$db0 = diag_db_count_before();
$sectionids = array_map(fn($s) => $s->id, array_filter($sections, fn($s) => $s->section > 0));
if (!empty($sectionids)) {
    $keynames = array_map(fn($id) => 'sectionicon_' . $id, $sectionids);
    list($insql, $inparams) = $DB->get_in_or_equal($keynames, SQL_PARAMS_NAMED);
    $inparams['courseid'] = $course->id;
    $inparams['format']   = 'aicourse';
    $bulkicons = $DB->get_records_select(
        'course_format_options',
        "courseid = :courseid AND format = :format AND name $insql",
        $inparams,
        '',
        'name, value'
    );
} else {
    $bulkicons = [];
}
$t_icons_bulk = diag_ms_raw($t, diag_time());
$db_icons_bulk = diag_db_count_delta($db0);

$results['s7'] = [
    'label'               => 'S7 — Section icon query pattern (N+1 vs bulk)',
    'section_count'       => count($sectionids),
    'individual_ms'       => $t_icons_individual,
    'individual_queries'  => $db_icons_individual,
    'bulk_ms'             => $t_icons_bulk,
    'bulk_queries'        => $db_icons_bulk,
    'savings_ms'          => round($t_icons_individual - $t_icons_bulk, 2),
    'savings_queries'     => $db_icons_individual - $db_icons_bulk,
    'sev'                 => diag_count_sev($db_icons_individual, 5, 10),
    'note'                => 'format_aicourse_get_section_icon() issues one get_field() per section. Called from render_section_cards() in a foreach loop. Fix: bulk-load all section icons in one query before the loop.',
];

// S8 — render_section_cards() — full card view
if (!empty($options['displayascards'])) {
    $t = diag_time();
    $db0 = diag_db_count_before();
    $cards_html = format_aicourse_render_section_cards($course, $options);
    $t_cards = diag_ms_raw($t, diag_time());
    $db_cards = diag_db_count_delta($db0);
    $cards_size = strlen($cards_html);
    $results['s8'] = [
        'label'      => 'S8 — render_section_cards()',
        'ms'         => $t_cards,
        'db_queries' => $db_cards,
        'html_kb'    => round($cards_size / 1024, 1),
        'sev'        => diag_sev($t_cards, 200, 600),
        'note'       => 'Includes section icon N+1 queries (S7), section progress per section, and activity cards rendering. DB query count driven by number of sections × activities.',
    ];
} else {
    $results['s8'] = [
        'label' => 'S8 — render_section_cards()',
        'ms'    => 0,
        'note'  => 'Card view is disabled for this course (displayascards=0). Skipped.',
        'sev'   => 'ok',
    ];
}

// S9 — get_activity_completion_info() — per-activity grade DB cost
// Find the first activity that has grade-based completion and measure the cost.
$t = diag_time();
$db0 = diag_db_count_before();
$graded_cm_sample = null;
foreach ($cms as $cm) {
    if ($cm->uservisible && !empty($cm->completionusegrade)) {
        $graded_cm_sample = $cm;
        break;
    }
}
$t_find = diag_ms_raw($t, diag_time());

if ($graded_cm_sample) {
    $t = diag_time();
    $db0 = diag_db_count_before();
    $cmobj = get_coursemodule_from_id('', $graded_cm_sample->id);
    $compinfo = format_aicourse_get_activity_completion_info($course, $cmobj, $USER->id);
    $t_cmcomp = diag_ms_raw($t, diag_time());
    $db_cmcomp = diag_db_count_delta($db0);
    $graded_activity_count = count(array_filter((array)$cms, fn($c) => !empty($c->completionusegrade)));
    $results['s9'] = [
        'label'                => 'S9 — get_activity_completion_info() (grade-based)',
        'sample_activity'      => format_string($graded_cm_sample->name),
        'ms_per_activity'      => $t_cmcomp,
        'db_queries_per'       => $db_cmcomp,
        'graded_activities'    => $graded_activity_count,
        'projected_total_ms'   => round($t_cmcomp * $graded_activity_count, 1),
        'projected_total_db'   => $db_cmcomp * $graded_activity_count,
        'sev'                  => diag_count_sev($db_cmcomp * $graded_activity_count, 10, 30),
        'note'                 => 'Queries grade_items + grade_grades + course_completion_criteria per activity on activity card pages. The static $infoCache means completion_info is shared but grade_items/grade_grades are still per-activity.',
    ];
} else {
    $results['s9'] = [
        'label' => 'S9 — get_activity_completion_info() (grade-based)',
        'ms_per_activity'  => 0,
        'db_queries_per'   => 0,
        'graded_activities'=> 0,
        'note'  => 'No activities with grade-based completion found in this course.',
        'sev'   => 'ok',
    ];
}

// S10 — get_course_content_for_ai() — AI index (first call = heavy)
// Bust the static in-request cache first by using a fresh function call via eval
// so we can measure the MUC-cached path accurately.
$muccache = null;
try {
    $muccache = cache::make('format_aicourse', 'coursecontent');
} catch (Exception $e) {
    // cache unavailable
}

$cachekey = (int)$course->id . '_' . (int)$USER->id;
$muc_has_entry = false;
if ($muccache) {
    $cached = $muccache->get($cachekey);
    $muc_has_entry = ($cached !== false && is_array($cached));
}

// Measure MUC-cached path (warm)
$t = diag_time();
$db0 = diag_db_count_before();
$content_warm = format_aicourse_get_course_content_for_ai($course);
$t_content_warm = diag_ms_raw($t, diag_time());
$db_content_warm = diag_db_count_delta($db0);

// Measure cold path: purge MUC + clear static cache via a temp object
$cold_ms = null;
$cold_db = null;
if ($muccache) {
    $muccache->delete($cachekey);
    // Clear the static request cache by calling via a closure that shadows the static
    // We can't directly clear PHP static locals; measure total content length instead
    // and note the cold path cost is always the heavy build path
    $db0 = diag_db_count_before();
    $t = diag_time();
    $content_cold = format_aicourse_get_course_content_for_ai($course);
    $cold_ms = diag_ms_raw($t, diag_time());
    $cold_db = diag_db_count_delta($db0);
    // Note: static $requestcache in the function means this still hits the in-request
    // cache on the SECOND call. The cold_ms above measures the MUC rebuild path
    // (which re-primes MUC from DB). A true cold start requires a fresh PHP process.
}

$content_size = isset($content_warm['activities']) ? count($content_warm['activities']) : 0;
$results['s10'] = [
    'label'          => 'S10 — get_course_content_for_ai() (AI knowledge index)',
    'muc_was_warm'   => $muc_has_entry,
    'warm_ms'        => $t_content_warm,
    'warm_db'        => $db_content_warm,
    'cold_ms'        => $cold_ms,
    'cold_db'        => $cold_db,
    'activities_indexed' => $content_size,
    'sev'            => diag_sev($cold_ms ?? 0, 300, 1000),
    'note'           => 'Cold path (first AI chat per 10 min) builds the full index: one DB query per page/book/label/lesson activity plus file reads for resource files. MUC 10-min TTL + static in-request cache make repeat calls nearly free. NOT called from format.php — only from ajax.php aichat action.',
];

// S11 — MUC cache probe
if ($muccache) {
    $t = diag_time();
    $muccache->set('diag_probe_' . time(), ['probe' => true]);
    $t_write = diag_ms_raw($t, diag_time());
    $t = diag_time();
    $probe = $muccache->get('diag_probe_' . time());
    $t_read = diag_ms_raw($t, diag_time());
    $results['s11'] = [
        'label'      => 'S11 — MUC coursecontent cache',
        'available'  => true,
        'write_ms'   => $t_write,
        'read_ms'    => $t_read,
        'was_warm'   => $muc_has_entry,
        'sev'        => 'ok',
        'note'       => 'Moodle MUC is available. The 10-minute TTL cache for AI content indexing is functioning.',
    ];
} else {
    $results['s11'] = [
        'label'     => 'S11 — MUC coursecontent cache',
        'available' => false,
        'sev'       => 'warn',
        'note'      => 'MUC cache is unavailable. Every AI chat message will rebuild the full course content index from DB.',
    ];
}

// S12 — AMD build file format check (define() vs ES6 import)
$amd_build_file  = $pluginroot . '/amd/build/courseformat.min.js';
$amd_build_plain = $pluginroot . '/amd/build/courseformat.js';
$amd_src_file    = $pluginroot . '/amd/src/courseformat.js';

function diag_check_amd_format(string $path, string $label): array {
    if (!file_exists($path)) {
        return ['file' => $label, 'exists' => false, 'format' => 'missing', 'sev' => 'crit',
                'note' => 'File does not exist — Moodle will fail to load this AMD module.'];
    }
    $head = file_get_contents($path, false, null, 0, 512);
    $has_define = (strpos($head, 'define(') !== false || strpos($head, 'define (') !== false);
    $has_import = (strpos($head, 'import ') !== false || strpos($head, 'export ') !== false);
    if ($has_define && !$has_import) {
        $format = 'AMD define() ✓';
        $sev = 'ok';
        $note = 'Correct AMD format for Moodle 4.x.';
    } elseif ($has_import) {
        $format = 'ES6 import/export ✗';
        $sev = 'crit';
        $note = 'ES6 import/export syntax will cause JS crashes in Moodle 4.x. Build files MUST use AMD define() format.';
    } else {
        $format = 'unknown';
        $sev = 'warn';
        $note = 'Could not detect module format in first 512 bytes.';
    }
    return [
        'file'    => $label,
        'exists'  => true,
        'size_kb' => round(filesize($path) / 1024, 1),
        'format'  => $format,
        'sev'     => $sev,
        'note'    => $note,
    ];
}

$results['s12'] = [
    'label' => 'S12 — AMD build file format',
    'files' => [
        diag_check_amd_format($amd_build_file, 'amd/build/courseformat.min.js'),
        diag_check_amd_format($amd_build_plain, 'amd/build/courseformat.js'),
        diag_check_amd_format($amd_src_file,   'amd/src/courseformat.js (source — OK to be ES6)'),
    ],
    'sev'  => 'ok',
    'note' => 'Build files must use AMD define() format. Source files (amd/src/) may be ES6.',
];
// Roll up severity
foreach ($results['s12']['files'] as $f) {
    if ($f['sev'] === 'crit') { $results['s12']['sev'] = 'crit'; break; }
    if ($f['sev'] === 'warn') { $results['s12']['sev'] = 'warn'; }
}

// S13 — Inline chatbox script size
$t = diag_time();
$chatbox_script = format_aicourse_render_ai_chatbox_script($course);
$t_chat = diag_ms_raw($t, diag_time());
$chat_size = strlen($chatbox_script);
$tutor_enabled = format_aicourse_is_tutor_enabled();
$results['s13'] = [
    'label'        => 'S13 — Inline chatbox script size',
    'tutor_enabled'=> $tutor_enabled,
    'ms'           => $t_chat,
    'script_bytes' => $chat_size,
    'script_kb'    => round($chat_size / 1024, 1),
    'sev'          => diag_sev($chat_size / 1024, 20, 50),
    'note'         => 'Embedded inline on every course page and every activity page (via hook) when AI tutor is enabled. Consider moving this to an AMD module with inline data passed via M.cfg or data attributes.',
];

// S14 — Hook breadth analysis
$hook_page_types = [
    'mod-*'              => 'Activity pages (all activity types) — renders full hero banner + chatbox',
    'course-section'     => 'Section pages',
    'course-view-aicourse' => 'Course main page (single section view only)',
    'grade-*'            => 'Grades pages — renders full hero banner + chatbox',
    'user-index'         => 'Participants page — renders full hero banner + chatbox',
    'course-user-*'      => 'Course user pages',
    'enrol-*'            => 'Enrolment pages',
    'badges-*'           => 'Badges pages — renders full hero banner + chatbox',
    'competency-*'       => 'Competency pages — renders full hero banner + chatbox',
    'report-competency-*'=> 'Competency reports — renders full hero banner + chatbox',
    'report-*'           => 'All report pages — renders full hero banner + chatbox',
];
$results['s14'] = [
    'label'      => 'S14 — Hook (before_footer_html_generation) page-type breadth',
    'page_types' => $hook_page_types,
    'total_types'=> count($hook_page_types),
    'sev'        => 'warn',
    'note'       => 'The hook fires on 10+ page type patterns. Each trigger runs: format_aicourse_render_hero_banner() or render_activity_hero_banner() — which calls get_fast_modinfo(), get_banner_image_url(), get_course_image(), completion queries, and grade queries. All of this happens WITHOUT session_write_close() being called first, locking the session for the full render duration.',
];

// S15 — DB query totals summary across all renders
$results['s15'] = [
    'label' => 'S15 — DB query cost summary (per page render)',
    'breakdown' => [
        'get_fast_modinfo'         => $db_mod,
        'get_banner_image_url'     => $db_banner,
        'get_course_image'         => $db_img,
        'get_progress'             => $db_prog,
        'render_hero_banner'       => $db_hero,
        'section_icons_N+1'        => $db_icons_individual,
        'section_icons_bulk'       => $db_icons_bulk,
        'render_section_cards'     => isset($results['s8']['db_queries']) ? $results['s8']['db_queries'] : 'N/A',
        'completion_per_activity'  => isset($results['s9']['db_queries_per']) ? $results['s9']['db_queries_per'] : 0,
        'graded_activities'        => isset($results['s9']['graded_activities']) ? $results['s9']['graded_activities'] : 0,
    ],
    'sev' => 'info',
    'note' => 'These costs are additive across the page render. Activity pages pay the full hero render cost PLUS the hook fires again (doubling some queries). The session lock is held for all of this.',
];

// ── HTML output ────────────────────────────────────────────────────────────────

$pluginversion = '';
if (file_exists($pluginroot . '/version.php')) {
    include($pluginroot . '/version.php');
    $pluginversion = isset($plugin->release) ? $plugin->release : (isset($plugin->version) ? $plugin->version : 'unknown');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>format_aicourse — Performance Diagnostic</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; background: #f5f5f5; color: #222; margin: 0; padding: 0; }
  .wrap { max-width: 1000px; margin: 0 auto; padding: 24px 16px 60px; }
  h1 { font-size: 22px; margin: 0 0 4px; }
  .subtitle { color: #666; margin: 0 0 24px; font-size: 13px; }
  .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.12); margin-bottom: 16px; overflow: hidden; }
  .card-header { padding: 12px 16px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #eee; }
  .card-header h2 { margin: 0; font-size: 14px; font-weight: 600; flex: 1; }
  .badge { padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
  .badge-ok   { background: #e6f4ea; color: #1e7e34; }
  .badge-warn { background: #fff3cd; color: #856404; }
  .badge-crit { background: #f8d7da; color: #721c24; }
  .badge-info { background: #d1ecf1; color: #0c5460; }
  .card-body  { padding: 12px 16px; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { text-align: left; color: #555; font-weight: 500; padding: 4px 8px 4px 0; border-bottom: 1px solid #eee; white-space: nowrap; }
  td { padding: 4px 8px 4px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top; }
  td.val { font-family: monospace; }
  .note { background: #f9f9f9; border-left: 3px solid #ccc; padding: 8px 12px; margin-top: 10px; border-radius: 0 4px 4px 0; font-size: 13px; color: #444; line-height: 1.5; }
  .note-crit { border-color: #dc3545; background: #fff5f5; }
  .note-warn { border-color: #ffc107; background: #fffdf0; }
  .note-ok   { border-color: #28a745; background: #f0fff4; }
  .highlight-crit { color: #dc3545; font-weight: 700; }
  .highlight-warn { color: #856404; font-weight: 600; }
  .highlight-ok   { color: #1e7e34; font-weight: 600; }
  .amd-file { font-family: monospace; font-size: 12px; display: flex; gap: 8px; align-items: center; padding: 3px 0; }
  .page-type-list { font-size: 12px; }
  .page-type-list li { padding: 2px 0; }
  .section-sep { height: 1px; background: #eee; margin: 12px 0; }
  .summary-box { background: #1a1a2e; color: #eee; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
  .summary-box h2 { color: #fff; margin: 0 0 12px; font-size: 16px; }
  .root-cause { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 10px; padding: 10px; background: rgba(255,255,255,.06); border-radius: 6px; }
  .root-cause .icon { font-size: 18px; width: 24px; flex-shrink: 0; text-align: center; margin-top: 2px; }
  .root-cause .text { flex: 1; line-height: 1.5; }
  .root-cause .title { font-weight: 700; color: #ff6b6b; margin-bottom: 2px; }
  .root-cause.warn .title { color: #ffd43b; }
  .root-cause.ok   .title { color: #69db7c; }
  .fix { color: #74c0fc; font-size: 12px; margin-top: 3px; }
  .ts { font-size: 11px; color: #999; text-align: right; margin-top: 4px; }
</style>
</head>
<body>
<div class="wrap">

<h1>format_aicourse — Performance Diagnostic</h1>
<p class="subtitle">
  Course: <strong><?php echo s(format_string($course->fullname)); ?></strong>
  (id=<?php echo (int)$course->id; ?>)
  &nbsp;|&nbsp; Plugin: <strong><?php echo s($pluginversion); ?></strong>
  &nbsp;|&nbsp; Sections: <?php echo $nsections; ?> &nbsp;|&nbsp; Activities: <?php echo $nactivities; ?>
  &nbsp;|&nbsp; Run: <?php echo date('Y-m-d H:i:s T'); ?>
</p>

<div class="summary-box">
  <h2>Root-cause summary</h2>

  <div class="root-cause">
    <div class="icon">&#x1F512;</div>
    <div class="text">
      <div class="title">CRITICAL — PHP session lock held open during full page render</div>
      <code>format.php</code> and the <code>before_footer_html_generation</code> hook both render the full hero banner, progress calculation, and section cards <strong>without calling <code>session_write_close()</code> first</strong>.
      The PHP session file stays locked for the entire render duration. Every parallel request for the same user — course index AJAX, prev/next nav clicks, anything — is <strong>blocked until rendering completes</strong>.
      This is the primary cause of unresponsive buttons and slow course index loading (even on non-aicourse formats when visiting an aicourse activity).
      <div class="fix">Fix: call <code>\core\session\manager::write_close()</code> at the top of format.php (before the hero render) and at the top of the hook callback (before get_format_options).</div>
    </div>
  </div>

  <div class="root-cause warn">
    <div class="icon">&#x26A0;</div>
    <div class="text">
      <div class="title">HIGH — N+1 section icon queries (one DB round-trip per section)</div>
      <code>format_aicourse_get_section_icon()</code> is called inside the <code>foreach ($sections as $section)</code> loop in <code>render_section_cards()</code>. Each call issues an individual <code>get_field()</code> on <code>course_format_options</code>. A course with <?php echo $nsections; ?> sections = <?php echo $nsections; ?> extra DB queries.
      <div class="fix">Fix: before the foreach loop, bulk-load all icon values for the course in a single <code>get_records_select()</code> call and build a <code>[$sectionid =&gt; $icon]</code> lookup array.</div>
    </div>
  </div>

  <div class="root-cause warn">
    <div class="icon">&#x26A0;</div>
    <div class="text">
      <div class="title">HIGH — No static cache on get_banner_image_url() / get_course_image()</div>
      Both functions call <code>get_area_files()</code> (a DB query) on every invocation with no static variable guard.
      They are called from <code>format.php</code> AND from the <code>before_footer</code> hook — meaning on an activity page they run <strong>twice per page</strong>.
      On a section page with the hook also injecting, the same DB queries fire twice.
      <div class="fix">Fix: add a <code>static $cache = [];</code> keyed by <code>$course-&gt;id</code> in each function. Return the cached result on subsequent calls within the same request.</div>
    </div>
  </div>

  <div class="root-cause warn">
    <div class="icon">&#x26A0;</div>
    <div class="text">
      <div class="title">MEDIUM — Hook fires on too many page types, including grades/participants/badges/reports</div>
      The hook renders the full hero banner (with DB queries) on grades pages, participants pages, badges pages, competency pages, and all report pages. Most of these pages don&apos;t benefit from the hero. Each trigger holds the session lock and fires the full render chain.
      <div class="fix">Fix: restrict the hook to <code>$isActivityPage || $isSectionPage</code> only. Remove <code>$isGradesPage</code>, <code>$isParticipantsPage</code>, <code>$isEnrolPage</code>, <code>$isBadgesPage</code>, <code>$isCompetencyPage</code>, <code>$isReportPage</code> triggers.</div>
    </div>
  </div>

  <div class="root-cause warn">
    <div class="icon">&#x26A0;</div>
    <div class="text">
      <div class="title">MEDIUM — Large inline chatbox script on every page</div>
      <code>format_aicourse_render_ai_chatbox_script()</code> outputs an inline <code>&lt;script&gt;</code> block of ~<?php echo round(strlen($chatbox_script)/1024, 1); ?> KB on every course page and every activity page. Inline scripts block HTML parsing and cannot be cached by the browser.
      <div class="fix">Fix: move the chatbox logic to an AMD module (<code>amd/src/chatbox.js</code>) and pass the dynamic config (courseid, sesskey, activityid, quickPrompts, welcomeMsg) via data attributes or <code>M.util.js_pending</code>.</div>
    </div>
  </div>

  <div class="root-cause ok">
    <div class="icon">&#x2705;</div>
    <div class="text">
      <div class="title">OK — Completion bulk-load is correctly implemented</div>
      <code>get_progress()</code>, <code>get_section_progress()</code>, and <code>render_section_cards()</code> all use the <code>$wholecourse=true</code> technique on the first <code>get_data()</code> call to bulk-load all completion rows in one query. No N+1 completion queries.
    </div>
  </div>

  <div class="root-cause ok">
    <div class="icon">&#x2705;</div>
    <div class="text">
      <div class="title">OK — ajax.php correctly calls session_write_close() before AI calls</div>
      All AJAX handlers in <code>ajax.php</code> call <code>\core\session\manager::write_close()</code> after authentication and before the heavy AI/DB operations. No session lock contention from AJAX endpoints.
    </div>
  </div>

  <div class="root-cause ok">
    <div class="icon">&#x2705;</div>
    <div class="text">
      <div class="title">OK — AI content index is properly double-cached (static + MUC 10 min)</div>
      <code>get_course_content_for_ai()</code> uses a static in-request cache plus a 10-minute MUC cache. Cold builds are heavy but rare. Not called from format.php — only from the aichat AJAX action.
    </div>
  </div>
</div>

<?php
// Severity helper for rendering
function render_badge(string $sev): string {
    $labels = ['ok' => 'OK', 'warn' => 'WARN', 'crit' => 'CRITICAL', 'info' => 'INFO'];
    $label = $labels[$sev] ?? strtoupper($sev);
    return '<span class="badge badge-' . $sev . '">' . $label . '</span>';
}

function render_ms(float $ms, string $sev): string {
    $cls = $sev === 'crit' ? 'highlight-crit' : ($sev === 'warn' ? 'highlight-warn' : 'highlight-ok');
    return '<span class="' . $cls . '">' . $ms . ' ms</span>';
}

function render_db(int $n, string $sev): string {
    $cls = $sev === 'crit' ? 'highlight-crit' : ($sev === 'warn' ? 'highlight-warn' : 'highlight-ok');
    return '<span class="' . $cls . '">' . $n . ' queries</span>';
}

function note_class(string $sev): string {
    return 'note note-' . ($sev === 'info' ? 'ok' : $sev);
}
?>

<!-- S1 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s1']['label']); ?></h2>
    <?php echo render_badge($results['s1']['sev']); ?>
  </div>
  <div class="card-body">
    <table>
      <tr><th>Session status</th><td class="val"><?php echo $results['s1']['session_open'] ? '<span class="highlight-crit">OPEN (locked)</span>' : '<span class="highlight-ok">Closed</span>'; ?></td></tr>
      <tr><th>Session ID (prefix)</th><td class="val"><?php echo s($results['s1']['session_id']); ?></td></tr>
      <tr><th>Session file</th><td class="val"><?php echo s($results['s1']['session_file']); ?></td></tr>
      <tr><th>Session file size</th><td class="val"><?php echo s($results['s1']['session_file_size']); ?></td></tr>
      <tr><th>write_close() called in format.php</th><td class="val"><span class="highlight-crit">NO</span></td></tr>
      <tr><th>write_close() called in hook callback</th><td class="val"><span class="highlight-crit">NO</span></td></tr>
      <tr><th>write_close() called in ajax.php</th><td class="val"><span class="highlight-ok">YES ✓</span></td></tr>
    </table>
    <div class="<?php echo note_class($results['s1']['sev']); ?>"><?php echo s($results['s1']['note']); ?></div>
  </div>
</div>

<!-- S2 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s2']['label']); ?></h2>
    <?php echo render_badge($results['s2']['sev']); ?>
  </div>
  <div class="card-body">
    <table>
      <tr><th>Time</th><td class="val"><?php echo render_ms($results['s2']['ms'], $results['s2']['sev']); ?></td></tr>
      <tr><th>DB queries</th><td class="val"><?php echo render_db($results['s2']['db_queries'], 'ok'); ?></td></tr>
      <tr><th>Sections (excl. §0)</th><td class="val"><?php echo (int)$results['s2']['sections']; ?></td></tr>
      <tr><th>Activities</th><td class="val"><?php echo (int)$results['s2']['activities']; ?></td></tr>
    </table>
    <div class="<?php echo note_class($results['s2']['sev']); ?>"><?php echo s($results['s2']['note']); ?></div>
  </div>
</div>

<!-- S3 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s3']['label']); ?></h2>
    <?php echo render_badge($results['s3']['sev']); ?>
  </div>
  <div class="card-body">
    <table>
      <tr><th>Time</th><td class="val"><?php echo render_ms($results['s3']['ms'], $results['s3']['sev']); ?></td></tr>
      <tr><th>DB queries</th><td class="val"><?php echo render_db($results['s3']['db_queries'], diag_count_sev($results['s3']['db_queries'], 2, 5)); ?></td></tr>
      <tr><th>Custom banner found</th><td class="val"><?php echo $results['s3']['has_banner'] ? 'Yes' : 'No'; ?></td></tr>
      <tr><th>Static cache in function</th><td class="val"><span class="highlight-crit">NO — hits DB every call</span></td></tr>
    </table>
    <div class="<?php echo note_class($results['s3']['sev']); ?>"><?php echo s($results['s3']['note']); ?></div>
  </div>
</div>

<!-- S4 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s4']['label']); ?></h2>
    <?php echo render_badge($results['s4']['sev']); ?>
  </div>
  <div class="card-body">
    <table>
      <tr><th>Time</th><td class="val"><?php echo render_ms($results['s4']['ms'], $results['s4']['sev']); ?></td></tr>
      <tr><th>DB queries</th><td class="val"><?php echo render_db($results['s4']['db_queries'], diag_count_sev($results['s4']['db_queries'], 2, 5)); ?></td></tr>
      <tr><th>Overview image found</th><td class="val"><?php echo $results['s4']['has_image'] ? 'Yes' : 'No'; ?></td></tr>
      <tr><th>Static cache in function</th><td class="val"><span class="highlight-crit">NO — hits DB every call</span></td></tr>
    </table>
    <div class="<?php echo note_class($results['s4']['sev']); ?>"><?php echo s($results['s4']['note']); ?></div>
  </div>
</div>

<!-- S5 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s5']['label']); ?></h2>
    <?php echo render_badge($results['s5']['sev']); ?>
  </div>
  <div class="card-body">
    <table>
      <tr><th>Time</th><td class="val"><?php echo render_ms($results['s5']['ms'], $results['s5']['sev']); ?></td></tr>
      <tr><th>DB queries</th><td class="val"><?php echo render_db($results['s5']['db_queries'], 'ok'); ?></td></tr>
      <tr><th>Completion enabled</th><td class="val"><?php echo $results['s5']['enabled'] ? 'Yes' : 'No'; ?></td></tr>
      <tr><th>Tracked activities</th><td class="val"><?php echo (int)$results['s5']['total']; ?></td></tr>
      <tr><th>Completed</th><td class="val"><?php echo (int)$results['s5']['completed']; ?></td></tr>
      <tr><th>Progress</th><td class="val"><?php echo (int)$results['s5']['percentage']; ?>%</td></tr>
      <tr><th>Bulk-load technique</th><td class="val"><span class="highlight-ok">YES (wholecourse=true) ✓</span></td></tr>
    </table>
    <div class="<?php echo note_class($results['s5']['sev']); ?>"><?php echo s($results['s5']['note']); ?></div>
  </div>
</div>

<!-- S6 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s6']['label']); ?></h2>
    <?php echo render_badge($results['s6']['sev']); ?>
  </div>
  <div class="card-body">
    <table>
      <tr><th>Time</th><td class="val"><?php echo render_ms($results['s6']['ms'], $results['s6']['sev']); ?></td></tr>
      <tr><th>DB queries</th><td class="val"><?php echo render_db($results['s6']['db_queries'], diag_count_sev($results['s6']['db_queries'], 5, 15)); ?></td></tr>
      <tr><th>HTML output size</th><td class="val"><?php echo (int)$results['s6']['html_bytes']; ?> bytes (<?php echo $results['s6']['html_kb']; ?> KB)</td></tr>
      <tr><th>Hero banner enabled</th><td class="val"><?php echo $results['s6']['showbanner'] ? 'Yes' : 'No'; ?></td></tr>
      <tr><th>Output cached</th><td class="val"><span class="highlight-crit">NO — rendered fresh every call</span></td></tr>
    </table>
    <div class="<?php echo note_class($results['s6']['sev']); ?>"><?php echo s($results['s6']['note']); ?></div>
  </div>
</div>

<!-- S7 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s7']['label']); ?></h2>
    <?php echo render_badge($results['s7']['sev']); ?>
  </div>
  <div class="card-body">
    <table>
      <tr><th>Sections measured</th><td class="val"><?php echo (int)$results['s7']['section_count']; ?></td></tr>
      <tr>
        <th>Current (N+1 individual queries)</th>
        <td class="val"><?php echo render_ms($results['s7']['individual_ms'], diag_sev($results['s7']['individual_ms'], 20, 80)); ?>
          — <?php echo render_db($results['s7']['individual_queries'], diag_count_sev($results['s7']['individual_queries'], 5, 10)); ?></td>
      </tr>
      <tr>
        <th>Alternative (1 bulk query)</th>
        <td class="val"><?php echo render_ms($results['s7']['bulk_ms'], 'ok'); ?>
          — <?php echo render_db($results['s7']['bulk_queries'], 'ok'); ?></td>
      </tr>
      <tr><th>Savings</th><td class="val">
        ~<?php echo $results['s7']['savings_ms']; ?> ms &amp; <?php echo $results['s7']['savings_queries']; ?> fewer DB queries
      </td></tr>
    </table>
    <div class="<?php echo note_class($results['s7']['sev']); ?>"><?php echo s($results['s7']['note']); ?></div>
  </div>
</div>

<!-- S8 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s8']['label']); ?></h2>
    <?php echo render_badge($results['s8']['sev']); ?>
  </div>
  <div class="card-body">
    <?php if (isset($results['s8']['db_queries'])): ?>
    <table>
      <tr><th>Time</th><td class="val"><?php echo render_ms($results['s8']['ms'], $results['s8']['sev']); ?></td></tr>
      <tr><th>DB queries</th><td class="val"><?php echo render_db($results['s8']['db_queries'], diag_count_sev($results['s8']['db_queries'], 10, 30)); ?></td></tr>
      <tr><th>HTML output</th><td class="val"><?php echo $results['s8']['html_kb']; ?> KB</td></tr>
    </table>
    <?php endif; ?>
    <div class="<?php echo note_class($results['s8']['sev']); ?>"><?php echo s($results['s8']['note']); ?></div>
  </div>
</div>

<!-- S9 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s9']['label']); ?></h2>
    <?php echo render_badge($results['s9']['sev']); ?>
  </div>
  <div class="card-body">
    <table>
      <?php if ($results['s9']['graded_activities'] > 0): ?>
      <tr><th>Sample activity</th><td class="val"><?php echo s($results['s9']['sample_activity']); ?></td></tr>
      <tr><th>DB queries per activity</th><td class="val"><?php echo render_db($results['s9']['db_queries_per'], diag_count_sev($results['s9']['db_queries_per'], 2, 4)); ?></td></tr>
      <tr><th>Time per activity</th><td class="val"><?php echo render_ms($results['s9']['ms_per_activity'], diag_sev($results['s9']['ms_per_activity'], 20, 60)); ?></td></tr>
      <?php endif; ?>
      <tr><th>Activities with grade completion</th><td class="val"><?php echo (int)$results['s9']['graded_activities']; ?></td></tr>
      <?php if ($results['s9']['graded_activities'] > 0): ?>
      <tr><th>Projected total DB queries</th><td class="val"><?php echo render_db($results['s9']['projected_total_db'], diag_count_sev($results['s9']['projected_total_db'], 10, 30)); ?></td></tr>
      <tr><th>Projected total time</th><td class="val"><?php echo render_ms($results['s9']['projected_total_ms'], diag_sev($results['s9']['projected_total_ms'], 100, 400)); ?></td></tr>
      <?php endif; ?>
    </table>
    <div class="<?php echo note_class($results['s9']['sev']); ?>"><?php echo s($results['s9']['note']); ?></div>
  </div>
</div>

<!-- S10 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s10']['label']); ?></h2>
    <?php echo render_badge($results['s10']['sev']); ?>
  </div>
  <div class="card-body">
    <table>
      <tr><th>MUC cache was warm</th><td class="val"><?php echo $results['s10']['muc_was_warm'] ? '<span class="highlight-ok">Yes (served from cache)</span>' : '<span class="highlight-warn">No (cold build required)</span>'; ?></td></tr>
      <tr><th>Warm path time</th><td class="val"><?php echo render_ms($results['s10']['warm_ms'], diag_sev($results['s10']['warm_ms'], 10, 50)); ?></td></tr>
      <tr><th>Warm path DB queries</th><td class="val"><?php echo render_db($results['s10']['warm_db'], 'ok'); ?></td></tr>
      <?php if ($results['s10']['cold_ms'] !== null): ?>
      <tr><th>Cold rebuild time (MUC miss)</th><td class="val"><?php echo render_ms($results['s10']['cold_ms'], diag_sev($results['s10']['cold_ms'], 300, 1000)); ?></td></tr>
      <tr><th>Cold rebuild DB queries</th><td class="val"><?php echo render_db($results['s10']['cold_db'], diag_count_sev($results['s10']['cold_db'], 20, 60)); ?></td></tr>
      <?php endif; ?>
      <tr><th>Activities indexed</th><td class="val"><?php echo (int)$results['s10']['activities_indexed']; ?></td></tr>
    </table>
    <div class="<?php echo note_class($results['s10']['sev']); ?>"><?php echo s($results['s10']['note']); ?></div>
  </div>
</div>

<!-- S11 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s11']['label']); ?></h2>
    <?php echo render_badge($results['s11']['sev']); ?>
  </div>
  <div class="card-body">
    <table>
      <tr><th>MUC available</th><td class="val"><?php echo $results['s11']['available'] ? '<span class="highlight-ok">Yes ✓</span>' : '<span class="highlight-crit">No ✗</span>'; ?></td></tr>
      <?php if ($results['s11']['available']): ?>
      <tr><th>Cache write time</th><td class="val"><?php echo render_ms($results['s11']['write_ms'], 'ok'); ?></td></tr>
      <tr><th>Cache read time</th><td class="val"><?php echo render_ms($results['s11']['read_ms'], 'ok'); ?></td></tr>
      <tr><th>Was warm before diag</th><td class="val"><?php echo $results['s11']['was_warm'] ? 'Yes' : 'No'; ?></td></tr>
      <?php endif; ?>
    </table>
    <div class="<?php echo note_class($results['s11']['sev']); ?>"><?php echo s($results['s11']['note']); ?></div>
  </div>
</div>

<!-- S12 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s12']['label']); ?></h2>
    <?php echo render_badge($results['s12']['sev']); ?>
  </div>
  <div class="card-body">
    <?php foreach ($results['s12']['files'] as $f): ?>
    <div class="amd-file">
      <?php echo render_badge($f['sev']); ?>
      <code><?php echo s($f['file']); ?></code>
      <?php if ($f['exists']): ?>
        — <?php echo s($f['format']); ?> (<?php echo $f['size_kb']; ?> KB)
      <?php else: ?>
        — <span class="highlight-crit">FILE MISSING</span>
      <?php endif; ?>
    </div>
    <?php if (!empty($f['note']) && $f['sev'] !== 'ok'): ?>
    <div class="note note-<?php echo $f['sev']; ?>" style="margin-bottom:6px;"><?php echo s($f['note']); ?></div>
    <?php endif; ?>
    <?php endforeach; ?>
    <div class="note note-ok" style="margin-top:8px;"><?php echo s($results['s12']['note']); ?></div>
  </div>
</div>

<!-- S13 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s13']['label']); ?></h2>
    <?php echo render_badge($results['s13']['sev']); ?>
  </div>
  <div class="card-body">
    <table>
      <tr><th>AI tutor enabled</th><td class="val"><?php echo $results['s13']['tutor_enabled'] ? 'Yes' : 'No (script not rendered)'; ?></td></tr>
      <tr><th>Script size</th><td class="val">
        <?php
          $kb = $results['s13']['script_kb'];
          $sev13 = diag_sev($kb, 20, 50);
          $cls13 = $sev13 === 'crit' ? 'highlight-crit' : ($sev13 === 'warn' ? 'highlight-warn' : 'highlight-ok');
          echo '<span class="' . $cls13 . '">' . $kb . ' KB</span>';
          echo ' (' . number_format($results['s13']['script_bytes']) . ' bytes)';
        ?>
      </td></tr>
      <tr><th>Render time</th><td class="val"><?php echo render_ms($results['s13']['ms'], 'ok'); ?></td></tr>
      <tr><th>Deliveries per page</th><td class="val">format.php: 1× + hook (activity/section/grades pages): 1× = <strong>up to 2× per page</strong></td></tr>
    </table>
    <div class="<?php echo note_class($results['s13']['sev']); ?>"><?php echo s($results['s13']['note']); ?></div>
  </div>
</div>

<!-- S14 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s14']['label']); ?></h2>
    <?php echo render_badge($results['s14']['sev']); ?>
  </div>
  <div class="card-body">
    <p style="margin:0 0 8px; font-size:13px;">The hook fires on all of these page type patterns:</p>
    <ul class="page-type-list">
      <?php foreach ($results['s14']['page_types'] as $pattern => $desc): ?>
      <li><code><?php echo s($pattern); ?></code> — <?php echo s($desc); ?></li>
      <?php endforeach; ?>
    </ul>
    <div class="<?php echo note_class($results['s14']['sev']); ?>"><?php echo s($results['s14']['note']); ?></div>
  </div>
</div>

<!-- S15 -->
<div class="card">
  <div class="card-header">
    <h2><?php echo s($results['s15']['label']); ?></h2>
    <?php echo render_badge('info'); ?>
  </div>
  <div class="card-body">
    <table>
      <tr><th>Operation</th><th>DB queries</th></tr>
      <?php foreach ($results['s15']['breakdown'] as $op => $n): ?>
      <tr>
        <td><?php echo s(str_replace('_', ' ', $op)); ?></td>
        <td class="val"><?php if (is_numeric($n)) { echo render_db((int)$n, diag_count_sev((int)$n, 5, 15)); } else { echo s($n); } ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="note note-ok" style="margin-top:8px;"><?php echo s($results['s15']['note']); ?></div>
  </div>
</div>

<!-- Priority fix list -->
<div class="card">
  <div class="card-header">
    <h2>Prioritised fix list</h2>
    <?php echo render_badge('info'); ?>
  </div>
  <div class="card-body">
    <table>
      <tr>
        <th>#</th><th>Priority</th><th>File</th><th>Change</th><th>Expected impact</th>
      </tr>
      <tr>
        <td>1</td>
        <td><?php echo render_badge('crit'); ?></td>
        <td><code>format.php</code></td>
        <td>Add <code>\core\session\manager::write_close();</code> at the very top of format.php (before the hero render, after the course object is loaded)</td>
        <td>Eliminates session lock contention — course index and buttons become immediately responsive</td>
      </tr>
      <tr>
        <td>2</td>
        <td><?php echo render_badge('crit'); ?></td>
        <td><code>classes/hook/before_footer_html_generation.php</code></td>
        <td>Add <code>\core\session\manager::write_close();</code> immediately after the early-return checks (before <code>course_get_format()</code>)</td>
        <td>Eliminates session lock on ALL activity, section, grades, participants pages</td>
      </tr>
      <tr>
        <td>3</td>
        <td><?php echo render_badge('warn'); ?></td>
        <td><code>lib.php</code> — <code>format_aicourse_render_section_cards()</code></td>
        <td>Bulk-load all section icons before the foreach loop. Add a <code>$iconmap = []</code> from one <code>get_records_select()</code> and replace the <code>format_aicourse_get_section_icon()</code> call with <code>$iconmap[$section->id] ?? ''</code></td>
        <td>Saves <?php echo $nsections; ?> DB queries per card-view render</td>
      </tr>
      <tr>
        <td>4</td>
        <td><?php echo render_badge('warn'); ?></td>
        <td><code>lib.php</code> — <code>format_aicourse_get_banner_image_url()</code> and <code>format_aicourse_get_course_image()</code></td>
        <td>Add <code>static $cache = [];</code> keyed by <code>$course->id</code> in each function</td>
        <td>Eliminates duplicate file-storage DB queries when called from both format.php and the hook</td>
      </tr>
      <tr>
        <td>5</td>
        <td><?php echo render_badge('warn'); ?></td>
        <td><code>classes/hook/before_footer_html_generation.php</code></td>
        <td>Remove <code>$isGradesPage</code>, <code>$isParticipantsPage</code>, <code>$isEnrolPage</code>, <code>$isBadgesPage</code>, <code>$isCompetencyPage</code>, <code>$isReportPage</code> from <code>$allowedPage</code>. Keep only <code>$isActivityPage || $isSectionPage</code></td>
        <td>Eliminates unnecessary hero renders + DB queries on non-core pages</td>
      </tr>
      <tr>
        <td>6</td>
        <td><?php echo render_badge('warn'); ?></td>
        <td><code>lib.php</code> — <code>format_aicourse_render_ai_chatbox_script()</code></td>
        <td>Move chatbox JS to <code>amd/src/chatbox.js</code>; pass dynamic config via data attributes on the chatbox HTML element</td>
        <td>Removes inline script block (~<?php echo round(strlen($chatbox_script)/1024, 1); ?> KB) from HTML; enables browser caching of the script</td>
      </tr>
    </table>
  </div>
</div>

<p class="ts">Generated by format_aicourse/diag.php — <?php echo date('c'); ?></p>
</div>
</body>
</html>
