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
 * Tests for the card image and card colour external functions (2.5.0).
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\external;

use format_aicourse\local\cardimage;
use format_aicourse\local\cardimage_test;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/format/aicourse/tests/external/external_testcase.php');
require_once($CFG->dirroot . '/course/format/aicourse/tests/local/cardimage_test.php');

/**
 * Tests for the card image and card colour external functions.
 *
 * The AI path is exercised end to end with core's curl mock, so no request leaves the test and
 * no credits are spent.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\external\upload_card_image
 * @covers     \format_aicourse\external\delete_card_image
 * @covers     \format_aicourse\external\set_card_colour
 * @covers     \format_aicourse\external\generate_card_image
 * @covers     \format_aicourse\external\get_card_image_status
 * @covers     \format_aicourse\external\generate_all_card_images
 * @covers     \format_aicourse\task\generate_card_image
 */
final class card_image_test extends external_testcase {
    /** @var \stdClass A page in section 1. */
    private $page;

    /** @var \section_info Section 1. */
    private $section;

    /**
     * Add an activity to the shared fixture.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->page = $this->getDataGenerator()->create_module(
            'page',
            ['course' => $this->course->id, 'section' => 1,
                'name' => 'Recognition that works']
        );
        $this->section = get_fast_modinfo($this->course)->get_section_info(1);
    }

    /**
     * Upload arguments for a target.
     *
     * @param string $type section or cm.
     * @param int $id Target id.
     * @return array
     */
    private function upload_args(string $type, int $id): array {
        return ['courseid' => $this->course->id, 'targettype' => $type, 'targetid' => $id,
            'imagedata' => self::b64(cardimage_test::png())];
    }

    /**
     * Base64 in the layout PARAM_BASE64 requires: 64-character lines joined by newlines.
     *
     * @param string $bytes Raw data.
     * @return string
     */
    private static function b64(string $bytes): string {
        return implode("\n", str_split(base64_encode($bytes), 64));
    }

    /**
     * A teacher's upload is stored against the card and its URL returned.
     */
    public function test_teacher_can_upload(): void {
        $this->setUser($this->teacher);
        $result = $this->call_function('format_aicourse_upload_card_image', $this->upload_args('cm', (int) $this->page->cmid));
        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        $this->assertStringContainsString('/cmcardimage/' . $this->page->cmid . '/', $result['data']['imageurl']);
    }

    /**
     * Learners cannot change cards.
     */
    public function test_student_cannot_upload_colour_or_generate(): void {
        $this->setUser($this->student);
        $this->assert_call_fails(
            'nopermissions',
            'format_aicourse_upload_card_image',
            $this->upload_args('section', (int) $this->section->id)
        );
        $this->assert_call_fails(
            'nopermissions',
            'format_aicourse_set_card_colour',
            ['courseid' => $this->course->id, 'targettype' => 'section', 'targetid' => $this->section->id, 'colour' => '#123456']
        );
        $this->assert_call_fails(
            'nopermissions',
            'format_aicourse_generate_card_image',
            ['courseid' => $this->course->id, 'targettype' => 'section', 'targetid' => $this->section->id]
        );
    }

    /**
     * A teacher of this course cannot write into another course's card.
     */
    public function test_another_courses_target_is_refused(): void {
        $other = $this->getDataGenerator()->create_course(['format' => 'aicourse', 'numsections' => 1], ['createsections' => true]);
        $othercm = $this->getDataGenerator()->create_module('page', ['course' => $other->id, 'section' => 1]);
        $this->setUser($this->teacher);
        $this->assert_call_fails(
            'error_cmnotincourse',
            'format_aicourse_upload_card_image',
            $this->upload_args('cm', (int) $othercm->cmid)
        );
        $this->assert_call_fails(
            'error_cmnotincourse',
            'format_aicourse_delete_card_image',
            ['courseid' => $this->course->id, 'targettype' => 'cm', 'targetid' => $othercm->cmid]
        );
    }

    /**
     * Bytes that are not an image are refused, however they are labelled.
     */
    public function test_upload_rejects_non_images(): void {
        $this->setUser($this->teacher);
        $args = $this->upload_args('cm', (int) $this->page->cmid);
        $args['imagedata'] = self::b64('<?php echo 1; ?> and more than sixty-four bytes of text, so it spans two lines');
        $this->assert_call_fails('error_cardimageinvalid', 'format_aicourse_upload_card_image', $args);
        // Not base64 at all, and base64 in one long line: both refused by parameter validation.
        $args['imagedata'] = '%%%not base64%%%';
        $this->assert_call_fails('invalidparameter', 'format_aicourse_upload_card_image', $args);
        $args['imagedata'] = base64_encode(cardimage_test::png());
        $this->assert_call_fails('invalidparameter', 'format_aicourse_upload_card_image', $args);
    }

    /**
     * Removing a section card's own image reports the banner it falls back to.
     */
    public function test_delete_reports_what_the_card_shows_now(): void {
        $this->setUser($this->teacher);
        $this->call_function('format_aicourse_upload_card_image', $this->upload_args('section', (int) $this->section->id));
        get_file_storage()->create_file_from_string(['contextid' => $this->context->id, 'component' => 'format_aicourse',
            'filearea' => 'sectionbannerimage', 'itemid' => (int) $this->section->id, 'filepath' => '/', 'filename' => 'b.png'],
            cardimage_test::png());

        $result = $this->call_function(
            'format_aicourse_delete_card_image',
            ['courseid' => $this->course->id, 'targettype' => 'section', 'targetid' => $this->section->id]
        );
        $this->assertFalse($result['error']);
        $this->assertSame('banner', $result['data']['source']);
        $this->assertStringContainsString('/sectionbannerimage/', $result['data']['imageurl']);
    }

    /**
     * Colours are validated on the way in.
     */
    public function test_set_colour(): void {
        $this->setUser($this->teacher);
        $args = ['courseid' => $this->course->id, 'targettype' => 'cm', 'targetid' => $this->page->cmid, 'colour' => '#ABCDEF'];
        $this->assertSame('#abcdef', $this->call_function('format_aicourse_set_card_colour', $args)['data']['colour']);
        $args['colour'] = 'red;}body{display:none';
        $this->assert_call_fails('error_cardcolour', 'format_aicourse_set_card_colour', $args);
        $args['colour'] = '';
        $this->assertSame('', $this->call_function('format_aicourse_set_card_colour', $args)['data']['colour']);
    }

    /**
     * Generation refuses to queue on a site with no credentials.
     */
    public function test_generate_requires_configuration(): void {
        $this->setUser($this->teacher);
        $this->assert_throws_errorcode('aiassistant_notconfigured', function (): void {
            generate_card_image::execute($this->course->id, 'cm', (int) $this->page->cmid, '');
        });
    }

    /**
     * Generation queues one task with the teacher's prompt, capped, and reports queued.
     */
    public function test_generate_queues_a_task(): void {
        $this->set_fake_credentials();
        $this->setUser($this->teacher);
        $result = generate_card_image::execute($this->course->id, 'cm', (int) $this->page->cmid, str_repeat('a', 900));
        $this->assertSame('queued', $result['status']);

        $tasks = \core\task\manager::get_adhoc_tasks('\\format_aicourse\\task\\generate_card_image');
        $this->assertCount(1, $tasks);
        $data = reset($tasks)->get_custom_data();
        $this->assertSame('cm', $data->targettype);
        $this->assertSame((int) $this->page->cmid, $data->targetid);
        $this->assertSame(generate_card_image::PROMPT_MAX, \core_text::strlen($data->prompt));
        $this->assertSame('queued', cardimage::get_status((int) $this->course->id, 'cm', (int) $this->page->cmid)['state']);
    }

    /**
     * Card generation shares the banner's rate limit rather than adding a second allowance.
     */
    public function test_generate_shares_the_banner_throttle(): void {
        $this->set_fake_credentials();
        $this->setUser($this->teacher);
        for ($i = 0; $i < throttle::BANNER_MAX; $i++) {
            generate_banner_image::execute($this->course->id);
        }
        $this->assert_throws_errorcode('error_toomanyrequests', function (): void {
            generate_card_image::execute($this->course->id, 'cm', (int) $this->page->cmid, '');
        });
    }

    /**
     * The request tells the service what the card is, its shape and the course's style.
     */
    public function test_payload_describes_the_card(): void {
        $format = course_get_format($this->course);
        $format->update_course_format_options(['id' => $this->course->id, 'cardimagestyle' => 'illustration']);
        $cm = get_fast_modinfo($this->course)->get_cm($this->page->cmid);

        $payload = generate_card_image::build_payload(get_course($this->course->id), 'cm', $cm, 'warm light');
        $this->assertSame('card', $payload['imageKind']);
        $this->assertSame('16:9', $payload['aspectRatio']);
        $this->assertSame('illustration', $payload['imageStyle']);
        $this->assertSame('Recognition that works', $payload['activityName']);
        $this->assertSame('page', $payload['activityType']);
        $this->assertSame('warm light', $payload['extraDetail']);
        $this->assertStringStartsWith('Subject: Recognition that works.', $payload['prompt']);
        $this->assertStringEndsWith("Teacher's direction: warm light.", $payload['prompt']);
        $this->assertSame(\format_aicourse\local\cardprompt::VERSION, $payload['promptVersion']);
        $this->assertNotEmpty($payload['negativePrompt']);

        $payload = generate_card_image::build_payload(get_course($this->course->id), 'section', $this->section, '');
        $this->assertSame((int) $this->section->id, $payload['sectionId']);
        $this->assertArrayNotHasKey('extraDetail', $payload);
    }

    /**
     * The task stores what the service returns and the status poll reports it.
     */
    public function test_task_stores_the_image_and_status_reports_done(): void {
        $this->set_fake_credentials();
        $this->setUser($this->teacher);
        generate_card_image::execute($this->course->id, 'section', (int) $this->section->id, '');

        \curl::mock_response(json_encode(['success' => true, 'imageBase64' => base64_encode(cardimage_test::png())]));
        $this->expectOutputRegex('/.*/');
        $this->runAdhocTasks('\\format_aicourse\\task\\generate_card_image');

        $this->setUser($this->teacher);
        $status = get_card_image_status::execute($this->course->id, 'section', (int) $this->section->id);
        $this->assertSame('done', $status['status']);
        $this->assertStringContainsString('/sectioncardimage/' . $this->section->id . '/ai_card_', $status['imageurl']);
    }

    /**
     * A service failure is recorded, not retried, and reported to the teacher.
     */
    public function test_task_records_a_failure(): void {
        $this->set_fake_credentials();
        $this->setUser($this->teacher);
        generate_card_image::execute($this->course->id, 'cm', (int) $this->page->cmid, '');
        \curl::mock_response(json_encode(['success' => false, 'error' => 'Out of credits']));
        $this->expectOutputRegex('/.*/');
        $this->runAdhocTasks('\\format_aicourse\\task\\generate_card_image');

        $status = get_card_image_status::execute($this->course->id, 'cm', (int) $this->page->cmid);
        $this->assertSame('failed', $status['status']);
        $this->assertStringContainsString('Out of credits', $status['message']);
        $this->assertNull(cardimage::get_url((int) $this->course->id, 'cm', (int) $this->page->cmid));
    }

    /**
     * A dry run counts cards and credits and queues nothing; the real run queues and is limited.
     */
    public function test_generate_all(): void {
        $this->set_fake_credentials();
        $this->setUser($this->teacher);
        cardimage::store((int) $this->course->id, 'section', (int) $this->section->id, cardimage_test::png());

        $dry = generate_all_card_images::execute($this->course->id, 'all', true, true);
        // Sections 2 and 3 (1 has an image) and the one page.
        $this->assertSame(3, $dry['count']);
        $this->assertSame(15, $dry['credits']);
        $this->assertFalse($dry['queued']);
        $this->assertCount(0, \core\task\manager::get_adhoc_tasks('\\format_aicourse\\task\\generate_card_image'));

        $this->assertSame(1, generate_all_card_images::execute($this->course->id, 'activities', true, true)['count']);
        $this->assertSame(3, generate_all_card_images::execute($this->course->id, 'sections', false, true)['count']);

        $real = generate_all_card_images::execute($this->course->id, 'all', true, false);
        $this->assertTrue($real['queued']);
        $this->assertCount(3, \core\task\manager::get_adhoc_tasks('\\format_aicourse\\task\\generate_card_image'));

        // A second batch straight away is refused, so a double click cannot spend twice.
        $this->assert_throws_errorcode('error_toomanyrequests', function (): void {
            generate_all_card_images::execute($this->course->id, 'all', true, false);
        });
    }

    /**
     * Learners cannot count or queue a batch.
     */
    public function test_student_cannot_generate_all(): void {
        $this->setUser($this->student);
        $this->assert_call_fails(
            'nopermissions',
            'format_aicourse_generate_all_card_images',
            ['courseid' => $this->course->id, 'scope' => 'all']
        );
    }

    /**
     * Every queued job carries its own idempotency key, so a repeat is never charged twice.
     */
    public function test_queued_jobs_carry_a_request_id(): void {
        $this->set_fake_credentials();
        $this->setUser($this->teacher);
        generate_card_image::execute($this->course->id, 'section', (int) $this->section->id, '');
        generate_card_image::execute($this->course->id, 'cm', (int) $this->page->cmid, '');

        $ids = [];
        foreach (\core\task\manager::get_adhoc_tasks('\\format_aicourse\\task\\generate_card_image') as $task) {
            $ids[] = (string) ($task->get_custom_data()->requestid ?? '');
        }
        $this->assertCount(2, $ids);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $ids[0]);
        $this->assertNotSame($ids[0], $ids[1]);
    }
}
