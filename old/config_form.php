<?php

defined('MOODLE_INTERNAL') || die();
require_once("{$CFG->libdir}/formslib.php");
require_once("{$CFG->libdir}/environmentlib.php");
require_once './lib.php';

$current_version = normalize_version(get_config('', 'release'));
if(version_compare($current_version, '2.5', '<')) {
    require_once($CFG->dirroot.'/course/lib.php');
} else {
    require_once($CFG->libdir.'/coursecatlib.php');
}

class saas_export_config_form extends moodleform {

    function definition() {
        global $DB, $CFG, $OUTPUT;

        $mform = $this->_form;

        $step = $this->_customdata['step'];
        $saas = $this->_customdata['saas'];

        switch ($step) {

            case 0:
                $mform->addElement('hidden', 'step', $step);
                $mform->setType('step', PARAM_INT);

                $mform->addElement('html', get_string('saas_presentation', 'report_saas_export'));

                $url_mapping_courses = new moodle_url('/report/saas_export/index.php', array('step'=>1));
                $url_mapping_classes = new moodle_url('/report/saas_export/index.php', array('step'=>2));
                $url_mapping_polos = new moodle_url('/report/saas_export/index.php', array('step'=>3));
                $url_send_data = new moodle_url('/report/saas_export/index.php', array('step'=>4));
                $url_config_table = new moodle_url('/report/saas_export/index.php', array('step'=>6));

                $mform->addElement('html', html_writer::tag('a', get_string('menu_config_table', 'report_saas_export'),
                                           array('href' => $url_config_table, 'style'=>'display: block; margin-top:0.5cm; margin-left:0.5cm;')));

                $mform->addElement('html', html_writer::tag('a', get_string('menu_map_course_offer', 'report_saas_export'),
                                           array('href' => $url_mapping_courses, 'style'=>'display: block; margin-top:0.5cm; margin-left:0.5cm;')));

                $mform->addElement('html', html_writer::tag('a', get_string('menu_map_class_offer', 'report_saas_export'),
                                           array('href' => $url_mapping_classes, 'style'=>'display: block; margin-left:0.5cm;')));

                $mform->addElement('html', html_writer::tag('a', get_string('menu_map_polo', 'report_saas_export'),
                                           array('href' => $url_mapping_polos, 'style'=>'display: block; margin-left:0.5cm;')));

                $mform->addElement('html', html_writer::tag('a', get_string('menu_send_data', 'report_saas_export'),
                                           array('href' => $url_send_data, 'style'=>'display: block; margin-left:0.5cm;')));
                list($count_new_courses, $count_new_classes) = $saas->update_offers();

                $mform->addElement('html', '<BR>');
                if ($count_new_courses > 0) {
                    $mform->addElement('html', html_writer::tag('p', "Há {$count_new_courses} nova(s) oferta(s) de curso a ser(em) mapeada(s).",
                                               array('style'=>'font-weight: bold;')));
                }
                if ($count_new_classes > 0) {
                    $mform->addElement('html', html_writer::tag('p', "Há {$count_new_classes} nova(s) oferta(s) de disciplina a se(rem) mapeada(s).",
                                               array('style'=>'font-weight: bold;')));
                }

                break;

           case 1:
                $mform->addElement('hidden', 'step', $step);
                $mform->setType('step', PARAM_INT);

                $current_version = normalize_version(get_config('', 'release'));
                if(version_compare($current_version, '2.5', '<')) {
                    $categories = array();
                    $parentlist = array();
                    make_categories_list($categories, $parentlist);
                } else {
                    $categories = coursecat::make_categories_list();
                }

                $categories[0] = 'Não relacionar (todos os cursos serão mostrados)';
                $categories[-1] = 'Ainda não Mapeada';
                try {
                    $saas_courses = $saas->get_courses_offers();
                } catch (Exception $e) {
                    print_error('ws_error', 'report_saas_export', new moodle_url('/report/saas_export/index.php'), $e->getMessage());
                }

                try {
                    $saas->save_courses($saas_courses);
                } catch (Exception $e) {
                    print_error('update_data_error', 'report_saas_export', new moodle_url('/report/saas_export/index.php'), $e->getMessage());
                }

                $mapped_courses = $saas->get_mapped_courses();

                if (!empty($saas_courses)){
                    $mform->addElement('html', '<h2>' .get_string('title_passo1', 'report_saas_export'). '</h2>');
                    $mform->addElement('html', '<BR>' . get_string('obs_passo1', 'report_saas_export'));

                    $mform->addElement('static', 'title', html_writer::tag('strong', 'SAAS'), html_writer::tag('strong', 'MOODLE'));

                    foreach ($saas_courses as $sc) {
                        $mform->addElement('select', 'map['.$sc->uid.']', $sc->nome.' ('.$sc->ano.'-'.$sc->periodo . ')', $categories);
                        if (array_key_exists($sc->uid, $mapped_courses)){
                            $mform->setDefault('map['.$sc->uid.']', $mapped_courses[$sc->uid]);
                        } else {
                            $mform->setDefault($sc->uid, -1);
                        }
                    }
                    $this->add_action_buttons(true, get_string('savechanges'));
                } else {
                    $mform->addElement('html', get_string('no_course_offer_from_ws', 'report_saas_export'));
                    $mform->addElement('html', html_writer::tag('input', '', array('class'=>'btn-cancel', 'name'=>'cancel', 'value'=> 'Voltar',
                                                                'type'=>'submit', 'onclick'=>'skipClientValidation = true; return true;',
                                                                'style'=>'display:block; margin-top:1em;')));
                }
                break;

            case 2:
                $mform->addElement('hidden', 'step', $step);
                $mform->setType('step', PARAM_INT);

                try {
                    $saas_classes_offers = $saas->get_classes_offers();
                } catch (Exception $e) {
                    print_error('ws_error', 'report_saas_export', new moodle_url('/report/saas_export/index.php'), $e->getMessage());
                }
                try {
                    $saas->save_classes($saas_classes_offers);
                } catch (Exception $e) {
                    print_error('update_data_error', 'report_saas_export', new moodle_url('/report/saas_export/index.php'), $e->getMessage());
                }

                $mapped_courses = $saas->get_courses_offers_info();
                $mapped_classes = $saas->get_mapped_classes();

                if (empty($mapped_classes)) {
                    $mform->addElement('html', get_string('no_class_offer_from_ws', 'report_saas_export'));
                    $mform->addElement('html', html_writer::tag('input', '', array('class'=>'btn-cancel', 'name'=>'cancel', 'value'=> 'Voltar',
                                                                'type'=>'submit', 'onclick'=>'skipClientValidation = true; return true;',
                                                                'style'=>'display:block; margin-top:1em;')));
                } else {
                    $mform->addElement('html', html_writer::tag('h2', get_string('title_passo2', 'report_saas_export')));
                    $mform->addElement('html', get_string('obs_passo2', 'report_saas_export'));

                    foreach ($mapped_courses as $mapped_course) {
                        $category = $DB->get_field('saas_ofertas_cursos', 'categoryid', array('saas_id'=>$mapped_course->saas_id, 'enable'=>1));
                        if (($category == 0)||($category == -1)){
                            $moodleCourses = $DB->get_records('course', null, null, 'id, category, fullname, shortname, sortorder');
                        } else {
                            $moodleCourses = $saas->get_courses_from_category($category);
                        }

                        $courses = array(-1=>'Não Mapeado');
                        foreach ($moodleCourses as $mc ){
                            $courses[$mc->id] = $mc->fullname;
                        }

                        $classes_to_show = $DB->get_records('saas_ofertas_disciplinas', array('saas_course_offer_id'=>$mapped_course->id));

                        $title = $mapped_course->name . ' ('. $mapped_course->year .'-'. $mapped_course->period . ')';
                        $mform->addElement('header', $title, $title);

                        if (empty($classes_to_show)){
                            $mform->addElement('html', get_string('no_class_offer_from_ws', 'report_saas_export'));
                        } else {
                            $mform->addElement('static', 'title'.$mapped_course->id, html_writer::tag('strong', 'SAAS'), html_writer::tag('strong', 'MOODLE'));
                            foreach ($classes_to_show as $class){
                                $class_title = $class->name .' ('. $class->year .'-'.  $mapped_course->period . ')';
                                $mform->addElement('select', 'map['.$mapped_course->id.']['.$class->saas_id.']', $class_title, $courses);
                                if (array_key_exists($class->saas_id, $mapped_classes)){
                                    $mform->setDefault('map['.$mapped_course->id.']['.$class->saas_id.']', $mapped_classes[$class->saas_id]);
                                } else {
                                    $mform->setDefault($class->saas_id, -1);
                                }
                            }
                        }
                    }
                    $this->add_action_buttons(true, get_string('savechanges'));
                }
                break;

                case 3:
                    $mform->addElement('hidden', 'step', $step);
                    $mform->setType('step', PARAM_INT);

                    try {
                        $mapped_courses = $saas->get_courses_offers_info();
                        $mapped_classes = $saas->get_mapped_classes();
                        $mapped_polos = $saas->get_mapped_polos_by_name();
                    } catch (Exception $e) {
                        print_error('get_data_error', 'report_saas_export', new moodle_url('/report/saas_export/index.php'), $e->getMessage());
                    }
                    if (empty($mapped_courses)) {
                        $mform->addElement('html', get_string('no_mapped_courses_offer', 'report_saas_export'));
                        $mform->addElement('html', html_writer::tag('input', '', array('class'=>'btn-cancel', 'name'=>'cancel', 'value'=> 'Voltar',
                                                                   'type'=>'submit', 'onclick'=>'skipClientValidation = true; return true;',
                                                                   'style'=>'display:block; margin-top:1em;')));
                    } else if (empty($mapped_classes)) {
                        $mform->addElement('html', get_string('no_mapped_classes_offer', 'report_saas_export'));
                        $mform->addElement('html', html_writer::tag('input', '', array('class'=>'btn-cancel', 'name'=>'cancel', 'value'=> 'Voltar',
                                                                    'type'=>'submit', 'onclick'=>'skipClientValidation = true; return true;',
                                                                    'style'=>'display:block; margin-top:1em;')));
                    } else {
                        $mform->addElement('html', html_writer::tag('h2', get_string('title_passo3', 'report_saas_export')));
                        $mform->addElement('html', get_string('obs_passo3', 'report_saas_export'));
                        foreach ($mapped_courses as $mapped_course) {
                            $offer_title = $mapped_course->name . ' ('. $mapped_course->year .'-'. $mapped_course->period . ')';
                            $mform->addElement('header', $offer_title, $offer_title);
                            $sql = "SELECT DISTINCT g.name
                                      FROM {saas_ofertas_disciplinas} AS od
                                      JOIN {groups} AS g
                                        ON g.courseid = od.courseid
                                     WHERE od.enable = 1
                                       AND od.saas_course_offer_id = ?";
                            $params = array('course_offer_id'=> $mapped_course->id);

                            $groups = $DB->get_records_sql($sql, $params);
                            $not_mapped_groups = array();

                            if (empty($groups)) {
                                $mform->addElement('html', get_string('no_groups_found','report_saas_export'));
                            } else {
                                foreach ($groups as $g) {
                                    if (array_key_exists($g->name, $mapped_polos)) {
                                        $mform->addElement('advcheckbox', 'map['.$mapped_course->id.']['.$g->name.']', $g->name, '', null, array(0,1));
                                        $mform->setDefault('map['.$mapped_course->id.']['.$g->name.']', $mapped_polos[$g->name]);
                                    } else {
                                        $not_mapped_groups[] = $g;
                                    }
                                }
                            }

                            if (!empty($not_mapped_groups)){
                                foreach ($not_mapped_groups as $g){
                                    $mform->addElement('advcheckbox', 'map['.$mapped_course->id.']['.$g->name.']', $g->name, ' (Novo grupo Moodle)', null, array(0,1));
                                }
                            }
                        }
                        $this->add_action_buttons(true, get_string('savechanges'));
                    }
                    break;

            case 4:
                $mform->addElement('html', html_writer::tag('h2', get_string('title_passo4', 'report_saas_export'),
                                           array('style'=>'display: block; margin-top:0.5cm;')));

                $total_of_users = $saas->get_total_users_to_send();

                if(empty($total_of_users)){
                    $mform->addElement('hidden', 'step', 0);
                    $mform->setType('step', PARAM_INT);

                    $mform->addElement('html', get_string('no_data_to_export','report_saas_export'));
                    $mform->addElement('html', html_writer::tag('input', '', array('class'=>'btn-cancel', 'name'=>'cancel', 'value'=> 'Voltar',
                                                    'type'=>'submit', 'onclick'=>'skipClientValidation = true; return true;',
                                                    'style'=>'display:block; margin-top:1em;')));

                } else {
                    $mform->addElement('hidden', 'step', $step);
                    $mform->setType('step', PARAM_INT);

                    $mform->addElement('html', html_writer::tag('p', get_string('obs_passo4', 'report_saas_export'),
                                               array('style'=>'display: block; margin-top:0.5cm;')));

                    $mform->addElement('html', html_writer::tag('p', get_string('total_users_to_send', 'report_saas_export') . $total_of_users,
                                               array('style'=>'display: block; margin-top:0.5cm;')));

                    $mform->addElement('html', html_writer::tag('p', get_string('users_by_class_offer', 'report_saas_export'),
                                               array('style'=>'display: block; margin-top:0.5cm;')));

                    $courses_offers = $saas->get_count_users();
                    $table = new html_table();
                    $table->head  = array('Ofertas de cursos/disciplinas');
                    foreach (saas::$role_names as $r){
                        $table->head[] = get_string($r, 'report_saas_export');
                    }
                    $table->data  = array();
                    $line = array();

                    $offers = $DB->get_records('saas_ofertas_cursos', array('enable'=>1), null, 'saas_id, id, name');

                    foreach ($courses_offers as $saas_course_offer_id => $classes_offer){
                        $class_names = $DB->get_records_menu('saas_ofertas_disciplinas', array('enable'=>1,
                                                             'saas_course_offer_id' => $offers[$saas_course_offer_id]->id),
                                                             null, 'saas_id, name');
                        $line[] = array('<strong>' . $offers[$saas_course_offer_id]->name . '</strong>');
                        foreach ($classes_offer as $class_offer => $counts){
                            $l = array('&nbsp &nbsp' . $class_names[$class_offer]);
                            foreach (saas::$role_names as $r){
                                if (isset($counts[$r])){
                                    $l[] = $counts[$r];
                                } else {
                                    $l[] = 0;
                                }
                            }
                            $line[] = $l;
                        }
                    }
                    $table->data = $line;

                    $mform->addElement('html', html_writer::table($table));

                    $this->add_action_buttons(true, 'Exportar dados');
                }

                break;

           case 5:
                $mform->addElement('hidden', 'step', $step);
                $mform->setType('step', PARAM_INT);

                $mform->addElement('html', html_writer::tag('p', get_string('success', 'report_saas_export'),
                                           array('style'=>'display: block; margin-top:0.5cm;')));

                $this->add_action_buttons(false, 'Finalizar');

                break;

           case 6:
                $mform->addElement('hidden', 'step', 0);
                $mform->setType('step', PARAM_INT);

                $mform->addElement('html', html_writer::tag('h2', get_string('config', 'report_saas_export'),
                                           array('style'=>'display: block; margin-top:0.5cm;')));

                $table = new html_table();
                $table->head = array('Nome', 'Valor');

                $config = $saas->config;
                unset($config->api_key);

                $data = array();
                $data[] = array('name' => get_string('ws_url', 'report_saas_export') . $OUTPUT->help_icon('ws_url', 'report_saas_export'),
                                'value' => $config->ws_url);

                $data[] = array('name' => get_string('course_name_default', 'report_saas_export'). $OUTPUT->help_icon('course_name_default', 'report_saas_export'), 
                                'value' => $config->course_name_default);
                $data[] = array('name' => get_string('user_id_field', 'report_saas_export') . $OUTPUT->help_icon('user_id_field', 'report_saas_export'),
                                'value' => $config->user_id_field);

                $cpf_field_help =  $OUTPUT->help_icon('cpf', 'report_saas_export');
                $data[] = array('name' => get_string('cpf_field', 'report_saas_export', 'estudantes') . $cpf_field_help,
                                'value' => $config->cpf_student_field);
                $data[] = array('name' => get_string('cpf_field', 'report_saas_export', 'tutores a distância') . $cpf_field_help,
                                'value' => $config->cpf_tutor_field);
                $data[] = array('name' => get_string('cpf_field', 'report_saas_export', 'tutores presênciais') . $cpf_field_help,
                                'value' => $config->cpf_tutor_polo_field);
                $data[] = array('name' => get_string('cpf_field', 'report_saas_export', 'professores') . $cpf_field_help,
                                'value' => $config->cpf_teacher_field);

                $name_field_help = $OUTPUT->help_icon('profile_field_name', 'report_saas_export');
                $data[] = array('name' => get_string('name_field', 'report_saas_export', 'estudantes') . $name_field_help,
                                'value' => get_string($config->name_student_field, 'report_saas_export'));
                $data[] = array('name' => get_string('name_field', 'report_saas_export', 'tutores a distância') . $name_field_help,
                                'value' => get_string($config->name_tutor_field, 'report_saas_export'));
                $data[] = array('name' => get_string('name_field', 'report_saas_export', 'tutores presênciais') . $name_field_help,
                                'value' => get_string($config->name_tutor_polo_field, 'report_saas_export'));
                $data[] = array('name' => get_string('name_field', 'report_saas_export', 'professores') . $name_field_help,
                                'value' => get_string($config->name_teacher_field, 'report_saas_export'));

                $user_role_help = $OUTPUT->help_icon('roles', 'report_saas_export');
                $data[] = array('name' => get_string('user_role', 'report_saas_export', 'estudantes') . $user_role_help,
                                'value' => $saas->serialize_role_names($config->student_role));
                $data[] = array('name' => get_string('user_role', 'report_saas_export', 'tutores a distância') . $user_role_help,
                                'value' => $saas->serialize_role_names($config->tutor_role));
                $data[] = array('name' => get_string('user_role', 'report_saas_export', 'tutores presênciais') . $user_role_help,
                                'value' => $saas->serialize_role_names($config->tutor_polo_role));
                $data[] = array('name' => get_string('user_role', 'report_saas_export', 'professores') . $user_role_help,
                                'value' => $saas->serialize_role_names($config->teacher_role));

                $table->data = $data;

                $mform->addElement('html', html_writer::table($table));

                $mform->addElement('html', html_writer::tag('input', '', array('class'=>'btn-cancel', 'name'=>'cancel', 'value'=> 'Voltar',
                                                'type'=>'submit', 'onclick'=>'skipClientValidation = true; return true;',
                                                'style'=>'display:block; margin-top:1em;')));

                break;
        }
    }
}
