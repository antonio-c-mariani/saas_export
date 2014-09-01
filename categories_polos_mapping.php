<?php

defined('MOODLE_INTERNAL') || die();

print html_writer::start_tag('DIV', array('align'=>'center'));

if(isset($_POST['map_polos']) && isset($_POST['save'])) {
    $mapped = $DB->get_records('saas_map_categories_polos', null, null, 'polo_id, id, categoryid');
    $saved = false;
    foreach($_POST['map_polos'] AS $poloid=>$categoryid) {
        if(isset($mapped[$poloid]) && empty($categoryid)) {
            $DB->delete_records('saas_map_categories_polos', array('id'=>$mapped[$poloid]->id));
            $saved = true;
        }
    }
    foreach($_POST['map_polos'] AS $poloid=>$categoryid) {
        if(isset($mapped[$poloid]) && !empty($categoryid)) {
            if($categoryid != $mapped[$poloid]->categoryid) {
                $obj = new stdClass();
                $obj->id = $mapped[$poloid]->id;
                $obj->categoryid = $categoryid;
                $DB->update_record('saas_map_categories_polos', $obj);
                $saved = true;
            }
        }
    }
    foreach($_POST['map_polos'] AS $poloid=>$categoryid) {
        if(!isset($mapped[$poloid]) && !empty($categoryid)) {
            $obj = new stdClass();
            $obj->categoryid = $categoryid;
            $obj->polo_id = $poloid;
            $DB->insert_record('saas_map_categories_polos', $obj);
            $saved = true;
        }
    }
    $msg = $saved  ? get_string('saved', 'report_saas_export') : get_string('no_changes', 'report_saas_export');
    print $OUTPUT->heading($msg, 4, 'saas_export_message');
}

$categories = $DB->get_records_menu('course_categories', null, 'name', 'id, name');

$sql = "SELECT sp.id AS sp_id, sp.nome As nome_polo, cpm.categoryid, cc.name as nome_categoria
          FROM {saas_polos} sp
     LEFT JOIN {saas_map_categories_polos} cpm ON (cpm.polo_id = sp.id)
     LEFT JOIN {course_categories} cc ON (cc.id = cpm.categoryid)
         WHERE sp.enable = 1
      ORDER BY sp.nome";
$map = $DB->get_records_sql($sql);
$data = array();
foreach($map AS $m) {
    $catid = empty($m->categoryid) ? 0 : $m->categoryid;
    $select = html_writer::select($categories, "map_polos[{$m->sp_id}]", $catid);
    $data[] = array($m->nome_polo, $select);
}

echo html_writer::start_tag('form', array('method'=>'post', 'action'=>'index.php'));
echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'action', 'value'=>'polo_mapping'));

$table = new html_table();
$table->head  = array(get_string('polo_saas', 'report_saas_export'), get_string('category'));
$table->data = $data;
echo html_writer::table($table);

echo html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'save', 'value'=>s(get_string('save', 'admin'))));
echo html_writer::end_tag('form');

print html_writer::end_tag('DIV');
