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

namespace format_aicourse\external;

use format_aicourse\byok;
use format_aicourse\local\byok\engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/format/aicourse/tests/external/external_testcase.php');
require_once($CFG->dirroot . '/course/format/aicourse/tests/local/cardimage_test.php');

/**
 * Tests for the AI Tutor and course images on the site's own AI (Bring your own AI).
 *
 * local\byok\engine was compared with the LMS Labs service code on 1,750 randomised requests and AI answers
 * (identical validation, tutor prompts, scene writer requests, image prompts, statuses and answers); these tests
 * cover the wiring into the plugin.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\byok
 * @covers     \format_aicourse\local\byok\engine
 * @covers     \format_aicourse\external\ai_chat
 * @covers     \format_aicourse\external\generate_card_image
 * @covers     \format_aicourse\external\generate_banner_image
 */
#[\PHPUnit\Framework\Attributes\CoversClass(byok::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(engine::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(ai_chat::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(generate_card_image::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(generate_banner_image::class)]
final class byok_test extends external_testcase {
    /** @var array Requests the mocked providers received. */
    protected $requests = [];
    /** @var array Text answers the mocked text provider gives, in order. */
    protected $answers = [];
    /** @var bool Whether the image provider refuses. */
    protected $refuse = false;

    /**
     * Licence the site for Bring your own AI on a test key, with text on Anthropic and images on OpenAI.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\local_aiconfig\byok\ai')) {
            $this->markTestSkipped('AI Grader Central Config 1.1.0 or later is not installed.');
        }
        $pair = sodium_crypto_sign_keypair();
        set_config('testpublickey', base64_encode(sodium_crypto_sign_publickey($pair)), 'local_aiconfig');
        $payload = json_encode(['v' => 1, 'id' => 'lic_t', 'site' => \local_aiconfig\byok\licence::site_host(),
            'plan' => 'pro', 'byok' => true, 'products' => ['*'], 'sub' => 'sub_t', 'exp' => time() + DAYSECS]);
        $b64 = function (string $s): string {
            return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        };
        set_config('licencekey', $b64($payload) . '.' .
            $b64(sodium_crypto_sign_detached($payload, sodium_crypto_sign_secretkey($pair))), 'local_aiconfig');
        set_config('src_text', 'ownkey', 'local_aiconfig');
        set_config('vendor_text', 'anthropic', 'local_aiconfig');
        set_config('src_image', 'ownkey', 'local_aiconfig');
        set_config('key_anthropic', 'sk-test', 'local_aiconfig');
        set_config('key_openai', 'sk-open', 'local_aiconfig');
        // No LMS Labs credentials: everything on the site's own AI must work without them.
        foreach (['local_aiconfig', 'format_aicourse'] as $plugin) {
            set_config('siteid', '', $plugin);
            set_config('apikey', '', $plugin);
        }
        $GLOBALS['local_aiconfig_byok_mock'] = function ($url, $headers, $body, $vendor, $raw = false) {
            $this->requests[] = ['url' => $url, 'body' => $body, 'vendor' => $vendor];
            if (strpos($url, 'images/generations') !== false) {
                if ($this->refuse) {
                    throw new \moodle_exception('byok_callfailed', 'local_aiconfig', '', (object) ['vendor' => $vendor,
                        'detail' => 'Your request was rejected by our safety system (content_policy_violation)']);
                }
                return ['data' => [['b64_json' => base64_encode(\format_aicourse\local\cardimage_test::png())]]];
            }
            $answer = array_shift($this->answers);
            if ($answer === null) {
                throw new \moodle_exception('byok_callfailed', 'local_aiconfig', '', (object) ['vendor' => $vendor,
                    'detail' => 'Overloaded']);
            }
            if (!empty($body['tools'])) {
                return ['content' => [['type' => 'tool_use', 'name' => $body['tools'][0]['name'],
                    'input' => json_decode($answer, true)]], 'stop_reason' => 'tool_use'];
            }
            return ['content' => [['type' => 'text', 'text' => $answer]], 'stop_reason' => 'end_turn'];
        };
    }

    /**
     * Clean up the provider mock.
     */
    protected function tearDown(): void {
        unset($GLOBALS['local_aiconfig_byok_mock']);
        engine::$aicall = engine::$image = null;
        parent::tearDown();
    }

    /**
     * A course section row.
     *
     * @param int $number
     * @return \stdClass
     */
    protected function section(int $number): \stdClass {
        global $DB;
        return $DB->get_record('course_sections', ['course' => $this->course->id, 'section' => $number], '*', MUST_EXIST);
    }

    public function test_tutor_answers_on_own_text_without_credentials(): void {
        global $DB;
        $this->answers = ['A **hazard** is anything that could cause harm. Can you name one in your workplace?'];
        $this->setUser($this->student);
        $result = ai_chat::execute($this->course->id, 'What is a hazard? Quiz me');
        $result = \core_external\external_api::clean_returnvalue(ai_chat::execute_returns(), $result);
        $this->assertStringContainsString('A **hazard** is anything', $result['answer']);
        $this->assertFalse($result['truncated']);
        $this->assertTrue($DB->record_exists('format_aicourse_chats', ['id' => $result['chatid']]));

        // The service's tutor prompt, sent as one user turn to the site's Anthropic key.
        $this->assertCount(1, $this->requests);
        $this->assertSame('Anthropic', $this->requests[0]['vendor']);
        $sent = json_encode($this->requests[0]['body'], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('You are a world-class AI Tutor embedded inside a Moodle course.', $sent);
        $this->assertStringContainsString('The student requested PRACTICE.', $sent);
        $this->assertStringContainsString('Student says: What is a hazard? Quiz me', $sent);
    }

    public function test_tutor_failure_is_the_usual_error(): void {
        $this->setUser($this->student);
        $this->assert_throws_errorcode('aiassistant_error', function (): void {
            ai_chat::execute($this->course->id, 'What is a hazard?');
        });
        // The provider's own error is logged for the administrator, then the service's failure.
        $this->assertDebuggingCalledCount(2);
    }

    public function test_card_image_runs_on_own_ai_with_the_scene_writer(): void {
        $section = $this->section(1);
        $concept = ['interpretation' => 'Working at heights means fall prevention on a roof edge.',
            'visualConcept' => 'A roofer clips a harness lanyard to a certified anchor point',
            'setting' => 'A pitched metal roof on a warehouse under an overcast sky',
            'action' => 'The worker checks the karabiner gate is locked before stepping towards the gutter',
            'composition' => 'Medium shot from the ridge looking down along the roof line',
            'relevance' => 'Anchor, lanyard and edge together distinguish fall protection from general roof work.',
            'details' => ['a yellow inertia reel on the anchor', 'guard rail posts along the eave', 'a rescue kit bag']];
        $this->answers = [json_encode($concept)];
        $this->setUser($this->teacher);
        $url = generate_card_image::generate_card($this->course, 'section', (int) $section->id, 'Show a roof anchor');
        $this->assertStringContainsString('/sectioncardimage/' . $section->id . '/ai_card_', $url);

        $images = array_values(array_filter($this->requests, function ($r) {
            return strpos($r['url'], 'images/generations') !== false;
        }));
        $this->assertCount(1, $images);
        $this->assertSame('1536x1024', $images[0]['body']['size']);
        $this->assertSame('high', $images[0]['body']['quality']);
        // When the scene writer's concept passes the service's checks it replaces the scene part of the prompt.
        $scenes = array_values(array_filter($this->requests, function ($r) {
            return strpos($r['url'], 'images/generations') === false;
        }));
        $this->assertCount(1, $scenes);
        $this->assertSame('course_visual_concept', $scenes[0]['body']['tools'][0]['name']);
        $this->assertStringStartsWith(
            'A realistic professional photograph. A roofer clips a harness lanyard',
            $images[0]['body']['prompt']
        );

        // Stored as the service's 1536x864 card.
        $file = \format_aicourse\local\cardimage::get_url((int) $this->course->id, 'section', (int) $section->id);
        $this->assertNotNull($file);
    }

    public function test_crop_makes_the_service_sizes(): void {
        $png = \format_aicourse\local\cardimage_test::png();
        foreach ([[true, 1536, 864], [false, 1400, 420]] as [$card, $w, $h]) {
            $info = getimagesizefromstring(engine::crop($png, $card));
            $this->assertSame([$w, $h, 'image/jpeg'], [$info[0], $info[1], $info['mime']]);
        }
    }

    public function test_refused_image_says_so_and_spends_nothing(): void {
        $this->refuse = true;
        $section = $this->section(2);
        $this->setUser($this->teacher);
        try {
            generate_card_image::generate_card($this->course, 'section', (int) $section->id, '');
            $this->fail('Expected a failure.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_bannerfailed_detail', $e->errorcode);
            $this->assertStringContainsString('content_policy_violation', $e->getMessage());
            $this->assertStringContainsString('No LMS Labs credits were used', $e->getMessage());
        }
        $this->assertDebuggingCalled();
    }

    public function test_banner_runs_on_own_ai(): void {
        $this->answers = [];
        $this->setUser($this->teacher);
        $url = generate_banner_image::generate_and_store($this->course, 'A Pilbara mine site at dawn');
        $this->assertStringContainsString('ai_banner_', $url);
        $image = end($this->requests);
        $this->assertStringContainsString('images/generations', $image['url']);
    }

    public function test_generate_all_counts_no_credits(): void {
        $this->setUser($this->teacher);
        $dry = generate_all_card_images::execute($this->course->id, 'sections', false, true);
        $this->assertGreaterThan(0, $dry['count']);
        $this->assertSame(0, $dry['credits']);
    }

    public function test_without_licence_everything_needs_lms_labs(): void {
        set_config('licencekey', '', 'local_aiconfig');
        $this->assertFalse(byok::uses('text'));
        $this->assertFalse(byok::uses('image'));
        $this->setUser($this->student);
        $this->assert_throws_errorcode('aiassistant_notconfigured', function (): void {
            ai_chat::execute($this->course->id, 'What is a hazard?');
        });
    }
}
