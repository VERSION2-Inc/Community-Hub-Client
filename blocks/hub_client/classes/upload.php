<?php
/**
 *  MAJ Hub Client
 *  
 *  @author  VERSION2, Inc.
 *  @version $Id: upload.php 224 2013-02-26 03:15:05Z malu $
 */
namespace hub_client;

require_once __DIR__.'/backup.php';
require_once __DIR__.'/client.php';
require_once __DIR__.'/scoped.php';

/**
 *  Uploads a course under given user's capabilities
 *  
 *  @global \moodle_database $DB
 *  @param int $backupid
 *  @param int $courseid
 *  @param int $userid
 *  @throws \moodle_exception
 */
function upload($backupid, $courseid, $userid)
{
    global $DB;

    // double check to prevent the backup from being duplicated
    $backup = $DB->get_record('block_hub_client_backups', array('id' => $backupid), '*', MUST_EXIST);
    if (!empty($backup->timestarted) || !empty($backup->fileid))
        return; // the backup task has already been started

    $chunksize = 1024 * 1024; // TODO: this should be configurable and negotiatable

    // indicates that the backup task has been started
    $backup->timestarted = time();
    $DB->update_record('block_hub_client_backups', $backup);

    // first, makes a course backup and store its file id to backups table
    $file = backup($courseid, $userid);
    $backup->fileid        = $file->get_id();
    $backup->timecompleted = $file->get_timecreated();
    $DB->update_record('block_hub_client_backups', $backup);

    // second, creates a new courseware in the MAJ Hub
    $filesize = $file->get_filesize();
    $course  = $DB->get_record('course', array('id' => $backup->courseid), '*', MUST_EXIST);
    $account = $DB->get_record('block_hub_client_accounts', array('id' => $backup->accountid), '*', MUST_EXIST);
    $server  = $DB->get_record('block_hub_client_servers',
        array('id' => $account->serverid, 'deleted' => 0), '*', MUST_EXIST);
    $client = new client($server->wwwroot);
    $client->login($account->username, $account->password);
    $coursewareid = $client->create($course->fullname, $course->shortname, $filesize);
    $upload = new \stdClass;
    $upload->backupid     = $backup->id;
    $upload->coursewareid = $coursewareid;
    $upload->dataposition = 0;
    $upload->timequeued   = time();
    $upload->id = $DB->insert_record('block_hub_client_uploads', $upload);

    // third, uploads all the part of the course backup to the MAJ Hub
    $upload->timestarted = time();
    $DB->update_record('block_hub_client_uploads', $upload);
    $fp = $file->get_content_file_handle();
    $scoped = new scoped(function () use ($fp) { fclose($fp); });
    while (!feof($fp) && ($content = fread($fp, $chunksize)) !== false) {
        $client->upload($coursewareid, $upload->dataposition, $content);
        $upload->dataposition  += strlen($content);
        $upload->timeprogressed = time();
        $DB->update_record('block_hub_client_uploads', $upload);
        if (strlen($content) < $chunksize)
            break;
    }
    unset($scoped);
    $upload->timecompleted = time();
    $DB->update_record('block_hub_client_uploads', $upload);
}
