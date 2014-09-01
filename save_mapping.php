<?php
    define('AJAX_SCRIPT', true);
    require_once '../../config.php';
    require_once($CFG->dirroot . '/report/saas_export/classes/saas.php');

    $uid = required_param('uid', PARAM_TEXT);
    $id = required_param('id', PARAM_TEXT);

    $saas = new saas();
    $mapping_type = $saas->config->course_mapping;

    switch ($mapping_type) {
        case 'one_to_one':
            if ($record = $DB->get_record('saas_map_course', array('oferta_disciplina_id' => $uid))) {
                $record->courseid = $id;
                $DB->update_record('saas_map_course', $record);
            } else {
                $record = new stdClass();
                $record->courseid = $id;
                $record->oferta_disciplina_id = $uid;
                $DB->insert_record('saas_map_course', $record);
            }

            break;
        case 'many_to_one':
            $record = new stdClass();
            $record->courseid = $id;
            $record->oferta_disciplina_id = $uid;
            $DB->insert_record('saas_map_course', $record);
            break;
        case 'one_to_many':
            if ($uid == -1) {
                $record = new stdClass();
                $record->courseid = $id;
                $record->oferta_disciplina_id = $uid;
                $DB->insert_record('saas_map_course', $record);
            } else {
                if ($record = $DB->get_record('saas_map_course', array('courseid'=>$id, 'oferta_disciplina_id'=>-1))) {
                    $record->oferta_disciplina_id = $uid;
                    $DB->update_record('saas_map_course', $record);
                } else {
                    $record = new stdClass();
                    $record->courseid = $id;
                    $record->oferta_disciplina_id = $uid;
                    $DB->insert_record('saas_map_course', $record);
                }
            }
           break;
    }
?>
