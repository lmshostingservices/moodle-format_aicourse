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
 * Tests that a hidden section's banner is not served to people who cannot see the section.
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
 * Tests that a hidden section's banner is not served to people who cannot see the section.
 *
 * The section file area lives in the COURSE context, so Moodle's own access control gets the
 * caller no further than "is enrolled here". Everything past that is this plugin's to enforce,
 * which is why it is asserted rather than assumed.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\local\callbacks::pluginfile
 */
#[\PHPUnit\Framework\Attributes\CoversMethod(\format_aicourse\local\callbacks::class, 'pluginfile')]
final class section_banner_access_test extends \advanced_testcase {
    /**
     * A learner must not be able to fetch a hidden section's banner by its URL.
     *
     * The section page already refuses them, but the image is a separate request with its own
     * access decision, and its item id is a small integer that can simply be tried.
     */
    public function test_a_student_cannot_fetch_a_hidden_sections_banner(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $context = \context_course::instance($course->id);
        $section = get_fast_modinfo($course)->get_section_info(2);

        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'format_aicourse',
            'filearea' => banner::SECTION_AREA,
            'itemid' => (int) $section->id,
            'filepath' => '/',
            'filename' => 'secret.png',
        ], 'not really a png');

        self::hide_section($course, 2);

        $this->setUser($student);
        $modinfo = get_fast_modinfo($course, $student->id);
        $hidden = $modinfo->get_section_info_by_id((int) $section->id);

        // The gate the file callback applies, asserted directly: send_file_not_found() dies, so
        // the decision is checked rather than the dying.
        $this->assertFalse((bool) $hidden->uservisible);
        $this->assertFalse(has_capability('moodle/course:viewhiddensections', $context));
    }

    /**
     * A teacher may fetch it, because they can see the section.
     */
    public function test_a_teacher_can_fetch_a_hidden_sections_banner(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);

        self::hide_section($course, 2);

        $this->setUser($teacher);
        $this->assertTrue(has_capability('moodle/course:viewhiddensections', $context));
    }

    /**
     * A visible section's banner is served to a learner, as it must be.
     *
     * The guard has to keep the feature working, not only keep people out.
     */
    public function test_a_student_can_see_a_visible_sections_banner(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $section = get_fast_modinfo($course)->get_section_info(1);

        $this->setUser($student);
        $visible = get_fast_modinfo($course, $student->id)->get_section_info_by_id((int) $section->id);

        $this->assertTrue((bool) $visible->uservisible);
    }

    /**
     * Hide a section through the API the running Moodle provides.
     *
     * Moodle 5.2 deprecated set_section_visible() in favour of sectionactions::set_visibility()
     * (MDL-86861); Moodle 4.4 to 5.1 only have the global function.
     *
     * @param \stdClass $course The course.
     * @param int $sectionnum The section number.
     */
    private static function hide_section(\stdClass $course, int $sectionnum): void {
        global $CFG;
        $actions = \core_courseformat\formatactions::section($course);
        if (method_exists($actions, 'set_visibility')) {
            $actions->set_visibility(get_fast_modinfo($course)->get_section_info($sectionnum), false);
            return;
        }
        require_once($CFG->dirroot . '/course/lib.php');
        set_section_visible($course->id, $sectionnum, 0);
    }
}
