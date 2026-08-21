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

/**
 * First-run guided tour of the AI Course Format.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\local;

/**
 * Builds the step list for the first-run tour.
 *
 * ACF-FIX-2.1.43. The tour is defined here rather than in JavaScript so every line of narration
 * is a language string and can be translated, and so the steps a learner sees are decided
 * server-side by capability rather than by a flag the browser could be asked to ignore.
 *
 * Two separate tours, not one tour with hidden steps. A teacher and a learner are looking at
 * genuinely different pages -- the learner has no editing controls, no report, and by default no
 * secondary navigation -- so a shared script would spend half its time explaining things one of
 * them cannot see.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tour {
    /** @var string Preference name recording that a user has finished or skipped the tour. */
    public const SEEN_PREF = 'format_aicourse_tour_seen';

    /** @var int Bump when the steps change materially, to re-offer the tour. */
    public const TOUR_VERSION = 3;

    /**
     * Whether this user should be offered the tour on this page.
     *
     * @param \context $context Course context.
     * @return bool
     */
    public static function should_offer(\context $context): bool {
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        $seen = (int) get_user_preferences(self::SEEN_PREF, 0);
        return $seen < self::TOUR_VERSION;
    }

    /**
     * Build the step list appropriate to this user.
     *
     * Every step names a CSS selector to spotlight. A step whose target is absent from the page is
     * dropped by the JavaScript rather than shown pointing at nothing -- a course with no banner,
     * or a site with the AI tutor switched off, simply has a shorter tour.
     *
     * @param \context $context Course context.
     * @return array[] Ordered steps, each with target, title, body and optional audio key.
     */
    public static function get_steps(\context $context): array {
        $canedit = has_capability('moodle/course:update', $context);
        return $canedit ? self::teacher_steps() : self::student_steps();
    }

    /**
     * Steps shown to someone who can edit the course.
     *
     * ACF-FIX-2.1.48: keys are role-prefixed. The two tours share step names -- both have a
     * welcome, both end with done -- but their narration differs, and the audio file is named
     * after the key. Unprefixed, a learner starting the tour would have heard the teacher's
     * script read to them.
     *
     * @return array[]
     */
    protected static function teacher_steps(): array {
        $s = function (string $key): string {
            return get_string($key, 'format_aicourse');
        };
        return [
            [
                'key' => 't_welcome',
                'target' => '',
                'title' => $s('tour_t_welcome_title'),
                'body' => $s('tour_t_welcome_body'),
            ],
            [
                'key' => 't_banner',
                'target' => '.aicourse-hero-banner',
                'title' => $s('tour_t_banner_title'),
                'body' => $s('tour_t_banner_body'),
            ],
            [
                'key' => 't_generate',
                'target' => '.aicourse-ai-generate-banner',
                'title' => $s('tour_t_generate_title'),
                'body' => $s('tour_t_generate_body'),
            ],
            [
                'key' => 't_ring',
                'target' => '.aicourse-progress-ring-container, .aicourse-hero-progress',
                'title' => $s('tour_t_ring_title'),
                'body' => $s('tour_t_ring_body'),
            ],
            [
                'key' => 't_index',
                'target' => '.courseindex, .drawer-left',
                'title' => $s('tour_t_index_title'),
                'body' => $s('tour_t_index_body'),
            ],
            [
                'key' => 't_cards',
                'target' => '.aicourse-cards-grid',
                'title' => $s('tour_t_cards_title'),
                'body' => $s('tour_t_cards_body'),
            ],
            [
                'key' => 't_icons',
                'target' => '.aicourse-card-icon-wrap',
                'title' => $s('tour_t_icons_title'),
                'body' => $s('tour_t_icons_body'),
            ],
            [
                'key' => 't_time',
                'target' => '.aicourse-card-time, .aicourse-activity-card-time',
                'title' => $s('tour_t_time_title'),
                'body' => $s('tour_t_time_body'),
            ],
            [
                'key' => 't_activities',
                'target' => '.aicourse-card-activities, .aicourse-card-dots',
                'title' => $s('tour_t_activities_title'),
                'body' => $s('tour_t_activities_body'),
            ],
            [
                'key' => 't_sidebar',
                'target' => '#aicourse-player-header',
                'title' => $s('tour_t_sidebar_title'),
                'body' => $s('tour_t_sidebar_body'),
            ],
            [
                'key' => 't_grades',
                'target' => '.aicourse-hero-grades',
                'title' => $s('tour_t_grades_title'),
                'body' => $s('tour_t_grades_body'),
            ],
            [
                'key' => 't_tutor',
                'target' => '.aicourse-ai-toggle',
                'title' => $s('tour_t_tutor_title'),
                'body' => $s('tour_t_tutor_body'),
            ],
            [
                'key' => 't_studentview',
                'target' => '#user-menu-toggle, .usermenu',
                'title' => $s('tour_t_studentview_title'),
                'body' => $s('tour_t_studentview_body'),
            ],
            [
                'key' => 't_report',
                'target' => '.secondary-navigation, #page-header',
                'title' => $s('tour_t_report_title'),
                'body' => $s('tour_t_report_body'),
            ],
            [
                'key' => 't_settings',
                'target' => '.secondary-navigation, #page-header',
                'title' => $s('tour_t_settings_title'),
                'body' => $s('tour_t_settings_body'),
            ],
            [
                'key' => 't_done',
                'target' => '',
                'title' => $s('tour_t_done_title'),
                'body' => $s('tour_t_done_body'),
            ],
        ];
    }

    /**
     * Steps shown to a learner.
     *
     * @return array[]
     */
    protected static function student_steps(): array {
        $s = function (string $key): string {
            return get_string($key, 'format_aicourse');
        };
        return [
            [
                'key' => 's_welcome',
                'target' => '',
                'title' => $s('tour_s_welcome_title'),
                'body' => $s('tour_s_welcome_body'),
            ],
            [
                'key' => 's_progress',
                'target' => '.aicourse-hero-banner',
                'title' => $s('tour_s_progress_title'),
                'body' => $s('tour_s_progress_body'),
            ],
            [
                'key' => 's_ring',
                'target' => '.aicourse-progress-ring-container, .aicourse-hero-progress',
                'title' => $s('tour_s_ring_title'),
                'body' => $s('tour_s_ring_body'),
            ],
            [
                'key' => 's_index',
                'target' => '.courseindex, .drawer-left',
                'title' => $s('tour_s_index_title'),
                'body' => $s('tour_s_index_body'),
            ],
            [
                'key' => 's_cards',
                'target' => '.aicourse-cards-grid',
                'title' => $s('tour_s_cards_title'),
                'body' => $s('tour_s_cards_body'),
            ],
            [
                'key' => 's_time',
                'target' => '.aicourse-card-time, .aicourse-activity-card-time',
                'title' => $s('tour_s_time_title'),
                'body' => $s('tour_s_time_body'),
            ],
            [
                'key' => 's_status',
                'target' => '.aicourse-status-badge, .aicourse-card-dots',
                'title' => $s('tour_s_status_title'),
                'body' => $s('tour_s_status_body'),
            ],
            [
                'key' => 's_sidebar',
                'target' => '#aicourse-player-header',
                'title' => $s('tour_s_sidebar_title'),
                'body' => $s('tour_s_sidebar_body'),
            ],
            [
                'key' => 's_grades',
                'target' => '.aicourse-hero-grades',
                'title' => $s('tour_s_grades_title'),
                'body' => $s('tour_s_grades_body'),
            ],
            [
                'key' => 's_tutor',
                'target' => '.aicourse-ai-toggle',
                'title' => $s('tour_s_tutor_title'),
                'body' => $s('tour_s_tutor_body'),
            ],
            [
                'key' => 's_done',
                'target' => '',
                'title' => $s('tour_s_done_title'),
                'body' => $s('tour_s_done_body'),
            ],
        ];
    }

    /**
     * Configuration handed to the AMD module.
     *
     * @param \context $context Course context.
     * @return array
     */
    public static function get_js_config(\context $context): array {
        global $CFG;
        return [
            'steps' => self::get_steps($context),
            'version' => self::TOUR_VERSION,
            'audiobase' => $CFG->wwwroot . '/course/format/aicourse/pix/tour/',
            'voice' => get_config('format_aicourse', 'tourvoice') ?: 'en-AU',
            'autoplay' => (int) (get_config('format_aicourse', 'tourvoiceover') !== '0'),
        ];
    }
}
