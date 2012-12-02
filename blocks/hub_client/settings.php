<?php // $Id: settings.php 152 2012-12-02 07:04:43Z malu $

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(
        new admin_setting_configselect('block_hub_client/method',
            get_string('settings/method', 'block_hub_client'),
            get_string('settings/method/description', 'block_hub_client'),
            0, array(
                0 => get_string('settings/method:cron', 'block_hub_client'),
                //1 => get_string('settings/method:immediate', 'block_hub_client'),
                )
            )
        );
    $settings->add(
        new admin_setting_heading('block_hub_client/servers', '',
            html_writer::link(
                new moodle_url('/blocks/hub_client/admin/servers.php'),
                get_string('settings/servers', 'block_hub_client')))
        );
}
