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
 * Tests for the collapsed state of the top band.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/format/aicourse/lib.php');
require_once($CFG->dirroot . '/user/externallib.php');

/**
 * Tests for the collapsed state of the top band.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\local\herocollapse
 */
final class herocollapse_test extends \advanced_testcase {
    /**
     * A user who has never touched the toggle gets the band open.
     *
     * @return void
     */
    public function test_defaults_to_open(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertFalse(herocollapse::is_collapsed());
    }

    /**
     * The preference is what decides it, in both directions.
     *
     * @return void
     */
    public function test_reads_the_preference(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        set_user_preference(herocollapse::PREF, 1, $user);
        $this->assertTrue(herocollapse::is_collapsed());

        set_user_preference(herocollapse::PREF, 0, $user);
        $this->assertFalse(herocollapse::is_collapsed());
    }

    /**
     * The state is per user. One person collapsing the band must not collapse it for anyone else.
     *
     * @return void
     */
    public function test_state_is_per_user(): void {
        $this->resetAfterTest();
        $collapser = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $this->setUser($collapser);
        set_user_preference(herocollapse::PREF, 1, $collapser);
        $this->assertTrue(herocollapse::is_collapsed());

        $this->setUser($other);
        $this->assertFalse(herocollapse::is_collapsed());
    }

    /**
     * A guest has nowhere to keep the preference, so the band stays open rather than erroring.
     *
     * @return void
     */
    public function test_guest_gets_the_default(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        $this->assertFalse(herocollapse::is_collapsed());
    }

    /**
     * The button's label on load must describe the action available, not the current state.
     *
     * This is the assertion that catches the mismatch worth catching: a page rendered with the
     * band already collapsed announcing "Collapse header" to a screen reader.
     *
     * @return void
     */
    public function test_label_matches_the_state_it_is_rendered_in(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $open = herocollapse::export();
        $this->assertFalse($open['herocollapsed']);
        $this->assertSame($open['collapselabel'], $open['herotogglelabel']);

        set_user_preference(herocollapse::PREF, 1, $user);
        $shut = herocollapse::export();
        $this->assertTrue($shut['herocollapsed']);
        $this->assertSame($shut['expandlabel'], $shut['herotogglelabel']);

        // The two labels must actually differ, or the toggle says the same thing either way.
        $this->assertNotSame($open['collapselabel'], $open['expandlabel']);
    }

    /**
     * The preference must be declared, or core rejects the AJAX write and the band silently
     * reopens on every page. This is ACF-FIX-2.1.43 repeating, and it is invisible in the UI
     * until someone notices the state never sticks.
     *
     * @return void
     */
    public function test_preference_is_declared_for_ajax_writes(): void {
        $this->resetAfterTest();
        $declared = format_aicourse_user_preferences();

        $this->assertArrayHasKey(herocollapse::PREF, $declared);
        $definition = $declared[herocollapse::PREF];
        $this->assertSame(PARAM_INT, $definition['type']);
        $this->assertArrayHasKey('permissioncallback', $definition);
    }

    /**
     * The whole point of the declaration is that core accepts the write. Assert against core's
     * own validator rather than against our array, so a change in what core expects is caught
     * here rather than in production.
     *
     * @return void
     */
    public function test_core_accepts_the_preference_write(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // This is the exact call amd/src/herocollapse.js makes. Going through the external
        // function means the preference passes core's own declaration check, which is the thing
        // being tested -- asserting against our own array would prove nothing.
        \core_user_external::update_user_preferences(
            $user->id,
            null,
            [['type' => herocollapse::PREF, 'value' => '1']]
        );
        $this->assertTrue(herocollapse::is_collapsed());
    }
}
