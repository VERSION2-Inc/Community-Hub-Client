<?php
/**
 *  MAJ Hub Client block
 *  
 *  @author  VERSION2, Inc.
 *  @version $Id: block_hub_client.php 152 2012-12-02 07:04:43Z malu $
 */

require_once __DIR__.'/classes/controller.php';

class block_hub_client extends block_base
{
    public function init()
    {
        $this->title   = get_string('title', __CLASS__);
        $this->version = 2012113000;
    }

    public function applicable_formats()
    {
        return array('course' => true);
    }

    /**
     *  Gets a block content
     *  
     *  @return object|string
     */
    public function get_content()
    {
        if ($this->content !== null)
            return $this->content;

        if (!$this->page->user_is_editing())
            return $this->content = '';

        $context = context_course::instance($this->page->course->id);
        if (!has_capability('moodle/backup:backupactivity', $context))
            return $this->content = '';

        $controller = new hub_client\controller();
        $html = $controller->render_list($this->page->course->id);

        $this->page->requires->strings_for_js(array('login', 'cancel', 'username', 'password'), 'moodle');
        $this->page->requires->string_for_js('error:missingcourseware', __CLASS__);
        $this->page->requires->string_for_js('confirm:retryupload', __CLASS__);
        $this->page->requires->js_init_call('M.block_hub_client.init');

        return $this->content = (object)array('text' => $html);
    }

    /**
     *  Cron job
     *  
     *  @global \moodle_database $DB
     *  @return boolean true if succeeded
     */
    public function cron()
    {
        global $DB;

        try {
            require_once __DIR__.'/classes/backup.php';
            require_once __DIR__.'/classes/client.php';
            require_once __DIR__.'/classes/scoped.php';

            set_time_limit(0);

            // gets queued active backup tasks
            $tasks = $DB->get_records_sql(
                'SELECT b.*, a.userid
                 FROM {block_hub_client_servers} s
                 JOIN {block_hub_client_accounts} a ON a.serverid = s.id
                 JOIN {block_hub_client_backups} b ON b.accountid = a.id
                 WHERE s.deleted = 0 AND b.timestarted IS NULL');
            foreach ($tasks as $task) try {
                // double check to prevent the backup from begin duplicated
                $backup = $DB->get_record('block_hub_client_backups',
                    array('id' => $task->id), '*', MUST_EXIST);
                if (!empty($backup->timestarted) || !empty($backup->fileid))
                    continue; // the task has already been started

                // indicates that the backup task has been started
                $backup->timestarted = time();
                $DB->update_record('block_hub_client_backups', $backup);

                // first, makes a course backup and store its file id to backups table
                $file = hub_client\backup($task->courseid, $task->userid);
                $backup->fileid = $file->get_id();
                $backup->timecompleted = $file->get_timecreated();
                $DB->update_record('block_hub_client_backups', $backup);

                // second, creates a new courseware in the MAJ Hub
                $filesize = $file->get_filesize();
                $course = $DB->get_record('course', array('id' => $backup->courseid), '*', MUST_EXIST);
                $account = $DB->get_record('block_hub_client_accounts',
                    array('id' => $backup->accountid), '*', MUST_EXIST);
                $server = $DB->get_record('block_hub_client_servers',
                    array('id' => $account->serverid, 'deleted' => 0), '*', MUST_EXIST);
                $client = new hub_client\client($server->wwwroot);
                $client->login($account->username, $account->password);
                $coursewareid = $client->create($course->fullname, $course->shortname, $filesize);
                $upload = (object)array(
                    'backupid' => $backup->id,
                    'coursewareid' => $coursewareid,
                    'dataposition' => 0,
                    'timequeued' => time(),
                    );
                $upload->id = $DB->insert_record('block_hub_client_uploads', $upload);

                $chunksize = 1024 * 1024; // TODO: this should be configurable and negotiatable

                // third, uploads all the part of the course backup to the MAJ Hub
                $upload->timestarted = time();
                $DB->update_record('block_hub_client_uploads', $upload);
                $fp = $file->get_content_file_handle();
                $scoped = new hub_client\scoped(function () use ($fp) { fclose($fp); });
                while (!feof($fp) && ($content = fread($fp, $chunksize)) !== false) {
                    $client->upload($coursewareid, $upload->dataposition, $content);
                    $upload->dataposition += strlen($content);
                    $upload->timeprogressed = time();
                    $DB->update_record('block_hub_client_uploads', $upload);
                    if (strlen($content) < $chunksize)
                        break;
                }
                $upload->timecompleted = time();
                $DB->update_record('block_hub_client_uploads', $upload);

            } catch (moodle_exception $ex) {
                // TODO: gather errors and show them after all
                // TODO: retry failed uploads in next cron job
                error_log($ex->__toString());
                continue;
            }
        } catch (moodle_exception $ex) {
            error_log($ex->__toString());
            return false;
        }
        return true;
    }
}
