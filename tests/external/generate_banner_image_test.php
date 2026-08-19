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
 * Tests for the format_aicourse_generate_banner_image external function.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/format/aicourse/tests/external/external_testcase.php');

/**
 * Tests for the format_aicourse_generate_banner_image external function.
 *
 * The success path ends in a paid HTTP call to lms-labs.com and is therefore not exercised here;
 * every guard that runs before the network is.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\external\generate_banner_image
 */
final class generate_banner_image_test extends external_testcase {
    /**
     * A student cannot spend the course's image credits.
     */
    public function test_execute_requires_capability(): void {
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        generate_banner_image::execute($this->course->id);
    }

    /**
     * A site without credentials says so rather than making a doomed HTTP call.
     */
    public function test_execute_requires_configuration(): void {
        set_config('siteid', '', 'format_aicourse');
        set_config('apikey', '', 'format_aicourse');
        $this->setUser($this->teacher);

        $this->assert_throws_errorcode('aiassistant_notconfigured', function (): void {
            generate_banner_image::execute($this->course->id);
        });
    }

    /**
     * The paid remote call stays rate limited: the fourth generation in the window is refused.
     */
    public function test_execute_is_rate_limited(): void {
        $this->setUser($this->teacher);
        // No credentials, so every allowed call fails at the configuration check instead of
        // reaching the network; the throttle runs before that check.
        set_config('siteid', '', 'format_aicourse');

        for ($i = 0; $i < throttle::BANNER_MAX; $i++) {
            try {
                generate_banner_image::execute($this->course->id);
                $this->fail('Expected the configuration check to reject this call.');
            } catch (\moodle_exception $e) {
                $this->assertSame('aiassistant_notconfigured', $e->errorcode);
            }
        }

        $this->assert_throws_errorcode('error_toomanyrequests', function (): void {
            generate_banner_image::execute($this->course->id);
        });
    }

    /**
     * A courseid that is not an integer is rejected before any code runs.
     */
    public function test_execute_rejects_invalid_parameters(): void {
        $this->setUser($this->teacher);

        $this->assert_call_fails('invalidparameter', 'format_aicourse_generate_banner_image', [
            'courseid' => 'not-a-number',
        ]);
    }
}
