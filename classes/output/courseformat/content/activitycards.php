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

use cm_info;
use completion_info;
use core\output\named_templatable;
use format_aicourse\local\activityinfo;
use format_aicourse\local\progress;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;

/**
 * The activity card grid for a single section.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activitycards implements named_templatable, renderable {
    /** @var stdClass Course record. */
    protected $course;

    /** @var int Section number. */
    protected $sectionnum;

    /** @var array Course format options. */
    protected $options;

    /**
     * ACF-FIX-2.0: render the region <h2>. False when the caller (the General block) already
     * emitted a heading for this section.
     *
     * @var bool
     */
    protected $showheading;

    /**
     * Constructor.
     *
     * @param stdClass $course Course record.
     * @param int $sectionnum Section number.
     * @param array $options Course format options.
     * @param bool $showheading Render the region <h2>; false when the caller already emitted one.
     */
    public function __construct($course, $sectionnum, array $options, $showheading = true) {
        $this->course = $course;
        $this->sectionnum = $sectionnum;
        $this->options = $options;
        $this->showheading = $showheading;
    }

    /**
     * Get the name of the template to use for this templatable.
     *
     * @param renderer_base $renderer The renderer requesting the template name.
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_aicourse/activity_cards';
    }

    /**
     * Export the data for the mustache template.
     *
     * Pre-escaped values in the returned context, all rendered with a triple mustache:
     *  - sectionname: already passed through format_string().
     *  - cards[].name: already passed through format_string().
     *  - contentrows[].content: already passed through cm_info::get_formatted_content(), which
     *    runs format_text() with the activity context.
     *
     * @param renderer_base $output Typically, the renderer that is calling this function.
     * @return stdClass Data context for format_aicourse/activity_cards.
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER;

        $course = $this->course;
        $sectionnum = $this->sectionnum;

        $data = (object) [
            'notfound' => false,
            'regionlabel' => '',
            'showheading' => (bool) $this->showheading,
            'sectionname' => '',
            'sectionid' => 0,
            'cards' => [],
            'contentrows' => [],
            'isempty' => false,
        ];

        $modinfo = get_fast_modinfo($course, $USER->id);
        $section = $modinfo->get_section_info($sectionnum);

        if (!$section) {
            $data->notfound = true;
            return $data;
        }

        $info = new completion_info($course);
        $completionenabled = $info->is_enabled();

        $sectionname = get_section_name($course, $section);

        // ACF-FIX-2.0: a11y — the container is a named region and carries a real <h2> so that the
        // activity names below (now <h3>, previously <h4>) sit at the correct depth under the page
        // <h1>. On a section page these cards render standalone, so without this the document went
        // straight from h1 to h4.
        // ACF-FIX-2.1.17: a11y — this region's name must differ from the hero's. The hero above is
        // a <section aria-label="{section name}">, which is itself a landmark, so labelling this
        // region with the bare section name too gave two landmarks the identical accessible name.
        // A screen reader user cycling landmarks heard the same title twice with no way to tell
        // the banner from the activity list. Flagged by axe-core as landmark-unique.
        $data->regionlabel = get_string('sectionactivitiesregion', 'format_aicourse', $sectionname);
        $data->sectionname = format_string($sectionname);
        $data->sectionid = (int) $section->id;

        $activitycount = 0;

        // Get activity IDs directly from the section (safer than comparing section numbers).
        $sectioncmids = isset($modinfo->sections[$sectionnum]) ? $modinfo->sections[$sectionnum] : [];

        // Bulk-load ALL completion rows for this user + course in one SELECT before the loop.
        // Passing $wholecourse=true on the first get_data() call triggers a single SELECT of
        // every course_modules_completion row, caching them inside $info. Every subsequent
        // get_data($cm, false, ...) call in the loop is then served from that in-memory cache
        // with zero additional DB queries — fixing the N+1 pattern that ran one SELECT per activity.
        if ($completionenabled) {
            foreach ($sectioncmids as $bulkcmid) {
                $bulkcm = $modinfo->get_cm($bulkcmid);
                if ($bulkcm->completion != COMPLETION_TRACKING_NONE) {
                    $info->get_data($bulkcm, true, $USER->id); // Wholecourse = true triggers the bulk SELECT.
                    break;
                }
            }
        }

        foreach ($sectioncmids as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            // ACF-FIX-2.0: one shared predicate with progress::get_section_progress(), so the
            // "N activities" badge can never disagree with what is actually rendered here.
            if (!activityinfo::cm_counts_as_content($cm)) {
                continue;
            }

            if (!$cm->url) {
                // FIX-ACF-SUBSECTION (v1.7.48): Moodle 4.4+ introduces mod_subsection — a
                // special module type that acts as a container for a child section rather than
                // a standalone activity. These cms appear in the parent section's cmids list
                // but have $cm->url === null, so the old "skip labels" guard silently dropped
                // them. Detect the 'subsection' modname, resolve the delegated child section,
                // and render it as a clickable section-navigation card.
                if ($cm->modname === 'subsection') {
                    $card = $this->export_subsection_card($cm);
                    if ($card !== null) {
                        $activitycount++;
                        $data->cards[] = $card;
                    }
                    continue;
                }

                // ACF-FIX-2.0 — HEADLINE FIX (part 2). Everything else without a url is a content
                // module: mod_label ("Text and media area") and anything behaving like it. These used
                // to be dropped silently, which is how a General section whose only content was a
                // welcome label rendered completely empty. They are now rendered as full-width
                // content blocks (not cards) and DO count toward the activity total.
                // ACF-FIX-2.0: url-less content modules are rendered as full-width content rows
                // AFTER the card grid, so they do not disturb the grid layout.
                $labelcontent = $cm->get_formatted_content(['overflowdiv' => true, 'noclean' => true]);
                if (trim((string) $labelcontent) === '') {
                    // Fall back to the module name so an empty label is still visible rather than
                    // producing a blank row.
                    $labelcontent = format_string($cm->name);
                }
                if (trim((string) $labelcontent) !== '') {
                    $activitycount++;
                    $data->contentrows[] = (object) [
                        'cmid' => (int) $cm->id,
                        'content' => $labelcontent,
                    ];
                }
                continue;
            }

            $activitycount++;
            $data->cards[] = $this->export_activity_card($cm, $info, $completionenabled);
        }

        $data->isempty = ($activitycount === 0);

        return $data;
    }

    /**
     * Build the template context for a mod_subsection navigation card.
     *
     * @param cm_info $cm The subsection course module.
     * @return stdClass|null Context for format_aicourse/activity_card, or null when the delegated
     *                       section is not visible to this user.
     */
    protected function export_subsection_card(cm_info $cm): ?stdClass {
        $delegated = activityinfo::get_delegated_section($cm);
        if (!$delegated || !$delegated->uservisible) {
            return null;
        }

        $subsectionurl = new moodle_url(
            '/course/view.php',
            ['id' => $this->course->id, 'section' => $delegated->section]
        );

        return (object) [
            'issubsection' => true,
            'cmid' => (int) $cm->id,
            'url' => $subsectionurl->out(false),
            'status' => 'not_started',
            'haslabel' => false,
            'cardlabel' => '',
            'iconurl' => '',
            // ACF-FIX-2.0: a11y — h4 demoted to h3 under the new region <h2>.
            'name' => format_string(get_section_name($this->course, $delegated)),
            'typename' => get_string('section', 'moodle'),
            'iscompleted' => false,
            'statuslabel' => '',
            'badgeclass' => '',
        ];
    }

    /**
     * Build the template context for a normal activity card.
     *
     * @param cm_info $cm The course module.
     * @param completion_info $info Shared, bulk-loaded completion data.
     * @param bool $completionenabled True when completion tracking is on for this course.
     * @return stdClass Context for format_aicourse/activity_card.
     */
    protected function export_activity_card(cm_info $cm, completion_info $info, bool $completionenabled): stdClass {
        global $USER;

        // Get completion status.
        $status = 'not_started';

        if ($completionenabled && $cm->completion != COMPLETION_TRACKING_NONE) {
            $data = $info->get_data($cm, false, $USER->id);
            if ($data->completionstate == COMPLETION_COMPLETE || $data->completionstate == COMPLETION_COMPLETE_PASS) {
                $status = 'completed';
            } else if ($data->viewed) {
                $status = 'in_progress';
            }
        }
        // ACF-FIX-2.0: single source of truth for the visible badge text and the ARIA text.
        $statuslabel = activityinfo::get_status_label($status);

        // ACF-FIX-2.0: a11y — status was conveyed by the card's colour class alone. The link's
        // accessible name now includes both the activity name and its completion status.
        $cardlabel = get_string('activitywithstatus', 'format_aicourse', (object) [
            'name' => format_string($cm->name),
            'status' => $statuslabel,
        ]);

        // ACF-FIX-2.1.47: the estimated duration, so a learner can see what an activity will cost
        // them before opening it. Same figure the section card's total is built from -- teacher
        // override first, then the site default for the type, with a quiz calculated from its
        // question count -- so the parts visibly add up to the whole rather than being two
        // separate guesses. An estimate of 0, which is how a teacher hides one, yields an empty
        // string and the pill is not rendered.
        $estimatedtime = progress::format_estimated_time(progress::estimate_activity_minutes($cm));

        return (object) [
            'issubsection' => false,
            'hastime' => ($estimatedtime !== ''),
            'estimatedtime' => $estimatedtime,
            'timelabel' => ($estimatedtime === '') ? '' :
                get_string('estimatedtimefor', 'format_aicourse', $estimatedtime),
            'cmid' => (int) $cm->id,
            'url' => $cm->url->out(false),
            'status' => $status,
            'haslabel' => true,
            'cardlabel' => $cardlabel,
            // Card icon — decorative: the module type is already visible text in the card footer.
            'iconurl' => $cm->get_icon_url()->out(false),
            // ACF-FIX-2.0: a11y — h4 demoted to h3 so the heading levels run h1 → h2 → h3.
            'name' => format_string($cm->name),
            'typename' => activityinfo::get_activity_type_name($cm),
            'iscompleted' => ($status === 'completed'),
            'statuslabel' => $statuslabel,
            'badgeclass' => 'aicourse-status-badge-' . $status,
        ];
    }

    /**
     * Render the activity cards for this section.
     *
     * @return string HTML.
     */
    public function out(): string {
        global $OUTPUT;

        return $OUTPUT->render($this);
    }
}
