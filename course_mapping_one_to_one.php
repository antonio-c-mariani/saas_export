<?php
defined('MOODLE_INTERNAL') || die();

$of_cursos = $DB->get_records('saas_ofertas_cursos', array('enable'=>1), 'name', 'uid, id, name, year, period');
$sql = "SELECT od.*
          FROM {saas_ofertas_cursos} oc
          JOIN {saas_ofertas_disciplinas} od ON (od.oferta_curso_uid = oc.uid AND od.enable = 1)
         WHERE oc.enable = 1
      ORDER BY oc.name, oc.year, oc.period, od.name";
$of_disciplinas = $DB->get_records_sql($sql);

print html_writer::start_tag('DIV', array('align'=>'center'));

if(empty($of_disciplinas)) {
    print $OUTPUT->box_start('generalbox boxwidthnormal');
    print $OUTPUT->heading(get_string('no_ofertas_disciplinas', 'report_saas_export'));
    print $OUTPUT->box_end();
} else {
    var_dump($of_cursos, $of_disciplinas);

}

print html_writer::end_tag('DIV');
