<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_masterytrack', get_string('pluginname', 'local_masterytrack'));

    // Enable plugin
    $settings->add(new admin_setting_configcheckbox(
        'local_masterytrack/enabled',
        get_string('enableplugin', 'local_masterytrack'),
        get_string('enableplugin_desc', 'local_masterytrack'),
        1
    ));

    // LLM Settings
    $settings->add(new admin_setting_heading(
        'local_masterytrack/llmsettings',
        get_string('llmsettings', 'local_masterytrack'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_masterytrack/llm_endpoint',
        get_string('llmendpoint', 'local_masterytrack'),
        'LLM API endpoint URL',
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_masterytrack/llm_apikey',
        get_string('llmapikey', 'local_masterytrack'),
        'LLM API key',
        ''
    ));

    $ADMIN->add('localplugins', $settings);

    // Add link to manage programs
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_masterytrack_programs',
        get_string('programs', 'local_masterytrack'),
        new moodle_url('/local/masterytrack/manage_programs.php')
    ));
}
