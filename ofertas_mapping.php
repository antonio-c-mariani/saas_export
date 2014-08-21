<?php
  defined('MOODLE_INTERNAL') || die();
  require_once($CFG->dirroot . '/report/saas_export/locallib.php');
  require_once($CFG->dirroot . '/report/saas_export/classes/saas.php');
?>
<div class="saas-styles">

<link rel="stylesheet" href="css/bootstrap.css" type="text/css">

<script src="js/jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="js/bootstrap.min.js"></script>
<script type="text/javascript" src="js/app.js"></script>

<table class="table table-hover">

<th>SAAS</th>
<th>MOODLE</th>

<?php
  $saas = new saas();
  $mapeamentos = $saas->get_mapeamento_cursos();
  $courses = $DB->get_records_menu('course', null, null, 'id, fullname');

  //Mapeamento de um curso moodle para uma ou mais ofertas do SAAS.
  if ($saas->config->course_mapping == 'one_to_many') {

    $ofertas = $DB->get_records_menu('saas_ofertas_disciplinas', null, null, 'id, nome');
    $cursos_moodle_com_ofertas = array();

    foreach ($mapeamentos as $key => $map) {
        $cursos_moodle_com_ofertas[$map->courseid] = $DB->get_records('saas_course_mapping',
                                   array('courseid'=>$map->courseid), null, 'oferta_disciplina_id');
    }

    foreach ($cursos_moodle_com_ofertas as $courseid => $ofertas_ids) {
      echo html_writer::start_tag('tr');

        echo html_writer::start_tag('td');

          foreach ($ofertas_ids as $ofertaid) {
              if ($ofertaid->oferta_disciplina_id != -1) {
                  echo html_writer::start_tag('div', array('id'=>$courseid . '-' . $ofertaid->oferta_disciplina_id));
                    echo html_writer::tag('div', $ofertas[$ofertaid->oferta_disciplina_id],
                                    array('id' => $ofertaid->oferta_disciplina_id, 'style' => 'float:left;'));
                    echo html_writer::tag('input', '', array('class'=>'delete_bt', 'type'=>'image', 'src' =>'img/delete.png', 'alt'=>'Apagar mapeamento', 'height'=>'15', 'width'=>'15', 'uid'=>$ofertaid->oferta_disciplina_id, 'id'=>$courseid,
                    'style'=>'margin-left:2px;'));
                  echo html_writer::end_tag('div');

              }
          }

        echo html_writer::tag('button', 'Adicionar', array('type'=>'button', 'id' =>$courseid,
                              'class'=>'btn btn-default btn-xs saas_map_bt', 'style'=>'margin-top:5px;'));

        echo html_writer::end_tag('td');

        echo html_writer::start_tag('div', array('id'=>$courseid . '-' . $ofertaid->oferta_disciplina_id));
          echo html_writer::start_tag('td');
          echo $courses[$courseid];
          echo html_writer::tag('input', '', array('class'=>'delete_many_offers_bt', 'type'=>'image',
                                'src' =>'img/delete.png', 'alt'=>'Apagar mapeamento', 'height'=>'15',
                                'width'=>'15', 'id'=>$courseid, 'style'=>'margin-left:2px;'));
          echo html_writer::end_tag('td');
        echo html_writer::end_tag('div');

        }

      echo html_writer::end_tag('tr');

    echo html_writer::start_tag('tr');
      echo html_writer::start_tag('td');
        echo html_writer::tag('button', 'Adicionar', array('type'=>'button', 'class'=>
                              'btn btn-default btn-xs saas_map_bt', 'style'=>'margin-top:5px;', 'disabled'=>'true'));
      echo html_writer::end_tag('td');

      echo html_writer::start_tag('td');
        echo html_writer::tag('button', 'Adicionar', array('type'=>'button', 'class'=>
                              'btn btn-default btn-xs moodle_map_bt', 'style'=>'margin-top:5px;'));
      echo html_writer::end_tag('td');
    echo html_writer::end_tag('tr');

  //Mapeamento de uma oferta do SAAS para 1 ou mais cursos Moodle.
  } else {
    $ofertas = $saas->get_ofertas_disciplinas_salvas();
    $ofertas_mapeadas_com_cursos = array();

    foreach ($mapeamentos as $key => $map) {
        $ofertas_mapeadas_com_cursos[$map->oferta_disciplina_id] = $DB->get_records('saas_course_mapping',
                                     array('oferta_disciplina_id'=>$map->oferta_disciplina_id), null, 'courseid');
    }

    foreach ($ofertas as $ofertaid => $oferta) {
      echo html_writer::start_tag('tr');
        $nome_formatado = $oferta->nome .' ('. saas::format_date($oferta->inicio, $oferta->fim) . ')';
        echo html_writer::tag('td', $nome_formatado, array('id' => $ofertaid));

        echo html_writer::start_tag('td');
          echo html_writer::start_tag('div');
            if (array_key_exists($ofertaid, $ofertas_mapeadas_com_cursos)) {
              foreach ($ofertas_mapeadas_com_cursos[$ofertaid] as $courseid => $object) {
                echo html_writer::start_tag('div', array('id'=>$courseid . '-' . $ofertaid));
                  echo html_writer::tag('div', $courses[$courseid], array('style' => 'float:left;'));
                  echo html_writer::tag('input', '', array('class'=>'delete_bt', 'type'=>'image', 'src' =>'img/delete.png',
                    'alt'=>'Apagar mapeamento', 'height'=>'15', 'width'=>'15', 'uid'=>$ofertaid, 'id'=>$courseid,
                    'style'=>'margin-left:2px;'));
                echo html_writer::end_tag('div');
              }

              if ($saas->config->course_mapping == 'many_to_one') {
                      echo html_writer::start_tag('div');
                        echo html_writer::tag('button', 'Adicionar', array('type'=>'button', 'id'=>$oferta->id, 
                                              'class'=>'btn btn-default btn-xs moodle_map_bt', 
                                              'style'=>'margin-top:5px;', 'oferta'=>$nome_formatado));
                      echo html_writer::end_tag('div');
              }

            } else {
              echo html_writer::start_tag('div');
                echo html_writer::tag('button', 'Adicionar', array('type'=>'button', 'id'=>$oferta->id, 'class'=>'btn btn-default btn-xs moodle_map_bt'));
              echo html_writer::end_tag('div');
            }
          echo html_writer::end_tag('div');
        echo html_writer::end_tag('td');

      echo html_writer::end_tag('tr');
    }
  }
?>

</table>

<!-- Modal para Cursos Moodle-->
<div class="modal fade bs-example-modal-lg" id="cursos_moodle_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <h2 class="modal_cursos_moodle_title">Cursos Moodle</h2>
      </div>
        <?php
          $repeat_allowed = false;

          if ($saas->config->course_mapping == 'many_to_one') {
            $repeat_allowed = true;
          }

          build_tree_categories($repeat_allowed);
        ?>
      <div class="modal-footer">
        <button type="button" class="btn btn-default saas-bt-close" data-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal para Ofertas do SAAS-->
<div class="modal fade bs-example-modal-lg" id="ofertas_saas_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <h4 class="modal_ofertas_saas_title">Ofertas SAAS</h4>
      </div>
        <?php
          build_saas_tree_offers();
        ?>
      <div class="modal-footer">
        <button type="button" class="btn btn-default saas-bt-close" data-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

</div>