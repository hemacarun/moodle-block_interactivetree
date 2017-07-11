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
defined('MOODLE_INTERNAL') || die();
require_once('../../config.php');
class block_interactivetree_manage {
    public function get_node($id, $options = array()) {
        global $DB;
        $sql  = "SELECT s.id, s.lft, s.rgt, s.lvl, s.pid, s.pos, d.nm
			    FROM {block_interactivetree_struct} s, {block_interactivetree_data} d
			    WHERE s.id = d.id AND s.id = ? ";
        $node = $DB->get_record_sql($sql, array(
            $id
        ));
        if (!$node) {
            throw new Exception('Node does not exist');
        }
        if (isset($options['with_children'])) {
            $node->children = $this->get_children($id, isset($options['deep_children']));
        }
        if (isset($options['with_path'])) {
            $node->path = $this->get_path($id);
        }
        return $node;
    }

    public function get_children($id, $recursive = false) {
        global $DB;
        $sql = false;
        if ($recursive) {
            $node     = $this->get_node($id);
            $sql      = "SELECT  s.id, s.lft, s.rgt, s.lvl, s.pid, s.pos , d.nm
                    FROM  {block_interactivetree_struct} s,{block_interactivetree_data} d
				    WHERE s.id = d.id AND s.lft > :osleft AND s.rgt < :osright
				    ORDER BY s.lft";
            $response = $DB->get_records_sql($sql, array(
                'osleft' => $node->lft,
                'osright' => $node->rgt
            ));
        } else {
            $sql      = " SELECT  s.id, s.lft, s.rgt, s.lvl, s.pid, s.pos , d.nm
		     FROM  {block_interactivetree_struct} s,{block_interactivetree_data} d
		     WHERE s.id = d.id AND s.pid = :parentid ORDER BY  s.pos ";
            $response = $DB->get_records_sql($sql, array(
                'parentid' => $id
            ));
        }
        return $response;
    }

    public function get_path($id) {
        global $DB;
        $node = $this->get_node($id);
        $sql  = false;
        if ($node) {
            $sql = "SELECT  s.id, s.lft, s.rgt, s.lvl, s.pid, s.pos , d.nm
		    FROM  {block_interactivetree_struct} s, {block_interactivetree_data} d
		    WHERE s.id = d.id AND s.lft < :osleft AND
		    s.rgt > :osright ORDER BY s.lft";
        }
        return $sql ? $DB->get_records_sql($sql, array(
            'osleft' => $node->lft,
            'osright' => $node->rgt
        )) : false;
    }

    public function createnode($parent, $position = 0, $data = array()) {
        global $DB;
        $parent = (int) $parent;
        if ($parent == 0) {
            throw new Exception('Parent is 0');
        }
        $parent = $this->get_node($parent, array(
            'with_children' => true
        ));
        if (!$parent->children) {
            $position = 0;
        }
        if ($parent->children && $position >= count($parent->children)) {
            $position = count($parent->children);
        }
        $sql      = array();
        // PREPARE NEW PARENT.
        // Update positions of all next elements.
        $sql[]    = "UPDATE {block_interactivetree_struct}
		    SET pos = pos + 1
		    WHERE pid  = :parentstructureid  AND
		    pos  >= :position";
        $params[] = array(
            'parentstructureid' => $parent->id,
            'position' => $position
        );
        // Update left indexes.
        $refleft  = false;
        if (!$parent->children) {
            $refleft = $parent->rgt;
        } else if (!isset($parent->children[$position])) {
            $refleft = $parent->rgt;
        } else {
            $position      = (int) $position;
            $parentchild   = $parent->children;
            $parentpos     = $parentchild->$position;
            $parentposleft = $parentpos->lft;
            $refleft       = $parentposleft;
        }
        $sql[]    = "UPDATE {block_interactivetree_struct}
		            SET lft = lft + 2
		            WHERE lft  >= :ref";
        $params[] = array(
            'ref' => $refleft
        );
        // Update right indexes.
        $refright = false;
        if (!$parent->children) {
            $refright = $parent->rgt;
        } else if (!isset($parent->children->$position)) {
            $refright = $parent->rgt;
        } else {
            $position      = (int) $position;
            $parentchild   = $parent->children;
            $parentpos     = $parentchild->$position;
            $parentposleft = $parentpos->lft;
            $refright      = $parentposleft + 1;
        }
        $sql[]           = "UPDATE {block_interactivetree_struct}
		                SET  rgt = rgt + 2 WHERE rgt >= :refright";
        $params[]        = array(
            'refright' => $refright
        );
        $inserttemp      = new Stdclass();
        $inserttemp->id  = null;
        $inserttemp->lft = (int) $refleft;
        $inserttemp->rgt = (int) $refleft + 1;
        $inserttemp->lvl = (int) $parent->pid + 1;
        $inserttemp->pid = $parent->id;
        $inserttemp->pos = $position;
        $node            = $DB->insert_record('block_interactivetree_struct', $inserttemp);
        foreach ($sql as $key => $values) {
            try {
                $DB->execute($values, $params[$key]);
            } catch (Exception $e) {
                throw new Exception('Could not create');
            }
        }
        if ($data && count($data)) {
            if (!$this->renamenode($node, $data)) {
                $this->removenode($node);
                throw new Exception('Could not rename after create');
            }
        }
        return $node;
    }

    public function removenode($id) {
        global $DB;
        $id = (int) $id;
        if (!$id || $id === 1) {
            throw new Exception('Could not create inside roots');
        }
        $data = $this->get_node($id, array(
            'with_children' => true,
            'deep_children' => true
        ));
        $dif  = $data->rgt - $data->lft + 1;
        if ($id) {
            $childrenexists = $DB->get_records('block_interactivetree_struct', array(
                'pid' => $id
            ));
            if ($childrenexists) {
                throw new Exception('could not remove');
            }
        }
        $sql      = array();
        $params   = array();
        // Deleting node and its children from structure.
        $sql[]    = "DELETE FROM {block_interactivetree_struct}
		          WHERE lft >= :osleft  AND rgt <= :osright AND id = :osid ";
        $params[] = array(
            'osleft' => $data->lft,
            'osright' => $data->rgt,
            'osid' => $data->id
        );
        // Shift left indexes of nodes right of the node.
        $sql[]    = "UPDATE {block_interactivetree_struct}
			      SET lft = lft - :setleft WHERE lft > :osleft";
        $params[] = array(
            'setleft' => $dif,
            'osleft' => $data->rgt
        );
        // Shift right indexes of nodes right of the node and the node's parents.
        $sql[]    = "UPDATE {block_interactivetree_struct }
				  SET rgt = rgt - :setright WHERE rgt > :osright";
        $params[] = array(
            'setright' => $dif,
            'osright' => $data->lft
        );
        // Update position of siblings below the deleted node.
        $sql[]    = "UPDATE {block_interactivetree_struct }
				  SET pos = pos - 1  WHERE pid = :osparentid  AND pos > :ospos";
        $params[] = array(
            'osparentid' => $data->pid,
            'ospos' => $data->pos
        );
        // Delete from data table.
        $tmp      = array();
        $tmp[]    = (int) $data->id;
        if ($data->children && is_array($data->children)) {
            foreach ($data->children as $v) {
                $tmp[] = $v->id;
            }
        }
        $sql[] = "DELETE FROM {block_interactivetree_data} WHERE id IN (" . implode(',', $tmp) . ")";
        foreach ($sql as $k => $v) {
            try {
                $DB->execute($v, $params[$k]);
            } catch (Exception $e) {
                throw new Exception('Could not remove');
            }
        }
        return true;
    }

    public function renamenode($id, $data) {
        global $DB;
        $existingnode = $DB->get_record_sql("SELECT 1 AS res FROM {block_interactivetree_struct} WHERE id = $id");
        if (!$existingnode->res) {
            throw new Exception('Could not rename non-existing node');
        }
        $tmp = array();
        if (isset($data['nm'])) {
            $tmp['nm'] = $data['nm'];
        }
        if (count($tmp)) {
            $tmp['id'] = $id;
            $sql       = "INSERT INTO {block_interactivetree_data} (" . implode(',', array_keys($tmp)) . ")
					VALUES(?" . str_repeat(',?', count($tmp) - 1) . ") ON DUPLICATE KEY UPDATE
					" . implode(' = ?, ', array_keys($tmp)) . " = ?";
            $par       = array_merge(array_values($tmp), array_values($tmp));
            try {
                $DB->execute($sql, $par);
            } catch (Exception $e) {
                throw new Exception('Could not rename the link');
            }
        }
        return true;
    }
}
