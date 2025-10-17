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
 * Configuration page.
 *
 * @package   block_clampmail
 * @copyright 2012 Louisiana State University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');

require_login();

$courseid = required_param('courseid', PARAM_INT);
$reset = optional_param('reset', 0, PARAM_INT);

if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    print_error('no_course', 'block_clampmail', '', $courseid);
}

$context = context_course::instance($courseid);

require_capability('block/clampmail:canconfig', $context);

$blockname = get_string('pluginname', 'block_clampmail');
$header = get_string('config', 'block_clampmail');

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url('/blocks/clampmail/config.php', ['courseid' => $courseid]);
$PAGE->set_title($blockname . ': ' . $header);
$PAGE->set_heading($blockname . ': ' . $header);
$PAGE->navbar->add($blockname, new moodle_url('/blocks/clampmail/email.php', ['courseid' => $courseid]));
$PAGE->navbar->add($header);
$PAGE->set_pagetype('CLAMPMail');
$PAGE->set_pagelayout('standard');

$changed = false;

if ($reset) {
    $changed = true;
    block_clampmail\config::reset_course_configuration($courseid);
}

$form = new block_clampmail\config_form(null, [
    'courseid' => $courseid,
    'groupmodeforce' => $course->groupmodeforce,
]);

if ($data = $form->get_data()) {
    $config = get_object_vars($data);

    unset($config['save'], $config['courseid']);

    $config['roleselection'] = implode(',', $config['roleselection']);

    block_clampmail\config::save_configuration($courseid, $config);
    $changed = true;
}

$config = block_clampmail\config::load_configuration($course);
$config['roleselection'] = explode(',', $config['roleselection']);
$form->set_data($config);

echo $OUTPUT->header();
echo $OUTPUT->heading($blockname);
echo block_clampmail\navigation::print_navigation(
    block_clampmail\navigation::get_links($course->id, $context),
    $header
);

echo $OUTPUT->box_start();

if ($changed) {
    echo $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
}

$form->display();
echo $OUTPUT->box_end();

echo $OUTPUT->footer();
