<?php // $Id: settings.php 224 2013-02-26 03:15:05Z malu $

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(
        new admin_setting_configselect('block_hub_client/method',
            get_string('settings/method', 'block_hub_client'),
            get_string('settings/method/description', 'block_hub_client'),
            'immediate', array(
                'cron'      => get_string('settings/method:cron', 'block_hub_client'),
                'immediate' => get_string('settings/method:immediate', 'block_hub_client'),
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
