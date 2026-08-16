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
use format_aicourse\local\permissions;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;

/**
 * The AI Tutor chat panel.
 *
 * PHASE 2: the markup lives in templates/chatbox.mustache and the behaviour in
 * amd/src/chatbox.js. {@see self::script()} no longer emits a <script> block; it queues the AMD
 * module with a config object, which Moodle JSON-encodes for us. Nothing this class produces is
 * interpolated into JavaScript source any more, so the whole injection surface that the old
 * inline script had is gone, and the panel works under a Content-Security-Policy that forbids
 * inline script.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chatbox implements named_templatable, renderable {
    /**
     * Activity module types the AI Tutor can pull question / instruction context for.
     *
     * @var string[]
     */
    protected const CONTEXT_AWARE_MODULES = ['quiz', 'aiquiz', 'assign', 'knowledgecheck', 'practicalassessment'];

    /**
     * Course the chat panel is bound to. Falls back to $PAGE->course when not supplied.
     *
     * @var stdClass|null
     */
    protected $course;

    /**
     * Memoised result of {@see self::resolve_activity_context()}.
     *
     * out() and script() both need it, and it touches modinfo.
     *
     * @var stdClass|null
     */
    protected $activitycontext = null;

    /**
     * Constructor.
     *
     * @param stdClass|null $course Course record. Optional: $PAGE->course is used when omitted.
     */
    public function __construct(?stdClass $course = null) {
        $this->course = $course;
    }

    /**
     * The template this renderable is rendered with.
     *
     * @param renderer_base $renderer The renderer requesting the template name.
     * @return string Template name.
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_aicourse/chatbox';
    }

    /**
     * Export the chat panel.
     *
     * ACF-FIX-2.0 notes preserved from the string-builder this replaced:
     *  - a11y: the panel is an ARIA dialog (role/aria-modal/aria-labelledby); the message list is a
     *    role="log" aria-live region with dir="auto"; the icon-only close and send buttons and the
     *    textarea all have accessible names.
     *  - i18n: the greeting fallback name comes from get_string('defaultgreetingname'), not the
     *    hardcoded English word "there".
     *
     * The only pre-escaped value in the context is `welcome`: a language string that legitimately
     * contains <strong>, resolved with $a values that have already been through format_string().
     * It is therefore rendered with a triple mustache. Everything else is escaped by Mustache.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Template context.
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->closelabel = get_string('closebuttontitle');
        $data->inputlabel = get_string('aiassistant_input_label', 'format_aicourse');
        $data->placeholder = get_string('aiassistant_placeholder', 'format_aicourse');
        $data->sendlabel = get_string('aiassistant_send', 'format_aicourse');
        $data->welcome = $this->get_welcome_message($this->resolve_activity_context());

        return $data;
    }

    /**
     * Render the AI Course Assistant chat panel.
     *
     * @return string HTML, or '' when the AI Tutor is disabled site-wide.
     */
    public function out(): string {
        global $OUTPUT;

        if (!permissions::is_tutor_enabled()) {
            return '';
        }

        return $OUTPUT->render($this);
    }

    /**
     * Queue the AMD module that drives the AI Course Assistant chat panel.
     *
     * Every PHP value the module needs travels in the config object below. Moodle JSON-encodes
     * js_call_amd() arguments itself, so no value is ever concatenated into JavaScript source.
     * The method is safe to call more than once per request (format.php and the
     * before_footer_html_generation hook both call it); the module's init() is idempotent.
     *
     * @return string Always '', kept so callers that echo the return value keep working.
     */
    public function script(): string {
        global $PAGE;

        if (!permissions::is_tutor_enabled()) {
            return '';
        }

        $PAGE->requires->js_call_amd('format_aicourse/chatbox', 'init', [$this->get_js_config()]);

        return '';
    }

    /**
     * The config object handed to format_aicourse/chatbox's init().
     *
     * @return array Config, JSON-encoded by js_call_amd().
     */
    protected function get_js_config(): array {
        global $USER;

        $context = $this->resolve_activity_context();
        $endpoint = new moodle_url('/course/format/aicourse/ajax.php');

        return [
            'courseid' => (int) $this->get_course()->id,
            'userid' => (int) $USER->id,
            'sesskey' => sesskey(),
            'endpoint' => $endpoint->out(false),
            'activityid' => $context->activityid,
            'activityname' => $context->activityname,
            'activitytype' => $context->activitytype,
            'sectionid' => $context->sectionid,
            'contextaware' => in_array($context->activitytype, self::CONTEXT_AWARE_MODULES, true),
        ];
    }

    /**
     * The course the panel belongs to.
     *
     * @return stdClass Course record.
     */
    protected function get_course(): stdClass {
        global $PAGE;

        return $this->course ?? $PAGE->course;
    }

    /**
     * Work out which activity or section the learner is currently looking at.
     *
     * SECURITY: every name is passed through format_string() before it can reach the browser, and
     * the module name through clean_param(PARAM_PLUGIN).
     *
     * @return stdClass With int activityid, string activityname, string activitytype,
     *                  int sectionid and string sectionname.
     */
    protected function resolve_activity_context(): stdClass {
        global $PAGE;

        if ($this->activitycontext !== null) {
            return $this->activitycontext;
        }

        $context = (object) [
            'activityid' => 0,
            'activityname' => '',
            'activitytype' => '',
            'sectionid' => 0,
            'sectionname' => '',
        ];

        if ($PAGE->cm) {
            $context->activityid = (int) $PAGE->cm->id;
            $context->activityname = format_string($PAGE->cm->name);
            $context->activitytype = clean_param((string) $PAGE->cm->modname, PARAM_PLUGIN);
            $context->sectionid = (int) $PAGE->cm->section;
            $context->sectionname = get_section_name($this->get_course(), $PAGE->cm->sectionnum);
        } else {
            $sectionnum = optional_param('section', 0, PARAM_INT);
            if ($sectionnum > 0) {
                $context->sectionid = $sectionnum;
                $context->sectionname = get_section_name($this->get_course(), $sectionnum);
            }
        }
        $context->sectionname = format_string($context->sectionname);
        $this->activitycontext = $context;

        return $context;
    }

    /**
     * Build the context-aware greeting shown at the top of the conversation.
     *
     * The three variants are the plain greeting, "you are in <section>" and "you are working on
     * <activity>". The section and activity names have already been through format_string(); the
     * language strings themselves contain <strong>, which is why the result is rendered with a
     * triple mustache.
     *
     * @param stdClass $context As returned by {@see self::resolve_activity_context()}.
     * @return string Greeting HTML.
     */
    protected function get_welcome_message(stdClass $context): string {
        global $USER;

        // ACF-FIX-2.0: i18n — the fallback was the hardcoded English word 'there', which was
        // interpolated into the translated greeting producing "Hi there!" in every language.
        $firstname = !empty($USER->firstname)
            ? format_string($USER->firstname)
            : get_string('defaultgreetingname', 'format_aicourse');

        if (!empty($context->activityname)) {
            return get_string('aiassistant_welcome_activity', 'format_aicourse', (object) [
                'name' => $firstname,
                'activity' => $context->activityname,
            ]);
        }
        if (!empty($context->sectionname)) {
            return get_string('aiassistant_welcome_section', 'format_aicourse', (object) [
                'name' => $firstname,
                'section' => $context->sectionname,
            ]);
        }

        return get_string('aiassistant_welcome_name', 'format_aicourse', $firstname);
    }
}
