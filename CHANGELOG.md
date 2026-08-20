# Changelog - AI Course Format Plugin

All notable changes to this plugin will be documented in this file.

## [2.1.24] - 2026-08-20

Full audit pass covering activity coverage, endpoint wiring and credit handling.

### Fixed

- **The AI Tutor could not see any label activity.** `get_course_content_for_ai()` filtered with
  `if (!$cm->uservisible || !$cm->url) continue;`. Labels have no view URL by design — they render
  inline on the course page — so every one was dropped before it reached the index. Labels are
  where teachers put headings, instructions and framing text, so this removed real teaching content
  from what the tutor can read. The switch below the guard already carried a `case 'label':` branch,
  which the guard made unreachable: indexing them was always the intent. `mod_subsection` and any
  future view-less module were in the same position. `$cm->url` was used nowhere else in the loop.

  Verified on a course containing 14 activities of 14 different types: 13 indexed before, **14 of
  14 after**.

- **"Out of credits" and "invalid API key" were indistinguishable from an outage.** Both
  integrations collapsed every non-200 response into a single generic message, writing the real
  status only to `debugging()` — which is off on production sites, so the one place the answer
  existed was the one place nobody looks.

  The service returns **401** for a bad key or mismatched site URL and **402** for insufficient
  credits (confirmed against the service's own code). These now map to specific, translated
  messages naming the actual cause, on both the tutor and the banner. Unknown statuses still fall
  through to the previous generic message.

## [2.1.23] - 2026-08-19

### Changed

- **The hero banner is roughly half its previous height on every screen size.** Measured before
  and after in a real browser:

  | Viewport | Before | After |
  |---|---|---|
  | 1440px | 232px | **128px** |
  | 1024px | 214px | **114px** |
  | 768px  | 206px | **109px** |
  | 430px  | 255px | **166px** |
  | 390px  | 254px | **165px** |
  | 320px  | 279px | **190px** |

  Four things were making it tall, and only the last was obvious:

  1. **Image mode stacked its content in a column** — title, summary, meta and progress each
     claiming a row — while the right two thirds of the banner sat empty. It is now a single row
     with the text on the left and the progress cluster at the end, which uses width that was
     already there. Below 700px it becomes a wrapping row, so the text still takes full lines but
     the progress cluster shares the last line with the icon pill rather than taking a row of its
     own.
  2. **`--acf-hero-image-min-h` was `clamp(9.5rem, 5.5rem + 13vw, 14.5rem)`** — 152px to 232px.
     This, not the course's own `herobannerheight`, was what actually set the height: at 1440px it
     resolved to 232px and overrode a 96px setting entirely. Now 88px to 128px.
  3. **The prev/next spacers were dead weight.** `.aicourse-hero-nav-spacer` exists to balance a
     centred chevron row when one chevron is missing. Gradient mode dropped them long ago; image
     mode kept them. On a course page, which has no chevrons at all, the two spacers *were* the
     entire progress cluster — 100px of empty boxes, and at 320px they were what pushed the icon
     pill onto a second row. Hidden in both modes now; section pages still show real chevrons.
  4. **The default `herobannerheight` is 96, down from 180.** Courses that never saved their
     format settings pick this up; a course with a stored value keeps it, since that was a
     deliberate choice.

  Verified at six viewports from 320px to 1440px, on both course and section pages: no horizontal
  overflow, no clipped content, and no collision between the title text and the progress cluster.

## [2.1.22] - 2026-08-19

### Fixed

- **The hero banner disappeared in "Switch role to... Student".** `permissions::is_grader()`
  decided on role archetypes read through `get_user_roles()`, carrying a comment asserting that
  this "respects `$SESSION->role_switch`". It does not. `get_user_roles()` reads the user's real
  entries in `{role_assignments}`; role switching is applied by `has_capability()` via
  `$USER->access['rsw']` and leaves role assignments untouched.

  So a teacher previewing as a student was still reported as a grader, while correctly losing
  `moodle/course:update`. `format.php` renders the hero only when `(!$isgrader || $canedit)`,
  which then evaluated false — and the banner vanished in exactly the view whose purpose is to
  show what a student sees. A student does see the hero.

  When a role switch is active the archetype branch is now skipped and the capability checks
  decide, because those do honour the switch. The memoisation key includes the switched role, so
  a value computed before a switch cannot be reused after one.

  Verified end to end on a real Moodle: switching an admin to Student now leaves the hero
  rendered and visible, with the body carrying neither `aicourse-is-grader` nor
  `aicourse-can-edit` — which is correct for a student.

### Added

- `tests/local/permissions_test.php` — two tests covering grader status for real roles and across
  a live `role_switch()`, including an assertion that the switch actually removed the editing
  capability, so the regression guard cannot silently pass for the wrong reason.

## [2.1.21] - 2026-08-19

### Changed

- **Edit mode: the section container chrome is removed.** In edit mode the format hands rendering
  back to core's standard renderer so teachers keep drag handles, action menus and bulk selection.
  Core's section then sat inside this sheet's card treatment, which drew three separate layers of
  chrome around content that was already visually grouped:

  - a 1px border with an 18px radius, a raised background and a drop shadow,
  - a 3px brand-coloured gradient rule along the top (a `::before` on `.course-section`),
  - dashed `<hr>` dividers between every activity.

  Each activity already carries its own rectangle, so the container was a box around boxes and the
  dividers described a grouping the rectangles made obvious. All three are gone; the activities
  now sit directly on the page.

  Nothing that carries meaning was touched: drag handles, action menus, availability and
  completion controls, bulk selection and the "Add an activity or resource" affordance are all
  unchanged. **View mode is entirely unaffected** — verified that this container does not exist
  outside edit mode, so the change cannot leak into the student view.

## [2.1.20] - 2026-08-19

### Fixed

- **The banner overlay darkened the whole image instead of just the text area.** 2.1.12 added a
  Light/Medium/Strong setting, but all three ramps ran top-to-bottom, which meant a near-uniform
  0.56-0.68 blanket across 92% of the banner. On a dark photograph even "Light" still crushed the
  image, because every pixel received the same treatment — reducing the numbers could not fix a
  problem that was structural.

  All hero text — title, summary, meta, progress ring — sits in the left half of the banner. The
  Light and Medium ramps now run **left to right**: heavier than before where the text actually
  is, and nearly clear on the right where the photograph should be visible.

  Contrast improves rather than degrades. The title band moves from 0.56-0.57 alpha to 0.62-0.70,
  measured against a worst-case pure-white image at **5.9:1 (Light) and 8.1:1 (Medium)**, against
  the previous 4.7-4.9:1 and an AA floor of 4.5:1. Meanwhile the right-hand third of the overlay
  drops to 0.06-0.20 alpha.

  Measured on a dark test banner: mean brightness of the right third rose from **15.7 to 29.7**,
  an 89% increase, while the text side was unchanged.

  Below 600px the title wraps across the full width, so there is no protected side and the
  vertical ramp is retained. Strong is unchanged, so anyone relying on the previous appearance
  keeps it exactly.

## [2.1.19] - 2026-08-19

Systematic contrast sweep. After the same fault appeared on two unrelated surfaces, every screen
was scanned generically rather than element by element: each text-bearing node inside plugin
markup had its effective background composited up the ancestor chain and its ratio measured.
Six screens, both colour modes.

### Fixed

- **"General" heading was light-on-white in dark mode (1.10:1).** This heading sits above the
  card, directly on the theme's own page background, which the plugin does not control. It was
  taking `--acf-text-primary`, so in dark mode it turned light while the page behind it stayed
  white. It now inherits the theme's page colour, which is correct in all four combinations of
  colour mode and theme.
- **The chat input's label was dark-on-dark in dark mode (1.11:1).** `.aicourse-ai-chatbox-input`
  sets a mode-aware background but left its text colour to the theme — the **third** surface with
  this exact fault, after the section card and the message list. Anchored.
- **The empty section-icon glyph was below AA in both modes** — 4.34:1 light, 2.34:1 dark, against
  a 4.5:1 floor. It is small rendered text rather than a decorative shape, so it has to clear the
  threshold. Moved from `--acf-text-tertiary` to `--acf-text-secondary`.

### Verified

- **0 contrast failures** across course, section, chat panel, course report, admin report and
  report index, in light and dark: 138 element/mode combinations measured.
- **axe-core: 0 violations** on all three report screens in both modes, adding to the course,
  section and chat panel results from 2.1.17 and 2.1.18.

## [2.1.18] - 2026-08-19

AI Tutor chat panel audited — previously the largest untested surface in the plugin.

### Fixed

- **The tutor's own answers were unreadable in dark mode.** `.aicourse-ai-chatbox-messages` takes
  its background from `--acf-surface-sunken`, which flips with colour mode, but the message text
  inherited the host theme's colour — Boost hands down `rgb(29, 33, 37)`. In dark mode that is
  near-black text on a `rgb(15, 23, 42)` panel: **measured 1.1:1 where AA requires 4.5:1**. Unlike
  the section card, where the same missing invariant was latent, this one was live: the greeting
  and every tutor reply were affected. Now ~15:1.

  This is the second surface found with the same fault — a surface that sets its background from a
  mode-aware token while letting its text colour fall through to the theme. Worth treating as a
  pattern to check for rather than two isolated bugs.

### Verified

The chat panel is otherwise a textbook modal dialog, confirmed by measurement at 1400x900 and
390x844:

- `role="dialog"`, `aria-modal="true"`, accessible name present, `aria-expanded` on the toggle
  kept in sync.
- Focus moves into the panel on open, and Tab cycles correctly through all eight controls —
  send, close, and the five quick prompts — without escaping the dialog.
- Escape closes the panel **and returns focus to the toggle**, which is the step most
  implementations forget.
- The panel fits the viewport at both sizes; on mobile it becomes a full-width sheet with no
  horizontal overflow.
- **axe-core: 0 violations** on the open panel at both viewports.

## [2.1.17] - 2026-08-19

Accessibility, performance and internationalisation reviews, each measured with real tooling.

### Fixed

- **Two landmarks shared an accessible name on section pages.** The hero renders as
  `<section aria-label="{section name}">`, which is a landmark, and the activity list rendered as
  `<div role="region" aria-label="{section name}">` — the same name. A screen reader user cycling
  landmarks heard the identical title twice with no way to tell the section banner from the
  activity list. The activity region is now labelled "{section name} activities". Flagged by
  axe-core as `landmark-unique`, and the only violation it found.

### Verified

- **axe-core, WCAG 2.0/2.1/2.2 A + AA plus best-practice, scoped to plugin markup: 0 violations**
  on the course and section pages, in both light and dark mode, after the fix above.
- **Performance is at parity with core.** Benchmarked against `format_topics` with identical
  content, five runs each, median: PHP 0.140s vs 0.155s (this plugin faster), 43 DB reads vs 39,
  5.6 MB RAM vs 5.8 MB, 595 files included vs 623, 1,599 DOM nodes vs 1,574. The format costs
  about four extra database reads over core for a considerably richer page.
- **Right-to-left support is complete.** The stylesheet uses **zero** physical direction
  properties — no `margin-left`, `padding-right`, `left:`, `text-align: left` or `border-left`
  anywhere — against 232 uses of their logical equivalents. Verified by rendering with `dir="rtl"`
  at 1400px and 390px: the layout mirrors correctly (the hero icon pill moves from right to left,
  cards right-align) with zero horizontal overflow and no element spilling outside the viewport.

## [2.1.16] - 2026-08-19

Dark mode audited by measuring rendered contrast ratios in a real browser across every colour
mode and both OS colour schemes, compositing alpha up the ancestor chain rather than reading a
single background value.

### Fixed

- **Section cards did not carry their text colour with their surface.** `.aicourse-card` takes its
  background from `--acf-surface-raised`, which flips with colour mode, but its text colour was
  inherited from the host theme — Boost hands down `rgb(29, 33, 37)`. In dark mode that is
  near-black text on a `#1e293b` card: **measured 1.1:1 against a 4.5:1 requirement**.

  Nothing was visibly broken, because every text element inside currently sets its own colour
  explicitly. That made it a latent fault rather than a live one: any text added to a card without
  an explicit colour would have been invisible in dark mode, and a section summary is the obvious
  candidate. The surface now establishes `color: var(--acf-text-primary)` so background and text
  flip together. Measured 1.1:1 → 13.4:1.

  `.aicourse-activity-card` already declared this, which is why activity cards were unaffected —
  the section card was the one surface missing the invariant.

### Verified

Contrast measured in both light and dark, for card title, card link, status badge, hero title,
hero meta, activity name and activity type. Every pair clears WCAG AA; the lowest is 4.8:1
(activity type in light mode) and most sit between 13:1 and 18:1.

## [2.1.15] - 2026-08-19

Mobile pass. Audited at 320, 360, 390, 430, 600, 768, 1024 and 1440px in a real rendering engine,
measuring layout rather than inspecting source.

### Fixed

- **The hero action icons overlapped the course title.** The icon pill is absolutely positioned
  over the banner's top-right while `.aicourse-hero-title` is `inline-size: 100%`, and nothing
  reserved space for it. Measured across seven viewports the two boxes overlapped by 198px
  horizontally at *every* width — desktop included. It went unnoticed because a short course name
  never visually reaches the pill; on a phone the title wraps to full width and runs straight
  underneath it, which is where it became obvious.

  From 600px up the title and summary now reserve the pill's measured footprint. Below 600px,
  where reserving ~200px of a 320px banner would leave nothing for the title, the pill stops being
  absolutely positioned and takes its own row beneath the hero content. Verified clear at all
  eight viewports by measuring the title's text range against the pill's box — the element box
  alone cannot show this, because padding sits inside it.
- **Status badges were 11px on phones.** The 2xs step is a deliberate dense chip size that works
  at desk distance, but 11px set uppercase with wide tracking is below comfortable reading on a
  handset. The token is lifted to 12px under 600px, which raises every 2xs use on mobile at once
  and leaves desktop density untouched.

### Verified, not changed

- **Zero horizontal overflow** at all six device widths on both the course and section pages. No
  plugin element exceeds the viewport at any size.
- **Touch targets** — the section card's title link reports as 302x16 to
  `getBoundingClientRect()`, but a stretched `::after` makes the whole card the hit area, and the
  sheet already documents that the measured box will mislead. No genuine sub-44px plugin target
  was found.

## [2.1.14] - 2026-08-19

First release whose test suite has actually been executed, and whose visual changes were verified
in a real rendering engine rather than reasoned about.

### Fixed

- **Six test failures, introduced by 2.1.4's own refactor.** Converting the external-function
  assertions to error-code checks wrapped each call in a closure, but PHP closures do not capture
  outer scope without an explicit `use` clause, so `$othersection`, `$quiz` and `$chatid` were
  undefined inside them. Six tests errored with "Undefined variable". Both phpcs standards and
  `php -l` passed this code cleanly; only running it found the fault. All 99 tests now pass with
  390 assertions.
- **Section cards showed a dashed placeholder icon to students.** A dashed outline means "drop
  something here" — an editing affordance. On a course whose teacher never chose section icons,
  which is the common case, every card carried a dashed box with a question mark and the page read
  as broken rather than merely unstyled. View mode now uses a solid, quiet placeholder; edit mode
  keeps the dashed invitation.
- **The section card grid left a ragged gap.** The grid used `repeat(auto-fill, ...)`, which keeps
  empty phantom tracks. A course with three sections in a wide container filled three tracks of
  four and left roughly 280px of dead space beside the last card, under a hero running the full
  width. `auto-fit` collapses the unused track. Measured before and after in a headless browser:
  three cards went from ending at x=1222 to ending at x=1529, exactly matching the hero's right
  edge, with card width growing from 283px to 386px.

## [2.1.13] - 2026-08-19

### Fixed

- **Hero banner layout on courses that have a banner image.** With an image, the progress cluster
  was laid out as a column: the ring stacked above a linear bar capped at 22rem, the pair packed
  to the left. On a wide banner that left the ring floating alone at the left edge, an orphaned
  bar beneath it, and roughly 60% of the banner empty.

  The cluster is now a single row — ring, then bar taking the width the row leaves it (to a
  34rem ceiling so it cannot stretch absurdly on an ultrawide screen), vertically centred against
  each other. It reads as one control rather than two strays.

  Gradient mode is unchanged: it hides the linear bar outright, because there the ring alone is
  the anchor and the bar merely duplicates it. That asymmetry was deliberate but image mode had
  simply been left as it was rather than laid out.

## [2.1.12] - 2026-08-19

### Added

- **Banner overlay strength setting.** The dark gradient laid over hero banner images is now
  selectable: *Light*, *Medium* (the new default) or *Strong* (the previous appearance).

  The overlay keeps white title text readable over any image, including a near-white one, but
  the original ramp was calibrated well past that requirement — its foot reached **16.2:1**
  against white text where WCAG AA asks for **4.5:1**. On a dark photograph that is far more
  darkening than the image can carry, and the banner reads as almost black.

  The minimum alpha clearing AA on a worst-case pure-white image is 0.539. Every level keeps the
  band where the title and summary actually sit at 0.56 alpha or above (4.9:1 measured on that
  same worst case) and relaxes only the top and bottom of the gradient, where no text sits. All
  three levels remain accessible; they differ in how much of the photograph survives.

### Changed

- **The default is now Medium, so existing sites will see banners lighten on upgrade.** Choose
  *Strong* in the format settings to restore the previous appearance exactly.

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
