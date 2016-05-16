<?php

//defined('MOODLE_INTERNAL') || die();
require_once('../../config.php');

// TO DO: better exceptions, use params
class block_interactivetree_manage {

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
    protected $ospid;
    protected $osid;
    protected $ospos;
    protected $osleft;
    protected $osright;
    protected $oslevel;
    protected $structuretable;
    protected $datatable;
    protected $data2structure;

    public function __construct(array $options = array()) {
        $this->options = array_merge($this->default, $options);
      //  pid = $this->options['structure']['parent_id'];
      //  id = $this->options['structure']['id'];
        $this->ospos =  $this->options['structure']['position'];
        $this->osleft = $this->options['structure']['left'];
        $this->osright = $this->options['structure']['right'];
       // lvl = $this->options['structure']['level'];
        $this->structuretable = $this->options['structure_table'];
        $this->datatable = $this->options['data_table'];
        $this->data2structure = $this->options['data2structure'];
    }

    
    
    function get_node($id, $options = array()) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE;		
        $sql = " SELECT s.id, s.lft, s.rgt, s.lvl, s.pid, s.pos , d.nm 
			FROM {block_interactivetree_struct} as  s, {block_interactivetree_data} as d 
			WHERE s.id = d.id AND s.id = ? ";
        $node = $DB->get_record_sql($sql, array($id));	
        
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
        global $CFG, $USER, $DB, $OUTPUT, $PAGE;
        $sql = false;

        if ($recursive) {
            $node = $this->get_node($id);   

            $sql = "SELECT  s.id, s.lft, s.rgt, s.lvl, s.pid, s.pos , d.nm			
                       FROM  {block_interactivetree_struct} s,{block_interactivetree_data} d 
				WHERE s.id = d.id AND s.lft > :osleft AND s.rgt < :osright
				ORDER BY s.lft";
            $response = $DB->get_records_sql($sql, array('osleft' => $node->lft, 'osright' => $node->rgt));
        } else {
            $sql = " SELECT  s.id, s.lft, s.rgt, s.lvl, s.pid, s.pos , d.nm
		     FROM  {block_interactivetree_struct} s,{block_interactivetree_data} d 
		     WHERE s.id = d.id AND s.pid = :parentid ORDER BY  s.pos ";
            $response = $DB->get_records_sql($sql, array('parentid' => $id));
        } 
        
        return $response;
    }

    public function get_path($id) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE;
        $node = $this->get_node($id);
        $sql = false;
        if ($node) {
            $sql = "SELECT  s.id, s.lft, s.rgt, s.lvl, s.pid, s.pos , d.nm
		    FROM  {block_interactivetree_struct} s,{block_interactivetree_data} d 
		    WHERE s.id = d.id AND s.lft < :osleft  AND 
		    s.rgt > :osright  ORDER BY  s.lft ";
        }
        return $sql ? $DB->get_records_sql($sql, array('osleft' => $node->lft, 'osright' => $node->rgt)) : false;
    }
    
    
   public function mk($parent, $position = 0, $data = array()) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE;
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

        // PREPARE NEW PARENT 
        // update positions of all next elements
        $sql[] = "UPDATE {block_interactivetree_struct}
		  SET pos = pos + 1
		  WHERE pid  = :parentstructureid  AND 
		  pos  >=:position";
		  
        $params[]=array('parentstructureid'=> $parent->id ,'position'=> $position );
        
        // update left indexes
        $ref_lft = false;
        if (!$parent->children) {
            $ref_lft = $parent->rgt;
        } else if (!isset($parent->children[$position])) {
            $ref_lft = $parent->rgt;
        } else {
            $position = (int) $position;
            $parentchild = $parent->children;
            $parentpos = $parentchild->$position;
            $parentpos_left = $parentpos->lft;
            //$ref_lft = $parent['children'][(int)$position][$this->options['structure']["left"]];
            $ref_lft = $parentpos_left;
        }
        $sql[] = "UPDATE {block_interactivetree_struct} 
		    SET lft = lft + 2
		    WHERE lft  >= :ref ";
        $params[]=array('ref'=> $ref_lft );
        

        // update right indexes
        $ref_rgt = false;
        if (!$parent->children) {
            $ref_rgt = $parent->rgt;
        } else if (!isset($parent->children->$position)) {
            $ref_rgt = $parent->rgt;
        } else {
            $position = (int) $position;
            $parentchild = $parent->children;
            $parentpos = $parentchild->$position;
            $parentpos_left = $parentpos->lft;

            //$ref_rgt = $parent['children'][(int)$position][$this->options['structure']["left"]] + 1;
            $ref_rgt = $parentpos_left + 1;
        }
        $sql[] = "UPDATE {block_interactivetree_struct} 
		    SET  rgt = rgt + 2
		    WHERE rgt >= :refright";
        $params[]= array('refright'=>$ref_rgt );
        
        //$tmp = array();
        $insert_temp = new Stdclass();
		$insert_temp->id = null;
		$insert_temp->lft = (int) $ref_lft;
		$insert_temp->rgt = (int) $ref_lft + 1;
		$insert_temp->lvl = (int) $parent->pid + 1;
		$insert_temp->pid = $parent->id;
		$insert_temp->pos = $position;		
		
        $node = $DB->insert_record('block_interactivetree_struct', $insert_temp);
        foreach ($sql as $key => $values) {                      
           try {
             $DB->execute($values,$params[$key]);       
	       }
            catch (Exception $e) {	    
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
        global $CFG, $USER, $DB, $OUTPUT, $PAGE;
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
        if ($id->pid == $parent->id && $position > $id->pos) {
            $position ++;
        }
        if ($parent->children && $position >= count($parent->children)) {
            $position = count($parent->children);
        }
        if ($id->lft < $parent->lft && $id->rgt > $parent->rgt) {
            throw new Exception('Could not move parent inside child');
        }

        $tmp = array();
        $tmp[] = (int) $id->id;
        if ($id->children && is_array($id->children)) {
            foreach ($id->children as $c) {
                $tmp[] = (int) $c->id;
            }
        }
        $width = (int) $id->rgt - (int) $id->lft + 1;
        $sql = array();

        // PREPARE NEW PARENT
        // update positions of all next elements
        $sql[] = "UPDATE {block_interactivetree_struct}
				SET pos = pos + 1
			WHERE id != :osid AND pid = :osparentid AND pos  >= :ospos  ";
       $params[]=array('osid'=>(int) $id->id,'osparentid'=>(int) $parent->id,'ospos'=>$position); 

        // update left indexes
        $ref_lft = false;
        $parent_c = $parent->children;
        if (!$parent->children) {
            $ref_lft = $parent->rgt;
        } else if (!isset($parent_c->$position)) {
            $ref_lft = $parent->rgt;
        } else {
            $parent_pos = $parent_c->$position;
            $ref_lft = $parent_pos->lft;
        }
        $sql[] = " UPDATE {block_interactivetree_struct}
				SET lft = :osleftwidth 
			WHERE lft >= :reflft AND 
				id NOT IN(" . implode(',', $tmp) . ") 
			";
        $params[]=array('osleftwidth'=>lft + $width ,'reflft'=>(int) $ref_lft );
        // update right indexes
        $ref_rgt = false;
        if (!$parent->children) {
            $ref_rgt = $parent->rgt;
        } else if (!isset($parent_c->$position)) {
            $ref_rgt = $parent->rgt;
        } else {
            $parent_pos = $parent_c->$position;
            $ref_rgt = $parent_pos->lft + 1;
        }
        $sql[] = "UPDATE {block_interactivetree_struct} 
		    SET rgt  = :osrightwidth 
		    WHERE 
	            rgt >=  :ref_rgt AND 
	            id NOT IN(" . implode(',', $tmp) . ") ";
        $params[]= array('osrightwidth'=>rgt + $width ,'ref_rgt'=> (int) $ref_rgt);

        // MOVE THE ELEMENT AND CHILDREN
        // left, right and level
        $diff = $ref_lft - (int) $id->lft;

        if ($diff > 0) {
            $diff = $diff - $width;
        }
        $ldiff = ((int) $parent->lvl + 1) - (int) $id->lvl;
        $sql[] = " UPDATE {block_interactivetree_struct}
		   SET rgt = :osrightdiff , lft = :osleftdiff , 
	            lvl = :osleveldiff 
		    WHERE  id IN (" . implode(',', $tmp) . ") ";
        $params =array('osrightdiff'=> rgt + $diff,'osleftdiff'=>  lft + $diff,'osleveldiff'=> lvl + $ldiff );
        
        // position and parent_id
        $sql[] = " UPDATE {block_interactivetree_struct}
		  SET pos = :ospos , pid = :osparentid 
		  WHERE id = :osid  ";
        $params[]= array('ospos'=> $position, 'osparentid'=>(int) $parent->id , 'osid'=>(int) $id->id );

        // CLEAN OLD PARENT
        // position of all next elements
        $sql[] = " UPDATE {block_interactivetree_struct}
		   SET pos = pos - 1
		   WHERE pid = :osparentid AND pos > :ospos  ";
       $params[]=array('osparentid'=> (int) $id->pid ,'ospos'=> (int) $id->$options_structure_pos);         
        
        // left indexes
        $sql[] = "UPDATE {block_interactivetree_struct}
		  SET lft = :osleftwidth 
                  WHERE lft > :osleft  AND 
		  id NOT IN(" . implode(',', $tmp) . ")";
        $params[]= array('osleftwidth'=>lft - $width ,'osleft'=>(int) $id->rgt);        
        
        // right indexes
        $sql[] = "
			UPDATE {block_interactivetree_struct}
			SET rgt = :osrightwidth 
			WHERE rgt > :osright  AND 
			id NOT IN(" . implode(',', $tmp) . ") ";
        $params[]= array('osrightwidth'=>rgt - $width ,'osright'=> (int) $id->rgt );

        foreach ($sql as $key => $value) {
            //echo preg_replace('@[\s\t]+@',' ',$v) ."\n";
            try {
                $DB->execute($value, $params[$key]);
            } catch (Exception $e) {
                throw new Exception('Error moving');
            }
        }
        return true;
    }

    public function cp($id, $parent, $position = 0) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE;

        $id = (int) $id;
        $parent = (int) $parent;
        if ($parent == 0 || $id == 0 || $id == 1) {
            throw new Exception('Could not copy inside parent 0, or copy root nodes');
        }
        $parent = $this->get_node($parent, array('with_children' => true, 'with_path' => true));
        $id = $this->get_node($id, array('with_children' => true, 'deep_children' => true, 'with_path' => true));     

        $old_nodes = $DB->get_records_sql("SELECT * FROM {block_interactivetree_struct }
			WHERE lft  > :osleft  AND rgt < :osright  
			ORDER BY  lft ",array('osleft'=>$id->lft ,'osright'=>$id->rgt ));

        if (!$parent->children) {
            $position = 0;
        }
        if ($id->pid == $parent->id && $position > $id->pos) {
            //$position ++;
        }
        if ($parent->children && $position >= count($parent->children)) {
            $position = count($parent->children);
        }

        $tmp = array();
        $tmp[] = (int) $id->id;
        if ($id->children && is_array($id->children)) {
            foreach ($id->children as $c) {
                $tmp[] = (int) $c[id];
            }
        }
        $width = (int) $id->rgt - (int) $id->lft + 1;

        $sql = array(); $params=array();

        // PREPARE NEW PARENT
        // update positions of all next elements
        $sql[] = "  UPDATE {block_interactivetree_struct }
		    SET pos = pos + 1
		    WHERE pid  = :osparentid  AND 
		     pos  >= :ospos ";
        
        $params[]= array('osparentid'=>(int) $parent->id ,'ospos'=> $position);

        // update left indexes
        $ref_lft = false;
        $parent_c = $parent->children;
        if (!$parent->children) {
            $ref_lft = $parent->rgt;
        } else if (!isset($parent_c->$position)) {
            $ref_lft = $parent->rgt;
        } else {
            $par_pos = $parent_c->$position;
            $ref_lft = $par_pos->lft;
        }
        $sql[] = " UPDATE {block_interactivetree_struct }
		   SET lft  =  :osleftwidth 
		    WHERE lft  >=  :osleft  ";
        $params[]= array('osleftwidth'=>lft  +  $width  ,'osleft'=>(int) $ref_lft);
        
        // update right indexes
        $ref_rgt = false;
        if (!$parent->children) {
            $ref_rgt = $parent->rgt;
        } else if (!isset($parent_c->$position)) {
            $ref_rgt = $parent->rgt;
        } else {
            $par_pos = $parent_c->$position;
            $ref_rgt = $par_pos->lft + 1;
        }
        $sql[] = "  UPDATE {block_interactivetree_struct }
		    SET rgt  =  :osrightwidth
		    WHERE rgt >= :osright ";
        $params[]= array('osrightwidth'=>  rgt  +  $width ,'osright'=>(int) $ref_rgt);

        // MOVE THE ELEMENT AND CHILDREN
        // left, right and level
        $diff = $ref_lft - $id->lft;

        if ($diff <= 0) {
            $diff = $diff - $width;
        }
        $ldiff = ($parent->lvl + 1) - $id->lvl;

        // build all fields + data table
        //$fields = array_combine($this->options['structure'], $this->options['structure']);
        //unset($fields['id']);
        //$fields[lft] = lft + $diff;
        //$fields[rgt] = rgt + $diff;
        //$fields[lvl] = lvl + $ldiff;
		$fields=array('rgt'=>'rgt'+$diff,'lft'=>'lft'+$diff,'lvl'=>'lvl'+$ldiff);

        $record_toinsert = $DB->get_record_sql("SELECT  implode(',', array_values($fields)) FROM {block_interactivetree_struct }WHEREid IN (" . implode(",", $tmp) . ") 
			ORDER BY  lvl ASC ");

        $insert_temp = new stdClass();
        $insert_temp->id = null;
        $insert_temp->rgt = $record_toinsert->$fields['rgt'];
        $insert_temp->lft = $record_toinsert->$fields['lft'];
        $insert_temp->lvl = $record_toinsert->$fields['lvl'];
        $insert_temp->pid = $record_toinsert->pid;
        $insert_temp->pos = $record_toinsert->pos;

        $iid = $DB->insert_record('block_interactivetree_struct', $insert_temp);


        foreach ($sql as $key => $value) {
            try {
                $DB->execute($value, $params[$key]);
            } catch (Exception $e) {
                throw new Exception('Error copying');
            }
        }


        try {
            $DB->execute("  UPDATE {block_interactivetree_struct }
			    SET pos = :ospos  ,
			    pid = :osparentid 
			    WHERE id  = :osid ",array('ospos'=>$position,'osparentid'=>$parent->id ,'osid'=>$iid));
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
              //  $data2structure = $this->data2structure;

                $DB->execute("INSERT INTO {block_interactivetree_data} ( id ," . implode(",", $fields) . ") 
				SELECT $iid ," . implode(",", $fields) . " FROM {block_interactivetree_data} WHERE id = $id->id 
				ON DUPLICATE KEY UPDATE  $update_fields ");
            } catch (Exception $e) {
                $this->rm($iid);
                throw new Exception('Could not update data after copy');
            }
        }

        $new_nodes = $DB->get_records_sql("SELECT * FROM {block_interactivetree_struct }
			WHERE lft > :osleft AND  rgt < :osright AND id != :osid  
			ORDER BY lft", array('osleft'=> $ref_lft,'osright'=> ($ref_lft + $width - 1),'osid'=> $iid ));


        $parents = array();
        foreach ($new_nodes as $node) {
            $nodeleft = $node->lft;

            if (!isset($parents->$nodeleft)) {
                $parents->$nodeleft = $iid;
            }
            for ($i = $node->lft + 1; $i < $node->rgt; $i++) {
                $parents->$i = $node->id;
            }
        }
        $sql = array();
	$params=array();


        foreach ($new_nodes as $k => $node) {
            $nodeleft = $node->lft;
            $sql[] = " UPDATE {block_interactivetree_struct }
		       SET pid = :osparentid 
			WHERE id = :osid ";
	     $params[]=array('osparentid'=>$parents->$nodeleft,'osid'=>$node->id);		
            if (count($fields)) {
                $up = "";
                foreach ($fields as $f)
                    $keyid = $k->id;
                $sql[] = "INSERT INTO {block_interactivetree_data} (id," . implode(",", $fields) . ") 
					SELECT " . $node->id . "," . implode(",", $fields) . " FROM {block_interactivetree_data} 
						WHERE id = " . $old_nodes->$keyid . " 
					ON DUPLICATE KEY UPDATE " . $update_fields . " 
				";
				
				
            }
        }

        //var_dump($sql);
        foreach ($sql as $k => $v) {
            try {
                $DB->execute($v, $params[$key]);
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
       
        $lft = $data->lft;
        $rgt = $data->rgt;
        $pid = $data->pid;
        $pos = $data->pos;
        $dif = $rgt - $lft + 1;

        if ($id) {
            $children_exists = $DB->get_records('block_interactivetree_struct', array('pid' => $id));
            if ($children_exists)
                throw new Exception('could not remove');
        }

        $sql = array();
	$params=array();
        // deleting node and its children from structure
        $sql[] = "DELETE FROM {block_interactivetree_struct}
		    WHERE lft >= :osleft  AND rgt <= :osright AND id = :osid ";
			
        $params[]= array('osleft'=>$lft,'osright'=> $rgt,'osid'=>$data->id);
	
        // shift left indexes of nodes right of the node
        $sql[] = "UPDATE {block_interactivetree_struct}
				SET lft = :setos WHERE lft > :osleft";
        $params[]= array('setos'=> lft -  $dif ,'osleft'=> $rgt);		
		
        // shift right indexes of nodes right of the node and the node's parents
        $sql[] = "UPDATE {block_interactivetree_struct }
				SET rgt = :setright WHERE rgt > :osright";
        $params[]= array('setright'=> rgt - $dif  ,'osright'=> $lft );			
		
        // Update position of siblings below the deleted node
        $sql[] = "UPDATE {block_interactivetree_struct }
				SET pos = pos - 1  WHERE pid = :osparentid  AND pos > :ospos";
	    $params[]= array('osparentid'=> $pid ,'ospos'=>$pos );		
		
        // delete from data table
       // if ($this->datatable) {
            $tmp = array();
            $tmp[] = (int) $data->id;
            if ($data->children && is_array($data->children)) {
                foreach ($data->children as $v) {
                    $tmp[] = $v->id;
                }
            }
            $sql[] = "DELETE FROM {block_interactivetree_data} WHERE id IN (" . implode(',', $tmp) . ")";
       // }

        foreach ($sql as $k=>$v) {
        try {		
             $DB->execute($v, $params[$k]);
            } catch (Exception $e) {
                //$this->reconstruct();
                throw new Exception('Could not remove');
           }
        }
        return true;
    }

    public function rn($id, $data) {
        global $DB, $CFG, $PAGE;

        $checking_existingnode = $DB->get_record_sql("SELECT 1 AS res FROM {block_interactivetree_struct} WHERE id = $id");
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
            $tmp['id'] = $id;
            $sql = "
				    INSERT INTO 
					{block_interactivetree_data} (" . implode(',', array_keys($tmp)) . ") 
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

        $morethan_onerootnode = $DB->get_record_sql("SELECT COUNT(id) AS res FROM {block_interactivetree_struct } WHERE pid = 0");
        if ($morethan_onerootnode->res !== 1) {
            $report[] = "No or more than one root node.";
        }
        //if((int)
        $rootnode_leftindex = $DB->get_record_sql("SELECT lft AS res FROM {block_interactivetree_struct} WHERE pid = 0");
        if ($rootnode_leftindex->res !== 1) {
            $report[] = "Root node's left index is not 1.";
        }
        $checking_missingparent = $DB->get_record_sql("	SELECT COUNT(id) AS res 
			FROM {block_interactivetree_struct } s 
			WHERE pid != 0 AND 
				 (SELECT COUNT(id) FROM {block_interactivetree_struct } WHERE id =s.pid) = 0");

        if ($checking_missingparent->res > 0) {
            $report[] = "Missing parents.";
        }

        $rightindex = $DB->get_record_sql("SELECT MAX(rgt) AS res FROM {block_interactivetree_struct} ");
        $nodecount = $DB->get_record_sql("SELECT COUNT(id) AS res FROM {block_interactivetree_struct} ");

        if ($rightindex->res / 2 != $nodecount->res) {
            $report[] = "Right index does not match node count.";
        }

        $dup_rightindex = $DB->get_record_sql("SELECT COUNT(DISTINCT rgt) AS res FROM {block_interactivetree_struct} ");
        $dup_leftindex = $DB->get_record_sql("SELECT COUNT(DISTINCT lft) AS res FROM {block_interactivetree_struct} ");
        if ($dup_rightindex->res != $dup_leftindex->res) {
            $report[] = "Duplicates in nested set.";
        }

        $un_node = $DB->get_record_sql("SELECT COUNT(DISTINCTid) AS res FROM {block_interactivetree_struct} ");
        $un_left = $DB->get_record_sql("SELECT COUNT(DISTINCT lft) AS res FROM {block_interactivetree_struct} ");
        if ($un_node->res != $un_left->res) {
            $report[] = "Left indexes not unique.";
        }
    
        $un_node1 = $DB->get_record_sql("SELECT COUNT(DISTINCTid) AS res FROM {block_interactivetree_struct} ");
        $rt_index = $DB->get_record_sql("SELECT COUNT(DISTINCT rgt) AS res FROM {block_interactivetree_struct} ");
        if ($un_node1->res != $rt_index->res) {
            $report[] = "Right indexes not unique.";
        }

        $checking_leftrightindex = $DB->get_record_sql("
				SELECT s1.id AS res 
				FROM {block_interactivetree_struct }s1, {block_interactivetree_struct }s2 
				WHERE s1.id != s2.id AND s1.lft = s2.rgt LIMIT 1");
        if ($checking_leftrightindex->res) {
            $report[] = "Nested set - matching left and right indexes.";
        }
	
        $checking_positions1 = $DB->get_record_sql("
				SELECT id AS res 
				FROM {block_interactivetree_struct} s 
				WHERE pos  >= ( SELECT COUNT(id) FROM {block_interactivetree_struct} WHERE  pid=s.pid)
				LIMIT 1");
		
        $positon2 = $DB->get_record_sql("
				SELECT s1.id AS res 
				FROM {block_interactivetree_struct } s1, {block_interactivetree_struct } s2 
				WHERE 
					s1.id != s2.id AND s1.pid = s2.pid  AND s1.pos = s2.pos 
				LIMIT 1");

        if (isset($checking_positions1->res) || isset($positon2->res)) {
            $report[] = "Positions not correct.";
        }
		$checking_Adjacency = $DB->get_record_sql("
			SELECT COUNT(id) as res FROM {block_interactivetree_struct} s 
			WHERE 
				(SELECT COUNT(id) FROM {block_interactivetree_struct}
					WHERE rgt < s.rgt AND lft > s.lft AND lvl = s.lvl + 1) != 
				(SELECT COUNT(*) FROM {block_interactivetree_struct} WHERE pid = s.id)");

        if ($checking_Adjacency->res) {
            $report[] = "Adjacency and nested set do not match.";
        }
        $checking_missingrecord = $DB->get_record_sql("SELECT COUNT(id) AS res 
				FROM {block_interactivetree_struct } s 
				WHERE (SELECT COUNT(id) FROM {block_interactivetree_data} WHERE id = s.id) = 0");

        if ($checking_missingrecord->res) {
            $report[] = "Missing records in data table.";
        }

        $checking_danglingrecord = $DB->get_record_sql("SELECT COUNT(id) AS res 
				FROM {block_interactivetree_data} s 
				WHERE (SELECT COUNT(id) FROM {block_interactivetree_struct } WHERE id = s.id) = 0");
        if ($checking_danglingrecord->res) {
            $report[] = "Dangling records in data table.";
        }
        return $get_errors ? $report : count($report) == 0;
    }

   
    public function dump() {
        global $DB, $CFG;        
        $nodes = $DB->get_records_sql("
			SELECT s.id, s.lft, s.rgt, s.lvl, s.pid, s.pos, d.nm 
			FROM {block_interactivetree_struct} s, 
				  {block_interactivetree_data} d 
			WHERE s. id  = d.id 
			ORDER BY lft"
        );
        echo "\n\n";
        foreach ($nodes as $node) {
            echo str_repeat(" ", (int) $node[lvl] * 2);
            echo $node['id'] . " " . $node["nm"] . " (" . $node['lft'] . "," . $node['rgt'] . "," . $node['lvl'] . "," . $node['pid'] . "," . $node['pos'] . ")" . "\n";
        }
        echo str_repeat("-", 40);
        echo "\n\n";
    }

}
