<?php  

require_once($CFG->dirroot.'/config.php');
require_once('locallib.php');

function connectproxymodule_add_instance($connectproxymodule) {
    #debugging("add_instance starts");

    global $CFG, $DB, $USER;

    // TODO: Move connection preparation to a function, and add connection checking.
    $cc = new connectproxymodule(array(
        'baseurl' => $CFG->connectproxymodule_cp_url,
        'username' => $CFG->connectproxymodule_cp_user,
        'password' => $CFG->connectproxymodule_cp_pass,
        'meeting_template' => $CFG->connectproxymodule_meeting_template,
    ));

    // get handle of course object
    $course = $DB->get_record('course', array('id'=>$connectproxymodule->course), '*', MUST_EXIST);

    // ensure user exists, so that hosts will not contain an invalid email address
    if (! $cc->user_exists($USER->email)) {
        $ret = $cc->create_user(array(
            'firstname' => $USER->firstname,
            'lastname' => $USER->lastname,
            'email' => $USER->email,
        ));
        if ( $ret !== true ) {
            $msg = 'Failed to create user with email '.$USER->email." (".$ret['message'].")";
            notice($msg, "$CFG->wwwroot/course/view.php?id=$course->id");
            return false;
        }
    }

    if (! $cc->user_exists($USER->email)) { 
        #debugging('Cannot create user with email '.$USER->email, DEBUG_DEVELOPER);
        notice('User was not created - email '.$USER->email, "$CFG->wwwroot/course/view.php?id=$course->id");
        return false;
    }

    // get folder id for user, create if one does not exist
    $folder_id = $cc->get_folder(array('name' => $USER->email));
    if (!$folder_id) {
        $res = $cc->create_folder(array('name' => $USER->email));
        $folder_id = $cc->get_folder(array('name' => $USER->email));
    }

    if (!is_numeric($folder_id) || $folder_id == 0) {
        //failed to get a valid folder_id
        $res = $cc->create_folder(array('name' => 'tmp'));
        $folder_id = $cc->get_folder(array('name' => 'tmp'));
    }

    // create the meeting
    $res = $cc->create_meeting(array(
        'name' => $connectproxymodule->name,
        'shortname' => $course->shortname,
        'id' => $connectproxymodule->coursemodule,
        'hosts' => array($USER->email),
        'folder_id' => $folder_id,
    ));

    $meeting_id = 0;
    $url = "";

    if ($res['error'] == 0) {
        $meeting_id = $res['results']['MeetingId'];
        $url = $res['results']['Url'];
    } else {
        #debugging('Cannot create meeting', DEBUG_DEVELOPER);
        notice('Cannot create meeting. '.json_encode($res), "$CFG->wwwroot/course/view.php?id=$course->id");
        return false;
    }

    $connectproxymodule->meeting_id = $meeting_id;
    $connectproxymodule->url = $url;

    $id = $DB->insert_record("connectproxymodule", $connectproxymodule);
    return $id;
}


function connectproxymodule_update_instance($connectproxymodule) {
	global $CFG, $DB;
	
    $connectproxymodule->timemodified = time();
    $connectproxymodule->id = $connectproxymodule->instance;

    // get handle of course object
    $course = $DB->get_record('course', array('id'=>$connectproxymodule->course), '*', MUST_EXIST);

    $cc = new connectproxymodule(array(
        'baseurl' => $CFG->connectproxymodule_cp_url,
        'username' => $CFG->connectproxymodule_cp_user,
        'password' => $CFG->connectproxymodule_cp_pass,
        'meeting_template' => $CFG->connectproxymodule_meeting_template,
    ));

    $res = $cc->rename_meeting($connectproxymodule);

    if ($res['error'] == 0) {
        $meeting_id = $res['results']['MeetingId'];
        $msg = 'Renamed meeting.';
        add_to_log($course->id, "connectproxymodule", "rename", "view.php?id=".$connectproxymodule->coursemodule, $msg, "$connectproxymodule->coursemodule");
    } else {
        $msg = 'Cannot rename meeting. ('.$res['message'].')';
        add_to_log($course->id, "connectproxymodule", "rename", "view.php?id=".$connectproxymodule->coursemodule, $msg, "$connectproxymodule->coursemodule");
        notice($msg, "$CFG->wwwroot/course/view.php?id=$course->id");
        return false;
    }
    
    return $DB->update_record("connectproxymodule", $connectproxymodule);
}


function connectproxymodule_delete_instance($id) {
    global $CFG, $DB;

    if (! $meeting = $DB->get_record("connectproxymodule", array("id"=>$id))) {
        return false;
    }

    $ac_meeting_id = $meeting->meeting_id;

    $cc = new connectproxymodule(array(
        'baseurl' => $CFG->connectproxymodule_cp_url,
        'username' => $CFG->connectproxymodule_cp_user,
        'password' => $CFG->connectproxymodule_cp_pass,
        'meeting_template' => $CFG->connectproxymodule_meeting_template,
    ));

    $res = $cc->delete_meeting(array(
        'meeting_id' => $ac_meeting_id,
    ));

    // The course_modules record is removed automatically,
    // but the connectproxymodule record must be removed manually.
    $DB->delete_records("connectproxymodule", array("id"=>$id));

    # TODO: Read results from $res, confirm deletion of meeting room.

    return true;
}

function connectproxymodule_supports($feature) {
    switch($feature) {
    case FEATURE_IDNUMBER:                return false;
    case FEATURE_GROUPS:                  return false;
    case FEATURE_GROUPINGS:               return false;
    case FEATURE_GROUPMEMBERSONLY:        return false;
    case FEATURE_MOD_INTRO:               return false;
    case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
    case FEATURE_GRADE_HAS_GRADE:         return false;
    case FEATURE_GRADE_OUTCOMES:          return false;
    #case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_OTHER;
    case FEATURE_BACKUP_MOODLE2:          return false;
    case FEATURE_NO_VIEW_LINK:            return false;

    default: return null;
    }
}

