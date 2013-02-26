<?php
/**
 *  MAJ Hub Client block
 *  
 *  @author  VERSION2, Inc.
 *  @version $Id: block_hub_client.php 224 2013-02-26 03:15:05Z malu $
 */

require_once __DIR__.'/classes/controller.php';

class block_hub_client extends block_base
{
    public function init()
    {
        $this->title   = get_string('title', __CLASS__);
        $this->version = 2013022100;
    }

    public function applicable_formats()
    {
        return array('course' => true, 'course-category' => false);
    }

    public function instance_can_be_docked()
    {
        return false; // AJAX won't work with Dock
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
        $this->page->requires->strings_for_js(array('uploadcompleted'), __CLASS__);
        $this->page->requires->string_for_js('error:missingcourseware', __CLASS__);
        $this->page->requires->string_for_js('confirm:editmetadata', __CLASS__);
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

        require_once __DIR__.'/classes/upload.php';

        try {
            set_time_limit(0);

            // gets queued active backup tasks
            $tasks = $DB->get_records_sql(
                'SELECT b.*, a.userid
                 FROM {block_hub_client_servers} s
                 JOIN {block_hub_client_accounts} a ON a.serverid = s.id
                 JOIN {block_hub_client_backups} b ON b.accountid = a.id
                 WHERE s.deleted = 0 AND b.timestarted IS NULL');
            foreach ($tasks as $task) try {
                hub_client\upload($task->id, $task->courseid, $task->userid);
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
