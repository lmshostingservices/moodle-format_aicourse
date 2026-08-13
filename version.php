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
 * format_aicourse file.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'format_aicourse';
$plugin->version   = 2026072300;
$plugin->requires     = 2022041900;
$plugin->supported    = [400, 500];
$plugin->maturity     = MATURITY_STABLE;
$plugin->release      = '1.7.74'; // FIX-ACF-EDITMODE (v1.7.70): Gate section card edit controls (rename/duplicate/delete buttons, icon picker, add-section card) by $PAGE->user_is_editing() in addition to capability check. Edit mode OFF now shows exactly the student view (banner + cards, no edit UI). Edit mode ON restores all edit controls. The card layout itself always renders (preserving the UX-ACF-EDITMODE-WIPE fix). Moodle 4.4 specific issue — Moodle 5 was already working correctly. // SAVEPOINT-BUMP v1.7.67: no-op savepoint marker for clean upgrade path. No DB schema changes.; // v1.7.66: FORCE-UPGRADE — version bump to force Moodle to re-register the plugin at the correct directory path (course/format/aicourse/), resolving err_unexpected_plugin_rootdir on uninstall. No code or DB changes. // v1.7.65: CARDS-ALIGN — Removed padding: 0 var(--aicourse-gutter) and max-width/margin centering from both .aicourse-cards-container and .aicourse-activity-cards-container. Same gutter-indent issue as the hero wrapper (v1.7.63) — the 22px horizontal padding on each side was pushing section cards and activity cards away from both edges. Both containers now fill the natural width of their parent. CSS-only. No PHP/AMD/DB changes. No savepoint required. // FIX-CAP-VIEWALL (v1.7.64): Replaced invalid capability string 'grade/report:viewall' with correct 'moodle/grade:viewall' in all three has_capability() calls in lib.php. PHP-only. No CSS/AMD/DB changes. No savepoint required.
$plugin->dependencies = [];
