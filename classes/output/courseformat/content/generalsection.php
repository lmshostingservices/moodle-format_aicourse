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

namespace format_aicourse\output\courseformat\content;

use context_course;
use core\output\named_templatable;
use course_modinfo;
use format_aicourse\local\activityinfo;
use renderable;
use renderer_base;
use section_info;
use stdClass;
use Throwable;

/**
 * ACF-FIX-2.0 — THE HEADLINE FIX. Render the General section (section 0) above the card grid.
 *
 * Two compounding defects made section 0 render as an empty block on the course home page:
 *
 *  1. The "does this section have content?" probe required $s0cm->url. mod_label ("Text and
 *     media area") and mod_subsection both have a NULL url, so a General section whose content
 *     is a welcome label failed the gate and the ENTIRE block — wrapper, summary and all — was
 *     omitted. The summary probe used strip_tags(), so an image-only or iframe-only summary was
 *     also treated as empty. Both probes are fixed here: visibility is tested with
 *     uservisible + is_visible_on_course_page(), and the summary with a plain trim().
 *
 *  2. Even when the gate passed, the block rendered activity cards unconditionally — ignoring
 *     $options['activitydisplaymode'] and $PAGE->user_is_editing() — and that renderer dropped
 *     every url-less cm. Content could therefore silently vanish. Section 0 now falls back to the
 *     core section renderer whenever the card mode is off or a teacher is editing, so nothing is
 *     ever hidden and teachers keep every edit control.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generalsection implements named_templatable, renderable {
    /** @var stdClass Course record. */
    protected $course;

    /** @var section_info The General section. */
    protected $section0;

    /** @var array Course format options. */
    protected $options;

    /** @var course_modinfo Pre-loaded modinfo for the current user. */
    protected $modinfo;

    /** @var context_course Course context. */
    protected $coursecontext;

    /**
     * Constructor.
     *
     * @param stdClass $course Course record.
     * @param section_info $section0 The General section.
     * @param array $options Course format options.
     * @param course_modinfo $modinfo Pre-loaded modinfo for the current user.
     * @param context_course $coursecontext Course context.
     */
    public function __construct($course, $section0, array $options, $modinfo, $coursecontext) {
        $this->course = $course;
        $this->section0 = $section0;
        $this->options = $options;
        $this->modinfo = $modinfo;
        $this->coursecontext = $coursecontext;
    }

    /**
     * Get the name of the template to use for this templatable.
     *
     * @param renderer_base $renderer The renderer requesting the template name.
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_aicourse/general_section';
    }

    /**
     * Export the data for the mustache template.
     *
     * Pre-escaped values in the returned context, all rendered with a triple mustache:
     *  - corecontent: HTML rendered by core's own section output class.
     *  - sectionname: already passed through format_string().
     *  - summary: already passed through format_text() with the course context.
     *
     * @param renderer_base $output Typically, the renderer that is calling this function.
     * @return stdClass Data context for format_aicourse/general_section.
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $PAGE;

        $course = $this->course;
        $section0 = $this->section0;
        $options = $this->options;
        $modinfo = $this->modinfo;
        $coursecontext = $this->coursecontext;

        $data = (object) [
            'hasoutput' => false,
            'usecoresection' => false,
            'corecontent' => '',
            'headingclass' => '',
            'sectionname' => '',
            'hassummary' => false,
            'summary' => '',
            'activitycards' => null,
        ];

        // Content probe — visibility, NOT the presence of a url.
        $hascontent = false;
        $section0cmids = isset($modinfo->sections[0]) ? $modinfo->sections[0] : [];
        foreach ($section0cmids as $s0cmid) {
            if (activityinfo::cm_counts_as_content($modinfo->get_cm($s0cmid))) {
                $hascontent = true;
                break;
            }
        }

        // Summary probe — a raw trim(), so an image-only or iframe-only summary still counts.
        $hassummary = (trim((string) ($section0->summary ?? '')) !== '');

        $isediting = $PAGE->user_is_editing();
        $usecards = !empty($options['activitydisplaymode']);

        if (!$hascontent && !$hassummary && !$isediting) {
            return $data;
        }

        $data->hasoutput = true;

        // Edit mode, or "standard activity display" — hand the whole section to the core renderer so
        // teachers get drag handles, action menus and "Add an activity or resource", and so students
        // see exactly what core would show. This is the fallback that guarantees nothing vanishes.
        if ($isediting || !$usecards) {
            try {
                $format = course_get_format($course);
                $sectionclass = $format->get_output_classname('content\\section');
                $renderer = $PAGE->get_renderer('format_aicourse');
                $widget = new $sectionclass($format, $section0);
                $data->usecoresection = true;
                $data->corecontent = $renderer->render($widget);
                return $data;
            } catch (Throwable $e) {
                // Never let a renderer problem blank the General section — fall through to the
                // card/summary rendering below instead.
                $data->usecoresection = false;
                $data->corecontent = '';
                debugging('format_aicourse: core section renderer failed for section 0: '
                    . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // ACF-FIX-2.0: a11y — a real <h2> so the <h3> card titles and <h3> activity names below do
        // not hang off the page <h1> with a level skipped. When the teacher has not renamed the
        // section the default name ("General") is kept out of the visual design with .accesshide,
        // which is a core Moodle visually-hidden class.
        $sectionname = get_section_name($course, $section0);
        $hascustomname = (trim((string) ($section0->name ?? '')) !== '');
        $data->headingclass = 'aicourse-general-heading' . ($hascustomname ? '' : ' accesshide');
        $data->sectionname = format_string($sectionname);

        if ($hassummary) {
            $summarytext = file_rewrite_pluginfile_urls(
                $section0->summary,
                'pluginfile.php',
                $coursecontext->id,
                'course',
                'section',
                $section0->id
            );
            $data->hassummary = true;
            $data->summary = format_text($summarytext, $section0->summaryformat, ['context' => $coursecontext]);
        }

        if ($hascontent) {
            $cards = new activitycards($course, 0, $options, false);
            $data->activitycards = $cards->export_for_template($output);
        }

        return $data;
    }

    /**
     * Render the General section.
     *
     * @return string HTML, or '' when there is genuinely nothing to show.
     */
    public function out(): string {
        global $OUTPUT;

        return $OUTPUT->render($this);
    }
}
