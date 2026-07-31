# Changelog - AI Course Format Plugin

All notable changes to this plugin will be documented in this file.

## [1.7.47] - 2026-04-28

### Fixed
- **BUG: ajax.php saveicon — Remove icon button silently failed on server** — The `saveicon` AJAX case used `required_param('icon', PARAM_ALPHANUMEXT)` which passes, then validated the key against the icon library. An empty string (sent by the JS "Remove icon" button) passes through the param cleaning as `''`, is not in the icon library, and was rejected with `{"success":false,"error":"Invalid icon"}` — meaning the UI updated correctly but the database was never cleared. Fix: changed to `optional_param('icon', '', PARAM_ALPHANUMEXT)` and skip the library validation when the key is empty. Empty string is now explicitly accepted and stored to clear the icon.
- **BUG: lang/en/format_aicourse.php — `searchicons` string showed literal `\u2026`** — PHP single-quoted strings do not interpret `\u2026` as a Unicode ellipsis. The icon picker search placeholder rendered as `Search icons\u2026` instead of `Search icons…`. Fixed by replacing `\u2026` with the actual UTF-8 ellipsis character `…`.

## [1.7.46] - 2026-04-28

### Improved
- **ICON-UX-v1.7.46** — Major icon picker UX overhaul for section card icons.
  - **Always-visible label**: Each section card now shows a small "Add icon" or "Change icon" label directly below the icon box (visible at all times for editors, not just on hover). The old hover-only pencil badge is removed.
  - **Category groupings**: The icon picker is now organised into sections — Numbers, Education, Work & Industry, Safety & Compliance, People, Achievement, General — with a bold category heading above each group.
  - **Icon labels**: Every icon in the picker now displays its name below the icon for easy identification.
  - **Live search**: A search bar at the top of the picker lets editors filter icons by name in real time; unmatched icons and their category headings are hidden automatically.
  - **Remove icon**: A clearly styled "Remove icon" button (with ×) appears at the top of the picker to clear a section's icon.
  - **JS changes**: Click selector updated from `.aicourse-card-icon-wrap.aicourse-card-icon-editable` to `.aicourse-icon-col.aicourse-card-icon-editable`; SVG extracted from `$(this).find('svg').prop('outerHTML')` (not button innerHTML which includes the label); `__clear__` key handled (restores pencil placeholder + resets label text); search filter handler wired to `.aicourse-icon-search-input`; search resets and focus moves to search input on picker open.
  - **CSS changes**: Added `.aicourse-icon-col`, `.aicourse-icon-change-label`, `.aicourse-icon-picker-search`, `.aicourse-icon-picker-body`, `.aicourse-icon-picker-category`, `.aicourse-icon-picker-category-label`, `.aicourse-icon-picker-label`, `.aicourse-icon-remove-btn`, `.aicourse-icon-picker-grid--remove`; picker widened to 560 px; grid is 7 columns; item min-height 62 px to accommodate label; dark mode variants added for all new elements.

## [1.7.45] - 2026-04-28

### Fixed
- **UX-ACF-GENERAL-INLINE** — Section 0 (General) is now rendered inline above the section cards grid instead of as a card. The section summary is shown first (if set), followed by the General section's activity cards. This matches standard Moodle behaviour where General content appears at the top of the course home page. Empty General sections (no activities, no summary) produce no output. No AMD or DB changes.

## [1.7.44] - 2026-04-28

### Fixed
- **UX-ACF-BREADCRUMB-ALWAYS** — Breadcrumb navigation bar (showing course > section > activity path) now always visible regardless of edit mode. Previously the `#page-navbar` / `.breadcrumb` / `nav[aria-label="Navigation bar"]` were hidden in non-edit mode on course-home, section, and activity pages. CSS-only change — both hide rule blocks removed. No PHP, AMD, or DB changes.

## [1.7.43] - 2026-04-28

### Fixed
- **UX-ACF-EDITMODE-WIPE** — Turning on Edit mode no longer replaces the custom card layout with the stock Moodle Topics accordion. The card view now persists regardless of edit mode state, so teachers always see the same UI.
- **UX-ACF-ICONPICKER** — Section icon picker was inaccessible: PHP rendered it only in non-edit mode, but CSS enabled clicks only in edit mode. Both constraints removed. The icon is now clickable for any user with course:update capability at all times. A pencil badge now appears on hover (not just in edit mode).
- **UX-ACF-ADDSECTION** — "Add Section" dashed card was never visible: PHP rendered it in non-edit mode, but CSS showed it only in edit mode. CSS gate removed. Editors now see the Add Section card at all times in the card view.
- **UX-ACF-GENERAL-CARD** — Section 0 (General) no longer appears as a confusing placeholder card when it has no activities and no summary. Courses that use General for actual content are unaffected.
- **UX-ACF-EDITBTNS** — Card delete/duplicate buttons were rendered for all users (no capability check). Now PHP-gated by `$canedit` so students never see edit controls. A new gear (settings) button is added alongside delete/duplicate, linking to `editsection.php` so teachers can rename a section or set restrictions without needing the Topics editor. All three buttons are hidden at rest and revealed on card hover.

## [1.7.18] - 2026-03-27

### Changed
- Hero banner now shows **course title only** — the shortname label pill and the book icon have been removed from the course-home hero header. The plain course title text is the sole heading element.

## [1.6.0] - 2026-03-16

### Added
- New site-wide admin Q&A report (`admin_report.php`) — site administrators can
  now view every AI Tutor question and response across all courses from a single
  page, with filters by course, student, rating, refused/answered status, and
  date range, plus a one-click CSV export.
- "View all AI Tutor Q&A" button added to Site Administration > Plugins > Course
  formats > AI Course Format settings page for quick access.
- 32 new lang strings supporting the admin report UI.

## [1.5.95] - 2026-02-12

### Fixed
- Cast modfullname to string before array lookup to prevent PHP 8 "Illegal offset type" error

## [1.5.94] - 2026-02-12

### Changed
- Activity type labels now use friendlier names (Learning Content, Learning Activities, Knowledge Check, Learning Slides)

## [1.5.93] - 2026-02-12

### Fixed
- Activity card titles (h4) now respect card title size setting - overrides Moodle theme heading styles with !important

## [1.5.92] - 2026-02-12

### Fixed
- Card title size setting now applies to both section cards and activity cards consistently

## [1.5.91] - 2026-02-12

### Added
- Card title text size setting - choose exact font size in pixels for section and activity card titles

## [1.5.90] - 2026-02-12

### Added
- Hero banner width setting - match banner to your theme's content width in pixels
- Hero banner alignment setting - choose centre or left alignment

## [1.5.89] - 2026-02-12

### Changed
- Section title now stacks directly above activity title in hero banner, saving vertical space

## [1.5.88] - 2026-02-12

### Fixed
- Section nav links no longer crash on activities without URLs (e.g. labels)

## [1.5.87] - 2026-02-12

### Fixed
- Section 0 now correctly shows activity cards (PHP empty() treats 0 as empty)

## [1.5.86] - 2026-02-12

### Fixed
- Section 0 (Course Instructions) card now navigates correctly instead of staying on same page

## [1.5.84] - 2026-02-11

### Added
- AI Tutor: Distinctive purple gradient button with sparkles icon
- Chat memory persists across page navigation

## [1.5.83] - 2026-02-11

### Added
- AI Tutor now passes all quiz questions to AI for full question awareness

## [1.5.82] - 2026-02-11

### Fixed
- Quiz question retrieval now works with Moodle 4.x question bank (question_references table)

## [1.5.81] - 2026-02-11

### Added
- AI Tutor now fetches activity context (questions, instructions) from server for full awareness

## [1.5.80] - 2025-12-30

### Added
- Debug console.log output for AI Tutor troubleshooting (tagged "[AI Tutor Debug v1.5.80]")
- Logs request URL, body, context, response status, and parsed data
- Helps diagnose "Invalid request parameters" errors

## [1.5.79] - 2025-12-29

### Fixed
- upgrade.php schema repair section now always runs to fix missing tables/columns
- Handles cases where plugin was installed at a higher version or database got corrupted
- Proper Moodle-compliant solution - all schema changes in upgrade.php, not ajax.php

## [1.5.78] - 2025-12-29

### Fixed
- Use cm_info.sectionnum for correct section name lookup in AI tutor AJAX
- dbrepair action added to fix missing tables/columns without requiring upgrade
