<?php

class connectproxymodule {
    var $settings;

    public function __construct($params) {
        $parameter_names = array('baseurl','username','password','meeting_template');

        foreach($parameter_names as $name) {
            if (isset($params[$name])) {
                $this->settings[$name] = $params[$name];
            }
        }
    }

    public function query($input) {
        $action = $input['action'];
        $method = $input['method'];
        $params = $input['params'];

        // construct the url for the query, ensuring the baseurl
        // ends with a slash
        $url = $this->settings['baseurl'];
        if (substr($url,-1) != '/') {
            $url .= '/';
        }
        $url .= $action;
        $url .= '?username='.$this->settings['username'].'&password='.$this->settings['password'];

        if ($method == "GET" && is_array($params) && sizeof($params) > 0) {
            $url .= "&".http_build_query($params, '', '&');
        }

        $ch = curl_init($url);

        if ($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));

            $json_header =  array( 'Content-type: application/json; charset=utf-8');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $json_header);
        }
        elseif ($method == "DELETE") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }
        elseif ($method == "PUT") {
            $put_fields = (is_array($params)) ? http_build_query($params, '', '&') : $params; 
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $put_fields); 
        }

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $ret = curl_exec($ch);

        $curl_errormsg = "";
        if(curl_errno($ch))
        {
            $curl_errormsg = ' Curl error msg: ' . curl_error($ch);
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $information = curl_getinfo($ch, CURLINFO_HEADER_OUT);
        curl_close($ch);

        if ($http_code >= 400) {
            debugging( "Error: HTTP request to API failed ($http_code).", DEBUG_DEVELOPER );
            return false;
        }

        $returned_data = json_decode($ret, true);

        if (!is_array($returned_data)) {
            debugging( "Error: API call returned no data.", DEBUG_DEVELOPER );
            return false;
        }
        if (array_key_exists('Message', $returned_data) && $returned_data['Message'] == "username & password is not valid") {
            debugging( "Error: API call denied.", DEBUG_DEVELOPER );
            return false;
        }

        if (array_key_exists('Message', $returned_data) && $returned_data['Message'] == "An error has occurred.") {
            debugging( "Error: API call failed.", DEBUG_DEVELOPER );
            return false;
        }

        if (!array_key_exists('Error', $returned_data)) {
            debugging( "Error: Message from API not well formed.", DEBUG_DEVELOPER );
            return false;
        }

        if (!array_key_exists('Result', $returned_data)) {
            debugging( "Error: No result found in response from API.", DEBUG_DEVELOPER );
            return false;
        }

        if (!empty($curl_errormsg)) {
            $returned_data['Error']++;
            $returned_data['ErrorMessage'] .= $curl_errormsg;
        }

        return $returned_data;
    }

    public function user_exists($email) {

        $action = 'users/FindUsersByEmail';
        $method = "GET";

        $params = array('email' => $email);

        $input = array(
            'action' => $action,
            'method' => $method,
            'params' => $params,
        );

        $ret = $this->query($input);
        if (!$ret) {
            notice("Error: API call by user_exists() failed");
        }

        $res = $ret['Result'];

        return (sizeof($res) > 0 ? true : false);
    }

    public function create_user($params) {

        $cu_results = array(
            'error' => 0,
            'message' => '',
        );

        $action = 'users';
        $method = "POST";

        $firstname = $params['firstname'];
        $lastname = $params['lastname'];
        $handle = $params['email'];

        $inputdata = array(
            'EPPN' => $handle,
            'FirstName' => $firstname,
            'LastName' => $lastname,
            'Email' => $handle,
            'OrganizationIdentification' => $handle,
        );

        $input = array(
            'action' => $action,
            'method' => $method,
            'params' => $inputdata,
        );

        $ret = $this->query($input);
        if (!$ret) {
            notice("Error: API call by create_user() failed");
        }

        if ($ret['ErrorCode'] > 0) {
            $cu_results['error'] = 1;
            $cu_results['message'] = "Create user query - error code: ".$ret['ErrorCode'].". message: ".$ret['ErrorMessage'];
            return $cu_results;
        }

        if (!array_key_exists('Result', $ret)) {
            $cu_results['error'] = 1;
            $cu_results['message'] = "Create user query: No result found.";
            return $cu_results;
        }

        if ($ret['Result']['Email'] == $handle) {
            return true;
        }

        $cu_results['error'] = 1;
        $cu_results['message'] = 'Create user query: Unknown error';
        return $cu_results;
    }

    public function create_meeting($params) {

        $cm_results = array(
            'error' => 0,
            'message' => '',
        );

        $action = 'meetings';
        $method = "POST";

        $name = $params['name'];
        $handle = $params['shortname'].'_'.$params['id'];
        $hosts = $params['hosts'];

        $folder_id = $params['folder_id'];

        $meeting_name = $name . " [" . $handle . "]";

        date_default_timezone_set('UTC');

        # TODO: Read start time and duration from moodle form.

        $inputdata = array(
            'Template' => $this->settings['meeting_template'],
            'Permissions' => 'Protected',
            'Name'  => $meeting_name,
            'Begin' => date('c'),
            'End'   => date('c', time() + 60*60*2),
            'Hosts' => $hosts,
            'FolderId' => $folder_id,
        );

        $input = array(
            'action' => $action,
            'method' => $method,
            'params' => $inputdata,
        );

        $ret = $this->query($input);

        if ($ret['ErrorCode'] > 0) {
            $cm_results['error'] = 1;
            $cm_results['message'] = "Create meeting - error code: ".$ret['ErrorCode'].", error message: ".$ret['ErrorMessage'];
            return $cm_results;
        }

        if (!array_key_exists('Result', $ret)) {
            $cm_results['error'] = 1;
            $cm_results['message'] = "Create meeting - no result found.";
            return $cm_results;
        }

        if ($ret['Result']['Name'] == $meeting_name) {
            $cm_results['results'] = $ret['Result'];
            return $cm_results;
        } else {
            $cm_results['error'] = 1;
            $cm_results['message'] = "Error creating meeting: meeting name mismatch";
            return $cm_results;
        }
    }

    public function rename_meeting($form_object) {

        global $DB;

        $rm_results = array(
            'error' => 0,
            'message' => '',
        );

        $moodle_meeting = $DB->get_record('connectproxymodule', array('id'=>$form_object->id), '*', MUST_EXIST);

        $meeting_id = $moodle_meeting->meeting_id;
        $action = 'meetings/'.$meeting_id;
        $method = "PUT";

        $course = $DB->get_record('course', array('id'=>$form_object->course), '*', MUST_EXIST);

        $new_name = $form_object->name;
        $shortname = $course->shortname;
        $moodle_id = $form_object->coursemodule;

        $handle = $shortname.'_'.$moodle_id;
        $meeting_name = $new_name . " [" . $handle . "]";

        date_default_timezone_set('UTC');

        # TODO: Read start time and duration from moodle form.

        $inputdata = array(
            'Name'  => $meeting_name,
            'Begin' => date('c'),
            'End'   => date('c', time() + 60*60*2),
        );

        $input = array(
            'action' => $action,
            'method' => $method,
            'params' => $inputdata,
        );

        $ret = $this->query($input);

        if ($ret['ErrorCode'] > 0) {
            $rm_results['error'] = 1;
            $rm_results['message'] = "Rename meeting - error code: ".$ret['ErrorCode'].", error message: ".$ret['ErrorMessage'];
            return $rm_results;
        }

        if (!array_key_exists('Result', $ret)) {
            $rm_results['error'] = 1;
            $rm_results['message'] = "Rename meeting - no result found. error?";
            return $rm_results;
        }

        if ($ret['Result']['Name'] == $meeting_name) {
            $rm_results['results'] = $ret['Result'];
            return $rm_results;
        } else {
            $rm_results['error'] = 1;
            $rm_results['message'] = "Error renaming meeting: meeting name mismatch";
            return $rm_results;
        }
    }

    public function delete_meeting($params) {

        $action = 'meetings';
        $method = "DELETE";

        $meeting_id = $params['meeting_id'];

        $action .= '/'.$meeting_id;

        $input = array(
            'action' => $action,
            'method' => $method,
            'params' => array(),
        );

        $ret = $this->query($input);

        if (array_key_exists('ErrorCode', $ret) && $ret['ErrorCode'] > 0) {
            echo "Delete meeting - error code: ".$ret['ErrorCode'].", message: ".$ret['ErrorMessage']."\n";
            #print_r($ret);
            return false;
        }

        if (array_key_exists('Message', $ret) && $ret['Message'] == "An error has occurred.") {
            echo "Delete meeting - message: ".$ret['Message']."\n";
            #print_r($ret);
            return false;
        }

        return true;
    }

    public function delete_recording($params) {

        $action = 'recordings';
        $method = "DELETE";

        $recording_id = $params['recording_id'];

        $action .= '/'.$recording_id;

        $input = array(
            'action' => $action,
            'method' => $method,
            'params' => array(),
        );

        $ret = $this->query($input);

        if (array_key_exists('ErrorCode', $ret) && $ret['ErrorCode'] > 0) {
            echo "Delete recording - error code: ".$ret['ErrorCode'].", message: ".$ret['ErrorMessage']."\n";
            #print_r($ret);
            return false;
        }

        if (array_key_exists('Message', $ret) && $ret['Message'] == "An error has occurred.") {
            echo "Delete recording - message: ".$ret['Message']."\n";
            #print_r($ret);
            return false;
        }

        return true;
    }

    public function get_recordings($params) {

        $action = 'recordings/GetMeetingRecordings';
        $method = "GET";

        $meeting_id = $params['meeting_id'];

        $inputdata = array(
            'meetingId' => $meeting_id,
        );

        $action .= "/" . $meeting_id;

        $input = array(
            'action' => $action,
            'method' => $method,
            'params' => array(),
        );

        $ret = $this->query($input);

        if ($ret['ErrorCode'] > 0) {
            echo "Get recordings - error code: ".$ret['ErrorCode'].", message: ".$ret['ErrorMessage']."\n";
            #print_r($ret);
            return false;
        }

        if (!array_key_exists('Result', $ret)) {
            echo "Get recordings: No result found.";
            return false;
        }

        return $ret['Result'];
    }

    public function add_user_to_meeting($params) {

        $autm_results = array(
            'error' => 0,
            'message' => '',
        );

        $action = 'meetings/AddUserToMeeting';
        $method = "POST";

        # rest:
        # /{userId}/{meetingId}/{permission}

        $meeting_id = $params['meeting_id'];
        $user_id = $params['user_id'];
        $permission = $params['permission'];

        $action .= "/" . $user_id;
        $action .= "/" . $meeting_id;
        $action .= "/" . $permission;

        $input = array(
            'action' => $action,
            'method' => $method,
            'params' => array(),
        );

        $ret = $this->query($input);

        if ($ret['ErrorCode'] > 0) {
            $autm_results['error'] = 1;
            $autm_results['message'] = json_encode($ret['ErrorCode']);
            return $autm_results;
        }

        return true;
    }

    public function create_folder($params) {

        $cu_results = array(
            'error' => 0,
            'message' => '',
        );

        $action = 'folders';
        $method = "POST";

        $folder_name = $params['name'];

        $inputdata = array(
            'Name'  => $folder_name,
        );

        $input = array(
            'action' => $action,
            'method' => $method,
            'params' => $inputdata,
        );

        $ret = $this->query($input);
        if (!$ret) {
            notice("Error: API call by create_folder() failed");
        }

        if ($ret['ErrorCode'] > 0) {
            $cu_results['error'] = 1;
            $cu_results['message'] = "Create folder query - error code: ".$ret['ErrorCode'].". message: ".$ret['ErrorMessage'];
            return $cu_results;
        }

        if (!array_key_exists('Result', $ret)) {
            $cu_results['error'] = 1;
            $cu_results['message'] = "Create folder query: No result found.";
            return $cu_results;
        }

        if ($ret['Result']['Name'] == $folder_name) {
            return true;
        }

        $cu_results['error'] = 1;
        $cu_results['message'] = 'Create folder query: Unknown error';
        return $cu_results;
    }

    public function get_folder($params) {

        $action = 'folders/GetFolderByName';
        $method = "GET";

        $name = $params['name'];

        #$inputdata = array(
        #    'meetingId' => $meeting_id,
        #);

        $action .= "/" . $name;

        $input = array(
            'action' => $action,
            'method' => $method,
            'params' => array(),
        );

        $ret = $this->query($input);

        if ($ret['ErrorCode'] > 0) {
            echo "Get folder - error code: ".$ret['ErrorCode'].", message: ".$ret['ErrorMessage']."\n";
            #print_r($ret);
            return false;
        }

        if (!is_array($ret)) {
            #echo "Get folder: No result found.";
            return false;
        }

        if (!array_key_exists('Result', $ret)) {
            #echo "Get folder: No result found.";
            return false;
        }

        if (empty($ret['Result'])) {
            #echo "Folder not found.";
            return false;
        }

        if ($ret['Result'][0]['Name'] == $name) {
            return $ret['Result'][0]['ScoId'];
        }
        
        #echo "Get folder: Wrong folder returned.";
        return false;
    }

    public function get_folders($params) {

        $action = 'folders';
        $method = "GET";

        #$meeting_id = $params['meeting_id'];

        #$inputdata = array(
        #    'meetingId' => $meeting_id,
        #);

        #$action .= "/" . $meeting_id;

        $input = array(
            'action' => $action,
            'method' => $method,
            'params' => array(),
        );

        $ret = $this->query($input);

        if ($ret['ErrorCode'] > 0) {
            echo "Get folders - error code: ".$ret['ErrorCode'].", message: ".$ret['ErrorMessage']."\n";
            #print_r($ret);
            return false;
        }

        if (!array_key_exists('Result', $ret)) {
            #echo "Get folders: No result found.";
            return false;
        }

        return $ret['Result'];
    }
}

