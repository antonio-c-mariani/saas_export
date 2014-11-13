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

$polo_mapping_type = $saas->get_config('polo_mapping');
$may_export = has_capability('report/saas_export:export', $syscontext);

$tab_items = array('guidelines', 'settings');
if($saas->is_configured()) {
    $tab_items[] = 'saas_data';
    $tab_items[] = 'course_mapping';
    if($polo_mapping_type != 'no_polo') {
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
        if($polo_mapping_type != 'no_polo') {
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

        $oferta_disciplina = false;
        if(has_capability('report/saas_export:export', $syscontext)) {
            switch($saas_data_action) {
                case 'add_oferta':
                    $ocid = optional_param('oferta_curso_id', 0, PARAM_INT);
                    $oferta_form = new oferta_form($url, array('data'=>$ocid));
                    if ($oferta_form->is_cancelled()) {
                        $url->param('subaction', 'ofertas');
                        $url->param('reload', 0);
                        redirect($url);
                    } else if ($oferta_disciplina = $oferta_form->get_data()) {
                        if(!empty($oferta_disciplina->disciplina_id)) {
                            $saas->send_oferta_disciplina($oferta_disciplina);
                            $saas->load_ofertas_disciplinas_saas();
                            $url->param('subaction', 'ofertas');
                            $url->param('reload', 0);
                            redirect($url);
                        }
                    }
                    break;
                case 'add_polo':
                    $polo_form = new polo_form($url);
                    if ($polo_form->is_cancelled()) {
                        $url->param('subaction', 'polos');
                        $url->param('reload', 0);
                        redirect($url);
                    } else if ($polo = $polo_form->get_data()) {
                        $saas->send_polo($polo);
                        $saas->load_polos_saas();
                        $url->param('subaction', 'polos');
                        $url->param('reload', 0);
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
                saas_show_nome_instituicao();
                print html_writer::tag('DIV', $OUTPUT->heading('Ofertas de cursos e de disciplinas definidas no SAAS', 3), array('align'=>'center'));
                saas_show_table_ofertas_curso_disciplinas(0, false);
                break;
            case 'add_oferta':
                if(has_capability('report/saas_export:export', $syscontext)) {
                    saas_show_nome_instituicao();
                    if($saas->has_oferta_curso()) {
                        print html_writer::tag('DIV', $OUTPUT->heading(get_string('add_oferta', 'report_saas_export'), 3), array('align'=>'center'));
                        print html_writer::start_tag('DIV', array('align'=>'center'));
                        print $OUTPUT->box_start('generalbox boxwidthwide');
                        $PAGE->requires->js_init_call('M.report_saas_export.init');
                        $oferta_form->set_data($oferta_disciplina);
                        $oferta_form->display();
                        print $OUTPUT->box_end();
                        print html_writer::end_tag('DIV');
                    } else {
                        print html_writer::tag('DIV', $OUTPUT->heading(get_string('no_ofertas_cursos', 'report_saas_export'), 3), array('align'=>'center'));
                    }
                }
                break;
            case 'polos':
                if(optional_param('reload', true, PARAM_INT)) {
                    $saas->load_polos_saas();
                }
                saas_show_nome_instituicao();
                print html_writer::tag('DIV', $OUTPUT->heading('Polos definidos no SAAS', 3), array('align'=>'center'));
                saas_show_table_polos();
                break;
            case 'add_polo':
                if(has_capability('report/saas_export:export', $syscontext)) {
                    saas_show_nome_instituicao();
                    print html_writer::tag('DIV', $OUTPUT->heading(get_string('add_polo', 'report_saas_export'), 3), array('align'=>'center'));
                    print html_writer::start_tag('DIV', array('align'=>'center'));
                    print $OUTPUT->box_start('generalbox boxwidthnormal');
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
            switch($subaction) {
                case 'change_group':
                    $odid = required_param('odid', PARAM_INT);
                    $group_map_id = required_param('group_map_id', PARAM_INT);
                    $od = $DB->get_record('saas_ofertas_disciplinas', array('id'=>$odid), 'id, group_map_id, oferta_curso_uid', MUST_EXIST);
                    if($group_map_id == -1) {
                        $max = $DB->get_field_sql("SELECT MAX(group_map_id) FROM {saas_ofertas_disciplinas} od");
                        $od->group_map_id = empty($max) ? 1 : $max+1;
                    } else {
                        $od->group_map_id = $group_map_id;
                    }
                    $DB->update_record('saas_ofertas_disciplinas', $od);
                    $oc = $saas->get_oferta_curso($od->oferta_curso_uid);
                    $ocid = $oc->id;
                    break;
                case 'delete':
                    $courseid = required_param('courseid', PARAM_INT);
                    $group_map_id = required_param('group_map_id', PARAM_INT);
                    $ocid = required_param('ocid', PARAM_INT);
                    $DB->delete_records('saas_map_course', array('courseid' => $courseid, 'group_map_id' => $group_map_id));
                    break;
                case 'add':
                    $courseid = required_param('courseid', PARAM_INT);
                    $course = $DB->get_record('course', array('id'=>$courseid), 'id, category');

                    $group_map_id = required_param('group_map_id', PARAM_INT);
                    $map = new stdClass();
                    $map->courseid = $courseid;
                    $map->group_map_id = $group_map_id;
                    $DB->insert_record('saas_map_course', $map);
                    $ods = $saas->get_ofertas_disciplinas_by_group_map($group_map_id);
                    $od = reset($ods);
                    $oc = $saas->get_oferta_curso($od->oferta_curso_uid);
                    $ocid = $oc->id;

                    $SESSION->last_categoryid = $course->category;
                    break;
            }
        }
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);

        if($subaction == 'show_tree') {
            $group_map_id = required_param('group_map_id', PARAM_INT);
            saas_show_categories_tree($group_map_id);
        } else {
            if(!isset($ocid)) {
                $ocid = optional_param('ocid', -1, PARAM_INT);
            }

            $course_mapping_type = $saas->get_config('course_mapping');
            saas_show_nome_instituicao();
            print html_writer::start_tag('DIV', array('align'=>'center'));
            print $OUTPUT->heading('Mapeamento de Ofertas de disciplinas para cursos Moodle' .
                                   $OUTPUT->help_icon($course_mapping_type, 'report_saas_export'), 3);
            print html_writer::end_tag('DIV');
            saas_show_course_mappings($ocid);
        }

        echo $OUTPUT->footer();
        break;
    case 'polo_mapping':
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);

        if($saas->has_polo()) {
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
        if($polo_mapping_type != 'no_polo') {
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
            saas_show_nome_instituicao();
            saas_show_menu_ofertas_cursos($ocid, $url);
            if($odid = optional_param('odid', 0 , PARAM_INT)) {
                saas_show_users_oferta_disciplina($odid);
            } else {
                saas_show_table_ofertas_curso_disciplinas($ocid, true);
            }
        } else {
            $ocid = optional_param('ocid', 0 , PARAM_INT);
            $poloid = optional_param('poloid', 0 , PARAM_INT);
            saas_show_nome_instituicao();
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
                    list($count_errors, $errors, $count_sent_users, $count_sent_ods, $count_sent_polos) =
                                        $saas->send_data($ocs, $ods, $polos, $send_user_details);
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
                    $rows = array();
                    $rows[] = array('Ofertas de disciplinas exportadas', $count_sent_ods);
                    $rows[] = array('Polos exportados', $count_sent_polos);
                    foreach($count_sent_users AS $r=>$count) {
                        $rows[] = array(get_string($r.'s', 'report_saas_export') . ' exportados', $count);
                    }
                    $table = new html_table();
                    $table->tablealign = 'center';
                    $table->attributes = array('class'=>'saas_table');
                    $table->data = $rows;
                    print html_writer::table($table);
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
