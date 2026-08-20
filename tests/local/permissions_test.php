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
 * Tests for the permissions helper.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\local;

/**
 * Tests for format_aicourse\local\permissions.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\local\permissions
 */
final class permissions_test extends \advanced_testcase {
    /**
     * A teacher is a grader, a student is not.
     *
     * @return void
     */
    public function test_is_grader_for_real_roles(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'aicourse']);
        $context = \context_course::instance($course->id);

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $this->assertTrue(permissions::is_grader($context));

        $this->setUser($student);
        $this->assertFalse(permissions::is_grader($context));
    }

    /**
     * A teacher who switches role to Student must stop counting as a grader.
     *
     * ACF-FIX-2.1.22 regression guard. is_grader() used to decide on role archetypes read via
     * get_user_roles(), on the stated but incorrect assumption that it honours role switching.
     * It does not: switching is applied through $USER->access['rsw'] and leaves
     * {role_assignments} untouched. A teacher previewing as a student therefore stayed a
     * "grader" while losing moodle/course:update, and format.php renders the hero banner only
     * when (!$isgrader || $canedit) — so the hero vanished in exactly the view whose purpose is
     * to show what a student sees.
     *
     * @return void
     */
    public function test_is_grader_respects_role_switch(): void {
        global $USER;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'aicourse']);
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($teacher);
        $this->assertTrue(
            permissions::is_grader($context),
            'An editing teacher should be a grader before switching role.'
        );

        // Switch to the student role, exactly as "Switch role to..." does.
        $studentrole = $GLOBALS['DB']->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        role_switch($studentrole->id, $context);

        $this->assertTrue(
            is_role_switched($course->id),
            'The switch should be active for the rest of this test.'
        );
        $this->assertFalse(
            has_capability('moodle/course:update', $context),
            'Switching to Student must remove the editing capability; if this fails the test '
            . 'below proves nothing.'
        );
        $this->assertFalse(
            permissions::is_grader($context),
            'While switched to Student the user must not be treated as a grader, or the hero '
            . 'banner is suppressed in student view.'
        );

        // Switching back restores the teacher's own role.
        role_switch(0, $context);
        $this->assertFalse(is_role_switched($course->id));
        $this->assertTrue(
            permissions::is_grader($context),
            'Returning to the normal role must restore grader status.'
        );

        unset($USER->access['rsw']);
    }
}
