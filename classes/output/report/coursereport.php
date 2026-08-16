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
use renderable;
use renderer_base;
use stdClass;

/**
 * The per-course AI Tutor report page (report.php).
 *
 * Draws the page heading and the tab strip, and delegates the body to whichever of the two tab
 * renderables is active.
 *
 * This page is gated by report.php on format/aicourse:viewreport. ACF-FIX-2.0 replaced
 * moodle/course:viewparticipants with that capability, because viewparticipants is CAP_ALLOW for
 * the student archetype in /lib/db/access.php and therefore let any enrolled student read every
 * classmate's AI tutor transcript.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursereport implements named_templatable, renderable {
    /** @var stdClass Course record. */
    protected $course;

    /** @var context_course Course context. */
    protected $context;

    /** @var chatfilter The request criteria. */
    protected $filter;

    /** @var array|null Group ids the viewer is limited to, or null when they may see everybody. */
    protected $allowedgroupids;

    /**
     * Constructor.
     *
     * @param stdClass $course Course record.
     * @param context_course $context Course context.
     * @param chatfilter $filter The request criteria.
     */
    public function __construct(stdClass $course, context_course $context, chatfilter $filter) {
        $this->course = $course;
        $this->context = $context;
        $this->filter = $filter;
        $this->allowedgroupids = self::get_allowed_group_ids($course, $context);
    }

    /**
     * ACF-FIX-2.0: work out the separate groups restriction for the current viewer.
     *
     * A teacher without moodle/site:accessallgroups in a course using separate groups must only
     * ever see the chat rows of users who share one of their own groups.
     *
     * @param stdClass $course Course record.
     * @param context_course $context Course context.
     * @return array|null Null when the viewer may see everybody; otherwise the (possibly empty)
     *         list of group ids they are restricted to.
     */
    public static function get_allowed_group_ids(stdClass $course, context_course $context): ?array {
        global $USER;

        $groupmode = groups_get_course_groupmode($course);
        if ($groupmode != SEPARATEGROUPS || has_capability('moodle/site:accessallgroups', $context)) {
            return null;
        }
        return array_keys(groups_get_all_groups($course->id, $USER->id, 0, 'g.id'));
    }

    /**
     * Get the name of the template to use for this templatable.
     *
     * @param renderer_base $renderer The renderer requesting the template name.
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_aicourse/report_page';
    }

    /**
     * Export the data for the mustache template.
     *
     * Everything in the returned context is plain text or a nested tab context; nothing here is
     * pre-escaped HTML, so the page template uses double mustaches throughout.
     *
     * @param renderer_base $output Typically, the renderer that is calling this function.
     * @return stdClass Data context for format_aicourse/report_page.
     */
    public function export_for_template(renderer_base $output): stdClass {
        $ishistory = ($this->filter->tab === chatfilter::TAB_HISTORY);

        $tabs = [];
        $tabdefinition = [
            chatfilter::TAB_CONTENT => 'aireport_content',
            chatfilter::TAB_HISTORY => 'aireport_history',
        ];
        foreach ($tabdefinition as $key => $stringkey) {
            $tabs[] = (object) [
                'label' => get_string($stringkey, 'format_aicourse'),
                'url' => $this->filter->get_url(['tab' => $key, 'page' => 0])->out(false),
                'active' => ($this->filter->tab === $key),
            ];
        }

        $data = (object) [
            'title' => get_string('aireport', 'format_aicourse'),
            'tabslabel' => get_string('aireport', 'format_aicourse'),
            'tabs' => $tabs,
            'iscontent' => !$ishistory,
            'ishistory' => $ishistory,
            'contenttab' => null,
            'historytab' => null,
        ];

        if ($ishistory) {
            $tab = new historytab($this->course, $this->context, $this->filter, $this->allowedgroupids);
            $data->historytab = $tab->export_for_template($output);
        } else {
            $tab = new contenttab($this->course, $this->context);
            $data->contenttab = $tab->export_for_template($output);
        }

        return $data;
    }
}
