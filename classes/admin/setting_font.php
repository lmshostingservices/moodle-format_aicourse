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

namespace format_aicourse\admin;

use format_aicourse\local\fonts;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * A site font setting: a grouped menu of the format's Google Fonts with a live preview.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_font extends \admin_setting_configselect {
    /** @var bool Whether the preview stylesheets have been added to this page already. */
    protected static bool $previewloaded = false;

    /**
     * Constructor.
     *
     * @param string $name Setting name, e.g. 'format_aicourse/font'.
     * @param string $visiblename Label.
     * @param string $description Help text.
     * @param string $firstlabel Label of the '' choice (theme font, or same as the body font).
     */
    public function __construct(string $name, string $visiblename, string $description, string $firstlabel) {
        parent::__construct($name, $visiblename, $description, '', fonts::menu(['' => $firstlabel], true));
    }

    /**
     * Store only '' or a key of the font list.
     *
     * @param mixed $data The submitted value.
     * @return string '' on success, else an error.
     */
    public function write_setting($data) {
        $data = (string) $data;
        if ($data !== '' && !fonts::is_font($data)) {
            return get_string('error_fontunknown', 'format_aicourse');
        }
        return $this->config_write($this->name, $data) ? '' : get_string('errorsetting', 'admin');
    }

    /**
     * The select, with a sample of the chosen font beneath it.
     *
     * @param mixed $data The current value.
     * @param string $query Admin search query.
     * @return string HTML.
     */
    public function output_html($data, $query = '') {
        global $PAGE;

        $html = parent::output_html($data, $query);
        $sample = get_string('font_preview', 'format_aicourse');
        $stacks = [];
        foreach (array_keys(fonts::FONTS) as $key) {
            $stacks[$key] = fonts::stack($key);
        }

        $links = '';
        if (!self::$previewloaded) {
            // Only the preview's own characters are requested, so all the fonts together are a few
            // kilobytes. Added once however many font settings the page shows.
            self::$previewloaded = true;
            foreach (array_keys(fonts::FONTS) as $key) {
                $links .= '<link rel="stylesheet" href="' . s(fonts::stylesheet_url($key, $sample)) . '">';
            }
            $PAGE->requires->js_call_amd('format_aicourse/fontpreview', 'init');
        }

        $preview = \html_writer::div(
            s($sample),
            'aicourse-font-preview',
            [
                'data-aicourse-fontpreview' => $this->get_id(),
                'data-stacks' => json_encode($stacks),
                'aria-hidden' => 'true',
            ]
        );
        // The preview sits inside the setting's own row, after the select.
        $marker = '</select>';
        $pos = strrpos($html, $marker);
        if ($pos === false) {
            return $links . $html . $preview;
        }
        $pos += strlen($marker);
        return $links . substr($html, 0, $pos) . $preview . substr($html, $pos);
    }
}
