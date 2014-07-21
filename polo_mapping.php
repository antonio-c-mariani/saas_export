<?php
defined('MOODLE_INTERNAL') || die();

print html_writer::start_tag('DIV', array('align'=>'center'));

$from = "FROM {saas_course_mapping} cm
         JOIN {course} c ON (c.id = cm.courseid)
         JOIN {saas_ofertas_disciplinas} od ON (od.id = cm.oferta_disciplina_id AND od.enable = 1)";

if($DB->record_exists_sql('SELECT true ' . $from)) {

} else {
    print $OUTPUT->box_start('generalbox boxwidthnormal');
    print $OUTPUT->heading(get_string('no_course_mapping', 'report_saas_export'));
    print $OUTPUT->box_end();
}

print html_writer::end_tag('DIV');
