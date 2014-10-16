<?php

// ----------------------------------------------------------------------------------------------
// Rotinas auxiliares para mapeamento de ofertas de disciplina para cursos Moodle

function saas_show_nome_instituicao() {
    global $saas, $OUTPUT;

    print html_writer::tag('DIV', $OUTPUT->heading('Instituição: ' . $saas->get_config('nome_instituicao'), 3), array('align'=>'center'));
}

function saas_show_categories_tree($group_map_id) {
    global $DB, $SESSION, $PAGE, $OUTPUT, $saas;

    $PAGE->requires->js_init_call('M.report_saas_export.init');
    $concat_category = saas::get_concat_category();

    // Hierarquia de categorias com cursos a selecionar (não vazias)
    $sql = "SELECT DISTINCT ccp.id, ccp.depth, ccp.path, ccp.name
              FROM {course_categories} ccp
              JOIN (SELECT DISTINCT cc.id, cc.path
                      FROM {course_categories} cc
                      JOIN {course} c ON (c.category = cc.id)
                 LEFT JOIN (SELECT DISTINCT smc.courseid
                              FROM {saas_ofertas_disciplinas} od
                              JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = od.api_key)
                              JOIN {saas_map_course} smc ON (smc.group_map_id = od.group_map_id)
                             WHERE od.enable = 1) cr
                        ON (cr.courseid = c.id)
                     WHERE c.id > 1
                       AND cr.courseid IS NULL) cat
                ON (ccp.id = cat.id OR cat.path LIKE {$concat_category})
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
         LEFT JOIN (SELECT DISTINCT smc.courseid
                      FROM {saas_ofertas_disciplinas} od
                      JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = od.api_key)
                      JOIN {saas_map_course} smc ON (smc.group_map_id = od.group_map_id)
                     WHERE od.enable = 1) cr
                ON (cr.courseid = c.id)
             WHERE c.id > 1
               AND cr.courseid IS NULL
          ORDER BY c.fullname";
    $courses = $DB->get_records_sql($sql);
    foreach($courses AS $course) {
        $categories[$course->category]->courses[$course->id] = $course;
    }

    foreach(array_keys($categories) AS $catid) {
        if($categories[$catid]->depth > 1) {
            unset($categories[$catid]);
        }
    }

    $sql = "SELECT od.*, d.nome
              FROM {saas_ofertas_disciplinas} od
              JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = od.api_key)
              JOIN {saas_disciplinas} d ON (d.uid = od.disciplina_uid AND d.enable = 1 AND d.api_key = od.api_key)
             WHERE od.enable = 1
               AND od.group_map_id = :group_map_id";
    $ods = $DB->get_records_sql($sql, array('group_map_id'=>$group_map_id));
    $best_options = array();
    if(count($ods) == 1) {
        $od = reset($ods);
        $title_ods = 'Disciplina: ' . html_writer::tag('font', $od->nome . ' (' . $saas->format_date($od->inicio, $od->fim) . ')', array('color'=>'darkblue'));

        if(count($ods) == 1) {
            $distances = array();
            foreach($courses AS $c) {
                $distances[$c->id] = levenshtein($c->fullname, $od->nome);
            }
            asort($distances);
            $count = 0;
            foreach($distances AS $cid=>$dist) {
                $c = $courses[$cid];
                $c->distance = $dist;
                $best_options[$cid] = $c;

                $count++;
                if($count >= 5) {
                    break;
                }
            }
        }

    } else {
        $title_ods = '';
        foreach($ods AS $od) {
            $title_ods .= html_writer::tag('LI', $od->nome . ' (' . $saas->format_date($od->inicio, $od->fim) . ')');
        }
        $title_ods = 'Disciplinas:' . html_writer::tag('UL', html_writer::tag('font', $title_ods, array('color'=>'darkblue')));
    }

    $oc = $saas->get_oferta_curso($od->oferta_curso_uid);
    $cancel_url = new moodle_url('index.php', array('action'=>'course_mapping', 'subaction'=>'ofertas', 'ocid'=>$oc->id));

    echo html_writer::start_tag('div', array('align'=>'center'));
    echo $OUTPUT->heading(get_string('course_selection', 'report_saas_export') .
                    $OUTPUT->help_icon('course_selection', 'report_saas_export'), 3);
    echo html_writer::end_tag('div', array('align'=>'center'));

    echo html_writer::start_tag('div', array('class'=>'saas_tree saas_area_normal'));
    print $OUTPUT->heading('Curso: '. html_writer::tag('font', "{$oc->nome} ({$oc->ano}/{$oc->periodo})", array('color'=>'darkblue')), 4);
    print $OUTPUT->heading($title_ods, 4);
    echo html_writer::end_tag('div');

    if(!empty($best_options)) {
        echo html_writer::start_tag('div', array('align'=>'center'));
        print html_writer::empty_tag('BR');
        print $OUTPUT->heading('Opções com nomes mais similares', 3);
        print $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');
        $table = new html_table();
        $table->attributes = array('class'=>'saas_table');
        $table->head = array('Curso Moodle', 'Categoria', 'Distância Levenshtein');
        $table->colclasses = array('leftalign', 'leftalign', 'centeralign');
        $table->tablealign = 'center';
        $table->cellpadding = 5;

        $table->data = array();
        foreach($best_options AS $c) {
            $cat_names = saas::get_concatenated_categories_names($c->category, '/ ');
            $url = new moodle_url('index.php', array('action'=>'course_mapping', 'subaction'=>'add', 'courseid'=>$c->id,'group_map_id'=>$group_map_id));
            $link = html_writer::link($url, $c->fullname, array('title'=>'Clique para selecionar este curso'));
            $table->data[] = array($link, html_writer::tag('small', $cat_names), $c->distance);
        }

        print html_writer::table($table);

        print $OUTPUT->single_button($cancel_url, get_string('cancel'));

        print $OUTPUT->box_end();
        echo html_writer::end_tag('div');
    }

    echo html_writer::start_tag('div', array('align'=>'center'));
    print $OUTPUT->heading('Hierarquia de classe/cursos Moodle disponíveis para seleção', 3);
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', array('class'=>'saas_tree saas_area_normal'));
    echo html_writer::start_tag('ul');
    saas_show_categories($group_map_id, $categories, $category_path);
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', array('align'=>'center'));
    echo $OUTPUT->single_button($cancel_url, get_string('cancel'));
    echo html_writer::end_tag('div');
}

function saas_show_categories($group_map_id, &$categories, $open_catids = array()){
    global $OUTPUT;

    foreach ($categories as $cat){
        echo html_writer::start_tag('li');
        $label = 'cat_'.$cat->id;
        $report_path = strpos(__FILE__, '/admin/report/') !== false ? '/admin/report' : '/report';
        $img_folder = html_writer::empty_tag('img', array('class'=>'saas_img_folder',
                                      'src' => new moodle_url($report_path . '/saas_export/img/folder.png')));
        echo html_writer::tag('label', $img_folder . $cat->name, array('for'=>$label));
        $checked = in_array($cat->id, $open_catids);
        echo html_writer::checkbox(null, null, $checked, '', array('id'=>$label));

        echo html_writer::start_tag('ul');

        foreach ($cat->courses as $c){
            echo html_writer::start_tag('li', array('class'=>'course'));
            echo $OUTPUT->pix_icon("i/course", '', 'moodle', array('class' => 'icon smallicon'));
            $url = new moodle_url('index.php', array('action'=>'course_mapping', 'subaction'=>'add', 'courseid'=>$c->id,'group_map_id'=>$group_map_id));
            echo html_writer::link($url, $c->fullname, array('title'=>'Clique para selecionar este curso'));
            echo html_writer::end_tag('li');
        }

        if(!empty($cat->subs)){
            saas_show_categories($group_map_id, $cat->subs, $open_catids);
        }

        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('li');
    }
}

// ----------------------------------------------------------------------------------------------
// Rotinas auxiliara para mapeamento de cursos para categorias

function saas_get_category_tree_map_courses_polos() {
    global $DB, $saas;

    $concat_category = saas::get_concat_category();
    $sql = "SELECT DISTINCT ccp.id, ccp.depth, ccp.path, ccp.name
              FROM {saas_ofertas_disciplinas} od
              JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = od.api_key)
              JOIN {saas_map_course} smc ON (smc.group_map_id = od.group_map_id)
              JOIN {course} c ON (c.id = smc.courseid)
              JOIN {course_categories} cc ON (cc.id = c.category)
              JOIN {course_categories} ccp ON (ccp.id = cc.id OR cc.path LIKE {$concat_category})
             WHERE od.enable = 1
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

    $sql = "SELECT DISTINCT c.id, c.category, c.fullname AS name, jp.polo_id
              FROM {saas_ofertas_disciplinas} od
              JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = od.api_key)
              JOIN {saas_map_course} smc ON (smc.group_map_id = od.group_map_id)
              JOIN {course} c ON (c.id = smc.courseid)
         LEFT JOIN (SELECT DISTINCT smcp.instanceid as courseid, smcp.polo_id
                      FROM {saas_polos} pl
                      JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = pl.api_key)
                      JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'course' AND smcp.polo_id = pl.id)
                     WHERE pl.enable = 1) jp
                ON (jp.courseid = c.id)
             WHERE od.enable = 1
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
        $row->attributes['class'] = 'saas_level' . $cat->depth;

        $cell = new html_table_cell();
        $cell->attributes['class'] = 'saas_level' . $cat->depth;
        $padding = ($cat->depth-1)*18;

        $report_path = strpos(__FILE__, '/admin/report/') !== false ? '/admin/report' : '/report';
        $img_folder = html_writer::empty_tag('img', array('class'=>'saas_pix', 'style'=>"padding-left: {$padding}px;",
                                      'src' => new moodle_url($report_path . '/saas_export/img/folder.png')));

        $cell->text = $img_folder . $cat->name;
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->attributes['class'] = 'saas_level' . $cat->depth;
        $row->cells[] = $cell;

        $rows[] = $row;

        if(count($cat->courses) > 0) {
            $color_class = 'saas_normalcolor';
            foreach($cat->courses AS $c) {
                $color_class = $color_class == 'saas_normalcolor' ? 'saas_alternatecolor' : 'saas_normalcolor';
                $row = new html_table_row();
                $row->attributes['class'] = $color_class;

                $cell = new html_table_cell();
                $cell->attributes['class'] = $color_class;
                $padding = $cat->depth*18;
                $cell->text = html_writer::empty_tag('img', array('src'=> $OUTPUT->pix_url('i/course'), 'class'=>'saas_pix', 'style'=>"padding-left: {$padding}px;"));
                $cell->text .= $c->name;
                $row->cells[] = $cell;

                $poloid = empty($c->polo_id) ? 0 : $c->polo_id;
                $cell = new html_table_cell();
                $cell->attributes['class'] = $color_class;
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
    global $DB, $saas;

    $concat_category = saas::get_concat_category();
    $sql = "SELECT DISTINCT ccp.id, ccp.depth, ccp.path, ccp.name, jp.polo_id
              FROM {saas_ofertas_disciplinas} od
              JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = od.api_key)
              JOIN {saas_map_course} smc ON (smc.group_map_id = od.group_map_id)
              JOIN {course} c ON (c.id = smc.courseid)
              JOIN {course_categories} cc ON (cc.id = c.category)
              JOIN {course_categories} ccp ON (ccp.id = cc.id OR cc.path LIKE {$concat_category})
         LEFT JOIN (SELECT DISTINCT smcp.instanceid as catid, smcp.polo_id
                      FROM {saas_polos} pl
                      JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = pl.api_key)
                      JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'category' AND smcp.polo_id = pl.id)
                     WHERE pl.enable = 1) jp
                ON (jp.catid = ccp.id)
             WHERE od.enable = 1
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
        $row->attributes['class'] = 'saas_level' . $cat->depth;

        $cell = new html_table_cell();
        $cell->attributes['class'] = 'saas_level' . $cat->depth;
        $padding = ($cat->depth-1)*18;

        $report_path = strpos(__FILE__, '/admin/report/') !== false ? '/admin/report' : '/report';
        $img_folder = html_writer::empty_tag('img', array('class'=>'saas_pix', 'style'=>"padding-left: {$padding}px;",
                                      'src' => new moodle_url($report_path . '/saas_export/img/folder.png')));
        $cell->text = $img_folder . $cat->name;
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->attributes['class'] = 'saas_level' . $cat->depth;
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
    global $DB, $OUTPUT, $saas;

    $polos = $saas->get_polos();

    print html_writer::start_tag('DIV', array('class'=>'saas_area_normal'));

    if(empty($polos)) {
        print $OUTPUT->heading(get_string('no_polos', 'report_saas_export'), 4);
    } else {
        $rows = array();
        $index = 0;
        foreach($polos as $pl) {
            $index++;
            $row = array($index . '.');
            $row[] = $pl->nome;
            $row[] = $pl->cidade;
            $row[] = $pl->estado;
            $rows[] = $row;
        }

        $table = new html_table();
        $table->head = array('',
                             get_string('nome_polo', 'report_saas_export'),
                             get_string('cidade', 'report_saas_export'),
                             get_string('estado', 'report_saas_export'));
        $table->colclasses = array('leftalign', 'leftalign', 'leftalign', 'leftalign');
        $table->data = $rows;

        print $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');
        $table->attributes = array('class'=>'saas_table');
        $table->tablealign = 'center';
        $table->cellpadding = 5;
        print html_writer::table($table);
        print $OUTPUT->box_end();
    }

    print html_writer::end_tag('DIV');
}

function saas_show_overview_polos($ocid, $poloid) {
    global $DB, $saas;

    $polo_mapping_type = $saas->get_config('polo_mapping');

    if(!$saas->has_polo()) {
        print html_writer::tag('h3', get_string('no_polos', 'report_saas_export'));
        return;
    }
    if($polo_mapping_type == 'no_polo') {
        print $OUTPUT->heading(get_string('title_no_polo', 'report_saas_export'), 4);
        return;
    }

    $url = new moodle_url('index.php', array('action'=>'overview', 'subaction'=>'polos'));
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
        $polos = $saas->get_polos_by_oferta_curso($ocid);
        saas_show_table_overview_polos($polos);
    }
}

function saas_show_table_overview_polos($polos) {
    global $DB, $OUTPUT, $saas;

    $ofertas_cursos = $saas->get_ofertas_cursos();

    $counts = $saas->get_polos_count();
    $role_types = $saas->get_role_types('polos');

    foreach($ofertas_cursos AS $oc) {
        if(!isset($polos[$oc->id])) {
            continue;
        }

        $rows = array();
        $index = 0;
        foreach($polos[$oc->id] AS $pl) {
            $index++;
            $row = array($index.'.');

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

            if($show_url) {
                $url = new moodle_url('index.php', array('action'=>'overview', 'subaction'=>'polos', 'ocid'=>$oc->id, 'poloid'=>$pl->id));
                $row[] = html_writer::link($url, $pl->nome);
            } else {
                $row[] = $pl->nome;
            }
            $row[] = $pl->cidade;
            $row[] = $pl->estado;

            foreach($role_types AS $r) {
                $row[] = $texts[$r];
            }

            $rows[] = $row;
        }

        print html_writer::start_tag('DIV', array('class'=>'saas_area_normal'));
        print $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');
        print $OUTPUT->heading("{$oc->nome} ({$oc->ano}/{$oc->periodo})", 3);

        $table = new html_table();
        $table->head = array('', get_string('nome_polo', 'report_saas_export'), 'Cidade', 'UF');
        $table->colclasses = array('leftalign',  'leftalign', 'leftalign', 'leftalign');
        foreach($role_types AS $r) {
            $table->head[] = get_string($r, 'report_saas_export');
            $table->colclasses[] = 'rightalign';
        }
        $table->data = $rows;
        $table->attributes = array('class'=>'saas_table');
        $table->tablealign = 'center';
        $table->cellpadding = 5;
        print html_writer::table($table);

        print $OUTPUT->box_end();
        print html_writer::end_tag('DIV');
    }
}

function saas_show_users_oferta_curso_polo($ocid, $poloid, $sql, $params) {
    global $DB, $OUTPUT, $saas;

    $oc = $DB->get_record('saas_ofertas_cursos', array('id'=>$ocid));
    $polo = $DB->get_record('saas_polos', array('id'=>$poloid));

    $role_types = $saas->get_role_types('polos');

    print $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');
    print $OUTPUT->heading("Curso: {$oc->nome} ({$oc->ano}/{$oc->periodo})", 3);
    print $OUTPUT->heading("Polo: {$polo->nome} ({$polo->cidade}/{$polo->estado})", 3);
    print $OUTPUT->box_end();

    $rows = array();
    foreach($role_types AS $role) {
        $rows[$role] = array();
    }
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach($rs AS $rec) {
        $user = $saas->get_user($rec->role, $rec->userid, $rec->uid);
        $rows[$rec->role][] = array((count($rows[$rec->role])+1) . '.', $user->nome, $user->uid, $user->email, $user->cpf);
    }

    foreach($role_types AS $role) {
        if(count($rows[$role]) > 0) {
            print html_writer::start_tag('DIV', array('class'=>'saas_area_normal'));
            print $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');

            print $OUTPUT->heading(get_string($role . 's', 'report_saas_export'), 3);

            $table = new html_table();
            $table->head = array('', get_string('name'), get_string('username', 'report_saas_export'), get_string('email'), get_string('cpf', 'report_saas_export'));
            $table->data = $rows[$role];
            $table->attributes = array('class'=>'saas_table');
            $table->tablealign = 'center';
            $table->cellpadding = 5;
            print html_writer::table($table);
            print $OUTPUT->box_end();
            print html_writer::end_tag('DIV');
        }
    }

}

function saas_show_users_oferta_disciplina($ofer_disciplina_id) {
    global $DB, $OUTPUT, $saas;

    $grades = $saas->get_grades($ofer_disciplina_id);

    $od = $saas->get_oferta_disciplina($ofer_disciplina_id);
    $oc = $DB->get_record('saas_ofertas_cursos', array('uid'=>$od->oferta_curso_uid));

    print $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');
    print $OUTPUT->heading("Curso: {$oc->nome} ({$oc->ano}/{$oc->periodo})", 3);
    print $OUTPUT->heading("Disciplina: {$od->nome} ". $saas->format_date($od->inicio, $od->fim), 3);
    print $OUTPUT->box_end();

    list($sql, $params) =  $saas->get_sql_users_by_oferta_disciplina(0, $ofer_disciplina_id);
    $rs = $DB->get_recordset_sql($sql, $params);
    $rows = array();
    $suspended = array();
    $role_types = $saas->get_role_types('disciplinas');
    foreach($role_types AS $r) {
        $rows[$r] = array();
    }
    foreach($rs AS $rec) {
        if(empty($rec->suspended)) {
            $user = $saas->get_user($rec->role, $rec->userid, $rec->uid);
            $row = array((count($rows[$rec->role])+1) . '.', $user->nome, $user->uid, $user->email, $user->cpf);
            if($rec->role == 'student') {
                $row[] = get_string('no');
                $row[] = empty($rec->currentlogin) ? '-' : date('d-m-Y H:i', $rec->currentlogin);
                $row[] = empty($rec->lastaccess) ? '-' : date('d-m-Y H:i', $rec->lastaccess);
                $row[] = isset($grades[$rec->userid]) && $grades[$rec->userid] >= 0 ? $grades[$rec->userid] : '-';
            }
            $rows[$rec->role][] = $row;
        } else {
            $suspended[] = $rec;
        }
    }

    foreach($suspended AS $rec) {
        $user = $saas->get_user($rec->role, $rec->userid, $rec->uid);
        $row = new html_table_row();

        $row->cells[] = (count($rows[$rec->role])+1) . '.';
        $row->cells[] = $user->nome;
        $row->cells[] = $user->uid;
        $row->cells[] = $user->email;
        $row->cells[] = $user->cpf;
        $row->cells[] = html_writer::tag('span', get_string('yes'), array('class'=>'saas_export_warning'));
        $row->cells[] = empty($rec->currentlogin) ? '-' : date('d-m-Y H:i', $rec->currentlogin);
        $row->cells[] = empty($rec->lastaccess) ? '-' : date('d-m-Y H:i', $rec->lastaccess);
        $row->cells[] = isset($grades[$rec->userid]) && $grades[$rec->userid] >= 0 ? $grades[$rec->userid] : '-';

        $rows[$rec->role][] = $row;
    }

    foreach($role_types AS $r) {
        if(count($rows[$r]) > 0) {
            $table = new html_table();
            $table->head = array('', get_string('name'), get_string('username', 'report_saas_export'), get_string('email'), get_string('cpf', 'report_saas_export'));
            $table->colclasses = array('rightalign', 'leftalign', 'leftalign', 'leftalign', 'leftalign');
            if($r == 'student') {
                print html_writer::start_tag('DIV', array('class'=>'saas_area_large'));
                print $OUTPUT->box_start('generalbox boxaligncenter');

                $table->head[] = get_string('suspended', 'report_saas_export');
                $table->head[] = get_string('lastlogin');
                $table->head[] = get_string('lastcourseaccess', 'report_saas_export');
                $table->head[] = get_string('finalgrade', 'grades');
                $table->colclasses[] = 'centeralign';
                $table->colclasses[] = 'centeralign';
                $table->colclasses[] = 'centeralign';
                $table->colclasses[] = 'rightalign';
            } else {
                print html_writer::start_tag('DIV', array('class'=>'saas_area_normal'));
                print $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');
            }
            $table->data = $rows[$r];
            $table->attributes['class'] = 'saas_table';

            print $OUTPUT->heading(get_string($r . 's', 'report_saas_export'), 3);
            $table->attributes = array('class'=>'saas_table');
            $table->tablealign = 'center';
            $table->cellpadding = 5;
            print html_writer::table($table);
            print $OUTPUT->box_end();
            print html_writer::end_tag('DIV');
        }
    }
}

function saas_show_menu_ofertas_cursos($oferta_curso_id=0, $url) {
    global $PAGE, $DB, $saas;

    // obtem ofertas de curso
    $ofertas_cursos = $saas->get_ofertas_cursos();
    $ofertas_menu = array();
    $ofertas_menu[0] = get_string('all');
    foreach($ofertas_cursos AS $ocid=>$oc) {
        $ofertas_menu[$ocid] = "{$oc->nome} ({$oc->ano}/{$oc->periodo})";
    }

    print html_writer::start_tag('DIV', array('class'=>'saas_oferta_curso_menu'));

    print html_writer::start_tag('DIV', array('style'=>'position:relative; float: right;'));
    print html_writer::start_tag('form', array('method'=>'post', 'action'=>$url));
    print html_writer::select($ofertas_menu, 'ocid', $oferta_curso_id, false);
    print html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'list', 'value'=>get_string('list')));
    print html_writer::end_tag('form');
    print html_writer::end_tag('DIV');

    print html_writer::tag('DIV', get_string('oferta_curso', 'report_saas_export') . ': ', array('style'=>'position:relative; float: right;'));

    print html_writer::end_tag('DIV');

}

function saas_show_table_ofertas_curso_disciplinas($oferta_curso_id=0, $show_counts=false) {
    global $OUTPUT, $DB, $saas;

    if($show_counts) {
        list($sql, $params) =  $saas->get_sql_users_by_oferta_disciplina($oferta_curso_id, 0, true);
        $rs = $DB->get_recordset_sql($sql, $params);
        $ofertas_disciplinas_counts = array();
        foreach($rs AS $rec) {
            $ofertas_disciplinas_counts[$rec->odid][$rec->role] = $rec->count;
        }

        print html_writer::start_tag('DIV', array('align'=>'center'));
        print $OUTPUT->box_start('generalbox boxaligncenter');
        print html_writer::tag('html','Clique no nome da oferta de discplina para visualizar detalhes sobre dados a serem exportados.');
        print $OUTPUT->box_end();
        print html_writer::end_tag('DIV');
    }

    print html_writer::start_tag('DIV', array('class'=>'saas_area_large'));

    $role_types = $saas->get_role_types('disciplinas');
    $ofertas = $saas->get_ofertas($oferta_curso_id);
    if(empty($ofertas)) {
        print $OUTPUT->heading(get_string('no_ofertas_cursos', 'report_saas_export'), 4);
    } else {
        foreach($ofertas AS $ocid=>$oc) {
            $rows = array();
            $index = 0;

            foreach($oc->ofertas_disciplinas AS $odid=>$od) {
                $index++;
                $row = array($index . '.');
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
                        $url = new moodle_url('index.php', array('action'=>'overview', 'subaction'=>'ofertas', 'ocid'=>$ocid, 'odid'=>$odid));
                        $row[] = html_writer::link($url, $od->nome);
                    } else {
                        $row[] = $od->nome;
                    }
                } else {
                    $row[] = $od->nome;
                }

                $row[] = $saas->format_date($od->inicio);
                $row[] = $saas->format_date($od->fim);

                if($show_counts) {
                    foreach($role_types AS $r) {
                        $row[] = $texts[$r];
                    }
                }

                $rows[] = $row;
            }

            $table = new html_table();
            $table->head = array('', 'Oferta de disciplina', 'Início', 'Fim');
            $table->colclasses = array('leftalign', 'leftalign', 'leftalign', 'leftalign');
            if($show_counts) {
                foreach($role_types AS $r) {
                    $table->head[] = get_string($r, 'report_saas_export');
                    $table->colclasses[] = 'rightalign';
                }
            }
            $table->data = $rows;

            if($show_counts) {
                print $OUTPUT->box_start('generalbox boxaligncenter');
            } else {
                print $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');
            }
            print $OUTPUT->heading("{$oc->nome} ({$oc->ano}/{$oc->periodo})", 3);
            $table->attributes = array('class'=>'saas_table');
            $table->tablealign = 'center';
            $table->cellpadding = 5;
            print html_writer::table($table);
            print $OUTPUT->box_end();
        }
    }
    print html_writer::end_tag('DIV');
}

function saas_show_export_options($url, $selected_ocs=true, $selected_ods=true, $selected_polos=true) {
    global $DB, $saas, $PAGE, $OUTPUT;

    $PAGE->requires->js_init_call('M.report_saas_export.init');

    $ofertas_cursos = $saas->get_ofertas_cursos();
    $ofertas_disciplinas_oc = $saas->get_ofertas_disciplinas(0, true);

    $show_polos = $saas->get_config('polo_mapping') != 'no_polo';
    $polos_oc = $show_polos ? $saas->get_polos_by_oferta_curso() : array();

    if(empty($ofertas_cursos)) {
        print $OUTPUT->heading(get_string('no_ofertas_cursos', 'report_saas_export'), 4);
        return;
    }

    $rows = array();
    $show_form = false;
    foreach($ofertas_cursos AS $ocid=>$oc) {
        if(isset($ofertas_disciplinas_oc[$ocid]) || ($show_polos && !empty($polos_oc[$ocid]))) {
            $tag_checkbox = 'saas_oc_' . $ocid;
            $row = new html_table_row();

            $cell = new html_table_cell();
            $checked = $selected_ocs===true || isset($selected_ocs[$ocid]);
            $disabled = $checked ? array('disabled'=>true) : array();
            $lable = "{$oc->nome} ({$oc->ano}/{$oc->periodo})";
            $cell->text = html_writer::checkbox("oc[{$ocid}]", $ocid, $checked, $lable, array('id'=>$tag_checkbox, 'class'=>'oc_checkbox'));
            $cell->style = "vertical-align: middle;";
            $row->cells[] = $cell;

            $cell = new html_table_cell();
            if(isset($ofertas_disciplinas_oc[$ocid])) {
                $show_form = true;
                $cell->text = html_writer::start_tag('UL', array('style'=>'list-style-type: none;'));
                $params = array_merge($disabled, array('class'=>'od_'.$tag_checkbox));
                foreach($ofertas_disciplinas_oc[$ocid] AS $odid=>$od) {
                    $checked = $selected_ocs===true || isset($selected_ods[$ocid][$odid]);
                    $label = $od->nome . ' (' . $saas->format_date($od->inicio, $od->fim) . ')';
                    $checkbox = html_writer::checkbox("od[{$ocid}][{$odid}]", $ocid, $checked, $label, $params);
                    $cell->text .= html_writer::tag('LI', $checkbox);
                }

                $cell->text .= html_writer::empty_tag('img', array('src'=>'img/arrow_ltr.png'));
                $params = array_merge($disabled, array('class'=>'checkall_button', 'id'=>"od_{$tag_checkbox}"));
                $cell->text .= html_writer::checkbox('', '', true, 'todos/nenhum', $params);

                $cell->text .= html_writer::end_tag('UL');
            }
            $cell->style = "vertical-align: middle;";
            $row->cells[] = $cell;

            if($show_polos) {
                $cell = new html_table_cell();
                if(!empty($polos_oc[$ocid])) {
                    $show_form = true;
                    $cell->text = html_writer::start_tag('UL', array('style'=>'list-style-type: none;'));
                    $params = array_merge($disabled, array('class'=>'polo_'.$tag_checkbox));
                    foreach($polos_oc[$ocid] AS $plid=>$pl) {
                        $checked = $selected_ocs===true || isset($selected_polos[$ocid][$plid]);
                        $checkbox = html_writer::checkbox("polo[{$ocid}][{$plid}]", $ocid, $checked, $pl->nome, $params);
                        $cell->text .= html_writer::tag('LI', $checkbox);
                    }
                    $cell->text .= html_writer::empty_tag('img', array('src'=>'img/arrow_ltr.png'));
                    $params = array_merge($disabled, array('class'=>'checkall_button', 'id'=>"polo_{$tag_checkbox}"));
                    $cell->text .= html_writer::checkbox('', '', true, 'todos/nenhum', $params);

                    $cell->text .= html_writer::end_tag('UL');
                }
                $cell->style = "vertical-align: middle;";
                $row->cells[] = $cell;
            }

            $rows[] = $row;
        }
    }

    saas_show_nome_instituicao();
    print html_writer::start_tag('DIV', array('align'=>'center'));
    print $OUTPUT->heading('Exportação de dados para o SAAS' .  $OUTPUT->help_icon('export', 'report_saas_export'), 3);
    print html_writer::end_tag('DIV');

    if($show_form) {
        print html_writer::start_tag('DIV', array('class'=>'saas_area_large'));
        print $OUTPUT->box_start('generalbox boxaligncenter');

        print html_writer::start_tag('form', array('method'=>'post', 'action'=>$url));

        $table = new html_table();
        $table->head = array('Oferta de Curso', 'Ofertas de disciplina');
        $table->colclasses = array('leftalign', 'leftalign');
        if($show_polos) {
            $table->head[] = 'Polos';
            $table->colclasses[] = 'leftalign';
        }
        $table->data = $rows;
        $table->attributes = array('class'=>'saas_table');
        $table->tablealign = 'center';
        $table->cellpadding = 5;
        print html_writer::table($table);

        print html_writer::start_tag('DIV', array('class'=>'centeralign'));
        print html_writer::checkbox('send_user_details', 'ok', true, 'Enviar detalhes de estudantes (últimos acessos e notas)');
        print html_writer::empty_tag('br');
        print html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'export', 'value'=>s(get_string('saas_export:export', 'report_saas_export')), 'class'=>'boxaligncenter'));
        print html_writer::end_tag('DIV');

        print html_writer::end_tag('form');

        print $OUTPUT->box_end();
        print html_writer::end_tag('DIV');
    } else {
        print $OUTPUT->heading('Não há dados a serem exportados', 4);
    }
}

function saas_show_course_mappings($pocid=0) {
    global $saas, $DB, $OUTPUT, $PAGE;

    $syscontext = saas::get_context_system();
    $may_export = has_capability('report/saas_export:export', $syscontext);

    if($may_export) {
        $PAGE->requires->js_init_call('M.report_saas_export.init');
    }

    $one_to_many = $saas->get_config('course_mapping') == 'one_to_many';

    // obtem ofertas de curso
    $ofertas_cursos = $saas->get_ofertas_cursos();
    if($pocid === -1) {
        if(!empty($ofertas_cursos)) {
            $oc = reset($ofertas_cursos);
            $pocid = $oc->id;
        }
    }

    $params = array();
    $cond = '';
    if(!empty($pocid)) {
        $cond = 'AND oc.id = :ocid';
        $params['ocid'] = $pocid;
    }

    $sql = "SELECT oc.id as ocid, od.id as odid, od.group_map_id, od.inicio, od.fim, d.nome
              FROM {saas_ofertas_cursos} oc
              JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = oc.api_key)
              JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1 AND od.api_key = oc.api_key)
              JOIN {saas_disciplinas} d ON (d.uid = od.disciplina_uid AND d.enable = 1 AND d.api_key = oc.api_key)
             WHERE oc.enable = 1
               {$cond}
          ORDER BY oc.nome, od.group_map_id ,d.nome";
    $ofertas = array();
    foreach($DB->get_recordset_sql($sql, $params) AS $rec) {
        $ofertas[$rec->ocid][$rec->group_map_id][] = $rec;
    }

    // obtem mapeamentos
    $sql = "SELECT DISTINCT smc.courseid, smc.group_map_id, c.fullname
              FROM {saas_ofertas_disciplinas} od
              JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = od.api_key)
              JOIN {saas_map_course} smc ON (smc.group_map_id = od.group_map_id)
              JOIN {course} c ON (c.id = smc.courseid)
             WHERE od.enable = 1";
    $mapping = array();
    foreach($DB->get_recordset_sql($sql) AS $rec) {
        $mapping[$rec->group_map_id][] = $rec;
    }

    print html_writer::start_tag('div', array('class'=>'saas_area_large'));

    if(empty($ofertas_cursos)) {
        print $OUTPUT->heading(get_string('no_ofertas_cursos', 'report_saas_export'), 4);
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
                                if($may_export) {
                                    $cell->text .= html_writer::tag('input', '', array('class'=>'delete_map_bt', 'type'=>'image', 'src' =>'img/delete.png',
                                                    'alt'=>'Apagar mapeamento', 'height'=>'15', 'width'=>'15', 'group_map_id'=>$group_map_id,
                                                    'courseid'=>$r->courseid, 'ocid'=>$ocid, 'style'=>'margin-left:2px;'));
                                }
                                $cell->text .= html_writer::empty_tag('br');
                                $has_mapping = true;
                            }
                        }

                        if (!$has_mapping || $saas->get_config('course_mapping') == 'many_to_one') {
                            $cell->text .= html_writer::start_tag('div');
                            if($may_export) {
                                $cell->text .= html_writer::tag('button', 'Adicionar', array('type'=>'button', 'id'=>$group_map_id,
                                                                    'class'=>'add_map_bt', 'style'=>'margin-top:5px;'));
                            }
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
            print $OUTPUT->heading($oc_nome_formatado, 3);
            $table->tablealign = 'center';
            print html_writer::table($table);
            print $OUTPUT->box_end();

        }
    }
    print html_writer::end_tag('div');
}
