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
 * The collapsed/expanded state of the top band.
 *
 * The band is the hero banner and, in merged course index mode, the drawer header beside it.
 * Both are sized from one shared custom property, --acf-topblock, so collapsing is a matter of
 * zeroing that token and hiding what the band holds -- see the 2.4.0 block in styles.css.
 *
 * WHY THE STATE IS READ SERVER-SIDE. A learner who has collapsed the band is going to load many
 * pages with it collapsed. If the class that collapses it were added by JavaScript, every one of
 * those pages would paint the full 126px band first and snap shut a moment later -- the flash is
 * worst on exactly the pages where it is least wanted. So the preference is read during page
 * setup and written into the <body> tag before anything is painted, and the JavaScript only
 * handles the click. This mirrors what page_set_course() already does for the colour mode, the
 * scrim and the grader class, and the reasoning there (ACF-FIX-2.1.4, FIX-GRADER-PHPCLASS) is
 * the same reasoning here.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class herocollapse {
    /**
     * @var string The user preference holding the state.
     *
     * Deliberately NOT per course. The band is page furniture, not course content: someone who
     * wants the extra room wants it everywhere, and a per-course preference would mean collapsing
     * it again in each course they open. It must also be declared in
     * format_aicourse_user_preferences() or core rejects the AJAX write outright -- see
     * ACF-FIX-2.1.43, which is the bug this would otherwise repeat.
     */
    public const PREF = 'format_aicourse_herocollapsed';

    /** @var string Body class published when the band is collapsed. */
    public const BODY_CLASS = 'aicourse-hero-collapsed';

    /**
     * Whether the current user has the band collapsed.
     *
     * @return bool True when collapsed.
     */
    public static function is_collapsed(): bool {
        if (!isloggedin() || isguestuser()) {
            // A guest has nowhere to store the preference, so the band is always open for them.
            // Returning the default rather than reading keeps this callable during early page
            // setup, before the session user is fully established.
            return false;
        }
        return (bool) get_user_preferences(self::PREF, 0);
    }

    /**
     * Template data for the toggle button.
     *
     * Both hero templates render the same control, so the labels are assembled once here rather
     * than twice in the two renderers, where they would drift.
     *
     * @return array Keys: herocollapsed, collapselabel, expandlabel, herotogglelabel.
     */
    public static function export(): array {
        $collapsed = self::is_collapsed();
        return [
            'herocollapsed' => $collapsed,
            'collapselabel' => get_string('herocollapse', 'format_aicourse'),
            'expandlabel' => get_string('heroexpand', 'format_aicourse'),
            // The label the button carries on load, which must match the state the body class
            // is about to apply -- otherwise a screen reader announces "Collapse header" on a
            // page whose header is already shut.
            'herotogglelabel' => $collapsed
                ? get_string('heroexpand', 'format_aicourse')
                : get_string('herocollapse', 'format_aicourse'),
        ];
    }
}
