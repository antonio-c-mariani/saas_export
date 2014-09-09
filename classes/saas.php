<?php

require_once($CFG->libdir . '/gradelib.php');

class saas {

    public static $role_types             = array('teacher'=>'professores', 'student'=>'estudantes', 'tutor_polo'=>'tutores', 'tutor_inst'=>'tutores');
    public static $role_types_disciplinas = array('teacher', 'tutor_inst', 'student');
    public static $role_types_polos       = array('tutor_polo', 'student');

    public $config;

    public $curl = null;

    function __construct() {
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
               $url = new moodle_url('/report/saas_export/index.php', array('action'=>'settings'));
               print_error('ws_error', 'report_saas_export', $url, $e->getMessage());
           }
       }
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

        $cursos_saas = $this->get_ws('cursos');
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

    function get_polos() {
        global $DB;

        return $DB->get_records('saas_polos', array('enable'=>1), 'nome, cidade, estado');
    }

    function get_ofertas_curso() {
        global $DB;

        return $DB->get_records('saas_ofertas_cursos', array('enable'=>1), 'nome, ano, periodo');
    }

    function get_oferta_disciplina($oferta_disciplina_id = 0) {
        global $DB;

        if(empty($oferta_disciplina_id)) {
            $where = '';
            $params = array();
        } else {
            $where = 'AND od.id = :odid';
            $params = array('odid' => $oferta_disciplina_id);
        }
        $sql = "SELECT od.*, d.nome
                  FROM {saas_ofertas_disciplinas} od
                  JOIN {saas_disciplinas} d ON (d.uid = od.disciplina_uid)
                 WHERE od.enable = 1
                   AND d.enable = 1
                   {$where}";
        return $DB->get_record_sql($sql, $params);
    }

    function get_ofertas_disciplinas($oferta_de_curso_uid='') {
        global $DB;

        if(empty($oferta_de_curso_uid)) {
            $where = '';
            $params = array();
        } else {
            $where = 'AND oferta_curso_uid = :oferta_curso_uid';
            $params = array('oferta_curso_uid' => $oferta_de_curso_uid);
        }
        $sql = "SELECT od.*, d.nome
                  FROM {saas_ofertas_disciplinas} od
                  JOIN {saas_disciplinas} d ON (d.uid = od.disciplina_uid)
                 WHERE od.enable = 1
                   AND d.enable = 1
                   {$where}";
        return $DB->get_records_sql($sql, $params);
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
                $field = "ra.userid, u.{$userid_field} AS uid";
            } else {
                if($fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$userid_field))) {
                    $join_user_info_data = "JOIN {user_info_data} udt ON (udt.fieldid = :fieldid AND udt.userid = u.id AND udt.data != '')";
                    $params['fieldid'] = $fieldid;
                    $field = "ra.userid, udt.data AS uid";
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
                $field = "ra.userid, u.{$userid_field} AS uid";
            } else {
                if($fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$userid_field))) {
                    $join_user_info_data = "JOIN {user_info_data} udt ON (udt.fieldid = :fieldid AND udt.userid = u.id AND udt.data != '')";
                    $params['fieldid'] = $fieldid;
                    $field = "ra.userid, udt.data AS uid";
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
                        ((ra.component = '' AND e.enrol = 'manual') OR (ra.component = CONCAT('enrol_',e.enrol) AND ra.itemid = e.id)))
                  JOIN {saas_config_roles} scr ON (scr.roleid = ra.roleid AND scr.role IN ('student', 'tutor_polo'))
                  JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0)
                  {$join_user_info_data}
                  JOIN {saas_map_catcourses_polos} smcp ON (smcp.type = 'course' AND smcp.instanceid = c.id)
                  JOIN {saas_polos} sp ON (sp.id = smcp.polo_id AND sp.enable = 1)
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
                $field = "ra.userid, u.{$userid_field} AS uid";
            } else {
                if($fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$userid_field))) {
                    $join_user_info_data = "JOIN {user_info_data} udt ON (udt.fieldid = :fieldid AND udt.userid = u.id AND udt.data != '')";
                    $params['fieldid'] = $fieldid;
                    $field = "ra.userid, udt.data AS uid";
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

    function get_sql_users_by_oferta_disciplina($id_oferta_curso=0, $id_oferta_disciplina=0, $only_count=false) {
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
        }

        $condition = '';
        if($id_oferta_disciplina) {
            $condition .= " AND od.id = {$id_oferta_disciplina}";
        }
        if($id_oferta_curso) {
            $condition .= " AND oc.id = {$id_oferta_curso}";
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
                        ((ra.component = '' AND e.enrol = 'manual') OR (ra.component = CONCAT('enrol_',e.enrol) AND ra.itemid = e.id)))
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
    function send_users_by_polo($id_oferta_curso=0, $id_polo=0) {
        global $DB;

        $role_types = $this->get_role_types('polos');
        $ofertas = $this->get_ofertas_curso();
        $polos = $this->get_polos();
        $mapped = array();
        foreach($ofertas AS $ocid=>$of) {
            foreach($polos AS $poloid=>$p) {
                $mapped[$ocid][$poloid] = true;
            }
        }

        $polo_mapping_type = $this->get_config('polo_mapping');
        switch ($polo_mapping_type) {
            case 'group_to_polo':
                list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_groups($id_oferta_curso, $id_polo);
                break;
            case 'category_to_polo':
                list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_categories($id_oferta_curso, $id_polo);
                break;
            case 'course_to_polo':
                list($sql, $params) = $this->get_sql_users_by_oferta_curso_polo_courses($id_oferta_curso, $id_polo);
                break;
        }
        $rs = $DB->get_recordset_sql($sql, $params);
        $ocid = 0;
        $poloid = 0;
        $users_by_roles = array();
        foreach($rs AS $rec) {
            $this->send_user($rec);

            if($rec->oc_id != $ocid || $rec->p_id != $poloid) {
                if($ocid !== 0) {
                    $encoded_oferta_uid = urlencode($ofertas[$ocid]->uid);
                    $encoded_polo_uid = urlencode($polos[$poloid]->uid);
                    foreach($users_by_roles AS $r=>$users) {
//todo                        $this->put_ws("ofertas/cursos/{$encoded_oferta_uid}/polos/{$encoded_polo_uid}/".self::$role_types[$r], $users);
                    }
                    unset($mapped[$ocid][$poloid]);
                }
                foreach($role_types AS $r) {
                    $users_by_roles[$r] = array();
                }
                $ocid = $rec->oc_id;
                $poloid = $rec->p_id;
            }
            $users_by_roles[$rec->role][] = $rec->uid;
        }

        //send the last one
        if($ocid !== 0) {
            $encoded_oferta_uid = urlencode($ofertas[$ocid]->uid);
            $encoded_polo_uid = urlencode($polos[$poloid]->uid);
            foreach($users_by_roles AS $r=>$users) {
//todo                $this->put_ws("ofertas/cursos/{$encoded_oferta_uid}/polos/{$encoded_polo_uid}/".self::$role_types[$r], $users);
            }
            unset($mapped[$ocid][$poloid]);
        }
    }

    //envia os usuários com os seus devidos papéis em cada oferta de disciplina.
    function send_users_by_ofertas_disciplinas($id_oferta_curso=0, $id_oferta_disciplina=0) {
        global $DB;

        $ofertas = $this->get_ofertas_disciplinas();
        $role_types = $this->get_role_types('disciplinas');

        list($sql, $params) = $this->get_sql_users_by_oferta_disciplina($id_oferta_curso, $id_oferta_disciplina);
        $rs = $DB->get_recordset_sql($sql, $params);
        $odid = 0;
        $users_by_roles = array();
        foreach($rs AS $rec) {
            $this->send_user($rec);

            if($rec->od_id != $odid) {
                if($odid !== 0) {
                    $this->send_users_by_oferta_disciplina($ofertas[$odid], $users_by_roles, $lastaccess);
                }
                foreach($role_types AS $r) {
                    $users_by_roles[$r] = array();
                }
                $lastaccess = array();
                $odid = $rec->od_id;
            }

            $users_by_roles[$rec->role][$rec->userid] = $rec->uid;
            if(!empty($rec->lastaccess)) {
                $lastaccess[$rec->userid] = $rec->lastaccess;
            }
        }

        //send the last one
        if($odid !== 0) {
            $this->send_users_by_oferta_disciplina($ofertas[$odid], $users_by_roles, $lastaccess);
        }
    }

    function send_users_by_oferta_disciplina($oferta_disciplina, $users_by_roles, $lastaccess) {
        $oferta_uid_encoded = urlencode($oferta_disciplina->uid);
        foreach($users_by_roles AS $r=>$users) {
//todo            $this->put_ws("ofertas/disciplinas/{$oferta_uid_encoded}/". self::$role_types[$r], array_values($users));
            if($r == 'student') {
                $grades = $this->get_grades($oferta_disciplina->id);
                $obj_nota = new stdClass();
                $obj_lastaccess = new stdClass();
                foreach($users AS $userid=>$uid) {
                    if(!empty($uid)) {
                        $user_uid_encoded = urlencode($uid);
                        if(isset($grades[$userid])) {
                            $obj_nota->nota = $grades[$userid];
//todo                            $this->post_ws("ofertas/disciplinas/{$oferta_uid_encoded}/estudantes/{$user_uid_encoded}/nota", $obj_nota);
                        }

                        if(isset($lastaccess[$userid])) {
                            $obj_lastaccess->ultimoAcesso = $lastaccess[$userid];
//todo                            $this->post_ws("ofertas/disciplinas/{$oferta_uid_encoded}/estudantes/{$user_uid_encoded}/ultimoAcesso", $obj_lastaccess);
                        }
                    }
                }
            }
        }
    }

    function send_user($rec) {
        if(!isset($this->sent_users[$rec->userid])) {
            $this->sent_users[$rec->userid] = true;
            $this->post_ws('pessoas',  $this->get_user($rec->role, $rec->userid, $rec->uid));
            $this->count_sent_users[$rec->role]++;
            if($rec->role == 'student' && !empty($rec->uid) && !empty($rec->currentlogin)) {
                $obj = new stdClass();
                $obj->ultimoAcesso = $rec->currentlogin;
                $user_uid_encoded = urlencode($rec->uid);
//todo                $this->post_ws("pessoas/{$user_uid_encoded}/ultimoAcesso", $obj);
            }
        }
    }

    function send_data($selected_ocs=array(), $selected_ods=array(), $selected_polos=array()) {
        try {
            $this->sent_users = array();
            $this->count_sent_users = array();
            foreach(self::$role_types AS $r=>$rname) {
                $this->count_sent_users[$r] = 0;
            }

            $ofertas_cursos = $this->get_ofertas_curso();

            foreach($ofertas_cursos AS $oc_id=>$oc) {
                if(isset($selected_ocs[$oc_id])) {
                    $this->send_users_by_ofertas_disciplinas($oc_id);
                } else if(isset($selected_ods[$oc_id])) {
                    foreach($selected_ods[$oc_id] AS $od_id=>$i) {
                        $this->send_users_by_ofertas_disciplinas(0, $od_id);
                    }
                }
            }

            $polo_mapping_type = $this->get_config('polo_mapping');
            if($polo_mapping_type != 'no_polo') {
                foreach($ofertas_cursos AS $oc_id=>$oc) {
                    if(isset($selected_ocs[$oc_id])) {
                        $this->send_users_by_polo($oc_id);
                    } else if(isset($selected_polos[$oc_id])) {
                        foreach($selected_polos[$oc_id] AS $polo_id=>$i) {
                            $this->send_users_by_polo($oc_id, $polo_id);
                        }
                    }
                }
            }
        } catch (dml_write_exception $e){
            print_error('bd_error', 'report_saas_export', '', $e->debuginfo);
        } catch (Exception $e){
            $url = new moodle_url('/report/saas_export/index.php', array('action'=>'settings'));
            print_error('ws_error', 'report_saas_export', $url, $e->getMessage());
        }
    }


    //Métodos para acesso ao webservice.
    function make_ws_url($functionname) {
        return $this->config->ws_url . '/instituicoes/' . $this->config->api_key  . '/' . $functionname;
    }

    function init_curl() {
        $this->curl = new \report_saas_export\curl();
    }

    function head_ws($functionname) {
        $this->init_curl();
        $this->curl->head($this->make_ws_url($functionname));
        return $this->handle_ws_errors();
    }

    function get_ws($functionname) {
        $this->init_curl();
        $response = $this->curl->get($this->make_ws_url($functionname));
        $this->handle_ws_errors();
        return json_decode($response);
    }

    function post_ws($functionname, $data = array()) {
        $this->init_curl();
        $options = array('CURLOPT_HTTPHEADER'=>array('Content-Type: application/json'));
        $response = $this->curl->post($this->make_ws_url($functionname), json_encode($data), $options);
        $this->handle_ws_errors();
        return json_decode($response);
    }

    function put_ws($functionname, $data = array()) {
        $this->init_curl();
        $this->curl->put_json($this->make_ws_url($functionname), json_encode($data));
        return $this->handle_ws_errors();
    }

    function handle_ws_errors() {
        $info = $this->curl->get_info();
        if($info['http_code'] <= 299) {
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
    }

    function get_student_roles_menu() {
        global $DB, $CFG;

        $context = context_system::instance();
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

        $context = context_system::instance();
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
        $result = date("d-m-Y", substr($saas_timestamp_inicio, 0, 10));
        if($saas_timestamp_fim) {
            $result .= $separador;
            $result .= date("d-m-Y", substr($saas_timestamp_fim, 0, 10));
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
