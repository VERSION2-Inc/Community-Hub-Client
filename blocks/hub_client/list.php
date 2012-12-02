<?php // $Id: list.php 152 2012-12-02 07:04:43Z malu $

require_once __DIR__.'/../../config.php';
require_once __DIR__.'/classes/controller.php';

try {
    $courseid = required_param('course', PARAM_INT);

    $controller = new hub_client\controller();
    $PAGE->set_context(context_course::instance($courseid));
    echo $controller->render_list($courseid);

} catch (Exception $ex) {
    header('HTTP/1.1 400 Bad Request');
    echo hub_client\exception::jsonify($ex);
}
