<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = array(

    'mod/connectproxymodule:view' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'guest'          => CAP_PREVENT,
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'coursecreator'  => CAP_ALLOW,
            'manager'        => CAP_ALLOW
        ),
    ),
    'mod/connectproxymodule:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'guest'          => CAP_PREVENT,
            'student'        => CAP_PREVENT,
            'teacher'        => CAP_PREVENT,
            'editingteacher' => CAP_ALLOW,
            'coursecreator'  => CAP_ALLOW,
            'manager'        => CAP_ALLOW
        ),
    ),
    'mod/connectproxymodule:deleteinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'guest'          => CAP_PREVENT,
            'student'        => CAP_PREVENT,
            'teacher'        => CAP_PREVENT,
            'editingteacher' => CAP_ALLOW,
            'coursecreator'  => CAP_ALLOW,
            'manager'        => CAP_ALLOW
        ),
    ),

);
