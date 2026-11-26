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
 * Responsible AI (3.4.0): what the plugin does to support the EU AI Act.
 *
 * The AI Tutor and the image generator are not high-risk under Annex III point 3: the tutor gives
 * formative help and nothing it says is recorded as a result. They are covered by Article 50:
 *  - 50(1): people must know they are interacting with an AI system -> the tutor's AI notice.
 *  - 50(2): synthetic images and text must be marked in a machine-readable way -> every AI image is
 *    stored with IPTC/XMP "trainedAlgorithmicMedia" metadata, and AI answers carry
 *    data-ai-generated markup.
 * Article 4 (AI literacy) -> the "About this AI" panel. Article 26(5) and good practice -> a
 * site-wide pause that stops every AI call while the rest of the course keeps working.
 *
 * See docs/eu-ai-act.md. This is the provider's technical analysis, not legal advice.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class responsibleai {
    /** @var string IPTC Digital Source Type for media created by a trained model. */
    public const SOURCE_TYPE = 'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia';

    /** @var string The generator named in the image metadata. */
    public const GENERATOR = 'AI Course Format (format_aicourse) by LMS Labs';

    /**
     * Whether an administrator has paused every AI feature of the plugin.
     *
     * @return bool
     */
    public static function paused(): bool {
        return (bool) get_config('format_aicourse', 'aipaused');
    }

    /**
     * Stop here when AI is paused. Called by every web service that would call an AI model.
     *
     * @throws \moodle_exception When paused.
     */
    public static function require_not_paused(): void {
        if (self::paused()) {
            throw new \moodle_exception('responsibleai_pausederror', 'format_aicourse');
        }
    }

    /**
     * Whether learners see the AI notice in the tutor (on unless an administrator turned it off).
     *
     * @return bool
     */
    public static function show_notice(): bool {
        $value = get_config('format_aicourse', 'ainotice');
        return $value === false || (bool) $value;
    }

    /**
     * The data the tutor and the banner templates need.
     *
     * @return array
     */
    public static function template_data(): array {
        return [
            'aipaused' => self::paused(),
            'ainotice' => self::show_notice(),
        ];
    }

    /**
     * The "Bring your own AI" status on the settings page: where each service runs, as MDS badges.
     *
     * @return string HTML.
     */
    public static function byok_status_html(): string {
        $rows = '';
        foreach (['text' => 'byok_service_text', 'image' => 'byok_service_image'] as $service => $label) {
            $source = \format_aicourse\byok::source($service);
            $variant = $source === 'credits' ? 'secondary' : 'success';
            $rows .= \html_writer::tag(
                'li',
                \html_writer::span(get_string($label, 'format_aicourse'), 'acf-byok-service')
                    . \html_writer::span(
                        get_string('byok_source_' . $source, 'format_aicourse'),
                        'mds-badge badge mds-badge--' . $variant . ' mds-badge--subtle mds-badge--pill'
                    ),
                ['class' => 'acf-byok-row']
            );
        }
        $html = \html_writer::tag('ul', $rows, ['class' => 'acf-byok-list']);
        if (class_exists('\local_aiconfig\byok\ai')) {
            $url = new \moodle_url('/local/aiconfig/byok.php');
            $html .= \html_writer::tag('p', get_string('byok_engine_on', 'format_aicourse'), ['class' => 'acf-byok-note'])
                . \html_writer::link($url, get_string('byok_manage', 'format_aicourse'), [
                    'class' => 'mds-btn btn btn-secondary mds-btn--size-md',
                ]);
        } else {
            $html .= \html_writer::tag('p', get_string('byok_engine_off', 'format_aicourse'), ['class' => 'acf-byok-note']);
        }
        return \html_writer::div($html, 'acf-byok acf-mds-root');
    }

    /**
     * Mark AI-generated image bytes as AI-generated, in a machine-readable way (Article 50(2)).
     *
     * Adds an XMP packet with the IPTC Digital Source Type "trainedAlgorithmicMedia", the
     * generator and the date: an APP1 segment for JPEG, an iTXt chunk for PNG. Other formats and
     * bytes that do not parse are returned unchanged, so marking can never lose an image.
     *
     * @param string $bytes Image bytes.
     * @param string $source Where the image came from: credits, moodle or ownkey.
     * @return string Marked bytes.
     */
    public static function mark_image(string $bytes, string $source = 'credits'): string {
        $xmp = self::xmp($source);
        if (strncmp($bytes, "\xFF\xD8", 2) === 0) {
            return self::mark_jpeg($bytes, $xmp);
        }
        if (strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) === 0) {
            return self::mark_png($bytes, $xmp);
        }
        return $bytes;
    }

    /**
     * Whether image bytes carry this plugin's AI marking.
     *
     * @param string $bytes Image bytes.
     * @return bool
     */
    public static function is_marked(string $bytes): bool {
        return strpos($bytes, 'trainedAlgorithmicMedia') !== false;
    }

    /**
     * The XMP packet.
     *
     * @param string $source credits, moodle or ownkey.
     * @return string
     */
    protected static function xmp(string $source): string {
        $date = gmdate('Y-m-d\TH:i:s\Z');
        $tool = s(self::GENERATOR . ' (' . clean_param($source, PARAM_ALPHA) . ')');
        return '<?xpacket begin="' . "\u{FEFF}" . '" id="W5M0MpCehiHzreSzNTczkc9d"?>'
            . '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"'
            . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<Iptc4xmpExt:DigitalSourceType>' . self::SOURCE_TYPE . '</Iptc4xmpExt:DigitalSourceType>'
            . '<xmp:CreatorTool>' . $tool . '</xmp:CreatorTool>'
            . '<xmp:CreateDate>' . $date . '</xmp:CreateDate>'
            . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">AI-generated image</rdf:li></rdf:Alt></dc:description>'
            . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="r"?>';
    }

    /**
     * Insert an XMP APP1 segment after the JPEG SOI (and after a JFIF APP0 when there is one).
     *
     * @param string $bytes JPEG bytes.
     * @param string $xmp XMP packet.
     * @return string
     */
    protected static function mark_jpeg(string $bytes, string $xmp): string {
        $payload = "http://ns.adobe.com/xap/1.0/\0" . $xmp;
        if (strlen($payload) + 2 > 0xFFFF) {
            return $bytes;
        }
        $segment = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;
        $pos = 2;
        if (substr($bytes, 2, 2) === "\xFF\xE0" && strlen($bytes) > 6) {
            $len = unpack('n', substr($bytes, 4, 2))[1];
            $pos = 4 + $len;
        }
        return substr($bytes, 0, $pos) . $segment . substr($bytes, $pos);
    }

    /**
     * Insert an XMP iTXt chunk right after the PNG IHDR chunk.
     *
     * @param string $bytes PNG bytes.
     * @param string $xmp XMP packet.
     * @return string
     */
    protected static function mark_png(string $bytes, string $xmp): string {
        if (substr($bytes, 12, 4) !== 'IHDR') {
            return $bytes;
        }
        $ihdrlen = unpack('N', substr($bytes, 8, 4))[1];
        $pos = 8 + 12 + $ihdrlen;
        $data = "XML:com.adobe.xmp\0\0\0\0\0" . $xmp;
        $chunk = pack('N', strlen($data)) . 'iTXt' . $data . pack('N', crc32('iTXt' . $data));
        return substr($bytes, 0, $pos) . $chunk . substr($bytes, $pos);
    }
}
