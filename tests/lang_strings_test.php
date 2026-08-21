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

namespace format_aicourse;

/**
 * Every language string the plugin asks for must exist.
 *
 * ACF-FIX-2.1.111. A missing string is not a cosmetic fault: Moodle's settings page calls
 * get_string() while building the form, so one absent key fills the page with debugging output and
 * can stop an administrator reaching the settings at all.
 *
 * It is also the easiest kind of mistake to make. Strings are added by hand alongside the code that
 * uses them, and a rename or a bad merge separates the two silently -- nothing fails until someone
 * opens the page.
 *
 * This walks the plugin's own source for `get_string('x', 'format_aicourse')` and asserts each key
 * is defined, which turns "someone will notice eventually" into a test failure.
 *
 * @package    format_aicourse
 * @covers     \format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lang_strings_test extends \advanced_testcase {
    /**
     * Every string referenced in the plugin's PHP is defined in the language file.
     *
     * @return void
     */
    public function test_referenced_strings_exist(): void {
        global $CFG;
        $root = $CFG->dirroot . '/course/format/aicourse';

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        $referenced = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            // The tests themselves are excluded: a test may reference a deliberately absent key to
            // check the failure path, and that is not a fault in the plugin.
            if (strpos($file->getPathname(), '/tests/') !== false) {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            $pattern = '/get_string\(\s*[\'"]([a-zA-Z0-9_:.\-]+)[\'"]\s*,\s*[\'"]format_aicourse[\'"]/';
            if (preg_match_all($pattern, $source, $matches)) {
                foreach ($matches[1] as $key) {
                    $referenced[$key] = $file->getFilename();
                }
            }
        }

        $this->assertNotEmpty($referenced, 'No strings found to check - the scan itself is broken.');

        $manager = get_string_manager();
        $missing = [];
        foreach ($referenced as $key => $where) {
            if (!$manager->string_exists($key, 'format_aicourse')) {
                $missing[] = $key . ' (used in ' . $where . ')';
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Language strings are referenced but not defined:\n  " . implode("\n  ", $missing)
        );
    }

    /**
     * Every setting shown on the settings page has both a name and an explanation.
     *
     * A setting with a label and no description is not fatal, but it reaches an administrator as a
     * control with no indication of what it does -- and it is the same omission that produces a
     * missing-string error one line over.
     *
     * @return void
     */
    public function test_course_settings_are_described(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/course/format/aicourse/lib.php');

        // The course settings form declares each option with 'help' => 'name'.
        preg_match_all("/'help'\s*=>\s*'([a-zA-Z0-9_]+)'/", $source, $matches);
        $this->assertNotEmpty($matches[1], 'No course settings found to check.');

        $manager = get_string_manager();
        $undescribed = [];
        foreach (array_unique($matches[1]) as $name) {
            if (!$manager->string_exists($name . '_help', 'format_aicourse')) {
                $undescribed[] = $name . '_help';
            }
        }

        $this->assertSame(
            [],
            $undescribed,
            "Course settings declare help text that does not exist:\n  " . implode("\n  ", $undescribed)
        );
    }
}
