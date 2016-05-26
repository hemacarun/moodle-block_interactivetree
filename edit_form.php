<?php
/**

 * Used to define settings of the block interactive tree
 *

 */
class block_interactivetree_edit_form extends block_edit_form {

    protected function specific_definition($mform) {
        global $DB;
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));
        $firstnode = array('select the node to add url');
        $nodelists = $DB->get_records_sql_menu('select id,nm  from {block_interactivetree_data}');
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

    public function definition_after_data() {
        global $DB;
        $mform = $this->_form;
        $node = $mform->getElementValue('config_node');
        if ($node[0] > 0) {
            $availablefromgroup = array();
            foreach ($node as $value) {
                $nodeinfo = $DB->get_record('block_interactivetree_data', array('id' => $value));
                $nodename = $nodeinfo->nm;
                $nodeid = $nodeinfo->id;
                $availablefromgroup[] = $mform->createElement('static', 'config_description', '', $nodename, array('style' => 'width:35%'));
                $availablefromgroup[] = $mform->createElement('text', 'config_' . $nodeid, $nodeid,
                                        array('placeholder' => 'add url to ' . $nodename, 'style' => 'width:40%'));
            }
            $group = $mform->createElement('group', 'config_group', 'group1', $availablefromgroup,
                                           array('&nbsp;&nbsp; &nbsp;', '</br>'), false);
            $mform->insertElementBefore($group, 'addurlplace');
        }
    }
}

