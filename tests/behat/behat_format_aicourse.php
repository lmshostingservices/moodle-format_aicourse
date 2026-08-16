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
 * Behat page resolvers for format_aicourse.
 *
 * Lets feature files address this plugin's pages with the standard
 * "I am on the ... page" steps instead of hardcoding URLs.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file is required by behat before including
// the config.php in the same way as the phpunit bootstrap does.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Behat page resolvers for format_aicourse.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_format_aicourse extends behat_base {
    /**
     * Pages that do not belong to a specific instance.
     *
     * Recognised page types:
     * | admin report | The site wide AI Tutor question and answer report. |
     * | index        | The AI Tutor plugin index page (site administration).  |
     *
     * @param string $page The page name.
     * @return moodle_url The page URL.
     * @throws ExpectationException When the page name is not recognised.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'admin report':
                return new moodle_url('/course/format/aicourse/admin_report.php');
            case 'index':
                return new moodle_url('/course/format/aicourse/index.php');
            default:
                throw new ExpectationException(
                    "Unrecognised format_aicourse page type '{$page}'.",
                    $this->getSession()
                );
        }
    }

    /**
     * Pages that belong to a particular course.
     *
     * Recognised page types:
     * | course report | The AI Tutor report for a course, identified by shortname. |
     *
     * @param string $type The page type.
     * @param string $identifier The course shortname.
     * @return moodle_url The page URL.
     * @throws ExpectationException When the page type is not recognised.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        switch (strtolower($type)) {
            case 'course report':
                $courseid = $DB->get_field('course', 'id', ['shortname' => $identifier], MUST_EXIST);
                return new moodle_url('/course/format/aicourse/report.php', ['id' => $courseid]);

            default:
                throw new ExpectationException(
                    "Unrecognised format_aicourse page type '{$type}'.",
                    $this->getSession()
                );
        }
    }
}
