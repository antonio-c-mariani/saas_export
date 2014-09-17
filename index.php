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

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/report/saas_export/classes/saas.php');
require_once($CFG->dirroot . '/report/saas_export/polo_form.php');
require_once($CFG->dirroot . '/report/saas_export/oferta_form.php');
require('./locallib.php');

require_login();
$syscontext = context_system::instance();
require_capability('report/saas_export:view', $syscontext);
admin_externalpage_setup('report_saas_export', '', null, '', array('pagelayout'=>'report'));

$baseurl = new moodle_url('/report/saas_export/index.php');

$saas = new saas();

$polo_mapping = get_config('report_saas_export', 'polo_mapping');
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
    $tabs[$act] = new tabobject($act, new moodle_url('/report/saas_export/index.php', array('action'=>$act)), get_string($act, 'report_saas_export'));
}

$action = optional_param('action', 'guidelines' , PARAM_TEXT);
$action = isset($tabs[$action]) ? $action : 'guidelines';

switch ($action) {
    case 'guidelines':
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);
        print $OUTPUT->heading('Exportação de dados do Moodle para SAAS');
        include('orientacoes.html');
        echo $OUTPUT->footer();
        break;
    case 'settings':
        require_once($CFG->dirroot . '/report/saas_export/settings_form.php');
        $baseurl->param('action', 'settings');
        $mform = new saas_export_settings_form($baseurl);

        if(has_capability('report/saas_export:config', $syscontext)) {
            if ($mform->is_cancelled()) {
                redirect($baseurl);
            } else if ($data = $mform->get_data()) {
                $saas->save_settings($data);
                if($saas->verify_api_key()) {
                    redirect($baseurl);
                } else {
                    print_error('api_key_unknown', 'report_saas_export', $baseurl);
                }
            }
        }

        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);

        if(!has_capability('report/saas_export:config', $syscontext)) {
            print $OUTPUT->heading(get_string('no_permission_to_config', 'report_saas_export'), '3');
        }

        $mform->display();
        echo $OUTPUT->footer();
        break;
    case 'saas_data':
        $saas->load_saas_data();

        $saas_data_tab_items = array('ofertas'    => false,
                                     'add_oferta' => 'report/saas_export:export');
        if($polo_mapping != 'no_polo') {
            $saas_data_tab_items['polos'] = false;
            $saas_data_tab_items['add_polo'] = 'report/saas_export:export';
        }

        $saas_data_tabs = array();
        foreach($saas_data_tab_items AS $act=>$capability) {
            if(!$capability || has_capability($capability, $syscontext)) {
                $url = clone($baseurl);
                $url->param('action', $action);
                $url->param('data', $act);
                $saas_data_tabs[$act] = new tabobject($act, $url, get_string($act, 'report_saas_export'));
            }
        }
        $saas_data_action = optional_param('data', 'ofertas' , PARAM_TEXT);
        $saas_data_action = isset($saas_data_tabs[$saas_data_action]) ? $saas_data_action : 'ofertas';

        $url = clone($baseurl);
        $url->param('action', $action);
        $url->param('data', $saas_data_action);

        if(has_capability('report/saas_export:export', $syscontext)) {
            switch($saas_data_action) {
                case 'add_oferta':
                    $oferta_form = new oferta_form($url, array('saas'=>$saas));
                    if ($oferta_form->is_cancelled()) {
                        redirect($url);
                    } else if ($oferta = $oferta_form->get_data()) {
                        $new_oferta = new stdClass();
                        $new_oferta->disciplina = new stdClass();
                        $new_oferta->disciplina->uid = $DB->get_field('saas_disciplinas', 'uid', array('id'=>$oferta->disciplina_id), MUST_EXIST);
                        $new_oferta->ofertaCurso = new stdClass();
                        $new_oferta->ofertaCurso->uid = $DB->get_field('saas_ofertas_cursos', 'uid', array('id'=>$oferta->oferta_curso_id), MUST_EXIST);
                        $new_oferta->inicio = $oferta->inicio;
                        $new_oferta->fim = $oferta->fim;
                        $saas->post_ws('ofertas/disciplinas', $new_oferta);
                        $saas->load_ofertas_disciplinas_saas();
                        $url->param('data', 'ofertas');
                        $url->param('reload', 0);
                        redirect($url);
                    }
                    break;
                case 'add_polo':
                    $polo_form = new polo_form($url);
                    if ($polo_form->is_cancelled()) {
                        redirect($url);
                    } else if ($polo = $polo_form->get_data()) {
                        $new_polo = new stdClass();
                        $new_polo->nome = trim($polo->nome);
                        $new_polo->cidade = trim($polo->cidade);
                        $new_polo->estado = $polo->estado;
                        $saas->post_ws('polos', $new_polo);
                        $saas->load_polos_saas();
                        $url->param('data', 'polos');
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
                    print $OUTPUT->heading(get_string('add_oferta', 'report_saas_export'));
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
                    print $OUTPUT->heading(get_string('add_polo', 'report_saas_export'));
                    $polo_form->display();
                    print $OUTPUT->box_end();
                    print html_writer::end_tag('DIV');
                }
                break;
        }

        echo $OUTPUT->footer();
        break;
    case 'course_mapping':
        $syscontext = context_system::instance();
        $may_export = has_capability('report/saas_export:export', $syscontext);

        $od_id = optional_param('od_id', 0, PARAM_INT);
        $group_map_id = optional_param('group_map_id', 0, PARAM_INT);
        if(!empty($od_id) && !empty($group_map_id) && $may_export) {
            $od = $DB->get_record('saas_ofertas_disciplinas', array('id'=>$od_id), 'id, group_map_id, oferta_curso_uid', MUST_EXIST);
            if($group_map_id == -1) {
                $max = $DB->get_field_sql("SELECT MAX(group_map_id) FROM {saas_ofertas_disciplinas}");
                $od->group_map_id = empty($max) ? 1 : $max+1;
            } else {
                $od->group_map_id = $group_map_id;
            }
            $DB->update_record('saas_ofertas_disciplinas', $od);
            $id = $DB->get_field('saas_ofertas_cursos', 'id', array('uid'=>$od->oferta_curso_uid));
            redirect(new moodle_url('/report/saas_export/index.php', array('action'=>'course_mapping', 'oc_id'=>$id), 'oc'.$id));
        }

        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);

        include('group_ofertas.php');

        echo $OUTPUT->footer();
        break;
    case 'polo_mapping':
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);

        if($DB->record_exists('saas_polos', array('enable'=>1))) {
            $polo_mapping_type = $saas->get_config('polo_mapping');
            switch ($polo_mapping_type) {
                case 'no_polo':
                    print $OUTPUT->heading(get_string('title_no_polo', 'report_saas_export'));
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
                    print $OUTPUT->heading('Mapeamento ainda não implementado: ' . $polo_mapping_type);
            }
        } else {
            print html_writer::tag('h3', get_string('no_polos', 'report_saas_export'));
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
            $url->param('data', $act);
            $saas_data_tabs[$act] = new tabobject($act, $url, get_string($act, 'report_saas_export'));
        }
        $saas_data_action = optional_param('data', 'ofertas' , PARAM_TEXT);
        $saas_data_action = isset($saas_data_tabs[$saas_data_action]) ? $saas_data_action : 'ofertas';
        print_tabs(array($saas_data_tabs), $saas_data_action);

        if($saas_data_action == 'ofertas') {
            $oc_id = optional_param('oc_id', 0 , PARAM_INT);
            saas_show_table_ofertas_curso_disciplinas($oc_id, true, true);
            if($odid = optional_param('odid', 0 , PARAM_INT)) {
                saas_show_users_oferta_disciplina($odid);
            }
        } else {
            if($DB->record_exists('saas_polos', array('enable'=>1))) {
                $polo_mapping_type = $saas->get_config('polo_mapping');
                $ocid = optional_param('ocid', 0 , PARAM_INT);
                $poloid = optional_param('poloid', 0 , PARAM_INT);
                switch ($polo_mapping_type) {
                    case 'no_polo':
                        print $OUTPUT->heading(get_string('title_no_polo', 'report_saas_export'));
                        break;
                    case 'group_to_polo':
                        saas_show_overview_groups_polos($ocid, $poloid);
                        break;
                    case 'category_to_polo':
                        saas_show_overview_categories_polos($ocid, $poloid);
                        break;
                    case 'course_to_polo':
                        saas_show_overview_courses_polos($ocid, $poloid);
                        break;
                    default:
                        print $OUTPUT->heading('Mapeamento ainda não implementado: ' . $polo_mapping_type);
                }
            } else {
                print html_writer::tag('h3', get_string('no_polos', 'report_saas_export'));
            }
        }

        echo $OUTPUT->footer();
        break;
    case 'export':
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);

        if(has_capability('report/saas_export:export', $syscontext)) {
            $ocs = optional_param_array('oc', array(), PARAM_INT);
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
        print $OUTPUT->heading('Ainda estamos trabalhando. Disponível em breve ...');
        print $OUTPUT->box_end();
        print $OUTPUT->footer();
}
