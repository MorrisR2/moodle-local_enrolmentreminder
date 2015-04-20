<?php

global $CFG;
require_once("$CFG->libdir/formslib.php");
 
class enrolmentreminderadd_form extends moodleform {
    function definition() {
        global $CFG;
 
        $mform =& $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('textarea', 'tmpltext', 'Reminder text', 'wrap="virtual" rows="20" cols="50"');
        $mform->setType('tmpltext', PARAM_TEXT);
        $mform->setDefault('tmpltext', $this->_customdata['tmpltext']);
        $mform->addElement('select', 'leadtime', get_string('durationlabel', 'local_enrolmentreminder'), range(0,90));
        $mform->setType('leadtime', PARAM_INT);
        $mform->setDefault('leadtime', 10);
    }

    public function definition_after_data() {
        parent::definition_after_data();

        $mform =& $this->_form;
        $this->add_action_buttons(false);
    }
}

