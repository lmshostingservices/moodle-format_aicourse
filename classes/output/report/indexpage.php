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

namespace format_aicourse\output\report;

use context_course;
use core\output\named_templatable;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;

/**
 * The AI Tutor report index (index.php): every course using this format, with its AI tutor totals.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class indexpage implements named_templatable, renderable {
    /**
     * Get the name of the template to use for this templatable.
     *
     * @param renderer_base $renderer The renderer requesting the template name.
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_aicourse/report_index';
    }

    /**
     * Export the data for the mustache template.
     *
     * Course names go through format_string() with escape disabled, because the template escapes
     * them again with a double mustache. Nothing in this context is pre-escaped HTML.
     *
     * @param renderer_base $output Typically, the renderer that is calling this function.
     * @return stdClass Data context for format_aicourse/report_index.
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $DB;

        $courses = $DB->get_records('course', ['format' => 'aicourse'], 'fullname ASC', 'id, fullname, shortname');

        // ACF-FIX-2.1: the totals used to be three count queries per course, so an installation
        // with 200 AI courses issued 600 queries to draw this page. One grouped query now serves
        // every card.
        $totals = $DB->get_records_sql(
            'SELECT courseid,
                    COUNT(1) AS questions,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS helpful,
                    SUM(CASE WHEN correction IS NOT NULL THEN 1 ELSE 0 END) AS corrected
               FROM {format_aicourse_chats}
              GROUP BY courseid'
        );

        $cards = [];
        foreach ($courses as $course) {
            $context = context_course::instance($course->id);
            $stats = $totals[$course->id] ?? null;
            $cards[] = (object) [
                'fullname' => format_string($course->fullname, true, ['context' => $context, 'escape' => false]),
                'shortname' => format_string($course->shortname, true, ['context' => $context, 'escape' => false]),
                'reporturl' => (new moodle_url(
                    '/course/format/aicourse/report.php',
                    ['id' => $course->id]
                ))->out(false),
                'stats' => [
                    (object) [
                        'value' => number_format($stats ? (int) $stats->questions : 0),
                        'label' => get_string('aireport_total_questions', 'format_aicourse'),
                    ],
                    (object) [
                        'value' => number_format($stats ? (int) $stats->helpful : 0),
                        'label' => get_string('aireport_helpful', 'format_aicourse'),
                    ],
                    (object) [
                        'value' => number_format($stats ? (int) $stats->corrected : 0),
                        'label' => get_string('aireport_corrected', 'format_aicourse'),
                    ],
                ],
            ];
        }

        return (object) [
            'title' => get_string('aireport', 'format_aicourse'),
            'hascourses' => !empty($cards),
            'courses' => $cards,
            'emptytitle' => get_string('aireport_nocourses', 'format_aicourse'),
            'emptydesc' => get_string('aireport_nocourses_desc', 'format_aicourse'),
            'viewlabel' => get_string('aireport_view', 'format_aicourse'),
            'adminreporturl' => (new moodle_url('/course/format/aicourse/admin_report.php'))->out(false),
            'adminreportlabel' => get_string('admin_report_view', 'format_aicourse'),
        ];
    }
}
