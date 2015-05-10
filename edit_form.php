<?php

/**

 * Used to define settings of the block interactive tree
 *

 */
class block_interactivetree_edit_form extends block_edit_form {

    protected function specific_definition($mform) {
        global $CFG, $DB;

        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $firstnode = array('select the node to add url');
        $nodelists = $DB->get_records_sql_menu('select id,nm  from {tree_data}');
        $totalnodes = $firstnode + $nodelists;

        $select = $mform->addElement('select', 'config_node', get_string('selectnode', 'block_interactivetree'), $totalnodes);
        $select->setMultiple(true);
        $mform->setDefault('config_node', '');
        $mform->setType('config_node', PARAM_MULTILANG);


        $mform->registerNoSubmitButton('addurl');
        $mform->addElement('submit', 'addurl', get_string('addurl', 'block_interactivetree'));


        $mform->addElement('hidden', 'addurlplace');
        $mform->setType('addurlplace', PARAM_INT);



    }

    function definition_after_data() {
        global $DB;
        $mform = $this->_form;
        $node = $mform->getElementValue('config_node');
        if ($node[0] > 0) {
            $availablefromgroup = array();
            foreach ($node as $key => $value) {
                $nodeinfo = $DB->get_record('tree_data', array('id' => $value));
                $nodename = $nodeinfo->nm;
                $nodeid = $nodeinfo->id;
                
                $availablefromgroup[] = $mform->createElement('static', 'config_description', '',  $nodename,array('style'=>'width:35%'));
                $availablefromgroup[] = $mform->createElement('text', 'config_' . $nodeid, $nodeid, array('placeholder' =>'add url to '.$nodename,'style'=>'width:40%'));
               // $mform->addHelpButton('config_' . $nodeid, 'status', 'enrol_manual');
            }
            $group = $mform->createElement('group', 'config_group', 'group1', $availablefromgroup, null, false);
            $mform->insertElementBefore($group, 'addurlplace');
        }
    }

}

?>