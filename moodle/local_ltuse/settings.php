<?php
// Admin settings for local_ltuse.
//
// One page, holding only the settings moodle/site/ declares for this plugin, so that
// scripts/site_config.py can find each in the admin tree. Change a value in
// moodle/site/settings/*.yaml and apply it; a value clicked here shows as drift.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ltuse', get_string('pluginname', 'local_ltuse'));
    $ADMIN->add('localplugins', $settings);

    // Spec 008: administration. Whether course mentors are enrolled automatically (research
    // R10). Declared 1 in moodle/site/settings/admin.yaml since 2026-10-05 (#97).
    $settings->add(new admin_setting_configcheckbox(
        'local_ltuse/coursementorsync',
        new lang_string('setting:coursementorsync', 'local_ltuse'),
        new lang_string('setting:coursementorsync_desc', 'local_ltuse'),
        0
    ));
}
