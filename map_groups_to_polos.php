<?php

defined('MOODLE_INTERNAL') || die();

$syscontext = saas::get_context_system();
$can_export = has_capability('report/saas_export:export', $syscontext);

$message = '';

if (isset($_POST['map_polos']) && isset($_POST['save']) && $can_export) {
    $saved = false;

    foreach ($_POST['map_polos'] AS $groupname=>$poloid) {
        if ($map_group_polo = $DB->get_record('saas_map_groups_polos', array('api_key'=>$saas->api_key(), 'groupname'=>$groupname), 'id, polo_id')) {
            if (empty($poloid)) {
                $DB->delete_records('saas_map_groups_polos', array('id'=>$map_group_polo->id));
                $saved = true;
            } else {
                if ($poloid != $map_group_polo->polo_id) {
                    $obj = new stdClass();
                    $obj->id = $map_group_polo->id;
                    $obj->polo_id = $poloid;
                    $DB->update_record('saas_map_groups_polos', $obj);
                    $saved = true;
                }
            }
        } else {
            if (!empty($poloid)) {
                $obj = new stdClass();
                $obj->api_key = $saas->api_key();
                $obj->groupname = $groupname;
                $obj->polo_id = $poloid;
                $DB->insert_record('saas_map_groups_polos', $obj);
                $saved = true;
            }
        }
    }

    $message = $saved  ? get_string('saved', 'report_saas_export') : get_string('no_changes', 'report_saas_export');
}

// Grupos com mesmo nome de polo e que ainda não estejam mapeados
$sql = "SELECT DISTINCT g.name, pl.id
          FROM {saas_ofertas_disciplinas} od
          JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = od.api_key)
          JOIN {saas_map_course} smc ON (smc.group_map_id = od.group_map_id)
          JOIN {course} c ON (c.id = smc.courseid)
          JOIN {groups} g ON (g.courseid = c.id)
          JOIN {saas_polos} pl ON (pl.nome = g.name AND pl.enable = 1 and pl.api_key = od.api_key)
     LEFT JOIN {saas_polos} pl2 ON (pl2.enable = 1 AND pl2.nome = pl.nome AND pl2.id != pl.id AND pl2.api_key = od.api_key)
         WHERE od.enable = 1
           AND pl2.id IS NULL";
$candidate_group_names = $DB->get_records_sql_menu($sql);

$polos = $saas->get_polos_menu();
$polos = array(-1=>'-- este grupo não corresponde a um polo --') + $polos;

$sql = "SELECT DISTINCT g.name as groupname, spm.polo_id
          FROM {saas_ofertas_disciplinas} od
          JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = od.api_key)
          JOIN {saas_map_course} smc ON (smc.group_map_id = od.group_map_id)
          JOIN {course} c ON (c.id = smc.courseid)
          JOIN {groups} g ON (g.courseid = c.id)
     LEFT JOIN {saas_map_groups_polos} spm ON (spm.groupname = g.name AND spm.api_key = od.api_key)
         WHERE od.enable = 1
      ORDER BY g.name";
$map = $DB->get_records_sql($sql);

echo saas_print_title(get_string('group_to_polo', 'report_saas_export') . $OUTPUT->help_icon('group_to_polo', 'report_saas_export'));

if ($message) {
    echo $OUTPUT->heading($message, 5, 'saas_export_message');
}

if (empty($map)) {
    echo saas_print_alert('Não há polos a serem mapeados. É necessário inicialmente mapear as disciplinas.');
} else {
    $tipos = array(0 => 'Grupos novos (ainda não mapeados)',
                   1 => 'Grupos corresponentes a polos SAAS',
                   2 => 'Grupos que não correspondem a polos SAAS');
    $html = '';
    foreach ($tipos AS $tipo => $title) {
        $rows = array();
        $index = 0;
        foreach ($map AS $groupname=>$m) {
            $poloid = empty($m->polo_id) ? 0 : $m->polo_id;
            if ($tipo == 0 && $poloid == 0 || $tipo == 1 && $poloid > 0 || $tipo == 2 && $poloid == -1) {
                $index++;
                $polo_name = empty($m->saas_polo_nome) ? '' : $m->saas_polo_nome;

                $row = new html_table_row();

                $cell = new html_table_cell();
                $cell->text = $index . '.';
                $row->cells[] = $cell;

                $cell = new html_table_cell();
                if (empty($m->polo_id)) {
                    $cell->text = html_writer::tag('span', $m->groupname, array('style'=>'color:red'));
                } else {
                    $cell->text = $m->groupname;
                }
                $row->cells[] = $cell;

                if ($poloid == 0) {
                   $poloid = isset($candidate_group_names[$m->groupname]) ? $candidate_group_names[$m->groupname] : -1;
                }

                $cell = new html_table_cell();
                $cell->text = $can_export ? html_writer::select($polos, "map_polos[{$groupname}]", $poloid) : $polo_name;
                $row->cells[] = $cell;

                $rows[] = $row;
            }
        }

        if (!empty($rows)) {
            $table = new html_table();
            $table->head  = array('', get_string('moodle_group', 'report_saas_export'), get_string('polo_saas', 'report_saas_export'));
            $table->colclasses = array('leftalign', 'leftalign', 'centeralign');
            $table->data = $rows;
            $table->attributes = array('class'=>'saas_table');

            $str_title = saas_print_heading($title, 5);
            $str_table = html_writer::table($table);
            $html .= $OUTPUT->box($str_title  . $str_table, 'generalbox saas_area_large');
        }
    }

    if ($can_export) {
        $html .= $OUTPUT->box(html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'save', 'value'=>s(get_string('save', 'admin')))), 'saas_area_large');
    }

    $form = html_writer::start_tag('form', array('method'=>'post', 'action'=>'index.php'));
    $form .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'action', 'value'=>'polo_mapping'));
    $form .= $html;
    $form .= html_writer::end_tag('form');
    echo $OUTPUT->box($form, '');
}
