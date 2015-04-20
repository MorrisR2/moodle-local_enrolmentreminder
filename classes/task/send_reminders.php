<?php

namespace local_enrolmentreminder\task;

class send_reminders extends \core\task\scheduled_task {      
    public function get_name() {
        return get_string('enrolmentreminder', 'local_enrolmentreminder');
    }
                                                                     
    public function execute() {
        include_once($CFG->dirroot.'/local/enrolmentreminder/lib.php');
        mtrace('running enrolment reminders scheduled task');
        local_enrolmentreminder_cron();
        set_config('lastcron', time(), 'local_enrolmentreminder');
    }                                                                                                                               
}
