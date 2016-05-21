<?php
require_once('../../config.php');
require_once(dirname(__FILE__) . '/class_tree.php');
$opertaion = optional_param('operation', ' ', PARAM_TEXT);
$id = optional_param('id', 0, PARAM_INT);
$parent = optional_param('parent', 0, PARAM_INT);
$position = optional_param('position', 0, PARAM_INT);
$text = optional_param('text', ' ', PARAM_TEXT);
global $CFG, $DB, $OUTPUT, $PAGE;

// Getting block context level
$instance = $DB->get_record('block_instances', array('blockname' => 'interactivetree'), '*', MUST_EXIST);
$blockcontext =  context_block::instance($instance->id);

if (isset($opertaion)) {
    $fs = new block_interactivetree_manage();
    try {       
        $rslt = null;
        switch ($opertaion) {
         
            case 'get_node':
                $node = isset($id) && $id !== '#' ? (int) $id : 0;
                $temp = $fs->get_children($node);
                $rslt = array();
                foreach ($temp as $v) {
                    $treeinfo = $DB->get_record('block_interactivetree_data', array('id' => $v->id));
                    if ($treeinfo->url != null)
                        $url = $treeinfo->url;
                    else
                        $url = '#';                   
                    $rslt[] = array('id' => $v->id, 'text' => $v->nm, 'children' => ($v->rgt - $v->lft > 1),'a_attr' => array('href' => $url));
                }                
                break;
            
            case "get_content":
                $node = isset($id) && $id !== '#' ? $id : 0;
                $node = explode(':', $node);
                if (count($node) > 1) {
                    $rslt = array('content' => 'Multiple selected');
                } else {
                    $temp = $fs->get_node((int) $node[0], array('with_path' => true));
                    $rslt = array('content' => 'Selected: /' . implode('/', array_map(function ($v) {
                                            return $v->nm;
                                        }, $temp->path)) . '/' . $temp->nm);
                }
                break;
            
            case 'create_node':
               if( has_capability('block/interactivetree:manage', $blockcontext)){
                    $node = isset($id) && $id !== '#' ? (int) $id : 0;
                    $temp = $fs->createnode($node, isset($position) ? (int) $position : 0, array('nm' => isset($text) ? $text : 'New node'));
                    $rslt = array('id' => $temp);
                }
                break;
            
            case 'rename_node':
                if( has_capability('block/interactivetree:manage', $blockcontext)){
                    $node = isset($id) && $id !== '#' ? (int) $id : 0;
                    $rslt = $fs->renamenode($node, array('nm' => isset($text) ? $text : 'Renamed node'));
                }
                break;
            
            case 'delete_node':
                if( has_capability('block/interactivetree:manage', $blockcontext)){
                    $node = isset($id) && $id !== '#' ? (int) $id : 0;
                    $rslt = $fs->removenode($node);
                }
                break;
            
            default:
                throw new Exception('Unsupported operation: ' . $operation);
                break;
        }
        header('Content-Type: application/json; charset=utf-8');        
        echo json_encode($rslt);
    } catch (Exception $e) {
        header($_SERVER["SERVER_PROTOCOL"] . ' 500 Server Error');
        header('Status:  500 Server Error');
        echo $e->getMessage();
    }
    die();
}

