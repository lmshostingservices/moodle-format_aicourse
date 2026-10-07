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
 * Adhoc task that generates a card image in the background.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\task;

use format_aicourse\external\generate_card_image as generator;
use format_aicourse\local\cardimage;

/**
 * Generate one section or activity card image away from the web request.
 *
 * Same reasoning as {@see generate_banner}: the service takes around two minutes, longer than
 * the shortest proxy timeout in front of many sites. Failures are recorded, not rethrown, so
 * Moodle never retries on its own and spends credits the teacher is no longer watching.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_card_image extends \core\task\adhoc_task {
    /**
     * Descriptive name for the admin task screens.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskgeneratecardimage', 'format_aicourse');
    }

    /**
     * Generate the image and record the outcome.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $courseid = (int) ($data->courseid ?? 0);
        $type = (string) ($data->targettype ?? '');
        $id = (int) ($data->targetid ?? 0);
        if ($courseid <= 0 || $id <= 0 || !in_array($type, [cardimage::TYPE_SECTION, cardimage::TYPE_CM], true)) {
            return;
        }

        try {
            $course = get_course($courseid);
        } catch (\moodle_exception $e) {
            return;
        }

        // One cron process runs many jobs; a colour a teacher changed since the last one must count.
        cardimage::reset_cache();
        cardimage::set_status($courseid, $type, $id, 'running');
        try {
            $url = generator::generate_card(
                $course,
                $type,
                $id,
                (string) ($data->prompt ?? ''),
                (string) ($data->requestid ?? '')
            );
            cardimage::set_status($courseid, $type, $id, 'done', $url);
        } catch (\Throwable $e) {
            cardimage::set_status($courseid, $type, $id, 'failed', $e->getMessage());
        }
    }
}
