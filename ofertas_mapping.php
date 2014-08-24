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

<th><h4>Ofertas de Cursos SAAS</h4></th>
<th><h4>Cursos Moodle</h4></th>

<?php
  $saas = new saas();
  
  $ofertas_de_curso = $saas->get_ofertas_curso_salvas();
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
    $ofertas_de_disciplina = $saas->get_ofertas_disciplinas_salvas();
    
    $ofertas_mapeadas_com_cursos = array();

    foreach ($ofertas_de_curso as $oferta_de_curso) {
      $of = array();
      foreach ($ofertas_de_disciplina as $oferta_de_disciplina) {
          
          if ($oferta_de_curso->uid == $oferta_de_disciplina->oferta_curso_uid) {
            $of[] = $oferta_de_disciplina;
          }
      }
      $oferta_de_curso->ofertas_de_disciplina = $of;
    }

    foreach ($mapeamentos as $key => $map) {
        $ofertas_mapeadas_com_cursos[$map->oferta_disciplina_id] = $DB->get_records('saas_course_mapping',
                                     array('oferta_disciplina_id'=>$map->oferta_disciplina_id), null, 'courseid');
    }

    foreach ($ofertas_de_curso as $oferta_de_curso) {
      echo html_writer::start_tag('tr');
        $nome_formatado = $oferta_de_curso->nome .' ('. $oferta_de_curso->ano .'/'. $oferta_de_curso->periodo . ')';
        echo html_writer::tag('td', $nome_formatado, array('style' =>'font-weight:bold; margin-left:10px;'));
        echo html_writer::tag('td', '');
      echo html_writer::end_tag('tr');

      foreach ($oferta_de_curso->ofertas_de_disciplina as $oferta_de_disciplina) {
        echo html_writer::start_tag('div');
          echo html_writer::start_tag('tr');
              $nome_formatado = $oferta_de_disciplina->nome .' ('. saas::format_date($oferta_de_disciplina->inicio, $oferta_de_disciplina->fim) . ')';
              echo html_writer::tag('td', $nome_formatado, array('id' => $oferta_de_disciplina->id,
                                    'style'=>'text-indent: 30px;', 'bgcolor'=>'#F0F0F0'));
              
              echo html_writer::start_tag('td', array('bgcolor'=>'#F0F0F0'));
                echo html_writer::start_tag('div');
                if (array_key_exists($oferta_de_disciplina->id, $ofertas_mapeadas_com_cursos)) {
                  foreach ($ofertas_mapeadas_com_cursos[$oferta_de_disciplina->id] as $courseid => $object) {
                    echo html_writer::start_tag('div', array('id'=>$courseid . '-' . $oferta_de_disciplina->id));
                      echo html_writer::tag('div', $courses[$courseid], array('style' => 'float:left;'));
                      echo html_writer::tag('input', '', array('class'=>'delete_bt', 'type'=>'image', 'src' =>'img/delete.png',
                        'alt'=>'Apagar mapeamento', 'height'=>'15', 'width'=>'15', 'uid'=>$oferta_de_disciplina->id, 
                        'id'=>$courseid, 'style'=>'margin-left:2px;'));
                    echo html_writer::end_tag('div');
                  }

                  if ($saas->config->course_mapping == 'many_to_one') {
                          echo html_writer::start_tag('div');
                            echo html_writer::tag('button', 'Adicionar', array('type'=>'button', 'id'=>$oferta_de_disciplina->id, 
                                                  'class'=>'btn btn-default btn-xs moodle_map_bt', 
                                                  'style'=>'margin-top:5px;', 'oferta'=>$nome_formatado));
                          echo html_writer::end_tag('div');
                  }

                } else {
                  echo html_writer::start_tag('div');
                    echo html_writer::tag('button', 'Adicionar', array('type'=>'button', 
                                          'id'=>$oferta_de_disciplina->id, 
                                          'class'=>'btn btn-default btn-xs moodle_map_bt', 
                                          'oferta'=>$nome_formatado));
                  echo html_writer::end_tag('div');
                }
                echo html_writer::end_tag('div');
              echo html_writer::end_tag('td');
          echo html_writer::end_tag('tr');
        echo html_writer::end_tag('div');
      }
    }
  }
?>

</table>

<!-- Modal para Cursos Moodle-->
<div class="modal fade bs-example-modal-lg" id="cursos_moodle_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="saas-bt-close close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
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
        <button type="button" class="saas-bt-close close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <h2 class="modal_ofertas_saas_title">Ofertas SAAS</h2>
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
