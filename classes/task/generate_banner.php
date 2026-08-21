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
 * Adhoc task that generates a course banner image in the background.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\task;

use format_aicourse\external\generate_banner_image;

/**
 * Generate a course banner image away from the web request.
 *
 * ACF-FIX-2.1.42. Banner generation takes around 110 seconds end to end, and it used to happen
 * inside the AJAX request the teacher's browser was waiting on. That request has to survive every
 * intermediary between the browser and PHP, and the shortest timeout in that chain wins: a
 * reverse proxy at 60s, Cloudflare's fixed 100s on its lower tiers, PHP-FPM's
 * request_terminate_timeout at 30s. On a site behind such a proxy the request was killed with a
 * 504 before PHP ever finished, and no timeout value inside the plugin could change that — the
 * connection was already gone. Raising the plugin's own cURL timeout, which earlier releases did
 * twice, could not have worked.
 *
 * Running the work here removes the long-held request altogether. The browser gets an immediate
 * answer, this task does the waiting, and the page polls for the result. Nothing in the chain is
 * asked to hold a connection open for two minutes.
 *
 * The generation itself is unchanged: this calls the same routine the synchronous path used, so
 * the request shape, the credit cost and the validation of what comes back are all identical.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_banner extends \core\task\adhoc_task {
    /**
     * Descriptive name for the admin task screens.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskgeneratebanner', 'format_aicourse');
    }

    /**
     * Generate the banner and record the outcome.
     *
     * Failures are recorded rather than rethrown. A rethrow would make Moodle retry the task with
     * a backoff, and every retry spends credits on a request whose outcome the teacher is no
     * longer watching for. The teacher sees the reason and decides whether to try again.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $courseid = (int) ($data->courseid ?? 0);
        if ($courseid <= 0) {
            return;
        }

        try {
            $course = get_course($courseid);
        } catch (\moodle_exception $e) {
            // Course deleted between queueing and running: nothing to do, and nothing to report to.
            return;
        }

        generate_banner_image::set_status($courseid, 'running', '');

        try {
            $url = generate_banner_image::generate_and_store($course);
            generate_banner_image::set_status($courseid, 'done', $url);
        } catch (\Throwable $e) {
            generate_banner_image::set_status($courseid, 'failed', $e->getMessage());
        }
    }
}
