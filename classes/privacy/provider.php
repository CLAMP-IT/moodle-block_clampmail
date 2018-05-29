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
 * @package   block_clampmail
 * @copyright 2018 Collaborative Liberal Arts Moodle Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_clampmail\privacy;

defined('MOODLE_INTERNAL') || die();

use context_user;
use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\writer;
use \core_privacy\local\request\helper;
use \core_privacy\local\request\transform;

class provider implements
        // This plugin does store personal user data.
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\metadata\provider {

    use \core_privacy\local\legacy_polyfill;

    public static function _get_metadata(collection $collection) {
        $collection->add_database_table(
            'block_clampmail_log',
            [
                'userid' => 'privacy:metadata:clampmail_message:userid',
                'mailto' => 'privacy:metadata:clampmail_message:mailto',
                'subject' => 'privacy:metadata:clampmail_message:subject',
                'message' => 'privacy:metadata:clampmail_message:message',
                'time' => 'privacy:metadata:clampmail_message:time',
            ],
            'privacy:metadata:clampmail_log'
        );

        $collection->add_database_table(
            'block_clampmail_drafts',
            [
                'userid' => 'privacy:metadata:clampmail_message:userid',
                'mailto' => 'privacy:metadata:clampmail_message:mailto',
                'subject' => 'privacy:metadata:clampmail_message:subject',
                'message' => 'privacy:metadata:clampmail_message:message',
                'time' => 'privacy:metadata:clampmail_message:time',
            ],
            'privacy:metadata:clampmail_drafts'
        );

        $collection->add_database_table(
            'block_clampmail_signatures',
            [
                'userid' => 'privacy:metadata:clampmail_signatures:userid',
                'title' => 'privacy:metadata:clampmail_signatures:title',
                'signature' => 'privacy:metadata:clampmail_signatures:signature',
            ],
            'privacy:metadata:clampmail_signatures'
        );

        return $collection;
    }

    /**
     * Find all courses which have a CLAMPMail block and underlying user data.
     * This can include sent items, drafts, and signatures.
     */
    public static function _get_contexts_for_userid(int $userid) {
        $contextlist = new \core_privacy\local\request\contextlist();

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {block_instances} bi ON bi.parentcontextid = c.id
             LEFT JOIN {block_clampmail_log} bcl ON bcl.courseid = c.instanceid
             LEFT JOIN {block_clampmail_drafts} bcd ON bcd.courseid = c.instanceid
                 WHERE bi.blockname = 'clampmail'
                   AND (
                       bcl.userid = :loguserid OR
                       bcd.userid = :draftuserid
                   )";
        $params = [
            'loguserid'   => $userid,
            'draftuserid' => $userid
        ];
        $contextlist->add_from_sql($sql, $params);
        // And we also store signatures by user context.
        $contextlist->add_user_context($userid);
        return $contextlist;
    }

    public static function _export_user_data(approved_contextlist $contextlist) {
        global $DB;

        // If no contexts, bail out.
        if (empty($contextlist)) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        // Get all courses where the user has Quickmail data.
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $sql = "SELECT
                    ctx.id AS contextid,
                    c.*,
                    bcl.userid as logs,
                    bcd.userid as drafts
                  FROM {context} ctx
                  JOIN {block_instances} bi ON bi.parentcontextid = ctx.id
                  JOIN {course} c ON c.id = ctx.instanceid
             LEFT JOIN {block_clampmail_log} bcl ON bcl.courseid = ctx.instanceid
             LEFT JOIN {block_clampmail_drafts} bcd ON bcd.courseid = ctx.instanceid
                 WHERE (
                    ctx.id {$contextsql}
                   )
        ";
        $params = [
            'loguserid'   => $userid,
            'draftuserid' => $userid
        ];
        $params += $contextparams;

        // Export sent messages and drafts (course context)
        $courses = $DB->get_recordset_sql($sql, $params);
        foreach ($courses as $course) {
            $context = \context::instance_by_id($course->contextid);

            $sent = static::get_messages('log', $userid, $course->id);
            $drafts = static::get_messages('drafts', $userid, $course->id);

            $data = (object) array(
                'sent_messages' => $sent,
                'drafts' => $drafts,
            );

            writer::with_context($context)->export_data([get_string('pluginname', 'block_clampmail')], $data);
        }
        $courses->close();

        // Export signatures (user context)
        $context = context_user::instance($userid);
        if (in_array($context->id, $contextlist->get_contextids())) {
            $signatures = static::get_signatures($userid);
            $data = (object) [
                'signatures' => $signatures,
            ];
            writer::with_context($context)->export_data([get_string('pluginname', 'block_clampmail')], $data);
        }
    }

    /**
     * Delete all use data which matches the specified deletion criteria.
     *
     * @param   context         $context   The specific context to delete data for.
     */
    public static function _delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel === 30) {
            // instanceid is userid
            static::delete_data_user_level($context->instanceid);
        } else if ($context->contextlevel === 50) {
            // instanceid is courseid
            static::delete_data_course_level($context->instanceid);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function _delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        // If no contexts, bail out.
        if (empty($contextlist)) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        // Get all courses where the user has Quickmail data.
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $sql = "SELECT
                    ctx.id AS contextid,
                    c.*,
                    bcl.userid as logs,
                    bcd.userid as drafts
                  FROM {context} ctx
                  JOIN {block_instances} bi ON bi.parentcontextid = ctx.id
                  JOIN {course} c ON c.id = ctx.instanceid
             LEFT JOIN {block_clampmail_log} bcl ON bcl.courseid = ctx.instanceid
             LEFT JOIN {block_clampmail_drafts} bcd ON bcd.courseid = ctx.instanceid
                 WHERE (
                    ctx.id {$contextsql}
                   )
        ";
        $params = [
            'loguserid'   => $userid,
            'draftuserid' => $userid
        ];
        $params += $contextparams;

        // Delete sent messages and drafts (course context)
        $courses = $DB->get_recordset_sql($sql, $params);
        foreach ($courses as $course) {
            static::delete_data_course_level($course->id, $userid);
        }
        $courses->close();

        // Delete signatures (user context)
        $context = context_user::instance($userid);
        if (in_array($context->id, $contextlist->get_contextids())) {
            static::delete_data_user_level($userid);
        }
    }

    protected static function get_signatures($userid) {
        global $DB;

        $sigs = $DB->get_records('block_clampmail_signatures', array(
                'userid' => $userid,
            )
        );

        $signatures = array();
        foreach ($sigs as $sig) {
            $signature = (object) [
                'title' => format_string($sig->title, true),
                'signature' => $sig->signature,
            ];
            array_push($signatures, $signature);
        }
        return (object) $signatures;
    }

    protected static function get_messages($type, $userid, $courseid) {
        global $DB;

        $records = $DB->get_records('block_clampmail_' . $type, array(
                'courseid' => $courseid,
                'userid' => $userid,
            )
        );

        $messages = array();
        foreach ($records as $record) {
            $mailtoids = explode(',', $record->mailto);
            $mailto = array();
            foreach ($mailtoids as $mid) {
                array_push($mailto, transform::user($mid));
            }
            $mailto = implode(',', $mailto);

            $message = (object) [
                'subject'   => format_string($record->subject, true),
                'mailto'    => $mailto,
                'message'   => $record->message,
                'time'      => transform::datetime($record->time),
            ];
            array_push($messages, $message);
        }
        return (object) $messages;
    }

    public static function delete_data_user_level($userid) {
        global $DB;

        $context = context_user::instance($userid);
        $DB->delete_records('block_clampmail_signatures', array(
            'userid' => $userid
        ));
    }

    public static function delete_data_course_level($courseid, $userid = null) {
        global $DB;

        $conditions = array(
            'courseid' => $courseid
        );

        if (!empty($userid)) {
            $conditions['userid'] = $userid;
        }

        $DB->delete_records('block_clampmail_log', $conditions);
        $DB->delete_records('block_clampmail_drafts', $conditions);
    }
}