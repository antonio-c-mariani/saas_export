<?php
define('AJAX_SCRIPT', true);

if (strpos(__FILE__, '/admin/report/') !== false) {
    require('../../../config.php');
} else {
    require('../../config.php');
}
require_once('./classes/saas.php');

$ocid = required_param('ocid', PARAM_INT);

$saas = new saas();
$disciplinas = $saas->get_disciplinas_for_oc($ocid);
echo json_encode($disciplinas);
die;
