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
use format_aicourse\local\activityinfo;
use format_aicourse\local\cardimage;

/**
 * Web service queueing AI images for many cards at once.
 *
 * 2.5.0. Called twice from the edit-mode dialogue: first with dryrun set, which only counts the
 * cards so the teacher sees the total credit cost before agreeing to it, then for real.
 *
 * Spending is bounded three ways. The count is what the teacher confirmed, recomputed here rather
 * than trusted from the browser. A batch is capped at MAX_CARDS. And batches have their own
 * throttle bucket, one per BATCH_WINDOW, so a double click or a retry cannot queue the course
 * twice. Each card still runs as its own adhoc task, so one failure does not stop the rest.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_all_card_images extends external_api {
    /** @var int Most cards one batch will queue. */
    public const MAX_CARDS = 60;

    /** @var int Seconds that must pass between two batches by the same teacher in one course. */
    public const BATCH_WINDOW = 300;

    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'scope' => new external_value(PARAM_ALPHA, 'sections, activities or all'),
            'onlymissing' => new external_value(PARAM_BOOL, 'Skip cards that already show an image', VALUE_DEFAULT, true),
            'dryrun' => new external_value(PARAM_BOOL, 'Count only; queue nothing', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * List the cards a batch would cover.
     *
     * @param \stdClass $course The course.
     * @param string $scope sections, activities or all.
     * @param bool $onlymissing Skip cards that already show an image.
     * @return array<int, array{0: string, 1: int}> [type, id] pairs, in course order.
     */
    public static function targets(\stdClass $course, string $scope, bool $onlymissing): array {
        $modinfo = get_fast_modinfo($course);
        $targets = [];
        foreach (activityinfo::get_listed_sections($modinfo) as $section) {
            if ($scope !== 'activities' && (int) $section->section > 0) {
                $has = cardimage::resolve((int) $course->id, cardimage::TYPE_SECTION, (int) $section->id)['url'] !== '';
                if (!$onlymissing || !$has) {
                    $targets[] = [cardimage::TYPE_SECTION, (int) $section->id];
                }
            }
            if ($scope === 'sections') {
                continue;
            }
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                // Only modules drawn as cards: a label is drawn as a text block and has no card.
                if (!activityinfo::cm_counts_as_content($cm) || (!$cm->url && $cm->modname !== 'subsection')) {
                    continue;
                }
                $has = cardimage::get_url((int) $course->id, cardimage::TYPE_CM, (int) $cm->id) !== null;
                if (!$onlymissing || !$has) {
                    $targets[] = [cardimage::TYPE_CM, (int) $cm->id];
                }
            }
        }
        return $targets;
    }

    /**
     * Count, or queue, the batch.
     *
     * @param int $courseid Course id.
     * @param string $scope sections, activities or all.
     * @param bool $onlymissing Skip cards that already show an image.
     * @param bool $dryrun Count only.
     * @return array
     */
    public static function execute(int $courseid, string $scope, bool $onlymissing = true, bool $dryrun = true): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'scope' => $scope,
            'onlymissing' => $onlymissing,
            'dryrun' => $dryrun,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        if (!in_array($params['scope'], ['sections', 'activities', 'all'], true)) {
            throw new \moodle_exception('error_cardimagetype', 'format_aicourse');
        }

        $targets = self::targets($course, $params['scope'], (bool) $params['onlymissing']);
        $total = count($targets);
        $targets = array_slice($targets, 0, self::MAX_CARDS);
        $count = count($targets);
        $result = [
            'count' => $count,
            'skipped' => $total - $count,
            'credits' => $count * generate_card_image::CREDIT_COST,
            'queued' => false,
            'targets' => [],
        ];

        if ($params['dryrun'] || $count === 0) {
            return $result;
        }

        if (isguestuser()) {
            throw new \moodle_exception('error_guestnotallowed', 'format_aicourse');
        }
        throttle::check('cardimagebatch', (int) $course->id, (int) $USER->id, 1, self::BATCH_WINDOW);
        credentials::require_configured();

        foreach ($targets as [$type, $id]) {
            $task = new \format_aicourse\task\generate_card_image();
            $task->set_custom_data([
                'courseid' => (int) $course->id,
                'targettype' => $type,
                'targetid' => $id,
                'prompt' => '',
                'requestid' => \core\uuid::generate(),
            ]);
            $task->set_component('format_aicourse');
            cardimage::set_status((int) $course->id, $type, $id, 'queued');
            \core\task\manager::queue_adhoc_task($task);
            $result['targets'][] = ['type' => $type, 'id' => $id];
        }
        $result['queued'] = true;

        return $result;
    }

    /**
     * Return value description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'count' => new external_value(PARAM_INT, 'Cards in this batch'),
            'skipped' => new external_value(PARAM_INT, 'Cards left out because the batch is capped'),
            'credits' => new external_value(PARAM_INT, 'Credits the batch will use'),
            'queued' => new external_value(PARAM_BOOL, 'True when the batch was queued'),
            'targets' => new \core_external\external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_ALPHA, 'section or cm'),
                    'id' => new external_value(PARAM_INT, 'Target id'),
                ]),
                'The cards queued',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }
}
