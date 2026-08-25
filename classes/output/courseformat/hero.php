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

use completion_info;
use context_course;
use core\output\named_templatable;
use format_aicourse\local\activityinfo;
use format_aicourse\local\banner;
use format_aicourse\local\navigation;
use format_aicourse\local\permissions;
use format_aicourse\local\progress;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;

/**
 * The course / section hero banner.
 *
 * Renders format_aicourse/hero. The same class serves both variants of the banner:
 *
 *  - the course home page hero (no section number), which shows the course title, an optional
 *    summary in image mode, the section/activity/time meta row, a course progress ring and a
 *    decorative progress bar;
 *  - the section page hero (a section number), which shows the section title, previous/next
 *    section chevrons and one numbered circle per visible activity instead of the bar.
 *
 * Pre-escaped values in the exported context — these are the ONLY values the template may render
 * with a triple mustache, and each is documented again in templates/hero.mustache:
 *
 *  - title            {@see format_string()} / {@see get_section_name()} output.
 *  - prevurl, nexturl {@see moodle_url::out()} output (ampersands already entity-encoded).
 *  - gradesurl, homeurl  ditto.
 *
 * Everything else in the context is plain text or an integer and is escaped by the template with
 * a double mustache, which is exactly the `s()` call the pre-template string builder applied.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hero implements named_templatable, renderable {
    /** @var int Radius of the progress ring circle inside the 200x200 viewBox. */
    protected const RING_RADIUS = 90;

    /** @var int Stroke width of the progress ring, in viewBox units. */
    protected const RING_STROKE = 12;

    /** @var stdClass Course record. */
    protected $course;

    /** @var array Course format options. */
    protected $options;

    /** @var int|null Section number when rendering a section hero, null on the course home page. */
    protected $sectionnum;

    /**
     * Constructor.
     *
     * @param stdClass $course Course record.
     * @param array $options Course format options.
     * @param int|null $sectionnum Section number, or null for the course home page.
     */
    public function __construct(stdClass $course, array $options, $sectionnum = null) {
        $this->course = $course;
        $this->options = $options;
        $this->sectionnum = $sectionnum;
    }

    /**
     * Name of the template this renderable renders with.
     *
     * @param renderer_base $renderer The renderer requesting the template name.
     * @return string Template name.
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_aicourse/hero';
    }

    /**
     * Export the hero banner data for the template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Template context.
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER;

        $course = $this->course;
        $options = $this->options;
        $sectionnum = $this->sectionnum;

        // Custom banner image takes priority; fall back to course overview image.
        // Track custom banner separately so we can show the delete button only for custom images.
        $custombanner = banner::get_banner_image_url($course);
        $imageurl = $custombanner;
        if (!$imageurl) {
            $imageurl = banner::get_course_image($course);
        }

        // Get section info first if viewing a section.
        $modinfo = get_fast_modinfo($course, $USER->id);
        $sectioninfo = null;
        $titletext = format_string($course->fullname);

        if ($sectionnum !== null) {
            $sectioninfo = $modinfo->get_section_info($sectionnum);
            if ($sectioninfo) {
                $titletext = get_section_name($course, $sectioninfo);
            }
        }

        // Use section-specific progress when viewing a section, otherwise course progress.
        // Create one shared completion_info here so both get_section_progress() and
        // get_progress() reuse the same in-memory bulk-loaded completion data.
        $completioninfo = new completion_info($course);

        if ($sectioninfo !== null) {
            $progressdata = progress::get_section_progress($course, $sectioninfo, $USER->id, $completioninfo);
            // FIX-ACF-NAVCHEVRONS (v1.7.48): Only build nav links when the setting is enabled.
            // Previously the shownavchevrons option was stored but never consulted here, so the
            // arrows always appeared even when the teacher set "Show navigation chevrons = No".
            if (!empty($options['shownavchevrons'])) {
                $navdata = navigation::get_section_nav_links($course, $sectionnum, $modinfo);
            } else {
                $navdata = ['prev' => null, 'next' => null];
            }
        } else {
            // ACF-FIX-2.0: $needactivities = false — the course-home hero only reads the percentage
            // and the completed/total counters; the per-activity array was built and discarded.
            $progressdata = progress::get_progress($course, $USER->id, $completioninfo, false);
            // Course home has no prev/next navigation.
            $navdata = ['prev' => null, 'next' => null];
        }

        // ACF-FIX-2.0: herobannerheight was emitted as an inline `max-height`, i.e. a cap. A cap is
        // the wrong control: it clipped wrapped titles and the numbered activity circles, so the
        // stylesheet had to neutralise it — which made the setting inert at every value. It is now
        // published as a custom property and consumed by the stylesheet as a *floor*
        // (min-block-size: max(--acf-hero-min-h, --acf-hero-h)), so the teacher's chosen height is
        // honoured in both gradient and image mode and content can still grow past it.
        $data = (object) [
            // ACF-FIX-2.0: a11y — the hero is a named region so it can be reached (and skipped)
            // with landmark navigation. The wrapper class is unchanged.
            // ACF-FIX-2.1.23: the hero is rendered outside .format-aicourse-container, so it
            // cannot inherit the accent custom properties that format.php puts there. It
            // carries its own copy. Validated in format_aicourse::get_accent_style().
            'accentstyle' => \format_aicourse::get_accent_style($options),
            'title' => $titletext,
            // ACF-FIX-2.1.26: size tiers, computed here because CSS cannot count characters.
            // The banner gives the title a FIXED zone of two lines; rather than truncate a long
            // name to fit it, the type scale steps down so the whole name is shown. Thresholds
            // are character counts on the plain text, chosen so each tier still fills roughly
            // two lines at the width the zone gets on a 1400px shell.
            'titlesize' => self::size_tier(
                \core_text::strlen(html_to_text($titletext, 0, false)),
                [28 => 'xl', 48 => 'lg', 72 => 'md', 104 => 'sm']
            ),
            'hasimage' => !empty($imageurl),
            'imageurl' => (string) $imageurl,
            'issection' => ($sectioninfo !== null),
            'hassummary' => false,
            'summary' => '',
            'hasmeta' => false,
            'metaparts' => [],
        ];

        // In image mode, show course summary below the title on the course home page.
        // CSS handles the 2-line visual clamp; PHP just strips HTML tags.
        if (!empty($imageurl) && $sectioninfo === null) {
            $summaryclean = trim(strip_tags((string) $course->summary));
            if ($summaryclean !== '') {
                $data->hassummary = true;
                $data->summary = $summaryclean;
            }
        }

        if ($sectioninfo === null) {
            $data->metaparts = $this->export_meta($modinfo);
            $data->hasmeta = !empty($data->metaparts);
        }

        $this->export_nav($data, $navdata);
        $this->export_progress($data, $progressdata, $modinfo, $sectioninfo);
        $this->export_icons($data, $custombanner);

        return $data;
    }

    /**
     * Build the course-home meta row: section count, activity count and total estimated time.
     *
     * ACF-FIX-2.0: Without it the gradient hero contains only a title and a progress ring, leaving
     * a wide empty band between them at desktop widths that no amount of CSS can justify. Each item
     * becomes a separate <span> so the stylesheet can insert the separators; the strings use {$a}
     * placeholders so translations control word order.
     *
     * Counted from modinfo rather than from the progress data, so the row is still correct on
     * courses where completion tracking is switched off (the progress total counts only
     * completion-tracked activities and is zero in that case).
     *
     * @param \course_modinfo $modinfo Course modinfo for the current user.
     * @return array List of ['text' => string] entries, in display order.
     */
    protected function export_meta($modinfo): array {
        $metaparts = [];
        $sectioncount = 0;
        $activitycount = 0;
        $totalminutes = 0;

        foreach (activityinfo::get_listed_sections($modinfo) as $metasection) {
            if ($metasection->section <= 0 || !$metasection->uservisible) {
                continue;
            }
            $sectioncount++;
            $metacmids = isset($modinfo->sections[$metasection->section])
                ? $modinfo->sections[$metasection->section]
                : [];
            foreach ($metacmids as $metacmid) {
                $metacm = $modinfo->get_cm($metacmid);
                if (!$metacm->uservisible) {
                    continue;
                }
                if (activityinfo::cm_counts_as_content($metacm)) {
                    $activitycount++;
                }
                $totalminutes += progress::estimate_activity_minutes($metacm);
            }
        }

        if ($sectioncount > 0) {
            $metaparts[] = ['text' => get_string(
                $sectioncount == 1 ? 'onesection' : 'nsections',
                'format_aicourse',
                $sectioncount
            )];
        }
        if ($activitycount > 0) {
            $metaparts[] = ['text' => get_string(
                $activitycount == 1 ? 'oneactivity' : 'nactivities',
                'format_aicourse',
                $activitycount
            )];
        }
        $totaltime = progress::format_estimated_time($totalminutes);
        if ($totaltime !== '') {
            $metaparts[] = ['text' => $totaltime];
        }

        return $metaparts;
    }

    /**
     * Add the previous/next section chevrons to the context.
     *
     * ACF-FIX-2.0: a11y — icon-only chevrons had only title="<destination name>", so the previous
     * and next links were indistinguishable by direction. aria-label now names both the direction
     * and the destination using the (previously dead) previoussection/nextsection lang strings.
     *
     * @param stdClass $data Context being built, modified in place.
     * @param array $navdata Navigation links with 'prev' and 'next' keys.
     * @return void
     */
    protected function export_nav(stdClass $data, array $navdata): void {
        $data->hasprev = !empty($navdata['prev']);
        $data->prevurl = '';
        $data->prevname = '';
        $data->prevlabel = '';
        if ($data->hasprev) {
            $data->prevurl = $navdata['prev']['url'];
            $data->prevname = $navdata['prev']['name'];
            $data->prevlabel = get_string('previoussectionnamed', 'format_aicourse', $navdata['prev']['name']);
        }

        $data->hasnext = !empty($navdata['next']);
        $data->nexturl = '';
        $data->nextname = '';
        $data->nextlabel = '';
        if ($data->hasnext) {
            $data->nexturl = $navdata['next']['url'];
            $data->nextname = $navdata['next']['name'];
            $data->nextlabel = get_string('nextsectionnamed', 'format_aicourse', $navdata['next']['name']);
        }
    }

    /**
     * Pick a size tier for a piece of content whose length is only known at render time.
     *
     * @param int $measure The thing being measured -- a character count, or a number of items.
     * @param array $thresholds Map of upper-bound => tier name, in ascending order of bound.
     *                          The first bound the measure falls within wins.
     * @return string The tier name, or 'xs' when the measure exceeds every bound.
     */
    public static function size_tier(int $measure, array $thresholds): string {
        foreach ($thresholds as $bound => $tier) {
            if ($measure <= $bound) {
                return $tier;
            }
        }
        return 'xs';
    }

    /**
     * Add the progress ring, the course progress bar and the numbered activity circles.
     *
     * ACF-FIX-2.0: a11y — the ring is exposed as a real progressbar. It previously carried no role
     * and no ARIA values, and the visible text node was hard-coded to "0%" with the true figure
     * only applied later by JS, so screen readers always announced 0%.
     *
     * @param stdClass $data Context being built, modified in place.
     * @param array $progressdata Progress data from \format_aicourse\local\progress.
     * @param \course_modinfo $modinfo Course modinfo for the current user.
     * @param \section_info|null $sectioninfo Section being displayed, or null on the course home page.
     * @return void
     */
    protected function export_progress(stdClass $data, array $progressdata, $modinfo, $sectioninfo): void {
        $data->progressenabled = !empty($progressdata['enabled']);
        $data->hascircles = false;
        $data->circles = [];
        if (!$data->progressenabled) {
            return;
        }

        $percentage = (int) $progressdata['percentage'];
        $circumference = 2 * M_PI * self::RING_RADIUS;
        $offset = $circumference - ($percentage / 100) * $circumference;

        $data->percentage = $percentage;
        $data->iscomplete = ($percentage >= 100);
        $data->radius = self::RING_RADIUS;
        $data->strokewidth = self::RING_STROKE;
        // Cast to string here so the template emits the same precision the string builder did.
        $data->circumference = (string) $circumference;
        $data->targetoffset = (string) $offset;

        $data->progresslabel = ($sectioninfo !== null)
            ? get_string('sectionprogress', 'format_aicourse')
            : get_string('courseprogress', 'format_aicourse');
        $data->progresstext = get_string('percentcomplete', 'format_aicourse', $percentage);
        // Short form for the visible node inside the ring — the long form would overflow it.
        $data->progressshort = get_string('percentvalue', 'format_aicourse', $percentage);
        $data->countertext = get_string('completedof', 'format_aicourse', (object) [
            'completed' => (int) $progressdata['completed'],
            'total'     => (int) $progressdata['total'],
        ]);

        if ($sectioninfo === null) {
            return;
        }

        // Section view: numbered circles — one per visible activity in this section.
        // White = not started, amber = in progress, green = completed,
        // dimmed white = no completion tracking on this activity.
        $sectioncmids = isset($modinfo->sections[$sectioninfo->section])
            ? $modinfo->sections[$sectioninfo->section]
            : [];
        // Build a quick lookup of cmid → status from the progress data.
        $actstatusmap = [];
        foreach ($progressdata['activities'] as $act) {
            $actstatusmap[$act['id']] = $act['status'];
        }
        $actnum = 1;
        foreach ($sectioncmids as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if (!$cm->uservisible) {
                continue;
            }
            $status = isset($actstatusmap[$cmid]) ? $actstatusmap[$cmid] : 'no_completion';
            // ACF-FIX-2.0: a11y — the circles conveyed completion status by colour alone and their
            // only accessible name was the digit. The status is now part of the accessible name
            // (WCAG 1.4.1 / 2.4.4).
            $data->circles[] = [
                'num' => $actnum,
                'statusclass' => 'aicourse-number-' . $status,
                'islink' => (bool) $cm->url,
                'url' => $cm->url ? $cm->url->out() : '',
                'name' => format_string($cm->name),
                'label' => get_string('activitynumberstatus', 'format_aicourse', (object) [
                    'num'    => $actnum,
                    'name'   => format_string($cm->name),
                    'status' => activityinfo::get_status_label($status),
                ]),
            ];
            $actnum++;
        }
        $data->hascircles = !empty($data->circles);
        // ACF-FIX-2.1.26: the circles get a fixed zone of two rows. Rather than let a long
        // section overflow it or scroll, the circle size steps down as the count rises so the
        // whole set is visible. CSS cannot count children into a size, so the tier is decided
        // here alongside the data it describes.
        $data->circlesize = self::size_tier(count($data->circles), [6 => 'xl', 12 => 'lg', 20 => 'md', 30 => 'sm']);
    }

    /**
     * Add the icon rail: grades, AI assistant, home and the banner buttons.
     *
     * FIX-GRADES-LINK (v1.7.54): Teachers (grade/report:viewall) go to the grader report; everyone
     * else goes to their own user grade report.
     *
     * ACF-FIX-2.0: a11y — every icon-only control carries an aria-label sourced from a lang string.
     * title alone is not exposed reliably to touch screen readers or voice control and is never
     * shown on keyboard focus.
     *
     * @param stdClass $data Context being built, modified in place.
     * @param string|null $custombanner URL of the uploaded custom banner, or null when there is none.
     * @return void
     */
    protected function export_icons(stdClass $data, $custombanner): void {
        global $PAGE;

        $course = $this->course;
        $context = context_course::instance($course->id);

        if (has_capability('moodle/grade:viewall', $context, null, false)) {
            $gradesurl = new moodle_url('/grade/report/grader/index.php', ['id' => $course->id]);
        } else {
            $gradesurl = new moodle_url('/grade/report/user/index.php', ['id' => $course->id]);
        }

        $data->courseid = (int) $course->id;
        $data->sesskey = sesskey();
        $data->gradesurl = $gradesurl->out();
        $data->gradeslabel = get_string('grades', 'format_aicourse');
        $data->showtutor = permissions::is_tutor_enabled();
        $data->aiassistantlabel = get_string('aiassistant', 'format_aicourse');
        $data->homeurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out();
        $data->homelabel = get_string('gotocourse', 'format_aicourse');

        // AI Generate Banner button — editors only. The delete button additionally needs an
        // uploaded custom banner AND Moodle to be in edit mode.
        $data->canedit = has_capability('moodle/course:update', $context);
        $data->showremovebanner = ($data->canedit && !empty($custombanner) && $PAGE->user_is_editing());
        $data->removebannerlabel = get_string('removebannerimage', 'format_aicourse');
        $data->generatebannerlabel = get_string('generatebannerimage', 'format_aicourse');
        // ACF-FIX-2.1.191: plain text. This is read back out of data-coursename by
        // courseformat.js and written into the modal with jQuery .text(), which does not parse
        // markup -- the escaped form showed as "&amp;" in the Generate AI banner dialog.
        $data->coursename = \format_aicourse\local\text::plain($course->fullname, $context);
        $data->shortname = $course->shortname;
    }

    /**
     * Render the hero banner.
     *
     * The AI Assistant chatbox markup is appended after the banner, exactly where the pre-template
     * string builder emitted it. It is a sibling of the hero <section>, not a child, so it stays
     * out of the hero template and keeps its own renderable.
     *
     * @return string HTML.
     */
    public function out(): string {
        global $OUTPUT;

        return $OUTPUT->render($this) . (new chatbox())->out();
    }
}
