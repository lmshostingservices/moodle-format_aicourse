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
 * Google Fonts for course text and headings.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The fixed list of Google Fonts the format offers, and the head markup that applies them.
 *
 * 3.3.1. The list is closed: every value stored in a setting or course option is a key of
 * {@see self::FONTS}, checked on write and again on read, so nothing a teacher types can reach a
 * URL or a stylesheet. Each font asks only for weights it is known to have (always 400 and 700),
 * and each is its own request, so one font failing to load cannot take the others with it. The
 * CSS always ends in a generic family, so text stays readable if Google cannot be reached.
 *
 * Settings:
 *  - site `font` ('' = the theme's font, else a key) and `headingfont` ('' = same as `font`);
 *  - site `fontscope`: 'content' (the course page, its drawers and the tutor) or 'page' (the
 *    whole page, navbar included);
 *  - course `font` ('' = the site's choice, 'theme' = the theme's font, else a key) and
 *    `headingfont` ('' = the site's choice, 'same' = the course's body font, else a key).
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fonts {
    /** @var string Course value: use the theme's own font, whatever the site chose. */
    public const THEME = 'theme';

    /** @var string Course heading value: headings use the course's body font. */
    public const SAME = 'same';

    /** @var string[] The groups, in menu order. */
    public const GROUPS = ['sans', 'readable', 'serif', 'display'];

    /**
     * Every font: key => [family, weights, generic fallback, group].
     *
     * @var array
     */
    public const FONTS = [
        // Clean sans-serif.
        'inter' => ['Inter', [400, 500, 600, 700], 'sans-serif', 'sans'],
        'roboto' => ['Roboto', [400, 500, 700], 'sans-serif', 'sans'],
        'opensans' => ['Open Sans', [400, 600, 700], 'sans-serif', 'sans'],
        'lato' => ['Lato', [400, 700], 'sans-serif', 'sans'],
        'montserrat' => ['Montserrat', [400, 500, 600, 700], 'sans-serif', 'sans'],
        'poppins' => ['Poppins', [400, 500, 600, 700], 'sans-serif', 'sans'],
        'nunito' => ['Nunito', [400, 600, 700], 'sans-serif', 'sans'],
        'sourcesans3' => ['Source Sans 3', [400, 600, 700], 'sans-serif', 'sans'],
        'worksans' => ['Work Sans', [400, 500, 600, 700], 'sans-serif', 'sans'],
        'raleway' => ['Raleway', [400, 600, 700], 'sans-serif', 'sans'],
        'dmsans' => ['DM Sans', [400, 500, 700], 'sans-serif', 'sans'],
        'manrope' => ['Manrope', [400, 500, 600, 700], 'sans-serif', 'sans'],
        'plusjakartasans' => ['Plus Jakarta Sans', [400, 500, 600, 700], 'sans-serif', 'sans'],
        'ibmplexsans' => ['IBM Plex Sans', [400, 500, 600, 700], 'sans-serif', 'sans'],
        'figtree' => ['Figtree', [400, 500, 600, 700], 'sans-serif', 'sans'],
        'notosans' => ['Noto Sans', [400, 500, 700], 'sans-serif', 'sans'],
        // Easy to read: dyslexia-friendly, low-vision and early-reader fonts.
        'lexend' => ['Lexend', [400, 500, 600, 700], 'sans-serif', 'readable'],
        'atkinsonhyperlegible' => ['Atkinson Hyperlegible', [400, 700], 'sans-serif', 'readable'],
        'andika' => ['Andika', [400, 700], 'sans-serif', 'readable'],
        'quicksand' => ['Quicksand', [400, 500, 600, 700], 'sans-serif', 'readable'],
        'fredoka' => ['Fredoka', [400, 500, 600, 700], 'sans-serif', 'readable'],
        // Serif.
        'merriweather' => ['Merriweather', [400, 700], 'serif', 'serif'],
        'lora' => ['Lora', [400, 500, 600, 700], 'serif', 'serif'],
        'playfairdisplay' => ['Playfair Display', [400, 600, 700], 'serif', 'serif'],
        'sourceserif4' => ['Source Serif 4', [400, 600, 700], 'serif', 'serif'],
        'librebaskerville' => ['Libre Baskerville', [400, 700], 'serif', 'serif'],
        'ebgaramond' => ['EB Garamond', [400, 500, 600, 700], 'serif', 'serif'],
        'crimsonpro' => ['Crimson Pro', [400, 600, 700], 'serif', 'serif'],
        'notoserif' => ['Noto Serif', [400, 700], 'serif', 'serif'],
        'ptserif' => ['PT Serif', [400, 700], 'serif', 'serif'],
        'robotoslab' => ['Roboto Slab', [400, 500, 700], 'serif', 'serif'],
        // Display: best for headings.
        'outfit' => ['Outfit', [400, 500, 600, 700], 'sans-serif', 'display'],
        'spacegrotesk' => ['Space Grotesk', [400, 500, 600, 700], 'sans-serif', 'display'],
        'rubik' => ['Rubik', [400, 500, 700], 'sans-serif', 'display'],
        'oswald' => ['Oswald', [400, 500, 600, 700], 'sans-serif', 'display'],
    ];

    /**
     * Whether a key names a font in the list.
     *
     * @param string $key A candidate key.
     * @return bool
     */
    public static function is_font(string $key): bool {
        return $key !== '' && array_key_exists($key, self::FONTS);
    }

    /**
     * The fonts as select options, grouped (for an optgroup-capable select) or flat.
     *
     * @param array $first Options placed before the fonts, value => label.
     * @param bool $grouped True for [group label => [key => family]], false for [key => family].
     * @return array
     */
    public static function menu(array $first = [], bool $grouped = false): array {
        if (!$grouped) {
            $menu = $first;
            foreach (self::FONTS as $key => $font) {
                $menu[$key] = $font[0];
            }
            return $menu;
        }
        $menu = [];
        if ($first) {
            $menu[get_string('font_group_default', 'format_aicourse')] = $first;
        }
        foreach (self::GROUPS as $group) {
            $label = get_string('font_group_' . $group, 'format_aicourse');
            foreach (self::FONTS as $key => $font) {
                if ($font[3] === $group) {
                    $menu[$label][$key] = $font[0];
                }
            }
        }
        return $menu;
    }

    /**
     * The CSS font-family value for a font.
     *
     * @param string $key A font key.
     * @return string e.g. '"Open Sans", sans-serif', or '' for an unknown key.
     */
    public static function stack(string $key): string {
        if (!self::is_font($key)) {
            return '';
        }
        [$family, , $generic] = self::FONTS[$key];
        return '"' . $family . '", ' . $generic;
    }

    /**
     * The Google Fonts stylesheet URL for one font.
     *
     * @param string $key A font key.
     * @param string $text When set, only these characters are requested (for a preview).
     * @return string The URL, or '' for an unknown key.
     */
    public static function stylesheet_url(string $key, string $text = ''): string {
        if (!self::is_font($key)) {
            return '';
        }
        [$family, $weights] = self::FONTS[$key];
        $url = 'https://fonts.googleapis.com/css2?family=' . str_replace(' ', '+', $family)
            . ':wght@' . implode(';', $weights) . '&display=swap';
        if ($text !== '') {
            $url .= '&text=' . rawurlencode($text);
        }
        return $url;
    }

    /**
     * What a course resolves to.
     *
     * @param array $options The course's format options.
     * @return array ['body' => key or '', 'heading' => key or '', 'scope' => 'content'|'page'];
     *               '' means the theme's own font.
     */
    public static function for_course(array $options): array {
        $sitebody = (string) get_config('format_aicourse', 'font');
        $sitebody = self::is_font($sitebody) ? $sitebody : '';
        $siteheading = (string) get_config('format_aicourse', 'headingfont');
        $siteheading = self::is_font($siteheading) ? $siteheading : '';

        $coursebody = (string) ($options['font'] ?? '');
        if ($coursebody === self::THEME) {
            $body = '';
        } else if (self::is_font($coursebody)) {
            $body = $coursebody;
        } else {
            $body = $sitebody;
        }

        $courseheading = (string) ($options['headingfont'] ?? '');
        if ($courseheading === self::SAME) {
            $heading = $body;
        } else if (self::is_font($courseheading)) {
            $heading = $courseheading;
        } else {
            // The site's heading font when it set one; otherwise headings follow the body font.
            $heading = $siteheading !== '' ? $siteheading : $body;
        }

        $scope = get_config('format_aicourse', 'fontscope') === 'page' ? 'page' : 'content';
        return ['body' => $body, 'heading' => $heading, 'scope' => $scope];
    }

    /**
     * The <link> tags for the fonts in use: one request per font.
     *
     * @param string[] $keys Font keys; duplicates and unknown keys are ignored.
     * @return string
     */
    public static function links(array $keys): string {
        $html = '';
        $keys = array_values(array_unique(array_filter($keys, [self::class, 'is_font'])));
        if (!$keys) {
            return '';
        }
        $html .= '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        $html .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        foreach ($keys as $key) {
            $html .= '<link rel="stylesheet" href="' . s(self::stylesheet_url($key)) . '">' . "\n";
        }
        return $html;
    }

    /**
     * The CSS that applies a resolved choice.
     *
     * @param array $choice Output of {@see self::for_course()}.
     * @return string CSS, or '' when the theme's fonts apply throughout.
     */
    public static function css(array $choice): string {
        $roots = $choice['scope'] === 'page'
            ? ['body.format-aicourse']
            : ['body.format-aicourse #page', 'body.format-aicourse .drawer', 'body.format-aicourse #aicourse-ai-chatbox'];
        $css = '';
        if ($choice['body'] !== '') {
            $stack = self::stack($choice['body']);
            $css .= implode(', ', $roots) . ' {--acf-font-family: ' . $stack . '; font-family: ' . $stack . ';}' . "\n";
        }
        if ($choice['heading'] !== '' && $choice['heading'] !== $choice['body']) {
            $stack = self::stack($choice['heading']);
            $selectors = array_map(
                // The hero's course title is a span, not a heading element, so it is named.
                // `:not(#_acf_never)` matches everything and adds an id's worth of specificity, so the
                // heading font beats the components' own `font-family: var(--acf-font-family)` (which
                // carries the body font) in both scopes, as styles.css does for its own pinned pairs.
                fn($root) => $root . ':not(#_acf_never) :is(h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6, '
                    . '.aicourse-hero-title)',
                $roots
            );
            $css .= implode(', ', $selectors) . ' {font-family: ' . $stack . ';}' . "\n";
        }
        return $css;
    }

    /**
     * Everything to add to the page head for a course: the font links and the CSS.
     *
     * @param array $options The course's format options.
     * @return string HTML, or '' when the theme's fonts apply.
     */
    public static function head_html(array $options): string {
        $choice = self::for_course($options);
        $css = self::css($choice);
        if ($css === '') {
            return '';
        }
        // Every value in the CSS comes from self::FONTS, never from a setting, so it needs no escaping.
        return self::links([$choice['body'], $choice['heading']])
            . '<style id="aicourse-fonts">' . "\n" . $css . '</style>' . "\n";
    }
}
