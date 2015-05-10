<?php

// TO DO: better exceptions, use params
class tree {

    protected $DB;
    protected $options = null;
    protected $default = array(
        'structure_table' => 'structure', // the structure table (containing the id, left, right, level, parent_id and position fields)
        'data_table' => 'structure', // table for additional fields (apart from structure ones, can be the same as structure_table)
        'data2structure' => 'id', // which field from the data table maps to the structure table
        'structure' => array(// which field (value) maps to what in the structure (key)
            'id' => 'id',
            'left' => 'lft',
            'right' => 'rgt',
            'level' => 'lvl',
            'parent_id' => 'pid',
            'position' => 'pos'
        ),
        'data' => array()   // array of additional fields from the data table
    );
    protected $osparentid;
    protected $osid;
    protected $ospos;
    protected $osleft;
    protected $osright;
    protected $oslevel;

    public function __construct(array $options = array()) {
        $this->options = array_merge($this->default, $options);
        $this->osparentid = $this->options['structure']['parent_id'];
        $this->osid = $this->options['structure']['id'];
        $this->ospos = $this->options['structure']['position'];
        $this->osleft = $this->options['structure']['left'];
        $this->osright = $this->options['structure']['right'];
        $this->oslevel = $this->options['structure']['level'];
    }

    public function get_node($id, $options = array()) {
        global $DB, $CFG, $PAGE;

        $node = $DB->get_record_sql("
			SELECT 
				s." . implode(", s.", $this->options['structure']) . ", 
				d." . implode(", d.", $this->options['data']) . " 
			FROM 
                                " . $DB->get_prefix() . $this->options['structure_table'] . " s, 
                                " . $DB->get_prefix() . $this->options['data_table'] . " d 
			WHERE 
				s." . $this->options['structure']['id'] . " = d." . $this->options['data2structure'] . " AND 
				s." . $this->options['structure']['id'] . " = " . (int) $id
        );
        $sql = "
			SELECT 
				s." . implode(", s.", $this->options['structure']) . ", 
				d." . implode(", d.", $this->options['data']) . " 
			FROM 
                                " . $DB->get_prefix() . $this->options['structure_table'] . " s, 
                                " . $DB->get_prefix() . $this->options['data_table'] . " d 
			WHERE 
				s." . $this->options['structure']['id'] . " = d." . $this->options['data2structure'] . " AND 
				s." . $this->options['structure']['id'] . " = " . (int) $id;
                   
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
        global $DB, $CFG, $USER;
        $osleft = $this->options['structure']['left'];
        $osright = $this->options['structure']['right'];

        $sql = false;
        if ($recursive) {
            $node = $this->get_node($id);
            $sql = "
				SELECT 
					s." . implode(", s.", $this->options['structure']) . ", 
					d." . implode(", d.", $this->options['data']) . " 
				FROM 
                        " . $DB->get_prefix() . $this->options['structure_table'] . " as s, 
                        " . $DB->get_prefix() . $this->options['data_table'] . " as d 
				WHERE 
					s." . $this->options['structure']['id'] . " = d." . $this->options['data2structure'] . " AND 
					s." . $this->options['structure']['left'] . " > " . $node->$osleft . " AND 
					s." . $this->options['structure']['right'] . " < " . $node->$osright . " 
				ORDER BY 
					s." . $this->options['structure']['left'] . "
			";
        } else {
            $sql = "
				SELECT 
					s." . implode(", s.", $this->options['structure']) . ", 
					d." . implode(", d.", $this->options['data']) . " 
				FROM 
                        " . $DB->get_prefix() . $this->options['structure_table'] . " as s, 
                        " . $DB->get_prefix() . $this->options['data_table'] . " as  d 
				WHERE 
					s." . $this->options['structure']['id'] . " = d." . $this->options['data2structure'] . " AND 
					s." . $this->options['structure']['parent_id'] . " = " . (int) $id . " 
				ORDER BY 
					s." . $this->options['structure']['position'] . "
			";
        }
        $response = $DB->get_records_sql($sql);
        return $response;
    }

    public function get_path($id) {
        global $DB, $CFG, $USER;
        $node = $this->get_node($id);        
        $osleft = $this->osleft;
        $osright = $this->osright;
        $sql = false;
        if ($node) {
            
          $sql = "
				SELECT 
					s." . implode(", s.", $this->options['structure']) . ", 
					d." . implode(", d.", $this->options['data']) . " 
				FROM 
                        " . $DB->get_prefix() . $this->options['structure_table'] . " s, 
                        " . $DB->get_prefix() . $this->options['data_table'] . " d 
				WHERE 
					s." . $this->options['structure']['id'] . " = d." . $this->options['data2structure'] . " AND 
					s." . $this->options['structure']['left'] . " < " . $node->$osleft . " AND 
					s." . $this->options['structure']['right'] . " > " . $node->$osright . " 
				ORDER BY 
					s." . $this->options['structure']['left'] . "
			";
        }
        
        return $sql ? $DB->get_records_sql($sql) : false;
    }

    public function mk($parent, $position = 0, $data = array()) {
        global $DB, $CFG, $USER;
        $parent = (int) $parent;
        if ($parent == 0) {
            throw new Exception('Parent is 0');
        }
        $parent = $this->get_node($parent, array('with_children' => true));

        if (!$parent->children) {
            $position = 0;
        }
        if ($parent->children && $position >= count($parent->children)) {
            $position = count($parent->children);
        }

        $sql = array();
        $par = array();

        // PREPARE NEW PARENT 
        // update positions of all next elements
        $option_structureid = $this->options['structure']['id'];

        $sql[] = "
                        UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . " 
				SET " . $this->options['structure']["position"] . " = " . $this->options['structure']["position"] . " + 1 
			WHERE 
				" . $this->options['structure']["parent_id"] . " = " . (int) $parent->$option_structureid . " AND 
				" . $this->options['structure']["position"] . " >= " . $position . "
			";
        $par[] = false;



        $options_structure_right = $this->options['structure']["right"];
        $options_structure_left = $this->options['structure']["left"];
        // update left indexes
        $ref_lft = false;
        if (!$parent->children) {
            $ref_lft = $parent->$options_structure_right;
        } else if (!isset($parent->children[$position])) {
            $ref_lft = $parent->$options_structure_right;
        } else {
            $position = (int) $position;
            $parentchild = $parent->children;
            $parentpos = $parentchild->$position;
            $parentpos_left = $parentpos->$options_structure_left;
            //$ref_lft = $parent['children'][(int)$position][$this->options['structure']["left"]];
            $ref_lft = $parentpos_left;
        }
        $sql[] = "
                        UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . " 
				SET " . $this->options['structure']["left"] . " = " . $this->options['structure']["left"] . " + 2 
			WHERE 
				" . $this->options['structure']["left"] . " >= " . (int) $ref_lft . " 
			";
        $par[] = false;

        // update right indexes
        $ref_rgt = false;
        if (!$parent->children) {
            $ref_rgt = $parent->$options_structure_right;
        } else if (!isset($parent->children->$position)) {
            $ref_rgt = $parent->$options_structure_right;
        } else {
            $position = (int) $position;
            $parentchild = $parent->children;
            $parentpos = $parentchild->$position;
            $parentpos_left = $parentpos->$options_structure_left;

            //$ref_rgt = $parent['children'][(int)$position][$this->options['structure']["left"]] + 1;
            $ref_rgt = $parentpos_left + 1;
        }
        $sql[] = "
                        UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . " 
				SET " . $this->options['structure']["right"] . " = " . $this->options['structure']["right"] . " + 2 
			WHERE 
				" . $this->options['structure']["right"] . " >= " . (int) $ref_rgt . " 
			";
        $par[] = false;

  
        $tmp = array();
        $insert_temp = new Stdclass();
        foreach ($this->options['structure'] as $k => $v) {
            switch ($k) {
                case 'id':
                    $tmp[] = null;
                    $insert_temp->id = null;
                    break;
                case 'left':
                    $tmp[] = (int) $ref_lft;
                    $insert_temp->lft = (int) $ref_lft;
                    break;
                case 'right':
                    $tmp[] = (int) $ref_lft + 1;
                    $insert_temp->rgt = (int) $ref_lft + 1;
                    break;
                case 'level':
                    $tmp[] = (int) $parent->$v + 1;
                    $insert_temp->lvl = (int) $parent->$v + 1;
                    break;
                case 'parent_id':
                    $tmp[] = $parent->$option_structureid;
                    $insert_temp->pid = $parent->$option_structureid;
                    break;
                case 'position':
                    $tmp[] = $position;
                    $insert_temp->pos = $position;
                    break;
                default:
                    $tmp[] = null;
            }
        }
        $par[] = $tmp;       
        $treestruct_table = $this->options['structure_table'];
        $node = $DB->insert_record($treestruct_table, $insert_temp);

        foreach ($sql as $k => $v) {
           
            try {
                $DB->execute($v);
            } catch (Exception $e) {             

                throw new Exception('Could not create');
            }
        }

        if ($data && count($data)) {
            
            if (!$this->rn($node, $data)) {
                $this->rm($node);
                throw new Exception('Could not rename after create');
            }
        }
      
        return $node;
    }

    public function mv($id, $parent, $position = 0) {
        global $CFG, $PAGE, $DB, $USER;
        $id = (int) $id;
        $parent = (int) $parent;
        if ($parent == 0 || $id == 0 || $id == 1) {
            throw new Exception('Cannot move inside 0, or move root node');
        }

        $parent = $this->get_node($parent, array('with_children' => true, 'with_path' => true));
        $id = $this->get_node($id, array('with_children' => true, 'deep_children' => true, 'with_path' => true));
        if (!$parent->children) {
            $position = 0;
        }
        $options_structure_parentid = $this->options['structure']['parent_id'];
        $options_structure_id = $this->options['structure']['id'];
        $options_structure_pos = $this->options['structure']['position'];
        $options_structure_left = $this->options['structure']['left'];
        $options_structure_right = $this->options['structure']['right'];
        $options_structure_level = $this->options['structure']['level'];


        if ($id->$options_structure_parentid == $parent->$options_structure_id && $position > $id->$options_structure_pos) {
            $position ++;
        }
        if ($parent->children && $position >= count($parent->children)) {
            $position = count($parent->children);
        }
        if ($id->$options_structure_left < $parent->$options_structure_left && $id->$options_structure_right > $parent->$options_structure_right) {
            throw new Exception('Could not move parent inside child');
        }

        $tmp = array();
        $tmp[] = (int) $id->$options_structure_id;
        if ($id->children && is_array($id->children)) {
            foreach ($id->children as $c) {
                $tmp[] = (int) $c->$options_structure_id;
            }
        }
        $width = (int) $id->$options_structure_right - (int) $id->$options_structure_left + 1;

        $sql = array();

        // PREPARE NEW PARENT
        // update positions of all next elements
        $sql[] = "
			UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . " 
				SET " . $this->options['structure']["position"] . " = " . $this->options['structure']["position"] . " + 1 
			WHERE 
				" . $this->options['structure']["id"] . " != " . (int) $id->$options_structure_id . " AND 
				" . $this->options['structure']["parent_id"] . " = " . (int) $parent->$options_structure_id . " AND 
				" . $this->options['structure']["position"] . " >= " . $position . "
			";

        // update left indexes
        $ref_lft = false;
        $parent_c = $parent->children;
        if (!$parent->children) {
            $ref_lft = $parent->$options_structure_right;
        } else if (!isset($parent_c->$position)) {
            $ref_lft = $parent->$options_structure_right;
        } else {
            $parent_pos = $parent_c->$position;

            $ref_lft = $parent_pos->$options_structure_left;
        }
        $sql[] = "
			UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . "
				SET " . $this->options['structure']["left"] . " = " . $this->options['structure']["left"] . " + " . $width . " 
			WHERE 
				" . $this->options['structure']["left"] . " >= " . (int) $ref_lft . " AND 
				" . $this->options['structure']["id"] . " NOT IN(" . implode(',', $tmp) . ") 
			";
        // update right indexes
        $ref_rgt = false;
        if (!$parent->children) {
            $ref_rgt = $parent->$options_structure_right;
        } else if (!isset($parent_c->$position)) {
            $ref_rgt = $parent->$options_structure_right;
        } else {
            $parent_pos = $parent_c->$position;
            $ref_rgt = $parent_pos->$options_structure_left + 1;
           
        }
        $sql[] = "
			UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . " 
				SET " . $this->options['structure']["right"] . " = " . $this->options['structure']["right"] . " + " . $width . " 
			WHERE 
				" . $this->options['structure']["right"] . " >= " . (int) $ref_rgt . " AND 
				" . $this->options['structure']["id"] . " NOT IN(" . implode(',', $tmp) . ") 
			";

        // MOVE THE ELEMENT AND CHILDREN
        // left, right and level
        $diff = $ref_lft - (int) $id->$options_structure_left;

        if ($diff > 0) {
            $diff = $diff - $width;
        }
        $ldiff = ((int) $parent->$options_structure_level + 1) - (int) $id->$options_structure_level;
        $sql[] = "
			UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . "
				SET " . $this->options['structure']["right"] . " = " . $this->options['structure']["right"] . " + " . $diff . ", 
					" . $this->options['structure']["left"] . " = " . $this->options['structure']["left"] . " + " . $diff . ", 
					" . $this->options['structure']["level"] . " = " . $this->options['structure']["level"] . " + " . $ldiff . " 
				WHERE " . $this->options['structure']["id"] . " IN(" . implode(',', $tmp) . ") 
		";
        // position and parent_id
        $sql[] = "
			UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . "
				SET " . $this->options['structure']["position"] . " = " . $position . ",
					" . $this->options['structure']["parent_id"] . " = " . (int) $parent->$options_structure_id . " 
				WHERE " . $this->options['structure']["id"] . "  = " . (int) $id->$options_structure_id . " 
		";

        // CLEAN OLD PARENT
        // position of all next elements
        $sql[] = "
			UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . "
				SET " . $this->options['structure']["position"] . " = " . $this->options['structure']["position"] . " - 1 
			WHERE 
				" . $this->options['structure']["parent_id"] . " = " . (int) $id->$options_structure_parentid . " AND 
				" . $this->options['structure']["position"] . " > " . (int) $id->$options_structure_pos;
        // left indexes
        $sql[] = "
			UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . "
				SET " . $this->options['structure']["left"] . " = " . $this->options['structure']["left"] . " - " . $width . " 
			WHERE 
				" . $this->options['structure']["left"] . " > " . (int) $id->$options_structure_right . " AND 
				" . $this->options['structure']["id"] . " NOT IN(" . implode(',', $tmp) . ") 
		";
        // right indexes
        $sql[] = "
			UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . " 
				SET " . $this->options['structure']["right"] . " = " . $this->options['structure']["right"] . " - " . $width . " 
			WHERE 
				" . $this->options['structure']["right"] . " > " . (int) $id->$options_structure_right . " AND 
				" . $this->options['structure']["id"] . " NOT IN(" . implode(',', $tmp) . ") 
		";

        foreach ($sql as $k => $v) {
            //echo preg_replace('@[\s\t]+@',' ',$v) ."\n";
            try {
                $DB->execute($v);
            } catch (Exception $e) {               
                throw new Exception('Error moving');
            }
        }
        return true;
    }

    public function cp($id, $parent, $position = 0) {
        global $CFG, $DB, $PAGE;

        $id = (int) $id;
        $parent = (int) $parent;
        if ($parent == 0 || $id == 0 || $id == 1) {
            throw new Exception('Could not copy inside parent 0, or copy root nodes');
        }


        $parent = $this->get_node($parent, array('with_children' => true, 'with_path' => true));
        $id = $this->get_node($id, array('with_children' => true, 'deep_children' => true, 'with_path' => true));       

        $osleft = $this->osleft;
        $osright = $this->osright;
        $osparentid = $this->osparentid;
        $osid = $this->osid;
        $ospos = $this->ospos;
        $oslvl = $this->oslevel;


        $old_nodes = $DB->get_records_sql("
			SELECT * FROM " . $DB->get_prefix() . $this->options['structure_table'] . " 
			WHERE " . $osleft . " > " . $id->$osleft . " AND " . $osright . " < " . $id->$osright . " 
			ORDER BY " . $osleft . "
		");

        if (!$parent->children) {
            $position = 0;
        }
        if ($id->$osparentid == $parent->$osid && $position > $id->$ospos) {
            //$position ++;
        }
        if ($parent->children && $position >= count($parent->children)) {
            $position = count($parent->children);
        }

        $tmp = array();
        $tmp[] = (int) $id->$osid;
        if ($id->children && is_array($id->children)) {
            foreach ($id->children as $c) {
                $tmp[] = (int) $c[$osid];
            }
        }
        $width = (int) $id->$osright - (int) $id->$osleft + 1;

        $sql = array();

        // PREPARE NEW PARENT
        // update positions of all next elements
        $sql[] = "
			UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . "
				SET " . $ospos . " = " . $ospos . " + 1 
			WHERE 
				" . $osparentid . " = " . (int) $parent->$osid . " AND 
				" . $ospos . " >= " . $position . "
			";

        // update left indexes
        $ref_lft = false;
        $parent_c = $parent->children;
        if (!$parent->children) {
            $ref_lft = $parent->$osright;
        } else if (!isset($parent_c->$position)) {
            $ref_lft = $parent->$osright;
        } else {
            $par_pos = $parent_c->$position;
            $ref_lft = $par_pos->$osleft;           
        }
        $sql[] = "
			UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . "
				SET " . $osleft . " = " . $osleft . " + " . $width . " 
			WHERE 
				" . $osleft . " >= " . (int) $ref_lft . " 
			";
        // update right indexes
        $ref_rgt = false;
        if (!$parent->children) {
            $ref_rgt = $parent->$osright;
        } else if (!isset($parent_c->$position)) {
            $ref_rgt = $parent->$osright;
        } else {
            $par_pos = $parent_c->$position;
            $ref_rgt = $par_pos->$osleft + 1;
           
        }
        $sql[] = "
			UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . " 
				SET " . $osright . " = " . $osright . " + " . $width . " 
			WHERE 
				" . $osright . " >= " . (int) $ref_rgt . " 
			";

        // MOVE THE ELEMENT AND CHILDREN
        // left, right and level
        $diff = $ref_lft - $id->$osleft;

        if ($diff <= 0) {
            $diff = $diff - $width;
        }
        $ldiff = ($parent->$oslvl + 1) - $id->$oslvl;

        // build all fields + data table
        $fields = array_combine($this->options['structure'], $this->options['structure']);
        unset($fields['id']);
        $fields[$osleft] = $osleft . " + " . $diff;
        $fields[$osright] = $osright . " + " . $diff;
        $fields[$oslvl] = $oslvl . " + " . $ldiff;     

        $record_toinsert = $DB->get_record_sql("SELECT " . implode(',', array_values($fields)) . " FROM " . $DB->get_prefix() . $this->options['structure_table'] . " WHERE " . $this->options['structure']["id"] . " IN (" . implode(",", $tmp) . ") 
			ORDER BY " . $oslvl . " ASC");

        $insert_temp = new stdClass();
        $insert_temp->$osid = null;
        $insert_temp->$osright = $record_toinsert->$fields[$osright];
        $insert_temp->$osleft = $record_toinsert->$fields[$osleft];
        $insert_temp->$oslvl = $record_toinsert->$fields[$oslvl];
        $insert_temp->$osparentid = $record_toinsert->$fields[$osparentid];
        $insert_temp->$ospos = $record_toinsert->$fields[$ospos];

        $iid = $DB->insert_record($this->options['structure_table'], $insert_temp);  


        foreach ($sql as $k => $v) {
            try {
                $DB->execute($v);
            } catch (Exception $e) {
               
                throw new Exception('Error copying');
            }
        }


        try {
            $DB->execute("
				UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . " 
					SET " . $this->options['structure']["position"] . " = " . $position . ",
						" . $this->options['structure']["parent_id"] . " = " . $parent->$osid . " 
					WHERE " . $this->options['structure']["id"] . "  = " . $iid . " 
			");
        } catch (Exception $e) {

            $this->rm($iid);           
            throw new Exception('Could not update adjacency after copy');
        }

        $fields = $this->options['data'];
        unset($fields['id']);
        $update_fields = array();
        foreach ($fields as $f) {
            $update_fields[] = $f . '=VALUES(' . $f . ')';
        }
        $update_fields = implode(',', $update_fields);

        if (count($fields)) {
            try {
                $data2structure = $this->options['data2structure'];

                $DB->execute("
						INSERT INTO " . $DB->get_prefix() . $this->options['data_table'] . " (" . $this->options['data2structure'] . "," . implode(",", $fields) . ") 
						SELECT " . $iid . "," . implode(",", $fields) . " FROM " . $this->options['data_table'] . " WHERE " . $this->options['data2structure'] . " = " . $id->$data2structure . " 
						ON DUPLICATE KEY UPDATE " . $update_fields . " 
				");
            } catch (Exception $e) {
                $this->rm($iid);             
                throw new Exception('Could not update data after copy');
            }
        }

        $new_nodes = $DB->get_records_sql("
			SELECT * FROM " . $DB->get_prefix() . $this->options['structure_table'] . " 
			WHERE " . $this->options['structure']["left"] . " > " . $ref_lft . " AND " . $this->options['structure']["right"] . " < " . ($ref_lft + $width - 1) . " AND " . $this->options['structure']["id"] . " != " . $iid . "
			ORDER BY " . $this->options['structure']["left"] . "
		");


        $parents = array();
        foreach ($new_nodes as $node) {
            $nodeleft = $node->$osleft;

            if (!isset($parents->$nodeleft)) {
                $parents->$nodeleft = $iid;
            }
            for ($i = $node->$osleft + 1; $i < $node->$osright; $i++) {
                $parents->$i = $node->$osid;
            }
        }
        $sql = array();


        foreach ($new_nodes as $k => $node) {
            $nodeleft = $node->$osleft;
            $sql[] = "
				UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . " 
				SET " . $this->options['structure']["parent_id"] . " = " . $parents->$nodeleft . " 
				WHERE " . $this->options['structure']["id"] . " = " . $node->$osid . "
			";
            if (count($fields)) {
                $up = "";
                foreach ($fields as $f)
                    $keyid = $k->$osid;
                $sql[] = "
					INSERT INTO " . $DB->get_prefix() . $this->options['data_table'] . " (" . $this->options['data2structure'] . "," . implode(",", $fields) . ") 
					SELECT " . $node->$osid . "," . implode(",", $fields) . " FROM " . $DB->get_prefix() . $this->options['data_table'] . " 
						WHERE " . $this->options['data2structure'] . " = " . $old_nodes->$keyid . " 
					ON DUPLICATE KEY UPDATE " . $update_fields . " 
				";
            }
        }

        //var_dump($sql);
        foreach ($sql as $k => $v) {
            try {
                $DB->execute($v);
            } catch (Exception $e) {
                $this->rm($iid);                
                throw new Exception('Error copying');
            }
        }
        return $iid;
    }

    public function rm($id) {
        global $DB, $CFG, $OUTPUT, $PAGE;
        $id = (int) $id;
        if (!$id || $id === 1) {
            throw new Exception('Could not create inside roots');
        }
        $data = $this->get_node($id, array('with_children' => true, 'deep_children' => true));
        $osleft = $this->osleft;
        $osright = $this->osright;
        $osparentid = $this->osparentid;
        $ospos = $this->ospos;
        $lft = $data->$osleft;
        $rgt = $data->$osright;
        $pid = $data->$osparentid;
        $pos = $data->$ospos;
        $dif = $rgt - $lft + 1;
	
	if($id){
	    $children_exists=$DB->get_records('tree_struct',array('pid'=>$id));
	    if($children_exists)
	   throw new Exception('could not remove');
	    
	}

        $sql = array();
        // deleting node and its children from structure
        $sql[] = "
			DELETE FROM " . $DB->get_prefix() . $this->options['structure_table'] . " 
			WHERE " . $this->options['structure']["left"] . " >= " . $lft . " AND " . $this->options['structure']["right"] . " <= " . $rgt . " AND " . $this->options['structure']["id"] . " = $data->id";
        //";
        // shift left indexes of nodes right of the node
        $sql[] = "
			UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . " 
				SET " . $this->options['structure']["left"] . " = " . $this->options['structure']["left"] . " - " . $dif . " 
			WHERE " . $this->options['structure']["left"] . " > " . $rgt . "
		";
        // shift right indexes of nodes right of the node and the node's parents
        $sql[] = "
			UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . " 
				SET " . $this->options['structure']["right"] . " = " . $this->options['structure']["right"] . " - " . $dif . " 
			WHERE " . $this->options['structure']["right"] . " > " . $lft . " 
		";
        // Update position of siblings below the deleted node
        $sql[] = "
			UPDATE " . $DB->get_prefix() . $this->options['structure_table'] . " 
				SET " . $this->options['structure']["position"] . " = " . $this->options['structure']["position"] . " - 1 
			WHERE " . $this->options['structure']["parent_id"] . " = " . $pid . " AND " . $this->options['structure']["position"] . " > " . $pos . " 
		";
        // delete from data table
        if ($this->options['data_table']) {
            $tmp = array();
            $tmp[] = (int) $data->id;
            if ($data->children && is_array($data->children)) {
                foreach ($data->children as $v) {
                    $tmp[] = $v->id;
                }
            }
            $sql[] = "DELETE FROM " . $DB->get_prefix() . $this->options['data_table'] . " WHERE " . $this->options['data2structure'] . " IN (" . implode(',', $tmp) . ")";
        }
   
        foreach ($sql as $v) {
            try {
                $DB->execute($v);
            } catch (Exception $e) {
                //$this->reconstruct();
                throw new Exception('Could not remove');
            }
        }
        return true;
    }

    public function rn($id, $data) {
        global $DB, $CFG, $PAGE;
      
        $checking_existingnode = $DB->get_record_sql('SELECT 1 AS res FROM ' . $DB->get_prefix() . $this->options['structure_table'] . ' WHERE ' . $this->options['structure']['id'] . ' = ' . (int) $id);
        if (!$checking_existingnode->res) {
            throw new Exception('Could not rename non-existing node');
        }

        $tmp = array();
        foreach ($this->options['data'] as $v) {
            if (isset($data[$v])) {
                $tmp[$v] = $data[$v];
            }
        }
        if (count($tmp)) {
            $tmp[$this->options['data2structure']] = $id;
            $sql = "
				INSERT INTO 
					" . $DB->get_prefix() . $this->options['data_table'] . " (" . implode(',', array_keys($tmp)) . ") 
					VALUES(?" . str_repeat(',?', count($tmp) - 1) . ") 
				ON DUPLICATE KEY UPDATE 
					" . implode(' = ?, ', array_keys($tmp)) . " = ?";
            $par = array_merge(array_values($tmp), array_values($tmp));
            try {
                $DB->execute($sql, $par);
            } catch (Exception $e) {
                throw new Exception('Could not rename');
            }
        }
        return true;
    }

    public function analyze($get_errors = false) {
        
        global $DB, $CFG, $PAGE;
        $report = array();
        //if((int)

        $morethan_onerootnode = $DB->get_record_sql("SELECT COUNT(" . $this->options['structure']["id"] . ") AS res FROM " . $DB->get_prefix() . $this->options['structure_table'] . " WHERE " . $this->options['structure']["parent_id"] . " = 0");

        if ($morethan_onerootnode->res !== 1) {
            $report[] = "No or more than one root node.";
        }
        //if((int)
        $rootnode_leftindex = $DB->get_record_sql("SELECT " . $this->options['structure']["left"] . " AS res FROM " . $DB->get_prefix() . $this->options['structure_table'] . " WHERE " . $this->options['structure']["parent_id"] . " = 0");

        if ($rootnode_leftindex->res !== 1) {
            $report[] = "Root node's left index is not 1.";
        }
        $checking_missingparent = $DB->get_record_sql("
			SELECT 
				COUNT(" . $this->options['structure']['id'] . ") AS res 
			FROM " . $DB->get_prefix() . $this->options['structure_table'] . " s 
			WHERE 
				" . $this->options['structure']["parent_id"] . " != 0 AND 
				(SELECT COUNT(" . $this->options['structure']['id'] . ") FROM " . $DB->get_prefix() . $this->options['structure_table'] . " WHERE " . $this->options['structure']["id"] . " = s." . $this->options['structure']["parent_id"] . ") = 0");

        if ($checking_missingparent->res > 0) {
            $report[] = "Missing parents.";
        }

        $rightindex = $DB->get_record_sql("SELECT MAX(" . $this->options['structure']["right"] . ") AS res FROM " . $DB->get_prefix() . $this->options['structure_table']);
        $nodecount = $DB->get_record_sql("SELECT COUNT(" . $this->options['structure']["id"] . ") AS res FROM " . $DB->get_prefix() . $this->options['structure_table']);

        if ($rightindex->res / 2 != $nodecount->res) {
            $report[] = "Right index does not match node count.";
        }

        $dup_rightindex = $DB->get_record_sql("SELECT COUNT(DISTINCT " . $this->options['structure']["right"] . ") AS res FROM " . $DB->get_prefix() . $this->options['structure_table']);
        $dup_leftindex = $DB->get_record_sql("SELECT COUNT(DISTINCT " . $this->options['structure']["left"] . ") AS res FROM " . $DB->get_prefix() . $this->options['structure_table']);
        if ($dup_rightindex->res != $dup_leftindex->res) {
            $report[] = "Duplicates in nested set.";
        }

        $un_node = $DB->get_record_sql("SELECT COUNT(DISTINCT " . $this->options['structure']["id"] . ") AS res FROM " . $DB->get_prefix() . $this->options['structure_table']);
        $un_left = $DB->get_record_sql("SELECT COUNT(DISTINCT " . $this->options['structure']["left"] . ") AS res FROM " . $DB->get_prefix() . $this->options['structure_table']);
        if ($un_node->res != $un_left->res) {
            $report[] = "Left indexes not unique.";
        }


        $un_node1 = $DB->get_record_sql("SELECT COUNT(DISTINCT " . $this->options['structure']["id"] . ") AS res FROM " . $DB->get_prefix() . $this->options['structure_table']);
        $rt_index = $DB->get_record_sql("SELECT COUNT(DISTINCT " . $this->options['structure']["right"] . ") AS res FROM " . $DB->get_prefix() . $this->options['structure_table']);
        if ($un_node1->res != $rt_index->res) {
            $report[] = "Right indexes not unique.";
        }


        $checking_leftrightindex = $DB->get_record_sql("
				SELECT 
					s1." . $this->options['structure']["id"] . " AS res 
				FROM " . $DB->get_prefix() . $this->options['structure_table'] . " s1, " . $DB->get_prefix() . $this->options['structure_table'] . " s2 
				WHERE 
					s1." . $this->options['structure']['id'] . " != s2." . $this->options['structure']['id'] . " AND 
					s1." . $this->options['structure']['left'] . " = s2." . $this->options['structure']['right'] . " 
				LIMIT 1");
        if ($checking_leftrightindex->res) {
            $report[] = "Nested set - matching left and right indexes.";
        }


        $checking_positions1 = $DB->get_record_sql("
				SELECT 
					" . $this->options['structure']["id"] . " AS res 
				FROM " . $DB->get_prefix() . $this->options['structure_table'] . " s 
				WHERE 
					" . $this->options['structure']['position'] . " >= (
						SELECT 
							COUNT(" . $this->options['structure']["id"] . ") 
						FROM " . $DB->get_prefix() . $this->options['structure_table'] . " 
						WHERE " . $this->options['structure']['parent_id'] . " = s." . $this->options['structure']['parent_id'] . "
					)
				LIMIT 1");


        $positon2 = $DB->get_record_sql("
				SELECT 
					s1." . $this->options['structure']["id"] . " AS res 
				FROM " . $DB->get_prefix() . $this->options['structure_table'] . " s1, " . $DB->get_prefix() . $this->options['structure_table'] . " s2 
				WHERE 
					s1." . $this->options['structure']['id'] . " != s2." . $this->options['structure']['id'] . " AND 
					s1." . $this->options['structure']['parent_id'] . " = s2." . $this->options['structure']['parent_id'] . " AND 
					s1." . $this->options['structure']['position'] . " = s2." . $this->options['structure']['position'] . " 
				LIMIT 1");
      
        if (isset($checking_positions1->res) || isset($positon2->res)) {
            $report[] = "Positions not correct.";
        }


        $checking_Adjacency = $DB->get_record_sql("
			SELECT 
				COUNT(" . $this->options['structure']["id"] . ") as res FROM " . $DB->get_prefix() . $this->options['structure_table'] . " s 
			WHERE 
				(
					SELECT 
						COUNT(" . $this->options['structure']["id"] . ") 
					FROM " . $DB->get_prefix() . $this->options['structure_table'] . " 
					WHERE 
						" . $this->options['structure']["right"] . " < s." . $this->options['structure']["right"] . " AND 
						" . $this->options['structure']["left"] . " > s." . $this->options['structure']["left"] . " AND 
						" . $this->options['structure']["level"] . " = s." . $this->options['structure']["level"] . " + 1
				) != 
				(
					SELECT 
						COUNT(*) 
					FROM " . $DB->get_prefix() . $this->options['structure_table'] . "
					WHERE 
						" . $this->options['structure']["parent_id"] . " = s." . $this->options['structure']["id"] . "
				)");

        if ($checking_Adjacency->res) {
            $report[] = "Adjacency and nested set do not match.";
        }



        $checking_missingrecord = $DB->get_record_sql("
				SELECT 
					COUNT(" . $this->options['structure']["id"] . ") AS res 
				FROM " . $DB->get_prefix() . $this->options['structure_table'] . " s 
				WHERE 
					(SELECT COUNT(" . $this->options['data2structure'] . ") FROM " . $DB->get_prefix() . $this->options['data_table'] . " WHERE " . $this->options['data2structure'] . " = s." . $this->options['structure']["id"] . ") = 0
			");

        if ($this->options['data_table'] && $checking_missingrecord->res) {
            $report[] = "Missing records in data table.";
        }


        $checking_danglingrecord = $DB->get_record_sql("
				SELECT 
					COUNT(" . $this->options['data2structure'] . ") AS res 
				FROM " . $DB->get_prefix() . $this->options['data_table'] . " s 
				WHERE 
					(SELECT COUNT(" . $this->options['structure']["id"] . ") FROM " . $DB->get_prefix() . $this->options['structure_table'] . " WHERE " . $this->options['structure']["id"] . " = s." . $this->options['data2structure'] . ") = 0
			");
        if ($this->options['data_table'] && $checking_danglingrecord->res) {
            $report[] = "Dangling records in data table.";
        }
        return $get_errors ? $report : count($report) == 0;
    }



    public function res($data = array()) {
        global $CFG, $DB;
        if (!$DB->execute("TRUNCATE TABLE " . $this->options['structure_table'])) {
            return false;
        }
        if (!$DB->execute("TRUNCATE TABLE " . $this->options['data_table'])) {
            return false;
        }

        //$sql = "INSERT INTO {".$this->options['structure_table']."} (".implode(",", $this->options['structure']).") VALUES (?".str_repeat(',?', count($this->options['structure']) - 1).")";
        $par = array();
        $insert_temp = new Stdclass();
        foreach ($this->options['structure'] as $k => $v) {
            switch ($k) {
                case 'id':
                    $par[] = null;
                    $insert_temp->id = null;
                    break;
                case 'left':
                    $par[] = 1;
                    $insert_temp->lft = 1;
                    break;
                case 'right':
                    $par[] = 2;
                    $insert_temp->rgt = 2;
                    break;
                case 'level':
                    $par[] = 0;
                    $insert_temp->lvl = 0;
                    break;
                case 'parent_id':
                    $par[] = 0;
                    $insert_temp->pid = 0;
                    break;
                case 'position':
                    $par[] = 0;
                    $insert_temp->pos = 0;
                    break;
                default:
                    $par[] = null;
            }
        }

        if (!$this->db->query($sql, $par)) {
            return false;
        }
        $id = $this->db->insert_id();
        foreach ($this->options['structure'] as $k => $v) {
            if (!isset($data[$k])) {
                $data[$k] = null;
            }
        }
        return $this->rn($id, $data);
    }

    public function dump() {
        global $DB, $CFG;
        $nodes = $DB->get_records_sql("
			SELECT 
				s." . implode(", s.", $this->options['structure']) . ", 
				d." . implode(", d.", $this->options['data']) . " 
			FROM 
				{" . $this->options['structure_table'] . "} s, 
				{" . $this->options['data_table'] . "} d 
			WHERE 
				s." . $this->options['structure']['id'] . " = d." . $this->options['data2structure'] . " 
			ORDER BY " . $this->options['structure']["left"]
        );
        echo "\n\n";
        foreach ($nodes as $node) {
            echo str_repeat(" ", (int) $node[$this->options['structure']["level"]] * 2);
            echo $node[$this->options['structure']["id"]] . " " . $node["nm"] . " (" . $node[$this->options['structure']["left"]] . "," . $node[$this->options['structure']["right"]] . "," . $node[$this->options['structure']["level"]] . "," . $node[$this->options['structure']["parent_id"]] . "," . $node[$this->options['structure']["position"]] . ")" . "\n";
        }
        echo str_repeat("-", 40);
        echo "\n\n";
    }

}
