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
        global $DB, $saas;

        $mform = $this->_form;
        $ocid = $this->_customdata['data'];

        $ofertas_cursos = empty($ocid) ? array(0=>'-- selecione uma oferta de curso') : array();
        foreach ($saas->get_ofertas_cursos() AS $oc) {
            $ofertas_cursos[$oc->id] = "{$oc->nome} {$oc->ano}/{$oc->periodo}";
        }

        $disciplinas = array(0=>'-- selecione uma disciplina');
        $disciplinas = empty($ocid) ? $disciplinas : $disciplinas + $saas->get_disciplinas_for_oc($ocid, true);

        $mform->addElement('select', 'oferta_curso_id', get_string('oferta_curso', 'report_saas_export'), $ofertas_cursos);
        $mform->setDefault('oferta_curso_id', $ocid);

        $mform->addElement('select', 'disciplina_id', get_string('disciplina', 'report_saas_export'), $disciplinas);
        $mform->disabledif ('disciplina_id', 'oferta_curso_id', 'eq', 0);

        $year = date('Y');
        $attributes = array(
                'startyear' => $year - 1,
                'stopyear'  => $year + 1,
                'timezone'  => 99,
                'applydst'  => true,
                'optional'  => false,
                );
        $mform->addElement('date_selector', 'inicio', get_string('inicio', 'report_saas_export'), $attributes);
        $mform->disabledif ('inicio', 'disciplina_id', 'eq', 0);
        $mform->addElement('date_selector', 'fim', get_string('fim', 'report_saas_export'), $attributes);
        $mform->disabledif ('fim', 'disciplina_id', 'eq', 0);

        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        global $DB, $saas;

        $errors = parent::validation($data, $files);

        if (empty($data['oferta_curso_id'])) {
            $errors['oferta_curso_id'] = get_string('required');
            return $errors;
        }

        if (empty($data['disciplina_id'])) {
            $errors['disciplina_id'] = get_string('required');
            return $errors;
        }

        if ($data['fim'] <= $data['inicio']) {
            $errors['fim'] = 'Data de fim deve ser posterior à de início';
            return $errors;
        }

        $disciplinas = $saas->get_disciplinas_for_oc($data['oferta_curso_id'], true);
        if (!isset($disciplinas[$data['disciplina_id']])) {
            $errors['disciplina_id'] = get_string('duplicated_disciplina', 'report_saas_export');
        }
        return $errors;
    }
}
