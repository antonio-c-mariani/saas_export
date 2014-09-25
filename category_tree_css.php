<?php

/* --------------------------------------------- */
/* CSS Tree menu styles */

define('NO_MOODLE_COOKIES', true); // session not used here

if (strpos(__FILE__, '/admin/report/') !== false) {
    require('../../../config.php');
    $plugin_url = $CFG->wwwroot . '/admin/report/saas_export';
    $PAGE->set_url('/admin/report/category_tree_css.php');
} else {
    require('../../config.php');
    $plugin_url = $CFG->wwwroot . '/report/saas_export';
    $PAGE->set_url('/report/category_tree_css.php');
}

$lifetime  = 600;                                   // Seconds to cache this stylesheet

header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
header('Expires: ' . gmdate("D, d M Y H:i:s", time() + $lifetime) . ' GMT');
header('Cache-control: max_age = '. $lifetime);
header('Pragma: ');
header('Content-type: text/css; charset=utf-8');  // Correct MIME type

?>

.saas_tree li {
    position: relative;
    margin-left: -15px;
    list-style: none;
}

.saas_tree li input {
    position: absolute;
    left: 0;
    margin-left: 0;
    opacity: 0;
    z-index: 2;
    cursor: pointer;
    height: 1em;
    width: 1em;
    top: 0;
}

.saas_tree li input + ul {
    background: url(<?php echo $plugin_url . '/img/toggle-small-expand.png';?>) 40px 0 no-repeat;
    margin: -0.938em 0 0 -44px; /* 15px */
    height: 1em;
}

.saas_tree li label {
    background: url(<?php echo $plugin_url . '/img/folder-horizontal.png';?>) 15px 1px no-repeat;
    cursor: pointer;
    display: block;
    padding-left: 37px;
}

.saas_tree li input:checked + ul {
    background: url(<?php echo $plugin_url . '/img/toggle-small.png';?>) 40px 5px no-repeat;
    margin: -1.25em 0 0 -44px; /* 20px */
    padding: 1.563em 0 0 80px;
    height: auto;
}

.saas_tree li input + ul > li {
    display: none;
    margin-left: -14px !important;
    padding-left: 1px;
}


.saas_tree li input:checked + ul > li {
    display: block;
    margin: 0 0 0.125em;  /* 2px */
}

.saas_tree li input:checked + ul > li:last-child {
    margin: 0 0 0.063em; /* 1px */
}
