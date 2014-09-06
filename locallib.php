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

  $modal = "";
  
  $ofertas_de_disciplinas = $DB->get_records('saas_ofertas_disciplinas', array('oferta_curso_uid'=>$oferta_de_curso_uid));

  $modal .= html_writer::start_tag('div', array('style'=>'display:block;', 'id'=>$oferta_de_curso_uid,
                                   'class'=>'lista_de_ofertas'));
      foreach($ofertas_de_disciplinas as $od) {
          $modal .= html_writer::tag('input', '', array('type'=>'checkbox', 'class'=>'od_checkbox', 
                                         'chk_id'=>$od->uid, 'style'=>'margin-right:5px;'));
          $modal .= html_writer::tag('div', $od->nome, array('style'=>'display:inline;'));
      }
  $modal .= html_writer::end_tag('div');
  
  return $modal;
}

// ----------------------------------------------------------------------------------------------
// Rotinas auxiliara para mapeamento de cursos para categoriasA

function saas_get_polos_menu() {
    global $DB;

    return $DB->get_records_menu('saas_polos', null, 'nome', "id, CONCAT(nome, ' (', cidade, '/', estado, ')') as nome");
}

function saas_get_category_tree_map_courses_polos() {
    global $DB;

    $sql = "SELECT DISTINCT ccp.id, ccp.depth, ccp.path, ccp.name
              FROM {course} c
              JOIN {saas_map_course} smc ON (smc.courseid = c.id)
              JOIN {course_categories} cc ON (cc.id = c.category)
              JOIN {course_categories} ccp ON (ccp.id = cc.id OR cc.path LIKE CONCAT('%/',ccp.id,'/%'))
          ORDER BY ccp.depth, ccp.name";
    $categories = $DB->get_records_sql($sql);

    foreach($categories AS $cat) {
        $cat->subs = array();
        $cat->courses = array();
        if($cat->depth > 1) {
            $path = explode('/', $cat->path);
            $superid = $path[count($path)-2];
            $categories[$superid]->subs[$cat->id] = $cat;
        }
    }

    $sql = "SELECT DISTINCT c.id, c.category, c.fullname AS name, smcp.polo_id
              FROM {course} c
              JOIN {saas_map_course} smc ON (smc.courseid = c.id)
         LEFT JOIN {saas_map_catcourses_polos} smcp ON (smcp.instanceid = c.id AND smcp.type = 'course')
          ORDER BY c.fullname";

    foreach($DB->get_records_sql($sql) AS $course) {
        $categories[$course->category]->courses[$course->id] = $course;
    }

    foreach(array_keys($categories) AS $catid) {
        if($categories[$catid]->depth > 1) {
            unset($categories[$catid]);
        }
    }

    return $categories;
}

function saas_show_category_tree_map_courses_polos(&$categories, &$polos) {
    foreach($categories AS $cat) {
        print html_writer::start_tag('LI', array('class'=>'category'));
        print "<label for=\"category{$cat->id}\">$cat->name</label>";
        print "<input type=\"checkbox\" checked id=\"category{$cat->id}\" />\n";

        print html_writer::start_tag('OL');

        if(count($cat->courses) > 0) {
            $count=0;
            foreach($cat->courses AS $c) {

                $count++;
                $class= $count%2==1 ? 'normalcolor' : 'alternatecolor';

                print html_writer::start_tag('LI', array('class'=>$class));
                print html_writer::tag('SPAN', $c->name);
                $poloid = empty($c->polo_id) ? 0 : $c->polo_id;
                print html_writer::select($polos, "map_polos[{$c->id}]", $poloid);
                print html_writer::end_tag('LI');
            }
        }

        if(!empty($cat->subs)) {
            saas_show_category_tree_map_courses_polos($cat->subs, $polos);
        }

        print html_writer::end_tag('OL');
        print html_writer::end_tag('LI');
        print html_writer::tag('LI', ''); // incluido para clear:both
    }
}

function saas_get_category_tree_map_categories_polos() {
    global $DB;

    $sql = "SELECT DISTINCT ccp.id, ccp.depth, ccp.path, ccp.name, smcp.polo_id
              FROM {course} c
              JOIN {saas_map_course} smc ON (smc.courseid = c.id)
              JOIN {course_categories} cc ON (cc.id = c.category)
              JOIN {course_categories} ccp ON (ccp.id = cc.id OR cc.path LIKE CONCAT('%/',ccp.id,'/%'))
         LEFT JOIN {saas_map_catcourses_polos} smcp ON (smcp.instanceid = ccp.id AND smcp.type = 'category')
          ORDER BY ccp.depth, ccp.name";
    $categories = $DB->get_records_sql($sql);

    foreach($categories AS $cat) {
        $cat->subs = array();
        if($cat->depth > 1) {
            $path = explode('/', $cat->path);
            $superid = $path[count($path)-2];
            $categories[$superid]->subs[$cat->id] = $cat;
        }
    }

    foreach(array_keys($categories) AS $catid) {
        if($categories[$catid]->depth > 1) {
            unset($categories[$catid]);
        }
    }

    return $categories;
}

function saas_show_category_tree_map_categories_polos(&$categories, &$polos) {
    foreach($categories AS $cat) {
        print html_writer::start_tag('LI', array('class'=>'category'));
        print "<span>";
        print "<label for=\"category{$cat->id}\">$cat->name</label>";
        print "<input type=\"checkbox\" checked id=\"category{$cat->id}\" />\n";
        print "</span>";

        $poloid = empty($cat->polo_id) ? 0 : $cat->polo_id;
        print html_writer::select($polos, "map_polos[{$cat->id}]", $poloid);

        print html_writer::start_tag('OL');

        if(!empty($cat->subs)) {
            saas_show_category_tree_map_categories_polos($cat->subs, $polos);
        }

        print html_writer::end_tag('OL');
        print html_writer::end_tag('LI');
        print html_writer::tag('LI', ''); // incluido para clear:both
    }
}
?>
