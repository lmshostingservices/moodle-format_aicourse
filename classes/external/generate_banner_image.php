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
use format_aicourse\local\banner;

/**
 * Web service generating a course banner image with the remote AI image service.
 *
 * Replaces the 'generate_banner_image' action of the plugin's deprecated ajax.php endpoint.
 *
 * SECURITY, all carried over from ajax.php:
 *  - moodle/course:update is required;
 *  - guests are refused outright, because the call spends purchased API credits;
 *  - the call is rate limited per user per course (see \format_aicourse\external\throttle);
 *  - the returned bytes are strictly base64 decoded, size capped and confirmed to be an image of
 *    an allowed type before they are stored, and the file extension comes from the detected type.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_banner_image extends external_api {
    /** @var string Endpoint of the remote banner generation service. */
    protected const API_URL = 'https://lms-labs.com/api/moodle/aicourse/generate-banner';

    /** @var int Maximum accepted size in bytes for a generated banner image. */
    protected const MAX_BANNER_BYTES = 5 * 1024 * 1024;

    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Id of the course to generate a banner for'),
            // 2.1.191: the teacher's own extra direction for the image, e.g. "warm evening light,
            // no people". Optional and defaulted, so an older cached courseformat.js that does not
            // send it still calls this function successfully rather than failing validation.
            'extraprompt' => new external_value(
                PARAM_TEXT,
                'Optional extra detail from the teacher, added to the image prompt',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Generate and store a banner image for the course.
     *
     * @param int $courseid Id of the course.
     * @return array The URL of the stored image and the credits it cost.
     */
    public static function execute(int $courseid, string $extraprompt = ''): array {
        global $CFG, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'extraprompt' => $extraprompt,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        // ACF-FIX-2.0: Guests must never spend API credits.
        if (isguestuser()) {
            throw new \moodle_exception('error_guestnotallowed', 'format_aicourse');
        }

        // ACF-FIX-2.0: Banner generation is a 90 second call that spends purchased credits.
        throttle::check(
            'bannerimage',
            $course->id,
            (int) $USER->id,
            throttle::BANNER_MAX,
            throttle::BANNER_WINDOW
        );

        // ACF-FIX-2.1.42: validate credentials HERE, before queueing.
        //
        // The first version of this split left the check to the task, which meant a site with no
        // API key queued happily and only reported the problem when cron got round to it -- the
        // teacher saw a spinner, then a failure minutes later, for something knowable instantly.
        // The unit tests caught it. The check runs in both places: here so the teacher is told
        // at once, and again in the task because configuration can change between the two.
        credentials::require_configured();

        // ACF-FIX-2.1.42: queue the work, do not do it here.
        //
        // Generation takes about 110 seconds. Done inline it had to outlive every intermediary
        // between the browser and PHP, and the shortest timeout won: a reverse proxy at 60s,
        // Cloudflare's fixed 100s on its lower tiers, PHP-FPM at 30s. Sites behind one saw a 504
        // and no banner. Raising this plugin's cURL timeout could never have helped -- the
        // connection was already severed upstream of PHP.
        //
        // The browser now gets an answer immediately and polls get_banner_status for the result.
        // 2.1.191: the extra prompt travels with the task, not in a session or a cache. The task
        // may run minutes later in cron, in a different process, for a user who has since logged
        // out -- custom data is the only thing that survives that journey. Capped at 300
        // characters here, which is the authoritative cap; the textarea's maxlength and the JS
        // slice are conveniences, and neither is trusted.
        $extra = \core_text::substr(trim($params['extraprompt']), 0, 300);

        $task = new \format_aicourse\task\generate_banner();
        $task->set_custom_data([
            'courseid' => (int) $course->id,
            'extraprompt' => $extra,
        ]);
        $task->set_component('format_aicourse');
        self::set_status((int) $course->id, 'queued', '');
        \core\task\manager::queue_adhoc_task($task);

        return [
            'status' => 'queued',
            'imageurl' => '',
            'message' => '',
        ];
    }

    /**
     * Record the state of a course's banner generation.
     *
     * Stored in plugin config rather than a new table: it is one short string per course that is
     * read a few times a minute while a teacher watches a spinner and is meaningless afterwards.
     * A table would need an install step, an upgrade step, a backup decision and a privacy
     * declaration for data with a lifetime of about two minutes.
     *
     * @param int $courseid The course being generated for.
     * @param string $state One of queued, running, done, failed.
     * @param string $detail The image URL when done, the failure reason when failed.
     * @return void
     */
    public static function set_status(int $courseid, string $state, string $detail): void {
        set_config(
            'bannerstatus_' . $courseid,
            json_encode(['state' => $state, 'detail' => $detail, 'time' => time()]),
            'format_aicourse'
        );
    }

    /**
     * Read back the state of a course's banner generation.
     *
     * @param int $courseid The course to report on.
     * @return array{state: string, detail: string, time: int}
     */
    public static function get_status(int $courseid): array {
        $raw = get_config('format_aicourse', 'bannerstatus_' . $courseid);
        if ($raw === false || $raw === '') {
            return ['state' => 'idle', 'detail' => '', 'time' => 0];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['state'])) {
            return ['state' => 'idle', 'detail' => '', 'time' => 0];
        }
        return [
            'state' => (string) $decoded['state'],
            'detail' => (string) ($decoded['detail'] ?? ''),
            'time' => (int) ($decoded['time'] ?? 0),
        ];
    }

    /**
     * Call the image service and store the result against the course.
     *
     * This is the whole of the previous synchronous implementation, unchanged apart from being
     * reachable from the adhoc task. Keeping it byte-identical in behaviour is deliberate: the
     * request shape, the credit cost and the validation of the response are all as they were, so
     * moving the work off the web request cannot alter what the service is asked for or what is
     * accepted back.
     *
     * @param \stdClass $course The course to generate for.
     * @return string The moodle_url of the stored image.
     */
    public static function generate_and_store(\stdClass $course, string $extraprompt = ''): string {
        global $CFG;

        $context = \context_course::instance($course->id);

        [$siteid, $apikey] = credentials::require_configured();

        $postdata = [
            'siteUrl' => $siteid,
            'apiKey' => $apikey,
            // ACF-FIX-2.1.191: the image service is given a course name to read, not markup to
            // render. It was being sent "Research Design &amp; Industry Context".
            'courseName' => \format_aicourse\local\text::plain($course->fullname, $context),
            'courseShortname' => $course->shortname,
            'courseId' => $course->id,
        ];

        // 2.1.191: sent only when there is something to send. An empty key would ask the image
        // service to reason about a blank instruction, and the parameter is new on this side --
        // omitting it entirely is what a service that has not been updated for it expects.
        $extraprompt = trim($extraprompt);
        if ($extraprompt !== '') {
            $postdata['extraDetail'] = \core_text::substr($extraprompt, 0, 300);
        }

        // ACF-FIX-2.1.10: 180 seconds, not 90.
        //
        // Banner generation genuinely takes about two minutes end to end at the moment: the
        // remote service's primary image model has been retired upstream, so every request now
        // fails against it first and falls back to a second, slower provider. At 90 seconds this
        // client hung up while the server was still working -- the server then finished
        // successfully, charged the account 5 credits, and returned an image to a connection that
        // had already gone. The user saw only "Generation failed", and had paid for it.
        //
        // The PHP time limit has to be raised with it, or the request is killed by the script
        // limit before cURL ever returns.
        \core_php_time_limit::raise(300);

        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 180,
            // Distinct from the read timeout: a service that is down should fail fast rather than
            // holding the user on the spinner for the full three minutes.
            'CURLOPT_CONNECTTIMEOUT' => 30,
        ]);
        $curl->setHeader(['Content-Type: application/json', 'Accept: application/json']);
        $response = $curl->post(self::API_URL, json_encode($postdata));
        $httpcode = (int) ($curl->info['http_code'] ?? 0);

        if ($curl->error || $httpcode !== 200) {
            // ACF-FIX-2.1.26: say WHY.
            //
            // This used to log the remote body at DEBUG_DEVELOPER and show the user
            // "Generation failed. Please try again." -- which is unactionable, and on a
            // production site with debugging off the real reason was written nowhere the
            // teacher could reach. Retrying a 402 or a bad API key forever is not a plan.
            //
            // Showing the detail is safe here: execute() already required
            // moodle/course:update, so the only people who can reach this line are course
            // editors. The remote body is truncated and stripped of tags before display.
            debugging('format_aicourse generate_banner_image HTTP ' . $httpcode . ' ' . $curl->error . ' '
                . substr((string) $response, 0, 500), DEBUG_DEVELOPER);
            throw new \moodle_exception(
                'error_bannerfailed_detail',
                'format_aicourse',
                '',
                self::describe_failure($httpcode, (string) $curl->error, (string) $response)
            );
        }

        $result = json_decode($response, true);
        if (!$result || empty($result['success']) || empty($result['imageBase64'])) {
            debugging('format_aicourse generate_banner_image no image in '
                . substr((string) $response, 0, 500), DEBUG_DEVELOPER);
            // A 200 with no image is the shape the service returns when it is out of credits
            // or every image provider it tried failed, and its own message says which.
            $remote = '';
            if (is_array($result)) {
                foreach (['error', 'message', 'reason', 'detail'] as $key) {
                    if (!empty($result[$key]) && is_string($result[$key])) {
                        $remote = $result[$key];
                        break;
                    }
                }
            }
            throw new \moodle_exception(
                'error_bannerfailed_detail',
                'format_aicourse',
                '',
                $remote !== ''
                    ? self::clean_remote_message($remote)
                    : get_string('error_bannernoimage', 'format_aicourse')
            );
        }

        // ACF-FIX-2.0: strict base64 decoding — the old call silently accepted garbage.
        $imagedata = base64_decode($result['imageBase64'], true);
        if ($imagedata === false || $imagedata === '') {
            throw new \moodle_exception('error_bannerinvalidimage', 'format_aicourse');
        }

        // ACF-FIX-2.0: the response body used to be written straight to a .jpg with no checks at
        // all. Enforce a size ceiling, confirm the bytes really are an image of an allowed type,
        // and derive the extension from the detected type rather than assuming JPEG.
        if (strlen($imagedata) > self::MAX_BANNER_BYTES) {
            debugging('format_aicourse rejected banner of ' . strlen($imagedata) . ' bytes.', DEBUG_DEVELOPER);
            throw new \moodle_exception('error_bannertoolarge', 'format_aicourse');
        }

        $imageinfo = getimagesizefromstring($imagedata);
        if ($imageinfo === false || empty($imageinfo['mime'])) {
            throw new \moodle_exception('error_bannerinvalidimage', 'format_aicourse');
        }

        $allowedmimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        if (!isset($allowedmimes[$imageinfo['mime']])) {
            debugging('format_aicourse rejected banner mimetype ' . $imageinfo['mime'], DEBUG_DEVELOPER);
            throw new \moodle_exception('error_bannerinvalidimage', 'format_aicourse');
        }

        $fs = get_file_storage();

        // Remove any existing banner images for this course.
        $fs->delete_area_files($context->id, 'format_aicourse', 'bannerimage', banner::BANNER_ITEMID);

        $fileinfo = [
            'component' => 'format_aicourse',
            'filearea' => 'bannerimage',
            'itemid' => banner::BANNER_ITEMID,
            'contextid' => $context->id,
            'filepath' => '/',
            'filename' => 'ai_banner_' . time() . '.' . $allowedmimes[$imageinfo['mime']],
        ];

        try {
            $file = $fs->create_file_from_string($fileinfo, $imagedata);
        } catch (\Exception $e) {
            debugging('format_aicourse banner save failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('error_bannersavefailed', 'format_aicourse');
        }

        $fileurl = \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'format_aicourse',
            'bannerimage',
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );

        return $fileurl->out(false);
    }

    /**
     * Build a short, human-readable reason for a failed generation request.
     *
     * @param int $httpcode HTTP status returned by the remote service, 0 if the request never
     *                      completed.
     * @param string $curlerror cURL's own error string, empty when the transport was fine.
     * @param string $response Raw response body.
     * @return string A single line safe to show to a course editor.
     */
    protected static function describe_failure(int $httpcode, string $curlerror, string $response): string {
        if ($curlerror !== '') {
            // No HTTP response at all: DNS, TLS, timeout, firewall. The teacher cannot fix this
            // but the administrator can, and the distinction matters.
            return get_string('error_bannerunreachable', 'format_aicourse', s($curlerror));
        }

        // Prefer the service's own explanation over the bare status code.
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            foreach (['error', 'message', 'reason', 'detail'] as $key) {
                if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                    return get_string('error_bannerhttp', 'format_aicourse', (object) [
                        'code' => $httpcode,
                        'message' => self::clean_remote_message($decoded[$key]),
                    ]);
                }
            }
        }

        return get_string('error_bannerhttp', 'format_aicourse', (object) [
            'code' => $httpcode,
            'message' => self::clean_remote_message($response),
        ]);
    }

    /**
     * Reduce a remote response to one short, safe line.
     *
     * Tags are stripped and the result is truncated, so a service that answers with an HTML
     * error page cannot inject markup into the dialogue or fill the screen with it.
     *
     * @param string $message Raw text from the remote service.
     * @return string
     */
    protected static function clean_remote_message(string $message): string {
        $message = trim(preg_replace('/\s+/', ' ', html_to_text($message, 0, false)));
        if ($message === '') {
            return get_string('error_bannernoreason', 'format_aicourse');
        }
        return s(\core_text::substr($message, 0, 200));
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            // ACF-FIX-2.1.42: the call queues an image rather than producing one. imageurl and
            // creditsused are kept in the structure, defaulted, so a browser still running the
            // previous AMD bundle reads an empty string and a zero instead of failing on a
            // missing key.
            'status' => new external_value(PARAM_ALPHA, 'queued, running, done or failed'),
            'imageurl' => new external_value(
                // ACF-FIX-2.1.115: declared as a URL rather than an untyped string. The value is a
                // pluginfile URL this plugin builds itself, so the URL type both documents the
                // contract and has Moodle validate it on the way out.
                PARAM_URL,
                'Empty when queued; the URL once done',
                VALUE_DEFAULT,
                ''
            ),
            'message' => new external_value(
                PARAM_TEXT,
                'Failure reason when status is failed',
                VALUE_DEFAULT,
                ''
            ),
            'creditsused' => new external_value(
                PARAM_INT,
                'Retained for compatibility; always 0 here',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }
}
