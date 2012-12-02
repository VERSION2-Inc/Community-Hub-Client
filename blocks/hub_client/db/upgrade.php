<?php // $Id: upgrade.php 152 2012-12-02 07:04:43Z malu $

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

//  if ($oldversion < 2012120200) {
//  }

    return true;
}
