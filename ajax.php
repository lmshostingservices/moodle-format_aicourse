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
 * Deprecated AJAX endpoint for the AI Course Format.
 *
 * @deprecated since 2.1 - every action is now a proper external function, declared in
 * db/services.php and called from JavaScript through core/ajax. This shim exists only so a page
 * that was cached or left open across the upgrade does not get a 404 from an in-flight request;
 * it forwards to the external function and re-wraps the result in the legacy JSON shape. It will
 * be removed in the release after next. Do not add actions here.
 *
 * The shim keeps the endpoint's original auth guarantees: the course is loaded and the page
 * context set before require_login(), the session key is checked, and every capability check is
 * then performed again inside the external function itself.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

use core_external\external_api;

// Legacy action name => [external function name, [POST parameter name => param type]].
// The courseid parameter is added to every call except the site wide schema repair.
$map = [
    'getprogress' => ['format_aicourse_get_progress', []],
    'saveicon' => ['format_aicourse_save_icon', ['sectionid' => PARAM_INT, 'icon' => PARAM_ALPHANUMEXT]],
    'addsection' => ['format_aicourse_add_section', []],
    'duplicatesection' => ['format_aicourse_duplicate_section', ['sectionid' => PARAM_INT]],
    'deletesection' => ['format_aicourse_delete_section', ['sectionid' => PARAM_INT]],
    'aichat' => ['format_aicourse_ai_chat', [
        'question' => PARAM_TEXT,
        'activityid' => PARAM_INT,
        'sectionid' => PARAM_INT,
        'isfirstmessage' => PARAM_BOOL,
        'questionslot' => PARAM_INT,
        'questiontext' => PARAM_TEXT,
        'allquestions' => PARAM_TEXT,
    ]],
    'ratechat' => ['format_aicourse_rate_chat', ['chatid' => PARAM_INT, 'rating' => PARAM_INT]],
    'correctchat' => ['format_aicourse_correct_chat', ['chatid' => PARAM_INT, 'correction' => PARAM_TEXT]],
    'getactivitycontext' => ['format_aicourse_get_activity_context', [
        'activityid' => PARAM_INT,
        'questionslot' => PARAM_INT,
    ]],
    'generate_banner_image' => ['format_aicourse_generate_banner_image', []],
    'delete_banner_image' => ['format_aicourse_delete_banner_image', []],
];

$action = required_param('action', PARAM_ALPHAEXT);
$courseid = required_param('courseid', PARAM_INT);

// Fetch the course and set the PAGE context BEFORE require_login(): require_login() without a
// Course argument resets $PAGE->context to the system context.
$course = get_course($courseid);
$PAGE->set_context(context_course::instance($courseid));
require_login($course);
require_sesskey();

header('Content-Type: application/json');

if (!isset($map[$action])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'errorcode' => 'unknownaction',
        'error' => get_string('error_unknownaction', 'format_aicourse'),
    ]);
    die();
}

[$function, $paramtypes] = $map[$action];

debugging('format_aicourse/ajax.php is deprecated. Call the external function ' . $function
    . ' instead.', DEBUG_DEVELOPER);

$args = ['courseid' => $courseid];
foreach ($paramtypes as $name => $type) {
    $value = optional_param($name, null, $type);
    if ($value !== null) {
        $args[$name] = $value;
    }
}

// The third argument stays false: this endpoint performs its own require_login() and
// Require_sesskey() above, and each external function performs its own require_capability().
// Every function reachable through this map is registered with 'ajax' => true, so nothing here
// Is exposed that core/ajax would not already expose.
$result = external_api::call_external_function($function, $args, false);

if ($result['error']) {
    $exception = $result['exception'];
    // The old endpoint's stable codes had no 'error_' prefix; the language string keys the
    // External functions raise do. Strip it so a cached client still recognises the code.
    $errorcode = $exception->errorcode ?? 'unknownerror';
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'errorcode' => preg_replace('/^error_/', '', $errorcode),
        'error' => $exception->message ?? get_string('unknownerror'),
    ]);
    die();
}

echo json_encode(array_merge(['success' => true], (array) $result['data']));
