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
 * Works out which settings arrived recently.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\local;

/**
 * Reads the release each setting was added in, from the settings file itself.
 *
 * ACF-FIX-2.1.137. The "Recently added" filter began as a hand-written list, which is the one part
 * of the settings page that could go stale -- and a list nobody prunes ends up marking half the
 * page as new, at which point the filter says nothing.
 *
 * Every settings block in `settings.php` already carries a `// ACF-FIX-2.1.NNN:` comment directly
 * above it, written as part of adding the setting. That comment is the record: pairing each setting
 * name with the nearest marker above it gives the release it arrived in, with nothing extra to
 * maintain and nothing that can disagree with the code.
 *
 * The changelog was the other candidate. It is prose -- settings appear there by their label, not
 * their name, and phrasing varies -- so matching against it would be guesswork that fails silently.
 * The markers are already machine-readable and sit beside the thing they describe.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settingsmeta {
    /**
     * How many releases back still counts as recent.
     *
     * Ten is roughly a working session's worth of releases. Wide enough that a batch of related
     * settings stays visible together, narrow enough that the filter keeps meaning something.
     */
    protected const RECENT_RELEASES = 10;

    /**
     * Setting names added within the last few releases.
     *
     * @return string[] Setting names, without the plugin prefix.
     */
    public static function get_recent(): array {
        global $CFG;

        $file = $CFG->dirroot . '/course/format/aicourse/settings.php';
        if (!is_readable($file)) {
            return [];
        }
        $source = file_get_contents($file);
        if ($source === false) {
            return [];
        }

        $current = self::current_patch();
        if ($current === null) {
            return [];
        }
        $cutoff = $current - self::RECENT_RELEASES;

        // Walk the file in order, remembering the most recent marker seen. A setting belongs to
        // whichever marker precedes it -- which is exactly how the file reads to a person.
        //
        // Three shapes have to be recognised, because not every setting is written out in full:
        //
        // 'format_aicourse/defaultcardcolour'          a literal name
        // 'format_aicourse/default' . $acfcolour       built in a loop over a list of suffixes
        // $acfcolours = ['indexheadingcolour', ...]    the list those loops read
        //
        // The first version of this only matched literals and returned three results, two of which
        // were the fragments 'default' and 'force' -- the prefixes of the concatenated form. Any
        // filter built on that would have been quietly wrong rather than visibly broken.
        // Order matters, and so does the lookahead. With the literal alternative first it matched
        // 'format_aicourse/default' out of the concatenated form -- backtracking let the whitespace
        // match nothing, so the "not followed by a dot" check passed against the space. Putting the
        // concatenated form first and testing `(?!\s*\.)` fixes both halves of that.
        $pattern = '/(?:\/\/\s*ACF-FIX-2\.1\.(\d+))'
            . '|(?:\'format_aicourse\/(default|force)\'\s*\.\s*\$([a-z]+))'
            . '|(?:\'format_aicourse\/([a-z]+)\'(?!\s*\.))'
            . '|(?:\$([a-z]+)\s*=\s*\[([^\]]*)\])/';
        if (!preg_match_all($pattern, $source, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $recent = [];
        $marker = null;
        // Suffix lists, so a loop's settings can be expanded: $acfcolours => ['a', 'b'].
        $lists = [];
        $pending = [];
        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $marker = (int) $match[1];
                continue;
            }
            if (($match[5] ?? '') !== '') {
                // A list assignment. Record its members for any loop that reads it.
                preg_match_all('/\'([a-z]+)\'/', $match[6] ?? '', $items);
                $lists[$match[5]] = $items[1];
                continue;
            }
            if ($marker === null || $marker <= $cutoff) {
                continue;
            }
            if (($match[2] ?? '') !== '') {
                // A concatenated name: remember the prefix and the variable it is joined to.
                $pending[] = [$match[2], $match[3]];
            } else if (($match[4] ?? '') !== '') {
                $recent[$match[4]] = true;
            }
        }

        // Expand the loops now every list has been seen -- a list can be declared after the loop
        // that reads it in some styles, so this cannot be done in one pass.
        foreach ($pending as [$prefix, $var]) {
            foreach ($lists[$var] ?? [] as $suffix) {
                $recent[$prefix . $suffix] = true;
            }
        }

        return array_keys($recent);
    }

    /**
     * The patch number of the release running now.
     *
     * @return int|null Null when the release string is not the expected shape.
     */
    protected static function current_patch(): ?int {
        global $CFG;

        $file = $CFG->dirroot . '/course/format/aicourse/version.php';
        if (!is_readable($file)) {
            return null;
        }
        $source = file_get_contents($file);
        if ($source === false) {
            return null;
        }
        if (!preg_match('/\$plugin->release\s*=\s*\'2\.1\.(\d+)\'/', $source, $m)) {
            return null;
        }
        return (int) $m[1];
    }
}
