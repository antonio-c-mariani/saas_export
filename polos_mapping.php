<?php
defined('MOODLE_INTERNAL') || die();

print html_writer::start_tag('DIV', array('align'=>'center'));

$polos = $DB->get_records_menu('saas_polos', null, 'nome', 'nome, id');

$sql = "SELECT DISTINCT g.name as groupname, polo.polo_id, polo.nome as saas_polo_nome
          FROM mdl_saas_course_mapping scm
          JOIN mdl_course c ON (c.id = scm.courseid)
          JOIN mdl_saas_ofertas_disciplinas sod ON (sod.id = scm.oferta_disciplina_id AND sod.enable = 1)
          JOIN mdl_groups g ON (g.courseid = c.id)
     LEFT JOIN (SELECT spm.groupname, spm.polo_id, sp.nome
                  FROM mdl_saas_polos_mapping spm
                  JOIN mdl_saas_polos sp ON (sp.id = spm.polo_id)) polo
            ON (polo.groupname = g.name)
      ORDER BY g.name";
$map = $DB->get_records_sql($sql);
$data = array();
foreach($map AS $m) {
    $data = array($m->groupname, $m->saas_polo_nome, $m->polo_id);

    print $OUTPUT->box_start('generalbox boxwidthnormal');
    print $OUTPUT->heading(get_string('no_course_mapping', 'report_saas_export'));
    print $OUTPUT->box_end();

print html_writer::end_tag('DIV');
