<?php

defined('MOODLE_INTERNAL') || die;

$settings->add(
    new admin_setting_configtext(
        'connectproxymodule_cp_url',
        get_string('cp_url', 'connectproxymodule'),
        get_string('cp_url_desc', 'connectproxymodule'),
        'host:port/path/',
        PARAM_URL
    )
);

$settings->add(
    new admin_setting_configtext(
        'connectproxymodule_cp_user',
        get_string('cp_user', 'connectproxymodule'),
        get_string('cp_user_desc', 'connectproxymodule'),
        '',
        PARAM_TEXT
    )
);

$settings->add(
    new admin_setting_configtext(
        'connectproxymodule_cp_pass',
        get_string('cp_pass', 'connectproxymodule'),
        get_string('cp_pass_desc', 'connectproxymodule'),
        '',
        PARAM_TEXT
    )
);

$settings->add(
    new admin_setting_configtext(
        'connectproxymodule_meeting_template',
        get_string('meeting_template', 'connectproxymodule'),
        get_string('meeting_template_desc', 'connectproxymodule'),
        '',
        PARAM_INT
    )
);

$settings->add(
    new admin_setting_configtext(
        'connectproxymodule_ac_url',
        get_string('ac_url', 'connectproxymodule'),
        get_string('ac_url_desc', 'connectproxymodule'),
        '',
        PARAM_URL
    )
);

$settings->add(
    new admin_setting_configtext(
        'connectproxymodule_idp',
        get_string('idp', 'connectproxymodule'),
        get_string('idp_desc', 'connectproxymodule'),
        '',
        PARAM_TEXT
    )
);

