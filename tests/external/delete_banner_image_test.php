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
 * Tests for the format_aicourse_delete_banner_image external function.
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
 * Tests for the format_aicourse_delete_banner_image external function.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\external\delete_banner_image
 */
final class delete_banner_image_test extends external_testcase {
    /**
     * Put one file in the course's banner file area.
     *
     * @return void
     */
    protected function create_banner_file(): void {
        get_file_storage()->create_file_from_string([
            'component' => 'format_aicourse',
            'filearea' => 'bannerimage',
            'itemid' => banner::BANNER_ITEMID,
            'contextid' => $this->context->id,
            'filepath' => '/',
            'filename' => 'ai_banner_test.jpg',
        ], 'not really a jpeg');
    }

    /**
     * An editing teacher can remove the banner.
     */
    public function test_execute_removes_the_banner(): void {
        $this->create_banner_file();
        $fs = get_file_storage();
        $this->assertNotEmpty($fs->get_area_files(
            $this->context->id,
            'format_aicourse',
            'bannerimage',
            banner::BANNER_ITEMID,
            'itemid',
            false
        ));

        $this->setUser($this->teacher);
        $result = delete_banner_image::execute($this->course->id);
        $result = external_api::clean_returnvalue(delete_banner_image::execute_returns(), $result);

        $this->assertTrue($result['status']);
        $this->assertEmpty($fs->get_area_files(
            $this->context->id,
            'format_aicourse',
            'bannerimage',
            banner::BANNER_ITEMID,
            'itemid',
            false
        ));
    }

    /**
     * A student cannot remove the banner.
     */
    public function test_execute_requires_capability(): void {
        $this->create_banner_file();
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        delete_banner_image::execute($this->course->id);
    }

    /**
     * A courseid that is not an integer is rejected before any code runs.
     */
    public function test_execute_rejects_invalid_parameters(): void {
        $this->setUser($this->teacher);

        $this->assert_call_fails('invalidparameter', 'format_aicourse_delete_banner_image', [
            'courseid' => 'not-a-number',
        ]);
    }
}
