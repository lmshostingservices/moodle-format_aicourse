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
use core_courseformat\base as course_format;
use core_text;
use format_aicourse\local\activityinfo;
use format_aicourse\local\icons;
use format_aicourse\local\progress;
use format_aicourse\output\courseformat\content\generalsection;
use format_topics\output\courseformat\content as topics_content;
use moodle_url;
use renderable;
use renderer_base;
use require_login_exception;
use section_info;
use stdClass;

/**
 * The AI Course format course home page: the General section plus the section card grid.
 *
 * The class extends format_topics' content output class so that
 * core_courseformat\base::get_output_classname('content') keeps resolving to a valid
 * core_courseformat\output\local\content subclass. That means ONE class serves two callers:
 *
 *  - core (and format.php's non-card branch) instantiate it with `new content($format)` and hand
 *    it to the renderer. In that mode it must behave exactly like format_topics' content, i.e.
 *    render core_courseformat/local/content with core's context.
 *  - format.php's card branch calls out(), which renders the AI Course card grid instead.
 *
 * The two modes are told apart by the $cardview constructor flag, which is set once at
 * construction and never mutated, so get_template_name() and export_for_template() stay
 * consistent for the lifetime of an instance. Overriding get_template_name() unconditionally
 * would hijack the standard rendering path and blank every non-card page.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends topics_content implements named_templatable, renderable {
    /** @var int Maximum number of activity dots painted in a card footer. */
    protected const MAX_DOTS = 5;

    /**
     * @var int Fallback cap on activities listed on a card, used only if the course option is
     * somehow absent. ACF-FIX-2.1.25: the cap is now the "Activities listed per card" course
     * setting and DEFAULTS TO 0, meaning show every activity and let the card grow. The old
     * hard-coded 4 produced the "+7" chip that hid most of a section's contents from the very
     * teacher who had just switched the list on.
     */
    protected const MAX_CARD_ACTIVITIES = 0;

    /** @var int Maximum length of the truncated card summary, in characters. */
    protected const SUMMARY_LENGTH = 130;

    /** @var bool True when this instance renders the AI Course card grid instead of core's content. */
    protected $cardview;

    /**
     * Constructor.
     *
     * @param course_format $format The course format.
     * @param bool $cardview True to render the AI Course card grid instead of core's course content.
     */
    public function __construct(course_format $format, bool $cardview = false) {
        parent::__construct($format);
        $this->cardview = $cardview;
    }

    /**
     * Get the name of the template to use for this templatable.
     *
     * Only the card view uses the plugin template. Every other instance keeps the template core
     * resolved for it, so the standard (non card) course rendering path is untouched.
     *
     * @param renderer_base $renderer The renderer requesting the template name.
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        if ($this->cardview) {
            return 'format_aicourse/content';
        }
        return parent::get_template_name($renderer);
    }

    /**
     * Export the data for the mustache template.
     *
     * In card mode the returned context describes the AI Course card grid. In every other mode it
     * is core's own course content context, produced by the parent class.
     *
     * Pre-escaped values in the card context (rendered with a triple mustache):
     *  - iconpicker: HTML produced by the icon picker renderable.
     *
     * @param renderer_base $output Typically, the renderer that is calling this function.
     * @return stdClass Data context for a mustache template.
     */
    public function export_for_template(renderer_base $output): stdClass {
        if (!$this->cardview) {
            return parent::export_for_template($output);
        }
        return $this->export_cards_for_template($output);
    }

    /**
     * Render the section cards for the course home page.
     *
     * @return string HTML.
     */
    public function out(): string {
        global $OUTPUT;

        return $OUTPUT->render(new self($this->format, true));
    }

    /**
     * Re-render one section card, for the format_aicourse/sectioncard output fragment.
     *
     * A card shows server-computed data the reactive state does not carry: the "N activities"
     * count, the completion percentage badge and the compact progress dots. When an activity is
     * moved into or out of a section those three values change, so the card has to be rebuilt by
     * the same code that built it on page load. This is the card-view equivalent of core's
     * core_courseformat_output_fragment_section(), which reloads a whole section for the same
     * reason.
     *
     * Access is checked here, not by the caller: a fragment is a web-service entry point.
     *
     * @param array $args Fragment arguments: courseid and id (the section record id).
     * @return string The rendered format_aicourse/section_card markup.
     * @throws require_login_exception When the course or the section is not available to this user.
     */
    public static function render_section_card_fragment(array $args): string {
        global $PAGE, $USER;

        $course = get_course((int) ($args['courseid'] ?? 0));
        if (!can_access_course($course, null, '', true)) {
            throw new require_login_exception('Course is not available');
        }

        $format = course_get_format($course);
        $modinfo = get_fast_modinfo($course, $USER->id);
        $section = $modinfo->get_section_info_by_id((int) ($args['id'] ?? 0), MUST_EXIST);
        // Section 0 is drawn above the grid by format_aicourse/general_section and never as a card.
        if (!$section->uservisible || $section->section == 0) {
            throw new require_login_exception('Section is not available');
        }

        $output = $PAGE->get_renderer('format_aicourse');
        $content = new self($format, true);
        $coursecontext = context_course::instance($course->id);
        $isediting = has_capability('moodle/course:update', $coursecontext) && $PAGE->user_is_editing();

        $completioninfo = new completion_info($course);
        $card = $content->export_section_card(
            $course,
            $section,
            icons::get_library(),
            $completioninfo,
            $isediting
        );

        return $output->render_from_template('format_aicourse/section_card', $card);
    }

    /**
     * Build the template context for the card grid.
     *
     * @param renderer_base $output The renderer that is calling this function.
     * @return stdClass Data context for format_aicourse/content.
     */
    protected function export_cards_for_template(renderer_base $output): stdClass {
        global $USER, $PAGE;

        $course = $this->format->get_course();
        $options = $this->format->get_format_options();

        $modinfo = get_fast_modinfo($course, $USER->id);
        $sections = activityinfo::get_listed_sections($modinfo);
        $coursecontext = context_course::instance($course->id);
        $canedit = has_capability('moodle/course:update', $coursecontext);
        // FIX-ACF-EDITMODE (v1.7.70): Gate all edit controls by actual edit mode state.
        // $canedit only checks capability; $isediting also requires the teacher to have
        // turned on edit mode. This mirrors the student view when edit mode is OFF.
        // The card layout itself still always renders (fix for UX-ACF-EDITMODE-WIPE).
        $isediting = $canedit && $PAGE->user_is_editing();
        $iconlibrary = icons::get_library();

        // Create one shared completion_info and bulk-load ALL user completion data in a single
        // DB query. Passing $wholecourse=true on the first get_data() call triggers a SELECT of
        // every completion row for this user+course and caches it inside the object. Every
        // subsequent call on the same instance hits the in-memory cache, so we avoid NxM
        // individual queries (N sections x M activities per section).
        $sharedcompletioninfo = new completion_info($course);
        if ($sharedcompletioninfo->is_enabled()) {
            $allcms = $modinfo->get_cms();
            foreach ($allcms as $firstcm) {
                if ($firstcm->completion != COMPLETION_TRACKING_NONE) {
                    $sharedcompletioninfo->get_data($firstcm, true, $USER->id);
                    break;
                }
            }
        }

        // ACF-FIX-2.0: a11y - the cards container is a named region so it can be reached with
        // landmark navigation and screen readers announce what the grid is.
        // ACF-FIX-2.0: a11y - the grid exposes list semantics so screen readers announce the item
        // count and users can navigate with list commands. role="list"/role="listitem" is used rather
        // than <ul>/<li> so no existing CSS class or grid rule changes. The roles are only applied
        // when NOT editing, because the "Add section" <button> lives inside the grid and a role="list"
        // must contain only listitem children.
        $data = (object) [
            'regionlabel' => get_string('coursesectionsregion', 'format_aicourse'),
            'courseid' => (int) $course->id,
            'isediting' => $isediting,
            'uselistroles' => !$isediting,
            'generalsection' => null,
            'sections' => [],
            'iconpicker' => null,
            'bulkedittools' => null,
        ];

        // Moodle puts a "Bulk actions" button in the page header for any format that
        // supports_components(), so the card view has to ship the other half of that feature: the
        // sticky action bar it drives. Core's own renderable and template are used unchanged, and
        // the per-card checkboxes are emitted by export_section_card(). Without this the button
        // would be a control that visibly does nothing.
        if ($this->format->show_editor()) {
            $toolsclass = $this->format->get_output_classname('content\\bulkedittools');
            $data->bulkedittools = (new $toolsclass($this->format))->export_for_template($output);
        }

        // Always render Section 0 (General) inline above the section cards grid.
        // This matches standard Moodle behaviour: General section appears first, then module sections.
        $section0 = isset($sections[0]) ? $sections[0] : null;
        if ($section0 && $section0->uservisible) {
            $general = new generalsection($course, $section0, $options, $modinfo, $coursecontext);
            $data->generalsection = $general->export_for_template($output);
        }

        // OPT-ACF-BULK-ICONS (v1.7.51): Preload ALL section icons in a single DB query
        // before entering the per-section loop. Without this, each call to
        // icons::get_section_icon() inside the loop issued its own SELECT,
        // producing N sequential queries for N sections. The preload populates the static
        // cache (icons::$sectioniconcache) so every subsequent icons::get_section_icon()
        // call in this request hits memory at zero DB cost.
        $preloadsectionids = [];
        foreach ($sections as $s) {
            if ($s->uservisible && $s->section > 0) {
                $preloadsectionids[] = $s->id;
            }
        }
        if (!empty($preloadsectionids)) {
            icons::preload_section_icons($course->id, $preloadsectionids);
        }

        foreach ($sections as $section) {
            if (!$section->uservisible) {
                continue;
            }

            // Section 0 (General) is always rendered inline above the grid - never as a card.
            if ($section->section == 0) {
                continue;
            }

            $data->sections[] = $this->export_section_card(
                $course,
                $section,
                $iconlibrary,
                $sharedcompletioninfo,
                $isediting
            );
        }

        // Icon picker modal - only needed when edit mode is ON.
        if ($isediting) {
            $data->iconpicker = (new iconpicker($iconlibrary))->out();
        }

        return $data;
    }

    /**
     * Build the template context for a single section card.
     *
     * @param stdClass $course Course record.
     * @param section_info $section The section this card represents.
     * @param array $iconlibrary The icon library, keyed by icon name.
     * @param completion_info $completioninfo Shared, bulk-loaded completion data.
     * @param bool $isediting True when the teacher has edit mode turned on.
     * @return stdClass Data context for format_aicourse/section_card.
     */
    protected function export_section_card(
        $course,
        section_info $section,
        array $iconlibrary,
        completion_info $completioninfo,
        bool $isediting
    ): stdClass {
        global $USER;

        $sectionname = get_section_name($course, $section);
        $formattedname = format_string($sectionname);
        // ACF-FIX-2.1: accessible names are plain-text ATTRIBUTE values, and Mustache escapes
        // them again with {{double}}. Feeding them format_string()'s escaped output produced
        // double encoding, so a screen reader announced "Data &amp;amp; Features". Ask
        // format_string() for the filtered but UNescaped string and let Mustache do the single,
        // correct escaping pass. Tags are stripped because multilang <span>s must not leak into
        // an attribute. $formattedname stays escaped for the visible heading, which is rendered
        // with {{{triple}}} so multilang filtering still works there.
        $plainname = html_to_text(
            format_string($sectionname, true, ['escape' => false]),
            0,
            false
        );
        $progressdata = progress::get_section_progress($course, $section, $USER->id, $completioninfo);

        // ACF-FIX-2.0: duration formatting moved to progress::format_estimated_time()
        // so the cards and the course-home hero meta row cannot drift apart.
        $estimatedtime = progress::format_estimated_time($progressdata['estimatedminutes']);

        // Sections in the grid are never section 0 (it renders inline above the grid), so they all
        // use the Moodle 4.x section.php route keyed on the section ID.
        $sectionurl = (new moodle_url('/course/section.php', ['id' => $section->id]))->out(false);

        // ACF-FIX-2.0: dead code removed - $circumference / $offset were computed for a progress
        // ring that this card layout has never drawn (it shows a percentage badge instead).
        $percentage = (int) $progressdata['percentage'];

        // Get saved icon for this section.
        $savedicon = icons::get_section_icon($course->id, $section->id);
        $hasicon = !empty($savedicon) && isset($iconlibrary[$savedicon]);

        $activitycount = isset($progressdata['activitycount']) ? (int) $progressdata['activitycount'] : 0;
        $hasprogress = (bool) ($progressdata['enabled'] && $progressdata['total'] > 0);

        // ACF-FIX-2.0: a11y - this is the ONE named link into the section, and its accessible
        // name carries the whole summary of the card (name, activity count, estimated time,
        // percentage complete) so nothing is lost by silencing the duplicate links.
        $labelparts = [$plainname];
        if ($activitycount > 0) {
            $labelparts[] = $this->get_activity_count_text($activitycount);
        }
        if ($estimatedtime !== '') {
            $labelparts[] = get_string('estimatedtimefor', 'format_aicourse', $estimatedtime);
        }
        if ($hasprogress) {
            $labelparts[] = get_string('percentcomplete', 'format_aicourse', $percentage);
        }

        $card = (object) [
            'sectionid' => (int) $section->id,
            'sectionnum' => (int) $section->section,
            'courseid' => (int) $course->id,
            'sectionurl' => $sectionurl,
            'sectionname' => $formattedname,
            'isediting' => $isediting,
            'uselistroles' => !$isediting,
            // Core's bulk selection checkbox. Section 0 is never bulk editable (see
            // core_courseformat\output\local\state\section::is_bulk_editable()) and never renders
            // as a card anyway, so the check mirrors showeditbuttons.
            'sectionbulk' => ($isediting && $section->section > 0)
                ? (object) ['id' => (int) $section->id, 'name' => $formattedname]
                : null,
            // UX-ACF-EDITBTNS: Card edit buttons - only shown when edit mode is ON.
            'showeditbuttons' => ($isediting && $section->section > 0),
            'hasicon' => $hasicon,
            'iconsvg' => $hasicon ? $iconlibrary[$savedicon] : '',
            'hastime' => ($estimatedtime !== ''),
            'estimatedtime' => $estimatedtime,
            'cardlinklabel' => implode(get_string('labelseparator', 'format_aicourse'), $labelparts),
            'hassummary' => false,
            'summary' => '',
            'hasfooter' => ($activitycount > 0 || $hasprogress),
            'hascount' => ($activitycount > 0),
            'counttext' => ($activitycount > 0) ? $this->get_activity_count_text($activitycount) : '',
            'hasprogress' => $hasprogress,
            'badgeclass' => ($percentage == 100) ? 'aicourse-progress-badge-complete' : 'aicourse-progress-badge',
            'percenttext' => get_string('percentvalue', 'format_aicourse', $percentage),
            'hasdots' => false,
            'dots' => [],
            'moredots' => null,
            // The "Show activities on cards" option is off by default, and while it is off these
            // three keys keep exactly these values, so the template emits nothing and the card is
            // byte-identical to what it rendered before the option existed.
            'hasactivities' => false,
            'activities' => [],
            'moreactivities' => null,
        ];

        // ACF-FIX-2.0: a11y - every card previously produced an identically-named "Edit section" /
        // "Delete section" control. The accessible names now include the section name so a
        // screen-reader user can tell them apart.
        if ($card->showeditbuttons) {
            $editsectionurl = new moodle_url('/course/editsection.php', ['id' => $section->id, 'sr' => 0]);
            $card->editsectionurl = $editsectionurl->out(false);
            $card->editsectionlabel = get_string('editsectionnamed', 'format_aicourse', $plainname);
            $card->editsectiontitle = get_string('editsection', 'moodle');
            $card->duplicatelabel = get_string('duplicatesectionnamed', 'format_aicourse', $plainname);
            $card->duplicatetitle = get_string('duplicatesection', 'format_aicourse');
            $card->deletelabel = get_string('deletesectionnamed', 'format_aicourse', $plainname);
            $card->deletetitle = get_string('deletesection', 'format_aicourse');
            // The grab handle is both the drag affordance and the keyboard "Move section" control,
            // so its accessible name has to name the section it moves. Core already ships both
            // strings, so no new language string is introduced for this.
            $card->movelabel = get_string('movecontent', 'moodle', $plainname);
            $card->movetitle = get_string('movecoursesection', 'moodle');
        }

        if ($isediting) {
            // ACF-FIX-2.0: a11y - the icon column was a bare <div> with no tabindex, no role and no
            // keyboard handler, and the JS binds click only. Keyboard-only and screen-reader teachers
            // could not open the icon picker at all. It is a real <button type="button"> with an
            // accessible name; the click delegate in courseformat.js is unaffected because the class
            // names are unchanged.
            $card->iconbuttonlabel = $hasicon
                ? get_string('changeiconfor', 'format_aicourse', $plainname)
                : get_string('addiconfor', 'format_aicourse', $plainname);
        }

        // Section summary / description (truncated) shown below title on card.
        if (!empty($section->summary)) {
            $summaryclean = strip_tags(format_string($section->summary));
            if (core_text::strlen($summaryclean) > self::SUMMARY_LENGTH) {
                $summaryclean = core_text::substr($summaryclean, 0, self::SUMMARY_LENGTH) . '…';
            }
            if ($summaryclean !== '') {
                $card->hassummary = true;
                $card->summary = $summaryclean;
            }
        }

        if ($hasprogress && !empty($progressdata['activities'])) {
            $this->export_progress_dots($card, $progressdata['activities'], $sectionurl);
        }

        if ($this->show_activities_on_cards()) {
            $this->export_card_activities($card, $course, $section, $plainname, $progressdata, $sectionurl);
        }

        return $card;
    }

    /**
     * Whether this course lists each section's activities on its section card.
     *
     * Read from the format options rather than passed in, so the option is honoured on both paths
     * that build a card: the full page render and the format_aicourse/sectioncard fragment.
     * get_format_options() caches per format instance, so this is not a repeated query.
     *
     * @return bool True when the teacher has turned "Show activities on cards" on.
     */
    protected function show_activities_on_cards(): bool {
        $options = $this->format->get_format_options();
        return !empty($options['showactivitiesoncards']);
    }

    /**
     * Add the compact activity list (at most self::MAX_CARD_ACTIVITIES, plus a "+N" overflow link)
     * to a card.
     *
     * This is deliberately NOT the activity-card treatment used on section pages: one line per
     * activity, the name and its completion state, because the card grid's whole value is that it
     * is scannable. The list is emitted in normal flow above the footer, whose
     * `margin-block-start: auto` keeps pinning it to the bottom edge of a stretched card.
     *
     * Visibility uses exactly the predicate the rest of the plugin uses -- $cm->uservisible plus
     * activityinfo::cm_counts_as_content() -- so a learner can never see an activity here that
     * they cannot see anywhere else, and the list can never disagree with the "N activities"
     * count in the footer, which counts with the same predicate.
     *
     * Completion state is taken from the rows progress::get_section_progress() has ALREADY
     * computed for the progress dots, so the list costs no extra completion query and the two
     * cannot show a different state for the same activity. Activities with completion tracking
     * turned off simply have no state.
     *
     * @param stdClass $card The card context being built; modified in place.
     * @param stdClass $course Course record.
     * @param section_info $section The section this card represents.
     * @param string $plainname Unescaped section name, used to make each link's accessible name
     *                          distinguishable from the identically-named link on another card.
     * @param array $progressdata Output of progress::get_section_progress() for this section.
     * @param string $sectionurl Unescaped section URL, used as the overflow target and as the
     *                           fallback for activities that have no URL of their own (a label).
     * @return void
     */
    protected function export_card_activities(
        stdClass $card,
        $course,
        section_info $section,
        string $plainname,
        array $progressdata,
        string $sectionurl
    ): void {
        global $USER;

        $statusbycmid = [];
        foreach ($progressdata['activities'] as $activity) {
            $statusbycmid[(int) $activity['id']] = $activity['status'];
        }

        $modinfo = get_fast_modinfo($course, $USER->id);
        $cmids = $modinfo->sections[$section->section] ?? [];

        $formatoptions = course_get_format($course)->get_format_options();
        $limit = isset($formatoptions['cardactivitylimit'])
            ? max(0, (int) $formatoptions['cardactivitylimit'])
            : self::MAX_CARD_ACTIVITIES;

        $items = [];
        $shown = 0;
        $total = 0;
        foreach ($cmids as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if (!$cm->uservisible || !activityinfo::cm_counts_as_content($cm)) {
                continue;
            }
            $total++;
            // 0 means no limit: list everything. Anything above 0 caps the list and the
            // remainder becomes the "+N" chip below.
            if ($limit > 0 && $shown >= $limit) {
                continue;
            }
            $shown++;

            // Same escaping contract as the section name: the visible text keeps format_string()'s
            // entities and is rendered with a triple mustache, while the accessible name is the
            // plain unescaped string because Mustache escapes attribute values itself.
            $plaincmname = html_to_text(
                format_string($cm->name, true, ['escape' => false]),
                0,
                false
            );
            $status = $statusbycmid[$cm->id] ?? null;
            $label = ($status !== null)
                ? get_string('cardactivitystatuslabel', 'format_aicourse', (object) [
                    'name' => $plaincmname,
                    'section' => $plainname,
                    'status' => activityinfo::get_status_label($status),
                ])
                : get_string('cardactivitylabel', 'format_aicourse', (object) [
                    'name' => $plaincmname,
                    'section' => $plainname,
                ]);

            $items[] = (object) [
                // Already escaped by moodle_url::out(); see the template PHPDoc. A label and a
                // subsection have no URL of their own, so they point at the section that holds
                // them rather than becoming the one item in the list that does not link anywhere.
                'url' => $cm->url ? $cm->url->out() : s($sectionurl),
                'name' => format_string($cm->name),
                'label' => $label,
                'stateclass' => 'aicourse-actstate-' . ($status ?? 'none'),
            ];
        }

        $card->hasactivities = !empty($items);
        $card->activities = $items;
        if ($total > $shown) {
            $card->moreactivities = (object) [
                // ACF-FIX-2.1.4: s(), matching the two sibling assignments above and below. The
                // template renders this through {{{url}}}, so the value must arrive escaped;
                // $sectionurl is deliberately the unescaped out(false) form.
                'url' => s($sectionurl),
                'remaining' => $total - $shown,
                // Named with the section, so the "+N" link on one card is distinguishable from
                // the "+N" link on the next one (WCAG 2.4.4).
                'label' => get_string('cardactivitiesmore', 'format_aicourse', $plainname),
            ];
        }
    }

    /**
     * Add the compact progress dots (at most self::MAX_DOTS, plus a "+N" overflow link) to a card.
     *
     * ACF-FIX-2.0: a11y - the dots were EMPTY 12x12px <a> elements whose only information was their
     * colour: no accessible name at all, and completion status conveyed by hue alone. Each now
     * carries the activity name AND its status in the accessible name.
     *
     * @param stdClass $card The card context being built; modified in place.
     * @param array $activities Activity rows from progress::get_section_progress().
     * @param string $sectionurl Unescaped fallback URL for activities with no URL of their own.
     * @return void
     */
    protected function export_progress_dots(stdClass $card, array $activities, string $sectionurl): void {
        $dots = [];
        foreach ($activities as $activity) {
            if (count($dots) >= self::MAX_DOTS) {
                $card->moredots = (object) [
                    // ACF-FIX-2.1.4: s(), for the same reason as the dot URLs below.
                    'url' => s($sectionurl),
                    'remaining' => count($activities) - self::MAX_DOTS,
                    'title' => get_string('viewallactivities', 'format_aicourse'),
                ];
                break;
            }
            $label = get_string('activitywithstatus', 'format_aicourse', (object) [
                'name' => $activity['name'],
                'status' => activityinfo::get_status_label($activity['status']),
            ]);
            $dots[] = (object) [
                // Already escaped by moodle_url::out(); see the template PHPDoc.
                'url' => !empty($activity['url']) ? $activity['url'] : s($sectionurl),
                'statusclass' => 'aicourse-dot-' . $activity['status'],
                'label' => $label,
            ];
        }
        $card->hasdots = !empty($dots);
        $card->dots = $dots;
    }

    /**
     * Localised "N activities" text.
     *
     * ACF-FIX-2.0: i18n - the count used a hand-rolled two-form plural built by concatenation
     * ("5" . " " . "activities"), which is wrong for Arabic, Russian, Polish and Welsh and fixes
     * English word order. Single placeholder strings now let the language pack decide.
     *
     * @param int $count Number of activities.
     * @return string
     */
    protected function get_activity_count_text(int $count): string {
        return ($count == 1)
            ? get_string('oneactivity', 'format_aicourse')
            : get_string('nactivities', 'format_aicourse', $count);
    }
}
