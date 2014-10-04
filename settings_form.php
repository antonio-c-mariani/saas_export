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

defined('MOODLE_INTERNAL') || die();
require_once("{$CFG->libdir}/formslib.php");
require_once("./classes/saas.php");

class saas_export_settings_form extends moodleform {

    function definition() {
        global $DB, $CFG, $OUTPUT, $saas;

        $syscontext = saas::get_context_system();
        $may_config = has_capability('report/saas_export:config', $syscontext);

        if($may_config) {
            $attributes = array();
            $text_attr = array('size' => 50);
        } else {
            $attributes['disabled'] = 'disabled';
            $text_attr['disabled'] = 'disabled';
        }

        $mform = $this->_form;
        $saas = new saas();

        // ----------------------------------------------------------------------------------------------
        $mform->addElement('header', 'saas_settings', get_string('saas_settings', 'report_saas_export'));

        $mform->addElement('text', 'ws_url', get_string('ws_url', 'report_saas_export'), $text_attr);
        $mform->setDefault('ws_url', 'http://saas.ufsc.br/service');
        $mform->addHelpButton('ws_url', 'ws_url', 'report_saas_export');
        $mform->setType('ws_url', PARAM_RAW);

        $mform->addElement('text', 'api_key', get_string('api_key', 'report_saas_export'), $text_attr);
        $mform->addHelpButton('api_key', 'api_key', 'report_saas_export');
        $mform->setType('api_key', PARAM_RAW);


        if(isset($saas->config->nome_instituicao)) {
            $mform->addElement('static', 'nome_instituicao', get_string('nome_instituicao', 'report_saas_export'));
        }

        // ----------------------------------------------------------------------------------------------
        $mform->addElement('header', 'course_settings', get_string('course_settings', 'report_saas_export'));

        $course_name_options = array('shortname' => get_string('shortname'),
                                     'fullname'  => get_string('fullname'),
                                     'idnumber'  => get_string('idnumber') );
        $mform->addElement('select', 'course_name_default', get_string('course_name_default', 'report_saas_export'), $course_name_options, $attributes);
        $mform->setDefault('course_name_default', 'fullname');
        $mform->addHelpButton('course_name_default', 'course_name_default', 'report_saas_export');

        $course_mapping_options = array('one_to_one'  => get_string('one_to_one', 'report_saas_export'),
                                        'one_to_many' => get_string('one_to_many', 'report_saas_export'),
                                        'many_to_one' => get_string('many_to_one', 'report_saas_export'));
        $mform->addElement('select', 'course_mapping', get_string('course_mapping', 'report_saas_export'), $course_mapping_options, $attributes);
        $mform->setDefault('course_mapping', 'one_to_one');
        $mform->addHelpButton('course_mapping', 'course_mapping', 'report_saas_export');

        $polo_mapping_options = array('no_polo'          => get_string('no_polo', 'report_saas_export'),
                                      'group_to_polo'    => get_string('group_to_polo', 'report_saas_export'),
                                      'category_to_polo' => get_string('category_to_polo', 'report_saas_export'),
                                      'course_to_polo'   => get_string('course_to_polo', 'report_saas_export'));
        $mform->addElement('select', 'polo_mapping', get_string('polo_mapping', 'report_saas_export'), $polo_mapping_options, $attributes);
        $mform->setDefault('polo_mapping', 'group_to_polo');
        $mform->addHelpButton('polo_mapping', 'polo_mapping', 'report_saas_export');

        // ----------------------------------------------------------------------------------------------
        $mform->addElement('header', 'user_settings', get_string('user_settings', 'report_saas_export'));

        $userid_options = array('username' => get_string('username'),
                                'idnumber' => get_string('idnumber') );
        $userid_options = array_merge($userid_options, $saas->get_user_info_fields());
        $mform->addElement('select', 'userid_field', get_string('userid_field', 'report_saas_export'), $userid_options, $attributes);
        $mform->setDefault('userid_field', 'fullname');
        $mform->addHelpButton('userid_field', 'userid_field', 'report_saas_export');

        $mform->addElement('checkbox', 'filter_userid_field', get_string('filter_userid_field','report_saas_export'), null, $attributes);
        $mform->addHelpButton('filter_userid_field', 'filter_userid_field', 'report_saas_export');

        $name_options = array('firstname'         => get_string('firstname'),
                              'lastname'          => get_string('lastname'),
                              'firstnamelastname' => get_string('firstname') .'+'. get_string('lastname'));

        $mform->addElement('select', 'name_field_teacher', get_string('name_field_teacher', 'report_saas_export'), $name_options, $attributes);
        $mform->setDefault('name_field_teacher', 'firstnamelastname');
        $mform->addHelpButton('name_field_teacher', 'name_field_teacher', 'report_saas_export');

        $mform->addElement('select', 'name_field_student', get_string('name_field_student', 'report_saas_export'), $name_options, $attributes);
        $mform->setDefault('name_field_student', 'firstnamelastname');
        $mform->addHelpButton('name_field_student', 'name_field_student', 'report_saas_export');

        $mform->addElement('select', 'name_field_tutor_polo', get_string('name_field_tutor_polo', 'report_saas_export'), $name_options, $attributes);
        $mform->setDefault('name_field_tutor_polo', 'firstnamelastname');
        $mform->addHelpButton('name_field_tutor_polo', 'name_field_tutor_polo', 'report_saas_export');

        $mform->addElement('select', 'name_field_tutor_inst', get_string('name_field_tutor_inst', 'report_saas_export'), $name_options, $attributes);
        $mform->setDefault('name_field_tutor_inst', 'firstnamelastname');
        $mform->addHelpButton('name_field_tutor_inst', 'name_field_tutor_inst', 'report_saas_export');

        $mform->addElement('text', 'name_regexp', get_string('name_regexp', 'report_saas_export'), $text_attr);
        $mform->setDefault('name_regexp', '');
        $mform->addHelpButton('name_regexp', 'name_regexp', 'report_saas_export');
        $mform->setType('name_regexp', PARAM_TEXT);

        // ----------------------------------------------------------------------------------------------
        $mform->addElement('header', 'cpf_settings', get_string('cpf_settings', 'report_saas_export'));

        $cpf_options = array(''         => get_string('none'),
                             'username' => get_string('username'),
                             'idnumber' => get_string('idnumber'),
                             'lastname' => get_string('lastname') );
        $cpf_options = array_merge($cpf_options, $saas->get_user_info_fields());

        $mform->addElement('select', 'cpf_field_teacher', get_string('cpf_field_teacher', 'report_saas_export'), $cpf_options, $attributes);
        $mform->setDefault('cpf_field_teacher', 'none');
        $mform->addHelpButton('cpf_field_teacher', 'cpf_field_teacher', 'report_saas_export');

        $mform->addElement('select', 'cpf_field_student', get_string('cpf_field_student', 'report_saas_export'), $cpf_options, $attributes);
        $mform->setDefault('cpf_field_student', 'none');
        $mform->addHelpButton('cpf_field_student', 'cpf_field_student', 'report_saas_export');

        $mform->addElement('select', 'cpf_field_tutor_polo', get_string('cpf_field_tutor_polo', 'report_saas_export'), $cpf_options, $attributes);
        $mform->setDefault('cpf_field_tutor_polo', 'none');
        $mform->addHelpButton('cpf_field_tutor_polo', 'cpf_field_tutor_polo', 'report_saas_export');

        $mform->addElement('select', 'cpf_field_tutor_inst', get_string('cpf_field_tutor_inst', 'report_saas_export'), $cpf_options, $attributes);
        $mform->setDefault('cpf_field_tutor_inst', 'none');
        $mform->addHelpButton('cpf_field_tutor_inst', 'cpf_field_tutor_inst', 'report_saas_export');

        $mform->addElement('text', 'cpf_regexp', get_string('cpf_regexp', 'report_saas_export'), $text_attr);
        $mform->setDefault('cpf_regexp', '|([0-9]+)|');
        $mform->addHelpButton('cpf_regexp', 'cpf_regexp', 'report_saas_export');
        $mform->setType('cpf_regexp', PARAM_TEXT);

        // ----------------------------------------------------------------------------------------------
        $mform->addElement('header', 'role_settings', get_string('role_settings', 'report_saas_export'));

        $student_roles_menu = array(0=>get_string('none')) + $saas->get_student_roles_menu();
        $other_roles_menu = array(0=>get_string('none')) + $saas->get_other_roles_menu();

        $select_teacher =& $mform->addElement('select', 'roles_teacher', get_string('roles_teacher', 'report_saas_export'), $other_roles_menu, $attributes);
        $select_teacher->setMultiple(true);
        $select_teacher->setSize(5);
        $teacher_id = $DB->get_field('role', 'id', array('shortname'=>'editingteacher'));
        $mform->setDefault('roles_teacher', $teacher_id);
        $mform->addHelpButton('roles_teacher', 'roles_teacher', 'report_saas_export');

        $select_student =& $mform->addElement('select', 'roles_student', get_string('roles_student', 'report_saas_export'), $student_roles_menu, $attributes);
        $select_student->setMultiple(true);
        $select_student->setSize(3);
        $mform->addHelpButton('roles_student', 'roles_student', 'report_saas_export');

        $select_tutor_polo =& $mform->addElement('select', 'roles_tutor_polo', get_string('roles_tutor_polo', 'report_saas_export'), $other_roles_menu, $attributes);
        $select_tutor_polo->setMultiple(true);
        $select_tutor_polo->setSize(5);
        $mform->addHelpButton('roles_tutor_polo', 'roles_tutor_polo', 'report_saas_export');

        $select_tutor_inst =& $mform->addElement('select', 'roles_tutor_inst', get_string('roles_tutor_inst', 'report_saas_export'), $other_roles_menu, $attributes);
        $select_tutor_inst->setMultiple(true);
        $select_tutor_inst->setSize(5);
        $mform->addHelpButton('roles_tutor_inst', 'roles_tutor_inst', 'report_saas_export');

        $mform->addElement('header', 'suspended_settings', get_string('suspended_settings', 'report_saas_export'));
        $mform->addElement('checkbox', 'suspended_as_evaded', get_string('suspended_as_evaded','report_saas_export'), null, $attributes);
        $mform->addHelpButton('suspended_as_evaded', 'suspended_as_evaded', 'report_saas_export');

        $this->set_data($saas->config);

        if($may_config) {
            $this->add_action_buttons();
        }
    }

    public function validation($data, $files) {
        global $saas;

        $errors = parent::validation($data, $files);

        $ws_url = trim($data['ws_url'], ' /');
        if(empty($ws_url) || filter_var($ws_url , FILTER_VALIDATE_URL) === false) {
            $errors['ws_url'] = 'Inválida ou não informada.';
        }

        $api_key = trim($data['api_key']);
        if(empty($api_key) || !preg_match('|^[0-9]{15,30}$|', $api_key) ) {
            $errors['api_key'] = 'Inválida ou não informada.';
        }

        $roles = array();
        foreach(saas::$role_types AS $r=>$rname) {
            $role = 'roles_' . $r;
            if(isset($data[$role])) {
                foreach($data[$role] AS $roleid) {
                    if(!empty($roleid) && isset($roles[$roleid])) {
                        $errors[$role] = get_string('duplicated_role', 'report_saas_export');
                    }
                    $roles[$roleid] = true;
                }
            }
        }
        return $errors;
    }
}
