<?php

defined('MOODLE_INTERNAL') || die();

$syscontext = context_system::instance();
$may_export = has_capability('report/saas_export:export', $syscontext);

print html_writer::start_tag('DIV', array('align'=>'center'));
print $OUTPUT->heading(get_string('category_to_polo', 'report_saas_export'));
print $OUTPUT->box_start('generalbox boxwidthwide');
print html_writer::tag('P', get_string('category_to_polo_msg1', 'report_saas_export'), array('class'=>'justifiedalign'));
print html_writer::tag('P', get_string('category_to_polo_msg2', 'report_saas_export'), array('class'=>'justifiedalign'));
print $OUTPUT->box_end();

if(isset($_POST['map_polos']) && isset($_POST['save']) && $may_export) {
    $mapped = $DB->get_records('saas_map_catcourses_polos', array('type'=>'category'), null, 'polo_id, id, instanceid');
    $saved = false;
    foreach($_POST['map_polos'] AS $poloid=>$categoryid) {
        if(isset($mapped[$poloid]) && empty($categoryid)) {
            $DB->delete_records('saas_map_catcourses_polos', array('id'=>$mapped[$poloid]->id));
            $saved = true;
        }
    }
    foreach($_POST['map_polos'] AS $poloid=>$categoryid) {
        if(isset($mapped[$poloid]) && !empty($categoryid)) {
            if($categoryid != $mapped[$poloid]->instanceid) {
                $obj = new stdClass();
                $obj->id = $mapped[$poloid]->id;
                $obj->instanceid = $categoryid;
                $DB->update_record('saas_map_catcourses_polos', $obj);
                $saved = true;
            }
        }
    }
    foreach($_POST['map_polos'] AS $poloid=>$categoryid) {
        if(!isset($mapped[$poloid]) && !empty($categoryid)) {
            $obj = new stdClass();
            $obj->type = 'category';
            $obj->instanceid = $categoryid;
            $obj->polo_id = $poloid;
            $DB->insert_record('saas_map_catcourses_polos', $obj);
            $saved = true;
        }
    }
    $msg = $saved  ? get_string('saved', 'report_saas_export') : get_string('no_changes', 'report_saas_export');
    print $OUTPUT->heading($msg, 4, 'saas_export_message');
}

$categories = $DB->get_records_menu('course_categories', null, 'name', 'id, name');

$sql = "SELECT sp.id AS sp_id, sp.nome As nome_polo, cpm.instanceid AS categoryid, cc.name as nome_categoria
          FROM {saas_polos} sp
     LEFT JOIN {saas_map_catcourses_polos} cpm ON (cpm.polo_id = sp.id AND cpm.type = 'category')
     LEFT JOIN {course_categories} cc ON (cc.id = cpm.instanceid)
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
