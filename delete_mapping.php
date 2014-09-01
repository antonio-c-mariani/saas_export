<?php
define('AJAX_SCRIPT', true);
require_once '../../config.php';

$uid = required_param('uid', PARAM_INT);
$id = required_param('id', PARAM_INT);
$action = optional_param('action', 'delete_one', PARAM_TEXT);

switch ($action) {
    case 'delete_one':
        $DB->delete_records('saas_map_course', array('courseid' => $id, 'oferta_disciplina_id' => $uid));
        break;
    case 'delete_many_offers':
        $DB->delete_records('saas_map_course', array('courseid' => $id));
        break;
    case 'delete_many_courses':
        $DB->delete_records('saas_map_course', array('oferta_disciplina_id' => $uid));
        break;
}
