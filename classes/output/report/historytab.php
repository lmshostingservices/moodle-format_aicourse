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
use core\output\notification;
use core_user\fields;
use moodle_url;
use renderable;
use renderer_base;
use single_select;
use stdClass;
use user_picture;

/**
 * The "Chat History" tab of the per-course AI Tutor report.
 *
 * Every AI tutor exchange in the course, filtered by student, group, rating and free text,
 * sorted, paged, and with the teacher's rating and correction controls on each row.
 *
 * ACF-FIX-2.0 guards this class carries:
 *  - the viewer's separate-groups restriction is applied to the table AND to the headline
 *    counters, so a teacher restricted to their own groups can neither see nor count the chats of
 *    students in other groups;
 *  - the row window is built from a clamped perpage, so perpage=0 cannot divide by zero and a
 *    huge perpage cannot exhaust memory.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class historytab implements named_templatable, renderable {
    /** @var int Characters of the question shown in the table. */
    const QUESTION_LENGTH = 200;

    /** @var int Characters of the response shown in the table. */
    const RESPONSE_LENGTH = 300;

    /** @var int Characters of a teacher correction shown in the table. */
    const CORRECTION_LENGTH = 200;

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
     * @param array|null $allowedgroupids Null when the viewer may see every group; otherwise the
     *        (possibly empty) list of group ids the viewer is restricted to.
     */
    public function __construct(
        stdClass $course,
        context_course $context,
        chatfilter $filter,
        ?array $allowedgroupids
    ) {
        $this->course = $course;
        $this->context = $context;
        $this->filter = $filter;
        $this->allowedgroupids = $allowedgroupids;
    }

    /**
     * Get the name of the template to use for this templatable.
     *
     * @param renderer_base $renderer The renderer requesting the template name.
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_aicourse/report_history_tab';
    }

    /**
     * Export the data for the mustache template.
     *
     * The chat question, response and correction are raw student and AI generated text. They are
     * exported as plain text and MUST be rendered with a double mustache, which is what escapes
     * them; rendering them with a triple mustache would be a stored XSS hole.
     *
     * Pre-escaped values in the returned context, all rendered with a triple mustache, and all of
     * them produced by a core output component rather than by string building here:
     *  - pagingbar: core paging_bar.
     *  - perpageselect: core single_select.
     *  - groupsnotice: core notification.
     *  - rows[].avatar: core user_picture.
     *
     * @param renderer_base $output Typically, the renderer that is calling this function.
     * @return stdClass Data context for format_aicourse/report_history_tab.
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $DB;

        $filter = $this->filter;
        $this->require_js((int) $this->course->id);

        [$where, $params] = $filter->get_where($DB, $this->context, $this->allowedgroupids);
        $totalcount = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {format_aicourse_chats} c
               JOIN {user} u ON u.id = c.userid
              WHERE $where",
            $params
        );

        $chats = $DB->get_records_sql(
            "SELECT c.id, c.userid, c.question, c.response, c.rating, c.correction, c.timecreated
               FROM {format_aicourse_chats} c
               JOIN {user} u ON u.id = c.userid
              WHERE $where
           ORDER BY " . $filter->get_order_by(),
            $params,
            $filter->page * $filter->perpage,
            $filter->perpage
        );

        $data = (object) [
            'stats' => $this->export_stats($DB),
            'filterform' => $this->export_filter_form(),
            'columns' => $this->export_columns(),
            'rows' => $this->export_rows($output, $chats),
            'hasrows' => !empty($chats),
            'isfiltered' => $this->is_filtered(),
            'tablecaption' => get_string('aireport_chattable_caption', 'format_aicourse'),
            'emptytitle' => $this->is_filtered()
                ? get_string('admin_report_no_filtered', 'format_aicourse')
                : get_string('aireport_no_chats', 'format_aicourse'),
            'hasemptydesc' => !$this->is_filtered(),
            'emptydesc' => get_string('aireport_no_chats_desc', 'format_aicourse'),
            'groupsnotice' => '',
            'hasgroupsnotice' => false,
            'perpageselect' => $this->export_perpage_select($output),
            'pagingbar' => $output->paging_bar(
                $totalcount,
                $filter->page,
                $filter->perpage,
                $filter->get_url()
            ),
            // Core's own string, on purpose. The plugin string that says this properly
            // (aireport_rate_ownonly, queued in /root/work/strings_reportjs.txt) cannot be
            // referenced until the language pack merge lands: get_string() on a key that is not
            // in the pack yet raises a debugging() call, which fails the report renderable test.
            'showingtext' => get_string('admin_report_showing', 'format_aicourse', [
                'from' => $totalcount > 0 ? ($filter->page * $filter->perpage + 1) : 0,
                'to' => min(($filter->page + 1) * $filter->perpage, $totalcount),
                'total' => number_format($totalcount),
            ]),
        ];

        if ($this->allowedgroupids !== null) {
            $data->hasgroupsnotice = true;
            $data->groupsnotice = $output->notification(
                get_string('groupsseparate'),
                notification::NOTIFY_INFO,
                false
            );
        }

        return $data;
    }

    /**
     * True when the viewer has narrowed the list with at least one filter.
     *
     * @return bool
     */
    protected function is_filtered(): bool {
        return $this->filter->filteruser > 0
            || $this->filter->filtergroup > 0
            || $this->filter->filterrating !== ''
            || $this->filter->search !== '';
    }

    /**
     * The three headline counters, restricted to what this viewer may see.
     *
     * @param \moodle_database $db The database driver.
     * @return array<int, stdClass> One object per counter, each with value and label.
     */
    protected function export_stats(\moodle_database $db): array {
        [$where, $params] = $this->filter->get_stats_where($this->context, $this->allowedgroupids);

        $count = function (string $extra) use ($db, $where, $params): int {
            return $db->count_records_sql(
                "SELECT COUNT(1) FROM {format_aicourse_chats} c WHERE $where $extra",
                $params
            );
        };

        return [
            (object) [
                'value' => number_format($count('')),
                'label' => get_string('aireport_total_questions', 'format_aicourse'),
            ],
            (object) [
                'value' => number_format($count('AND c.rating = 1')),
                'label' => get_string('aireport_helpful', 'format_aicourse'),
            ],
            (object) [
                'value' => number_format($count('AND c.correction IS NOT NULL')),
                'label' => get_string('aireport_corrected', 'format_aicourse'),
            ],
        ];
    }

    /**
     * The filter form: its action, its hidden values and every menu it offers.
     *
     * Group names go through format_string() with escape disabled, because the template escapes
     * them again with a double mustache; user names are raw and are escaped by the template too.
     *
     * @return stdClass
     */
    protected function export_filter_form(): stdClass {
        global $USER;

        $filter = $this->filter;
        $courseid = $this->course->id;

        // Groups the viewer may filter by — their own groups only in separate groups mode.
        if ($this->allowedgroupids === null) {
            $groups = groups_get_all_groups($courseid);
        } else {
            $groups = groups_get_all_groups($courseid, $USER->id);
        }

        // Enrolled users, honouring the separate groups restriction. get_enrolled_users() treats an
        // empty $groupids array as "all groups", so the empty case (a teacher in separate groups
        // mode who belongs to no group) is short-circuited rather than passed through.
        // Every field fullname() needs must be selected, or it raises a developer debugging
        // notice on each row and falls back to a partial name.
        $namefields = fields::for_name()->including('id')->get_sql('u', false, '', '', false)->selects;
        if ($this->allowedgroupids === null) {
            $enrolled = get_enrolled_users($this->context, '', 0, $namefields, 'u.lastname, u.firstname');
        } else if (!empty($this->allowedgroupids)) {
            $enrolled = get_enrolled_users(
                $this->context,
                '',
                $this->allowedgroupids,
                $namefields,
                'u.lastname, u.firstname'
            );
        } else {
            $enrolled = [];
        }

        $useroptions = [(object) [
            'value' => 0,
            'label' => get_string('aireport_all_students', 'format_aicourse'),
            'selected' => ($filter->filteruser === 0),
        ]];
        foreach ($enrolled as $user) {
            $useroptions[] = (object) [
                'value' => (int) $user->id,
                'label' => fullname($user),
                'selected' => ($filter->filteruser == $user->id),
            ];
        }

        $groupoptions = [(object) [
            'value' => 0,
            'label' => get_string('aireport_all_groups', 'format_aicourse'),
            'selected' => ($filter->filtergroup === 0),
        ]];
        foreach ($groups as $group) {
            $groupoptions[] = (object) [
                'value' => (int) $group->id,
                'label' => format_string(
                    $group->name,
                    true,
                    ['context' => $this->context, 'escape' => false]
                ),
                'selected' => ($filter->filtergroup == $group->id),
            ];
        }

        $ratingoptions = [];
        $ratinglabels = [
            '' => get_string('aireport_all_ratings', 'format_aicourse'),
            'helpful' => get_string('aireport_filter_helpful', 'format_aicourse'),
            'nothelpful' => get_string('aireport_filter_nothelpful', 'format_aicourse'),
            'corrected' => get_string('aireport_filter_corrected', 'format_aicourse'),
        ];
        foreach ($ratinglabels as $value => $label) {
            $ratingoptions[] = (object) [
                'value' => $value,
                'label' => $label,
                'selected' => ($filter->filterrating === $value),
            ];
        }

        return (object) [
            'action' => (new moodle_url('/course/format/aicourse/report.php'))->out(false),
            'courseid' => $courseid,
            'sort' => $filter->sort,
            'dir' => $filter->dir,
            'perpage' => $filter->perpage,
            'search' => $filter->search,
            'useroptions' => $useroptions,
            'groupoptions' => $groupoptions,
            'ratingoptions' => $ratingoptions,
            'hasgroups' => (count($groupoptions) > 1),
            'reseturl' => (new moodle_url(
                '/course/format/aicourse/report.php',
                ['id' => $courseid, 'tab' => chatfilter::TAB_HISTORY]
            ))->out(false),
        ];
    }

    /**
     * The "rows per page" menu, a core single_select that carries the current filters forward.
     *
     * @param renderer_base $output The renderer.
     * @return string Rendered single_select markup.
     */
    protected function export_perpage_select(renderer_base $output): string {
        $url = $this->filter->get_url(['page' => 0]);
        $url->remove_params('perpage');
        $select = new single_select($url, 'perpage', chatfilter::get_perpage_options(), $this->filter->perpage, null);
        $select->set_label(get_string('perpage'));
        $select->attributes['class'] = 'aicourse-filter-select';
        return $output->render($select);
    }

    /**
     * The table header cells, with a sort link on every sortable column.
     *
     * @return array<int, stdClass>
     */
    protected function export_columns(): array {
        $filter = $this->filter;
        $definition = [
            'student' => ['aireport_student', true],
            'question' => ['aireport_question', true],
            'response' => ['aireport_response', false],
            'timecreated' => ['aireport_date', true],
            'rating' => ['aireport_rating', true],
            'actions' => ['aireport_actions', false],
        ];

        $columns = [];
        foreach ($definition as $key => [$stringkey, $sortable]) {
            $label = get_string($stringkey, 'format_aicourse');
            $issorted = $sortable && ($filter->sort === $key);
            $nextdir = ($issorted && $filter->dir === 'ASC') ? 'DESC' : 'ASC';
            $columns[] = (object) [
                'label' => $label,
                'sortable' => $sortable,
                'issorted' => $issorted,
                'ariasort' => $issorted ? ($filter->dir === 'ASC' ? 'ascending' : 'descending') : 'none',
                'isascending' => $issorted && $filter->dir === 'ASC',
                'isdescending' => $issorted && $filter->dir === 'DESC',
                'sorturl' => $sortable
                    ? $filter->get_url(['sort' => $key, 'dir' => $nextdir, 'page' => 0])->out(false)
                    : '',
                'sortlabel' => $sortable
                    ? get_string($nextdir === 'ASC' ? 'sortbyx' : 'sortbyxreverse', 'core', $label)
                    : '',
            ];
        }
        return $columns;
    }

    /**
     * One exported row per chat record.
     *
     * @param renderer_base $output The renderer, used for the core user_picture component.
     * @param array $chats Chat records for the current page.
     * @return array<int, stdClass>
     */
    protected function export_rows(renderer_base $output, array $chats): array {
        global $DB, $USER;

        $userids = array_unique(array_column($chats, 'userid'));
        $users = [];
        if (!empty($userids)) {
            $users = $DB->get_records_list(
                'user',
                'id',
                $userids,
                '',
                implode(',', fields::get_picture_fields())
            );
        }

        $unknown = get_string('aireport_unknownuser', 'format_aicourse');
        $dateformat = get_string('strftimedatetimeshort', 'core_langconfig');
        $rows = [];
        foreach ($chats as $chat) {
            $user = $users[$chat->userid] ?? null;
            $avatar = '';
            if ($user) {
                $picture = new user_picture($user);
                $picture->size = 32;
                $picture->courseid = $this->course->id;
                $picture->class = 'aicourse-chat-avatar';
                // The picture is decorative next to the name in the same cell, so it is not a
                // link: a 32px link would be an undersized touch target with no unique purpose.
                $picture->link = false;
                $avatar = $output->render($picture);
            }

            $correction = (string) ($chat->correction ?? '');
            $rows[] = (object) [
                'id' => (int) $chat->id,
                'avatar' => $avatar,
                'hasavatar' => ($avatar !== ''),
                'fullname' => $user ? fullname($user) : $unknown,
                'question' => shorten_text((string) $chat->question, self::QUESTION_LENGTH),
                'response' => shorten_text((string) $chat->response, self::RESPONSE_LENGTH),
                'hascorrection' => ($correction !== ''),
                'correction' => $correction,
                'correctionshort' => shorten_text($correction, self::CORRECTION_LENGTH),
                'date' => userdate($chat->timecreated, $dateformat),
                'ishelpful' => ((int) $chat->rating === 1),
                'isnothelpful' => ((int) $chat->rating === -1),
                // ACF-2.1: format_aicourse_rate_chat looks the row up by
                // ['id', 'courseid', 'userid' => $USER->id], i.e. a user may only rate their own
                // conversation, which is right for the student chat window but means every rating
                // button on a row this viewer did not write would fail with "chatnotfound". The
                // buttons are therefore rendered disabled on those rows, with the reason as a
                // tooltip, rather than offering a control that cannot work. See
                // /root/work/notes_reportjs.txt — if rate_chat is later widened to accept
                // format/aicourse:viewreport, delete this flag and the template branch with it.
                'canrate' => ((int) $chat->userid === (int) $USER->id),
            ];
        }
        return $rows;
    }

    /**
     * Bootstrap the JavaScript the rating and correction controls need.
     *
     * ACF-2.1: this used to register ~40 lines with $PAGE->requires->js_amd_inline(). That
     * bootstrap is gone: the behaviour now lives in the amd/src/report.js module, which updates
     * the row in place instead of reloading the page, manages focus on the correction disclosure
     * and announces the result in an aria-live region.
     *
     * @param int $courseid Course the report is for.
     * @return void
     */
    protected function require_js(int $courseid): void {
        global $PAGE;

        $PAGE->requires->js_call_amd('format_aicourse/report', 'init', [$courseid]);
    }
}
