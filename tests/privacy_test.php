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

defined('MOODLE_INTERNAL') || die();

// global $CFG;
//
// require_once(__DIR__ . '/helper.php');
// require_once($CFG->dirroot . '/rating/lib.php');

use \block_clampmail\privacy\provider;

class block_clampmail_privacy_testcase extends \core_privacy\tests\provider_testcase {

    // Include the privacy subcontext_info trait.
    // This includes the subcontext builders.
    // use \mod_forum\privacy\subcontext_info;

    // Include the mod_forum test helpers.
    // This includes functions to create forums, users, discussions, and posts.
    // use helper;

    // Include the privacy helper trait for the ratings API.
    // use \core_rating\phpunit\privacy_helper;

    // Include the privacy helper trait for the tag API.
    // use \core_tag\tests\privacy_helper;

    /**
     * Test setUp.
     */
    public function setUp() {
        global $DB;

        $this->resetAfterTest(true);
        $this->data = array();

        $this->data['provider'] = new provider();

        // Test teacher user.
        $this->data['teacher'] = $this->getDataGenerator()->create_user(array(
            'username'  => 'hfogg',
            'email'     => 'hfogg@brakebills.edu',
            'firstname' => 'Henry',
            'lastname'  => 'Fogg',
        ));

        // Test student user.
        $this->data['student1'] = $this->getDataGenerator()->create_user(array(
            'username'  => 'jwicker',
            'email'     => 'jwicker@brakebills.edu',
            'firstname' => 'Julia',
            'lastname'  => 'Wicker',
        ));

        // Test student user.
        $this->data['student2'] = $this->getDataGenerator()->create_user(array(
            'username'  => 'qcoldwater',
            'email'     => 'qcoldwater@brakebills.edu',
            'firstname' => 'Quentin',
            'lastname'  => 'Coldwater',
        ));

        // Test course.
        $this->data['course'] = $this->getDataGenerator()->create_course(array(
            'shortname' => 'testcourse'
        ));
        // Manual enrolment entry.
        $this->data['manualenrol'] = $DB->get_record('enrol', array('enrol' => 'manual', 'courseid' => $this->data['course']->id));
        $this->data['coursecontext'] = \context_course::instance($this->data['course']->id);

        $page = new \moodle_page();
        $page->set_context($this->data['coursecontext']);
        $page->set_pagelayout('standard');
        $page->set_pagetype('course-view');
        $page->set_course($this->data['course']);
        $page->blocks->load_blocks();
        $page->blocks->add_block_at_end_of_default_region('clampmail');

        $page2 = new \moodle_page();
        $page2->set_context($page->context);
        $page2->set_pagelayout($page->pagelayout);
        $page2->set_pagetype($page->pagetype);
        $page2->set_course($this->data['course']);
        $page->blocks->load_blocks();
        $page2->blocks->load_blocks();
        $blocks = $page2->blocks->get_blocks_for_region($page2->blocks->get_default_region());
        $block = end($blocks);
        $block = block_instance('clampmail', $block->instance);

        $this->data['block'] = $block;

        $this->data['me_plugin'] = new enrol_manual_plugin();

        $this->data['studentrole'] = $DB->get_record('role', array('shortname' => 'student'))->id;
        $this->data['teacherrole'] = $DB->get_record('role', array('shortname' => 'editingteacher'))->id;

        $this->setUser($this->data['teacher']);
    }

    public function insert_message($type, $fromid, $toid, $courseid) {
        global $DB;

        $hash = md5(rand());

        $data = new stdClass();
        $data->courseid = $courseid;
        $data->userid = $fromid;
        $data->mailto = $toid;
        $data->subject = "Subject $hash";
        $data->message = "Message $hash $hash $hash";
        $data->attachment = '';
        $data->time = time();

        $data->id = $DB->insert_record('block_clampmail_' . $type, $data, true);

        return $data;
    }

    public function insert_signature($userid) {
        global $DB;

        $hash = md5(rand());

        $data = new stdClass();
        $data->userid = $userid;
        $data->title = "Title $hash";
        $data->signature = "My name is $hash $hash";

        $data->id = $DB->insert_record('block_clampmail_signatures', $data, true);

        return $data;
    }

    public function delete_data_for_context($context) {
        $classname = $this->get_provider_classname('block_clampmail');
        $classname::_delete_data_for_all_users_in_context($context);
    }

    public function delete_data_for_user($userid) {
        $contextlist = $this->get_contexts_for_userid($userid, 'block_clampmail');

        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($userid),
            'block_clampmail',
            $contextlist->get_contextids()
        );

        $classname = $this->get_provider_classname('block_clampmail');
        $classname::_delete_data_for_user($approvedcontextlist);
    }

    /**
     * Test that a user who is enrolled in a course, but who has never
     * used CLAMPMail will not have any link to that context.
     */
    public function test_user_with_no_data() {
        global $DB;

        $D = $this->data;

        $D['me_plugin']->enrol_user($D['manualenrol'], $D['teacher']->id, $D['teacherrole']);

        // Test that no contexts were retrieved.
        $contextlist = $this->get_contexts_for_userid($D['teacher']->id, 'block_clampmail');
        $contexts = $contextlist->get_contextids();
        $this->assertCount(0, $contexts);

        // Attempting to export data for this context should return nothing either.
        $this->export_all_data_for_user($D['teacher']->id, 'block_clampmail');
        $writer = \core_privacy\local\request\writer::with_context($D['coursecontext']);
        // The provider should always export data for any context explicitly asked of it, but there should be no
        // metadata, files, or discussions.
        $this->assertEmpty($writer->get_data([get_string('pluginname', 'block_clampmail')]));
        $this->assertEmpty($writer->get_all_metadata([]));
        $this->assertEmpty($writer->get_files([]));

        // And there should be nothing in the user context either.
        $usercontext = \context_user::instance($D['teacher']->id);
        $writer = \core_privacy\local\request\writer::with_context($usercontext);
        $this->assertEmpty($writer->get_data([get_string('pluginname', 'block_clampmail')]));
        $this->assertEmpty($writer->get_all_metadata([get_string('pluginname', 'block_clampmail')]));
        $this->assertEmpty($writer->get_files([get_string('pluginname', 'block_clampmail')]));
    }

    /**
     * Test exporting data for a user who has sent messages, drafts, and a
     * signature.
     */
    public function test_export_user_data() {
        global $DB;

        $D = $this->data;

        $D['me_plugin']->enrol_user($D['manualenrol'], $D['teacher']->id, $D['teacherrole']);

        $this->insert_message('log', $D['teacher']->id, $D['student1']->id, $D['course']->id);
        $this->insert_message('drafts', $D['teacher']->id, $D['student1']->id, $D['course']->id);
        $this->insert_signature($D['teacher']->id);

        // Test that correct contexts were retrieved (one course, one user).
        $contextlist = $this->get_contexts_for_userid($D['teacher']->id, 'block_clampmail');
        $contexts = $contextlist->get_contextids();

        $usercontext = \context_user::instance($D['teacher']->id);

        $this->assertCount(2, $contexts);
        $this->assertContains($usercontext->id, $contexts);
        $this->assertContains($D['coursecontext']->id, $contexts);

        // Testing data export.
        $this->export_all_data_for_user($D['teacher']->id, 'block_clampmail');

        // In course context...
        $writer = \core_privacy\local\request\writer::with_context($D['coursecontext']);
        $this->assertNotEmpty($writer->get_data([get_string('pluginname', 'block_clampmail')]));
        $this->assertNotEmpty($writer->get_all_metadata([get_string('pluginname', 'block_clampmail')]));
        // $this->assertNotEmpty($writer->get_files([get_string('pluginname', 'block_clampmail')]));

        // In user context...
        $writer = \core_privacy\local\request\writer::with_context($usercontext);
        $this->assertNotEmpty($writer->get_data([get_string('pluginname', 'block_clampmail')]));
        $this->assertNotEmpty($writer->get_all_metadata([get_string('pluginname', 'block_clampmail')]));
        // $this->assertEmpty($writer->get_files([]));
    }

    /**
     * Test deleting all data for a given user.
     */
    public function test_delete_user_data() {
        global $DB;

        $D = $this->data;

        $D['me_plugin']->enrol_user($D['manualenrol'], $D['teacher']->id, $D['teacherrole']);

        $usercontext = \context_user::instance($D['teacher']->id);

        $this->insert_message('log', $D['teacher']->id, $D['student1']->id, $D['course']->id);
        $this->insert_message('drafts', $D['teacher']->id, $D['student1']->id, $D['course']->id);
        $this->insert_signature($D['teacher']->id);

        $this->delete_data_for_user($D['teacher']->id);

        $this->export_all_data_for_user($D['teacher']->id, 'block_clampmail');

        // In course context...
        $writer = \core_privacy\local\request\writer::with_context($D['coursecontext']);
        $this->assertEmpty($writer->get_data([get_string('pluginname', 'block_clampmail')]));
        $this->assertEmpty($writer->get_all_metadata([get_string('pluginname', 'block_clampmail')]));
        // $this->assertNotEmpt y($writer->get_files([get_string('pluginname', 'block_clampmail')]));

        // In user context...
        $writer = \core_privacy\local\request\writer::with_context($usercontext);
        $this->assertEmpty($writer->get_data([get_string('pluginname', 'block_clampmail')]));
        $this->assertEmpty($writer->get_all_metadata([get_string('pluginname', 'block_clampmail')]));
        // $this->assertEmpty($writer->get_files([]));
    }

    /**
     * Test deleting all data for a given context.
     */
    public function test_delete_all_data() {
        global $DB;

        $D = $this->data;

        $D['me_plugin']->enrol_user($D['manualenrol'], $D['student1']->id, $D['studentrole']);
        $D['me_plugin']->enrol_user($D['manualenrol'], $D['student2']->id, $D['studentrole']);
        $D['me_plugin']->enrol_user($D['manualenrol'], $D['teacher']->id, $D['teacherrole']);

        $this->insert_message('log', $D['teacher']->id, $D['student1']->id, $D['course']->id);
        $this->insert_message('drafts', $D['teacher']->id, $D['student1']->id, $D['course']->id);
        $this->insert_message('log', $D['student1']->id, $D['student2']->id, $D['course']->id);
        $this->insert_message('drafts', $D['student1']->id, $D['student2']->id, $D['course']->id);
        $this->insert_message('log', $D['student2']->id, $D['teacher']->id, $D['course']->id);
        $this->insert_message('drafts', $D['student2']->id, $D['teacher']->id, $D['course']->id);
        $this->insert_signature($D['teacher']->id);
        $this->insert_signature($D['student1']->id);
        $this->insert_signature($D['student2']->id);

        $this->delete_data_for_context($D['coursecontext']);

        $this->export_all_data_for_user($D['teacher']->id, 'block_clampmail');
        $writer = \core_privacy\local\request\writer::with_context($D['coursecontext']);
        $this->assertEmpty($writer->get_data([get_string('pluginname', 'block_clampmail')]));
        $this->assertEmpty($writer->get_all_metadata([get_string('pluginname', 'block_clampmail')]));
        $ucontext = \context_user::instance($D['teacher']->id);
        $uwriter = \core_privacy\local\request\writer::with_context($ucontext);
        $this->assertNotEmpty($uwriter->get_data([get_string('pluginname', 'block_clampmail')]));

        $this->export_all_data_for_user($D['student1']->id, 'block_clampmail');
        $writer = \core_privacy\local\request\writer::with_context($D['coursecontext']);
        $this->assertEmpty($writer->get_data([get_string('pluginname', 'block_clampmail')]));
        $this->assertEmpty($writer->get_all_metadata([get_string('pluginname', 'block_clampmail')]));
        $ucontext = \context_user::instance($D['student1']->id);
        $uwriter = \core_privacy\local\request\writer::with_context($ucontext);
        $this->assertNotEmpty($uwriter->get_data([get_string('pluginname', 'block_clampmail')]));

        $this->export_all_data_for_user($D['student2']->id, 'block_clampmail');
        $writer = \core_privacy\local\request\writer::with_context($D['coursecontext']);
        $this->assertEmpty($writer->get_data([get_string('pluginname', 'block_clampmail')]));
        $this->assertEmpty($writer->get_all_metadata([get_string('pluginname', 'block_clampmail')]));
        $ucontext = \context_user::instance($D['student2']->id);
        $uwriter = \core_privacy\local\request\writer::with_context($ucontext);
        $this->assertNotEmpty($uwriter->get_data([get_string('pluginname', 'block_clampmail')]));
    }
}
