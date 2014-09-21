<?php

defined('MOODLE_INTERNAL') || die();

require_once('./locallib.php');
require_once('./classes/saas.php');
$PAGE->requires->js_init_call('M.report_saas_export.init');

$syscontext = saas::get_context_system();
$may_export = has_capability('report/saas_export:export', $syscontext);

$one_to_many = $saas->get_config('course_mapping') == 'one_to_many';
$pocid = optional_param('ocid', -1, PARAM_INT);

// obtem ofertas de curso
$ofertas_cursos = $saas->get_ofertas_curso();
if($pocid === -1) {
    if(!empty($ofertas_cursos)) {
        $oc = reset($ofertas_cursos);
        $pocid = $oc->id;
    }
}

if(empty($pocid)) {
    $cond = '';
    $params = array();
} else {
    $cond = 'AND oc.id = :ocid';
    $params = array('ocid'=>$pocid);
}

$sql = "SELECT oc.id as ocid, od.id as odid, od.group_map_id, od.inicio, od.fim, d.nome
          FROM {saas_ofertas_cursos} oc
          JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
          JOIN {saas_disciplinas} d ON (d.uid = od.disciplina_uid)
         WHERE oc.enable = 1
           {$cond}
      ORDER BY oc.nome, od.group_map_id ,d.nome";
$ofertas = array();
foreach($DB->get_recordset_sql($sql, $params) AS $rec) {
    $ofertas[$rec->ocid][$rec->group_map_id][] = $rec;
}

// obtem mapeamentos
$sql = "SELECT mc.courseid, mc.group_map_id, c.fullname
          FROM {saas_map_course} mc
          JOIN {course} c ON (c.id = mc.courseid)";
$mapping = array();
foreach($DB->get_recordset_sql($sql) AS $rec) {
    $mapping[$rec->group_map_id][] = $rec;
}

print html_writer::start_tag('div', array('class'=>'saas_area_large'));

if(empty($ofertas_cursos)) {
    print $OUTPUT->heading(get_string('no_ofertas_cursos', 'report_saas_export'));
} else {
    $url = new moodle_url('index.php', array('action'=>'course_mapping', 'subaction'=>'ofertas'));
    saas_show_menu_ofertas_cursos($pocid, $url);

    foreach($ofertas AS $ocid=>$maps) {
        $oc = $ofertas_cursos[$ocid];

        $group_options = array(0=>'');
        foreach(array_keys($maps) AS $ind=>$group_map_id) {
            $group_options[$group_map_id] = 'Grupo ' . ($ind+1);
        }
        $group_options[-1] = 'Novo grupo';

        $rows = array();
        $index = 0;
        $color_class = '';
        foreach($maps AS $group_map_id=>$recs) {
            $index++;
            $oc_nome_formatado = "{$oc->nome} ({$oc->ano}/{$oc->periodo})";
            $color_class = $color_class == 'saas_normalcolor' ? 'saas_alternatecolor' : 'saas_normalcolor';

            $od_nome_formatado = '';
            if(count($recs) == 1) {
                $rec = reset($recs);
                $od_nome_formatado =  $rec->nome . ' (' . $saas->format_date($rec->inicio, $rec->fim) . ')';
            } else if(count($recs) > 1) {
                $od_nome_formatado = html_writer::start_tag('UL');
                foreach($recs AS $rec) {
                    $od_nome_formatado .= html_writer::tag('LI', $rec->nome . ' (' . $saas->format_date($rec->inicio, $rec->fim) . ')');
                }
                $od_nome_formatado .= html_writer::end_tag('UL');
            }

            $first = true;
            foreach($recs AS $rec) {
                $row = new html_table_row();
                $row->attributes['class'] = $color_class;
                if($first) {
                    $cell = new html_table_cell();
                    $cell->text = $index . '.';
                    $cell->rowspan = count($recs);
                    $cell->style = "vertical-align: middle;";
                    $cell->attributes['class'] = $color_class;
                    $row->cells[] = $cell;
                }

                if($one_to_many) {
                    $cell = new html_table_cell();
                    if(count($recs) > 1 || !isset($mapping[$group_map_id])) {
                        $local_group_options = $group_options;
                        unset($local_group_options[$group_map_id]);
                        $cell->text = html_writer::select($local_group_options, $rec->odid, 0, false, array('class'=>'select_group_map'));
                    } else {
                        $cell->text = '';
                    }
                    $cell->attributes['class'] = $color_class;
                    $row->cells[] = $cell;
                }

                $cell = new html_table_cell();
                $cell->text = $rec->nome . ' (' . $saas->format_date($rec->inicio, $rec->fim) . ')';
                $cell->style = "vertical-align: middle;";
                $cell->attributes['class'] = $color_class;
                $row->cells[] = $cell;

                if($first) {
                    $cell = new html_table_cell();
                    $cell->rowspan = count($recs);
                    $cell->style = "vertical-align: middle;";
                    $cell->attributes['class'] = $color_class;
                    $cell->text = '';
                    $has_mapping = false;
                    if(isset($mapping[$group_map_id])) {
                        foreach($mapping[$group_map_id] AS $r) {
                            $cell->text .= $r->fullname;
                            $cell->text .= html_writer::tag('input', '', array('class'=>'delete_bt', 'type'=>'image', 'src' =>'img/delete.png',
                                            'alt'=>'Apagar mapeamento', 'height'=>'15', 'width'=>'15', 'group_map_id'=>$group_map_id,
                                            'courseid'=>$r->courseid, 'ocid'=>$ocid, 'style'=>'margin-left:2px;'));
                            $cell->text .= html_writer::empty_tag('br');
                            $has_mapping = true;
                        }
                    }

                    if (!$has_mapping || $saas->get_config('course_mapping') == 'many_to_one') {
                        $cell->text .= html_writer::start_tag('div');
                        $cell->text .= html_writer::tag('button', 'Adicionar', array('type'=>'button', 'id'=>$group_map_id,
                                'class'=>'btn btn-default btn-xs moodle_map_bt',
                                'style'=>'margin-top:5px;', 'od_nome'=>$od_nome_formatado, 'oc_nome'=>$oc_nome_formatado));
                        $cell->text .= html_writer::end_tag('div');
                    }

                    $cell->attributes['class'] = $color_class;
                    $row->cells[] = $cell;
                }

                $rows[] = $row;
                $first = false;
            }

        }

        $table = new html_table();
        $table->head = array();
        if($one_to_many) {
            $table->head = array('Grupo');
            $table->head[] = 'Mover para';
        } else {
            $table->head = array('');
        }
        $table->head[] = 'Oferta de disciplina';
        $table->head[] = 'Curso Moodle';
        $table->colclasses = array('leftalign', 'leftalign', 'leftalign', 'leftalign');
        $table->data = $rows;

        print $OUTPUT->box_start('generalbox');
        print $OUTPUT->heading($oc_nome_formatado);
        $table->tablealign = 'center';
        print html_writer::table($table);
        print $OUTPUT->box_end();

    }
}
print html_writer::end_tag('div');

?>

<div class="saas-styles">

<link rel="stylesheet" href="css/bootstrap.css" type="text/css">

<script src="js/jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="js/bootstrap.min.js"></script>
<script type="text/javascript" src="js/app.js"></script>


  <!-- Modal para Cursos Moodle-->
  <div class="modal bs-example-modal-lg" id="cursos_moodle_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">

        <div class="modal-header">
          <button type="button" class="saas-bt-close close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
          <H4 class="modal_cursos_moodle_title">Seleção de curso Moodle para:</H4>
        </div>

        <div class="modal-body">
          <?php
            saas_build_tree_categories();
          ?>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-default saas-bt-close" data-dismiss="modal">Fechar</button>
        </div>

      </div>
    </div>
  </div>

</div>
