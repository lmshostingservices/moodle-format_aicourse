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

/**
 * Per user, per course sliding window throttle for the plugin's paid external API calls.
 *
 * Both \format_aicourse\external\ai_chat and \format_aicourse\external\generate_banner_image
 * spend purchased credits at lms-labs.com and hold a PHP worker for up to 90 seconds, so both
 * are rate limited. This class carries the limits that used to live in ajax.php so the
 * protection survives the move to web services.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class throttle {
    /** @var int Maximum AI Tutor questions allowed per user per course inside the window. */
    public const AICHAT_MAX = 10;

    /** @var int Length in seconds of the AI Tutor window. */
    public const AICHAT_WINDOW = 60;

    /** @var int Maximum banner generations allowed per user per course inside the window. */
    public const BANNER_MAX = 3;

    /** @var int Length in seconds of the banner generation window. */
    public const BANNER_WINDOW = 60;

    /**
     * Record a request and throw when the caller has exceeded its allowance.
     *
     * @param string $action Identifier of the throttled operation, e.g. 'aichat'.
     * @param int $courseid Course the request belongs to.
     * @param int $userid User making the request.
     * @param int $max Maximum number of requests allowed inside the window.
     * @param int $window Window length in seconds.
     * @return void
     * @throws \moodle_exception When the allowance is exhausted.
     */
    public static function check(string $action, int $courseid, int $userid, int $max, int $window): void {
        if (!self::allowed($action, $courseid, $userid, $max, $window)) {
            throw new \moodle_exception('error_toomanyrequests', 'format_aicourse');
        }
    }

    /**
     * Test and consume one slot of the caller's allowance.
     *
     * Backed by an ad-hoc MUC application cache so it works without a db/caches.php definition.
     * If the cache subsystem is unavailable the request is allowed through rather than locking
     * the user out of the feature.
     *
     * @param string $action Identifier of the throttled operation.
     * @param int $courseid Course the request belongs to.
     * @param int $userid User making the request.
     * @param int $max Maximum number of requests allowed inside the window.
     * @param int $window Window length in seconds.
     * @return bool True when the request may proceed, false when it must be rejected.
     */
    public static function allowed(string $action, int $courseid, int $userid, int $max, int $window): bool {
        try {
            $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, 'format_aicourse', 'ajaxratelimit');
        } catch (\Exception $e) {
            debugging('format_aicourse throttle cache unavailable: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return true;
        }

        $key = $action . '_' . $courseid . '_' . $userid;
        $now = time();
        $hits = $cache->get($key);
        if (!is_array($hits)) {
            $hits = [];
        }

        $recent = [];
        foreach ($hits as $hit) {
            if ((int) $hit > ($now - $window)) {
                $recent[] = (int) $hit;
            }
        }

        if (count($recent) >= $max) {
            $cache->set($key, $recent);
            return false;
        }

        $recent[] = $now;
        $cache->set($key, $recent);

        return true;
    }
}
