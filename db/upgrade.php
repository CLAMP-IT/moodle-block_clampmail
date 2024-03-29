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
 * Upgrade tasks.
 *
 * @package   block_clampmail
 * @copyright 2012 Louisiana State University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Handle plugin upgrades.
 *
 * @param int $oldversion the current installed version
 * @return boolean
 */
function xmldb_block_clampmail_upgrade($oldversion) {
    global $CFG;

    require_once($CFG->dirroot . '/blocks/clampmail/db/upgradelib.php');

    if ($oldversion < 2017092301) {
        // Move configuration to plugin namespace.
        block_clampmail_migrate_settings();
        upgrade_plugin_savepoint(true, 2017092301, 'block', 'clampmail');
    }

    return true;
}
