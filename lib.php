<?php

require_once(dirname(__FILE__) . '/../../config.php');
require_once('lib_teste.php');

class saas {

    public static $role_names = array('teacher', 'student', 'tutor_polo', 'tutor_inst');

    function __construct() {
        $this->config = get_config('report_saas_export');
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
               $this->load_ofertas_cursos_saas();
               $this->load_ofertas_disciplinas_saas();
               //$this->load_polos_saas();
               set_config('lastupdated', $now, 'report_saas_export');
           } catch (Exception $e){
            var_dump($e);
               print_error($e->getMessage());
           }
       }
    }

    function load_cursos_saas(){
        return $this->get_ws('cursos');
    }

    function load_disciplinas_saas(){
        return $this->get_ws('disciplinas');
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
                    $record->nome = $curso->nome .'('. $oferta_curso->ano .'/'. $oferta_curso->periodo .')';
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
        //$ofertas_cursos_saas = json_decode(teste_get_ofertas_cursos());
        foreach($local AS $uid=>$rec) {
            if($rec->enable){
                $DB->set_field('saas_ofertas_cursos', 'enable', 0, array('id'=>$rec->id));
            }
        }
        exit;
    }

    function load_ofertas_disciplinas_saas(){
        global $DB;

        $ofertas_curso = $this->get_ofertas_curso_salvas();
        $local = $DB->get_records('saas_ofertas_disciplinas', null, null ,'uid, id, enable');

        foreach ($ofertas_curso as $of) {
            $ofertas_disciplina = $this->get_ws('ofertas/curso/'.$of->uid.'/ofertas/disciplinas');
            //$ofertas_disciplina = json_decode(teste_get_ofertas_disciplinas($of->uid));

            if (!empty($ofertas_disciplina)) {
                foreach ($ofertas_disciplina as $od){
                    $record = new stdClass();
                    $record->inicio = $od->inicio;
                    $record->fim = $od->fim;
                    $record->nome = $od->disciplina->nome;
                    $record->oferta_curso_uid = $of->uid;
                    $record->enable = 1;

                    if (isset($local[$od->uid])){
                        $record->id = $local[$od->uid]->id;
                        $DB->update_record('saas_ofertas_disciplinas', $record);
                        unset($local[$od->uid]);
                    } else {
                        $record->uid = $od->uid;
                        $DB->insert_record('saas_ofertas_disciplinas', $record);
                    }
                }
            }
        }
        
        foreach ($local AS $uid=>$rec) {
            if($rec->enable){
                $DB->set_field('saas_ofertas_disciplinas', 'enable', 0, array('id'=>$rec->id));
            }
        }
    }

    function load_polos_saas(){
        global $DB;

        $local = $DB->get_records('saas_polos', null, '' ,'uid, id, enable');

        $polos_saas = $this->get_ws('polos');
        
        if (!empty($polos_saas)) {
            foreach ($polos_saas as $pl){
                $record = new stdClass();
                $record->name = $pl->nome;
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

        return $DB->get_records('saas_ofertas_cursos', array('enable'=>1));
    }
    
    function get_ofertas_disciplinas_salvas() {
        global $DB;

        return $DB->get_records('saas_ofertas_disciplinas', array('enable'=>1));
    }

    function get_mapeamento_cursos() {
        global $DB;

        return $DB->get_records('saas_course_mapping');
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

        $polos = $DB->get_records('saas_polos', array('enable'=>1), 'name');

        print html_writer::start_tag('DIV', array('align'=>'center'));
        print $OUTPUT->box_start('generalbox');
        print $OUTPUT->heading(get_string('polos_title', 'report_saas_export'));

        $table = new html_table();
        $table->head = array('Ident.', 'Nome do Polo');
        $table->data = array();
        foreach($polos as $pl) {
            $table->data[] = array($pl->uid, $pl->name);
        }
        print html_writer::table($table);

        print $OUTPUT->box_end();
        print html_writer::end_tag('DIV');
    }

    function show_table_ofertas_curso_disciplinas() {
        global $DB, $OUTPUT;

        $sql = "SELECT oc.uid AS oc_uid, oc.name AS oc_name, oc.year AS oc_year, oc.period AS oc_period,
                       od.uid AS od_uid, od.name AS od_name, od.beginning AS od_beginning, od.ending AS od_ending, od.year AS od_year
                  FROM {saas_ofertas_cursos} oc
             LEFT JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
                 WHERE oc.enable = 1
              ORDER BY oc.name, oc.year, oc.period, od.name";
        $ofertas = $DB->get_recordset_sql($sql);

        print html_writer::start_tag('DIV', array('align'=>'center'));
        print $OUTPUT->box_start('generalbox');
        print $OUTPUT->heading(get_string('ofertas_title', 'report_saas_export'));

        $oc_data = array();
        $od_data = array();
        foreach($ofertas as $of) {
            if(!isset($oc_data[$of->oc_uid])) {
                $oc_data[$of->oc_uid] = array($of->oc_uid, $of->oc_name, $of->oc_year. '/'.$of->oc_period);
                $od_data[$of->oc_uid] = array();
            }
            if(!empty($of->od_uid)) {
                $od_data[$of->oc_uid][] = array($of->od_uid, $of->od_name, $of->od_year, $of->od_beginning, $of->od_ending);
            }
        }

        $table = new html_table();
        $table->head = array('Ident.', 'Nome da Oferta de Curso', 'Período');
        $table->data = array();
        foreach($oc_data AS $oc_uid=>$oc_rec) {
            $table->data[] = $oc_rec;
            if(!empty($od_data[$oc_uid])) {
                $od_table = new html_table();
                $od_table->data = $od_data[$oc_uid];
                $table->data[] = array('', html_writer::table($od_table));
            }
        }
        print html_writer::table($table);
        print $OUTPUT->box_end();
        print html_writer::end_tag('DIV');
    }

    // Funções para salvar os mapeamentos
    // -----------------------------------------------------------------

    function save_ofertas_cursos_mapping($formdata){
        global $DB;

        $ofertas_cursos = $formdata->map;
        foreach ($ofertas_cursos as $saas_id => $categoryid){
            $sql = "UPDATE {ofertas_cursos_saas}
                       SET categoryid = ?
                     WHERE saas_id = ?";
            $params = array('categoryid'=>$categoryid, 'saas_id'=>$saas_id);
            $DB->execute($sql, $params);
        }
    }

    function save_ofertas_disciplinas_offers_mapping($formdata){
        global $DB;

        $ofertas_cursos = $formdata->map;
        foreach ($ofertas_cursos as $saas_course_id => $ofertas_disciplinas_offers) {
            foreach($ofertas_disciplinas_offers as $saas_class_offer_id => $courseid) {
                $sql = "UPDATE {ofertas_disciplinas_saas}
                           SET courseid = :courseid
                         WHERE saas_id = :saas_class_id";
                $params = array('courseid'=>$courseid, 'saas_class_id'=>$saas_class_offer_id);
                $DB->execute($sql, $params);
            }
        }
    }

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

        $config_role = "{$user_type}_role";
        $str_roleids = $this->config->{$config_role};
        if(empty($str_roleids)) {
            return false;
        }

        $config_cpf = $this->config->{"cpf_{$user_type}_field"};

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

        $config_name = $this->config->{"name_{$user_type}_field"};
        switch ($config_name){
            case 'firstname': 
            case 'lastname': 
                $from_name = $config_name;
                break;
            case 'firstnamelastname': 
                $from_name = "CONCAT(u.firstname, ' ', u.lastname)";
                break;
        }
        $fields = "u.{$this->config->user_id_field} AS uid,
                   u.email, {$from_name} as nome,
                   {$cpf_field}";

        list($in_sql, $params) = $DB->get_in_or_equal(explode(',', $str_roleids), SQL_PARAMS_NAMED);
        $sql = "SELECT DISTINCT {$fields}
                  FROM {ofertas_cursos_saas} AS oc
                  JOIN {ofertas_disciplinas_saas} AS od
                    ON (od.saas_course_offer_id = oc.id AND
                        od.enable = 1)
                  JOIN {course} c
                    ON (c.id = od.courseid)
                  JOIN {context} ctx
                    ON (ctx.contextlevel = :contextlevel AND
                        ctx.instanceid = c.id)
                  JOIN {role_assignments} ra
                    ON (ra.contextid = ctx.id AND
                        ra.roleid {$in_sql})
                  JOIN {user} u
                    ON (u.id = ra.userid)
                  {$join_custom_fields}
                 WHERE oc.enable = 1";
        return array($sql, $params);
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
                        $this->post_ws('pessoa', $users_to_send);
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
                    $this->post_ws('pessoa', $users_to_send);
                }
            }
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
                    ON (u.id = gm.userid AND u.deleted = 0)
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
                    ON (u.id = ra.userid)
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
        $this->send_users();
        $this->send_users_by_role();
        $this->send_users_by_polo();
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
            $this->post_ws('polo/oferta/curso/aluno/'. $offer_polo->saas_id .'/'. urlencode($offer_polo->groupname),
                           $this->get_users_at_polo($this->config->student_role, $offer_polo->saas_id, $offer_polo->groupname));
            //send tutors
            $this->post_ws('polo/oferta/curso/tutor/'. $offer_polo->saas_id .'/'. urlencode($offer_polo->groupname),
                           $this->get_users_at_polo($this->config->tutor_polo_role, $offer_polo->saas_id, $offer_polo->groupname));
        }
    }

    //envia os usuários com os seus devidos papéis em cada oferta de disciplina.
    function send_users_by_role() {
        global $DB;

        $sql = "SELECT saas_id, courseid
                  FROM {ofertas_disciplinas_saas} od
                 WHERE od.enable = 1
                   AND od.courseid <> -1";
        $mapped_ofertas_disciplinas = $DB->get_records_sql_menu($sql);
        $roles = array('aluno' => $this->config->student_role,
                       'professor' => $this->config->teacher_role,
                       'tutor' =>$this->config->tutor_role);

        foreach ($mapped_ofertas_disciplinas as $saas_id => $courseid){
            foreach ($roles as $papel => $str_roleids){
                $this->post_ws('oferta/disciplina/'.$papel.'/' . $saas_id, $this->get_users_by_role($str_roleids, $courseid));
            }
        }
    }

    function make_ws_url($functionname) {
        return $this->config->ws_url . '/' . $this->config->api_key . '/' . $functionname;
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
        $curl->count = 0;        
        $curl->setHeader('Content-Type: application/json');
        
        $curl->post($this->make_ws_url($functionname), json_encode($data));

        if(is_array($curl->info) && isset($curl->info['http_code']) && $curl->info['http_code'] != '200') {
            throw new Exception('Erro de acesso ao SAAS: ' . $curl->info['http_code']);
        }
        if (!empty($curl->error)) {
            throw new Exception($curl->error, $curl->info);
        }

        return true;
    }

    // ----------------------------------------------------------------
    // Métodos estáticos

    static function save_settings($data) {
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

}