<?php // $Id: servers.php 152 2012-12-02 07:04:43Z malu $

require_once __DIR__.'/../../../config.php';

if (false) {
    $CFG    = new stdClass;
    $SITE   = new stdClass;
    $DB     = new mysqli_native_moodle_database;
    $OUTPUT = new core_renderer;
    $PAGE   = new moodle_page;
}

require_once $CFG->libdir.'/adminlib.php';
require_once $CFG->libdir.'/tablelib.php';

const TEXTBOX_URL_SIZE = 50;

require_login(null, false); // no guest autologin

$updateid = optional_param('id', 0, PARAM_INT);
$wwwroot  = optional_param('url', '', PARAM_TEXT);
$deleteid = optional_param('delete', 0, PARAM_INT);

$section = 'blocksetting' . 'hub_client';
$baseurl = new moodle_url('/blocks/hub_client/admin/servers.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/admin/settings.php', array('section' => $section));
$PAGE->set_pagetype('admin-setting-' . $section);
$PAGE->set_pagelayout('admin');

$adminroot = admin_get_root();
$settingspage = $adminroot->locate($section, true);
if (empty($settingspage) || !($settingspage instanceof admin_settingpage)) {
    //print_error('sectionerror', 'admin', "$CFG->wwwroot/$CFG->admin/");
    print_error('accessdenied', 'admin');
}
if (!$settingspage->check_access()) {
    print_error('accessdenied', 'admin');
}

$pathtosection = array_reverse($settingspage->visiblepath);
$strtitle = get_string('settings/servers', 'block_hub_client');
$PAGE->set_title($SITE->shortname . ': ' . implode(': ', $pathtosection) . ': ' . $strtitle);
$PAGE->set_heading($SITE->fullname);
$PAGE->navbar->add($strtitle, $baseurl);

if ($wwwroot && confirm_sesskey()) try {
    require_once __DIR__.'/../classes/client.php';
    $client = new hub_client\client($wwwroot);
    $server = (object)array(
        'wwwroot' => $client->wwwroot,
        'title' => $client->get_title(),
        );
    if ($updateid) {
        $server->id = $updateid;
        $server->timemodified = time();
        $DB->update_record('block_hub_client_servers', $server);
        redirect($baseurl);
    }
    if (!$DB->record_exists('block_hub_client_servers', array('wwwroot' => $wwwroot, 'deleted' => 0))) {
        $server->timecreated = time();
        $server->timemodified = time();
        $DB->insert_record('block_hub_client_servers', $server);
        redirect($baseurl);
    }
    // already registered
    $wwwroot = '';
} catch (Exception $ex) {
    // Error: show an error message in the form cell
    unset($ex);
}

if ($deleteid && confirm_sesskey()) {
    $server = $DB->get_record('block_hub_client_servers', array('id' => $deleteid, 'deleted' => 0));
    if ($server) {
        $server->deleted = 1;
        $server->timemodified = time();
        $DB->update_record('block_hub_client_servers', $server);
        redirect($baseurl);
    }
}

function html_input_tag($type, $name, $value, array $attributes = array())
{
    return html_writer::empty_tag('input',
        array('type' => $type, 'name' => $name, 'value' => $value) + $attributes);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($strtitle);
echo html_writer::start_tag('form', array('action' => $baseurl, 'method' => 'post'));

$servers = $DB->get_records('block_hub_client_servers', array('deleted' => 0));

$strsitename = get_string('sitename', 'block_hub_client');
$strnotfound = get_string('error:notfound', 'block_hub_client');
$tagsesskey = html_input_tag('hidden', 'sesskey', sesskey());
$table = new flexible_table('block_hub_client-servers');
$table->define_baseurl($baseurl);
$table->define_columns(array('title', 'wwwroot', 'actions'));
$table->define_headers(array($strsitename, get_string('serverurl', 'block_hub_client'), get_string('actions')));
$table->column_class('actions', 'actions');
$table->setup();
foreach ($servers as $server) {
    if ($server->id == $updateid) {
        $title = $server->title;
        if (empty($wwwroot)) {
            $wwwroot = $server->wwwroot;
        } else {
            $title = html_writer::tag('span', $strnotfound, array('class' => 'error'));
        }
        $hidden = html_writer::tag('span',
            $tagsesskey . html_input_tag('hidden', 'id', $server->id),
            array('style' => 'display:none;'));
        $input = html_input_tag('text', 'url', $wwwroot, array('size' => TEXTBOX_URL_SIZE));
        $submit = html_input_tag('submit', 'update', get_string('update'));
        $cancel = html_writer::link($baseurl, get_string('cancel'));
        $table->add_data(array($title, $hidden . $input, $submit . ' ' . $cancel));
    } else {
        $edit = $OUTPUT->action_icon(
            new moodle_url($baseurl, array('id' => $server->id)),
            new pix_icon('t/edit', get_string('edit'))
            );
        $delete = $OUTPUT->action_icon(
            new moodle_url($baseurl, array('delete' => $server->id, 'sesskey' => sesskey())),
            new pix_icon('t/delete', get_string('delete')),
            new confirm_action(get_string('confirm:deleteserver', 'block_hub_client'))
            );
        $table->add_data(array($server->title, $server->wwwroot, $edit . ' ' . $delete));
    }
}
if (!$updateid) {
    $title = '';
    if (!empty($wwwroot)) {
        $title = html_writer::tag('span', $strnotfound, array('class' => 'error'));
    }
    $hidden = html_writer::tag('span', $tagsesskey, array('style' => 'display:none;'));
    $input = html_input_tag('text', 'url', $wwwroot, array('size' => TEXTBOX_URL_SIZE));
    $submit = html_input_tag('submit', 'add', get_string('add'));
    $table->add_data(array($title, $hidden . $input, $submit));
}
$table->print_html();

echo html_writer::end_tag('form');
echo $OUTPUT->footer();
