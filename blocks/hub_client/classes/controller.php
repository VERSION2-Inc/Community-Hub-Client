<?php
/**
 *  MAJ Hub Client
 *  
 *  @author  VERSION2, Inc.
 *  @version $Id: controller.php 224 2013-02-26 03:15:05Z malu $
 */
namespace hub_client;

require_once __DIR__.'/exception.php';

/**
 *  Action controller
 */
class controller
{
    /**
     *  Constructor
     *  
     *  @throws \require_login_exception
     */
    public function __construct()
    {
        \require_login(null, false, null, false, true);
    }

    /**
     *  Render a Hub server list
     *  
     *  @global object $USER
     *  @global \moodle_database $DB
     *  @global \core_renderer $OUTPUT
     *  @param int $courseid
     *  @return string
     */
    public function render_list($courseid)
    {
        global $USER, $DB, $OUTPUT;

        $servers = $DB->get_records_sql(
            'SELECT s.id, s.wwwroot, s.title, b.id AS backupid, b.timestarted,
                    u.coursewareid, u.timecompleted, u.dataposition, f.filesize
             FROM {block_hub_client_servers} s
             LEFT JOIN {block_hub_client_accounts} a ON a.serverid = s.id AND a.userid = :userid
             LEFT JOIN {block_hub_client_backups} b ON b.accountid = a.id AND b.courseid = :courseid
             LEFT JOIN {block_hub_client_uploads} u ON u.backupid = b.id
             LEFT JOIN {files} f ON f.id = b.fileid
             WHERE s.deleted = 0',
            array('userid' => $USER->id, 'courseid' => $courseid)
            );
        if (!$servers) {
            return \html_writer::tag('ul',
                \html_writer::tag('li', \get_string('error:noserver', 'block_hub_client')),
                array('class' => 'servers list'));
        }
        $html = \html_writer::start_tag('ul', array('class' => 'servers list'));
        foreach ($servers as $server) {
            $html .= \html_writer::start_tag('li');
            $html .= \html_writer::start_tag('fieldset');
            $html .= \html_writer::tag('legend', $server->title,
                array('class' => "block_hub_client-server-{$server->id}", 'title' => $server->wwwroot));
            if (!$server->backupid) {
                $html .= \html_writer::link(
                    "javascript:M.block_hub_client.upload({$server->id})",
                    $OUTPUT->pix_icon('i/publish', '') . get_string('uploadtohub', 'block_hub_client')
                    );
            } elseif (!$server->timestarted) {
                $cancelaction = "M.block_hub_client.cancel({$server->id})";
                $html .= \html_writer::tag('div',
                    $OUTPUT->pix_icon('i/scheduled', '') . get_string('uploading', 'block_hub_client') .
                    \html_writer::tag('button', get_string('cancel'), array('onclick' => $cancelaction))
                    );
                $html .= \html_writer::tag('div',
                    $OUTPUT->pix_icon('i/progressbar', '', '', array('class' => 'progressbar'))
                    );
            } elseif (!$server->timecompleted) {
                $width = !$server->filesize ? 0 : (5 + ceil(150 * $server->dataposition / $server->filesize));
                $html .= \html_writer::tag('div',
                    $OUTPUT->pix_icon('i/loading_small', '') . get_string('uploading', 'block_hub_client')
                    );
                $html .= \html_writer::tag('div',
                    \html_writer::tag('div', '&nbsp;', array('style' => "width:{$width}px;")),
                    array('class' => 'progressbar')
                    );
            } else {
                $html .= \html_writer::link(
                    "{$server->wwwroot}/local/majhub/edit.php?id={$server->coursewareid}",
                    $OUTPUT->pix_icon('i/edit', '') . get_string('editmetadata', 'block_hub_client')
                    );
            }
            $html .= \html_writer::end_tag('fieldset');
            $html .= \html_writer::end_tag('li');
        }
        $html .= \html_writer::end_tag('ul');
        return $html;
    }

    /**
     *  Authenticate the MAJ Hub server account
     *  
     *  @global object $USER
     *  @global \moodle_database $DB
     *  @param int $serverid
     *  @param string $username
     *  @param string $password
     *  @throws \moodle_exception
     */
    public function auth($serverid, $username = null, $password = null)
    {
        global $USER, $DB;

        $server = $DB->get_record('block_hub_client_servers', array('id' => $serverid), '*', MUST_EXIST);
        $account = $DB->get_record('block_hub_client_accounts',
            array('userid' => $USER->id, 'serverid' => $server->id));
        if (empty($username) || empty($password)) {
            if (!$account)
                throw new exception('noaccount');
            $username = $account->username;
            $password = $account->password;
        }

        require_once __DIR__.'/client.php';
        $client = new client($server->wwwroot);
        $client->login($username, $password);

        if (!$account) {
            $account = new \stdClass;
            $account->userid       = $USER->id;
            $account->serverid     = $server->id;
            $account->username     = $username;
            $account->password     = $password;
            $account->timecreated  = time();
            $account->timemodified = time();
            $DB->insert_record('block_hub_client_accounts', $account);
        } elseif ($username !== $account->username || $password !== $account->password) {
            $account->username     = $username;
            $account->password     = $password;
            $account->timemodified = time();
            $DB->update_record('block_hub_client_accounts', $account);
        }
    }

    /**
     *  Queue a course backup and upload task
     *  
     *  @global object $USER
     *  @global \moodle_database $DB
     *  @param int $serverid
     *  @param int $courseid
     *  @throws \moodle_exception
     */
    public function queue($serverid, $courseid)
    {
        global $USER, $DB;

        $server = $DB->get_record('block_hub_client_servers', array('id' => $serverid), '*', MUST_EXIST);
        $account = $DB->get_record('block_hub_client_accounts',
            array('userid' => $USER->id, 'serverid' => $server->id), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
        $context = \context_course::instance($course->id);
        \require_capability('moodle/backup:backupcourse', $context);

        $backup = $DB->get_record('block_hub_client_backups',
            array('accountid' => $account->id, 'courseid' => $course->id, 'timestarted' => null));
        if (!$backup) {
            $backup = new \stdClass;
            $backup->accountid  = $account->id;
            $backup->courseid   = $course->id;
            $backup->timequeued = time();
            $backup->id = $DB->insert_record('block_hub_client_backups', $backup);
        } else {
            // already queued, do nothing
        }

        if (\get_config('block_hub_client', 'method') === 'immediate') {
            require_once __DIR__.'/upload.php';
            upload($backup->id, $course->id, $account->userid);
        }
    }

    /**
     *  Cancel a queued task
     *  
     *  @global object $USER
     *  @global \moodle_database $DB
     *  @param int $serverid
     *  @param int $courseid
     *  @throws \moodle_exception
     */
    public function cancel($serverid, $courseid)
    {
        global $USER, $DB;

        $DB->execute(
            'DELETE b FROM {block_hub_client_backups} b, {block_hub_client_accounts} a
             WHERE a.id = b.accountid AND a.userid = :userid AND a.serverid = :serverid
               AND b.courseid = :courseid AND b.timestarted IS NULL',
            array('userid' => $USER->id, 'serverid' => $serverid, 'courseid' => $courseid)
            );
    }

    /**
     *  Retry upload
     *  
     *  @global object $USER
     *  @global \moodle_database $DB
     *  @param int $coursewareid
     *  @return int  The serverid
     *  @throws \moodle_exception
     */
    public function retry($coursewareid)
    {
        global $USER, $DB;

        $task = $DB->get_record_sql(
            'SELECT a.serverid, b.courseid
             FROM {block_hub_client_accounts} a
             JOIN {block_hub_client_backups} b ON b.accountid = a.id
             JOIN {block_hub_client_uploads} u ON u.backupid = b.id
             WHERE a.userid = :userid AND u.coursewareid = :coursewareid',
            array('userid' => $USER->id, 'coursewareid' => $coursewareid),
            MUST_EXIST);
        $DB->execute(
            'DELETE u, b FROM {block_hub_client_uploads} u,
                              {block_hub_client_backups} b,
                              {block_hub_client_accounts} a
             WHERE a.id = b.accountid AND a.userid = :userid
               AND b.id = u.backupid AND u.coursewareid = :coursewareid',
            array('userid' => $USER->id, 'coursewareid' => $coursewareid)
            );
        $this->queue($task->serverid, $task->courseid);
        return $task->serverid;
    }
}
