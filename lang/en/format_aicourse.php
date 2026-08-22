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
 * Strings for component 'format_aicourse', language 'en'.
 *
 * Keys are kept in alphabetical order, as required by the Moodle coding style.
 *
 * @package    format_aicourse
 * @category   string
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accentcolour'] = 'Accent colour (used for headings, icons and highlights)';
$string['accentcolour_desc'] = 'The colour this format tints everything with: the hero banner background, card borders and hover states, icon wells, progress chips and the keyboard focus ring. Leave empty to keep following your theme\'s primary colour. Set here it applies to every course using this format; an individual course can override it in its own course settings.';
$string['accentcolour_help'] = 'The accent colour is the one colour that marks the structure of your course. It is used for:

* section and card headings
* activity icons
* the headings and dividers in the course index
* the outline that appears when you tab to a button with the keyboard

Enter a hex colour — six characters after a hash, like <code>#194866</code> for navy or <code>#b5179e</code> for magenta. Your theme\'s own primary colour is used if you leave this empty.

*Tip:* pick something with reasonable contrast against white. Very pale colours will make headings hard to read.';
$string['activities'] = 'activities';
$string['activity'] = 'activity';
$string['activitydisplaycards'] = 'Beautiful activity cards';
$string['activitydisplaymode'] = 'How activities look inside a section';
$string['activitydisplaymode_desc'] = '<strong>What this does:</strong> chooses how activities look inside a section.<br /><br /><strong>Standard Moodle list</strong> is the familiar plain list.<br /><strong>Activity cards</strong> gives each activity a tile with its icon, its type and how long it takes.<br /><br /><em>Example:</em> a section holding a page, a video and a quiz reads as three distinct things rather than three lines of text.';
$string['activitydisplaymode_help'] = 'Choose between traditional Moodle activity list or beautiful card view with status badges and icons.';
$string['activitydisplaystandard'] = 'Standard Moodle list';
$string['activitynumberstatus'] = 'Activity {$a->num}: {$a->name} ({$a->status})';
$string['activitytype_activities'] = 'Learning Activities';
$string['activitytype_content'] = 'Learning Content';
$string['activitytype_knowledgecheck'] = 'Knowledge Check';
$string['activitytype_slides'] = 'Learning Slides';
$string['activitywithstatus'] = '{$a->name} ({$a->status})';
$string['addicon'] = 'Add icon';
$string['addiconfor'] = 'Add an icon for {$a}';
$string['addsection'] = 'Add section';
$string['admin_report_all'] = 'All';
$string['admin_report_all_courses'] = 'All courses';
$string['admin_report_answered'] = 'Answered';
$string['admin_report_answered_only'] = 'Answered only';
$string['admin_report_col_activity'] = 'Activity';
$string['admin_report_col_activityid'] = 'Activity ID';
$string['admin_report_col_course'] = 'Course';
$string['admin_report_col_email'] = 'Email';
$string['admin_report_col_refused'] = 'Refused';
$string['admin_report_export_csv'] = 'Export CSV';
$string['admin_report_filter_capped'] = 'Showing the first {$a} entries only.';
$string['admin_report_filter_course'] = 'Course';
$string['admin_report_filter_datefrom'] = 'From date';
$string['admin_report_filter_dateto'] = 'To date';
$string['admin_report_filter_rating'] = 'Rating';
$string['admin_report_filter_refused'] = 'Response type';
$string['admin_report_filter_student'] = 'Student';
$string['admin_report_filter_unrated'] = 'Unrated';
$string['admin_report_link'] = 'AI Tutor Q&A Report';
$string['admin_report_no_filtered'] = 'No records match the current filters.';
$string['admin_report_refused'] = 'Refused';
$string['admin_report_refused_only'] = 'Refused only';
$string['admin_report_reset'] = 'Reset';
$string['admin_report_search'] = 'Search questions / responses';
$string['admin_report_show_more'] = 'Show more';
$string['admin_report_showing'] = 'Showing {$a->from}–{$a->to} of {$a->total} records';
$string['admin_report_stat_courses'] = 'Active Courses';
$string['admin_report_stat_helpful'] = 'Rated Helpful';
$string['admin_report_stat_refused'] = 'Refused (academic integrity)';
$string['admin_report_stat_students'] = 'Active Students';
$string['admin_report_stat_total'] = 'Total Questions (all time)';
$string['admin_report_table_caption'] = 'AI Tutor questions and responses from every course on this site';
$string['admin_report_title'] = 'AI Tutor — All Q&A (Site-wide)';
$string['admin_report_view'] = 'View all AI Tutor Q&A';
$string['aiassistant'] = 'AI Tutor';
$string['aiassistant_error'] = 'Sorry, I couldn\'t process your question. Please try again.';
$string['aiassistant_input_label'] = 'Your question for the AI Tutor';
$string['aiassistant_locked'] = 'You\'ve already submitted this assignment. I can\'t help with answers now, but I can help you reflect on feedback, identify learning gaps, or prepare for future tasks. What would you like to explore?';
$string['aiassistant_notconfigured'] = 'AI Tutor is not configured. Please set up the Site ID and API Key in the plugin settings.';
$string['aiassistant_placeholder'] = 'Ask a question about the course...';
$string['aiassistant_prompt_checklist'] = 'Can you give me a checklist of things I should include or check before I submit my work?';
$string['aiassistant_prompt_concepts'] = 'Can you explain the key concepts I need to understand for this activity? Break it down in simple terms.';
$string['aiassistant_prompt_practice'] = 'Can you give me a practice question or scenario to help me prepare? Something similar to what I need to do.';
$string['aiassistant_prompt_structure'] = 'Can you help me understand how to structure my response for {activity}? I don\'t need an answer, just guidance on the format and what sections to include.';
$string['aiassistant_prompt_workplace'] = 'Can you give me a real workplace example that relates to this activity? I want to understand how this applies in a real job.';
$string['aiassistant_quick_checklist'] = 'Checklist';
$string['aiassistant_quick_concepts'] = 'Explain concepts';
$string['aiassistant_quick_label'] = 'Quick help:';
$string['aiassistant_quick_practice'] = 'Practice questions';
$string['aiassistant_quick_structure'] = 'How to structure';
$string['aiassistant_quick_workplace'] = 'Workplace examples';
$string['aiassistant_rate_helpful'] = 'This answer was helpful';
$string['aiassistant_rate_nothelpful'] = 'This answer was not helpful';
$string['aiassistant_rating_thanks'] = 'Thanks for the feedback!';
$string['aiassistant_restored'] = 'Restored from history';
$string['aiassistant_send'] = 'Send message';
$string['aiassistant_settings_desc'] = 'Configure the AI Tutor connection. If you have the AI Grader Central Config plugin installed, these settings are optional as it will use the central configuration.';
$string['aiassistant_thinking'] = 'Thinking…';
$string['aiassistant_thisactivity'] = 'this activity';
$string['aiassistant_welcome_activity'] = 'Hi {$a->name}! I see you\'re working on <strong>{$a->activity}</strong>. What would you like help with?';
$string['aiassistant_welcome_name'] = 'Hi {$a}! I\'m your AI Tutor for this course. What would you like help with today?';
$string['aiassistant_welcome_question'] = 'I see you\'re on question {$a->num}. This question is about {$a->topic}. How can I help you think it through?';
$string['aiassistant_welcome_questionnotopic'] = 'I see you\'re on question {$a}. How can I help you think it through?';
$string['aiassistant_welcome_section'] = 'Hi {$a->name}! I see you\'re in <strong>{$a->section}</strong>. What would you like help with?';
$string['aicourse:correctresponses'] = 'Correct an AI Tutor response';
$string['aicourse:useaitutor'] = 'Use the AI Tutor';
$string['aicourse:view'] = 'View AI Course Format';
$string['aicourse:viewreport'] = 'View AI Course Format reports';
$string['aireport'] = 'AI Tutor Report';
$string['aireport_actions'] = 'Actions';
$string['aireport_activities'] = 'Activities';
$string['aireport_all_groups'] = 'All Groups';
$string['aireport_all_ratings'] = 'All Ratings';
$string['aireport_all_students'] = 'All Students';
$string['aireport_apply'] = 'Apply Filters';
$string['aireport_cancel'] = 'Cancel';
$string['aireport_characters'] = 'Characters Learned';
$string['aireport_chattable_caption'] = 'AI Tutor questions and responses for this course';
$string['aireport_content'] = 'Course Content';
$string['aireport_correct'] = 'Correct';
$string['aireport_corrected'] = 'Corrected Responses';
$string['aireport_correction'] = 'Correction';
$string['aireport_correction_placeholder'] = 'Enter the correct answer to retrain the AI...';
$string['aireport_course_summary'] = 'Course Summary';
$string['aireport_date'] = 'Date';
$string['aireport_filter_corrected'] = 'Corrected Only';
$string['aireport_filter_helpful'] = 'Helpful Only';
$string['aireport_filter_nothelpful'] = 'Not Helpful Only';
$string['aireport_helpful'] = 'Marked Helpful';
$string['aireport_history'] = 'Chat History';
$string['aireport_learned'] = 'AI has learned this content';
$string['aireport_learned_content'] = 'Content Learned by AI';
$string['aireport_no_chats'] = 'No chat history yet';
$string['aireport_no_chats_desc'] = 'Students haven\'t asked any questions to the AI Tutor yet.';
$string['aireport_nocourses'] = 'No courses using AI Course Format';
$string['aireport_nocourses_desc'] = 'Change a course format to AI Course Format to enable the AI Tutor.';
$string['aireport_nocoursesummary'] = 'No course summary';
$string['aireport_question'] = 'Question';
$string['aireport_rating'] = 'Rating';
$string['aireport_rating_learner_helpful'] = 'Learner: helpful';
$string['aireport_rating_learner_none'] = 'Learner: not rated';
$string['aireport_rating_learner_nothelpful'] = 'Learner: not helpful';
$string['aireport_response'] = 'AI Response';
$string['aireport_save'] = 'Save';
$string['aireport_search'] = 'Search questions or responses...';
$string['aireport_sections'] = 'Sections';
$string['aireport_student'] = 'Student';
$string['aireport_total_questions'] = 'Total Questions';
$string['aireport_unknownuser'] = 'Unknown user';
$string['aireport_view'] = 'View Report';
$string['apikey'] = 'API key from LMS-Labs';
$string['apikey_desc'] = '<strong>What this is:</strong> the key that lets this site talk to the LMS-Labs AI service. You get it from your dashboard at lms-labs.com.<br /><br />Without it the AI Tutor cannot answer anything and will report that it is not configured.';
$string['bannerdel_confirm'] = 'Remove image';
$string['bannerdel_desc'] = 'The banner image will be permanently removed from this course. You can generate or upload a new one at any time.';
$string['bannerdel_error'] = 'Failed to remove banner. Please try again.';
$string['bannerdel_removed'] = 'Banner image removed';
$string['bannerdel_removing'] = 'Removing';
$string['bannerdel_title'] = 'Remove banner image?';
$string['bannergen_applied'] = 'Banner applied to your course';
$string['bannergen_cost'] = '{$a} credits';
$string['bannergen_costdetail'] = 'One-time generation cost';
$string['bannergen_desc'] = 'AI reads your course name and generates a cinematic, full-width banner image tailored to your course subject. The image is automatically cropped and optimised for your course header, then saved directly to your course.';
$string['bannergen_failed'] = 'Generation failed. Please try again.';
$string['bannergen_failedtitle'] = 'Generation failed';
$string['bannergen_generate'] = 'Generate banner';
$string['bannergen_loadingsub'] = 'AI is crafting a cinematic banner for your course. This usually takes one to two minutes - please leave this window open.';
$string['bannergen_loadingtitle'] = 'Generating your banner';
$string['bannergen_previewalt'] = 'Generated course banner';
$string['bannergen_retry'] = 'Try again';
$string['bannergen_subtitle'] = 'AI image generation';
$string['bannergen_success'] = 'Your AI banner has been saved to the course.';
$string['bannergen_title'] = 'Generate AI banner';
$string['bannerimage'] = 'Upload banner image';
$string['bannerimage_help'] = 'Upload one image to use as this course\'s hero banner. It replaces the course image on the course home page and on every section page. Landscape images work best - around 1920 x 600 pixels, at most 5 MB, in JPG, PNG or WebP. Leave it empty to fall back to the course image, or generate one with AI from the course page.';
$string['bannerimage_ratio_formats'] = 'Accepted formats: JPG · PNG · WebP  ·  Maximum file size: 5 MB  ·  One image per course.';
$string['bannerimage_ratio_hint'] = 'Ideal image size is 1920 × 600 px (or 1600 × 500 px minimum). Centre your subject — edges may be cropped on narrow screens. Dark or high-contrast images give the best result with the white text overlay.';
$string['bannerimage_ratio_title'] = 'Recommended ratio: 16:5';
$string['bannerimageheader'] = 'Course Banner Image';
$string['bannerqueued'] = 'Generating your banner image. This takes one to two minutes and continues even if you leave this page \u2014 the banner will appear when it is ready.';
$string['bannerstillrunning'] = 'Still generating. You can leave this page; the banner will be there when it is done.';
$string['cachedef_ajaxratelimit'] = 'AI Tutor and banner generation rate limit counters';
$string['cachedef_coursecontent'] = 'Course content index used by the AI Tutor';
$string['cardactivitiesmore'] = 'View all activities in {$a}';
$string['cardactivitylabel'] = '{$a->name}, {$a->section}';
$string['cardactivitylimit'] = 'How many activities to list on each section card';
$string['cardactivitylimit_desc'] = '<strong>What this does:</strong> caps how many activities are listed on each section card.<br /><br /><em>Example:</em> set it to 4 and a section holding 12 activities lists the first four and then a <code>+8</code> link. Set it to 0 and every activity is listed, however many there are.';
$string['cardactivitylimit_help'] = 'Only applies when **Show activities on cards** is turned on.

* **0** &mdash; list every activity. Cards grow as tall as they need and, because the grid stretches cards to a common height, the whole row matches the tallest one. This is the default.
* **4** &mdash; the old fixed behaviour: four activities and a "+N" link to the section.

Set a cap if you have sections with a great many activities and you want the course home page to stay scannable.';
$string['cardactivitystatuslabel'] = '{$a->name}, {$a->section} ({$a->status})';
$string['cardcolour'] = 'Background colour of the cards';
$string['cardcolour_help'] = 'The background of the section and activity cards.

A hex colour such as <code>#fafbfc</code>, which is the default - a very soft grey that lifts a card off the white page behind it without reading as a filled panel.

Leave empty to follow the site setting.

*Tip:* this sits behind the card\'s title and its list of activities, so keep it light. A strong colour here makes the text on top harder to read, and the card headings already carry your accent colour.';
$string['cardlayout'] = 'How the section cards are arranged';
$string['cardlayout_desc'] = '<strong>What this does:</strong> chooses how the section cards are arranged.<br /><br /><strong>Grid</strong> puts them side by side — good for a course with several short modules a learner picks between.<br /><strong>List</strong> puts one per row down the page — good when section names are long, or when the course is meant to be worked through in order.';
$string['cardlayout_grid'] = 'Grid - cards side by side';
$string['cardlayout_help'] = 'How the section cards are arranged.

**Grid** fits as many cards across the page as the width allows and is the default.

**List** gives every card the full width of the page and stacks them vertically, which suits long section names and courses with only a few sections. On a phone both settings look the same, because a single column is all that fits either way.';
$string['cardlayout_list'] = 'List - one card per row, down the page';
$string['cardopacity'] = 'How strong the card colour is';
$string['cardopacity_help'] = 'How strongly the card colour is applied, as a percentage from 0 to 100.

* **100** - the colour exactly as you set it.
* **50** - halfway between your colour and white.
* **0** - plain white; the card colour has no effect.

This lets you pick a colour you like and then dial it back until it is as subtle as you want, rather than hunting for a paler hex value.

The card stays fully opaque at every setting - the strength mixes your colour toward white rather than making the card see-through, so nothing behind it shows through when the page scrolls.';
$string['cardtitlesize'] = 'Size of the title on each section card';
$string['cardtitlesize_desc'] = '<strong>What this does:</strong> sets the size of the section name on each card, in pixels.<br /><br /><em>Example:</em> 16 is compact and fits long names on one line; 22 is bold and easy to scan. Around 18 suits most courses.';
$string['cardtitlesize_help'] = 'Set the font size for section card and activity card titles in pixels. For example, enter 12 for smaller titles or 16 for larger. Default is 14px.';
$string['changeicon'] = 'Change icon';
$string['changeiconfor'] = 'Change the icon for {$a}';
$string['colourmode'] = 'Light or dark appearance';
$string['colourmode_dark'] = 'Always dark';
$string['colourmode_desc'] = '<strong>What this does:</strong> decides whether the course pages are painted light or dark.<br /><br /><strong>Follow the theme</strong> (recommended) matches whatever your theme is doing.<br /><strong>Follow the device</strong> uses each person\'s own phone or laptop setting.<br /><strong>Always light</strong> or <strong>Always dark</strong> ignores both and picks one.<br /><br /><em>If you are unsure, leave it on Follow the theme.</em>';
$string['colourmode_device'] = 'Follow the device setting';
$string['colourmode_light'] = 'Always light';
$string['colourmode_theme'] = 'Follow the theme (recommended)';
$string['completed'] = 'Completed';
$string['completedof'] = '{$a->completed} of {$a->total} activities completed';
$string['completionrequirement_auto'] = 'Complete activity';
$string['completionrequirement_grade100'] = 'Required grade 100%';
$string['completionrequirement_gradeany'] = 'Receive a grade';
$string['completionrequirement_gradepass'] = 'Required grade {$a}';
$string['completionrequirement_gradepasspct'] = 'Required grade {$a}%';
$string['completionrequirement_manual'] = 'Mark as done';
$string['completionrequirement_view'] = 'View activity';
$string['courseindex_activity'] = 'Activity pages only';
$string['courseindex_all'] = 'All pages (home, section, activity)';
$string['courseindex_home'] = 'Course home only';
$string['courseindex_home_activity'] = 'Course home + Activity pages';
$string['courseindex_home_section'] = 'Course home + Section pages';
$string['courseindex_none'] = 'Hide on all pages';
$string['courseindex_section'] = 'Section pages only';
$string['courseindex_section_activity'] = 'Section + Activity pages';
$string['coursenavplace'] = 'Where the course tabs sit';
$string['coursenavplace_default'] = 'Below the banner, as the theme renders them';
$string['coursenavplace_header'] = 'In the site header, beside Home and Dashboard';
$string['coursenavplace_help'] = 'Course, Settings, Participants, Grades and Reports are links only teachers use — but as a full-width row under the banner they take up space on every page, including for people who never click them.

* **Below the banner** — leave them where your theme puts them.
* **In the site header** — move them up to sit beside Home, Dashboard and My courses, where a teacher already looks for links like these.

This only affects people who can edit the course. What students see is decided by the "Course navigation tabs" setting instead.

The tabs go back to their normal place while Edit mode is on.';
$string['courseprogress'] = 'Course Progress';
$string['coursesectionsregion'] = 'Course sections';
$string['currentsection'] = 'This section';
$string['defaultaccentcolour_desc'] = '<strong>What this does:</strong> sets the one colour the format uses for section headings, activity icons, course index headings and focus outlines.<br /><br />Write a hex colour such as <code>#194866</code>. Leave it empty to follow your theme\'s own primary colour, which is usually what you want.';
$string['defaultcardcolour_desc'] = '<strong>What this does:</strong> the background colour of the section and activity cards.<br /><br />Keep it light — the card\'s title and activity list sit on top of it. <em>Example:</em> <code>#fafbfc</code>, a very soft grey, which is the shipped default.';
$string['defaultcardopacity_desc'] = '<strong>What this does:</strong> how strongly the card colour above is applied, from 0 to 100.<br /><br />100 is the colour exactly as you set it, 50 is halfway to white, 0 is plain white.<br /><br />The card never becomes see-through — this mixes toward white rather than turning the card transparent.';
$string['defaultgreetingname'] = 'there';
$string['defaultherosticky_desc'] = '<strong>What this does:</strong> keeps the banner pinned to the top of the screen while the learner scrolls, instead of letting it scroll away.<br /><br /><em>Example:</em> on a long section page, a pinned banner keeps the progress ring and the next/previous arrows within reach the whole way down.';
$string['defaulthidetimeactivitycards_desc'] = 'Whether the time pill on each activity card, on section pages and in the activity lists is shown, for any course that has not chosen its own.';
$string['defaulthidetimeindex_desc'] = 'Whether the small time pill on each activity row in the course index panel is shown, for any course that has not chosen its own.';
$string['defaulthidetimesectioncards_desc'] = 'Whether the time pill in the corner of each section card, showing the total for that section is shown, for any course that has not chosen its own.';
$string['defaulthidetimetotal_desc'] = 'Whether the total time shown under the course name at the top of the course index panel is shown, for any course that has not chosen its own.';
$string['defaultindexcolour_desc'] = '<strong>What this does:</strong> the background colour of the course index panel.<br /><br /><strong>Leave it empty and it matches the cards automatically</strong>, which is usually what looks right. Only set a colour here if you deliberately want the panel to differ.';
$string['defaultindexheadingcolour_desc'] = 'The background of section headings in the course index, for any course that has not chosen its own. The heading text is white.<br /><br />Leave empty to use the accent colour.';
$string['defaultindexiconcolour_desc'] = 'The colour of the activity icons in the course index, for any course that has not chosen its own.<br /><br />Leave empty to use the accent colour.';
$string['defaultindexopacity_desc'] = '<strong>What this does:</strong> how strongly the course index colour above is applied, from 0 to 100.<br /><br />It does nothing at all while that colour is left empty.';
$string['defaultminutes'] = 'How many minutes each type of activity is assumed to take';
$string['defaultminutes_desc'] = '<strong>What this does:</strong> tells the plugin how long each <strong>type</strong> of activity usually takes. These figures add up to the time shown on each section card and in the course index.<br /><br />Write one line per type, as <code>name=minutes</code>, using Moodle\'s internal module names:<br /><br /><code>assign=30<br />page=5<br />quiz=10<br />forum=10</code><br /><br /><em>A teacher can override any single activity</em> by clicking its time badge with editing turned on, and that override always wins over the figures here.';
$string['defaultplayerheadercolour_desc'] = '<strong>What this does:</strong> the colour of the band at the very top of the course index — the part holding your logo, the course name and the progress ring.<br /><br />A hex colour such as <code>#eceff4</code>. Leave empty for the default shade.';
$string['deletesection'] = 'Delete section';
$string['deletesectionconfirm'] = 'Are you sure you want to delete this section? This action cannot be undone.';
$string['deletesectionnamed'] = 'Delete section: {$a}';
$string['displayascards'] = 'Show sections as cards instead of a long page';
$string['displayascards_desc'] = '<strong>What this does:</strong> shows each section as a card in a grid instead of Moodle\'s usual single long page.<br /><br /><em>Example:</em> a course with six modules becomes six tiles a learner can scan in a second, each showing its own progress and estimated time, rather than a page they have to scroll.';
$string['displayascards_help'] = 'Choose between traditional section view (expandable sections with activities listed) or beautiful card view (modern cards with progress tracking, estimated time, and activity dots).';
$string['displayascardsoption'] = 'Beautiful cards';
$string['displayassections'] = 'Traditional sections';
$string['displaysettings'] = 'Default Display Settings';
$string['displaysettings_desc'] = 'These are the starting values for <strong>new</strong> courses using this format. Every course can change any of them in its own course settings.<br /><br />Where a setting has an <strong>\'apply to ALL existing courses\'</strong> version below it, that is the one that changes courses you have already built.';
$string['duplicatesection'] = 'Duplicate section';
$string['duplicatesectionnamed'] = 'Duplicate section: {$a}';
$string['editsectionnamed'] = 'Edit section: {$a}';
$string['edittime'] = 'Edit estimated time';
$string['edittime_invalid'] = 'Enter a whole number of minutes between 0 and 10000.';
$string['edittime_prompt'] = 'Estimated minutes for this activity. Leave blank to use the site default, or enter 0 to hide the badge.';
$string['edittime_saved'] = 'Estimated time updated';
$string['enabletutor'] = 'Turn the AI Tutor on';
$string['enabletutor_desc'] = '<strong>What this does:</strong> shows the AI Tutor chat bubble to learners and teachers on courses using this format.<br /><br />Switch it off and the tutor disappears everywhere, and nothing is sent to the external service at all.';
$string['error_activitynotfound'] = 'That activity could not be found.';
$string['error_activitynotvisible'] = 'You do not have access to this activity.';
$string['error_addsectionfailed'] = 'The section could not be added.';
$string['error_apinocredits'] = 'Your site has run out of AI credits. Please contact your administrator to top up the account.';
$string['error_apiratelimited'] = 'The AI service is receiving too many requests from this site right now. Please wait a moment and try again.';
$string['error_apiunauthorized'] = 'The AI Tutor could not be authenticated with the AI service. Ask your administrator to check the Site URL and API key in the AI Course Format settings.';
$string['error_bannerfailed'] = 'The banner image could not be generated. Please try again.';
$string['error_bannerfailed_detail'] = 'Banner generation failed. {$a}';
$string['error_bannerhttp'] = 'The image service answered with HTTP {$a->code}: {$a->message}';
$string['error_bannerinvalidimage'] = 'The generated banner image could not be read.';
$string['error_bannernoimage'] = 'The AI service did not return an image.';
$string['error_bannernoreason'] = 'It gave no reason.';
$string['error_bannersavefailed'] = 'The banner image could not be saved to this course.';
$string['error_bannertoolarge'] = 'The generated banner image is too large to be stored.';
$string['error_bannerunreachable'] = 'The image service could not be reached ({$a}). This is usually a network, firewall or TLS problem on the server rather than anything wrong with the course.';
$string['error_cannotdeletegeneral'] = 'The General section cannot be deleted.';
$string['error_cannotdeletesection'] = 'This section cannot be deleted.';
$string['error_cannotduplicategeneral'] = 'The General section cannot be duplicated.';
$string['error_chatlogunavailable'] = 'Your question was answered, but it could not be saved to the chat history.';
$string['error_chatnotfound'] = 'That chat entry could not be found.';
$string['error_correctionfailed'] = 'The correction could not be saved. Please try again.';
$string['error_deletesectionfailed'] = 'The section could not be deleted.';
$string['error_duplicatesectionfailed'] = 'The section could not be duplicated.';
$string['error_guestnotallowed'] = 'Guest users cannot use the AI Tutor.';
$string['error_invalidicon'] = 'That icon is not available.';
$string['error_invalidrating'] = 'That rating value is not valid.';
$string['error_invalidsection'] = 'That section number is not valid.';
$string['error_memoryunavailable'] = 'The AI Tutor could not update what it remembers about this activity.';
$string['error_questionrequired'] = 'Please enter a question.';
$string['error_ratingfailed'] = 'The rating could not be saved. Please try again.';
$string['error_sectionnotfound'] = 'The requested section could not be found.';
$string['error_toomanyrequests'] = 'You have made too many requests. Please wait a moment and try again.';
$string['error_tutordisabled'] = 'The AI Tutor has been turned off for this site by an administrator.';
$string['error_unknownaction'] = 'Unknown action requested.';
$string['estimatedtime'] = 'Est. time';
$string['estimatedtimefor'] = 'Estimated time {$a}';
$string['esttime_h'] = '{$a} hr';
$string['esttime_hm'] = '{$a->hours} hr {$a->mins} min';
$string['esttime_m'] = '{$a} min';
$string['externalservice'] = 'External AI service';
$string['externalservice_desc'] = 'When a user asks the AI Tutor a question, this plugin sends their user ID, their first name, the course ID and name, the question text and the text content of the course to <a href="https://lms-labs.com">lms-labs.com</a>, which is operated by LMS-Labs outside your Moodle site. Nothing is sent unless both a Site ID and an API Key are set below and a user actively asks a question. Set "Enable AI Tutor" to No to stop all transmission. See the plugin privacy policy and Site administration &gt; Users &gt; Privacy and policies &gt; Plugin privacy registry for the full field list.';
$string['forceaccentcolour'] = 'Accent colour — apply to ALL existing courses';
$string['forceaccentcolour_desc'] = 'A hex colour applied to every course using this format, overriding each course\'s own choice. Unlike the default above, this DOES affect existing courses. Leave empty to let each course decide.';
$string['forcecardcolour'] = 'Card colour — apply to ALL existing courses';
$string['forcecardcolour_desc'] = 'A hex colour applied to every course using this format, overriding each course\'s own choice. Unlike the default above, this DOES affect existing courses. Leave empty to let each course decide.';
$string['forcecardopacity'] = 'Card colour strength — apply to ALL existing courses';
$string['forcecardopacity_desc'] = 'A strength from 0 to 100 applied to every course, overriding each course\'s own choice. Set to -1 to let each course decide.';
$string['forceheroimageoverlay'] = 'Banner darkening — apply to ALL existing courses';
$string['forceheroimageoverlay_desc'] = 'Applies one overlay opacity to every course using this format at once, overriding each course\'s own setting. Unlike the site default above, this DOES affect existing courses. Set it to -1 to leave each course alone.';
$string['forceherosticky'] = 'Keep the banner on screen — apply to ALL existing courses';
$string['forceherosticky_desc'] = 'Applies one choice to every course using this format at once, overriding each course\'s own setting. Unlike the default above, this DOES affect existing courses.';
$string['forcehidebreadcrumb'] = 'Breadcrumb trail — apply to ALL existing courses';
$string['forcehidebreadcrumb_desc'] = 'Applies one breadcrumb setting to every course using this format at once, overriding each course\'s own choice. Leave as \'Let each course decide\' to respect the per-course setting.';
$string['forcehidebreadcrumb_leave'] = 'Let each course decide';
$string['forcehidefooter'] = 'Site footer — apply to ALL existing courses';
$string['forcehidefooter_desc'] = 'Applies one footer setting to every course using this format at once, overriding each course\'s own choice. Unlike the site default above, this DOES affect existing courses. Leave as \'Let each course decide\' to respect the per-course setting.';
$string['forcehidegeneral'] = 'Hide the General section — apply to ALL existing courses';
$string['forcehidegeneral_desc'] = '<strong>What this does:</strong> forces one choice onto <strong>every</strong> course using this format, right now, ignoring what each course has set for itself.<br /><br /><em>Example:</em> set it to \'Hide from everyone\' and the General section disappears from all 40 of your courses the moment you save. Set it back to \'Let each course decide\' and each course returns to its own setting — nothing is lost.<br /><br />Use this when you want one rule for the whole site. Use the setting above instead when you only want to change what new courses start with.';
$string['forcehidesecondarynav'] = 'Course tabs — apply to ALL existing courses';
$string['forcehidesecondarynav_desc'] = 'Applies one choice to <strong>every</strong> course using this format at once, ignoring what each course has chosen for itself.<br /><br /><strong>Why this exists.</strong> The "Course navigation tabs" setting above only affects <em>brand new</em> courses. A course that has ever had its settings saved keeps its own stored value and will never pick up a change you make to the default. This override is the only way to change courses that already exist.<br /><br /><em>Example:</em> you have 200 courses and want the tabs gone from all of them. Setting the default above does nothing. Setting this to "Hide from everyone" does it immediately.<br /><br />Choose <strong>Follow each course\'s own setting</strong> if you would rather decide course by course.';
$string['forcehidesecondarynav_follow'] = 'Follow each course\'s own setting';
$string['forcehidetimeactivitycards'] = 'Time on activity cards — apply to ALL existing courses';
$string['forcehidetimeactivitycards_desc'] = 'Applies one choice to every course using this format at once, overriding each course\'s own setting. Unlike the default above, this DOES affect existing courses.';
$string['forcehidetimeindex'] = 'Time in the course index — apply to ALL existing courses';
$string['forcehidetimeindex_desc'] = 'Applies one choice to every course using this format at once, overriding each course\'s own setting. Unlike the default above, this DOES affect existing courses.';
$string['forcehidetimesectioncards'] = 'Time on section cards — apply to ALL existing courses';
$string['forcehidetimesectioncards_desc'] = 'Applies one choice to every course using this format at once, overriding each course\'s own setting. Unlike the default above, this DOES affect existing courses.';
$string['forcehidetimetotal'] = 'Total course time — apply to ALL existing courses';
$string['forcehidetimetotal_desc'] = 'Applies one choice to every course using this format at once, overriding each course\'s own setting. Unlike the default above, this DOES affect existing courses.';
$string['forceimmersive'] = 'Hide the site logo band — apply to ALL existing courses';
$string['forceimmersive_desc'] = 'Applies one choice to every course using this format at once, overriding each course\'s own setting. Unlike the default above, this DOES affect existing courses.';
$string['forceindexcolour'] = 'Course index colour — apply to ALL existing courses';
$string['forceindexcolour_desc'] = 'A hex colour applied to every course using this format, overriding each course\'s own choice. Unlike the default above, this DOES affect existing courses. Leave empty to let each course decide.';
$string['forceindexheadingcolour'] = 'Section heading colour — apply to ALL existing courses';
$string['forceindexheadingcolour_desc'] = 'A hex colour applied to every course using this format, overriding each course\'s own choice. Unlike the default above, this DOES affect existing courses.';
$string['forceindexiconcolour'] = 'Activity icon colour — apply to ALL existing courses';
$string['forceindexiconcolour_desc'] = 'A hex colour applied to every course using this format, overriding each course\'s own choice. Unlike the default above, this DOES affect existing courses.';
$string['forceindexopacity'] = 'Course index colour strength — apply to ALL existing courses';
$string['forceindexopacity_desc'] = 'A strength from 0 to 100 applied to every course. Set to -1 to let each course decide.';
$string['forceindexstate'] = 'Course index on first entry — apply to ALL existing courses';
$string['forceindexstate_desc'] = 'Applies one choice to every course using this format at once, overriding each course\'s own setting. Still applied only once per user per course.';
$string['forceplayerheadercolour'] = 'Course index header colour — apply to ALL existing courses';
$string['forceplayerheadercolour_desc'] = 'A hex colour applied to every course using this format, overriding each course\'s own choice. Unlike the default above, this DOES affect existing courses. Leave empty to let each course decide.';
$string['forceplayerindex'] = 'Course player sidebar — apply to ALL existing courses';
$string['forceplayerindex_desc'] = 'Applies one choice to every course using this format at once, overriding each course\'s own setting. Unlike the default above, this DOES affect existing courses.';
$string['generatebannerimage'] = 'Generate AI banner image';
$string['gotocourse'] = 'Course Home';
$string['gradefraction'] = '{$a->current}/{$a->max}';
$string['gradefractionnone'] = '-/{$a}';
$string['grades'] = 'My Grades';
$string['heroattop'] = 'Put the banner above the course tabs';
$string['heroattop_desc'] = '<strong>What this does:</strong> moves the banner above the course tabs, so it is the very first thing on the page rather than sitting underneath Moodle\'s page furniture.<br /><br /><em>Example:</em> with this on, a learner opening a course sees the course image and their progress immediately, and the tabs come after it.';
$string['heroattop_help'] = 'Moves the hero banner above Moodle\'s page header and course navigation tabs, so it is the first thing on the page.

Moodle renders the page header before a course format gets a chance to output anything, so this is done by moving the banner in the browser after the page loads. Two consequences worth knowing:

* It is switched off automatically while **edit mode** is on, so the page header controls stay where you expect them.
* On a theme with an unusual page structure it simply does nothing and the banner stays where Moodle put it. It will not break the page.

Turn it off if your theme puts something above the banner that you need to keep there.';
$string['herobanneralign'] = 'Hero banner alignment';
$string['herobanneralign_center'] = 'Centre';
$string['herobanneralign_help'] = 'Choose whether the hero banner is centred or left-aligned on the page. Left-aligned is useful when you want the banner to line up with the left edge of your page content.';
$string['herobanneralign_left'] = 'Left';
$string['herobannerfade'] = 'Tint the banner when a course has no image';
$string['herobannerfade_desc'] = 'How much of the accent colour is mixed into the hero banner background when the course has no banner image, as a percentage. 0 is the plain card surface, 3 is a barely-there tint (the default), 8 is noticeably coloured, 16 and above is a solid accent panel. Values are clamped to 0-100. This has no effect when a banner image is set, because the image covers the background.';
$string['herobannerfade_help'] = 'A whole number from 0 to 100.

* **0** &mdash; no tint at all, the banner matches the cards below it.
* **3** &mdash; the default: the palest hint of your accent colour.
* **8** &mdash; clearly coloured but still light behind the title.
* **16+** &mdash; a solid accent panel.

This only applies when the course has **no banner image**; with an image the background is covered by the picture. Text contrast stays above the WCAG AA threshold at every value in the range.';
$string['herobannerheight'] = 'Hero banner height';
$string['herobannerheight_help'] = 'The **minimum** height of the hero banner in pixels, for courses with no banner image. The banner never gets shorter than this, and grows past it if the content needs more room &mdash; so it is a floor, not a cap, and a long course name is never clipped.

The default is 110, which matches the compact banner layout. Set it to 0 to let the banner size itself entirely from its content. Larger values (160-240) give a plain banner more presence.

Courses **with** a banner image ignore this: an image banner is sized by its own layout so the picture always has a sensible aspect ratio.';
$string['herobannerwidth'] = 'Hero banner width';
$string['herobannerwidth_help'] = 'Set the maximum width of the hero banner in pixels to match your theme\'s content width. For example, if your Moodle theme has a 1200px content area, set this to 1200. Set to 0 (default) for full width up to 1400px.';
$string['heroimageoverlay'] = 'How much to darken the banner image behind the text';
$string['heroimageoverlay_desc'] = '<strong>What this does:</strong> darkens the banner image so the white course title on top of it stays readable. 0 means no darkening at all; 100 is almost black.<br /><br /><em>Why it matters:</em> a pale photograph — snow, a bright sky, a white background — makes white text vanish. The overlay is what stops that.<br /><br /><em>Suggested:</em> around 45 for a normal photograph. Below 25 only if your images are already dark. Above 70 the picture is barely visible.';
$string['heroimageoverlay_help'] = 'How dark the overlay between the banner **image** and the banner text is, as a percentage. Leave it at **-1** to follow the site-wide "Banner overlay strength" setting.

* **-1** &mdash; follow the site setting (Light 55, Medium 62, Strong 72).
* **0** &mdash; no overlay at all. The image shows at full strength.
* **55** &mdash; the lightest value at which white text still clears the WCAG AA contrast threshold on a worst-case near-white image.
* **62** &mdash; the default.
* **72** &mdash; heavy; use it if your banner images are busy or pale.

The overlay is a single flat tone, not a gradient, so it dims the whole image evenly. Below about 55 the title can become hard to read over a light photo &mdash; check your own images before going lower.

This has no effect on courses with no banner image.';
$string['herosticky'] = 'Keep the banner on screen while scrolling';
$string['herosticky_help'] = 'Whether the course banner stays at the top of the screen as the page scrolls.

* **Stays at the top** — the banner and its navigation remain reachable however far down a learner is. This is the default.
* **Scrolls away** — the banner behaves like the rest of the page.

Sticky suits a course a learner works through, where the progress ring and the next/previous controls are worth keeping to hand.

*When you might turn it off:* on a short course the banner never leaves the screen anyway, and on a small laptop it takes height from the content for the whole visit.';
$string['herosticky_no'] = 'Scrolls away with the page';
$string['herosticky_yes'] = 'Stays at the top while scrolling';
$string['hidebreadcrumb'] = 'Breadcrumb trail (the \'Home / My courses / …\' line above the page)';
$string['hidebreadcrumb_desc'] = 'Whether Moodle\'s breadcrumb trail is shown on this course\'s pages. The activity hero already names the course, the section and the activity, so on many sites the breadcrumb repeats all three directly beneath it. This hides the trail only — it is not an access control, and every page in it stays reachable. It is never hidden while editing.';
$string['hidebreadcrumb_help'] = 'The breadcrumb is the small trail of links near the top of the page, like *Home ▸ My courses ▸ Workplace Safety ▸ Section 1*.

On this format the banner already tells you the course, the section and the activity you are in — so the breadcrumb usually repeats all three, immediately below it.

* **Show** — leave it as your theme draws it.
* **Hide from students** — teachers keep it, students do not.
* **Hide from everyone** — nobody sees it while reading the course.

This hides a trail, it does not lock anything: every page it pointed at is still reachable, and still checks permissions in the normal way. It returns in Edit mode.';
$string['hidefooter'] = 'Site footer (the block of links at the very bottom of every page)';
$string['hidefooter_desc'] = '<strong>What the footer is:</strong> the block of links and site information at the very bottom of every Moodle page.<br /><br /><strong>What this does:</strong> hides it on course pages only, so a course ends with its content rather than with the site\'s links.<br /><br />It is never hidden while editing, so a teacher can still reach everything.';
$string['hidefooter_help'] = 'The site footer is the strip at the very bottom of every page, usually holding a copyright line, contact details or policy links.

On a course page it is the last thing a learner needs and the first thing between them and the end of the content.

* **Show** — leave it as your theme draws it.
* **Hide from students** — teachers keep it, students do not.
* **Hide from everyone** — nobody sees it while reading the course.

The editing toolbar that appears at the bottom of the screen while you build a course is a different thing and is **never** hidden, so Move, Duplicate and Delete always stay available. The footer returns in Edit mode.';
$string['hidefromothers'] = 'Hide section';
$string['hidegeneral'] = 'Hide the General section (Moodle\'s \'Section 0\', usually just Announcements)';
$string['hidegeneral_desc'] = '<strong>What the General section is:</strong> every Moodle course has a first section called \'General\' (Moodle calls it Section 0). Usually it holds nothing but the Announcements forum.<br /><br />On a course whose real content starts at Section 1, that empty section is one more thing a learner scrolls past before reaching what they came for.<br /><br /><strong>What this does:</strong> hides it from the course index and from the section cards.<br /><br /><em>It always comes back when editing is turned on</em>, so a teacher can still post announcements.';
$string['hidegeneral_help'] = 'Section 0 of a Moodle course is called "General". It usually holds only the Announcements forum, and on a course whose real content starts at Section 1 it is a heading learners read past before reaching anything they came for.

* **Show** - leave it in the course index and the cards.
* **Hide from students** - teachers still see it, students do not.
* **Hide from everyone** - nobody sees it while reading the course.

This hides the section from view. It does not delete anything and does not stop announcements being posted or emailed - the forum still works exactly as before.

It always comes back in edit mode, so a teacher can still reach it.';
$string['hidesecondarynav'] = 'Course tabs (Course, Settings, Participants, Grades, Reports)';
$string['hidesecondarynav_all'] = 'Hide from everyone';
$string['hidesecondarynav_desc'] = '<strong>What the course tabs are:</strong> the row reading <em>Course, Settings, Participants, Grades, Reports, More</em> that Moodle puts under the course name.<br /><br /><strong>What this does:</strong> hides that row.<br /><br /><em>Why you might:</em> almost none of it is for learners — Settings, Reports and Participants are teacher tools. \'Hide from students\' keeps the tabs for staff and clears them away for everyone else.';
$string['hidesecondarynav_help'] = 'The Course navigation tabs are the row of links Moodle puts above your course content: Course, Settings, Participants, Grades, Reports and More.

This format already gives you the same places to go — the banner has quick links, and the course index lists every section and activity — so the tabs are often just a duplicate row taking up space.

* **Show** — leave them exactly as your theme draws them.
* **Hide from students** — teachers and anyone who can edit the course still see them. Students do not.
* **Hide from everyone** — nobody sees them while simply reading the course.

**They always come back when you turn Edit mode on**, so you never lose them while you are building the course.

*Example:* a short induction course looks much cleaner with them set to Hide from everyone. A large course where teachers constantly check Grades might prefer Hide from students.';
$string['hidesecondarynav_show'] = 'Show';
$string['hidesecondarynav_students'] = 'Hide from students';
$string['hidetime_hide'] = 'Hide';
$string['hidetimeactivitycards'] = 'Show the estimated time on activity cards';
$string['hidetimeactivitycards_help'] = 'Whether to show the time pill on each activity card, on section pages and in the activity lists.

* **Show** - display it.
* **Hide** - remove it.

Hiding a time removes only that one; the others have their own settings, so you can keep times where they help and drop them where they do not.

*Tip:* estimates are only useful if they are roughly right. If a course has activities whose durations have not been set, hiding the times reads better than showing figures nobody trusts.';
$string['hidetimeindex'] = 'Show the estimated time in the course index';
$string['hidetimeindex_help'] = 'Whether to show the small time pill on each activity row in the course index panel.

* **Show** - display it.
* **Hide** - remove it.

Hiding a time removes only that one; the others have their own settings, so you can keep times where they help and drop them where they do not.

*Tip:* estimates are only useful if they are roughly right. If a course has activities whose durations have not been set, hiding the times reads better than showing figures nobody trusts.';
$string['hidetimesectioncards'] = 'Show the estimated time on section cards';
$string['hidetimesectioncards_help'] = 'Whether to show the time pill in the corner of each section card, showing the total for that section.

* **Show** - display it.
* **Hide** - remove it.

Hiding a time removes only that one; the others have their own settings, so you can keep times where they help and drop them where they do not.

*Tip:* estimates are only useful if they are roughly right. If a course has activities whose durations have not been set, hiding the times reads better than showing figures nobody trusts.';
$string['hidetimetotal'] = 'Show the total course time in the course index';
$string['hidetimetotal_help'] = 'Whether to show the total time shown under the course name at the top of the course index panel.

* **Show** - display it.
* **Hide** - remove it.

Hiding a time removes only that one; the others have their own settings, so you can keep times where they help and drop them where they do not.

*Tip:* estimates are only useful if they are roughly right. If a course has activities whose durations have not been set, hiding the times reads better than showing figures nobody trusts.';
$string['icon_alert_triangle'] = 'Warning triangle';
$string['icon_award'] = 'Award';
$string['icon_book'] = 'Book';
$string['icon_book_open'] = 'Open book';
$string['icon_briefcase'] = 'Briefcase';
$string['icon_calendar'] = 'Calendar';
$string['icon_check_circle'] = 'Tick in a circle';
$string['icon_clipboard'] = 'Clipboard';
$string['icon_clock'] = 'Clock';
$string['icon_file_text'] = 'Text document';
$string['icon_flag'] = 'Flag';
$string['icon_folder'] = 'Folder';
$string['icon_graduation'] = 'Graduation cap';
$string['icon_hard_hat'] = 'Hard hat';
$string['icon_heart'] = 'Heart';
$string['icon_help_circle'] = 'Question mark in a circle';
$string['icon_home'] = 'Home';
$string['icon_info'] = 'Information';
$string['icon_laptop'] = 'Laptop';
$string['icon_layers'] = 'Layers';
$string['icon_lightbulb'] = 'Light bulb';
$string['icon_lock'] = 'Padlock';
$string['icon_map_pin'] = 'Map pin';
$string['icon_message'] = 'Message';
$string['icon_monitor'] = 'Monitor';
$string['icon_package'] = 'Package';
$string['icon_pen'] = 'Pen';
$string['icon_play_circle'] = 'Play button';
$string['icon_rocket'] = 'Rocket';
$string['icon_settings'] = 'Settings';
$string['icon_shield'] = 'Shield';
$string['icon_shield_check'] = 'Shield with a tick';
$string['icon_star'] = 'Star';
$string['icon_target'] = 'Target';
$string['icon_trophy'] = 'Trophy';
$string['icon_user'] = 'Person';
$string['icon_users'] = 'People';
$string['icon_wrench'] = 'Spanner';
$string['icon_zap'] = 'Lightning bolt';
$string['iconcategory_achievement'] = 'Achievement';
$string['iconcategory_education'] = 'Education';
$string['iconcategory_general'] = 'General';
$string['iconcategory_numbers'] = 'Numbers';
$string['iconcategory_people'] = 'People';
$string['iconcategory_safety'] = 'Safety & compliance';
$string['iconcategory_work'] = 'Work & industry';
$string['iconnumber'] = 'Number {$a}';
$string['iconsaved'] = 'Icon saved';
$string['iconsaveerror'] = 'Error saving icon';
$string['immersive'] = 'Hide the site logo band (the tall strip carrying your logo)';
$string['immersive_desc'] = '<strong>What this does:</strong> hides the tall band at the top of the page that carries your site logo and site links — usually well over a hundred pixels of height on every single page.<br /><br />The compact bar above it stays, so notifications, the user menu and the Edit mode toggle are all still there.<br /><br /><em>Example:</em> on a laptop this is roughly one more paragraph of course content visible without scrolling.';
$string['immersive_help'] = 'Most themes put two bands across the top of every page:

1. a thin bar with notifications, messages and your profile menu
2. a taller band underneath holding the site logo and links like Home and Dashboard

This setting hides the **second** band only, which is often 100–150 pixels of height on every single course page.

* **Show** — leave both bands alone.
* **Hide from students** — teachers keep the logo band, students do not.
* **Hide from everyone** — nobody sees the logo band while reading the course.

**The thin bar with your profile and the Edit mode switch is never hidden**, so nothing is taken away — and if you turn on the player sidebar, your logo appears there instead.

The band always comes back in Edit mode.';
$string['indexcolour'] = 'Background colour of the course index';
$string['indexcolour_help'] = 'The background of the course index panel - the area listing the sections and activities.

**Leave this empty and it follows the card colour**, so the panel and the cards match without you setting the same value twice. That is the default.

Set a hex colour here only if you want the panel to differ from the cards.

*Tip:* the band at the top of the panel, holding the logo and progress ring, has its own setting - Course index header colour.';
$string['indexheadingcolour'] = 'Colour of the section headings in the course index';
$string['indexheadingcolour_help'] = 'The background of the section headings in the course index. The heading text is white, so this needs to be dark enough to read against.

Leave empty to use your accent colour, which follows the theme\'s primary if you have not set one. That is the default.

*Tip:* the headings are what break a long list into sections, so a colour with some weight works better here than a pale one.';
$string['indexiconcolour'] = 'Colour of the activity icons in the course index';
$string['indexiconcolour_help'] = 'The colour of the small activity icons in the course index.\n\nLeave empty to use your accent colour, which follows the theme\'s primary if you have not set one. That is the default, and it matches the section headings so the course\'s colour appears in both places.\n\nThe icons have no background of their own - the shape itself is coloured, so a mid to dark tone reads best against the panel behind it.\n\n*Tip:* if you would rather the icons blended with the activity names instead of standing out, set this to your body text colour.';
$string['indexopacity'] = 'How strong the course index colour is';
$string['indexopacity_help'] = 'How strongly the course index colour is applied, 0 to 100.

* **100** - the colour exactly as set.
* **50** - halfway between your colour and white.
* **0** - plain white.

This has no effect while Course index colour is empty, because the panel is then following the card colour and its strength instead.

The panel stays fully opaque at every setting - the strength mixes toward white rather than making it see-through.';
$string['indexstate'] = 'How the course index looks when a student first opens the course';
$string['indexstate_collapsed'] = 'Start collapsed';
$string['indexstate_desc'] = '<strong>What this does:</strong> decides whether the course index starts open or closed the very first time a person opens a course.<br /><br />After that first visit, whatever they choose themselves is remembered and this setting stays out of the way. It would be rude to keep reopening a menu somebody has closed.<br /><br /><em>Example:</em> \'Start collapsed\' gives a learner the full width for reading on their first visit; if they open the menu, it stays open next time.';
$string['indexstate_help'] = 'This decides whether the course index panel is already open the first time someone enters the course.

Moodle normally opens it and then remembers whatever that person last chose, across the whole site.

* **Remember the user\'s choice** — leave Moodle\'s normal behaviour alone.
* **Start collapsed** — closed on first entry, so the course content gets the full width of the screen.
* **Start open** — open on first entry, so learners can see the whole course straight away.

**This only sets the starting point.** After that first visit the learner\'s own choice is respected — if they open the panel, it stays open next time. A setting that forced it every page load would be fighting the person using it.

*Example:* a course meant to be read straight through suits Start collapsed. A reference course people dip in and out of suits Start open.';
$string['indexstate_open'] = 'Start open';
$string['indexstate_remember'] = 'Remember the user\'s choice';
$string['inprogress'] = 'In Progress';
$string['js_completionerror'] = 'Failed to update completion status';
$string['js_done'] = 'Done';
$string['js_iconremoved'] = 'Icon removed';
$string['js_iconsfound'] = '{$a} icons found';
$string['js_progressannounce'] = 'Course progress: {$a}%';
$string['js_sectionadded'] = 'Section added';
$string['js_sectionadderror'] = 'Failed to add section';
$string['js_sectiondeleted'] = 'Section deleted successfully';
$string['js_sectiondeleteerror'] = 'Failed to delete section';
$string['js_sectionduplicated'] = 'Section duplicated successfully';
$string['js_sectionduplicateerror'] = 'Failed to duplicate section';
$string['labelseparator'] = ', ';
$string['listseparator'] = ' • ';
$string['markasdonefor'] = 'Mark {$a} as done';
$string['markasdoneundo'] = 'Mark {$a} as not done';
$string['minutesfallback'] = 'Minutes to use for anything not listed above';
$string['minutesfallback_desc'] = '<strong>What this does:</strong> the time used for any activity type you have not listed in the box above.<br /><br />Keep it small. It is a guess, and a confident-looking wrong number is worse than a modest one. <em>Example:</em> 5 minutes.';
$string['minutesperquestion'] = 'Minutes to allow per quiz question';
$string['minutesperquestion_desc'] = '<strong>What this does:</strong> works out how long a quiz should take by multiplying this number by the number of questions in it.<br /><br /><em>Example:</em> at 1 minute per question, a 10-question quiz shows \'10 min\' and a 40-question exam shows \'40 min\'.<br /><br />It is done per question rather than as one flat figure because \'a quiz\' is not one length — a five-question check and a final exam are very different things.';
$string['nactivities'] = '{$a} activities';
$string['nextactivity'] = 'Next activity';
$string['nextactivitynamed'] = 'Next activity: {$a}';
$string['nextsection'] = 'Next section';
$string['nextsectionnamed'] = 'Next section: {$a}';
$string['noactivitiesinsection'] = 'This section is empty. Add activities to get started.';
$string['nocompletion'] = 'No completion tracking';
$string['notstarted'] = 'Not Started';
$string['nsections'] = '{$a} modules';
$string['oneactivity'] = '1 activity';
$string['onesection'] = '1 module';
$string['page-course-view-aicourse'] = 'Any course main page in AI Course format';
$string['page-course-view-aicourse-x'] = 'Any course page in AI Course format';
$string['percentcomplete'] = '{$a}% complete';
$string['percentvalue'] = '{$a}%';
$string['player_closeindex'] = 'Close course index';
$string['player_completedon'] = 'Completed {$a}';
$string['player_dashboard'] = 'Dashboard';
$string['player_done'] = 'Completed';
$string['player_gradeachieved'] = 'Grade {$a->grade} / {$a->max} ({$a->percent}%)';
$string['player_home'] = 'Home';
$string['player_mycourses'] = 'My courses';
$string['player_navlabel'] = 'Site navigation';
$string['player_notdone'] = 'Not completed';
$string['player_progress'] = '{$a}% of this course complete';
$string['player_requires'] = 'To complete this activity';
$string['playerheadercolour'] = 'Colour of the band at the top of the course index';
$string['playerheadercolour_help'] = 'This is the background of the band at the very top of the course index — the part holding your logo, the course name, the progress ring and the total time.

The rest of the panel is white, so this band is what separates the course information from the list of activities beneath it.

Enter a hex colour like <code>#eceff4</code>. Leave it empty to use the site setting, and if that is empty too, a light grey.

*Tip:* keep it subtle. This is a background behind text, not a feature colour — something close to white usually reads best.';
$string['playerindex'] = 'Course player sidebar (turns the plain side menu into a progress tracker)';
$string['playerindex_desc'] = '<strong>What this does:</strong> turns Moodle\'s plain course index into a progress sidebar.<br /><br />You get the course name, a progress ring, the total time the course should take, a link back to My courses, and one row per activity showing how long it takes and whether it is finished.<br /><br /><em>Example:</em> a learner opening Module 2 sees at a glance that they are 63% through the course and that three activities in this module are still outstanding.<br /><br />It decorates Moodle\'s own course index rather than replacing it, so drag-and-drop and everything else a teacher expects still works.';
$string['playerindex_help'] = 'The course index is the panel that slides out on the left, listing every section and activity in the course.

Moodle\'s version is a plain list of links. The **player sidebar** turns it into something a learner can plan with:

* your logo at the top
* the course name, a progress ring, and the total time the course takes
* every activity showing its own icon, how long it takes, and a green tick once it is finished

It still behaves like Moodle\'s course index underneath — sections still collapse, drag and drop still works while editing, and the page you are on is still highlighted.

* **Plain course index** — Moodle\'s normal list.
* **Player sidebar** — the richer version described above.';
$string['playerindex_off'] = 'Plain course index';
$string['playerindex_on'] = 'Player sidebar';
$string['playerindex_site'] = 'Use the site default';
$string['playerlogo'] = 'Logo shown at the top of the course index';
$string['playerlogo_desc'] = '<strong>What this does:</strong> puts your own logo at the top of the course index, instead of the site logo.<br /><br /><em>Useful when</em> a course is branded for a client rather than for the institution hosting it.<br /><br />Leave it empty to fall back to the site logo. Any web image format works and it is scaled to fit, so a wide logo is fine.';
$string['pluginname'] = 'AI Course Format';
$string['previousactivity'] = 'Previous activity';
$string['previousactivitynamed'] = 'Previous activity: {$a}';
$string['previoussection'] = 'Previous section';
$string['previoussectionnamed'] = 'Previous section: {$a}';
$string['privacy:metadata:format_aicourse_actminutes'] = 'Per-activity estimated durations set by a teacher.';
$string['privacy:metadata:format_aicourse_actminutes:cmid'] = 'The activity the estimate applies to.';
$string['privacy:metadata:format_aicourse_actminutes:courseid'] = 'The course the activity belongs to.';
$string['privacy:metadata:format_aicourse_actminutes:minutes'] = 'The estimated duration in minutes.';
$string['privacy:metadata:format_aicourse_actminutes:timemodified'] = 'When the estimate was last changed.';
$string['privacy:metadata:format_aicourse_actminutes:usermodified'] = 'The user who last set this estimate.';
$string['privacy:metadata:format_aicourse_ai_memory'] = 'A short rolling summary, held per user and per activity, of the topics the user has previously asked the AI Tutor about. It is used to give continuity between tutoring sessions and never stores answers to assessed work.';
$string['privacy:metadata:format_aicourse_ai_memory:activityid'] = 'The ID of the course module the memory relates to.';
$string['privacy:metadata:format_aicourse_ai_memory:courseid'] = 'The ID of the course the memory relates to.';
$string['privacy:metadata:format_aicourse_ai_memory:memory'] = 'The summary of the topics the user has previously asked about.';
$string['privacy:metadata:format_aicourse_ai_memory:timeupdated'] = 'The time the memory was last updated.';
$string['privacy:metadata:format_aicourse_ai_memory:userid'] = 'The ID of the user the memory belongs to.';
$string['privacy:metadata:format_aicourse_chats'] = 'A record of every question asked of the AI Tutor, the answer the AI gave, and any correction a teacher later applied to that answer.';
$string['privacy:metadata:format_aicourse_chats:activityid'] = 'The ID of the course module the question was asked from, or 0 if it was asked from the course home page.';
$string['privacy:metadata:format_aicourse_chats:correctedby'] = 'The ID of the teacher who wrote the correction.';
$string['privacy:metadata:format_aicourse_chats:correction'] = 'A correction written by a teacher to replace or amend the AI response.';
$string['privacy:metadata:format_aicourse_chats:courseid'] = 'The ID of the course the question was asked in.';
$string['privacy:metadata:format_aicourse_chats:locked'] = 'Whether the related activity had already been submitted, in which case the AI Tutor answers in reflection mode only.';
$string['privacy:metadata:format_aicourse_chats:question'] = 'The full text of the question the user asked the AI Tutor.';
$string['privacy:metadata:format_aicourse_chats:questionslot'] = 'The quiz question slot number the question relates to, where the user asked from within a quiz.';
$string['privacy:metadata:format_aicourse_chats:rating'] = 'The rating the user gave the AI response: helpful, not helpful, or unrated.';
$string['privacy:metadata:format_aicourse_chats:refused'] = 'Whether the AI declined to answer in order to protect academic integrity.';
$string['privacy:metadata:format_aicourse_chats:response'] = 'The full text of the answer the AI Tutor returned.';
$string['privacy:metadata:format_aicourse_chats:timecorrected'] = 'The time the correction was written.';
$string['privacy:metadata:format_aicourse_chats:timecreated'] = 'The time the question was asked.';
$string['privacy:metadata:format_aicourse_chats:userid'] = 'The ID of the user who asked the question.';
$string['privacy:metadata:lms_labs_ai'] = 'To generate a tutor response, the AI Course Format sends the user\'s question together with the surrounding course content to the LMS-Labs AI service (lms-labs.com), which is operated outside Moodle. No data is sent unless a Site ID and API Key have been configured by an administrator and a user actively asks the AI Tutor a question. Setting "Enable AI Tutor" to No stops all transmission.';
$string['privacy:metadata:lms_labs_ai:activityname'] = 'The name of the activity the user was viewing when the question was asked.';
$string['privacy:metadata:lms_labs_ai:coursecontext'] = 'The text content of the course (section names, activity names, descriptions, slide text and quiz question wording) that the AI needs in order to answer in context. Correct answers, answer feedback and essay marking guides are only included if the site setting \'Share assessment answers with the AI Tutor\' permits it, either for the whole site or, where the administrator has delegated the decision, for this individual course. It is set to never share by default.';
$string['privacy:metadata:lms_labs_ai:courseid'] = 'The ID of the course the question was asked in.';
$string['privacy:metadata:lms_labs_ai:coursename'] = 'The full name of the course the question was asked in.';
$string['privacy:metadata:lms_labs_ai:priortutormemory'] = 'The stored summary of the topics this user previously asked about in this activity.';
$string['privacy:metadata:lms_labs_ai:question'] = 'The full text of the question the user asked.';
$string['privacy:metadata:lms_labs_ai:questionslot'] = 'The quiz question slot number the user was working on, if any.';
$string['privacy:metadata:lms_labs_ai:questiontext'] = 'The text of the quiz question the user was working on, if any.';
$string['privacy:metadata:lms_labs_ai:sectionname'] = 'The name of the course section the user was viewing when the question was asked.';
$string['privacy:metadata:lms_labs_ai:siteurl'] = 'The URL of this Moodle site, used by the service to identify the subscription the request belongs to.';
$string['privacy:metadata:lms_labs_ai:studentname'] = 'The first name of the user asking the question, so that the AI can address them by name.';
$string['privacy:metadata:lms_labs_ai:userid'] = 'The ID of the user asking the question.';
$string['privacy:metadata:preference:tourseen'] = 'Whether this user has seen or dismissed the first-run tour of the course format.';
$string['privacy:path:chats'] = 'AI Tutor conversations';
$string['privacy:path:corrections'] = 'AI Tutor corrections written by you';
$string['privacy:path:memory'] = 'AI Tutor memory';
$string['removebannerimage'] = 'Remove banner image';
$string['removeicon'] = 'Remove icon';
$string['returntosectionnamed'] = 'Return to section: {$a}';
$string['scrimstrength'] = 'Banner darkening (older setting — use the one above instead)';
$string['scrimstrength_desc'] = 'How much the hero banner image is darkened behind the course title.<br /><br />The overlay exists so white text stays readable over any image, including a near-white one. <strong>Strong</strong> was the only behaviour before 2.1.12 and is heavier than accessibility requires — its foot reaches 16.2:1 against white text where WCAG AA asks for 4.5:1 — which visibly crushes darker photographs. <strong>Medium</strong> (the default) and <strong>Light</strong> relax the top and bottom of the gradient only; the band where the title and summary sit never drops below 4.9:1 on a worst-case white image, so all three remain accessible.';
$string['scrimstrength_light'] = 'Light - image most visible';
$string['scrimstrength_medium'] = 'Medium (recommended)';
$string['scrimstrength_strong'] = 'Strong - pre-2.1.12 appearance';
$string['searchicons'] = 'Search icons…';
$string['section0name'] = 'General';
$string['sectionactivitiesregion'] = '{$a} activities';
$string['sectionname'] = 'Section';
$string['sectionnotfound'] = 'Section not found.';
$string['sectionnumber'] = 'Section {$a}';
$string['sectionprogress'] = 'Section progress';
$string['selecticon'] = 'Select icon';
$string['settingsui_about'] = 'About this plugin';
$string['settingsui_all'] = 'All';
$string['settingsui_appliestoall'] = 'Applies to every course that already exists';
$string['settingsui_course'] = 'Course';
$string['settingsui_filterby'] = 'Filter:';
$string['settingsui_hide'] = 'Hide';
$string['settingsui_new'] = 'Recently added';
$string['settingsui_nomatches'] = 'No settings match that search. Try a shorter word, or choose All settings.';
$string['settingsui_other'] = 'Other';
$string['settingsui_search'] = 'Search settings…';
$string['settingsui_setting'] = 'setting';
$string['settingsui_settings'] = 'settings';
$string['settingsui_show'] = 'Show';
$string['shareanswers_always'] = 'Always share, in every course';
$string['shareanswers_never'] = 'Never share';
$string['shareanswers_percourse'] = 'Let each course decide';
$string['shareassessmentanswers'] = 'Send quiz and knowledge-check answers to the AI Tutor';
$string['shareassessmentanswers_desc'] = 'The AI Tutor answers from an index of each course that is sent to the external AI service. This setting decides whether that index also includes the correct answers to quiz and knowledge check questions, the answer options, the per-option feedback, the slide answers and the essay marking guide ("information for graders") that learners never see.<br /><br /><strong>Never share</strong> (the default) keeps every answer key inside your Moodle site.<br /><strong>Always share, in every course</strong> sends answer keys for every course on this site.<br /><strong>Let each course decide</strong> keeps answer keys back unless a teacher turns "Share assessment answers with the AI Tutor" on in that individual course\'s settings; this setting is always the ceiling, so a course can never share what you have not permitted here.<br /><br /><strong>Leave this on "Never share" unless you have a specific reason to change it, and have confirmed that your AI provider agreement permits assessment answers to leave your Moodle site.</strong> With it off the tutor still receives every question\'s wording, so it can discuss the topic and point learners at the right material - it simply does not hold the answer key.';
$string['shareassessmentanswers_help'] = 'The AI Tutor answers from an index of this course that is sent to an external AI service. When this is set to Yes, that index also includes the correct answers to this course\'s quiz and knowledge check questions, the answer options, the per-option feedback, the slide answers and the essay marking guide ("information for graders") that learners never see.<br /><br /><strong>This setting only takes effect if your site administrator has set "Share assessment answers with the AI Tutor" to "Let each course decide". On a site set to "Never share" nothing is shared whatever you choose here, and on a site set to "Always share" answers are shared whatever you choose here.</strong><br /><br /><strong>Only choose Yes if you have a specific teaching reason - a revision course, for example - and have confirmed with your site administrator that your AI provider agreement permits assessment answers to leave your Moodle site.</strong> With it set to No the tutor still receives every question\'s wording, so it can discuss the topic and point learners at the right material - it simply does not hold the answer key.';
$string['showactivitiesoncards'] = 'List the activities on each section card';
$string['showactivitiesoncards_desc'] = '<strong>What this does:</strong> lists the activities inside each section card, on the course home page, with a tick beside the ones already finished.<br /><br /><em>Example:</em> a learner can see that Module 2 contains a video, a reading and a quiz — and that they have done the first two — without opening it.';
$string['showactivitiesoncards_help'] = 'When enabled, each section card on the course home page also lists that section\'s activities beneath the summary, with the learner\'s completion state for each one. Only the first few are listed; a "+N" link opens the section to see the rest. Activities a learner cannot see are never listed. Leave this off to keep the card grid at its most compact and scannable.';
$string['showcourseindex'] = 'Show the course index (the side menu listing every section and activity)';
$string['showcourseindex_desc'] = '<strong>What the course index is:</strong> the menu that slides out from the side of a course, listing every section and every activity so a learner can jump straight to any part of it.<br /><br /><strong>What this does:</strong> chooses which pages show that menu.<br /><br /><em>Example:</em> choose \'Course home only\' and learners get the menu on the main course page, while an activity page fills the whole width with nothing beside it — good for reading, less good for jumping around.<br /><br />This is a starting value for new courses. Each course can change it in its own settings.';
$string['showcourseindex_help'] = 'The course index sidebar appears on the left side, allowing quick navigation between sections and activities. Choose which pages should display it.';
$string['showfromothers'] = 'Show section';
$string['showherobanner'] = 'Show the hero banner (the wide image strip across the top of the course)';
$string['showherobanner_desc'] = '<strong>What the hero banner is:</strong> the wide strip across the top of a course carrying the course image, its name, and the learner\'s progress.<br /><br /><em>Example:</em> switch it off and the course starts straight at the section cards, which suits a short course where a banner is more decoration than help.';
$string['showherobanner_help'] = 'When enabled, a beautiful sticky hero banner appears at the top of the course page featuring the course image, title, and real-time progress tracking. The banner uses glassmorphism effects and stays visible as students scroll.';
$string['shownavchevrons'] = 'Show next / previous arrows on activity pages';
$string['shownavchevrons_desc'] = '<strong>What this does:</strong> shows back and forward arrows on activity pages so a learner can move to the next activity without returning to the course page.<br /><br /><em>Example:</em> finishing a video, they press the arrow and land on the quiz that follows it.';
$string['shownavchevrons_help'] = 'When enabled, elegant navigation chevrons appear on the left and right sides of activity pages, allowing students to quickly move between activities without returning to the course page.';
$string['sitedefault_desc'] = '<strong>What this does:</strong> sets the starting value for <em>brand new</em> courses only.<br /><br /><strong>Important:</strong> changing this does <strong>not</strong> touch courses that already exist. A course keeps whatever it has as soon as anyone saves its settings form.<br /><br /><em>Example:</em> you set this to \'Hide\'. A course created tomorrow starts hidden. The forty courses you already have carry on exactly as they were.<br /><br />To change every existing course, use the <strong>\'apply to ALL existing courses\'</strong> setting that sits directly below this one.';
$string['siteid'] = 'This site\'s web address';
$string['siteid_desc'] = '<strong>What this is:</strong> the full web address of this Moodle site, which is how the AI service recognises you.<br /><br />Include the <code>https://</code>. <em>Example:</em> <code>https://moodle.example.com</code>.<br /><br />Anything that is not a valid address is discarded when you save, and the tutor will then say it is not configured.';
$string['taskgeneratebanner'] = 'Generate AI course banner image';
$string['themesupport'] = 'Theme compatibility';
$string['themesupport_desc'] = 'This course format is developed and tested against <strong>Boost</strong> and <strong>Academi</strong>. Both are checked on every release, on desktop, tablet and mobile widths.<br /><br />It should work with most Boost-based themes, because it builds on the same course index, navigation and header that Boost provides. A theme that positions those differently may produce a layout that does not look right — a banner that will not reach the edges, a course index that overlaps the content, or spacing that looks wrong.<br /><br /><strong>If you are using another theme and something looks wrong</strong>, please email <a href="mailto:support@lmshostingservices.com">support@lmshostingservices.com</a> and tell us which theme you are using. Nearly all of these turn out to be small differences we can support once we know the theme exists.';
$string['timingheading'] = 'Estimated activity durations';
$string['timingheading_desc'] = '<strong>What these do:</strong> the time badge on each section card is simply the sum of its activities\' estimated times. These settings decide the starting figure for each kind of activity.<br /><br />A teacher can override any single activity by clicking its time badge with editing turned on, and that override always wins.';
$string['tour_back'] = 'Back';
$string['tour_finish'] = 'Finish';
$string['tour_mute'] = 'Mute narration';
$string['tour_next'] = 'Next';
$string['tour_offer_body'] = 'A two-minute walkthrough of this course format, narrated. You can skip or mute it at any point.';
$string['tour_offer_dismiss'] = 'No thanks';
$string['tour_offer_start'] = 'Start tour';
$string['tour_offer_title'] = 'Take a quick tour?';
$string['tour_progress'] = 'Step {$a->current} of {$a->total}';
$string['tour_s_cards_body'] = 'Each card is a section of the course. The dots show which activities you have finished, and the percentage is your progress through that section.';
$string['tour_s_cards_title'] = 'Course sections';
$string['tour_s_done_body'] = 'That is everything. Pick a section to get started.';
$string['tour_s_done_title'] = 'You are ready';
$string['tour_s_grades_body'] = 'This takes you to your own grades for the course. You only ever see your own.';
$string['tour_s_grades_title'] = 'Checking your grades';
$string['tour_s_index_body'] = 'The course index lists every section and activity. Use it to jump straight to anything without going back to the course page first.';
$string['tour_s_index_title'] = 'Finding your way around';
$string['tour_s_progress_body'] = 'The ring shows how much of the course you have completed. It updates as you finish activities.';
$string['tour_s_progress_title'] = 'Your progress';
$string['tour_s_ring_body'] = 'The ring fills as you complete activities. It counts only the activities your teacher is tracking, so it may not include everything you can see.';
$string['tour_s_ring_title'] = 'How far you have got';
$string['tour_s_sidebar_body'] = 'The panel on the left lists everything in the course. Each row shows roughly how long the activity takes and a tick once you have finished it, and the ring at the top is your progress through the whole course.';
$string['tour_s_sidebar_title'] = 'Your course at a glance';
$string['tour_s_status_body'] = 'A filled marker means you have completed that activity, an empty one means it is still waiting for you. Some activities complete themselves when you finish them; others you tick off yourself.';
$string['tour_s_status_title'] = 'What you have finished';
$string['tour_s_time_body'] = 'Each activity shows roughly how long to allow for it, and each section shows the total. Use it to decide what you can fit into the time you have.';
$string['tour_s_time_title'] = 'How long things take';
$string['tour_s_tutor_body'] = 'Stuck on something? Open the tutor and ask. It knows this course\'s material and will help you work it out rather than simply hand you the answer.';
$string['tour_s_tutor_title'] = 'Ask the AI Tutor';
$string['tour_s_welcome_body'] = 'A very quick tour of how this course works. It takes about a minute, and you can skip it.';
$string['tour_s_welcome_title'] = 'Welcome to your course';
$string['tour_skip'] = 'Skip tour';
$string['tour_t_activities_body'] = 'The card lists the activities in the section and marks off the ones a learner has finished. You can cap how many are listed, or switch the list off entirely, in the course settings.';
$string['tour_t_activities_title'] = 'What is inside each section';
$string['tour_t_banner_body'] = 'The banner carries the course name, its progress ring and the quick actions. You can upload your own image in the course settings, or have one generated for you.';
$string['tour_t_banner_title'] = 'The course banner';
$string['tour_t_cards_body'] = 'Each section becomes a card showing its activities and how far learners have got. You can give each one an icon, and choose a grid or a list in the course settings.';
$string['tour_t_cards_title'] = 'Section cards';
$string['tour_t_done_body'] = 'You can reopen it any time from your profile preferences. Have a look as a learner next.';
$string['tour_t_done_title'] = 'That is the tour';
$string['tour_t_generate_body'] = 'This creates a banner image from your course name. It takes a minute or two and runs in the background, so you can carry on working while it finishes.';
$string['tour_t_generate_title'] = 'Generate a banner image';
$string['tour_t_grades_body'] = 'This takes you straight to the gradebook for the course, without going through the menus. Learners see the same button, but it shows them only their own grades.';
$string['tour_t_grades_title'] = 'Grades at a glance';
$string['tour_t_icons_body'] = 'Click a section\'s icon while editing to choose a different one. Icons make a long course scannable at a glance, and a course with none set shows a neutral placeholder rather than looking unfinished.';
$string['tour_t_icons_title'] = 'Section icons';
$string['tour_t_index_body'] = 'Every section and activity, always to hand. You can choose whether learners see it on the course page, section pages and activity pages independently, in the course settings.';
$string['tour_t_index_title'] = 'The course index';
$string['tour_t_report_body'] = 'Under the course menu you will find reporting on how learners are using the AI Tutor: what they asked, where they got stuck, and which activities generate the most questions. It is often the fastest way to spot a confusing piece of content.';
$string['tour_t_report_title'] = 'The reporting dashboard';
$string['tour_t_ring_body'] = 'This shows how much of the course a learner has completed. It counts only activities with completion tracking switched on, so if it looks low, check which activities are being tracked.';
$string['tour_t_ring_title'] = 'The progress ring';
$string['tour_t_settings_body'] = 'Everything in this tour is configurable per course under Settings, and site-wide under Plugins, Course formats, AI Course Format.';
$string['tour_t_settings_title'] = 'Where the settings are';
$string['tour_t_sidebar_body'] = 'When it is switched on, the course index becomes a player sidebar: your logo, the course, a progress ring, the total time, and a row per activity showing how long it takes and whether it is done. You choose whether it starts open or collapsed.';
$string['tour_t_sidebar_title'] = 'The course player sidebar';
$string['tour_t_studentview_body'] = 'This is the important one. A learner sees a noticeably different page: the Course, Settings and Participants tabs are hidden by default, and so are all editing controls. Use Switch role to Student from this menu before you judge the layout.';
$string['tour_t_studentview_title'] = 'See it as a learner';
$string['tour_t_time_body'] = 'Each activity carries an estimated duration, and the section total is the sum of them. The starting figures come from the site settings, quizzes are worked out from how many questions they contain, and you can correct any single activity yourself.';
$string['tour_t_time_title'] = 'Estimated time';
$string['tour_t_tutor_body'] = 'Learners open the tutor here and ask questions about the course. It reads the course content, so its answers are about your material rather than the internet at large.';
$string['tour_t_tutor_title'] = 'The AI Tutor';
$string['tour_t_welcome_body'] = 'This is a short tour of what this format adds to your course. It takes about two minutes, and you can leave at any point.';
$string['tour_t_welcome_title'] = 'Welcome to the AI Course Format';
$string['tour_unmute'] = 'Unmute narration';
$string['tourvoice'] = 'Language for the tour narration';
$string['tourvoice_desc'] = '<strong>What this does:</strong> chooses the accent the narration uses.<br /><br />Use a standard language tag: <code>en-AU</code> for Australian English, <code>en-GB</code> for British, <code>en-US</code> for American. The closest available voice is picked.';
$string['tourvoiceover'] = 'Read the guided tour aloud';
$string['tourvoiceover_desc'] = '<strong>What this does:</strong> lets the first-run guided tour read each step out loud.<br /><br />Learners and teachers can mute it themselves and the choice is remembered, so this only sets the starting point.';
$string['viewallactivities'] = 'View all activities';
$string['viewsection'] = 'View section';
