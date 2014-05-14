<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version info
 *
 * @package    report
 * @subpackage saas-export
 * @copyright  2014 Caio Doneda and Daniel Neis
 */
require_once($CFG->libdir.'/environmentlib.php');

$current_version = normalize_version(get_config('', 'release'));

if (version_compare($current_version, '2.0', '>=')) {
    require_once($CFG->dirroot . '/report/saas_export/lib.php');
    $ADMIN->add('reports', new admin_externalpage('report_saas_export', get_string('pluginname', 'report_saas_export'), "$CFG->wwwroot/report/saas_export/index.php",'report/saas_export:view'));
} else {
    require_once($CFG->dirroot . '/admin/report/saas_export/lib.php');
    $ADMIN->add('reports', new admin_externalpage('report_saas_export', get_string('pluginname', 'report_saas_export'), "$CFG->wwwroot/admin/report/saas_export/index.php",'report/saas_export:view'));
    $settings = new admin_settingpage('saas_export_settings', get_string('settings', 'report_saas_export'));
}
$settings->add(new admin_setting_configtext('saas_export/ws_url',
                                            get_string('ws_url', 'report_saas_export'),
                                            get_string('desc_ws_url', 'report_saas_export'),
                                            'http://saas.../servico', PARAM_TEXT));

$settings->add(new admin_setting_configtext('saas_export/api_key',
                                            get_string('api_key', 'report_saas_export'),
                                            get_string('desc_api_key', 'report_saas_export'),
                                            '', PARAM_TEXT));

$course_name_options = array('shortname'=>'shortname', 'fullname'=>'fullname', 'idnumber'=>'idnumber');
$settings->add(new admin_setting_configselect('saas_export/course_name_default',
                                            get_string('course_name_default', 'report_saas_export'),
                                            get_string('desc_course_name_default', 'report_saas_export'),
                                            "fullname", $course_name_options));

$id_options = array('username'=>'username', 'idnumber'=>'idnumber');
$settings->add(new admin_setting_configselect('saas_export/user_id_field',
                                            get_string('user_id_field', 'report_saas_export'),
                                            get_string('desc_user_id_field', 'report_saas_export'),
                                            "username", $id_options));

$name_options = array('firstname'=>get_string('firstname'), 'lastname'=>get_string('lastname'),
                      'firstnamelastname' => get_string('firstname') .'+'. get_string('lastname'));

$settings->add(new admin_setting_configselect('saas_export/name_teacher_field',
                                            get_string('name_field', 'report_saas_export','professores'),
                                            get_string('desc_name_field', 'report_saas_export', 'professores'),
                                            'firstnamelastname', $name_options));

$settings->add(new admin_setting_configselect('saas_export/name_student_field',
                                            get_string('name_field', 'report_saas_export','alunos'),
                                            get_string('desc_name_field', 'report_saas_export', 'alunos'),
                                            'firstnamelastname', $name_options));

$settings->add(new admin_setting_configselect('saas_export/name_tutor_field',
                                            get_string('name_field', 'report_saas_export','tutores'),
                                            get_string('desc_name_field', 'report_saas_export', 'tutores'),
                                            'firstnamelastname', $name_options));

$cpf_options = array('username'=>'username', 'idnumber'=>'idnumber', 'lastname'=>'lastname', 'none'=>get_string('none'));
$cpf_options = array_merge($cpf_options, saas_export_get_user_custom_fields());

$settings->add(new admin_setting_configselect('saas_export/cpf_teacher_field',
                                            get_string('cpf_field', 'report_saas_export','professores'),
                                            get_string('desc_cpf_field', 'report_saas_export', 'professores'),
                                            'none', $cpf_options));

$settings->add(new admin_setting_configselect('saas_export/cpf_tutor_field',
                                            get_string('cpf_field', 'report_saas_export', 'tutores'),
                                            get_string('desc_cpf_field', 'report_saas_export', 'tutores'),
                                            'idnumber', $cpf_options));

$settings->add(new admin_setting_configselect('saas_export/cpf_student_field',
                                            get_string('cpf_field', 'report_saas_export', 'alunos'),
                                            get_string('desc_cpf_field', 'report_saas_export', 'alunos'),
                                            'idnumber', $cpf_options));

if (version_compare($current_version, '2.0', '>=')) {
    $context = context_system::instance();
} else {
    $context = get_context_instance(CONTEXT_SYSTEM);
}
if (isset($CFG->gradebookroles)) {
    
    if (version_compare($current_version, '2.0', '>=')) {
        $roles = get_all_roles($context);
    } else {
        $roles = get_records_menu('role', null, 'name', 'id, name');
    }
    $role_names = role_fix_names($roles, $context);    
    
    $student_roles = $CFG->gradebookroles;
    $student_roles_choices = array();
    $student_roles_labels = array();
    $roles_to_hide = $student_roles .',1,6,7,8';//admin, guest ids.
    $roles_labels = array();
    if (version_compare($current_version, '2.0', '>=')) {
        $sql_students = "SELECT *
                           FROM {role}
                          WHERE id IN ({$student_roles})";
        $roles_st = $DB->get_records_sql($sql_students);
        foreach ($roles_st as $r) {
            $student_roles_labels[$r->id] = $role_names[$r->id]->localname;
        }

        $sql = "SELECT *
                  FROM {role}
                 WHERE id NOT IN ({$roles_to_hide})";
        $roles = $DB->get_records_sql($sql);

        foreach ($roles as $r) {
            $roles_labels[$r->id] = $role_names[$r->id]->localname;
        }

        $id_teacher = $DB->get_field('role', 'id', array('shortname'=>'editingteacher'));
        $id_student = $DB->get_field('role', 'id', array('shortname'=>'student'));

    } else {
        $sql_students = "SELECT *
                           FROM {$CFG->prefix}role
                          WHERE id IN ({$student_roles})";
        $roles_st = get_records_sql($sql_students);
        foreach ($roles_st as $r) {
            $student_roles_labels[$r->id] = $role_names[$r->id];
        }

        $sql = "SELECT *
                  FROM {$CFG->prefix}role
                 WHERE id NOT IN ({$roles_to_hide})";
        $roles = get_records_sql($sql);

        foreach ($roles as $r) {
            $roles_labels[$r->id] = $role_names[$r->id];
        }

        $id_teacher = get_field('role', 'id', 'shortname', 'editingteacher');
        $id_student = get_field('role', 'id', 'shortname', 'student');
    }

    $settings->add(new admin_setting_configmultiselect('saas_export/teacher_role',
                                                get_string('user_role', 'report_saas_export', 'professores'),
                                                get_string('desc_user_role', 'report_saas_export', 'professores'),
                                                array($id_teacher), $roles_labels));

    $settings->add(new admin_setting_configmultiselect('saas_export/tutor_role',
                                                get_string('user_role', 'report_saas_export', 'tutores a distância'),
                                                get_string('desc_user_role', 'report_saas_export', 'tutores a distância'),
                                                array(''), $roles_labels));

    $settings->add(new admin_setting_configmultiselect('saas_export/tutor_polo_role',
                                                get_string('user_role', 'report_saas_export', 'tutores presenciais (polo)'),
                                                get_string('desc_user_role', 'report_saas_export', 'tutores presenciais (polo)'),
                                                array(''), $roles_labels));
    
    $settings->add(new admin_setting_configmultiselect('saas_export/student_role',
                                                get_string('user_role', 'report_saas_export', 'alunos'),
                                                get_string('desc_user_role', 'report_saas_export', 'alunos'),
                                                array($id_student), $student_roles_labels));

}
if (version_compare($current_version, '2.0', '<')) {
    $ADMIN->add('reports', $settings, 0);
}
