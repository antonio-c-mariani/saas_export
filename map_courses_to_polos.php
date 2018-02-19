<?php

defined('MOODLE_INTERNAL') || die();

require_once('./locallib.php');

$syscontext = saas::get_context_system();
$may_export = has_capability('report/saas_export:export', $syscontext);

$message = '';

if (isset($_POST['map_polos']) && isset($_POST['save']) && $may_export) {
    $sql = "SELECT smcp.instanceid, smcp.id, smcp.polo_id
              FROM {saas_map_catcourses_polos} smcp
              JOIN {saas_polos} pl ON (pl.id = smcp.polo_id AND pl.enable = 1)
              JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = pl.api_key)
             WHERE smcp.type = 'course'";
    $mapped = $DB->get_records_sql($sql);
    $saved = false;
    foreach ($_POST['map_polos'] AS $courseid=>$polo_id) {
        if (isset($mapped[$courseid]) && empty($polo_id)) {
            $DB->delete_records('saas_map_catcourses_polos', array('id'=>$mapped[$courseid]->id));
            $saved = true;
        }
    }
    foreach ($_POST['map_polos'] AS $courseid=>$polo_id) {
        if (isset($mapped[$courseid]) && !empty($polo_id)) {
            if ($polo_id != $mapped[$courseid]->polo_id) {
                $obj = new stdClass();
                $obj->id = $mapped[$courseid]->id;
                $obj->polo_id = $polo_id;
                $DB->update_record('saas_map_catcourses_polos', $obj);
                $saved = true;
            }
        }
    }
    foreach ($_POST['map_polos'] AS $courseid=>$polo_id) {
        if (!isset($mapped[$courseid]) && !empty($polo_id)) {
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

echo saas_print_title(get_string('course_to_polo', 'report_saas_export') . $OUTPUT->help_icon('course_to_polo', 'report_saas_export'));

if ($message) {
    echo $OUTPUT->heading($message, 4, 'saas_export_message');
}

$categories = saas_get_category_tree_map_courses_polos();
$polos = $saas->get_polos_menu();

if (empty($categories)) {
    echo saas_print_alert('Não foram encontrados mapeamentos de cursos Moodle para ofertas de disciplinas');
} else {
    $rows = array();
    saas_mount_category_tree_map_courses_polos($categories, $polos, $rows);

    $table = new html_table();
    $table->head = array(get_string('moodle_courses', 'report_saas_export'),
                         get_string('polos_title', 'report_saas_export'));
    $table->size = array('60%', '40%');
    $table->colclasses = array('leftalign', 'leftalign');
    $table->data = $rows;

    $form = html_writer::start_tag('form', array('method'=>'post', 'action'=>'index.php'));
    $form .=html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'action', 'value'=>'polo_mapping'));
    $form .= html_writer::table($table);
    $form .= html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'save', 'value'=>s(get_string('save', 'admin'))));
    $form .= html_writer::end_tag('form');

    echo $OUTPUT->box($form, 'generalbox saas_area_large');
}
