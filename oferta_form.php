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
        global $DB;

        $mform = $this->_form;

        $saas = $this->_customdata['saas'];
        $ofertas_cursos = array();
        foreach($saas->get_ofertas_curso() AS $oc) {
            $ofertas_cursos[$oc->id] = "{$oc->nome} {$oc->ano}/{$oc->periodo}";
        }

        $disciplinas = $DB->get_records_menu('saas_disciplinas', array('enable'=>1), 'nome', 'id, nome');

        $mform->addElement('select', 'oferta_curso_id', get_string('oferta_curso', 'report_saas_export'), $ofertas_cursos);
        $mform->addElement('select', 'disciplina_id', get_string('disciplina', 'report_saas_export'), $disciplinas);

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

        $sql = "SELECT 1
                  FROM {saas_ofertas_cursos} oc
                  JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid)
                  JOIN {saas_disciplinas} d ON (d.uid = od.disciplina_uid)
                 WHERE oc.id = :ocid
                   AND d.id = :disciplinaid";
        if($DB->record_exists_sql($sql, array('ocid'=>$data['oferta_curso_id'], 'disciplinaid'=>$data['disciplina_id']))) {
            $errors['disciplina_id'] = get_string('duplicated_disciplina', 'report_saas_export');
        }
        return $errors;
    }
}
