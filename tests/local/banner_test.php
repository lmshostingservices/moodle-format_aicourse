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
 * Tests for banner resolution: section image, then course image, then course overview image.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Tests for banner resolution: section image, then course image, then course overview image.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\local\banner
 */
final class banner_test extends \advanced_testcase {
    /** @var \stdClass Course under test. */
    private $course;

    /** @var \section_info Section 1 of that course. */
    private $section;

    /** @var \section_info Section 2, used to prove the areas are per-section. */
    private $othersection;

    /**
     * Build a course with sections to hang images off.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $modinfo = get_fast_modinfo($this->course);
        $this->section = $modinfo->get_section_info(1);
        $this->othersection = $modinfo->get_section_info(2);
    }

    /**
     * Store a one-pixel image in a banner file area.
     *
     * @param string $filearea The file area to write into.
     * @param int $itemid The item id to file it under.
     * @param string $filename Name of the stored file.
     * @return void
     */
    private function store_image(string $filearea, int $itemid, string $filename): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance($this->course->id)->id,
            'component' => 'format_aicourse',
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
        ], 'not really a png, and nothing here decodes it');
    }

    /**
     * With nothing stored anywhere, there is no banner and no pretence of one.
     */
    public function test_no_images_anywhere_resolves_to_nothing(): void {
        $resolved = banner::resolve($this->course, (int) $this->section->id);

        $this->assertNull($resolved['url']);
        $this->assertSame('', $resolved['source']);
    }

    /**
     * A section with no image of its own inherits the course banner.
     *
     * This is the behaviour the whole feature is defined by, so it is asserted on the source and
     * not only on the URL: a section resolving to the course banner must say so, because that is
     * what suppresses the "remove this section's image" button.
     */
    public function test_a_section_without_its_own_image_inherits_the_course_banner(): void {
        $this->store_image(banner::COURSE_AREA, banner::BANNER_ITEMID, 'course.png');

        $resolved = banner::resolve($this->course, (int) $this->section->id);

        $this->assertSame('course', $resolved['source']);
        $this->assertStringContainsString('course.png', $resolved['url']);
    }

    /**
     * A section's own image wins over the course banner.
     */
    public function test_a_section_image_takes_priority_over_the_course_banner(): void {
        $this->store_image(banner::COURSE_AREA, banner::BANNER_ITEMID, 'course.png');
        $this->store_image(banner::SECTION_AREA, (int) $this->section->id, 'section.png');

        $resolved = banner::resolve($this->course, (int) $this->section->id);

        $this->assertSame('section', $resolved['source']);
        $this->assertStringContainsString('section.png', $resolved['url']);
        $this->assertStringContainsString(banner::SECTION_AREA, $resolved['url']);
    }

    /**
     * One section's image must not leak into another section.
     *
     * The section area is keyed by item id, and an item id is easy to get wrong. If this ever
     * fails, every section in every course is showing the same picture.
     */
    public function test_a_section_image_does_not_apply_to_other_sections(): void {
        $this->store_image(banner::COURSE_AREA, banner::BANNER_ITEMID, 'course.png');
        $this->store_image(banner::SECTION_AREA, (int) $this->section->id, 'section.png');

        $other = banner::resolve($this->course, (int) $this->othersection->id);

        $this->assertSame('course', $other['source']);
        $this->assertStringContainsString('course.png', $other['url']);
    }

    /**
     * The course home page never picks up a section's image.
     *
     * Passing no section is how the course hero asks, and it must not be answered with whatever
     * section happens to sort first.
     */
    public function test_the_course_page_ignores_section_images(): void {
        $this->store_image(banner::SECTION_AREA, (int) $this->section->id, 'section.png');

        $resolved = banner::resolve($this->course, null);

        $this->assertNull($resolved['url']);
        $this->assertSame('', $resolved['source']);
    }

    /**
     * With no banner of either kind, the course overview image is still the last resort.
     */
    public function test_the_course_overview_image_remains_the_final_fallback(): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance($this->course->id)->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'overview.png',
        ], 'not really a png');

        $resolved = banner::resolve($this->course, (int) $this->section->id);

        $this->assertSame('overview', $resolved['source']);
        $this->assertStringContainsString('overview.png', $resolved['url']);
    }

    /**
     * target() must send the course banner and a section banner to different places.
     */
    public function test_target_separates_the_course_and_section_areas(): void {
        $this->assertSame([banner::COURSE_AREA, banner::BANNER_ITEMID], banner::target(0));
        $this->assertSame([banner::SECTION_AREA, 42], banner::target(42));
    }

    /**
     * A section id from a different course must be refused.
     *
     * The external functions check the capability against the course they were given, so without
     * this a teacher of course A could name a section of course B and have the plugin write into
     * -- or delete from -- a course they have no rights over.
     */
    public function test_a_section_from_another_course_is_refused(): void {
        $othercourse = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 2],
            ['createsections' => true]
        );
        $foreignsection = get_fast_modinfo($othercourse)->get_section_info(1);

        $this->expectException(\moodle_exception::class);
        banner::require_section_in_course($this->course, (int) $foreignsection->id);
    }

    /**
     * A section of this course is accepted and returned.
     */
    public function test_a_section_of_this_course_is_accepted(): void {
        $result = banner::require_section_in_course($this->course, (int) $this->section->id);

        $this->assertNotNull($result);
        $this->assertSame((int) $this->section->id, (int) $result->id);
    }

    /**
     * Section id 0 means "the course banner" and is not an error.
     */
    public function test_zero_means_the_course_itself(): void {
        $this->assertNull(banner::require_section_in_course($this->course, 0));
    }
}
