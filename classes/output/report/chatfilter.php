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
use moodle_database;
use moodle_url;

/**
 * Request criteria for the per-course AI Tutor report (report.php).
 *
 * This class owns every value the report page reads from the request, the clamping of those
 * values, and the SQL fragments built from them. Keeping it in one place means the page, the
 * headline counters and the pager can never disagree about what is being shown.
 *
 * ACF-FIX-2.0 guards preserved here:
 *  - perpage is clamped to [PERPAGE_MIN, PERPAGE_MAX]. perpage=0 used to reach
 *    ceil($total / $perpage) and raise a fatal DivisionByZeroError on PHP 8, and a huge value
 *    pulled the whole table into memory.
 *  - page can never be negative.
 *  - the free-text search is turned into a LIKE pattern with $DB->sql_like_escape(), so '%' and
 *    '_' typed by a user are literals rather than wildcards.
 *  - the sort column is looked up in an allow list, never interpolated from the request.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chatfilter {
    /** @var int Smallest number of rows a user may ask for. */
    const PERPAGE_MIN = 5;

    /** @var int Largest number of rows a user may ask for. */
    const PERPAGE_MAX = 200;

    /** @var int Default number of rows per page. */
    const PERPAGE_DEFAULT = 20;

    /** @var string The course content tab. */
    const TAB_CONTENT = 'content';

    /** @var string The chat history tab. */
    const TAB_HISTORY = 'history';

    /** @var int Course id the report is for. */
    public $courseid = 0;

    /** @var string Active tab, one of TAB_CONTENT or TAB_HISTORY. */
    public $tab = self::TAB_CONTENT;

    /** @var int Zero-based page number. */
    public $page = 0;

    /** @var int Rows per page, already clamped. */
    public $perpage = self::PERPAGE_DEFAULT;

    /** @var int User id to filter by, 0 for everybody. */
    public $filteruser = 0;

    /** @var int Group id to filter by, 0 for every group. */
    public $filtergroup = 0;

    /** @var string Rating filter: '', 'helpful', 'nothelpful' or 'corrected'. */
    public $filterrating = '';

    /** @var string Free-text search over the question and the response. */
    public $search = '';

    /** @var string Sort key, always one of the keys of get_sort_columns(). */
    public $sort = 'timecreated';

    /** @var string Sort direction, either 'ASC' or 'DESC'. */
    public $dir = 'DESC';

    /**
     * Sortable columns of the chat history table, keyed by the value accepted in the URL.
     *
     * The values are literal SQL column references and are the ONLY thing ever placed in the
     * ORDER BY clause, so a crafted sort parameter cannot inject SQL.
     *
     * @return array<string, string> Sort key => SQL expression.
     */
    public static function get_sort_columns(): array {
        return [
            'student' => 'u.lastname, u.firstname',
            'question' => 'c.question',
            'timecreated' => 'c.timecreated',
            'rating' => 'c.rating',
        ];
    }

    /**
     * Build the criteria from the current request.
     *
     * Reads the request parameters and hands them to from_values(), which owns every clamp and
     * allow-list. Splitting it this way keeps the validation unit testable without a test having
     * to write to a superglobal to reach it.
     *
     * @return self
     */
    public static function from_request(): self {
        return self::from_values([
            'id' => required_param('id', PARAM_INT),
            'tab' => optional_param('tab', self::TAB_CONTENT, PARAM_ALPHA),
            'page' => optional_param('page', 0, PARAM_INT),
            'perpage' => optional_param('perpage', self::PERPAGE_DEFAULT, PARAM_INT),
            'filteruser' => optional_param('filteruser', 0, PARAM_INT),
            'filtergroup' => optional_param('filtergroup', 0, PARAM_INT),
            'filterrating' => optional_param('filterrating', '', PARAM_ALPHA),
            'search' => optional_param('search', '', PARAM_TEXT),
            'sort' => optional_param('sort', 'timecreated', PARAM_ALPHA),
            'dir' => optional_param('dir', 'DESC', PARAM_ALPHA),
        ]);
    }

    /**
     * Build the criteria from an array of already-typed values.
     *
     * This is where every bound, clamp and allow-list lives, so it is the single place that
     * decides what a user is allowed to ask this report for. In particular `perpage` is clamped
     * (an unbounded 0 caused a DivisionByZeroError, and an unbounded large value could exhaust
     * memory) and `sort` and `dir` are resolved against allow-lists so nothing user-supplied is
     * ever interpolated into SQL.
     *
     * @param array $values Raw values keyed by request parameter name. Missing keys use defaults.
     * @return self
     */
    public static function from_values(array $values): self {
        $filter = new self();
        $filter->courseid = (int) ($values['id'] ?? 0);

        $tab = (string) ($values['tab'] ?? self::TAB_CONTENT);
        $filter->tab = ($tab === self::TAB_HISTORY) ? self::TAB_HISTORY : self::TAB_CONTENT;

        $filter->page = max((int) ($values['page'] ?? 0), 0);

        $perpage = (int) ($values['perpage'] ?? self::PERPAGE_DEFAULT);
        $filter->perpage = min(max($perpage, self::PERPAGE_MIN), self::PERPAGE_MAX);

        $filter->filteruser = max((int) ($values['filteruser'] ?? 0), 0);
        $filter->filtergroup = max((int) ($values['filtergroup'] ?? 0), 0);

        $rating = (string) ($values['filterrating'] ?? '');
        $filter->filterrating = in_array($rating, ['helpful', 'nothelpful', 'corrected'], true) ? $rating : '';

        $filter->search = trim((string) ($values['search'] ?? ''));

        $sort = (string) ($values['sort'] ?? 'timecreated');
        $filter->sort = array_key_exists($sort, self::get_sort_columns()) ? $sort : 'timecreated';

        $filter->dir = (strtoupper((string) ($values['dir'] ?? 'DESC')) === 'ASC') ? 'ASC' : 'DESC';

        return $filter;
    }

    /**
     * Number of rows per page offered in the "rows per page" menu.
     *
     * @return array<int, string> Value => label.
     */
    public static function get_perpage_options(): array {
        $options = [];
        foreach ([5, 10, 20, 50, 100, 200] as $value) {
            $options[$value] = (string) $value;
        }
        return $options;
    }

    /**
     * Every criteria value as URL parameters, so a link can carry the current view forward.
     *
     * @return array<string, mixed>
     */
    public function get_params(): array {
        return [
            'id' => $this->courseid,
            'tab' => $this->tab,
            'perpage' => $this->perpage,
            'filteruser' => $this->filteruser,
            'filtergroup' => $this->filtergroup,
            'filterrating' => $this->filterrating,
            'search' => $this->search,
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
        return new moodle_url('/course/format/aicourse/report.php', array_merge($this->get_params(), $overrides));
    }

    /**
     * The SQL ORDER BY clause for the chat history table.
     *
     * @return string
     */
    public function get_order_by(): string {
        $columns = self::get_sort_columns();
        return $columns[$this->sort] . ' ' . $this->dir;
    }

    /**
     * WHERE clause restricting the chat rows to what this viewer asked for and may see.
     *
     * @param moodle_database $db The database driver, for its portable LIKE helpers.
     * @param context_course $context Course context, used to resolve enrolled users per group.
     * @param array|null $allowedgroupids Null when the viewer may see every group; otherwise the
     *        (possibly empty) list of group ids the viewer shares with the students they may see.
     * @return array{0: string, 1: array} WHERE clause (chats aliased c, user aliased u) and params.
     */
    public function get_where(moodle_database $db, context_course $context, ?array $allowedgroupids): array {
        $params = ['courseid' => $this->courseid];
        $where = 'c.courseid = :courseid';

        if ($this->filteruser > 0) {
            $where .= ' AND c.userid = :userid';
            $params['userid'] = $this->filteruser;
        }

        if ($this->filterrating === 'helpful') {
            $where .= ' AND c.rating = 1';
        } else if ($this->filterrating === 'nothelpful') {
            $where .= ' AND c.rating = -1';
        } else if ($this->filterrating === 'corrected') {
            $where .= ' AND c.correction IS NOT NULL';
        }

        if ($this->search !== '') {
            // ACF-FIX-2.0: the raw value used to be interpolated into a LIKE pattern, so '%' and
            // '_' typed by the user acted as wildcards. Use the portable helpers instead.
            $where .= ' AND (' . $db->sql_like('c.question', ':search1', false)
                . ' OR ' . $db->sql_like('c.response', ':search2', false) . ')';
            $params['search1'] = '%' . $db->sql_like_escape($this->search) . '%';
            $params['search2'] = '%' . $db->sql_like_escape($this->search) . '%';
        }

        // ACF-FIX-2.0: in separate groups mode a teacher without moodle/site:accessallgroups may
        // only filter by groups they belong to themselves.
        if ($this->filtergroup > 0 && $allowedgroupids !== null && !in_array($this->filtergroup, $allowedgroupids)) {
            $this->filtergroup = 0;
        }
        if ($this->filtergroup > 0) {
            $groupmembers = groups_get_members($this->filtergroup, 'u.id');
            if (!empty($groupmembers)) {
                [$insql, $inparams] = $db->get_in_or_equal(array_keys($groupmembers), SQL_PARAMS_NAMED, 'grp');
                $where .= ' AND c.userid ' . $insql;
                $params = array_merge($params, $inparams);
            } else {
                // No members, so no results.
                $where .= ' AND 1 = 0';
            }
        }

        [$groupwhere, $groupparams] = $this->get_group_restriction($context, $allowedgroupids);
        $where .= $groupwhere;
        $params = array_merge($params, $groupparams);

        return [$where, $params];
    }

    /**
     * WHERE clause for the headline counters.
     *
     * ACF-FIX-2.0: these used to be course-wide, which leaked cross-group activity volume in
     * separate groups mode. They now carry the same group restriction as the table.
     *
     * @param context_course $context Course context.
     * @param array|null $allowedgroupids See get_where().
     * @return array{0: string, 1: array} WHERE clause (chats aliased c) and params.
     */
    public function get_stats_where(context_course $context, ?array $allowedgroupids): array {
        $params = ['statscourseid' => $this->courseid];
        $where = 'c.courseid = :statscourseid';

        [$groupwhere, $groupparams] = $this->get_group_restriction($context, $allowedgroupids);

        return [$where . $groupwhere, array_merge($params, $groupparams)];
    }

    /**
     * ACF-FIX-2.0: the separate groups restriction, limiting every row to users the viewer shares
     * a group with.
     *
     * @param context_course $context Course context.
     * @param array|null $allowedgroupids See get_where().
     * @return array{0: string, 1: array} A WHERE fragment starting with ' AND ', and its params.
     */
    protected function get_group_restriction(context_course $context, ?array $allowedgroupids): array {
        if ($allowedgroupids === null) {
            return ['', []];
        }
        if (empty($allowedgroupids)) {
            // The viewer belongs to no group, so they may see nobody.
            return [' AND 1 = 0', []];
        }
        [$enrolledsql, $enrolledparams] = get_enrolled_sql($context, '', $allowedgroupids);
        return [' AND c.userid IN (' . $enrolledsql . ')', $enrolledparams];
    }
}
