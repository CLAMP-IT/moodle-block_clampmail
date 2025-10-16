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
 * Form definition for configuring the block.
 *
 * @package   block_clampmail
 * @copyright 2012 Louisiana State University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_clampmail;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

/**
 * Form definition for configuring the block.
 *
 * @package   block_clampmail
 * @copyright 2012 Louisiana State University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config_form extends \moodleform {
    /**
     * Code that defines the form.
     */
    public function definition() {
        $mform =& $this->_form;

        $resetlink = \html_writer::link(
            new \moodle_url('/blocks/clampmail/config.php', [
                'courseid' => $this->_customdata['courseid'],
                'reset' => 1,
            ]),
            get_string('reset', 'block_clampmail')
        );
        $mform->addElement('static', 'reset', '', $resetlink);

        $roles = users::get_roles();
        $filterroles =& $mform->addElement(
            'select',
            'roleselection',
            get_string('select_roles', 'block_clampmail'),
            $roles
        );

        $filterroles->setMultiple(true);

        $cansend =& $mform->addElement(
            'select',
            'cansend',
            get_string('select_cansend', 'block_clampmail'),
            $roles
        );
        $cansend->setMultiple(true);
        $mform->addHelpButton('cansend', 'select_cansend', 'block_clampmail');

        $options = [
            0 => get_string('none'),
            'idnumber' => get_string('idnumber'),
            'shortname' => get_string('shortname'),
        ];

        $mform->addElement(
            'select',
            'prepend_class',
            get_string('prepend_class', 'block_clampmail'),
            $options
        );

        $studentselect = [0 => get_string('no'), 1 => get_string('yes')];
        $mform->addElement(
            'select',
            'receipt',
            get_string('receipt', 'block_clampmail'),
            $studentselect
        );

        // Groups mode.
        $choices = [];
        $choices[NOGROUPS] = get_string('groupsnone', 'group');
        $choices[SEPARATEGROUPS] = get_string('groupsseparate', 'group');
        $choices[VISIBLEGROUPS] = get_string('groupsvisible', 'group');
        $mform->addElement('select', 'groupmode', get_string('groupmode', 'group'), $choices);
        if ($this->_customdata['groupmodeforce'] == 1) {
            $mform->hardFreeze('groupmode');
        }

        $mform->addElement('submit', 'save', get_string('savechanges'));
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->addRule('roleselection', null, 'required');
        $mform->addRule('cansend', null, 'required');
    }
}
