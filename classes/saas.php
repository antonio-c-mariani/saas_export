<?php

require_once($CFG->libdir . '/gradelib.php');
require_once(dirname(__FILE__) . '/curl.php');

class saas {

    public static $role_types             = array('teacher'=>'professores', 'student'=>'estudantes', 'tutor_polo'=>'tutores', 'tutor_inst'=>'tutores');
    public static $role_types_disciplinas = array('tutor_inst', 'teacher', 'student');
    public static $role_types_polos       = array('tutor_polo', 'student');

    public $config;
    public $api_key;

    private $saas_enrols_with_itemid = null;

    private $count_errors = 0;
    private $errors = array();

    public $curl = null;
    public $count_ws_calls = array('head'=>0, 'get'=>0, 'post'=>0, 'put'=>0, 'delete'=>0);

    function __construct() {
        $this->load_settings();
    }

    function load_settings() {
        global $DB;

        $this->config = get_config('report_saas_export');

        $roles = array();
        foreach($DB->get_recordset('saas_config_roles') as $rec) {
            $roles[$rec->role][] = $rec->roleid;
        }

        foreach(self::$role_types as $r=>$rname) {
            $role = 'roles_'.$r;
            $this->config->$role = isset($roles[$r]) ? implode(',', $roles[$r]) : '';
        }

        $this->api_key = $this->get_config('api_key');
    }

    function get_config($name) {
        if(isset($this->config->{$name})) {
            return $this->config->{$name};
        } else {
            return false;
        }
    }

    function is_configured() {
        $url = $this->get_config('ws_url');
        return !empty($this->api_key) && !empty($url);
    }

    function verify_config($url='', $print_error=true) {
        try {
            $institution = $this->get_ws('', true);
            set_config('nome_instituicao', $institution->nome, 'report_saas_export');
            set_config('sigla_instituicao', $institution->sigla, 'report_saas_export');
        } catch(Exception $e) {
            $url_saas = $this->make_ws_url();
            if($print_error) {
                print_error('api_key_unknown', 'report_saas_export', $url, $url_saas);
            } else {
                echo "\nERRO: " . get_string('api_key_unknown', 'report_saas_export', $url_saas) . "\n";
                return false;
            }
        }

        $rteacher = $this->get_config('roles_teacher');
        $rstudent = $this->get_config('roles_student');
        $rtutor_polo = $this->get_config('roles_tutor_polo');
        $rtutor_inst = $this->get_config('roles_tutor_inst');

        if(empty($rteacher) && empty($rstudent) && empty($rtutor_polo) && empty($rtutor_inst)) {
            if($print_error) {
                print_error('no_roles', 'report_saas_export', $url);
            } else {
                echo "\nERRO: " . get_string('no_roles', 'report_saas_export') . "\n";
                return false;
            }
        }
        return true;
    }

    // types: disciplinas || polos
    function get_role_types($type='disciplinas') {
        global $DB;

        if($type == 'disciplinas') {
            $role_types = self::$role_types_disciplinas;
        } else if($type == 'polos') {
            $role_types = self::$role_types_polos;
        } else {
            return array();
        }

        $sql = "SELECT DISTINCT role FROM {saas_config_roles}";
        $role_settings = $DB->get_records_sql($sql);

        return array_intersect($role_types, array_keys($role_settings));
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
               $this->load_cursos_saas();
               $this->load_ofertas_cursos_saas();
               $this->load_ofertas_disciplinas_saas();
               $this->load_polos_saas();
               set_config('lastupdated', $now, 'report_saas_export');
           } catch (dml_write_exception $e){
               print_error('bd_error', 'report_saas_export', '', $e->debuginfo);
           } catch (Exception $e){
               $url = new moodle_url('index.php', array('action'=>'settings'));
               print_error('ws_error', 'report_saas_export', $url, $e->getMessage());
           }
       }
    }

    function load_disciplinas_saas(){
        global $DB;

        $local = $DB->get_records_menu('saas_disciplinas', array('api_key'=>$this->api_key), '', 'id, enable');

        $disciplinas = $this->get_ws('disciplinas');
        $disciplinas = empty($disciplinas) ? array() : $disciplinas;

        foreach($disciplinas as $dis) {
            $dis->enable = 1;
            if($id = $DB->get_field('saas_disciplinas', 'id', array('api_key'=>$this->api_key, 'uid' => $dis->uid))) {
                $dis->id = $id;
                $DB->update_record('saas_disciplinas', $dis);
                unset($local[$id]);
            } else {
                $dis->api_key = $this->api_key;
                $DB->insert_record('saas_disciplinas', $dis);
            }
        }

        foreach($local AS $id=>$enable) {
            if($enable){
                $DB->set_field('saas_disciplinas', 'enable', 0, array('id'=>$id));
            }
        }
    }

    function load_cursos_saas(){
        global $DB;

        $local = $DB->get_records_menu('saas_cursos', array('api_key'=>$this->api_key), '', 'id, enable');

        $cursos = $this->get_ws('cursos');
        $cursos = empty($cursos) ? array() : $cursos;

        foreach($cursos as $cur) {
            $cur->enable = 1;
            if($id = $DB->get_field('saas_cursos', 'id', array('api_key'=>$this->api_key, 'uid' => $cur->uid))) {
                $cur->id = $id;
                $DB->update_record('saas_cursos', $cur);
                unset($local[$id]);
            } else {
                $cur->api_key = $this->api_key;
                $DB->insert_record('saas_cursos', $cur);
            }
        }

        foreach($local AS $id=>$enable) {
            if($enable){
                $DB->set_field('saas_cursos', 'enable', 0, array('id'=>$id));
            }
        }
    }

    function load_ofertas_cursos_saas(){
        global $DB;

        $local = $DB->get_records_menu('saas_ofertas_cursos', array('api_key'=>$this->api_key), '', 'id, enable');

        $ofertas_cursos_saas = $this->get_ws('ofertas/cursos');
        $ofertas_cursos_saas = empty($ofertas_cursos_saas) ? array() : $ofertas_cursos_saas;

        foreach ($ofertas_cursos_saas as $oferta_curso) {
            $curso_id = $DB->get_field('saas_cursos', 'id', array('api_key' => $this->api_key, 'uid' => $oferta_curso->curso->uid));
            if (empty($curso_id)) {
                $curso_id = -1;
            }

            $record = new stdClass();
            $record->curso_id = $curso_id;
            $record->ano = $oferta_curso->ano;
            $record->periodo = $oferta_curso->periodo;
            $record->enable = 1;

            if($id = $DB->get_field('saas_ofertas_cursos', 'id', array('api_key'=>$this->api_key, 'uid' => $oferta_curso->uid))) {
                $record->id = $id;
                $DB->update_record('saas_ofertas_cursos', $record);
                unset($local[$id]);
            } else {
                $record->uid = $oferta_curso->uid;
                $record->api_key = $this->api_key;
                $DB->insert_record('saas_ofertas_cursos', $record);
            }
        }

        foreach($local AS $id=>$enable) {
            if($enable){
                $DB->set_field('saas_ofertas_cursos', 'enable', 0, array('id'=>$id));
            }
        }
    }

    function load_ofertas_disciplinas_saas(){
        global $DB;

        $local = $DB->get_records_menu('saas_ofertas_disciplinas', array('api_key'=>$this->api_key), '', 'id, enable');

        $ofertas_disciplinas = $this->get_ws('ofertas/disciplinas');
        $ofertas_disciplinas = empty($ofertas_disciplinas) ? array() : $ofertas_disciplinas;

        foreach ($ofertas_disciplinas as $oferta_disciplina){
            $disciplina_id = $DB->get_field('saas_disciplinas', 'id', array('api_key' => $this->api_key, 'uid' => $oferta_disciplina->disciplina->uid));
            if (empty($disciplina_id)) {
                $disciplina_id = -1;
            }

            $oferta_curso_id = $DB->get_field('saas_ofertas_cursos', 'id', array('api_key' => $this->api_key, 'uid' => $oferta_disciplina->ofertaCurso->uid));
            if (empty($oferta_curso_id)) {
                $oferta_curso_id = -1;
            }

            $record = new stdClass();
            $record->disciplina_id = $disciplina_id;
            $record->inicio = !isset($oferta_disciplina->inicio) || empty($oferta_disciplina->inicio) ? '' : $oferta_disciplina->inicio;
            $record->fim = !isset($oferta_disciplina->fim ) || empty($oferta_disciplina->fim) ? '' : $oferta_disciplina->fim;
            $record->oferta_curso_id = $oferta_curso_id;
            $record->enable = 1;

            if($id = $DB->get_field('saas_ofertas_disciplinas', 'id', array('api_key' => $this->api_key, 'uid' => $oferta_disciplina->uid))) {
                $record->id = $id;
                $DB->update_record('saas_ofertas_disciplinas', $record);
                unset($local[$id]);
            } else {
                $record->uid = $oferta_disciplina->uid;
                $record->api_key = $this->api_key;
                $max = $DB->get_field_sql("SELECT MAX(group_map_id) FROM {saas_ofertas_disciplinas}");
                $record->group_map_id = empty($max) ? 1 : $max+1;
                $DB->insert_record('saas_ofertas_disciplinas', $record);
            }
        }

        foreach ($local AS $id=>$enable) {
            if($enable){
                $DB->set_field('saas_ofertas_disciplinas', 'enable', 0, array('id'=>$id));
            }
        }
    }

    function load_polos_saas() {
        global $DB;

        $local = $DB->get_records_menu('saas_polos', array('api_key'=>$this->api_key), '', 'id, enable');

        $polos_saas = $this->get_ws('polos');
        $polos_saas = empty($polos_saas) ? array() : $polos_saas;

        foreach ($polos_saas as $pl){
            $record = new stdClass();
            $record->nome = $pl->nome;
            $record->cidade = $pl->cidade;
            $record->estado = $pl->estado;
            $record->enable = 1;
            if($id = $DB->get_field('saas_polos', 'id', array('api_key'=>$this->api_key, 'uid' => $pl->uid))) {
                $record->id = $id;
                $DB->update_record('saas_polos', $record);
                unset($local[$id]);
            } else {
                $record->uid = $pl->uid;
                $record->api_key = $this->api_key;
                $DB->insert_record('saas_polos', $record);
            }
        }

        foreach ($local AS $id=>$enable) {
            if($enable){
                $DB->set_field('saas_polos', 'enable', 0, array('id'=>$id));
            }
        }
    }

    function get_not_empty_polos_saas_from_oferta_curso($oc_uid) {
        $types = array();
        foreach(self::$role_types_polos AS $t) {
            $types[] = 'quantidadeDe' . ucfirst(self::$role_types[$t]);
        }

        $encoded_oferta_uid = rawurlencode($oc_uid);
        $polos = $this->get_ws("ofertas/cursos/{$encoded_oferta_uid}/polos");

        $not_empty_polos = array();
        foreach($polos AS $p) {
            foreach($types AS $t) {
                if($p->$t != 0) {
                    $not_empty_polos[$p->uid] = $p;
                    break;
                }
            }
        }
        return $not_empty_polos;
    }

    function get_not_empty_ofertas_disciplinas_saas_from_oferta_curso($oc_uid) {
        $types = array();
        foreach(self::$role_types_disciplinas AS $t) {
            $types[] = 'quantidadeDe' . ucfirst(self::$role_types[$t]);
        }

        $encoded_oferta_uid = rawurlencode($oc_uid);
        $ods = $this->get_ws("ofertas/cursos/{$encoded_oferta_uid}/disciplinas");

        $not_empty_ods = array();
        foreach($ods AS $od) {
            foreach($types AS $t) {
                if($od->$t != 0) {
                    $not_empty_ods[$od->uid] = $od;
                    break;
                }
            }
        }
        return $not_empty_ods;
    }

    function get_concatenated_categories_names($categoryid, $separator = '/') {
        global $DB;

        $concat_category = self::get_concat_category();
        $sql = "SELECT ccp.id, ccp.name
                  FROM {course_categories} cc
                  JOIN {course_categories} ccp ON (ccp.id = cc.id OR cc.path LIKE {$concat_category})
                 WHERE cc.id = {$categoryid}
              ORDER BY ccp.depth";
        $cats = $DB->get_records_sql_menu($sql);
        return implode($separator, $cats);
    }

    // Gets para os dados já salvos no plugin
    //---------------------------------------------------------------------------------------------------

    // retorna todos os polos da Institutição
    function get_disciplinas() {
        global $DB;

        return $DB->get_records('saas_disciplinas', array('enable'=>1, 'api_key'=>$this->api_key), 'nome');
    }

    function get_disciplinas_for_oc($ocid=0, $menu_format=false) {
        global $DB;

        $where = '';
        $params = array();
        if(!empty($ocid)) {
            $where = "AND oc.id = :ocid";
            $params['ocid'] = $ocid;
        }

        $sql = "SELECT d.id, d.nome
                  FROM {saas_disciplinas} d
                  JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = d.api_key)
             LEFT JOIN (SELECT dd.id as dis_id
                          FROM {saas_ofertas_cursos} oc
                          JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = oc.api_key)
                          JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_id = oc.id AND od.enable = 1 AND od.api_key = oc.api_key)
                          JOIN {saas_disciplinas} dd ON (dd.id = od.disciplina_id AND dd.enable = 1 AND dd.api_key = oc.api_key)
                         WHERE oc.enable = 1
                           {$where}) dis
                    ON (dis.dis_id = d.id)
                 WHERE d.enable = 1
                   AND dis.dis_id IS NULL
              ORDER BY d.nome";
        if($menu_format) {
            return $DB->get_records_sql_menu($sql, $params);
        } else {
            $disciplinas = array();
            foreach($DB->get_recordset_sql($sql, $params) AS $rec) {
                $disciplinas[] = array($rec->id, $rec->nome);
            }
            return $disciplinas;
        }
    }

    // retorna todos os polos da Institutição
    function get_polos() {
        global $DB;

        return $DB->get_records('saas_polos', array('enable'=>1, 'api_key'=>$this->api_key));
    }

    function get_polo_by_uid($uid) {
        global $DB;

        return $DB->get_record('saas_polos', array('uid'=>$uid, 'api_key'=>$this->api_key, 'enable'=>1));
    }

    // retorna os polos por oferta de curso
    function get_polos_by_oferta_curso($oferta_curso_id=0) {
        global $DB;

        $polo_mapping_type = $this->get_config('polo_mapping');

        $sql = "SELECT DISTINCT sp.*, oc.id as ocid
                  FROM {saas_ofertas_cursos} oc
                  JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = oc.api_key)
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_id = oc.id AND od.enable = 1 AND od.api_key = oc.api_key)
                  JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)
                  JOIN {course} c ON (c.id = cm.courseid)";

        switch ($polo_mapping_type) {
            case 'group_to_polo':
                $sql .= " JOIN {groups} g ON (g.courseid = c.id)
                          JOIN {saas_map_groups_polos} spm ON (spm.groupname = g.name AND spm.api_key = oc.api_key)
                          JOIN {saas_polos} sp ON (sp.id = spm.polo_id AND sp.enable = 1 AND sp.api_key = oc.api_key)";
                break;
            case 'category_to_polo':
                $concat_category = self::get_concat_category();
                $sql .= " JOIN {course_categories} cc ON (cc.id = c.category)
                          JOIN {course_categories} ccp ON (ccp.id = cc.id OR cc.path LIKE {$concat_category})
                          JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'category' AND smcp.instanceid = ccp.id)
                          JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1 AND sp.api_key = oc.api_key)";
                break;
            case 'course_to_polo':
                $sql .= " JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'course' AND smcp.instanceid = c.id)
                          JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1 AND sp.api_key = oc.api_key)";
                break;
            default:
                return array();
        }

        $params = array();

        $polos = array();
        if($oferta_curso_id) {
            $sql .= " AND oc.id = :ocid";
            $params['ocid'] = $oferta_curso_id;
            $polos[$oferta_curso_id] = array();
        } else {
            $ocs = $this->get_ofertas_cursos();
            if(!empty($ocs)) {
                foreach($ocs AS $ocid=>$oc) {
                    $polos[$ocid] = array();
                }
            }
        }

        $sql .= " WHERE oc.enable = 1";
        $sql .= " ORDER BY sp.nome, sp.cidade, sp.estado";

        $recs = $DB->get_recordset_sql($sql, $params);
        foreach($recs as $rec) {
            $polos[$rec->ocid][$rec->id] = $rec;
        }
        return $polos;
    }

    // retorna todas as ofertas de curso
    function has_oferta_curso() {
        global $DB;

        return $DB->record_exists('saas_ofertas_cursos', array('enable'=>1, 'api_key'=>$this->api_key));
    }

    // retorna todas as ofertas de curso
    function get_ofertas_cursos() {
        global $DB;

        $sql = "SELECT oc.*, c.nome, c.uid as curso_uid
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_cursos} c ON (c.id = oc.curso_id)
                 WHERE oc.api_key = :api_key
                   AND oc.enable = 1
              ORDER BY c.nome, oc.ano, oc.periodo";
        return $DB->get_records_sql($sql, array('api_key'=>$this->api_key));
    }

    // retorna uma oferta de curso com base em seu id
    function get_oferta_curso($id) {
        global $DB;

        $sql = "SELECT oc.*, c.nome, c.uid as curso_uid
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_cursos} c ON (c.id = oc.curso_id)
                 WHERE oc.api_key = :api_key
                   AND oc.id = :id
                   AND oc.enable = 1";
        return $DB->get_record_sql($sql, array('api_key'=>$this->api_key, 'id'=>$id));
    }

    // retorna array com ofertas de curso e respectivas ofertas de disciplinas, mapeadas ou não
    function get_ofertas($oferta_curso_id=0) {
        global $DB;

        $ofertas = $this->get_ofertas_cursos();

        $cond = '';
        $params = array();
        if(empty($oferta_curso_id)) {
            foreach($ofertas AS $ocid=>$oc) {
                $ofertas[$ocid]->ofertas_disciplinas = array();
            }
        } else {
            if(!isset($ofertas[$oferta_curso_id])) {
                return array();
            }
            $cond = 'AND oc.id = :id';
            $params['id'] = $oferta_curso_id;

            $oferta = $ofertas[$oferta_curso_id];
            $oferta->ofertas_disciplinas = array();
            $ofertas = array($oferta_curso_id => $oferta);
        }

        $sql = "SELECT DISTINCT od.*, d.nome, oc.id as ocid, cm.id IS NOT NULL AS mapped
                  FROM {saas_ofertas_cursos} oc
                  JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = oc.api_key)
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_id = oc.id AND od.enable = 1 AND od.api_key = oc.api_key)
                  JOIN {saas_disciplinas} d ON (d.id = od.disciplina_id AND d.enable = 1 AND d.api_key = oc.api_key)
             LEFT JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)
                 WHERE oc.enable = 1
                   {$cond}
              ORDER BY d.nome";
        $recs = $DB->get_recordset_sql($sql, $params);
        foreach($recs as $rec) {
            $ofertas[$rec->ocid]->ofertas_disciplinas[$rec->id] = $rec;
        }
        return $ofertas;
    }

    // retorna oferta de disciplina dado seu id
    function get_oferta_disciplina($oferta_disciplina_id) {
        global $DB;

        $sql = "SELECT od.*, d.nome
                  FROM {saas_ofertas_disciplinas} od
                  JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = od.api_key)
                  JOIN {saas_disciplinas} d ON (d.id = od.disciplina_id AND d.enable = 1 AND d.api_key = od.api_key)
                 WHERE od.id = :odid
                   AND od.enable = 1";
        return $DB->get_record_sql($sql, array('odid' => $oferta_disciplina_id));
    }

    function get_ofertas_disciplinas_by_group_map($group_map_id) {
        global $DB;

        return $DB->get_records('saas_ofertas_disciplinas',
                                 array('group_map_id'=>$group_map_id, 'api_key'=>$this->api_key, 'enable'=>1),
                                 null, 'id, oferta_curso_id');
    }

    function get_oferta_disciplina_by_uid($uid) {
        global $DB;

        $sql = "SELECT od.*, d.nome
                  FROM {saas_ofertas_disciplinas} od
                  JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = od.api_key)
                  JOIN {saas_disciplinas} d ON (d.id = od.disciplina_id AND d.enable = 1 AND d.api_key = od.api_key)
                 WHERE od.uid = :uid
                   AND od.enable = 1";
        return $DB->get_record_sql($sql, array('uid' => $uid));
    }

    // retorna ofertas de disciplina de uma oferta de curso dado seu id, num array onde a chave é a id da oferta de curso.
    // retorna todas as oferta de disciplina caso não seja informado o id da oferta de curso.
    function get_ofertas_disciplinas($ocid=0, $only_mapped=true) {
        global $DB;

        if(empty($ocid)) {
            $where = '';
            $params = array();
        } else {
            $where = 'AND oc.id = :ocid';
            $params = array('ocid' => $ocid);
        }

        $join = '';
        if($only_mapped) {
            $join = 'JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)';
        }

        $sql = "SELECT DISTINCT od.*, d.nome, oc.id as ocid
                  FROM {saas_ofertas_disciplinas} od
                  JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = od.api_key)
                  JOIN {saas_ofertas_cursos} oc ON (oc.id = od.oferta_curso_id AND oc.enable = 1 AND oc.api_key = od.api_key)
                  JOIN {saas_disciplinas} d ON (d.id = od.disciplina_id AND d.enable = 1 AND d.api_key = od.api_key)
                  {$join}
                 WHERE od.enable = 1
                   {$where}";
        $recs = $DB->get_recordset_sql($sql, $params);
        $ofertas = array();
        foreach($recs as $rec) {
            $ofertas[$rec->ocid][$rec->id] = $rec;
        }
        return $ofertas;
    }

    function get_sql_users_by_oferta_curso_polo_categories($ocid=0, $poloid=0, $only_count=false) {
        global $DB;

        $condition = '';
	    $field = '';
        $params = array('contextcourse'=>CONTEXT_COURSE, 'enable'=>ENROL_INSTANCE_ENABLED);
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
            $field .= ', COUNT(DISTINCT ra.userid) AS count';
            $distinct = '';
            $orderby = '';
        } else {
            $group_by = '';
            $userid_field = $this->get_config('userid_field');
            if($userid_field == 'username' || $userid_field == 'idnumber') {
                $field .= ", ra.userid, u.{$userid_field} AS uid";
            } else {
                if($fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$userid_field))) {
                    $join_user_info_data = "JOIN {user_info_data} udt ON (udt.fieldid = :fieldid AND udt.userid = u.id AND udt.data != '')";
                    $params['fieldid'] = $fieldid;
                    $field = ", ra.userid, udt.data AS uid";
                } else {
                    print_error('userid_field_unknown', 'report_saas_export', '', $userid_field);
                }
            }
            $distinct = 'DISTINCT';
            $concat_nome = self::get_concat_nome();
            $orderby = ", {$concat_nome}";
	        $field .= ", {$concat_nome} as nome";
        }

        list($join_ra, $params_ra) = $this->get_join_role_assignments();
        $params = array_merge($params, $params_ra);

        $concat_category = self::get_concat_category();
        $sql = "SELECT {$distinct} oc.id AS ocid, sp.id AS p_id, scr.role $field
                  FROM {saas_ofertas_cursos} oc
                  JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = oc.api_key)
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_id = oc.id AND od.enable = 1 AND od.api_key = oc.api_key)
                  JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)
                  JOIN {course} c ON (c.id = cm.courseid)
                  JOIN {enrol} e ON (e.courseid = c.id AND e.status = :enable)
                  JOIN {user_enrolments} ue ON (ue.enrolid = e.id)
                  JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse)
                  {$join_ra}
                  JOIN {saas_config_roles} scr ON (scr.roleid = ra.roleid AND scr.role IN ('student', 'tutor_polo'))
                  JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0)
                  {$join_user_info_data}
                  JOIN {course_categories} cc ON (cc.id = c.category)
                  JOIN {course_categories} ccp ON (ccp.id = cc.id OR cc.path LIKE {$concat_category})
                  JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'category' AND smcp.instanceid = ccp.id)
                  JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1 AND sp.api_key = oc.api_key)
                 WHERE oc.enable = 1
                   {$condition}
              {$group_by}
              ORDER BY oc.id, sp.id, scr.role {$orderby}";
        return array($sql, $params);
    }

    function get_sql_users_by_oferta_curso_polo_courses($ocid=0, $poloid=0, $only_count=false) {
        global $DB;

        $condition = '';
	    $field = '';
        $params = array('contextcourse'=>CONTEXT_COURSE, 'enable'=>ENROL_INSTANCE_ENABLED);
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
            $field = ', COUNT(DISTINCT ra.userid) AS count';
            $distinct = '';
            $orderby = '';
        } else {
            $group_by = '';
            $userid_field = $this->get_config('userid_field');
            if($userid_field == 'username' || $userid_field == 'idnumber') {
                $field = ", ra.userid, u.{$userid_field} AS uid";
            } else {
                if($fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$userid_field))) {
                    $join_user_info_data = "JOIN {user_info_data} udt ON (udt.fieldid = :fieldid AND udt.userid = u.id AND udt.data != '')";
                    $params['fieldid'] = $fieldid;
                    $field = ", ra.userid, udt.data AS uid";
                } else {
                    print_error('userid_field_unknown', 'report_saas_export', '', $userid_field);
                }
            }
            $distinct = 'DISTINCT';
            $concat_nome = self::get_concat_nome();
            $orderby = ", {$concat_nome}";
            $field .= ", {$concat_nome} as nome";
        }

        list($join_ra, $params_ra) = $this->get_join_role_assignments();
        $params = array_merge($params, $params_ra);

        $sql = "SELECT {$distinct} oc.id AS ocid, sp.id AS p_id, scr.role $field
                  FROM {saas_ofertas_cursos} oc
                  JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = oc.api_key)
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_id = oc.id AND od.enable = 1 AND od.api_key = oc.api_key)
                  JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)
                  JOIN {course} c ON (c.id = cm.courseid)
                  JOIN {enrol} e ON (e.courseid = c.id)
                  JOIN {user_enrolments} ue ON (ue.enrolid = e.id)
                  JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse)
                  {$join_ra}
                  JOIN {saas_config_roles} scr ON (scr.roleid = ra.roleid AND scr.role IN ('student', 'tutor_polo'))
                  JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0)
                  {$join_user_info_data}
                  JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'course' AND smcp.instanceid = c.id)
                  JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1 AND sp.api_key = oc.api_key)
                 WHERE oc.enable = 1
                   {$condition}
              {$group_by}
              ORDER BY oc.id, sp.id, scr.role {$orderby}";
        return array($sql, $params);
    }

    function get_sql_users_by_oferta_curso_polo_groups($ocid=0, $poloid=0, $only_count=false) {
        global $DB;

        $condition = '';
	    $field = '';
        $params = array('contextcourse'=>CONTEXT_COURSE, 'enable'=>ENROL_INSTANCE_ENABLED);
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
            $field .= ', COUNT(DISTINCT ra.userid) AS count';
            $distinct = '';
            $orderby = '';
        } else {
            $group_by = '';
            $userid_field = $this->get_config('userid_field');
            if($userid_field == 'username' || $userid_field == 'idnumber') {
                $field .= ", ra.userid, u.{$userid_field} AS uid";
            } else {
                if($fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$userid_field))) {
                    $join_user_info_data = "JOIN {user_info_data} udt ON (udt.fieldid = :fieldid AND udt.userid = u.id AND udt.data != '')";
                    $params['fieldid'] = $fieldid;
                    $field .= ", ra.userid, udt.data AS uid";
                } else {
                    print_error('userid_field_unknown', 'report_saas_export', '', $userid_field);
                }
            }
            $distinct = 'DISTINCT';
            $concat_nome = self::get_concat_nome();
            $orderby = ", {$concat_nome}";
	        $field .= ", {$concat_nome} as nome";
        }

        list($join_ra, $params_ra) = $this->get_join_role_assignments();
        $params = array_merge($params, $params_ra);

        $sql = "SELECT {$distinct} oc.id AS ocid, sp.id AS p_id, scr.role $field
                  FROM {saas_ofertas_cursos} oc
                  JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = oc.api_key)
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_id = oc.id AND od.enable = 1 AND od.api_key = oc.api_key)
                  JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)
                  JOIN {course} c ON (c.id = cm.courseid)
                  JOIN {enrol} e ON (e.courseid = c.id AND e.status = :enable)
                  JOIN {user_enrolments} ue ON (ue.enrolid = e.id)
                  JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse)
                  {$join_ra}
                  JOIN {saas_config_roles} scr ON (scr.roleid = ra.roleid AND scr.role IN ('student', 'tutor_polo'))
                  JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0)
                  {$join_user_info_data}
                  JOIN {groups} g ON (g.courseid = c.id)
                  JOIN {groups_members} gm ON (gm.groupid = g.id AND gm.userid = u.id)
                  JOIN {saas_map_groups_polos} spm ON (spm.groupname = g.name AND spm.api_key = oc.api_key)
                  JOIN {saas_polos} sp ON (sp.id = spm.polo_id AND sp.enable = 1 AND sp.api_key = oc.api_key)
                 WHERE oc.enable = 1
                   {$condition}
              {$group_by}
              ORDER BY oc.id, sp.id, scr.role {$orderby}";
        return array($sql, $params);
    }

    function has_polo() {
        global $DB;

        return $DB->record_exists('saas_polos', array('enable'=>1, 'api_key'=>$this->api_key));
    }

    function get_polos_menu() {
        global $DB;

        $concat_polo = saas::get_concat_polo();
        return $DB->get_records_menu('saas_polos', array('enable'=>1, 'api_key'=>$this->api_key), 'nome', "id, {$concat_polo} as nome");
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
            $polo_counts[$rec->ocid][$rec->p_id][$rec->role] = $rec->count;
        }

        return $polo_counts;
    }

    function get_sql_users_by_oferta_disciplina($id_oferta_curso=0, $id_oferta_disciplina=0, $only_count=false) {
        global $DB;

        $params = array('contextlevel'=>CONTEXT_COURSE, 'enable'=>ENROL_INSTANCE_ENABLED);
        $group_by = 'GROUP BY odid, scr.role';

        $join_user_info_data = '';
        $join_user_lastaccess = '';
        if($only_count) {
            $fields = ', COUNT(DISTINCT ra.userid) AS count';
            $orderby = '';
        } else {
            $fields = ', u.suspended AS global_suspended';
            $group_by .= ', u.suspended';

            $userid_field = $this->get_config('userid_field');
            if($userid_field == 'username' || $userid_field == 'idnumber') {
                $fields .= ", ra.userid, u.{$userid_field} AS uid";
                $group_by .= ', ra.userid, u.' . $userid_field;
            } else {
                if($fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$userid_field))) {
                    $join_user_info_data = "JOIN {user_info_data} udt ON (udt.fieldid = :fieldid AND udt.userid = u.id AND udt.data != '')";
                    $params['fieldid'] = $fieldid;
                    $fields .= ", ra.userid, udt.data AS uid";
                    $group_by .= ', ra.userid, udt.data';
                } else {
                    print_error('userid_field_unknown', 'report_saas_export', '', $userid_field);
                }
            }
            $join_user_lastaccess = 'LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = c.id)';
            $fields .= ', MIN(ue.status) as suspended, MAX(u.currentlogin) AS currentlogin, MAX(ul.timeaccess) AS lastaccess';
            $orderby = ', ' . self::get_concat_nome();
            $group_by .= ', u.firstname, u.lastname';
        }

        $condition = '';
        if($id_oferta_disciplina) {
            $condition .= " AND od.id = {$id_oferta_disciplina}";
        }
        if($id_oferta_curso) {
            $condition .= " AND oc.id = {$id_oferta_curso}";
        }

        list($join_ra, $params_ra) = $this->get_join_role_assignments();
        $params = array_merge($params, $params_ra);

        $sql = "SELECT od.id AS odid, scr.role {$fields}
                  FROM {saas_ofertas_cursos} oc
                  JOIN {config_plugins} cp ON (cp.plugin = 'report_saas_export' AND cp.name = 'api_key' AND cp.value = oc.api_key)
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_id = oc.id AND od.enable = 1 AND od.api_key = oc.api_key)
                  JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)
                  JOIN {course} c ON (c.id = cm.courseid)
                  JOIN {enrol} e ON (e.courseid = c.id AND e.status = :enable)
                  JOIN {user_enrolments} ue ON (ue.enrolid = e.id)
                  JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)
                  {$join_ra}
                  JOIN {saas_config_roles} scr ON (scr.roleid = ra.roleid AND scr.role IN ('student', 'teacher', 'tutor_inst'))
                  JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0)
                  {$join_user_info_data}
                  {$join_user_lastaccess}
                 WHERE oc.enable = 1
                   {$condition}
              {$group_by}
              ORDER BY odid, scr.role {$orderby}";
        return array($sql, $params);
    }

    function get_user($role_type, $userid, $uid) {
        global $DB;

        $dbuser = $DB->get_record('user', array('id'=>$userid), 'id, username, idnumber, firstname, lastname, email');

        $user = new stdClass();
        $user->email = $dbuser->email;
        $user->uid   = $this->get_config('filter_userid_field') ? $this->format_cpf($uid) : $uid;

        $name_field = $this->get_config('name_field_' . $role_type);
        switch($name_field) {
            case 'firstname': $user->nome = $dbuser->firstname; break;
            case 'lastname': $user->nome = $dbuser->lastname; break;
            default: $user->nome = $dbuser->firstname . ' ' . $dbuser->lastname;
        }
        $name_regexp =  $this->get_config('name_regexp');
        if(!empty($name_regexp)) {
            if(preg_match($name_regexp, $user->nome, $matches)) {
                unset($matches[0]);
                $user->nome = implode('', $matches);
            }
        }

        $cpf_field = $this->get_config('cpf_field_' . $role_type);
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

    function get_grades($group_map_id) {
        global $DB;

        $grades = array();
        foreach($DB->get_records('saas_map_course', array('group_map_id' => $group_map_id)) AS $rec) {
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

    // Funções para que enviam ou auxíliam no envio de dados para o SAAS
    //----------------------------------------------------------------------------------------------------------

    //envia os usuários com seus devidos papéis nos pólos.
    function send_users_by_polos($pocid, $send_user_details=true, $clear_poloids=array()) {
        global $DB;

        $oc = $this->get_oferta_curso($pocid);

        $role_types = $this->get_role_types('polos');

        $polos = $this->get_polos();

        $polo_mapping_type = $this->get_config('polo_mapping');
        switch ($polo_mapping_type) {
            case 'group_to_polo':
                list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_groups($pocid);
                break;
            case 'category_to_polo':
                list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_categories($pocid);
                break;
            case 'course_to_polo':
                list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_courses($pocid);
                break;
        }
        $rs = $DB->get_recordset_sql($sql, $params);
        $poloid = 0;
        $users_by_roles = array();
        foreach($rs AS $rec) {
            $this->send_user($rec, $send_user_details);

            if($rec->p_id != $poloid) {
                if($poloid !== 0) {
                    $this->send_users_by_polo($oc->uid, $polos[$poloid]->uid, $users_by_roles);
                }
                foreach($role_types AS $r) {
                    $users_by_roles[$r] = array();
                }
                $poloid = $rec->p_id;
            }
            $users_by_roles[$rec->role][] = $rec->uid;
        }

        //send the last one
        if($poloid !== 0) {
            $this->send_users_by_polo($oc->uid, $polos[$poloid]->uid, $users_by_roles);
        }

        // clear not mapped polos that user selected
        $users_by_roles = array();
        foreach($role_types AS $r) {
            $users_by_roles[$r] = array();
        }
        foreach($clear_poloids AS $poloid=>$ok) {
            $this->send_users_by_polo($oc->uid, $polos[$poloid]->uid, $users_by_roles, false, false);
        }
    }

    function send_users_by_polo($oc_uid, $polo_uid, $users_by_roles, $show_progress=true) {
        if ($show_progress) {
            $this->update_progressbar("Exportando polos");
        }

        $encoded_oferta_uid = rawurlencode($oc_uid);
        $encoded_polo_uid = rawurlencode($polo_uid);
        foreach($users_by_roles AS $r=>$users) {
            $this->put_ws("ofertas/cursos/{$encoded_oferta_uid}/polos/{$encoded_polo_uid}/".self::$role_types[$r], $users);
        }

        if ($show_progress) {
            $this->count_sent_polos++;
        }
    }

    //envia os usuários com os seus devidos papéis em cada oferta de disciplina.
    function send_users_by_ofertas_disciplinas($pocid, $send_user_details=true, $clear_odids=array()) {
        global $DB;

        $ofertas = $this->get_ofertas_disciplinas($pocid, false);
        $ofertas = $ofertas[$pocid];

        $role_types = $this->get_role_types('disciplinas');

        list($sql, $params) = $this->get_sql_users_by_oferta_disciplina($pocid);
        $rs = $DB->get_recordset_sql($sql, $params);
        $odid = 0;
        $users_by_roles = array();
        foreach($rs AS $rec) {
            $this->send_user($rec, $send_user_details);

            if($rec->odid != $odid) {
                if($odid !== 0) {
                    $this->send_users_by_oferta_disciplina($ofertas[$odid], $users_by_roles, $send_user_details);
                }
                foreach($role_types AS $r) {
                    $users_by_roles[$r] = array();
                }
                $odid = $rec->odid;
            }

            $users_by_roles[$rec->role][$rec->uid] = $rec;
        }
        $rs->close();

        //send the last one
        if($odid !== 0) {
            $this->send_users_by_oferta_disciplina($ofertas[$odid], $users_by_roles, $send_user_details);
        }

        // clear not mapped ods that user selected
        $users_by_roles = array();
        foreach($role_types AS $r) {
            $users_by_roles[$r] = array();
        }
        foreach($clear_odids AS $odid=>$ok) {
            $this->send_users_by_oferta_disciplina($ofertas[$odid], $users_by_roles, false, false);
        }
    }

    function send_users_by_oferta_disciplina($oferta_disciplina, $users_by_roles, $send_user_details=true, $show_progress=true) {
        if($show_progress) {
            $this->update_progressbar("Exportando ofertas de disciplina");
        }

        $oferta_uid_encoded = rawurlencode($oferta_disciplina->uid);
        foreach($users_by_roles AS $r=>$users) {
            $this->put_ws("ofertas/disciplinas/{$oferta_uid_encoded}/". self::$role_types[$r], array_keys($users));
            if($r == 'student' && $send_user_details) {
                $grades = $this->get_grades($oferta_disciplina->id);
                $obj_nota = new stdClass();
                $obj_lastaccess = new stdClass();
                $obj_suspended = new stdClass();
                foreach($users AS $uid=>$user) {
                    $user_uid_encoded = rawurlencode($uid);
                    if(isset($grades[$user->userid])) {
                        $obj_nota->nota = $grades[$user->userid];
                        $this->put_ws("ofertas/disciplinas/{$oferta_uid_encoded}/estudantes/{$user_uid_encoded}/nota", $obj_nota);
                    }

                    if(!empty($users_lastaccess[$user->userid])) {
                        $obj_lastaccess->ultimoAcesso = $users_lastaccess[$user->userid];
                        $this->put_ws("ofertas/disciplinas/{$oferta_uid_encoded}/estudantes/{$user_uid_encoded}/ultimoAcesso", $obj_lastaccess);
                    }

                    $obj_suspended->suspenso = $user->suspended == ENROL_USER_SUSPENDED;
                    $this->put_ws("ofertas/disciplinas/{$oferta_uid_encoded}/estudantes/{$user_uid_encoded}/suspenso", $obj_suspended);
                }
            }
        }

        if($show_progress) {
            $this->count_sent_ods++;
        }
    }

    function send_user($rec, $send_user_details=true) {
        if(!isset($this->sent_users[$rec->userid])) {
            $user_uid_encoded = rawurlencode($rec->uid);
            $this->put_ws("pessoas/{$user_uid_encoded}",  $this->get_user($rec->role, $rec->userid, $rec->uid));

            if($send_user_details && $rec->role == 'student') {
                if(!empty($rec->currentlogin)) {
                    $this->put_ws("pessoas/{$user_uid_encoded}/ultimoLogin", array('ultimoLogin'=>$rec->currentlogin));
                }
                $this->put_ws("pessoas/{$user_uid_encoded}/suspenso", array('suspenso'=>!empty($rec->global_suspended)));
            }
            $this->sent_users[$rec->userid] = true;
            $this->count_sent_users[$rec->role]++;
        }
    }

    function send_data($selected_ocs=array(), $send_user_details=true, $clear_ods=array(), $clear_polos=array(), $print_error=true) {
        global $DB;

        if($this->verify_config('', $print_error) === false) {
            exit;
        }

        $this->count_errors = 0;
        $this->errors = array();
        $this->count_sent_ods = 0;
        $this->count_sent_polos = 0;
        $this->sent_users = array();
        $this->count_sent_users = array();
        foreach(self::$role_types AS $r=>$rname) {
            $this->count_sent_users[$r] = 0;
        }

        $this->send_resume(false);
        $this->init_progressbar();

        $this->start_progressbar("Exportando ofertas de disciplina");

        $ofertas_cursos = $this->get_ofertas_cursos();
        $ods_saas = array();
        $polos_saas = array();
        foreach($ofertas_cursos AS $ocid=>$oc) {
            if(isset($selected_ocs[$ocid])) {
                $ods = $this->get_ofertas_disciplinas($ocid, true);
                $this->progressbar_total += count($ods[$ocid]);
            }
        }

        foreach($ofertas_cursos AS $ocid=>$oc) {
            if(isset($selected_ocs[$ocid])) {
                $clear = isset($clear_ods[$ocid]) ? $clear_ods[$ocid] : array();
                $this->send_users_by_ofertas_disciplinas($ocid, $send_user_details, $clear);
            }
        }

        $polo_mapping_type = $this->get_config('polo_mapping');
        if($polo_mapping_type != 'no_polo') {
            $this->start_progressbar("Exportando polos");

            foreach($ofertas_cursos AS $ocid=>$oc) {
                if(isset($selected_ocs[$ocid])) {
                    $pls = $this->get_polos_by_oferta_curso($ocid);
                    $this->progressbar_total += count($pls[$ocid]);
                }
            }

            foreach($ofertas_cursos AS $ocid=>$oc) {
                if(isset($selected_ocs[$ocid])) {
                    $clear = isset($clear_polos[$ocid]) ? $clear_polos[$ocid] : array();
                    $this->send_users_by_polos($ocid, $send_user_details, $clear);
                }
            }
        }

        $this->send_resume();
        $this->end_progressbar();

        $result = array($this->count_errors, $this->errors, $this->count_sent_users, $this->count_sent_ods, $this->count_sent_polos);
        return $result;
    }

    function send_resume($complete=true) {
        $resume = array();
        $resume['config'] = $this->config;
        if($complete) {
            $resume['count_errors'] = $this->count_errors;
            $resume['errors'] = $this->errors;
            $resume['count_sent_ods'] = $this->count_sent_ods;
            $resume['count_sent_polos'] = $this->count_sent_polos;
            $resume['count_sent_users'] = $this->count_sent_users;
        }
        $this->post_ws('moodle/configuracoes', $resume);
    }

    function send_polo($data) {
        $new_polo = new stdClass();
        $new_polo->nome = trim($data->nome);
        $new_polo->cidade = trim($data->cidade);
        $new_polo->estado = $data->estado;
        $this->post_ws('polos', $new_polo);
    }

    function send_oferta_disciplina($data) {
        global $DB;

        $new_oferta = new stdClass();
        $new_oferta->disciplina = new stdClass();
        $new_oferta->disciplina->uid = $DB->get_field('saas_disciplinas', 'uid', array('id'=>$data->disciplina_id), MUST_EXIST);
        $new_oferta->ofertaCurso = new stdClass();
        $new_oferta->ofertaCurso->uid = $DB->get_field('saas_ofertas_cursos', 'uid', array('id'=>$data->oferta_curso_id), MUST_EXIST);
        $new_oferta->inicio = $data->inicio;
        $new_oferta->fim = $data->fim;
        $this->post_ws('ofertas/disciplinas', $new_oferta);
    }

    //Métodos para acesso ao webservice.
    function make_ws_url($functionname='') {
        if(empty($functionname)) {
            return $this->config->ws_url . '/instituicoes/' . $this->config->api_key;
        } else {
            return $this->config->ws_url . '/instituicoes/' . $this->config->api_key  . '/' . $functionname;
        }
    }

    function init_curl() {
        $this->curl = new \report_saas_export\curl();
    }

    function head_ws($functionname, $throw_exception=false) {
        $this->init_curl();
        $this->curl->head($this->make_ws_url($functionname));
        $this->count_ws_calls['head']++;
        return $this->handle_ws_errors('head', $throw_exception);
    }

    function get_ws($functionname='', $throw_exception=false) {
        $this->init_curl();
        $response = $this->curl->get($this->make_ws_url($functionname));
        $this->handle_ws_errors('get', $throw_exception);
        $this->count_ws_calls['get']++;
        return json_decode($response);
    }

    function post_ws($functionname, $data = array(), $throw_exception=false) {
        $this->init_curl();
        $options = array('CURLOPT_HTTPHEADER'=>array('Content-Type: application/json'));
        $response = $this->curl->post($this->make_ws_url($functionname), json_encode($data), $options);
        $this->handle_ws_errors('post', $throw_exception);
        $this->count_ws_calls['post']++;
        return json_decode($response);
    }

    function put_ws($functionname, $data = array(), $throw_exception=false) {
        $this->init_curl();
        $this->curl->put_json($this->make_ws_url($functionname), json_encode($data));
        $this->count_ws_calls['put']++;
        return $this->handle_ws_errors('put', $throw_exception);
    }

    function delete_ws($functionname, $data = array(), $throw_exception=false) {
        $this->init_curl();
        $this->curl->delete($this->make_ws_url($functionname), $data);
        $this->count_ws_calls['delete']++;
        return $this->handle_ws_errors('delete', $throw_exception);
    }

    function handle_ws_errors($ws_type, $throw_exception=false) {
        $info = $this->curl->get_info();
        if($info['http_code'] <= 299) {
            return true;
        } else if($info['http_code'] <= 499) {
            $this->count_errors++;
            if(count($this->errors) < 50) {
                $this->errors[] = "Falha {$info['http_code']} executando '{$ws_type}' no SAAS para: ". $info['url'];
            }
            if($throw_exception) {
                throw new Exception("Falha {$info['http_code']} executando '{$ws_type}' no SAAS para: ". $info['url']);
            }
            return true;
        } else {
            throw new Exception("Falha {$info['http_code']} executando '{$ws_type}' no SAAS para: ". $info['url']);
        }
    }

    // ----------------------------------------------------------------
    // Métodos estáticos

    function save_settings($data) {
        global $DB;

        $DB->delete_records('saas_config_roles');

        $data->ws_url = trim($data->ws_url, ' /');
        $data->api_key = trim($data->api_key);

        foreach(self::$role_types AS $r=>$rname) {
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

        if(!isset($data->filter_userid_field)) {
            set_config('filter_userid_field', '0', 'report_saas_export');
        }
        if(!isset($data->suspended_as_evaded)) {
            set_config('suspended_as_evaded', '0', 'report_saas_export');
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

        $this->load_settings();
    }

    function get_student_roles_menu() {
        global $DB, $CFG;

        $context = self::get_context_system();
        $roles = role_fix_names(get_all_roles($context), $context);

        if(isset($CFG->gradebookroles) && !empty($CFG->gradebookroles)) {
            $roleids = $CFG->gradebookroles;
        } else {
            $roleids = $DB->get_field('role', 'id', array('shortname' => 'student'));
        }
        $roles_menu = array();
        foreach (explode(',', $roleids) as $roleid) {
            $roles_menu[$roleid] = $roles[$roleid]->localname;
        }
        return $roles_menu;
    }

    function get_other_roles_menu() {
        global $DB, $CFG;

        $context = self::get_context_system();
        $roles = role_fix_names(get_all_roles($context), $context);

        if(isset($CFG->gradebookroles) && !empty($CFG->gradebookroles)) {
            $roleids = $CFG->gradebookroles;
        } else {
            $roleids = $DB->get_field('role', 'id', array('shortname' => 'student'));
        }

        $sql = "SELECT *
                  FROM {role}
                 WHERE id NOT IN ($roleids)
                   AND shortname NOT IN ('manager', 'guest', 'user', 'frontpage')";
        $dbroles = $DB->get_records_sql($sql);

        $roles_menu = array();
        foreach ($dbroles as $r) {
            $roles_menu[$r->id] = $roles[$r->id]->localname;
        }
        return $roles_menu;
    }

    function get_user_info_fields() {
        global $DB;

        $userfields = array();
        if ($user_info_fields = $DB->get_records('user_info_field')) {
            foreach ($user_info_fields as $field) {
                $userfields[$field->shortname] = $field->name;
            }
        }
        return  $userfields;
    }

    function format_date($saas_timestamp_inicio, $saas_timestamp_fim=false, $separador=' / ') {
        $result = empty($saas_timestamp_inicio) ? '' : date("d-m-Y", substr($saas_timestamp_inicio, 0, 10));
        if($saas_timestamp_fim !== false) {
            $result .= $separador;
            $result .= empty($saas_timestamp_fim) ? '' : date("d-m-Y", substr($saas_timestamp_fim, 0, 10));
        }
        return $result;
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

    static function get_estados($mostrar_sigla=true) {
        $ufs = array(
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
        if($mostrar_sigla) {
            $siglas = array();
            $keys = array_keys($ufs);
            sort($keys);
            foreach($keys AS $sigla) {
                $siglas[$sigla] = $sigla;
            }
            return $siglas;
        } else {
            return $ufs;
        }
    }

    static function get_context_system() {
        if(class_exists('context_system')) {
            return context_system::instance();
        } else {
            return get_context_instance(CONTEXT_SYSTEM);
        }
    }

    function get_join_role_assignments() {
        global $DB;

        if(is_null($this->saas_enrols_with_itemid)) {
            $this->saas_enrols_with_itemid = array();
            $sql = "SELECT DISTINCT ra.component
                      FROM {role_assignments} ra
                     WHERE ra.component != ''
                       AND ra.itemid > 0";
            foreach($DB->get_records_sql($sql) AS $component=>$obj) {
                $this->saas_enrols_with_itemid[substr($component, 6)] = $component;
            }
        }

        if(empty($this->saas_enrols_with_itemid)) {
            $sql = "JOIN {role_assignments} ra
                      ON (ra.contextid = ctx.id AND
                          ra.userid = ue.userid)";
            $params = array();
        } else {
            list($not_insql_enrol, $params_enrol) = $DB->get_in_or_equal(array_keys($this->saas_enrols_with_itemid), SQL_PARAMS_NAMED, 'enrol', false);
            list($insql_component, $params_component) = $DB->get_in_or_equal(array_values($this->saas_enrols_with_itemid), SQL_PARAMS_NAMED, 'component');
            $params = $params_enrol + $params_component;
            $sql = "JOIN {role_assignments} ra
                      ON (ra.contextid = ctx.id AND
                          ra.userid = ue.userid AND
                          ((ra.component = '' AND e.enrol $not_insql_enrol) OR (ra.component $insql_component AND ra.itemid = e.id)))";
        }
        return array($sql, $params);
    }


    static function get_concat_nome() {
        global $DB;

        if ($DB instanceof pgsql_native_moodle_database) {
            return "(u.firstname || ' ' || u.lastname)";
        } else {
            return "CONCAT(u.firstname, ' ', u.lastname)";
        }
    }

    static function get_concat_category() {
        global $DB;

        if ($DB instanceof pgsql_native_moodle_database) {
            return "('%/' || ccp.id || '/%')";
        } else {
            return "CONCAT('%/', ccp.id, '/%')";
        }
    }

    static function get_concat_polo() {
        global $DB;

        if ($DB instanceof pgsql_native_moodle_database) {
            return "(nome || ' (' || cidade || '/' || estado || ')')";
        } else {
            return "CONCAT(nome, ' (', cidade, '/', estado, ')')";
        }
    }


    function init_progressbar() {
        $this->progressbar_total = 0;
        $this->progressbar_count = 0;
        if (!CLI_SCRIPT && !isset($this->progressbar)) {
            $this->progressbar = new progress_bar('export_saas', 500, true);
        }
    }

    function start_progressbar($msg) {
        $this->progressbar_total = 0;
        $this->progressbar_count = 0;
        if (CLI_SCRIPT) {
            echo "\n== {$msg}\n";
        } else {
            $this->progressbar->update_full(0, $msg);
            ob_flush();
        }
    }

    function update_progressbar($msg) {
        $this->progressbar_count++;
        $final_msg = "{$msg}: {$this->progressbar_count}/{$this->progressbar_total}";
        if (CLI_SCRIPT) {
            echo '  --' . $final_msg . "\n";
        } else {
            $this->progressbar->update($this->progressbar_count, $this->progressbar_total, $final_msg);
            ob_flush();
        }
    }

    function end_progressbar() {
        $msg = "Exportados dados para SAAS";
        if (CLI_SCRIPT) {
            echo "\n== {$msg}\n";
        } else {
            $this->progressbar->update_full(100, $msg);
            ob_flush();
        }
    }

}
