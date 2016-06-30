<?php

defined('MOODLE_INTERNAL') || die;

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_connectproxymodule_mod_form extends moodleform_mod {

	function definition() {

		global $gw, $login;
		$mform = $this->_form;

		$mform->addElement('header', 'general', 'Meeting');
		$mform->addElement('text', 'name', get_string('title', 'connectproxymodule'), array('size'=>'64'));
		$mform->setType('name', PARAM_TEXT);
		$mform->addRule('name', null, 'required', null, 'client');

        $meeting_types = array(
            'collaboration' => 'Collaboration',
            'presentation' => 'Presentation',
            'lecture' => 'Lecture',
        );

        $mform->addElement('select', 'meeting_type', 'Type', $meeting_types);
        $mform->setDefault('meeting_type', 'lecture');
        $mform->addHelpButton('meeting_type', 'meeting_type', 'connectproxymodule');

		$this->standard_coursemodule_elements();

		$this->add_action_buttons();

	}

}

global $data, $cm;
$course = $DB->get_record('course', array('id'=>$data->course), '*', MUST_EXIST);
$mform = new mod_connectproxymodule_mod_form($data, $data->section, $cm, $course);

