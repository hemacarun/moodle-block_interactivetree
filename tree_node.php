<?php

require_once('../../config.php');

require_once(dirname(__FILE__) . '/class_tree.php');
$opertaion = optional_param('operation', ' ', PARAM_TEXT);
$id = optional_param('id', 0, PARAM_INT);
$parent = optional_param('parent', 0, PARAM_INT);
$position = optional_param('position', 0, PARAM_INT);
$text = optional_param('text', ' ', PARAM_TEXT);
//require_once(dirname(__FILE__) . '/blocks/interactivetree/class.db.php');
global $CFG, $DB, $OUTPUT, $PAGE;




if (isset($opertaion)) {
    $fs = new interactivetree_manage(array('structure_table' => 'block_interactivetree_struct', 'data_table' => 'block_interactivetree_data', 'data' => array('nm')));

    try {
        $rslt = null;
        switch ($opertaion) {
            case 'analyze':
                var_dump($fs->analyze(true));
                die();
                break;
            case 'get_node':
                $node = isset($id) && $id !== '#' ? (int) $id : 0;
                $temp = $fs->get_children($node);
                //print_object($temp);
                $rslt = array();
                foreach ($temp as $v) {
                    $treeinfo = $DB->get_record('block_interactivetree_data', array('id' => $v->id));
                    if ($treeinfo->url != null)
                        $url = $treeinfo->url;
                    else
                        $url = '#';


                    $rslt[] = array('id' => $v->id, 'text' => $v->nm, 'children' => ($v->rgt - $v->lft > 1), 'a_attr' => array('href' => $url));
                }
                // print_object($rslt);
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
                $node = isset($id) && $id !== '#' ? (int) $id : 0;
                $temp = $fs->mk($node, isset($position) ? (int) $position : 0, array('nm' => isset($text) ? $text : 'New node'));
                $rslt = array('id' => $temp);
                break;
            case 'rename_node':
                $node = isset($id) && $id !== '#' ? (int) $id : 0;
                $rslt = $fs->rn($node, array('nm' => isset($text) ? $text : 'Renamed node'));
                break;
            case 'delete_node':
                $node = isset($id) && $id !== '#' ? (int) $id : 0;
                $rslt = $fs->rm($node);
                break;
            case 'move_node':
                $node = isset($id) && $id !== '#' ? (int) $id : 0;
                $parn = isset($parent) && $parent !== '#' ? (int) $parent : 0;
                $rslt = $fs->mv($node, $parn, isset($position) ? (int) $position : 0);
                break;
            case 'copy_node':
                $node = isset($id) && $id !== '#' ? (int) $id : 0;
                $parn = isset($parent) && $parent !== '#' ? (int) $parent : 0;
                $rslt = $fs->cp($node, $parent, isset($position) ? (int) $position : 0);
                break;
            default:
                throw new Exception('Unsupported operation: ' . $_GET['operation']);
                break;
        }
        header('Content-Type: application/json; charset=utf-8');
        // print_object($rslt);
        echo json_encode($rslt);
    } catch (Exception $e) {
        header($_SERVER["SERVER_PROTOCOL"] . ' 500 Server Error');
        header('Status:  500 Server Error');
        echo $e->getMessage();
    }
    die();
}
?>