<?php

defined('MOODLE_INTERNAL') || die();

$syscontext = saas::get_context_system();
$may_export = has_capability('report/saas_export:export', $syscontext);

print html_writer::start_tag('DIV', array('align'=>'center'));
print $OUTPUT->heading(get_string('group_to_polo', 'report_saas_export'));
print $OUTPUT->box_start('generalbox boxwidthwide');
print html_writer::tag('P', get_string('group_to_polo_msg1', 'report_saas_export'), array('class'=>'saas_justifiedalign'));
print html_writer::tag('P', get_string('group_to_polo_msg2', 'report_saas_export'), array('class'=>'saas_justifiedalign'));
print $OUTPUT->box_end();
print html_writer::end_tag('DIV');

print html_writer::start_tag('div', array('class'=>'saas_category_tree'));

if(isset($_POST['map_polos']) && isset($_POST['save']) && $may_export) {
    $mapped_groups = $DB->get_records('saas_map_groups_polos', null, 'groupname', 'groupname, id, polo_id');
    $saved = false;
    foreach($_POST['map_polos'] AS $groupname=>$poloid) {
        $decoded_groupname = urldecode($groupname);
        if(isset($mapped_groups[$decoded_groupname])) {
            if(empty($poloid)) {
                $DB->delete_records('saas_map_groups_polos', array('id'=>$mapped_groups[$decoded_groupname]->id));
                $saved = true;
            } else {
                if($poloid != $mapped_groups[$decoded_groupname]->polo_id) {
                    $obj = new stdClass();
                    $obj->id = $mapped_groups[$decoded_groupname]->id;
                    $obj->polo_id = $poloid;
                    $DB->update_record('saas_map_groups_polos', $obj);
                    $saved = true;
                }
            }
        } else {
            if(!empty($poloid)) {
                $obj = new stdClass();
                $obj->groupname = $decoded_groupname;
                $obj->polo_id = $poloid;
                $DB->insert_record('saas_map_groups_polos', $obj);
                $saved = true;
            }
        }
    }
    $msg = $saved  ? get_string('saved', 'report_saas_export') : get_string('no_changes', 'report_saas_export');
    print $OUTPUT->heading($msg, 4, 'saas_export_message');
}


$sql = "SELECT DISTINCT g.name, pl.id
          FROM {saas_map_course} scm
          JOIN {course} c ON (c.id = scm.courseid)
          JOIN {groups} g ON (g.courseid = c.id)
          JOIN {saas_polos} pl ON (pl.nome = g.name AND pl.enable = 1)
     LEFT JOIN {saas_polos} pl2 ON (pl2.enable = 1 AND pl2.nome = pl.nome AND pl2.id != pl.id)
         WHERE ISNULL(pl2.id)";
$group_names = $DB->get_records_sql_menu($sql);

$polos = $DB->get_records_menu('saas_polos', array('enable'=>1), 'nome', 'id, nome');
$polos = array(-1=>'-- este grupo não corresponde a um polo --') + $polos;

$sql = "SELECT DISTINCT g.name as groupname, spm.polo_id
          FROM {saas_map_course} scm
          JOIN {course} c ON (c.id = scm.courseid)
          JOIN {saas_ofertas_disciplinas} sod ON (sod.group_map_id = scm.group_map_id AND sod.enable = 1)
          JOIN {groups} g ON (g.courseid = c.id)
     LEFT JOIN {saas_map_groups_polos} spm ON (spm.groupname = g.name)
      ORDER BY g.name";
$map = $DB->get_records_sql($sql);

print html_writer::start_tag('form', array('method'=>'post', 'action'=>'index.php'));
print html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'action', 'value'=>'polo_mapping'));

foreach(array(1, -1) AS $tipo) {
    $data = array();
    $color_class = '';
    foreach($map AS $groupname=>$m) {
        $poloid = empty($m->polo_id) ? 0 : $m->polo_id;
        if($tipo == 1 && $poloid >= 0 || $tipo == -1 && $poloid == -1) {
            $color_class = $color_class == 'saas_normalcolor' ? 'saas_alternatecolor' : 'saas_normalcolor';
            $encoded_groupname = urlencode($groupname);
            $polo_name = empty($m->saas_polo_nome) ? '' : $m->saas_polo_nome;

            $row = new html_table_row();

            $cell = new html_table_cell();

            if(empty($m->polo_id)) {
                $cell->text = html_writer::tag('span', $m->groupname, array('style'=>'color:red'));
            } else {
                $cell->text = $m->groupname;
            }
            $cell->attributes['class'] = $color_class;
            $row->cells[] = $cell;

            if($poloid == 0) {
               $poloid = isset($group_names[$m->groupname]) ? $group_names[$m->groupname] : -1;
            }

            $cell = new html_table_cell();
            $cell->text = $may_export ? html_writer::select($polos, "map_polos[{$encoded_groupname}]", $poloid) : $polo_name;
            $cell->attributes['class'] = $color_class;
            $row->cells[] = $cell;

            $data[] = $row;
        }
    }

    $title = $tipo == 1 ? 'Grupos corresponentes a polos SAAS' : 'Grupos que não correspondem a polos SAAS';
    print html_writer::tag('h4', $title);

    $table = new html_table();
    $table->head  = array(get_string('moodle_group', 'report_saas_export'), get_string('polo_saas', 'report_saas_export'));
    $table->colclasses = array('leftalign', 'leftalign');
    $table->size = array('60%', '40%');
    $table->data = $data;
    print html_writer::table($table);
}

if($may_export) {
    print html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'save', 'value'=>s(get_string('save', 'admin'))));
}
print html_writer::end_tag('form');

print html_writer::end_tag('DIV');
