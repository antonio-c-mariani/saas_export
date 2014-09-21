<?php

defined('MOODLE_INTERNAL') || die();

require_once('./locallib.php');

$syscontext = saas::get_context_system();
$may_export = has_capability('report/saas_export:export', $syscontext);

$message = '';
$errors = array();

if(isset($_POST['map_polos']) && isset($_POST['save']) && $may_export) {
    $sql = "SELECT smcp.instanceid, smcp.id, smcp.polo_id, cc.path
              FROM {saas_map_catcourses_polos} smcp
              JOIN {course_categories} cc ON (cc.id = smcp.instanceid)
             WHERE smcp.type = 'category'";
    $mapped = $DB->get_records_sql($sql);
    $saved = false;
    foreach($_POST['map_polos'] AS $categoryid=>$polo_id) {
        if(isset($mapped[$categoryid]) && empty($polo_id)) {
            $DB->delete_records('saas_map_catcourses_polos', array('id'=>$mapped[$categoryid]->id));
            unset($mapped[$categoryid]);
            $saved = true;
        }
    }
    foreach($_POST['map_polos'] AS $categoryid=>$polo_id) {
        if(isset($mapped[$categoryid]) && !empty($polo_id)) {
            if($polo_id != $mapped[$categoryid]->polo_id) {
                $obj = new stdClass();
                $obj->id = $mapped[$categoryid]->id;
                $obj->polo_id = $polo_id;
                $DB->update_record('saas_map_catcourses_polos', $obj);
                $mapped[$categoryid] = $polo_id;
                $saved = true;
            }
        }
    }
    foreach($_POST['map_polos'] AS $categoryid=>$polo_id) {
        if(!isset($mapped[$categoryid]) && !empty($polo_id)) {
            $sql = "SELECT cc.*
                      FROM {course_categories} cc
                      JOIN {saas_map_catcourses_polos} smcp ON (smcp.instanceid = cc.id AND smcp.type = 'category')
                     WHERE cc.path LIKE '%/{$categoryid}/%'
                     UNION
                    SELECT ccp.*
                      FROM {course_categories} cc
                      JOIN {course_categories} ccp ON (ccp.id = cc.id OR cc.path LIKE CONCAT('%/', ccp.id, '/%'))
                      JOIN {saas_map_catcourses_polos} smcp ON (smcp.instanceid = ccp.id AND smcp.type = 'category')
                     WHERE cc.id = {$categoryid}";
            $cats = $DB->get_records_sql($sql);
            if(empty($cats)) {
                $obj = new stdClass();
                $obj->type = 'category';
                $obj->instanceid = $categoryid;
                $obj->polo_id = $polo_id;
                $DB->insert_record('saas_map_catcourses_polos', $obj);
                $saved = true;
            } else {
                $conflito = reset($cats);
                $cat = $DB->get_record('course_categories', array('id'=>$categoryid));
                $polo = $DB->get_record('saas_polos', array('id'=>$polo_id));
                $errors[] = "Polo '{$polo->nome}' não pode ser associado à categoria: '{$cat->name}' pois já há polo associado à categoria: '{$conflito->name}'.";
            }
        }
    }
    $message = $saved  ? get_string('saved', 'report_saas_export') : get_string('no_changes', 'report_saas_export');
}

print html_writer::start_tag('div', array('align'=>'center'));
print $OUTPUT->heading(get_string('category_to_polo', 'report_saas_export'), 3);
print $OUTPUT->box_start('generalbox boxwidthwide');
print html_writer::tag('P', get_string('category_to_polo_msg1', 'report_saas_export'), array('class'=>'saas_justifiedalign'));
print html_writer::tag('P', get_string('category_to_polo_msg2', 'report_saas_export'), array('class'=>'saas_justifiedalign'));
print $OUTPUT->box_end();
print html_writer::end_tag('div');

print html_writer::start_tag('div', array('class'=>'saas_area_large'));

if(!empty($errors)) {
    print $OUTPUT->box_start('generalbox boxwidthwide');
    print html_writer::start_tag('ul');
    foreach($errors AS $err) {
        print html_writer::tag('LI', $err, array('class'=>'saas_export_error'));
    }
    print html_writer::end_tag('ul');
    print $OUTPUT->box_end();
} else if($message) {
    print $OUTPUT->heading($message, 4, 'saas_export_message');
}

$categories = saas_get_category_tree_map_categories_polos();
$polos = $saas->get_polos_menu();

if(empty($categories)) {
    print $OUTPUT->heading('Não foram encontrados mapeamentos de cursos Moodle para ofertas de disciplinas', 3);
} else {
    $rows = array();
    saas_mount_category_tree_map_categories_polos($categories, $polos, $rows);

    $table = new html_table();
    $table->head = array(get_string('moodle_categories', 'report_saas_export'),
                         get_string('polos_title', 'report_saas_export'));
    $table->colclasses = array('leftalign', 'leftalign');
    $table->size = array('60%', '40%');
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
print html_writer::end_tag('div');
