<?php

// ----------------------------------------------------------------------------------------------
// Rotinas auxiliares para mapeamento de ofertas de disciplina para cursos Moodle

function saas_build_tree_categories() {
    global $DB, $SESSION;

    // Hierarquia de categorias com cursos a selecionar (não vazias)
    $sql = "SELECT DISTINCT ccp.id, ccp.depth, ccp.path, ccp.name
              FROM {course_categories} ccp
              JOIN (SELECT DISTINCT cc.id, cc.path
                      FROM {course_categories} cc
                      JOIN {course} c ON (c.category = cc.id)
                 LEFT JOIN {saas_map_course} mc ON (mc.courseid = c.id)
                     WHERE c.id > 1
                       AND ISNULL(mc.id)) cat
                ON (ccp.id = cat.id OR cat.path LIKE CONCAT('%/',ccp.id,'/%'))
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

    if(isset($SESSION->last_categoryid)) {
        if(isset($categories[$SESSION->last_categoryid])) {
            $category_path = explode('/', $categories[$SESSION->last_categoryid]->path);
            unset($category_path[0]);
        } else {
            if($path = $DB->get_field('course_categories', 'path', array('id'=>$SESSION->last_categoryid))) {
                $category_path = explode('/', $path);
                unset($category_path[0]);
            } else {
                $category_path = array();
            }
        }
    } else {
        $category_path = array();
    }

    // Cursos ainda não selecionados
    $sql = "SELECT c.id, c.category, c.fullname
              FROM {course} c
         LEFT JOIN {saas_map_course} mc ON (mc.courseid = c.id)
             WHERE c.id > 1
               AND ISNULL(mc.id)
          ORDER BY c.fullname";
    foreach($DB->get_records_sql($sql) AS $course) {
        $categories[$course->category]->courses[$course->id] = $course;
    }

    foreach(array_keys($categories) AS $catid) {
        if($categories[$catid]->depth > 1) {
            unset($categories[$catid]);
        }
    }

    echo html_writer::start_tag('div', array('class'=>'tree well'));
    echo html_writer::start_tag('ul');
    saas_show_categories($categories, $category_path);
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('div');
}

function saas_get_moodle_categories(){
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

function saas_get_courses_from_categories(&$categories, $repeat_allowed = true) {
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

function saas_show_categories(&$categories, $open_catids = array()){
    global $OUTPUT;

    foreach ($categories as $cat){
        $cat_class = $cat->depth == 1 ? 'category-root' : '';

        $open = in_array($cat->id, $open_catids);
        $cat_class .= $open ? " folder-open" : " folder-close";
        $icon_class = $open ? "icon-folder-open" : "icon-folder-close";

        echo html_writer::start_tag('li', array('class'=>$cat_class));
        echo html_writer::start_tag('span');
        echo html_writer::tag('i', '', array('class'=>$icon_class));
        echo $cat->name;
        echo html_writer::end_tag('span');

        echo html_writer::start_tag('ul');

        foreach ($cat->courses as $c){
            echo html_writer::start_tag('li');

            echo html_writer::start_tag('span', array('id'=>$c->id, 'class'=>'select_moodle_course', 'rel'=>'tooltip',
                                    'data-placement'=>'right', 'data-original-title'=>'Selecionar'));
            echo $OUTPUT->pix_icon("i/course", '', 'moodle', array('class' => 'icon smallicon'));
            echo html_writer::tag('html', $c->fullname);
            echo html_writer::end_tag('span');

            echo html_writer::end_tag('li');
        }

        if(!empty($cat->subs)){
            saas_show_categories($cat->subs, $open_catids);
        }

        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('li');
    }
}

// ----------------------------------------------------------------------------------------------
// Rotinas auxiliara para mapeamento de cursos para categorias

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

function saas_mount_category_tree_map_courses_polos(&$categories, &$polos, &$rows) {
    global $OUTPUT;

    foreach($categories AS $cat) {
        $row = new html_table_row();

        $cell = new html_table_cell();
        $cell->attributes['class'] = 'level' . $cat->depth;
        $depth = $cat->depth*2;
        $cell->text = html_writer::empty_tag('img', array('src'=> $OUTPUT->pix_url('f/folder'), 'class'=>'saas_pix', 'style'=>"padding-left: {$depth}%;"));
        $cell->text .= $cat->name;
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->attributes['class'] = 'level' . $cat->depth;
        $row->cells[] = $cell;

        $rows[] = $row;

        if(count($cat->courses) > 0) {
            $depth += 2;
            foreach($cat->courses AS $c) {
                $row = new html_table_row();

                $cell = new html_table_cell();
                $cell->attributes['class'] = 'level' . $cat->depth;
                $depth = ($cat->depth+2)*2;
                $cell->text = html_writer::empty_tag('img', array('src'=> $OUTPUT->pix_url('i/course'), 'class'=>'saas_pix', 'style'=>"padding-left: {$depth}%;"));
                $cell->text .= $c->name;
                $row->cells[] = $cell;

                $poloid = empty($c->polo_id) ? 0 : $c->polo_id;
                $cell = new html_table_cell();
                $cell->attributes['class'] = 'level' . $cat->depth;
                $cell->text = html_writer::select($polos, "map_polos[{$c->id}]", $poloid);

                $row->cells[] = $cell;

                $rows[] = $row;
            }
        }

        if(!empty($cat->subs)) {
            saas_mount_category_tree_map_courses_polos($cat->subs, $polos, $rows);
        }
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

function saas_mount_category_tree_map_categories_polos($categories, &$polos, &$rows) {
    global $OUTPUT;

    foreach($categories AS $cat) {
        $row = new html_table_row();

        $cell = new html_table_cell();
        $cell->attributes['class'] = 'level' . $cat->depth;
        $depth = $cat->depth*2;
        $cell->text = html_writer::empty_tag('img', array('src'=> $OUTPUT->pix_url('i/course'), 'class'=>'saas_pix', 'style'=>"padding-left: {$depth}%;"));
        $cell->text .= $cat->name;
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->attributes['class'] = 'level' . $cat->depth;
        $poloid = empty($cat->polo_id) ? 0 : $cat->polo_id;
        $cell->text = html_writer::select($polos, "map_polos[{$cat->id}]", $poloid);
        $row->cells[] = $cell;

        $rows[] = $row;

        if(!empty($cat->subs)) {
            saas_mount_category_tree_map_categories_polos($cat->subs, $polos, $rows);
        }
    }
}

// Criação de tabelas para visualização dos dados.
//---------------------------------------------------------------------------------------------------

function saas_show_table_polos() {
    global $DB, $OUTPUT;

    $polos = $DB->get_records('saas_polos', array('enable'=>1), 'nome');
    $color = '#E0E0E0';

    print html_writer::start_tag('DIV', array('align'=>'center'));

    if(empty($polos)) {
        print html_writer::tag('h3', get_string('no_polos', 'report_saas_export'));
    } else {
        $table = new html_table();
        $table->head = array(get_string('nome_polo', 'report_saas_export'),
                             get_string('cidade', 'report_saas_export'),
                             get_string('estado', 'report_saas_export'));
        $table->colclasses = array('leftalign', 'leftalign', 'leftalign');
        $table->attributes = array('class'=>'saas_table');
        $table->data = array();

        foreach($polos as $pl) {
            $color = $color == '#C0C0C0' ? '#E0E0E0 ' : '#C0C0C0';
            $row = new html_table_row();

            foreach(array('nome', 'cidade', 'estado') AS $field) {
                $cell = new html_table_cell();
                $cell->text = $pl->$field;
                $cell->style = "background-color: {$color};";
                $row->cells[] = $cell;
            }

            $table->data[] = $row;
        }
        print html_writer::table($table);
    }

    print html_writer::end_tag('DIV');
}

function saas_show_overview_polos($ocid, $poloid) {
    global $DB, $saas;

    $polo_mapping_type = $saas->get_config('polo_mapping');

    if(!$DB->record_exists('saas_polos', array('enable'=>1))) {
        print html_writer::tag('h3', get_string('no_polos', 'report_saas_export'));
    }
    if($polo_mapping_type == 'no_polo') {
        print $OUTPUT->heading(get_string('title_no_polo', 'report_saas_export'));
        return;
    }

    $url = new moodle_url('index.php', array('action'=>'overview', 'data'=>'polos'));
    saas_show_menu_ofertas_cursos($ocid, $url);

    if($ocid && $poloid) {
        switch ($polo_mapping_type) {
            case 'group_to_polo':
                list($sql, $params) = $saas->get_sql_users_by_oferta_curso_polo_groups($ocid, $poloid, false);
                saas_show_users_oferta_curso_polo($ocid, $poloid, $sql, $params);
                break;
            case 'category_to_polo':
                list($sql, $params) = $saas->get_sql_users_by_oferta_curso_polo_categories($ocid, $poloid, false);
                saas_show_users_oferta_curso_polo($ocid, $poloid, $sql, $params);
                break;
            case 'course_to_polo':
                list($sql, $params) = $saas->get_sql_users_by_oferta_curso_polo_courses($ocid, $poloid, false);
                saas_show_users_oferta_curso_polo($ocid, $poloid, $sql, $params);
                break;
        }
    } else {
        switch ($polo_mapping_type) {
            case 'group_to_polo':
                saas_show_overview_groups_polos($ocid);
                break;
            case 'category_to_polo':
                saas_show_overview_categories_polos($ocid);
                break;
            case 'course_to_polo':
                saas_show_overview_courses_polos($ocid);
                break;
        }
    }
}

function saas_show_overview_courses_polos($ocid) {
    if($ocid) {
        $cond = "AND oc.id = :ocid";
        $params = array('ocid'=>$ocid);
    } else {
        $cond = '';
        $params = array();
    }
    $sql = "SELECT DISTINCT oc.id AS ocid, sp.*
              FROM {saas_ofertas_cursos} oc
              JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
              JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)
              JOIN {course} c ON (c.id = cm.courseid)
              JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'course' AND smcp.instanceid = c.id)
              JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1)
             WHERE oc.enable = 1
               {$cond}
          ORDER BY oc.id, sp.nome";
    saas_show_table_overview_polos($sql, $params);
}

function saas_show_overview_categories_polos($ocid) {
    if($ocid) {
        $cond = "AND oc.id = :ocid";
        $params = array('ocid'=>$ocid);
    } else {
        $cond = '';
        $params = array();
    }
    $sql = "SELECT DISTINCT oc.id AS ocid, sp.*
              FROM {saas_ofertas_cursos} oc
              JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
              JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)
              JOIN {course} c ON (c.id = cm.courseid)
              JOIN {course_categories} cc ON (cc.id = c.category)
              JOIN {course_categories} ccp ON (ccp.id = cc.id OR cc.path LIKE CONCAT('%/',ccp.id,'/%'))
              JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'category' AND smcp.instanceid = ccp.id)
              JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1)
             WHERE oc.enable = 1
               {$cond}
          ORDER BY oc.id, sp.nome";
    saas_show_table_overview_polos($sql, $params);
}

function saas_show_overview_groups_polos($ocid) {
    if($ocid) {
        $cond = "AND oc.id = :ocid";
        $params = array('ocid'=>$ocid);
    } else {
        $cond = '';
        $params = array();
    }
    $sql = "SELECT DISTINCT oc.id AS ocid, sp.*
              FROM {saas_ofertas_cursos} oc
              JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
              JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)
              JOIN {course} c ON (c.id = cm.courseid)
              JOIN {groups} g ON (g.courseid = c.id)
              JOIN {saas_map_groups_polos} spm ON (spm.groupname = g.name AND spm.polo_id > 0)
              JOIN {saas_polos} sp ON (sp.id = spm.polo_id AND sp.enable = 1)
             WHERE oc.enable = 1
               {$cond}
          ORDER BY oc.id, sp.nome";
    saas_show_table_overview_polos($sql, $params);
}

function saas_show_table_overview_polos($sql, $params) {
    global $DB, $saas;

    $ofertas_cursos = $DB->get_records('saas_ofertas_cursos', array('enable'=>1), 'nome, ano, periodo');
    foreach($ofertas_cursos AS $id=>$oc) {
        $oc->polos = array();
    }
    foreach($DB->get_recordset_sql($sql, $params) AS $pl) {
        $ofertas_cursos[$pl->ocid]->polos[] = $pl;
    }

    $counts = $saas->get_polos_count();
    $role_types = $saas->get_role_types('polos');

    $data = array();
    $color_class = '';
    foreach($ofertas_cursos AS $oc) {
        if(!empty($oc->polos)) {
            $color_class = $color_class == 'saas_normalcolor' ? 'saas_alternatecolor' : 'saas_normalcolor';
            $rows = max(count($oc->polos), 1);
            $row = new html_table_row();

            $cell = new html_table_cell();
            $cell->text = $oc->nome;
            $cell->rowspan = $rows;
            $cell->style = "vertical-align: middle;";
            $cell->attributes['class'] = $color_class;
            $row->cells[] = $cell;

            $cell = new html_table_cell();
            $cell->text = "{$oc->ano}/{$oc->periodo}";
            $cell->rowspan = $rows;
            $cell->style = "vertical-align: middle;";
            $cell->attributes['class'] = $color_class;
            $row->cells[] = $cell;

            foreach($oc->polos AS $pl) {
                $texts = array();
                $show_url = false;
                foreach($role_types AS $r) {
                    if(isset($counts[$oc->id][$pl->id][$r]) && $counts[$oc->id][$pl->id][$r] > 0) {
                        $texts[$r] = $counts[$oc->id][$pl->id][$r];
                        $show_url = true;
                    } else {
                        $texts[$r] = 0;
                    }
                }

                $cell = new html_table_cell();
                if($show_url) {
                    $url = new moodle_url('index.php', array('action'=>'overview', 'data'=>'polos', 'ocid'=>$oc->id, 'poloid'=>$pl->id));
                    $cell->text = html_writer::link($url, $pl->nome);
                } else {
                    $cell->text = $pl->nome;
                }
                $cell->attributes['class'] = $color_class;
                $row->cells[] = $cell;

                foreach($role_types AS $r) {
                    $cell = new html_table_cell();
                    $cell->text = $texts[$r];
                    $cell->style = "text-align: right;";
                    $cell->attributes['class'] = $color_class;
                    $row->cells[] = $cell;
                }

                $data[] = $row;
                $row = new html_table_row();
            }
        }
    }

    print html_writer::start_tag('DIV', array('align'=>'center'));

    $table = new html_table();
    $table->head = array(get_string('oferta_curso', 'report_saas_export'),
                         get_string('periodo', 'report_saas_export'),
                         get_string('nome_polo', 'report_saas_export'));
    $table->colclasses = array('leftalign', 'leftalign', 'leftalign');
    foreach($role_types AS $r) {
        $table->head[] = get_string($r, 'report_saas_export');
        $table->colclasses[] = 'rightalign';
    }

    $table->attributes = array('class'=>'saas_table');
    $table->data = $data;
    print html_writer::table($table);

    print html_writer::end_tag('DIV');
}

function saas_show_users_oferta_curso_polo($ocid, $poloid, $sql, $params) {
    global $DB, $OUTPUT, $saas;

    $oc = $DB->get_record('saas_ofertas_cursos', array('id'=>$ocid));
    $polo = $DB->get_record('saas_polos', array('id'=>$poloid));

    $role_types = $saas->get_role_types('polos');

    print html_writer::start_tag('DIV', array('align'=>'center'));

    print html_writer::tag('H3', "Oferta de curso: {$oc->nome} ({$oc->ano}/{$oc->periodo})");
    print html_writer::tag('H3', "Polo: {$polo->nome}");

    $data = array();
    foreach($role_types AS $role) {
        $data[$role] = array();
    }
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach($rs AS $rec) {
        $user = $saas->get_user($rec->role, $rec->userid, $rec->uid);
        $data[$rec->role][] = array((count($data[$rec->role])+1) . '.', $user->nome, $user->uid, $user->email, $user->cpf);
    }

    foreach($role_types AS $role) {
        if(count($data[$role]) > 0) {
            print $OUTPUT->box_start('generalbox boxwidthwide');
            print $OUTPUT->heading(get_string($role . 's', 'report_saas_export'), '4');

            $table = new html_table();
            $table->head = array('', get_string('name'), get_string('username', 'report_saas_export'), get_string('email'), get_string('cpf', 'report_saas_export'));
            $table->attributes = array('class'=>'saas_table');
            $table->data = $data[$role];
            print html_writer::table($table);
            print $OUTPUT->box_end();
        }
    }

    print html_writer::end_tag('DIV');
}

function saas_show_users_oferta_disciplina($ofer_disciplina_id) {
    global $DB, $OUTPUT, $saas;

    $grades = $saas->get_grades($ofer_disciplina_id);

    $od = $saas->get_oferta_disciplina($ofer_disciplina_id);
    $oc = $DB->get_record('saas_ofertas_cursos', array('uid'=>$od->oferta_curso_uid));

    print html_writer::start_tag('DIV', array('align'=>'center'));

    print html_writer::tag('H3', "Oferta de curso: {$oc->nome} ({$oc->ano}/{$oc->periodo})");
    print html_writer::tag('H3', "Oferta de disciplina: {$od->nome} ". $saas->format_date($od->inicio, $od->fim));

    list($sql, $params) =  $saas->get_sql_users_by_oferta_disciplina(0, $ofer_disciplina_id);
    $rs = $DB->get_recordset_sql($sql, $params);
    $data = array();
    $suspended = array();
    $role_types = $saas->get_role_types('disciplinas');
    foreach($role_types AS $r) {
        $data[$r] = array();
    }
    foreach($rs AS $rec) {
        if(empty($rec->suspended)) {
            $user = $saas->get_user($rec->role, $rec->userid, $rec->uid);
            $row = array((count($data[$rec->role])+1) . '.', $user->nome, $user->uid, $user->email, $user->cpf);
            if($rec->role == 'student') {
                $row[] = get_string('no');
                $row[] = empty($rec->currentlogin) ? '-' : date('d-m-Y H:i', $rec->currentlogin);
                $row[] = empty($rec->lastaccess) ? '-' : date('d-m-Y H:i', $rec->lastaccess);
                $row[] = isset($grades[$rec->userid]) && $grades[$rec->userid] >= 0 ? $grades[$rec->userid] : '-';
            }
            $data[$rec->role][] = $row;
        } else {
            $suspended[] = $rec;
        }
    }

    $color = '#D0D0D0';
    foreach($suspended AS $rec) {
        $user = $saas->get_user($rec->role, $rec->userid, $rec->uid);
        $row = new html_table_row();

        $row->style = "background-color: {$color};";
        $row->cells[] = (count($data[$rec->role])+1) . '.';
        $row->cells[] = $user->nome;
        $row->cells[] = $user->uid;
        $row->cells[] = $user->email;
        $row->cells[] = $user->cpf;
        $row->cells[] = html_writer::tag('span', get_string('yes'), array('class'=>'saas_export_warning'));
        $row->cells[] = empty($rec->currentlogin) ? '-' : date('d-m-Y H:i', $rec->currentlogin);
        $row->cells[] = empty($rec->lastaccess) ? '-' : date('d-m-Y H:i', $rec->lastaccess);
        $row->cells[] = isset($grades[$rec->userid]) && $grades[$rec->userid] >= 0 ? $grades[$rec->userid] : '-';

        $data[$rec->role][] = $row;
    }

    foreach($role_types AS $r) {
        if(count($data[$r]) > 0) {
            print $OUTPUT->box_start('generalbox boxwidthwide');
            print $OUTPUT->heading(get_string($r . 's', 'report_saas_export'), '4');

            $table = new html_table();
            $table->head = array('', get_string('name'), get_string('username', 'report_saas_export'), get_string('email'), get_string('cpf', 'report_saas_export'));
            $table->colclasses = array('rightalign', 'leftalign', 'leftalign', 'leftalign', 'leftalign');
            if($r == 'student') {
                $table->head[] = get_string('suspended', 'report_saas_export');
                $table->head[] = get_string('lastlogin');
                $table->head[] = get_string('lastcourseaccess', 'report_saas_export');
                $table->head[] = get_string('finalgrade', 'grades');
                $table->colclasses[] = 'centeralign';
                $table->colclasses[] = 'centeralign';
                $table->colclasses[] = 'centeralign';
                $table->colclasses[] = 'rightalign';
            }
            $table->data = $data[$r];
            $table->attributes = array('class'=>'saas_table');
            print html_writer::table($table);
            print $OUTPUT->box_end();
        }
    }

    print html_writer::end_tag('DIV');
}

function saas_show_menu_ofertas_cursos($oferta_curso_id=0, $url) {
    global $PAGE, $DB, $saas;

    // obtem ofertas de curso
    $ofertas_cursos = $saas->get_ofertas_curso();
    $ofertas_menu = array();
    $ofertas_menu[0] = get_string('all');
    foreach($ofertas_cursos AS $ocid=>$oc) {
        $ofertas_menu[$ocid] = "{$oc->nome} ({$oc->ano}/{$oc->periodo})";
    }

    print html_writer::start_tag('div', array('class'=>'saas_table', 'align'=>'right'));
    print get_string('oferta_curso', 'report_saas_export') . ':';

    print html_writer::start_tag('form', array('method'=>'post', 'action'=>$url));
    print html_writer::select($ofertas_menu, 'ocid', $oferta_curso_id, false);
    print html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'list', 'value'=>get_string('list')));
    print html_writer::end_tag('form');

    print html_writer::end_tag('div');
}

function saas_show_table_ofertas_curso_disciplinas($oferta_curso_id=0, $show_counts=false) {
    global $PAGE, $DB, $saas;

    $data = array();
    $color = '#E0E0E0';

    if($show_counts) {
        list($sql, $params) =  $saas->get_sql_users_by_oferta_disciplina($oferta_curso_id, 0, true);
        $rs = $DB->get_recordset_sql($sql, $params);
        $ofertas_disciplinas_counts = array();
        foreach($rs AS $rec) {
            $ofertas_disciplinas_counts[$rec->odid][$rec->role] = $rec->count;
        }
    }

    print html_writer::start_tag('div', array('class'=>'saas_table'));

    $role_types = $saas->get_role_types('disciplinas');
    $ofertas = $saas->get_ofertas($oferta_curso_id);
    if(empty($ofertas)) {
        print html_writer::tag('h3', get_string('no_ofertas_cursos', 'report_saas_export'));
    } else {
        foreach($ofertas AS $ocid=>$oc) {
            $oc_nome_formatado = "{$oc->nome} ({$oc->ano}/{$oc->periodo})";
            print html_writer::tag('a', html_writer::tag('h3',$oc_nome_formatado), array('id'=>'oc'.$ocid));

            $rows = array();
            $index = 0;
            $color_class = '';

            foreach($oc->ofertas_disciplinas AS $odid=>$od) {
                $row = new html_table_row();
                $color_class = $color_class == 'saas_normalcolor' ? 'saas_alternatecolor' : 'saas_normalcolor';
                $cell = new html_table_cell();
                if($show_counts) {
                    $texts = array();
                    $show_url = false;
                    foreach($role_types AS $r) {
                        if($od->mapped) {
                            if(isset($ofertas_disciplinas_counts[$odid][$r]) && $ofertas_disciplinas_counts[$odid][$r] > 0) {
                                $texts[$r] = $ofertas_disciplinas_counts[$odid][$r];
                                $show_url = true;
                            } else {
                                $texts[$r] = 0;
                            }
                        } else {
                            $texts[$r] = '-';
                        }
                    }
                    if($show_url) {
                        $url = new moodle_url('index.php', array('action'=>'overview', 'data'=>'ofertas', 'ocid'=>$ocid, 'odid'=>$odid));
                        $cell->text = html_writer::link($url, $od->nome);
                    } else {
                        $cell->text = $od->nome;
                    }
                } else {
                    $cell->text = $od->nome;
                }
                $cell->attributes['class'] = $color_class;
                $row->cells[] = $cell;

                $cell = new html_table_cell();
                $cell->text = $saas->format_date($od->inicio);
                $cell->attributes['class'] = $color_class;
                $row->cells[] = $cell;

                $cell = new html_table_cell();
                $cell->text = $saas->format_date($od->fim);
                $cell->attributes['class'] = $color_class;
                $row->cells[] = $cell;

                if($show_counts) {
                    foreach($role_types AS $r) {
                        $cell = new html_table_cell();
                        $cell->text = $texts[$r];
                        $cell->attributes['class'] = $color_class;
                        $row->cells[] = $cell;
                    }
                }

                $rows[] = $row;
            }

            $table = new html_table();
            $table->head = array('Oferta de disciplina', 'Início', 'Fim');
            $table->colclasses = array('leftalign', 'leftalign', 'leftalign');
            if($show_counts) {
                foreach($role_types AS $r) {
                    $table->head[] = get_string($r, 'report_saas_export');
                    $table->colclasses[] = 'rightalign';
                }
            }
            $table->data = $rows;
            print html_writer::table($table);
        }
    }
    print html_writer::end_tag('DIV');
}

function saas_show_export_options($url, $selected_ocs=true, $selected_ods=true, $selected_polos=true) {
    global $DB, $saas, $PAGE, $OUTPUT;

    $PAGE->requires->js_init_call('M.report_saas_export.init');

    $data = array();
    $color = '#E0E0E0';

    list($sql, $params) =  $saas->get_sql_users_by_oferta_disciplina(0, 0, true);
    $rs = $DB->get_recordset_sql($sql, $params);
    $od_counts = array();
    foreach($rs AS $rec) {
        $od_counts[$rec->odid] = true;
    }

    $polos = $saas->get_polos();
    $polos_count = $saas->get_polos_count();

    $ofertas = $saas->get_ofertas();

    if(empty($ofertas)) {
        print html_writer::tag('h3', get_string('no_ofertas_cursos', 'report_saas_export'));
        return;
    }

    foreach($ofertas AS $ocid=>$oc) {
        foreach(array_keys($oc->ofertas_disciplinas) AS $odid) {
            if(!isset($od_counts[$odid])) {
                unset($ofertas[$ocid]->ofertas_disciplinas[$odid]);
            }
        }

        $ofertas[$ocid]->polos = array();
        if(isset($polos_count[$ocid])) {
            foreach($polos_count[$ocid] AS $poloid=>$counts) {
                $ofertas[$ocid]->polos[$poloid] = $polos[$poloid];
            }
        }
    }


    $rows = array();
    foreach($ofertas AS $ocid=>$oc) {
        if(!empty($oc->ofertas_disciplinas) || !empty($oc->polos)) {
            $color = $color == '#C0C0C0' ? '#E0E0E0 ' : '#C0C0C0';

            $tag_checkbox = 'saas_oc_' . $ocid;
            $row = new html_table_row();

            $cell = new html_table_cell();
            $checked = $selected_ocs===true || isset($selected_ocs[$ocid]);
            $disabled = $checked ? array('disabled'=>true) : array();
            $lable = "{$oc->nome} ({$oc->ano}/{$oc->periodo})";
            $cell->text = html_writer::checkbox("oc[{$ocid}]", $ocid, $checked, $lable, array('id'=>$tag_checkbox, 'class'=>'oc_checkbox'));
            $cell->style = "vertical-align: middle; background-color: {$color};";
            $row->cells[] = $cell;

            $cell = new html_table_cell();
            if(!empty($oc->ofertas_disciplinas)) {
                $cell->text = html_writer::start_tag('UL', array('style'=>'list-style-type: none;'));
                $params = array_merge($disabled, array('class'=>'od_'.$tag_checkbox));
                foreach($oc->ofertas_disciplinas AS $od) {
                    $checked = $selected_ocs===true || isset($selected_ods[$ocid][$od->id]);
                    $label = $od->nome . ' (' . $saas->format_date($od->inicio, $od->fim) . ')';
                    $checkbox = html_writer::checkbox("od[{$ocid}][{$od->id}]", $ocid, $checked, $label, $params);
                    $cell->text .= html_writer::tag('LI', $checkbox);
                }

                $cell->text .= html_writer::empty_tag('img', array('src'=>'img/arrow_ltr.png'));
                $params = array_merge($disabled, array('class'=>'checkall_button', 'id'=>"od_{$tag_checkbox}"));
                $cell->text .= html_writer::checkbox('', '', true, 'todos/nenhum', $params);

                $cell->text .= html_writer::end_tag('UL');
            }
            $cell->style = "vertical-align: middle; background-color: {$color};";
            $row->cells[] = $cell;

            $cell = new html_table_cell();
            if(!empty($oc->polos)) {
                $cell->text = html_writer::start_tag('UL', array('style'=>'list-style-type: none;'));
                $params = array_merge($disabled, array('class'=>'polo_'.$tag_checkbox));
                foreach($oc->polos AS $pl) {
                    $checked = $selected_ocs===true || isset($selected_polos[$ocid][$pl->id]);
                    $checkbox = html_writer::checkbox("polo[{$ocid}][{$pl->id}]", $ocid, $checked, $pl->nome, $params);
                    $cell->text .= html_writer::tag('LI', $checkbox);
                }
                $cell->text .= html_writer::empty_tag('img', array('src'=>'img/arrow_ltr.png'));
                $params = array_merge($disabled, array('class'=>'checkall_button', 'id'=>"polo_{$tag_checkbox}"));
                $cell->text .= html_writer::checkbox('', '', true, 'todos/nenhum', $params);

                $cell->text .= html_writer::end_tag('UL');
            }
            $cell->style = "vertical-align: middle; background-color: {$color};";
            $row->cells[] = $cell;

            $rows[] = $row;
        }
    }

    print html_writer::start_tag('DIV', array('align'=>'center'));

    print $OUTPUT->box_start('generalbox boxwidthwide');
    print html_writer::tag('p', 'Selecione abaixo as ofertas de curso, de disciplinas e polos cujos dados devam ser exportados para o SAAS');
    print $OUTPUT->box_end();

    print html_writer::start_tag('form', array('method'=>'post', 'action'=>$url));

    $table = new html_table();
    $table->head = array('Oferta de Curso', 'Ofertas de disciplina', 'Polos');
    $table->attributes = array('class'=>'saas_table_map');
    $table->colclasses = array('leftalign', 'leftalign', 'leftalign', 'leftalign');
    $table->data = $rows;
    print html_writer::table($table);

    print html_writer::checkbox('send_user_details', 'ok', true, 'Enviar detalhes de estudantes (últimos acessos e notas)');
    print html_writer::empty_tag('br');
    print html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'export', 'value'=>s(get_string('saas_export:export', 'report_saas_export'))));
    print html_writer::end_tag('form');
    print html_writer::end_tag('DIV');
}
