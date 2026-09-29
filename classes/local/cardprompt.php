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
 * Writes the complete image prompt for one section or activity card.
 *
 * 2.6.0. Until now the plugin sent the card's fields and the image service wrote the prompt with
 * its banner template, so the quality of every card image depended on wording the plugin did not
 * own. The plugin knows the card best: its title, its summary or activity description, the course's
 * chosen style and the card's colour. So the plugin writes the prompt, and the service is asked to
 * use it verbatim.
 *
 * Every prompt is assembled in the same order, so the images in a course read as one set:
 *
 *  1. Subject: the card's title, plus the first lines of its summary or activity description.
 *  2. Context: which course it belongs to, and an instruction to show one concrete scene rather
 *     than the stock clichés image models fall back on.
 *  3. Style: the course's AI card image style, spelled out as art direction.
 *  4. Palette: the card's own colour, or the course accent, named and given as hex.
 *  5. Composition: 16:9, one focal point, calm space, nothing at the edges the card crops, no text.
 *  6. The teacher's own words, last, so they refine the image rather than replace the brief.
 *
 * A separate negative prompt lists what must never appear. The version string travels with every
 * request so the service can compare quality across plugin releases.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cardprompt {
    /** @var string Identifies this prompt recipe in the service's logs. */
    public const VERSION = 'card-1';

    /** @var int Longest prompt sent, in characters; agreed with the image service. */
    public const PROMPT_MAX = 2000;

    /** @var int Longest summary or description excerpt used as subject detail. */
    public const DETAIL_MAX = 280;

    /** @var string[] Art direction for each value of cardimage::STYLES. */
    private const STYLE_DIRECTION = [
        'photo' => 'editorial photograph, natural light, realistic colour, shallow depth of field, '
            . '50mm lens, crisp detail',
        'illustration' => 'modern editorial illustration, clean confident shapes, soft gradients, subtle paper '
            . 'grain, gentle depth',
        'render3d' => 'polished 3D render, soft studio lighting, matte clay-like materials, gentle shadows, '
            . 'minimal set',
        'flat' => 'flat vector illustration, simple geometric shapes, limited palette, crisp edges, '
            . 'no gradients, generous negative space',
    ];

    /** @var array Hue upper bounds, in degrees, and the colour word for each band. */
    private const HUES = [
        [15, 'red'], [40, 'orange'], [65, 'golden yellow'], [90, 'lime'], [150, 'green'], [185, 'teal'],
        [205, 'cyan blue'], [245, 'blue'], [275, 'indigo'], [320, 'purple'], [345, 'pink'], [361, 'red'],
    ];

    /** @var string What must never appear in a card image. */
    public const NEGATIVE = 'text, letters, words, numbers, captions, logos, watermarks, signatures, user '
        . 'interface, screens with content, charts, labels, borders, frames, collage, split panels, clip art, '
        . 'stock-photo people smiling at the camera, distorted faces, distorted hands, extra fingers, '
        . 'lightbulbs, gears, graduation caps, stacks of books, blurry, low resolution, oversaturated';

    /**
     * Compose the prompt for one card.
     *
     * @param \stdClass $course The course.
     * @param string $type cardimage::TYPE_SECTION or cardimage::TYPE_CM.
     * @param \section_info|\cm_info $target The card's section or activity.
     * @param string $teacher The teacher's own words; may be empty.
     * @return array{prompt: string, negativePrompt: string, promptVersion: string}
     */
    public static function compose(\stdClass $course, string $type, $target, string $teacher): array {
        $context = \context_course::instance($course->id);
        $coursename = self::clean(text::plain((string) $course->fullname, $context));
        $options = course_get_format($course)->get_format_options();
        $style = cardimage::clean_style($options['cardimagestyle'] ?? '');

        if ($type === cardimage::TYPE_CM) {
            $title = self::clean(text::plain((string) $target->name, $context));
            $detail = self::excerpt(self::activity_intro($target));
            $kind = self::activity_kind((string) $target->modname);
            $section = $target->get_section_info();
            $sectionname = ($section && trim((string) $section->name) !== '')
                ? self::clean(text::plain((string) $section->name, $context)) : '';
            $colour = cardimage::get_colour((int) $course->id, cardimage::TYPE_CM, (int) $target->id);
        } else {
            $title = trim((string) $target->name) !== ''
                ? self::clean(text::plain((string) $target->name, $context)) : '';
            $detail = self::excerpt(self::html_plain((string) $target->summary));
            $kind = 'a section';
            $sectionname = '';
            $colour = cardimage::get_colour((int) $course->id, cardimage::TYPE_SECTION, (int) $target->id);
            if ($title === '') {
                // An unnamed section says nothing on its own; its summary or the course carries it.
                $title = $detail !== '' ? $detail : $coursename;
                $detail = '';
            }
        }
        if ($colour === '') {
            $colour = self::course_accent($options);
        }

        $where = 'the online course "' . $coursename . '"';
        if ($sectionname !== '') {
            $where .= ', in the part called "' . $sectionname . '"';
        }

        $lines = [];
        $lines[] = 'Subject: ' . self::sentence($title) . ($detail !== '' ? ' ' . self::sentence($detail) : '');
        $lines[] = 'Context: the cover image for ' . $kind . ' in ' . $where . '. Show one clear, concrete '
            . 'scene or object that captures this topic. Avoid generic classrooms, laptops and stock imagery.';
        $lines[] = 'Style: ' . self::STYLE_DIRECTION[$style] . '.';
        $lines[] = $colour !== ''
            ? 'Palette: ' . self::colour_name($colour) . ' tones (' . strtoupper($colour) . ') as the main '
                . 'accent, with calm, harmonious neutrals.'
            : 'Palette: natural, balanced colour with calm neutrals.';
        $lines[] = 'Composition: wide 16:9 frame, a single focal point slightly off centre, calm uncluttered '
            . 'background, nothing important near the top or bottom edge. No text, letters or logos anywhere.';

        $teacher = self::clean($teacher);
        $tail = $teacher !== '' ? "\n" . 'Teacher\'s direction: ' . self::sentence($teacher) : '';

        // Only the subject can be long enough to push past the limit (names and descriptions are
        // the teacher's), so it is the part shortened; the brief after it always survives whole.
        $prompt = implode("\n", $lines);
        $over = \core_text::strlen($prompt) + \core_text::strlen($tail) - self::PROMPT_MAX;
        if ($over > 0) {
            $subject = $lines[0];
            $keep = max(40, \core_text::strlen($subject) - $over - 1);
            $lines[0] = rtrim(\core_text::substr($subject, 0, $keep)) . '…';
            $prompt = implode("\n", $lines);
        }

        return [
            'prompt' => $prompt . $tail,
            'negativePrompt' => self::NEGATIVE,
            'promptVersion' => self::VERSION,
        ];
    }

    /**
     * The activity's own description, as plain text, or '' when the module has none.
     *
     * @param \cm_info $cm The activity.
     * @return string
     */
    private static function activity_intro(\cm_info $cm): string {
        global $DB;

        $columns = $DB->get_columns($cm->modname);
        if (!isset($columns['intro'])) {
            return '';
        }
        $intro = (string) $DB->get_field($cm->modname, 'intro', ['id' => $cm->instance]);
        return self::html_plain($intro);
    }

    /**
     * Text from HTML, words kept as written.
     *
     * Not html_to_text(): it writes bold as UPPERCASE and links as footnotes, which a model reads
     * as shouting and noise. Block ends become spaces so paragraphs do not run together.
     *
     * @param string $html Stored HTML.
     * @return string
     */
    private static function html_plain(string $html): string {
        $html = preg_replace('~<(br|/p|/div|/li|/h[1-6]|/td|/tr)\b[^>]*>~i', ' ', $html);
        return html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * How the prompt refers to an activity: "a Quiz activity", "a Forum activity".
     *
     * @param string $modname The module's component name without mod_.
     * @return string
     */
    private static function activity_kind(string $modname): string {
        if ($modname === 'subsection') {
            return 'a section';
        }
        $label = get_string_manager()->string_exists('modulename', 'mod_' . $modname)
            ? get_string('modulename', 'mod_' . $modname) : $modname;
        return 'a ' . self::clean($label) . ' activity';
    }

    /**
     * The course's accent colour, resolved as the page resolves it, or ''.
     *
     * @param array $options The course format options.
     * @return string '#rrggbb' or ''.
     */
    private static function course_accent(array $options): string {
        $colour = trim((string) ($options['accentcolour'] ?? ''));
        if ($colour === '') {
            $colour = trim((string) get_config('format_aicourse', 'defaultaccentcolour'));
        }
        $forced = trim((string) get_config('format_aicourse', 'forceaccentcolour'));
        if ($forced !== '') {
            $colour = $forced;
        }
        return cardimage::clean_colour($colour);
    }

    /**
     * A plain colour word for a hex colour, so the model reads intent as well as a value.
     *
     * @param string $hex '#rrggbb' or '#rgb'.
     * @return string
     */
    public static function colour_name(string $hex): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        [$r, $g, $b] = array_map(fn($c) => hexdec($c) / 255, str_split($hex, 2));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        $s = $d == 0 ? 0 : $d / (1 - abs(2 * $l - 1));

        if ($s < 0.2) {
            return $l < 0.2 ? 'charcoal' : ($l > 0.85 ? 'soft white' : 'slate grey');
        }
        if ($max == $r) {
            $h = 60 * fmod((($g - $b) / $d), 6);
        } else if ($max == $g) {
            $h = 60 * ((($b - $r) / $d) + 2);
        } else {
            $h = 60 * ((($r - $g) / $d) + 4);
        }
        if ($h < 0) {
            $h += 360;
        }

        $name = 'red';
        foreach (self::HUES as [$limit, $label]) {
            if ($h < $limit) {
                $name = $label;
                break;
            }
        }
        if ($l < 0.3) {
            return 'deep ' . $name;
        }
        if ($l > 0.75) {
            return 'pale ' . $name;
        }
        return $name;
    }

    /**
     * One line of plain text: control characters removed, whitespace collapsed.
     *
     * @param string $text Any text.
     * @return string
     */
    private static function clean(string $text): string {
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);
        return trim(preg_replace('/\s+/u', ' ', (string) $text));
    }

    /**
     * The opening of a summary or description, cut at a word boundary.
     *
     * @param string $text Plain text.
     * @return string
     */
    private static function excerpt(string $text): string {
        $text = self::clean($text);
        if (\core_text::strlen($text) <= self::DETAIL_MAX) {
            return $text;
        }
        $cut = \core_text::substr($text, 0, self::DETAIL_MAX);
        $space = \core_text::strrpos($cut, ' ');
        return rtrim($space > self::DETAIL_MAX / 2 ? \core_text::substr($cut, 0, $space) : $cut, ' ,;:-') . '…';
    }

    /**
     * Text ending in a full stop, so the prompt's parts never run into each other.
     *
     * @param string $text Plain text.
     * @return string
     */
    private static function sentence(string $text): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        return preg_match('/[.!?…]$/u', $text) ? $text : $text . '.';
    }
}
