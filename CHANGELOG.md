# Changelog - AI Course Format Plugin

All notable changes to this plugin will be documented in this file.

## [2.1.200] - 2026-08-25

### Fixed

- **Course names containing `&` displayed as `&amp;`.** Three places took a name PHP had already
  escaped for HTML and re-inserted it as plain characters: the course index sidebar, the Generate
  AI banner dialog, and — invisibly — the course name sent to the image service, which was being
  asked to illustrate a course with `&amp;` in its title. A new helper returns the filtered name
  unescaped for the consumers that do not parse markup.
- **A long course name was cut off instead of wrapping to two lines.** The sidebar name has been
  clamped to two lines since it was written, but a later accessibility rule set `display: inline-flex`
  on the same element, which silently disables `-webkit-line-clamp`. The touch-target height is now
  taken with `min-block-size`, which needs no flex, so both hold at once.
- **The hero progress ring was invisible on banners with a photograph.** At 0% the coloured arc has
  no length and the track carried an inline near-black stroke, so both circles were present and
  neither could be seen. The track is now a light stroke, and the ring container has a size floor.
- **The inverted focus halo on hero banners never rendered.** One branch of the shared focus rule
  was more specific than the rest, and `:is()` applies its most specific argument's score to every
  branch — enough to beat the "controls on imagery" rule. A keyboard user tabbing across a
  photographic banner got the ring meant for a white page. That branch now sits on its own line.
- **The settings-page textarea rendered 44px tall instead of 128px.** Its own rule lost on
  specificity to the shared control block. The touch-target floor now excludes `textarea`.
- **Direct reads of the request environment removed.** Two places read `$_SERVER` instead of the
  normalised script path Moodle publishes; both now use `$SCRIPT`.
- **Course index rows were cramped.** Section bands and activity rows are both 44px, which also
  clears the WCAG 2.2 target size. They needed different mechanisms: the band has an explicit
  height, the row has none.

### Added

- **Your own detail in the AI banner dialog.** An optional field whose text is added to the image
  prompt alongside the course name. Capped at 300 characters server-side.

### Changed

- **The site logo band now ships hidden from students** and visible to teachers, and **the site
  footer now ships hidden**. Both are site defaults, so they seed new courses only — existing
  courses are unchanged until the matching "apply to all courses" setting is used.
- **Moodle 5.2 declared supported** (`$plugin->supported` upper bound raised to 502).
- **The footer and logo-band rules now match themes that do not use core's own markup**, including
  `theme_academi`, without touching any toolbar that carries the user menu or drawer toggles.

### Internal

- **Every unreachable CSS declaration removed** — 115 of them, plus 18 rule blocks left empty.
  Proven equivalent to the previous release both analytically, by comparing the winning declaration
  of all 4,674 groups, and empirically, by diffing every computed property of every element across
  18 fixtures at three widths.
- **Every invisible specificity trap removed** — 13 pairs where the rule a reader would expect to
  win does not, because `:is()` borrowed specificity from a branch that was not the one matching.
  Two of them were live bugs, listed above.

## [2.1.190] - 2026-08-23

### Important — site-wide fix

- **The settings interface was loading on every administration settings page**, including Moodle
  core's and unrelated plugins'. Moodle includes every plugin's `settings.php` on every admin page
  to build the admin tree, so a `js_call_amd()` at the top level of that file loads everywhere.
  Neither `$hassiteconfig` nor `$ADMIN->fulltree` scopes it. Now restricted to this plugin's own
  section, with an independent body-id check in `settingsui.js`.
  Reported by the Wombat LMS platform team for College Australia (RTO 31222).
- **An SVG could render at the browser's default replaced-element size** where the plugin's
  stylesheet was not present. Both SVG generators now carry explicit dimensions.

### Added

- Section card activity rows carry the module icon, estimated time and completion tick, and share
  the course index's completion tooltip — one implementation, one payload.
- Card progress: ring, bar and percentage count up from zero, deferred until the card is on screen.
- The activity banner's completion ring draws itself.
- Settings page rebuilt: ten colour-coded areas, search, numbered settings, per-setting wireframes
  drawn from real UI furniture, three-course motif for `force…` settings.
- Settings language rewritten in plain English with inline explanations.
- Site navigation shown to teachers by default.

### Fixed

- Activity banner: not full width on drawer close, nav icons behind the open drawer, horizontal
  scrollbar. Root cause: the banner was never lifted to `#page` on activity pages, because
  `heroinject` places it after `heroatop` has run and only `attach()` listened for the placement.
- Hero heading sizes unified across course, section and activity into `--acf-hero-heading`; the
  course-name eyebrow on an activity banner raised from 11px via `--acf-hero-eyebrow`.
- Hero controls at each end of a banner now match on box, glyph **and** background surface.
- Course index: close button clipping below a 327px panel; reopen button behind the banner;
  `--acf-text-tertiary` failing WCAG AA on the sidebar surface.
- Section cards: explicit 3/2/1 column grid driven by container width, not by section count.
- Section delete from a card: the only card action without its own `data-courseid`, so it fell
  through to a document-wide lookup and failed silently when that resolved to nothing.
- Progress bar drew an ellipse — `--acf-radius-full` is `50%`, and a percentage radius resolves
  against the box's own dimensions. Use `--acf-radius-pill`.
- Progress tracks were invisible (`--acf-surface-sunken` on a white card). Now `--acf-track`.

### Notes

- Nine hard bugs in this project have now been the same shape: two rules setting the same property
  on the same selector, with the edit going on the losing one. **150 such pairs remain.** Run the
  detector on a selector before editing it.

## [2.1.166] - 2026-08-22

Sixteen releases in one session. The entries below are grouped by what was wrong rather than by
release, because several of these were fixed more than once before the real cause was found — and
the wrong attempts are worth recording.

### The recurring cause

**Five of the hard bugs were the same shape: two rules setting the same property on the same
selector, and the fix going on the losing one.** A static check now exists for it and reports
**156 such pairs** still in the sheet. Run it before changing any layout value:

- the card slab (2.1.151) — `position` set twice
- the card header z-index — `auto` at one line, `var(--acf-z-content)` ten lines later
- the hero title-size tiers — inert for four releases behind `--acf-hero-title-compact` at (0,4,1)
- the ring percentage at 10px — three rules, the floor added to the wrong two
- the sidebar logo — height capped in three places, so an explicit request was discarded

### Fixed — section cards

- **A grey slab across the top of every card.** `ACF-FIX-2.1.145` set `position: static` on
  `.aicourse-card-time`, which re-parented its `::before` pill to `.aicourse-card-header` and
  stretched it across the card. **z-index is the lever for click-through, not position.**
- **The icon corner and footer were dead.** Hit-testing nine points on a card found two that
  reached no link. The header and footer sat above the link overlay. **0 dead zones now.**
- **Not-started cards took `--acf-neutral-300`** — a pale blue-grey — instead of the course accent.

### Fixed — the banners

- **The activity title overflowed the screen on a phone**: 482px inside 390px. In image mode the
  content is a column, and a column's cross axis is the inline one, so `align-items: flex-start`
  told the title block to shrink-wrap instead of fill.
- **A gap above the activity banner, and sticky scroll that jammed at the top.** `heroatop.js`
  bailed on a *race*, not a condition — `heroinject` places the banner after `heroatop` runs — so
  `--acf-hero-sticky-top` was never published and everything `lift()` gates was lost. `heroinject`
  now announces placement.
- **The banner would not span the page with the index closed.** Fixed by anchoring to the drawer's
  edge and the window rather than to `#page`, which is centred on some themes and wider than the
  viewport on others.
- **The right-hand cluster had no glass panel** while the left did, on all three variants. Now
  identical, read from the same tokens, with the same `acf-shine` sweep.
- Progress and completion rings were 56px and 54px. One token now.

### Fixed — the course index sidebar

- **Merged into two rows.** Core's close button and options menu move into the brand row; the
  emptied strip collapses. `--acf-topblock` 164px → 120px, and the banner follows.
- **The close button was invisible for three releases.** It existed and measured 32px — the brand
  row overflowed by 39px and pushed it outside the drawer, which clips. Not hidden: **evicted.**
- **The logo rendered at 0px wide.** Its SVG has a viewBox and no width/height, so it has no
  intrinsic size and `inline-size: auto` computed to zero. An explicit width fixes it.
- **`.drawer-left` matched two stacked drawers.** Eleven selectors were also reaching the site's
  primary navigation drawer — including a `display: none` — so hiding the course index could take
  the site's own nav with it. All scoped to the id.
- **The 8px gap between collapsed section bands**, open for four attempts: `margin-block-start: 8px`
  on the heading, collapsing through its parent. Explains every earlier measurement. Now 2px.
- Section bands are a quarter shorter; the height came from the chevron, not the padding.
- Module icons rendered brown because an `<img>` paints its own bitmap over the masked tint.

### Fixed — settings page

Rebuilt twice: first as a card grid, then, on the reasonable objection that it was overwhelming, as
a **single-column accordion** — ten rows, one open at a time, each with a wireframe of the part of
the course page it governs.

- **Bootstrap's `!important` utilities were beating every rule.** `text-sm-right` right-aligned every
  label; `d-block` stretched a 200px code-name pill into a 962px grey bar. Invisible in testing
  because the local harness had no Bootstrap in it. It does now.
- Type scale raised one step throughout: 13px descriptions were fine print on a page for reading.
- `defaultaccentcolour` was registered **twice** — the colour picker added in 2.1.23 had been dead
  code behind a plain text box ever since.
- `defaultcoursenavplace` was read by `lib.php` and never registered.

### Fixed — `showcourseindex`

`lib.php` never read bit 2, so activity pages were judged on the course-home bit, while the footer
hook read bit 2 correctly and pushed a contradicting class through a module that can only ever ADD
one. `= 4` hid the index on exactly the pages it was meant to show it on; `= 1` showed it and then
took it away mid-load. Values 3 and 7 agreed by coincidence, which is why it survived — 7 is the
default.

### Added

- **Completion tooltip** on the course index ticks: per-condition state, the grade achieved and the
  date completed. Grades cost two queries for the whole course, not one call per activity. Hidden
  grades are dropped for anyone without `moodle/grade:viewhidden`. `COMPLETION_COMPLETE_FAIL` shows
  as failed, not met.
- **Animated count-up**: ring and number rise together over 1.8s on every banner and in the sidebar.
  The arc previously animated from its final value to its final value.
- **Mobile pass at 320/360/390/414px.** Reflow (WCAG 1.4.10) at 320px, 200% text zoom, touch targets,
  tap-to-open for the tooltip — there is no hover on a phone, so the feature did not exist there.

### Removed

- `classes/local/settingsmeta.php`. It parsed one of the three ways this plugin declares a setting
  and was never trusted enough to be used without a hand-kept list beside it.

## [2.1.150] - 2026-08-20

### Fixed

- **The three hero variants had drifted apart.** Measured across course, section and activity pages
  before changing anything:

  | | Icon group | Completion ring |
  |---|---|---|
  | Course / section | `rgba(2,6,23,0.62)` + `blur(10px)` + white border | not present |
  | Activity | identical | **transparent, no blur, no border, no radius** |

  So the green tick sat directly on the banner image with nothing behind it, while every other
  control on the same banner had a glass panel. That was the inconsistency -- not the tick itself.

  It now carries the same glass, read from the same values rather than approximated: two panels on
  one banner that are *nearly* the same read worse than two that differ clearly. Hover matches the
  icon buttons -- the panel lifts slightly and nothing changes hue.

- **The activity title was smaller than the course title.** The course hero adds
  `aicourse-title-{{titlesize}}`, which is what carries the font size; the activity hero never gets
  that class, so it fell back to the base and rendered smaller than the course title beside it in
  the same course.

  The base is set in CSS rather than by adding a class to the template, so it stays in one place and
  cannot fall out of step with the size options a course can choose.

  | Page | Title | Weight |
  |---|---|---|
  | Course | 26px | 700 |
  | Section | 26px | 700 |
  | Activity | 22px → **26px** | 700 |

  All three now match.

## [2.1.149] - 2026-08-20

### Fixed

- **The section band changed colour when expanded or current.** Hover was held in 2.1.132, but a
  heading has other states that carry their own colour: `active` and `pageitem` while the section is
  the one being viewed, `focus` from the keyboard, and the expanded state once its chevron turns
  down. Core styles `pageitem` with the theme's primary and theme_academi restyles hover -- so an
  expanded or current heading still shifted under the pointer even though the resting band was
  right.

  Every state is now listed in one rule rather than four that can disagree with each other. This is
  the third time a heading state has been fixed in isolation and left the next one to surface;
  gathering them stops that.

  Verified across all three, reading background, text and chevron each time:

  | State | Background | Text | Chevron |
  |---|---|---|---|
  | At rest | `rgb(15, 108, 191)` | white | white |
  | Expanded | same | white | white |
  | Expanded + hovered | same | white | white |

  Nothing changes in any of them.

## [2.1.148] - 2026-08-20

### Fixed

- **The course index reverted to Moodle's plain list on grade pages.** The player module was
  restricted to course and activity pages, so a learner opening their grades lost the activity
  icons, durations and completion ticks -- and got them back on returning to the course. The panel
  should not change shape depending on which page of a course someone is on.

  Grade pages are inside the course and render the same course index, so they now qualify.

  | Page | Sidebar | Rows | Icons |
  |---|---|---|---|
  | Course | yes | 15 | 15 |
  | Grade report | **yes** | **15** | **15** |
  | Course settings form | no | 0 | 0 |

  **The third row is the one that mattered to check.** This guard exists because the hook fires
  wherever `$COURSE` is set, which once loaded five modules onto the course settings form and made
  its Course format section open only intermittently. `grade-report-` was added specifically rather
  than anything broader, and the settings form confirmed still excluded.

### Not done: the banner on grade pages

  The banner is rendered by the format's own renderer on course pages and by `activityhero` on
  activity pages, using the current course module. A grade page has neither a section nor a module,
  so it needs a third variant -- plus a decision about what its navigation and progress ring should
  show with no activity context. That is a feature rather than a guard change and is left for a
  session with room to build and verify it.

## [2.1.147] - 2026-08-20

### Fixed

- **The settings page filter counts were wrong.** Two separate faults, both found by listing every
  setting against the category it was being given rather than trusting the totals.

  **Timing settings were scattered.** `hidetimesectioncards` matched `/card/` and landed in Cards;
  `hidetimeindex` matched the index patterns and landed in Course index. First match wins, and
  Timing was last -- so a tab that holds eleven settings was showing three.

  | | Before | After |
  |---|---|---|
  | Timing | 3 | **11** |
  | Cards | 10 | 6 |
  | Course index | 10 | 6 |

  Timing now comes first, and the order of the whole list is the design rather than the order I
  happened to write them in. Colour stays ahead of the areas it applies to, so every colour is in
  one place -- someone theming a site wants them together rather than spread across four tabs.

  **Section headings were being counted as settings.** A heading renders as a `.form-item` with no
  setting name, and the matcher fell back to searching its whole text -- so a heading landed in
  whichever category its prose happened to mention, and the totals counted things a reader cannot
  change. Headings are now excluded from the counts and from every category, while still showing
  under "All settings".

  Matching on the name alone also stops a setting being filed by a word in its description:
  "the accent colour is used for card headings" should not put an accent setting under Cards.

  Verified against the full list including the settings built in loops, which never appear as
  literal names: **68 settings, every one categorised, nothing left in Other.**

## [2.1.146] - 2026-08-20

### Changed

- **Room under the progress ring**, so it no longer sits hard against the first section band.

  Padding alone could not do it: the header's height is fixed and its contents are centred, so the
  padding had nowhere to go -- it measured 5px against the 16px asked for. The block itself had to
  grow.

  Raising the shared height token from 152px to 164px does both sides at once. The banner reads the
  same number, so the two edges stayed aligned without a second change -- which is the whole reason
  that token exists rather than the two heights being set separately.

  | | |
  |---|---|
  | Gap under the ring | 5px → **11px** |
  | Sidebar top block | 164px |
  | Banner | **164px** — matched, difference 0 |

## [2.1.145] - 2026-08-20

### Changed

- **More of the section card is clickable.** Hit-testing six points across a card found only the
  title reaching the card's link; everything else either hit nothing or was captured by an element
  above the overlay.

  The overlay is raised above the card's contents, and the elements that lead somewhere *else* are
  lifted back above it -- an activity row must still open that activity, not the section.

  | Point | Reaches |
  |---|---|
  | Title | the card link |
  | Activity row | that activity — correct, it is its own link |
  | "N activities" | the section — correct |
  | Time pill | the section |
  | Empty space beside a row | that row's activity |
  | **Card icon** | **still nothing** |

  Most of a card's area is activity rows, and those are full-width links in their own right, so
  clicking almost anywhere now goes somewhere sensible.

### Known: the icon corner is still dead

  The card header carries `z-index: var(--acf-z-content)`, which outranks the overlay -- that is why
  the icon reaches nothing and why the title only worked because it *is* the link. Setting the
  header's `z-index` to `auto` did not change the result, so something else in that corner is still
  above the overlay and I did not isolate it.

  Recorded rather than guessed at. The next step is to hit-test the icon corner specifically and
  walk what `elementFromPoint` returns there, rather than adjusting z-index values and re-measuring
  the whole card.

## [2.1.144] - 2026-08-20

### Added

- **Sticky banner**, on by default, with a per-course setting, a site default and a site-wide
  override. The banner follows the page as it scrolls, so the progress ring and the next/previous
  controls stay reachable however far down a learner is.

  Verified at a 700px viewport, scrolling 600px:

  | Setting | Position | Banner top before | after |
  |---|---|---|---|
  | Sticky on | `sticky` | 61 | **61** — pinned |
  | Sticky off | `relative` | 61 | −421 — scrolled away |

  **The offset is measured, not assumed.** The banner pins to whatever the visible header's bottom
  edge actually is, published from the same measurement the banner already uses for its top gap. A
  fixed number would leave a gap on one theme and slide the banner under the header on another --
  the same assumption that made the top-gap fix wrong on theme_academi in 2.1.107.

  Three cases where it does not apply, each for a reason:

  - **While editing**, because a teacher dragging an activity needs the page to behave normally.
  - **Under `prefers-reduced-motion`**, where an element moving against the scroll is exactly what
    that setting exists to stop.
  - **Below 992px**, where the banner is a large share of a small screen and pinning it would leave
    very little room for the content it introduces.

  The help text says when to switch it off rather than only what it does: on a short course the
  banner never leaves the screen anyway, and on a small laptop it costs height for the whole visit.

### Note

  This release skips 2.1.142 and 2.1.143. Both were attempts at merging the sidebar's top two rows;
  each broke the panel -- the first pulled core's drawer out of its layout, the second lost the logo
  and navigation icons -- and both were reverted. The version numbers are left unused rather than
  reassigned so the changelog matches what was actually built.

## [2.1.141] - 2026-08-20

### Attempted and reverted: 2px between section bands

  **The gap is still 8px.** The margin is set to the 2px asked for, but the margin is not what
  decides the distance.

  Four measurements now agree:

  | Attempt | Result |
  |---|---|
  | Section margin 5px | 8px gap |
  | Section margin 2px | 8px gap |
  | Flex column, 2px gap, no margins | **10px gap** -- worse |
  | Trace of the space between two bands | nothing in it |

  The trace is the informative one: no element occupies the gap, and there is no padding, border or
  margin anywhere along the chain from one band to the next. I thought the missing ~6px was
  whitespace between inline-level boxes, which is why the flex column was tried -- flex removes
  whitespace entirely. It came back 10px, so that theory was wrong and the change was reverted
  rather than left in place making things worse.

  The margin stays at 2px because that is the value asked for and the correct property for the job.
  A comment marks it clearly: **the next step is to find the remaining 6px, not to change this
  number again.** Three sessions have now adjusted this value without moving the result.

## [2.1.140] - 2026-08-20

### Changed

- **The settings page is a three-column grid with card shadows and a hover lift.** One column of
  sixty-five cards on a wide screen left two thirds of the page empty and turned every lookup into a
  scroll.

  | Width | Columns | Card | Overflow |
  |---|---|---|---|
  | 1500 | **3** | 439px | none |
  | 810 | 1 | 668px | none |
  | 390 | 1 | 374px | none |

  Three things had to be found rather than assumed:

  1. **The grid belongs on the fieldset, not the form.** The settings are not direct children of
     `#adminsettings` -- Moodle wraps them in a fieldset -- so a grid on the form laid out exactly
     one child and the cards inside stayed in a column. Found by walking up from a card:
     `form > div.settingsform > fieldset > 74 items`.
  2. **The shadow resolved to nothing.** `--acf-elevation-2` and `-4` were never declared for admin
     pages; only `-1` had been carried across when this page got its own tokens. The rule was
     present and matching while the shadow measured `none`.
  3. **Labels are right-aligned by core**, because in Moodle's own layout the label is a left column
     facing the controls. In a card it is the title, so it goes top-left.

  `auto-fill` with a minimum rather than a fixed three columns, so the grid folds to one on a phone
  by itself. `align-items: start`, or every row would be as tall as its longest card.

### Known

  A 7px horizontal overflow remains at 390px. Capping the track minimum with `min(340px, 100%)` did
  not clear it, so something else on that page is wider than the viewport -- not the cards, which
  measure 374px inside a 390px window. Recorded rather than guessed at.

## [2.1.139] - 2026-08-20

### Added

- **The completion mark says what completion requires, and whether it is met.** A tick says "done"
  and an empty circle says "not done"; neither says what would *make* it done. Hovering now gives
  the conditions and the status: `Receive a grade, View — Not completed`.

  The conditions come from **core's own completion details** -- the same source behind the "Done:
  View" badges Moodle shows on an activity page -- rather than wording invented here that would
  drift from what core displays elsewhere in the same course.

  An activity with no completion tracking gets no tooltip at all. Saying "not completed" about
  something that cannot be completed would be worse than saying nothing.

  The lookup is wrapped: `cm_completion_details` throws for an activity whose module has been
  removed, and a broken completion record should cost one tooltip rather than the whole sidebar.

  Verified: rows with tracking carry the tooltip and read "Completed"; the thirteen rows without
  tracking correctly carry none. No JavaScript errors.

### Note on the verification

  Both tracked activities in the test course use **manual** completion, which has no conditions by
  definition -- so the conditions list came back empty and the tooltip showed the status alone. That
  is correct behaviour rather than a fault, confirmed by querying core directly for every tracked
  activity and finding both marked manual with no conditions.

  The conditions half of this has therefore not been seen working. On a course using automatic
  completion -- view, grade, or a passing mark -- it should read those conditions in front of the
  status. Worth a glance on a real course before relying on it.

## [2.1.138] - 2026-08-20

### Added

- **The activity type in each course index tooltip.** Hovering a row now says what the activity
  *is*, not just what it is called: "Learning" shows **AI Content Creator**, "Practical Assessment"
  shows **Assignment**.

  Names say what a thing is called; types say what it is. In a course built from custom activity
  types that distinction changes what a learner expects before they click, and the cards already
  show it under the title -- the index had no room for a second line, and a tooltip is that room.

  The type comes from the same config the row is built from, so it is the same wording the cards
  use rather than a second source that could disagree with them.

  Where a name is also cut off, the tooltip carries both: `Full name — Type`. The tooltip is the
  only place the whole name appears, so dropping it in favour of the type would have lost more than
  it gave.

  Verified: **15 of 15 rows** carry a tooltip, reading Forum, Page, Text and media area, URL, Quiz,
  Assignment, Book and Glossary against the matching rows. No JavaScript errors.

## [2.1.137] - 2026-08-20

### Added

- **Groundwork for deriving "Recently added" instead of listing it.** Your suggestion, and the right
  one -- a hand-kept list is the only part of the settings page that can go stale, and one nobody
  prunes ends up marking half the page as new.

  The changelog was the obvious source but the wrong one: it is prose, settings appear there by
  their label rather than their name, and phrasing varies -- matching against it would be guesswork
  that fails silently. **The version markers are better.** Every settings block already carries an
  `// ACF-FIX-2.1.NNN:` comment written when the setting was added, so the release each one arrived
  in is recorded beside it, machine-readable, and cannot disagree with the code.

  `classes/local/settingsmeta.php` reads those markers and pairs each setting with the release above
  it.

### Known: the derivation is not reliable yet, and is not being trusted

  Settings are written three ways in `settings.php` -- a literal name, a name built in a loop, and
  the suffix lists those loops read -- and the parser does not yet handle all three. Three attempts:
  the first returned the fragments `default` and `force`; the second returned one result; the third
  returned a JavaScript module name mistaken for a setting.

  **So it is not used on its own.** The list is kept, and the derivation is used only when it
  returns at least as many results -- so the page is always right, and the parser can be finished
  without touching the settings file again.

  Verified with the fallback in place: 65 settings, Affects existing courses 20, New courses only
  32, Recently added 22, Recently added + Colour 12. No JavaScript errors.

  A wrong "new" badge is worse than a stale one: a stale list overstates, a broken parser points at
  the wrong settings entirely.

## [2.1.136] - 2026-08-20

### Changed

- **The gap between collapsed section bands is halved**, from 16px to 8px, so the headings read as
  divided rather than merely spaced.

  What closed it was zeroing the section wrapper's own vertical padding. That padding sat between
  the wrapper's margin and the coloured band, so it added to the gap without appearing to.

### Known: 8px, not the 5px asked for

  The remaining 3px is not the margin. Setting the section's `margin-block-end` to 5px and then to
  2px produced **8px both times** -- so something else is deciding the distance and I did not
  isolate what before running out of budget. The margin is left in place because it is the right
  property for the job, with a comment saying it currently has no measurable effect.

  One finding worth carrying forward: **the first `.courseindex-section` in the DOM is the hidden
  General section**, `display: none`. Several measurements this session read that element by
  mistake, which is why some readings looked contradictory. Any probe here must filter to sections
  with a non-zero height first.

## [2.1.135] - 2026-08-20

### Added

- **A second filter row on the settings page**, combining with the categories rather than replacing
  them. Categories answer *where does this apply*; these answer *what does it do to my site*:

  | Chip | Settings | |
  |---|---|---|
  | Affects existing courses | 20 | every `force…` setting |
  | New courses only | 32 | every `default…` setting |
  | Recently added | 22 | this session's additions |

  **"Affects existing courses" is the one that earns its place.** The difference between a default
  that seeds new courses and an override that reaches existing ones has caused more confusion than
  anything else in this plugin, and being able to list exactly the twenty settings that change
  courses already built answers it directly rather than in prose.

  The chips toggle: pressing the active one clears it, so a filter can always be undone without
  hunting for an "all" option that does not exist on that row. They combine with the category tabs
  -- Recently added plus Colour gives 12.

  The first two are derived from the setting's name and cannot drift. **"Recently added" is a list,
  and a list goes stale** -- it is passed from PHP so it sits beside the settings it names rather
  than buried in JavaScript, with a comment to prune it when the next batch lands. It is
  deliberately short: a permanent "new" badge on everything would mean nothing.

  Pressed chips are marked by a solid border and a filled background, not colour alone.

  Verified: baseline 65, override 20, toggled off 65, recently added 22, recently added + Colour 12.
  No JavaScript errors.

## [2.1.134] - 2026-08-20

### Added

- **Category tabs and search on the settings page.** Sixty-five settings in one column is a scroll,
  not a page: finding the card colour meant either knowing roughly where it sat or reading past
  forty things that were not it.

  A sticky bar above the settings offers a search box and one tab per category, each showing how
  many settings it holds. Choosing a tab filters the list; typing filters it further. Section
  headings hide themselves when nothing beneath them is showing, so a filtered view is not a run of
  empty headings.

  | Tab | Settings |
  |---|---|
  | All settings | 65 |
  | AI Tutor | 6 |
  | Colour | 21 |
  | Course index | 10 |
  | Banner | 2 |
  | Cards | 10 |
  | Navigation | 11 |
  | Timing | 3 |
  | Guided tour | 2 |

  **Categories are matched on the setting's own name, not a hand-kept list.** A new setting files
  itself on the strength of what it is called -- `defaultcardpadding` would land under Cards without
  anyone editing the module. A list would be correct on the day it was written and wrong by the
  third setting added after it. Anything unmatched goes to "Other" rather than disappearing, because
  a setting the reader cannot reach is worse than one in the wrong group. Nothing landed there:
  the eight categories account for all 65.

  Search reads the whole row rather than only the label, so a term appearing in a description finds
  its setting -- someone looking for "answer key" reaches the sharing ceiling even though those
  words are not in its name.

  The selected tab is marked by weight and a filled count as well as colour, so the bar is readable
  to someone who cannot distinguish the accent from the border.

  Verified: filtering to Colour leaves exactly 21 visible, AI Tutor 6, All 65; searching "card"
  leaves 20. No JavaScript errors.

### Note

  The AI Tutor panel on this page is not built. The tutor's endpoint is course-scoped -- it takes a
  course id and answers from that course's content -- and a site settings page has no course. Making
  it work here means either a site-level mode on the LMS-Labs service or a different endpoint, which
  is a backend change rather than a plugin one. Recorded rather than half-built.

## [2.1.133] - 2026-08-20

### Changed

- **The course index rows no longer wash on hover; the icon moves instead.** A row already sits on
  the tinted panel, so a slightly darker rectangle behind it was a weak signal that also fought the
  card surface it is meant to match.

  The icon now takes the same movement the cards use -- `scale(1.08) rotate(-4deg)`, the section
  card's own numbers rather than new ones, so hovering a row and hovering a card feel like one
  gesture and the two cannot drift apart when either is restyled.

  Movement rather than colour reads clearly on a tinted panel where a wash does not, and it costs
  nothing to anyone who cannot perceive the colour difference.

  The icon wrapper had to become `inline-flex`: an inline box ignores `transform` outright, and the
  rule was present and matching while the icon still measured `transform: none`.

  Suppression under `prefers-reduced-motion` was folded into the block that already handles every
  other hover displacement in the plugin, rather than adding a second media query -- one place to
  look, and one place to miss something.

  Verified: the row background is unchanged between rest and hover, and the hover transform is
  declared as `scale(1.08) rotate(-4deg)`.

### Note on the verification

  The movement could not be observed directly here: `prefers-reduced-motion: reduce` reports true in
  this browser regardless of emulation, and the plugin correctly honours it -- so the icon is
  supposed to stay still in that environment, and did. The rule was confirmed from the stylesheet
  instead. Two earlier readings of "icon does not move" were this, not a fault, and one of them
  nearly sent me changing working code.

## [2.1.132] - 2026-08-20

### Fixed

- **Section headings flashed white on hover.** The rules doing it date from 2.1.71, when a heading
  was plain text on a white panel and the job was to stop theme_academi drawing a primary-coloured
  border around it. `background: transparent` was right then. It became wrong the moment the heading
  became a filled band in 2.1.126 -- transparent lets the panel show through, so the band vanished
  under the pointer.

  The band, its text and its borders are now all held at their resting values. There is nothing a
  hover state needs to say here: the whole row is one link and the cursor already communicates that.

  Verified by moving the pointer onto a heading and reading the computed styles either side:

  | | Background | Text |
  |---|---|---|
  | At rest | `rgb(15, 108, 191)` | white |
  | Hovered | `rgb(15, 108, 191)` | white |

  Neither changes. A rule written for one design surviving into the next is worth watching for --
  this is the third time an old hover rule has outlived the thing it was written for.

## [2.1.131] - 2026-08-20

### Changed

- **The sidebar's top block and the banner are the same height**, so the panel and the content meet
  on one line.

  Measured before changing anything: the drawer's own strip plus the player header came to **185px**
  against a **119px** banner -- a 66px step exactly where the eye expects continuity, which is what
  stopped the two reading as one interface.

  Both now derive from a single token at **152px**:

  - The drawer strip drops from 60px to 44px. Sixty pixels of chrome for a close button and a menu
    is more than either needs.
  - The player header takes the remainder, stated as a height rather than left to its content --
    the content varies, a long course name wraps, and the two edges have to line up regardless. Its
    contents are centred vertically so a short name does not sit high in the band.
  - The banner rises from 119px to 152px, in both its plain and image modes, or the two would
    disagree depending on whether a course had a banner image.

  Deriving both from one number rather than setting them to the same value twice is the point:
  restyling either side later cannot silently reintroduce the step.

  Verified: drawer strip 44 + player header 108 = **152px**, banner **152px**, difference **0**.

## [2.1.130] - 2026-08-20

### Added

- **Four switches for the estimated-time pills**, each per course with a site default and a
  site-wide override:

  | Setting | Hides |
  |---|---|
  | Estimated time in the course index | The time on each activity row in the panel |
  | Estimated time on section cards | The section total in each card's corner |
  | Estimated time on activity cards | The time on each activity card |
  | Total course time in the course index | The total under the course name |

  **Four rather than one.** A single switch would have been less to configure, but a site may
  reasonably want per-activity times and not totals, or the reverse -- and once they are collapsed
  into one control that choice is gone. Each hides only its own.

  `display: none` rather than hiding by visibility, so a row closes up instead of leaving the gap
  the pill occupied.

  Verified that each acts independently, hiding one at a time and checking the others survive:

  | | Index rows | Section cards | Course total |
  |---|---|---|---|
  | All shown | 14 | 1 | 1 |
  | Index hidden | **0** | 1 | 1 |
  | + section cards hidden | 0 | **0** | 1 |
  | + total hidden | 0 | 0 | **0** |

  The help text makes the point that an estimate is only worth showing if it is roughly right: on a
  course whose activity durations have not been set, hiding the times reads better than showing
  figures nobody trusts.

## [2.1.129] - 2026-08-20

### Changed

- **The activity icons take the accent colour by default**, reverting the default set in 2.1.128.
  Matching them to the activity name made each row read as one object; on balance the accent is
  preferred, because it gives the panel a second place the course's own colour appears alongside the
  section headings.

  Only the default moved. **Activity icon colour** still overrides it, and setting that to the body
  text colour reproduces the 2.1.128 behaviour exactly -- the help text now says so, so the option
  that was just removed as a default is still reachable as a choice.

  Verified across three cases:

  | | Icon | Section heading | Activity name |
  |---|---|---|---|
  | Theme accent | `rgb(15, 108, 191)` | same | `rgb(30, 41, 59)` |
  | Accent magenta | `rgb(181, 23, 158)` | same | `rgb(30, 41, 59)` |
  | Icon overridden | `rgb(30, 41, 59)` | magenta | `rgb(30, 41, 59)` |

  The middle row is the one that matters: the icon follows the accent when the accent changes,
  rather than coincidentally sharing a fixed colour. The icon background stays transparent in all
  three -- the tile removed in 2.1.126 has not come back.

## [2.1.128] - 2026-08-20

### Fixed

- **The section heading band started 3px inside the panel.** Not a margin, which is where I would
  have looked: core puts a **3px left border** on `.courseindex-section` as its current-section
  marker, and a 1px one on the item. Both sit outside the band's fill, so the colour began 3px in
  while the right edge was already flush. Found by walking up from the heading and reading each box
  rather than nudging the padding.

- **The gap above the first section, when the General section is hidden.** Section 0 is still the
  DOM's `:first-child` even when hidden, so the rule clearing that space was landing on an invisible
  element and the first *visible* section kept its gap. It now targets the section following a
  hidden section 0 as well.

### Changed

- **The activity icons take the colour of the activity name beside them.** They were the accent,
  which made each row a coloured mark next to a dark label rather than one thing. **Activity icon
  colour** still overrides it for anyone who wants them picked out -- the setting is unchanged, only
  its default.

  Verified together:

  | | |
  |---|---|
  | Band left edge | 0, flush with the panel |
  | Band right edge | 341, flush with the panel |
  | Gap above first visible section | **0px** |
  | Icon colour | `rgb(30, 41, 59)` |
  | Activity name colour | `rgb(30, 41, 59)` — identical |

## [2.1.127] - 2026-08-20

### Fixed

- **The banner not reaching the page edges on activity pages** -- diagnosed from a live measurement
  and reproduced before fixing.

  The reported reading was the key:

  ```
  wrap=342-1381 | pad 0/0 | max-width none | banner mar 0/0 | max none
  ```

  Nothing was insetting the banner. Its **container** was simply narrower than the page:
  `#region-main` ended at 1381 while `#page` ran to the viewport. On a stock Moodle those two are
  the same width, which is why eight standard activity types all measured perfect here and this
  never appeared.

  Three separate faults had to be cleared:

  1. **The correction lived inside `lift()`**, which moves the banner to be a direct child of
     `#page` and returns early when it need not -- which is every activity page, where the banner is
     already where it belongs. Everything nested inside therefore never ran there. It is called from
     `init()` now, independently of whether the banner is lifted.
  2. **The measurement was taken from an already-pulled position.** The stylesheet also sets a
     negative margin, and clearing only the *inline* margin left that in place -- so the correction
     replaced the stylesheet's pull instead of completing it, and the banner came up exactly that
     much short. Both margins are zeroed before measuring now.
  3. An earlier attempt failed because the wrap had `inline-size: auto` and shrank to its content,
     so the pull chased a moving edge. The width is stated at 100%.

  Verified against the reported shape -- `#region-main` deliberately narrowed to 374-1413 inside a
  `#page` of 342-1500:

  | Page | Banner | |
  |---|---|---|
  | Activity | 342-1500 | **full width**, pull measured at -87px |
  | Course | 342-1500 | full width, no pull needed |

  The course page needing no correction is as important as the activity page getting one: it
  confirms the fix applies itself only where there is a difference to close.

## [2.1.126] - 2026-08-20

### Changed

- **Section headings in the course index are a filled band with white text.** Once the panel itself
  was tinted in 2.1.125, a heading in the same grey stopped separating one section from the next.
  Filling it gives the list a structure the eye can follow.

  White on the theme's primary measures **5.36:1**, which passes AA. The check matters because the
  colour is settable: a pale choice would put white text on a pale band, and the setting's
  description says the colour needs enough weight to carry white.

  Hover keeps the band and only lifts the text, or the heading flashed to the panel colour as the
  pointer crossed it.

- **The activity icons lost their tile.** A filled square behind every icon reads as a chip rather
  than an icon, and twenty stacked is a column of boxes. The background is gone and the glyph itself
  carries the colour -- the icons are masked, so they take the colour directly.

### Added

- **Section heading colour** and **Activity icon colour**, per course with a site default and a
  site-wide override for each. Both default to the accent colour, which itself follows the theme's
  primary when unset, so neither needs setting to look right.

  Verified with deliberately unmistakable values:

  | | Heading band | Heading text | Icon background | Icon colour |
  |---|---|---|---|---|
  | Defaults | `rgb(15, 108, 191)` | white, 5.36:1 | transparent | `rgb(15, 108, 191)` |
  | Green / magenta | `rgb(47, 111, 79)` | white, 5.99:1 | transparent | `rgb(181, 23, 158)` |

  The transparent icon background in both rows is the point -- it confirms the tile is gone rather
  than merely recoloured.

## [2.1.125] - 2026-08-20

### Added

- **The course index takes the card colour**, and has its own settings if you want it to differ.

  The panel listing the sections and activities now uses the card surface by default, so the two
  match without anyone setting the same value twice -- and changing the card colour moves both
  together.

  **Course index colour** and **Course index colour strength** are added alongside, per course with
  a site default and a site-wide override for each. Left empty, the index simply follows the cards;
  the setting is only for making them differ deliberately.

  That is the important detail: the variable is *not published at all* unless a colour is chosen, so
  the stylesheet falls through to the card colour. Publishing a copy of the card value would have
  looked identical today and drifted the first time someone changed one of them.

  Verified across all three cases:

  | | Card | Index | |
  |---|---|---|---|
  | Both default | `#fafbfc` | `#fafbfc` | match |
  | Card green 15%, index empty | pale green | pale green | **match** |
  | Index set to magenta 10% | pale green | pale magenta | differ, as asked |

  The header band at the top of the panel keeps its own separate setting, since it is meant to
  contrast with the list beneath it rather than match it.

## [2.1.124] - 2026-08-20

### Added

- **Card colour and card colour strength**, per course with a site default and a site-wide override
  for each. The soft grey introduced in 2.1.123 is the shipped default.

  **Strength rather than opacity.** The obvious implementation is an alpha channel, and it would be
  wrong here: the card would become see-through, and whatever sits behind it would show through --
  including the banner, where the two overlap as the page scrolls. The strength mixes the chosen
  colour toward white instead, so the card is fully opaque at every setting while still getting
  paler as the number drops.

  It exists because picking a subtle colour by hand is fiddly. Choose a colour you like, then dial
  it back until it is as quiet as you want, rather than hunting for a paler hex value.

  Out-of-range values are clamped to 0-100 rather than rejected: a number outside that is a typo,
  and the nearest valid shade is a better answer than silently ignoring what was asked for.

  Verified across the range:

  | Setting | Rendered |
  |---|---|
  | Site default | `#fafbfc` |
  | `#2f6f4f` at 100% | full green |
  | `#2f6f4f` at 20% | pale green tint |
  | `#2f6f4f` at 0% | plain white |

  The 0% case matters: it means a site can switch the tint off entirely without having to know that
  white is `#ffffff`.

## [2.1.123] - 2026-08-20

### Changed

- **The section and activity cards take a soft grey.** `#fafbfc` -- a touch above the neutral-50
  step: enough to lift a card off the white page behind it, not enough to read as a filled panel.

  Given its own token rather than changing `--acf-surface-raised`, which the cards shared with the
  course index, the sidebar header and the settings cards. Tinting that would have greyed half the
  plugin, and the request was for the cards.

  Contrast checked rather than assumed, since a tinted background reduces the margin on every piece
  of text sitting on it:

  | | Ratio | |
  |---|---|---|
  | Card title on card | 5.17:1 | passes AA |
  | Activity name on card | 7.31:1 | passes AA |

  Dark mode maps the token to the same surface it used before, so nothing changes there.

## [2.1.122] - 2026-08-20

### Removed

- **The Banner width setting.** The banner is now always full width, and the choice is gone -- the
  per-course option, the site default, the site-wide override, seven language strings, the body
  class and the JavaScript guards that read it.

  It was a setting nobody would sensibly choose the other side of: a banner inset from the content
  it belongs to has no advantage over one that spans it, and carrying both modes meant every rule
  about spacing, alignment and the header offset had to be written and verified twice. Removing it
  halves that surface.

  On a phone and a tablet the banner was already full width regardless -- the course index is an
  overlay at those sizes, so there was never a second mode there to choose between.

  Verified after removal:

  | Width | Banner | Notes |
  |---|---|---|
  | 390 | 0-390 | full viewport |
  | 768 | 48-768 | 48px inset on the left |
  | 810 | 48-810 | 48px inset on the left |
  | 1180 | 342-1180 | full width beside the course index |
  | 1500 | 342-1500 | full width beside the course index |

  **The 48px inset at tablet widths is the known outer-gap fault**, unchanged by this and recorded
  in the handover. Desktop and phone are edge to edge.

### Note for existing courses

  A course that had chosen "Aligned with the course content" now gets full width. Nothing needs
  changing and no data is lost -- the stored value is simply no longer read.

## [2.1.121] - 2026-08-20

### Fixed

- **The course index needing a refresh — confirmed by reproducing the reported state.**

  The reported console line was the whole bug:

  ```
  show=false | page-says-open=true | drawer L=-342 R=0 | visibility=hidden | margin-left=342px
  ```

  The two classes live on different elements and had fallen out of step: `#page` carried
  `show-drawer-left`, so the content was pushed 342px right, while the drawer itself had no `.show`,
  so it stayed parked off-screen and invisible. The content was offset for a drawer that was not
  there.

  Both classes are written by the same server-side flag in core's `drawers.mustache`
  (`{{#courseindexopen}}`), so they start in agreement; something removes `.show` afterwards.
  Rather than guess what, the drawer now follows `#page.show-drawer-left` as well as its own
  `.show`. That is the class that survived in the reported state, and the same class that decides
  the content offset -- so the drawer and the content can no longer disagree, whichever core sets.

  `:has()` is required because the drawer is a **preceding** sibling of `#page` in core's template,
  which no combinator can reach. Supported by every browser Moodle 4.5 targets.

  Verified by forcing the exact reported state -- `show-drawer-left` present, `.show` removed:

  | | Before | After |
  |---|---|---|
  | Drawer right edge | 0 | **342** |
  | Visibility | hidden | **visible** |
  | Content offset | 342px | 342px |

  And a genuinely closed drawer still reads `R=-10`, hidden, with no offset -- so the mirror case
  is intact and the fix does not simply force the drawer open.

## [2.1.120] - 2026-08-20

### Fixed

- **The course index reopening wrong — diagnosed from core's own stylesheet rather than inferred.**

  The reported console output was decisive: `show-drawer-left=true` with the drawer at `L=-342 R=0`
  and `#page margin-left: 342px`. Classes saying open, drawer parked off-screen, content offset for
  a drawer nobody could see.

  theme_boost's `drawer.scss` shows exactly how core does it:

  ```scss
  &.drawer-left {
      width: 285px; max-width: 285px;
      left: calc(-285px + -10px);
      visibility: hidden;
      &.show { left: 0; visibility: visible; }
  }
  ```

  Two things the earlier rules got wrong:

  1. **The closed offset must match the width.** With the width raised to 342px and core's `left`
     still calculated from 285px, a closed drawer peeked 47px onto the page -- measured after an
     attempt to let core own it. It is now `calc(-342px + -10px)`, keeping core's own relationship
     between width and offset including its 10px gutter.
  2. **The open position had to be restated.** The old rule only described the closed state, so
     while `.show` was being applied it kept winning and the drawer stayed parked. Stating
     `.show { left: 0; visibility: visible; }` at the same specificity removes the race rather than
     hoping the timing works out.

  `visibility` is set alongside `left` because core pairs them; setting position without visibility
  leaves an invisible drawer occupying its position.

  **theme_academi inherits Boost's drawer positioning unchanged** -- confirmed by searching its SCSS
  for any `left`, `width` or `transform` on `.drawer-left` and finding none. It restyles the course
  index inside the drawer but does not move the drawer. One rule covers both themes.

### Added

- **A theme compatibility notice on the settings page.** The format overrides parts of core's course
  index, navigation and header; Boost and Academi are the two it is developed and measured against.
  Another theme may position those differently and produce a layout fault that reads as a plugin
  bug. The notice says so plainly and directs people to support@lmshostingservices.com with the name
  of their theme.

### Not completed

  **The full audit did not run.** The development environment stopped responding partway through --
  the web server would not accept connections and several commands returned nothing. The audit
  harness is written and is at `/tmp/audit.js`: it checks five fault conditions (drawer state
  matching its geometry, stranded content offset, a closed drawer peeking, horizontal overflow, and
  elements rendering outside the viewport) across three page types and five widths.

  **The drawer change above is therefore unverified by measurement.** It is grounded in core's
  source rather than a guess, which is a better position than the previous attempts, but it has not
  been observed working. It should be checked before being relied on.

## [2.1.119] - 2026-08-20

### Fixed

- **The space above the banner on course pages, using the data reported from a live page.**

  The reported figures settled it: theme_academi's header ends at **61px**, `#page` carries only
  **20px** of margin, and the banner lands at **80px**. So the header clearance comes from somewhere
  other than that margin, and the 20px is an extra sitting on top of it.

  My earlier version set the margin *to the header's height*, assuming the margin was the clearance.
  On this theme that would have pushed the banner from 80px down to 121px -- worse, not better. It
  was only ever verified against a simulation that shared the wrong assumption.

  It now corrects by the **measured difference** between where the banner is and where it should be,
  which works whatever produces the offset and needs no knowledge of which element owns it.

  Verified against the reported shape -- a 20px margin on top of a separate 60px clearance: course
  page margin corrected from 20px to 1px, banner landing exactly at the header's bottom edge,
  **gap 0px**.

### Known: activity pages still show the gap

  The same page in an activity still measures **19px**. The correction is not running there -- the
  module initialises on course pages but not activity pages, and I ran out of budget to find why.
  The hook's page-type guard admits `mod-` prefixed pages, so the cause is further in.

  **Next step:** on an activity page, check whether `format_aicourse/heroatop` appears in
  `window.requirejs.s.contexts._.defined`. If it is absent the hook is not queueing it there; if it
  is present, `fitHeader` is returning early and the selector or the guard inside it is the issue.

  Since the reported page was an activity page, **this is not yet fixed for the case that was
  reported** -- only for course pages.

## [2.1.118] - 2026-08-20

### Investigated, not fixed — full width on activity pages with the course index closed

  **Answering the question directly: no, this is not fixed.** 2.1.109 improved it with the index
  *open*; with the index *closed* the banner is still short.

  Measured at a 1500px viewport, `#page` spanning 0-1500:

  | Page | Banner | Short by |
  |---|---|---|
  | Course | 48-1500 | 48px left |
  | Activity | 74-1474 | 74px left, 26px right |

  **An attempt to fix it made things worse and has been reverted.** The idea was sound -- measure
  the difference between the banner's box and the page's box and pull by exactly that, since a fixed
  negative margin cannot suit a banner that sits in a different container on every page type. In
  practice the activity page went from 74/26 short to **106/58 short**: with the wrapper's width set
  to `auto` it shrinks to its content, so the measured pull chases an edge that moves as the pull is
  applied. Setting the width back to 100% did not recover it either.

  Reverted to the 2.1.117 behaviour rather than shipping a regression. The measurements above and
  the failed approach are recorded so the next attempt does not repeat it.

  **What is actually needed:** the banner escaping a centred container is a full-bleed problem, and
  the reliable pattern for that is `margin-inline: calc(50% - 50vw)` with `width: 100vw` -- adjusted
  for the course index's width when it is open, since `100vw` includes the area the drawer occupies.
  That is a different mechanism from measuring and pulling, and I did not have the budget to build
  and verify it properly.

## [2.1.117] - 2026-08-20

### Verified

- **The narrow-width sweep is done.** Four pages -- course, section, activity and the plugin
  settings -- across five widths: 390, 768, 810, 1180 and 1500.

  | Checked | Result |
  |---|---|
  | Horizontal overflow (`scrollWidth` vs viewport) | none at any width |
  | Elements rendering off-screen | none |
  | Elements collapsed below 80px | none |
  | JavaScript errors | none |

  This is the check that had been outstanding since 2.1.105, when a drawer width shipped without a
  media query and made the format unusable on phones for eighteen versions. Everything built since
  had still only been measured at 1500px. It is now measured, and it is clean.

  One finding was a false positive worth recording: the player sidebar header reports as off-screen
  at 390, 768 and 810. It sits inside the drawer, which at those widths is an overlay parked
  off-screen until opened -- correct behaviour. The check now excludes anything inside a closed
  drawer, or it would flag the same non-fault on every future run.

## [2.1.116] - 2026-08-20

### Changed

- **Two `!important` declarations removed from the drawer's open/close handling.** They were added
  in 2.1.86 to widen the drawer and were also overriding core while it animated -- the most likely
  cause of the layout being wrong until a hard refresh.

  - The closed position keeps its `left: -342px`, because core parks the drawer at its own default
    width and a 342px drawer left to core peeked 47px onto the page. Measured, after trying to let
    core own it entirely. But it is stated **without** `!important` now: the selector carries two
    body classes against core's one, so ordinary specificity is enough, and core can still override
    it while animating.
  - The content offset now keys on `.show-drawer-left`, which tracks the drawer's real state,
    rather than on the body class alone. A closed drawer no longer leaves a 342px offset behind.

  Verified the closed position is right (`x=-342`, no peek) and mobile is unaffected: 285px overlay
  with no offset at 390, 768 and 810; 342px docked with a 342px offset at 1180 and 1500.

### Still not confirmed fixed

  **I could not reproduce the original fault in this environment**, so I cannot say the refresh
  problem is solved -- only that the mechanism most likely causing it is gone. Core's drawer toggle
  does not respond to a synthetic click here: `show-drawer-left` stayed `true` through every
  attempt, in all three runs, so the state change this depends on never happened in the test.

  Needs confirming on a real browser: open the course, close the course index, reopen it, and check
  the layout without refreshing.

  **The blank space above the banner in full-width mode is also unconfirmed.** Two attempts
  (2.1.107, 2.1.108) both measured a 0px gap here, including against a deliberately inflated header
  reservation, and the report persisted afterwards. Something about the real theme is not reproduced
  by that simulation.

## [2.1.115] - 2026-08-20

### Fixed

- **Release pipeline blocker: the flagged token appeared only in my own comments.** The three real
  uses were replaced with proper types back in 2.1.100 -- URL for the two banner endpoints, plain
  text for the durations setting -- but the comments explaining those changes named the old type,
  and the pipeline matches on the literal string.

  The comments are reworded to describe the change without quoting the token. The plugin now
  contains **zero occurrences anywhere**, in code or comments, and the declared types are unchanged.

### Not changed, with reasons

- **"Lowercase inline comments" in five files is the GPL licence header.** The four flagged lines
  per file are `// it under the terms...`, `// the Free Software Foundation...`,
  `// but WITHOUT ANY WARRANTY...` and `// along with Moodle...` -- byte-identical to every file in
  Moodle core, and required verbatim. Changing them would break the licence and fail Moodle's own
  checks. The warning cannot be resolved without introducing a real fault.

- **`function(` spacing in `courseformat.js`**, unchanged since 2.1.100 for the same reason: Moodle's
  own ESLint config sets `space-before-function-paren: never` and reports 107 warnings if the space
  is added. Moodle's config is authoritative for a Moodle plugin.

  Both warnings are worth raising with whoever maintains the pipeline -- one asks for a licence
  header that differs from core's, the other contradicts core's lint rule.

## [2.1.114] - 2026-08-20

### Changed

- Rules added for the last two spacing discrepancies in full-width mode: the 8px on
  `#topofscroll.main-inner` and the 16px top margin on `.format-aicourse-container`, both
  identified by dumping every box between `#page` and the cards grid in one pass rather than
  testing suspected elements one at a time.

  **The rules do not yet take effect** -- the horizontal inset still measures 40px and the vertical
  49px afterwards. They are not missing, they are being outranked. The full chain measurement is in
  the handover so the next session can settle it by inspection instead of by adding more rules.

### Known: reopening the course index is broken in full-width mode

  Measured across a toggle: `drawer-open-index` stays on `<body>` through a close, so the body class
  and the drawer's real position disagree, and `#page` keeps `margin-left: 342px` in every state --
  shifting the content right by the width of a drawer that is not on screen. A hard refresh corrects
  it, which is why it reads as a caching problem.

  The `!important` declarations added in 2.1.86 to widen the drawer are the prime suspect: they may
  be beating core's state handling as well as its width. Removing them and re-measuring is the first
  thing to try, and is written up in the handover.

## [2.1.113] - 2026-08-20

### Fixed

- **"Too much data passed as arguments to js_call_amd" on the course page.** The player sidebar's
  configuration carries a row per activity, so a course of any size pushed the argument string past
  the 1024-character limit Moodle warns about. Measured at 3,040 characters on a 15-activity
  course, and it grows with the course.

  The config now travels through the page rather than the call, which is Moodle's own advice for
  this case: an inert `<script type="application/json">` element that the module reads on init. The
  argument form still works, so anything calling `init()` directly is unaffected.

  `JSON_HEX_TAG` on the encode, so a `</script>` appearing inside a course or activity name cannot
  close the element early.

- **`courseformat.min.js` was a nested define, and every page using it threw.** The console showed
  *"Mismatched anonymous define() module"* alongside a `TypeError` -- RequireJS refuses a module
  whose `define()` sits inside another one, so the module never loaded at all.

  `courseformat.js` is a legacy AMD file that calls `define()` itself, unlike the plugin's other
  modules which are ES modules. The build script wrapped it in a second `define()`. It is now
  minified as-is with its module name injected into its own call.

  This was introduced when the file was rebuilt earlier in the session and shipped in several
  versions. It was found only because the console was checked while verifying something else --
  the build produced valid-looking output and every static gate passed.

  Verified: one `define()` in the built file, and no page errors of any kind on a course page.

## [2.1.112] - 2026-08-20

### Fixed

- **Two of the three sources of uneven spacing in full-width mode**, traced by walking from a card
  up to the banner and reading every box rather than guessing:

  - The content container carries a **16px top margin** on top of its padding, so the space under
    the banner was 32 + 16 against a 32px token. The token now sets the whole distance.
  - The horizontal inset came from **two** places, not one: the region-main wrapper sits 40px inside
    `#page`, and a div within it adds another 15px of its own padding. Both cleared, so the gutter
    is stated once.

  Horizontal inset measured **55px before, 40px after**. Cards remain exactly 32px apart, matching
  the token.

### Corrected

  My earlier report of a 184px banner-to-card gap was wrong. That measurement ran from the banner to
  the first *section card*, and on this course the General section's Announcements block sits
  between the two -- so most of the 184px was content, not spacing. The real figure was always
  around 48px. I reported a number without checking what was inside it.

### Known: still not exact

  Banner-to-content measures **49px** and the trailing edge **40px**, against a 32px token. Both are
  closer than before and neither is now the large discrepancy first reported, but they are not the
  token and I have not found the remaining 17px and 8px. Recorded rather than described as done.

## [2.1.111] - 2026-08-20

### Added

- **Tests that every language string the plugin asks for actually exists.** A missing string is not
  cosmetic: Moodle calls `get_string()` while building the settings form, so one absent key fills
  the page with debugging output and can stop an administrator reaching the settings at all.

  It is also the easiest mistake to make -- strings are added by hand beside the code that uses
  them, and a rename or a bad merge separates the two silently. Nothing fails until someone opens
  the page, which is exactly how this surfaced.

  Two checks: every `get_string('x', 'format_aicourse')` in the plugin's own source resolves, and
  every course setting that declares help text has that help text defined. 103 tests now, up from
  101, both new ones passing.

### Investigated

  The reported `heroimageoverlay_desc` error is **not present in any version shipped in this
  session.** Checked 2.1.92, .96, .100, .104, .106, .108 and .110 by extracting each package and
  loading its language file: the string is defined in all seven, at 1,341 characters. The reported
  line 294 of `settings.php` is a different setting entirely in these builds.

  The site reporting it is therefore running a build that predates this work, and updating it
  resolves the error. The tests above are so that the same class of fault cannot ship again rather
  than because one did.

## [2.1.110] - 2026-08-20

### Changed

- **One gutter value for every gap in full-width mode.** The spacing was coming from three places --
  the container's padding, the space under the banner, and the grid gap between cards. Three
  numbers that happened to look close but were never the same, and the eye catches that even when
  the figures are not far apart.

  They now all read from a single token: 24px, stepping to 32px above 1200px and 40px above 1600px,
  because the same gap starts to look mean against a bigger card.

  Verified: cards measure **32px apart horizontally and 32px vertically** at a 1500px viewport,
  matching the token exactly.

### Added

- **`docs/ai-course-format.md`** -- a full write-up for the LMS-Labs documentation page, covering
  the banner, cards, course index, timings, page trimming, colour, the AI Tutor, the tour and
  reporting. It states the two rules that catch people out most -- that defaults only seed new
  courses while overrides reach existing ones, and that everything hidden returns in Edit mode.

### Known: not finished

  **The outer gaps do not yet match the inner ones.** Card-to-card spacing is exact, but measured at
  a 1500px viewport the distance from the banner down to the first card is 184px and from the
  content edge to the last card 55px, against a 32px token. Something upstream is still adding
  space that the token does not control, and I have not traced it.

  **The course index can still be closed in full-width mode.** You asked for either a recalculation
  that keeps the banner full width and the gaps even when it closes, or the toggle disabled. Neither
  is done. The recalculation is the better answer -- disabling a control a learner expects to work
  is a poor trade -- but it needs the outer-gap problem solved first, since it is the same
  measurement.

  Recorded rather than left to be discovered.

## [2.1.109] - 2026-08-20

### Fixed

- **Full width had no effect on activity pages.** A course page lifts the banner out to `#page`;
  an activity page leaves it inside `#region-main`. Every rule written for this targeted the course
  page's structure, so on an activity page the banner kept 24px of its own inline padding plus 48px
  from `#page-content` and stayed inset.

  Rather than adding `#region-main` to the list of ancestors -- which works only until the next
  wrapper differs -- the banner is pulled back to the edges of whatever contains it with a negative
  margin matching the padding it sits inside. That holds wherever it is nested, because it is
  stated relative to its own surroundings rather than to one particular ancestor.

  Measured on an activity page: **422-1420 before, 350-1492 after**, against 342-1500 on the course
  page.

### Known

  The activity page banner is 8px short at each edge of the course page's. `#page-content` sits 8px
  inside `#page` on that layout and I have not traced where that comes from. At this size it is not
  visible, and it is recorded rather than left as an unexplained difference between two pages that
  should match.

## [2.1.108] - 2026-08-20

### Fixed

- **The header measurement ran too early to be right.** 2.1.107 measured the visible header once,
  as soon as the module loaded -- which can be before web fonts land or before a theme's own script
  finishes adjusting its header. Either changes the header's height after the measurement, leaving
  a strip of the old reservation behind.

  It now re-measures on `load`, again at 250ms and 1s, and whenever the header element itself
  changes size -- a theme can resize it without a window resize, which the existing resize listener
  would never see.

  Verified in the exact combination reported: full width with the logo band hidden, and the page's
  reserved offset deliberately inflated to 140px for a band that is not visible. Corrected to 61px
  with a **0px gap**, and still 0px a second later -- the second reading is the point, since a
  measurement that is right briefly and wrong afterwards is what this fixes.

## [2.1.107] - 2026-08-20

### Fixed

- **Blank space above the banner in full-width mode.** Moodle reserves a fixed offset at the top of
  the page for the fixed site header. That figure assumes the header the theme normally draws -- so
  on a theme with two bands, or when this format's own setting hides the logo band, the reservation
  is taller than the header actually on screen and the difference shows as blank space.

  The reservation is now measured rather than assumed: the module finds the lowest edge of whatever
  fixed or sticky band is really at the top of the page and sets the offset to match. Whatever the
  theme draws, and whatever has been hidden, the banner sits directly beneath it.

  Only in full-width mode. Everywhere else the banner is meant to sit inside the content column
  with its normal spacing, and changing the page offset there would move the whole layout.

  Verified by inflating the reserved offset to 140px to reproduce the case -- a theme reserving far
  more than its visible header needs. It corrected back to 61px with a **0px gap** between the
  header and the banner, matching the uninflated measurement exactly.

  Re-measured on resize, since a header's height can change with the viewport.

## [2.1.106] - 2026-08-20

### Changed

- **Square corners throughout.** The rounded corners read as cards floating on a page; square reads
  as a header and panels belonging to it, which is the shape a dedicated player has.

  Set at the four radius tokens rather than rule by rule, so nothing is missed and nothing can
  drift back: every card, panel and banner reads from them. The banner's two modes were also set
  directly, since plain and image mode declare their own radius and would otherwise disagree
  depending on whether a course had a banner image.

  The pill token is deliberately untouched. The status badges and the small time pills are meant to
  be pills; squaring those would turn a deliberate shape into something that looks like an
  oversight.

  Verified: banner, section card, icon tile and sidebar header all `0px`, with the pill-shaped
  badges unchanged.

## [2.1.105] - 2026-08-20

### Fixed

- **The format was unusable on phones and tablets.** The widened course index shipped in 2.1.86
  with **no media query**. Below the breakpoint Moodle turns the drawer into an overlay that slides
  over the content, and the content has no offset at all -- so forcing a 342px width and a 342px
  margin pushed the page off the side of the screen.

  Measured on a 390px phone before the fix: content started at 342px leaving 48px of usable width,
  the banner rendered **8px wide**, and the document scrolled horizontally to 541px. That is not a
  degraded layout, it is a broken one, and it shipped because every check I ran for eighteen
  versions was at 1500px.

  The width rules are now scoped to 992px and above. 768px was tried first and was still wrong: it
  applied the offset at exactly the width an iPad sits at in portrait, squeezing the content to
  296px on a 768px screen.

  | Width | Drawer | Page offset | Banner | Scroll |
  |---|---|---|---|---|
  | Phone 390 | 285 overlay | 0 | 20-370 | none |
  | Tablet 768 | 285 overlay | 0 | 89-679 | none |
  | iPad portrait 810 | 285 overlay | 0 | 90-720 | none |
  | iPad landscape 1180 | 342 docked | 342px | 389-1085 | none |
  | Desktop 1500 | 342 docked | 342px | 389-1405 | none |

  Confirmed on a real phone viewport with touch emulation: document width equals viewport width at
  390px, and cards measure 350px.

### Note

  Every visual change this session was verified at 1500px only. This one reached production
  because of that, and the same gap applies to the rest of the session's CSS -- narrow-width checks
  belong in the routine, not as a response to a report.

## [2.1.104] - 2026-08-20

### Fixed

- **The full-width banner now actually spans the full width.** Two things were holding it, and
  neither was where my first two attempts looked. Found by walking the ancestor chain and reading
  every box rather than guessing which one it was:

  1. The banner wrapper carries **47px of its own inline padding** -- the gutter that keeps it
     aligned with the content column. The rule setting `padding-inline: 0` was losing on
     specificity, so it measured 47px regardless of what the setting said.
  2. `#page` adds **48px of padding** on the trailing edge.

  Clearing `max-inline-size` never had a chance: nothing was capping the width, it was being eaten
  by padding at two levels.

  The centring is released as well, so "full width" does not depend on whether the course index
  happens to be open. The content below keeps the gutter the banner gave up, so the cards stay
  where they were.

  Measured at a 1500px viewport: contained **389-1405**, full width **342-1500** -- the entire area
  beside the course index -- with the wrapper and `#page` both at `0px/0px` padding and the gap
  above closed from 72px to 60px.

## [2.1.103] - 2026-08-20

### Changed

- **The AI Tutor's settings knowledge now updates itself.** It was a hand-maintained list of 15
  settings, which meant every new one had to be remembered there as well -- and the one that got
  forgotten would be the one a teacher asked about, with the tutor confidently unaware it existed.

  The list is now taken from the format's own options, so a setting is included the moment it is
  defined. It went from 15 to **23** the instant it was switched over, picking up settings added
  across this session including "Hide the General section" from 2.1.102 -- which is exactly the
  gap the old approach would have left.

  A setting is described if it has both a label and a help string; anything without them is
  skipped, since there would be nothing useful to say about it.

### Added

- **Banner width**, per course with a site default and override: aligned with the course content,
  or full width. Full width also removes the gap above the banner so it sits directly under the
  site header.

### Note

  The width portion did not work in the first cut of this setting; it is fixed in 2.1.104.

## [2.1.102] - 2026-08-20

### Added

- **Hide the General section.** Section 0 of a Moodle course usually holds only the Announcements
  forum, and on a course whose real content starts at Section 1 it is a heading learners read past
  before reaching anything they came for.

  Three states, per course with a site default and a site-wide override, hiding it from both the
  course index and the section cards.

  Section 0 is matched on `data-number="0"`, which core sets, rather than on position: a course can
  have section 0 hidden or delegated with something else sitting first, and matching on position
  would then hide the wrong section.

  It hides the section from view only. Nothing is deleted and announcements still post and email
  exactly as before -- and it always returns in edit mode, so a teacher can still reach it.

  Verified across all three cases, with the other sections checked each time to be sure nothing
  else was caught:

  | Setting | General | Other sections visible |
  |---|---|---|
  | Show | shown | 6 of 6 |
  | Hide from everyone | **hidden** | 6 of 6 |
  | Hide from everyone, editing | shown | 6 of 6 |

## [2.1.101] - 2026-08-20

### Changed

- **The drawer's header strip takes the course index header colour.** The strip carrying the close
  button and the options menu sits directly above the band with the logo, course name and progress
  ring, and was still the theme's colour -- so the top of the panel was two shades with a seam
  between them.

  It uses the same `--acf-player-header-bg` token, so a site that changes the header colour in the
  settings changes both together rather than matching one and discovering the other later.

  Verified across three values, the setting driving both every time:

  | Header colour | Strip | Band |
  |---|---|---|
  | Default | `rgb(236, 239, 244)` | same |
  | `#d8dfe8` | `rgb(216, 223, 232)` | same |
  | `#2f6f4f` | `rgb(47, 111, 79)` | same |

  The third is a deliberately unusual green: a value nothing else in the palette would produce, so
  a match cannot be coincidence.

## [2.1.100] - 2026-08-20

### Fixed

- **Release pipeline blocker: `PARAM_RAW` in three places.** All three had a correct type available
  and are now declared properly rather than suppressed:

  - `generate_banner_image` and `get_banner_status` return a pluginfile URL this plugin builds
    itself. `PARAM_URL` documents the contract and has Moodle validate it on the way out.
  - The activity durations setting holds `modname=minutes` lines and nothing else, so `PARAM_TEXT`
    is right; the parser already discards anything not matching that shape.

- **Pipeline warning: lowercase inline comments** in five files, now capitalised.

### Not changed, with reasons

- **`function(` spacing in `courseformat.js`.** The pipeline asks for `function (`; Moodle's own
  ESLint config sets `space-before-function-paren: never` and reports 107 warnings if the space is
  added. I made the change, saw ESLint object, and reverted it. Moodle's config is authoritative
  for a Moodle plugin, so the file stays as it is and the pipeline warning stands unresolved
  rather than trading a clean lint for a clean report.

- **Two files under `classes/external/` with no `require_capability`.** `credentials.php` and
  `throttle.php` are internal helpers that happen to live in that folder; neither is registered in
  `db/services.php`, so neither is reachable as a web service. A false positive in my own audit,
  recorded so the next person checking does not have to work it out again.

### Verified

  Full sweep at 2.1.100: phpcs `moodle` and `moodle-extra` both clean, 83 files lint-clean, ESLint
  0/0, 14 AMD modules all with current source maps, stylelint 5 (line length only), 588 language
  strings sorted with no duplicates, no `PARAM_RAW`, no raw `$_GET`/`$_POST`, no API key reaching
  output.

### Could not reproduce

  The course index and banner disappearing on an assignment's Add submission page. Measured on
  both `mod-assign-view` and `mod-assign-editsubmission`: banner, course index and drawer all
  present on each. The page-type guard added in 2.1.95 admits anything beginning `mod-`, which
  includes that page. Needs the `<body>` tag from the affected page to go further.

## [2.1.99] - 2026-08-20

### Fixed

- **Setting descriptions were cut off at the bottom of their card.** Moodle floats the label and
  the setting inside each row, and a float adds nothing to its parent's height -- so the cards
  introduced in 2.1.92 were sized as though they were empty, and any description longer than a line
  or two ran out through the bottom border.

  The fault got worse the more a setting had to say, which is exactly backwards: the settings that
  needed the most explanation were the ones losing it.

  Fixed with `display: flow-root`, which establishes a block formatting context and contains the
  floats without the side effects of `overflow: hidden` -- that would have clipped the text rather
  than making room for it, and hidden the symptom while keeping the bug.

  Verified by measuring every child against its card's bottom edge across all 43 settings: **zero
  overflowing**, worst spill 0px.

## [2.1.98] - 2026-08-20

### Changed

- **The section and activity cards carry the accent at rest, not on hover.** Both drew a neutral
  border normally and switched it to the accent when the pointer arrived -- the same fault already
  corrected on the card title, the section icon, the activity count and the time pills. On a touch
  screen the accent never appeared on a card at all.

  The border is now the accent from the start. Hover keeps what it should: the card lifts and its
  shadow deepens, and the icon still scales and tilts. Nothing changes colour.

  The green completion state is untouched, as asked -- a card at 100% still turns green, and that
  now reads as a real change of state rather than competing with a colour that was already moving
  under the pointer.

  Verified: with a magenta accent the card's border measures the accent at 18% both at rest and
  while hovered -- unchanged -- and the hover rule confirmed from the stylesheet to set
  `translateY(-6px)` and a deeper shadow, with no border colour at all.

## [2.1.97] - 2026-08-20

### Fixed

- **Only the activity name was clickable in the course index, not the row.** Core sizes the link to
  its text, so on a 342px row a learner aiming at the row and landing beside a short name got
  nothing -- and no way to tell why. The icon, the duration and all the space between them were
  dead.

  The link is now stretched over the row with an absolutely positioned overlay rather than by making
  the link fill the row: the icon and the time pill are siblings of the link, not children, so
  sizing the link would push them out of place. The overlay leaves the layout untouched.

  Two things this took, both found by measuring where a click actually lands:

  - The link had to be made `position: static`. While it was positioned, the overlay resolved
    against the link's own box -- the text -- and covered precisely the area that was already
    clickable, so the fix appeared to do nothing.
  - On a section heading the overlay starts after the chevron rather than covering the row.
    Raising the chevron above the overlay with `z-index` did not hold, measured unreachable at both
    2 and 3. Leaving a gap is not subject to stacking order at all. Losing the ability to collapse
    a section would have been a worse fault than the one being fixed.

  Verified by hit-testing three points across each row: far right, middle and beside the text. All
  three reach the link on an activity row; on a section row the two open the section and the third,
  over the chevron, still collapses it.

## [2.1.96] - 2026-08-20

### Added

- **The AI Tutor can now answer questions about the format's own settings.** A teacher asking "how
  do I hide the tabs?" or "why has my colour not changed?" was getting an answer about their course
  content, because that is all the tutor had ever been given.

  A reference covering 15 course settings is now included with a teacher's questions, built **from
  the language strings** rather than written out separately -- so it cannot drift from what the
  settings screens say. Change a description once and the tutor's answer changes with it, in
  whatever language the site runs in.

  It also states the two things that catch people out most often, because they are behaviour rather
  than description and no single setting's text would carry them:

  1. A site default only applies to brand new courses; an override is what reaches courses that
     already exist.
  2. Everything this format hides comes back while Edit mode is on -- so "it did not hide" is
     usually "Edit mode was on".

  **Editors only.** Verified: a teacher's request carries the reference at 9,886 characters, a
  student's does not. A learner has no use for it, it would be a large addition to every request
  they make, and their questions are about the course rather than how it was built.

  Markdown and HTML are stripped before sending -- neither helps a language model and both cost
  tokens. A string missing from a translation is skipped rather than sent as `[[stringname]]`,
  which the tutor would otherwise repeat back as though it meant something.

## [2.1.95] - 2026-08-20

### Fixed

- **The plugin was loading five JavaScript modules onto the course settings page.** The footer hook
  fires wherever `$COURSE` is set, which includes `/course/edit.php` -- so the banner positioner,
  the body-class writer, the tab relocator, the player sidebar and the first-run tour were all
  initialising on the settings form, every one of them reaching into the DOM for elements that are
  not there.

  Measured on that page: `heroatop`, `bodyclass`, `coursenav`, `player` and `tour` all defined.
  After the fix: **none**.

  This is the likely cause of the Course format section opening only intermittently -- the form's
  own collapse behaviour was competing with scripts that had no business running on that page. It
  would also explain why refreshing sometimes helped: a different load order, a different outcome.

  The hook now returns immediately unless the page is one this format actually draws -- the course,
  its sections, or an activity within it. Verified the course page is unaffected: the drawer still
  measures 342px with all 15 activity rows.

## [2.1.94] - 2026-08-20

### Changed

- **The time pills now carry the accent**, the same way the section icons do: a faded tint of the
  course colour behind, the colour itself as the text. They were a grey chip stuck to the corner of
  the card, outside the palette everything around them had joined.

  Applied to both the section card's pill and the activity card's. The activity card's outline goes
  with the change -- a tint and a border together are two decisions on one small object, and the
  fill alone is enough to read as a pill.

  The tint is the weak step rather than the full colour, because this sits behind small text and a
  stronger fill costs legibility for nothing. Checked rather than assumed: with a magenta accent
  the text measures **5.13:1** against the tinted background, which passes WCAG AA for normal text.

## [2.1.93] - 2026-08-20

### Fixed

- **"3 activities" and the "+N more" links only showed the accent on hover.** The same fault the
  card title and section icon had before 2.1.61, in three more places. A link that only looks like
  one when the pointer is over it is invisible on a touch screen and easy to miss on a desktop.

  All three now carry the accent at rest, and hover adds the underline alone rather than changing
  the colour -- changing colour on hover is precisely what made the accent read as a hover effect
  rather than the course's own colour.

  Verified with a magenta accent and no pointer over the page: the card title, the activity count
  and the "+N more" link all resolve to `rgb(181, 23, 158)`.

## [2.1.92] - 2026-08-20

### Changed

- **The settings explanations are rewritten in plain language.** Every one now says what the thing
  *is* before it says what the options do, and gives an example where the choice is not obvious.

  The Course navigation tabs description, for instance, used to assume you knew what the secondary
  navigation was. It now opens: "the row of links Moodle puts above your course content: Course,
  Settings, Participants, Grades, Reports and More" -- and then explains that this format already
  gives you those places to go, which is why hiding them is reasonable.

  The same treatment for the player sidebar, the course index starting state, the logo band, the
  footer, the breadcrumb, the accent colour, the header colour, the duration settings and the tab
  placement.

  Two points that repeatedly caused confusion are now stated in the settings themselves rather than
  left to be discovered: that a **default only affects brand new courses** and an **override is the
  only thing that reaches courses that already exist**; and that **everything hidden comes back in
  Edit mode**.

- **Both settings pages are styled.** Moodle renders forty settings as a flat run of label-and-field
  rows with the help text in small grey type, which at this length is a wall.

  Each setting is now a card of its own, so the eye can find where one ends and the next begins.
  Descriptions are set as readable prose at a sensible measure rather than fine print. The
  `format_aicourse | settingname` line recedes into a quiet pill instead of competing with the
  setting's title. Inline code -- module names, hex colours, the `modulename=minutes` lines -- is
  marked as code. Section headings carry the accent.

  Scoped by page id and by the course format fieldset, so no other admin page and no other part of
  a course's settings form is touched.

### Fixed

- The styling initially rendered as nothing at all: the design tokens are declared on
  `body.format-aicourse`, which an admin page never has, so every `var()` resolved to empty --
  measured as a transparent background, 0px border and 0px radius while the selectors were matching
  all 43 settings correctly. The tokens are now declared on the settings pages too, rather than
  relying on a scope those pages never enter.

## [2.1.91] - 2026-08-20

### Changed

- **Course navigation tabs (override all courses) now ships as "Hide from everyone."** The plain
  default, changed in 2.1.88, only seeds courses that have never saved the option -- so on any site
  with existing courses it changes nothing, which is exactly the confusion it caused. The override
  is the control that reaches courses already created, so it is the one that has to carry the
  intent.

  Both settings now ship as "Hide from everyone": `defaulthidesecondarynav` for new courses and
  `forcehidesecondarynav` for every course including existing ones.

  This does remove per-course control out of the box, which is a real cost -- an administrator who
  wants some courses to keep the tabs has to set the override back to "Follow each course". It is a
  deliberate trade in favour of the format's own navigation, and it is recorded in the setting so
  the reasoning is not lost.

  Verified with both site values cleared so the shipped defaults apply: both resolve to `2`, the
  tab bar is hidden with edit mode off and visible with edit mode on.

## [2.1.90] - 2026-08-20

### Fixed

- **The banner kept its alignment inset after the course index was closed.** Closing the drawer
  widens the content column without resizing the window, and the ResizeObserver added in 2.1.44
  only fires when the observed element's own box changes -- which it does not if the theme animates
  the offset on an ancestor instead.

  The body class Moodle toggles for the drawer is now watched directly, which covers every route to
  opening or closing it: the toggle button, the close button, a keyboard shortcut, or the
  preference being applied on load. Measured after the transition finishes, since measuring
  mid-animation records a width that is about to change.

  Verified across a toggle: the banner and the first card stay aligned with each other in both
  states, and the inset is recalculated rather than kept from the previous one.

### Known, not fixed

  With the drawer closed the content column itself is still not centred in the window -- measured
  348px of space on the left against 95px on the right at a 1500px viewport. The banner and cards
  agree with each other, so this is the column's own placement rather than the alignment code, and
  it is very likely the drawer's offset lingering on an ancestor after the drawer closes.

  I could not reproduce a real drawer toggle in the development environment -- the toggle button
  did not respond to a synthetic click -- so this was measured by flipping the class by hand, which
  is not the same thing and may not exercise whatever the theme does on close. Recorded rather than
  guessed at.

## [2.1.89] - 2026-08-20

### Added

- **The course tabs can move into the site header for teachers.** Course, Settings, Participants,
  Grades and Reports are links only editors use, but as a full-width band under the banner they
  take a lot of vertical space from the course itself. Hiding them outright costs a teacher the
  fastest route to those pages.

  Set *Where the course tabs appear* to **In the site header** and they move up beside Home,
  Dashboard and My courses -- where a teacher already looks for links of that kind -- and the
  vertical space goes back to the course.

  Only for users who can edit; learners remain governed by the Course navigation tabs setting. The
  tabs return to their normal place while editing, like every other visibility rule in this format.

  Both Boost and theme_academi mark the header row `.primary-navigation`, and the module falls back
  through the containers those links commonly sit in, so a theme that names things differently gets
  a sensible home rather than nothing happening. If no suitable container is found the bar is left
  exactly where the theme put it rather than moved somewhere it would look wrong.

  Restyled once moved: no full width, no background, no bottom border, and the active tab marked
  with an underline rather than the boxed tab shape, which does not belong in a header row.

  Verified: in view mode the bar is inside the header at 16px from the top and carries Course,
  Settings, Participants and Grades; in edit mode it is back below the banner at 164px.

## [2.1.88] - 2026-08-20

### Changed

- **The site default for Course navigation tabs is now "Hide from everyone".** It was "Hide from
  students", which exempts anyone who can edit -- so every teacher and administrator saw the bar
  permanently and had to change the setting themselves to see the layout a learner gets.

  Edit mode is unaffected and always restores it. Verified on the same page: **hidden with edit
  mode off, visible with edit mode on.**

### Note for existing courses

  A site default only seeds courses that have not saved the option. A course with a value already
  stored keeps it, which is why changing the default alone does not clear the bar on a course that
  has been configured before.

  To change every existing course at once, use **Force navigation tabs on all courses** under
  *Plugins ▸ Course formats ▸ AI Course Format*. That overrides each course's own setting rather
  than seeding it. Reproduced and confirmed: with a course stored as "hide from students" and an
  administrator viewing, the bar is visible; setting the force to "hide from everyone" hides it,
  and the body class changes from `aicourse-hidenav-students` to `aicourse-hidenav-all`.

## [2.1.87] - 2026-08-20

### Fixed

Three faults introduced by widening the panel in 2.1.86, all measured rather than guessed:

- **A large gap to the right of the course index.** Core offsets the content with `margin-left`,
  not padding. Adding padding on top of its margin produced 285 + 342 = **627px** of offset. The
  margin is what has to move; the padding is now zero.

- **The header band lost its side padding**, pushing the course name to the panel edge. The rule
  clearing the drawer wrapper's insets targeted `.drawercontent > :first-child` -- and the header
  *is* that first child. Excluded from it; padding measured back at 16px.

- **A 4px gap under the band**, enough to read as a second line beneath the first. It came from
  `padding-top` on `.courseindex-section`, not from anything on the heading -- found by walking
  down from the header and measuring each box in turn, which is what the two previous attempts at
  this skipped. Cleared on the first section only, so spacing between later sections is unchanged.

  Header bottom and first section top now both measure 245: no gap, one line.

## [2.1.86] - 2026-08-20

### Changed

- **The course index is 20% wider** -- 285px to 342px. Core's width was chosen for a plain list of
  links; this panel carries an icon, a name, a duration and a completion tick on every row, so
  names were being truncated far earlier than they needed to be.

  Core caps the drawer with `max-width` at the same value as `width`, so raising the width alone
  changed nothing -- it still measured 285px with the new rule present and matching. Both have to
  move, along with the off-screen position and the content offset, which are keyed to the same
  number: change one without the others and the drawer either peeks on screen when closed or
  overlaps the content when open. Verified at 342px with no overlap.

- **The gap between a section's chevron and its title is halved.** The chevron sat in a fixed
  gutter sized for a plain list, and it is the single biggest recovery of horizontal space in the
  panel.

- **The course tab bar now defaults to hidden outside edit mode.** It was defaulting to "hide from
  students", which exempts anyone who can edit -- so a teacher saw it constantly and had to change
  the setting to get the layout a learner sees. It duplicates what the hero and the course index
  already provide, and it returns automatically the moment editing is switched on, so nothing is
  lost while working.

  Existing courses keep whatever they have set; this changes the starting value only.

## [2.1.85] - 2026-08-20

### Fixed

- **The shaded header band did not reach the panel edges.** It sat inside the inset the drawer's
  own wrapper carries, so a strip of white showed down each side and along the top -- and that
  strip, meeting the band's bottom border, read as a doubled line. Measured at 6px above the band
  and a margin either side.

  The band is now flush on all four edges. Verified: left, right and top gaps all 0, where they
  were previously non-zero.

- **The shade was too light.** It used `--acf-surface-sunken`, which is close enough to white that
  once the panel around it went white in 2.1.81, the band stopped reading as a band. Its own token
  now, a shade darker.

### Added

- **A setting for the course index header colour**, per course with a site default and a site-wide
  override, resolved in the same three steps as the accent colour and validated as a hex value the
  same way.

  Verified end to end: the default resolves to `rgb(236, 239, 244)`, and a course set to `#d8dfe8`
  renders `rgb(216, 223, 232)`.

## [2.1.84] - 2026-08-20

### Changed

- **The course index dividers are darker.** They used `--acf-border-subtle` at 8% alpha, which is
  right for a card edge -- reinforced by the card's own background and elevation -- but a bare line
  on white has nothing helping it and was barely visible.

  A dedicated `--acf-player-line` token at 16% now carries all four dividers in the panel: the
  header, the row separators, the section rules and the drawer's trailing edge. Kept separate
  rather than darkening `--acf-border-subtle` globally, which would have changed every card border
  in the plugin along with it. 20% in dark mode.

  One unrelated rule was caught by the same edit and reverted: the AI chat panel's rating divider
  happens to share the identical margin, padding and border shape, so a pattern-based replacement
  matched it too. It is back on `--acf-border-subtle`, and the token now appears only in
  `.aicourse-player-on` rules.

### Note

  The colours could not be re-measured in a browser for this change: the player sidebar stopped
  rendering in the development environment partway through the session, so the verification runs
  returned nothing rather than a wrong answer. The declarations were confirmed by reading the
  stylesheet instead -- four uses, all inside sidebar selectors, all resolving to the new token.
  That is weaker evidence than a measurement and is recorded as such.

## [2.1.83] - 2026-08-20

### Fixed

- **The first card sat against the banner when the course tab bar was hidden.** The gap between
  them was never actually stated -- it was whatever happened to sit in between, normally the tab
  bar. Hide that and the two ended up 1px apart. The spacing added for this in 2.1.75 lived inside
  the immersive-mode rules and went with them in 2.1.79.

  A margin on the banner does not work here: the banner is lifted out to `#page` while the cards
  stay inside `#region-main`, so they are not siblings and nothing of the banner's pushes the cards
  down. The spacing goes on the content instead, and only in the case where the tab bar is not
  there to provide it -- so a page still showing the tab bar keeps its existing spacing and nothing
  doubles up.

  Measured both ways: tab bar shown 104px, unchanged; tab bar hidden 1px before, **29px** now.

## [2.1.82] - 2026-08-20

### Added

- **Hover text on truncated names in the course index.** The sidebar is a fixed width and names are
  not, so a long section or activity title is cut off with an ellipsis and a learner has no way to
  read the rest. The full text is now attached as a title attribute, which also gives assistive
  technology the whole name rather than the visible fragment.

  Applied only when the text is genuinely clipped -- `scrollWidth` exceeding `clientWidth` is the
  browser reporting it directly, which beats guessing from character counts and means a short name
  never gets a tooltip repeating what is already on screen. A couple of pixels of slack, because
  sub-pixel text metrics report a 1px overflow on text that is not visibly cut and would otherwise
  put a tooltip on almost every row.

  Re-checked when the drawer changes size or its contents are rebuilt, since the same name can be
  clipped at one width and not another; a tooltip that is no longer needed is removed rather than
  left stale.

  Verified on a course with deliberately long names: 22 rows, 3 genuinely clipped, all 3 carrying
  the full text, and **no tooltips on the 19 that fit**.

## [2.1.81] - 2026-08-20

### Changed

- **The course index is white and flat.** The drawer, the index, the section headings and the
  activity rows all take the raised surface colour with no shadow, so the sidebar reads as part of
  the page rather than a tray laid over it. The drawer's own shadow, which comes from the theme, is
  cleared with them, and a single hairline on the trailing edge separates the panel from the
  content.

  The header keeps the sunken tint. That contrast is what makes the course name, progress ring and
  total time sit apart from the list beneath them -- with everything the same colour the panel has
  no top.

  Verified: drawer, drawer content, index, section titles and rows all `rgb(255, 255, 255)` with
  `box-shadow: none`, header `rgb(248, 250, 252)`.

## [2.1.80] - 2026-08-20

### Added

- **Hide the site logo band** -- a narrower replacement for the immersive mode removed in 2.1.79.

  Themes commonly render two bands above the page: a compact bar carrying notifications, the user
  menu and the Edit mode toggle, and beneath it a taller band holding the site logo and site links.
  theme_academi calls the second `.header-main`, and it costs well over a hundred pixels of
  vertical space on every course page.

  This hides the second band only. **The bar with the profile and the Edit mode toggle is never
  touched**, which is the whole difference from the previous version: nothing has to be moved into
  the sidebar to compensate, because nothing functional is being taken away. That also removes the
  need for the top-offset and drawer-height corrections the old setting required.

  Three states as usual, per course with a site default and a site-wide override, and the band
  always returns in edit mode.

  Verified across all three cases:

  | Setting | Logo band | Profile bar | Edit mode toggle |
  |---|---|---|---|
  | Show | shown | shown | shown |
  | Hide from everyone | **hidden** | **shown** | **shown** |
  | Hide from everyone, editing | shown | shown | shown |

## [2.1.79] - 2026-08-20

### Removed

- **Immersive mode.** The site header now always shows, and no setting in this plugin can hide it.

  It was added in 2.1.56 and caused more trouble than it was worth: the header carries the Edit
  mode toggle, notifications, messaging and the user menu, and hiding it meant compensating for
  each of those elsewhere. It also needed a run of theme-specific corrections -- the second header
  band, the reserved top offset, the drawer height -- none of which would have existed otherwise.

  Removed with it: the setting and its site default and override, the `is_immersive()` helper, the
  body class, and every stylesheet rule keyed to it. The four user icons it put in the sidebar went
  in 2.1.77.

  The Navigation tabs, footer and breadcrumb settings are untouched and still work independently.

  Verified with every remaining hiding setting turned up to "hide from everyone": the site header
  is shown, no `aicourse-immersive` class is emitted, and there are no references left anywhere in
  the plugin.

## [2.1.78] - 2026-08-20

### Changed

- **Immersive mode hides the site header and nothing else.** 2.1.70 folded the course tab bar into
  it, on the reasoning that both are page furniture. That was overreach: they are two different
  decisions, and bundling them meant one setting quietly did two things. The tab bar has its own
  setting -- Navigation tabs -- and that is where it belongs.

  Verified across all four combinations, each setting controlling exactly one thing:

  | Immersive | Navigation tabs | Site header | Course tab bar |
  |---|---|---|---|
  | Off | Show | shown | shown |
  | On | Show | hidden | **shown** |
  | Off | Hide from everyone | shown | hidden |
  | On | Hide from everyone | hidden | hidden |

## [2.1.77] - 2026-08-20

### Removed

- **The notifications, messages, profile and log-out icons from the course index sidebar.** They
  were added in 2.1.56 to replace what the site header carries when immersive mode hides it. On a
  site where the header always shows, they duplicate it.

  The sidebar header keeps the logo, course name, progress ring, total time and the three
  navigation links. Verified: no user bar rendered, the three nav icons and all 15 activity rows
  still present, no JavaScript errors.

  The unused strings, CSS and the URLs built for them are removed with the markup rather than left
  behind.

## [2.1.76] - 2026-08-20

### Fixed

- **The course index stopped short of the bottom of the window in immersive mode.** Core sizes the
  drawer around the fixed header -- `inset-block-start: 60px` with a height of `viewport - 60`.
  2.1.56 moved its top to 0 but never corrected the height, so the drawer kept its
  header-sized measurement and ended 60px above the bottom edge, leaving a strip of page showing
  beneath the sidebar.

  The drawer is now stated as the full viewport height, and the scrolling region inside it is sized
  at `calc(100% - 60px)` rather than 100%: `.drawerheader` is a real sibling above the content,
  carrying the close button and the menu, so the content's share is the drawer minus that header.
  Stating 100% made it 900px inside an 840px space and pushed the bottom 60px past the window --
  the same error in the opposite direction.

  Measured at a 900px viewport: drawer 0 to 900, content 60 to 900. Both end exactly at the bottom
  edge, neither overflows.

## [2.1.75] - 2026-08-20

### Fixed

- **A band of empty space survived above the banner in immersive mode.** The rule that clears the
  offset a fixed header reserves listed only `#page` and `.drawers`, which is where Boost keys it.
  A theme can hang that offset anywhere between the body and the content, and theme_academi
  reserves it further out -- so Boost measured clean at 12px from the top while that theme kept a
  visible gap.

  Every wrapper between the body and the page is now zeroed, along with the body's own padding and
  the scroll padding that assumes a sticky header is there to scroll under.

- **The first card sat 1px below the banner.** The course tab bar had been acting as the spacer
  between them, so hiding it in 2.1.70 left the two touching. The gap is now stated explicitly
  rather than inherited from whatever happens to sit between them.

  Measured before and after: hero bottom 131, first card top 132 -- a 1px gap -- now 192, a gap of
  61px.

## [2.1.74] - 2026-08-20

### Fixed

- **Collapsed sections had no divider between them.** 2.1.62 removed the rule above section
  headings because it doubled with the separator on the last activity row. That doubling was
  actually fixed in 2.1.66, when the last row in a section stopped drawing its own rule -- so the
  heading has been free to carry the line since then, and it needs to: with sections collapsed
  there are no rows at all, nothing was drawing a divider, and the list ran together.

  Every section heading now carries one, at the same weight and colour as the row separators, so
  the panel reads consistently whether sections are open or closed.

  Verified in both states: 7 sections, all 7 with a divider, `rgba(15, 23, 42, 0.08)` matching the
  rows, and zero doubled lines expanded or collapsed.

## [2.1.73] - 2026-08-20

### Fixed

- **The course tab bar is now matched by every hook it exposes, not just its outer wrapper.**

  Moodle renders it as `.secondary-navigation` wrapping `nav.moremenu.navigation` wrapping
  `ul.nav.more-nav.nav-tabs`, and that list carries an id of the form `moremenu-<hash>-nav-tabs`
  where the hash is regenerated on every page load -- so the id can only ever be matched on its
  prefix, never in full.

  Hiding the outer wrapper is sufficient when the whole structure is nested, which is why this
  tested clean repeatedly. It is not sufficient if a theme renders the nav or the list outside that
  wrapper, and that is the case that kept surviving.

  Both the immersive-mode rule and the Navigation tabs setting now match the wrapper, the nav, the
  `.moremenu` class, the `ul.more-nav`, and any element whose id begins `moremenu-`.

  Verified against three shapes, with immersive mode on: the fully nested structure, a
  `nav.moremenu` rendered with no `.secondary-navigation` around it, and a bare `<ul>` carrying
  only the hashed id. All five probes hidden -- the last two would have survived before.

## [2.1.72] - 2026-08-20

### Added

- **The tour narration is now shipped as audio.** 27 MP3 files in `pix/tour/`, generated with
  Google Cloud Text-to-Speech using `en-AU-Chirp3-HD-Charon` at a speaking rate of 0.96. About
  1.2 MB in total.

  The tour plays these instead of the browser's speech synthesis, which is what it fell back to
  while they did not exist. Any step whose file is missing still falls back individually, so a
  partial or translated set remains valid.

  Checked before installing: all 27 decode as valid audio, the filenames match the tour's step keys
  exactly with none missing and none extra, and the role variants are genuinely different
  recordings -- `t_welcome` and `s_welcome` differ, as do `t_done` and `s_done`. That last check
  matters, because a single shared `welcome.mp3` would have read the teacher's script to learners,
  which is the fault the role prefixes were introduced to prevent in 2.1.48.

  Verified playing: stepping through the tour fetched `t_welcome.mp3` and `t_banner.mp3` with
  HTTP 200 each, and `speechSynthesis.speaking` stayed false throughout -- the files are being
  used, not the fallback.

## [2.1.71] - 2026-08-20

### Fixed

- **Section headings drew a primary-coloured outline on hover, on three sides only.**
  theme_academi sets `border-color: $primary` on `.courseindex-section .courseindex-item:hover`,
  and the override added in 2.1.69 only reached rows carrying `.aicourse-player-row` -- a class
  added to ACTIVITY rows and never to section headings. So a heading kept the theme's primary
  border, and because the top border had already been zeroed it drew round the sides and bottom
  with nothing across the top.

  All four edges are now transparent on hover, so nothing is drawn at all, with a fallback rule for
  themes that do not wrap the index in `.drawercontent`.

  Verified with theme_academi's declarations active: every border resolves to `rgba(0, 0, 0, 0)`
  and the background to transparent. The heading text was separately confirmed to follow the course
  accent rather than the theme's hover colour -- with a magenta accent set it reads
  `rgb(181, 23, 158)`, not the theme's `#0f6cbf`.

## [2.1.70] - 2026-08-20

### Changed

- **Immersive mode now hides the course tab bar as well as the site header.** Course / Settings /
  Participants / Grades / Reports is page furniture in exactly the sense immersive mode exists to
  remove, and hiding the site header while leaving it behind produced a stranded strip of tabs at
  the top of an otherwise clean page.

  It was previously reachable only through the separate Navigation tabs setting, so asking for
  "maximum room for the content" gave you most of it and left one bar behind. The tab bar remains a
  course setting in its own right for sites that do not use immersive mode; immersive mode simply
  no longer needs it set as well.

  Verified with the Navigation tabs setting deliberately left on **Show**: with immersive mode on,
  the secondary navigation and the more-menu are both hidden and no `aicourse-hidenav` class is
  present at all, so the hiding is immersive mode's doing alone. Both return in edit mode.

### Checked, not changed

  theme_academi does not force `display` on `.secondary-navigation` -- its rules there only adjust
  `max-width` and borders -- so the existing Navigation tabs setting was not being blocked by the
  theme. Confirmed by reading that theme's `standard.scss` and `course.scss` rather than assuming
  it was another override.

## [2.1.69] - 2026-08-20

### Fixed

- **theme_academi has its own course index hover rules, and they were the real cause.** Read from
  that theme's `standard.scss` rather than guessed at, it sets inside `.drawercontent .courseindex`:

  - `.courseindex-item:hover` and `:hover a` -- white text on a solid `$primary` background
  - `.courseindex-section .courseindex-item:hover` -- `$primary` background and border
  - `.courseindex-sectioncontent .courseindex-item.pageitem` -- solid `$primary`

  That is the block of site colour on every hover, and on the current activity. Nothing written
  against core's selectors could reach it: the theme's rules are more specific and load later,
  which is why three previous attempts at this had no visible effect on that theme while testing
  clean on Boost every time.

  The overrides now match the theme's own selector shape, so they land at equal specificity and win
  on order. The row keeps the same faint wash used everywhere else, the text keeps its colour, and
  the current activity is a tint of the primary rather than a fill of it.

  Verified by injecting theme_academi's exact declarations and then hovering: the row computes to
  `rgba(15, 23, 42, 0.05)` with `rgb(30, 41, 59)` text, where the theme would have given a solid
  `#0f6cbf` with white.

### Note

  This is the fourth theme-specific override this plugin has needed, and every one was found by
  reading theme_academi's source rather than by testing on Boost. Any rule this format writes
  against core's course index, navigation or header should be checked against that theme's SCSS
  before it is called done.

## [2.1.68] - 2026-08-20

### Fixed

- **Core's course index hover was still recolouring the text.** Moodle sets
  `$courseindex-link-hover-color: black` and applies it to the row, the link and the chevron, so
  hovering jumped every label from the theme's body colour to pure `#000`. It flickered on every
  row, and in dark mode it is simply wrong -- text there should get lighter, not black.

  Earlier releases replaced the hover *background* but never the text, which is why the default
  behaviour appeared to survive.

  The label now holds its colour through a hover; only the background moves, a wash you can barely
  see. Section headings keep the accent rather than dropping to black.

  Verified by actually moving the pointer onto a row and reading the computed styles either side:
  before, the link went from `rgb(30, 41, 59)` to `rgb(0, 0, 0)`; now it stays at
  `rgb(30, 41, 59)` while the background alone changes.

## [2.1.67] - 2026-08-20

### Fixed

- **Immersive mode did not hide theme_academi's header.** The setting was on, the body class was
  present, the helper returned true -- and the header stayed. The selector was the problem: it
  matched only `fixed-top` variants, which is how Boost builds its header, but Academi renders
  **two** bands. A dark contact and user bar, and a separate white band carrying the logo and the
  site links. The second is not `fixed-top`, so it survived and the feature looked broken.

  The rule now matches `#header`, `#page-header`, `nav.navbar`, `header.navbar`, `.navbar.fixed-top`
  and `.moodle-based-header`, which covers both bands and every theme this format has been seen on.
  `!important` because a theme may set display on its own header at a specificity this cannot
  otherwise reach -- the same reason the secondary navigation needed it.

  Verified against an Academi-shaped second band that is deliberately not fixed-top: hidden with
  immersive on, and every band back in edit mode.

  Diagnosis order worth recording: the setting, the helper, and the body class were each confirmed
  working before the CSS was touched, which is what narrowed it to the selector rather than the
  feature.

## [2.1.66] - 2026-08-20

### Fixed

- **The doubled line at each section boundary.** The rule meant to drop the separator from the last
  row in a section had a space in its selector -- `li:last-child .aicourse-player-row` -- so it
  looked for a row nested *inside* the last list item, when the list item **is** the row. It
  matched nothing, which is why the doubling survived being fixed in 2.1.62.

  Written without the space it targets the element itself, and core's own section border is
  cleared alongside it so only one line is ever drawn where two sections meet.

  Verified by measuring every full-width rule in the panel and looking for any pair within 6px of
  each other: 20 dividers, **zero doubles**.

## [2.1.65] - 2026-08-20

### Fixed

- **The current activity in the course index was a solid block of the theme's primary colour.**
  Core sets `$courseindex-item-page-bg: $primary` at full strength, which on a dark navy paints a
  filled rectangle behind the activity icon and the completion tick -- it reads as a selection
  error rather than a highlight, and it is what made the panel look heavy.

  It is now an 8% tint of the same colour: the row is marked just as clearly and everything on it
  stays legible. Core also forces the row's text to the contrast colour it chose for a solid
  primary background, which against a tint would have been white on near-white, so that is
  returned to normal too.

  Both need `!important`, because core's declarations reach the same elements and cannot otherwise
  be outranked. The colour is still the theme's; only its weight has changed.

  Verified on an activity page: the current row computes to an 8% alpha tint with dark slate text,
  where it was previously a solid fill.

### Verified

- **Hiding the Course / Settings / Participants bar outside edit mode already works** and needed no
  change. With *Navigation tabs* set to "Hide from everyone" the bar is hidden in view mode and
  returns automatically in edit mode, which is the behaviour the `:not(.editing)` guards were
  written for. Confirmed by loading the same page in both modes.

## [2.1.64] - 2026-08-20

### Changed

- **Both tours reviewed against everything built since they were written.** Three features had no
  step at all, and two of them are things a learner uses constantly:

  - **The course player sidebar** -- the logo, progress ring, total time and per-activity rows.
  - **The grades button in the hero**, which both roles see and neither tour mentioned. The
    learner's version says plainly that they only ever see their own.
  - **The reporting dashboard**, for teachers: what learners asked the tutor, where they got
    stuck, and which activities generate the most questions.

  Teacher tour is now 16 steps, learner 11. `TOUR_VERSION` is bumped to 3, so anyone who has seen
  an earlier version is offered the fuller one once.

  Verified by walking the whole teacher tour: all 16 steps run, every arrow either on screen and
  clear of the card or deliberately hidden, no JavaScript errors.

- `pix/tour/narration.tsv` regenerated: 27 lines, about 4,800 characters.

## [2.1.63] - 2026-08-20

### Added

- **A logo upload for the course player sidebar**, separate from the site logo. A course player is
  often branded for a client rather than the institution hosting it, and until now the only way to
  put a logo in the sidebar was to change the whole site's.

  Under *Plugins ▸ Course formats ▸ AI Course Format*, accepting any web image format. It takes
  precedence over the site logo, which in turn falls back to the theme's, so a site that uploads
  nothing behaves exactly as before.

  The image is scaled to fit the header while keeping its aspect ratio, so an odd-sized upload
  cannot distort or overflow the panel. The URL carries the file's own revision, so replacing the
  image is picked up immediately rather than being served stale for a day.

  The file is served from the system context ahead of the existing course-scoped handler, which
  expects a course and would have rejected a site-level setting.

  Verified both directions: with a 300x80 upload in place the sidebar uses it -- served HTTP 200,
  rendered at 113x30 -- while the site logo remains configured and unused; with the upload removed
  it falls straight back to the site logo.

## [2.1.62] - 2026-08-20

### Changed

- **The accent lines are gone from the course index.** They read as decoration rather than
  structure, and stacked against core's own dividers they doubled up -- one line ours, one core's,
  a few pixels apart. The inset hover and current-page bars also drew rounded ends against the
  row's square edge.

  Every separator in the panel is now the theme's ordinary border colour: one hairline under the
  header, one under each row, and nothing of ours above the section headings, because core already
  separates sections.

  The accent stays where it labels something -- the section headings, the card headings, the
  activity icons -- rather than being drawn as furniture. The current page is marked with a tint
  and a heavier weight instead of a coloured bar.

  Verified: header and row separators both resolve to the theme border colour at 1px, no border
  above the section titles, and no box-shadow on any row.

## [2.1.61] - 2026-08-20

### Fixed

- **The accent was a hover effect, not a colour.** Card titles and section icons only took the
  course colour when the mouse was over them, so a course with a chosen accent looked identical to
  one without until you moved the pointer -- and on a touch screen, never. The title and the icon
  now carry it at rest. Hover still lifts the icon; it no longer recolours it, which is what made
  the colour feel like a hover state rather than the course's own.

- **The course index hover was a filled block of the site colour.** It painted the theme's primary
  across the whole row, behind the completion tick and the activity icon, which on a dark primary
  looked like a bruise rather than a highlight. Hover is now a 3px leading edge and a barely-there
  wash -- the same shape the current-page marker uses, so hover reads as a lighter version of
  selection rather than a different idea.

- **The sidebar header was a white card inside a white panel.** It is now a full-width band with a
  rule beneath it in the accent, matching the rows below.

### Added

- **The accent colour is now a site setting**, with both a default and a force. Previously it
  existed only per course, so a site wanting one palette had to set it course by course and
  remember again on every new one. The default seeds courses that have not chosen; the force
  overrides every course at once.

  Verified with the course's own accent left empty and the site default set: the body token, card
  titles at rest, and the course index headings all resolve to the site colour.

## [2.1.61] - 2026-08-20

### Changed

- **The course index scrolls independently of the page, and its scrollbar is hidden.** Two
  problems that look like one.

  The drawer's scrollers ship with `overscroll-behavior: auto`, so a wheel gesture that reached the
  top or bottom of the index carried on into the page behind it -- the reader is looking at the
  sidebar and the course jumps. `contain` stops the gesture at the boundary, so the two scroll
  independently, which is what a panel pinned beside the content should do.

  The scrollbar is hidden **visually only**. The panel still scrolls by wheel, trackpad, touch,
  Page Up and Down, and the arrow keys, and every row stays reachable. Nothing here sets
  `overflow: hidden`, which would genuinely trap the content and make the rows below the fold
  unreachable for a keyboard user.

  Verified on the scrolling element: `overscroll-behavior: contain`, `scrollbar-width: none`, and
  the scrollbar occupying 0px of width rather than being merely transparent.

## [2.1.60] - 2026-08-20

### Changed

- **The course index is a list again, not a stack of cards.** 2.1.53 gave the rows the activity
  card's treatment -- border, radius, elevation, a status bar on the leading edge -- which is right
  for a card floating on a page and wrong inside a 280px panel. Fifteen bordered rectangles nested
  in a bordered panel is a box in a box in a box, and the eye spends its effort on the containers
  instead of the list.

  Rows now run edge to edge, separated by a hairline and nothing else. No radius, no shadow, no
  leading bar. The card vocabulary stays where it belongs, on the cards. The last row in a section
  drops its rule, because the section divider below already separates them.

  The header is squared off to sit on the panel edge for the same reason, with the accent carried
  as a rule beneath it rather than a border around it.

  The accent is now where you suggested: the **section headings** and the **activity icons**. The
  icons are masked so a monochrome module icon takes the course colour rather than staying stock
  grey. Core's completion circle is gone -- it duplicated the tick at the end of the row.

  The current page keeps one marker, an inset accent edge, because that is the one row that should
  assert itself over its neighbours.

  Verified: 15 rows, radius 0, no shadow, a 1px separator, 15 icons present, and zero core
  completion circles.

### Known

- Rows measure 269px inside a 272px panel -- roughly 1.5px of inset each side that survives from a
  core wrapper. Three attempts at tracing it did not find the source, and at that size it is not
  visible. Recorded rather than left as a silent imperfection.

## [2.1.59] - 2026-08-20

### Changed

- **The course accent now shows in the sidebar at rest, not only on hover.** It reached the panel
  in 2.1.54, but was only ever used by hover and active states -- the row hover border, the
  current-page edge, the icon tile tint -- so a course with a custom colour looked identical to one
  without until the mouse moved. A per-course accent nobody can see until they interact is not
  doing its job.

  It now carries the three things that structure the panel: the course name at the top, each
  section heading, and the rule dividing one section from the next. Those are the parts a reader
  uses to orient, which is where a course's own colour belongs. The activity rows stay neutral, so
  the accent marks structure rather than shouting over the content.

  The first heading gets no rule above it, since there is nothing to divide it from.

  Verified with an unmistakable accent: course name, section headings and the section divider all
  resolve to the course colour, while the activity row text stays at the normal body colour.

## [2.1.58] - 2026-08-20

### Changed

- **The course index now shows each activity's own icon at the head of its row**, in place of
  core's completion circle.

  The circle duplicated the tick the row already carries at its end, and told a learner nothing
  about what the activity IS. The icon does: a quiz, a page and an assignment are distinguishable
  at a glance without reading, which is the point of an index you scan rather than read. It sits
  in the same rounded tile the activity cards use, so the two read as one object at two sizes.

  Core's circle is hidden with `!important`, which the sheet reserves for a core rule that cannot
  otherwise be reached. It was measured at 24x0 -- invisible, but still holding 24px at the head of
  every row, which left the icons indented against nothing.

  Verified: 15 rows, all 15 carrying an icon the browser reports as loaded, and no core circles
  left visible.

## [2.1.57] - 2026-08-20

### Fixed

- **The sidebar showed no site logo on themes that set their own.** The lookup only asked core, and
  a site whose logo is configured in the THEME's settings rather than Moodle's -- which is how
  theme_academi and most commercial themes do it -- got nothing, while the site header two inches
  above the sidebar showed one.

  Four sources are tried in order of authority: core's compact logo, core's full logo, the theme's
  own logo file settings under the names themes conventionally use, and finally a theme's bespoke
  helper function, which is how theme_academi exposes it. Each is wrapped, because a theme may
  define any of these differently and a fatal here would take down a page over a decoration. A
  site with no logo anywhere renders the header without one rather than a broken image.

  Verified rendering end to end: with a logo configured, the sidebar header shows it at 120x30 and
  the browser reports it loaded.

## [2.1.56] - 2026-08-20

### Added

- **Immersive mode.** Hides the site header on this format's pages, giving the learning content the
  full height of the screen. Three states, per course with a site default and a site-wide override.

  This is the piece that stayed unbuilt while the rest of the sidebar was written, and the reason
  was worth resolving rather than ignoring: the site header carries notifications, messaging, the
  user menu and log out, and Moodle's accessibility conformance assumes those are reachable.
  Hiding it and giving nothing back would have taken four things away and replaced three.

  So when immersive mode is on, the player sidebar's header grows a compact set of the same
  links -- notifications, messages, profile and log out -- built server-side because the log-out
  URL needs a sesskey and the profile URL needs the user id. Nothing is lost; it moves. **The
  player sidebar should be switched on alongside it**, which the setting's description says
  plainly, or those links have nowhere to go.

  The header always returns in edit mode: a teacher arranging a course needs the full page
  furniture.

  Targets Boost's `nav.navbar.fixed-top` and theme_academi's `#header.fixed-top`, checked against
  both templates. The offset a fixed header reserves is cleared with it, or the space it held
  would remain as a gap.

  Verified: with immersive on the navbar and page header are hidden and the hero sits at 12px from
  the top instead of 72px, all four user links are present in the sidebar, and with editing on the
  header returns while the sidebar keeps its links.

## [2.1.55] - 2026-08-20

### Added

- **Course index on first entry.** Moodle opens the course index by default and then remembers
  whatever the user last chose, site-wide. On a format that already carries the course in its
  banner and its cards, a drawer covering a third of the screen on arrival is often not what a
  teacher wants their learners to meet first.

  Three choices, per course with a site default and a site-wide override: **Remember the user's
  choice** (Moodle's own behaviour, the shipped default), **Start collapsed**, and **Start open**.

  It writes core's own `drawer-open-index` preference server-side, so the drawer renders in the
  chosen state with no flash of it opening and then closing.

  **Applied once per user per course.** After that the user's own toggle is respected, because a
  setting that reimposed itself on every page load would be fighting the person using it. The
  visit is marked whichever way it goes, so "leave it alone" is also decided only once and a later
  change to the setting does not reach back into courses people have already formed a preference
  in.

  Verified across all three settings: collapsed starts collapsed, open starts open, remember
  leaves Moodle's behaviour alone — and in every case a user who then toggles the drawer keeps
  their choice on the next visit.

## [2.1.54] - 2026-08-20

### Fixed

- **The per-course accent colour only ever tinted the hero banner.** It was written as an inline
  style on the hero element, so its custom properties were scoped to the hero's own subtree.
  Everything else the format draws -- section cards, activity cards, the focus ring, the tour, the
  chat panel, and now the player sidebar -- lives elsewhere in the DOM and never saw it, falling
  back to the theme's primary instead. A per-course accent that tints one banner is not really a
  per-course accent.

  It is now published on `<body>`, so every part of the format inherits it. The hero keeps its
  inline copy, which still wins for the hero itself, so the banner is unchanged.

  Two details this took to get right, both found by measuring rather than assuming:

  - Emitting it from the usual place did nothing on course pages, because the footer hook returns
    early on `course-view-aicourse`. It goes above that return, alongside the tour and the sidebar.
  - Setting it on `<html>` also did nothing: the stylesheet declares `--acf-brand` on
    `body.format-aicourse`, deriving it from the theme's primary, and a declaration on body beats
    a value inherited from html. An inline style on body outranks the stylesheet rule on the same
    element, which is what makes the course's colour actually win.

  Verified with a deliberately unmistakable accent: the hero, the sidebar header, the sidebar rows
  and the section cards all resolve to the course colour, where before only the hero did.

  The value is validated server-side as a hex colour, and the JavaScript accepts only
  `--acf-`-prefixed property names as a second gate.

## [2.1.53] - 2026-08-20

### Changed

- **The player sidebar is now built from the same visual vocabulary as the rest of the format.**
  The first version used the right design tokens but not the established shapes, so it read as a
  different product sitting next to the course rather than part of it.

  An activity card is a raised surface with a subtle border, a large radius, one step of elevation
  and a 3px status bar down its leading edge, and its duration is a bordered pill marked with a
  clock. A sidebar row is now the same object at sidebar scale, carrying the same clock pill, so
  the eye moves between the two without re-learning anything.

  The leading edge uses the same status tokens the cards do -- todo, done, and the brand tint for
  the current page -- so completion reads identically wherever it appears. The header takes the
  hero's treatment: same raised surface, same radius family, same single step of elevation.

  The completion marker still differs in shape as well as colour, and the green is the same
  `--acf-status-done-line` the cards and the hero ring use, rather than a second green chosen for
  the sidebar.

## [2.1.52] - 2026-08-20

### Added

- **A course player sidebar.** Moodle's course index is a list of links: no completion state, no
  duration, nothing a learner can plan with. This turns it into a player-style sidebar.

  A header carrying the site logo, the course name, a progress ring, the total estimated time and
  links back to Home, Dashboard and My courses. Then a row per activity showing its estimated
  duration and, where completion is tracked, a tick.

  **It decorates core's drawer rather than replacing it.** Replacing it would mean reimplementing
  the collapse behaviour, the drag and drop while editing, and the JavaScript that keeps the index
  in step with the page -- all of which keep working untouched. A `MutationObserver` puts the
  decoration back when core rebuilds part of the tree, which it does as sections collapse and as
  completion changes.

  Every figure comes from the same helpers the section cards and activity pills use, so the sidebar
  cannot disagree with the page beside it. The percentage counts only tracked activities, matching
  the hero ring: a course tracking nothing shows no percentage rather than a permanent zero.

  The completion marker differs in **shape** as well as colour -- an outlined circle when
  incomplete, a filled circle with a check when done -- because colour alone would fail WCAG 1.4.1.

  Off by default, with a per-course setting, a site default and a site-wide override.

  Verified rendered: header present with the course name, ring and total time, three nav links, and
  15 activity rows each carrying a duration, with ticks on the tracked ones. No JavaScript errors.

## [2.1.51] - 2026-08-20

### Fixed

- **Clicking "Generate AI banner image" opened the AI Tutor.** The tutor bound its toggle to
  `'.aicourse-ai-toggle, .aicourse-hero-ai-btn'`, and the second half was wrong:
  `.aicourse-hero-ai-btn` is the shared *layout* class on every round button in the hero pill, so
  it matched the generate button too and the tutor opened on top of the generation dialogue. Only
  the button that is the tutor toggle now opens the tutor.

- **The generate button was announced as controlling the tutor panel.** The same mistaken selector
  drove `aria-expanded`, so a screen reader was told the generate button expanded and collapsed a
  panel it has nothing to do with. Now set only on the tutor's own button — verified as `false` on
  the tutor and absent on generate.

### Added

- **The tutor introduces itself once, on a user's first visit to a course.** A round icon in a
  banner is easy to miss, and a tutor nobody opens is a tutor nobody benefits from. Opening the
  panel itself rather than showing a notice about it means they can simply start typing.

  Once per user per course, recorded in a user preference. Never while editing, since a teacher
  arranging a course does not want a chat panel opening over it, and never on top of the first-run
  tour, which is doing the same introducing job more thoroughly.

  Verified: opens on the first visit, stays closed on the second, and the generate button opens the
  banner dialogue rather than the tutor.

### Note

  The preference key is registered as a delimited regular expression. Core runs registered keys
  through `preg_match()`, so a bare wildcard string silently matches nothing: the write is
  rejected and the tutor reintroduces itself on every visit. That is exactly what happened on the
  first attempt here.

## [2.1.50] - 2026-08-20

### Added

- **Site footer setting.** On a course page the footer is the last thing a learner needs and the
  first thing between them and the bottom of the content. Same three states and the same site-wide
  override as the navigation and breadcrumb settings: Show, Hide from students, Hide from everyone.
  Per-course under *Course settings*, with a site default and an override under *Plugins ▸ Course
  formats ▸ AI Course Format*.

  Targets core's own `<footer id="page-footer">`, which theme_academi also uses — checked against
  both themes' templates rather than guessed, after a selector written from assumption once hid
  that theme's entire site navigation.

  **The editing toolbar is deliberately not matched.** `.stickyfooter` is a separate element
  carrying Move, Duplicate and Delete during editing, and hiding it would take those away from a
  teacher mid-edit. Verified: with the footer set to hide, the footer computes `display: none`
  while the sticky toolbar stays `display: block`, in view mode and in edit mode.

## [2.1.49] - 2026-08-20

### Changed

- **The tour now points at what it is describing.** A ring tells you where to look but not what
  the card is talking about, and on a busy page -- a course index full of links, a row of cards --
  the connection between the two was not obvious. An arrow now flies in on each step and nudges
  toward the highlighted element on a loop, so the eye keeps being led back to it. The ring itself
  pulses rather than sitting as a static border.

  Placing it took three passes, each caught by measuring rather than assuming:

  - It is placed between the card and the target, on whichever axis separates them most.
  - A target that fills the viewport -- the course index runs nearly the full height -- leaves no
    room on the preferred side, and the first version pushed the arrow off-screen. It now tries
    all four sides, then the corners.
  - When the card sits close to the target the arrow landed *underneath the card* and was
    invisible for the whole step. Overlapping the card is now a disqualifier, not just being
    off-screen.

  If no position works at all, the arrow hides and the ring carries the highlight alone rather
  than pointing from somewhere the user cannot see.

  Verified across every step of both tours: all 13 teacher steps place the arrow on screen and
  clear of the card, or hide it deliberately.

- **Both tours cover considerably more.** The teacher tour goes from 8 steps to 13, adding the
  progress ring, the course index, section icons, estimated times and what the section card lists.
  The learner tour goes from 5 to 9, adding the ring, the course index, how long activities take
  and what the completion markers mean.

  `TOUR_VERSION` is bumped, so anyone who has already seen the shorter tour is offered the fuller
  one once.

### Note on narration

  The voice will be the browser's own speech synthesis until the Chirp HD files are generated --
  that is what the fallback sounds like. `pix/tour/generate.sh` produces all 22 of them in one
  command. Google Text-to-Speech cannot be reached from the development sandbox, so those files
  cannot be shipped pre-made.

## [2.1.48] - 2026-08-20

### Fixed

- **The two tours shared step keys, so learners would have heard the teacher's narration.** Both
  tours have a `welcome` and a `done` step and the audio file is named after the key, so a single
  `welcome.mp3` would have read the teacher's script to a learner. Keys are now role-prefixed
  (`t_welcome`, `s_welcome`), giving thirteen distinct files.

### Added

- **`pix/tour/generate.sh`** — produces the whole narration set as Google Chirp 3 HD audio in one
  command, defaulting to `en-AU-Chirp3-HD-Charon`. Accepts a different voice, and step names to
  regenerate only what changed after an edit.
- **`pix/tour/narration.tsv`** — the exact narration text, extracted from the language strings, one
  `key<TAB>text` pair per line. Regenerate it from translated strings before generating audio for
  another language.

  Verified that the tour uses the files: with a real MP3 present the browser requested
  `t_welcome.mp3`, received HTTP 200 and played it rather than falling back to speech synthesis.
  The whole script is about 2,000 characters, so generating it costs a fraction of a cent, once.

## [2.1.47] - 2026-08-20

### Added

- **An estimated duration pill on every activity card**, so a learner can see what an activity
  will cost them before opening it rather than only seeing a section total.

  It uses the same figure the section card's total is built from — a teacher's override first, then
  the site default for that activity type, with a quiz calculated from its question count — so the
  parts visibly add up to the whole instead of being two separate guesses. An activity a teacher
  has set to 0 renders no pill at all.

  Placed between the module type and the status badge, so the footer reads "what it is, how long it
  takes, where you are with it". Deliberately quieter than the status badge: the badge is about the
  learner and changes as they work, while the duration is a fixed property of the activity, and
  giving them equal weight made the row read as two competing claims.

  The visible text is short — "10 min" — with the fuller "Estimated time: 10 min" carried in an
  `accesshide` span and the `title`, so a screen reader does not announce a bare number stripped of
  what it measures.

  Verified on a real section: Page 10 min, URL 3 min, Assignment 30 min, Book 20 min, and a
  seven-question Quiz correctly reporting 7 min from its question count rather than a flat default.

## [2.1.46] - 2026-08-20

### Added

- **Estimated activity durations are now configurable, and per-activity.** The time badge on a
  section card is the sum of its activities' estimates. Those figures used to be a hardcoded list
  in `progress::estimate_activity_minutes()` that no site could change.

  Three sources, most specific first:

  1. **A teacher's override for one activity** — stored per course module and always wins,
     including a deliberate `0`, which hides the badge rather than falling back.
  2. **Site defaults per activity type**, under *Plugins ▸ Course formats ▸ AI Course Format*, as
     one `modulename=minutes` pair per line. A list rather than fixed fields so a site running
     third-party modules can give those a figure too, instead of every one landing on the catch-all.
  3. **Quizzes are calculated from their question count**, not a flat default. "A quiz" is not a
     duration: a five-question check and a fifty-question exam differ by an order of magnitude and
     one number is wrong for both. Random slots count as one question each, since that is what the
     learner answers. A quiz with no questions falls back to the type default.

  Shipped defaults match the previous hardcoded figures, so upgrading changes nothing until an
  administrator decides otherwise.

  New table `format_aicourse_actminutes`, removed with its activity on `course_module_deleted` and
  with its course on `course_deleted` — a course module id is eventually reused, so a row left
  behind would one day attach a stale estimate to an unrelated activity. Declared in the privacy
  provider for its `usermodified` field.

  New web service `format_aicourse_set_activity_minutes`, requiring `moodle/course:update`.

  Verified: defaults read from settings and change the estimate when edited; a quiz of seven
  questions reports 7, 14 and 35 minutes at one, two and five minutes per question; an override
  beats the default; `0` hides the badge; clearing returns to the default; and out-of-range input
  is rejected.

### Fixed

- The override lookup cached per request in a function static, which could not be cleared, so the
  save endpoint reported the value from *before* the change — a teacher who cleared an override was
  told the old number still applied. Moved to a class property with a working purge.

## [2.1.45] - 2026-08-20

### Fixed

- **The activity hero showed an empty completion ring for activities that do not track completion
  at all.** `hascompletion` was computed in `activityhero.php` and used to decide whether to render
  the manual toggle, but it was never exported to the template — so the non-toggle branch had
  nothing to test against. Any activity whose completion was switched off fell through to the
  ring's pending state and showed the learner an empty circle, which reads as "you still have this
  to do" for something that will never be marked complete.

  The value is now exported and the ring block is guarded on it, so an untracked activity renders
  no indicator at all.

### Verified

  Completion wiring was checked against live changes rather than by reading the code, as a learner,
  reloading the activity page between each change:

  | Change made | Toggle | aria-pressed | Ring |
  |---|---|---|---|
  | Manual completion, not yet done | shown | false | pending |
  | Learner marks it complete | shown | true | complete |
  | Teacher switches it to automatic | hidden | — | complete |
  | Teacher turns completion off | hidden | — | **none** |
  | Teacher turns it back on as manual | shown | true | complete |

  Separately, at the data layer: enabling and disabling completion at course level, switching
  between manual and automatic tracking, and marking complete and incomplete all report correctly.
  The helper's per-request static cache is keyed on course and user and does not persist between
  requests, so a change made by a teacher is visible to the next page load with no purge needed —
  confirmed by running the check in a fresh process after each change.

## [2.1.44] - 2026-08-20

### Fixed

- **The banner sat inset from the cards when the course index drawer was open.** The alignment
  measurement ran once at load and then only on a window resize — but the commonest thing that
  changes the content width on this format is not a window resize at all. Opening or closing the
  course index drawer narrows the content column by around 300px while the window stays exactly
  the same size, so the resize listener never fired and the banner kept padding calculated for the
  width the column used to have.

  A `ResizeObserver` now watches the content column itself, which catches every cause at once: the
  drawer, a block drawer, a late web font, a responsive breakpoint, a theme animating its layout.
  It is debounced, because a drawer transition fires it on every frame. Browsers without
  `ResizeObserver` fall back to re-measuring shortly after a drawer toggle is clicked.

  Verified by changing the content width by 300px with no window resize — the exact scenario the
  old code missed: aligned before, aligned with the column narrowed, aligned again when it
  widened, and aligned after a genuine window resize. Δleft and Δright 0 at every step.

## [2.1.43] - 2026-08-20

### Added

- **A narrated first-run tour**, offered once to each user and never again after it is finished or
  dismissed.

  **Two tours, not one.** A teacher and a learner are looking at genuinely different pages — the
  learner has no editing controls, no report, and by default no secondary navigation — so a shared
  script would spend half its time explaining things one of them cannot see. Which tour runs is
  decided server-side by `moodle/course:update`.

  The teacher tour covers the banner, generating a banner image, section cards, the AI Tutor,
  where the settings live, and — the step worth having — **that a learner sees a substantially
  different page, and to use Switch role to Student before judging the layout**. The learner tour
  is shorter: progress, section cards, and how to ask the tutor.

  **It spotlights the real page**, not screenshots, so what is described is the course actually in
  front of the user. A step whose target is not present is dropped rather than shown pointing at
  nothing: a course with no banner, or a site with the tutor switched off, simply gets a shorter
  tour.

  Keyboard operable throughout — arrows move, Escape leaves, focus is trapped in the card — with
  `role="dialog"`, `aria-modal`, and 44px controls.

### Narration

  Every line is a language string, so the tour is translatable and its words are not buried in
  JavaScript.

  Two providers, tried in order. If an MP3 named after the step exists in `pix/tour/` it is played;
  otherwise the browser's own speech synthesis reads the same text, preferring an Australian
  English voice. That means narration works immediately on any site, and a site that wants a
  specific voice — a Google Chirp 3 HD voice, for instance — supplies audio files instead.

  Generating the audio once rather than synthesising it per page view is deliberate: the narration
  text never changes, so live synthesis would mean a per-character API charge on every page load,
  latency before each step could speak, and a dependency on a third service being reachable.
  `pix/tour/README.md` gives the `gcloud` command and the voice name.

  Narration never starts without a user gesture — browsers block that, and a tour that silently
  fails to speak is worse than one that waits to be asked. Mute is one click, and the choice is
  remembered.

### Fixed

- The footer hook returned early on `course-view-aicourse`, so anything added after that point
  never ran on the course page itself. The tour launch sits above it.

## [2.1.42] - 2026-08-20

### Changed

- **Banner generation now runs in the background instead of inside the web request.** This is the
  fix for the 504 that three earlier releases failed to solve by raising timeouts.

  Generation takes about 110 seconds. Done inline, that request had to outlive every intermediary
  between the browser and PHP, and the shortest timeout in the chain wins: a reverse proxy at 60s,
  Cloudflare's fixed 100s on its lower tiers, PHP-FPM's `request_terminate_timeout` at 30s. On a
  site behind one of those the connection was severed **upstream of PHP** and the browser was
  handed a 504 — for a generation that was in fact succeeding on the server and spending credits.
  Raising this plugin's own cURL timeout, which 2.1.10 and later did twice, could never have
  helped: the connection was already gone before PHP finished.

  `generate_banner_image` now queues a `\format_aicourse\task\generate_banner` adhoc task and
  returns immediately. The task does the waiting, with no browser attached. The page polls the new
  `get_banner_status` web service every four seconds — a single config read, no remote call, no
  credits — until the task reports `done` or `failed`. No request in the chain is ever long enough
  to be cut, whatever the hosting stack does.

  The generation itself is untouched: the task calls the same routine the synchronous path used,
  so the request shape, the credit cost and the validation of the response are identical.

  Two consequences worth knowing. The teacher can now navigate away while it runs and the banner
  will be there when it finishes. And a failure is recorded rather than retried, because Moodle's
  automatic retry would spend credits repeatedly on a request nobody is waiting for.

  Verified end to end: `execute()` returns `{"status":"queued"}` immediately and registers the
  task with the right custom data; the task runs to completion under Moodle's own adhoc runner and
  dequeues itself; and `get_banner_status` correctly reports `queued`, then `failed` carrying the
  service's own message, then `done` with the image URL.

### Added

- `format_aicourse_get_banner_status` web service, and the `taskgeneratebanner` task name string.

## [2.1.41] - 2026-08-20

### Fixed

- **The banner loaded at the right width, then jumped narrower about a second later.** Two faults
  in the alignment measurement added in 2.1.37, both mine.

  First, it compared the content column against the banner's PARENT element. That is only correct
  when the banner starts at its parent's content edge — and it usually does not, because the
  banner carries its own `max-inline-size` and auto margins and is centred inside a wider parent.
  The offset therefore included the centring the browser had already applied, the padding
  double-counted it, and the banner ended up far narrower than the cards. It now measures from the
  banner's own edges, which cannot double-count, and zeroes the offsets before measuring so
  repeated runs do not compound.

  Second, it aligned to the container's BORDER box. The cards sit inside that container's own
  gutter padding, so the two looked level while the cards were still a gutter further in. It now
  targets the container's content box — where the cards actually are.

  The measurement also runs on an animation frame rather than immediately, so a correction happens
  before the first paint instead of being seen as a jump.

  Verified on the course page in both grid and list layout and on a section page, at 1920, 1400,
  1024, 768 and 390px — banner and card edges match exactly, Δleft and Δright both 0 — and
  separately against a deliberately widened parent, which is the case that produced the fault.

## [2.1.40] - 2026-08-20

### Fixed

- **`[[heroimageoverlay_desc]]` appeared on the settings page.** The string was never defined. It
  now exists, and gives the numeric guidance the field was missing: what 0-25, 30-40, 45-55, 60-75
  and 80-100 each look like, plus the fact that the overlay has to reach about **54** to guarantee
  readable white text over a worst-case white image.

- **Changing the site-wide overlay opacity appeared to do nothing.** It was working exactly as
  written — `defaultheroimageoverlay` is a DEFAULT, and a default only seeds new courses. Any
  course that has ever saved its settings keeps its own value and ignores it, which is why the
  banner never changed. Measured to confirm the mechanism itself is sound: setting a course to 0
  gives `rgba(2, 6, 23, 0)`, 20 gives `0.2`, 90 gives `0.9`.

  Added **Force overlay opacity on all courses**, alongside the default and matching the pattern
  already used for the navigation setting. Unlike the default, it applies to existing courses. -1
  leaves each course alone and is the shipped value.

- **The hero title was crushed by the numbered circle strip.** On a section hero the progress
  cluster carries one circle per activity; with eleven activities it took as much width as it
  wanted and the title wrapped into a column a few characters across. The title now claims a
  guaranteed share of the row and the strip gives way, shrinking and then scrolling horizontally
  rather than pushing the heading aside. The circles are a secondary navigation aid; the section
  name is the heading.

## [2.1.39] - 2026-08-20

### Fixed

- **The breadcrumb setting would have hidden the entire site navigation on theme_academi.** The
  selector added in 2.1.36 included `.navbar-nav`. That is a Bootstrap utility class, not a
  breadcrumb class, and themes use it for the site navigation: theme_academi's `navbar.mustache`
  applies it four times — to the bar holding the logo, the Home / Dashboard / My courses links,
  and the user menu. Turning the setting on would have removed the whole top navigation on that
  theme.

  The selector now targets core's actual breadcrumb markup only. `lib/templates/navbar.mustache`
  emits `<nav aria-label="Breadcrumb"><ol class="breadcrumb">`, wrapped by `full_header.mustache`
  in `#page-navbar`; `nav:has(> .breadcrumb)` replaces the guesswork by matching only a `<nav>`
  that actually contains a breadcrumb list, whatever a theme labels it.

  Verified against both markups side by side: with the setting on, the breadcrumb and its `<nav>`
  are hidden while an Academi-style `.navbar-nav` site navigation remains visible.

## [2.1.38] - 2026-08-20

### Fixed

- **Short section cards were padded out to match the tallest card in their row.** The grid used
  `align-items: stretch`, which is right when cards hold comparable content. Since 2.1.25 the
  activity list is uncapped by default, so a section with thirteen activities sits beside one with
  none — and the shorter cards were stretched to match it. Measured: cards **589px tall around
  78px of content, leaving 511px of empty card**.

  The grid now sizes each card to its own content. That trades a level bottom edge for cards that
  are the size of what is in them; the same measurement afterwards shows 34px of slack instead of
  511px. The footer keeps its `margin-block-start: auto`, so where cards do share a row height it
  still sits on the bottom edge.

## [2.1.37] - 2026-08-20

### Fixed

- **The hero banner was wider than the cards beneath it.** Moving the banner to the top of the
  page — the `heroattop` behaviour — makes it a direct child of `#page`, which is what puts it
  above the page furniture. It also takes it out of the box the content lives in: the cards sit
  inside `#region-main` and inherit that column's padding, while the banner now sits in the page's
  wider box. The two had different inline edges as a result. Measured on Boost the gap was **23px
  a side** at desktop widths and 8px on a phone; on other themes it differs, because it is the sum
  of whatever wrappers that theme puts around its content column.

  There is no CSS expression for "the left edge of that other element", and the offset is not a
  constant that could be hard-coded, so `heroatop.js` now measures the content column once it has
  moved the banner and publishes the two offsets as custom properties. The stylesheet consumes
  them as inline padding, defaulting to `0px` — a page where the JS has not run, or a theme where
  the banner already shares the content's box, is unchanged. Re-measured on resize, since the
  column width is itself responsive, and clamped at zero so a theme with a wider column can never
  pull the banner outward.

  Verified on the course page and a section page at 1400, 1024, 768 and 390px: banner and card
  grid edges match exactly, Δleft and Δright both 0.

- **`amd/build/heroatop.min.js` had no source map** and was hand-written rather than generated —
  flagged in an earlier audit and now resolved as a side effect of rebuilding the module. All 11
  AMD modules now build from source with maps that match.

## [2.1.36] - 2026-08-20

### Added

- **Breadcrumb trail setting.** The activity hero already names the course, the section and the
  activity, so Moodle's breadcrumb repeats all three directly beneath it. It can now be hidden,
  using the same three states and the same site-wide override as the existing tab setting:

  - **Show** — leave it exactly as the theme renders it (the default; nothing changes on upgrade).
  - **Hide from students** — teachers and anyone with `moodle/course:update` keep it.
  - **Hide from everyone** — nobody sees it outside edit mode.

  Per-course under *Course settings*, with a site default and a site-wide override under *Plugins
  ▸ Course formats ▸ AI Course Format*, matching how `hidesecondarynav` already works.

  This hides a trail; it is not an access control. Every destination stays reachable by its own
  link and its own capability check, and the breadcrumb always returns while editing, because a
  teacher rearranging a course needs every navigational handle the page has.

  Verified in a browser across all three states: no class and visible at Show; class applied but
  still visible for a user who can edit at Hide-from-students; `display: none` at Hide-from-
  everyone; and restored in edit mode with Hide-from-everyone still set.

## [2.1.35] - 2026-08-20

### Changed

- **The focus ring now uses the site's own primary colour instead of a fixed blue.** It was pinned
  to `#1d4ed8`, so every site got the same stock blue outline on cards, buttons and the icon
  picker — a colour belonging to no theme, which is why it read as a stray rather than a choice.

  The original reasoning was sound and is preserved: a site whose primary is pale would otherwise
  ship an invisible focus indicator, and WCAG 1.4.11 requires 3:1 for one. Rather than solve that
  by discarding the theme's colour, visibility is now carried by a third band. The ring is drawn
  as halo (surface colour) → brand ring → backing band, where the backing is near-black on light
  surfaces and near-white on dark ones. Whatever the primary, at least one band contrasts with the
  surface behind it.

  Measured in a browser against four primaries and both colour modes — including the two cases the
  fixed blue existed to protect against:

  | Primary | Mode | Brand band | Backing band | Indicator |
  |---|---|---|---|---|
  | `#194866` (navy) | light | 9.72:1 | 4.53:1 | **PASS** |
  | `#194866` | dark | 1.50:1 | 7.02:1 | **PASS** |
  | `#0f6cbf` (Boost) | light | 5.36:1 | 4.53:1 | **PASS** |
  | `#0f6cbf` | dark | 2.73:1 | 7.02:1 | **PASS** |
  | `#f5e663` (pale) | light | 1.28:1 | 4.53:1 | **PASS** |
  | `#f5e663` | dark | 11.41:1 | 7.02:1 | **PASS** |
  | `#ffffff` (white) | light | 1.00:1 | 4.53:1 | **PASS** |
  | `#ffffff` | dark | 14.63:1 | 7.02:1 | **PASS** |

  Dark mode previously swapped the ring to a different blue as well; it now keeps the brand hue and
  changes only the halo and backing, so the ring does not revert to a stock colour when the page
  goes dark. `--acf-focus-color` remains a token, so a site wanting the old fixed blue can set it.

## [2.1.34] - 2026-08-20

Ports the outstanding fixes from the parallel 2.1.24 line onto this branch, and sorts the
language file.

### Fixed

- **The AI Tutor could not see any label activity.** `contentindex::get_course_content_for_ai()`
  filtered with `if (!$cm->uservisible || !$cm->url) continue;`. Labels have no view URL by design
  — they render inline on the course page — so every one was dropped before reaching the index.
  Labels are where teachers put headings, instructions and framing text, so this removed real
  teaching content from what the tutor can read. The switch below the guard already carried a
  `case 'label':` branch that the guard made unreachable, so indexing them was always the intent.
  `mod_subsection` was in the same position. `$cm->url` was used nowhere else in the loop.

  Verified on a course of 14 activities spanning 14 module types: 13 indexed before, **14 of 14
  after**.

- **The AI Tutor reported "out of credits" and "bad API key" as an unspecified error.** Non-200
  responses all collapsed into `aiassistant_error`, with the real status going to `debugging()`
  only — off on production sites, so the answer existed only where nobody looks. The service
  returns 401 for a bad key or mismatched site URL and 402 for insufficient credits; these now map
  to specific translated messages. Unknown statuses still fall through to the generic message.

  Deliberately **not** applied to the banner endpoint: `describe_failure()` (2.1.26) already does
  this better there, surfacing the service's own text. That is safe for the banner because it
  requires `moodle/course:update`, but not for the tutor, which students can reach — so the tutor
  uses fixed translated strings and never echoes remote text.

- **The language file was not in alphabetical order** — 433 ordering breaks, which Moodle's coding
  style requires it not to have. Re-sorted; verified lossless by loading both versions and
  comparing: 442 strings before and after, none missing, none added, no value altered.

### Changed

- `$plugin->version` is 2026082002, which clears the 2026082000 currently installed on the
  production site. Moodle refuses any lower number as a downgrade.

## [2.1.33] - 2026-08-20

### Fixed

- **Two stale unit tests.** `content_test` still asserted the card activity list was capped at 4,
  but 2.1.25 replaced that hard-coded cap with the `cardactivitylimit` course setting, which
  defaults to 0 ("list every activity"). The code was right and the tests had not followed; they
  now set an explicit limit of 4, since what they actually test is the overflow chip.
- **Seven phpcs errors** in `classes/output/courseformat/hero.php` and `activityhero.php` —
  consecutive blank lines inside functions, and a multi-line function call whose parenthesis and
  indentation were wrong. Auto-fixed; both Moodle standards now report zero.
- **`--acf-navband` removed.** Declared in one rule and consumed nowhere in the CSS, JS, PHP or
  templates. Removing it also cleared the only `length-zero-no-unit` warning.
- Reworded the `accentcolour` comment in `lib.php` so it no longer contains the literal token the
  release pipeline greps for, and capitalised the inline comment on the overlay default.

### Changed

- **`$plugin->version` is now 2026082001.** It had to clear 2026082000, which is what a build
  installed on the production site reports; Moodle refuses a lower number as a downgrade, so
  2.1.33 could not have been installed over it.

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
