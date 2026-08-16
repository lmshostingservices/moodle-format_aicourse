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

/**
 * CSV download for the site-wide AI Tutor report.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csvexporter {
    /** @var adminfilter The criteria to export. */
    protected $filter;

    /**
     * Constructor.
     *
     * @param adminfilter $filter The criteria to export.
     */
    public function __construct(adminfilter $filter) {
        $this->filter = $filter;
    }

    /**
     * ACF-FIX-2.0: neutralise CSV formula injection.
     *
     * Excel, LibreOffice Calc and Google Sheets evaluate a cell whose first character is '=', '+',
     * '-', '@', TAB or CR as a formula. Student supplied question text lands verbatim in this
     * export, so a question beginning with =HYPERLINK(...) or =cmd|... would execute when an
     * administrator opens the downloaded file. Prefixing a single quote forces the spreadsheet to
     * treat the cell as text.
     *
     * @param mixed $value Cell value.
     * @return string Safe cell value.
     */
    public static function safe_cell($value): string {
        $value = (string) $value;
        if ($value === '') {
            return $value;
        }
        if (strpbrk(substr($value, 0, 1), "=+-@\t\r") !== false) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * The column headings of the export, already neutralised.
     *
     * @return string[]
     */
    public static function get_columns(): array {
        return array_map([self::class, 'safe_cell'], [
            get_string('aireport_date', 'format_aicourse'),
            get_string('admin_report_col_course', 'format_aicourse'),
            get_string('aireport_student', 'format_aicourse'),
            get_string('admin_report_col_email', 'format_aicourse'),
            get_string('admin_report_col_activityid', 'format_aicourse'),
            get_string('aireport_question', 'format_aicourse'),
            get_string('aireport_response', 'format_aicourse'),
            get_string('aireport_rating', 'format_aicourse'),
            get_string('admin_report_col_refused', 'format_aicourse'),
            get_string('aireport_correction', 'format_aicourse'),
        ]);
    }

    /**
     * One chat record as a row of already neutralised cells.
     *
     * @param \stdClass $chat Chat record joined with its user and course.
     * @return string[]
     */
    public static function get_row(\stdClass $chat): array {
        if ($chat->rating == 1) {
            $rating = get_string('aireport_filter_helpful', 'format_aicourse');
        } else if ($chat->rating == -1) {
            $rating = get_string('aireport_filter_nothelpful', 'format_aicourse');
        } else {
            $rating = get_string('admin_report_filter_unrated', 'format_aicourse');
        }

        return array_map([self::class, 'safe_cell'], [
            userdate($chat->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')),
            $chat->coursefullname,
            $chat->firstname . ' ' . $chat->lastname,
            $chat->email,
            $chat->activityid ?: '',
            $chat->question,
            $chat->response,
            $rating,
            $chat->refused ? get_string('yes') : get_string('no'),
            $chat->correction ?: '',
        ]);
    }

    /**
     * Stream the CSV to the browser and stop the request.
     *
     * Must be called before any page output has been sent.
     *
     * @return void
     */
    public function download(): void {
        global $DB;

        [$basesql, $params] = $this->filter->get_base_sql($DB);
        $records = $DB->get_recordset_sql(
            "SELECT c.id, c.courseid, c.userid, c.activityid, c.question, c.response,
                    c.rating, c.refused, c.timecreated, c.correction,
                    u.firstname, u.lastname, u.email,
                    co.fullname AS coursefullname
             $basesql
             ORDER BY " . $this->filter->get_order_by(),
            $params
        );

        $filename = 'aitutor_qa_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, self::get_columns());
        foreach ($records as $chat) {
            fputcsv($out, self::get_row($chat));
        }
        $records->close();
        fclose($out);
        exit;
    }
}
