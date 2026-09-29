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
 * Card images: the picture at the top of a section card or an activity card.
 *
 * 2.5.0. Every card now has an image area. A teacher fills it from edit mode, by uploading a
 * picture or by asking the AI image service for one; with nothing set, the card shows a neutral
 * placeholder so the grid stays even.
 *
 * Storage follows the section banners (see {@see banner::SECTION_AREA}): files live in the
 * COURSE context under a meaningful item id, one area per kind of card.
 *
 *  - Section cards: area 'sectioncardimage', item id = course_sections.id.
 *  - Activity cards: area 'cmcardimage', item id = course_modules.id.
 *
 * Both are in the course context rather than the module context on purpose: the format owns
 * them, not the activity, and a module context would make every activity's backup, file browser
 * and permission check aware of a file area its own plugin knows nothing about. The price is the
 * same one section banners already pay -- backup has to annotate per section and per module, and
 * restore has to translate the old ids through core's 'course_section' and 'course_module'
 * mappings -- and it is paid in the backup and restore classes.
 *
 * Stateless: every method is static and none of them produce output.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cardimage {
    /** @var string Target type naming a section card. */
    public const TYPE_SECTION = 'section';

    /** @var string Target type naming an activity card. */
    public const TYPE_CM = 'cm';

    /** @var string File area of section card images, item id = course_sections.id. */
    public const SECTION_AREA = 'sectioncardimage';

    /** @var string File area of activity card images, item id = course_modules.id. */
    public const CM_AREA = 'cmcardimage';

    /**
     * Largest accepted image, in bytes, whether uploaded or generated.
     *
     * The browser downscales an upload to at most 1600px wide before sending it, so a real upload
     * arrives well under this. The ceiling is for a client that skips that step.
     *
     * @var int
     */
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** @var array<string, string> Accepted image types and the extension each is stored with. */
    public const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /** @var string[] AI image styles a course can choose, in menu order. */
    public const STYLES = ['photo', 'illustration', 'render3d', 'flat'];

    /**
     * The image style menu, for the course and site settings.
     *
     * @return array<string, string>
     */
    public static function style_options(): array {
        $options = [];
        foreach (self::STYLES as $style) {
            $options[$style] = get_string('cardimagestyle_' . $style, 'format_aicourse');
        }
        return $options;
    }

    /**
     * A course's image style, falling back to photographic for anything unrecognised.
     *
     * @param mixed $value The stored option.
     * @return string One of self::STYLES.
     */
    public static function clean_style($value): string {
        return in_array((string) $value, self::STYLES, true) ? (string) $value : 'photo';
    }

    /**
     * Per-request cache of image URLs, keyed "contextid/area" then item id.
     *
     * A course home page draws one card per section and a section page one card per activity.
     * Asking the file API once per card would be one query per card; {@see self::preload()} asks
     * once per area and every later lookup is served from here.
     *
     * @var array<string, array<int, string>>
     */
    protected static $cache = [];

    /**
     * The file area that holds images for a target type.
     *
     * @param string $type self::TYPE_SECTION or self::TYPE_CM.
     * @return string
     * @throws \coding_exception For any other type.
     */
    public static function area(string $type): string {
        if ($type === self::TYPE_SECTION) {
            return self::SECTION_AREA;
        }
        if ($type === self::TYPE_CM) {
            return self::CM_AREA;
        }
        throw new \coding_exception('Unknown card image type: ' . $type);
    }

    /**
     * Load every card image URL of one kind in a course with a single query.
     *
     * @param int $courseid The course.
     * @param string $type self::TYPE_SECTION or self::TYPE_CM.
     * @return array<int, string> URL keyed by item id.
     */
    public static function preload(int $courseid, string $type): array {
        return self::preload_area($courseid, self::area($type));
    }

    /**
     * Load every image URL in one of the plugin's course-context file areas, with one query.
     *
     * @param int $courseid The course.
     * @param string $area A format_aicourse file area keyed by item id.
     * @return array<int, string> URL keyed by item id.
     */
    protected static function preload_area(int $courseid, string $area): array {
        $context = context_course::instance($courseid);
        $key = $context->id . '/' . $area;

        if (!isset(self::$cache[$key])) {
            $urls = [];
            // Sorted so that if an area ever held two files for one item, the newest one wins,
            // matching get_area_files()'s use elsewhere in the plugin.
            $files = get_file_storage()->get_area_files(
                $context->id,
                'format_aicourse',
                $area,
                false,
                'itemid, timemodified DESC, id DESC',
                false
            );
            foreach ($files as $file) {
                $itemid = (int) $file->get_itemid();
                if (isset($urls[$itemid])) {
                    continue;
                }
                $urls[$itemid] = moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    'format_aicourse',
                    $area,
                    $itemid,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
            }
            self::$cache[$key] = $urls;
        }

        return self::$cache[$key];
    }

    /**
     * Forget cached URLs, after an image has been stored or removed in this request.
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$cache = [];
        self::$colourcache = [];
    }

    /**
     * The card's OWN image URL, or null when it has none.
     *
     * @param int $courseid The course.
     * @param string $type self::TYPE_SECTION or self::TYPE_CM.
     * @param int $id course_sections.id or course_modules.id.
     * @return string|null
     */
    public static function get_url(int $courseid, string $type, int $id): ?string {
        if ($id <= 0) {
            return null;
        }
        return self::preload($courseid, $type)[$id] ?? null;
    }

    /**
     * Decide which picture a card shows, and say where it came from.
     *
     * An activity card shows only its own image. A section card falls back to the section's own
     * banner, so a course that already set section banners in 2.3 gets pictures on its cards
     * without doing anything. It does NOT fall back to the course banner: that would put the same
     * picture on every card, which is worse than the placeholder.
     *
     * `source` decides whether the card offers "Remove image": only when it is 'card'. Removing
     * a card image that is really the section banner would take the banner off the section page.
     *
     * @param int $courseid The course.
     * @param string $type self::TYPE_SECTION or self::TYPE_CM.
     * @param int $id course_sections.id or course_modules.id.
     * @return array{url: string, source: string} source is 'card', 'banner' or ''.
     */
    public static function resolve(int $courseid, string $type, int $id): array {
        $own = self::get_url($courseid, $type, $id);
        if ($own !== null) {
            return ['url' => $own, 'source' => 'card'];
        }
        if ($type === self::TYPE_SECTION) {
            // Read through the same one-query-per-area cache, so a course home page does not
            // ask the file API once per section for a banner most sections do not have.
            $banner = self::preload_area($courseid, banner::SECTION_AREA)[$id] ?? null;
            if ($banner !== null) {
                return ['url' => $banner, 'source' => 'banner'];
            }
        }
        return ['url' => '', 'source' => ''];
    }

    /**
     * Check that a card target really belongs to this course, and return it.
     *
     * Every external function taking a target calls this. They check capability against the
     * course named in the call, so without this a teacher of course A could name a section or an
     * activity of course B and write an image into -- or delete one from -- a course they have no
     * rights over.
     *
     * Section 0 is refused: it never renders as a card.
     *
     * @param \stdClass $course The course the caller was authorised against.
     * @param string $type self::TYPE_SECTION or self::TYPE_CM.
     * @param int $id course_sections.id or course_modules.id.
     * @return \section_info|\cm_info
     * @throws \moodle_exception When the target is not a card of this course.
     */
    public static function require_target(\stdClass $course, string $type, int $id) {
        $modinfo = get_fast_modinfo($course);
        if ($type === self::TYPE_SECTION) {
            $section = $id > 0 ? $modinfo->get_section_info_by_id($id, IGNORE_MISSING) : null;
            if (!$section || (int) $section->section === 0) {
                throw new \moodle_exception('error_sectionnotincourse', 'format_aicourse');
            }
            return $section;
        }
        if ($type === self::TYPE_CM) {
            $cms = $modinfo->get_cms();
            if ($id <= 0 || !isset($cms[$id])) {
                throw new \moodle_exception('error_cmnotincourse', 'format_aicourse');
            }
            return $cms[$id];
        }
        throw new \moodle_exception('error_cardimagetype', 'format_aicourse');
    }

    /**
     * Confirm a byte string is an acceptable image and return the extension to store it with.
     *
     * Shared by uploads and AI generation so both are held to the same rule: size capped, really
     * an image by content rather than by claimed type, and of a type every browser can draw.
     *
     * @param string $bytes The image data.
     * @return string File extension, e.g. 'jpg'.
     * @throws \moodle_exception When the data is not an acceptable image.
     */
    public static function validate_bytes(string $bytes): string {
        if ($bytes === '') {
            throw new \moodle_exception('error_cardimageinvalid', 'format_aicourse');
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new \moodle_exception('error_cardimagetoolarge', 'format_aicourse');
        }
        $info = @getimagesizefromstring($bytes);
        if ($info === false || empty($info['mime']) || !isset(self::ALLOWED_MIMES[$info['mime']])) {
            throw new \moodle_exception('error_cardimageinvalid', 'format_aicourse');
        }
        return self::ALLOWED_MIMES[$info['mime']];
    }

    /**
     * Replace a card's image.
     *
     * @param int $courseid The course.
     * @param string $type self::TYPE_SECTION or self::TYPE_CM.
     * @param int $id course_sections.id or course_modules.id.
     * @param string $bytes Raw image data; validated here.
     * @param string $prefix Filename prefix, 'upload' or 'ai'.
     * @return string The new image's URL.
     */
    public static function store(int $courseid, string $type, int $id, string $bytes, string $prefix = 'upload'): string {
        $extension = self::validate_bytes($bytes);
        $context = context_course::instance($courseid);
        $area = self::area($type);
        $fs = get_file_storage();

        // Scoped by item id, so replacing one card's image cannot touch another card's.
        $fs->delete_area_files($context->id, 'format_aicourse', $area, $id);

        // The timestamp makes the URL change whenever the image does. Files are served with a
        // day's browser cache, so a fixed name would keep showing the old picture.
        $prefix = preg_replace('/[^a-z]/', '', $prefix) ?: 'image';
        try {
            $file = $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'format_aicourse',
                'filearea' => $area,
                'itemid' => $id,
                'filepath' => '/',
                'filename' => $prefix . '_card_' . time() . '.' . $extension,
            ], $bytes);
        } catch (\Exception $e) {
            debugging('format_aicourse card image save failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('error_bannersavefailed', 'format_aicourse');
        }

        self::reset_cache();

        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'format_aicourse',
            $area,
            $id,
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    /**
     * Remove a card's own image.
     *
     * @param int $courseid The course.
     * @param string $type self::TYPE_SECTION or self::TYPE_CM.
     * @param int $id course_sections.id or course_modules.id.
     * @return void
     */
    public static function delete(int $courseid, string $type, int $id): void {
        $context = context_course::instance($courseid);
        get_file_storage()->delete_area_files($context->id, 'format_aicourse', self::area($type), $id);
        self::reset_cache();
    }

    /**
     * Name of the config setting holding one card's AI generation state.
     *
     * One key per card, for the reason section banners got one each: a teacher working down a
     * course starts several generations a few seconds apart, and a shared key would let the poll
     * for one card be answered with another card's image.
     *
     * @param int $courseid The course.
     * @param string $type self::TYPE_SECTION or self::TYPE_CM.
     * @param int $id course_sections.id or course_modules.id.
     * @return string
     */
    public static function status_key(int $courseid, string $type, int $id): string {
        return 'cardstatus_' . $courseid . '_' . ($type === self::TYPE_SECTION ? 's' : 'c') . $id;
    }

    /**
     * Record the state of a card image generation.
     *
     * @param int $courseid The course.
     * @param string $type self::TYPE_SECTION or self::TYPE_CM.
     * @param int $id course_sections.id or course_modules.id.
     * @param string $state queued, running, done or failed.
     * @param string $detail The image URL when done, the reason when failed.
     * @return void
     */
    public static function set_status(int $courseid, string $type, int $id, string $state, string $detail = ''): void {
        set_config(
            self::status_key($courseid, $type, $id),
            json_encode(['state' => $state, 'detail' => $detail, 'time' => time()]),
            'format_aicourse'
        );
    }

    /**
     * Read back the state of a card image generation.
     *
     * @param int $courseid The course.
     * @param string $type self::TYPE_SECTION or self::TYPE_CM.
     * @param int $id course_sections.id or course_modules.id.
     * @return array{state: string, detail: string, time: int}
     */
    public static function get_status(int $courseid, string $type, int $id): array {
        $raw = get_config('format_aicourse', self::status_key($courseid, $type, $id));
        $decoded = ($raw === false || $raw === '') ? null : json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['state'])) {
            return ['state' => 'idle', 'detail' => '', 'time' => 0];
        }
        return [
            'state' => (string) $decoded['state'],
            'detail' => (string) ($decoded['detail'] ?? ''),
            'time' => (int) ($decoded['time'] ?? 0),
        ];
    }

    /**
     * Forget a card's generation state.
     *
     * @param int $courseid The course.
     * @param string $type self::TYPE_SECTION or self::TYPE_CM.
     * @param int $id course_sections.id or course_modules.id.
     * @return void
     */
    public static function clear_status(int $courseid, string $type, int $id): void {
        unset_config(self::status_key($courseid, $type, $id), 'format_aicourse');
    }

    /**
     * Remove everything a deleted card left behind: its image and its generation state.
     *
     * Called from the section and module deletion observers. Both are needed for the same
     * reason as the section banner cleanup: the file is in the course context, which survives,
     * and course module and section ids are eventually reused.
     *
     * @param int $courseid The course.
     * @param string $type self::TYPE_SECTION or self::TYPE_CM.
     * @param int $id course_sections.id or course_modules.id.
     * @return void
     */
    public static function purge_target(int $courseid, string $type, int $id): void {
        try {
            $context = context_course::instance($courseid, IGNORE_MISSING);
        } catch (\moodle_exception $e) {
            $context = false;
        }
        if ($context) {
            get_file_storage()->delete_area_files($context->id, 'format_aicourse', self::area($type), $id);
        }
        self::clear_status($courseid, $type, $id);

        global $DB;
        $DB->delete_records('format_aicourse_cardstyle', ['targettype' => $type, 'targetid' => $id]);

        self::reset_cache();
    }

    /**
     * Per-request cache of card colours, keyed by course id then "type:id".
     *
     * @var array<int, array<string, string>>
     */
    protected static $colourcache = [];

    /**
     * Normalise a colour to lower-case #rrggbb, or return '' when it is not one.
     *
     * The value ends up inside a style attribute as a custom property, so this is the gate that
     * keeps anything but a hex colour out of it.
     *
     * @param string $colour Candidate colour, with or without the leading #, 3 or 6 digits.
     * @return string
     */
    public static function clean_colour(string $colour): string {
        $colour = strtolower(trim($colour));
        if (preg_match('/^#?([0-9a-f]{3})$/', $colour, $m)) {
            $c = $m[1];
            return '#' . $c[0] . $c[0] . $c[1] . $c[1] . $c[2] . $c[2];
        }
        if (preg_match('/^#?([0-9a-f]{6})$/', $colour, $m)) {
            return '#' . $m[1];
        }
        return '';
    }

    /**
     * Whether a colour is light enough that text on it must be dark.
     *
     * WCAG relative luminance. White text holds 4.5:1 down to a luminance of about 0.18, so any
     * colour lighter than that gets dark text instead. Chosen swatches are all dark; this is for
     * a custom colour.
     *
     * @param string $colour #rrggbb.
     * @return bool
     */
    public static function is_light(string $colour): bool {
        $colour = self::clean_colour($colour);
        if ($colour === '') {
            return false;
        }
        $lum = 0.0;
        foreach ([[1, .2126], [3, .7152], [5, .0722]] as [$at, $weight]) {
            $c = hexdec(substr($colour, $at, 2)) / 255;
            $c = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            $lum += $weight * $c;
        }
        return $lum > 0.18;
    }

    /**
     * Every card colour in a course, loaded with one query.
     *
     * @param int $courseid The course.
     * @return array<string, string> Colour keyed "section:ID" or "cm:ID".
     */
    public static function preload_colours(int $courseid): array {
        global $DB;
        if (!isset(self::$colourcache[$courseid])) {
            $map = [];
            $rows = $DB->get_records(
                'format_aicourse_cardstyle',
                ['courseid' => $courseid],
                '',
                'id, targettype, targetid, colour'
            );
            foreach ($rows as $row) {
                $map[$row->targettype . ':' . $row->targetid] = $row->colour;
            }
            self::$colourcache[$courseid] = $map;
        }
        return self::$colourcache[$courseid];
    }

    /**
     * The colour a teacher chose for a card, or '' when none.
     *
     * @param int $courseid The course.
     * @param string $type self::TYPE_SECTION or self::TYPE_CM.
     * @param int $id course_sections.id or course_modules.id.
     * @return string
     */
    public static function get_colour(int $courseid, string $type, int $id): string {
        return self::preload_colours($courseid)[$type . ':' . $id] ?? '';
    }

    /**
     * Set or clear a card's colour.
     *
     * @param int $courseid The course.
     * @param string $type self::TYPE_SECTION or self::TYPE_CM.
     * @param int $id course_sections.id or course_modules.id.
     * @param string $colour A hex colour, or '' to clear it.
     * @return string The stored colour, '' when cleared.
     * @throws \moodle_exception When the colour is neither empty nor a hex colour.
     */
    public static function set_colour(int $courseid, string $type, int $id, string $colour): string {
        global $DB, $USER;

        self::area($type);
        $clean = self::clean_colour($colour);
        if ($clean === '' && trim($colour) !== '') {
            throw new \moodle_exception('error_cardcolour', 'format_aicourse');
        }

        $existing = $DB->get_record('format_aicourse_cardstyle', ['targettype' => $type, 'targetid' => $id]);
        if ($clean === '') {
            if ($existing) {
                $DB->delete_records('format_aicourse_cardstyle', ['id' => $existing->id]);
            }
        } else if ($existing) {
            $existing->colour = $clean;
            $existing->courseid = $courseid;
            $existing->usermodified = (int) $USER->id;
            $existing->timemodified = time();
            $DB->update_record('format_aicourse_cardstyle', $existing);
        } else {
            $DB->insert_record('format_aicourse_cardstyle', (object) [
                'courseid' => $courseid,
                'targettype' => $type,
                'targetid' => $id,
                'colour' => $clean,
                'usermodified' => (int) $USER->id,
                'timemodified' => time(),
            ]);
        }

        unset(self::$colourcache[$courseid]);
        return $clean;
    }

    /**
     * Remove every card generation state of a deleted course.
     *
     * The images themselves go with the course context. The state rows are plugin config and
     * would not.
     *
     * @param int $courseid The deleted course.
     * @return void
     */
    public static function purge_course_status(int $courseid): void {
        global $DB;

        $like = $DB->sql_like('name', ':name');
        $DB->delete_records_select(
            'config_plugins',
            "plugin = :plugin AND $like",
            ['plugin' => 'format_aicourse', 'name' => 'cardstatus\_' . $courseid . '\_%']
        );
        // Written behind set_config()'s back, so its cache has to be told.
        \cache_helper::invalidate_by_definition('core', 'config', [], 'format_aicourse');

        $DB->delete_records('format_aicourse_cardstyle', ['courseid' => $courseid]);
        self::reset_cache();
    }
}
