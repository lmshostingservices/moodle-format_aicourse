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

namespace format_aicourse;

use format_aicourse\local\byok\engine;

/**
 * Bring your own AI: run the AI Tutor and course images on the site's own AI instead of LMS Labs credits.
 *
 * When the site has a Bring your own AI licence and AI Connect (local_aiconfig) sends text or images for this
 * plugin to the site's own AI (an API key or the Moodle AI subsystem), the LMS Labs routes run here, through
 * local\byok\engine, and the work goes straight from this Moodle site to the site's provider.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class byok {
    /** Component name AI Connect knows this plugin by. */
    const COMPONENT = 'format_aicourse';

    /** Seconds the tutor may take: the plugin's own limit on the LMS Labs request. */
    const CHAT_TIMEOUT = 60;

    /** Credentials the routes check for when the work does not go to LMS Labs. */
    const NO_CREDENTIALS = 'byok';

    /**
     * Whether a kind of work (text or image) runs on the site's own AI.
     *
     * @param string $kind text or image.
     * @return bool
     */
    public static function uses(string $kind): bool {
        return class_exists('\local_aiconfig\byok\ai') && \local_aiconfig\byok\ai::uses_own_ai(self::COMPONENT, $kind);
    }

    /**
     * Where a kind of work runs now: credits (LMS Labs), moodle (Moodle AI) or ownkey (the site's own key).
     *
     * Recorded with each tutor answer and shown on the settings page.
     *
     * @param string $kind text or image.
     * @return string credits, moodle or ownkey.
     */
    public static function source(string $kind): string {
        if (!class_exists('\local_aiconfig\byok\router')) {
            return 'credits';
        }
        return \local_aiconfig\byok\router::source(self::COMPONENT, $kind);
    }

    /**
     * Point the engine at the site's own AI.
     */
    protected static function hooks(): void {
        engine::$aicall = function (string $system, string $user, array $options): string {
            $options += ['timeout' => self::CHAT_TIMEOUT];
            return \local_aiconfig\byok\ai::text(self::COMPONENT, $system, $user, $options);
        };
        // The service asks for 1536x512 banners and 1536x864 cards; the site's provider makes 1536x1024 and the
        // route's own crop takes it to the final 1400x420 or 1536x864.
        engine::$image = function (string $prompt, array $options): string {
            return \local_aiconfig\byok\ai::image(self::COMPONENT, $prompt, ['size' => '1536x1024', 'quality' => 'high']);
        };
    }

    /**
     * Fill in the credentials the routes check for.
     *
     * @param array $payload
     * @return array
     */
    protected static function with_credentials(array $payload): array {
        foreach (['siteUrl', 'apiKey'] as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '') {
                $payload[$field] = self::NO_CREDENTIALS;
            }
        }
        return $payload;
    }

    /**
     * Answer a tutor question (the course-assistant chat route) on the site's own AI.
     *
     * @param array $payload The request the plugin would send to LMS Labs.
     * @return array [HTTP status, decoded answer, the provider's error or null]
     */
    public static function chat(array $payload): array {
        self::hooks();
        [$status, $body] = engine::chat(self::with_credentials($payload));
        return self::split($status, $body);
    }

    /**
     * Make a banner or card image (the generate-banner route) on the site's own AI.
     *
     * @param array $payload The request the plugin would send to LMS Labs.
     * @return array [HTTP status, decoded answer, the provider's error or null]
     */
    public static function image(array $payload): array {
        self::hooks();
        [$status, $body] = engine::image(self::with_credentials($payload));
        return self::split($status, $body);
    }

    /**
     * Separate the route's answer from the error behind it.
     *
     * @param int $status
     * @param array $body
     * @return array
     */
    protected static function split(int $status, array $body): array {
        $cause = $body['cause'] ?? null;
        unset($body['cause'], $body['sceneWriterStatus']);
        if ($cause instanceof \Throwable) {
            debugging('format_aicourse own AI: ' . $cause->getMessage(), DEBUG_DEVELOPER);
        }
        return [$status, $body, $cause];
    }

    /**
     * What to tell the teacher about an own-AI image failure.
     *
     * @param \Throwable|null $e
     * @param array $body The route's answer.
     * @return string
     */
    public static function message(?\Throwable $e, array $body): string {
        if ($e instanceof \moodle_exception && $e->errorcode === 'byok_notavailable') {
            return get_string('byok_unavailable', 'format_aicourse');
        }
        if ($e !== null && !($e instanceof local\byok\course_image_error)) {
            $detail = $e->getMessage();
        } else {
            $detail = (string) ($body['error'] ?? '');
        }
        return get_string('byok_failed', 'format_aicourse', \core_text::substr(clean_param($detail, PARAM_TEXT), 0, 400));
    }
}
