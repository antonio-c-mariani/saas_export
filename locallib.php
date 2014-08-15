<?php
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/report/saas_export/lib.php');

function build_tree_categories($repeat_allowed = true) {
 	$categories = get_moodle_categories();    

  foreach($categories as $cat){
    $cat->sub_ids = array();
  } 
  
  $topo = array();
  
  foreach($categories as $cat){
    if(isset($categories[$cat->parent])){
      $categories[$cat->parent]->sub_ids[] = $cat->id;
    } else {
      $topo[] = $cat->id;
    }
  }
  
  get_courses_from_categories($categories, $repeat_allowed);

  echo html_writer::start_tag('div', array('class'=>'tree well'));
    echo html_writer::start_tag('ul');
      show_categories($topo, $categories);
    echo html_writer::end_tag('ul');
  echo html_writer::end_tag('div');
}

function get_moodle_categories(){
  global $DB, $USER;

  $sql = "SELECT DISTINCT cc.id, cc.name, cc.parent, cc.sortorder 
            FROM {course_categories} cc
        ORDER BY cc.sortorder";
  
  $categories = $DB->get_records_sql($sql);
  
  $ids = array_keys($categories);
  
  while(!empty($ids)) {
      $str_ids = implode(', ', $ids);
      
      $sql = "SELECT cc.id, cc.name, cc.parent, cc.sortorder 
                FROM {course_categories} cc 
               WHERE cc.parent IN ({$str_ids}) 
            ORDER BY cc.sortorder";
      $cats = $DB->get_records_sql($sql);
      
      foreach ($cats as $c){
          $categories[$c->id] = $c;
      }
      
      $ids = array_keys($cats);
  }
  
  return $categories;
}
  
function get_courses_from_categories(&$categories, $repeat_allowed = true) {
  global $DB;

  if ($repeat_allowed) {
      $sql_repeat = "";
      $sql_and = "";
  } else {
      $sql_repeat = " LEFT JOIN {saas_course_mapping} scm ON (scm.courseid = c.id) ";
      $sql_and = " AND scm.courseid IS NULL";
                                           
  }

  foreach($categories AS $idcat=>$cat) {
    $categories[$idcat]->courses = array();
  }

  $str_ids = implode(', ', array_keys($categories));

  $sql = "SELECT c.id, c.shortname, c.fullname, c.category, c.visible 
            FROM {course} c
            {$sql_repeat}      
           WHERE c.category IN({$str_ids}) {$sql_and}";

  foreach($DB->get_records_sql($sql) AS $id=>$c) {
    $categories[$c->category]->courses[] = $c;
  }

}

function show_categories($catids, $categories){
  global $OUTPUT;

  foreach ($catids as $catid){
    $has_courses = empty($categories[$catid]->courses);
    $has_sub_categories = empty($categories[$catid]->sub_ids);
    
    $class = "";
    if($has_courses || $has_sub_categories){
      $class = "icon-minus-sign";
    } else {
      $class = "icon-leaf";
    }

    echo html_writer::start_tag('li');
      echo html_writer::start_tag('span');
        echo $categories[$catid]->name;
        echo html_writer::tag('i', '', array('class'=>$class));
      echo html_writer::end_tag('span');
    
    echo html_writer::start_tag('ul');
    
    
    foreach ($categories[$catid]->courses as $c){
      echo html_writer::start_tag('li');
      
        echo html_writer::start_tag('span', array('style'=>'background-color:#BDBDBD'));
          echo html_writer::tag('i', '', array('class'=>$class));
          echo html_writer::tag('html', $c->fullname);
        echo html_writer::end_tag('span');
          
        echo html_writer::tag('button', 'Selecionar', array('type'=>'button', 'id'=>$c->id, 
                              'class'=>'select_moodle_course btn btn-link'));
                
      echo html_writer::end_tag('li');
    }
    
        
    if(!empty($categories[$catid]->sub_ids)){
      show_categories($categories[$catid]->sub_ids, $categories);
    }
  
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('li');
  }
}

function build_saas_tree_offers() {
  $saas = new saas();
  
  $ofertas_curso = $saas->get_ofertas_curso_salvas();
  $ofertas_disciplina = $saas->get_ofertas_disciplinas_salvas();

  echo html_writer::start_tag('div', array('class'=>'tree well'));
    echo html_writer::start_tag('ul');
      
      foreach ($ofertas_curso as $oferta_curso) {
        echo html_writer::start_tag('li');
          echo html_writer::start_tag('span');
            echo $oferta_curso->nome;
          echo html_writer::end_tag('span');
    
          echo html_writer::start_tag('ul');
    
            foreach ($ofertas_disciplina as $oferta_disciplina) {
              if ($oferta_disciplina->oferta_curso_uid == $oferta_curso->uid) {
                echo html_writer::start_tag('li');
                  
                  echo html_writer::start_tag('span', array('style'=>'background-color:#BDBDBD'));
                    echo html_writer::tag('html', $oferta_disciplina->nome);
                  echo html_writer::end_tag('span');
                    
                  echo html_writer::tag('button', 'Selecionar', array('type'=>'button', 'id'=>$oferta_disciplina->id, 
                                        'class'=>'select_saas_offer btn btn-link'));
                          
                echo html_writer::end_tag('li');  
              }
            }
  
          echo html_writer::end_tag('ul');
        echo html_writer::end_tag('li');    
      }

    echo html_writer::end_tag('ul');
  echo html_writer::end_tag('div'); 
}

?>