<?php // $Id: queue.php 152 2012-12-02 07:04:43Z malu $

require_once __DIR__.'/../../config.php';
require_once __DIR__.'/classes/controller.php';

try {
    require_sesskey();
    $serverid = required_param('server', PARAM_INT);
    $courseid = required_param('course', PARAM_TEXT);

    $controller = new hub_client\controller();
    $controller->queue($serverid, $courseid);

} catch (Exception $ex) {
    header('HTTP/1.1 400 Bad Request');
    echo hub_client\exception::jsonify($ex);
}
