<?php
    define('AJAX_SCRIPT', true);
    require_once '../../config.php';

    $uid = required_param('uid', PARAM_INT);
    $id = required_param('id', PARAM_INT);
    $mapping_type = required_param('mapping_type', PARAM_ALPHA);
    echo $uid . $id . $mapping_type;
?>