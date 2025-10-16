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
 * Library functions for the alternate email functionality.
 *
 * @package   block_clampmail
 * @copyright 2012 Louisiana State University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_clampmail;

defined('MOODLE_INTERNAL') || die;

/**
 * Library functions for the alternate email functionality.
 *
 * @package   block_clampmail
 * @copyright 2012 Louisiana State University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class alternate {
    /**
     * Modal for viewing entries.
     */
    const VIEW = 'view';

    /**
     * Modal for deleting entries.
     */
    const DELETE = 'delete';

    /**
     * Modal for editing entries.
     */
    const INTERACT = 'interact';

    /**
     * Modal for confirming the deletion of entries.
     */
    const CONFIRMED = 'confirmed';

    /**
     * Modal for notifying a user after an entry was saved.
     */
    const INFORMATION = 'inform';

    /**
     * Modal for verifying an entry.
     */
    const VERIFY = 'verify';

    /**
     * Return the base URL for alternate email handling.
     *
     * @param int $courseid the course id
     * @param array $additional additional parameters
     * @return \moodle_url
     */
    private static function base_url($courseid, $additional = []) {
        $params = ['courseid' => $courseid] + $additional;
        return new \moodle_url('/blocks/clampmail/alternate.php', $params);
    }

    /**
     * Get the alternate emails for the course.
     *
     * @param stdClass $course the course object
     * @return array
     */
    public static function get($course) {
        global $DB;

        $params = ['courseid' => $course->id];
        return $DB->get_records('block_clampmail_alternate', $params, 'valid DESC');
    }

    /**
     * Get a single alternate email for the course.
     *
     * @param int $id the alternate email id
     * @return array
     */
    public static function get_one($id) {
        global $DB;

        $params = ['id' => $id];
        return $DB->get_record('block_clampmail_alternate', $params, '*', MUST_EXIST);
    }

    /**
     * Creates a dialog box for deleting an alternate email.
     *
     * @param stdClass $course the course object
     * @param int $id the alternate email to delete
     * @return string
     */
    public static function delete($course, $id) {
        global $OUTPUT;

        $email = self::get_one($id);

        $confirmurl = self::base_url($course->id, [
            'id' => $email->id, 'action' => self::CONFIRMED,
        ]);

        $cancelurl = self::base_url($course->id);

        return $OUTPUT->confirm(get_string('alternate_delete_confirm', 'block_clampmail', $email), $confirmurl, $cancelurl);
    }

    /**
     * Deletes an alternate email from a course.
     *
     * @param stdClass $course the course object
     * @param int $id the alternate email to delete
     */
    public static function confirmed($course, $id) {
        global $DB;

        $DB->delete_records('block_clampmail_alternate', ['id' => $id]);

        return redirect(self::base_url($course->id, ['flash' => 1]));
    }

    /**
     * Generate the alternate email verification dialog.
     *
     * @param stdClass $course the course
     * @param int $id the alternate email id
     * @return string
     */
    public static function verify($course, $id) {
        global $DB, $OUTPUT;

        $entry = self::get_one($id);

        $value = optional_param('key', null, PARAM_TEXT);
        $userid = optional_param('activator', null, PARAM_INT);

        $params = [
            'instance' => $course->id,
            'value' => $value,
            'userid' => $userid,
            'script' => 'blocks/clampmail',
        ];

        $backurl = self::base_url($course->id);

        // Pass through already valid entries.
        if ($entry->valid) {
            redirect($backurl);
        }

        // Verify key.
        if (empty($value) || !$DB->get_record('user_private_key', $params)) {
            $reactivate = self::base_url($course->id, [
                'id' => $id, 'action' => self::INFORMATION,
            ]);

            $html = $OUTPUT->notification(get_string('alternate_invalid', 'block_clampmail', $entry));
            $html .= $OUTPUT->continue_button($reactivate);
            return $html;
        }

        // One at a time...They can resend the link if they want.
        delete_user_key('blocks/clampmail', $userid);

        $entry->valid = 1;
        $DB->update_record('block_clampmail_alternate', $entry);

        $entry->course = $course->fullname;

        $html = $OUTPUT->notification(get_string('alternate_activated', 'block_clampmail', $entry), 'notifysuccess');
        $html .= $OUTPUT->continue_button($backurl);

        return $html;
    }

    /**
     * Add the alternate email and notify the user.
     *
     * Add the alternate email and notify the user. Creates a dialog box.
     *
     * @param stdClass $course the course
     * @param int $id the alternate email id
     * @return string
     */
    public static function inform($course, $id) {
        global $OUTPUT, $USER;

        $entry = self::get_one($id);

        // No restriction.
        // Valid forever.
        $value = get_user_key('blocks/clampmail', $USER->id, $course->id);

        $url = self::base_url($course->id);

        $approvalurl = self::base_url($course->id, [
            'id' => $id, 'action' => self::VERIFY,
            'activator' => $USER->id, 'key' => $value,
        ]);

        $a = new \stdClass();
        $a->address = $entry->address;
        $a->url = \html_writer::link($approvalurl, $approvalurl->out());
        $a->course = $course->fullname;
        $a->fullname = fullname($USER);

        $from = get_string('alternate_from', 'block_clampmail', get_string('pluginname', 'block_clampmail'));
        $subject = get_string('alternate_subject', 'block_clampmail');
        $htmlbody = get_string('alternate_body', 'block_clampmail', $a);
        $body = strip_tags($htmlbody);

        // Send email.
        $user = clone($USER);
        $user->email = $entry->address;
        $user->firstname = get_string('pluginname', 'block_clampmail');
        $user->lastname = get_string('alternate', 'block_clampmail');

        $result = email_to_user($user, $from, $subject, $body, $htmlbody);

        // Create the event, trigger it.
        $event = \block_clampmail\event\alternate_email_added::create([
            'courseid' => $course->id,
            'context' => \context_course::instance($course->id),
            'other' => [
                'address' => $entry->address,
            ],
        ]);
        $event->trigger();

        $html = $OUTPUT->box_start();

        if ($result) {
            $html .= $OUTPUT->notification(get_string('alternate_saved', 'block_clampmail', $entry), 'notifysuccess');
            $html .= \html_writer::tag('p', get_string('alternate_success', 'block_clampmail', $entry));
        } else {
            $html .= $OUTPUT->notification(get_string('alternate_failure', 'block_clampmail', $entry));
        }

        $html .= $OUTPUT->continue_button($url);
        $html .= $OUTPUT->box_end();

        return $html;
    }

    /**
     * Render form for modifying alternate email.
     *
     * @param stdClass $course the course
     * @param int $id the alternate email id
     * @return string
     */
    public static function interact($course, $id) {
        $form = new alternate_form(null, [
            'course' => $course, 'action' => self::INTERACT,
        ]);

        if ($form->is_cancelled()) {
            redirect(self::base_url($course->id));
        } else if ($data = $form->get_data()) {
            global $DB;

            // Check if email exists in this course.
            $older = $DB->get_record('block_clampmail_alternate', [
                'address' => $data->address, 'courseid' => $data->courseid,
            ]);

            if ($older) {
                $data->id = $older->id;
                $data->valid = $older->valid;
            } else if (!empty($data->id)) {
                // Changed address?
                if ($data->valid) {
                    $older = self::get_one($id);

                    $valid = $older->address != $data->address ? 0 : 1;

                    $data->valid = $valid;
                }

                $DB->update_record('block_clampmail_alternate', $data);
            } else {
                unset($data->id);
                $data->id = $DB->insert_record('block_clampmail_alternate', $data);
            }

            $action = $data->valid ? self::VERIFY : self::INFORMATION;

            redirect(self::base_url($course->id, [
                'action' => $action, 'id' => $data->id,
            ]));
        }

        if ($id) {
            $form->set_data(self::get_one($id));
        }

        return $form->render();
    }

    /**
     * Output list of alternate email addresses.
     *
     * @param stdClass $course the course
     * @return string
     */
    public static function view($course) {
        global $OUTPUT;

        $alternates = self::get($course);

        $newurl = self::base_url($course->id, ['action' => self::INTERACT]);

        if (empty($alternates)) {
            $html = $OUTPUT->notification(get_string('no_alternates', 'block_clampmail', $course));
            $html .= $OUTPUT->continue_button($newurl);
            return $html;
        }

        $table = new \html_table();
        $table->head = [
            get_string('email'),
            get_string('alternate_activation_status', 'block_clampmail'),
            get_string('action'),
        ];

        $approval = [get_string('alternate_waiting', 'block_clampmail'),
            get_string('alternate_approved', 'block_clampmail')];

        $icons = [
            self::INTERACT => $OUTPUT->pix_icon('i/edit', get_string('edit')),
            self::DELETE => $OUTPUT->pix_icon('t/delete', get_string('delete')),
        ];

        foreach ($alternates as $email) {
            $editurl = self::base_url($course->id, [
                'action' => self::INTERACT, 'id' => $email->id,
            ]);

            $edit = \html_writer::link($editurl, $icons[self::INTERACT]);

            $deleteurl = self::base_url($course->id, [
                'action' => self::DELETE, 'id' => $email->id,
            ]);

            $delete = \html_writer::link($deleteurl, $icons[self::DELETE]);

            $row = [
                $email->address,
                $approval[$email->valid],
                implode(' | ', [$edit, $delete]),
            ];

            $table->data[] = new \html_table_row($row);
        }

        $newlink = \html_writer::link($newurl, get_string('alternate_new', 'block_clampmail'));

        $html = \html_writer::tag('div', $newlink, ['class' => 'new_link']);
        $html .= $OUTPUT->box_start();
        $html .= \html_writer::table($table);
        $html .= $OUTPUT->box_end();
        return $html;
    }
}
