<?php

require_once(dirname(__FILE__) . '/../../config.php');

function saas_export_get_user_custom_fields() {
    global $DB;

    $userfields = array();
    if ($user_info_fields = $DB->get_records('user_info_field')) {
        foreach ($user_info_fields as $field) {
            $userfields[$field->shortname] = $field->name;
        }
    }
    return  $userfields;
}


class saas {

    public static $role_names = array('teacher', 'student', 'tutor', 'tutor_polo');

    function __construct() {
        global $DB;
	
        $this->config = get_config('saas_export');
    }

    function update_offers() {
       if(!$lastupdated = get_config('report_saas_export', 'lastupdated')) {
           $lastupdated = 0;
       }
       $now = time();
       $hourdiff = round(($now - $lastupdated)/3600, 1);
       if ($hourdiff > 1) {
           try {
               $count_course = $this->save_courses($this->get_courses_offers());
               $count_class = $this->save_classes($this->get_classes_offers());
               set_config('lastupdated', $now, 'report_saas_export');
               return array($count_course, $count_class);
           } catch (Exception $e){}
       }
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

    function get_mapped_courses() {
        global $DB;

        return $DB->get_records_menu('saas_ofertas_cursos', array('enable'=>1), null, 'saas_id, categoryid');
    }

    function get_courses_offers_info() {
        global $DB;

        $offers = array();
        $courses_offer =  $DB->get_records('saas_ofertas_cursos', array('enable'=>1));
        foreach ($courses_offer as $c){
            $offers[$c->saas_id] = $c;
        }
        return $offers;
    }

    function get_mapped_classes(){
        global $DB;

        return $DB->get_records_menu('saas_ofertas_disciplinas', array('enable'=>1), null, 'saas_id, courseid');
    }

    function get_classes_offers_info() {
        global $DB;

        $offers = array();
        $classes_offer =  $DB->get_records('saas_ofertas_disciplinas', array('enable'=>1));
        foreach ($classes_offer as $c){
            $offers[$c->saas_id] = $c;
        }
        return $offers;
    }
    
    function save_courses($courses_offers){
        global $DB;

        $offers = $DB->get_records('saas_ofertas_cursos', null, null ,'saas_id, id, enable');
        $count = 0;
        foreach ($courses_offers as $course_offer){
            $record = new stdClass();
            $record->name = $course_offer->nome;
            $record->year = $course_offer->ano;
            $record->period = $course_offer->periodo;
            $record->periodicity = $course_offer->periodicidade;
            $record->enable = 1;
            
            if (isset($offers[$course_offer->uid])){
                $record->id = $offers[$course_offer->uid]->id;
                $DB->update_record('saas_ofertas_cursos', $record);
                unset($offers[$course_offer->uid]);
            } else {
                $record->saas_id = $course_offer->uid;
                $record->categoryid = -1;
                $DB->insert_record('saas_ofertas_cursos', $record);
                $count++;
            }
        }

        foreach($offers AS $saas_id=>$rec) {
            if($rec->enable){
                $DB->set_field('saas_ofertas_cursos', 'enable', 0, array('id'=>$rec->id));
            }
        }

        return $count;
    }
    
    function save_classes($classes_offers){
        global $DB;
        
        $offers = $DB->get_records('saas_ofertas_disciplinas', null, null ,'saas_id, id, saas_course_offer_id, enable');
        $count = 0;
        foreach ($classes_offers as $class_offer){
            $record = new stdClass();
            $record->beginning = $class_offer->inicio;
            $record->ending = $class_offer->fim;
            $record->name = $class_offer->disciplina_nome;
            $record->year = $class_offer->ano;
            $record->enable = 1;
            if (isset($offers[$class_offer->uid])){
                $record->id = $offers[$class_offer->uid]->id;
                $DB->update_record('saas_ofertas_disciplinas', $record);
                unset($offers[$class_offer->uid]);
            } else {
                $record->saas_id = $class_offer->uid;
                $record->saas_course_offer_id = $DB->get_field('saas_ofertas_cursos', 'id', array('saas_id' => $class_offer->oferta_curso_uid));
                $record->courseid = -1;
                $DB->insert_record('saas_ofertas_disciplinas', $record);
                $count++;
            }
        }
        
        foreach ($offers AS $saas_id=>$rec) {
            if($rec->enable){
                $DB->set_field('saas_ofertas_disciplinas', 'enable', 0, array('id'=>$rec->id));
            }
        }

        return $count;
    }

    function save_courses_offers_mapping($formdata){
        global $DB;

        $courses_offers = $formdata->map;
        foreach ($courses_offers as $saas_id => $categoryid){
            $sql = "UPDATE {saas_ofertas_cursos}
                       SET categoryid = ?
                     WHERE saas_id = ?";
            $params = array('categoryid'=>$categoryid, 'saas_id'=>$saas_id);
            $DB->execute($sql, $params);
        }
    }

    function save_classes_offers_mapping($formdata){
        global $DB;

        $courses_offers = $formdata->map;
        foreach ($courses_offers as $saas_course_id => $classes_offers) {
            foreach($classes_offers as $saas_class_offer_id => $courseid) {
                $sql = "UPDATE {saas_ofertas_disciplinas}
                           SET courseid = :courseid
                         WHERE saas_id = :saas_class_id";
                $params = array('courseid'=>$courseid, 'saas_class_id'=>$saas_class_offer_id);
                $DB->execute($sql, $params);
            }
        }
    }

    function save_polos_mapping($formdata){
        global $DB;

        $courses_offers_polos = $formdata->map;
        foreach ($courses_offers_polos as $course_id => $polos) {
            foreach ($polos as $polo => $checked) {
                if (!$DB->record_exists('saas_polos', array('groupname'=>$polo, 'course_offer_id'=>$course_id))){
                    $record = new stdClass();
                    $record->groupname = $polo;
                    $record->course_offer_id = $course_id;
                    $record->is_polo = $checked;
                    $DB->insert_record('saas_polos', $record);
                } else {
                    $sql = "UPDATE {saas_polos} AS sp
                               SET is_polo = $checked
                             WHERE sp.course_offer_id = :course_id
                               AND sp.groupname = :groupname";
                    $params = array('course_id'=>$course_id, 'groupname'=>$polo);
                    $DB->execute($sql, $params);
                }
            }
        }
    }

    function get_courses_offers() {
        return $this->get_ws('get/oferta/curso');
    }

    function get_classes_offers() {
        return $this->get_ws('get/oferta/disciplina');
    }

    function get_courses_from_category($catid){
        global $DB;

        $sql = "SELECT c.id, c.category, c.shortname, c.fullname, c.sortorder
                  FROM {course_categories} cc
                  JOIN {course} c
                    ON (c.category = cc.id)
                 WHERE cc.id = $catid
                    OR cc.path  LIKE '%/$catid/%'";
        $courses = $DB->get_records_sql($sql);

        return $courses;
    }

    function get_users_sql($user_type) {
        global $DB;

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

        $config_role = "{$user_type}_role";
        list($in_sql, $params) = $DB->get_in_or_equal(explode(',', ($this->config->{$config_role})), SQL_PARAMS_NAMED);
        $sql = "SELECT DISTINCT {$fields}
                  FROM {saas_ofertas_cursos} AS oc
                  JOIN {saas_ofertas_disciplinas} AS od
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

        $user_types = array('student', 'tutor', 'teacher');
        foreach ($user_types as $user_type) {

            list($sql, $params) = $this->get_users_sql($user_type);
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

                if(preg_match('/[^0-9]*([0-9]{6,11})[^0-9]*/', $u->cpf, $matches)) {
                    $u->cpf = $matches[1];
                }
                $users_to_send[] = $u;
                $count++;
            }
            if ($count > 0) {
                $this->post_ws('pessoa', $users_to_send);
            }
        }
    }

    function get_users_by_role($roleid, $courseid){
       global $DB;
       
       $context = context_course::instance($courseid);
       $users = get_role_users($roleid, $context, false, "u.id, u.{$this->config->user_id_field}", 'u.id');
       $users_to_send = array();

       foreach ($users as $u){
           $users_to_send[] = $u->{$this->config->user_id_field};
       }
       return $users_to_send;
    }

    function get_users_at_polo($roles, $course_offer_id, $groupname){
        global $DB;

        list($in_sql, $in_params) = $DB->get_in_or_equal(explode(',', ($roles)), SQL_PARAMS_NAMED);
        
        $field = 'u.' . $this->config->user_id_field;
        $context = CONTEXT_COURSE;
        $query_params = array('context'=>$context, 'course_offer_id'=>$course_offer_id, 'groupname'=>$groupname);
        $params = array_merge($query_params, $in_params);
        
        $sql = "SELECT DISTINCT {$field} AS userfield
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_ofertas_disciplinas} od
                    ON (od.saas_course_offer_id = oc.id)
                  JOIN {saas_polos} p
                    ON (p.course_offer_id = oc.id)
                  JOIN {groups} g
                    ON (g.courseid = od.courseid AND g.name = p.groupname)
                  JOIN {groups_members} gm
                    ON (gm.groupid = g.id)
                  JOIN {user} u
                    ON (u.id = gm.userid)
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

        $role_ids = array();
        foreach(saas::$role_names as $r) {
            $role_name = $r . '_role';
            if(isset($this->config->$role_name) && !empty($this->config->$role_name)) {
                $role_ids = array_merge($role_ids, explode(',', $this->config->$role_name));
            }
        }

        if(empty($role_ids)) {
            return 0;
        }  
        
        //pega o total de usuários;

        list($in_sql, $in_params) = $DB->get_in_or_equal($role_ids, SQL_PARAMS_NAMED);
        $in_params['context'] = CONTEXT_COURSE;
        
        $sql = "SELECT count(DISTINCT u.id) AS total
                  FROM {saas_ofertas_cursos} AS oc
                  JOIN {saas_ofertas_disciplinas} AS od
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

                $sql = "SELECT oc.saas_id AS oferta_curso, od.saas_id as oferta_disciplina, 
                               COUNT(DISTINCT ra.userid) as count
                          FROM {saas_ofertas_cursos} AS oc
                          JOIN {saas_ofertas_disciplinas} AS od
                            ON (od.saas_course_offer_id = oc.id AND
                                od.enable = 1)
                          JOIN {course} c
                            ON (c.id = od.courseid)
                          JOIN {context} ctx
                            ON (ctx.contextlevel = :context AND
                                ctx.instanceid = c.id)
                          JOIN {role_assignments} ra
                            ON (ra.contextid = ctx.id)
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

        $sql = "SELECT p.id, oc.saas_id, p.groupname
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_polos} p
                    ON (p.course_offer_id = oc.id)
                 WHERE oc.enable = 1
                   AND p.is_polo = 1";
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
                  FROM {saas_ofertas_disciplinas} od
                 WHERE od.enable = 1
                   AND od.courseid <> -1";
        $mapped_classes = $DB->get_records_sql_menu($sql);
        $roles = array('aluno' => $this->config->student_role,
                       'professor' => $this->config->teacher_role,
                       'tutor' =>$this->config->tutor_role);

        foreach ($mapped_classes as $saas_id => $courseid){
            foreach ($roles as $papel => $roleid){
                $this->post_ws('oferta/disciplina/'.$papel.'/'.$saas_id, $this->get_users_by_role($roleid, $courseid));
            }
        }
    }

    function make_ws_url($functionname) {
        return $this->config->ws_url . '/'. $functionname. '/'. $this->config->api_key;
    }

    function get_ws($functionname) {
        $curl = new curl;
        $resp = $curl->get($this->make_ws_url($functionname));

        if ($curl->errno > 0) {
            throw new Exception($curl->error, $curl->errno);
        }
        return json_decode($resp);
    }

    function post_ws($functionname, $data = array()) {
        $curl = new curl();
        $curl->setHeader('Content-Type: application/json');
        $curl->post($this->make_ws_url($functionname), json_encode($data));

        if ($curl->errno > 0) {
            throw new Exception($curl->error, $curl->errno);
        }
        return true;
    }
}
