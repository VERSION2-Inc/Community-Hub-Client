<?php // $Id: upgrade.php 224 2013-02-26 03:15:05Z malu $

defined('MOODLE_INTERNAL') || die;

/**
 *  Hub Client upgrade
 *  
 *  @global moodle_database $DB
 */
function xmldb_block_hub_client_upgrade($oldversion = 0)
{
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2013022600) {
        $method = get_config('block_hub_client', 'method');
        if (empty($method) || ctype_digit($method))
            set_config('method', 'immediate', 'block_hub_client');
    }

    return true;
}
