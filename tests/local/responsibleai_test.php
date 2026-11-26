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

/**
 * Responsible AI (3.4.0): the EU AI Act features.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\local\responsibleai
 */
#[\PHPUnit\Framework\Attributes\CoversClass(responsibleai::class)]
final class responsibleai_test extends \advanced_testcase {
    /**
     * Encode a small image.
     *
     * @param string $type jpeg or png.
     * @return string
     */
    private static function image(string $type): string {
        $img = imagecreatetruecolor(64, 32);
        imagefill($img, 0, 0, imagecolorallocate($img, 15, 108, 191));
        ob_start();
        $type === 'png' ? imagepng($img) : imagejpeg($img, null, 85);
        return ob_get_clean();
    }

    /**
     * AI images carry the IPTC "trainedAlgorithmicMedia" marking and still decode (Article 50(2)).
     */
    public function test_mark_image(): void {
        foreach (['jpeg', 'png'] as $type) {
            $bytes = self::image($type);
            $this->assertFalse(responsibleai::is_marked($bytes));
            $marked = responsibleai::mark_image($bytes, 'ownkey');
            $this->assertTrue(responsibleai::is_marked($marked), $type);
            $this->assertStringContainsString(responsibleai::SOURCE_TYPE, $marked);
            $this->assertStringContainsString('ownkey', $marked);
            $info = getimagesizefromstring($marked);
            $this->assertSame([64, 32], [$info[0], $info[1]], $type . ' still decodes');
            $this->assertNotFalse(imagecreatefromstring($marked), $type . ' still opens');
        }
        // PNG chunk CRC is valid: the XMP chunk is well formed.
        $png = responsibleai::mark_image(self::image('png'));
        $pos = strpos($png, 'iTXt') - 4;
        $len = unpack('N', substr($png, $pos, 4))[1];
        $crc = unpack('N', substr($png, $pos + 8 + $len, 4))[1];
        $this->assertSame(crc32(substr($png, $pos + 4, 4 + $len)), $crc);
        // Unknown bytes pass through untouched.
        $this->assertSame('GIF89a', responsibleai::mark_image('GIF89a'));
    }

    /**
     * The pause stops AI everywhere and hides the tutor (supports Article 26(5)).
     */
    public function test_pause(): void {
        $this->resetAfterTest();
        $this->assertFalse(responsibleai::paused());
        $this->assertTrue(permissions::is_tutor_enabled());
        responsibleai::require_not_paused();

        set_config('aipaused', 1, 'format_aicourse');
        $this->assertTrue(responsibleai::paused());
        $this->assertFalse(permissions::is_tutor_enabled());
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('responsibleai_pausederror', 'format_aicourse'));
        responsibleai::require_not_paused();
    }

    /**
     * Paused AI refuses card image generation before anything is queued or sent.
     */
    public function test_pause_blocks_image_generation(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['format' => 'aicourse', 'numsections' => 1]);
        $this->setAdminUser();
        set_config('siteid', 'https://example.com', 'format_aicourse');
        set_config('apikey', 'key', 'format_aicourse');
        set_config('aipaused', 1, 'format_aicourse');
        $section = get_fast_modinfo($course)->get_section_info(1);
        try {
            \format_aicourse\external\generate_card_image::execute((int) $course->id, 'section', (int) $section->id, '');
            $this->fail('Expected the pause to refuse generation');
        } catch (\moodle_exception $e) {
            $this->assertSame('responsibleai_pausederror', $e->errorcode);
        }
        $this->assertSame(0, \core\task\manager::get_adhoc_tasks('\format_aicourse\task\generate_card_image') ? 1 : 0);
    }

    /**
     * The learner notice is on by default and can be turned off (Article 50(1)).
     */
    public function test_notice_setting(): void {
        $this->resetAfterTest();
        $this->assertTrue(responsibleai::show_notice());
        set_config('ainotice', 0, 'format_aicourse');
        $this->assertFalse(responsibleai::show_notice());
        $this->assertSame(['aipaused' => false, 'ainotice' => false], responsibleai::template_data());
    }

    /**
     * The settings page status names where each service runs, as MDS badges.
     */
    public function test_byok_status_html(): void {
        $this->resetAfterTest();
        $html = responsibleai::byok_status_html();
        $this->assertStringContainsString('mds-badge', $html);
        $this->assertStringContainsString(get_string('byok_source_credits', 'format_aicourse'), $html);
        $this->assertStringContainsString(get_string('byok_service_text', 'format_aicourse'), $html);
    }
}
