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
 * interactivetree block installation.
 *
 * @package    block_interactivetree

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_block_interactivetree_install() {
    global $DB;
    $temptreedata = new stdClass();
    $temptreedata->id = 1;
    $temptreedata->nm = 'root';
    $temptreedata->url = null;
    $DB->insert_record('block_interactivetree_data', $temptreedata);

    $temptreestruct = new stdClass();
    $temptreestruct->id = 1;
    $temptreestruct->lft = 1;
    $temptreestruct->rgt = 12;
    $temptreestruct->lvl = 0;
    $temptreestruct->pid = 0;
    $temptreestruct->pos = 0;
    $DB->insert_record('block_interactivetree_struct', $temptreestruct);
}
