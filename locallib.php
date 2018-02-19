<?php

/**
 * Returns a particular array value for the named variable, taken from
 * POST or GET, otherwise returning a given default.
 *
 * This function should be used to initialise all optional values
 * in a script that are based on parameters.  Usually it will be
 * used like this:
 *    $ids = optional_param('id', array(), PARAM_INT);
 *
 * Note: arrays of arrays are not supported, only alphanumeric keys with _ and - are supported
 *
 * @param string $parname the name of the page parameter we want
 * @param mixed $default the default value to return if nothing is found
 * @param string $type expected type of parameter
 * @return array
 * @throws coding_exception
 */
function saas_optional_param_array($parname, $default, $type) {
    if (function_exists('optional_param_array')) {
        return optional_param_array($parname, $default, $type);
    }

    if (func_num_args() != 3 or empty($parname) or empty($type)) {
        throw new coding_exception('optional_param_array requires $parname, $default + $type to be specified (parameter: '.$parname.')');
    }

    // POST has precedence.
    if (isset($_POST[$parname])) {
        $param = $_POST[$parname];
    } else if (isset($_GET[$parname])) {
        $param = $_GET[$parname];
    } else {
        return $default;
    }
    if (!is_array($param)) {
        debugging('optional_param_array() expects array parameters only: '.$parname);
        return $default;
    }

    $result = array();
    foreach ($param as $key => $value) {
        if (!preg_match('/^[a-z0-9_-]+$/i', $key)) {
            debugging('Invalid key name in optional_param_array() detected: '.$key.', parameter: '.$parname);
            continue;
        }
        $result[$key] = clean_param($value, $type);
    }

    return $result;
}

// ----------------------------------------------------------------------------------------------
// Rotinas auxiliares para mapeamento de ofertas de disciplina para cursos Moodle

function saas_print_header($tabs, $action) {
    global $saas, $OUTPUT;

    echo $OUTPUT->header();
    echo html_writer::tag('DIV', $OUTPUT->heading('Instituição: ' . $saas->get_config('nome_instituicao'), 3));
    print_tabs(array($tabs), $action);
}

function saas_print_title($title, $size=3, $classes='') {
    global $OUTPUT;

    if (empty($classes)) {
        $classes = 'leftalign saas_area_large';
    }
    return $OUTPUT->heading($title, $size, $classes);
}

function saas_print_heading($title, $size=3, $classes='') {
    global $OUTPUT;

    if (empty($classes)) {
        $classes = 'leftalign';
    }
    return $OUTPUT->heading($title, $size, $classes);
}

function saas_print_alert($alert) {
    global $OUTPUT;

    return $OUTPUT->box($alert, 'generalbox alert');
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
    foreach ($categories AS $cat) {
        $cat->subs = array();
        $cat->courses = array();
        if ($cat->depth > 1) {
            $path = explode('/', $cat->path);
            $superid = $path[count($path)-2];
            $categories[$superid]->subs[$cat->id] = $cat;
        }
    }

    if (isset($SESSION->last_categoryid)) {
        if (isset($categories[$SESSION->last_categoryid])) {
            $category_path = explode('/', $categories[$SESSION->last_categoryid]->path);
            unset($category_path[0]);
        } else {
            if ($path = $DB->get_field('course_categories', 'path', array('id'=>$SESSION->last_categoryid))) {
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
    foreach ($courses AS $course) {
        $categories[$course->category]->courses[$course->id] = $course;
    }

    foreach (array_keys($categories) AS $catid) {
        if ($categories[$catid]->depth > 1) {
            unset($categories[$catid]);
        }
    }

    $sql = "SELECT od.*, d.nome
              FROM {saas_ofertas_disciplinas} od
              JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = od.api_key)
              JOIN {saas_disciplinas} d ON (d.id = od.disciplina_id AND d.enable = 1 AND d.api_key = od.api_key)
             WHERE od.enable = 1
               AND od.group_map_id = :group_map_id";
    $ods = $DB->get_records_sql($sql, array('group_map_id'=>$group_map_id));
    $best_options = array();
    if (count($ods) == 1) {
        $od = reset($ods);
        $str_data = $saas->format_date($od->inicio, $od->fim);
        $title_ods = "Disciplina: {$od->nome} ({$str_data})";

        if (count($ods) == 1) {
            $distances = array();
            foreach ($courses AS $c) {
                $distances[$c->id] = levenshtein($c->fullname, $od->nome);
            }
            asort($distances);
            $count = 0;
            foreach ($distances AS $cid=>$dist) {
                $c = $courses[$cid];
                $c->distance = $dist;
                $best_options[$cid] = $c;

                $count++;
                if ($count >= 5) {
                    break;
                }
            }
        }
    } else {
        $od_list = array();
        foreach ($ods AS $od) {
            $str_data = $saas->format_date($od->inicio, $od->fim);
            $od_list[] = "{$od->nome} ({$str_data})";
        }
        $title_ods = 'Disciplinas:' . html_writer::alist($od_list);
    }

    $oc = $saas->get_oferta_curso($od->oferta_curso_id);
    $cancel_url = new moodle_url('index.php', array('action'=>'course_mapping', 'subaction'=>'ofertas', 'ocid'=>$oc->id));

    echo saas_print_title(get_string('course_selection', 'report_saas_export') . $OUTPUT->help_icon('course_selection', 'report_saas_export'));

    $html = saas_print_heading("Curso: {$oc->nome} ({$oc->ano}/{$oc->periodo})", 4);
    $html .= saas_print_heading($title_ods, 4);
    echo $OUTPUT->box($html, 'generalbox saas_tree saas_area_large');

    if (!empty($best_options)) {
        $table = new html_table();
        $table->attributes = array('class'=>'saas_table');
        $table->head = array('Curso Moodle', 'Categoria', 'Distância Levenshtein');
        $table->colclasses = array('leftalign', 'leftalign', 'centeralign');
        $table->cellpadding = 5;

        $table->data = array();
        foreach ($best_options AS $c) {
            $cat_names = $saas->get_concatenated_categories_names($c->category, '/ ');
            $url = new moodle_url('index.php', array('action'=>'course_mapping', 'subaction'=>'add', 'courseid'=>$c->id,'group_map_id'=>$group_map_id));
            $link = html_writer::link($url, $c->fullname, array('title'=>'Clique para selecionar este curso'));
            $table->data[] = array($link, html_writer::tag('small', $cat_names), $c->distance);
        }

        $html = html_writer::empty_tag('BR');
        $html .= saas_print_title('Opções com nomes mais similares', 4);
        $html .= html_writer::table($table);

        echo $OUTPUT->box($html, 'generalbox saas_area_large');
    }

    $html = html_writer::empty_tag('BR');
    $html .= saas_print_title('Hierarquia de classe/cursos Moodle disponíveis para seleção', 4);

    $html .= html_writer::start_tag('ul');
    $html .= saas_show_categories($group_map_id, $categories, $category_path);
    $html .= html_writer::end_tag('ul');

    echo $OUTPUT->box($html, 'generalbox saas_area_large saas_tree');

    echo $OUTPUT->box($OUTPUT->single_button($cancel_url, get_string('cancel')), 'saas_area_large');
}

function saas_report_path() {
    return strpos(__FILE__, '/admin/report/') !== false ? '/admin/report/saas_export' : '/report/saas_export';
}

function saas_show_categories($group_map_id, &$categories, $open_catids = array()){
    global $OUTPUT;

    $html = '';
    foreach ($categories as $cat){
        $html .= html_writer::start_tag('li');
        $label = "cat_{$cat->id}";
        $url = new moodle_url(saas_report_path() . '/img/folder.png');
        $img_folder = html_writer::empty_tag('img', array('class'=>'saas_img_folder', 'src'=>$url));
        $html .= html_writer::tag('label', $img_folder . $cat->name, array('for'=>$label));
        $checked = in_array($cat->id, $open_catids);
        $html .= html_writer::checkbox(null, null, $checked, '', array('id'=>$label));

        $html .= html_writer::start_tag('ul');

        foreach ($cat->courses as $c){
            $html .= html_writer::start_tag('li', array('class'=>'course'));
            $html .= $OUTPUT->pix_icon("i/course", '', 'moodle', array('class' => 'icon smallicon'));
            $url = new moodle_url('index.php', array('action'=>'course_mapping', 'subaction'=>'add', 'courseid'=>$c->id,'group_map_id'=>$group_map_id));
            $html .= html_writer::link($url, $c->fullname, array('title'=>'Clique para selecionar este curso'));
            $html .= html_writer::end_tag('li');
        }

        if (!empty($cat->subs)){
            $html .= saas_show_categories($group_map_id, $cat->subs, $open_catids);
        }

        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_tag('li');
    }

    return $html;
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

    foreach ($categories AS $cat) {
        $cat->subs = array();
        $cat->courses = array();
        if ($cat->depth > 1) {
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

    foreach ($DB->get_records_sql($sql) AS $course) {
        $categories[$course->category]->courses[$course->id] = $course;
    }

    foreach (array_keys($categories) AS $catid) {
        if ($categories[$catid]->depth > 1) {
            unset($categories[$catid]);
        }
    }

    return $categories;
}

function saas_mount_category_tree_map_courses_polos(&$categories, &$polos, &$rows) {
    global $OUTPUT;

    foreach ($categories AS $cat) {
        $row = new html_table_row();
        $row->attributes['class'] = 'saas_level' . $cat->depth;

        $cell = new html_table_cell();
        $cell->attributes['class'] = 'saas_level' . $cat->depth;
        $padding = ($cat->depth-1)*18;

        $src_url = new moodle_url(saas_report_path() . '/img/folder.png');
        $img_folder = html_writer::empty_tag('img', array('src'=>$src_url, 'class'=>'saas_pix', 'style'=>"padding-left: {$padding}px;"));

        $cell->text = $img_folder . $cat->name;
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->attributes['class'] = 'saas_level' . $cat->depth;
        $row->cells[] = $cell;

        $rows[] = $row;

        if (count($cat->courses) > 0) {
            $color_class = 'saas_normalcolor';
            foreach ($cat->courses AS $c) {
                $color_class = $color_class == 'saas_normalcolor' ? 'saas_alternatecolor' : 'saas_normalcolor';
                $row = new html_table_row();
                $row->attributes['class'] = $color_class;

                $cell = new html_table_cell();
                $cell->attributes['class'] = $color_class;
                $padding = $cat->depth*18;
                $src_url = new moodle_url(saas_report_path() . '/img/course.png');
                $cell->text = html_writer::empty_tag('img', array('src'=> $src_url, 'class'=>'saas_pix', 'style'=>"padding-left: {$padding}px;"));
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

        if (!empty($cat->subs)) {
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

    foreach ($categories AS $cat) {
        $cat->subs = array();
        if ($cat->depth > 1) {
            $path = explode('/', $cat->path);
            $superid = $path[count($path)-2];
            $categories[$superid]->subs[$cat->id] = $cat;
        }
    }

    foreach (array_keys($categories) AS $catid) {
        if ($categories[$catid]->depth > 1) {
            unset($categories[$catid]);
        }
    }

    return $categories;
}

function saas_mount_category_tree_map_categories_polos($categories, &$polos, &$rows) {
    global $OUTPUT;

    foreach ($categories AS $cat) {
        $row = new html_table_row();
        $row->attributes['class'] = 'saas_level' . $cat->depth;

        $cell = new html_table_cell();
        $cell->attributes['class'] = 'saas_level' . $cat->depth;
        $padding = ($cat->depth-1)*18;

        $img_folder = html_writer::empty_tag('img', array('class'=>'saas_pix', 'style'=>"padding-left: {$padding}px;",
                                      'src' => new moodle_url(saas_report_path() . '/img/folder.png')));
        $cell->text = $img_folder . $cat->name;
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->attributes['class'] = 'saas_level' . $cat->depth;
        $poloid = empty($cat->polo_id) ? 0 : $cat->polo_id;
        $cell->text = html_writer::select($polos, "map_polos[{$cat->id}]", $poloid);
        $row->cells[] = $cell;

        $rows[] = $row;

        if (!empty($cat->subs)) {
            saas_mount_category_tree_map_categories_polos($cat->subs, $polos, $rows);
        }
    }
}

// Criação de tabelas para visualização dos dados.
//---------------------------------------------------------------------------------------------------

function saas_show_table_polos() {
    global $DB, $OUTPUT, $saas;

    $polos = $saas->get_polos();

    if (empty($polos)) {
        echo saas_print_alert(get_string('no_polos', 'report_saas_export'));
    } else {
        $rows = array();
        $index = 0;
        foreach ($polos as $pl) {
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
        $table->attributes = array('class'=>'saas_table');
        $table->cellpadding = 5;
        echo $OUTPUT->box(html_writer::table($table), 'generalbox saas_area_large');
    }
}

function saas_show_overview_polos($ocid, $poloid) {
    global $DB, $saas;

    $polo_mapping_type = $saas->get_config('polo_mapping');

    if (!$saas->has_polo()) {
        echo saas_print_alert(get_string('no_polos', 'report_saas_export'));
        return;
    }
    if ($polo_mapping_type == 'no_polo') {
        echo saas_print_alert(get_string('title_no_polo', 'report_saas_export'));
        return;
    }

    $url = new moodle_url('index.php', array('action'=>'overview', 'subaction'=>'polos'));
    saas_show_menu_ofertas_cursos($ocid, $url);

    if ($ocid && $poloid) {
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

    $list = array();
    foreach ($ofertas_cursos AS $oc) {
        if (!isset($polos[$oc->id])) {
            continue;
        }

        $rows = array();
        $index = 0;
        foreach ($polos[$oc->id] AS $pl) {
            $index++;
            $row = array($index.'.');

            $texts = array();
            $show_url = false;
            foreach ($role_types AS $r) {
                if (isset($counts[$oc->id][$pl->id][$r]) && $counts[$oc->id][$pl->id][$r] > 0) {
                    $texts[$r] = $counts[$oc->id][$pl->id][$r];
                    $show_url = true;
                } else {
                    $texts[$r] = 0;
                }
            }

            if ($show_url) {
                $url = new moodle_url('index.php', array('action'=>'overview', 'subaction'=>'polos', 'ocid'=>$oc->id, 'poloid'=>$pl->id));
                $row[] = html_writer::link($url, $pl->nome);
            } else {
                $row[] = $pl->nome;
            }
            $row[] = $pl->cidade;
            $row[] = $pl->estado;

            foreach ($role_types AS $r) {
                $row[] = $texts[$r];
            }

            $rows[] = $row;
        }

        if (empty($rows)) {
            $list[] = saas_print_heading("{$oc->nome} ({$oc->ano}/{$oc->periodo})", 4) . '(Não há polos mapeados para este curso)';
        } else {
            $table = new html_table();
            $table->head = array('', get_string('nome_polo', 'report_saas_export'), 'Cidade', 'UF');
            $table->colclasses = array('leftalign',  'leftalign', 'leftalign', 'leftalign');
            foreach ($role_types AS $r) {
                $table->head[] = get_string($r, 'report_saas_export');
                $table->colclasses[] = 'rightalign';
            }
            $table->data = $rows;
            $table->attributes = array('class'=>'saas_table');
            $table->cellpadding = 5;

            $list[] = saas_print_heading("{$oc->nome} ({$oc->ano}/{$oc->periodo})", 4) . html_writer::table($table);
        }
    }
    echo $OUTPUT->box(html_writer::alist($list), 'generalbox saas_area_large');
}

function saas_show_users_oferta_curso_polo($ocid, $poloid, $sql, $params) {
    global $DB, $OUTPUT, $saas;

    $oc = $saas->get_oferta_curso($ocid);
    $polo = $DB->get_record('saas_polos', array('id'=>$poloid));

    $role_types = $saas->get_role_types('polos');

    $html = saas_print_heading("Curso: {$oc->nome} ({$oc->ano}/{$oc->periodo})", 4);
    $html .= saas_print_heading("Polo: {$polo->nome} ({$polo->cidade}/{$polo->estado})", 4);
    echo $OUTPUT->box($html, 'generalbox saas_area_large');

    $rows = array();
    foreach ($role_types AS $role) {
        $rows[$role] = array();
    }
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs AS $rec) {
        $user = $saas->get_user($rec->role, $rec->userid, $rec->uid);
        $rows[$rec->role][] = array((count($rows[$rec->role])+1) . '.', $user->nome, $user->uid, $user->email, $user->cpf);
    }

    foreach ($role_types AS $role) {
        if (count($rows[$role]) > 0) {
            $html = saas_print_title(get_string($role . 's', 'report_saas_export'), 3);

            $table = new html_table();
            $table->head = array('', get_string('name'), get_string('username', 'report_saas_export'), get_string('email'), get_string('cpf', 'report_saas_export'));
            $table->data = $rows[$role];
            $table->attributes = array('class'=>'saas_table');
            $table->cellpadding = 5;
            $html .= html_writer::table($table);

            echo $OUTPUT->box($html, 'generalbox saas_area_large');
        }
    }

}

function saas_show_users_oferta_disciplina($ofer_disciplina_id) {
    global $DB, $OUTPUT, $saas;

    $grades = $saas->get_grades($ofer_disciplina_id);

    $od = $saas->get_oferta_disciplina($ofer_disciplina_id);
    $oc = $saas->get_oferta_curso($od->oferta_curso_id);

    $html = saas_print_heading("Curso: {$oc->nome} ({$oc->ano}/{$oc->periodo})", 4);
    $html .= saas_print_heading("Disciplina: {$od->nome} ". $saas->format_date($od->inicio, $od->fim), 4);
    echo $OUTPUT->box($html, 'generalbox saas_area_large');

    list($sql, $params) =  $saas->get_sql_users_by_oferta_disciplina(0, $ofer_disciplina_id);
    $rs = $DB->get_recordset_sql($sql, $params);
    $rows = array();
    $suspended = array();
    $role_types = $saas->get_role_types('disciplinas');
    foreach ($role_types AS $r) {
        $rows[$r] = array();
    }
    $suspended_as_evaded = $saas->get_config('suspended_as_evaded');
    foreach ($rs AS $rec) {
        if ($rec->role == 'student') {
            if ($suspended_as_evaded && (!empty($rec->global_suspended) || !empty($rec->suspended))) {
                $suspended[] = $rec;
            } else {
                $user = $saas->get_user($rec->role, $rec->userid, $rec->uid);
                $row = array((count($rows[$rec->role])+1) . '.', $user->nome, $user->uid, $user->email, $user->cpf);
                $row[] = get_string('no');
                $row[] = get_string('no');
                $row[] = empty($rec->currentlogin) ? '-' : date('d-m-Y H:i', $rec->currentlogin);
                $row[] = empty($rec->lastaccess) ? '-' : date('d-m-Y H:i', $rec->lastaccess);
                $row[] = isset($grades[$rec->userid]) && $grades[$rec->userid] >= 0 ? format_float($grades[$rec->userid],1,true) : '-';

                $rows[$rec->role][] = $row;
            }
        } else {
            $user = $saas->get_user($rec->role, $rec->userid, $rec->uid);
            $row = array((count($rows[$rec->role])+1) . '.', $user->nome, $user->uid, $user->email, $user->cpf);
            $rows[$rec->role][] = $row;
        }
    }

    foreach ($suspended AS $rec) {
        $user = $saas->get_user($rec->role, $rec->userid, $rec->uid);
        $row = array();

        $row[] = (count($rows[$rec->role])+1) . '.';
        $row[] = $user->nome;
        $row[] = $user->uid;
        $row[] = $user->email;
        $row[] = $user->cpf;
        if (empty($rec->global_suspended)) {
            $row[] = get_string('no');
        } else {
            $row[] = html_writer::tag('span', get_string('yes'), array('class'=>'saas_export_warning'));
        }
        if (empty($rec->suspended)) {
            $row[] = get_string('no');
        } else {
            $row[] = html_writer::tag('span', get_string('yes'), array('class'=>'saas_export_warning'));
        }
        $row[] = empty($rec->currentlogin) ? '-' : date('d-m-Y H:i', $rec->currentlogin);
        $row[] = empty($rec->lastaccess) ? '-' : date('d-m-Y H:i', $rec->lastaccess);
        $row[] = isset($grades[$rec->userid]) && $grades[$rec->userid] >= 0 ? format_float($grades[$rec->userid],1,true) : '-';

        $rows[$rec->role][] = $row;
    }

    foreach ($role_types AS $r) {
        if (count($rows[$r]) > 0) {
            $table = new html_table();
            $table->head = array('', get_string('name'), get_string('username', 'report_saas_export'), get_string('email'), get_string('cpf', 'report_saas_export'));
            $table->colclasses = array('rightalign', 'leftalign', 'leftalign', 'leftalign', 'leftalign');
            if ($r == 'student') {
                $table->head[] = get_string('global_suspended', 'report_saas_export');
                $table->head[] = get_string('suspended', 'report_saas_export');
                $table->head[] = get_string('lastlogin');
                $table->head[] = get_string('lastcourseaccess', 'report_saas_export');
                $table->head[] = get_string('finalgrade', 'grades');
                $table->colclasses[] = 'centeralign';
                $table->colclasses[] = 'centeralign';
                $table->colclasses[] = 'centeralign';
                $table->colclasses[] = 'centeralign';
                $table->colclasses[] = 'rightalign';
            }
            $table->data = $rows[$r];
            $table->attributes['class'] = 'saas_table';

            $html = saas_print_title(get_string($r . 's', 'report_saas_export'), 3);
            $table->attributes = array('class'=>'saas_table');
            $table->cellpadding = 5;
            $html .= html_writer::table($table);
            echo $OUTPUT->box($html, 'generalbox saas_area_large');
        }
    }
}

function saas_show_menu_ofertas_cursos($oferta_curso_id=0, $url) {
    global $PAGE, $DB, $saas;

    // obtem ofertas de curso
    $ofertas_cursos = $saas->get_ofertas_cursos();
    $ofertas_menu = array();
    $ofertas_menu[0] = get_string('all');
    foreach ($ofertas_cursos AS $ocid=>$oc) {
        $ofertas_menu[$ocid] = "{$oc->nome} ({$oc->ano}/{$oc->periodo})";
    }

    echo html_writer::start_tag('DIV', array('class'=>'saas_oferta_curso_menu'));

    echo html_writer::start_tag('DIV', array('style'=>'position:relative; float: right;'));
    echo html_writer::start_tag('form', array('method'=>'post', 'action'=>$url));
    echo html_writer::select($ofertas_menu, 'ocid', $oferta_curso_id, false);
    echo html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'list', 'value'=>get_string('list')));
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('DIV');

    echo html_writer::tag('DIV', get_string('oferta_curso', 'report_saas_export') . ': ', array('style'=>'position:relative; float: right;'));

    echo html_writer::end_tag('DIV');

}

function saas_show_table_ofertas_curso_disciplinas($oferta_curso_id=0, $show_counts=false) {
    global $OUTPUT, $DB, $saas;

    if ($show_counts) {
        list($sql, $params) =  $saas->get_sql_users_by_oferta_disciplina($oferta_curso_id, 0, true);
        $rs = $DB->get_recordset_sql($sql, $params);
        $ofertas_disciplinas_counts = array();
        foreach ($rs AS $rec) {
            $ofertas_disciplinas_counts[$rec->odid][$rec->role] = $rec->count;
        }
    }

    $role_types = $saas->get_role_types('disciplinas');
    $ofertas = $saas->get_ofertas($oferta_curso_id);
    if (empty($ofertas)) {
        echo saas_print_alert(get_string('no_ofertas_cursos', 'report_saas_export'));
    } else {
        $lista_ofertas = array();
        foreach ($ofertas AS $ocid=>$oc) {
            $rows = array();
            $index = 0;

            foreach ($oc->ofertas_disciplinas AS $odid=>$od) {
                $index++;
                $row = array($index . '.');
                if ($show_counts) {
                    $texts = array();
                    $show_url = false;
                    foreach ($role_types AS $r) {
                        if ($od->mapped) {
                            if (isset($ofertas_disciplinas_counts[$odid][$r]) && $ofertas_disciplinas_counts[$odid][$r] > 0) {
                                $texts[$r] = $ofertas_disciplinas_counts[$odid][$r];
                                $show_url = true;
                            } else {
                                $texts[$r] = 0;
                            }
                        } else {
                            $texts[$r] = '-';
                        }
                    }
                    if ($show_url) {
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

                if ($show_counts) {
                    foreach ($role_types AS $r) {
                        $row[] = $texts[$r];
                    }
                }

                $rows[] = $row;
            }

            $table = new html_table();
            $table->head = array('', 'Oferta de disciplina', 'Início', 'Fim');
            $table->colclasses = array('leftalign', 'leftalign', 'leftalign', 'leftalign');
            if ($show_counts) {
                foreach ($role_types AS $r) {
                    $table->head[] = get_string($r, 'report_saas_export');
                    $table->colclasses[] = 'rightalign';
                }
            }
            $table->data = $rows;
            $table->attributes = array('class'=>'saas_table');
            $table->cellpadding = 5;

            $lista_ofertas[] = saas_print_heading("{$oc->nome} ({$oc->ano}/{$oc->periodo})", 4) .
                               html_writer::table($table);
        }
        echo $OUTPUT->box(html_writer::alist($lista_ofertas), 'generalbox saas_area_large');

        if ($show_counts) {
            echo $OUTPUT->box('(*) Clique no nome da oferta de discplina para visualizar detalhes sobre dados a serem exportados.', 'generalbox saas_area_large');
        }

    }
}

function saas_show_export_options($url, $selected_ocs=true) {
    global $DB, $saas, $PAGE, $OUTPUT;

    $PAGE->requires->js_init_call('M.report_saas_export.init');
    $report_path = saas_report_path();

    $ofertas_cursos = $saas->get_ofertas_cursos();
    $ofertas_disciplinas_oc = $saas->get_ofertas_disciplinas(0, true);

    $show_polos = $saas->get_config('polo_mapping') != 'no_polo';
    $polos_oc = $show_polos ? $saas->get_polos_by_oferta_curso() : array();

    if (empty($ofertas_cursos)) {
        echo saas_print_alert(get_string('no_ofertas_cursos', 'report_saas_export'));
        return;
    }

    $rows = array();
    $show_form = false;
    foreach ($ofertas_cursos AS $ocid=>$oc) {
        if (isset($ofertas_disciplinas_oc[$ocid]) || ($show_polos && !empty($polos_oc[$ocid]))) {
            $not_empty_polos = $saas->get_not_empty_polos_saas_from_oferta_curso($oc->uid);
            $not_empty_ods = $saas->get_not_empty_ofertas_disciplinas_saas_from_oferta_curso($oc->uid);

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
            if (isset($ofertas_disciplinas_oc[$ocid])) {
                $show_form = true;
                $cell->text .= '<b>' . get_string('od_mapped', 'report_saas_export') . '</b>';
                $cell->text .= html_writer::start_tag('UL');
                foreach ($ofertas_disciplinas_oc[$ocid] AS $odid=>$od) {
                    $label = $od->nome . ' (' . $saas->format_date($od->inicio, $od->fim) . ')';
                    $cell->text .= html_writer::tag('LI', $label);

                    unset($not_empty_ods[$od->uid]);
                }

                $cell->text .= html_writer::end_tag('UL');
            }

            $show_msg_clear = false;

            if (!empty($not_empty_ods)) {
                $empty = true;
                foreach ($not_empty_ods AS $od_uid=>$data) {
                    if ($od = $saas->get_oferta_disciplina_by_uid($od_uid)) {
                        if ($empty) {
                            $show_form = true;
                            $cell->text .= '<b>' . get_string('od_notmapped', 'report_saas_export') . '</b>';
                            $cell->text .= html_writer::start_tag('UL', array('class'=>'saas_list'));
                        }
                        $label = $od->nome . ' (' . $saas->format_date($od->inicio, $od->fim) . ')';
                        $checkbox = html_writer::checkbox("clear_ods[{$od->id}]", $oc->id, false, $label, array('class'=>'od_'.$tag_checkbox));
                        $cell->text .= html_writer::tag('LI', $checkbox);
                        $empty = false;
                    }
                }
                if (!$empty) {
                    $cell->text .= html_writer::empty_tag('img', array('class'=>'saas_img_folder',
                                  'src' => new moodle_url($report_path . '/img/arrow_ltr.png')));
                    $cell->text .= '<small>' . get_string('mark_clear', 'report_saas_export') . '</small>';
                    $cell->text .= html_writer::end_tag('UL');
                }
                $show_msg_clear = $show_msg_clear || !$empty;
            }

            $cell->style = "vertical-align: middle;";
            $row->cells[] = $cell;

            if ($show_polos) {
                $cell = new html_table_cell();
                if (!empty($polos_oc[$ocid])) {
                    $show_form = true;
                    $cell->text .= '<b>' . get_string('polos_mapped', 'report_saas_export') . '</b>';
                    $cell->text .= html_writer::start_tag('UL');
                    foreach ($polos_oc[$ocid] AS $plid=>$pl) {
                        $label = "{$pl->nome} ({$pl->cidade}/{$pl->estado})";
                        $cell->text .= html_writer::tag('LI', $label);
                        unset($not_empty_polos[$pl->uid]);
                    }
                    $cell->text .= html_writer::end_tag('UL');
                }
                $cell->style = "vertical-align: middle;";
                $row->cells[] = $cell;

                if (!empty($not_empty_polos)) {
                    $empty = true;
                    foreach ($not_empty_polos AS $polo_uid=>$data) {
                        if ($pl = $saas->get_polo_by_uid($polo_uid)) {
                            if ($empty) {
                                $show_form = true;
                                $cell->text .= '<b>' . get_string('polos_notmapped', 'report_saas_export') . '</b>';
                                $cell->text .= html_writer::start_tag('UL', array('class'=>'saas_list'));
                            }
                            $label = "{$pl->nome} ({$pl->cidade}/{$pl->estado})";
                            $checkbox = html_writer::checkbox("clear_polos[{$oc->id}_{$pl->id}]", 1, false, $label, array('class'=>'polo_'.$tag_checkbox));
                            $cell->text .= html_writer::tag('LI', $checkbox);
                            $empty = false;
                        }
                    }
                    if (!$empty) {
                        $cell->text .= html_writer::empty_tag('img', array('class'=>'saas_img_folder',
                                      'src' => new moodle_url($report_path . '/img/arrow_ltr.png')));
                        $cell->text .= '<small>' . get_string('mark_clear', 'report_saas_export') . '</small>';
                        $cell->text .= html_writer::end_tag('UL');
                    }
                    $show_msg_clear = $show_msg_clear || !$empty;
                }
            }

            $divrow = new html_table_row();
            $cell = new html_table_cell();
            $cell->text = '';
            $cell->colspan = 3;
            $cell->style = 'height:2px; background:LightGray;';
            $divrow->cells[] = $cell;

            if (empty($rows)) {
                $rows[] = $divrow;
            }
            $rows[] = $row;
            $rows[] = $divrow;
        }
    }

    echo saas_print_title('Exportação de dados para o SAAS' .  $OUTPUT->help_icon('export', 'report_saas_export'));

    if ($show_form) {
        $export_btn = html_writer::empty_tag('img', array('class'=>'saas_img_folder',
                                      'src' => new moodle_url($report_path . '/img/arrow_ltr.png')));
        $export_btn .=  html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'export',
                'value'=>s(get_string('saas_export:export', 'report_saas_export')), 'class'=>'boxaligncenter'));

        $rows[] = array($export_btn);

        $table = new html_table();
        $table->head = array('Oferta de Curso', 'Ofertas de disciplina');
        $table->colclasses = array('leftalign', 'leftalign');
        if ($show_polos) {
            $table->head[] = 'Polos';
            $table->colclasses[] = 'leftalign';
        }
        $table->data = $rows;
        $table->attributes = array('class'=>'saas_table');
        $table->cellpadding = 5;

        $form = html_writer::start_tag('form', array('method'=>'post', 'action'=>$url));
        $form .= html_writer::table($table);
        $form .= html_writer::end_tag('form');

        echo $OUTPUT->box($form, 'generalbox saas_area_large');
    } else {
        echo saas_print_alert('Não há dados a serem exportados');
    }
}

function saas_show_course_mappings($pocid=0) {
    global $saas, $DB, $OUTPUT, $PAGE;

    $syscontext = saas::get_context_system();
    $may_export = has_capability('report/saas_export:export', $syscontext);

    if ($may_export) {
        $PAGE->requires->js_init_call('M.report_saas_export.init');
    }

    $one_to_many = $saas->get_config('course_mapping') == 'one_to_many';

    // obtem ofertas de curso
    $ofertas_cursos = $saas->get_ofertas_cursos();
    if ($pocid === -1) {
        if (!empty($ofertas_cursos)) {
            $oc = reset($ofertas_cursos);
            $pocid = $oc->id;
        }
    }

    $params = array();
    $cond = '';
    if (!empty($pocid)) {
        $cond = 'AND oc.id = :ocid';
        $params['ocid'] = $pocid;
    }

    $sql = "SELECT oc.id as ocid, od.id as odid, od.group_map_id, od.inicio, od.fim, d.nome
              FROM {saas_ofertas_cursos} oc
              JOIN {saas_cursos} c ON (c.id = oc.curso_id AND c.api_key = oc.api_key)
              JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = oc.api_key)
              JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_id = oc.id AND od.enable = 1 AND od.api_key = oc.api_key)
              JOIN {saas_disciplinas} d ON (d.id = od.disciplina_id AND d.enable = 1 AND d.api_key = oc.api_key)
             WHERE oc.enable = 1
               {$cond}
          ORDER BY c.nome, od.group_map_id ,d.nome";
    $ofertas = array();
    foreach ($DB->get_recordset_sql($sql, $params) AS $rec) {
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
    foreach ($DB->get_recordset_sql($sql) AS $rec) {
        $mapping[$rec->group_map_id][] = $rec;
    }

    if (empty($ofertas_cursos)) {
        echo saas_print_alert(get_string('no_ofertas_cursos', 'report_saas_export'));
    } else {
        $url = new moodle_url('index.php', array('action'=>'course_mapping', 'subaction'=>'ofertas'));
        saas_show_menu_ofertas_cursos($pocid, $url);

        foreach ($ofertas AS $ocid=>$maps) {
            $oc = $ofertas_cursos[$ocid];

            $group_options = array(0=>'');
            foreach (array_keys($maps) AS $ind=>$group_map_id) {
                $group_options[$group_map_id] = 'Grupo ' . ($ind+1);
            }
            $group_options[-1] = 'Novo grupo';

            $rows = array();
            $index = 0;
            $color_class = '';
            foreach ($maps AS $group_map_id=>$recs) {
                $index++;
                $oc_nome_formatado = "{$oc->nome} ({$oc->ano}/{$oc->periodo})";
                $color_class = $color_class == 'saas_normalcolor' ? 'saas_alternatecolor' : 'saas_normalcolor';

                $od_nome_formatado = '';
                if (count($recs) == 1) {
                    $rec = reset($recs);
                    $od_nome_formatado =  $rec->nome . ' (' . $saas->format_date($rec->inicio, $rec->fim) . ')';
                } else if (count($recs) > 1) {
                    $od_nome_formatado = html_writer::start_tag('UL');
                    foreach ($recs AS $rec) {
                        $od_nome_formatado .= html_writer::tag('LI', $rec->nome . ' (' . $saas->format_date($rec->inicio, $rec->fim) . ')');
                    }
                    $od_nome_formatado .= html_writer::end_tag('UL');
                }

                $first = true;
                foreach ($recs AS $rec) {
                    $row = new html_table_row();
                    $row->attributes['class'] = $color_class;
                    if ($first) {
                        $cell = new html_table_cell();
                        $cell->text = $index . '.';
                        $cell->rowspan = count($recs);
                        $cell->style = "vertical-align: middle;";
                        $cell->attributes['class'] = $color_class;
                        $row->cells[] = $cell;
                    }

                    if ($one_to_many) {
                        $cell = new html_table_cell();
                        if (count($recs) > 1 || !isset($mapping[$group_map_id])) {
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

                    if ($first) {
                        $cell = new html_table_cell();
                        $cell->rowspan = count($recs);
                        $cell->style = "vertical-align: middle;";
                        $cell->attributes['class'] = $color_class;
                        $cell->text = '';
                        $has_mapping = false;
                        if (isset($mapping[$group_map_id])) {
                            foreach ($mapping[$group_map_id] AS $r) {
                                $cell->text .= $r->fullname;
                                if ($may_export) {
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
                            if ($may_export) {
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
            if ($one_to_many) {
                $table->head = array('Grupo');
                $table->head[] = 'Mover para';
            } else {
                $table->head = array('');
            }
            $table->head[] = 'Oferta de disciplina';
            $table->head[] = 'Curso Moodle';
            $table->colclasses = array('leftalign', 'leftalign', 'leftalign', 'leftalign');
            $table->data = $rows;

            $html = saas_print_heading($oc_nome_formatado, 4) .  html_writer::table($table);
            echo $OUTPUT->box($html, 'generalbox saas_area_large');
        }
    }
}
