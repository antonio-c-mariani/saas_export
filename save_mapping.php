<?php
    define('AJAX_SCRIPT', true);
    require_once '../../config.php';

    $uid = required_param('uid', PARAM_INT);
    $id = required_param('id', PARAM_INT);
    $mapping_type = required_param('mapping_type', PARAM_TEXT);
    
    switch ($mapping_type) {
      	case 'one_to_one':
    		if ($record = $DB->get_record('saas_course_mapping', array('oferta_disciplina_id' => $uid))) {
                $record->courseid = $id;
                $DB->update_record('saas_course_mapping', $record);
    		} else {
    		   $record = new stdClass();
 			   $record->courseid = $id;
 			   $record->oferta_disciplina_id = $uid;
 			   $DB->insert_record('saas_course_mapping', $record);
    		}

    		break;
    	case 'many_to_one':
		   $record = new stdClass();
		   $record->courseid = $id;
		   $record->oferta_disciplina_id = $uid;
		   $DB->insert_record('saas_course_mapping', $record);
    	   break;
    }
?>