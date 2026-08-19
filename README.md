# AI Course Format (format_aicourse)

A Moodle course format that presents a course as a set of visual section cards with a hero
banner and progress tracking, and adds an AI Tutor that students can ask questions about the
course they are studying.

The tutor is deliberately pedagogical rather than answer-giving: it is instructed to explain
concepts, suggest structure, give workplace examples and produce checklists, and to refuse to
write anything a student could submit as their own work. It also locks out of answer mode once
an assignment has been submitted. Teachers get a per-course report of every question asked,
and can write corrections that are fed back to the service.

> **This plugin sends data to a third-party service.** See
> [AI and external service disclosure](#ai-and-external-service-disclosure) before installing.

## Features

- **Section cards** — sections rendered as cards with an icon, activity count, estimated time
  and a progress ring. Falls back to a traditional section list per course setting.
- **General section rendering** — the General section (section 0) and its activities, including
  labels, always render inline at the top of the course home page.
- **Hero banner** — a course header showing the course image or a custom banner, the course or
  section title, and live completion progress. Configurable height, width and alignment.
- **Activity cards** — activities rendered as cards with completion status, grade and the
  completion requirement, or as the standard Moodle list.
- **Section and activity navigation** — previous/next chevrons and a "return to section" link
  with accessible names.
- **Per-section icons** — a searchable, categorised icon picker for teachers.
- **AI Tutor** — a chat panel on course, section and activity pages, aware of the course
  content, the current activity and (in quizzes) the current question.
- **Teacher report** — per-course AI Tutor report with filters, ratings and the ability to
  correct a response.
- **Site-wide admin report** — every question across every course, with filters and CSV export.
- **Course index control** — choose which page types show the course index sidebar.

## Screenshots

Screenshots are published on the plugin's page in the Moodle Plugins Directory rather than
shipped inside the package, so that installing the plugin does not copy several megabytes of
images onto every site. All were captured from a live Moodle 4.4 site running this release.

The set covers: the course home page (hero banner, completion ring, inline General section and
the section card grid); the same page in dark mode, which is a full token-level dark theme
rather than an inversion; a section page showing activities as cards with type, completion
state and progress; the course home page in edit mode, where the section cards remain and gain
drag reordering, bulk actions and per-section icons; the AI Tutor chat panel open over a course
page; and the layout at a 430&nbsp;px mobile viewport with no horizontal scrolling.

## Requirements

| | |
|---|---|
| Moodle | 4.4 or later (tested to 5.0) |
| PHP | 8.1 or later (as required by Moodle 4.4) |
| Database | Any database supported by Moodle |
| Optional | An LMS-Labs subscription, for the AI Tutor only |

Moodle 4.4 is a hard minimum. The plugin registers a callback for
`\core\hook\output\before_standard_footer_html_generation`, which does not exist before 4.4,
and the hero banner and AI Tutor are injected through it.

Everything except the AI Tutor works with no subscription and no outbound network access.

### Moodle Mobile app

This format does not ship a `db/mobile.php`, so the Moodle Mobile app renders these courses with
its own default course layout. Courses remain fully usable in the app — the section cards,
hero banner and AI Tutor panel are web-only.

## Installation

### From a ZIP file

1. Download the plugin ZIP.
2. Log in to your Moodle site as an administrator.
3. Go to **Site administration > Plugins > Install plugins**.
4. Upload the ZIP, choose plugin type **Course format (format)**, and select
   **Install plugin from the ZIP file**.
5. Complete the upgrade when prompted.

### From Git

```sh
cd /path/to/moodle
git clone https://github.com/lms-labs/moodle-format_aicourse.git course/format/aicourse
```

Then visit **Site administration > Notifications** and complete the upgrade, or run:

```sh
php admin/cli/upgrade.php
```

The plugin **must** be installed into `course/format/aicourse`. The directory name is part of
the component name `format_aicourse` and Moodle will refuse to install it elsewhere.

## Configuration

### Site settings

**Site administration > Plugins > Course formats > AI Course Format**

| Setting | Default | What it does |
|---|---|---|
| AI Tutor Q&A Report | — | A link to the site-wide report of every AI Tutor question. Not a stored setting. |
| External AI service | — | The disclosure notice describing what is sent to lms-labs.com. Not a stored setting. |
| Enable AI Tutor (`enabletutor`) | Yes | Master switch. When set to No, the AI Tutor chat panel is not rendered anywhere and no data leaves the site. |
| Site ID (`siteid`) | empty | Your Moodle site URL, used by LMS-Labs to identify your subscription. Without it the tutor is inactive. |
| API Key (`apikey`) | empty | Your LMS-Labs API key. Stored using Moodle's masked-password setting. Without it the tutor is inactive. |
| Show hero banner (`defaultshowherobanner`) | Yes | Default value of the per-course hero banner setting for newly created courses. |
| Section display mode (`defaultdisplayascards`) | Cards | Default value of the per-course section display setting for newly created courses. |
| Share assessment answers with the AI Tutor (`shareassessmentanswers`) | **Never share** | Whether correct answers, answer feedback and essay marking guides may be included in the course content sent to the AI service. **Never share** (default), **Always share in every course**, or **Let each course decide**. This is a ceiling: a course can only opt in when this is set to "Let each course decide". See [AI and external service disclosure](#ai-and-external-service-disclosure). |

If the `local_aiconfig` (AI Grader Central Config) plugin is installed, the Site ID and API
Key are read from it and the settings above are optional.

### Course settings

**Course > Settings > Course format** (only when the format is AI Course Format)

| Option | Values | What it does |
|---|---|---|
| Show hero banner | Yes / No | Show the hero banner at the top of course, section and activity pages. |
| Show navigation chevrons | Yes / No | Show previous/next arrows on activity and section pages. |
| Hero banner height | pixels | Maximum hero height. 140–180 is compact, 200–300 makes the image prominent. |
| Hero banner width | pixels, 0 = full | Maximum hero width, to line the banner up with your theme's content width. |
| Hero banner alignment | Centre / Left | How the hero is placed when a custom width is set. |
| Card title text size | pixels | Font size of section and activity card titles. Default 14. |
| Section display mode | Traditional sections / Cards | How sections appear on the course home page. |
| Activity display mode | Standard Moodle list / Activity cards | How activities appear inside a section. |
| Show activities on cards | Yes / **No** | List each section's activities on its card, with completion state. Off by default, because the card grid is designed to be scannable at a glance; turn it on when learners benefit from seeing what is inside a module without opening it. Long sections are capped with a "+N more" link. |
| Share assessment answers with the AI Tutor | Yes / **No** | Only has any effect when the site setting above is set to "Let each course decide". Lets a single course — a revision course, say — include its answer keys in what the tutor is told, without opening that up site-wide. |
| Show course index sidebar | 8 combinations | Which page types (home, section, activity) show Moodle's course index. |
| Upload banner image | file | A custom hero image for this course. Recommended 16:5, e.g. 1920 × 600 px, minimum 1200 × 375 px, max 5 MB. Falls back to the course overview image. |

Per-section icons are set from the course home page in edit mode, by activating the icon box
on a section card.

### Capabilities

| Capability | Default roles | Purpose |
|---|---|---|
| `format/aicourse:view` | all enrolled roles | See the enhanced course view. |
| `format/aicourse:useaitutor` | student, teacher, editing teacher, manager | Ask the AI Tutor questions. Prohibit this to stop a role sending data to the external service. |
| `format/aicourse:viewreport` | teacher, editing teacher, manager | View the per-course AI Tutor report, which contains other users' questions. |
| `format/aicourse:correctresponses` | teacher, editing teacher, manager | Write a correction onto another user's AI Tutor response. |

## AI and external service disclosure

The AI Tutor is **not** self-hosted. Answers are generated by the LMS-Labs AI service at
`https://lms-labs.com`, operated by LMS-Labs, and questions are relayed to it over HTTPS.

**What is sent, and when.** Nothing is transmitted unless *all* of the following are true: the
site administrator has set both a Site ID and an API Key, "Enable AI Tutor" is on, the course
uses this format, and a user actively types a question and presses send. Each such request
sends, to `https://lms-labs.com/api/moodle/course-assistant/chat`:

- your site URL and API key (to identify the subscription);
- the user's Moodle user ID and first name;
- the course ID and full name;
- the text content of the course visible to that user — section names, activity names and
  descriptions, page and label text, slide text, and quiz question text;
- the name and type of the activity and the name of the section the user is on;
- the question the user typed;
- for quizzes, the question slot number and the text of the quiz question;
- **only if a site administrator has explicitly enabled it** (see `shareassessmentanswers` above,
  which defaults to **Never share**): the correct answers to quiz and knowledge check questions,
  the per-option answer feedback, and the essay marking guide ("information for graders").
  With the default setting none of these leave the site, and the tutor receives only the
  *wording* of each question — enough to discuss the topic, not enough to give the answer away;
- the stored summary of what the user previously asked about in this activity.

No password, email address, surname, ID number or grade is sent.

Separately, the optional **Generate AI banner** button sends the site URL, API key, course ID,
course short name and course full name to
`https://lms-labs.com/api/moodle/aicourse/generate-banner`. That request contains no personal
data.

**How to disable it.** Set **Enable AI Tutor** to **No**, or leave the Site ID and API Key
empty, or prohibit `format/aicourse:useaitutor` for the roles concerned. With the tutor
disabled the plugin makes no outbound network requests at all and is a purely local course
format.

Before enabling the tutor you should review the LMS-Labs terms and privacy policy, and satisfy
yourself that transferring the data above to that provider is lawful for your institution and
your users. Depending on your jurisdiction this may require a data processing agreement, and,
if the provider processes data outside your region, an appropriate transfer mechanism.

## Privacy and GDPR

The plugin implements the full Moodle Privacy API
(`\format_aicourse\privacy\provider`). It is **not** a null provider: it stores personal data
and declares an external transfer.

**Stored data.**

- `format_aicourse_chats` — one row per question: course ID, user ID, activity ID, quiz question
  slot, the question text, the AI response, the user's rating, whether the AI refused, whether
  the activity was locked, any teacher correction, the ID of the teacher who wrote it, and the
  created and corrected timestamps.
- `format_aicourse_ai_memory` — one row per user per activity: course ID, activity ID, user ID,
  a short summary of topics previously asked about, and the last update time.

**Scope.** Both tables are keyed by course, so all of this data lives in the **course context**.
Data requests and deletions made at course level, and course deletion itself, remove it.

**Subject access requests.** Exports include the user's own conversations, the corrections that
user wrote on other people's conversations, and their tutoring memory, grouped per course.

**Erasure.** Deleting a user's data removes their chat rows and their tutoring memory. Where the
user appears only as the *author of a correction* on someone else's row, the row itself is kept
(it is the other user's personal data and must not be destroyed) and the attribution columns
`correctedby` and `timecorrected` are set to NULL instead.

**External transfer.** The transfer to LMS-Labs is declared to Moodle via
`add_external_location_link()` and appears in **Site administration > Users > Privacy and
policies > Plugin privacy registry**. Moodle cannot delete data held by an external provider;
erasure requests for data already sent to LMS-Labs must be made to LMS-Labs directly.

**Course deletion.** An event observer removes both tables' rows for a course when the course is
deleted, so no orphaned personal data is left behind.

## Accessibility

Release 2.0.0 includes a full accessibility pass against WCAG 2.1 AA. In this release:

- Section icon pickers, card action buttons and the chat send button are real, focusable
  controls with accessible names, keyboard activation and visible focus.
- Completion status is never conveyed by colour alone; the activity number circles carry the
  status in their accessible name.
- Progress rings expose their value through `role="progressbar"` and matching ARIA values, and
  the visible percentage matches what is announced.
- Navigation chevrons and the "return to section" link have descriptive names that include the
  destination, not just "Next".
- Decorative SVG icons are `aria-hidden` and removed from the tab order.
- The section card grid is a labelled landmark region.
- Colour contrast meets 4.5:1 for text and 3:1 for UI components in both light and dark themes.

Known gaps are tracked in the issue tracker. If you find an accessibility barrier, please
report it — accessibility bugs are treated as defects, not enhancements.

## Support

- Issues and feature requests: <https://github.com/lms-labs/moodle-format_aicourse/issues>
- Plugin page: <https://moodle.org/plugins/format_aicourse>

Please include your Moodle version, PHP version, plugin version and the exact steps to
reproduce.

## Licence

Copyright 2026 LMS-Labs.

This program is free software: you can redistribute it and/or modify it under the terms of the
GNU General Public License as published by the Free Software Foundation, either version 3 of
the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See
the GNU General Public License for more details.

The full licence text is in [LICENSE](LICENSE), or at
<https://www.gnu.org/licenses/gpl-3.0.html>.
