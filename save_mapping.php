<?php
define('AJAX_SCRIPT', true);
require_once '../../config.php';

$group_map_id = required_param('group_map_id', PARAM_INT);
$ods = $DB->get_records('saas_ofertas_disciplinas', array('group_map_id'=>$group_map_id));

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', array('id'=>$courseid), 'id, category');

if(empty($ods) || empty($course)) {
    return false;
}
$SESSION->last_categoryid = $course->category;

if(!$DB->record_exists('saas_map_course', array('courseid'=>$courseid, 'group_map_id'=>$group_map_id))) {
    $record = new stdClass();
    $record->courseid = $courseid;
    $record->group_map_id = $group_map_id;
    $DB->insert_record('saas_map_course', $record);
}

return true;
