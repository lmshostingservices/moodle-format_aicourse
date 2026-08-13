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
 * format_aicourse file.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Course Format';
$string['currentsection'] = 'This section';
$string['sectionname'] = 'Section';
$string['section0name'] = 'General';
$string['page-course-view-aicourse'] = 'Any course main page in AI Course format';
$string['page-course-view-aicourse-x'] = 'Any course page in AI Course format';
$string['hidefromothers'] = 'Hide section';
$string['showfromothers'] = 'Show section';
$string['privacy:metadata'] = 'The AI Course Format plugin does not store any personal data.';

// Hero banner strings.
$string['courseprogress'] = 'Course Progress';
$string['gotocourse'] = 'Course Home';
$string['completedof'] = '{$a->completed} of {$a->total} activities completed';

// Navigation strings.
$string['previousactivity'] = 'Previous activity';
$string['nextactivity'] = 'Next activity';
$string['previoussection'] = 'Previous section';
$string['nextsection'] = 'Next section';

// Settings strings.
$string['showherobanner'] = 'Show hero banner';
$string['showherobanner_desc'] = 'Display a sticky hero banner at the top of the course page with course image, title, and progress.';
$string['showherobanner_help'] = 'When enabled, a beautiful sticky hero banner appears at the top of the course page featuring the course image, title, and real-time progress tracking. The banner uses glassmorphism effects and stays visible as students scroll.';
$string['shownavchevrons'] = 'Show navigation chevrons';
$string['shownavchevrons_desc'] = 'Display elegant chevron arrows for navigating between activities.';
$string['shownavchevrons_help'] = 'When enabled, elegant navigation chevrons appear on the left and right sides of activity pages, allowing students to quickly move between activities without returning to the course page.';
$string['herobannerheight'] = 'Hero banner height';
$string['herobannerheight_desc'] = 'Maximum height of the hero banner in pixels.';
$string['herobannerheight_help'] = 'Set the maximum height of the hero banner in pixels. A smaller value (140-180px) creates a compact, professional look while a larger value (200-300px) makes the course image more prominent.';
$string['herobannerwidth'] = 'Hero banner width';
$string['herobannerwidth_desc'] = 'Maximum width of the hero banner in pixels. Set to 0 for full width.';
$string['herobannerwidth_help'] = 'Set the maximum width of the hero banner in pixels to match your theme\'s content width. For example, if your Moodle theme has a 1200px content area, set this to 1200. Set to 0 (default) for full width up to 1400px.';
$string['herobanneralign'] = 'Hero banner alignment';
$string['herobanneralign_desc'] = 'Horizontal alignment of the hero banner when a custom width is set.';
$string['herobanneralign_help'] = 'Choose whether the hero banner is centred or left-aligned on the page. Left-aligned is useful when you want the banner to line up with the left edge of your page content.';
$string['herobanneralign_center'] = 'Centre';
$string['herobanneralign_left'] = 'Left';

$string['cardtitlesize'] = 'Card title text size';
$string['cardtitlesize_desc'] = 'Font size for card titles in pixels. Default is 14.';
$string['cardtitlesize_help'] = 'Set the font size for section card and activity card titles in pixels. For example, enter 12 for smaller titles or 16 for larger. Default is 14px.';

// Course index settings.
$string['showcourseindex'] = 'Show course index sidebar';
$string['showcourseindex_desc'] = 'Choose where the course index navigation sidebar appears.';
$string['showcourseindex_help'] = 'The course index sidebar appears on the left side, allowing quick navigation between sections and activities. Choose which pages should display it.';
$string['courseindex_none'] = 'Hide on all pages';
$string['courseindex_home'] = 'Course home only';
$string['courseindex_section'] = 'Section pages only';
$string['courseindex_home_section'] = 'Course home + Section pages';
$string['courseindex_activity'] = 'Activity pages only';
$string['courseindex_home_activity'] = 'Course home + Activity pages';
$string['courseindex_section_activity'] = 'Section + Activity pages';
$string['courseindex_all'] = 'All pages (home, section, activity)';

// Display mode settings.
$string['displayascards'] = 'Section display mode';
$string['displayascards_desc'] = 'Choose how sections are displayed on the course home page.';
$string['displayascards_help'] = 'Choose between traditional section view (expandable sections with activities listed) or beautiful card view (modern cards with progress tracking, estimated time, and activity dots).';
$string['displayassections'] = 'Traditional sections';
$string['displayascardsoption'] = 'Beautiful cards';

// Activity display mode settings.
$string['activitydisplaymode'] = 'Activity display mode';
$string['activitydisplaymode_desc'] = 'Choose how activities are displayed within sections.';
$string['activitydisplaymode_help'] = 'Choose between traditional Moodle activity list or beautiful card view with status badges and icons.';
$string['activitydisplaystandard'] = 'Standard Moodle list';
$string['activitydisplaycards'] = 'Beautiful activity cards';

// Card view strings.
$string['estimatedtime'] = 'Est. time';
$string['minutesshort'] = 'min';
$string['hoursshort'] = 'hr';
$string['sectionprogress'] = 'Section progress';
$string['viewsection'] = 'View section';
$string['activity'] = 'activity';
$string['activities'] = 'activities';
$string['viewallactivities'] = 'View all activities';

// Activity card strings.
$string['backtosection'] = 'Back to Section';
$string['returntosection'] = 'Return to Section';
$string['notstarted'] = 'Not Started';
$string['inprogress'] = 'In Progress';
$string['completed'] = 'Completed';
$string['noactivitiesinsection'] = 'This section is empty. Add activities to get started.';
$string['sectionnotfound'] = 'Section not found.';
$string['nosectionsincourse'] = 'This course has no sections yet.';

// Icon picker strings.
$string['selecticon'] = 'Select icon';
$string['addicon'] = 'Add icon';
$string['changeicon'] = 'Change icon';
$string['removeicon'] = 'Remove icon';
$string['searchicons'] = 'Search icons…';
$string['addsection'] = 'Add section';
$string['deletesection'] = 'Delete section';
$string['deletesectionconfirm'] = 'Are you sure you want to delete this section? This action cannot be undone.';
$string['duplicatesection'] = 'Duplicate section';
$string['iconsaved'] = 'Icon saved';
$string['iconsaveerror'] = 'Error saving icon';

// Activity completion requirement strings.
$string['nocompletion'] = 'No completion tracking';
$string['completionrequirement_manual'] = 'Mark as done';
$string['completionrequirement_view'] = 'View activity';
$string['completionrequirement_gradeany'] = 'Receive a grade';
$string['completionrequirement_gradepass'] = 'Required grade {$a}';
$string['completionrequirement_grade100'] = 'Required grade 100%';
$string['completionrequirement_auto'] = 'Complete activity';

// AI Tutor strings.
$string['aiassistant'] = 'AI Tutor';
$string['aiassistant_welcome'] = 'Hi! I\'m your AI Tutor. I\'ve learned all the content in this course and can answer your questions. Ask me anything about the course material!';
$string['aiassistant_welcome_name'] = 'Hi {$a}! I\'m your AI Tutor for this course. What would you like help with today?';
$string['aiassistant_welcome_activity'] = 'Hi {$a->name}! I see you\'re working on <strong>{$a->activity}</strong>. What would you like help with?';
$string['aiassistant_welcome_section'] = 'Hi {$a->name}! I see you\'re in <strong>{$a->section}</strong>. What would you like help with?';
$string['aiassistant_placeholder'] = 'Ask a question about the course...';
$string['aiassistant_error'] = 'Sorry, I couldn\'t process your question. Please try again.';
$string['aiassistant_nocredits'] = 'AI credits are required to use the AI Tutor. Please contact your administrator.';
$string['aiassistant_notconfigured'] = 'AI Tutor is not configured. Please set up the Site ID and API Key in the plugin settings.';
$string['aiassistant_settings_desc'] = 'Configure the AI Tutor connection. If you have the AI Grader Central Config plugin installed, these settings are optional as it will use the central configuration.';

// AI Tutor Quick Actions.
$string['aiassistant_quick_label'] = 'Quick help:';
$string['aiassistant_quick_structure'] = 'How to structure';
$string['aiassistant_quick_concepts'] = 'Explain concepts';
$string['aiassistant_quick_workplace'] = 'Workplace examples';
$string['aiassistant_quick_practice'] = 'Practice questions';
$string['aiassistant_quick_checklist'] = 'Checklist';
$string['aiassistant_prompt_structure'] = 'Can you help me understand how to structure my response for {activity}? I don\'t need an answer, just guidance on the format and what sections to include.';
$string['aiassistant_prompt_concepts'] = 'Can you explain the key concepts I need to understand for this activity? Break it down in simple terms.';
$string['aiassistant_prompt_workplace'] = 'Can you give me a real workplace example that relates to this activity? I want to understand how this applies in a real job.';
$string['aiassistant_prompt_practice'] = 'Can you give me a practice question or scenario to help me prepare? Something similar to what I need to do.';
$string['aiassistant_prompt_checklist'] = 'Can you give me a checklist of things I should include or check before I submit my work?';
$string['aiassistant_locked'] = 'You\'ve already submitted this assignment. I can\'t help with answers now, but I can help you reflect on feedback, identify learning gaps, or prepare for future tasks. What would you like to explore?';
$string['aiassistant_rating_thanks'] = 'Thanks for the feedback!';

// AI Tutor enable/disable toggle.
$string['enabletutor'] = 'Enable AI Tutor';
$string['enabletutor_desc'] = 'When enabled, the AI Tutor chat bubble is shown to students and teachers on course and activity pages using AI Course Format. Uncheck to hide the tutor completely across all courses.';

// Settings strings.
$string['siteid'] = 'Site ID';
$string['siteid_desc'] = 'Your Moodle site URL (e.g., https://yourmoodle.com). This is used to identify your site with the AI Grader service.';
$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'Your API Key from the Essay Grader AI dashboard (lms-labs.com). Required for the AI Tutor to function.';
$string['displaysettings'] = 'Default Display Settings';
$string['displaysettings_desc'] = 'Default settings for new courses using AI Course Format.';

// AI Report strings.
$string['aireport'] = 'AI Tutor Report';
$string['aireport_content'] = 'Course Content';
$string['aireport_history'] = 'Chat History';
$string['aireport_sections'] = 'Sections';
$string['aireport_activities'] = 'Activities';
$string['aireport_characters'] = 'Characters Learned';
$string['aireport_course_summary'] = 'Course Summary';
$string['aireport_learned_content'] = 'Content Learned by AI';
$string['aireport_learned'] = 'AI has learned this content';
$string['aireport_total_questions'] = 'Total Questions';
$string['aireport_helpful'] = 'Marked Helpful';
$string['aireport_corrected'] = 'Corrected Responses';
$string['aireport_all_students'] = 'All Students';
$string['aireport_all_groups'] = 'All Groups';
$string['aireport_all_ratings'] = 'All Ratings';
$string['aireport_filter_helpful'] = 'Helpful Only';
$string['aireport_filter_nothelpful'] = 'Not Helpful Only';
$string['aireport_filter_corrected'] = 'Corrected Only';
$string['aireport_search'] = 'Search questions or responses...';
$string['aireport_apply'] = 'Apply Filters';
$string['aireport_no_chats'] = 'No chat history yet';
$string['aireport_no_chats_desc'] = 'Students haven\'t asked any questions to the AI Tutor yet.';
$string['aireport_nocourses'] = 'No courses using AI Course Format';
$string['aireport_nocourses_desc'] = 'Change a course format to AI Course Format to enable the AI Tutor.';
$string['aireport_view'] = 'View Report';
$string['aireport_student'] = 'Student';
$string['aireport_question'] = 'Question';
$string['aireport_response'] = 'AI Response';
$string['aireport_date'] = 'Date';
$string['aireport_rating'] = 'Rating';
$string['aireport_actions'] = 'Actions';
$string['aireport_correct'] = 'Correct';
$string['aireport_correction'] = 'Correction';
$string['aireport_correction_placeholder'] = 'Enter the correct answer to retrain the AI...';
$string['aireport_save'] = 'Save';
$string['aireport_cancel'] = 'Cancel';

// Navigation strings.
$string['grades'] = 'My Grades';
$string['aicourse:view'] = 'View AI Course Format';
$string['aicourse:viewreport'] = 'View AI Course Format reports';

// Admin Q&A report strings.
$string['admin_report_title']         = 'AI Tutor — All Q&A (Site-wide)';
$string['admin_report_link']          = 'AI Tutor Q&A Report';
$string['admin_report_view']          = 'View all AI Tutor Q&A';
$string['admin_report_stat_total']    = 'Total Questions (all time)';
$string['admin_report_stat_helpful']  = 'Rated Helpful';
$string['admin_report_stat_refused']  = 'Refused (academic integrity)';
$string['admin_report_stat_courses']  = 'Active Courses';
$string['admin_report_stat_students'] = 'Active Students';
$string['admin_report_search']        = 'Search questions / responses';
$string['admin_report_filter_course'] = 'Course';
$string['admin_report_all_courses']   = 'All courses';
$string['admin_report_filter_student']= 'Student';
$string['admin_report_filter_rating'] = 'Rating';
$string['admin_report_filter_unrated']= 'Unrated';
$string['admin_report_filter_refused']= 'Response type';
$string['admin_report_all']           = 'All';
$string['admin_report_refused_only']  = 'Refused only';
$string['admin_report_answered_only'] = 'Answered only';
$string['admin_report_filter_datefrom'] = 'From date';
$string['admin_report_filter_dateto']   = 'To date';
$string['admin_report_reset']         = 'Reset';
$string['admin_report_export_csv']    = 'Export CSV';
$string['admin_report_showing']       = 'Showing {$a->from}–{$a->to} of {$a->total} records';
$string['admin_report_col_course']    = 'Course';
$string['admin_report_col_activity']  = 'Activity';
$string['admin_report_col_refused']   = 'Refused';
$string['admin_report_refused']       = 'Refused';
$string['admin_report_answered']      = 'Answered';
$string['admin_report_show_more']     = 'Show more';
$string['admin_report_show_less']     = 'Show less';
$string['admin_report_no_filtered']   = 'No records match the current filters.';

// ── Banner image upload (v1.7.4) ─────────────────────────────────────────────
$string['bannerimageheader'] = 'Course Banner Image';

$string['bannerimage'] = 'Upload banner image';
$string['bannerimage_help'] = 'Upload a custom banner image that appears in the large hero panel at the top of your course.

<strong>Recommended aspect ratio: 16:5</strong> (e.g. 1920 × 600 px or 1600 × 500 px).
This ratio produces a beautiful panoramic banner that fills the hero at all screen sizes without cropping off important content.

Other ratios that work well:
<ul>
<li>16:6 (e.g. 1920 × 720) — slightly taller, more visual impact</li>
<li>16:9 (e.g. 1920 × 1080) — standard HD; top and bottom will be cropped on wider screens</li>
<li>3:1 (e.g. 1800 × 600) — ultra-wide cinematic strip</li>
</ul>

Practical guidelines:
<ul>
<li>Minimum size: 1200 × 375 px — smaller images will look blurry on high-DPI screens</li>
<li>Place key subjects in the <strong>centre</strong> of the frame — edges may be cropped on mobile</li>
<li>Choose images with good contrast so the white text overlay remains readable</li>
<li>Dark or moody images work best; bright white backgrounds can reduce text contrast</li>
<li>The plugin automatically adds a cinematic gradient overlay, so a plain dark overlay is not required</li>
</ul>

If no custom image is uploaded the course overview image (set in Course Settings → Overview files) is used instead.';

$string['bannerimage_ratio_title']   = 'Recommended ratio: 16:5';
$string['bannerimage_ratio_hint']    = 'Ideal image size is 1920 × 600 px (or 1600 × 500 px minimum). Centre your subject — edges may be cropped on narrow screens. Dark or high-contrast images give the best result with the white text overlay.';
$string['bannerimage_ratio_formats'] = 'Accepted formats: JPG · PNG · WebP  ·  Maximum file size: 5 MB  ·  One image per course.';
