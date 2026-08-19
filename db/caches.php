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
 * Cache definitions for format_aicourse.
 *
 * coursecontent: Caches the full course content array built by
 * \format_aicourse\local\contentindex::get_course_content_for_ai(), which is what the AI Tutor answers from.
 * Keyed by "courseid_userid", because the content is filtered by what the given user is
 * allowed to see.
 *
 * TTL is 10 minutes. In addition, the whole definition is purged by the event observers
 * registered in db/events.php whenever a module or section is created, updated or deleted,
 * or the course itself is updated, so the tutor never answers from content that no longer
 * matches the course. See \format_aicourse\observer.
 *
 * The purge is definition wide rather than per course: the MUC application store cannot
 * enumerate or pattern match keys, so a targeted delete would require a key index maintained
 * on the write side in lib.php.
 *
 * @package    format_aicourse
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // ACF-FIX-2.1.4: declared here rather than conjured at runtime with
    // cache::make_from_params(). A declared definition can be inspected, sized and redirected to
    // a shared store by an administrator, which matters on a cluster: an undeclared per-node
    // cache means the per-user rate limit is enforced per web node rather than site wide.
    // Deliberately no TTL -- \format_aicourse\external\throttle prunes timestamps outside the
    // window itself, and a TTL here would silently reset a user's allowance.
    'ajaxratelimit' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'staticacceleration' => true,
        'staticaccelerationsize' => 2,
    ],

    'coursecontent' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 600, // 10 minutes
        'simplekeys' => true,
        'simpledata' => false,
        'staticacceleration'      => true,
        'staticaccelerationsize'  => 3,
    ],
];
