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

/**
 * Resolves the LMS-Labs site id and API key used by the AI Tutor and the banner generator.
 *
 * The optional local_aiconfig plugin holds the credentials centrally for a whole site; when it
 * is not installed the plugin's own settings are used instead. Both external functions that talk
 * to the remote service need this lookup, so it lives in one place.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credentials {
    /**
     * Fetch the configured credentials.
     *
     * @return array Two element array of [string siteid, string apikey]. Either may be empty.
     */
    public static function get(): array {
        global $CFG;

        $aiconfiglib = $CFG->dirroot . '/local/aiconfig/lib.php';
        if (file_exists($aiconfiglib)) {
            require_once($aiconfiglib);
        }

        $siteid = '';
        if (function_exists('local_aiconfig_get_siteid')) {
            $siteid = trim(local_aiconfig_get_siteid('format_aicourse') ?? '');
        }
        if ($siteid === '') {
            $siteid = trim(get_config('format_aicourse', 'siteid') ?: '');
        }

        $apikey = '';
        if (function_exists('local_aiconfig_get_apikey')) {
            $apikey = trim(local_aiconfig_get_apikey('format_aicourse') ?? '');
        }
        if ($apikey === '') {
            $apikey = trim(get_config('format_aicourse', 'apikey') ?: '');
        }

        return [$siteid, $apikey];
    }

    /**
     * Fetch the configured credentials, failing when the site has not been set up.
     *
     * @return array Two element array of [string siteid, string apikey], both non empty.
     * @throws \moodle_exception When either credential is missing.
     */
    public static function require_configured(): array {
        [$siteid, $apikey] = self::get();
        if ($siteid === '' || $apikey === '') {
            throw new \moodle_exception('aiassistant_notconfigured', 'format_aicourse');
        }

        return [$siteid, $apikey];
    }
}
