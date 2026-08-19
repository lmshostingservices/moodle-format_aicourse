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

use moodle_database;
use moodle_url;

/**
 * Request criteria for the site-wide AI Tutor report (admin_report.php).
 *
 * As with the per-course report, every request value, every clamp and every SQL fragment lives
 * here so the table, the CSV export and the pager can never disagree.
 *
 * ACF-FIX-2.0 guards preserved here:
 *  - perpage is clamped to [PERPAGE_MIN, PERPAGE_MAX]; perpage=0 used to be a fatal
 *    DivisionByZeroError in the pager on PHP 8.
 *  - page can never be negative.
 *  - the free-text search is escaped with $DB->sql_like_escape().
 *  - the sort column comes from an allow list, never from the request text.
 *  - the course and student filter menus are capped at MAX_FILTER_OPTIONS entries.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adminfilter {
    /** @var int Smallest number of rows a user may ask for. */
    public const PERPAGE_MIN = 5;

    /** @var int Largest number of rows a user may ask for. */
    public const PERPAGE_MAX = 200;

    /** @var int Default number of rows per page. */
    public const PERPAGE_DEFAULT = 25;

    /** @var int Upper bound on the entries offered in the course/student filter menus. */
    public const MAX_FILTER_OPTIONS = 500;

    /** @var int Zero-based page number. */
    public $page = 0;

    /** @var int Rows per page, already clamped. */
    public $perpage = self::PERPAGE_DEFAULT;

    /** @var int Course id to filter by, 0 for every course. */
    public $filtercourseid = 0;

    /** @var int User id to filter by, 0 for every student. */
    public $filteruserid = 0;

    /** @var string Rating filter: '', 'helpful', 'nothelpful' or 'unrated'. */
    public $filterrating = '';

    /** @var string Response type filter: '', 'refused' or 'answered'. */
    public $filterrefused = '';

    /** @var string Raw "from" date as typed, in YYYY-MM-DD form. */
    public $datefromstr = '';

    /** @var string Raw "to" date as typed, in YYYY-MM-DD form. */
    public $datetostr = '';

    /** @var int Parsed "from" timestamp, 0 when unset. */
    public $datefrom = 0;

    /** @var int Parsed "to" timestamp, 0 when unset. */
    public $dateto = 0;

    /** @var string Free-text search over the question and the response. */
    public $search = '';

    /** @var string Sort key, always one of the keys of get_sort_columns(). */
    public $sort = 'timecreated';

    /** @var string Sort direction, either 'ASC' or 'DESC'. */
    public $dir = 'DESC';

    /** @var bool True when the request asked for the CSV download. */
    public $export = false;

    /**
     * Sortable columns of the site-wide table, keyed by the value accepted in the URL.
     *
     * The values are literal SQL column references and are the ONLY thing ever placed in the
     * ORDER BY clause, so a crafted sort parameter cannot inject SQL.
     *
     * @return array<string, string> Sort key => SQL expression.
     */
    public static function get_sort_columns(): array {
        return [
            'timecreated' => 'c.timecreated',
            'course' => 'co.fullname',
            'student' => 'u.lastname, u.firstname',
            'rating' => 'c.rating',
            'refused' => 'c.refused',
        ];
    }

    /**
     * Number of rows per page offered in the "rows per page" menu.
     *
     * @return array<int, string> Value => label.
     */
    public static function get_perpage_options(): array {
        $options = [];
        foreach ([10, 25, 50, 100, 200] as $value) {
            $options[$value] = (string) $value;
        }
        return $options;
    }

    /**
     * Build the criteria from the current request.
     *
     * @return self
     */
    public static function from_request(): self {
        return self::from_values([
            'page' => optional_param('page', 0, PARAM_INT),
            'perpage' => optional_param('perpage', self::PERPAGE_DEFAULT, PARAM_INT),
            'filtercourseid' => optional_param('filtercourseid', 0, PARAM_INT),
            'filteruserid' => optional_param('filteruserid', 0, PARAM_INT),
            'filterrating' => optional_param('filterrating', '', PARAM_ALPHA),
            'filterrefused' => optional_param('filterrefused', '', PARAM_ALPHA),
            'datefrom' => optional_param('datefrom', '', PARAM_TEXT),
            'dateto' => optional_param('dateto', '', PARAM_TEXT),
            'search' => optional_param('search', '', PARAM_TEXT),
            'sort' => optional_param('sort', 'timecreated', PARAM_ALPHA),
            'dir' => optional_param('dir', 'DESC', PARAM_ALPHA),
            'export' => optional_param('export', 0, PARAM_INT),
        ]);
    }

    /**
     * Build the criteria from an array of already-typed values.
     *
     * Every bound, clamp and allow-list lives here, so this is the single place that decides what
     * a user may ask the site-wide report for. Keeping it separate from from_request() means the
     * validation can be unit tested directly, without a test writing to a superglobal.
     *
     * @param array $values Raw values keyed by request parameter name. Missing keys use defaults.
     * @return self
     */
    public static function from_values(array $values): self {
        $filter = new self();

        $filter->page = max((int) ($values['page'] ?? 0), 0);

        $perpage = (int) ($values['perpage'] ?? self::PERPAGE_DEFAULT);
        $filter->perpage = min(max($perpage, self::PERPAGE_MIN), self::PERPAGE_MAX);

        $filter->filtercourseid = max((int) ($values['filtercourseid'] ?? 0), 0);
        $filter->filteruserid = max((int) ($values['filteruserid'] ?? 0), 0);

        $rating = (string) ($values['filterrating'] ?? '');
        $filter->filterrating = in_array($rating, ['helpful', 'nothelpful', 'unrated'], true) ? $rating : '';

        $refused = (string) ($values['filterrefused'] ?? '');
        $filter->filterrefused = in_array($refused, ['refused', 'answered'], true) ? $refused : '';

        $filter->datefromstr = (string) ($values['datefrom'] ?? '');
        $filter->datetostr = (string) ($values['dateto'] ?? '');
        $filter->datefrom = $filter->datefromstr ? (int) strtotime($filter->datefromstr . ' 00:00:00') : 0;
        $filter->dateto = $filter->datetostr ? (int) strtotime($filter->datetostr . ' 23:59:59') : 0;
        $filter->datefrom = max($filter->datefrom, 0);
        $filter->dateto = max($filter->dateto, 0);

        $filter->search = trim((string) ($values['search'] ?? ''));

        $sort = (string) ($values['sort'] ?? 'timecreated');
        $filter->sort = array_key_exists($sort, self::get_sort_columns()) ? $sort : 'timecreated';

        $filter->dir = (strtoupper((string) ($values['dir'] ?? 'DESC')) === 'ASC') ? 'ASC' : 'DESC';

        $filter->export = (bool) ($values['export'] ?? 0);

        return $filter;
    }

    /**
     * Every criteria value as URL parameters, so a link can carry the current view forward.
     *
     * @return array<string, mixed>
     */
    public function get_params(): array {
        return [
            'filtercourseid' => $this->filtercourseid,
            'filteruserid' => $this->filteruserid,
            'filterrating' => $this->filterrating,
            'filterrefused' => $this->filterrefused,
            'datefrom' => $this->datefromstr,
            'dateto' => $this->datetostr,
            'search' => $this->search,
            'perpage' => $this->perpage,
            'sort' => $this->sort,
            'dir' => $this->dir,
        ];
    }

    /**
     * The report URL for these criteria.
     *
     * @param array<string, mixed> $overrides Parameters to add or replace.
     * @return moodle_url
     */
    public function get_url(array $overrides = []): moodle_url {
        return new moodle_url(
            '/course/format/aicourse/admin_report.php',
            array_merge($this->get_params(), $overrides)
        );
    }

    /**
     * The SQL ORDER BY clause for the site-wide table.
     *
     * @return string
     */
    public function get_order_by(): string {
        $columns = self::get_sort_columns();
        return $columns[$this->sort] . ' ' . $this->dir;
    }

    /**
     * The FROM ... WHERE part of the site-wide query, shared by the table, the counter and the CSV.
     *
     * @param moodle_database $db The database driver, for its portable LIKE helpers.
     * @return array{0: string, 1: array} SQL fragment and its named parameters.
     */
    public function get_base_sql(moodle_database $db): array {
        $where = '1 = 1';
        $params = [];

        if ($this->filtercourseid > 0) {
            $where .= ' AND c.courseid = :courseid';
            $params['courseid'] = $this->filtercourseid;
        }
        if ($this->filteruserid > 0) {
            $where .= ' AND c.userid = :userid';
            $params['userid'] = $this->filteruserid;
        }
        if ($this->filterrating === 'helpful') {
            $where .= ' AND c.rating = 1';
        } else if ($this->filterrating === 'nothelpful') {
            $where .= ' AND c.rating = -1';
        } else if ($this->filterrating === 'unrated') {
            $where .= ' AND c.rating = 0';
        }
        if ($this->filterrefused === 'refused') {
            $where .= ' AND c.refused = 1';
        } else if ($this->filterrefused === 'answered') {
            $where .= ' AND c.refused = 0';
        }
        if ($this->datefrom > 0) {
            $where .= ' AND c.timecreated >= :datefrom';
            $params['datefrom'] = $this->datefrom;
        }
        if ($this->dateto > 0) {
            $where .= ' AND c.timecreated <= :dateto';
            $params['dateto'] = $this->dateto;
        }
        if ($this->search !== '') {
            $where .= ' AND (' . $db->sql_like('c.question', ':search1', false)
                . ' OR ' . $db->sql_like('c.response', ':search2', false) . ')';
            $params['search1'] = '%' . $db->sql_like_escape($this->search) . '%';
            $params['search2'] = '%' . $db->sql_like_escape($this->search) . '%';
        }

        $sql = "FROM {format_aicourse_chats} c
                  JOIN {user} u ON u.id = c.userid
                  JOIN {course} co ON co.id = c.courseid
                 WHERE $where";

        return [$sql, $params];
    }
}
