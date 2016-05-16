<?php

//
//
// This software is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This Moodle block is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The simple interactive tree block class
 *
 * Used to produce interactive tree block
 *
 * @package blocks
 * @copyright 2015 hemalatha arun <hemalatha@eabyas.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_interactivetree extends block_base {

    /** @var int */
    public static $navcount;

    /** @var string */
    public $blockname = null;

    /** @var bool */
    protected $contentgenerated = false;

    /** @var bool|null */
    protected $docked = null;

    function init() {    
        $this->blockname = get_class($this);        
        $this->title = get_string('pluginname', 'block_interactivetree');
    }

    /**
     * All multiple instances of this block
     * @return bool Returns true
     */
    function instance_allow_multiple() {
        return true;
    }

    /**
     * Set the applicable formats for this block to all
     * @return array
     */
    function applicable_formats() {
        return array('all' => true);
    }

    function specialization() {
      //  $systemcontext = context_system::instance();
      //  $title_string = format_string(get_string('pluginname', 'block_interactivetree'));

        if ($this->title == '') {
            $this->title = format_string(get_string('pluginname', 'block_interactivetree'));
        }
    }

    /**
     * Allow the user to configure a block instance
     * @return bool Returns true
     */
    function instance_allow_config() {
        return true;
    }

    /**
     * The navigation block cannot be hidden by default as it is integral to
     * the navigation of Moodle.
     *
     * @return false
     */
    function instance_can_be_hidden() {
        return true;
    }

    function instance_can_be_docked() {
        return (!empty($this->title) && parent::instance_can_be_docked());
    }

    function get_required_javascript() {
        global $PAGE;
        $PAGE->requires->jquery();
        $PAGE->requires->js('/blocks/interactivetree/dist/jstree.js', true);
        $context = $PAGE->context;
	    $PAGE->requires->js('/blocks/interactivetree/js/custom_jstree.js', true);
        
        if (is_siteadmin() || has_capability('block/interactivetree:manage', $context)) {
	   $capabality=1;
          //  $PAGE->requires->js('/blocks/interactivetree/js/custom.js');
        } else {
           $capabality=0;
           // $PAGE->requires->js('/blocks/interactivetree/js/custom_withoutaction.js');
        }
	 $PAGE->requires->js_init_call('interactive_jstree', array($capabality), false);
	
    }

    function interactivetree_addurl() {
        global  $DB;
        $formcontent = $this->config;

        if (isset($formcontent->node)) {
            foreach ($formcontent->node as  $value) {

                $temp = new stdClass();
                $exists_data = $DB->get_record('block_interactivetree_data', array('id' => $value));

                if (isset($formcontent->$value)) {
                    if ($exists_data->url != $formcontent->$value && !empty($formcontent->$value)) {

                        $temp->id = $value;
                        $temp->nm = $exists_data->nm;
                        $temp->url = $formcontent->$value;
                        $DB->update_record('block_interactivetree_data', $temp);
                    }
                }
            }
        }
    }

    function get_content() {

        global $PAGE;
        $PAGE->requires->css('/blocks/interactivetree/css/style.css');
        $PAGE->requires->css('/blocks/interactivetree/dist/themes/default/style.min.css');
       // $systemcontext = context_system::instance();

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';


        $this->page->navigation->initialise();
        $this->interactivetree_addurl();
	
				
        if (isloggedin()) {
            $this->content = new stdClass;
            $this->content->text = '<div id="block_interactivetree_main">
	                 <div id="block_interactivetree_container" role="main">
			<div id="block_interactivetree_tree"></div>
			<div id="block_interactivetree_data">
				<div class="block_interactivetree_content block_interactivetree_code" style="display:none;"><textarea id="block_interactivetree_code" readonly="readonly"></textarea></div>


			</div>
		        </div>
		        </div>';


            return $this->content;
        }
    }

}
