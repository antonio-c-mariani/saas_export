<?php

defined('MOODLE_INTERNAL') || die();

require_once('./locallib.php');

$syscontext = saas::get_context_system();
$may_export = has_capability('report/saas_export:export', $syscontext);

$message = '';

if(isset($_POST['map_polos']) && isset($_POST['save']) && $may_export) {
    $sql = "SELECT smcp.instanceid, smcp.id, smcp.polo_id
              FROM {saas_map_catcourses_polos} smcp
              JOIN {saas_polos} pl ON (pl.id = smcp.polo_id)
              JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = pl.api_key)
             WHERE smcp.type = 'course'";
    $mapped = $DB->get_records_sql($sql);
    $saved = false;
    foreach($_POST['map_polos'] AS $courseid=>$polo_id) {
        if(isset($mapped[$courseid]) && empty($polo_id)) {
            $DB->delete_records('saas_map_catcourses_polos', array('id'=>$mapped[$courseid]->id));
            $saved = true;
        }
    }
    foreach($_POST['map_polos'] AS $courseid=>$polo_id) {
        if(isset($mapped[$courseid]) && !empty($polo_id)) {
            if($polo_id != $mapped[$courseid]->polo_id) {
                $obj = new stdClass();
                $obj->id = $mapped[$courseid]->id;
                $obj->polo_id = $polo_id;
                $DB->update_record('saas_map_catcourses_polos', $obj);
                $saved = true;
            }
        }
    }
    foreach($_POST['map_polos'] AS $courseid=>$polo_id) {
        if(!isset($mapped[$courseid]) && !empty($polo_id)) {
            $obj = new stdClass();
            $obj->type = 'course';
            $obj->instanceid = $courseid;
            $obj->polo_id = $polo_id;
            $DB->insert_record('saas_map_catcourses_polos', $obj);
            $saved = true;
        }
    }
    $message = $saved  ? get_string('saved', 'report_saas_export') : get_string('no_changes', 'report_saas_export');
}

saas_show_nome_instituicao();
print html_writer::start_tag('DIV', array('align'=>'center'));
print $OUTPUT->heading(get_string('course_to_polo', 'report_saas_export') .
      $OUTPUT->help_icon('course_to_polo', 'report_saas_export'), 3);
print html_writer::end_tag('DIV');

print html_writer::start_tag('div', array('class'=>'saas_area_large'));
if($message) {
    print $OUTPUT->heading($message, 4, 'saas_export_message');
}

$categories = saas_get_category_tree_map_courses_polos();
$polos = $saas->get_polos_menu();

if(empty($categories)) {
    print $OUTPUT->heading('Não foram encontrados mapeamentos de cursos Moodle para ofertas de disciplinas', 4);
} else {
    $rows = array();
    saas_mount_category_tree_map_courses_polos($categories, $polos, $rows);

    $table = new html_table();
    $table->head = array(get_string('moodle_courses', 'report_saas_export'),
                         get_string('polos_title', 'report_saas_export'));
    $table->size = array('60%', '40%');
    $table->colclasses = array('leftalign', 'leftalign');
    $table->data = $rows;

    print html_writer::start_tag('form', array('method'=>'post', 'action'=>'index.php'));
    print html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'action', 'value'=>'polo_mapping'));

    print $OUTPUT->box_start('generalbox');
    $table->tablealign = 'center';
    print html_writer::table($table);
    print $OUTPUT->box_end();

    print html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'save', 'value'=>s(get_string('save', 'admin'))));
    print html_writer::end_tag('form');
}
print html_writer::end_tag('DIV');
