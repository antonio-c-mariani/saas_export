<?php
    define('AJAX_SCRIPT', true);
    require_once '../../config.php';
    require_once($CFG->dirroot . '/report/saas_export/lib.php');

    $uid = required_param('uid', PARAM_INT);
    $id = required_param('id', PARAM_INT);
        
    $saas = new saas();
    
    $DB->delete_records('saas_course_mapping', array('courseid'=>$id, 'oferta_disciplina_id'=>$uid));    
?>