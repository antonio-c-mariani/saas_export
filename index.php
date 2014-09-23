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
 *
 * @package    report
 * @subpackage saas_export
 */

if (strpos(__FILE__, '/admin/report/') !== false) {
    require('../../../config.php');
} else {
    require('../../config.php');
}

require_once($CFG->libdir . '/adminlib.php');
require_once('./classes/saas.php');
require_once('./polo_form.php');
require_once('./oferta_form.php');
require_once('./locallib.php');

require_login();
$syscontext = saas::get_context_system();
require_capability('report/saas_export:view', $syscontext);
admin_externalpage_setup('report_saas_export', '', null, '', array('pagelayout'=>'report'));

$baseurl = new moodle_url('index.php');

$saas = new saas();

$polo_mapping = $saas->get_config('polo_mapping');
$may_export = has_capability('report/saas_export:export', $syscontext);

$tab_items = array('guidelines', 'settings');
if($saas->is_configured()) {
    $tab_items[] = 'saas_data';
    $tab_items[] = 'course_mapping';
    if($polo_mapping != 'no_polo') {
        $tab_items[] = 'polo_mapping';
    }
    $tab_items[] = 'overview';
    if($may_export) {
        $tab_items[] = 'export';
    }
}

$tabs = array();
foreach($tab_items AS $act) {
    $tabs[$act] = new tabobject($act, new moodle_url('index.php', array('action'=>$act)), get_string($act, 'report_saas_export'));
}

$action = optional_param('action', 'guidelines' , PARAM_TEXT);
$action = isset($tabs[$action]) ? $action : 'guidelines';

switch ($action) {
    case 'guidelines':
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);
        print html_writer::start_tag('DIV', array('class'=>'saas_area_large'));
        print $OUTPUT->heading('Exportação de dados do Moodle para SAAS', 3);
        include('orientacoes.html');
        print html_writer::end_tag('DIV');
        echo $OUTPUT->footer();
        break;
    case 'settings':
        require_once('./settings_form.php');
        $baseurl->param('action', 'settings');
        $mform = new saas_export_settings_form($baseurl);

        if(has_capability('report/saas_export:config', $syscontext)) {
            if ($mform->is_cancelled()) {
                redirect($baseurl);
            } else if ($data = $mform->get_data()) {
                $saas->save_settings($data);
                $saas->verify_config($baseurl);
                redirect($baseurl);
            }
        }

        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);

        if(!has_capability('report/saas_export:config', $syscontext)) {
            print $OUTPUT->heading(get_string('no_permission_to_config', 'report_saas_export'), 4);
        }

        $mform->display();
        echo $OUTPUT->footer();
        break;
    case 'saas_data':
        $saas->load_saas_data();

        $saas_data_tab_items = array('ofertas'    => false,
                                     'add_oferta' => 'report/saas_export:export'
                                     );
        if($polo_mapping != 'no_polo') {
            $saas_data_tab_items['polos'] = false;
            $saas_data_tab_items['add_polo'] = 'report/saas_export:export';
        }

        $saas_data_tabs = array();
        foreach($saas_data_tab_items AS $act=>$capability) {
            if(!$capability || has_capability($capability, $syscontext)) {
                $url = clone($baseurl);
                $url->param('action', $action);
                $url->param('subaction', $act);
                $saas_data_tabs[$act] = new tabobject($act, $url, get_string($act, 'report_saas_export'));
            }
        }
        $saas_data_action = optional_param('subaction', 'ofertas' , PARAM_TEXT);
        $saas_data_action = isset($saas_data_tabs[$saas_data_action]) ? $saas_data_action : 'ofertas';

        $url = clone($baseurl);
        $url->param('action', $action);
        $url->param('subaction', $saas_data_action);

        if(has_capability('report/saas_export:export', $syscontext)) {
            switch($saas_data_action) {
                case 'add_oferta':
                    $oferta_form = new oferta_form($url, array('saas'=>$saas));
                    if ($oferta_form->is_cancelled()) {
                        redirect($url);
                    } else if ($oferta = $oferta_form->get_data()) {
                        $saas->send_oferta_disciplina($oferta);
                        $saas->load_ofertas_disciplinas_saas();
                        $url->param('subaction', 'ofertas');
                        $url->param('reload', 0);
                        redirect($url);
                    }
                    break;
                case 'add_polo':
                    $polo_form = new polo_form($url);
                    if ($polo_form->is_cancelled()) {
                        redirect($url);
                    } else if ($polo = $polo_form->get_data()) {
                        $saas->send_polo($polo);
                        $saas->load_polos_saas();
                        $url->param('subaction', 'polos');
                        redirect($url);
                    }
                    break;
            }
        }

        echo $OUTPUT->header();

        print_tabs(array($tabs), $action);
        print_tabs(array($saas_data_tabs), $saas_data_action);

        switch($saas_data_action) {
            case 'ofertas':
                if(optional_param('reload', true, PARAM_INT)) {
                    $saas->load_saas_data(true);
                }
                saas_show_table_ofertas_curso_disciplinas(0, false);
                break;
            case 'add_oferta':
                print html_writer::start_tag('DIV', array('align'=>'center'));
                print $OUTPUT->box_start('generalbox boxwidthnormal');
                if($DB->record_exists('saas_ofertas_cursos', array('enable'=>1))) {
                    print $OUTPUT->heading(get_string('add_oferta', 'report_saas_export'), 3);
                    $oferta_form->display();
                } else {
                    print html_writer::tag('h3', get_string('no_ofertas_cursos', 'report_saas_export'));
                }
                print $OUTPUT->box_end();
                print html_writer::end_tag('DIV');
                break;
            case 'polos':
                $saas->load_polos_saas();
                saas_show_table_polos();
                break;
            case 'add_polo':
                if(has_capability('report/saas_export:export', $syscontext)) {
                    print html_writer::start_tag('DIV', array('align'=>'center'));
                    print $OUTPUT->box_start('generalbox boxwidthnormal');
                    print $OUTPUT->heading(get_string('add_polo', 'report_saas_export'), 3);
                    $polo_form->display();
                    print $OUTPUT->box_end();
                    print html_writer::end_tag('DIV');
                }
                break;
        }

        echo $OUTPUT->footer();
        break;
    case 'course_mapping':
        $may_export = has_capability('report/saas_export:export', $syscontext);
        $subaction = optional_param('subaction', '', PARAM_TEXT);
        if($may_export) {
            if($subaction == 'map') {
                $odid = required_param('odid', PARAM_INT);
                $group_map_id = required_param('group_map_id', PARAM_INT);
                $od = $DB->get_record('saas_ofertas_disciplinas', array('id'=>$odid), 'id, group_map_id, oferta_curso_uid', MUST_EXIST);
                if($group_map_id == -1) {
                    $max = $DB->get_field_sql("SELECT MAX(group_map_id) FROM {saas_ofertas_disciplinas}");
                    $od->group_map_id = empty($max) ? 1 : $max+1;
                } else {
                    $od->group_map_id = $group_map_id;
                }
                $DB->update_record('saas_ofertas_disciplinas', $od);
                $ocid = $DB->get_field('saas_ofertas_cursos', 'id', array('uid'=>$od->oferta_curso_uid), MUST_EXIST);
                redirect(new moodle_url('index.php', array('action'=>'course_mapping', 'ocid'=>$ocid)));
            } else if($subaction == 'delete') {
                $courseid = required_param('courseid', PARAM_INT);
                $group_map_id = required_param('group_map_id', PARAM_INT);
                $ocid = required_param('ocid', PARAM_INT);
                $DB->delete_records('saas_map_course', array('courseid' => $courseid, 'group_map_id' => $group_map_id));
                redirect(new moodle_url('index.php', array('action'=>'course_mapping', 'ocid'=>$ocid)));
            }
        }
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);

        include('course_mapping.php');

        echo $OUTPUT->footer();
        break;
    case 'polo_mapping':
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);

        if($DB->record_exists('saas_polos', array('enable'=>1))) {
            $polo_mapping_type = $saas->get_config('polo_mapping');
            switch ($polo_mapping_type) {
                case 'no_polo':
                    print $OUTPUT->heading(get_string('title_no_polo', 'report_saas_export'), 4);
                    break;
                case 'group_to_polo':
                    include('map_groups_to_polos.php');
                    break;
                case 'category_to_polo':
                    include('map_categories_to_polos.php');
                    break;
                case 'course_to_polo':
                    include('map_courses_to_polos.php');
                    break;
                default:
                    print $OUTPUT->heading('Mapeamento ainda não implementado: ' . $polo_mapping_type, 4);
            }
        } else {
            print $OUTPUT->heading(get_string('no_polos', 'report_saas_export'), 4);
        }

        echo $OUTPUT->footer();
        break;
    case 'overview':
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);

        $saas_data_tab_items = array('ofertas');
        if($polo_mapping != 'no_polo') {
            $saas_data_tab_items[] = 'polos';
        }
        $saas_data_tabs = array();
        foreach($saas_data_tab_items AS $act) {
            $url = clone($baseurl);
            $url->param('action', $action);
            $url->param('subaction', $act);
            $saas_data_tabs[$act] = new tabobject($act, $url, get_string($act, 'report_saas_export'));
        }
        $saas_data_action = optional_param('subaction', 'ofertas' , PARAM_TEXT);
        $saas_data_action = isset($saas_data_tabs[$saas_data_action]) ? $saas_data_action : 'ofertas';
        print_tabs(array($saas_data_tabs), $saas_data_action);

        if($saas_data_action == 'ofertas') {
            $ocid = optional_param('ocid', 0 , PARAM_INT);
            $url = new moodle_url('index.php', array('action'=>'overview', 'subaction'=>'ofertas'));
            saas_show_menu_ofertas_cursos($ocid, $url);
            if($odid = optional_param('odid', 0 , PARAM_INT)) {
                saas_show_users_oferta_disciplina($odid);
            } else {
                saas_show_table_ofertas_curso_disciplinas($ocid, true);
            }
        } else {
            $ocid = optional_param('ocid', 0 , PARAM_INT);
            $poloid = optional_param('poloid', 0 , PARAM_INT);
            saas_show_overview_polos($ocid, $poloid);
        }

        echo $OUTPUT->footer();
        break;
    case 'export':
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);

        if(has_capability('report/saas_export:export', $syscontext)) {
            $ocs = isset($_POST['oc']) ? $_POST['oc'] : array();
            $ods = isset($_POST['od']) ? $_POST['od'] : array();
            $polos = isset($_POST['polo']) ? $_POST['polo'] : array();
            $baseurl->param('action', $action);
            if(optional_param('export', false, PARAM_TEXT)) {
                $send_user_details = optional_param('send_user_details', false, PARAM_BOOL);

                $exception_msg = false;
                try {
                    list($count_errors, $errors) = $saas->send_data($ocs, $ods, $polos, $send_user_details);
                } catch (dml_exception $e){
                    $debuginfo = empty($e->debuginfo) ? '' : '<BR>'.$e->debuginfo;
                    $exception_msg = get_string('bd_error', 'report_saas_export', $e->getMessage() . $debuginfo);
                } catch (Exception $e){
                    $exception_msg = get_string('ws_error', 'report_saas_export', $e->getMessage());
                }

                print html_writer::start_tag('DIV', array('align'=>'center'));
                print $OUTPUT->box_start('generalbox boxwidthormal');

                $report_url = $saas->make_ws_url('moodle/relatorioDeExportacao');
                if($exception_msg) {
                    print html_writer::tag('SPAN', $exception_msg, array('class'=>'saas_export_error'));
                } else if($count_errors == 0) {
                    print html_writer::tag('SPAN', get_string('export_ok', 'report_saas_export', $report_url), array('class'=>'saas_export_message'));
                } else {
                    $a = new stdClass();
                    $a->report_url = $report_url;
                    $a->errors = $count_errors;
                    print html_writer::tag('SPAN', get_string('export_errors', 'report_saas_export', $a), array('class'=>'saas_export_error'));
                }
                print $OUTPUT->box_end();
                print html_writer::end_tag('DIV');
                saas_show_export_options($baseurl, $ocs, $ods, $polos);
            } else {
                saas_show_export_options($baseurl);
            }
        } else {
            print_error('no_permission_to_export', 'report_saas_export');
        }
        echo $OUTPUT->footer();
        break;
    default:
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);
        print $OUTPUT->box_start('generalbox boxwidthormal');
        print $OUTPUT->heading('Ainda estamos trabalhando. Disponível em breve ...', 4);
        print $OUTPUT->box_end();
        print $OUTPUT->footer();
}
