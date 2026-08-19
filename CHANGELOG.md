# Changelog - AI Course Format Plugin

All notable changes to this plugin will be documented in this file.

## [2.1.11] - 2026-08-19

### Fixed

- **The banner dialog told customers their image was "Powered by Google Imagen 4 Ultra". It was
  not.** Google has removed the Imagen family from the Gemini API entirely — a live query of the
  list-models endpoint returns no `imagen-*` model at all — so the remote service's Imagen call
  has been returning 404 on every request and silently falling back to OpenAI `gpt-image-1`.
  Every banner produced in at least the past week came from OpenAI while the dialog credited
  Google.

  The subtitle is now the provider-neutral "AI image generation". Naming a specific model in a
  shipped language string means the claim goes stale the moment the service changes provider,
  which is precisely what happened here; the plugin has no way to know which model served a
  given request, so it should not assert one.

## [2.1.10] - 2026-08-19

### Fixed

- **AI banner generation always failed, and charged the account anyway.** The client gave up
  after 90 seconds; the remote service currently takes about two minutes, because its primary
  image model has been retired upstream and every request now fails against it before falling
  back to a slower second provider. The server finished successfully, deducted 5 credits and
  returned an image to a connection this plugin had already closed — so the user saw only
  "Generation failed" and had paid for it. Server-side logs show no 4xx and no errors on the
  route at all, which is why the failure was invisible from the Moodle side.

  The cURL timeout is now 180 seconds with a separate 30-second connect timeout, so a service
  that is genuinely down still fails fast. `core_php_time_limit::raise(300)` accompanies it:
  without that the request is killed by PHP's script limit before cURL ever returns.
- The AI Tutor chat call gained a 15-second connect timeout and a raised script limit for the
  same reason — its 60-second cURL timeout could previously be pre-empted by PHP.
- The progress dialog claimed banner generation "takes 15-40 seconds". It now says one to two
  minutes and asks the user to leave the window open.

## [2.1.9] - 2026-08-19

Verified against a real Moodle 4.5.13 / PostgreSQL 16 install rather than by inspection.

### Added

- **Colour mode site setting** (*Site administration ▸ Plugins ▸ Course formats ▸ AI Course
  Format*). 2.1.8 stopped the format painting itself dark on a light page, but it did so by
  ignoring the device preference entirely, which left no way to get dark styling at all on a
  theme that declares no mode. The site now chooses:

  - **Follow the theme** (default) — uses the mode the theme declares on the page. Safe on every
    theme; a theme that declares nothing keeps the format light.
  - **Always light** / **Always dark** — pins the format for themes with a fixed appearance that
    they do not declare.
  - **Follow the device setting** — the pre-2.1.8 behaviour, now opt-in, for sites whose theme
    genuinely follows the operating system.

  The choice is published as a body class from both `lib.php` and the footer hook, so it reaches
  course, activity, section and report pages alike. `Always light` vetoes every other trigger,
  including a theme-declared dark mode, and the print stylesheet neutralises the two new routes
  so printing stays black-on-white.

### Fixed

- The 2.1.5 banner item id migration left the old `.` directory record behind in `{files}` under
  the previous item id, where it lingered permanently. Each stale item id is now cleared
  wholesale, and directory records orphaned by an earlier partial run are swept up too.
- The same migration used `get_recordset_select()` with `DISTINCT` in its fields argument, which
  raises "Error reading from database" on PostgreSQL because the `DISTINCT` collides with the
  generated `ORDER BY`. It would have thrown part way through the upgrade on a real site.
  Rewritten as `get_recordset_sql()`. Caught by running the upgrade, not by review — phpcs and
  `php -l` both passed it.

## [2.1.8] - 2026-08-19

### Fixed

- **Course index titles were invisible for users whose operating system is in dark mode.** The
  plugin's dark design tokens were applied under `@media (prefers-color-scheme: dark)` guarded
  only by `html:not([data-bs-theme="light"])`. An element carrying no `data-bs-theme` attribute
  at all satisfies that guard, so on any theme that never sets the attribute — theme_academi sets
  it nowhere — the dark tokens were applied to every dark-mode user while the theme itself stayed
  light. `--acf-text-primary` became near-white on a white drawer, so the course index titles,
  and everything else coloured from it, disappeared.

  The symptom varied per person rather than per course, which made it look like a permissions
  fault: one user could read the drawer and another, in the same course with the same role, could
  not. The guard now requires the attribute to be present (`html[data-bs-theme]:not(...)`), so a
  mode-aware theme such as Boost 4.4+ still gets automatic dark mode while a theme that is
  light-only by omission is left alone. `.aicourse-force-light` remains as an explicit opt-out.
  Both the main dark block and the `prefers-contrast: more` block are corrected.
- The print stylesheet's dark-mode reset gained the same `html[data-bs-theme]` prefix. It works by
  mirroring the dark blocks' selector shapes and winning on source order; tightening the guard
  above raised those blocks from (0,4,2) to (0,5,2), which would have left the print reset losing
  and produced white-on-white when printing from a dark page.

## [2.1.7] - 2026-08-19

### Fixed

- **The selected item in the course index was unreadable on theme_academi (and themes like it).**
  2.1.6 tried to fix this by raising the plugin's specificity to (0,4,1). That is not enough:
  Academi sets the selected item's background from

      .drawer .drawercontent .courseindex .courseindex-section
      .courseindex-sectioncontent .courseindex-item.pageitem

  which is (0,7,0). The theme therefore won the background while the plugin still won the text
  colour, painting the theme's dark primary underneath this plugin's dark brand navy. Climbing
  the specificity ladder is an arms race against every theme, so the background and the text
  colour are now pinned together with `!important` — whatever a theme does, it can no longer
  take one declaration and leave the other.

### Changed

- Corrected the rationale recorded against the card-title sizing rules. 2.1.6 attributed the
  oversized titles to themes styling headings through an id selector; that was not verified and
  is not what theme_academi does (it sets a plain `h3 { font-size: 26px }`, which the plugin's
  (0,3,1) selector already outranks). The forcing is retained on its own merit — it makes the
  course's "Card title size" setting authoritative regardless of theme — but it is no longer
  described as a fix for a fault that was not diagnosed.

## [2.1.6] - 2026-08-19

### Fixed

- **Section and activity card titles ignored the "Card title size" course setting on some
  themes.** The rules that size these titles previously carried `!important`; it had been removed
  on the reasoning that a heading here competes only with Boost's element-level rule at (0,0,1),
  which the plugin's (0,3,1) selector beats comfortably. That holds for stock Boost, but not for
  a theme that styles headings through an id — `#page-content h3` is (1,0,1) and outranks it —
  or with `!important` of its own. On those sites the titles rendered at the theme's heading
  size and the course setting silently did nothing. `font-size` and `line-height` are forced
  again, and only those two properties; everything else still yields to the theme.
- **Banner generation failures were undiagnosable.** `format_aicourse_generate_banner_image` can
  fail for six distinct reasons, each with its own translated message, but the dialog collapsed
  all of them into "Generation failed. Please try again." `errorMessage()` only read the
  rejection's `message` property, and a rejection carrying just an `errorcode` fell straight
  through to the generic fallback. It now falls back through `errorcode`, `debuginfo` and
  `exception` before giving up, so the cause is identifiable from the dialog itself. Note this
  improves reporting of the failure; it does not change whether the remote service succeeds.

## [2.1.5] - 2026-08-19

Second pre-submission pass: backup/restore support and standards clean-up.

### Fixed

- **The course banner image was lost on backup, restore, duplication and import.** The plugin had
  no `backup/moodle2/` classes at all. Core backs up `{course_format_options}` automatically, so
  every *setting* survived a backup while the image those settings refer to did not. Added
  `backup_format_aicourse_plugin` and `restore_format_aicourse_plugin`.
- **Banner files moved from `itemid = courseid` to `itemid = 0`.** This is what made the restore
  correct rather than merely present: `restore_dbops::send_files_to_pool()` copies
  `itemid AS newitemid` verbatim for a file area restored without an item id mapping, so a course
  restored into a *new* course would have kept its banner filed under the *old* course id, where
  nothing looks for it. There is only ever one banner per course and it already lives in that
  course's context, so the item id never carried information. `db/upgrade.php` migrates existing
  files; the pluginfile callback continues to serve browser-cached URLs that still carry the old
  item id.

### Changed

- Class constants in the five report classes now declare explicit visibility.
- `/course/section.php` detection is now a single helper on `format_aicourse\local\callbacks`
  using the global `$SCRIPT` rather than three separate reads of `$_SERVER['SCRIPT_NAME']`.
- Removed 11 orphaned language strings.
- `styles.css` now passes Moodle's stylelint configuration with zero errors and zero warnings.
  The 28 `!important` declarations are retained — a course format overriding Boost's layout needs
  them — and each now carries an explicit suppression next to its existing numbered rationale, so
  a *new* accidental `!important` is still reported. Ten over-length selectors were wrapped; one
  cannot be, because a newline before a `>` combinator breaks `selector-combinator-space-before`,
  and is suppressed with that reason recorded.
- README documents that the Moodle Mobile app renders these courses with its default layout.

## [2.1.4] - 2026-08-18

Pre-submission hardening pass ahead of the Moodle Plugins Directory listing. No new features.

### Fixed

- **Upgrade could fail part way through on sites missing a plugin table.** The 2026081500 step
  recreates a missing table from `db/install.xml` and then added the `correctedby` foreign key.
  `install.xml` already declares that key, so on any site that took the recreate path the second
  operation was a duplicate index and raised a DDL exception mid-upgrade. The key is now only
  added when the table already existed.
- **"Enable AI Tutor" is now a real kill switch.** The setting was consulted only by the output
  classes that draw the chat panel, so unticking it hid the bubble while leaving
  `format_aicourse_ai_chat` and `format_aicourse_get_activity_context` fully callable through
  `core/ajax` — anyone holding `format/aicourse:useaitutor` could still spend purchased API
  credits. Both functions now enforce it.
- **Courses containing non-ASCII text could not use the AI Tutor.** Prompt context, tutor memory
  and every file-extraction path truncated with byte-wise `substr()`, which can cut a multibyte
  character in half. The resulting invalid UTF-8 made `json_encode()` return `false`, so the
  plugin posted an empty request body and the tutor failed with an opaque error on any course
  using accented characters, CJK or emoji. All truncation now uses `core_text`, and a failed
  encode raises a clear error instead of being sent.
- **Temporary files from Word document extraction could leak.** `.docx` extraction wrote to
  `sys_get_temp_dir()` and removed the file only on the success path, so any exception left it
  behind permanently. It now uses `make_request_directory()`, which Moodle cleans up
  automatically, and closes the archive handle in a `finally` block.
- **A single large course file could exhaust memory.** Text extraction called
  `stored_file::get_content()` with no size check, loading the whole file into a PHP string. A
  10 MB ceiling (`contentindex::MAX_EXTRACT_BYTES`) now applies to every extraction path; larger
  files contribute a filename placeholder instead.
- **Refusal counts read zero on non-English sites.** The AI Tutor report's academic-integrity
  counter was derived by matching English phrases in the answer text. It now uses the `refused`
  flag from the service response when present, falling back to phrase matching only when it is
  absent.
- Section card overflow links (`+N` activities, `+N` progress dots) are now escaped consistently
  with their sibling links.
- Corrected the "Site ID" setting label and description: the field is validated as a URL
  (`PARAM_URL`) and is sent as the site URL, so a non-URL value was silently discarded on save
  and the tutor then reported itself unconfigured for no visible reason.

- **The hero banner was hidden from every teacher, manager and admin.** `styles.css` hid
  `.aicourse-hero-sticky-wrap` for `.aicourse-is-grader`, a body class that is added for every
  user `permissions::is_grader()` matches — which is all teacher archetypes plus anyone holding
  `moodle/grade:viewall`, `moodle/course:manageactivities` or `moodle/course:viewhiddenactivities`.
  `format.php` deliberately renders the hero for anyone who can edit, so they can reach the
  "Generate banner" button (FIX-ACF-EDITOR-HERO, v1.7.68), so the banner was built on the server
  and then hidden again by CSS, in and out of edit mode. A companion `aicourse-can-edit` body
  class is now published alongside `aicourse-is-grader`, and the hide rule is scoped with
  `:not(.aicourse-can-edit)` so it is the exact complement of the PHP condition. The
  `#page-header h1` restore rule is scoped the same way, so an editing teacher outside edit mode
  does not see the course title twice.
- **The selected item in the course index could be unreadable.** The active item's background
  was declared at a lower specificity than its text colour, so a theme whose own
  `.courseindex .courseindex-item.pageitem` rule outranked the first but not the second painted
  a dark highlight beneath text this plugin had just coloured dark. Both are now carried at the
  higher specificity so a theme cannot win one and lose the other.

### Changed

- **`format/aicourse:correctresponses` is now enforced.** It was declared in `db/access.php`
  with `RISK_XSS | RISK_PERSONAL` but never checked anywhere; writing a correction was gated on
  `format/aicourse:viewreport` alone. `format_aicourse_correct_chat` now requires both. The two
  capabilities have identical default archetypes, so no existing site loses access — but a site
  can now grant read-only access to the report by revoking this one capability, and the report's
  correction controls disappear for those users rather than failing when used.
- **Removed the `db_repair` and `db_diagnostic` external functions.** They altered the database
  schema at runtime, which Moodle permits only through `db/install.xml` and `db/upgrade.php`,
  and the table `db_repair` built omitted every foreign key and index declared in
  `install.xml` — so a site that ran it was left with permanent schema drift. The supported
  route for a half-applied upgrade is `admin/cli/upgrade.php`. The corresponding `dbdiag` and
  `dbrepair` actions have been removed from the deprecated `ajax.php` shim.
- The AI Tutor rate limiter now uses a declared `ajaxratelimit` cache definition in
  `db/caches.php` instead of an ad-hoc runtime cache, so an administrator can point it at a
  shared store. On a cluster the previous arrangement enforced the limit per web node rather
  than site wide.
- Screenshots are no longer bundled in the package. They were 2.3 MB of a 4.3 MB download and
  are published on the plugin's directory page instead.
- `styles.css` reformatted to the Moodle stylelint standard.
- External function tests now assert on the exception's error code rather than on the rendered
  English message, so they no longer break when a string is reworded or when run under a
  different language pack.

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
