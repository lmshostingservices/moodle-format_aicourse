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
 * Tests for the section view URL, including stale section returns.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/format/aicourse/lib.php');

/**
 * Tests for the section view URL, including stale section returns.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse::get_view_url
 */
final class view_url_test extends \advanced_testcase {
    /**
     * A section return that still resolves must keep producing a section page URL.
     *
     * This is the regression guard for the fix below: the fallback must not swallow the
     * normal case and send every section link to the course page.
     */
    public function test_live_section_return_still_links_to_the_section_page(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $format = course_get_format($course);
        $sectionid = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 2]);

        $url = $format->get_view_url(null, ['sr' => 2]);

        $this->assertStringContainsString('/course/section.php', $url->out(false));
        $this->assertEquals($sectionid, $url->param('id'));
    }

    /**
     * A section return pointing at a section that does not exist must not blow up.
     *
     * format_topics::get_view_url() calls get_section() and dereferences the result without
     * checking it, so a stale 'sr' produces
     *
     *     Warning: Attempt to read property "id" on null
     *
     * and a URL of "/course/section.php?id" with no value. Asserting on the URL alone would
     * pass even with the warning still being emitted, so the error handler is installed to
     * catch it: this test fails if the warning comes back.
     */
    public function test_stale_section_return_falls_back_to_the_course_page(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $format = course_get_format($course);

        $warnings = [];
        set_error_handler(function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        });
        try {
            $url = $format->get_view_url(null, ['sr' => 99]);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings, 'get_view_url() emitted a PHP warning for a stale section return');
        $this->assertStringContainsString('/course/view.php', $url->out(false));
        $this->assertEquals($course->id, $url->param('id'));
    }

    /**
     * The fallback must be core's own answer, not a URL of this plugin's invention.
     *
     * Core's answer for "the course page" is not the same on every supported version: 4.4
     * returns a bare course URL, while 5.0 also sets a "#section-N" anchor for the section that
     * was asked for. An assertion naming either one would pass on one version and quietly
     * regress the other, so this asserts the invariant instead -- a stale section return must
     * produce exactly what core produces when no section return is supplied at all.
     */
    public function test_the_fallback_matches_what_core_answers_without_a_section_return(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $format = course_get_format($course);

        // No 'sr' at all: this goes straight to core, whatever core does on this version.
        $expected = $format->get_view_url(1, []);
        // A section return that no longer resolves must land in the same place.
        $actual = $format->get_view_url(1, ['sr' => 99]);

        $this->assertSame($expected->out(false), $actual->out(false));
    }

    /**
     * Deleting the section you are viewing must not produce a broken redirect target.
     *
     * This reproduces course/editsection.php's delete branch exactly. That script keeps 'sr'
     * in the parameters it redirects back to after the delete, and 'sr' outranks the section
     * number handed to course_get_url() alongside it, so deleting the last section of a
     * course while viewing it asks for a section that was destroyed on the previous line.
     */
    public function test_deleting_the_last_section_while_viewing_it_redirects_to_the_course(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 2],
            ['createsections' => true]
        );

        // Exactly what course/editsection.php?id=<id>&delete=1&sr=2 does.
        $returnparams = ['sr' => 2];
        $sectioninfo = get_fast_modinfo($course)->get_section_info(2);
        course_delete_section($course, $sectioninfo, true, true);

        $warnings = [];
        set_error_handler(function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        });
        try {
            $url = course_get_url($course, $sectioninfo->section - 1, $returnparams);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings, 'The post-delete redirect emitted a PHP warning');
        $this->assertStringContainsString('/course/view.php', $url->out(false));
        $this->assertEquals($course->id, $url->param('id'));

        // The symptom this guards against: an id parameter present but empty, which sends
        // the browser to /course/section.php?id and throws "Can't find data record".
        $this->assertStringNotContainsString('/course/section.php', $url->out(false));
    }
}
