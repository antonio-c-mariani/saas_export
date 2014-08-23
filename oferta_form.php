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

class oferta_form extends moodleform {

    function definition() {
        $mform = $this->_form;

        $saas = $this->_customdata['saas'];
        $ofertas_cursos = array();
        foreach($saas->get_ofertas_curso_salvas() AS $oc) {
            $ofertas_cursos[$oc->id] = "{$oc->nome} {$oc->ano}/{$oc->periodo}";
        }

        $disciplinas = $saas->get_disciplinas_saas();

        // ----------------------------------------------------------------------------------------------
        $mform->addElement('header', 'add_oferta', get_string('add_oferta', 'report_saas_export'));

        $mform->addElement('select', 'oferta_curso_id', get_string('oferta_curso', 'report_saas_export'), $ofertas_cursos);
        $mform->addElement('select', 'disciplinas_uid', get_string('disciplina', 'report_saas_export'), $disciplinas);

        $year = date('Y');
        $attributes = array(
                'startyear' => $year - 1,
                'stopyear'  => $year + 1,
                'timezone'  => 99,
                'applydst'  => true,
                'optional'  => false,
                );
        $mform->addElement('date_selector', 'inicio', get_string('inicio', 'report_saas_export'), $attributes);
        $mform->addElement('date_selector', 'fim', get_string('fim', 'report_saas_export'), $attributes);

        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        return $errors;
    }
}
