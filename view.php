<?php

require_once("../../config.php");
require_once("lib.php");

/*
    1. validate meeting on moodle-side
    2. validate meeting on cp-side

    3. validate user existance and permissions on moodle-side
    4. check if user exists on cp-side
        if not: create and continue
    5. make sure that the user has permissions on cp-side (update always? or not?)

    6. get recordings for meeting

    7. render page
        Show error and stop processing on errors in steps 1, 2, 3
        Show meeting link and information
        Show recordings (if num>0) with edit links if applicable
 */


$cmid = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);


if (!empty($cmid)) {
    if (! $cm = get_coursemodule_from_id('connectproxymodule', $cmid)) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error('coursemisconf');
    }
    if (! $meeting = $DB->get_record("connectproxymodule", array("id"=>$cm->instance))) {
        print_error('invalidid', 'connectproxymodule');
    }

} else {
    print_error('invalidid', 'connectproxymodule');
}

/*
$cm is the course module object ($cm->id is the same as $cmid (which is extracted from the url parameter "id") and references the course_modules table)
$course is the course object ($course->id references the course table)
$meeting is the connectproxymodule instance ($meeting->id is the key in the connectproxymodule table)
 */

require_login($course->id);
$PAGE->set_url('/mod/connectproxymodule/view.php', array('id' => $cm->id));
add_to_log($course->id, "connectproxymodule", "view", "view.php?id=".$cm->id, "$meeting->id");

// create connection object

$cc = new connectproxymodule(array(
    'baseurl' => $CFG->connectproxymodule_cp_url,
    'username' => $CFG->connectproxymodule_cp_user,
    'password' => $CFG->connectproxymodule_cp_pass,
    'meeting_template' => $CFG->connectproxymodule_meeting_template,
));

$context = get_context_instance(CONTEXT_MODULE, $cm->id);

switch ($action) {
case 'deleterecording':
    if (!has_capability('mod/connectproxymodule:deleteinstance', $context)) {
        add_to_log($course->id, "connectproxymodule", "view", "view.php?id=".$cm->id,
            "User tried to delete recording but does not does not have mod/connectproxymodule:deleteinstance capability.", "$cm->id");
        print_error('permissiondenied', 'connectproxymodule');
    } 
    $recording_id = optional_param('recordingid', 0, PARAM_INT);
    $delete_confirmed = optional_param('deleteconfirmed', 0, PARAM_BOOL);

    if (!$delete_confirmed) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('confirm_delete_recording', 'connectproxymodule'),
            new moodle_url($PAGE->url, array('action'=>'deleterecording', 'recordingid'=>$recording_id, 'deleteconfirmed'=>1)),
            new moodle_url($PAGE->url)
        );
        echo $OUTPUT->footer();
        die;
    } else {
        // Delete the recording.
        $cc->delete_recording(array('recording_id' => $recording_id));
        add_to_log($course->id, "connectproxymodule", "view", "view.php?id=".$cm->id, "User deleted recording $recording_id.", "$cm->id");
        redirect($PAGE->url);
    }
}

// Print the page header

if ($course->category) {
    $navigation = "<a href=\"../../course/view.php?id=$course->id\">$course->shortname</a> ->";
} else {
    $navigation = '';
}

print_header(
    "$course->shortname: $meeting->name",
    "$course->fullname",
    "$navigation <a href=index.php?id=$course->id>meetings</a> -> $meeting->name", 
    "",
    "",
    true,
    #update_module_button($cm->id, $course->id, get_string('modulename', 'connectproxymodule')), 

    navmenu($course, $cm)
);

#
# make sure user can access the remote resource:

if (! $cc->user_exists($USER->email)) {
    add_to_log($course->id, "connectproxymodule", "view", "view.php?id=".$cm->id, "User does not exist on remote resource. Attempting to create user.", "$cm->id");
    $ret = $cc->create_user(array(
        'firstname' => $USER->firstname,
        'lastname' => $USER->lastname,
        'email' => $USER->email,
    ));
    if ($ret === true) {
        add_to_log($course->id, "connectproxymodule", "view", "view.php?id=".$cm->id, "User was created.", "$cm->id");
    } else {
        add_to_log($course->id, "connectproxymodule", "view", "view.php?id=".$cm->id, "User creation unsuccessful.", "$cm->id");
    }
} else {
    #echo "User exists.<br>";
}

# If the user has the "add" capability, we assume the user has a teacher role.

$role = 'participant';

$moodle_role = has_capability('mod/connectproxymodule:addinstance', $context) ? 'teacher' : 'student';

$student_role = array(
    'lecture'       => 'participant',
    'presentation'  => 'presenter',
    'collaboration' => 'host',
);

if ($moodle_role == 'teacher') {
    $role = 'host';
} else {
    $meeting_type = $meeting->meeting_type;
    if (array_key_exists($meeting_type, $student_role)) {
        $role = $student_role[$meeting_type];
    }
}

$ret = $cc->add_user_to_meeting(array(
    'meeting_id'    => $meeting->meeting_id,
    'user_id'       => $USER->email,
    'permission'    => $role,
));
if ( $ret !== true ) {
    $msg = 'Failed to add user to meeting (email: '.$USER->email.") [".$ret['message']."]";
    notice($msg, "$CFG->wwwroot/course/view.php?id=$course->id");
}

// Print the main part of the page
//print_simple_box_start('center', '100%', '#ffffff', 10);

echo "<h1>$meeting->name</h1>";

$baseurl = $CFG->connectproxymodule_ac_url;

if ( substr($baseurl, -1) == '/' ) {
    $baseurl = substr($baseurl, 0, -1);
}

$meeting_url = $baseurl.$meeting->url;
$link_text = $meeting_url;

if( !empty( $CFG->connectproxymodule_idp ) )
{
    $meeting_url .= '?idp=' . $CFG->connectproxymodule_idp;
}

$settings_link = "";
$edit_url = new moodle_url('/course/modedit.php', array('update' => $cm->id, 'return' => 1));
if ($role == 'host') {
    $settings_link = "<span class='pushed'>[<a href='".$edit_url."'>Settings</a>]</span>";
}

echo "<div class='connectproxymodule-view-property'><span>Link: <a href='".$meeting_url."' target='_blank'>".$link_text."</a></span>".$settings_link."</div>";
echo "<div class='connectproxymodule-view-property'>Meeting type: ".$meeting->meeting_type."</div>";

$recordings = $cc->get_recordings(array('meeting_id' => $meeting->meeting_id));
$num = count($recordings);

if (is_array($recordings) && $num > 0) {
    $s = $num > 1 ? 's' : '';
    echo "Found $num recording${s}:<br/>";

    echo "<table id='connectproxymodule-recording-list'><tbody>";
    foreach($recordings as $r) {

        echo "<tr><td><a href='".$baseurl.$r['Url']."' target='_blank'>".$r['Name']."</a></td>";

        $instancename = $r['Name'];

        $dur = $r['Duration'];
        $dur_bits = explode('.', $dur);
        $dur = $dur_bits[0];
        $dur = preg_replace('/^00:/','',$dur);
        $dur = preg_replace('/^0/','',$dur);
        echo "<td>[".$dur."]</td>";

        if ($role == 'host') {
            echo "<td>[<a href='".$baseurl.$r['Url']."?pbMode=edit' target='_blank'>Edit recording</a>]</td>";
        }
        if ($role == 'host') {
            $delete_recording_url = new moodle_url($PAGE->url, array('action'=>'deleterecording', 'recordingid'=>$r['RecordingId']));
            echo "<td>[<a href='".$delete_recording_url."'>Delete</a>]</td>";
        }
        echo "</tr>";
    }
    echo "</tbody></table>";
}

// Finish the page
echo $OUTPUT->footer();

