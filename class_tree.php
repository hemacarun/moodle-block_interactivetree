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
    protected $osparentid;
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
        $this->osparentid = $this->options['structure']['parent_id'];
        $this->osid = $this->options['structure']['id'];
        $this->ospos =  $this->options['structure']['position'];
        $this->osleft = $this->options['structure']['left'];
        $this->osright = $this->options['structure']['right'];
        $this->oslevel = $this->options['structure']['level'];
        $this->structuretable = $this->options['structure_table'];
        $this->datatable = $this->options['data_table'];
        $this->data2structure = $this->options['data2structure'];
    }

    
    
    function get_node($id, $options = array()) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE;
        $selectedstructure = implode(", s.", $this->options['structure']);
        $selecteddata = implode(", d.", $this->options['data']);

        $sql = " SELECT s.$selectedstructure, d.$selecteddata 
			FROM 
                                {block_interactivetree_struct} as  s, 
                                {block_interactivetree_data} as d 
			WHERE 
				s.{$this->osid} = d.{$this->data2structure}  AND 
				s.{$this->osid} = ? ";

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
        $selectedstructure = implode(", s.", $this->options['structure']);
        $selecteddata = implode(", d.", $this->options['data']);

        if ($recursive) {
            $node = $this->get_node($id);
            $osleft = $this->osleft;
            $osright = $this->osright;

           $sql = "SELECT  s.$selectedstructure, d.$selecteddata			
                       FROM  {block_interactivetree_struct} s,{block_interactivetree_data} d 
				WHERE s.{$this->osid} = d.{$this->data2structure} AND 
					s.{$this->osleft} > :osleft AND 
					s.{$this->osright} < :osright
				ORDER BY 
					s.{$this->osleft}";
            $response = $DB->get_records_sql($sql, array('osleft' => $node->$osleft, 'osright' => $node->$osright));
        } else {
            $sql = " SELECT  s.$selectedstructure, d.$selecteddata
		     FROM  {block_interactivetree_struct} s,{block_interactivetree_data} d 
		     WHERE
		     s.{$this->osid} = d.{$this->data2structure} AND 
		     s.{$this->osparentid} = :parentid ORDER BY  s.{$this->ospos} ";
            $response = $DB->get_records_sql($sql, array('parentid' => $id));
        }
       
        
        return $response;
    }

    public function get_path($id) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE;
        $node = $this->get_node($id);
        $selectedstructure = implode(", s.", $this->options['structure']);
        $selecteddata = implode(", d.", $this->options['data']);
        $osleft = $this->osleft;
        $osright = $this->osright;
        $sql = false;
        if ($node) {

            $sql = "SELECT  s.$selectedstructure, d.$selecteddata
		    FROM  {block_interactivetree_struct} s,{block_interactivetree_data} d 
		    WHERE 
		    s.{$this->osid} = d.{$this->data2structure} AND 
		    s.{$this->osleft} < :osleft  AND 
		    s.{$this->osright} > :osright  ORDER BY  s.{$this->osleft} ";
        }

        return $sql ? $DB->get_records_sql($sql, array('osleft' => $node->$osleft, 'osright' => $node->$osright)) : false;
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
        $par = array();

        // PREPARE NEW PARENT 
        // update positions of all next elements
        $option_structureid = $this->osid;

        $sql[] = "UPDATE {block_interactivetree_struct}
		  SET {$this->ospos} = {$this->ospos} + 1
		  WHERE {$this->osparentid}  = :parentstructureid  AND 
		{$this->ospos}  >=:position";
		  
        $params[]=array('parentstructureid'=> $parent->$option_structureid ,'position'=> $position );
        $options_structure_right = $this->osright;
        $options_structure_left = $this->osleft;
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
        $sql[] = "UPDATE {block_interactivetree_struct} 
		    SET {$this->osleft} = {$this->osleft} + 2
		    WHERE 
		    {$this->osleft}  >= :ref ";
        $params[]=array('ref'=> $ref_lft );
        

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
        $sql[] = "UPDATE {block_interactivetree_struct} 
		    SET  {$this->osright} = {$this->osright} + 2
		    WHERE {$this->osright} >= :refright";
        $params[]= array('refright'=>$ref_rgt );
        


        //$tmp = array();
        $insert_temp = new Stdclass();
        foreach ($this->options['structure'] as $k => $v) {
            switch ($k) {
                case 'id':
                  //  $tmp[] = null;
                    $insert_temp->id = null;
                    break;
                case 'left':
                   // $tmp[] = (int) $ref_lft;
                    $insert_temp->lft = (int) $ref_lft;
                    break;
                case 'right':
                   // $tmp[] = (int) $ref_lft + 1;
                    $insert_temp->rgt = (int) $ref_lft + 1;
                    break;
                case 'level':
                   // $tmp[] = (int) $parent->$v + 1;
                    $insert_temp->lvl = (int) $parent->$v + 1;
                    break;
                case 'parent_id':
                   // $tmp[] = $parent->$option_structureid;
                    $insert_temp->pid = $parent->$option_structureid;
                    break;
                case 'position':
                    //$tmp[] = $position;
                    $insert_temp->pos = $position;
                    break;
                default: 
                    null;
                   // $tmp[] = null;
            }
        }
        //$par[] = $tmp;
        $treestruct_table = $this->structuretable;
        $node = $DB->insert_record($treestruct_table, $insert_temp);
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
        $structureparentid = $this->osparentid;
        $structureid = $this->osid;
        $structurepos = $this->ospos;
        $structureleft = $this->osleft;
        $structureright = $this->osright;
        $structurelevel = $this->oslevel;


        if ($id->$structureparentid == $parent->$structureid && $position > $id->$structurepos) {
            $position ++;
        }
        if ($parent->children && $position >= count($parent->children)) {
            $position = count($parent->children);
        }
        if ($id->$structureleft < $parent->$structureleft && $id->$structureright > $parent->$structureright) {
            throw new Exception('Could not move parent inside child');
        }

        $tmp = array();
        $tmp[] = (int) $id->$structureid;
        if ($id->children && is_array($id->children)) {
            foreach ($id->children as $c) {
                $tmp[] = (int) $c->$structureid;
            }
        }
        $width = (int) $id->$structureright - (int) $id->$structureleft + 1;

        $sql = array();

        // PREPARE NEW PARENT
        // update positions of all next elements
        $sql[] = "
			UPDATE {block_interactivetree_struct}
				SET {$this->ospos} = :osposnext
			WHERE 
			        {$this->osid} != :osid   AND 
				{$this->osparentid} = :osparentid  AND 
				{$this->ospos}  >= :ospos  ";
       $params[]=array('osposnext'=> $this->ospos + 1 ,'osid'=>(int) $id->$structureid,'osparentid'=>(int) $parent->$structureid,'ospos'=>$position); 

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
        $sql[] = " UPDATE {block_interactivetree_struct}
				SET {$this->osleft} = :osleftwidth 
			WHERE 
				{$this->osleft} >= :reflft AND 
				{$this->osid} NOT IN(" . implode(',', $tmp) . ") 
			";
        $params[]=array('osleftwidth'=>$this->osleft + $width ,'reflft'=>(int) $ref_lft );
        // update right indexes
        $ref_rgt = false;
        if (!$parent->children) {
            $ref_rgt = $parent->$structureright;
        } else if (!isset($parent_c->$position)) {
            $ref_rgt = $parent->$structureright;
        } else {
            $parent_pos = $parent_c->$position;
            $ref_rgt = $parent_pos->$structureleft + 1;
        }
        $sql[] = "UPDATE {block_interactivetree_struct} 
		    SET {$this->osright}  = :osrightwidth 
		    WHERE 
	            {$this->osright} >=  :ref_rgt AND 
	            {$this->osid} NOT IN(" . implode(',', $tmp) . ") ";
        $params[]= array('osrightwidth'=>$this->osright + $width ,'ref_rgt'=> (int) $ref_rgt);

        // MOVE THE ELEMENT AND CHILDREN
        // left, right and level
        $diff = $ref_lft - (int) $id->$structureleft;

        if ($diff > 0) {
            $diff = $diff - $width;
        }
        $ldiff = ((int) $parent->$structurelevel + 1) - (int) $id->$structurelevel;
        $sql[] = " UPDATE {block_interactivetree_struct}
		   SET {$this->osright} = :osrightdiff , {$this->osleft} = :osleftdiff , 
	            {$this->oslevel} = :osleveldiff 
		    WHERE  {$this->osid} IN (" . implode(',', $tmp) . ") ";
        $params =array('osrightdiff'=> $this->osright + $diff,'osleftdiff'=>  $this->osleft + $diff,'osleveldiff'=> $this->oslevel + $ldiff );
        
        // position and parent_id
        $sql[] = " UPDATE {block_interactivetree_struct}
		  SET {$this->ospos} = :ospos , {$this->osparentid} = :osparentid 
		  WHERE {$this->osid} = :osid  ";
        $params[]= array('ospos'=> $position, 'osparentid'=>(int) $parent->$structureid , 'osid'=>(int) $id->$structureid );

        // CLEAN OLD PARENT
        // position of all next elements
        $sql[] = " UPDATE {block_interactivetree_struct}
		   SET {$this->ospos} = :osprevious
		   WHERE {$this->osparentid} = :osparentid  AND 
		    {$this->ospos} > :ospos  ";
       $params[]=array('osprevious'=> $this->ospos- 1 ,'osparentid'=> (int) $id->$structureparentid ,'ospos'=> (int) $id->$options_structure_pos);         
        
        // left indexes
        $sql[] = "UPDATE {block_interactivetree_struct}
		  SET {$this->osleft} = :osleftwidth 
                  WHERE {$this->osleft} > :osleft  AND 
		  {$this->osid} NOT IN(" . implode(',', $tmp) . ")";
        $params[]= array('osleftwidth'=>$this->osleft - $width ,'osleft'=>(int) $id->$structureright);        
        
        // right indexes
        $sql[] = "
			UPDATE {block_interactivetree_struct}
			SET {$this->osright} = :osrightwidth 
			WHERE {$this->osright} > :osright  AND 
			{$this->osid} NOT IN(" . implode(',', $tmp) . ") ";
        $params[]= array('osrightwidth'=>$this->osright - $width ,'osright'=> (int) $id->$structureright );

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

        $osleft = $this->osleft;
        $osright = $this->osright;
        $osparentid = $this->osparentid;
        $osid = $this->osid;
        $ospos = $this->ospos;
        $oslvl = $this->oslevel;


        $old_nodes = $DB->get_records_sql("SELECT * FROM { " . $this->structuretable . " }
			WHERE {$this->osleft}  > :osleft  AND $osright < :osright  
			ORDER BY  $osleft ",array('osleft'=>$id->$osleft ,'osright'=>$id->$osright ));

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

        $sql = array(); $params=array();

        // PREPARE NEW PARENT
        // update positions of all next elements
        $sql[] = "  UPDATE { " . $this->structuretable . " }
		    SET $ospos = :osposnext
		    WHERE $osparentid  = :osparentid  AND 
		     $ospos  >= :ospos ";
        
        $params[]= array('osposnext'=> $ospos  + 1 ,'osparentid'=>(int) $parent->$osid ,'ospos'=> $position);

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
        $sql[] = " UPDATE { " . $this->structuretable . " }
		   SET $osleft  =  :osleftwidth 
		    WHERE $osleft  >=  :osleft  ";
        $params[]= array('osleftwidth'=>$osleft  +  $width  ,'osleft'=>(int) $ref_lft);
        
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
        $sql[] = "  UPDATE { " . $this->structuretable . " }
		    SET $osright  =  :osrightwidth
		    WHERE $osright >= :osright ";
        $params[]= array('osrightwidth'=>  $osright  +  $width ,'osright'=>(int) $ref_rgt);

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
        $fields[$osleft] = $osleft + $diff;
        $fields[$osright] = $osright + $diff;
        $fields[$oslvl] = $oslvl + $ldiff;

        $record_toinsert = $DB->get_record_sql("SELECT  implode(',', array_values($fields)) FROM { " . $this->structuretable . " }WHERE $this->osid IN (" . implode(",", $tmp) . ") 
			ORDER BY  $oslvl ASC ");

        $insert_temp = new stdClass();
        $insert_temp->$osid = null;
        $insert_temp->$osright = $record_toinsert->$fields[$osright];
        $insert_temp->$osleft = $record_toinsert->$fields[$osleft];
        $insert_temp->$oslvl = $record_toinsert->$fields[$oslvl];
        $insert_temp->$osparentid = $record_toinsert->$fields[$osparentid];
        $insert_temp->$ospos = $record_toinsert->$fields[$ospos];

        $iid = $DB->insert_record($this->structuretable, $insert_temp);


        foreach ($sql as $key => $value) {
            try {
                $DB->execute($value, $params[$key]);
            } catch (Exception $e) {
                throw new Exception('Error copying');
            }
        }


        try {
            $DB->execute("  UPDATE { " . $this->structuretable . " }
			    SET {$this->ospos} = :ospos  ,
			    {$this->osparentid} = :osparentid 
			    WHERE {$this->osid}  = :osid ",array('ospos'=>$position,'osparentid'=>$parent->$osid ,'osid'=>$iid));
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
                $data2structure = $this->data2structure;

                $DB->execute("INSERT INTO {block_interactivetree_data} ( $this->data2structure ," . implode(",", $fields) . ") 
				SELECT $iid ," . implode(",", $fields) . " FROM $this->datatable WHERE $this->data2structure = $id->$data2structure  
				ON DUPLICATE KEY UPDATE  $update_fields ");
            } catch (Exception $e) {
                $this->rm($iid);
                throw new Exception('Could not update data after copy');
            }
        }

        $new_nodes = $DB->get_records_sql("SELECT * FROM { " . $this->structuretable . " }
			WHERE {$this->osleft} > :osleft AND  {$this->osright} < :osright AND {$this->osid} != :osid  
			ORDER BY {$this->osleft}", array('osleft'=> $ref_lft,'osright'=> ($ref_lft + $width - 1),'osid'=> $iid ));


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
	$params=array();


        foreach ($new_nodes as $k => $node) {
            $nodeleft = $node->$osleft;
            $sql[] = " UPDATE { " . $this->structuretable . " }
		       SET {$this->osparentid} = :osparentid 
			WHERE {$this->osid} = :osid ";
	     $params[]=array('osparentid'=>$parents->$nodeleft,'osid'=>$node->$osid);		
            if (count($fields)) {
                $up = "";
                foreach ($fields as $f)
                    $keyid = $k->$osid;
                $sql[] = "INSERT INTO {block_interactivetree_data} (" . $this->data2structure . "," . implode(",", $fields) . ") 
					SELECT " . $node->$osid . "," . implode(",", $fields) . " FROM {block_interactivetree_data} 
						WHERE " . $this->data2structure . " = " . $old_nodes->$keyid . " 
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
        $osleft = $this->osleft;
        $osright = $this->osright;
        $osparentid = $this->osparentid;
        $ospos = $this->ospos;
        $lft = $data->$osleft;
        $rgt = $data->$osright;
        $pid = $data->$osparentid;
        $pos = $data->$ospos;
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
		    WHERE {$this->osleft} >= :osleft  AND {$this->osright} <= :osright AND {$this->osid} = :osid ";
			
        $params[]= array('osleft'=>$lft,'osright'=> $rgt,'osid'=>$data->id);
	
        // shift left indexes of nodes right of the node
        $sql[] = "
			UPDATE {" . $this->structuretable . " }
				SET {$this->osleft} = :setos
			WHERE {$this->osleft} > :osleft
		";
        $params[]= array('setos'=> $this->osleft -  $dif ,'osleft'=> $rgt);		
		
        // shift right indexes of nodes right of the node and the node's parents
        $sql[] = "
			UPDATE { " . $this->structuretable . " }
				SET {$this->osright} = :setright
			WHERE {$this->osright} > :osright
		";
        $params[]= array('setright'=> $this->osright - $dif  ,'osright'=> $lft );			
		
        // Update position of siblings below the deleted node
        $sql[] = "
			UPDATE { " . $this->structuretable . " }
				SET {$this->ospos} = :setpos 
			WHERE {$this->osparentid} = :osparentid  AND $this->ospos > :ospos  
		";
	$params[]= array('setpos'=> $this->ospos - 1  ,'osparentid'=> $pid ,'ospos'=>$pos );		
		
        // delete from data table
        if ($this->datatable) {
            $tmp = array();
            $tmp[] = (int) $data->id;
            if ($data->children && is_array($data->children)) {
                foreach ($data->children as $v) {
                    $tmp[] = $v->id;
                }
            }
            $sql[] = "DELETE FROM {block_interactivetree_data} WHERE $this->data2structure  IN (" . implode(',', $tmp) . ")";
        }

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

        $checking_existingnode = $DB->get_record_sql('SELECT 1 AS res FROM ' . $DB->get_prefix() . $this->structuretable . ' WHERE ' . $this->osid . ' = ' . (int) $id);
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
            $tmp[$this->data2structure] = $id;
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
        //if((int)

        $morethan_onerootnode = $DB->get_record_sql("SELECT COUNT($this->osid) AS res FROM { " . $this->structuretable . " } WHERE {$this->osparentid} = 0");

        if ($morethan_onerootnode->res !== 1) {
            $report[] = "No or more than one root node.";
        }
        //if((int)
        $rootnode_leftindex = $DB->get_record_sql("SELECT {$this->osleft} AS res FROM {". $this->structuretable ."} WHERE {$this->osparentid} = 0");

        if ($rootnode_leftindex->res !== 1) {
            $report[] = "Root node's left index is not 1.";
        }
        $checking_missingparent = $DB->get_record_sql("
			SELECT 
				COUNT(" . $this->osid . ") AS res 
			FROM { " . $this->structuretable . " }s 
			WHERE 
				$this->osparentid != 0 AND 
				(SELECT COUNT(" . $this->osid . ") FROM { " . $this->structuretable . " } WHERE {$this->osid} = s.{$this->osparentid}) = 0");

        if ($checking_missingparent->res > 0) {
            $report[] = "Missing parents.";
        }

        $rightindex = $DB->get_record_sql("SELECT MAX($this->osright) AS res FROM {". $this->structuretable ."} ");
        $nodecount = $DB->get_record_sql("SELECT COUNT($this->osid) AS res FROM {". $this->structuretable ."} ");

        if ($rightindex->res / 2 != $nodecount->res) {
            $report[] = "Right index does not match node count.";
        }

        $dup_rightindex = $DB->get_record_sql("SELECT COUNT(DISTINCT $this->osright) AS res FROM {". $this->structuretable ."} ");
        $dup_leftindex = $DB->get_record_sql("SELECT COUNT(DISTINCT $this->osleft) AS res FROM {". $this->structuretable ."} ");
        if ($dup_rightindex->res != $dup_leftindex->res) {
            $report[] = "Duplicates in nested set.";
        }

        $un_node = $DB->get_record_sql("SELECT COUNT(DISTINCT $this->osid) AS res FROM {". $this->structuretable ."} ");
        $un_left = $DB->get_record_sql("SELECT COUNT(DISTINCT $this->osleft) AS res FROM {". $this->structuretable ."} ");
        if ($un_node->res != $un_left->res) {
            $report[] = "Left indexes not unique.";
        }

    
        $un_node1 = $DB->get_record_sql("SELECT COUNT(DISTINCT $this->osid) AS res FROM {" .$this->structuretable."} ");
        $rt_index = $DB->get_record_sql("SELECT COUNT(DISTINCT $this->osright) AS res FROM {" .$this->structuretable."} ");
        if ($un_node1->res != $rt_index->res) {
            $report[] = "Right indexes not unique.";
        }


        $checking_leftrightindex = $DB->get_record_sql("
				SELECT 
					s1.$this->osid AS res 
				FROM { " . $this->structuretable . " }s1, { " . $this->structuretable . " }s2 
				WHERE 
					s1.{$this->osid} != s2.{$this->osid} AND 
					s1.{$this->osleft} = s2.{$this->osright} 
				LIMIT 1");
        if ($checking_leftrightindex->res) {
            $report[] = "Nested set - matching left and right indexes.";
        }
	
        $checking_positions1 = $DB->get_record_sql("
				SELECT 
					$this->osid AS res 
				FROM { " . $this->structuretable . " }s 
				WHERE 
					{$this->ospos}  >= (
						SELECT 
							COUNT($this->osid) 
						FROM { " . $this->structuretable . " }
						WHERE  {$this->osparentid}  = s.{$this->osparentid}
					)
				LIMIT 1");


        $positon2 = $DB->get_record_sql("
				SELECT 
					s1.{$this->osid} AS res 
				FROM { " . $this->structuretable . " }s1, { " . $this->structuretable . " }s2 
				WHERE 
					s1.{$this->osid}  != s2.{$this->osid} AND 
					s1.{$this->osparentid}  = s2.{$this->osparentid}  AND 
					s1.{$this->ospos}  = s2.{$this->ospos} 
				LIMIT 1");

        if (isset($checking_positions1->res) || isset($positon2->res)) {
            $report[] = "Positions not correct.";
        }


        $checking_Adjacency = $DB->get_record_sql("
			SELECT 
				COUNT($this->osid) as res FROM { " . $this->structuretable . " }s 
			WHERE 
				(
					SELECT 
						COUNT($this->osid) 
					FROM { " . $this->structuretable . " }
					WHERE 
						{$this->osright} < s.{$this->osright} AND 
						{$this->osleft} > s.{$this->osleft} AND 
						{$this->oslevel} = s.{$this->oslevel} + 1
				) != 
				(
					SELECT 
						COUNT(*) 
					FROM { " . $this->structuretable . " }
					WHERE 
						{$this->osparentid} = s.{$this->osid}
				)");

        if ($checking_Adjacency->res) {
            $report[] = "Adjacency and nested set do not match.";
        }



        $checking_missingrecord = $DB->get_record_sql("
				SELECT 
					COUNT($this->osid) AS res 
				FROM { " . $this->structuretable . " }s 
				WHERE 
					(SELECT COUNT(" . $this->data2structure . ") FROM {block_interactivetree_data} WHERE " . $this->data2structure . " = s.$this->osid) = 0
			");

        if ($this->datatable && $checking_missingrecord->res) {
            $report[] = "Missing records in data table.";
        }


        $checking_danglingrecord = $DB->get_record_sql("
				SELECT 
					COUNT(" . $this->data2structure . ") AS res 
				FROM {block_interactivetree_data} s 
				WHERE 
					(SELECT COUNT($this->osid) FROM { " . $this->structuretable . " } WHERE {$this->osid} = s." . $this->data2structure . ") = 0
			");
        if ($this->datatable && $checking_danglingrecord->res) {
            $report[] = "Dangling records in data table.";
        }
        return $get_errors ? $report : count($report) == 0;
    }

   
    public function dump() {
        global $DB, $CFG;
        $selectedstructure = implode(", s.", $this->options['structure']);
        $selecteddata = implode(", d.", $this->options['data']);
        
        $nodes = $DB->get_records_sql("
			SELECT 
				s.$selectedstructure, 
				d.$selecteddata 
			FROM 
				{block_interactivetree_struct} s, 
				{block_interactivetree_data} d 
			WHERE 
				s. {$this->osid}  = d.{$this->data2structure} 
			ORDER BY {$this->osleft}"
        );
        echo "\n\n";
        foreach ($nodes as $node) {
            echo str_repeat(" ", (int) $node[$this->oslevel] * 2);
            echo $node[$this->osid] . " " . $node["nm"] . " (" . $node[$this->osleft] . "," . $node[$this->osright] . "," . $node[$this->oslevel] . "," . $node[$this->osparentid] . "," . $node[$this->ospos] . ")" . "\n";
        }
        echo str_repeat("-", 40);
        echo "\n\n";
    }

}
