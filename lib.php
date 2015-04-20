<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library of interface functions and constants for module enrolmentreminder
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the enrolmentreminder specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    local
 * @subpackage enrolmentreminder
 * @copyright  2011 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function local_enrolmentreminder_extends_settings_navigation($settingsnav) {
    global $PAGE;
    global $DB;
    global $USER;
    global $SITE;

    if (has_capability('moodle/site:config', context_system::instance())) {
        if ( ($PAGE->context->contextlevel == CONTEXT_COURSE) && ($PAGE->course->id != $SITE->id) ) {
            if ($settingnode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE)) {
                $url = new moodle_url('/local/enrolmentreminder/index.php', array('courseid' => $PAGE->course->id));
                $mynode = navigation_node::create(
                    get_string('enrolmentreminder', 'local_enrolmentreminder'),
                    $url,
                    navigation_node::NODETYPE_LEAF,
                    'local_enrolmentreminder',
                    'local_enrolmentreminder',
                    new pix_icon('enrolmentreminder-16', get_string('enrolmentreminder', 'local_enrolmentreminder'), 'local_enrolmentreminder')
                );
                if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
                    $mynode->make_active();
                }
                $settingnode->add_node($mynode);
            }
        }
    }
}


function local_enrolmentreminder_cron() {
    global $CFG;
    global $DB;
    $lastcron = get_config('local_enrolmentreminder', 'lastcron') ?: (time() - (24 * 3600));
    $events = local_enrolmentreminder_get_events($lastcron, time());
    mtrace("Found " . count($events) . " expiring enrollments");
    local_enrolmentreminder_send_messages($events);
    if (! time() > $lastcron) {
        sleep(1); // To ensure that the same mesage isn't sent next time.
    }
}


function local_enrolmentreminder_get_events($timestart, $timeend) {
    global $DB;
    // Multiply leadtime in days by (3600 * 24) to get leadtime in seconds
    $query = "SELECT ue.id, timestart, timeend, e.courseid, userid, er.id AS reminderid " . 
                  ", :timestart2 + (er.leadtime * 60 * 60 * 24) AS wantstart, :timeend2 + (er.leadtime * 60 * 60 * 24) as wantend " .
                  "FROM {user_enrolments} ue, {enrol} e, {enrolmentreminder} er ".
                  "WHERE e.courseid=er.courseid AND ue.enrolid=e.id AND " .
                      "timeend >= :timestart + (er.leadtime * 60 * 60 * 24) AND " . 
                      "timeend <= :timeend + (er.leadtime * 60 * 60 * 24) AND ue.status != " . ENROL_USER_SUSPENDED;
    mtrace($query);
    $message = "timestart: " . ($timestart + (60 * 60 * 24)) . ' - timeend: ' . ($timeend + (60 * 60 * 24));
    mtrace($message);
    return $DB->get_records_sql($query, array('timestart'=>$timestart,'timeend'=>$timeend, 'timestart2'=>$timestart,'timeend2'=>$timeend));
}


function local_enrolmentreminder_send_messages($events) {
    global $DB;
    global $CFG;

    require_once($CFG->libdir.'/completionlib.php');

    $eventdata = new stdClass();
    $eventdata->component           = 'local_enrolmentreminder';   // plugin name
    $eventdata->name                = 'enrolmentending';     // message interface name
    $eventdata->userfrom            = get_admin();

    $dateformat = '%b %e';
    if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
        $dateformat = '%b %#d';
    }

    foreach($events as $event) {
        $course = $DB->get_record('course', array('id'=>$event->courseid));
        $user = $DB->get_record('user', array('id'=>$event->userid));

        if (empty($completioninfo[$event->courseid])) {
            $completioninfo[$event->courseid] = new completion_info($course);
        }
        if ( $completioninfo[$event->courseid]->is_course_complete($event->userid) ) {
            mtrace("user $event->userid has completed course $event->courseid");
            continue;
        }
        // use timeend for enrolments, timestart for events.
        $ending = userdate($event->timeend, $dateformat);
        $eventdata->fullmessage  = local_enrolmentreminder_get_message_plaintext($course, $user, $ending, $event->reminderid);
        if (empty($eventdata->fullmessage)) {
            mtrace("eventdata->fullmessage is empty");
        }
        if (!empty($eventdata->fullmessage)) {
            $eventdata->subject             = $course->shortname . ' ending ' . $ending;
            $eventdata->smallmessage        = $course->fullname . ' ending ' . $ending;
            $eventdata->fullmessagehtml     = '';
            $eventdata->fullmessageformat   = FORMAT_PLAIN;
            $eventdata->notification        = 1;
            $eventdata->userto = $user;
            // $eventdata->userto = get_admin();
            $mailresult = message_send($eventdata);
            mtrace("sent message with result $mailresult: " . print_r($eventdata, true));
            $params = array(
                'context' => context_course::instance($event->courseid),
                'objectid' => $course->id,
                'relateduserid' => $event->userid
            );
            $msg_event = \local_enrolmentreminder\event\message_sent::create($params);
            $msg_event->trigger();
        }
    }
}

function local_enrolmentreminder_get_message_plaintext($course, $user, $ending, $reminderid) {
    global $CFG;
    require_once($CFG->dirroot . '/local/enrolmentreminder/locallib.php');
    $reminders = local_enrolmentreminder_getexisting($course->id, false, $reminderid);
    foreach($reminders as $reminder) {
        if (!empty($reminder->tmpltext)) {
            return enrolmentreminder_processtemplate($reminder->tmpltext, array('course'=>$course,'user'=>$user,'enddate'=>$ending, 'CFG'=>$CFG));
        }
    }
}

function local_enrolmentreminder_getexisting($courseid, $defaultifnone = false, $reminderid = null) {
    global $DB;

    if ($reminderid) {
        $result = $DB->get_records('enrolmentreminder', array('id'=>$reminderid));
    } else {
        $result = $DB->get_records('enrolmentreminder', array('courseid'=>$courseid));
    }
    if (!empty($result)) {
        return $result;
    } else {
        if ($defaultifnone) {
           $default = file_get_contents(__DIR__.'/emailtemplates/default.php.inc');
           return array(array('courseid' => $courseid, 'tmpltext' => $default, 'submitbutton' => 'Add new reminder'));
        }
    }
}

