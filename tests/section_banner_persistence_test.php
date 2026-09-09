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
 * Tests that a section banner survives saving section settings, and duplicating a course.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse;

use format_aicourse\local\banner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests that a section banner survives saving section settings, and duplicating a course.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse
 * @covers     \backup_format_aicourse_plugin
 * @covers     \restore_format_aicourse_plugin
 */
final class section_banner_persistence_test extends \advanced_testcase {
    /**
     * Store an image against a section.
     *
     * @param \stdClass $course The course.
     * @param int $sectionid course_sections.id.
     * @param string $filename Name of the stored file.
     * @return void
     */
    private function store_section_image(\stdClass $course, int $sectionid, string $filename): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance($course->id)->id,
            'component' => 'format_aicourse',
            'filearea' => banner::SECTION_AREA,
            'itemid' => $sectionid,
            'filepath' => '/',
            'filename' => $filename,
        ], 'not really a jpeg');
    }

    /**
     * Saving section settings without touching the banner field must not delete the banner.
     *
     * This is the trap ACF-FIX-2.0 fixed for the course banner: a save that carries no draft
     * item id, passed to file_save_draft_area_files(), wipes the stored file. Section settings
     * are updated by plenty of things that have never heard of this field -- the reactive
     * editor's rename, bulk tools, restore, web services -- so the guard matters more here, not
     * less.
     */
    public function test_updating_a_section_without_the_banner_field_keeps_the_image(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $section = get_fast_modinfo($course)->get_section_info(1);
        $this->store_section_image($course, (int) $section->id, 'section.jpg');

        // A rename, exactly as the reactive editor performs one: no banner field in sight.
        course_update_section($course, $section, (object) ['name' => 'Renamed by something else']);

        $this->assertNotNull(
            banner::get_section_banner_image_url((int) $course->id, (int) $section->id),
            'Saving unrelated section settings deleted the section banner'
        );
    }

    /**
     * A submitted draft item id of zero must not delete the image either.
     *
     * Zero is what an unsubmitted or absent filemanager sends, and passing it through would be
     * read as "the teacher emptied the field".
     */
    public function test_a_zero_draft_item_id_does_not_delete_the_image(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $section = get_fast_modinfo($course)->get_section_info(1);
        $this->store_section_image($course, (int) $section->id, 'section.jpg');

        course_get_format($course)->update_section_format_options([
            'id' => (int) $section->id,
            \format_aicourse\form\section_edit_form::ELEMENT => 0,
        ]);

        $this->assertNotNull(
            banner::get_section_banner_image_url((int) $course->id, (int) $section->id)
        );
    }

    /**
     * An image uploaded through the section settings form is stored against that section.
     *
     * Driving the file picker in a browser is not a reliable way to assert this -- the picker is
     * a YUI dialogue and the assertion ends up being about the widget rather than about the
     * save. This exercises the path that actually matters: a draft area with a file in it,
     * handed to the same method the form submission calls.
     */
    public function test_an_uploaded_image_is_saved_against_the_section(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info(1);
        $othersection = $modinfo->get_section_info(2);

        // A draft area holding one image, which is what the filemanager submits.
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'uploaded.png',
        ], 'not really a png');

        course_get_format($course)->update_section_format_options([
            'id' => (int) $section->id,
            \format_aicourse\form\section_edit_form::ELEMENT => $draftitemid,
        ]);

        $stored = banner::get_section_banner_image_url((int) $course->id, (int) $section->id);
        $this->assertNotNull($stored, 'The uploaded image was not saved against the section');
        $this->assertStringContainsString('uploaded.png', $stored);

        // And it went to this section only.
        $this->assertNull(
            banner::get_section_banner_image_url((int) $course->id, (int) $othersection->id)
        );
    }

    /**
     * Reopening the form must show the stored image rather than an empty field.
     *
     * This is not cosmetic. An empty filemanager submits an empty draft area, and saving the
     * form would then delete the image the teacher came to look at.
     */
    public function test_the_form_prefills_with_the_stored_image(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $section = get_fast_modinfo($course)->get_section_info(1);
        $this->store_section_image($course, (int) $section->id, 'stored.png');

        // What section_edit_form::definition() does to seed the field.
        $draftitemid = 0;
        file_prepare_draft_area(
            $draftitemid,
            \context_course::instance($course->id)->id,
            'format_aicourse',
            banner::SECTION_AREA,
            (int) $section->id,
            ['maxbytes' => 5 * 1024 * 1024, 'maxfiles' => 1, 'subdirs' => 0]
        );

        $draftfiles = get_file_storage()->get_area_files(
            \context_user::instance($GLOBALS['USER']->id)->id,
            'user',
            'draft',
            $draftitemid,
            'itemid',
            false
        );

        $this->assertCount(1, $draftfiles);
        $this->assertSame('stored.png', reset($draftfiles)->get_filename());
    }

    /**
     * Deleting a section must take its banner image with it.
     *
     * The file is in the COURSE context, so deleting the section does not remove it: the
     * context survives and nothing ever looks at that item id again. Left alone it accumulates
     * unreachable images and carries them into every backup.
     */
    public function test_deleting_a_section_removes_its_banner(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3],
            ['createsections' => true]
        );
        $modinfo = get_fast_modinfo($course);
        $doomed = $modinfo->get_section_info(2);
        $survivor = $modinfo->get_section_info(1);
        $doomedid = (int) $doomed->id;
        $this->store_section_image($course, $doomedid, 'doomed.jpg');
        $this->store_section_image($course, (int) $survivor->id, 'survivor.jpg');

        course_delete_section($course, $doomed, true, true);

        $contextid = \context_course::instance($course->id)->id;
        $this->assertCount(0, get_file_storage()->get_area_files(
            $contextid,
            'format_aicourse',
            banner::SECTION_AREA,
            $doomedid,
            'itemid',
            false
        ), 'The deleted section left its banner behind');

        // And it took only its own.
        $this->assertNotNull(
            banner::get_section_banner_image_url((int) $course->id, (int) $survivor->id)
        );
    }

    /**
     * A duplicated course must keep each section's banner, on the right section.
     *
     * Section banners are filed under course_sections.id, and a restored course has new ones.
     * Without the 'course_section' item id mapping in the restore plugin the files land under
     * the ORIGINAL ids: still in the file pool, attached to sections that do not exist in the
     * new course, and invisible. Two sections are used with different images so the test also
     * fails if the mapping exists but pairs them up wrongly.
     */
    public function test_a_restored_course_keeps_its_section_banners(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 3, 'shortname' => 'ORIGINAL'],
            ['createsections' => true]
        );
        $modinfo = get_fast_modinfo($course);
        $sectionone = $modinfo->get_section_info(1);
        $sectiontwo = $modinfo->get_section_info(2);
        $this->store_section_image($course, (int) $sectionone->id, 'first.jpg');
        $this->store_section_image($course, (int) $sectiontwo->id, 'second.jpg');

        $newcourseid = $this->backup_and_restore($course, (int) $USER->id);
        $newcourse = get_course($newcourseid);
        $newmodinfo = get_fast_modinfo($newcourse);

        $restoredone = banner::get_section_banner_image_url(
            $newcourseid,
            (int) $newmodinfo->get_section_info(1)->id
        );
        $restoredtwo = banner::get_section_banner_image_url(
            $newcourseid,
            (int) $newmodinfo->get_section_info(2)->id
        );

        $this->assertNotNull($restoredone, 'Section 1 lost its banner in the restore');
        $this->assertNotNull($restoredtwo, 'Section 2 lost its banner in the restore');
        $this->assertStringContainsString('first.jpg', $restoredone);
        $this->assertStringContainsString('second.jpg', $restoredtwo);

        // And the images did not swap places on the way through.
        $this->assertStringNotContainsString('second.jpg', $restoredone);
    }

    /**
     * Back up a course and restore it into a new one.
     *
     * @param \stdClass $course Course to copy.
     * @param int $userid User performing the operation.
     * @return int Id of the new course.
     */
    private function backup_and_restore(\stdClass $course, int $userid): int {
        global $CFG;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid
        );
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $backupid = $bc->get_backupid();
        $bc->destroy();

        $dir = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($file, $dir);

        $newcourseid = \restore_dbops::create_new_course(
            $course->fullname . ' copy',
            $course->shortname . '_copy',
            $course->category
        );
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid,
            \backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
