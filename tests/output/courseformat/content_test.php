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
 * Tests for the section card exported context.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\output\courseformat;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/format/aicourse/lib.php');

use completion_info;
use format_aicourse\local\icons;
use ReflectionMethod;
use stdClass;

/**
 * Tests for the section card exported context.
 *
 * The "Show activities on cards" course option is off by default, and while it is off the card
 * context must be exactly what it was before the option existed -- so the default case is asserted
 * as tightly as the enabled one. What a learner may see in the list is a visibility rule, not a
 * formatting preference, so it is asserted from the learner's own session.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\output\courseformat\content
 */
final class content_test extends \advanced_testcase {
    /**
     * Export the card context for one section of a course.
     *
     * export_section_card() is protected because nothing outside the renderer should build a card,
     * so the test reaches it the same way the renderer does rather than asserting on rendered HTML:
     * the context IS the contract the template consumes.
     *
     * @param stdClass $course Course record.
     * @param int $sectionnum Section number to export.
     * @return stdClass The exported card context.
     */
    protected function export_card(stdClass $course, int $sectionnum): stdClass {
        $format = course_get_format($course);
        $content = new content($format, true);

        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info($sectionnum);

        $method = new ReflectionMethod($content, 'export_section_card');
        $method->setAccessible(true);

        return $method->invoke(
            $content,
            $course,
            $section,
            icons::get_library(),
            new completion_info($course),
            false
        );
    }

    /**
     * Every activity name listed on the card.
     *
     * @param stdClass $card The exported card context.
     * @return string[] Activity names, in card order.
     */
    protected function listed_names(stdClass $card): array {
        return array_map(static fn($item) => $item->name, $card->activities);
    }

    /**
     * Create a course, enrol a student in it and switch to that student's session.
     *
     * @param array $options Extra course options, e.g. showactivitiesoncards.
     * @return stdClass The course record.
     */
    protected function course_as_student(array $options = []): stdClass {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course($options + [
            'format' => 'aicourse',
            'numsections' => 2,
            'enablecompletion' => 1,
        ]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        return $course;
    }

    /**
     * With the option left alone, a card carries no activity list at all.
     */
    public function test_activities_are_not_listed_by_default(): void {
        $this->resetAfterTest();

        $course = $this->course_as_student();
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Reading one',
            'section' => 1,
        ]);

        $card = $this->export_card($course, 1);

        $this->assertFalse($card->hasactivities, 'The option defaults to off.');
        $this->assertSame([], $card->activities);
        $this->assertNull($card->moreactivities);
    }

    /**
     * The default really is the stored default, not just an unset option.
     */
    public function test_the_course_option_defaults_to_zero(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'aicourse']);
        $options = course_get_format($course)->get_format_options();

        $this->assertArrayHasKey('showactivitiesoncards', $options);
        $this->assertEquals(0, $options['showactivitiesoncards']);
    }

    /**
     * With the option on, a visible activity appears in the exported context, with a link, a
     * completion state and an accessible name that names the section it belongs to.
     */
    public function test_a_visible_activity_appears_when_the_option_is_on(): void {
        $this->resetAfterTest();

        $course = $this->course_as_student(['showactivitiesoncards' => 1]);
        $module = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Reading one',
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $card = $this->export_card($course, 1);

        $this->assertTrue($card->hasactivities);
        $this->assertSame(['Reading one'], $this->listed_names($card));
        $this->assertNull($card->moreactivities);

        $item = $card->activities[0];
        $this->assertStringContainsString('/mod/page/view.php', $item->url);
        $this->assertStringContainsString((string) $module->cmid, $item->url);
        // Completion is tracked and untouched, so the marker is "not started" -- and the state is
        // in the accessible name too, so it is never carried by colour alone.
        $this->assertSame('aicourse-actstate-not_started', $item->stateclass);
        $this->assertStringContainsString('Reading one', $item->label);
        $this->assertStringContainsString('Section 1', $item->label);
    }

    /**
     * An activity hidden from students never appears in a student's card.
     */
    public function test_a_hidden_activity_never_appears(): void {
        $this->resetAfterTest();

        $course = $this->course_as_student(['showactivitiesoncards' => 1]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Visible reading',
            'section' => 1,
        ]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Hidden reading',
            'section' => 1,
            'visible' => 0,
        ]);

        $card = $this->export_card($course, 1);

        $this->assertSame(['Visible reading'], $this->listed_names($card));
        // ...and it is not hidden behind the overflow chip either.
        $this->assertNull($card->moreactivities);
    }

    /**
     * An activity that is available but deliberately kept off the course page never appears.
     *
     * This is the second half of the visibility rule: $cm->uservisible is true for a stealth
     * activity, so only activityinfo::cm_counts_as_content() excludes it. The card must agree with
     * the rest of the plugin, which does not draw it either.
     */
    public function test_an_activity_kept_off_the_course_page_never_appears(): void {
        $this->resetAfterTest();

        // Core forces visibleoncoursepage back to 1 unless the site allows stealth activities,
        // so without this the "stealth" module below would not actually be stealthy.
        set_config('allowstealth', 1);

        $course = $this->course_as_student(['showactivitiesoncards' => 1]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Visible reading',
            'section' => 1,
        ]);
        $stealth = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Stealth reading',
            'section' => 1,
        ]);
        set_coursemodule_visible($stealth->cmid, 1, 0);
        $this->assertFalse(
            get_fast_modinfo($course->id)->get_cm($stealth->cmid)->is_visible_on_course_page(),
            'The fixture is only meaningful if the module really is off the course page.'
        );

        $card = $this->export_card($course, 1);

        $this->assertSame(['Visible reading'], $this->listed_names($card));
        $this->assertNull($card->moreactivities);
    }

    /**
     * The list is capped and the remainder is offered as a "+N" overflow link.
     */
    public function test_the_list_is_capped_and_the_remainder_is_offered(): void {
        $this->resetAfterTest();

        $course = $this->course_as_student(['showactivitiesoncards' => 1]);
        for ($i = 1; $i <= 6; $i++) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'name' => 'Reading ' . $i,
                'section' => 1,
            ]);
        }

        $card = $this->export_card($course, 1);

        $this->assertCount(4, $card->activities);
        $this->assertSame(
            ['Reading 1', 'Reading 2', 'Reading 3', 'Reading 4'],
            $this->listed_names($card)
        );
        $this->assertNotNull($card->moreactivities);
        $this->assertSame(2, $card->moreactivities->remaining);
        // The overflow link names its section, so the "+N" on one card is distinguishable from
        // the "+N" on the next (WCAG 2.4.4).
        $this->assertStringContainsString('Section 1', $card->moreactivities->label);
    }

    /**
     * A hidden activity is not counted towards the overflow remainder either.
     */
    public function test_hidden_activities_do_not_inflate_the_overflow_count(): void {
        $this->resetAfterTest();

        $course = $this->course_as_student(['showactivitiesoncards' => 1]);
        for ($i = 1; $i <= 5; $i++) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'name' => 'Reading ' . $i,
                'section' => 1,
            ]);
        }
        for ($i = 1; $i <= 3; $i++) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'name' => 'Hidden ' . $i,
                'section' => 1,
                'visible' => 0,
            ]);
        }

        $card = $this->export_card($course, 1);

        $this->assertCount(4, $card->activities);
        $this->assertNotNull($card->moreactivities);
        $this->assertSame(1, $card->moreactivities->remaining);
        foreach ($this->listed_names($card) as $name) {
            $this->assertStringNotContainsString('Hidden', $name);
        }
    }
}
