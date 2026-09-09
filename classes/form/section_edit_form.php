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

namespace format_aicourse\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/editsection_form.php');

/**
 * The section settings form, with this format's section banner image added.
 *
 * WHY THIS CLASS EXISTS RATHER THAN create_edit_form_elements().
 *
 * A course format is supposed to add its own section fields from
 * create_edit_form_elements($mform, true), and this format does exactly that for the COURSE
 * settings form. For sections it cannot, because core only calls that method when the format
 * declares at least one section format option:
 *
 *     $formatoptions = $courseformat->section_format_options(true);
 *     if (!empty($formatoptions)) {
 *         $elements = $courseformat->create_edit_form_elements($mform, true);
 *     }
 *
 * -- course/editsection_form.php, identical in Moodle 4.4 and 5.0.
 *
 * A banner is a file, and format options are rows of text in {course_format_options}; a file
 * cannot be one. So the format has no section options to declare, the gate never opens, and the
 * method is never called. Declaring a dummy option purely to open it would put a meaningless
 * row in every section of every course using this format, for ever, to work around an `if`.
 *
 * base::editsection_form() is the documented, overridable factory for this form, so the format
 * overrides it and returns this subclass instead. That also solves the second problem:
 * create_edit_form_elements() is handed only the form, and course/editsection.php builds the
 * format with course_get_format($course) and no section, so get_sectionid() there is null. The
 * section this form is for is in $customdata['cs'], which a form subclass can read and a format
 * method cannot.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_edit_form extends \editsection_form {
    /**
     * Name of the filemanager element carrying the section banner.
     *
     * Read back by format_aicourse::update_section_format_options(), which is the only thing
     * that saves it, so the two are pinned to one constant rather than a repeated string.
     *
     * @var string
     */
    public const ELEMENT = 'sectionbannerimage';

    /**
     * Maximum accepted size of an uploaded section banner, matching the course banner.
     *
     * @var int
     */
    public const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * Build the form, then append this format's banner field.
     *
     * The elements are added at the end of definition() rather than in definition_after_data(),
     * which is where core adds the Save and Cancel buttons. Appending here therefore lands the
     * field above those buttons; appending in definition_after_data() would land it below them.
     *
     * @return void
     */
    public function definition() {
        parent::definition();

        $mform = $this->_form;
        $course = $this->_customdata['course'];
        $sectioninfo = $this->_customdata['cs'] ?? null;

        if (!$sectioninfo || empty($sectioninfo->id)) {
            // No section to file an image against. Nothing else in this method is safe, and a
            // form without the field is a better outcome than a broken settings page.
            return;
        }

        $mform->addElement(
            'header',
            'aicoursesectionbannerheader',
            get_string('sectionbannerheader', 'format_aicourse')
        );
        $mform->setExpanded('aicoursesectionbannerheader');

        // Says what happens when the field is left empty, which is the whole point of the
        // feature and is not guessable from an empty filemanager.
        $mform->addElement(
            'static',
            'aicoursesectionbannerguidance',
            '',
            '<div class="alert alert-info aicourse-banner-guidance" role="note">'
                . '<p class="mb-1"><strong>' . get_string('bannerimage_ratio_title', 'format_aicourse')
                . '</strong> ' . get_string('bannerimage_ratio_hint', 'format_aicourse') . '</p>'
                . '<p class="mb-0 small text-muted">'
                . get_string('sectionbannerimage_inherits', 'format_aicourse') . '</p>'
                . '</div>'
        );

        $mform->addElement(
            'filemanager',
            self::ELEMENT,
            get_string('sectionbannerimage', 'format_aicourse'),
            null,
            [
                'maxbytes' => self::MAX_BYTES,
                'maxfiles' => 1,
                'subdirs' => 0,
                'accepted_types' => ['.jpg', '.jpeg', '.png', '.webp'],
            ]
        );
        $mform->addHelpButton(self::ELEMENT, 'sectionbannerimage', 'format_aicourse');

        // Fill the draft area with whatever is already stored for this section, so opening the
        // form shows the current image rather than an empty box -- and, more importantly, so
        // saving the form without touching the field does not save an empty draft over it. That
        // is the bug ACF-FIX-2.0 fixed for the course banner; the same trap is here.
        $draftitemid = 0;
        file_prepare_draft_area(
            $draftitemid,
            \context_course::instance($course->id)->id,
            'format_aicourse',
            \format_aicourse\local\banner::SECTION_AREA,
            (int) $sectioninfo->id,
            ['maxbytes' => self::MAX_BYTES, 'maxfiles' => 1, 'subdirs' => 0]
        );
        $mform->setDefault(self::ELEMENT, $draftitemid);
    }
}
