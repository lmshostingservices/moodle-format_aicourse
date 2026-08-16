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

/**
 * Section icon library, categories, labels and per-section icon storage.
 *
 * Stateless service class: every method is static and none of them produce output.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class icons {
    /**
     * In-process cache of section icons, keyed on "courseid_sectionid".
     *
     * OPT-ACF-ICON-CACHE (v1.7.51): shared by {@see self::preload_section_icons()},
     * {@see self::get_section_icon()} and {@see self::set_section_icon()} so a page render
     * reads every section icon with a single DB query. This replaces the former
     * format_aicourse_icon_cache_ref() by-reference accessor function.
     *
     * @var array<string, string>
     */
    private static $sectioniconcache = [];

    /**
     * Get the icon library for section cards.
     *
     * The values are inline SVG fragments (without the wrapping <svg> element), keyed on the
     * stable icon key stored in course_format_options.
     *
     * @return array<string, string> Icon key => inline SVG body.
     */
    public static function get_library() {
        return [
        // Numbers.
        'num-1' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">1</text>',
        'num-2' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">2</text>',
        'num-3' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">3</text>',
        'num-4' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">4</text>',
        'num-5' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">5</text>',
        'num-6' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">6</text>',
        'num-7' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">7</text>',
        'num-8' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">8</text>',
        'num-9' => '<text x="12" y="16" text-anchor="middle" font-size="14" font-weight="600" fill="currentColor">9</text>',
        'num-10' => '<text x="12" y="16" text-anchor="middle" font-size="12" font-weight="600" fill="currentColor">10</text>',
        // Education.
        'book' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20" fill="none" stroke="current'
            . 'Color" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'book-open' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" fill="none" stroke="currentColor" stroke-width="2'
            . '" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" fill="none" stroke="currentColor" stroke-width='
            . '"2" stroke-linecap="round" stroke-linejoin="round"/>',
        'graduation' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z" fill="none" stroke="currentColor" stroke-width="2" stroke-li'
            . 'necap="round" stroke-linejoin="round"/>'
            . '<path d="M6 12v5c3 3 9 3 12 0v-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap='
            . '"round" stroke-linejoin="round"/>',
        'pen' => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" fill="none" stroke="currentColor" stroke-'
            . 'width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'clipboard' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" fill="none" strok'
            . 'e="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<rect width="8" height="4" x="8" y="2" rx="1" ry="1" fill="none" stroke="currentColor" stroke-width='
            . '"2"/>',
        'file-text' => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" fill="none" stroke="'
            . 'currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<polyline points="14 2 14 8 20 8" fill="none" stroke="currentColor" stroke-width="2"/>'
            . '<line x1="16" x2="8" y1="13" y2="13" stroke="currentColor" stroke-width="2"/>'
            . '<line x1="16" x2="8" y1="17" y2="17" stroke="currentColor" stroke-width="2"/>',
        // Work & Safety.
        'briefcase' => '<rect width="20" height="14" x="2" y="7" rx="2" ry="2" fill="none" stroke="currentColor" stroke-widt'
            . 'h="2"/>'
            . '<path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" fill="none" stroke="currentColor" stroke-width='
            . '"2"/>',
        'hard-hat' => '<path d="M2 18a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v2z" fill="none" strok'
            . 'e="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M10 15V6a4 4 0 0 1 4-4v0a2 2 0 0 1 2 2v0" fill="none" stroke="currentColor" stroke-width="2'
            . '" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M14 15V6a4 4 0 0 0-4-4v0a2 2 0 0 0-2 2v0" fill="none" stroke="currentColor" stroke-width="2'
            . '" stroke-linecap="round" stroke-linejoin="round"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" fill="none" stroke="currentColor" stroke-width='
            . '"2" stroke-linecap="round" stroke-linejoin="round"/>',
        'shield-check' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" fill="none" stroke="currentColor" stroke-width='
            . '"2" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="m9 12 2 2 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" st'
            . 'roke-linejoin="round"/>',
        'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" fill="none" stro'
            . 'ke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<line x1="12" x2="12" y1="9" y2="13" stroke="currentColor" stroke-width="2"/>'
            . '<line x1="12" x2="12.01" y1="17" y2="17" stroke="currentColor" stroke-width="2"/>',
        // Tools & Tech.
        'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a'
            . '2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" fill="none" stroke="currentColor" str'
            . 'oke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'settings' => '<circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/>'
            . '<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.'
            . '65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a'
            . '1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1'
            . '.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.'
            . '82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.5'
            . '1V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 '
            . '1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2'
            . ' 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" fill="none" stroke="currentColor" stroke-width="2"/>',
        'monitor' => '<rect width="20" height="14" x="2" y="3" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>'
            . '<line x1="8" x2="16" y1="21" y2="21" stroke="currentColor" stroke-width="2"/>'
            . '<line x1="12" x2="12" y1="17" y2="21" stroke="currentColor" stroke-width="2"/>',
        'laptop' => '<path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0'
            . ' 0 1-.9-1.45L4 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-'
            . 'linejoin="round"/>',
        // General.
        'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="none" stroke="currentColor" stroke-wi'
            . 'dth="2" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<polyline points="9 22 9 12 15 12 15 22" fill="none" stroke="currentColor" stroke-width="2" stroke-l'
            . 'inecap="round" stroke-linejoin="round"/>',
        'info' => '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>'
            . '<path d="M12 16v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
            . '<path d="M12 8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="'
            . '2" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<circle cx="9" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/>'
            . '<path d="M22 21v-2a4 4 0 0 0-3-3.87" fill="none" stroke="currentColor" stroke-width="2" stroke-linec'
            . 'ap="round" stroke-linejoin="round"/>'
            . '<path d="M16 3.13a4 4 0 0 1 0 7.75" fill="none" stroke="currentColor" stroke-width="2" stroke-lineca'
            . 'p="round" stroke-linejoin="round"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="'
            . '2" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<circle cx="12" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/>',
        'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.9'
            . '1 8.26 12 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejo'
            . 'in="round"/>',
        'heart' => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A'
            . '5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" fill="none" stroke="currentColor" stroke-width="2" st'
            . 'roke-linecap="round" stroke-linejoin="round"/>',
        'target' => '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>'
            . '<circle cx="12" cy="12" r="6" fill="none" stroke="currentColor" stroke-width="2"/>'
            . '<circle cx="12" cy="12" r="2" fill="none" stroke="currentColor" stroke-width="2"/>',
        'trophy' => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6" fill="none" stroke="currentColor" stroke-width="2" stroke-lin'
            . 'ecap="round" stroke-linejoin="round"/>'
            . '<path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18" fill="none" stroke="currentColor" stroke-width="2" stroke-l'
            . 'inecap="round" stroke-linejoin="round"/>'
            . '<path d="M4 22h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-'
            . 'linejoin="round"/>'
            . '<path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22" fill="none" stroke="currentColo'
            . 'r" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22" fill="none" stroke="currentCol'
            . 'or" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M18 2H6v7a6 6 0 0 0 12 0V2Z" fill="none" stroke="currentColor" stroke-width="2" stroke-line'
            . 'cap="round" stroke-linejoin="round"/>',
        'flag' => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" fill="none" stroke="currentColor'
            . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<line x1="4" x2="4" y1="22" y2="15" stroke="currentColor" stroke-width="2"/>',
        'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" fill="none" stroke="currentColor" stroke-width="2" stro'
            . 'ke-linecap="round" stroke-linejoin="round"/>'
            . '<polyline points="22 4 12 14.01 9 11.01" fill="none" stroke="currentColor" stroke-width="2" stroke-l'
            . 'inecap="round" stroke-linejoin="round"/>',
        'lightbulb' => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.'
            . '5 1.5 2.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin'
            . '="round"/>'
            . '<path d="M9 18h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-l'
            . 'inejoin="round"/>'
            . '<path d="M10 22h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-'
            . 'linejoin="round"/>',
        'clock' => '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>'
            . '<polyline points="12 6 12 12 16 14" fill="none" stroke="currentColor" stroke-width="2" stroke-lineca'
            . 'p="round" stroke-linejoin="round"/>',
        'calendar' => '<rect width="18" height="18" x="3" y="4" rx="2" ry="2" fill="none" stroke="currentColor" stroke-widt'
            . 'h="2"/><line x1="16" x2="16" y1="2" y2="6" stroke="currentColor" stroke-width="2"/>'
            . '<line x1="8" x2="8" y1="2" y2="6" stroke="currentColor" stroke-width="2"/>'
            . '<line x1="3" x2="21" y1="10" y2="10" stroke="currentColor" stroke-width="2"/>',
        'map-pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" fill="none" stroke="currentColor" stroke-wi'
            . 'dth="2"/><circle cx="12" cy="10" r="3" fill="none" stroke="currentColor" stroke-width="2"/>',
        'rocket' => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z" '
            . 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-'
            . '4 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="rou'
            . 'nd"/>'
            . '<path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0" fill="none" stroke="currentColor" stroke-width="2" '
            . 'stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5" fill="none" stroke="currentColor" stroke-width="2"'
            . ' stroke-linecap="round" stroke-linejoin="round"/>',
        'zap' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" fill="none" stroke="currentColor" stroke-wi'
            . 'dth="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'layers' => '<polygon points="12 2 2 7 12 12 22 7 12 2" fill="none" stroke="currentColor" stroke-width="2" stroke'
            . '-linecap="round" stroke-linejoin="round"/>'
            . '<polyline points="2 17 12 22 22 17" fill="none" stroke="currentColor" stroke-width="2" stroke-lineca'
            . 'p="round" stroke-linejoin="round"/>'
            . '<polyline points="2 12 12 17 22 12" fill="none" stroke="currentColor" stroke-width="2" stroke-lineca'
            . 'p="round" stroke-linejoin="round"/>',
        'package' => '<path d="m7.5 4.27 9 5.15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
            . ' stroke-linejoin="round"/>'
            . '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0'
            . ' 0 2 0l7-4A2 2 0 0 0 21 16Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="roun'
            . 'd" stroke-linejoin="round"/>'
            . '<path d="m3.3 7 8.7 5 8.7-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="roun'
            . 'd" stroke-linejoin="round"/>'
            . '<path d="M12 22V12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke'
            . '-linejoin="round"/>',
        'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" fill="none" stroke="currentC'
            . 'olor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'help-circle' => '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>'
            . '<path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" fill="none" stroke="currentColor" stroke-width="2" st'
            . 'roke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'play-circle' => '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>'
            . '<polygon points="10 8 16 12 10 16 10 8" fill="none" stroke="currentColor" stroke-width="2" stroke-li'
            . 'necap="round" stroke-linejoin="round"/>',
        'folder' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" fill="none" st'
            . 'roke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2" fill="none" stroke="currentColor" stroke-wid'
            . 'th="2"/>'
            . '<path d="M7 11V7a5 5 0 0 1 10 0v4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap'
            . '="round" stroke-linejoin="round"/>',
        'award' => '<circle cx="12" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="2"/>'
            . '<path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11" fill="none" stroke="currentColor" stroke-width="2"'
            . ' stroke-linecap="round" stroke-linejoin="round"/>',
        ];
    }

    /**
     * Get icon categories for the section card icon picker.
     *
     * ACF-FIX-2.0: i18n — the array was keyed on hardcoded English category names which were
     * used BOTH as the visible label and as the data-category value read by the JS filter.
     * It is now keyed on a stable untranslated slug (safe for data-category) whose visible label
     * is resolved through get_string() at render time.
     *
     * @return array<string, string[]> Category slug => ordered list of icon keys.
     */
    public static function get_categories() {
        return [
            'numbers'    => ['num-1', 'num-2', 'num-3', 'num-4', 'num-5',
                             'num-6', 'num-7', 'num-8', 'num-9', 'num-10'],
            'education'  => ['book', 'book-open', 'graduation', 'pen',
                             'clipboard', 'file-text', 'lightbulb', 'play-circle'],
            'work'       => ['briefcase', 'hard-hat', 'wrench', 'settings',
                             'monitor', 'laptop', 'layers', 'package', 'folder'],
            'safety'     => ['shield', 'shield-check', 'alert-triangle',
                             'check-circle', 'lock'],
            'people'     => ['users', 'user', 'message', 'help-circle'],
            'achievement' => ['target', 'trophy', 'flag', 'star', 'heart',
                              'award', 'rocket', 'zap'],
            'general'    => ['home', 'info', 'clock', 'calendar', 'map-pin'],
        ];
    }

    /**
     * ACF-FIX-2.0: Localised, human-readable label for an icon key.
     *
     * Labels used to be derived from the English key with
     * ucfirst(str_replace('-', ' ', ...)) — producing "Book open", "Hard hat", "Map pin". They were
     * English-only, so the picker's search box only ever matched English words, and ucfirst() is
     * byte-based so it mangles multi-byte first characters.
     *
     * @param string $key Icon key from {@see self::get_library()}.
     * @return string Localised label.
     */
    public static function get_label($key) {
        if (preg_match('/^num-(\d+)$/', $key, $m)) {
            return get_string('iconnumber', 'format_aicourse', $m[1]);
        }
        return get_string('icon_' . str_replace('-', '_', $key), 'format_aicourse');
    }

    /**
     * OPT-ACF-BULK-ICONS (v1.7.51): Preload icons for multiple sections in a single
     * DB query. Call this once before iterating over sections to eliminate the N
     * individual get_field() calls that {@see self::get_section_icon()} would
     * otherwise make — one per section.
     *
     * @param int $courseid Course id.
     * @param int[] $sectionids Array of section record ids.
     * @return void
     */
    public static function preload_section_icons($courseid, array $sectionids) {
        global $DB;
        if (empty($sectionids)) {
            return;
        }

        // Only fetch rows that are not already in the cache.
        $toload = [];
        foreach ($sectionids as $sid) {
            if (!array_key_exists($courseid . '_' . $sid, self::$sectioniconcache)) {
                $toload[] = 'sectionicon_' . (int)$sid;
            }
        }
        if (empty($toload)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($toload, SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;
        $params['format']   = 'aicourse';
        $rows = $DB->get_records_select(
            'course_format_options',
            "courseid = :courseid AND format = :format AND name $insql",
            $params,
            '',
            'name, value'
        );

        // Populate cache from results.
        $found = [];
        foreach ($rows as $row) {
            if (preg_match('/^sectionicon_(\d+)$/', $row->name, $m)) {
                $sid = (int)$m[1];
                self::$sectioniconcache[$courseid . '_' . $sid] = $row->value;
                $found[$sid] = true;
            }
        }
        // Mark sections with no saved icon so get_section_icon() skips the DB.
        foreach ($sectionids as $sid) {
            if (!isset($found[(int)$sid])) {
                self::$sectioniconcache[$courseid . '_' . $sid] = '';
            }
        }
    }

    /**
     * Get the icon key saved against a section, or '' when none is set.
     *
     * @param int $courseid Course id.
     * @param int $sectionid course_sections.id of the section.
     * @return string Icon key, or '' when the section has no icon.
     */
    public static function get_section_icon($courseid, $sectionid) {
        global $DB;
        $cachekey = $courseid . '_' . $sectionid;
        if (array_key_exists($cachekey, self::$sectioniconcache)) {
            return self::$sectioniconcache[$cachekey];
        }
        // Cache miss — single DB query (also populates cache for next call).
        $key   = 'sectionicon_' . $sectionid;
        $value = $DB->get_field('course_format_options', 'value', [
            'courseid' => $courseid,
            'format'   => 'aicourse',
            'name'     => $key,
        ]);
        self::$sectioniconcache[$cachekey] = $value ? $value : '';
        return self::$sectioniconcache[$cachekey];
    }

    /**
     * Set section icon in course format options.
     *
     * @param int $courseid Course id.
     * @param int $sectionid course_sections.id of the section to set the icon on.
     * @param string $icon Icon key from {@see self::get_library()}, or '' to clear.
     * @return bool True on success, false when the section does not belong to the course.
     */
    public static function set_section_icon($courseid, $sectionid, $icon) {
        global $DB;

        $courseid  = (int) $courseid;
        $sectionid = (int) $sectionid;

        // ACF-FIX-2.0: verify the section actually belongs to this course. Without this check the
        // AJAX endpoint would happily write a sectionicon_<id> row keyed on ANY section id — a
        // teacher in course A could stamp icon rows for sections of course B.
        if (!$DB->record_exists('course_sections', ['id' => $sectionid, 'course' => $courseid])) {
            return false;
        }

        $key = 'sectionicon_' . $sectionid;
        $existing = $DB->get_record('course_format_options', [
            'courseid' => $courseid,
            'format' => 'aicourse',
            'name' => $key,
        ]);
        if ($existing) {
            $DB->update_record('course_format_options', (object)[
                'id' => $existing->id,
                'value' => $icon,
            ]);
        } else {
            $DB->insert_record('course_format_options', (object)[
                'courseid' => $courseid,
                'format' => 'aicourse',
                'sectionid' => 0,
                'name' => $key,
                'value' => $icon,
            ]);
        }

        // ACF-FIX-2.0: refresh the in-process icon cache. It was never invalidated after a write, so
        // anything rendered later in the same request kept showing the previous icon.
        self::$sectioniconcache[$courseid . '_' . $sectionid] = (string) $icon;

        return true;
    }
}
