<?php

if (strpos(__FILE__, '/admin/report/') !== false) {
    require(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
} else {
    require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
}

require_once($CFG->dirroot.'/group/lib.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

if (file_exists($CFG->dirroot.'/lib/coursecatlib.php')) {
    require_once($CFG->dirroot.'/lib/coursecatlib.php');
}

function saas_create_user($username, $name, $update_user_data=false) {
    global $DB, $CFG;

    if (!$user = $DB->get_record('user', array('username'=>$username), 'id, username, mnethostid')) {
        $user = new stdClass();
        $user->username   = $username;
        $user->mnethostid = $CFG->mnet_localhost_id;
    }

    $user->confirmed  = 1;
    $user->lang       = $CFG->lang;
    list($firstname, $lastname) = explode(' ', $name, 2);
    $user->firstname  = $firstname;
    $user->lastname   = $lastname;
    $user->email      = $username . '@moodle.org';
    $user->password   = $username;

    if (isset($user->id)) {
        if ($update_user_data) {
            user_update_user($user, true, false);
        }
    } else {
        $user->id = user_create_user($user, true, false);
    }

    return $user->id;

}

function saas_create_category($catname, $parent_catid=0) {
    global $DB;

    if (empty($parent_catid)) {
        $parentid = 0;
    } else if (is_numeric($parent_catid)) {
        $parentid = $parent_catid;
        if (!$DB->record_exists('course_categories', array('id'=>$parent_catid))) {
            throw new Exception("Categoria pai não localizada: '{$parent_catid}' para: '{$catname}'");
        }
    } else {
        throw new Exception("Id. categoria pai inválida: '{$parent_catid}' para: '{$catname}'");
    }

    if ($id = $DB->get_field('course_categories', 'id', array('name'=>$catname, 'parent'=>$parentid))) {
        return $id;
    }

    $newcategory = new stdClass();
	$newcategory->name = $catname;
	$newcategory->parent = $parentid;
    if (class_exists('coursecat')) {
        $newcategory = coursecat::create($newcategory);

    } else {
        $newcategory->id = $DB->insert_record('course_categories', $newcategory);
        $newcategory->context = get_context_instance(CONTEXT_COURSECAT, $newcategory->id);
        $categorycontext = $newcategory->context;
        mark_context_dirty($newcategory->context->path);
    }
    return $newcategory->id;
}

function saas_create_course($fullname, $shortname, $catid) {
    global $DB;

    if ($id = $DB->get_field('course', 'id', array('shortname'=>$shortname))) {
        return $id;
    }

    if (!$DB->record_exists('course_categories', array('id'=>$catid))) {
        throw new Exception("Categoria desconhecida: '{$catid}' ao criar curso '{$fullname}'");
    }

    $newcourse = new stdClass();
    $newcourse->shortname = $shortname;
    $newcourse->fullname = $fullname;
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

    return $course->id;
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
    if ($id = $DB->get_field('groups', 'id', array('name'=>$groupname, 'courseid'=>$courseid))) {
        return $id;
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

    if ($id = $DB->get_field('role', 'id', array('shortname'=>$shortname))) {
        return $id;
    }

    return create_role($fullname, $shortname, '');
}

function saas_create_user_custom_field($shortname, $name, $datatype='text') {
	global $DB;

    if ($id = $DB->get_field('user_info_field', 'id', array('shortname'=>$shortname))) {
        return $id;
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
    return $DB->insert_record('user_info_field', $field);
}

function saas_user_custom_data($userid, $fieldname, $value) {
	global $DB;

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

function saas_create_assignment($shortname, $name) {
	global $DB;

    if (!$course = $DB->get_record('course', array('shortname'=>$shortname))) {
        return false;
    }

    $modulename = 'assign';
	if ($id = $DB->get_field('assign', 'id', array('course'=>$course->id, 'name'=>$name))) {
		return $id;
	}

    $moduleinfo = new stdClass();
    $moduleinfo->modulename = $modulename;
	$moduleinfo->name = $name;
    $moduleinfo->course = $course->id;
    $moduleinfo->section = 0;
    $moduleinfo->visible = 1;
    $moduleinfo->intro = "<p>{$name}</p>".
    $moduleinfo->introformat = 1;

    $moduleinfo->submissiondrafts = 0;
    $moduleinfo->requiresubmissionstatement = 0;
    $moduleinfo->sendnotifications = 0;
    $moduleinfo->sendlatenotifications = 0;
    $moduleinfo->duedate = 0;
    $moduleinfo->cutoffdate = 0;
    $moduleinfo->gradingduedate = 0;
    $moduleinfo->allowsubmissionsfromdate = 0;
    $moduleinfo->grade = 100;
    $moduleinfo->teamsubmission = 0;
    $moduleinfo->requireallteammemberssubmit = 0;
    $moduleinfo->blindmarking = 0;
    $moduleinfo->markingworkflow = 0;
    $moduleinfo->markingallocation = 0;
    $moduleinfo->cmidnumber = 0;

    $module = $DB->get_record('modules', array('name'=>$modulename), '*', MUST_EXIST);
    $moduleinfo->module = $module->id;

    $mdi =  add_moduleinfo($moduleinfo, $course, null);
    return $mdi->instance;
}
