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

namespace format_aicourse\output\courseformat;

use core\output\named_templatable;
use format_aicourse\local\icons;
use renderable;
use renderer_base;
use stdClass;

/**
 * The section card icon picker modal.
 *
 * PHASE 2: the markup lives in templates/icon_picker.mustache; this class only assembles the
 * template context. {@see self::out()} is kept because the icon picker is embedded as a string
 * in the course content context (see \format_aicourse\output\courseformat\content).
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class iconpicker implements named_templatable, renderable {
    /** @var array<string, string> Icon key => inline SVG body, from icons::get_library(). */
    protected $iconlibrary;

    /**
     * Constructor.
     *
     * @param array $iconlibrary Icon library as returned by icons::get_library().
     */
    public function __construct(array $iconlibrary) {
        $this->iconlibrary = $iconlibrary;
    }

    /**
     * The template this renderable is rendered with.
     *
     * @param renderer_base $renderer The renderer requesting the template name.
     * @return string Template name.
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_aicourse/icon_picker';
    }

    /**
     * Export the icon picker: category groupings, localised icon labels, search and Remove.
     *
     * ACF-FIX-2.0 notes preserved from the string-builder this replaced:
     *  - a11y: the picker is an ARIA dialog (role/aria-modal/aria-labelledby) and its heading is an
     *    <h2>, so the document outline is not broken.
     *  - a11y: the search field has a real (visually hidden) <label>; a placeholder is not a label.
     *  - i18n: the close button's aria-label and the category headings come from get_string();
     *    data-category keeps the untranslated slug the JS filter compares against.
     *  - a11y: the redundant title="" on each icon button is gone — it duplicated the visible label
     *    exactly, so screen readers said "Book, Book".
     *
     * The only pre-escaped value in the context is `svg`: the inline SVG child markup from
     * {@see icons::get_library()}. That is plugin-defined constant markup, never user input, so it
     * is rendered with a triple mustache.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Template context.
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->closelabel = get_string('closebuttontitle');
        $data->searchlabel = get_string('searchicons', 'format_aicourse');
        $data->removelabel = get_string('removeicon', 'format_aicourse');
        $data->categories = [];

        foreach (icons::get_categories() as $catkey => $iconkeys) {
            $category = (object) [
                'key' => $catkey,
                'label' => get_string('iconcategory_' . $catkey, 'format_aicourse'),
                'icons' => [],
            ];
            foreach ($iconkeys as $key) {
                if (!isset($this->iconlibrary[$key])) {
                    continue;
                }
                $category->icons[] = (object) [
                    'key' => $key,
                    'label' => icons::get_label($key),
                    'svg' => $this->iconlibrary[$key],
                ];
            }
            $data->categories[] = $category;
        }

        return $data;
    }

    /**
     * Render the icon picker modal.
     *
     * @return string HTML.
     */
    public function out(): string {
        global $OUTPUT;

        return $OUTPUT->render($this);
    }
}
