# AI Course Format

A Moodle course format that turns a course page into something closer to a dedicated e-learning
player: a banner that carries the course and its progress, section and activity cards, a course
index that shows how long everything takes, and an AI Tutor that can answer questions about the
course content.

Requires Moodle 4.5 or later.

---

## The course page

### The banner

Sits at the top of the course, its sections and its activities. It carries the course name, how
many modules and activities it contains, the total time, and a progress ring.

- **Show hero banner** turns it on or off.
- **Banner width** — aligned with the content below it, or full width edge to edge. Full width also
  removes the gap above, giving the page the shape of a player rather than a web page with a
  picture on it.
- **Banner at top of page** lifts it above the course tab bar.
- **Hero image overlay opacity** controls how much the image is darkened so the white title stays
  readable. Around 54% is the point at which a worst-case white image still meets WCAG AA.

**Generating a banner image.** The banner button asks the LMS-Labs service to generate an image from
the course name. It costs credits, runs in the background, and typically completes in under ten
seconds. You can leave the page while it works.

### Section and activity cards

Each section becomes a card showing its activities, an estimated time, and progress. A card turns
green when every tracked activity in it is complete.

- **Section display mode** — cards or Moodle's standard list.
- **Section card layout** — grid or a single column.
- **Show activities on cards** and **Activities listed per card** control the list inside each card.
  Zero means no cap.
- **Activity display mode** — cards or a list, on section pages.
- **Card title text size** for long section names.

---

## The course index

Moodle's course index panel, optionally replaced by a **course player sidebar**: your logo, the
course name, a progress ring, the total time, and a row per activity showing its icon, its estimated
duration, and a tick once complete.

- **Course player sidebar** switches between the plain index and the player version.
- **Course index on first entry** — remember the user's choice, start collapsed, or start open. It
  applies once per user per course; after that the user's own choice is respected.
- **Course index header colour** sets the band at the top of the panel.
- **Hide the General section** removes section 0 from the index and the cards. It hides only —
  announcements still post and email as normal.

---

## Estimated time

Every activity carries an estimated duration. Section cards show the total, and the banner shows the
total for the course.

Three sources, in order of precedence:

1. **A teacher's own figure** for a single activity, which always wins. Zero hides the time.
2. **Quizzes** are worked out from how many questions they contain, times **Minutes per question**.
3. **Per-type defaults**, set site-wide as `modname=minutes` lines. Anything not listed falls back
   to **Fallback minutes**.

---

## Trimming the page

Each of these has three states — show, hide from students, hide from everyone — set per course, with
a site default and a site-wide override.

| Setting | What it hides |
|---|---|
| Course navigation tabs | The Course / Settings / Participants / Grades / Reports row |
| Where the course tabs appear | Moves those tabs into the site header for teachers instead |
| Hide the site logo band | The taller header band holding the site logo and links |
| Site footer | The footer strip at the bottom of the page |
| Breadcrumb trail | The Home ▸ My courses ▸ … trail |
| Hide the General section | Section 0 |

**Everything here returns while Edit mode is on.** If something has not hidden, check whether Edit
mode was switched on.

**Defaults versus overrides.** A site *default* only applies to courses that have never saved their
settings. A course that has been configured keeps its own value and will not pick up a changed
default. To change courses that already exist, use the matching *override all courses* setting.

---

## Colour

- **Accent colour** — a hex value used for section and card headings, activity icons, the course
  index headings, and keyboard focus rings. Empty follows the theme's primary colour.
- **Course index header colour** — the band at the top of the course index.
- **Course player logo** — a logo for the sidebar, separate from the site logo, for courses branded
  for a client rather than the institution.

Both colours exist per course, as a site default, and as a site-wide override.

---

## The AI Tutor

A chat panel learners open from the banner. It reads the course content, so its answers are about
your material rather than the internet at large.

- **Enable AI Tutor** switches it off entirely if you would rather not use it.
- **Site ID** and **API Key** connect it to the LMS-Labs service.
- **Share assessment answers with the AI Tutor** is an absolute ceiling on whether answer keys are
  ever transmitted.

**For teachers**, the tutor also knows this format's own settings — ask it how to hide the tabs or
change the accent colour and it will answer from the same descriptions you see on the settings
screens. Learners do not receive that information.

**What is sent.** For a learner's question: site id, user id, first name, course id and name,
activity name and type, section name, the question, and that learner's own prior questions. The
banner generator sends no personal data. Both are declared in the plugin's privacy provider.

---

## The guided tour

A narrated walkthrough offered on a user's first visit — 16 steps for teachers, 11 for learners,
covering the banner, progress, the course index, cards, timings, the tutor and the reporting
dashboard.

Audio ships with the plugin. Where a file is missing, that step falls back to the browser's own
speech synthesis rather than going silent.

---

## Reporting

**Site administration ▸ Plugins ▸ Course formats ▸ AI Course Format ▸ View all AI Tutor Q&A**, and a
per-course report under the course menu: what learners asked, where they got stuck, and which
activities generate the most questions. Often the fastest way to find a confusing piece of content.

---

## Where the settings are

- **Per course** — Course settings, then the *Course format* section.
- **Site-wide** — Site administration ▸ Plugins ▸ Course formats ▸ AI Course Format.
