<?php

die('Somente para testes');

define('CLI_SCRIPT', true);
error_reporting(E_ALL);

if (strpos(__FILE__, '/admin/report/') !== false) {
    require(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
} else {
    require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
}

defined('MOODLE_INTERNAL') || die('Interno');

require_once($CFG->dirroot.'/group/lib.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/user/lib.php');

if (file_exists($CFG->dirroot.'/lib/coursecatlib.php')) {
    require_once($CFG->dirroot.'/lib/coursecatlib.php');
}

saas_create_user_custom_field('cpf', 'CPF');

saas_create_category('Cursos');
saas_create_category('Curso 1', 'Cursos');
saas_create_category('Curso 2', 'Cursos');

saas_create_user('e1', 'Student 1');
saas_create_user('e2', 'Student 2');
saas_create_user('e3', 'Student 3');
saas_create_user('e4', 'Student 4');
saas_create_user('p1', 'Teacher 1');
saas_create_user('p2', 'Teacher 2');
saas_create_user('tpolo1', 'TutorPolo 1');
saas_create_user('tpolo2', 'TutorPolo 2');
saas_create_user('tinst1', 'TutorInst 1');
saas_create_user('tinst2', 'TutorInst 2');

saas_user_custom_data('e1', 'cpf', rand(11111111111, 99999999999));
saas_user_custom_data('e2', 'cpf', rand(11111111111, 99999999999));
saas_user_custom_data('e3', 'cpf', rand(11111111111, 99999999999));
saas_user_custom_data('e4', 'cpf', rand(11111111111, 99999999999));
saas_user_custom_data('p1', 'cpf', rand(11111111111, 99999999999));
saas_user_custom_data('p2', 'cpf', rand(11111111111, 99999999999));
saas_user_custom_data('tpolo1', 'cpf', rand(11111111111, 99999999999));
saas_user_custom_data('tpolo2', 'cpf', rand(11111111111, 99999999999));
saas_user_custom_data('tinst1', 'cpf', rand(11111111111, 99999999999));
saas_user_custom_data('tinst2', 'cpf', rand(11111111111, 99999999999));

saas_create_role('tutor_presencial', 'Tutor Presencial');
saas_create_role('tutor_distancia', 'Tutor a Distância');

saas_create_course('Informática Básica', 'Curso 1');
saas_create_course('Cálculo', 'Curso 1');
saas_create_course('Legislação', 'Curso 2');
saas_create_course('Algoritmos', 'Curso 2');

saas_enrol_user('e1', 'Informática Básica', 'student');
saas_enrol_user('e2', 'Informática Básica', 'student');
saas_enrol_user('p1', 'Informática Básica', 'editingteacher');
saas_enrol_user('tpolo1', 'Informática Básica', 'tutor_presencial');
saas_enrol_user('tinst1', 'Informática Básica', 'tutor_distancia');

saas_enrol_user('e1', 'Cálculo', 'student');
saas_enrol_user('e2', 'Cálculo', 'student');
saas_enrol_user('p1', 'Cálculo', 'editingteacher');
saas_enrol_user('tpolo1', 'Cálculo', 'tutor_presencial');
saas_enrol_user('tinst1', 'Cálculo', 'tutor_distancia');

saas_enrol_user('e3', 'Legislação', 'student');
saas_enrol_user('e4', 'Legislação', 'student');
saas_enrol_user('p2', 'Legislação', 'editingteacher');
saas_enrol_user('tpolo2', 'Legislação', 'tutor_presencial');
saas_enrol_user('tinst2', 'Legislação', 'tutor_distancia');

saas_enrol_user('e3', 'Algoritmos', 'student');
saas_enrol_user('e4', 'Algoritmos', 'student');
saas_enrol_user('p2', 'Algoritmos', 'editingteacher');
saas_enrol_user('tpolo2', 'Algoritmos', 'tutor_presencial');
saas_enrol_user('tinst2', 'Algoritmos', 'tutor_distancia');

saas_create_group('Jaraguá do sul', 'Informática Básica');
saas_create_group('São José', 'Informática Básica');
saas_create_group('Jaraguá do sul', 'Cálculo');
saas_create_group('São José', 'Cálculo');
saas_create_group('Escola Estadual', 'Legislação');
saas_create_group('Escola Municipal', 'Legislação');
saas_create_group('Escola Estadual', 'Algoritmos');
saas_create_group('Escola Municipal', 'Algoritmos');

saas_add_group_member('Jaraguá do sul', 'Informática Básica', 'e2');
saas_add_group_member('Jaraguá do sul', 'Informática Básica', 'tpolo1');
saas_add_group_member('São José', 'Informática Básica', 'e1');
saas_add_group_member('São José', 'Informática Básica', 'tpolo1');

saas_add_group_member('Jaraguá do sul', 'Cálculo', 'e2');
saas_add_group_member('Jaraguá do sul', 'Cálculo', 'tpolo1');
saas_add_group_member('São José', 'Cálculo', 'e1');
saas_add_group_member('São José', 'Cálculo', 'tpolo1');

saas_add_group_member('Escola Estadual', 'Legislação', 'e4');
saas_add_group_member('Escola Estadual', 'Legislação', 'tpolo2');
saas_add_group_member('Escola Municipal', 'Legislação', 'e3');
saas_add_group_member('Escola Municipal', 'Legislação', 'tpolo2');

saas_add_group_member('Escola Estadual', 'Algoritmos', 'e4');
saas_add_group_member('Escola Estadual', 'Algoritmos', 'tpolo2');
saas_add_group_member('Escola Municipal', 'Algoritmos', 'e3');
saas_add_group_member('Escola Municipal', 'Algoritmos', 'tpolo2');


function saas_create_user($username, $name) {
    global $DB, $CFG;

    if ($DB->get_field('user', 'id', array('username'=>$username))) {
        return;
    }

    $nuser = new stdClass();
    $nuser->username   = $username;
    $nuser->confirmed  = 1;
    $nuser->mnethostid = $CFG->mnet_localhost_id;
    $nuser->lang       = $CFG->lang;

    list($firstname, $lastname) = explode(' ', $name);
    $nuser->firstname  = $firstname;
    $nuser->lastname   = $lastname;
    $nuser->email      = $username . '@moodle.org';

    user_create_user($nuser, false);
}

function saas_create_category($catname, $parentname='') {
    global $DB;

    if (empty($parentname)) {
        $parentid = 0;
    } else {
        $parentid = $DB->get_field('course_categories', 'id', array('name'=>$parentname));
    }

    if ($DB->get_field('course_categories', 'id', array('name'=>$catname, 'parent'=>$parentid))) {
        return;
    }

    $newcategory = new stdClass();
	$newcategory->name = $catname;
	$newcategory->parent = $parentid;
    if (class_exists('coursecat')) {
        coursecat::create($newcategory);
    } else {
        $newcategory->id = $DB->insert_record('course_categories', $newcategory);
        $newcategory->context = get_context_instance(CONTEXT_COURSECAT, $newcategory->id);
        $categorycontext = $newcategory->context;
        mark_context_dirty($newcategory->context->path);
    }
}

function saas_create_course($coursename, $catname) {
    global $DB;

    if ($DB->get_field('course', 'id', array('shortname'=>$coursename))) {
        return;
    }

    $catid = $DB->get_field('course_categories', 'id', array('name'=>$catname));

    $newcourse = new stdClass();
    $newcourse->shortname = $coursename;
    $newcourse->fullname = $coursename;
    $newcourse->category  = $catid;
    $newcourse->visible   = '1';
    $newcourse->enrollable   = 0;
    $newcourse->startdate    = time();
    $course = create_course($newcourse);

    if (class_exists('context_course')) {
        context_course::instance($course->id);
    } else {
        get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);
    }
}

function saas_enrol_user($username, $coursename, $rolename) {
    global $DB;

    $roleid = $DB->get_field('role', 'id', array('shortname'=>$rolename));
    $userid = $DB->get_field('user', 'id', array('username'=>$username));
    $courseid = $DB->get_field('course', 'id', array('shortname'=>$coursename));

    $enrol = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', array('courseid'=>$courseid, 'enrol'=>'manual'), '*', IGNORE_MULTIPLE);
    $enrol->enrol_user($instance, $userid, $roleid, time(), 0, ENROL_USER_ACTIVE);
}

function saas_create_group($groupname, $coursename) {
    global $DB;

    $courseid = $DB->get_field('course', 'id', array('shortname'=>$coursename));
    if ($DB->get_field('groups', 'id', array('name'=>$groupname, 'courseid'=>$courseid))) {
        return;
    }

    $newgroup = new stdClass();
    $newgroup->name = $groupname;
    $newgroup->courseid = $courseid;

    return groups_create_group($newgroup);
}

function saas_add_group_member($groupname, $coursename, $username) {
    global $DB;

    $courseid = $DB->get_field('course', 'id', array('shortname'=>$coursename));
    $groupid = $DB->get_field('groups', 'id', array('name'=>$groupname, 'courseid'=>$courseid));
    $userid = $DB->get_field('user', 'id', array('username'=>$username));
    groups_add_member($groupid, $userid);
}

function saas_create_role($shortname, $fullname) {
    global $DB;

    if ($DB->get_field('role', 'id', array('shortname'=>$shortname))) {
        return;
    }

    create_role($fullname, $shortname, '');
}

function saas_create_user_custom_field($shortname, $name, $datatype='text') {
	global $DB;

    if ($DB->get_field('user_info_field', 'id', array('shortname'=>$shortname))) {
        return;
    }

	$field = new stdClass();
	$field->datatype = $datatype;
	$field->shortname = $shortname;
	$field->name = $name;
	$field->description = $name;
    $field->categoryid = 1;
    $field->descriptionformat = 1;
    $field->visible = 2;
    $field->param1 = 50;
    $field->param2 = 255;
    $DB->insert_record('user_info_field', $field);
}

function saas_user_custom_data($username, $fieldname, $value) {
	global $DB;

    $userid = $DB->get_field('user', 'id', array('username'=>$username));
    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$fieldname));

	$data = new stdClass();
    $data->userid = $userid;
    $data->fieldid = $fieldid;
    $data->data = $value;

    if ($id = $DB->get_field('user_info_data', 'id', array('userid'=>$userid, 'fieldid'=>$fieldid))) {
        $data->id = $id;
        $DB->update_record('user_info_data', $data);
    } else {
        $DB->insert_record('user_info_data', $data);
    }
}
