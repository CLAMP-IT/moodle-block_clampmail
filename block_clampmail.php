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
        $this->title = get_string('pluginname', 'block_clampmail');
    }

    public function applicable_formats() {
        return array('site' => false, 'my' => false, 'course' => true);
    }

    public function has_config() {
        return true;
    }

    public function get_content() {
        global $COURSE, $OUTPUT;

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

        $iconclass = array('class' => 'icon');

        if ($permission) {
            $cparam = array('courseid' => $COURSE->id);

            $sendemailstr = get_string('composenew', 'block_clampmail');
            $sendemail = html_writer::link(
                new moodle_url('/blocks/clampmail/email.php', $cparam),
                $sendemailstr
            );
            $this->content->items[] = $sendemail;
            $this->content->icons[] = $OUTPUT->pix_icon('i/email', $sendemailstr, 'moodle', $iconclass);

            $signaturestr = get_string('signature', 'block_clampmail');
            $signature = html_writer::link(
                new moodle_url('/blocks/clampmail/signature.php', $cparam),
                $signaturestr
            );
            $this->content->items[] = $signature;
            $this->content->icons[] = $OUTPUT->pix_icon('i/edit', $signaturestr, 'moodle', $iconclass);

            $draftparams = $cparam + array('type' => 'drafts');
            $draftsemailstr = get_string('drafts', 'block_clampmail');
            $drafts = html_writer::link(
                new moodle_url('/blocks/clampmail/emaillog.php', $draftparams),
                $draftsemailstr
            );
            $this->content->items[] = $drafts;
            $this->content->icons[] = $OUTPUT->pix_icon('i/settings', $draftsemailstr, 'moodle', $iconclass);

            $historystr = get_string('history', 'block_clampmail');
            $history = html_writer::link(
                new moodle_url('/blocks/clampmail/emaillog.php', $cparam),
                $historystr
            );
            $this->content->items[] = $history;
            $this->content->icons[] = $OUTPUT->pix_icon('i/settings', $historystr, 'moodle', $iconclass);
        }

        if (has_capability('block/clampmail:allowalternate', $context)) {
            $altstr = get_string('alternate', 'block_clampmail');
            $alt = html_writer::link(
                new moodle_url('/blocks/clampmail/alternate.php', $cparam),
                $altstr
            );

            $this->content->items[] = $alt;
            $this->content->icons[] = $OUTPUT->pix_icon('i/edit', $altstr, 'moodle', $iconclass);
        }

        if (has_capability('block/clampmail:canconfig', $context)) {
            $configstr = get_string('config', 'block_clampmail');
            $config = html_writer::link(
                new moodle_url('/blocks/clampmail/config.php', $cparam),
                $configstr
            );
            $this->content->items[] = $config;
            $this->content->icons[] = $OUTPUT->pix_icon('i/settings', $configstr, 'moodle', $iconclass);
        }

        return $this->content;
    }
}
