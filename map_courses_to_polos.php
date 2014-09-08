<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/report/saas_export/locallib.php');

$syscontext = context_system::instance();
$may_export = has_capability('report/saas_export:export', $syscontext);

$message = '';

if(isset($_POST['map_polos']) && isset($_POST['save']) && $may_export) {
    $mapped = $DB->get_records('saas_map_catcourses_polos', array('type'=>'course'), null, 'instanceid, id, polo_id');
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

print html_writer::start_tag('DIV', array('align'=>'center'));
print $OUTPUT->heading(get_string('course_to_polo', 'report_saas_export'));
print $OUTPUT->box_start('generalbox boxwidthwide');
print html_writer::tag('P', get_string('course_to_polo_msg1', 'report_saas_export'), array('class'=>'saas_justifiedalign'));
print html_writer::tag('P', get_string('course_to_polo_msg2', 'report_saas_export'), array('class'=>'saas_justifiedalign'));
print $OUTPUT->box_end();
print html_writer::end_tag('DIV');

if($message) {
    print $OUTPUT->heading($message, 4, 'saas_export_message');
}

$categories = saas_get_category_tree_map_courses_polos();
$polos = saas_get_polos_menu();

print html_writer::start_tag('DIV', array('class'=>'saas_category_tree'));
if(empty($categories)) {
    print $OUTPUT->heading('Não foram encontrados mapeamentos de cursos Moodle para ofertas de disciplinas');
} else {
    print html_writer::start_tag('DIV');
    print html_writer::tag('DIV', get_string('moodle_courses', 'report_saas_export'), array('class'=>'lefttitle'));
    print html_writer::tag('DIV', get_string('polos_title', 'report_saas_export'), array('class'=>'righttitle'));
    print html_writer::end_tag('DIV');

    print html_writer::start_tag('form', array('method'=>'post', 'action'=>'index.php'));
    print html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'action', 'value'=>'polo_mapping'));

    print html_writer::start_tag('UL');
    saas_show_category_tree_map_courses_polos($categories, $polos);
    print html_writer::end_tag('UL');

    print html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'save', 'value'=>s(get_string('save', 'admin'))));
    print html_writer::end_tag('form');
}
print html_writer::end_tag('DIV');
