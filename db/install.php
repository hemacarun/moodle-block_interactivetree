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
    $temp_treedata= new stdClass();
    $temp_treedata->id=1;
    $temp_treedata->nm='root';
    $temp_treedata->url=null;
    $DB->insert_record('tree_data',$temp_treedata);
    
    $temp_treestruct= new stdClass();
    $temp_treestruct->id=1;
    $temp_treestruct->lft=1;
    $temp_treestruct->rgt=12;
    $temp_treestruct->lvl=0;
    $temp_treestruct->pid=0;
    $temp_treestruct->pos=0;
    $DB->insert_record('tree_struct',$temp_treestruct);

/// Disable this block by default (because Feedback is not technically part of 2.0)
  //  $DB->set_field('block', 'visible', 0, array('name'=>'feedback'));

}

