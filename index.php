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

require_login();
$syscontext = context_system::instance();
require_capability('report/saas_export:view', $syscontext);
admin_externalpage_setup('report_saas_export', '', null, '', array('pagelayout'=>'report'));

$baseurl = new moodle_url('/report/saas_export/index.php');
$api_key = get_config('report_saas_export', 'api_key');

$tab_items = array('guidelines'=>true, 'settings'=>true, 'saas_data'=>false, 'course_mapping'=>false, 'polo_mapping'=>false, 'overview'=>false, 'export'=>false);

$tabs = array();
foreach($tab_items AS $act=>$always) {
    if($always || !empty($api_key)) {
        $tabs[$act] = new tabobject($act, new moodle_url('/report/saas_export/index.php', array('action'=>$act)), get_string($act, 'report_saas_export'));
    }
}

$action = optional_param('action', 'guidelines' , PARAM_TEXT);
$action = isset($tabs[$action]) ? $action : 'guidelines';

$saas = new saas();

switch ($action) {
    case 'guidelines':
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);
        print get_string('saas_presentation', 'report_saas_export');
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
                saas::save_settings($data);
                redirect($baseurl);
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

        $saas_data_tab_items = array('ofertas', 'polos', 'reload');
        $saas_data_tabs = array();
        foreach($saas_data_tab_items AS $act) {
            $url = clone($baseurl);
            $url->param('action', $action);
            $url->param('data', $act);
            $saas_data_tabs[$act] = new tabobject($act, $url, get_string($act, 'report_saas_export'));
        }
        $saas_data_action = optional_param('data', 'ofertas' , PARAM_TEXT);
        $saas_data_action = isset($saas_data_tabs[$saas_data_action]) ? $saas_data_action : 'ofertas';

        $url = clone($baseurl);
        $url->param('action', $action);
        $url->param('data', $saas_data_action);

        if($saas_data_action == 'ofertas') {
            $oferta_form = new oferta_form($url, array('saas'=>$saas));
            if ($oferta_form->is_cancelled()) {
                redirect($url);
            } else if ($oferta = $oferta_form->get_data()) {
                // todo: Cadastrar oferta no SAAS
                $saas->load_ofertas_disciplinas_saas();
                redirect($url);
            }
        } else if($saas_data_action == 'polos') {
            $polo_form = new polo_form($url);
            if ($polo_form->is_cancelled()) {
                redirect($url);
            } else if ($polo = $polo_form->get_data()) {
                // todo: Cadastrar polo no SAAS
                $saas->load_polos_saas();
                redirect($url);
            }
        }

        echo $OUTPUT->header();

        print_tabs(array($tabs), $action);
        print_tabs(array($saas_data_tabs), $saas_data_action);

        if($saas_data_action == 'ofertas') {
            $saas->show_table_ofertas_curso_disciplinas(false);
            print html_writer::start_tag('DIV', array('align'=>'center'));
            print $OUTPUT->box_start('generalbox boxwidthnormal');
            $oferta_form->display();
            print $OUTPUT->box_end();
            print html_writer::end_tag('DIV');
        } else if($saas_data_action == 'polos') {
            $saas->show_table_polos();
            print html_writer::start_tag('DIV', array('align'=>'center'));
            print $OUTPUT->box_start('generalbox boxwidthnormal');
            $polo_form->display();
            print $OUTPUT->box_end();
            print html_writer::end_tag('DIV');
        } else {
            $saas->load_saas_data(true);
            print html_writer::start_tag('DIV', array('align'=>'center'));
            print $OUTPUT->heading(get_string('reloaded', 'report_saas_export'));
            print html_writer::end_tag('DIV');
        }

        echo $OUTPUT->footer();
        break;
    case 'course_mapping':
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);

        include('ofertas_mapping.php');

        echo $OUTPUT->footer();
        break;
    case 'polo_mapping':
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);

        $polo_mapping_type = $saas->get_config('polo_mapping');
        switch ($polo_mapping_type) {
            case 'no_polo':
                print $OUTPUT->heading(get_string('title_no_polo', 'report_saas_export'));
                break;
            case 'group_to_polo':
                include('groups_polos_mapping.php');
                break;
            case 'category_to_polo':
                include('categories_polos_mapping.php');
                break;
            default:
                print $OUTPUT->heading('Mapeamento ainda não implementado: ' . $polo_mapping_type);
        }

        echo $OUTPUT->footer();
        break;
    case 'overview':
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);

        $saas_data_tab_items = array('ofertas', 'polos');
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
            $saas->show_table_ofertas_curso_disciplinas(true);
        } else {
            $polo_mapping_type = $saas->get_config('polo_mapping');
            switch ($polo_mapping_type) {
                case 'no_polo':
                    print $OUTPUT->heading(get_string('title_no_polo', 'report_saas_export'));
                    break;
                case 'group_to_polo':
                    $saas->show_overview_groups_polos();
                    break;
                default:
                    print $OUTPUT->heading('Mapeamento ainda não implementado: ' . $polo_mapping_type);
            }
        }

        echo $OUTPUT->footer();
        break;
    case 'export':
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);
        $saas->send_users_by_oferta_disciplina();
        echo $OUTPUT->footer();
        break;
    default:
        echo $OUTPUT->header();
        print_tabs(array($tabs), $action);
        print $OUTPUT->box_start('generalbox boxwidthormal');
        print $OUTPUT->heading('Ainda estamos trabalhando. Disponível em breve ...');
        print $OUTPUT->box_end();
        echo $OUTPUT->footer();
}
