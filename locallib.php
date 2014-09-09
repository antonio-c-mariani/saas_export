<?php
require_once(dirname(__FILE__) . '/../../config.php');

// ----------------------------------------------------------------------------------------------
// Rotinas auxiliara para mapeamento de ofertas de disciplina para cursos Moodle

function saas_build_tree_categories($repeat_allowed = true) {
    $categories = saas_get_moodle_categories();

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

    saas_get_courses_from_categories($categories, $repeat_allowed);

    echo html_writer::start_tag('div', array('class'=>'tree well'));
    echo html_writer::start_tag('ul');
    saas_show_categories($topo, $categories, true);
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

function saas_show_categories($catids, $categories, $first_category = false){
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
            saas_show_categories($categories[$catid]->sub_ids, $categories);
        }

        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('li');
    }
}

function saas_show_saas_offers($oferta_de_curso_uid, $repeat_allowed = true) {
    global $DB;

    $modal = "";

    $ofertas_de_disciplinas = $saas->get_ofertas_disciplinas($oferta_de_curso_uid);

    $modal .= html_writer::start_tag('div', array('style'=>'display:block;', 'id'=>$oferta_de_curso_uid, 'class'=>'lista_de_ofertas'));
    foreach($ofertas_de_disciplinas as $od) {
        $modal .= html_writer::tag('input', '', array('type'=>'checkbox', 'class'=>'od_checkbox',
                                                      'chk_id'=>$od->uid, 'style'=>'margin-right:5px;'));
        $modal .= html_writer::tag('div', $od->nome, array('style'=>'display:inline;'));
    }
    $modal .= html_writer::end_tag('div');

    return $modal;
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

function saas_show_category_tree_map_courses_polos(&$categories, &$polos) {
    global $OUTPUT;

    $o = '';
    foreach($categories AS $cat) {
        $o .= '<tr class="category">';
        $o .= '<td class="saas_category_name">';
        $depth = $cat->depth*2;
        $o .= '<img class="saas_category_pix" style="padding-left: '.$depth.'%;"src="'.$OUTPUT->pix_url('i/course').'" />';
        $o .= '<label class="saas_category_label" for="map_polos['.$cat->id.']" >'.$cat->name.'</label>';
        $o .= '</td>';

        if(count($cat->courses) > 0) {
            $count=0;
            $depth += 2;
            foreach($cat->courses AS $c) {
                $count++;
                $class= $count%2==1 ? 'normalcolor' : 'alternatecolor';

                $o .= '<tr class="category '.$class.'">';
                $o .= '<td class="saas_category_name">';
                $o .= '<img class="saas_category_pix" style="padding-left: '.$depth.'%;"src="'.$OUTPUT->pix_url('i/course').'" />';
                $o .= '<label class="saas_category_label" for="map_polos['.$c->id.']" >'.$c->name.'</label>';
                $o .= '</td>';

                $poloid = empty($c->polo_id) ? 0 : $c->polo_id;
                $o .= '<td class="saas_polo_select">';
                $o .= '<select id="map_polos['.$c->id.']" name="map_polos['.$c->id.']">';

                $selected = $poloid == 0 ? 'selected="selected"' : '';
                $o .= '<option value="0" '.$selected.'>Escolher...</option>';
                foreach ($polos as $pid => $p) {
                    $selected = $poloid == $pid ? 'selected="selected"' : '';
                    $o .= '<option value="'.$pid.'" '.$selected.'>'.$p.'</option>';
                }
                $o .= '</select>';
                $o .= '</td>';
                $o .= '</tr>';
            }
        }

        if(!empty($cat->subs)) {
            $o .= saas_show_category_tree_map_courses_polos($cat->subs, $polos);
        }

    }
    return $o;
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

function saas_show_category_tree_map_categories_polos($categories, &$polos) {
    global $OUTPUT;

    $o = '';
    foreach($categories AS $cat) {
        $o .= '<tr class="category">';
        $o .= '<td class="saas_category_name">';
        $depth = $cat->depth*2;
        $o .= '<img class="saas_category_pix" style="padding-left: '.$depth.'%;"src="'.$OUTPUT->pix_url('i/course').'" />';
        $o .= '<label class="saas_category_label" for="map_polos['.$cat->id.']" >'.$cat->name.'</label>';
        $o .= '</td>';

        $poloid = empty($cat->polo_id) ? 0 : $cat->polo_id;
        $o .= '<td class="saas_polo_select">';
        $o .= '<select id="map_polos['.$cat->id.']" name="map_polos['.$cat->id.']">';

        $selected = $poloid == 0 ? 'selected="selected"' : '';
        $o .= '<option value="0" '.$selected.'>Escolher...</option>';
        foreach ($polos as $pid => $p) {
            $selected = $poloid == $pid ? 'selected="selected"' : '';
            $o .= '<option value="'.$pid.'" '.$selected.'>'.$p.'</option>';
        }
        $o .= '</select>';
        $o .= '</td>';
        $o .= '</tr>';

        if(!empty($cat->subs)) {
            $o .= saas_show_category_tree_map_categories_polos($cat->subs, $polos);
        }
    }
    return $o;
}

// Criação de tabelas para visualização dos dados.
//---------------------------------------------------------------------------------------------------

function saas_show_table_polos() {
    global $DB, $OUTPUT;

    $polos = $DB->get_records('saas_polos', array('enable'=>1), 'nome');

    print html_writer::start_tag('DIV', array('align'=>'center'));

    $table = new html_table();
    $table->head = array(get_string('nome_polo', 'report_saas_export'),
                         get_string('cidade', 'report_saas_export'),
                         get_string('estado', 'report_saas_export'));
    $table->attributes = array('class'=>'saas_table');
    $table->data = array();
    foreach($polos as $pl) {
        $table->data[] = array($pl->nome, $pl->cidade, $pl->estado);
    }
    print html_writer::table($table);

    print html_writer::end_tag('DIV');
}

function saas_show_overview_courses_polos($ocid, $poloid) {
    global $saas;

    $sql = "SELECT DISTINCT oc.id AS oc_id, sp.*
              FROM {saas_ofertas_cursos} oc
              JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
              JOIN {saas_map_course} cm ON (cm.oferta_disciplina_id = od.id)
              JOIN {course} c ON (c.id = cm.courseid)
              JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'course' AND smcp.instanceid = c.id)
              JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1)
             WHERE oc.enable = 1
          ORDER BY oc.id, sp.nome";
    saas_show_table_overview_polos($sql);

    if($ocid && $poloid) {
        list($sql, $params) = $saas->get_sql_users_by_oferta_curso_polo_courses($ocid, $poloid, false);
        saas_show_users_oferta_curso_polo($ocid, $poloid, $sql, $params);
    }
}

function saas_show_overview_categories_polos($ocid, $poloid) {
    global $saas;

    $sql = "SELECT DISTINCT oc.id AS oc_id, sp.*
              FROM {saas_ofertas_cursos} oc
              JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
              JOIN {saas_map_course} cm ON (cm.oferta_disciplina_id = od.id)
              JOIN {course} c ON (c.id = cm.courseid)
              JOIN {course_categories} cc ON (cc.id = c.category)
              JOIN {course_categories} ccp ON (ccp.id = cc.id OR cc.path LIKE CONCAT('%/',ccp.id,'/%'))
              JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'category' AND smcp.instanceid = ccp.id)
              JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1)
             WHERE oc.enable = 1
          ORDER BY oc.id, sp.nome";
    saas_show_table_overview_polos($sql);

    if($ocid && $poloid) {
        list($sql, $params) = $saas->get_sql_users_by_oferta_curso_polo_categories($ocid, $poloid, false);
        $saas->show_users_oferta_curso_polo($ocid, $poloid, $sql, $params);
    }
}

function saas_show_overview_groups_polos($ocid, $poloid) {
    $sql = "SELECT DISTINCT oc.id AS oc_id, sp.*
              FROM {saas_ofertas_cursos} oc
              JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
              JOIN {saas_map_course} cm ON (cm.oferta_disciplina_id = od.id)
              JOIN {course} c ON (c.id = cm.courseid)
              JOIN {groups} g ON (g.courseid = c.id)
              JOIN {saas_map_groups_polos} spm ON (spm.groupname = g.name)
              JOIN {saas_polos} sp ON (sp.id = spm.polo_id AND sp.enable = 1)
             WHERE oc.enable = 1
          ORDER BY oc.id, sp.nome";
    saas_show_table_overview_polos($sql);

    if($ocid && $poloid) {
        list($sql, $params) = $saas->get_sql_users_by_oferta_curso_polo_groups($ocid, $poloid, false);
        $saas->show_users_oferta_curso_polo($ocid, $poloid, $sql, $params);
    }
}

function saas_show_table_overview_polos($sql) {
    global $DB, $saas;

    $ofertas_cursos = $DB->get_records('saas_ofertas_cursos', array('enable'=>1), 'nome, ano, periodo');
    foreach($ofertas_cursos AS $id=>$oc) {
        $oc->polos = array();
    }
    foreach($DB->get_recordset_sql($sql) AS $pl) {
        $ofertas_cursos[$pl->oc_id]->polos[] = $pl;
    }

    $counts = $saas->get_polos_count();
    $role_types = $saas->get_role_types('polos');

    $data = array();
    $color = '#E0E0E0';
    foreach($ofertas_cursos AS $oc) {
        $color = $color == '#C0C0C0' ? '#E0E0E0 ' : '#C0C0C0';
        $rows = max(count($oc->polos), 1);
        $row = new html_table_row();

        $cell = new html_table_cell();
        $cell->text = $oc->nome;
        $cell->rowspan = $rows;
        $cell->style = "vertical-align: middle; background-color: {$color};";
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->text = "{$oc->ano}/{$oc->periodo}";
        $cell->rowspan = $rows;
        $cell->style = "vertical-align: middle; background-color: {$color};";
        $row->cells[] = $cell;

        if(empty($oc->polos)) {
            for($i=1; $i <= count($role_types)+1; $i++) {
                $cell = new html_table_cell();
                $cell->text = '';
                $cell->style = "background-color: {$color};";
                $row->cells[] = $cell;
            }
            $data[] = $row;
        } else {
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
                    $url = new moodle_url('/report/saas_export/index.php', array('action'=>'overview', 'data'=>'polos', 'ocid'=>$oc->id, 'poloid'=>$pl->id));
                    $cell->text = html_writer::link($url, $pl->nome);
                } else {
                    $cell->text = $pl->nome;
                }
                $cell->style = "background-color: {$color};";
                $row->cells[] = $cell;

                foreach($role_types AS $r) {
                    $cell = new html_table_cell();
                    $cell->text = $texts[$r];
                    $cell->style = "text-align: right; background-color: {$color};";
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
    foreach($role_types AS $r) {
        $table->head[] = get_string($r, 'report_saas_export');
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
    $title = "{$oc->nome} ({$oc->ano}/{$oc->periodo}) - {$polo->nome}";

    $role_types = $saas->get_role_types('polos');

    print html_writer::start_tag('DIV', array('align'=>'center'));

    $data = array();
    foreach($role_types AS $role) {
        $data[$role] = array();
    }
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach($rs AS $rec) {
        $user = $saas->get_user($rec->role, $rec->userid, $rec->uid);
        $data[$rec->role][] = array((count($data[$rec->role])+1) . '.', $user->uid, $user->nome, $user->email, $user->cpf);
    }

    foreach($role_types AS $role) {
        if(count($data[$role]) > 0) {
            print $OUTPUT->box_start('generalbox boxwidthwide');
            print $OUTPUT->heading(get_string($role . 's', 'report_saas_export') . ' => ' . $title, '4');

            $table = new html_table();
            $table->head = array('', get_string('username', 'report_saas_export'), get_string('name'), get_string('email'), get_string('cpf', 'report_saas_export'));
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
    $title = "{$oc->nome} ({$oc->ano}/{$oc->periodo}) - {$od->nome} " . $saas->format_date($od->inicio, $od->fim);

    print html_writer::start_tag('DIV', array('align'=>'center'));

    list($sql, $params) =  $saas->get_sql_users_by_oferta_disciplina(0, $ofer_disciplina_id);
    $rs = $DB->get_recordset_sql($sql, $params);
    $data = array();
    $role_types = $saas->get_role_types('disciplinas');
    foreach($role_types AS $r) {
        $data[$r] = array();
    }
    foreach($rs AS $rec) {
        $user = $saas->get_user($rec->role, $rec->userid, $rec->uid);
        $row = array((count($data[$rec->role])+1) . '.', $user->uid, $user->nome, $user->email, $user->cpf);
        if($rec->role == 'student') {
            $row[] = $rec->suspended == 1 ? html_writer::tag('span', get_string('yes'), array('class'=>'saas_export_warning')) : get_string('no');
            $row[] = empty($rec->currentlogin) ? '-' : date('d-m-Y H:i', $rec->currentlogin);
            $row[] = empty($rec->lastaccess) ? '-' : date('d-m-Y H:i', $rec->lastaccess);
            $row[] = isset($grades[$rec->userid]) && $grades[$rec->userid] >= 0 ? $grades[$rec->userid] : '-';
        }
        $data[$rec->role][] = $row;
    }

    foreach($role_types AS $r) {
        if(count($data[$r]) > 0) {
            print $OUTPUT->box_start('generalbox boxwidthwide');
            print $OUTPUT->heading(get_string($r . 's', 'report_saas_export') . ' => ' . $title, '4');

            $table = new html_table();
            $table->head = array('', get_string('username', 'report_saas_export'), get_string('name'), get_string('email'), get_string('cpf', 'report_saas_export'));
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
            $table->attributes = array('class'=>'saas_table');
            $table->data = $data[$r];
            print html_writer::table($table);
            print $OUTPUT->box_end();
        }
    }

    print html_writer::end_tag('DIV');
}

function saas_show_table_ofertas_curso_disciplinas($show_counts=false) {
    global $DB, $saas;

    $data = array();
    $color = '#E0E0E0';

    if($show_counts) {
        list($sql, $params) =  $saas->get_sql_users_by_oferta_disciplina(0, 0, true);
        $rs = $DB->get_recordset_sql($sql, $params);
        $ofertas_disciplinas_counts = array();
        foreach($rs AS $rec) {
            $ofertas_disciplinas_counts[$rec->od_id][$rec->role] = $rec->count;
        }
    }

    $role_types = $saas->get_role_types('disciplinas');
    foreach($saas->get_ofertas() AS $oc_id=>$oc) {
        $color = $color == '#C0C0C0' ? '#E0E0E0 ' : '#C0C0C0';
        $rows = max(count($oc->ofertas_disciplinas), 1);
        $row = new html_table_row();

        $cell = new html_table_cell();
        $cell->text = $oc->nome;
        $cell->rowspan = $rows;
        $cell->style = "vertical-align: middle; background-color: {$color};";
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->text = $oc->ano. '/'.$oc->periodo;
        $cell->rowspan = $rows;
        $cell->style = "vertical-align: middle; background-color: {$color};";
        $row->cells[] = $cell;

        if(empty($oc->ofertas_disciplinas)) {
            $data[] = $row;
            for($i=1 ; $i <= count($role_types)+3; $i++) {
                $cell = new html_table_cell();
                $cell->text = '';
                $cell->style = "background-color: {$color};";
                $row->cells[] = $cell;
            }
        } else {
            foreach($oc->ofertas_disciplinas AS $od_id=>$od) {
                $cell = new html_table_cell();
                if($show_counts) {
                    $texts = array();
                    $show_url = false;
                    foreach($role_types AS $r) {
                        if($od->mapped) {
                            if(isset($ofertas_disciplinas_counts[$od_id][$r]) && $ofertas_disciplinas_counts[$od_id][$r] > 0) {
                                $texts[$r] = $ofertas_disciplinas_counts[$od_id][$r];
                                $show_url = true;
                            } else {
                                $texts[$r] = 0;
                            }
                        } else {
                            $texts[$r] = '-';
                        }
                    }
                    if($show_url) {
                        $url = new moodle_url('/report/saas_export/index.php', array('action'=>'overview', 'data'=>'ofertas', 'odid'=>$od_id));
                        $cell->text = html_writer::link($url, $od->nome);
                    } else {
                        $cell->text = $od->nome;
                    }
                } else {
                    $cell->text = $od->nome;
                }
                $cell->style = "background-color: {$color};";
                $row->cells[] = $cell;

                $cell = new html_table_cell();
                $cell->text = $saas->format_date($od->inicio);
                $cell->style = "background-color: {$color};";
                $row->cells[] = $cell;

                $cell = new html_table_cell();
                $cell->text = $saas->format_date($od->fim);
                $cell->style = "background-color: {$color};";
                $row->cells[] = $cell;

                if($show_counts) {
                    foreach($role_types AS $r) {
                        $cell = new html_table_cell();
                        $cell->text = $texts[$r];
                        $cell->style = "text-align: right; background-color: {$color};";
                        $row->cells[] = $cell;
                    }
                }

                $data[] = $row;
                $row = new html_table_row();
            }
        }
    }

    print html_writer::start_tag('DIV', array('align'=>'center'));
    $table = new html_table();
    $table->head = array('Oferta de Curso', 'Período', 'Oferta de disciplina', 'Início', 'Fim');
    if($show_counts) {
        foreach($role_types AS $r) {
            $table->head[] = get_string($r, 'report_saas_export');
        }
    }
    $table->attributes = array('class'=>'saas_table');
    $table->data = $data;
    print html_writer::table($table);
    print html_writer::end_tag('DIV');
}

function saas_show_export_options($url, $selected_ocs=true, $selected_ods=true, $selected_polos=true) {
    global $DB, $saas;

    $data = array();
    $color = '#E0E0E0';

    list($sql, $params) =  $saas->get_sql_users_by_oferta_disciplina(0, 0, true);
    $rs = $DB->get_recordset_sql($sql, $params);
    $od_counts = array();
    foreach($rs AS $rec) {
        $od_counts[$rec->od_id] = true;
    }

    $polos = $saas->get_polos();
    $polos_count = $saas->get_polos_count();

    $ofertas = $saas->get_ofertas();
    foreach($ofertas AS $oc_id=>$oc) {
        foreach(array_keys($oc->ofertas_disciplinas) AS $od_id) {
            if(!isset($od_counts[$od_id])) {
                unset($ofertas[$oc_id]->ofertas_disciplinas[$od_id]);
            }
        }

        $ofertas[$oc_id]->polos = array();
        if(isset($polos_count[$oc_id])) {
            foreach($polos_count[$oc_id] AS $poloid=>$counts) {
                $ofertas[$oc_id]->polos[$poloid] = $polos[$poloid];
            }
        }
    }


    $rows = array();
    foreach($ofertas AS $oc_id=>$oc) {
        if(!empty($oc->ofertas_disciplinas) || !empty($oc->polos)) {
            $color = $color == '#C0C0C0' ? '#E0E0E0 ' : '#C0C0C0';

            $row = new html_table_row();

            $cell = new html_table_cell();
            $checked = $selected_ocs===true || isset($selected_ocs[$oc_id]);
            $checkbox = html_writer::checkbox("oc[{$oc_id}]", $oc_id, $checked);
            $cell->text = $checkbox . $oc->nome;
            $cell->style = "vertical-align: middle; background-color: {$color};";
            $row->cells[] = $cell;

            $cell = new html_table_cell();
            $cell->text = $oc->ano. '/'.$oc->periodo;
            $cell->style = "vertical-align: middle; background-color: {$color};";
            $row->cells[] = $cell;

            $cell = new html_table_cell();
            if(!empty($oc->ofertas_disciplinas)) {
                $cell->text = html_writer::start_tag('UL', array('style'=>'list-style-type: none;'));
                foreach($oc->ofertas_disciplinas AS $od) {
                    $checked = $selected_ocs===true || isset($selected_ods[$oc_id][$od->id]);
                    $checkbox = html_writer::checkbox("od[{$oc_id}][{$od->id}]", $oc_id, $checked);
                    $text = $checkbox . $od->nome . ' (' . $saas->format_date($od->inicio, $od->fim) . ')';
                    $cell->text .= html_writer::tag('LI', $text);
                }
                $cell->text .= html_writer::end_tag('UL');
            }
            $cell->style = "vertical-align: middle; background-color: {$color};";
            $row->cells[] = $cell;

            $cell = new html_table_cell();
            if(!empty($oc->polos)) {
                $cell->text = html_writer::start_tag('UL', array('style'=>'list-style-type: none;'));
                foreach($oc->polos AS $pl) {
                    $checked = $selected_ocs===true || isset($selected_polos[$oc_id][$pl->id]);
                    $checkbox = html_writer::checkbox("polo[{$oc_id}][{$pl->id}]", $oc_id, $checked);
                    $text = $checkbox . $pl->nome;
                    $cell->text .= html_writer::tag('LI', $text);
                }
                $cell->text .= html_writer::end_tag('UL');
            }
            $cell->style = "vertical-align: middle; background-color: {$color};";
            $row->cells[] = $cell;

            $rows[] = $row;
        }
    }

    print html_writer::start_tag('DIV', array('align'=>'center'));
    print html_writer::start_tag('form', array('method'=>'post', 'action'=>$url));

    $table = new html_table();
    $table->head = array('Oferta de Curso', 'Período', 'Ofertas de disciplina', 'Polos');
    $table->attributes = array('class'=>'saas_table');
    $table->colclasses = array('leftalign', 'leftalign', 'leftalign', 'leftalign');
    $table->data = $rows;
    print html_writer::table($table);

    print html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'export', 'value'=>s(get_string('saas_export:export', 'report_saas_export'))));
    print html_writer::end_tag('form');
    print html_writer::end_tag('DIV');
}
