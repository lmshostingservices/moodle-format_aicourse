# Changelog - AI Course Format Plugin

All notable changes to this plugin will be documented in this file.

## [2.1.2] - 2026-08-16

### Added

- **"Show activities on cards" course setting.** A new course-level format option,
  `showactivitiesoncards`, **off by default**. With it off the section cards render exactly as
  before — the template emits no extra markup at all, so a default course is unchanged to the
  pixel. With it on, each section card lists that section's activities beneath the summary: one
  compact row per activity carrying the activity name and its completion state, capped at four
  with a `+N` overflow link into the section, following the `+N` idiom the card's progress dots
  already use. The list is a real `<ul>`; each link's accessible name carries the activity name,
  the section name (so the link on one card is distinguishable from the identically-named link on
  the next) and the completion state, and the state marker differs in shape as well as colour.
  Visibility uses the same predicate as the rest of the plugin —
  `activityinfo::cm_counts_as_content()` plus `$cm->uservisible` — so a learner can never see an
  activity here that they cannot see elsewhere. The footer stays pinned by
  `margin-block-start: auto`, so cards in a row still end level.
- **Per-course override for sharing assessment answers with the AI Tutor.** The site setting
  `format_aicourse/shareassessmentanswers` becomes a three-value select whose stored values are
  unchanged, so no site's behaviour changes on upgrade: `0` = never share (still the default),
  `1` = always share in every course (what a site that ticked the old checkbox holds), `2` = let
  each course decide. A new course-level option, also `shareassessmentanswers` and defaulting to
  no, is consulted only in state `2`. **The site setting is the ceiling** — a course can never opt
  into something the site has not permitted, and the check lives in exactly one place,
  `contentindex::may_share_assessment_answers()`, which now takes the course id. Callers with no
  course in hand get the safe answer: do not share. The AI index cache key carries the EFFECTIVE
  resolved value, so two courses with different per-course settings can never be served each
  other's index, and turning sharing off can never keep serving an answer-bearing one.

### Fixed

- The course settings form emitted a developer-level debugging message because
  `bannerimage_help` was never defined, although `create_edit_form_elements()` asked for it.

## [2.0.0] - 2026-08-15

The first release prepared against the Moodle Plugins Directory approval checklist. This is a
large release: it fixes a headline rendering bug, closes three security issues, replaces a
false privacy declaration with a real one, and completes accessibility, internationalisation
and visual-design passes across the whole plugin.

**Minimum Moodle version raised to 4.4** (`$plugin->requires = 2024042200`). The plugin
registers a callback for `\core\hook\output\before_standard_footer_html_generation`, which was
introduced in Moodle 4.4; the previously declared minimum of 4.0 was never actually supported.
`$plugin->supported` is now `[404, 500]`.

`$plugin->release` is now a plain semantic version. The release history that had accumulated
inside that string has been moved into this file, where it belongs.

### Fixed

- **General section no longer disappears from the course home page.** A General section (section
  0) containing only a label, or a label plus activities, rendered as empty. The General section
  and all of its activities, labels included, now always render inline above the section cards.
  Covered by a Behat regression test in `tests/behat/course_cards.feature`.

### Security

- **Course AI Tutor report access control.** `report.php` did not enforce a capability, so any
  user who could guess the URL could read every question every student in the course had asked.
  It now requires `format/aicourse:viewreport` in the course context. That capability is
  declared with `RISK_PERSONAL`.
- **Answer-key leakage.** The activity-context endpoint returned quiz question data including
  correct answers to any caller. Access is now checked against the calling user's visibility of
  the activity before any question data is returned.
- **Chat-correction forgery.** The `correctchat` action accepted any chat id in the course and
  wrote the caller's id into `correctedby` without a capability check appropriate to the action.
  Correction is now a distinct, auditable operation, and a new `format/aicourse:correctresponses`
  capability (`RISK_XSS | RISK_PERSONAL`) exists to govern it.
- **Debug endpoint removed.** `diag.php`, an unauthenticated diagnostic dump, has been deleted
  from the plugin.

### Added

- **Real privacy provider.** `\format_aicourse\privacy\provider` previously implemented
  `null_provider` and the lang pack claimed the plugin stored no personal data. Both were false.
  It now implements `metadata\provider`, `request\plugin\provider` and
  `request\core_userlist_provider`, declaring both database tables field by field and declaring
  the outbound transfer to lms-labs.com via `add_external_location_link()`. Export, per-user
  delete, per-context delete and bulk user delete are all implemented against the course context.
  Where a user appears only as the author of a correction on another user's row, the row is kept
  and the `correctedby` / `timecorrected` attribution is nulled instead.
- **Cache invalidation that actually runs.** `db/caches.php` claimed the `coursecontent` cache
  was invalidated by a `format_aicourse_course_updated()` hook that did not exist, so the AI
  Tutor could answer from content up to ten minutes stale with no way to refresh it. A new
  `db/events.php` registers `\format_aicourse\observer` against `course_module_created`,
  `course_module_updated`, `course_module_deleted`, `course_section_updated`, `course_updated`
  and `course_deleted`, and the cache is now purged whenever course content changes.
- **Course deletion cleanup.** Deleting a course now removes its rows from
  `format_aicourse_chats` and `format_aicourse_ai_memory`, which previously survived the course.
- **New capabilities.** `format/aicourse:useaitutor` (`RISK_PERSONAL`) makes it possible to stop
  a role from sending data to the external AI service, and `format/aicourse:correctresponses`
  governs writing a correction onto another user's response. `format/aicourse:viewreport` gained
  `RISK_PERSONAL`.
- **Database indexes.** `courseid_userid_idx` and `courseid_timecreated_idx` on
  `format_aicourse_chats`, matching how the reports and the privacy provider actually query it,
  plus a foreign key on `correctedby`. `db/upgrade.php`, which previously did nothing, now
  applies them, and repairs the tables on sites where an early release created them at runtime
  instead of from `install.xml`.
- **Documentation.** A real `README.md` covering features, requirements, both installation
  routes, every site setting and course option, the external-service disclosure, the privacy and
  GDPR position, and an accessibility statement. `LICENSE` now contains the full GPLv3 text
  rather than an eleven-line summary.
- **Test suite.** `tests/privacy/provider_test.php` covers metadata, context discovery, export
  and all three deletion paths. `tests/format_aicourse_test.php` covers the section icon
  get/set round trip. `tests/behat/course_cards.feature` covers the General section regression,
  section card rendering, and edit controls appearing only in edit mode.

### Changed

- **Full accessibility pass (WCAG 2.1 AA).** The section icon picker trigger and the card action
  controls are real buttons with accessible names and keyboard activation; the chat send button
  has an accessible name; completion status is no longer conveyed by colour alone and is carried
  in the accessible name of each activity number; progress rings expose `role="progressbar"` with
  correct ARIA values matching the visible text; navigation chevrons and the return link name
  their destination; decorative SVGs are `aria-hidden` and out of the tab order; the section grid
  is a labelled region; focus is moved to a sensible neighbour after a card is deleted.
- **Complete visual redesign.** Reworked section and activity cards, hero banner, chat panel,
  icon picker and reports, with a consistent design-token set, a proper dark-mode variant, and
  contrast meeting 4.5:1 for text and 3:1 for UI components throughout.
- **Internationalisation pass.** Every user-visible string in `lib.php`, `ajax.php`, `report.php`,
  `admin_report.php` and `amd/src/courseformat.js` now comes from the language pack: the AI
  banner and remove-banner modals, all AJAX error messages, the CSV export headers and values,
  icon and category labels, hero button tooltips and the chat welcome messages. Sentences built
  by concatenation (section numbers, estimated time, activity counts, grade fractions,
  percentages, "return to section") are now single strings with placeholders, so word order is
  translatable. Byte-based `substr`/`strlen`/`strtoupper`/`ucfirst` on user text replaced with
  `core_text` equivalents. Dates now use `core_langconfig` strftime strings and `userdate()`
  rather than server-timezone `date()`. Names are rendered with `fullname()`.
- **Language file rebuilt.** Sorted alphabetically per the Moodle coding style, with the standard
  header, and with the false `privacy:metadata` string removed and replaced by a full set of
  `privacy:metadata:*` declarations.
- `db/caches.php` documentation corrected to describe what the cache actually does and how it is
  actually invalidated.
- `thirdpartylibs.xml` verified: the plugin bundles no third-party code, and the file now records
  that explicitly.

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
