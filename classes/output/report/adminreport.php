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

use core\output\named_templatable;
use core\output\notification;
use html_table;
use html_table_cell;
use html_table_row;
use html_writer;
use moodle_url;
use renderable;
use renderer_base;
use single_select;
use stdClass;

/**
 * The site-wide AI Tutor report page (admin_report.php).
 *
 * Every AI tutor exchange on the site, filtered by course, student, rating, response type, date
 * range and free text, sorted, paged, and downloadable as CSV.
 *
 * The results table is built with core's html_table / html_writer::table(), which supplies the
 * <caption> and scope="col" on every header cell for free.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adminreport implements named_templatable, renderable {
    /** @var int Characters of the question shown before the disclosure. */
    const QUESTION_LENGTH = 160;

    /** @var int Characters of the response shown before the disclosure. */
    const RESPONSE_LENGTH = 220;

    /** @var adminfilter The request criteria. */
    protected $filter;

    /**
     * Constructor.
     *
     * @param adminfilter $filter The request criteria.
     */
    public function __construct(adminfilter $filter) {
        $this->filter = $filter;
    }

    /**
     * Get the name of the template to use for this templatable.
     *
     * @param renderer_base $renderer The renderer requesting the template name.
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_aicourse/admin_report_page';
    }

    /**
     * Export the data for the mustache template.
     *
     * Pre-escaped values in the returned context, all rendered with a triple mustache, and all of
     * them produced by a core output component rather than by string building here:
     *  - resultstable: core html_table rendered by html_writer::table(); every cell in it is
     *    escaped with s() or format_string() where it is built, below.
     *  - pagingbar: core paging_bar.
     *  - perpageselect: core single_select.
     *  - noresultsnotice, cappednotices[]: core notification.
     * Everything else is plain text and is rendered with a double mustache.
     *
     * @param renderer_base $output Typically, the renderer that is calling this function.
     * @return stdClass Data context for format_aicourse/admin_report_page.
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $DB;

        $filter = $this->filter;
        [$basesql, $params] = $filter->get_base_sql($DB);

        $filteredtotal = $DB->count_records_sql("SELECT COUNT(1) $basesql", $params);

        $chats = $DB->get_records_sql(
            "SELECT c.id, c.courseid, c.userid, c.activityid, c.question, c.response,
                    c.rating, c.refused, c.timecreated, c.correction,
                    u.firstname, u.lastname,
                    co.fullname AS coursefullname
             $basesql
             ORDER BY " . $filter->get_order_by(),
            $params,
            $filter->page * $filter->perpage,
            $filter->perpage
        );

        $data = (object) [
            'title' => get_string('admin_report_title', 'format_aicourse'),
            'stats' => $this->export_stats($DB),
            'filterform' => $this->export_filter_form($DB),
            'cappednotices' => [],
            'showingtext' => get_string('admin_report_showing', 'format_aicourse', [
                'from' => $filteredtotal > 0 ? ($filter->page * $filter->perpage + 1) : 0,
                'to' => min(($filter->page + 1) * $filter->perpage, $filteredtotal),
                'total' => number_format($filteredtotal),
            ]),
            'hasresults' => !empty($chats),
            'hasexport' => ($filteredtotal > 0),
            'csvurl' => $filter->get_url(['export' => 1])->out(false),
            'exportlabel' => get_string('admin_report_export_csv', 'format_aicourse'),
            'resultstable' => '',
            'noresultsnotice' => '',
            'perpageselect' => $this->export_perpage_select($output),
            'pagingbar' => $output->paging_bar($filteredtotal, $filter->page, $filter->perpage, $filter->get_url()),
        ];

        if (!empty($chats)) {
            $data->resultstable = html_writer::table($this->build_table($chats, $DB));
        } else {
            $anyrecords = $DB->record_exists('format_aicourse_chats', []);
            $data->noresultsnotice = $output->notification(
                $anyrecords
                    ? get_string('admin_report_no_filtered', 'format_aicourse')
                    : get_string('aireport_no_chats', 'format_aicourse'),
                notification::NOTIFY_INFO,
                false
            );
        }

        foreach ($data->filterform->cappedmessages as $message) {
            $data->cappednotices[] = $output->notification($message, notification::NOTIFY_WARNING, false);
        }

        return $data;
    }

    /**
     * The five site-wide headline counters. These deliberately ignore the filters.
     *
     * @param \moodle_database $db The database driver.
     * @return array<int, stdClass> One object per counter, each with value and label.
     */
    protected function export_stats(\moodle_database $db): array {
        $total = $db->count_records('format_aicourse_chats');
        $helpful = $db->count_records('format_aicourse_chats', ['rating' => 1]);
        $refused = $db->count_records('format_aicourse_chats', ['refused' => 1]);
        $courses = $db->count_records_sql('SELECT COUNT(DISTINCT courseid) FROM {format_aicourse_chats}');
        $students = $db->count_records_sql('SELECT COUNT(DISTINCT userid) FROM {format_aicourse_chats}');

        return [
            (object) [
                'value' => number_format($total),
                'label' => get_string('admin_report_stat_total', 'format_aicourse'),
            ],
            (object) [
                'value' => $total > 0 ? round(($helpful / $total) * 100) . '%' : '—',
                'label' => get_string('admin_report_stat_helpful', 'format_aicourse'),
            ],
            (object) [
                'value' => number_format($refused),
                'label' => get_string('admin_report_stat_refused', 'format_aicourse'),
            ],
            (object) [
                'value' => number_format($courses),
                'label' => get_string('admin_report_stat_courses', 'format_aicourse'),
            ],
            (object) [
                'value' => number_format($students),
                'label' => get_string('admin_report_stat_students', 'format_aicourse'),
            ],
        ];
    }

    /**
     * The filter form: its action and every menu it offers.
     *
     * ACF-FIX-2.0: both menus used to be built from every chat row ever recorded, so on a busy
     * site they grew without bound. They are capped at adminfilter::MAX_FILTER_OPTIONS and the
     * administrator is told when the list shown is partial.
     *
     * @param \moodle_database $db The database driver.
     * @return stdClass
     */
    protected function export_filter_form(\moodle_database $db): stdClass {
        $filter = $this->filter;
        $max = adminfilter::MAX_FILTER_OPTIONS;

        $courserecords = $db->get_records_sql(
            "SELECT DISTINCT co.id, co.fullname, co.shortname
               FROM {format_aicourse_chats} c
               JOIN {course} co ON co.id = c.courseid
              ORDER BY co.fullname",
            null,
            0,
            $max
        );
        $userrecords = $db->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname
               FROM {format_aicourse_chats} c
               JOIN {user} u ON u.id = c.userid
              ORDER BY u.lastname, u.firstname",
            null,
            0,
            $max
        );

        $courseoptions = [(object) [
            'value' => 0,
            'label' => get_string('admin_report_all_courses', 'format_aicourse'),
            'selected' => ($filter->filtercourseid === 0),
        ]];
        foreach ($courserecords as $record) {
            $courseoptions[] = (object) [
                'value' => (int) $record->id,
                'label' => shorten_text(
                    format_string(
                        $record->fullname,
                        true,
                        ['context' => \context_course::instance($record->id), 'escape' => false]
                    ),
                    50
                ),
                'selected' => ($filter->filtercourseid == $record->id),
            ];
        }

        $useroptions = [(object) [
            'value' => 0,
            'label' => get_string('aireport_all_students', 'format_aicourse'),
            'selected' => ($filter->filteruserid === 0),
        ]];
        foreach ($userrecords as $record) {
            $useroptions[] = (object) [
                'value' => (int) $record->id,
                'label' => $record->lastname . ', ' . $record->firstname,
                'selected' => ($filter->filteruserid == $record->id),
            ];
        }

        $cappedmessages = [];
        if (count($courserecords) >= $max) {
            $cappedmessages[] = get_string('admin_report_filter_capped', 'format_aicourse', $max);
        }
        if (count($userrecords) >= $max && empty($cappedmessages)) {
            $cappedmessages[] = get_string('admin_report_filter_capped', 'format_aicourse', $max);
        }

        return (object) [
            'action' => (new moodle_url('/course/format/aicourse/admin_report.php'))->out(false),
            'search' => $filter->search,
            'datefrom' => $filter->datefromstr,
            'dateto' => $filter->datetostr,
            'sort' => $filter->sort,
            'dir' => $filter->dir,
            'perpage' => $filter->perpage,
            'courseoptions' => $courseoptions,
            'useroptions' => $useroptions,
            'ratingoptions' => $this->build_options([
                '' => get_string('aireport_all_ratings', 'format_aicourse'),
                'helpful' => get_string('aireport_filter_helpful', 'format_aicourse'),
                'nothelpful' => get_string('aireport_filter_nothelpful', 'format_aicourse'),
                'unrated' => get_string('admin_report_filter_unrated', 'format_aicourse'),
            ], $filter->filterrating),
            'refusedoptions' => $this->build_options([
                '' => get_string('admin_report_all', 'format_aicourse'),
                'refused' => get_string('admin_report_refused_only', 'format_aicourse'),
                'answered' => get_string('admin_report_answered_only', 'format_aicourse'),
            ], $filter->filterrefused),
            'reseturl' => (new moodle_url('/course/format/aicourse/admin_report.php'))->out(false),
            'cappedmessages' => $cappedmessages,
        ];
    }

    /**
     * Turn a value => label map into template option objects.
     *
     * @param array<string, string> $labels Value => label.
     * @param string $selected Currently selected value.
     * @return array<int, stdClass>
     */
    protected function build_options(array $labels, string $selected): array {
        $options = [];
        foreach ($labels as $value => $label) {
            $options[] = (object) [
                'value' => $value,
                'label' => $label,
                'selected' => ((string) $value === $selected),
            ];
        }
        return $options;
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
        $select = new single_select($url, 'perpage', adminfilter::get_perpage_options(), $this->filter->perpage, null);
        $select->set_label(get_string('perpage'));
        return $output->render($select);
    }

    /**
     * Build the results table with core's html_table component.
     *
     * Every cell that carries database content is escaped here with s(), format_string() or
     * html_writer, because html_writer::table() emits cell text verbatim.
     *
     * @param array $chats Chat records for the current page.
     * @param \moodle_database $db The database driver, for the activity name lookup.
     * @return html_table
     */
    protected function build_table(array $chats, \moodle_database $db): html_table {
        $activitynames = $this->get_activity_names($chats, $db);

        $table = new html_table();
        $table->attributes['class'] = 'aicadmin-table table table-sm table-hover';
        $table->caption = get_string('admin_report_table_caption', 'format_aicourse');
        $table->head = [
            $this->header_cell('timecreated', 'aireport_date', true),
            $this->header_cell('course', 'admin_report_col_course', true),
            $this->header_cell('student', 'aireport_student', true),
            $this->header_cell('activity', 'admin_report_col_activity', false),
            $this->header_cell('question', 'aireport_question', false),
            $this->header_cell('response', 'aireport_response', false),
            $this->header_cell('rating', 'aireport_rating', true),
            $this->header_cell('refused', 'admin_report_col_refused', true),
        ];
        $table->data = [];

        foreach ($chats as $chat) {
            $row = new html_table_row();
            $row->cells = [
                $this->cell($this->format_date($chat->timecreated), 'text-nowrap small'),
                $this->cell(html_writer::link(
                    new moodle_url('/course/view.php', ['id' => $chat->courseid]),
                    s(shorten_text(format_string(
                        $chat->coursefullname,
                        true,
                        ['context' => \context_course::instance($chat->courseid), 'escape' => false]
                    ), 40)),
                    ['class' => 'aicadmin-course-link']
                )),
                $this->cell(html_writer::link(
                    new moodle_url('/user/view.php', ['id' => $chat->userid, 'course' => $chat->courseid]),
                    s($chat->firstname . ' ' . $chat->lastname),
                    ['class' => 'aicadmin-user-link']
                )),
                $this->cell($this->format_activity($activitynames[$chat->activityid] ?? null)),
                $this->cell(
                    $this->format_expandable((string) $chat->question, self::QUESTION_LENGTH),
                    'aicadmin-text-truncated'
                ),
                $this->cell(
                    $this->format_expandable((string) $chat->response, self::RESPONSE_LENGTH),
                    'aicadmin-text-truncated'
                ),
                $this->cell($this->format_rating((int) $chat->rating)),
                $this->cell($this->format_refused(!empty($chat->refused))),
            ];
            $table->data[] = $row;
        }

        return $table;
    }

    /**
     * A header cell, carrying a sort link when the column is sortable.
     *
     * @param string $key Sort key, or the column name for a column that cannot be sorted.
     * @param string $stringkey Language string key of the column label.
     * @param bool $sortable Whether the column can be sorted.
     * @return html_table_cell
     */
    protected function header_cell(string $key, string $stringkey, bool $sortable): html_table_cell {
        $label = get_string($stringkey, 'format_aicourse');
        $cell = new html_table_cell();
        $cell->header = true;

        if (!$sortable) {
            $cell->text = s($label);
            return $cell;
        }

        $issorted = ($this->filter->sort === $key);
        $nextdir = ($issorted && $this->filter->dir === 'ASC') ? 'DESC' : 'ASC';
        $indicator = '';
        if ($issorted) {
            $cell->attributes['aria-sort'] = ($this->filter->dir === 'ASC') ? 'ascending' : 'descending';
            $indicator = ' ' . html_writer::tag(
                'span',
                $this->filter->dir === 'ASC' ? '&#9650;' : '&#9660;',
                ['aria-hidden' => 'true']
            );
        }
        $cell->text = html_writer::link(
            $this->filter->get_url(['sort' => $key, 'dir' => $nextdir, 'page' => 0]),
            s($label) . $indicator,
            [
                'class' => 'aicadmin-sort-link',
                'aria-label' => get_string($nextdir === 'ASC' ? 'sortbyx' : 'sortbyxreverse', 'core', $label),
            ]
        );
        return $cell;
    }

    /**
     * A body cell.
     *
     * @param string $text Already escaped cell markup.
     * @param string $class Extra CSS classes.
     * @return html_table_cell
     */
    protected function cell(string $text, string $class = ''): html_table_cell {
        $cell = new html_table_cell($text);
        if ($class !== '') {
            $cell->attributes['class'] = $class;
        }
        return $cell;
    }

    /**
     * The two-line date cell.
     *
     * @param int $timestamp Unix timestamp.
     * @return string Escaped markup.
     */
    protected function format_date(int $timestamp): string {
        return html_writer::div(s(userdate($timestamp, '%d %b %Y')))
            . html_writer::div(s(userdate($timestamp, '%H:%M')), 'aicadmin-date-time');
    }

    /**
     * The activity pill, or an em dash when the chat was not tied to an activity.
     *
     * @param string|null $name Activity name straight from a module table.
     * @return string Escaped markup.
     */
    protected function format_activity(?string $name): string {
        if ($name === null || $name === '') {
            return html_writer::span('&mdash;', 'aicadmin-nodata', ['aria-hidden' => 'true']);
        }
        return html_writer::span(s($name), 'aicadmin-activity-pill');
    }

    /**
     * A long text cell: the shortened text, plus a native <details> disclosure with the full text
     * when there is more to show.
     *
     * ACF-FIX-2.1: this replaces a hand-rolled show-more/show-less widget that needed an inline
     * <script> and an onclick attribute on every row. <details>/<summary> is keyboard operable and
     * screen-reader announced with no JavaScript at all.
     *
     * @param string $text Raw text from the database.
     * @param int $length Number of characters to show before the disclosure.
     * @return string Escaped markup.
     */
    protected function format_expandable(string $text, int $length): string {
        $plain = strip_tags($text);
        $short = shorten_text($plain, $length);
        $output = html_writer::span(s($short), 'aicadmin-text-short');

        if (\core_text::strlen($plain) > $length) {
            $output .= html_writer::tag(
                'details',
                html_writer::tag(
                    'summary',
                    s(get_string('admin_report_show_more', 'format_aicourse')),
                    ['class' => 'aicadmin-toggle-link']
                ) . html_writer::span(s($text), 'aicadmin-text-full'),
                ['class' => 'aicadmin-text-expandable']
            );
        }

        return $output;
    }

    /**
     * The rating badge.
     *
     * @param int $rating 1 helpful, -1 not helpful, anything else unrated.
     * @return string Escaped markup.
     */
    protected function format_rating(int $rating): string {
        if ($rating === 1) {
            return html_writer::span(
                '&#10003; ' . s(get_string('aireport_filter_helpful', 'format_aicourse')),
                'aicadmin-badge aicadmin-badge-helpful'
            );
        }
        if ($rating === -1) {
            return html_writer::span(
                '&#8722; ' . s(get_string('aireport_filter_nothelpful', 'format_aicourse')),
                'aicadmin-badge aicadmin-badge-nothelpful'
            );
        }
        return html_writer::span(
            s(get_string('admin_report_filter_unrated', 'format_aicourse')),
            'aicadmin-badge aicadmin-badge-unrated'
        );
    }

    /**
     * The refused / answered badge.
     *
     * @param bool $refused Whether the AI refused to answer.
     * @return string Escaped markup.
     */
    protected function format_refused(bool $refused): string {
        if ($refused) {
            return html_writer::span(
                s(get_string('admin_report_refused', 'format_aicourse')),
                'aicadmin-badge aicadmin-badge-refused'
            );
        }
        return html_writer::span(
            s(get_string('admin_report_answered', 'format_aicourse')),
            'aicadmin-badge aicadmin-badge-answered'
        );
    }

    /**
     * Resolve the activity name for every course module referenced on this page.
     *
     * ACF-FIX-2.0: this used to run one $DB->get_field() per activity id inside the row loop — up
     * to perpage extra round trips per page view. The course modules are grouped by module type
     * first, and every name for a type is resolved in a single get_records_list() call.
     *
     * @param array $chats Chat records for the current page.
     * @param \moodle_database $db The database driver.
     * @return array<int, string> Course module id => activity name.
     */
    protected function get_activity_names(array $chats, \moodle_database $db): array {
        $activitynames = [];
        $activityids = array_filter(array_unique(array_column($chats, 'activityid')));
        if (empty($activityids)) {
            return $activitynames;
        }

        [$insql, $inparams] = $db->get_in_or_equal(array_values($activityids), SQL_PARAMS_NAMED);
        $cms = $db->get_records_sql(
            "SELECT cm.id, m.name AS modtype, cm.instance
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.id $insql",
            $inparams
        );

        $bymodtype = [];
        foreach ($cms as $cm) {
            // Fallback name, replaced below when the module table yields a real one.
            $activitynames[$cm->id] = ucfirst($cm->modtype) . ' #' . $cm->instance;
            $bymodtype[$cm->modtype][$cm->instance][] = $cm->id;
        }

        $dbman = $db->get_manager();
        foreach ($bymodtype as $modtype => $instances) {
            if (!$dbman->table_exists($modtype)) {
                continue;
            }
            try {
                $modrecords = $db->get_records_list($modtype, 'id', array_keys($instances), '', 'id, name');
            } catch (\Exception $e) {
                // Module table has no name column or is otherwise unusable — keep the fallbacks.
                continue;
            }
            foreach ($modrecords as $modrecord) {
                if (empty($modrecord->name)) {
                    continue;
                }
                foreach ($instances[$modrecord->id] as $cmid) {
                    $activitynames[$cmid] = $modrecord->name;
                }
            }
        }

        return $activitynames;
    }
}
