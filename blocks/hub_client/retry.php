<?php // $Id: retry.php 218 2013-02-21 13:11:22Z malu $

require_once __DIR__.'/../../config.php';
require_once __DIR__.'/classes/controller.php';

try {
    require_sesskey();
    $coursewareid = required_param('courseware', PARAM_INT);

    $controller = new hub_client\controller();
    echo $controller->retry($coursewareid);

} catch (Exception $ex) {
    header('HTTP/1.1 400 Bad Request');
    echo hub_client\exception::jsonify($ex);
}
