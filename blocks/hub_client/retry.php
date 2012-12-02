<?php // $Id: retry.php 152 2012-12-02 07:04:43Z malu $

require_once __DIR__.'/../../config.php';
require_once __DIR__.'/classes/controller.php';

try {
    require_sesskey();
    $coursewareid = required_param('courseware', PARAM_INT);

    $controller = new hub_client\controller();
    $controller->retry($coursewareid);

} catch (Exception $ex) {
    header('HTTP/1.1 400 Bad Request');
    echo hub_client\exception::jsonify($ex);
}
