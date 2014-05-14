<?php

require_once($CFG->libdir.'/environmentlib.php');

function saas_export_get_user_custom_fields() {

    $current_version = normalize_version(get_config('', 'release'));
    if (version_compare($current_version, '2.0', '>=')) {
        global $DB;
        $user_info_fields = $DB->get_records('user_info_field');
    } else {
        $user_info_fields = get_records('user_info_field');
    }

    $userfields = array();

    if ($user_info_fields) {
        foreach ($user_info_fields as $field) {
            $userfields[$field->shortname] = $field->name;
        }
    }
    return  $userfields;
}

class saas {

    public static $role_names = array('teacher', 'student', 'tutor', 'tutor_polo');

    function __construct() {

        $this->current_version = normalize_version(get_config('', 'release'));

        if ($this->config = get_config('saas_export'))  {
            $this->config->cpf_tutor_polo_field = $this->config->cpf_tutor_field;
            $this->config->name_tutor_polo_field = $this->config->name_tutor_field;
        } else {
            $this->config = new stdclass();
            $this->config->ws_url = '';
            $this->config->api_key = '';
            $this->config->course_name_default = '';
            $this->config->user_id_field = '';
            $this->config->cpf_student_field = '';
            $this->config->cpf_teacher_field = '';
            $this->config->cpf_tutor_field = '';
            $this->config->cpf_tutor_polo_field = '';
            $this->config->name_student_field = '';
            $this->config->name_teacher_field = '';
            $this->config->name_tutor_field = '';
            $this->config->name_tutor_polo_field = '';
            $this->config->student_role = '';
            $this->config->teacher_role = '';
            $this->config->tutor_role = '';
            $this->config->tutor_polo_role = '';

        }

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

    function serialize_role_names($str_roleids){
        if(empty($str_roleids)) {
            return '';
        }
        if (version_compare($this->current_version, '2.0', '>=')) {
            $context = context_system::instance();
            $roles = get_all_roles($context);
        } else {
            $context = get_context_instance(CONTEXT_SYSTEM);
            $roles = get_records_menu('role', null, 'name', 'id, name');
        }
        $role_names = role_fix_names($roles, $context);
        $roleids = explode(',', $str_roleids);
        $names = array();
        if (version_compare($this->current_version, '2.0', '>=')) {
            foreach ($roleids as $rid){
                $names[] = $role_names[$rid]->localname;
            }
        } else {
            foreach ($roleids as $rid){
                $names[] = $role_names[$rid];
            }
        }
        return implode(', ', $names);
    }

    function get_mapped_polos_by_name() {
        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;
            $polos = $DB->get_records('saas_polos', array('enable'=>1), null , 'id, is_polo, groupname');
        } else {
            $polos = get_records('saas_polos', 'enable', 1, null , 'id, is_polo, groupname');
        }
        $mapped_polos = array();
        if ($polos) {
            foreach ($polos as $p){
                $mapped_polos[$p->groupname] = $p->is_polo;
            }
        }
        return $mapped_polos;
    }

    function get_mapped_courses() {
        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;
            return $DB->get_records_menu('saas_ofertas_cursos', array('enable'=>1), null, 'saas_id, categoryid');
        } else {
            if ($oc = get_records_menu('saas_ofertas_cursos', 'enable', 1, null, 'saas_id, categoryid')) {
                return $oc;
            } else {
                return array();
            }
        }
    }

    function get_courses_offers_info() {
        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;
            $courses_offer =  $DB->get_records('saas_ofertas_cursos', array('enable'=>1));
        } else {
            $courses_offer =  get_records('saas_ofertas_cursos', 'enable', 1);
        }

        $offers = array();
        if ($courses_offer) {
            foreach ($courses_offer as $c){
                $offers[$c->saas_id] = $c;
            }
        }
        return $offers;
    }

    function get_mapped_classes(){
        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;
            return $DB->get_records_menu('saas_ofertas_disciplinas', array('enable'=>1), null, 'saas_id, courseid');
        } else {
            return get_records_menu('saas_ofertas_disciplinas', 'enable', 1, null, 'saas_id, courseid');
        }
    }

    function get_classes_offers_info() {
        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;
            $classes_offer =  $DB->get_records('saas_ofertas_disciplinas', array('enable'=>1));
        } else {
            $classes_offer =  get_records('saas_ofertas_disciplinas', 'enable', 1);
        }

        $offers = array();
        foreach ($classes_offer as $c){
            $offers[$c->saas_id] = $c;
        }
        return $offers;
    }

    function save_courses($courses_offers) {
        $count = 0;
        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;
            $offers = $DB->get_records('saas_ofertas_cursos', null, null ,'saas_id, id, enable');
            if ($courses_offers) {
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
            }

            if ($offers) {
                foreach($offers AS $saas_id=>$rec) {
                    if($rec->enable){
                        $DB->set_field('saas_ofertas_cursos', 'enable', 0, array('id'=>$rec->id));
                    }
                }
            }
        } else {
            $offers = get_records('saas_ofertas_cursos', null, null, null ,'saas_id, id, enable');
            if ($courses_offers) {
                foreach ($courses_offers as $course_offer){
                    $record = new stdClass();
                    $record->name = $course_offer->nome;
                    $record->year = $course_offer->ano;
                    $record->period = $course_offer->periodo;
                    $record->periodicity = $course_offer->periodicidade;
                    $record->enable = 1;

                    if (isset($offers[$course_offer->uid])){
                        $record->id = $offers[$course_offer->uid]->id;
                        update_record('saas_ofertas_cursos', $record);
                        unset($offers[$course_offer->uid]);
                    } else {
                        $record->saas_id = $course_offer->uid;
                        $record->categoryid = -1;
                        insert_record('saas_ofertas_cursos', $record);
                        $count++;
                    }
                }
            }

            if ($offers) {
                foreach($offers AS $saas_id=>$rec) {
                    if($rec->enable){
                        set_field('saas_ofertas_cursos', 'enable', 0, 'id', $rec->id);
                    }
                }
            }
        }

        return $count;
    }

    function save_classes($classes_offers){
        $count = 0;
        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;
            $offers = $DB->get_records('saas_ofertas_disciplinas', null, null ,'saas_id, id, saas_course_offer_id, enable');
            if ($classes_offers) {
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
            }

            if ($offers) {
                foreach ($offers AS $saas_id=>$rec) {
                    if($rec->enable){
                        $DB->set_field('saas_ofertas_disciplinas', 'enable', 0, array('id'=>$rec->id));
                    }
                }
            }
        } else {
            $offers = get_records('saas_ofertas_disciplinas', null, null, null ,'saas_id, id, saas_course_offer_id, enable');
            if ($classes_offers) {
                foreach ($classes_offers as $class_offer){
                    $record = new stdClass();
                    $record->beginning = $class_offer->inicio;
                    $record->ending = $class_offer->fim;
                    $record->name = $class_offer->disciplina_nome;
                    $record->year = $class_offer->ano;
                    $record->enable = 1;
                    if (isset($offers[$class_offer->uid])){
                        $record->id = $offers[$class_offer->uid]->id;
                        update_record('saas_ofertas_disciplinas', $record);
                        unset($offers[$class_offer->uid]);
                    } else {
                        $record->saas_id = $class_offer->uid;
                        $record->saas_course_offer_id = get_field('saas_ofertas_cursos', 'id', 'saas_id', $class_offer->oferta_curso_uid);
                        $record->courseid = -1;
                        insert_record('saas_ofertas_disciplinas', $record);
                        $count++;
                    }
                }
            }

            if ($offers) {
                foreach ($offers AS $saas_id=>$rec) {
                    if($rec->enable){
                        set_field('saas_ofertas_disciplinas', 'enable', 0, 'id', $rec->id);
                    }
                }
            }
        }

        return $count;
    }

    function save_courses_offers_mapping($formdata){

        $courses_offers = $formdata->map;

        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;
            foreach ($courses_offers as $saas_id => $categoryid){
                $sql = "UPDATE {saas_ofertas_cursos}
                           SET categoryid = ?
                         WHERE saas_id = ?";
                $params = array('categoryid'=>$categoryid, 'saas_id'=>$saas_id);
                $DB->execute($sql, $params);
            }
        } else {
            global $CFG;
            foreach ($courses_offers as $saas_id => $categoryid){
                $sql = "UPDATE {$CFG->prefix}saas_ofertas_cursos
                           SET categoryid = ".filter_var($categoryid, FILTER_SANITIZE_NUMBER_INT).
                       ' WHERE saas_id = '.filter_var($saas_id, FILTER_SANITIZE_STRING);
                execute_sql($sql);
            }
        }
    }

    function save_classes_offers_mapping($formdata){

        $courses_offers = $formdata->map;
        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;
            foreach ($courses_offers as $saas_course_id => $classes_offers) {
                foreach($classes_offers as $saas_class_offer_id => $courseid) {
                    $sql = "UPDATE {saas_ofertas_disciplinas}
                               SET courseid = :courseid
                             WHERE saas_id = :saas_class_id";
                    $params = array('courseid'=>$courseid, 'saas_class_id'=>$saas_class_offer_id);
                    $DB->execute($sql, $params);
                }
            }
        } else {
            global $CFG;
            foreach ($courses_offers as $saas_course_id => $classes_offers) {
                foreach($classes_offers as $saas_class_offer_id => $courseid) {
                    $sql = "UPDATE {$CFG->prefix}saas_ofertas_disciplinas
                               SET courseid = ".filter_var($courseid, FILTER_SANITIZE_NUMBER_INT).
                           ' WHERE saas_id = '.filter_var($saas_class_offer_id, FILTER_SANITIZE_NUMBER_INT);
                    execute_sql($sql);
                }
            }
        }
    }

    function save_polos_mapping($formdata){

        $courses_offers_polos = $formdata->map;
        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;
            foreach ($courses_offers_polos as $course_id => $polos) {
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
        } else {
            foreach ($courses_offers_polos as $course_id => $polos) {
                $existing = get_records_menu('saas_polos', 'course_offer_id', $course_id, null, 'id, 1');
                foreach ($polos as $polo => $checked) {
                    $record = new stdClass();
                    $record->enable = 1;
                    $record->is_polo = $checked;
                    if ($id = get_field('saas_polos', 'id', 'groupname', $polo, 'course_offer_id', $course_id)) {
                        $record->id = $id;
                        update_record('saas_polos', $record);
                        unset($existing[$id]);
                    } else {
                        $record->groupname = $polo;
                        $record->course_offer_id = $course_id;
                        $id = insert_record('saas_polos', $record);
                    }
                }
                foreach($existing AS $id=>$v) {
                    set_field('saas_polos', 'enable', 0, 'id', $id);
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

        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;
            $sql = "SELECT c.id, c.category, c.shortname, c.fullname, c.sortorder
                      FROM {course_categories} cc
                      JOIN {course} c
                        ON (c.category = cc.id)
                     WHERE cc.id = {$catid}
                        OR cc.path  LIKE '%/{$catid}/%'";
            return $DB->get_records_sql($sql);
        } else {
            global $CFG;
            $sql = "SELECT c.id, c.category, c.shortname, c.fullname, c.sortorder
                      FROM {$CFG->prefix}course_categories cc
                      JOIN {$CFG->prefix}course c
                        ON (c.category = cc.id)
                     WHERE cc.id = {$catid}
                        OR cc.path  LIKE '%/{$catid}/%'";
            return get_records_sql($sql);
        }
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

    function get_users_sql19($user_type) {
        global $CFG;

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
                $join_custom_fields = "LEFT JOIN {$CFG->prefix}user_info_field uf ON (uf.shortname = '{$config_cpf}')
                                       LEFT JOIN {$CFG->prefix}user_info_data ud ON (ud.fieldid = uf.id AND ud.userid = u.id)";
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

        $in_sql = get_in_or_equal(explode(',', $str_roleids));
        $sql = "SELECT DISTINCT {$fields}
                  FROM {$CFG->prefix}saas_ofertas_cursos AS oc
                  JOIN {$CFG->prefix}saas_ofertas_disciplinas AS od
                    ON (od.saas_course_offer_id = oc.id AND
                        od.enable = 1)
                  JOIN {$CFG->prefix}course c
                    ON (c.id = od.courseid)
                  JOIN {$CFG->prefix}context ctx
                    ON (ctx.contextlevel = ".CONTEXT_COURSE." AND
                        ctx.instanceid = c.id)
                  JOIN {$CFG->prefix}role_assignments ra
                    ON (ra.contextid = ctx.id AND
                        ra.roleid {$in_sql})
                  JOIN {$CFG->prefix}user u
                    ON (u.id = ra.userid)
                  {$join_custom_fields}
                 WHERE oc.enable = 1";
        return $sql;
    }

    //envia todos os usuários das ofertas de disciplina já mapeadas.
    function send_users() {

        if (version_compare($this->current_version, '2.0', '>=')) {
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
        } else {
            foreach (saas::$role_names as $user_type) {
                $sql = $this->get_users_sql19($user_type);
                if (!empty($sql)){
                    $users = get_recordset_sql($sql);
                    $users_to_send = array();
                    $count = 0;


                    while ($u = rs_fetch_next_record($users)) {
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
    }

    function get_users_by_role($str_roleids, $courseid){

        if(empty($str_roleids)) {
            return array();
        }

        if (version_compare($this->current_version, '2.0', '>=')) {
            $context = context_course::instance($courseid);
        } else {
            $context = get_context_instance(CONTEXT_COURSE, $courseid);
        }
        $users = get_role_users(explode(',', $str_roleids), $context, false, "u.id, u.{$this->config->user_id_field}", 'u.id');
        $users_to_send = array();

        foreach ($users as $u){
            $users_to_send[] = $u->{$this->config->user_id_field};
        }
        return $users_to_send;
    }

    function get_users_at_polo($str_roleids, $course_offer_id, $groupname){

        if(empty($str_roleids)) {
            return array();
        }

        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;

            list($in_sql, $in_params) = $DB->get_in_or_equal(explode(',', $str_roleids), SQL_PARAMS_NAMED);

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
                       AND p.enable = 1
                  ORDER BY u.username";

            $user = $DB->get_records_sql($sql,$params);
        } else {
            global $CFG;

            $in_sql = get_in_or_equal(explode(',', $str_roleids));

            $field = 'u.' . $this->config->user_id_field;

            $sql = "SELECT DISTINCT {$field} AS userfield
                      FROM {$CFG->prefix}saas_ofertas_cursos oc
                      JOIN {$CFG->prefix}saas_ofertas_disciplinas od
                        ON (od.saas_course_offer_id = oc.id)
                      JOIN {$CFG->prefix}saas_polos p
                        ON (p.course_offer_id = oc.id)
                      JOIN {$CFG->prefix}groups g
                        ON (g.courseid = od.courseid AND g.name = p.groupname)
                      JOIN {$CFG->prefix}groups_members gm
                        ON (gm.groupid = g.id)
                      JOIN {$CFG->prefix}user u
                        ON (u.id = gm.userid)
                      JOIN {$CFG->prefix}context ctx
                        ON (ctx.contextlevel = '".CONTEXT_COURSE."' AND
                            ctx.instanceid = od.courseid)
                      JOIN {$CFG->prefix}role r
                        ON (r.id {$in_sql})
                      JOIN {$CFG->prefix}role_assignments ra
                        ON (ra.contextid = ctx.id AND
                            ra.userid = u.id AND
                            ra.roleid = r.id)
                     WHERE oc.enable = 1
                       AND oc.saas_id = '{$course_offer_id}'
                       AND p.groupname = '{$groupname}'
                       AND p.is_polo = 1
                       AND p.enable = 1
                  ORDER BY u.username";

            $user = get_records_sql($sql);
        }
        $users_to_send = array();
        foreach ($user as $u){
            $users_to_send[] = $u->userfield;
        }

        return $users_to_send;
    }

    function get_total_users_to_send(){

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
        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;

            list($in_sql, $in_params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
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
        } else {
            global $CFG;

            $in_sql = get_in_or_equal($roleids);

            $sql = "SELECT count(DISTINCT u.id) AS total
                      FROM {$CFG->prefix}saas_ofertas_cursos AS oc
                      JOIN {$CFG->prefix}saas_ofertas_disciplinas AS od
                        ON (od.saas_course_offer_id = oc.id AND
                            od.enable = 1)
                      JOIN {$CFG->prefix}course c
                        ON (c.id = od.courseid)
                      JOIN {$CFG->prefix}context ctx
                        ON (ctx.contextlevel = ".CONTEXT_COURSE." AND
                            ctx.instanceid = c.id)
                      JOIN {$CFG->prefix}role_assignments ra
                        ON (ra.contextid = ctx.id AND
                            ra.roleid {$in_sql})
                      JOIN {$CFG->prefix}user u
                        ON (u.id = ra.userid)
                     WHERE oc.enable = 1";
            return count_records_sql($sql);
        }
    }

    function get_count_users(){

        $counts = array();

        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;
            foreach(saas::$role_names as $r) {
                $role_name = $r . '_role';
                if(isset($this->config->$role_name) && !empty($this->config->$role_name)) {
                    $roleids = explode(',', $this->config->$role_name);
                    list($in_sql, $in_params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
                    $in_params['context'] = CONTEXT_COURSE;

                    if($r == 'tutor_polo') {
                       $join = "JOIN {saas_polos} p
                                  ON (p.course_offer_id = oc.id AND
                                      p.enable = 1 AND
                                      p.is_polo = 1)
                                JOIN {groups} g
                                  ON (g.courseid = c.id AND
                                      g.name = p.groupname)
                                JOIN {groups_members} gm
                                  ON (gm.groupid = g.id AND
                                      gm.userid = ra.userid)";
                    } else {
                        $join = '';
                    }

                    $sql = "SELECT oc.saas_id AS oferta_curso, od.saas_id as oferta_disciplina,
                                   COUNT(DISTINCT ra.userid) as count
                              FROM {saas_ofertas_cursos} oc
                              JOIN {saas_ofertas_disciplinas} od
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
        } else {
            global $CFG;
            foreach(saas::$role_names as $r) {
                $role_name = $r . '_role';
                if(isset($this->config->$role_name) && !empty($this->config->$role_name)) {
                    $roleids = explode(',', $this->config->$role_name);
                    $in_sql = get_in_or_equal($roleids);

                    if($r == 'tutor_polo') {
                       $join = "JOIN {$CFG->prefix}saas_polos p
                                  ON (p.course_offer_id = oc.id AND
                                      p.enable = 1 AND
                                      p.is_polo = 1)
                                JOIN {$CFG->prefix}groups g
                                  ON (g.courseid = c.id AND
                                      g.name = p.groupname)
                                JOIN {$CFG->prefix}groups_members gm
                                  ON (gm.groupid = g.id AND
                                      gm.userid = ra.userid)";
                    } else {
                        $join = '';
                    }

                    $sql = "SELECT oc.saas_id AS oferta_curso, od.saas_id as oferta_disciplina,
                                   COUNT(DISTINCT ra.userid) as count
                              FROM {$CFG->prefix}saas_ofertas_cursos oc
                              JOIN {$CFG->prefix}saas_ofertas_disciplinas od
                                ON (od.saas_course_offer_id = oc.id AND
                                    od.enable = 1)
                              JOIN {$CFG->prefix}course c
                                ON (c.id = od.courseid)
                              JOIN {$CFG->prefix}context ctx
                                ON (ctx.contextlevel = ".CONTEXT_COURSE." AND
                                    ctx.instanceid = c.id)
                              JOIN {$CFG->prefix}role_assignments ra
                                ON (ra.contextid = ctx.id)
                              {$join}
                             WHERE oc.enable = 1
                               AND ra.roleid {$in_sql}
                             GROUP BY oc.saas_id, od.saas_id";
                    $rs = get_recordset_sql($sql);
                    while ($rec = rs_fetch_next_record($rs)) {
                        $counts[$rec->oferta_curso][$rec->oferta_disciplina][$r] = $rec->count;
                    }
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

        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;
            $sql = "SELECT p.id, oc.saas_id, p.groupname
                      FROM {saas_ofertas_cursos} oc
                      JOIN {saas_polos} p
                        ON (p.course_offer_id = oc.id)
                     WHERE oc.enable = 1
                       AND p.is_polo = 1
                       AND p.enable = 1";
            $offers_polo = $DB->get_records_sql($sql);
        } else {
            global $CFG;
            $sql = "SELECT p.id, oc.saas_id, p.groupname
                      FROM {$CFG->prefix}saas_ofertas_cursos oc
                      JOIN {$CFG->prefix}saas_polos p
                        ON (p.course_offer_id = oc.id)
                     WHERE oc.enable = 1
                       AND p.is_polo = 1
                       AND p.enable = 1";
            $offers_polo = get_records_sql($sql);
        }
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

        if (version_compare($this->current_version, '2.0', '>=')) {
            global $DB;
            $sql = "SELECT saas_id, courseid
                      FROM {saas_ofertas_disciplinas} od
                     WHERE od.enable = 1
                       AND od.courseid <> -1";
            $mapped_classes = $DB->get_records_sql_menu($sql);
        } else {
            global $CFG;
            $sql = "SELECT saas_id, courseid
                      FROM {$CFG->prefix}saas_ofertas_disciplinas od
                     WHERE od.enable = 1
                       AND od.courseid <> -1";
            $mapped_classes = get_records_sql_menu($sql);
        }
        $roles = array('aluno' => $this->config->student_role,
                       'professor' => $this->config->teacher_role,
                       'tutor' =>$this->config->tutor_role);

        foreach ($mapped_classes as $saas_id => $courseid){
            foreach ($roles as $papel => $str_roleids){
                $this->post_ws('oferta/disciplina/'.$papel.'/' . $saas_id, $this->get_users_by_role($str_roleids, $courseid));
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

$current_version = normalize_version(get_config('', 'release'));
if (version_compare($current_version, '2.0', '<')) {

    /**
     * Simple html output class
     *
     * @copyright 2009 Tim Hunt, 2010 Petr Skoda
     * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since Moodle 2.0
     * @package core
     * @category output
     */
    class html_writer {

        /**
         * Outputs a tag with attributes and contents
         *
         * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
         * @param string $contents What goes between the opening and closing tags
         * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
         * @return string HTML fragment
         */
        public static function tag($tagname, $contents, array $attributes = null) {
            return self::start_tag($tagname, $attributes) . $contents . self::end_tag($tagname);
        }

        /**
         * Outputs an opening tag with attributes
         *
         * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
         * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
         * @return string HTML fragment
         */
        public static function start_tag($tagname, array $attributes = null) {
            return '<' . $tagname . self::attributes($attributes) . '>';
        }

        /**
         * Outputs a closing tag
         *
         * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
         * @return string HTML fragment
         */
        public static function end_tag($tagname) {
            return '</' . $tagname . '>';
        }

        /**
         * Outputs an empty tag with attributes
         *
         * @param string $tagname The name of tag ('input', 'img', 'br' etc.)
         * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
         * @return string HTML fragment
         */
        public static function empty_tag($tagname, array $attributes = null) {
            return '<' . $tagname . self::attributes($attributes) . ' />';
        }

        /**
         * Outputs a tag, but only if the contents are not empty
         *
         * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
         * @param string $contents What goes between the opening and closing tags
         * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
         * @return string HTML fragment
         */
        public static function nonempty_tag($tagname, $contents, array $attributes = null) {
            if ($contents === '' || is_null($contents)) {
                return '';
            }
            return self::tag($tagname, $contents, $attributes);
        }

        /**
         * Outputs a HTML attribute and value
         *
         * @param string $name The name of the attribute ('src', 'href', 'class' etc.)
         * @param string $value The value of the attribute. The value will be escaped with {@link s()}
         * @return string HTML fragment
         */
        public static function attribute($name, $value) {
            if ($value instanceof moodle_url) {
                return ' ' . $name . '="' . $value->out() . '"';
            }

            // special case, we do not want these in output
            if ($value === null) {
                return '';
            }

            // no sloppy trimming here!
            return ' ' . $name . '="' . s($value) . '"';
        }

        /**
         * Outputs a list of HTML attributes and values
         *
         * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
         *       The values will be escaped with {@link s()}
         * @return string HTML fragment
         */
        public static function attributes(array $attributes = null) {
            $attributes = (array)$attributes;
            $output = '';
            foreach ($attributes as $name => $value) {
                $output .= self::attribute($name, $value);
            }
            return $output;
        }

        /**
         * Generates a simple image tag with attributes.
         *
         * @param string $src The source of image
         * @param string $alt The alternate text for image
         * @param array $attributes The tag attributes (array('height' => $max_height, 'class' => 'class1') etc.)
         * @return string HTML fragment
         */
        public static function img($src, $alt, array $attributes = null) {
            $attributes = (array)$attributes;
            $attributes['src'] = $src;
            $attributes['alt'] = $alt;

            return self::empty_tag('img', $attributes);
        }

        /**
         * Generates random html element id.
         *
         * @staticvar int $counter
         * @staticvar type $uniq
         * @param string $base A string fragment that will be included in the random ID.
         * @return string A unique ID
         */
        public static function random_id($base='random') {
            static $counter = 0;
            static $uniq;

            if (!isset($uniq)) {
                $uniq = uniqid();
            }

            $counter++;
            return $base.$uniq.$counter;
        }

        /**
         * Generates a simple html link
         *
         * @param string|moodle_url $url The URL
         * @param string $text The text
         * @param array $attributes HTML attributes
         * @return string HTML fragment
         */
        public static function link($url, $text, array $attributes = null) {
            $attributes = (array)$attributes;
            $attributes['href']  = $url;
            return self::tag('a', $text, $attributes);
        }

        /**
         * Generates a simple checkbox with optional label
         *
         * @param string $name The name of the checkbox
         * @param string $value The value of the checkbox
         * @param bool $checked Whether the checkbox is checked
         * @param string $label The label for the checkbox
         * @param array $attributes Any attributes to apply to the checkbox
         * @return string html fragment
         */
        public static function checkbox($name, $value, $checked = true, $label = '', array $attributes = null) {
            $attributes = (array)$attributes;
            $output = '';

            if ($label !== '' and !is_null($label)) {
                if (empty($attributes['id'])) {
                    $attributes['id'] = self::random_id('checkbox_');
                }
            }
            $attributes['type']    = 'checkbox';
            $attributes['value']   = $value;
            $attributes['name']    = $name;
            $attributes['checked'] = $checked ? 'checked' : null;

            $output .= self::empty_tag('input', $attributes);

            if ($label !== '' and !is_null($label)) {
                $output .= self::tag('label', $label, array('for'=>$attributes['id']));
            }

            return $output;
        }

        /**
         * Generates a simple select yes/no form field
         *
         * @param string $name name of select element
         * @param bool $selected
         * @param array $attributes - html select element attributes
         * @return string HTML fragment
         */
        public static function select_yes_no($name, $selected=true, array $attributes = null) {
            $options = array('1'=>get_string('yes'), '0'=>get_string('no'));
            return self::select($options, $name, $selected, null, $attributes);
        }

        /**
         * Generates a simple select form field
         *
         * @param array $options associative array value=>label ex.:
         *                array(1=>'One, 2=>Two)
         *              it is also possible to specify optgroup as complex label array ex.:
         *                array(array('Odd'=>array(1=>'One', 3=>'Three)), array('Even'=>array(2=>'Two')))
         *                array(1=>'One', '--1uniquekey'=>array('More'=>array(2=>'Two', 3=>'Three')))
         * @param string $name name of select element
         * @param string|array $selected value or array of values depending on multiple attribute
         * @param array|bool $nothing add nothing selected option, or false of not added
         * @param array $attributes html select element attributes
         * @return string HTML fragment
         */
        public static function select(array $options, $name, $selected = '', $nothing = array('' => 'choosedots'), array $attributes = null) {
            $attributes = (array)$attributes;
            if (is_array($nothing)) {
                foreach ($nothing as $k=>$v) {
                    if ($v === 'choose' or $v === 'choosedots') {
                        $nothing[$k] = get_string('choosedots');
                    }
                }
                $options = $nothing + $options; // keep keys, do not override

            } else if (is_string($nothing) and $nothing !== '') {
                // BC
                $options = array(''=>$nothing) + $options;
            }

            // we may accept more values if multiple attribute specified
            $selected = (array)$selected;
            foreach ($selected as $k=>$v) {
                $selected[$k] = (string)$v;
            }

            if (!isset($attributes['id'])) {
                $id = 'menu'.$name;
                // name may contaion [], which would make an invalid id. e.g. numeric question type editing form, assignment quickgrading
                $id = str_replace('[', '', $id);
                $id = str_replace(']', '', $id);
                $attributes['id'] = $id;
            }

            if (!isset($attributes['class'])) {
                $class = 'menu'.$name;
                // name may contaion [], which would make an invalid class. e.g. numeric question type editing form, assignment quickgrading
                $class = str_replace('[', '', $class);
                $class = str_replace(']', '', $class);
                $attributes['class'] = $class;
            }
            $attributes['class'] = 'select ' . $attributes['class']; // Add 'select' selector always

            $attributes['name'] = $name;

            if (!empty($attributes['disabled'])) {
                $attributes['disabled'] = 'disabled';
            } else {
                unset($attributes['disabled']);
            }

            $output = '';
            foreach ($options as $value=>$label) {
                if (is_array($label)) {
                    // ignore key, it just has to be unique
                    $output .= self::select_optgroup(key($label), current($label), $selected);
                } else {
                    $output .= self::select_option($label, $value, $selected);
                }
            }
            return self::tag('select', $output, $attributes);
        }

        /**
         * Returns HTML to display a select box option.
         *
         * @param string $label The label to display as the option.
         * @param string|int $value The value the option represents
         * @param array $selected An array of selected options
         * @return string HTML fragment
         */
        private static function select_option($label, $value, array $selected) {
            $attributes = array();
            $value = (string)$value;
            if (in_array($value, $selected, true)) {
                $attributes['selected'] = 'selected';
            }
            $attributes['value'] = $value;
            return self::tag('option', $label, $attributes);
        }

        /**
         * Returns HTML to display a select box option group.
         *
         * @param string $groupname The label to use for the group
         * @param array $options The options in the group
         * @param array $selected An array of selected values.
         * @return string HTML fragment.
         */
        private static function select_optgroup($groupname, $options, array $selected) {
            if (empty($options)) {
                return '';
            }
            $attributes = array('label'=>$groupname);
            $output = '';
            foreach ($options as $value=>$label) {
                $output .= self::select_option($label, $value, $selected);
            }
            return self::tag('optgroup', $output, $attributes);
        }

        /**
         * This is a shortcut for making an hour selector menu.
         *
         * @param string $type The type of selector (years, months, days, hours, minutes)
         * @param string $name fieldname
         * @param int $currenttime A default timestamp in GMT
         * @param int $step minute spacing
         * @param array $attributes - html select element attributes
         * @return HTML fragment
         */
        public static function select_time($type, $name, $currenttime = 0, $step = 5, array $attributes = null) {
            if (!$currenttime) {
                $currenttime = time();
            }
            $currentdate = usergetdate($currenttime);
            $userdatetype = $type;
            $timeunits = array();

            switch ($type) {
                case 'years':
                    for ($i=1970; $i<=2020; $i++) {
                        $timeunits[$i] = $i;
                    }
                    $userdatetype = 'year';
                    break;
                case 'months':
                    for ($i=1; $i<=12; $i++) {
                        $timeunits[$i] = userdate(gmmktime(12,0,0,$i,15,2000), "%B");
                    }
                    $userdatetype = 'month';
                    $currentdate['month'] = (int)$currentdate['mon'];
                    break;
                case 'days':
                    for ($i=1; $i<=31; $i++) {
                        $timeunits[$i] = $i;
                    }
                    $userdatetype = 'mday';
                    break;
                case 'hours':
                    for ($i=0; $i<=23; $i++) {
                        $timeunits[$i] = sprintf("%02d",$i);
                    }
                    break;
                case 'minutes':
                    if ($step != 1) {
                        $currentdate['minutes'] = ceil($currentdate['minutes']/$step)*$step;
                    }

                    for ($i=0; $i<=59; $i+=$step) {
                        $timeunits[$i] = sprintf("%02d",$i);
                    }
                    break;
                default:
                    throw new coding_exception("Time type $type is not supported by html_writer::select_time().");
            }

            if (empty($attributes['id'])) {
                $attributes['id'] = self::random_id('ts_');
            }
            $timerselector = self::select($timeunits, $name, $currentdate[$userdatetype], null, array('id'=>$attributes['id']));
            $label = self::tag('label', get_string(substr($type, 0, -1), 'form'), array('for'=>$attributes['id'], 'class'=>'accesshide'));

            return $label.$timerselector;
        }

        /**
         * Shortcut for quick making of lists
         *
         * Note: 'list' is a reserved keyword ;-)
         *
         * @param array $items
         * @param array $attributes
         * @param string $tag ul or ol
         * @return string
         */
        public static function alist(array $items, array $attributes = null, $tag = 'ul') {
            $output = html_writer::start_tag($tag, $attributes)."\n";
            foreach ($items as $item) {
                $output .= html_writer::tag('li', $item)."\n";
            }
            $output .= html_writer::end_tag($tag);
            return $output;
        }

        /**
         * Returns hidden input fields created from url parameters.
         *
         * @param moodle_url $url
         * @param array $exclude list of excluded parameters
         * @return string HTML fragment
         */
        public static function input_hidden_params(moodle_url $url, array $exclude = null) {
            $exclude = (array)$exclude;
            $params = $url->params();
            foreach ($exclude as $key) {
                unset($params[$key]);
            }

            $output = '';
            foreach ($params as $key => $value) {
                $attributes = array('type'=>'hidden', 'name'=>$key, 'value'=>$value);
                $output .= self::empty_tag('input', $attributes)."\n";
            }
            return $output;
        }

        /**
         * Generate a script tag containing the the specified code.
         *
         * @param string $jscode the JavaScript code
         * @param moodle_url|string $url optional url of the external script, $code ignored if specified
         * @return string HTML, the code wrapped in <script> tags.
         */
        public static function script($jscode, $url=null) {
            if ($jscode) {
                $attributes = array('type'=>'text/javascript');
                return self::tag('script', "\n//<![CDATA[\n$jscode\n//]]>\n", $attributes) . "\n";

            } else if ($url) {
                $attributes = array('type'=>'text/javascript', 'src'=>$url);
                return self::tag('script', '', $attributes) . "\n";

            } else {
                return '';
            }
        }

        /**
         * Renders HTML table
         *
         * This method may modify the passed instance by adding some default properties if they are not set yet.
         * If this is not what you want, you should make a full clone of your data before passing them to this
         * method. In most cases this is not an issue at all so we do not clone by default for performance
         * and memory consumption reasons.
         *
         * Please do not use .r0/.r1 for css, as they will be removed in Moodle 2.9.
         * @todo MDL-43902 , remove r0 and r1 from tr classes.
         *
         * @param html_table $table data to be rendered
         * @return string HTML code
         */
        public static function table(html_table $table) {
            // prepare table data and populate missing properties with reasonable defaults
            if (!empty($table->align)) {
                foreach ($table->align as $key => $aa) {
                    if ($aa) {
                        $table->align[$key] = 'text-align:'. fix_align_rtl($aa) .';';  // Fix for RTL languages
                    } else {
                        $table->align[$key] = null;
                    }
                }
            }
            if (!empty($table->size)) {
                foreach ($table->size as $key => $ss) {
                    if ($ss) {
                        $table->size[$key] = 'width:'. $ss .';';
                    } else {
                        $table->size[$key] = null;
                    }
                }
            }
            if (!empty($table->wrap)) {
                foreach ($table->wrap as $key => $ww) {
                    if ($ww) {
                        $table->wrap[$key] = 'white-space:nowrap;';
                    } else {
                        $table->wrap[$key] = '';
                    }
                }
            }
            if (!empty($table->head)) {
                foreach ($table->head as $key => $val) {
                    if (!isset($table->align[$key])) {
                        $table->align[$key] = null;
                    }
                    if (!isset($table->size[$key])) {
                        $table->size[$key] = null;
                    }
                    if (!isset($table->wrap[$key])) {
                        $table->wrap[$key] = null;
                    }

                }
            }
            if (empty($table->attributes['class'])) {
                $table->attributes['class'] = 'generaltable';
            }
            if (!empty($table->tablealign)) {
                $table->attributes['class'] .= ' boxalign' . $table->tablealign;
            }

            // explicitly assigned properties override those defined via $table->attributes
            $table->attributes['class'] = trim($table->attributes['class']);
            $attributes = array_merge($table->attributes, array(
                    'id'            => $table->id,
                    'width'         => $table->width,
                    'summary'       => $table->summary,
                    'cellpadding'   => $table->cellpadding,
                    'cellspacing'   => $table->cellspacing,
                ));
            $output = html_writer::start_tag('table', $attributes) . "\n";

            $countcols = 0;

            if (!empty($table->head)) {
                $countcols = count($table->head);

                $output .= html_writer::start_tag('thead', array()) . "\n";
                $output .= html_writer::start_tag('tr', array()) . "\n";
                $keys = array_keys($table->head);
                $lastkey = end($keys);

                foreach ($table->head as $key => $heading) {
                    // Convert plain string headings into html_table_cell objects
                    if (!($heading instanceof html_table_cell)) {
                        $headingtext = $heading;
                        $heading = new html_table_cell();
                        $heading->text = $headingtext;
                        $heading->header = true;
                    }

                    if ($heading->header !== false) {
                        $heading->header = true;
                    }

                    if ($heading->header && empty($heading->scope)) {
                        $heading->scope = 'col';
                    }

                    $heading->attributes['class'] .= ' header c' . $key;
                    if (isset($table->headspan[$key]) && $table->headspan[$key] > 1) {
                        $heading->colspan = $table->headspan[$key];
                        $countcols += $table->headspan[$key] - 1;
                    }

                    if ($key == $lastkey) {
                        $heading->attributes['class'] .= ' lastcol';
                    }
                    if (isset($table->colclasses[$key])) {
                        $heading->attributes['class'] .= ' ' . $table->colclasses[$key];
                    }
                    $heading->attributes['class'] = trim($heading->attributes['class']);
                    $attributes = array_merge($heading->attributes, array(
                            'style'     => $table->align[$key] . $table->size[$key] . $heading->style,
                            'scope'     => $heading->scope,
                            'colspan'   => $heading->colspan,
                        ));

                    $tagtype = 'td';
                    if ($heading->header === true) {
                        $tagtype = 'th';
                    }
                    $output .= html_writer::tag($tagtype, $heading->text, $attributes) . "\n";
                }
                $output .= html_writer::end_tag('tr') . "\n";
                $output .= html_writer::end_tag('thead') . "\n";

                if (empty($table->data)) {
                    // For valid XHTML strict every table must contain either a valid tr
                    // or a valid tbody... both of which must contain a valid td
                    $output .= html_writer::start_tag('tbody', array('class' => 'empty'));
                    $output .= html_writer::tag('tr', html_writer::tag('td', '', array('colspan'=>count($table->head))));
                    $output .= html_writer::end_tag('tbody');
                }
            }

            if (!empty($table->data)) {
                $oddeven    = 1;
                $keys       = array_keys($table->data);
                $lastrowkey = end($keys);
                $output .= html_writer::start_tag('tbody', array());

                foreach ($table->data as $key => $row) {
                    if (($row === 'hr') && ($countcols)) {
                        $output .= html_writer::tag('td', html_writer::tag('div', '', array('class' => 'tabledivider')), array('colspan' => $countcols));
                    } else {
                        // Convert array rows to html_table_rows and cell strings to html_table_cell objects
                        if (!($row instanceof html_table_row)) {
                            $newrow = new html_table_row();

                            foreach ($row as $cell) {
                                if (!($cell instanceof html_table_cell)) {
                                    $cell = new html_table_cell($cell);
                                }
                                $newrow->cells[] = $cell;
                            }
                            $row = $newrow;
                        }

                        $oddeven = $oddeven ? 0 : 1;
                        if (isset($table->rowclasses[$key])) {
                            $row->attributes['class'] .= ' ' . $table->rowclasses[$key];
                        }

                        $row->attributes['class'] .= ' r' . $oddeven;
                        if ($key == $lastrowkey) {
                            $row->attributes['class'] .= ' lastrow';
                        }

                        $output .= html_writer::start_tag('tr', array('class' => trim($row->attributes['class']), 'style' => $row->style, 'id' => $row->id)) . "\n";
                        $keys2 = array_keys($row->cells);
                        $lastkey = end($keys2);

                        $gotlastkey = false; //flag for sanity checking
                        foreach ($row->cells as $key => $cell) {
                            if ($gotlastkey) {
                                //This should never happen. Why do we have a cell after the last cell?
                                mtrace("A cell with key ($key) was found after the last key ($lastkey)");
                            }

                            if (!($cell instanceof html_table_cell)) {
                                $mycell = new html_table_cell();
                                $mycell->text = $cell;
                                $cell = $mycell;
                            }

                            if (($cell->header === true) && empty($cell->scope)) {
                                $cell->scope = 'row';
                            }

                            if (isset($table->colclasses[$key])) {
                                $cell->attributes['class'] .= ' ' . $table->colclasses[$key];
                            }

                            $cell->attributes['class'] .= ' cell c' . $key;
                            if ($key == $lastkey) {
                                $cell->attributes['class'] .= ' lastcol';
                                $gotlastkey = true;
                            }
                            $tdstyle = '';
                            $tdstyle .= isset($table->align[$key]) ? $table->align[$key] : '';
                            $tdstyle .= isset($table->size[$key]) ? $table->size[$key] : '';
                            $tdstyle .= isset($table->wrap[$key]) ? $table->wrap[$key] : '';
                            $cell->attributes['class'] = trim($cell->attributes['class']);
                            $tdattributes = array_merge($cell->attributes, array(
                                    'style' => $tdstyle . $cell->style,
                                    'colspan' => $cell->colspan,
                                    'rowspan' => $cell->rowspan,
                                    'id' => $cell->id,
                                    'abbr' => $cell->abbr,
                                    'scope' => $cell->scope,
                                ));
                            $tagtype = 'td';
                            if ($cell->header === true) {
                                $tagtype = 'th';
                            }
                            $output .= html_writer::tag($tagtype, $cell->text, $tdattributes) . "\n";
                        }
                    }
                    $output .= html_writer::end_tag('tr') . "\n";
                }
                $output .= html_writer::end_tag('tbody') . "\n";
            }
            $output .= html_writer::end_tag('table') . "\n";

            return $output;
        }

        /**
         * Renders form element label
         *
         * By default, the label is suffixed with a label separator defined in the
         * current language pack (colon by default in the English lang pack).
         * Adding the colon can be explicitly disabled if needed. Label separators
         * are put outside the label tag itself so they are not read by
         * screenreaders (accessibility).
         *
         * Parameter $for explicitly associates the label with a form control. When
         * set, the value of this attribute must be the same as the value of
         * the id attribute of the form control in the same document. When null,
         * the label being defined is associated with the control inside the label
         * element.
         *
         * @param string $text content of the label tag
         * @param string|null $for id of the element this label is associated with, null for no association
         * @param bool $colonize add label separator (colon) to the label text, if it is not there yet
         * @param array $attributes to be inserted in the tab, for example array('accesskey' => 'a')
         * @return string HTML of the label element
         */
        public static function label($text, $for, $colonize = true, array $attributes=array()) {
            if (!is_null($for)) {
                $attributes = array_merge($attributes, array('for' => $for));
            }
            $text = trim($text);
            $label = self::tag('label', $text, $attributes);

            // TODO MDL-12192 $colonize disabled for now yet
            // if (!empty($text) and $colonize) {
            //     // the $text may end with the colon already, though it is bad string definition style
            //     $colon = get_string('labelsep', 'langconfig');
            //     if (!empty($colon)) {
            //         $trimmed = trim($colon);
            //         if ((substr($text, -strlen($trimmed)) == $trimmed) or (substr($text, -1) == ':')) {
            //             //debugging('The label text should not end with colon or other label separator,
            //             //           please fix the string definition.', DEBUG_DEVELOPER);
            //         } else {
            //             $label .= $colon;
            //         }
            //     }
            // }

            return $label;
        }

        /**
         * Combines a class parameter with other attributes. Aids in code reduction
         * because the class parameter is very frequently used.
         *
         * If the class attribute is specified both in the attributes and in the
         * class parameter, the two values are combined with a space between.
         *
         * @param string $class Optional CSS class (or classes as space-separated list)
         * @param array $attributes Optional other attributes as array
         * @return array Attributes (or null if still none)
         */
        private static function add_class($class = '', array $attributes = null) {
            if ($class !== '') {
                $classattribute = array('class' => $class);
                if ($attributes) {
                    if (array_key_exists('class', $attributes)) {
                        $attributes['class'] = trim($attributes['class'] . ' ' . $class);
                    } else {
                        $attributes = $classattribute + $attributes;
                    }
                } else {
                    $attributes = $classattribute;
                }
            }
            return $attributes;
        }

        /**
         * Creates a <div> tag. (Shortcut function.)
         *
         * @param string $content HTML content of tag
         * @param string $class Optional CSS class (or classes as space-separated list)
         * @param array $attributes Optional other attributes as array
         * @return string HTML code for div
         */
        public static function div($content, $class = '', array $attributes = null) {
            return self::tag('div', $content, self::add_class($class, $attributes));
        }

        /**
         * Starts a <div> tag. (Shortcut function.)
         *
         * @param string $class Optional CSS class (or classes as space-separated list)
         * @param array $attributes Optional other attributes as array
         * @return string HTML code for open div tag
         */
        public static function start_div($class = '', array $attributes = null) {
            return self::start_tag('div', self::add_class($class, $attributes));
        }

        /**
         * Ends a <div> tag. (Shortcut function.)
         *
         * @return string HTML code for close div tag
         */
        public static function end_div() {
            return self::end_tag('div');
        }

        /**
         * Creates a <span> tag. (Shortcut function.)
         *
         * @param string $content HTML content of tag
         * @param string $class Optional CSS class (or classes as space-separated list)
         * @param array $attributes Optional other attributes as array
         * @return string HTML code for span
         */
        public static function span($content, $class = '', array $attributes = null) {
            return self::tag('span', $content, self::add_class($class, $attributes));
        }

        /**
         * Starts a <span> tag. (Shortcut function.)
         *
         * @param string $class Optional CSS class (or classes as space-separated list)
         * @param array $attributes Optional other attributes as array
         * @return string HTML code for open span tag
         */
        public static function start_span($class = '', array $attributes = null) {
            return self::start_tag('span', self::add_class($class, $attributes));
        }

        /**
         * Ends a <span> tag. (Shortcut function.)
         *
         * @return string HTML code for close span tag
         */
        public static function end_span() {
            return self::end_tag('span');
        }
    }

    /**
     * Holds all the information required to render a <table> by {@link core_renderer::table()}
     *
     * Example of usage:
     * $t = new html_table();
     * ... // set various properties of the object $t as described below
     * echo html_writer::table($t);
     *
     * @copyright 2009 David Mudrak <david.mudrak@gmail.com>
     * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since Moodle 2.0
     * @package core
     * @category output
     */
    class html_table {

        /**
         * @var string Value to use for the id attribute of the table
         */
        public $id = null;

        /**
         * @var array Attributes of HTML attributes for the <table> element
         */
        public $attributes = array();

        /**
         * @var array An array of headings. The n-th array item is used as a heading of the n-th column.
         * For more control over the rendering of the headers, an array of html_table_cell objects
         * can be passed instead of an array of strings.
         *
         * Example of usage:
         * $t->head = array('Student', 'Grade');
         */
        public $head;

        /**
         * @var array An array that can be used to make a heading span multiple columns.
         * In this example, {@link html_table:$data} is supposed to have three columns. For the first two columns,
         * the same heading is used. Therefore, {@link html_table::$head} should consist of two items.
         *
         * Example of usage:
         * $t->headspan = array(2,1);
         */
        public $headspan;

        /**
         * @var array An array of column alignments.
         * The value is used as CSS 'text-align' property. Therefore, possible
         * values are 'left', 'right', 'center' and 'justify'. Specify 'right' or 'left' from the perspective
         * of a left-to-right (LTR) language. For RTL, the values are flipped automatically.
         *
         * Examples of usage:
         * $t->align = array(null, 'right');
         * or
         * $t->align[1] = 'right';
         */
        public $align;

        /**
         * @var array The value is used as CSS 'size' property.
         *
         * Examples of usage:
         * $t->size = array('50%', '50%');
         * or
         * $t->size[1] = '120px';
         */
        public $size;

        /**
         * @var array An array of wrapping information.
         * The only possible value is 'nowrap' that sets the
         * CSS property 'white-space' to the value 'nowrap' in the given column.
         *
         * Example of usage:
         * $t->wrap = array(null, 'nowrap');
         */
        public $wrap;

        /**
         * @var array Array of arrays or html_table_row objects containing the data. Alternatively, if you have
         * $head specified, the string 'hr' (for horizontal ruler) can be used
         * instead of an array of cells data resulting in a divider rendered.
         *
         * Example of usage with array of arrays:
         * $row1 = array('Harry Potter', '76 %');
         * $row2 = array('Hermione Granger', '100 %');
         * $t->data = array($row1, $row2);
         *
         * Example with array of html_table_row objects: (used for more fine-grained control)
         * $cell1 = new html_table_cell();
         * $cell1->text = 'Harry Potter';
         * $cell1->colspan = 2;
         * $row1 = new html_table_row();
         * $row1->cells[] = $cell1;
         * $cell2 = new html_table_cell();
         * $cell2->text = 'Hermione Granger';
         * $cell3 = new html_table_cell();
         * $cell3->text = '100 %';
         * $row2 = new html_table_row();
         * $row2->cells = array($cell2, $cell3);
         * $t->data = array($row1, $row2);
         */
        public $data;

        /**
         * @deprecated since Moodle 2.0. Styling should be in the CSS.
         * @var string Width of the table, percentage of the page preferred.
         */
        public $width = null;

        /**
         * @deprecated since Moodle 2.0. Styling should be in the CSS.
         * @var string Alignment for the whole table. Can be 'right', 'left' or 'center' (default).
         */
        public $tablealign = null;

        /**
         * @deprecated since Moodle 2.0. Styling should be in the CSS.
         * @var int Padding on each cell, in pixels
         */
        public $cellpadding = null;

        /**
         * @var int Spacing between cells, in pixels
         * @deprecated since Moodle 2.0. Styling should be in the CSS.
         */
        public $cellspacing = null;

        /**
         * @var array Array of classes to add to particular rows, space-separated string.
         * Classes 'r0' or 'r1' are added automatically for every odd or even row,
         * respectively. Class 'lastrow' is added automatically for the last row
         * in the table.
         *
         * Example of usage:
         * $t->rowclasses[9] = 'tenth'
         */
        public $rowclasses;

        /**
         * @var array An array of classes to add to every cell in a particular column,
         * space-separated string. Class 'cell' is added automatically by the renderer.
         * Classes 'c0' or 'c1' are added automatically for every odd or even column,
         * respectively. Class 'lastcol' is added automatically for all last cells
         * in a row.
         *
         * Example of usage:
         * $t->colclasses = array(null, 'grade');
         */
        public $colclasses;

        /**
         * @var string Description of the contents for screen readers.
         */
        public $summary;

        /**
         * Constructor
         */
        public function __construct() {
            $this->attributes['class'] = '';
        }
    }

    /**
     * Component representing a table row.
     *
     * @copyright 2009 Nicolas Connault
     * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since Moodle 2.0
     * @package core
     * @category output
     */
    class html_table_row {

        /**
         * @var string Value to use for the id attribute of the row.
         */
        public $id = null;

        /**
         * @var array Array of html_table_cell objects
         */
        public $cells = array();

        /**
         * @var string Value to use for the style attribute of the table row
         */
        public $style = null;

        /**
         * @var array Attributes of additional HTML attributes for the <tr> element
         */
        public $attributes = array();

        /**
         * Constructor
         * @param array $cells
         */
        public function __construct(array $cells=null) {
            $this->attributes['class'] = '';
            $cells = (array)$cells;
            foreach ($cells as $cell) {
                if ($cell instanceof html_table_cell) {
                    $this->cells[] = $cell;
                } else {
                    $this->cells[] = new html_table_cell($cell);
                }
            }
        }
    }

    /**
     * Component representing a table cell.
     *
     * @copyright 2009 Nicolas Connault
     * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since Moodle 2.0
     * @package core
     * @category output
     */
    class html_table_cell {

        /**
         * @var string Value to use for the id attribute of the cell.
         */
        public $id = null;

        /**
         * @var string The contents of the cell.
         */
        public $text;

        /**
         * @var string Abbreviated version of the contents of the cell.
         */
        public $abbr = null;

        /**
         * @var int Number of columns this cell should span.
         */
        public $colspan = null;

        /**
         * @var int Number of rows this cell should span.
         */
        public $rowspan = null;

        /**
         * @var string Defines a way to associate header cells and data cells in a table.
         */
        public $scope = null;

        /**
         * @var bool Whether or not this cell is a header cell.
         */
        public $header = null;

        /**
         * @var string Value to use for the style attribute of the table cell
         */
        public $style = null;

        /**
         * @var array Attributes of additional HTML attributes for the <td> element
         */
        public $attributes = array();

        /**
         * Constructs a table cell
         *
         * @param string $text
         */
        public function __construct($text = null) {
            $this->text = $text;
            $this->attributes['class'] = '';
        }
    }

    /**
     * RESTful cURL class
     *
     * This is a wrapper class for curl, it is quite easy to use:
     * <code>
     * $c = new curl;
     * // enable cache
     * $c = new curl(array('cache'=>true));
     * // enable cookie
     * $c = new curl(array('cookie'=>true));
     * // enable proxy
     * $c = new curl(array('proxy'=>true));
     *
     * // HTTP GET Method
     * $html = $c->get('http://example.com');
     * // HTTP POST Method
     * $html = $c->post('http://example.com/', array('q'=>'words', 'name'=>'moodle'));
     * // HTTP PUT Method
     * $html = $c->put('http://example.com/', array('file'=>'/var/www/test.txt');
     * </code>
     *
     * @package   core_files
     * @category files
     * @copyright Dongsheng Cai <dongsheng@moodle.com>
     * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License
     */
    class curl {
        /** @var bool Caches http request contents */
        public  $cache    = false;
        /** @var bool Uses proxy, null means automatic based on URL */
        public  $proxy    = null;
        /** @var string library version */
        public  $version  = '0.4 dev';
        /** @var array http's response */
        public  $response = array();
        /** @var array Raw response headers, needed for BC in download_file_content(). */
        public $rawresponse = array();
        /** @var array http header */
        public  $header   = array();
        /** @var string cURL information */
        public  $info;
        /** @var string error */
        public  $error;
        /** @var int error code */
        public  $errno;
        /** @var bool use workaround for open_basedir restrictions, to be changed from unit tests only! */
        public $emulateredirects = null;

        /** @var array cURL options */
        private $options;
        /** @var string Proxy host */
        private $proxy_host = '';
        /** @var string Proxy auth */
        private $proxy_auth = '';
        /** @var string Proxy type */
        private $proxy_type = '';
        /** @var bool Debug mode on */
        private $debug    = false;
        /** @var bool|string Path to cookie file */
        private $cookie   = false;
        /** @var bool tracks multiple headers in response - redirect detection */
        private $responsefinished = false;

        /**
         * Curl constructor.
         *
         * Allowed settings are:
         *  proxy: (bool) use proxy server, null means autodetect non-local from url
         *  debug: (bool) use debug output
         *  cookie: (string) path to cookie file, false if none
         *  cache: (bool) use cache
         *  module_cache: (string) type of cache
         *
         * @param array $settings
         */
        public function __construct($settings = array()) {
            global $CFG;
            if (!function_exists('curl_init')) {
                $this->error = 'cURL module must be enabled!';
                trigger_error($this->error, E_USER_ERROR);
                return false;
            }

            // All settings of this class should be init here.
            $this->resetopt();
            if (!empty($settings['debug'])) {
                $this->debug = true;
            }
            if (!empty($settings['cookie'])) {
                if($settings['cookie'] === true) {
                    $this->cookie = $CFG->dataroot.'/curl_cookie.txt';
                } else {
                    $this->cookie = $settings['cookie'];
                }
            }
            if (!empty($settings['cache'])) {
                if (class_exists('curl_cache')) {
                    if (!empty($settings['module_cache'])) {
                        $this->cache = new curl_cache($settings['module_cache']);
                    } else {
                        $this->cache = new curl_cache('misc');
                    }
                }
            }
            if (!empty($CFG->proxyhost)) {
                if (empty($CFG->proxyport)) {
                    $this->proxy_host = $CFG->proxyhost;
                } else {
                    $this->proxy_host = $CFG->proxyhost.':'.$CFG->proxyport;
                }
                if (!empty($CFG->proxyuser) and !empty($CFG->proxypassword)) {
                    $this->proxy_auth = $CFG->proxyuser.':'.$CFG->proxypassword;
                    $this->setopt(array(
                                'proxyauth'=> CURLAUTH_BASIC | CURLAUTH_NTLM,
                                'proxyuserpwd'=>$this->proxy_auth));
                }
                if (!empty($CFG->proxytype)) {
                    if ($CFG->proxytype == 'SOCKS5') {
                        $this->proxy_type = CURLPROXY_SOCKS5;
                    } else {
                        $this->proxy_type = CURLPROXY_HTTP;
                        $this->setopt(array('httpproxytunnel'=>false));
                    }
                    $this->setopt(array('proxytype'=>$this->proxy_type));
                }

                if (isset($settings['proxy'])) {
                    $this->proxy = $settings['proxy'];
                }
            } else {
                $this->proxy = false;
            }

            if (!isset($this->emulateredirects)) {
                $this->emulateredirects = ini_get('open_basedir');
            }
        }

        /**
         * Resets the CURL options that have already been set
         */
        public function resetopt() {
            $this->options = array();
            $this->options['CURLOPT_USERAGENT']         = 'MoodleBot/1.0';
            // True to include the header in the output
            $this->options['CURLOPT_HEADER']            = 0;
            // True to Exclude the body from the output
            $this->options['CURLOPT_NOBODY']            = 0;
            // Redirect ny default.
            $this->options['CURLOPT_FOLLOWLOCATION']    = 1;
            $this->options['CURLOPT_MAXREDIRS']         = 10;
            $this->options['CURLOPT_ENCODING']          = '';
            // TRUE to return the transfer as a string of the return
            // value of curl_exec() instead of outputting it out directly.
            $this->options['CURLOPT_RETURNTRANSFER']    = 1;
            $this->options['CURLOPT_SSL_VERIFYPEER']    = 0;
            $this->options['CURLOPT_SSL_VERIFYHOST']    = 2;
            $this->options['CURLOPT_CONNECTTIMEOUT']    = 30;

            if ($cacert = self::get_cacert()) {
                $this->options['CURLOPT_CAINFO'] = $cacert;
            }
        }

        /**
         * Get the location of ca certificates.
         * @return string absolute file path or empty if default used
         */
        public static function get_cacert() {
            global $CFG;

            // Bundle in dataroot always wins.
            if (is_readable("$CFG->dataroot/moodleorgca.crt")) {
                return realpath("$CFG->dataroot/moodleorgca.crt");
            }

            // Next comes the default from php.ini
            $cacert = ini_get('curl.cainfo');
            if (!empty($cacert) and is_readable($cacert)) {
                return realpath($cacert);
            }

            // Windows PHP does not have any certs, we need to use something.
            if ($CFG->ostype === 'WINDOWS') {
                if (is_readable("$CFG->libdir/cacert.pem")) {
                    return realpath("$CFG->libdir/cacert.pem");
                }
            }

            // Use default, this should work fine on all properly configured *nix systems.
            return null;
        }

        /**
         * Reset Cookie
         */
        public function resetcookie() {
            if (!empty($this->cookie)) {
                if (is_file($this->cookie)) {
                    $fp = fopen($this->cookie, 'w');
                    if (!empty($fp)) {
                        fwrite($fp, '');
                        fclose($fp);
                    }
                }
            }
        }

        /**
         * Set curl options.
         *
         * Do not use the curl constants to define the options, pass a string
         * corresponding to that constant. Ie. to set CURLOPT_MAXREDIRS, pass
         * array('CURLOPT_MAXREDIRS' => 10) or array('maxredirs' => 10) to this method.
         *
         * @param array $options If array is null, this function will reset the options to default value.
         * @return void
         * @throws coding_exception If an option uses constant value instead of option name.
         */
        public function setopt($options = array()) {
            if (is_array($options)) {
                foreach ($options as $name => $val) {
                    if (!is_string($name)) {
                        throw new coding_exception('Curl options should be defined using strings, not constant values.');
                    }
                    if (stripos($name, 'CURLOPT_') === false) {
                        $name = strtoupper('CURLOPT_'.$name);
                    } else {
                        $name = strtoupper($name);
                    }
                    $this->options[$name] = $val;
                }
            }
        }

        /**
         * Reset http method
         */
        public function cleanopt() {
            unset($this->options['CURLOPT_HTTPGET']);
            unset($this->options['CURLOPT_POST']);
            unset($this->options['CURLOPT_POSTFIELDS']);
            unset($this->options['CURLOPT_PUT']);
            unset($this->options['CURLOPT_INFILE']);
            unset($this->options['CURLOPT_INFILESIZE']);
            unset($this->options['CURLOPT_CUSTOMREQUEST']);
            unset($this->options['CURLOPT_FILE']);
        }

        /**
         * Resets the HTTP Request headers (to prepare for the new request)
         */
        public function resetHeader() {
            $this->header = array();
        }

        /**
         * Set HTTP Request Header
         *
         * @param array $header
         */
        public function setHeader($header) {
            if (is_array($header)) {
                foreach ($header as $v) {
                    $this->setHeader($v);
                }
            } else {
                // Remove newlines, they are not allowed in headers.
                $this->header[] = preg_replace('/[\r\n]/', '', $header);
            }
        }

        /**
         * Get HTTP Response Headers
         * @return array of arrays
         */
        public function getResponse() {
            return $this->response;
        }

        /**
         * Get raw HTTP Response Headers
         * @return array of strings
         */
        public function get_raw_response() {
            return $this->rawresponse;
        }

        /**
         * private callback function
         * Formatting HTTP Response Header
         *
         * We only keep the last headers returned. For example during a redirect the
         * redirect headers will not appear in {@link self::getResponse()}, if you need
         * to use those headers, refer to {@link self::get_raw_response()}.
         *
         * @param resource $ch Apparently not used
         * @param string $header
         * @return int The strlen of the header
         */
        private function formatHeader($ch, $header) {
            $this->rawresponse[] = $header;

            if (trim($header, "\r\n") === '') {
                // This must be the last header.
                $this->responsefinished = true;
            }

            if (strlen($header) > 2) {
                if ($this->responsefinished) {
                    // We still have headers after the supposedly last header, we must be
                    // in a redirect so let's empty the response to keep the last headers.
                    $this->responsefinished = false;
                    $this->response = array();
                }
                list($key, $value) = explode(" ", rtrim($header, "\r\n"), 2);
                $key = rtrim($key, ':');
                if (!empty($this->response[$key])) {
                    if (is_array($this->response[$key])) {
                        $this->response[$key][] = $value;
                    } else {
                        $tmp = $this->response[$key];
                        $this->response[$key] = array();
                        $this->response[$key][] = $tmp;
                        $this->response[$key][] = $value;

                    }
                } else {
                    $this->response[$key] = $value;
                }
            }
            return strlen($header);
        }

        /**
         * Set options for individual curl instance
         *
         * @param resource $curl A curl handle
         * @param array $options
         * @return resource The curl handle
         */
        private function apply_opt($curl, $options) {
            // Some more security first.
            if (defined('CURLOPT_PROTOCOLS')) {
                $this->options['CURLOPT_PROTOCOLS'] = (CURLPROTO_HTTP | CURLPROTO_HTTPS);
            }
            if (defined('CURLOPT_REDIR_PROTOCOLS')) {
                $this->options['CURLOPT_REDIR_PROTOCOLS'] = (CURLPROTO_HTTP | CURLPROTO_HTTPS);
            }

            // Clean up
            $this->cleanopt();
            // set cookie
            if (!empty($this->cookie) || !empty($options['cookie'])) {
                $this->setopt(array('cookiejar'=>$this->cookie,
                                'cookiefile'=>$this->cookie
                                 ));
            }

            // Bypass proxy if required.
            if ($this->proxy === null) {
                if (!empty($this->options['CURLOPT_URL']) and is_proxybypass($this->options['CURLOPT_URL'])) {
                    $proxy = false;
                } else {
                    $proxy = true;
                }
            } else {
                $proxy = (bool)$this->proxy;
            }

            // Set proxy.
            if ($proxy) {
                $options['CURLOPT_PROXY'] = $this->proxy_host;
            } else {
                unset($this->options['CURLOPT_PROXY']);
            }

            $this->setopt($options);
            // reset before set options
            curl_setopt($curl, CURLOPT_HEADERFUNCTION, array(&$this,'formatHeader'));
            // set headers
            if (empty($this->header)) {
                $this->setHeader(array(
                    'User-Agent: MoodleBot/1.0',
                    'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
                    'Connection: keep-alive'
                    ));
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $this->header);

            if ($this->debug) {
                echo '<h1>Options</h1>';
                var_dump($this->options);
                echo '<h1>Header</h1>';
                var_dump($this->header);
            }

            // Do not allow infinite redirects.
            if (!isset($this->options['CURLOPT_MAXREDIRS'])) {
                $this->options['CURLOPT_MAXREDIRS'] = 0;
            } else if ($this->options['CURLOPT_MAXREDIRS'] > 100) {
                $this->options['CURLOPT_MAXREDIRS'] = 100;
            } else {
                $this->options['CURLOPT_MAXREDIRS'] = (int)$this->options['CURLOPT_MAXREDIRS'];
            }

            // Make sure we always know if redirects expected.
            if (!isset($this->options['CURLOPT_FOLLOWLOCATION'])) {
                $this->options['CURLOPT_FOLLOWLOCATION'] = 0;
            }

            // Set options.
            foreach($this->options as $name => $val) {
                if ($name === 'CURLOPT_PROTOCOLS' or $name === 'CURLOPT_REDIR_PROTOCOLS') {
                    // These can not be changed, sorry.
                    continue;
                }
                if ($name === 'CURLOPT_FOLLOWLOCATION' and $this->emulateredirects) {
                    // The redirects are emulated elsewhere.
                    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
                    continue;
                }
                $name = constant($name);
                curl_setopt($curl, $name, $val);
            }

            return $curl;
        }

        /**
         * Download multiple files in parallel
         *
         * Calls {@link multi()} with specific download headers
         *
         * <code>
         * $c = new curl();
         * $file1 = fopen('a', 'wb');
         * $file2 = fopen('b', 'wb');
         * $c->download(array(
         *     array('url'=>'http://localhost/', 'file'=>$file1),
         *     array('url'=>'http://localhost/20/', 'file'=>$file2)
         * ));
         * fclose($file1);
         * fclose($file2);
         * </code>
         *
         * or
         *
         * <code>
         * $c = new curl();
         * $c->download(array(
         *              array('url'=>'http://localhost/', 'filepath'=>'/tmp/file1.tmp'),
         *              array('url'=>'http://localhost/20/', 'filepath'=>'/tmp/file2.tmp')
         *              ));
         * </code>
         *
         * @param array $requests An array of files to request {
         *                  url => url to download the file [required]
         *                  file => file handler, or
         *                  filepath => file path
         * }
         * If 'file' and 'filepath' parameters are both specified in one request, the
         * open file handle in the 'file' parameter will take precedence and 'filepath'
         * will be ignored.
         *
         * @param array $options An array of options to set
         * @return array An array of results
         */
        public function download($requests, $options = array()) {
            $options['RETURNTRANSFER'] = false;
            return $this->multi($requests, $options);
        }

        /**
         * Multi HTTP Requests
         * This function could run multi-requests in parallel.
         *
         * @param array $requests An array of files to request
         * @param array $options An array of options to set
         * @return array An array of results
         */
        protected function multi($requests, $options = array()) {
            $count   = count($requests);
            $handles = array();
            $results = array();
            $main    = curl_multi_init();
            for ($i = 0; $i < $count; $i++) {
                if (!empty($requests[$i]['filepath']) and empty($requests[$i]['file'])) {
                    // open file
                    $requests[$i]['file'] = fopen($requests[$i]['filepath'], 'w');
                    $requests[$i]['auto-handle'] = true;
                }
                foreach($requests[$i] as $n=>$v) {
                    $options[$n] = $v;
                }
                $handles[$i] = curl_init($requests[$i]['url']);
                $this->apply_opt($handles[$i], $options);
                curl_multi_add_handle($main, $handles[$i]);
            }
            $running = 0;
            do {
                curl_multi_exec($main, $running);
            } while($running > 0);
            for ($i = 0; $i < $count; $i++) {
                if (!empty($options['CURLOPT_RETURNTRANSFER'])) {
                    $results[] = true;
                } else {
                    $results[] = curl_multi_getcontent($handles[$i]);
                }
                curl_multi_remove_handle($main, $handles[$i]);
            }
            curl_multi_close($main);

            for ($i = 0; $i < $count; $i++) {
                if (!empty($requests[$i]['filepath']) and !empty($requests[$i]['auto-handle'])) {
                    // close file handler if file is opened in this function
                    fclose($requests[$i]['file']);
                }
            }
            return $results;
        }

        /**
         * Single HTTP Request
         *
         * @param string $url The URL to request
         * @param array $options
         * @return bool
         */
        protected function request($url, $options = array()) {
            // Set the URL as a curl option.
            $this->setopt(array('CURLOPT_URL' => $url));

            // Create curl instance.
            $curl = curl_init();

            // Reset here so that the data is valid when result returned from cache.
            $this->info             = array();
            $this->error            = '';
            $this->errno            = 0;
            $this->response         = array();
            $this->rawresponse      = array();
            $this->responsefinished = false;

            $this->apply_opt($curl, $options);
            if ($this->cache && $ret = $this->cache->get($this->options)) {
                return $ret;
            }

            $ret = curl_exec($curl);
            $this->info  = curl_getinfo($curl);
            $this->error = curl_error($curl);
            $this->errno = curl_errno($curl);
            // Note: $this->response and $this->rawresponse are filled by $hits->formatHeader callback.

            if ($this->emulateredirects and $this->options['CURLOPT_FOLLOWLOCATION'] and $this->info['http_code'] != 200) {
                $redirects = 0;

                while($redirects <= $this->options['CURLOPT_MAXREDIRS']) {

                    if ($this->info['http_code'] == 301) {
                        // Moved Permanently - repeat the same request on new URL.

                    } else if ($this->info['http_code'] == 302) {
                        // Found - the standard redirect - repeat the same request on new URL.

                    } else if ($this->info['http_code'] == 303) {
                        // 303 See Other - repeat only if GET, do not bother with POSTs.
                        if (empty($this->options['CURLOPT_HTTPGET'])) {
                            break;
                        }

                    } else if ($this->info['http_code'] == 307) {
                        // Temporary Redirect - must repeat using the same request type.

                    } else if ($this->info['http_code'] == 308) {
                        // Permanent Redirect - must repeat using the same request type.

                    } else {
                        // Some other http code means do not retry!
                        break;
                    }

                    $redirects++;

                    $redirecturl = null;
                    if (isset($this->info['redirect_url'])) {
                        if (preg_match('|^https?://|i', $this->info['redirect_url'])) {
                            $redirecturl = $this->info['redirect_url'];
                        }
                    }
                    if (!$redirecturl) {
                        foreach ($this->response as $k => $v) {
                            if (strtolower($k) === 'location') {
                                $redirecturl = $v;
                                break;
                            }
                        }
                        if (preg_match('|^https?://|i', $redirecturl)) {
                            // Great, this is the correct location format!

                        } else if ($redirecturl) {
                            $current = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
                            if (strpos($redirecturl, '/') === 0) {
                                // Relative to server root - just guess.
                                $pos = strpos('/', $current, 8);
                                if ($pos === false) {
                                    $redirecturl = $current.$redirecturl;
                                } else {
                                    $redirecturl = substr($current, 0, $pos).$redirecturl;
                                }
                            } else {
                                // Relative to current script.
                                $redirecturl = dirname($current).'/'.$redirecturl;
                            }
                        }
                    }

                    curl_setopt($curl, CURLOPT_URL, $redirecturl);
                    $ret = curl_exec($curl);

                    $this->info  = curl_getinfo($curl);
                    $this->error = curl_error($curl);
                    $this->errno = curl_errno($curl);

                    $this->info['redirect_count'] = $redirects;

                    if ($this->info['http_code'] === 200) {
                        // Finally this is what we wanted.
                        break;
                    }
                    if ($this->errno != CURLE_OK) {
                        // Something wrong is going on.
                        break;
                    }
                }
                if ($redirects > $this->options['CURLOPT_MAXREDIRS']) {
                    $this->errno = CURLE_TOO_MANY_REDIRECTS;
                    $this->error = 'Maximum ('.$this->options['CURLOPT_MAXREDIRS'].') redirects followed';
                }
            }

            if ($this->cache) {
                $this->cache->set($this->options, $ret);
            }

            if ($this->debug) {
                echo '<h1>Return Data</h1>';
                var_dump($ret);
                echo '<h1>Info</h1>';
                var_dump($this->info);
                echo '<h1>Error</h1>';
                var_dump($this->error);
            }

            curl_close($curl);

            if (empty($this->error)) {
                return $ret;
            } else {
                return $this->error;
                // exception is not ajax friendly
                //throw new moodle_exception($this->error, 'curl');
            }
        }

        /**
         * HTTP HEAD method
         *
         * @see request()
         *
         * @param string $url
         * @param array $options
         * @return bool
         */
        public function head($url, $options = array()) {
            $options['CURLOPT_HTTPGET'] = 0;
            $options['CURLOPT_HEADER']  = 1;
            $options['CURLOPT_NOBODY']  = 1;
            return $this->request($url, $options);
        }

        /**
         * HTTP POST method
         *
         * @param string $url
         * @param array|string $params
         * @param array $options
         * @return bool
         */
        public function post($url, $params = '', $options = array()) {
            $options['CURLOPT_POST']       = 1;
            if (is_array($params)) {
                $this->_tmp_file_post_params = array();
                foreach ($params as $key => $value) {
                    if ($value instanceof stored_file) {
                        $value->add_to_curl_request($this, $key);
                    } else {
                        $this->_tmp_file_post_params[$key] = $value;
                    }
                }
                $options['CURLOPT_POSTFIELDS'] = $this->_tmp_file_post_params;
                unset($this->_tmp_file_post_params);
            } else {
                // $params is the raw post data
                $options['CURLOPT_POSTFIELDS'] = $params;
            }
            return $this->request($url, $options);
        }

        /**
         * HTTP GET method
         *
         * @param string $url
         * @param array $params
         * @param array $options
         * @return bool
         */
        public function get($url, $params = array(), $options = array()) {
            $options['CURLOPT_HTTPGET'] = 1;

            if (!empty($params)) {
                $url .= (stripos($url, '?') !== false) ? '&' : '?';
                $url .= http_build_query($params, '', '&');
            }
            return $this->request($url, $options);
        }

        /**
         * Downloads one file and writes it to the specified file handler
         *
         * <code>
         * $c = new curl();
         * $file = fopen('savepath', 'w');
         * $result = $c->download_one('http://localhost/', null,
         *   array('file' => $file, 'timeout' => 5, 'followlocation' => true, 'maxredirs' => 3));
         * fclose($file);
         * $download_info = $c->get_info();
         * if ($result === true) {
         *   // file downloaded successfully
         * } else {
         *   $error_text = $result;
         *   $error_code = $c->get_errno();
         * }
         * </code>
         *
         * <code>
         * $c = new curl();
         * $result = $c->download_one('http://localhost/', null,
         *   array('filepath' => 'savepath', 'timeout' => 5, 'followlocation' => true, 'maxredirs' => 3));
         * // ... see above, no need to close handle and remove file if unsuccessful
         * </code>
         *
         * @param string $url
         * @param array|null $params key-value pairs to be added to $url as query string
         * @param array $options request options. Must include either 'file' or 'filepath'
         * @return bool|string true on success or error string on failure
         */
        public function download_one($url, $params, $options = array()) {
            $options['CURLOPT_HTTPGET'] = 1;
            if (!empty($params)) {
                $url .= (stripos($url, '?') !== false) ? '&' : '?';
                $url .= http_build_query($params, '', '&');
            }
            if (!empty($options['filepath']) && empty($options['file'])) {
                // open file
                if (!($options['file'] = fopen($options['filepath'], 'w'))) {
                    $this->errno = 100;
                    return get_string('cannotwritefile', 'error', $options['filepath']);
                }
                $filepath = $options['filepath'];
            }
            unset($options['filepath']);
            $result = $this->request($url, $options);
            if (isset($filepath)) {
                fclose($options['file']);
                if ($result !== true) {
                    unlink($filepath);
                }
            }
            return $result;
        }

        /**
         * HTTP PUT method
         *
         * @param string $url
         * @param array $params
         * @param array $options
         * @return bool
         */
        public function put($url, $params = array(), $options = array()) {
            $file = $params['file'];
            if (!is_file($file)) {
                return null;
            }
            $fp   = fopen($file, 'r');
            $size = filesize($file);
            $options['CURLOPT_PUT']        = 1;
            $options['CURLOPT_INFILESIZE'] = $size;
            $options['CURLOPT_INFILE']     = $fp;
            if (!isset($this->options['CURLOPT_USERPWD'])) {
                $this->setopt(array('CURLOPT_USERPWD'=>'anonymous: noreply@moodle.org'));
            }
            $ret = $this->request($url, $options);
            fclose($fp);
            return $ret;
        }

        /**
         * HTTP DELETE method
         *
         * @param string $url
         * @param array $param
         * @param array $options
         * @return bool
         */
        public function delete($url, $param = array(), $options = array()) {
            $options['CURLOPT_CUSTOMREQUEST'] = 'DELETE';
            if (!isset($options['CURLOPT_USERPWD'])) {
                $options['CURLOPT_USERPWD'] = 'anonymous: noreply@moodle.org';
            }
            $ret = $this->request($url, $options);
            return $ret;
        }

        /**
         * HTTP TRACE method
         *
         * @param string $url
         * @param array $options
         * @return bool
         */
        public function trace($url, $options = array()) {
            $options['CURLOPT_CUSTOMREQUEST'] = 'TRACE';
            $ret = $this->request($url, $options);
            return $ret;
        }

        /**
         * HTTP OPTIONS method
         *
         * @param string $url
         * @param array $options
         * @return bool
         */
        public function options($url, $options = array()) {
            $options['CURLOPT_CUSTOMREQUEST'] = 'OPTIONS';
            $ret = $this->request($url, $options);
            return $ret;
        }

        /**
         * Get curl information
         *
         * @return string
         */
        public function get_info() {
            return $this->info;
        }

        /**
         * Get curl error code
         *
         * @return int
         */
        public function get_errno() {
            return $this->errno;
        }

        /**
         * When using a proxy, an additional HTTP response code may appear at
         * the start of the header. For example, when using https over a proxy
         * there may be 'HTTP/1.0 200 Connection Established'. Other codes are
         * also possible and some may come with their own headers.
         *
         * If using the return value containing all headers, this function can be
         * called to remove unwanted doubles.
         *
         * Note that it is not possible to distinguish this situation from valid
         * data unless you know the actual response part (below the headers)
         * will not be included in this string, or else will not 'look like' HTTP
         * headers. As a result it is not safe to call this function for general
         * data.
         *
         * @param string $input Input HTTP response
         * @return string HTTP response with additional headers stripped if any
         */
        public static function strip_double_headers($input) {
            // I have tried to make this regular expression as specific as possible
            // to avoid any case where it does weird stuff if you happen to put
            // HTTP/1.1 200 at the start of any line in your RSS file. This should
            // also make it faster because it can abandon regex processing as soon
            // as it hits something that doesn't look like an http header. The
            // header definition is taken from RFC 822, except I didn't support
            // folding which is never used in practice.
            $crlf = "\r\n";
            return preg_replace(
                    // HTTP version and status code (ignore value of code).
                    '~^HTTP/1\..*' . $crlf .
                    // Header name: character between 33 and 126 decimal, except colon.
                    // Colon. Header value: any character except \r and \n. CRLF.
                    '(?:[\x21-\x39\x3b-\x7e]+:[^' . $crlf . ']+' . $crlf . ')*' .
                    // Headers are terminated by another CRLF (blank line).
                    $crlf .
                    // Second HTTP status code, this time must be 200.
                    '(HTTP/1.[01] 200 )~', '$1', $input);
        }
    }

    function get_in_or_equal($values) {
        if (sizeof($values) == 1) {
            return "= {$values[0]}";
        }
        $new_values = array();
        foreach ($values as $v) {
            $new_values[] = '"'.$v.'"';
        }
        return 'IN (' . implode(",", $new_values) . ')';
    }


}
