<?php
require_once(dirname(__FILE__) . '/../../config.php');

function build_tree_categories() {
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
  
  get_courses_from_categories($categories);

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
  
function get_courses_from_categories(&$categories) {
  global $DB;

  foreach($categories AS $idcat=>$cat) {
    $categories[$idcat]->courses = array();
  }

  $str_ids = implode(', ', array_keys($categories));

  $sql = "SELECT id, shortname, fullname, category, visible 
            FROM {course} c
           WHERE c.category IN({$str_ids})";

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

function get_ofertas_disciplinas_salvas() {
  global $DB;

  return $DB->get_records('saas_ofertas_disciplinas', array('enable'=>1));
}

function get_ofertas_disciplinas_mapeadas() {
  global $DB;

  return $DB->get_records_menu('saas_course_mapping', null, null, 'oferta_disciplina_id, courseid');
}

?>