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

if (strpos(__FILE__, '/admin/report/') !== false) {
    $pluginpath = '/admin/report/saas_export';
    require('../../../config.php');
} else {
    $pluginpath = '/report/saas_export';
    require('../../config.php');
}

require_login();

require_once($CFG->dirroot.$pluginpath.'/lib.php');
require_once($CFG->libdir.'/adminlib.php');

$pluginurl = $CFG->wwwroot.$pluginpath.'/index.php';

$current_version = normalize_version(get_config('', 'release'));
if (version_compare($current_version, '2.0', '>=')) {
    $context_system = context_system::instance();
} else {
    $context_system = get_context_instance(CONTEXT_SYSTEM);
}
require_capability('report/saas_export:view', $context_system);

$step = optional_param('step', 0 , PARAM_INT);
$level = optional_param('level', -1 , PARAM_INT);
$option = optional_param('option', '', PARAM_ALPHA);

admin_externalpage_setup('report_saas_export', '', array(), '', array('pagelayout'=>'report'));

require_once($CFG->dirroot.$pluginpath.'/config_form.php');

$saas = new saas();

$mform = new saas_export_config_form(null, array('step'=>$step, 'saas' => $saas));

if ($mform->is_cancelled()) {

    redirect($pluginurl.'?step=0');

} else if ($data = $mform->get_data()) {
    switch ($step) {
        case 1:
            if (isset($data->map)) {
                $saas->save_courses_offers_mapping($data);
            }
            redirect($pluginurl.'?step=0');
            break;

        case 2:
            if (isset($data->map)) {
                $saas->save_classes_offers_mapping($data);
            }
            redirect($pluginurl.'?step=0');
            break;

        case 3:
            if (isset($data->map)) {
                $saas->save_polos_mapping($data);
            }
            redirect($pluginurl.'?step=0');
            break;

        case 4:
            try {
                $saas->send_data();
                redirect($pluginurl.'?step=5');
            } catch (Exception $e) {
                print_error('ws_error', 'report_saas_export', $pluginurl, $e->getMessage());
            }
            break;

        case 5:
            redirect($pluginurl.'?step=0');
            break;
    }
}

if (version_compare($current_version, '2.0', '>=')) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('title', 'report_saas_export'), 3);
    echo $OUTPUT->box_start('generalbox');
    $mform->display();
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
} else {
    admin_externalpage_print_header();
    print_heading(get_string('title', 'report_saas_export'));
    print_simple_box_start('center');
    $mform->display();
    print_simple_box_end();
    print_footer();
}
