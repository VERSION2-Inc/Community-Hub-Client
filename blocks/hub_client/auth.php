<?php // $Id: auth.php 152 2012-12-02 07:04:43Z malu $

require_once __DIR__.'/../../config.php';
require_once __DIR__.'/classes/controller.php';

try {
    require_sesskey();
    $serverid = required_param('server', PARAM_INT);
    $username = optional_param('username', '', PARAM_TEXT);
    $password = optional_param('password', '', PARAM_TEXT);

    $controller = new hub_client\controller();
    try {
        $controller->auth($serverid, $username, $password);
        echo json_encode(array('succeeded' => true));
    } catch (hub_client\exception $ex) {
        echo json_encode(array('succeeded' => null));
    }

} catch (Exception $ex) {
    header('HTTP/1.1 400 Bad Request');
    echo hub_client\exception::jsonify($ex);
}
