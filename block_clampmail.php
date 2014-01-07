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
 * @copyright 2013 Collaborative Liberal Arts Moodle Project
 * @copyright 2012 Louisiana State University (original Quickmail block)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/blocks/clampmail/lib.php');

class block_clampmail extends block_list {
    public function init() {
        $this->title = clampmail::_s('pluginname');
    }

    public function applicable_formats() {
        return array('site' => false, 'my' => false, 'course' => true);
    }

    public function has_config() {
        return true;
    }

    public function get_content() {
        global $CFG, $COURSE, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        $context = context_course::instance($COURSE->id);

        $config = clampmail::load_config($COURSE->id);
        $permission = has_capability('block/clampmail:cansend', $context);

        $icon_class = array('class' => 'icon');

        if ($permission) {
            $cparam = array('courseid' => $COURSE->id);

            $send_email_str = clampmail::_s('composenew');
            $send_email = html_writer::link(
                new moodle_url('/blocks/clampmail/email.php', $cparam),
                $send_email_str
            );
            $this->content->items[] = $send_email;
            $this->content->icons[] = $OUTPUT->pix_icon('i/email', $send_email_str, 'moodle', $icon_class);

            $signature_str = clampmail::_s('signature');
            $signature = html_writer::link(
                new moodle_url('/blocks/clampmail/signature.php', $cparam),
                $signature_str
            );
            $this->content->items[] = $signature;
            $this->content->icons[] = $OUTPUT->pix_icon('i/edit', $signature_str, 'moodle', $icon_class);

            $draft_params = $cparam + array('type' => 'drafts');
            $drafts_email_str = clampmail::_s('drafts');
            $drafts = html_writer::link(
                new moodle_url('/blocks/clampmail/emaillog.php', $draft_params),
                $drafts_email_str
            );
            $this->content->items[] = $drafts;
            $this->content->icons[] = $OUTPUT->pix_icon('i/settings', $drafts_email_str, 'moodle', $icon_class);

            $history_str = clampmail::_s('history');
            $history = html_writer::link(
                new moodle_url('/blocks/clampmail/emaillog.php', $cparam),
                $history_str
            );
            $this->content->items[] = $history;
            $this->content->icons[] = $OUTPUT->pix_icon('i/settings', $history_str, 'moodle', $icon_class);
        }

        if (has_capability('block/clampmail:allowalternate', $context)) {
            $alt_str = clampmail::_s('alternate');
            $alt = html_writer::link(
                new moodle_url('/blocks/clampmail/alternate.php', $cparam),
                $alt_str
            );

            $this->content->items[] = $alt;
            $this->content->icons[] = $OUTPUT->pix_icon('i/edit', $alt_str, 'moodle', $icon_class);
        }

        if (has_capability('block/clampmail:canconfig', $context)) {
            $config_str = clampmail::_s('config');
            $config = html_writer::link(
                new moodle_url('/blocks/clampmail/config.php', $cparam),
                $config_str
            );
            $this->content->items[] = $config;
            $this->content->icons[] = $OUTPUT->pix_icon('i/settings', $config_str, 'moodle', $icon_class);
        }

        return $this->content;
    }
}
