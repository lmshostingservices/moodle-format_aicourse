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

use context_course;
use moodle_url;

/**
 * Resolution of the course hero image: custom banner first, course overview image second.
 *
 * Stateless service class: every method is static and none of them produce output.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class banner {
    /**
     * Item id used for every banner file.
     *
     * ACF-FIX-2.1.5: this is deliberately 0, not the course id.
     *
     * There is exactly one banner per course and the file already lives in that course's
     * context, so the item id carries no information. It used to be the course id, which broke
     * backup and restore: restore_dbops::send_files_to_pool() copies "itemid AS newitemid"
     * verbatim when a file area is restored without an item id mapping, so a course restored
     * into a NEW course kept its banner filed under the OLD course id and
     * get_banner_image_url() -- which looks under the new one -- found nothing. Pinning the
     * item id to 0 makes the verbatim copy correct. db/upgrade.php migrates existing files.
     *
     * @var int
     */
    public const BANNER_ITEMID = 0;

    /**
     * File area holding the course level banner.
     *
     * @var string
     */
    public const COURSE_AREA = 'bannerimage';

    /**
     * File area holding per-section banners.
     *
     * Unlike the course banner this area DOES use a meaningful item id -- the
     * course_sections.id of the section the image belongs to -- because there is one image per
     * section rather than one per course. That makes backup and restore harder rather than
     * easier, and the plugin pays for it: backup annotates the area against the section element
     * and restore maps the old section id to the new one through the 'course_section' mapping.
     * The alternative, filing every section's banner under item id 0, cannot work: they would
     * all collide in one area.
     *
     * @var string
     */
    public const SECTION_AREA = 'sectionbannerimage';

    /**
     * Return the URL of the course overview image, or null when the course has none.
     *
     * @param \stdClass $course Course record.
     * @return string|null Absolute pluginfile URL, or null.
     */
    public static function get_course_image($course) {
        $context = context_course::instance($course->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder DESC, id ASC', false);

        if ($files) {
            $file = reset($files);
            $imageurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename()
            );
            return $imageurl->out();
        }
        return null;
    }

    /**
     * Return the URL of the custom banner image uploaded via the course format settings.
     *
     * Returns null when no custom banner image has been uploaded. Callers fall back to
     * {@see self::get_course_image()} so a course overview image still works if no dedicated
     * banner has been uploaded.
     *
     * @param \stdClass $course Course record.
     * @return string|null Absolute pluginfile URL, or null.
     */
    public static function get_banner_image_url($course) {
        $context = context_course::instance($course->id);
        $fs      = get_file_storage();
        $files   = $fs->get_area_files(
            $context->id,
            'format_aicourse',
            'bannerimage',
            self::BANNER_ITEMID,
            'sortorder DESC, id ASC',
            false
        );

        if ($files) {
            $file = reset($files);
            return moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                'format_aicourse',
                'bannerimage',
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            )->out();
        }

        return null;
    }

    /**
     * Return the URL of a section's own banner image, or null when it has none.
     *
     * "None" is the normal case and is not an error: a section without its own image inherits the
     * course banner. Callers should use {@see self::resolve()} rather than this method unless they
     * specifically need to know whether the section has an image of its own -- for instance to
     * decide whether to offer a "remove" button, which must not offer to remove an inherited one.
     *
     * @param int $courseid Course the section belongs to.
     * @param int $sectionid course_sections.id of the section.
     * @return string|null Absolute pluginfile URL, or null.
     */
    public static function get_section_banner_image_url(int $courseid, int $sectionid): ?string {
        if ($sectionid <= 0) {
            return null;
        }

        $context = context_course::instance($courseid);
        $files = get_file_storage()->get_area_files(
            $context->id,
            'format_aicourse',
            self::SECTION_AREA,
            $sectionid,
            'sortorder DESC, id ASC',
            false
        );

        if ($files) {
            $file = reset($files);
            return moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                'format_aicourse',
                self::SECTION_AREA,
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            )->out();
        }

        return null;
    }

    /**
     * Decide which image the hero should show, and say where it came from.
     *
     * This is the single definition of the fallback chain. Three places used to answer this
     * question -- the course hero, the section hero and the activity hero -- and each answered it
     * with its own two lines of "custom banner, else course image". Adding a third rung to that
     * chain in three copies is how they drift apart, so they now all call this.
     *
     * The chain, most specific first:
     *
     *   1. the section's own banner, when a section is in play and has one;
     *   2. the course banner;
     *   3. the course overview image, which is core's, not this plugin's.
     *
     * `source` matters as much as `url`. The hero offers a "remove image" button, and it must
     * remove the image the person is actually looking at -- offering to remove a section banner
     * that is really the course's, inherited, would delete it from every other section too.
     *
     * @param \stdClass $course Course record.
     * @param int|null $sectionid course_sections.id when a section is in play, null otherwise.
     * @return array{url: string|null, source: string} source is 'section', 'course', 'overview' or ''.
     */
    public static function resolve(\stdClass $course, ?int $sectionid = null): array {
        if ($sectionid !== null && $sectionid > 0) {
            $url = self::get_section_banner_image_url((int) $course->id, $sectionid);
            if ($url !== null) {
                return ['url' => $url, 'source' => 'section'];
            }
        }

        $url = self::get_banner_image_url($course);
        if ($url !== null) {
            return ['url' => $url, 'source' => 'course'];
        }

        $url = self::get_course_image($course);
        if ($url !== null) {
            return ['url' => $url, 'source' => 'overview'];
        }

        return ['url' => null, 'source' => ''];
    }

    /**
     * Return the file area and item id a banner for this target is stored under.
     *
     * One place decides this so the external functions, the adhoc task, the form and the file
     * serving callback cannot disagree about where a given banner lives.
     *
     * @param int $sectionid course_sections.id, or 0 for the course banner.
     * @return array{0: string, 1: int} The file area and the item id.
     */
    public static function target(int $sectionid): array {
        if ($sectionid > 0) {
            return [self::SECTION_AREA, $sectionid];
        }
        return [self::COURSE_AREA, self::BANNER_ITEMID];
    }

    /**
     * Check that a section id really belongs to the given course, and return it.
     *
     * Every external function that accepts a section id calls this. The capability check those
     * functions perform is against the COURSE context, so without this a teacher of course A
     * could pass a section id from course B and have the plugin write a banner into -- or delete
     * one from -- a course they have no rights over. The capability would pass; the target would
     * be somebody else's.
     *
     * @param \stdClass $course The course the caller was authorised against.
     * @param int $sectionid course_sections.id, or 0 for the course banner.
     * @return \section_info|null The section, or null when the target is the course itself.
     */
    public static function require_section_in_course(\stdClass $course, int $sectionid): ?\section_info {
        if ($sectionid <= 0) {
            return null;
        }

        $sectioninfo = get_fast_modinfo($course)->get_section_info_by_id($sectionid, IGNORE_MISSING);
        if (!$sectioninfo) {
            throw new \moodle_exception('error_sectionnotincourse', 'format_aicourse');
        }

        return $sectioninfo;
    }
}
