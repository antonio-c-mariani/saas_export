<?php

require_once($CFG->libdir . '/gradelib.php');

class saas {

    public static $role_names             = array('teacher'=>'professor', 'student'=>'aluno', 'tutor_polo'=>'tutor', 'tutor_inst'=>'tutor');
    public static $role_names_disciplinas = array('teacher', 'tutor_inst', 'student');
    public static $role_names_polos       = array('tutor_polo', 'student');

    public $config;

    public static function format_date($saas_timestamp_inicio, $saas_timestamp_fim=false, $separador=' / ') {
        $result = date("d-m-Y", substr($saas_timestamp_inicio, 0, 10));
        if($saas_timestamp_fim) {
            $result .= $separador;
            $result .= date("d-m-Y", substr($saas_timestamp_fim, 0, 10));
        }
        return $result;
    }

    function __construct() {
        global $DB;

        $this->config = get_config('report_saas_export');

        $roles = array();
        foreach($DB->get_recordset('saas_config_roles') as $rec) {
            $roles[$rec->role][] = $rec->roleid;
        }

        foreach(self::$role_names as $r=>$pap) {
            $role = 'roles_'.$r;
            $this->config->$role = isset($roles[$r]) ? implode(',', $roles[$r]) : '';
        }

        $this->config->filter_userid_field = isset($this->config->filter_userid_field) ? $this->config->filter_userid_field : false;
        $this->config->suspended_as_evaded = isset($this->config->suspended_as_evaded) ? $this->config->suspended_as_evaded : false;
    }

    function get_config($name) {
        if(isset($this->config->{$name})) {
            return $this->config->{$name};
        } else {
            return false;
        }
    }

    //Carrega os dados do web service para o plugin
    //---------------------------------------------------------------------------------------------------------------
    function load_saas_data($force_reload=false) {
       if(!$lastupdated = get_config('report_saas_export', 'lastupdated')) {
           $lastupdated = 0;
       }
       $now = time();
       $hourdiff = round(($now - $lastupdated)/3600, 1);
       if ($hourdiff > 1 || $force_reload) {
           try {
               $this->load_disciplinas_saas();
               $this->load_ofertas_cursos_saas();
               $this->load_ofertas_disciplinas_saas();
               $this->load_polos_saas();
               set_config('lastupdated', $now, 'report_saas_export');
           } catch (dml_write_exception $e){
               print_error('bd_error', 'report_saas_export', '', $e->debuginfo);
           } catch (Exception $e){
               $url = new moodle_url('/report/saas_export/index.php', array('action'=>'settings'));
               print_error('ws_error', 'report_saas_export', $url);
           }
       }
    }

    function load_cursos_saas(){
        return $this->get_ws('cursos');
    }

    function load_disciplinas_saas(){
        global $DB;

        $local = $DB->get_records('saas_disciplinas', null, null ,'uid, id, enable');

        foreach ($this->get_ws('disciplinas') as $dis) {
            $dis->enable = 1;
            if (isset($local[$dis->uid])){
                $dis->id = $local[$dis->uid]->id;
                $DB->update_record('saas_disciplinas', $dis);
                unset($local[$dis->uid]);
            } else {
                $DB->insert_record('saas_disciplinas', $dis);
            }
        }

        foreach($local AS $uid=>$dis) {
            if($dis->enable){
                $DB->set_field('saas_disciplinas', 'enable', 0, array('id'=>$dis->id));
            }
        }
    }

    function load_ofertas_cursos_saas(){
        global $DB;

        $local = $DB->get_records('saas_ofertas_cursos', null, null ,'uid, id, enable');

        $cursos_saas = $this->load_cursos_saas();
        $ofertas_cursos_saas = $this->get_ws('ofertas/cursos');

        foreach ($cursos_saas as $curso) {
            foreach ($ofertas_cursos_saas as $oferta_curso) {
                if ($curso->uid == $oferta_curso->curso->uid) {
                    $record = new stdClass();
                    $record->nome = $curso->nome; //Nome do curso
                    $record->ano = $oferta_curso->ano;
                    $record->periodo = $oferta_curso->periodo;
                    $record->enable = 1;

                    if (isset($local[$oferta_curso->uid])){
                        $record->id = $local[$oferta_curso->uid]->id;
                        $DB->update_record('saas_ofertas_cursos', $record);
                        unset($local[$oferta_curso->uid]);
                    } else {
                        $record->uid = $oferta_curso->uid;
                        $DB->insert_record('saas_ofertas_cursos', $record);
                    }
                }
            }
        }

        foreach($local AS $uid=>$rec) {
            if($rec->enable){
                $DB->set_field('saas_ofertas_cursos', 'enable', 0, array('id'=>$rec->id));
            }
        }
    }

    function load_ofertas_disciplinas_saas(){
        global $DB;

        $local = $DB->get_records('saas_ofertas_disciplinas', null, null ,'uid, id, enable');
        foreach ($this->get_ws('ofertas/disciplinas') as $oferta_disciplina){
            $record = new stdClass();
            $record->uid = $oferta_disciplina->uid;
            $record->disciplina_uid = $oferta_disciplina->disciplina->uid;
            $record->inicio = $oferta_disciplina->inicio;
            $record->fim = $oferta_disciplina->fim;
            $record->oferta_curso_uid = $oferta_disciplina->ofertaCurso->uid;
            $record->enable = 1;

            if (isset($local[$oferta_disciplina->uid])){
                $record->id = $local[$oferta_disciplina->uid]->id;
                $DB->update_record('saas_ofertas_disciplinas', $record);
                unset($local[$oferta_disciplina->uid]);
            } else {
                $DB->insert_record('saas_ofertas_disciplinas', $record);
            }
        }

        foreach ($local AS $uid=>$rec) {
            if($rec->enable){
                $DB->set_field('saas_ofertas_disciplinas', 'enable', 0, array('id'=>$rec->id));
            }
        }
    }

    function load_polos_saas() {
        global $DB;

        $local = $DB->get_records('saas_polos', null, '' ,'uid, id, enable');

        $polos_saas = $this->get_ws('polos');

        if (!empty($polos_saas)) {
            foreach ($polos_saas as $pl){
                $record = new stdClass();
                $record->nome = $pl->nome;
                $record->cidade = $pl->cidade;
                $record->estado = $pl->estado;
                $record->enable = 1;
                if (isset($local[$pl->uid])){
                    $record->id = $local[$pl->uid]->id;
                    $DB->update_record('saas_polos', $record);
                    unset($local[$pl->uid]);
                } else {
                    $record->uid = $pl->uid;
                    $DB->insert_record('saas_polos', $record);
                }
            }
        }

        foreach ($local AS $uid=>$rec) {
            if($rec->enable){
                $DB->set_field('saas_polos', 'enable', 0, array('id'=>$rec->id));
            }
        }
    }

    // Gets para os dados já salvos no plugin
    //---------------------------------------------------------------------------------------------------


    function get_ofertas_curso_salvas() {
        global $DB;

        return $DB->get_records('saas_ofertas_cursos', array('enable'=>1), 'nome, ano, periodo');
    }

    function get_ofertas_disciplinas_salvas() {
        global $DB;

        $sql = "SELECT od.*, d.nome
                  FROM {saas_ofertas_disciplinas} od
                  JOIN {saas_disciplinas} d ON (d.uid = od.disciplina_uid)
                 WHERE od.enable = 1
                   AND d.enable = 1";
        return $DB->get_records_sql($sql);
    }

    function get_mapeamento_cursos() {
        global $DB;

        return $DB->get_records('saas_map_course');
    }

    function get_mapped_polos_by_name() {
        global $DB;

        $polos = $DB->get_records('saas_polos', array('enable'=>1), null , 'id, is_polo, groupname');
        $mapped_polos = array();
        foreach ($polos as $p){
            $mapped_polos[$p->groupname] = $p->is_polo;
        }
        return $mapped_polos;
    }

    function get_ofertas_cursos_info() {
        global $DB;

        $offers = array();
        $courses_offer =  $DB->get_records('saas_ofertas_cursos', array('enable'=>1));
        foreach ($courses_offer as $c){
            $offers[$c->saas_id] = $c;
        }
        return $offers;
    }

    // Criação de tabelas para visualização dos dados.
    //---------------------------------------------------------------------------------------------------

    function show_table_polos() {
        global $DB, $OUTPUT;

        $polos = $DB->get_records('saas_polos', array('enable'=>1), 'nome');

        print html_writer::start_tag('DIV', array('align'=>'center'));

        $table = new html_table();
        $table->head = array(get_string('nome_polo', 'report_saas_export'),
                             get_string('cidade', 'report_saas_export'),
                             get_string('estado', 'report_saas_export'));
        $table->data = array();
        foreach($polos as $pl) {
            $table->data[] = array($pl->nome, $pl->cidade, $pl->estado);
        }
        print html_writer::table($table);

        print html_writer::end_tag('DIV');
    }

    function show_overview_courses_polos($ocid, $poloid) {
        $sql = "SELECT DISTINCT oc.id AS oc_id, sp.*
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
                  JOIN {saas_map_course} cm ON (cm.oferta_disciplina_id = od.id)
                  JOIN {course} c ON (c.id = cm.courseid)
                  JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'courses' AND smcp.instanceid = c.id)
                  JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1)
                 WHERE oc.enable = 1
              ORDER BY oc.id, sp.nome";
        $this->show_table_overview_polos($sql);

        if($ocid && $poloid) {
            list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_categories($ocid, $poloid, false);
            $this->show_users_oferta_curso_polo($ocid, $poloid, $sql, $params);
        }
    }

    function show_overview_categories_polos($ocid, $poloid) {
        // todo: faltam as subcategorias
        $sql = "SELECT DISTINCT oc.id AS oc_id, sp.*
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
                  JOIN {saas_map_course} cm ON (cm.oferta_disciplina_id = od.id)
                  JOIN {course} c ON (c.id = cm.courseid)

                  JOIN {course_categories} cc ON (cc.id = c.category)
                  -- JOIN {course_categories} cc ON (cc.id = c.category)
                  JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'category' AND smcp.instanceid = cc.id)

                  JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1)
                 WHERE oc.enable = 1
              ORDER BY oc.id, sp.nome";
        $this->show_table_overview_polos($sql);

        if($ocid && $poloid) {
            list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_categories($ocid, $poloid, false);
            $this->show_users_oferta_curso_polo($ocid, $poloid, $sql, $params);
        }
    }

    function show_overview_groups_polos($ocid, $poloid) {
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
        $this->show_table_overview_polos($sql);

        if($ocid && $poloid) {
            list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_groups($ocid, $poloid, false);
            $this->show_users_oferta_curso_polo($ocid, $poloid, $sql, $params);
        }
    }

    function show_table_overview_polos($sql) {
        global $DB;

        $ofertas_cursos = $DB->get_records('saas_ofertas_cursos', array('enable'=>1), 'nome, ano, periodo');
        foreach($ofertas_cursos AS $id=>$oc) {
            $oc->polos = array();
        }
        foreach($DB->get_recordset_sql($sql) AS $pl) {
            $ofertas_cursos[$pl->oc_id]->polos[] = $pl;
        }

        $counts = $this->get_polos_count();

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
                for($i=1; $i <= count(self::$role_names_polos)+1; $i++) {
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
                    foreach(self::$role_names_polos AS $r) {
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

                    foreach(self::$role_names_polos AS $r) {
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
        foreach(self::$role_names_polos AS $r) {
            $table->head[] = get_string($r, 'report_saas_export');
        }

        $table->data = $data;
        print html_writer::table($table);

        print html_writer::end_tag('DIV');
    }

    function show_users_oferta_curso_polo($ocid, $poloid, $sql, $params) {
        global $DB, $OUTPUT;

        $oc = $DB->get_record('saas_ofertas_cursos', array('id'=>$ocid));
        $polo = $DB->get_record('saas_polos', array('id'=>$poloid));
        $title = "{$oc->nome} ({$oc->ano}/{$oc->periodo}) - {$polo->nome}";

        print html_writer::start_tag('DIV', array('align'=>'center'));

        $rs = $DB->get_recordset_sql($sql, $params);
        $data = array();
        foreach(self::$role_names_polos AS $role) {
            $data[$role] = array();
        }
        foreach($rs AS $rec) {
            $user = $this->get_user($rec->role, $rec->userid, $rec->userfield);
            $data[$rec->role][] = array((count($data[$rec->role])+1) . '.', $user->userfield, $user->name, $user->email, $user->cpf);
        }

        foreach(self::$role_names_polos AS $role) {
            if(count($data[$role]) > 0) {
                print $OUTPUT->box_start('generalbox boxwidthwide');
                print $OUTPUT->heading(get_string($role . 's', 'report_saas_export') . ' => ' . $title, '4');

                $table = new html_table();
                $table->head = array('', get_string('username', 'report_saas_export'), get_string('name'), get_string('email'), get_string('cpf', 'report_saas_export'));
                $table->data = $data[$role];
                print html_writer::table($table);
                print $OUTPUT->box_end();
            }
        }

        print html_writer::end_tag('DIV');
    }

    function get_ofertas() {
        global $DB;

        $ofertas = $DB->get_records('saas_ofertas_cursos', array('enable'=>1), 'nome, ano, periodo');

        $sql = "SELECT DISTINCT od.*, d.nome, oc.id as oc_id, cm.id IS NOT NULL AS mapped
                  FROM {saas_ofertas_cursos} oc
             LEFT JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
             LEFT JOIN {saas_disciplinas} d ON (d.uid = od.disciplina_uid AND d.enable = 1)
             LEFT JOIN {saas_map_course} cm ON (cm.oferta_disciplina_id = od.id)
                 WHERE oc.enable = 1
              ORDER BY d.nome";
        $recs = $DB->get_recordset_sql($sql);
        foreach($recs as $rec) {
            if(empty($rec->id)) {
                $ofertas[$rec->oc_id]->ofertas_disciplinas = array();
            } else {
                $ofertas[$rec->oc_id]->ofertas_disciplinas[$rec->id] = $rec;
            }
        }
        return $ofertas;
    }

    function get_sql_users_by_oferta_curso_polo_categories($ocid=0, $poloid=0, $only_count=false) {
        global $DB;

        $condition = '';
        $params = array('contextcourse'=>CONTEXT_COURSE, 'enable'=>ENROL_INSTANCE_ENABLED, 'active'=>ENROL_USER_ACTIVE);
        if($ocid) {
            $condition .= ' AND oc.id = :ocid';
            $params['ocid'] = $ocid;
        }
        if($poloid) {
            $condition .= ' AND sp.id = :poloid';
            $params['poloid'] = $poloid;
        }

        $join_user_info_data = '';
        if($only_count) {
            $group_by = 'GROUP BY oc.id, sp.id, scr.role';
            $field = 'COUNT(DISTINCT ra.userid) AS count';
            $distinct = '';
        } else {
            $group_by = '';
            $userid_field = $this->get_config('userid_field');
            if($userid_field == 'username' || $userid_field == 'idnumber') {
                $field = "ra.userid, u.{$userid_field} AS userfield";
            } else {
                if($fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$userid_field))) {
                    $join_user_info_data = "JOIN {user_info_data} udt ON (udt.fieldid = :fieldid AND udt.userid = u.id AND udt.data != '')";
                    $params['fieldid'] = $fieldid;
                    $field = "ra.userid, udt.data AS userfield";
                } else {
                    print_error('userid_field_unknown', 'report_saas_export', '', $userid_field);
                }
            }
            $distinct = 'DISTINCT';
        }

        $sql = "SELECT {$distinct} oc.id AS oc_id, sp.id AS p_id, scr.role, $field
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
                  JOIN {saas_map_course} cm ON (cm.oferta_disciplina_id = od.id)
                  JOIN {course} c ON (c.id = cm.courseid)
                  JOIN {enrol} e ON (e.courseid = c.id AND e.status = :enable)
                  JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.status = :active)
                  JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse)
                  JOIN {role_assignments} ra
                    ON (ra.contextid = ctx.id AND
                        ra.userid = ue.userid AND
                        ((ra.component = '' AND e.enrol = 'manual') OR (ra.component = CONCAT('enrol_',e.enrol) AND ra.itemid = ue.id)))
                  JOIN {saas_config_roles} scr ON (scr.roleid = ra.roleid AND scr.role IN ('student', 'tutor_polo'))
                  JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0)
                  {$join_user_info_data}

                  JOIN {course_categories} cc ON (cc.id = c.category)
                  -- JOIN {course_categories} cc ON (cc.id = c.category)
                  JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'category' AND smcp.instanceid = cc.id)

                  JOIN {saas_map_groups_polos} spm ON (spm.polo_id = smcp.polo_id)
                  JOIN {saas_polos} sp ON (sp.id = spm.polo_id AND sp.enable = 1)
                 WHERE oc.enable = 1
                   {$condition}
              {$group_by}
              ORDER BY oc.id, sp.id, scr.role";
        return array($sql, $params);
    }

    function get_sql_users_by_oferta_curso_polo_courses($ocid=0, $poloid=0, $only_count=false) {
        global $DB;

        $condition = '';
        $params = array('contextcourse'=>CONTEXT_COURSE, 'enable'=>ENROL_INSTANCE_ENABLED, 'active'=>ENROL_USER_ACTIVE);
        if($ocid) {
            $condition .= ' AND oc.id = :ocid';
            $params['ocid'] = $ocid;
        }
        if($poloid) {
            $condition .= ' AND sp.id = :poloid';
            $params['poloid'] = $poloid;
        }

        $join_user_info_data = '';
        if($only_count) {
            $group_by = 'GROUP BY oc.id, sp.id, scr.role';
            $field = 'COUNT(DISTINCT ra.userid) AS count';
            $distinct = '';
        } else {
            $group_by = '';
            $userid_field = $this->get_config('userid_field');
            if($userid_field == 'username' || $userid_field == 'idnumber') {
                $field = "ra.userid, u.{$userid_field} AS userfield";
            } else {
                if($fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$userid_field))) {
                    $join_user_info_data = "JOIN {user_info_data} udt ON (udt.fieldid = :fieldid AND udt.userid = u.id AND udt.data != '')";
                    $params['fieldid'] = $fieldid;
                    $field = "ra.userid, udt.data AS userfield";
                } else {
                    print_error('userid_field_unknown', 'report_saas_export', '', $userid_field);
                }
            }
            $distinct = 'DISTINCT';
        }

        $sql = "SELECT {$distinct} oc.id AS oc_id, sp.id AS p_id, scr.role, $field
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
                  JOIN {saas_map_course} cm ON (cm.oferta_disciplina_id = od.id)
                  JOIN {course} c ON (c.id = cm.courseid)
                  JOIN {enrol} e ON (e.courseid = c.id AND e.status = :enable)
                  JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.status = :active)
                  JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse)
                  JOIN {role_assignments} ra
                    ON (ra.contextid = ctx.id AND
                        ra.userid = ue.userid AND
                        ((ra.component = '' AND e.enrol = 'manual') OR (ra.component = CONCAT('enrol_',e.enrol) AND ra.itemid = ue.id)))
                  JOIN {saas_config_roles} scr ON (scr.roleid = ra.roleid AND scr.role IN ('student', 'tutor_polo'))
                  JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0)
                  {$join_user_info_data}
                  JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'course' AND smcp.instanceid = c.id)
                  JOIN {saas_map_groups_polos} spm ON (spm.polo_id = smcp.polo_id)
                  JOIN {saas_polos} sp ON (sp.id = spm.polo_id AND sp.enable = 1)
                 WHERE oc.enable = 1
                   {$condition}
              {$group_by}
              ORDER BY oc.id, sp.id, scr.role";
        return array($sql, $params);
    }

    function get_sql_users_by_oferta_curso_polo_groups($ocid=0, $poloid=0, $only_count=false) {
        global $DB;

        $condition = '';
        $params = array('contextcourse'=>CONTEXT_COURSE, 'enable'=>ENROL_INSTANCE_ENABLED, 'active'=>ENROL_USER_ACTIVE);
        if($ocid) {
            $condition .= ' AND oc.id = :ocid';
            $params['ocid'] = $ocid;
        }
        if($poloid) {
            $condition .= ' AND sp.id = :poloid';
            $params['poloid'] = $poloid;
        }

        $join_user_info_data = '';
        if($only_count) {
            $group_by = 'GROUP BY oc.id, sp.id, scr.role';
            $field = 'COUNT(DISTINCT ra.userid) AS count';
            $distinct = '';
        } else {
            $group_by = '';
            $userid_field = $this->get_config('userid_field');
            if($userid_field == 'username' || $userid_field == 'idnumber') {
                $field = "ra.userid, u.{$userid_field} AS userfield";
            } else {
                if($fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$userid_field))) {
                    $join_user_info_data = "JOIN {user_info_data} udt ON (udt.fieldid = :fieldid AND udt.userid = u.id AND udt.data != '')";
                    $params['fieldid'] = $fieldid;
                    $field = "ra.userid, udt.data AS userfield";
                } else {
                    print_error('userid_field_unknown', 'report_saas_export', '', $userid_field);
                }
            }
            $distinct = 'DISTINCT';
        }

        $sql = "SELECT {$distinct} oc.id AS oc_id, sp.id AS p_id, scr.role, $field
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
                  JOIN {saas_map_course} cm ON (cm.oferta_disciplina_id = od.id)
                  JOIN {course} c ON (c.id = cm.courseid)
                  JOIN {enrol} e ON (e.courseid = c.id AND e.status = :enable)
                  JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.status = :active)
                  JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse)
                  JOIN {role_assignments} ra
                    ON (ra.contextid = ctx.id AND
                        ra.userid = ue.userid AND
                        ((ra.component = '' AND e.enrol = 'manual') OR (ra.component = CONCAT('enrol_',e.enrol) AND ra.itemid = ue.id)))
                  JOIN {saas_config_roles} scr ON (scr.roleid = ra.roleid AND scr.role IN ('student', 'tutor_polo'))
                  JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0)
                  {$join_user_info_data}
                  JOIN {groups} g ON (g.courseid = c.id)
                  JOIN {groups_members} gm ON (gm.groupid = g.id AND gm.userid = u.id)
                  JOIN {saas_map_groups_polos} spm ON (spm.groupname = g.name)
                  JOIN {saas_polos} sp ON (sp.id = spm.polo_id AND sp.enable = 1)
                 WHERE oc.enable = 1
                   {$condition}
              {$group_by}
              ORDER BY oc.id, sp.id, scr.role";
        return array($sql, $params);
    }

    function get_polos_count() {
        global $DB;

        $polo_counts = array();

        $polo_mapping_type = $this->get_config('polo_mapping');
        switch ($polo_mapping_type) {
            case 'group_to_polo':
                list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_groups(0, 0, true);
                break;
            case 'category_to_polo':
                list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_categories(0, 0, true);
                break;
            case 'course_to_polo':
                list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_courses(0, 0, true);
                break;
            default:
                return $polo_counts;
        }

        $rs = $DB->get_recordset_sql($sql, $params);

        foreach($rs AS $rec) {
            $polo_counts[$rec->oc_id][$rec->p_id][$rec->role] = $rec->count;
        }

        return $polo_counts;
    }

    function get_sql_users_by_oferta_disciplina($id_oferta_disciplina=0, $only_count=false) {
        global $DB;

        $params = array('contextlevel'=>CONTEXT_COURSE, 'enable'=>ENROL_INSTANCE_ENABLED);
        $group_by = 'GROUP BY od_id, scr.role';

        if($this->get_config('suspended_as_evaded')) {
            $user_enrol_condition = "AND (ue.status = :active OR scr.role = 'student')";
        } else {
            $user_enrol_condition = 'AND ue.status = :active';
        }
        $params['active'] = ENROL_USER_ACTIVE;

        $join_user_info_data = '';
        $join_user_lastaccess = '';
        if($only_count) {
            $fields = 'COUNT(DISTINCT ra.userid) AS count';
        } else {
            $userid_field = $this->get_config('userid_field');
            if($userid_field == 'username' || $userid_field == 'idnumber') {
                $fields = "ra.userid, u.{$userid_field} AS userfield";
                $group_by .= ', ra.userid, u.' . $userid_field;
            } else {
                if($fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$userid_field))) {
                    $join_user_info_data = "JOIN {user_info_data} udt ON (udt.fieldid = :fieldid AND udt.userid = u.id AND udt.data != '')";
                    $params['fieldid'] = $fieldid;
                    $fields = "ra.userid, udt.data AS userfield";
                    $group_by .= ', ra.userid, udt.data';
                } else {
                    print_error('userid_field_unknown', 'report_saas_export', '', $userid_field);
                }
            }
            $join_user_lastaccess = 'LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = c.id)';
            $fields .= ', MAX(ue.status) as suspended, MAX(u.currentlogin) AS currentlogin, MAX(ul.timeaccess) AS lastaccess';
        }

        $condition = '';
        if($id_oferta_disciplina) {
            $condition = "AND od.id = {$id_oferta_disciplina}";
        }

        $sql = "SELECT od.id AS od_id, scr.role, {$fields}
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
                  JOIN {saas_map_course} cm ON (cm.oferta_disciplina_id = od.id)
                  JOIN {course} c ON (c.id = cm.courseid)
                  JOIN {enrol} e ON (e.courseid = c.id AND e.status = :enable)
                  JOIN {user_enrolments} ue ON (ue.enrolid = e.id)
                  JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)
                  JOIN {role_assignments} ra
                    ON (ra.contextid = ctx.id AND
                        ra.userid = ue.userid AND
                        ((ra.component = '' AND e.enrol = 'manual') OR (ra.component = CONCAT('enrol_',e.enrol) AND ra.itemid = ue.id)))
                  JOIN {saas_config_roles} scr ON (scr.roleid = ra.roleid AND scr.role IN ('student', 'teacher', 'tutor_inst'))
                  JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0)
                  {$join_user_info_data}
                  {$join_user_lastaccess}
                 WHERE oc.enable = 1
                   {$condition}
                   {$user_enrol_condition}
              {$group_by}
              ORDER BY od_id, scr.role";

        return array($sql, $params);
    }

    function show_users_oferta_disciplina($ofer_disciplina_id) {
        global $DB, $OUTPUT;

        $grades = $this->get_grades($ofer_disciplina_id);

        $od = $DB->get_record('saas_ofertas_disciplinas', array('id'=>$ofer_disciplina_id));
        $oc = $DB->get_record('saas_ofertas_cursos', array('uid'=>$od->oferta_curso_uid));
        $title = "{$oc->nome} ({$oc->ano}/{$oc->periodo}) - {$od->nome} " . self::format_date($od->inicio, $od->fim);

        print html_writer::start_tag('DIV', array('align'=>'center'));

        list($sql, $params) =  $this->get_sql_users_by_oferta_disciplina($ofer_disciplina_id);
        $rs = $DB->get_recordset_sql($sql, $params);
        $data = array();
        foreach(self::$role_names_disciplinas AS $role) {
            $data[$role] = array();
        }
        foreach($rs AS $rec) {
            $user = $this->get_user($rec->role, $rec->userid, $rec->userfield);
            $row = array((count($data[$rec->role])+1) . '.', $user->userfield, $user->name, $user->email, $user->cpf);
            if($rec->role == 'student') {
                $row[] = $rec->suspended == 1 ? html_writer::tag('span', get_string('yes'), array('class'=>'saas_export_warning')) : get_string('no');
                $row[] = empty($rec->currentlogin) ? '-' : date('d-m-Y H:i', $rec->currentlogin);
                $row[] = empty($rec->lastaccess) ? '-' : date('d-m-Y H:i', $rec->lastaccess);
                $row[] = isset($grades[$rec->userid]) && $grades[$rec->userid] >= 0 ? $grades[$rec->userid] : '-';
            }
            $data[$rec->role][] = $row;
        }

        foreach(self::$role_names_disciplinas AS $role) {
            if(count($data[$role]) > 0) {
                print $OUTPUT->box_start('generalbox boxwidthwide');
                print $OUTPUT->heading(get_string($role . 's', 'report_saas_export') . ' => ' . $title, '4');

                $table = new html_table();
                $table->head = array('', get_string('username', 'report_saas_export'), get_string('name'), get_string('email'), get_string('cpf', 'report_saas_export'));
                $table->colclasses = array('rightalign', 'leftalign', 'leftalign', 'leftalign', 'leftalign');
                if($role == 'student') {
                    $table->head[] = get_string('suspended', 'report_saas_export');
                    $table->head[] = get_string('lastlogin');
                    $table->head[] = get_string('lastcourseaccess', 'report_saas_export');
                    $table->head[] = get_string('finalgrade', 'grades');
                    $table->colclasses[] = 'centeralign';
                    $table->colclasses[] = 'centeralign';
                    $table->colclasses[] = 'centeralign';
                    $table->colclasses[] = 'rightalign';
                }
                $table->data = $data[$role];
                print html_writer::table($table);
                print $OUTPUT->box_end();
            }
        }

        print html_writer::end_tag('DIV');
    }

    function get_user($role, $userid, $userfield) {
        global $DB;

        $dbuser = $DB->get_record('user', array('id'=>$userid), 'id, username, idnumber, firstname, lastname, email');

        $user = new stdClass();
        $user->email = $dbuser->email;
        $user->userfield = $this->get_config('filter_userid_field') ? $this->format_cpf($userfield) : $userfield;

        $name_field = $this->get_config('name_field_' . $role);
        switch($name_field) {
            case 'firstname': $user->name = $dbuser->firstname; break;
            case 'lastname': $user->name = $dbuser->lastname; break;
            default: $user->name = $dbuser->firstname . ' ' . $dbuser->lastname;
        }
        if($name_regexp =  $this->get_config('name_regexp')) {
            // todo: tratar expressão regular
        }

        $cpf_field = $this->get_config('cpf_field_' . $role);
        switch($cpf_field) {
            case ''        : $cpf = ''; break;
            case 'username': $cpf = $dbuser->username; break;
            case 'idnumber': $cpf = $dbuser->idnumber; break;
            case 'lastname': $cpf = $dbuser->lastname; break;
            default:
                if($fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$cpf_field))) {
                    $cpf = $DB->get_field('user_info_data', 'data', array('userid'=>$dbuser->id, 'fieldid'=>$fieldid));
                } else {
                    $cpf = '';
                }
        }
        $user->cpf = $this->format_cpf($cpf);

        return $user;
    }

    function get_grades($ofer_disciplina_id) {
        global $DB;

        $grades = array();
        foreach($DB->get_records('saas_map_course', array('oferta_disciplina_id' => $ofer_disciplina_id)) AS $rec) {
            $grade_item = grade_item::fetch_course_item($rec->courseid);
            $sql = "SELECT DISTINCT ra.userid
                      FROM {context} ctx
                      JOIN {role_assignments} ra ON (ra.contextid = ctx.id)
                      JOIN {saas_config_roles} scr ON (scr.role = 'student' AND scr.roleid = ra.roleid)
                     WHERE ctx.instanceid = :courseid
                       AND ctx.contextlevel = :contextlevel";
            foreach($DB->get_recordset_sql($sql, array('courseid'=>$rec->courseid, 'contextlevel'=>CONTEXT_COURSE)) AS $us) {
                if($grade_item->gradetype == GRADE_TYPE_VALUE) {
                    $grade = new grade_grade(array('itemid'=>$grade_item->id, 'userid'=>$us->userid));
                    $finalgrade = $grade->finalgrade;
                    if(is_numeric($finalgrade)) {
                        $final = (float)$finalgrade / $grade_item->grademax * 10;
                    } else {
                        $final = 0.0;
                    }
                } else {
                    $final = -1.0;
                }
                if(isset($grades[$us->userid])) {
                    $grades[$us->userid] = max($grades[$us->userid], $final);
                } else {
                    $grades[$us->userid] = $final;
                }
            }
        }
        return $grades;
    }

    function format_cpf($cpf) {
        if(empty($cpf)) {
            $cpf = '';
        } else {
            $cpf_regexp = $this->get_config('cpf_regexp');
            if(!empty($cpf_regexp)) {
                if(preg_match($cpf_regexp, $cpf, $matches)) {
                    unset($matches[0]);
                    $cpf = implode('', $matches);
                }
            }
            $cpf = empty($cpf) ? '' : str_pad(preg_replace('|[^0-9]+|', '', $cpf), 11, '0', STR_PAD_LEFT);
        }
        return $cpf;
    }

    function show_table_ofertas_curso_disciplinas($show_counts=false) {
        global $DB;

        $data = array();
        $color = '#E0E0E0';

        if($show_counts) {
            list($sql, $params) =  $this->get_sql_users_by_oferta_disciplina(0, true);
            $rs = $DB->get_recordset_sql($sql, $params);
            $ofertas_disciplinas_counts = array();
            foreach($rs AS $rec) {
                $ofertas_disciplinas_counts[$rec->od_id][$rec->role] = $rec->count;
            }
        }

        foreach($this->get_ofertas() AS $oc_id=>$oc) {
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
                for($i=1 ; $i <= count(self::$role_names_disciplinas)+3; $i++) {
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
                        foreach(self::$role_names_disciplinas AS $r) {
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
                    $cell->text = self::format_date($od->inicio);
                    $cell->style = "background-color: {$color};";
                    $row->cells[] = $cell;

                    $cell = new html_table_cell();
                    $cell->text = self::format_date($od->fim);
                    $cell->style = "background-color: {$color};";
                    $row->cells[] = $cell;

                    if($show_counts) {
                        foreach(self::$role_names_disciplinas AS $r) {
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
            foreach(self::$role_names_disciplinas AS $r) {
                $table->head[] = get_string($r, 'report_saas_export');
            }
        }
        $table->data = $data;
        print html_writer::table($table);
        print html_writer::end_tag('DIV');
    }

    // Funções para salvar os mapeamentos
    // -----------------------------------------------------------------

    function save_polos_mapping($formdata){
        global $DB;

        $ofertas_cursos_polos = $formdata->map;
        foreach ($ofertas_cursos_polos as $course_id => $polos) {
            $existing = $DB->get_records_menu('saas_polos', array('course_offer_id'=>$course_id), null, 'id, 1');
            foreach ($polos as $polo => $checked) {
                $record = new stdClass();
                $record->enable = 1;
                $record->is_polo = $checked;
                if ($id = $DB->get_field('saas_polos', 'id', array('groupname'=>$polo, 'course_offer_id'=>$course_id))){
                    $record->id = $id;
                    $DB->update_record('saas_polos', $record);
                    unset($existing[$id]);
                } else {
                    $record->groupname = $polo;
                    $record->course_offer_id = $course_id;
                    $id = $DB->insert_record('saas_polos', $record);
                }
            }
            foreach($existing AS $id=>$v) {
                $DB->set_field('saas_polos', 'enable', 0, array('id'=>$id));
            }
        }
    }


    // Funções para que enviam ou auxíliam no envio de dados para o SAAS
    //----------------------------------------------------------------------------------------------------------

    function serialize_role_names($str_roleids){
        if(empty($str_roleids)) {
            return '';
        }
        $context = context_system::instance();
        $role_names = role_fix_names(get_all_roles($context), $context);
        $roleids = explode(',', $str_roleids);
        $names = array();
        foreach ($roleids as $rid){
            $names[] = $role_names[$rid]->localname;
        }
        return implode(', ', $names);
    }

    function get_users_sql($user_type) {
        global $DB;

        $config_role = "roles_{$user_type}";

        //Verifica se o papel foi definido
        if ($str_roleids = $this->get_config($config_role)) {
            if(empty($str_roleids)) {
                return false;
            }

            $config_cpf = $this->get_config('cpf_field_'.$user_type);

            $join_custom_fields = '';
            if($config_cpf == 'none') {
                $cpf_field = "'' AS cpf";
            } else {
                $custom_fields = saas_export_get_user_custom_fields();
                if(isset($custom_fields[$config_cpf])) {
                    $join_custom_fields = "LEFT JOIN {user_info_field} uf ON (uf.shortname = '{$config_cpf}')
                                           LEFT JOIN {user_info_data} ud ON (ud.fieldid = uf.id AND ud.userid = u.id)";
                    $cpf_field = "ud.data AS cpf";
                } else {
                    $cpf_field = "u.{$config_cpf} AS cpf";
                }
            }

            $config_name = $this->config->{"name_field_{$user_type}"};
            switch ($config_name){
                case 'firstname':
                case 'lastname':
                    $from_name = $config_name;
                    break;
                case 'firstnamelastname':
                    $from_name = "CONCAT(u.firstname, ' ', u.lastname)";
                    break;
            }
            $fields = "u.{$this->config->userid_field} AS uid,
                       u.email, {$from_name} as nome,
                       {$cpf_field}";

            list($in_sql, $params) = $DB->get_in_or_equal(explode(',', $str_roleids), SQL_PARAMS_NAMED);
            $sql = "SELECT DISTINCT {$fields}
                       FROM {saas_map_course} AS cm
                       JOIN {saas_ofertas_disciplinas} od ON (cm.oferta_disciplina_id = od.id)
                       JOIN {course} c ON (c.id = cm.courseid)
                      JOIN {context} ctx
                        ON (ctx.contextlevel = :contextlevel AND
                            ctx.instanceid = c.id)
                      JOIN {role_assignments} ra
                        ON (ra.contextid = ctx.id AND
                            ra.roleid {$in_sql})
                      JOIN {user} u
                        ON (u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0)
                      {$join_custom_fields}
                     WHERE od.enable = 1";
            return array($sql, $params);
        } else {
            return null;
        }
    }

    function get_users_by_role($str_roleids, $courseid){
        global $DB;

        if(empty($str_roleids)) {
            return array();
        }

        $context = context_course::instance($courseid);
        $users = get_role_users(explode(',', $str_roleids), $context, false, "u.id, u.{$this->config->user_id_field}", 'u.id');
        $users_to_send = array();

        foreach ($users as $u){
            $users_to_send[] = $u->{$this->config->user_id_field};
        }
        return $users_to_send;
    }

    function get_users_at_polo($str_roleids, $course_offer_id, $groupname){
        global $DB;

        if(empty($str_roleids)) {
            return array();
        }

        list($in_sql, $in_params) = $DB->get_in_or_equal(explode(',', $str_roleids), SQL_PARAMS_NAMED);

        $field = 'u.' . $this->config->user_id_field;
        $context = CONTEXT_COURSE;
        $query_params = array('context'=>$context, 'course_offer_id'=>$course_offer_id, 'groupname'=>$groupname);
        $params = array_merge($query_params, $in_params);

        $sql = "SELECT DISTINCT {$field} AS userfield
                  FROM {ofertas_cursos_saas} oc
                  JOIN {ofertas_disciplinas_saas} od
                    ON (od.saas_course_offer_id = oc.id)
                  JOIN {saas_polos} p
                    ON (p.course_offer_id = oc.id)
                  JOIN {groups} g
                    ON (g.courseid = od.courseid AND g.name = p.groupname)
                  JOIN {groups_members} gm
                    ON (gm.groupid = g.id)
                  JOIN {user} u
                    ON (u.id = gm.userid AND u.deleted = 0 AND u.suspended = 0)
                  JOIN {context} ctx
                    ON (ctx.contextlevel = :context AND
                        ctx.instanceid = od.courseid)
                  JOIN {role} r
                    ON (r.id {$in_sql})
                  JOIN {role_assignments} ra
                    ON (ra.contextid = ctx.id AND
                        ra.userid = u.id AND
                        ra.roleid = r.id)
                 WHERE oc.enable = 1
                   AND oc.saas_id = :course_offer_id
                   AND p.groupname = :groupname
                   AND p.is_polo = 1
                   AND p.enable = 1
                   AND od.enable = 1
              ORDER BY u.username";

        $user = $DB->get_records_sql($sql,$params);
        $users_to_send = array();
        foreach ($user as $u){
            $users_to_send[] = $u->userfield;
        }

        return $users_to_send;
    }

    function get_total_users_to_send(){
        global $DB;

        $roleids = array();
        foreach(saas::$role_names as $r) {
            $role_name = $r . '_role';
            if(isset($this->config->$role_name) && !empty($this->config->$role_name)) {
                $roleids = array_merge($roleids, explode(',', $this->config->$role_name));
            }
        }

        if(empty($roleids)) {
            return 0;
        }

        $roleids = array_unique($roleids);

        //pega o total de usuários;

        list($in_sql, $in_params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
        $in_params['context'] = CONTEXT_COURSE;

        $sql = "SELECT count(DISTINCT u.id) AS total
                  FROM {ofertas_cursos_saas} AS oc
                  JOIN {ofertas_disciplinas_saas} AS od
                    ON (od.saas_course_offer_id = oc.id AND
                        od.enable = 1)
                  JOIN {course} c
                    ON (c.id = od.courseid)
                  JOIN {context} ctx
                    ON (ctx.contextlevel = :context AND
                        ctx.instanceid = c.id)
                  JOIN {role_assignments} ra
                    ON (ra.contextid = ctx.id AND
                        ra.roleid {$in_sql})
                  JOIN {user} u
                    ON (u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0)
                 WHERE oc.enable = 1";
        return $DB->count_records_sql($sql, $in_params);
    }

    function get_count_users(){
        global $DB;

        $counts = array();
        foreach(saas::$role_names as $r) {
            $role_name = $r . '_role';
            if(isset($this->config->$role_name) && !empty($this->config->$role_name)) {
                $roleids = explode(',', $this->config->$role_name);
                list($in_sql, $in_params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
                $in_params['context'] = CONTEXT_COURSE;

                if($r == 'tutor_polo') {
                   $join = "JOIN {saas_polos} p ON (p.course_offer_id = oc.id AND p.enable = 1 AND p.is_polo = 1)
                            JOIN {groups} g ON (g.courseid = c.id AND g.name = p.groupname)
                            JOIN {groups_members} gm ON (gm.groupid = g.id AND gm.userid = ra.userid)";
                } else {
                    $join = '';
                }

                $sql = "SELECT oc.saas_id AS oferta_curso, od.saas_id as oferta_disciplina,
                               COUNT(DISTINCT ra.userid) as count
                          FROM {ofertas_cursos_saas} oc
                          JOIN {ofertas_disciplinas_saas} od
                            ON (od.saas_course_offer_id = oc.id AND
                                od.enable = 1)
                          JOIN {course} c
                            ON (c.id = od.courseid)
                          JOIN {context} ctx
                            ON (ctx.contextlevel = :context AND
                                ctx.instanceid = c.id)
                          JOIN {role_assignments} ra
                            ON (ra.contextid = ctx.id)
                          {$join}
                         WHERE oc.enable = 1
                           AND ra.roleid {$in_sql}
                         GROUP BY oc.saas_id, od.saas_id";
                $recs = $DB->get_recordset_sql($sql, $in_params);
                foreach($recs AS $rec) {
                    $counts[$rec->oferta_curso][$rec->oferta_disciplina][$r] = $rec->count;
                }
            }
        }
        return $counts;
    }

    //Envia todos os dados, tanto pessoas, como pessoas com os papéis e as pessoas por pólos.
    function send_data(){
        //$this->send_users();
        //$this->send_users_by_oferta_disciplina();
        //$this->send_users_by_polo();
    }

    //envia todos os usuários das ofertas de disciplina já mapeadas.
    function send_users() {
        global $DB;

        foreach (saas::$role_names as $user_type) {
            list($sql, $params) = $this->get_users_sql($user_type);
            if (!empty($sql)){
                $params['contextlevel'] = CONTEXT_COURSE;
                $users = $DB->get_recordset_sql($sql, $params);
                $users_to_send = array();
                $count = 0;

                foreach ($users as $u) {
                    if ($count > 100) {
                        $this->put_ws('pessoa', $users_to_send);
                        $users_to_send = array();
                        $count = 0;
                    }

                    if(!empty($u->cpf) && preg_match('/^[^0-9]*([0-9]{3})[^0-9]?([0-9]{3})[^0-9]?([0-9]{3})[^0-9]?([0-9]{2})[^0-9]*$/', $u->cpf, $matches)) {
                        unset($matches[0]);
                        $u->cpf = implode('', $matches);
                    }else {
                        $u->cpf = '';
                    }
                    $users_to_send[] = $u;
                    $count++;
                }

                if ($count > 0) {
                    $this->put_ws('pessoas', $users_to_send);
                }
            }
        }
    }


    //envia os usuários com seus devidos papéis nos pólos.
    function send_users_by_polo() {
        global $DB;

        $sql = "SELECT DISTINCT p.id, oc.saas_id, p.groupname
                  FROM {ofertas_cursos_saas} oc
                  JOIN {saas_polos} p
                    ON (p.course_offer_id = oc.id)
                  JOIN {ofertas_disciplinas_saas} od
                    ON (od.saas_course_offer_id = oc.id AND od.courseid > 0)
                 WHERE oc.enable = 1
                   AND p.is_polo = 1
                   AND p.enable = 1";
        $offers_polo = $DB->get_records_sql($sql);
        foreach ($offers_polo as $offer_polo){
            //send students
            $this->put_ws('polo/oferta/curso/aluno/'. $offer_polo->saas_id .'/'. urlencode($offer_polo->groupname),
                           $this->get_users_at_polo($this->config->student_role, $offer_polo->saas_id, $offer_polo->groupname));
            //send tutors
            $this->put_ws('polo/oferta/curso/tutor/'. $offer_polo->saas_id .'/'. urlencode($offer_polo->groupname),
                           $this->get_users_at_polo($this->config->tutor_polo_role, $offer_polo->saas_id, $offer_polo->groupname));
        }
    }

    //envia os usuários com os seus devidos papéis em cada oferta de disciplina.
    function send_users_by_oferta_disciplina() {
        global $DB;

        $sql = "SELECT DISTINCT od.id, od.uid
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
                  JOIN {saas_map_course} cm ON (cm.oferta_disciplina_id = od.id)
                 WHERE oc.enable = 1";
        foreach($DB->get_records_sql($sql) AS $id=>$od) {
            $users = array();
            list($sql, $params) = $this->get_sql_users_by_oferta_disciplina($id);
            $rs = $DB->get_recordset_sql($sql, $params);
            foreach($rs AS $rec) {
                $users[$rec->role][] = $rec->userfield;
            }

            foreach(self::$role_names_disciplinas AS $r) {
                if(!isset($users[$r])) {
                    $users[$r] = array();
                }
                $this->put_ws('oferta/disciplina/' . self::$role_names[$r] . '/' . $od->uid, $users[$r]);
            }
        }
    }

    //Métodos para acesso ao webservice.
    function make_ws_url($functionname) {
        return $this->config->ws_url . '/instituicoes/' . $this->config->api_key  . '/' . $functionname;
    }

    function get_ws($functionname) {
        $curl = new curl();
        $curl->count = 0;

        $resp = $curl->get($this->make_ws_url($functionname));

        if(is_array($curl->info) && isset($curl->info['http_code']) && $curl->info['http_code'] != '200') {
            throw new Exception('Erro de acesso ao SAAS: ' . $curl->info['http_code']);
        }
        if (!empty($curl->error)) {
            throw new Exception($curl->error);
        }

        return json_decode($resp);
    }

    function post_ws($functionname, $data = array()) {
        $curl = new curl();
        $curl->setHeader('Content-Type: application/json');
        $response = $curl->post($this->make_ws_url($functionname), json_encode($data));

        if(is_array($curl->info) && isset($curl->info['http_code']) && $curl->info['http_code'] != '200') {
            throw new Exception('Erro de acesso ao SAAS: ' . $curl->info['http_code']);
        }
        if (!empty($curl->error)) {
            throw new Exception($curl->error, $curl->info);
        }

        return json_decode($response);
    }

    function put_ws($functionname, $data = array()) {
        $curl = new curl();
        $curl->count = 0;
        $curl->setHeader('Content-Type: application/json');

        $path = saas::create_file_to_send($data);

        $curl->put($this->make_ws_url($functionname), array('file'=>$path));

        if(is_array($curl->info) && isset($curl->info['http_code']) && $curl->info['http_code'] != '204') {
            throw new Exception('Erro de acesso ao SAAS: ' . $curl->info['http_code']);
        }
        if (!empty($curl->error)) {
            throw new Exception($curl->error, $curl->info);
        }

        return true;
    }

    // ----------------------------------------------------------------
    // Métodos estáticos

    static function create_file_to_send($data) {
        global $CFG;

        $path = $CFG->dataroot. '/temp/saas_data.txt';
        $file = fopen($path, "w");

        fwrite($file, json_encode($data));
        fclose($file);

        return $path;
    }

    static function save_settings($data) {
        global $DB;

        $DB->delete_records('saas_config_roles');

        foreach(self::$role_names AS $r=>$pap) {
            $rname = 'roles_' . $r;
            if(isset($data->$rname)) {
                foreach($data->$rname AS $roleid) {
                    if($roleid !== '0') {
                        $rec = new stdClass();
                        $rec->role = $r;
                        $rec->roleid = $roleid;
                        $DB->insert_record('saas_config_roles', $rec);
                    }
                }
                unset($data->$rname);
            }
        }

        foreach($data AS $key=>$value) {
            if($key != 'submitbutton') {
                if(is_array($value)) {
                    $val = implode(',', $value);
                } else {
                    $val = $value;
                }
                set_config($key, $val, 'report_saas_export');
            }
        }
    }

    static function get_student_roles_menu() {
        global $DB, $CFG;

        $context = context_system::instance();
        $role_names = role_fix_names(get_all_roles($context), $context);

        if(isset($CFG->gradebookroles) && !empty($CFG->gradebookroles)) {
            $roleids = $CFG->gradebookroles;
        } else {
            $roleids = $DB->get_field('role', 'id', array('shortname' => 'student'));
        }
        $roles_menu = array();
        foreach (explode(',', $roleids) as $roleid) {
            $roles_menu[$roleid] = $role_names[$roleid]->localname;
        }
        return $roles_menu;
    }

    static function get_other_roles_menu() {
        global $DB, $CFG;

        $context = context_system::instance();
        $role_names = role_fix_names(get_all_roles($context), $context);

        if(isset($CFG->gradebookroles) && !empty($CFG->gradebookroles)) {
            $roleids = $CFG->gradebookroles;
        } else {
            $roleids = $DB->get_field('role', 'id', array('shortname' => 'student'));
        }

        $sql = "SELECT *
                  FROM {role}
                 WHERE id NOT IN ($roleids)
                   AND shortname NOT IN ('manager', 'guest', 'user', 'frontpage')";
        $roles = $DB->get_records_sql($sql);

        $roles_menu = array();
        foreach ($roles as $r) {
            $roles_menu[$r->id] = $role_names[$r->id]->localname;
        }
        return $roles_menu;
    }

    static function get_user_info_fields() {
        global $DB;

        $userfields = array();
        if ($user_info_fields = $DB->get_records('user_info_field')) {
            foreach ($user_info_fields as $field) {
                $userfields[$field->shortname] = $field->name;
            }
        }
        return  $userfields;
    }

    static function get_estados() {
        return array(
                    'AC'=>'Acre',
                    'AL'=>'Alagoas',
                    'AM'=>'Amazonas',
                    'AP'=>'Amapá',
                    'BA'=>'Bahia',
                    'CE'=>'Ceará',
                    'DF'=>'Distrito Federal',
                    'ES'=>'Espírito Santo',
                    'GO'=>'Goiás',
                    'MA'=>'Maranhão',
                    'MT'=>'Mato Grosso',
                    'MS'=>'Mato Grosso do Sul',
                    'MG'=>'Minas Gerais',
                    'PA'=>'Pará',
                    'PB'=>'Paraíba',
                    'PR'=>'Paraná',
                    'PE'=>'Pernambuco',
                    'PI'=>'Piauí',
                    'RJ'=>'Rio de Janeiro',
                    'RN'=>'Rio Grande do Norte',
                    'RS'=>'Rio Grande do Sul',
                    'RO'=>'Rondônia',
                    'RR'=>'Roraima',
                    'SC'=>'Santa Catarina',
                    'SE'=>'Sergipe',
                    'SP'=>'São Paulo',
                    'TO'=>'Tocantins');
    }
}
