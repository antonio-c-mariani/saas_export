<?php
define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('./classes/saas.php');

$ocid = required_param('ocid', PARAM_INT);

$saas = new saas();
$disciplinas = $saas->get_disciplinas_for_oc($ocid);
echo json_encode($disciplinas);
die;
