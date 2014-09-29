<?php

require_once($CFG->libdir . '/gradelib.php');
require_once(dirname(__FILE__) . '/curl.php');

class saas {

    public static $role_types             = array('teacher'=>'professores', 'student'=>'estudantes', 'tutor_polo'=>'tutores', 'tutor_inst'=>'tutores');
    public static $role_types_disciplinas = array('tutor_inst', 'teacher', 'student');
    public static $role_types_polos       = array('tutor_polo', 'student');

    public $config;

    private $count_errors = 0;
    private $errors = array();

    public $curl = null;

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

    function is_configured() {
        $api_key = $this->get_config('api_key');
        $url = $this->get_config('ws_url');
        return !empty($api_key) && !empty($url);
    }

    function verify_config($url) {
        try {
            $this->get_ws('', true);
        } catch(Exception $e) {
            print_error('api_key_unknown', 'report_saas_export', $url);
        }

        $rteacher = $this->get_config('roles_teacher');
        $rstudent = $this->get_config('roles_student');
        $rtutor_polo = $this->get_config('roles_tutor_polo');
        $rtutor_inst = $this->get_config('roles_tutor_inst');

        if(empty($rteacher) && empty($rstudent) && empty($rtutor_polo) && empty($rtutor_inst)) {
            print_error('no_roles', 'report_saas_export', $url);
        }
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

        $local = $DB->get_records('saas_disciplinas', null, null ,'uid, id, enable');

        $disciplinas = $this->get_ws('disciplinas');
        $disciplinas = empty($disciplinas) ? array() : $disciplinas;

        foreach($disciplinas as $dis) {
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

        $cursos_saas = $this->get_ws('cursos');
        $cursos_saas = empty($cursos_saas) ? array() : $cursos_saas;

        $ofertas_cursos_saas = $this->get_ws('ofertas/cursos');
        $ofertas_cursos_saas = empty($ofertas_cursos_saas) ? array() : $ofertas_cursos_saas;

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

        $ofertas_disciplinas = $this->get_ws('ofertas/disciplinas');
        $ofertas_disciplinas = empty($ofertas_disciplinas) ? array() : $ofertas_disciplinas;

        foreach ($ofertas_disciplinas as $oferta_disciplina){
            $record = new stdClass();
            $record->uid = $oferta_disciplina->uid;
            $record->disciplina_uid = $oferta_disciplina->disciplina->uid;
            $record->inicio = !isset($oferta_disciplina->inicio) || empty($oferta_disciplina->inicio) ? '' : $oferta_disciplina->inicio;
            $record->fim = !isset($oferta_disciplina->fim ) || empty($oferta_disciplina->fim) ? '' : $oferta_disciplina->fim;
            $record->oferta_curso_uid = $oferta_disciplina->ofertaCurso->uid;
            $record->enable = 1;

            if (isset($local[$oferta_disciplina->uid])){
                $record->id = $local[$oferta_disciplina->uid]->id;
                $DB->update_record('saas_ofertas_disciplinas', $record);
                unset($local[$oferta_disciplina->uid]);
            } else {
                $max = $DB->get_field_sql("SELECT MAX(group_map_id) FROM {saas_ofertas_disciplinas}");
                $record->group_map_id = empty($max) ? 1 : $max+1;
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
        $polos_saas = empty($polos_saas) ? array() : $polos_saas;

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

        foreach ($local AS $uid=>$rec) {
            if($rec->enable){
                $DB->set_field('saas_polos', 'enable', 0, array('id'=>$rec->id));
            }
        }
    }

    function get_concatenated_categories_names($categoryid) {
        global $DB;

        $sql = "SELECT ccp.id, ccp.name
                  FROM {course_categories} cc
                  JOIN {course_categories} ccp ON (ccp.id = cc.id OR cc.path LIKE CONCAT('%/',ccp.id,'/%'))
                 WHERE cc.id = {$categoryid}
              ORDER BY ccp.depth";
        $cats = $DB->get_records_sql_menu($sql);
        return implode('/', $cats);
    }

    // Gets para os dados já salvos no plugin
    //---------------------------------------------------------------------------------------------------

    // retorna todos os polos da Institutição
    function get_polos() {
        global $DB;

        return $DB->get_records('saas_polos', array('enable'=>1), 'nome, cidade, estado');
    }

    // retorna os polos por oferta de curso
    function get_polos_by_oferta_curso($oferta_curso_id=0) {
        global $DB;

        $polo_mapping_type = $this->get_config('polo_mapping');

        $sql = "SELECT DISTINCT sp.*, oc.id as ocid
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
                  JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)
                  JOIN {course} c ON (c.id = cm.courseid)";

        switch ($polo_mapping_type) {
            case 'group_to_polo':
                $sql .= " JOIN {groups} g ON (g.courseid = c.id)
                          JOIN {saas_map_groups_polos} spm ON (spm.groupname = g.name)
                          JOIN {saas_polos} sp ON (sp.id = spm.polo_id AND sp.enable = 1)";
                break;
            case 'category_to_polo':
                $sql .= " JOIN {course_categories} cc ON (cc.id = c.category)
                          JOIN {course_categories} ccp ON (ccp.id = cc.id OR cc.path LIKE CONCAT('%/',ccp.id,'/%'))
                          JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'category' AND smcp.instanceid = ccp.id)
                          JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1)";
                break;
            case 'course_to_polo':
                $sql .= " JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'course' AND smcp.instanceid = c.id)
                          JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1)";
                break;
        }

        $sql .= " WHERE oc.enable = 1";
        $params = array();
        if($oferta_curso_id) {
            $sql .= " AND oc.id = :ocid";
            $params['ocid'] = $oferta_curso_id;
        }

        $sql .= " ORDER BY sp.nome, sp.cidade, sp.estado";

        $polos = array();
        $ocs = $this->get_ofertas_cursos();
        if(!empty($ocs)) {
            foreach($ocs AS $ocid=>$oc) {
                $polos[$ocid] = array();
            }
        }

        $recs = $DB->get_recordset_sql($sql, $params);
        foreach($recs as $rec) {
            $polos[$rec->ocid][$rec->id] = $rec;
        }
        return $polos;
    }

    // retorna todas as ofertas de curso
    function get_ofertas_cursos() {
        global $DB;

        return $DB->get_records('saas_ofertas_cursos', array('enable'=>1), 'nome, ano, periodo');
    }

    // retorna array com ofertas de curso e respectivas ofertas de disciplinas, mapeadas ou não
    function get_ofertas($oferta_curso_id=0) {
        global $DB;

        $cond = '';
        $params = array();
        if(!empty($oferta_curso_id)) {
            $cond = 'AND oc.id = :id';
            $params['id'] = $oferta_curso_id;
        }

        $ofertas = $this->get_ofertas_cursos();
        foreach($ofertas AS $ocid=>$oc) {
            $ofertas[$ocid]->ofertas_disciplinas = array();
        }

        $sql = "SELECT DISTINCT od.*, d.nome, oc.id as ocid, cm.id IS NOT NULL AS mapped
                  FROM {saas_ofertas_cursos} oc
             LEFT JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
             LEFT JOIN {saas_disciplinas} d ON (d.uid = od.disciplina_uid AND d.enable = 1)
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
                  JOIN {saas_disciplinas} d ON (d.uid = od.disciplina_uid AND d.enable = 1)
                 WHERE od.id = :odid
                   AND od.enable = 1";
        return $DB->get_record_sql($sql, array('odid' => $oferta_disciplina_id));
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
                  JOIN {saas_ofertas_cursos} oc ON (oc.uid = od.oferta_curso_uid AND oc.enable = 1)
                  JOIN {saas_disciplinas} d ON (d.uid = od.disciplina_uid AND d.enable = 1)
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
            $orderby = ', CONCAT(u.firstname, u.lastname)';
	        $field .= ', CONCAT(u.firstname, u.lastname) as nome';
        }

        $sql = "SELECT {$distinct} oc.id AS ocid, sp.id AS p_id, scr.role $field
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
                  JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)
                  JOIN {course} c ON (c.id = cm.courseid)
                  JOIN {enrol} e ON (e.courseid = c.id AND e.status = :enable)
                  JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.status = :active)
                  JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse)
                  JOIN {role_assignments} ra
                    ON (ra.contextid = ctx.id AND
                        ra.userid = ue.userid AND
                        ((ra.component = '' AND e.enrol = 'manual') OR (ra.component = CONCAT('enrol_',e.enrol) AND ra.itemid = e.id)))
                  JOIN {saas_config_roles} scr ON (scr.roleid = ra.roleid AND scr.role IN ('student', 'tutor_polo'))
                  JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0)
                  {$join_user_info_data}
                  JOIN {course_categories} cc ON (cc.id = c.category)
                  JOIN {course_categories} ccp ON (ccp.id = cc.id OR cc.path LIKE CONCAT('%/',ccp.id,'/%'))
                  JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'category' AND smcp.instanceid = ccp.id)
                  JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1)
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
            $orderby = ', CONCAT(u.firstname, u.lastname)';
            $field .= ', CONCAT(u.firstname, u.lastname) as nome';
        }

        $sql = "SELECT {$distinct} oc.id AS ocid, sp.id AS p_id, scr.role $field
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
                  JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)
                  JOIN {course} c ON (c.id = cm.courseid)
                  JOIN {enrol} e ON (e.courseid = c.id AND e.status = :enable)
                  JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.status = :active)
                  JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse)
                  JOIN {role_assignments} ra
                    ON (ra.contextid = ctx.id AND
                        ra.userid = ue.userid AND
                        ((ra.component = '' AND e.enrol = 'manual') OR (ra.component = CONCAT('enrol_',e.enrol) AND ra.itemid = e.id)))
                  JOIN {saas_config_roles} scr ON (scr.roleid = ra.roleid AND scr.role IN ('student', 'tutor_polo'))
                  JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0)
                  {$join_user_info_data}
                  JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'course' AND smcp.instanceid = c.id)
                  JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1)
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
            $orderby = ', CONCAT(u.firstname, u.lastname)';
	        $field .= ', CONCAT(u.firstname, u.lastname) as nome';
        }

        $sql = "SELECT {$distinct} oc.id AS ocid, sp.id AS p_id, scr.role $field
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
                  JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)
                  JOIN {course} c ON (c.id = cm.courseid)
                  JOIN {enrol} e ON (e.courseid = c.id AND e.status = :enable)
                  JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.status = :active)
                  JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse)
                  JOIN {role_assignments} ra
                    ON (ra.contextid = ctx.id AND
                        ra.userid = ue.userid AND
                        ((ra.component = '' AND e.enrol = 'manual') OR (ra.component = CONCAT('enrol_',e.enrol) AND ra.itemid = e.id)))
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
              ORDER BY oc.id, sp.id, scr.role {$orderby}";
        return array($sql, $params);
    }

    function get_polos_menu() {
        global $DB;

        return $DB->get_records_menu('saas_polos', array('enable'=>1), 'nome', "id, CONCAT(nome, ' (', cidade, '/', estado, ')') as nome");
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

        if($this->get_config('suspended_as_evaded') && !$only_count) {
            $user_enrol_condition = "AND (ue.status = :active OR scr.role = 'student')";
        } else {
            $user_enrol_condition = 'AND ue.status = :active';
        }
        $params['active'] = ENROL_USER_ACTIVE;

        $join_user_info_data = '';
        $join_user_lastaccess = '';
        if($only_count) {
            $fields = 'COUNT(DISTINCT ra.userid) AS count';
            $orderby = '';
        } else {
            $userid_field = $this->get_config('userid_field');
            if($userid_field == 'username' || $userid_field == 'idnumber') {
                $fields = "ra.userid, u.{$userid_field} AS uid";
                $group_by .= ', ra.userid, u.' . $userid_field;
            } else {
                if($fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$userid_field))) {
                    $join_user_info_data = "JOIN {user_info_data} udt ON (udt.fieldid = :fieldid AND udt.userid = u.id AND udt.data != '')";
                    $params['fieldid'] = $fieldid;
                    $fields = "ra.userid, udt.data AS uid";
                    $group_by .= ', ra.userid, udt.data';
                } else {
                    print_error('userid_field_unknown', 'report_saas_export', '', $userid_field);
                }
            }
            $join_user_lastaccess = 'LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = c.id)';
            $fields .= ', MAX(ue.status) as suspended, MAX(u.currentlogin) AS currentlogin, MAX(ul.timeaccess) AS lastaccess';
            $orderby = ', CONCAT(u.firstname, u.lastname)';
            $group_by .= ', u.firstname, u.lastname';
        }

        $condition = '';
        if($id_oferta_disciplina) {
            $condition .= " AND od.id = {$id_oferta_disciplina}";
        }
        if($id_oferta_curso) {
            $condition .= " AND oc.id = {$id_oferta_curso}";
        }

        $sql = "SELECT od.id AS odid, scr.role, {$fields}
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
                  JOIN {saas_map_course} cm ON (cm.group_map_id = od.group_map_id)
                  JOIN {course} c ON (c.id = cm.courseid)
                  JOIN {enrol} e ON (e.courseid = c.id AND e.status = :enable)
                  JOIN {user_enrolments} ue ON (ue.enrolid = e.id)
                  JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)
                  JOIN {role_assignments} ra
                    ON (ra.contextid = ctx.id AND
                        ra.userid = ue.userid AND
                        ((ra.component = '' AND e.enrol = 'manual') OR (ra.component = CONCAT('enrol_',e.enrol) AND ra.itemid = e.id)))
                  JOIN {saas_config_roles} scr ON (scr.roleid = ra.roleid AND scr.role IN ('student', 'teacher', 'tutor_inst'))
                  JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0)
                  {$join_user_info_data}
                  {$join_user_lastaccess}
                 WHERE oc.enable = 1
                   {$condition}
                   {$user_enrol_condition}
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

    //envia os usuários com seus devidos papéis nos pólos.
    function send_users_by_polos($pocid, $id_polo=0, $send_user_details=true) {
        global $DB;

        $oc = $DB->get_record('saas_ofertas_cursos', array('id'=>$pocid));
        $role_types = $this->get_role_types('polos');

        if($id_polo) {
            $polos = array($id_polo=>$DB->get_record('saas_polos', array('id'=>$id_polo)));
        } else {
            $polos = $this->get_polos_by_oferta_curso($pocid);
            $polos = $polos[$pocid];
        }

        $polo_mapping_type = $this->get_config('polo_mapping');
        switch ($polo_mapping_type) {
            case 'group_to_polo':
                list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_groups($pocid, $id_polo);
                break;
            case 'category_to_polo':
                list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_categories($pocid, $id_polo);
                break;
            case 'course_to_polo':
                list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_courses($pocid, $id_polo);
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
                    unset($polos[$poloid]);
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
            unset($polos[$poloid]);
        }

        foreach($role_types AS $r) {
            $users_by_roles[$r] = array();
        }
        foreach($polos AS $pid=>$polo) {
            $this->send_users_by_polo($oc->uid, $polo->uid, $users_by_roles);
        }
    }

    function send_users_by_polo($oc_uid, $polo_uid, $users_by_roles) {
        $encoded_oferta_uid = rawurlencode($oc_uid);
        $encoded_polo_uid = rawurlencode($polo_uid);
        foreach($users_by_roles AS $r=>$users) {
            $this->put_ws("ofertas/cursos/{$encoded_oferta_uid}/polos/{$encoded_polo_uid}/".self::$role_types[$r], $users);
        }
        $this->count_sent_polos++;
    }

    //envia os usuários com os seus devidos papéis em cada oferta de disciplina.
    function send_users_by_ofertas_disciplinas($pocid=0, $podid=0, $send_user_details=true) {
        global $DB;

        if($pocid) {
            $ofertas = $this->get_ofertas_disciplinas($pocid, true);
            $ofertas = $ofertas[$pocid];
        } else if($podid) {
            $ofertas = array($podid=>$this->get_oferta_disciplina($podid));
        } else {
            return;
        }

        $role_types = $this->get_role_types('disciplinas');

        list($sql, $params) = $this->get_sql_users_by_oferta_disciplina($pocid, $podid);
        $rs = $DB->get_recordset_sql($sql, $params);
        $odid = 0;
        $users_by_roles = array();
        foreach($rs AS $rec) {
            $this->send_user($rec, $send_user_details);

            if($rec->odid != $odid) {
                if($odid !== 0) {
                    $this->send_users_by_oferta_disciplina($ofertas[$odid], $users_by_roles, $users_lastaccess, $users_suspended, $send_user_details);
                    unset($ofertas[$odid]);
                }
                foreach($role_types AS $r) {
                    $users_by_roles[$r] = array();
                }
                $users_lastaccess = array();
                $users_suspended = array();
                $odid = $rec->odid;
            }

            if(empty($rec->suspended)) {
                $users_by_roles[$rec->role][$rec->userid] = $rec->uid;
                if(!empty($rec->lastaccess)) {
                    $users_lastaccess[$rec->userid] = $rec->lastaccess;
                }
            } else {
                $users_suspended[$rec->userid] = $rec->uid;
            }
        }

        //send the last one
        if($odid !== 0) {
            $this->send_users_by_oferta_disciplina($ofertas[$odid], $users_by_roles, $users_lastaccess, $users_suspended, $send_user_details);
            unset($ofertas[$odid]);
        }

        foreach($role_types AS $r) {
            $users_by_roles[$r] = array();
        }
        foreach($ofertas AS $odid=>$od) {
            $this->send_users_by_oferta_disciplina($od, $users_by_roles, array(), array(), false);
        }
    }

    function send_users_by_oferta_disciplina($oferta_disciplina, $users_by_roles, $users_lastaccess, $users_suspended, $send_user_details=true) {
        $oferta_uid_encoded = rawurlencode($oferta_disciplina->uid);
        foreach($users_by_roles AS $r=>$users) {
            $this->put_ws("ofertas/disciplinas/{$oferta_uid_encoded}/". self::$role_types[$r], array_values($users));
            if($r == 'student' && $send_user_details) {
                $grades = $this->get_grades($oferta_disciplina->id);
                $obj_nota = new stdClass();
                $obj_lastaccess = new stdClass();
                foreach($users AS $userid=>$uid) {
                    if(!empty($uid)) {
                        $user_uid_encoded = rawurlencode($uid);
                        if(isset($grades[$userid])) {
                            $obj_nota->nota = $grades[$userid];
                            $this->put_ws("ofertas/disciplinas/{$oferta_uid_encoded}/estudantes/{$user_uid_encoded}/nota", $obj_nota);
                        }

                        if(isset($users_lastaccess[$userid])) {
                            $obj_lastaccess->ultimoAcesso = $users_lastaccess[$userid];
                            $this->put_ws("ofertas/disciplinas/{$oferta_uid_encoded}/estudantes/{$user_uid_encoded}/ultimoAcesso", $obj_lastaccess);
                        }

                    }
                }

                $obj_suspended = new stdClass();
                foreach($users_suspended AS $userid=>$uid) {
                    if(!empty($uid)) {
                        $user_uid_encoded = rawurlencode($uid);
                        $obj_suspended->suspenso = true;
                        $this->put_ws("ofertas/disciplinas/{$oferta_uid_encoded}/estudantes/{$user_uid_encoded}/suspenso", $obj_suspended);
                    }
                }
            }
        }

        $this->count_sent_ods++;
    }

    function send_user($rec, $send_user_details=true) {
        if(!isset($this->sent_users[$rec->userid])) {
            $user_uid_encoded = rawurlencode($rec->uid);
            $this->put_ws("pessoas/{$user_uid_encoded}",  $this->get_user($rec->role, $rec->userid, $rec->uid));
            if($send_user_details && $rec->role == 'student' && !empty($rec->uid) && !empty($rec->currentlogin)) {
                $this->put_ws("pessoas/{$user_uid_encoded}/ultimoLogin", array('ultimoLogin'=>$rec->currentlogin));
            }
            $this->sent_users[$rec->userid] = true;
            $this->count_sent_users[$rec->role]++;
        }
    }

    function send_data($selected_ocs=array(), $selected_ods=array(), $selected_polos=array(), $send_user_details=true) {
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

        $ofertas_cursos = $this->get_ofertas_cursos();

        foreach($ofertas_cursos AS $ocid=>$oc) {
            if(isset($selected_ocs[$ocid])) {
                $this->send_users_by_ofertas_disciplinas($ocid, 0, $send_user_details);
            } else if(isset($selected_ods[$ocid])) {
                foreach($selected_ods[$ocid] AS $odid=>$i) {
                    $this->send_users_by_ofertas_disciplinas(0, $odid, $send_user_details);
                }
            }
        }

        $polo_mapping_type = $this->get_config('polo_mapping');
        if($polo_mapping_type != 'no_polo') {
            foreach($ofertas_cursos AS $ocid=>$oc) {
                if(isset($selected_ocs[$ocid])) {
                    $this->send_users_by_polos($ocid, 0, $send_user_details);
                } else if(isset($selected_polos[$ocid])) {
                    foreach($selected_polos[$ocid] AS $polo_id=>$i) {
                        $this->send_users_by_polos($ocid, $polo_id, $send_user_details);
                    }
                }
            }
        }

        $this->send_resume();

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
        return $this->handle_ws_errors($throw_exception);
    }

    function get_ws($functionname='', $throw_exception=false) {
        $this->init_curl();
        $response = $this->curl->get($this->make_ws_url($functionname));
        $this->handle_ws_errors($throw_exception);
        return json_decode($response);
    }

    function post_ws($functionname, $data = array(), $throw_exception=false) {
        $this->init_curl();
        $options = array('CURLOPT_HTTPHEADER'=>array('Content-Type: application/json'));
        $response = $this->curl->post($this->make_ws_url($functionname), json_encode($data), $options);
        $this->handle_ws_errors($throw_exception);
        return json_decode($response);
    }

    function put_ws($functionname, $data = array(), $throw_exception=false) {
        $this->init_curl();
        $this->curl->put_json($this->make_ws_url($functionname), json_encode($data));
        return $this->handle_ws_errors($throw_exception);
    }

    function handle_ws_errors($throw_exception=false) {
        $info = $this->curl->get_info();
        if($info['http_code'] <= 299) {
            return true;
        } else if($info['http_code'] <= 499) {
            $this->count_errors++;
            if(count($this->errors) < 5) {
                $this->errors[] = "Falha {$info['http_code']} no acesso ao SAAS para: ". $info['url'];
            }
            if($throw_exception) {
                throw new Exception("Falha {$info['http_code']} no acesso ao SAAS para: ". $info['url']);
            }
            return true;
        } else {
            throw new Exception("Falha {$info['http_code']} no acesso ao SAAS para: ". $info['url']);
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

}
