<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace format_aicourse\local;

use format_aicourse\local\byok\engine;

/**
 * Bring your own AI parity: the engine builds the same tutor prompt and scenes as the LMS Labs service.
 *
 * The fixtures were made by running the service's own code (server/routes.ts, aiCourseVisualConcept.ts)
 * on each input, so a difference here means the site's own AI would get a different request than credits do.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(engine::class)]
final class byok_parity_test extends \advanced_testcase {
    /**
     * Read a fixture.
     *
     * @param string $name File name in tests/fixtures.
     * @return array
     */
    private static function fixture(string $name): array {
        return json_decode(file_get_contents(__DIR__ . '/../fixtures/' . $name), true);
    }

    /**
     * The tutor request is byte-for-byte the service's prompt, for every fixture case.
     */
    public function test_tutor_prompt_matches_the_service(): void {
        $cases = self::fixture('byok_tutor_parity.json');
        $this->assertGreaterThanOrEqual(30, count($cases));
        $sent = null;
        engine::$aicall = function (string $system, string $user, array $options) use (&$sent): string {
            $sent = $user;
            return 'An answer.';
        };
        foreach ($cases as $i => $case) {
            $payload = ['siteUrl' => 'byok', 'apiKey' => 'byok'];
            foreach (['courseName', 'courseContext', 'question', 'activityName', 'activityType', 'sectionName'] as $f) {
                if (array_key_exists($f, $case['in'])) {
                    // A null the plugin sends (no activity) is kept: the service prints it as "null".
                    $payload[$f] = $case['in'][$f] === null ? null : (string) $case['in'][$f];
                }
            }
            $sent = null;
            [$status] = engine::chat($payload);
            $this->assertSame(200, $status, 'Tutor case ' . $i);
            $this->assertSame($case['out'], $sent, 'Tutor case ' . $i);
        }
    }

    /**
     * Scene composition and validation match the service for every case.
     */
    public function test_scene_composition_matches_the_service(): void {
        $mediums = ['photo' => 'A realistic professional photograph', 'illustration' => 'A modern editorial illustration',
            'render3d' => 'A polished 3D render', 'flat' => 'A flat vector illustration'];
        $cases = self::fixture('byok_scene_parity.json');
        $this->assertGreaterThanOrEqual(60, count($cases));
        foreach ($cases as $i => $case) {
            $composed = null;
            $error = false;
            try {
                $composed = engine::compose_concept($case['c'], $mediums[$case['style']]);
            } catch (\Throwable $e) {
                $error = true;
            }
            $this->assertSame($case['err'], $error, 'Scene case ' . $i);
            $this->assertSame($case['composed'], $composed, 'Scene case ' . $i);
            if ($composed !== null) {
                $this->assertSame($case['valid'], engine::valid_scene($composed, $case['style'], $case['tail']), 'Valid ' . $i);
            }
        }
    }
}
