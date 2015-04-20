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
 * @package    local
 * @subpackage enrolmentreminder
 * @copyright  2012, 2013 Texas A&M Engineering Extension Service by Ray Morris
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', 1);

require_once('../../config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/forms.php');

require_login();

$course = null;
if (!empty($_REQUEST['courseid'])) {
    $context = context_course::instance($_REQUEST['courseid']);
    $course = $DB->get_record('course', array('id' => $_REQUEST['courseid']), '*', MUST_EXIST);
} else {
    $context = context_user::instance($USER->id);
}

require_login($course);

$mform = new enrolmentreminderadd_form();
if ($fromform = $mform->get_data()) {
    local_enrolmentreminder_add($fromform);
    redirect(new moodle_url('/local/enrolmentreminder/index.php', array('courseid' => $course->id)));
    exit;
}

if (!empty($_REQUEST['action']) && ($_REQUEST['action'] == 'delete')) {
    local_enrolmentreminder_delete($_REQUEST['reminderid']);
    redirect(new moodle_url('/local/enrolmentreminder/index.php', array('courseid' => $course->id)));
    exit;
}


$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->set_url('/local/enrolmentreminder/index.php', array());
$PAGE->set_title(get_string('pluginname', 'local_enrolmentreminder'));
$PAGE->set_heading(get_string('pluginname', 'local_enrolmentreminder'));


echo $OUTPUT->header();

$mform = new enrolmentreminderadd_form();

if (empty($_REQUEST['courseid']) ) {
    local_enrolmentreminder_choosecourse();
} else {
    $reminders = local_enrolmentreminder_getexisting($_REQUEST['courseid'], false);
    if (!empty($reminders)) {
        foreach ($reminders as $reminder) {
            echo html_writer::start_tag('div', array('class' => 'enrolmentreminderform'));
            $img = $OUTPUT->pix_url('t/delete');
            $url = 'index.php?action=delete&reminderid='.$reminder->id.'&amp;courseid='.$_REQUEST['courseid'];
            echo "<a href=\"$url\"><img src=\"$img\" /> Delete</a>\n\n";
            $mform = local_enrolmentreminder_addform($reminder);
            echo html_writer::end_tag('div');
        }
    }

    echo html_writer::tag('h2', 'Add new reminder');
    $defaulttext = file_get_contents(__DIR__.'/emailtemplates/default.php.inc');
    $newreminder = array('id' => '', 'courseid' => $_REQUEST['courseid'], 'tmpltext' => $defaulttext, 'submitbutton' => 'Add new reminder');
    echo html_writer::start_tag('div', array('class' => 'enrolmentreminderform'));
    $mform = local_enrolmentreminder_addform($newreminder);
    html_writer::end_tag('div');

}

echo $OUTPUT->footer();


function local_enrolmentreminder_choosecourse() {
	global $DB;
    global $PAGE;
?>
    <script src="/teex/js/jquery-1.9.1.js"></script>
    <link href="select2/select2.css" rel="stylesheet"/>
    <script src="select2/select2.js"></script>
    <script>
        $(document).ready(function() { $("#course").select2(); });
    </script>

<form id="assignform" method="post" action="<?php echo $PAGE->url ?>">
  <input type="hidden" name="sesskey" value="<?php echo sesskey() ?>" />
  <table summary="" class="roleassigntable generaltable generalbox boxaligncenter" cellspacing="0">
    <tr>
      <td id="existingcell">
          <p><label for="course">Select a course</label></p>
		  <select name="courseid" id="course">
		  <?php
				$result = $DB->get_records('course');
				foreach ($result as $course) {
					if (! empty ($course->idnumber) ) {
						?>
						<option value="<?php echo $course->id ?>"><?php echo $course->idnumber . ': ' . $course->fullname ?></option>
						<?php
					}
				}
		  ?>
		  </select>
      </td>
    </tr>
	<tr><td><input type="submit" value="Continue" /></td</tr>
  </table>
</form>
<?php
}

function local_enrolmentreminder_addform($data) {
    $mform = new enrolmentreminderadd_form(null, (array) $data);
    $mform->set_data($data);
    $mform->display();
}

function local_enrolmentreminder_add($fromform) {
    global $DB;

    if (!empty($fromform->tmpltext)) {
        $fromform->submitbutton = null;
        if ((!empty($fromform->id)) && ($fromform->id > 0)) {
            $DB->update_record('enrolmentreminder', $fromform);
        } else {
            $fromform->id = null;
            $DB->insert_record('enrolmentreminder', $fromform);
        }
    }
}

function local_enrolmentreminder_delete($reminderid) {
    global $DB;
    $DB->delete_records('enrolmentreminder', array('id'=>$reminderid));
}

