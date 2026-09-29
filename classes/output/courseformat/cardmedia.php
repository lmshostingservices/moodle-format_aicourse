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

use format_aicourse\local\cardimage;

/**
 * Template context for the image area at the top of a section or activity card.
 *
 * 2.5.0. One exporter for both kinds of card, so the two can never disagree about what an
 * image area contains, which tools a teacher sees, or how an empty one is drawn.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cardmedia {
    /**
     * Build the context for format_aicourse/card_media.
     *
     * @param int $courseid The course.
     * @param string $type cardimage::TYPE_SECTION or cardimage::TYPE_CM.
     * @param int $id course_sections.id or course_modules.id.
     * @param string $plainname The card's name as plain text, for accessible names.
     * @param bool $isediting True when the edit-mode tools should be drawn.
     * @param array $placeholder What the colour panel of a card with no image carries: 'iconsvg'
     *                           (trusted SVG child markup from the plugin's icon library) or
     *                           'iconurl' (a module icon), 'eyebrow' (plain text) and 'title'
     *                           (PRE-ESCAPED, from format_string()).
     * @return \stdClass
     */
    public static function export(
        int $courseid,
        string $type,
        int $id,
        string $plainname,
        bool $isediting,
        array $placeholder
    ): \stdClass {
        $resolved = cardimage::resolve($courseid, $type, $id);
        $colour = cardimage::get_colour($courseid, $type, $id);

        $data = (object) [
            'cardtype' => $type,
            'cardid' => $id,
            'courseid' => $courseid,
            'cardname' => $plainname,
            'hasimage' => $resolved['url'] !== '',
            'imageurl' => $resolved['url'],
            'imagesource' => $resolved['source'],
            'colour' => $colour,
            'hascolour' => $colour !== '',
            'placeholdersvg' => (string) ($placeholder['iconsvg'] ?? ''),
            'placeholdericonurl' => (string) ($placeholder['iconurl'] ?? ''),
            // Status chip; the caller sets these when the card has a status worth showing.
            'hasstatus' => false,
            'status' => '',
            'statuslabel' => '',
            'iscomplete' => false,
            'isediting' => $isediting,
            'canremove' => $isediting && $resolved['source'] === 'card',
        ];
        $data->covereyebrow = (string) ($placeholder['eyebrow'] ?? '');
        $data->covertitle = (string) ($placeholder['title'] ?? s($plainname));
        $data->islight = $colour !== '' && cardimage::is_light($colour);
        $data->hasplaceholdersvg = $data->placeholdersvg !== '';
        $data->hasplaceholdericon = !$data->hasplaceholdersvg && $data->placeholdericonurl !== '';

        if ($isediting) {
            $data->uploadlabel = get_string('cardimage_uploadfor', 'format_aicourse', $plainname);
            $data->ailabel = get_string('cardimage_aifor', 'format_aicourse', $plainname);
            $data->colourlabel = get_string('cardimage_colourfor', 'format_aicourse', $plainname);
            $data->removelabel = get_string('cardimage_removefor', 'format_aicourse', $plainname);
        }

        return $data;
    }

    /**
     * The data format_aicourse/cardimage needs on an editing page, as HTML.
     *
     * Activity cards are not drawn while editing, so the module needs to know, for each activity
     * that WOULD be a card, its name, image, colour and module icon, to add an image row to core's
     * activity row. It rides in a data attribute rather than in js_call_amd() arguments, which
     * Moodle warns about past 1KB, and rather than an inline script, which a Content Security
     * Policy may block.
     *
     * @param \stdClass $course The course.
     * @return string A hidden element carrying the JSON.
     */
    public static function page_data_html(\stdClass $course): string {
        global $DB;

        $courseid = (int) $course->id;
        $modinfo = get_fast_modinfo($course);
        $format = course_get_format($course);
        $options = $format->get_format_options();
        $style = cardimage::clean_style($options['cardimagestyle'] ?? '');

        cardimage::preload($courseid, cardimage::TYPE_CM);
        $cms = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->url && $cm->modname !== 'subsection') {
                // Labels are drawn as text blocks, never as cards.
                continue;
            }
            $resolved = cardimage::resolve($courseid, cardimage::TYPE_CM, (int) $cm->id);
            $cms[(int) $cm->id] = [
                'name' => html_to_text(format_string($cm->name, true, ['escape' => false]), 0, false),
                'url' => $resolved['url'],
                'source' => $resolved['source'],
                'colour' => cardimage::get_colour($courseid, cardimage::TYPE_CM, (int) $cm->id),
                'iconurl' => $cm->get_icon_url()->out(false),
            ];
        }

        // Generations still queued or running, so a reload keeps showing their progress. Ten
        // minutes is past the six the browser waits; anything older has died with its cron run.
        $running = [];
        $like = $DB->sql_like('name', ':name');
        $rows = $DB->get_records_select(
            'config_plugins',
            "plugin = :plugin AND $like",
            ['plugin' => 'format_aicourse', 'name' => 'cardstatus\_' . $courseid . '\_%'],
            '',
            'id, name, value'
        );
        foreach ($rows as $row) {
            $state = json_decode((string) $row->value, true);
            if (!is_array($state) || !in_array($state['state'] ?? '', ['queued', 'running'], true)
                    || (int) ($state['time'] ?? 0) < time() - 600) {
                continue;
            }
            if (preg_match('/_(s|c)(\d+)$/', $row->name, $m)) {
                $running[] = ['type' => $m[1] === 's' ? cardimage::TYPE_SECTION : cardimage::TYPE_CM, 'id' => (int) $m[2]];
            }
        }

        $data = [
            'courseid' => $courseid,
            'cost' => \format_aicourse\external\generate_card_image::CREDIT_COST,
            'style' => cardimage::style_options()[$style],
            'cms' => (object) $cms,
            'running' => $running,
        ];

        return \html_writer::div('', '', [
            'id' => 'aicourse-cardimage-data',
            'hidden' => 'hidden',
            'data-json' => json_encode($data),
        ]);
    }

    /**
     * The inline style that carries a card's own colour, or '' for the course accent.
     *
     * The colour went through cardimage::clean_colour() when stored and is re-checked here, so
     * nothing but a hex colour can reach the style attribute.
     *
     * @param \stdClass $media Output of self::export().
     * @return string
     */
    public static function style(\stdClass $media): string {
        $colour = cardimage::clean_colour((string) $media->colour);
        return $colour === '' ? '' : '--acf-card-colour:' . $colour . ';';
    }
}
