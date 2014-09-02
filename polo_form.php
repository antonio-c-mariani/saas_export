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

class polo_form extends moodleform {

    function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'add_polo', get_string('add_polo', 'report_saas_export'));

        $mform->addElement('text', 'nome', get_string('nome_polo', 'report_saas_export'), 'size="50"');
        $mform->addRule('nome', get_string('required'), 'required', null, 'client');
        $mform->setType('nome', PARAM_TEXT);

        $mform->addElement('text', 'cidade', get_string('cidade', 'report_saas_export'), 'size="50"');
        $mform->addRule('cidade', get_string('required'), 'required', null, 'client');
        $mform->setType('cidade', PARAM_TEXT);

        $estados = array(
                    "AC"=>"Acre",
                    "AL"=>"Alagoas",
                    "AM"=>"Amazonas",
                    "AP"=>"Amapá",
                    "BA"=>"Bahia",
                    "CE"=>"Ceará",
                    "DF"=>"Distrito Federal",
                    "ES"=>"Espírito Santo",
                    "GO"=>"Goiás",
                    "MA"=>"Maranhão",
                    "MT"=>"Mato Grosso",
                    "MS"=>"Mato Grosso do Sul",
                    "MG"=>"Minas Gerais",
                    "PA"=>"Pará",
                    "PB"=>"Paraíba",
                    "PR"=>"Paraná",
                    "PE"=>"Pernambuco",
                    "PI"=>"Piauí",
                    "RJ"=>"Rio de Janeiro",
                    "RN"=>"Rio Grande do Norte",
                    "RO"=>"Rondônia",
                    "RS"=>"Rio Grande do Sul",
                    "RR"=>"Roraima",
                    "SC"=>"Santa Catarina",
                    "SE"=>"Sergipe",
                    "SP"=>"São Paulo",
                    "TO"=>"Tocantins");
        $mform->addElement('select', 'estado', get_string('estado', 'report_saas_export'), $estados);

        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $nome = trim($data['nome']);
        if(empty($nome)) {
            $errors['nome'] = get_string('invalid_nome_polo', 'report_saas_export');
        } else {
            if($DB->record_exists('saas_polos', array('nome'=>$nome))) {
                $errors['nome'] = get_string('exists_nome_polo', 'report_saas_export');
            }
        }

        $cidade = trim($data['cidade']);
        if(empty($cidade)) {
            $errors['cidade'] = get_string('invalid_cidade', 'report_saas_export');
        }

        return $errors;
    }
}
