<?php
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/report/saas_export/classes/saas.php');

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
      show_categories($topo, $categories, true);
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
      $sql_repeat = " LEFT JOIN {saas_map_course} scm ON (scm.courseid = c.id) ";
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

function show_categories($catids, $categories, $first_category = false){
  global $OUTPUT;

  foreach ($catids as $catid){
    $has_courses = empty($categories[$catid]->courses);
    $has_sub_categories = empty($categories[$catid]->sub_ids);

    $class = "";
    $cat_class = "";
    $style = "";

    if($has_courses && $has_sub_categories){
      $style = "background: #ccc;";
    } elseif ($first_category) {
      $class = "icon-folder-open";
      $cat_class = 'category-root';
    } else {
      $class = "icon-folder-close";
    }  

    echo html_writer::start_tag('li', array('class'=>$cat_class));
      echo html_writer::start_tag('span', array('style'=>$style));
        echo html_writer::tag('i', '', array('class'=>$class));
        echo $categories[$catid]->name;
      echo html_writer::end_tag('span');

    echo html_writer::start_tag('ul');

    foreach ($categories[$catid]->courses as $c){
      echo html_writer::start_tag('li');
        
        echo html_writer::start_tag('span', array('id'=>$c->id, 'class'=>'select_moodle_course',
                                    'rel'=>'tooltip', 'data-placement'=>'right',
                                    'data-original-title'=>'Selecionar'));
          echo $OUTPUT->pix_icon("i/course", '', 'moodle', array('class' => 'icon smallicon'));
          echo html_writer::tag('html', $c->fullname);
        echo html_writer::end_tag('span');
        
      echo html_writer::end_tag('li');
    }


    if(!empty($categories[$catid]->sub_ids)){
      show_categories($categories[$catid]->sub_ids, $categories);
    }

    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('li');
  }
}

function show_saas_offers($oferta_de_curso_uid, $repeat_allowed = true) {
  global $DB;

  $ofertas_de_disciplinas = $DB->get_records('saas_ofertas_disciplinas', array('oferta_curso_uid'=>$oferta_de_curso_uid));

  echo html_writer::start_tag('ul', array('class'=>'saas_offers_list', 'id'=>$oferta_de_curso_uid));
    foreach($ofertas_de_disciplinas as $od) {
        echo html_writer::tag('li', $od->nome, array('class'=>'oferta_de_disciplina', 'id'=>$od->uid));
    }
  echo html_writer::end_tag('ul');
}

?>
