<?php
define('AJAX_SCRIPT', true);
require_once '../../config.php';

$group_map_id = required_param('group_map_id', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'delete_one', PARAM_TEXT);

switch ($action) {
    case 'delete_one':
        $DB->delete_records('saas_map_course', array('courseid' => $courseid, 'group_map_id' => $group_map_id));
        break;
    case 'delete_many_offers':
        $DB->delete_records('saas_map_course', array('courseid' => $courseid));
        break;
    case 'delete_many_courses':
        $DB->delete_records('saas_map_course', array('group_map_id' => $group_map_id));
        break;
}
