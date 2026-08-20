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
        ]);
    }

    /**
     * Generate and store a banner image for the course.
     *
     * @param int $courseid Id of the course.
     * @return array The URL of the stored image and the credits it cost.
     */
    public static function execute(int $courseid): array {
        global $CFG, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
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

        [$siteid, $apikey] = credentials::require_configured();

        $postdata = [
            'siteUrl' => $siteid,
            'apiKey' => $apikey,
            'courseName' => format_string($course->fullname),
            'courseShortname' => $course->shortname,
            'courseId' => $course->id,
        ];

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
            // ACF-FIX-2.0: log the remote body, return a generic translated message.
            debugging('format_aicourse generate_banner_image HTTP ' . $httpcode . ' ' . $curl->error . ' '
                . substr((string) $response, 0, 500), DEBUG_DEVELOPER);
            $key = self::error_key_for_status($httpcode);
            throw new \moodle_exception($key ?? 'error_bannerfailed', 'format_aicourse');
        }

        $result = json_decode($response, true);
        if (!$result || empty($result['success']) || empty($result['imageBase64'])) {
            debugging('format_aicourse generate_banner_image no image in '
                . substr((string) $response, 0, 500), DEBUG_DEVELOPER);
            throw new \moodle_exception('error_bannernoimage', 'format_aicourse');
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

        return [
            'imageurl' => $fileurl->out(false),
            'creditsused' => (int) ($result['creditsUsed'] ?? 5),
        ];
    }

    /**
     * Translate an HTTP status from the LMS-Labs service into a specific error string.
     *
     * ACF-FIX-2.1.24: both integrations used to collapse every non-200 into a single generic
     * message, so "you have run out of credits" and "your API key is wrong" were indistinguishable
     * from "the service is down" — for the student, the teacher and the administrator alike. The
     * real status was written to debugging() only, which is off on production sites, so the one
     * place the answer existed was the one place nobody was looking.
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
            'imageurl' => new external_value(PARAM_URL, 'URL of the stored banner image'),
            'creditsused' => new external_value(PARAM_INT, 'Number of credits the generation cost'),
        ]);
    }
}
