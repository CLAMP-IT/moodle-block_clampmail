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
 * Defines adhoc task for sending emails.
 * @package   block_clampmail
 * @copyright 2023 Lafayette College ITS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_clampmail\task;

/**
 * Adhoc task for sending emails.
 *
 * @package   block_clampmail
 * @copyright 2023 Lafayette College ITS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_task extends \core\task\adhoc_task {
    /**
     * Return the name of the component.
     *
     * @return string The name of the component.
     */
    public function get_component() {
        return 'block_clampmail';
    }

    /**
     * Execute the task
     */
    public function execute() {
        $data = $this->get_custom_data();

        // Abort if no one to email.
        if(empty($data->mailto)) {
            mtrace("No users to email");
            return;
        }

        // Send emails.
        foreach ($data->mailto as $user) {
            $success = email_to_user($user, $data->sender, $data->subject,
                $data->messagetext, $data->messagehtml, $data->file, $data->filename, false, $data->replyto);
            if (!$success) {
                $this->failed($user, $data->sender, $data->subject);
            }
        }

        if (!empty($data->receipt)) {
            email_to_user($data->sender,  $data->sender, $data->subject,
            $data->messagetext, $data->messagehtml, $data->file, $data->filename, false, $data->replyto);
        }

        if (!empty($data->actualfile)) {
            unlink($data->actualfile);
        }
    }

    /**
     * Notify the sender of a failed email.
     *
     * @param stdClass $to A user object
     * @param stdClass $from A user object
     * @param string $subject plain text subject line of the email
     */
    protected function failed($to, $from, $subject) {
        $message = new \core\message\message();
        $message->component = 'block_clampmail';
        $message->name = 'emaildeliveryfailure';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $from;
        $message->subject = get_string('no_email_subject', 'block_clampmail');
        $message->fullmessage = get_string('no_email_body', 'block_clampmail',
            [
                'subject' => $subject,
                'firstname' => $to->firstname,
                'lastname' => $to->lastname,
            ]
        );
        $message->fullmessageformat = FORMAT_PLAIN;
        message_send($message);
    }
}