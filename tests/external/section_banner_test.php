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
 * Tests for the section banner behaviour of the banner external functions.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\external;

use core_external\external_api;
use format_aicourse\local\banner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/format/aicourse/tests/external/external_testcase.php');

/**
 * Tests for the section banner behaviour of the banner external functions.
 *
 * The course-banner paths are already covered by delete_banner_image_test and
 * generate_banner_image_test; this file covers only what the section id adds.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\external\delete_banner_image
 * @covers     \format_aicourse\external\get_banner_status
 */
final class section_banner_test extends external_testcase {
    /**
     * Store an image against a banner target.
     *
     * @param string $filearea File area to write into.
     * @param int $itemid Item id to file it under.
     * @param string $filename Name of the stored file.
     * @return void
     */
    private function store_image(string $filearea, int $itemid, string $filename): void {
        get_file_storage()->create_file_from_string([
            'contextid' => $this->context->id,
            'component' => 'format_aicourse',
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
        ], 'not really a jpeg');
    }

    /**
     * Return a section of the fixture course.
     *
     * @param int $number Section number.
     * @return \section_info
     */
    private function section(int $number): \section_info {
        return get_fast_modinfo($this->course)->get_section_info($number);
    }

    /**
     * Count the files in a banner area.
     *
     * @param string $filearea File area to count.
     * @param int $itemid Item id to count under.
     * @return int
     */
    private function count_files(string $filearea, int $itemid): int {
        return count(get_file_storage()->get_area_files(
            $this->context->id,
            'format_aicourse',
            $filearea,
            $itemid,
            'itemid',
            false
        ));
    }

    /**
     * Removing a section's banner must leave the course banner alone.
     *
     * The two live in different file areas and this is the assertion that keeps them there. If
     * it ever fails, a teacher tidying up one section's image silently removes the banner from
     * the whole course.
     */
    public function test_deleting_a_section_banner_leaves_the_course_banner(): void {
        $section = $this->section(1);
        $this->store_image(banner::COURSE_AREA, banner::BANNER_ITEMID, 'course.jpg');
        $this->store_image(banner::SECTION_AREA, (int) $section->id, 'section.jpg');

        $this->setUser($this->teacher);
        $result = delete_banner_image::execute($this->course->id, (int) $section->id);
        $result = external_api::clean_returnvalue(delete_banner_image::execute_returns(), $result);

        $this->assertTrue($result['status']);
        $this->assertSame(0, $this->count_files(banner::SECTION_AREA, (int) $section->id));
        $this->assertSame(1, $this->count_files(banner::COURSE_AREA, banner::BANNER_ITEMID));
    }

    /**
     * Removing a section's banner reports the course banner it falls back to.
     *
     * The browser repaints the hero from this value. Reporting nothing would blank a banner that
     * is still there, and the teacher would only find out it was fine by reloading.
     */
    public function test_deleting_a_section_banner_reports_the_inherited_one(): void {
        $section = $this->section(1);
        $this->store_image(banner::COURSE_AREA, banner::BANNER_ITEMID, 'course.jpg');
        $this->store_image(banner::SECTION_AREA, (int) $section->id, 'section.jpg');

        $this->setUser($this->teacher);
        $result = delete_banner_image::execute($this->course->id, (int) $section->id);
        $result = external_api::clean_returnvalue(delete_banner_image::execute_returns(), $result);

        $this->assertStringContainsString('course.jpg', $result['imageurl']);
    }

    /**
     * Removing the course banner while a section has its own leaves the section's alone.
     */
    public function test_deleting_the_course_banner_leaves_section_banners(): void {
        $section = $this->section(1);
        $this->store_image(banner::COURSE_AREA, banner::BANNER_ITEMID, 'course.jpg');
        $this->store_image(banner::SECTION_AREA, (int) $section->id, 'section.jpg');

        $this->setUser($this->teacher);
        delete_banner_image::execute($this->course->id);

        $this->assertSame(0, $this->count_files(banner::COURSE_AREA, banner::BANNER_ITEMID));
        $this->assertSame(1, $this->count_files(banner::SECTION_AREA, (int) $section->id));
    }

    /**
     * A teacher must not be able to delete a banner from a course they do not teach.
     *
     * The capability is checked against the course in the call, so a section id belonging to
     * another course would otherwise be accepted on this course's authority.
     */
    public function test_a_section_from_another_course_cannot_be_deleted(): void {
        $othercourse = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 2],
            ['createsections' => true]
        );
        $foreign = get_fast_modinfo($othercourse)->get_section_info(1);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance($othercourse->id)->id,
            'component' => 'format_aicourse',
            'filearea' => banner::SECTION_AREA,
            'itemid' => (int) $foreign->id,
            'filepath' => '/',
            'filename' => 'foreign.jpg',
        ], 'not really a jpeg');

        $this->setUser($this->teacher);
        try {
            delete_banner_image::execute($this->course->id, (int) $foreign->id);
            $this->fail('A section from another course was accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_sectionnotincourse', $e->errorcode);
        }

        // The other course's file is still there.
        $this->assertCount(1, get_file_storage()->get_area_files(
            \context_course::instance($othercourse->id)->id,
            'format_aicourse',
            banner::SECTION_AREA,
            (int) $foreign->id,
            'itemid',
            false
        ));
    }

    /**
     * A student must not be able to remove a section banner.
     */
    public function test_a_student_cannot_delete_a_section_banner(): void {
        $section = $this->section(1);
        $this->store_image(banner::SECTION_AREA, (int) $section->id, 'section.jpg');

        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        delete_banner_image::execute($this->course->id, (int) $section->id);
    }

    /**
     * Each target's generation status is reported separately.
     *
     * Two generations a few seconds apart is exactly how a teacher works through a course's
     * sections. Sharing one status key would have the browser polling for one banner and being
     * told about another, and applying the wrong image to the wrong section.
     */
    public function test_each_target_has_its_own_generation_status(): void {
        $one = $this->section(1);
        $two = $this->section(2);

        generate_banner_image::set_status($this->course->id, 'running', '', (int) $one->id);
        generate_banner_image::set_status($this->course->id, 'failed', 'nope', (int) $two->id);
        generate_banner_image::set_status($this->course->id, 'queued', '');

        $this->setUser($this->teacher);

        $first = external_api::clean_returnvalue(
            get_banner_status::execute_returns(),
            get_banner_status::execute($this->course->id, (int) $one->id)
        );
        $second = external_api::clean_returnvalue(
            get_banner_status::execute_returns(),
            get_banner_status::execute($this->course->id, (int) $two->id)
        );
        $coursestatus = external_api::clean_returnvalue(
            get_banner_status::execute_returns(),
            get_banner_status::execute($this->course->id)
        );

        $this->assertSame('running', $first['status']);
        $this->assertSame('failed', $second['status']);
        $this->assertSame('nope', $second['message']);
        $this->assertSame('queued', $coursestatus['status']);
    }

    /**
     * A finished generation whose status lost its URL still reports the stored image.
     *
     * This fallback used to hand a course id to a method that takes a course record and reads
     * ->id off it, which was fatal on PHP 8. It only runs when a generation completes but its
     * status write comes back empty, which is why nobody hit it.
     */
    public function test_a_done_status_with_no_url_falls_back_to_the_stored_file(): void {
        $section = $this->section(1);
        $this->store_image(banner::SECTION_AREA, (int) $section->id, 'section.jpg');
        $this->store_image(banner::COURSE_AREA, banner::BANNER_ITEMID, 'course.jpg');

        generate_banner_image::set_status($this->course->id, 'done', '', (int) $section->id);
        generate_banner_image::set_status($this->course->id, 'done', '');

        $this->setUser($this->teacher);

        $forsection = external_api::clean_returnvalue(
            get_banner_status::execute_returns(),
            get_banner_status::execute($this->course->id, (int) $section->id)
        );
        $forcourse = external_api::clean_returnvalue(
            get_banner_status::execute_returns(),
            get_banner_status::execute($this->course->id)
        );

        $this->assertStringContainsString('section.jpg', $forsection['imageurl']);
        $this->assertStringContainsString('course.jpg', $forcourse['imageurl']);
    }

    /**
     * Asking for the status of another course's section is refused.
     */
    public function test_status_refuses_a_section_from_another_course(): void {
        $othercourse = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 2],
            ['createsections' => true]
        );
        $foreign = get_fast_modinfo($othercourse)->get_section_info(1);

        $this->setUser($this->teacher);
        $this->expectException(\moodle_exception::class);
        get_banner_status::execute($this->course->id, (int) $foreign->id);
    }
}
