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

namespace format_aicourse\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests for card images and card colours (2.5.0).
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\local\cardimage
 * @covers     \format_aicourse\observer
 * @covers     \backup_format_aicourse_plugin
 * @covers     \restore_format_aicourse_plugin
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_aicourse\local\cardimage::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\format_aicourse\observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_format_aicourse_plugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_format_aicourse_plugin::class)]
final class cardimage_test extends \advanced_testcase {
    /**
     * A real, tiny PNG.
     *
     * @param int $red Red channel, so two images differ.
     * @return string
     */
    public static function png(int $red = 200): string {
        $img = imagecreatetruecolor(32, 18);
        imagefill($img, 0, 0, imagecolorallocate($img, $red, 80, 40));
        ob_start();
        imagepng($img);
        return (string) ob_get_clean();
    }

    /**
     * A course with two sections and a page in each.
     *
     * @param string $shortname Course short name.
     * @return array [course, section1, section2, cm1, cm2]
     */
    private function fixture(string $shortname = 'CI'): array {
        $course = $this->getDataGenerator()->create_course(
            ['format' => 'aicourse', 'numsections' => 2, 'shortname' => $shortname],
            ['createsections' => true]
        );
        $cm1 = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1]);
        $cm2 = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 2]);
        $modinfo = get_fast_modinfo($course);
        return [$course, $modinfo->get_section_info(1), $modinfo->get_section_info(2), $cm1, $cm2];
    }

    /**
     * Store and read back for both kinds of card.
     */
    public function test_store_and_read_back_for_both_kinds_of_card(): void {
        $this->resetAfterTest();
        [$course, $s1, , $cm1] = $this->fixture();

        $surl = cardimage::store((int) $course->id, 'section', (int) $s1->id, self::png());
        $curl = cardimage::store((int) $course->id, 'cm', (int) $cm1->cmid, self::png(10));

        $this->assertStringContainsString('/sectioncardimage/' . $s1->id . '/', $surl);
        $this->assertStringContainsString('/cmcardimage/' . $cm1->cmid . '/', $curl);
        $this->assertSame($surl, cardimage::get_url((int) $course->id, 'section', (int) $s1->id));
        $this->assertSame(['url' => $curl, 'source' => 'card'], cardimage::resolve((int) $course->id, 'cm', (int) $cm1->cmid));
    }

    /**
     * Replacing an image leaves one file.
     */
    public function test_replacing_an_image_leaves_one_file(): void {
        $this->resetAfterTest();
        [$course, $s1] = $this->fixture();
        cardimage::store((int) $course->id, 'section', (int) $s1->id, self::png(1));
        cardimage::store((int) $course->id, 'section', (int) $s1->id, self::png(2));
        $files = get_file_storage()->get_area_files(
            \context_course::instance($course->id)->id,
            'format_aicourse',
            'sectioncardimage',
            (int) $s1->id,
            'id',
            false
        );
        $this->assertCount(1, $files);
    }

    /**
     * Section card falls back to its banner but not the course banner.
     */
    public function test_section_card_falls_back_to_its_banner_but_not_the_course_banner(): void {
        $this->resetAfterTest();
        [$course, $s1, $s2] = $this->fixture();
        $ctx = \context_course::instance($course->id);
        $fs = get_file_storage();
        $fs->create_file_from_string(
            [
                'contextid' => $ctx->id,
                'component' => 'format_aicourse',
                'filearea' => banner::SECTION_AREA,
                'itemid' => (int) $s1->id,
                'filepath' => '/',
                'filename' => 'b.png',
            ],
            self::png()
        );
        $fs->create_file_from_string(
            [
                'contextid' => $ctx->id,
                'component' => 'format_aicourse',
                'filearea' => banner::COURSE_AREA,
                'itemid' => 0,
                'filepath' => '/',
                'filename' => 'c.png',
            ],
            self::png()
        );
        cardimage::reset_cache();

        $this->assertSame('banner', cardimage::resolve((int) $course->id, 'section', (int) $s1->id)['source']);
        // The course banner would put one picture on every card: the placeholder is better.
        $this->assertSame(['url' => '', 'source' => ''], cardimage::resolve((int) $course->id, 'section', (int) $s2->id));
    }

    /**
     * Only real images are accepted.
     */
    public function test_only_real_images_are_accepted(): void {
        $this->resetAfterTest();
        $this->assertSame('png', cardimage::validate_bytes(self::png()));
        foreach (['', 'not an image', '<svg xmlns="http://www.w3.org/2000/svg"/>'] as $bad) {
            try {
                cardimage::validate_bytes($bad);
                $this->fail('Accepted ' . var_export($bad, true));
            } catch (\moodle_exception $e) {
                $this->assertSame('error_cardimageinvalid', $e->errorcode);
            }
        }
        try {
            cardimage::validate_bytes(self::png() . str_repeat('x', cardimage::MAX_BYTES));
            $this->fail('Accepted an oversized image');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_cardimagetoolarge', $e->errorcode);
        }
    }

    /**
     * Targets from another course are refused.
     */
    public function test_targets_from_another_course_are_refused(): void {
        $this->resetAfterTest();
        [$course, $s1] = $this->fixture('A');
        [, $other1, , $othercm] = $this->fixture('B');

        $this->assertSame((int) $s1->id, (int) cardimage::require_target($course, 'section', (int) $s1->id)->id);
        foreach (
            [['section', (int) $other1->id, 'error_sectionnotincourse'], ['cm', (int) $othercm->cmid, 'error_cmnotincourse'],
                ['section', (int) get_fast_modinfo($course)->get_section_info(0)->id, 'error_sectionnotincourse'],
                ['banana', 1, 'error_cardimagetype']] as [$type, $id, $code]
        ) {
            try {
                cardimage::require_target($course, $type, $id);
                $this->fail("$type $id accepted");
            } catch (\moodle_exception $e) {
                $this->assertSame($code, $e->errorcode);
            }
        }
    }

    /**
     * Colours are cleaned stored and cleared.
     */
    public function test_colours_are_cleaned_stored_and_cleared(): void {
        $this->resetAfterTest();
        [$course, $s1, , $cm1] = $this->fixture();

        $this->assertSame('#aabbcc', cardimage::clean_colour('ABC'));
        $this->assertSame('', cardimage::clean_colour('red; background:url(x)'));

        $this->assertSame('#1d4ed8', cardimage::set_colour((int) $course->id, 'section', (int) $s1->id, '#1D4ED8'));
        cardimage::set_colour((int) $course->id, 'cm', (int) $cm1->cmid, '#123456');
        $this->assertSame('#1d4ed8', cardimage::get_colour((int) $course->id, 'section', (int) $s1->id));
        $this->assertSame('#123456', cardimage::get_colour((int) $course->id, 'cm', (int) $cm1->cmid));

        cardimage::set_colour((int) $course->id, 'section', (int) $s1->id, '');
        $this->assertSame('', cardimage::get_colour((int) $course->id, 'section', (int) $s1->id));

        $this->expectException(\moodle_exception::class);
        cardimage::set_colour((int) $course->id, 'section', (int) $s1->id, 'url(javascript:1)');
    }

    /**
     * Deleting an activity removes its image colour and state.
     */
    public function test_deleting_an_activity_removes_its_image_colour_and_state(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, , , $cm1, $cm2] = $this->fixture();
        foreach ([$cm1, $cm2] as $cm) {
            cardimage::store((int) $course->id, 'cm', (int) $cm->cmid, self::png());
            cardimage::set_colour((int) $course->id, 'cm', (int) $cm->cmid, '#123456');
            cardimage::set_status((int) $course->id, 'cm', (int) $cm->cmid, 'done', 'x');
        }

        self::delete_cm($course, (int) $cm1->cmid);
        cardimage::reset_cache();

        $this->assertNull(cardimage::get_url((int) $course->id, 'cm', (int) $cm1->cmid));
        $this->assertFalse($DB->record_exists('format_aicourse_cardstyle', ['targettype' => 'cm', 'targetid' => $cm1->cmid]));
        $this->assertSame('idle', cardimage::get_status((int) $course->id, 'cm', (int) $cm1->cmid)['state']);
        // Only its own.
        $this->assertNotNull(cardimage::get_url((int) $course->id, 'cm', (int) $cm2->cmid));
        $this->assertSame('#123456', cardimage::get_colour((int) $course->id, 'cm', (int) $cm2->cmid));
    }

    /**
     * Deleting a section removes its card image.
     */
    public function test_deleting_a_section_removes_its_card_image(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $s1, $s2] = $this->fixture();
        cardimage::store((int) $course->id, 'section', (int) $s1->id, self::png());
        cardimage::store((int) $course->id, 'section', (int) $s2->id, self::png());

        course_delete_section($course, $s2, true);
        cardimage::reset_cache();

        $this->assertNull(cardimage::get_url((int) $course->id, 'section', (int) $s2->id));
        $this->assertNotNull(cardimage::get_url((int) $course->id, 'section', (int) $s1->id));
    }

    /**
     * Deleting a course removes its colours and state only.
     */
    public function test_deleting_a_course_removes_its_colours_and_state_only(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $s1] = $this->fixture('GONE');
        [$keep, $k1] = $this->fixture('KEEP');
        foreach ([[$course, $s1], [$keep, $k1]] as [$c, $s]) {
            cardimage::set_colour((int) $c->id, 'section', (int) $s->id, '#123456');
            cardimage::set_status((int) $c->id, 'section', (int) $s->id, 'done', 'x');
        }

        delete_course($course, false);
        cardimage::reset_cache();

        $this->assertSame(0, $DB->count_records('format_aicourse_cardstyle', ['courseid' => $course->id]));
        $this->assertSame('idle', cardimage::get_status((int) $course->id, 'section', (int) $s1->id)['state']);
        $this->assertSame('#123456', cardimage::get_colour((int) $keep->id, 'section', (int) $k1->id));
        $this->assertSame('done', cardimage::get_status((int) $keep->id, 'section', (int) $k1->id)['state']);
    }

    /**
     * A restored course keeps card images and colours on the right cards.
     */
    public function test_a_restored_course_keeps_card_images_and_colours_on_the_right_cards(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $s1, $s2, $cm1, $cm2] = $this->fixture('ORIG');
        $cid = (int) $course->id;
        cardimage::store($cid, 'section', (int) $s1->id, self::png(1));
        cardimage::store($cid, 'cm', (int) $cm2->cmid, self::png(2));
        cardimage::set_colour($cid, 'section', (int) $s2->id, '#0f766e');
        cardimage::set_colour($cid, 'cm', (int) $cm1->cmid, '#9333ea');

        $newid = $this->backup_and_restore($course, (int) $USER->id);
        cardimage::reset_cache();
        $new = get_course($newid);
        $mi = get_fast_modinfo($new);
        $ns1 = (int) $mi->get_section_info(1)->id;
        $ns2 = (int) $mi->get_section_info(2)->id;
        $ncm1 = (int) $mi->sections[1][0];
        $ncm2 = (int) $mi->sections[2][0];

        $this->assertNotNull(cardimage::get_url($newid, 'section', $ns1), 'section card image lost');
        $this->assertNull(cardimage::get_url($newid, 'section', $ns2));
        $this->assertNotNull(cardimage::get_url($newid, 'cm', $ncm2), 'activity card image lost');
        $this->assertNull(cardimage::get_url($newid, 'cm', $ncm1));
        $this->assertSame('#0f766e', cardimage::get_colour($newid, 'section', $ns2));
        $this->assertSame('#9333ea', cardimage::get_colour($newid, 'cm', $ncm1));
        $this->assertSame('', cardimage::get_colour($newid, 'cm', $ncm2));
        // The original is untouched.
        $this->assertSame('#9333ea', cardimage::get_colour($cid, 'cm', (int) $cm1->cmid));
    }

    /**
     * Duplicating an activity keeps its colour.
     */
    public function test_duplicating_an_activity_keeps_its_colour(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, , , $cm1] = $this->fixture();
        cardimage::set_colour((int) $course->id, 'cm', (int) $cm1->cmid, '#be185d');

        $newcm = self::duplicate_cm($course, (int) $cm1->cmid);
        cardimage::reset_cache();

        $this->assertSame('#be185d', cardimage::get_colour((int) $course->id, 'cm', (int) $newcm->id));
    }

    /**
     * Back up a course and restore it into a new one.
     *
     * @param \stdClass $course Course to copy.
     * @param int $userid User performing the operation.
     * @return int Id of the new course.
     */
    private function backup_and_restore(\stdClass $course, int $userid): int {
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid
        );
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $backupid = $bc->get_backupid();
        $bc->destroy();
        $dir = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($file, $dir);
        $newid = \restore_dbops::create_new_course($course->fullname . ' copy', $course->shortname . '_copy', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid,
            \backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
        return (int) $newid;
    }

    /**
     * Delete an activity through the API the running Moodle provides.
     *
     * Moodle 5.2 deprecated course_delete_module() in favour of cmactions::delete() (MDL-86856);
     * Moodle 4.4 to 5.1 only have the global function.
     *
     * @param \stdClass $course The course.
     * @param int $cmid The course module id.
     */
    private static function delete_cm(\stdClass $course, int $cmid): void {
        global $CFG;
        $actions = \core_courseformat\formatactions::cm($course);
        if (method_exists($actions, 'delete')) {
            $actions->delete($cmid);
            return;
        }
        require_once($CFG->dirroot . '/course/lib.php');
        course_delete_module($cmid);
    }

    /**
     * Duplicate an activity through the API the running Moodle provides.
     *
     * Moodle 5.2 deprecated duplicate_module() in favour of cmactions::duplicate() (MDL-86858).
     *
     * @param \stdClass $course The course.
     * @param int $cmid The course module id.
     * @return \cm_info The new activity.
     */
    private static function duplicate_cm(\stdClass $course, int $cmid): \cm_info {
        global $CFG;
        $actions = \core_courseformat\formatactions::cm($course);
        if (method_exists($actions, 'duplicate')) {
            return $actions->duplicate($cmid);
        }
        require_once($CFG->dirroot . '/course/lib.php');
        return duplicate_module($course, get_fast_modinfo($course)->get_cm($cmid));
    }
}
