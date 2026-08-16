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
 * Unit tests for the AI Tutor report pages.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\report;

use context_course;
use format_aicourse\output\report\adminfilter;
use format_aicourse\output\report\adminreport;
use format_aicourse\output\report\chatfilter;
use format_aicourse\output\report\contenttab;
use format_aicourse\output\report\coursereport;
use format_aicourse\output\report\csvexporter;
use format_aicourse\output\report\historytab;
use format_aicourse\output\report\indexpage;

/**
 * Unit tests for the AI Tutor report pages.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\output\report\chatfilter
 * @covers     \format_aicourse\output\report\adminfilter
 * @covers     \format_aicourse\output\report\csvexporter
 * @covers     \format_aicourse\output\report\coursereport
 * @covers     \format_aicourse\output\report\historytab
 * @covers     \format_aicourse\output\report\contenttab
 * @covers     \format_aicourse\output\report\adminreport
 * @covers     \format_aicourse\output\report\indexpage
 */
final class report_test extends \advanced_testcase {
    /**
     * Request parameter values under test.
     *
     * The filter classes expose from_values() precisely so their clamps and allow-lists can be
     * driven directly. Accumulating the values here mirrors how a real request builds up a query
     * string, without a test having to write to a superglobal.
     *
     * @var array
     */
    protected $params = [];

    /**
     * Start every test with an empty parameter set.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->params = [];
    }

    /**
     * Insert one AI tutor chat row.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param string $question Question text.
     * @param array $extra Any other column overrides.
     * @return int The new record id.
     */
    protected function make_chat(int $courseid, int $userid, string $question, array $extra = []): int {
        global $DB;

        $record = (object) array_merge([
            'courseid' => $courseid,
            'userid' => $userid,
            'activityid' => 0,
            'question' => $question,
            'response' => 'Answer to: ' . $question,
            'rating' => 0,
            'refused' => 0,
            'locked' => 0,
            'correction' => null,
            'correctedby' => null,
            'timecreated' => time(),
            'timecorrected' => null,
        ], $extra);

        return (int) $DB->insert_record('format_aicourse_chats', $record);
    }

    /**
     * perpage is clamped at both ends.
     *
     * ACF-FIX-2.0: perpage=0 reached ceil($total / $perpage) and raised a fatal
     * DivisionByZeroError on PHP 8; a huge value pulled the whole table into memory.
     *
     * @return void
     */
    public function test_chatfilter_clamps_perpage(): void {
        $this->resetAfterTest();

        $this->params['id'] = 7;

        $this->params['perpage'] = 0;
        $this->assertEquals(chatfilter::PERPAGE_MIN, chatfilter::from_values($this->params)->perpage);

        $this->params['perpage'] = 99999;
        $this->assertEquals(chatfilter::PERPAGE_MAX, chatfilter::from_values($this->params)->perpage);

        $this->params['perpage'] = -5;
        $this->assertEquals(chatfilter::PERPAGE_MIN, chatfilter::from_values($this->params)->perpage);

        $this->params['perpage'] = 20;
        $this->assertEquals(20, chatfilter::from_values($this->params)->perpage);

        unset($this->params['perpage']);
        $this->assertEquals(chatfilter::PERPAGE_DEFAULT, chatfilter::from_values($this->params)->perpage);

        // And the same clamp on the site-wide report.
        $this->params['perpage'] = 0;
        $this->assertEquals(adminfilter::PERPAGE_MIN, adminfilter::from_values($this->params)->perpage);
        $this->params['perpage'] = 500000;
        $this->assertEquals(adminfilter::PERPAGE_MAX, adminfilter::from_values($this->params)->perpage);
    }

    /**
     * page can never be negative, which would make an invalid LIMIT offset.
     *
     * @return void
     */
    public function test_filters_clamp_page(): void {
        $this->resetAfterTest();

        $this->params['id'] = 7;
        $this->params['page'] = -10;
        $this->assertEquals(0, chatfilter::from_values($this->params)->page);
        $this->assertEquals(0, adminfilter::from_values($this->params)->page);
    }

    /**
     * Only a known sort key can ever reach the ORDER BY clause.
     *
     * @return void
     */
    public function test_sort_key_allow_list(): void {
        $this->resetAfterTest();

        $this->params['id'] = 7;
        $this->params['sort'] = 'question';
        $this->params['dir'] = 'asc';
        $filter = chatfilter::from_values($this->params);
        $this->assertEquals('question', $filter->sort);
        $this->assertEquals('ASC', $filter->dir);
        $this->assertEquals('c.question ASC', $filter->get_order_by());

        // Anything not in the allow list falls back to the default, so no request text can be
        // interpolated into the SQL.
        $this->params['sort'] = 'evil';
        $this->params['dir'] = 'DROP';
        $filter = chatfilter::from_values($this->params);
        $this->assertEquals('timecreated', $filter->sort);
        $this->assertEquals('DESC', $filter->dir);
        $this->assertEquals('c.timecreated DESC', $filter->get_order_by());

        $this->params['sort'] = 'nope';
        $this->assertEquals('c.timecreated DESC', adminfilter::from_values($this->params)->get_order_by());

        foreach (array_keys(chatfilter::get_sort_columns()) as $key) {
            $this->params['sort'] = $key;
            $this->assertStringNotContainsString(';', chatfilter::from_values($this->params)->get_order_by());
        }
    }

    /**
     * Only a known rating / refused filter value is accepted.
     *
     * @return void
     */
    public function test_enumerated_filters_are_validated(): void {
        $this->resetAfterTest();

        $this->params['id'] = 7;
        $this->params['filterrating'] = 'bogus';
        $this->assertSame('', chatfilter::from_values($this->params)->filterrating);

        $this->params['filterrating'] = 'corrected';
        $this->assertSame('corrected', chatfilter::from_values($this->params)->filterrating);

        $this->params['filterrefused'] = 'bogus';
        $this->assertSame('', adminfilter::from_values($this->params)->filterrefused);

        $this->params['filterrefused'] = 'refused';
        $this->assertSame('refused', adminfilter::from_values($this->params)->filterrefused);
    }

    /**
     * A '%' or '_' typed into the search box is a literal, not a wildcard.
     *
     * @return void
     */
    public function test_search_escapes_like_wildcards(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $context = context_course::instance($course->id);

        $this->make_chat($course->id, $user->id, 'plain question about gravity');
        $this->make_chat($course->id, $user->id, 'question with a literal 100% mark');

        $this->params['id'] = $course->id;
        $this->params['search'] = '%';
        $filter = chatfilter::from_values($this->params);
        [$where, $params] = $filter->get_where($DB, $context, null);
        $count = $DB->count_records_sql(
            "SELECT COUNT(1) FROM {format_aicourse_chats} c JOIN {user} u ON u.id = c.userid WHERE $where",
            $params
        );
        $this->assertEquals(1, $count, 'A literal % must match only the row that contains one.');

        $this->params['search'] = 'gravity';
        [$where, $params] = chatfilter::from_values($this->params)->get_where($DB, $context, null);
        $this->assertEquals(1, $DB->count_records_sql(
            "SELECT COUNT(1) FROM {format_aicourse_chats} c JOIN {user} u ON u.id = c.userid WHERE $where",
            $params
        ));
    }

    /**
     * ACF-FIX-2.0: a teacher restricted to separate groups sees only their own group's rows,
     * and their headline counters agree with the table.
     *
     * @return void
     */
    public function test_separate_groups_restriction(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'aicourse', 'groupmode' => SEPARATEGROUPS]);
        $context = context_course::instance($course->id);

        $teacher = $generator->create_user();
        $mine = $generator->create_user();
        $theirs = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->enrol_user($mine->id, $course->id, 'student');
        $generator->enrol_user($theirs->id, $course->id, 'student');

        $groupa = $generator->create_group(['courseid' => $course->id]);
        $groupb = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $mine->id]);
        $generator->create_group_member(['groupid' => $groupb->id, 'userid' => $theirs->id]);

        $this->make_chat($course->id, $mine->id, 'question from my own group');
        $this->make_chat($course->id, $theirs->id, 'question from the other group');

        $this->setUser($teacher);
        $allowed = coursereport::get_allowed_group_ids($course, $context);
        $this->assertIsArray($allowed, 'A non-editing teacher must be restricted in separate groups mode.');
        $this->assertEquals([$groupa->id], array_values($allowed));

        $this->params['id'] = $course->id;
        unset(
            $this->params['search'],
            $this->params['sort'],
            $this->params['dir'],
            $this->params['filterrating'],
            $this->params['perpage'],
            $this->params['page']
        );
        $filter = chatfilter::from_values($this->params);

        [$where, $params] = $filter->get_where($DB, $context, $allowed);
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.question FROM {format_aicourse_chats} c JOIN {user} u ON u.id = c.userid WHERE $where",
            $params
        );
        $this->assertCount(1, $rows);
        $this->assertEquals('question from my own group', reset($rows)->question);

        // The headline counters carry the same restriction.
        [$statswhere, $statsparams] = $filter->get_stats_where($context, $allowed);
        $this->assertEquals(1, $DB->count_records_sql(
            "SELECT COUNT(1) FROM {format_aicourse_chats} c WHERE $statswhere",
            $statsparams
        ));

        // A teacher who belongs to no group at all sees nobody.
        [$nowhere, $noparams] = $filter->get_where($DB, $context, []);
        $this->assertEquals(0, $DB->count_records_sql(
            "SELECT COUNT(1) FROM {format_aicourse_chats} c JOIN {user} u ON u.id = c.userid WHERE $nowhere",
            $noparams
        ));

        // A manager with moodle/site:accessallgroups is not restricted.
        $this->setAdminUser();
        $this->assertNull(coursereport::get_allowed_group_ids($course, $context));
    }

    /**
     * A group the viewer does not belong to cannot be selected in the filter.
     *
     * @return void
     */
    public function test_group_filter_cannot_escape_the_restriction(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'aicourse', 'groupmode' => SEPARATEGROUPS]);
        $context = context_course::instance($course->id);
        $teacher = $generator->create_user();
        $theirs = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->enrol_user($theirs->id, $course->id, 'student');
        $groupa = $generator->create_group(['courseid' => $course->id]);
        $groupb = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $groupb->id, 'userid' => $theirs->id]);
        $this->make_chat($course->id, $theirs->id, 'question from the other group');

        $this->setUser($teacher);
        $this->params['id'] = $course->id;
        $this->params['filtergroup'] = $groupb->id;
        unset(
            $this->params['search'],
            $this->params['sort'],
            $this->params['dir'],
            $this->params['filterrating'],
            $this->params['perpage'],
            $this->params['page']
        );
        $filter = chatfilter::from_values($this->params);

        [$where, $params] = $filter->get_where($DB, $context, [$groupa->id]);
        $this->assertEquals(0, $DB->count_records_sql(
            "SELECT COUNT(1) FROM {format_aicourse_chats} c JOIN {user} u ON u.id = c.userid WHERE $where",
            $params
        ));
        $this->assertEquals(0, $filter->filtergroup, 'The out-of-range group filter must be discarded.');
    }

    /**
     * ACF-FIX-2.0: every CSV cell that could be read as a formula is neutralised.
     *
     * @return void
     */
    public function test_csv_formula_injection_is_neutralised(): void {
        $this->assertSame("'=cmd|' /C calc'!A0", csvexporter::safe_cell("=cmd|' /C calc'!A0"));
        $this->assertSame("'=HYPERLINK(\"http://evil\")", csvexporter::safe_cell('=HYPERLINK("http://evil")'));
        $this->assertSame("'+1234", csvexporter::safe_cell('+1234'));
        $this->assertSame("'-1234", csvexporter::safe_cell('-1234'));
        $this->assertSame("'@SUM(A1)", csvexporter::safe_cell('@SUM(A1)'));
        $this->assertSame("'\tleading tab", csvexporter::safe_cell("\tleading tab"));
        $this->assertSame("'\rleading cr", csvexporter::safe_cell("\rleading cr"));

        // Ordinary values are untouched.
        $this->assertSame('', csvexporter::safe_cell(''));
        $this->assertSame('What is a light year?', csvexporter::safe_cell('What is a light year?'));
        $this->assertSame('1234', csvexporter::safe_cell(1234));

        // Every heading is passed through the same filter.
        foreach (csvexporter::get_columns() as $heading) {
            $this->assertFalse(strpbrk(substr($heading, 0, 1), "=+-@\t\r"));
        }
    }

    /**
     * A whole chat row survives the CSV mapping with its hostile cells neutralised.
     *
     * @return void
     */
    public function test_csv_row_mapping(): void {
        $this->resetAfterTest();

        $chat = (object) [
            'timecreated' => 1700000000,
            'coursefullname' => 'Astro 101',
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'email' => 'ada@example.com',
            'activityid' => 0,
            'question' => "=cmd|' /C calc'!A0",
            'response' => 'A safe response',
            'rating' => 1,
            'refused' => 0,
            'correction' => null,
        ];
        $row = csvexporter::get_row($chat);
        $this->assertCount(10, $row);
        $this->assertSame("'=cmd|' /C calc'!A0", $row[5]);
        $this->assertSame('A safe response', $row[6]);
        $this->assertSame(get_string('aireport_filter_helpful', 'format_aicourse'), $row[7]);
        $this->assertSame(get_string('no'), $row[8]);
    }

    /**
     * The chat history export escapes nothing itself: it hands the template plain text, which
     * the template escapes with a double mustache. Markup a student typed must survive as text.
     *
     * @return void
     */
    public function test_history_export_keeps_transcript_as_plain_text(): void {
        global $PAGE;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'aicourse']);
        $context = context_course::instance($course->id);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->make_chat($course->id, $student->id, '<img src=x onerror=alert(1)>');

        $this->setAdminUser();
        $this->params['id'] = $course->id;
        $this->params['tab'] = 'history';
        unset(
            $this->params['search'],
            $this->params['sort'],
            $this->params['dir'],
            $this->params['filterrating'],
            $this->params['perpage'],
            $this->params['page']
        );

        $PAGE->set_url(new \moodle_url('/course/format/aicourse/report.php', ['id' => $course->id]));
        $PAGE->set_context($context);
        $output = $PAGE->get_renderer('format_aicourse');

        $tab = new historytab($course, $context, chatfilter::from_values($this->params), null);
        $data = $tab->export_for_template($output);

        $this->assertTrue($data->hasrows);
        $this->assertCount(1, $data->rows);
        $this->assertSame('<img src=x onerror=alert(1)>', $data->rows[0]->question);

        // And the rendered template escapes it.
        $html = $output->render_from_template('format_aicourse/report_history_tab', $data);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
    }

    /**
     * Every report renderable exports a context its template can render.
     *
     * @return void
     */
    public function test_every_renderable_renders(): void {
        global $PAGE;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'aicourse']);
        $context = context_course::instance($course->id);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->make_chat($course->id, $student->id, 'A question', ['rating' => 1]);
        $this->make_chat($course->id, $student->id, 'Another question', ['correction' => 'Not quite.']);

        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/course/format/aicourse/report.php', ['id' => $course->id]));
        $PAGE->set_context($context);
        $output = $PAGE->get_renderer('format_aicourse');

        unset(
            $this->params['search'],
            $this->params['sort'],
            $this->params['dir'],
            $this->params['filterrating'],
            $this->params['perpage'],
            $this->params['page']
        );
        $this->params['id'] = $course->id;

        foreach (['content', 'history'] as $tab) {
            $this->params['tab'] = $tab;
            $report = new coursereport($course, $context, chatfilter::from_values($this->params));
            $this->assertSame('format_aicourse/report_page', $report->get_template_name($output));
            $html = $output->render($report);
            $this->assertStringContainsString('aicourse-report-container', $html);
        }

        $contenttab = new contenttab($course, $context);
        $this->assertSame('format_aicourse/report_content_tab', $contenttab->get_template_name($output));
        $this->assertIsInt($contenttab->export_for_template($output)->sectioncount);

        $admin = new adminreport(adminfilter::from_values($this->params));
        $this->assertSame('format_aicourse/admin_report_page', $admin->get_template_name($output));
        $adminhtml = $output->render($admin);
        $this->assertStringContainsString('aicadmin-wrap', $adminhtml);
        $this->assertStringContainsString('aicadmin-table', $adminhtml);

        $index = new indexpage();
        $this->assertSame('format_aicourse/report_index', $index->get_template_name($output));
        $indexhtml = $output->render($index);
        $this->assertStringContainsString('aitutor-course-grid', $indexhtml);
    }

    /**
     * The URL helpers round-trip every criteria value, so paging and sorting keep the filters.
     *
     * @return void
     */
    public function test_urls_carry_the_current_view(): void {
        $this->resetAfterTest();

        $this->params['id'] = 42;
        $this->params['tab'] = 'history';
        $this->params['filteruser'] = 9;
        $this->params['filterrating'] = 'helpful';
        $this->params['search'] = 'entropy';
        $this->params['sort'] = 'rating';
        $this->params['dir'] = 'ASC';
        $url = chatfilter::from_values($this->params)->get_url(['page' => 3])->out(false);

        $fragments = [
            'id=42', 'tab=history', 'filteruser=9', 'filterrating=helpful',
            'search=entropy', 'sort=rating', 'dir=ASC', 'page=3',
        ];
        foreach ($fragments as $fragment) {
            $this->assertStringContainsString($fragment, $url);
        }
    }

    /**
     * The site-wide query honours every filter it offers.
     *
     * @return void
     */
    public function test_adminfilter_base_sql(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $one = $generator->create_course(['format' => 'aicourse']);
        $two = $generator->create_course(['format' => 'aicourse']);
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $one->id);
        $generator->enrol_user($user->id, $two->id);

        $this->make_chat($one->id, $user->id, 'helpful one', ['rating' => 1]);
        $this->make_chat($one->id, $user->id, 'refused one', ['refused' => 1]);
        $this->make_chat($two->id, $user->id, 'other course');

        $count = function (adminfilter $filter) use ($DB): int {
            [$sql, $params] = $filter->get_base_sql($DB);
            return $DB->count_records_sql("SELECT COUNT(1) $sql", $params);
        };

        unset($this->params['search'], $this->params['sort'], $this->params['dir'], $this->params['perpage']);
        unset($this->params['page'], $this->params['datefrom'], $this->params['dateto']);
        unset($this->params['filteruserid'], $this->params['filterrefused']);

        $this->params['filtercourseid'] = 0;
        $this->params['filterrating'] = '';
        $this->assertEquals(3, $count(adminfilter::from_values($this->params)));

        $this->params['filtercourseid'] = $one->id;
        $this->assertEquals(2, $count(adminfilter::from_values($this->params)));

        $this->params['filterrating'] = 'helpful';
        $this->assertEquals(1, $count(adminfilter::from_values($this->params)));

        $this->params['filterrating'] = '';
        $this->params['filterrefused'] = 'refused';
        $this->assertEquals(1, $count(adminfilter::from_values($this->params)));

        $this->params['filterrefused'] = '';
        $this->params['filtercourseid'] = 0;
        $this->params['search'] = 'other course';
        $this->assertEquals(1, $count(adminfilter::from_values($this->params)));
    }
}
