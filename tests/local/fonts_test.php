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

use format_aicourse\admin\setting_font;
use format_aicourse\hook\before_head_html_generation;

/**
 * Tests for the Google Fonts feature.
 *
 * @package    format_aicourse
 * @category   test
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_aicourse\local\fonts
 * @covers     \format_aicourse\hook\before_head_html_generation
 * @covers     \format_aicourse\admin\setting_font
 */
#[\PHPUnit\Framework\Attributes\CoversClass(fonts::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(before_head_html_generation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(setting_font::class)]
final class fonts_test extends \advanced_testcase {
    /**
     * Every entry is well formed: 35 fonts, a known group, 400 and 700 always, a generic fallback.
     */
    public function test_the_list(): void {
        $this->assertCount(35, fonts::FONTS);
        foreach (fonts::FONTS as $key => [$family, $weights, $generic, $group]) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]+$/', $key);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9 ]+$/', $family);
            $this->assertContains(400, $weights, $family);
            $this->assertContains(700, $weights, $family);
            $this->assertSame($weights, array_values(array_unique($weights)));
            $sorted = $weights;
            sort($sorted);
            $this->assertSame($sorted, $weights, $family . ' weights must be ascending for the css2 API');
            $this->assertContains($generic, ['sans-serif', 'serif'], $family);
            $this->assertContains($group, fonts::GROUPS, $family);
        }
        $this->assertNotContains(fonts::THEME, array_keys(fonts::FONTS));
        $this->assertNotContains(fonts::SAME, array_keys(fonts::FONTS));
    }

    /**
     * Stacks and URLs are built only for list keys.
     */
    public function test_stack_and_url(): void {
        $this->assertSame('"Open Sans", sans-serif', fonts::stack('opensans'));
        $this->assertSame('"Lora", serif', fonts::stack('lora'));
        $this->assertSame('', fonts::stack('Comic Sans'));
        $this->assertSame(
            'https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&display=swap',
            fonts::stylesheet_url('atkinsonhyperlegible')
        );
        $this->assertStringEndsWith('&text=Ab%20c', fonts::stylesheet_url('inter', 'Ab c'));
        $this->assertSame('', fonts::stylesheet_url('x" onload="alert(1)'));
    }

    /**
     * The grouped menu holds every font once, after the leading choices.
     */
    public function test_menu(): void {
        $grouped = fonts::menu(['' => 'Theme font'], true);
        $this->assertSame(['' => 'Theme font'], reset($grouped));
        $all = [];
        foreach (array_slice($grouped, 1, null, true) as $options) {
            $all += $options;
        }
        $this->assertSame(array_keys(fonts::FONTS), array_keys($all));
        $flat = fonts::menu(['' => 'Theme font']);
        $this->assertCount(36, $flat);
    }

    /**
     * How the site and course values combine, and that anything outside the list is ignored.
     */
    public function test_resolution(): void {
        $this->resetAfterTest();
        $this->assertSame(['body' => '', 'heading' => '', 'scope' => 'content'], fonts::for_course([]));

        set_config('font', 'lexend', 'format_aicourse');
        $this->assertSame(['body' => 'lexend', 'heading' => 'lexend', 'scope' => 'content'], fonts::for_course([]));

        set_config('headingfont', 'playfairdisplay', 'format_aicourse');
        $this->assertSame('playfairdisplay', fonts::for_course([])['heading']);

        // The course's own choices.
        $this->assertSame('inter', fonts::for_course(['font' => 'inter'])['body']);
        $this->assertSame('', fonts::for_course(['font' => fonts::THEME])['body']);
        $this->assertSame('inter', fonts::for_course(['font' => 'inter', 'headingfont' => fonts::SAME])['heading']);
        $this->assertSame('oswald', fonts::for_course(['headingfont' => 'oswald'])['heading']);

        // Injection attempts fall back to the site's choice.
        $this->assertSame('lexend', fonts::for_course(['font' => 'x;}body{display:none'])['body']);
        $this->assertSame('playfairdisplay', fonts::for_course(['headingfont' => '"><script>'])['heading']);
        set_config('font', '"><script>', 'format_aicourse');
        $this->assertSame('', fonts::for_course([])['body']);

        set_config('fontscope', 'page', 'format_aicourse');
        $this->assertSame('page', fonts::for_course([])['scope']);
        set_config('fontscope', 'everything', 'format_aicourse');
        $this->assertSame('content', fonts::for_course([])['scope']);
    }

    /**
     * The CSS for each scope, and nothing at all while the theme's font applies.
     */
    public function test_css(): void {
        $this->assertSame('', fonts::css(['body' => '', 'heading' => '', 'scope' => 'content']));

        $css = fonts::css(['body' => 'lexend', 'heading' => 'playfairdisplay', 'scope' => 'content']);
        $this->assertStringContainsString('body.format-aicourse #page, body.format-aicourse .drawer, '
            . 'body.format-aicourse #aicourse-ai-chatbox {--acf-font-family: "Lexend", sans-serif;', $css);
        $this->assertStringContainsString(
            'body.format-aicourse #page:not(#_acf_never) :is(h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6, '
                . '.aicourse-hero-title)',
            $css
        );
        $this->assertStringContainsString('{font-family: "Playfair Display", serif;}', $css);

        $css = fonts::css(['body' => 'inter', 'heading' => 'inter', 'scope' => 'page']);
        $this->assertStringStartsWith('body.format-aicourse {--acf-font-family: "Inter", sans-serif;', $css);
        $this->assertStringNotContainsString(':is(h1', $css, 'Headings in the body font need no rule of their own');

        // Headings alone: the body text keeps the theme's font.
        $css = fonts::css(['body' => '', 'heading' => 'oswald', 'scope' => 'content']);
        $this->assertStringNotContainsString('--acf-font-family', $css);
        $this->assertStringContainsString('"Oswald", sans-serif', $css);
    }

    /**
     * One request per font, and none twice.
     */
    public function test_links(): void {
        $this->assertSame('', fonts::links(['', 'nope']));
        $html = fonts::links(['lora', 'lora', 'inter']);
        $this->assertSame(2, substr_count($html, 'rel="stylesheet"'));
        $this->assertStringContainsString('family=Lora:wght@400;500;600;700&amp;display=swap', $html);
        $this->assertStringContainsString('rel="preconnect" href="https://fonts.gstatic.com" crossorigin', $html);
    }

    /**
     * The head hook runs on this format's courses only, never the front page or another format.
     */
    public function test_hook(): void {
        global $COURSE;
        $this->resetAfterTest();
        set_config('font', 'lexend', 'format_aicourse');

        $run = function (\stdClass $course): string {
            global $COURSE;
            $COURSE = $course;
            $hook = new \core\hook\output\before_standard_head_html_generation(
                new \core_renderer(new \moodle_page(), RENDERER_TARGET_GENERAL)
            );
            before_head_html_generation::callback($hook);
            return $hook->get_output();
        };

        $course = $this->getDataGenerator()->create_course(['format' => 'aicourse']);
        $html = $run(get_course($course->id));
        $this->assertStringContainsString('family=Lexend', $html);
        $this->assertStringContainsString('<style id="aicourse-fonts">', $html);

        // The course's own choice wins.
        course_get_format($course)->update_course_format_options(['id' => $course->id, 'font' => 'lora']);
        $html = $run(get_course($course->id));
        $this->assertStringContainsString('family=Lora', $html);
        $this->assertStringNotContainsString('family=Lexend', $html);

        course_get_format($course)->update_course_format_options(['id' => $course->id, 'font' => fonts::THEME]);
        $this->assertSame('', $run(get_course($course->id)));

        $topics = $this->getDataGenerator()->create_course(['format' => 'topics']);
        $this->assertSame('', $run(get_course($topics->id)));
        $this->assertSame('', $run(get_site()));
        $COURSE = get_site();
    }

    /**
     * The admin setting stores only '' or a list key, and renders a preview.
     */
    public function test_admin_setting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $setting = new setting_font('format_aicourse/font', 'Font', 'Desc', 'Theme font');

        $this->assertSame('', $setting->write_setting('merriweather'));
        $this->assertSame('merriweather', get_config('format_aicourse', 'font'));
        $this->assertSame('', $setting->write_setting(''));
        $this->assertSame('', get_config('format_aicourse', 'font'));
        $this->assertSame(
            get_string('error_fontunknown', 'format_aicourse'),
            $setting->write_setting('</style><script>')
        );
        $this->assertSame('', get_config('format_aicourse', 'font'));

        $html = $setting->output_html('merriweather');
        $this->assertStringContainsString('<optgroup', $html);
        $this->assertStringContainsString('data-aicourse-fontpreview="id_s_format_aicourse_font"', $html);
        $this->assertStringContainsString('\\&quot;Merriweather\\&quot;, serif', $html);
        $this->assertStringContainsString('&amp;text=', $html);
        // The preview stylesheets are added once per page.
        $again = (new setting_font('format_aicourse/headingfont', 'Headings', 'Desc', 'Same'))->output_html('');
        $this->assertStringNotContainsString('fonts.googleapis.com', $again);
    }
}
