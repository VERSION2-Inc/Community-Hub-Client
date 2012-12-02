<?php
/**
 *  MAJ Hub Client
 *  
 *  @author  VERSION2, Inc.
 *  @version $Id: backup.php 152 2012-12-02 07:04:43Z malu $
 */
namespace hub_client;

require_once __DIR__.'/exception.php';

/**
 *  Creates a backup of a course under given user's capabilities
 *  
 *  @global object $CFG
 *  @global \moodle_database $DB
 *  @param int $courseid
 *  @param int $userid
 *  @return \stored_file
 *  @throws \moodle_exception
 */
function backup($courseid, $userid)
{
    global $CFG, $DB;

    require_once __DIR__.'/../../../backup/util/includes/backup_includes.php';

    // checks if the course exists
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

    // backup settings - includes activities, blocks and filters
    $settings = array(
        //'users'                => false, // MODE_HUB never save users
        'role_assignments'       => false,
        'activities'             => true,
        'blocks'                 => true,
        'filters'                => true,
        'comments'               => false,
        'completion_information' => false,
        'logs'                   => false,
        'histories'              => false,
        );

    \raise_memory_limit(MEMORY_EXTRA);

    // constructs a course backup plan
    $filename = sprintf('%s-%s.mbz', \clean_filename($course->shortname), date('Ymd-His'));
    $bc = new \backup_controller(\backup::TYPE_1COURSE, $course->id,
        \backup::FORMAT_MOODLE, \backup::INTERACTIVE_NO, \backup::MODE_HUB,
        $userid);
    foreach ($settings as $name => $value) {
        if ($bc->get_plan()->setting_exists($name))
            $bc->get_plan()->get_setting($name)->set_value($value);
    }
    $bc->get_plan()->get_setting('filename')->set_value($filename);

    // executes the plan and results a stored file
    $bc->set_status(\backup::STATUS_AWAITING);
    $bc->execute_plan();
    $results = $bc->get_results();
    if (!empty($results['missing_files_in_pool'])) {
        // TODO: store warnings
    }
    if (empty($results['backup_destination']))
        throw new exception('backupfailed');
    return $results['backup_destination'];
}
