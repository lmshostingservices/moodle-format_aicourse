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
 * Privacy Subsystem implementation for format_aicourse.
 *
 * @package    format_aicourse
 * @category   privacy
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_aicourse\privacy;

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for format_aicourse.
 *
 * The AI Course Format stores two kinds of personal data, both scoped to a course:
 *
 * - {format_aicourse_chats}: one row per question a user asks the AI Tutor, holding the
 *   question text, the AI response, the user's rating of that response, and (optionally) a
 *   teacher supplied correction together with the id of the teacher who wrote it.
 * - {format_aicourse_ai_memory}: a per user, per activity rolling summary of what the user
 *   has previously asked, used to give continuity between tutoring sessions.
 *
 * The plugin also transmits personal data to the external LMS-Labs AI service in order to
 * generate a tutor response. See get_metadata().
 *
 * Deletion policy for teacher corrections: a user may appear on another user's chat row via
 * the correctedby column. Deleting that teacher's data must not destroy the student's record,
 * so the teacher's attribution (correctedby and timecorrected) is nulled out instead, leaving
 * the student's question, the AI response and the correction text in place. The correction
 * text is treated as part of the student's chat record: it is exported and deleted with the
 * student, not with the teacher.
 *
 * The three interfaces mean, in order: this plugin has data in its own database tables; it can
 * determine which users have data within a given context; and it stores personal data scoped to
 * course contexts.
 *
 * @package    format_aicourse
 * @category   privacy
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'format_aicourse_chats',
            [
                'courseid' => 'privacy:metadata:format_aicourse_chats:courseid',
                'userid' => 'privacy:metadata:format_aicourse_chats:userid',
                'activityid' => 'privacy:metadata:format_aicourse_chats:activityid',
                'questionslot' => 'privacy:metadata:format_aicourse_chats:questionslot',
                'question' => 'privacy:metadata:format_aicourse_chats:question',
                'response' => 'privacy:metadata:format_aicourse_chats:response',
                'rating' => 'privacy:metadata:format_aicourse_chats:rating',
                'refused' => 'privacy:metadata:format_aicourse_chats:refused',
                'locked' => 'privacy:metadata:format_aicourse_chats:locked',
                'correction' => 'privacy:metadata:format_aicourse_chats:correction',
                'correctedby' => 'privacy:metadata:format_aicourse_chats:correctedby',
                'timecreated' => 'privacy:metadata:format_aicourse_chats:timecreated',
                'timecorrected' => 'privacy:metadata:format_aicourse_chats:timecorrected',
            ],
            'privacy:metadata:format_aicourse_chats'
        );

        $collection->add_database_table(
            'format_aicourse_ai_memory',
            [
                'courseid' => 'privacy:metadata:format_aicourse_ai_memory:courseid',
                'activityid' => 'privacy:metadata:format_aicourse_ai_memory:activityid',
                'userid' => 'privacy:metadata:format_aicourse_ai_memory:userid',
                'memory' => 'privacy:metadata:format_aicourse_ai_memory:memory',
                'timeupdated' => 'privacy:metadata:format_aicourse_ai_memory:timeupdated',
            ],
            'privacy:metadata:format_aicourse_ai_memory'
        );

        $collection->add_external_location_link(
            'lms_labs_ai',
            [
                'siteUrl' => 'privacy:metadata:lms_labs_ai:siteurl',
                'userId' => 'privacy:metadata:lms_labs_ai:userid',
                'studentName' => 'privacy:metadata:lms_labs_ai:studentname',
                'courseId' => 'privacy:metadata:lms_labs_ai:courseid',
                'courseName' => 'privacy:metadata:lms_labs_ai:coursename',
                'courseContext' => 'privacy:metadata:lms_labs_ai:coursecontext',
                'activityName' => 'privacy:metadata:lms_labs_ai:activityname',
                'sectionName' => 'privacy:metadata:lms_labs_ai:sectionname',
                'question' => 'privacy:metadata:lms_labs_ai:question',
                'questionSlot' => 'privacy:metadata:lms_labs_ai:questionslot',
                'questionText' => 'privacy:metadata:lms_labs_ai:questiontext',
                'priorTutorMemory' => 'privacy:metadata:lms_labs_ai:priortutormemory',
            ],
            'privacy:metadata:lms_labs_ai'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * Both tables are keyed by courseid, so all data lives in course contexts.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Questions asked by the user, and corrections written by the user.
        $sql = "SELECT ctx.id
                  FROM {format_aicourse_chats} c
                  JOIN {context} ctx
                    ON ctx.instanceid = c.courseid
                   AND ctx.contextlevel = :contextcourse
                 WHERE c.userid = :userid
                    OR c.correctedby = :correctedby";
        $contextlist->add_from_sql($sql, [
            'contextcourse' => CONTEXT_COURSE,
            'userid' => $userid,
            'correctedby' => $userid,
        ]);

        // Per activity tutoring memory belonging to the user.
        $sql = "SELECT ctx.id
                  FROM {format_aicourse_ai_memory} m
                  JOIN {context} ctx
                    ON ctx.instanceid = m.courseid
                   AND ctx.contextlevel = :contextcourse
                 WHERE m.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextcourse' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in
     *                           this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof context_course) {
            return;
        }

        $params = ['courseid' => $context->instanceid];

        $userlist->add_from_sql('userid', "SELECT c.userid
                                             FROM {format_aicourse_chats} c
                                            WHERE c.courseid = :courseid", $params);

        $userlist->add_from_sql('correctedby', "SELECT c.correctedby
                                                  FROM {format_aicourse_chats} c
                                                 WHERE c.courseid = :courseid
                                                   AND c.correctedby IS NOT NULL", $params);

        $userlist->add_from_sql('userid', "SELECT m.userid
                                             FROM {format_aicourse_ai_memory} m
                                            WHERE m.courseid = :courseid", $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;

        $courseids = static::get_course_ids_from_contextlist($contextlist);
        if (empty($courseids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'crs');

        // Export the AI Tutor conversations started by this user.
        $params = array_merge($inparams, ['userid' => $userid]);
        $chats = $DB->get_recordset_select(
            'format_aicourse_chats',
            "courseid {$insql} AND userid = :userid",
            $params,
            'courseid, timecreated, id'
        );
        static::export_grouped_by_course(
            $chats,
            [get_string('privacy:path:chats', 'format_aicourse')],
            'chats',
            function ($record) {
                return (object) [
                    'question' => $record->question,
                    'response' => $record->response,
                    'rating' => static::describe_rating($record->rating),
                    'refused' => transform::yesno($record->refused),
                    'locked' => transform::yesno($record->locked),
                    'activityid' => $record->activityid,
                    'questionslot' => $record->questionslot,
                    'correction' => $record->correction,
                    'timecreated' => transform::datetime($record->timecreated),
                    'timecorrected' => $record->timecorrected ? transform::datetime($record->timecorrected) : null,
                ];
            }
        );

        // Export the corrections this user wrote on other people's conversations.
        $params = array_merge($inparams, ['correctedby' => $userid]);
        $corrections = $DB->get_recordset_select(
            'format_aicourse_chats',
            "courseid {$insql} AND correctedby = :correctedby",
            $params,
            'courseid, timecorrected, id'
        );
        static::export_grouped_by_course(
            $corrections,
            [get_string('privacy:path:corrections', 'format_aicourse')],
            'corrections',
            function ($record) {
                return (object) [
                    'correction' => $record->correction,
                    'timecorrected' => $record->timecorrected ? transform::datetime($record->timecorrected) : null,
                ];
            }
        );

        // Export the per activity tutoring memory.
        $params = array_merge($inparams, ['userid' => $userid]);
        $memories = $DB->get_recordset_select(
            'format_aicourse_ai_memory',
            "courseid {$insql} AND userid = :userid",
            $params,
            'courseid, activityid, id'
        );
        static::export_grouped_by_course(
            $memories,
            [get_string('privacy:path:memory', 'format_aicourse')],
            'memory',
            function ($record) {
                return (object) [
                    'activityid' => $record->activityid,
                    'memory' => $record->memory,
                    'timeupdated' => transform::datetime($record->timeupdated),
                ];
            }
        );
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if (!$context instanceof context_course) {
            return;
        }

        $courseid = $context->instanceid;

        $DB->delete_records('format_aicourse_chats', ['courseid' => $courseid]);
        $DB->delete_records('format_aicourse_ai_memory', ['courseid' => $courseid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to
     *                                          delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        $courseids = static::get_course_ids_from_contextlist($contextlist);
        if (empty($courseids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'crs');

        $params = array_merge($inparams, ['userid' => $userid]);
        $DB->delete_records_select('format_aicourse_chats', "courseid {$insql} AND userid = :userid", $params);
        $DB->delete_records_select('format_aicourse_ai_memory', "courseid {$insql} AND userid = :userid", $params);

        static::unset_correction_attribution($insql, $inparams, [$userid]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete
     *                                    information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof context_course) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        $courseid = $context->instanceid;

        [$userinsql, $userinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $params = array_merge(['courseid' => $courseid], $userinparams);

        $DB->delete_records_select(
            'format_aicourse_chats',
            "courseid = :courseid AND userid {$userinsql}",
            $params
        );
        $DB->delete_records_select(
            'format_aicourse_ai_memory',
            "courseid = :courseid AND userid {$userinsql}",
            $params
        );

        static::unset_correction_attribution('= :courseid', ['courseid' => $courseid], $userids);
    }

    /**
     * Remove the teacher attribution from corrections written by the given users.
     *
     * The chat row itself belongs to the student who asked the question, so it must not be
     * deleted. Only the identifying attribution columns are cleared.
     *
     * @param string $coursesql An SQL fragment matching courseid, e.g. "IN (:crs1, :crs2)".
     * @param array $courseparams The named parameters used by $coursesql.
     * @param array $userids The users whose attribution should be removed.
     */
    protected static function unset_correction_attribution(string $coursesql, array $courseparams, array $userids) {
        global $DB;

        if (empty($userids)) {
            return;
        }

        [$userinsql, $userinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'corr');
        $params = array_merge($courseparams, $userinparams);

        $sql = "UPDATE {format_aicourse_chats}
                   SET correctedby = NULL, timecorrected = NULL
                 WHERE courseid {$coursesql}
                   AND correctedby {$userinsql}";

        $DB->execute($sql, $params);
    }

    /**
     * Reduce an approved contextlist to the list of course ids it covers.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return array An array of course ids.
     */
    protected static function get_course_ids_from_contextlist(approved_contextlist $contextlist): array {
        $courseids = [];

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                $courseids[] = $context->instanceid;
            }
        }

        return array_values(array_unique($courseids));
    }

    /**
     * Export a recordset that is ordered by courseid, writing one file per course context.
     *
     * @param \moodle_recordset $recordset The recordset to export, ordered by courseid.
     * @param array $subcontext The subcontext path to write to.
     * @param string $key The property name holding the list of records in the exported object.
     * @param callable $mapper Maps a database record onto the exportable object.
     */
    protected static function export_grouped_by_course(
        \moodle_recordset $recordset,
        array $subcontext,
        string $key,
        callable $mapper
    ) {

        $lastcourseid = null;
        $data = [];

        foreach ($recordset as $record) {
            if ($lastcourseid !== null && $record->courseid != $lastcourseid) {
                static::write_course_data((int) $lastcourseid, $subcontext, $key, $data);
                $data = [];
            }
            $data[] = $mapper($record);
            $lastcourseid = $record->courseid;
        }
        $recordset->close();

        if (!empty($data)) {
            static::write_course_data((int) $lastcourseid, $subcontext, $key, $data);
        }
    }

    /**
     * Write one batch of exported records into a course context.
     *
     * @param int $courseid The course the data belongs to.
     * @param array $subcontext The subcontext path to write to.
     * @param string $key The property name holding the list of records.
     * @param array $data The records to write.
     */
    protected static function write_course_data(int $courseid, array $subcontext, string $key, array $data) {
        $context = context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return;
        }

        writer::with_context($context)->export_data($subcontext, (object) [$key => $data]);
    }

    /**
     * Turn the numeric rating column into a human readable string.
     *
     * @param int|null $rating The stored rating: 1 helpful, -1 not helpful, 0 or null unrated.
     * @return string
     */
    protected static function describe_rating($rating): string {
        if ((int) $rating === 1) {
            return get_string('aireport_filter_helpful', 'format_aicourse');
        }
        if ((int) $rating === -1) {
            return get_string('aireport_filter_nothelpful', 'format_aicourse');
        }
        return get_string('admin_report_filter_unrated', 'format_aicourse');
    }
}
