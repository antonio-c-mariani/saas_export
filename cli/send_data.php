<?php

define('CLI_SCRIPT', true);
error_reporting(E_ALL);

if (strpos(__FILE__, '/admin/report/') !== false) {
    require(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
} else {
    require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
}
require_once($CFG->libdir.'/clilib.php');
require_once(dirname(dirname(__FILE__)) . '/classes/saas.php');

$saas = new saas();

list($options, $unrecognized) = cli_get_params(
        array('help'     => false,
              'all'      => false,
              'ocid'     => false,
              'list'     => false,
              'details'  => false,
             ),
        array('h' => 'help',
              'l' => 'list',
              'd' => 'details',
              'a' => 'all',
             ));

$all = !empty($options['all']);
$list = !empty($options['list']);
$details = !empty($options['details']);

if(empty($options['ocid'])) {
    $ocid = 0;
} else {
    $ocid = $options['ocid'];
    if(!is_numeric($ocid)) {
        echo "\nId. da oferta de curso é inválido.\n";
        $options['help'] = true;
    } else {
        $ocs = $saas->get_ofertas($ocid);
        if(!isset($ocs[$ocid])) {
            echo "\nId. da oferta de curso não foi localizado.\n";
            $options['help'] = true;
        }
    }
}

if ($options['help'] || !empty($unrecognized) || (empty($all) && empty($ocid) && empty($list))) {
        echo "
        Exporta dados para SAAS:
          \$ php {$argv[0]} [options]

        Options:
        -h, --help                  Mostra este auxílio
        -l, --list                  Lista ofertas de curso
        -a, --all                   Exporta dados de todos as ofertas de curso
        -d, --details               Exporta detalhes dos estudantes (notas, último acesso, etc)

            --ocid=<id da oferta de curso>  Exporta dados de uma oferta de curso específica

        Exemplos:
           \$ php {$argv[0]} -a -d
           \$ php {$argv[0]} -l
           \$ php {$argv[0]} --ocid=42 -d

        \n";
        exit;
}

$CFG->debug = DEBUG_NORMAL;     // Errors, warnings and notices

$ofertas_cursos = $saas->get_ofertas_cursos();
if(empty($ofertas_cursos)) {
    echo get_string('no_ofertas_cursos', 'report_saas_export');
    return;
}

$selected_ocs = array();
if(empty($ocid) || $list) {
    $ofertas_disciplinas_oc = $saas->get_ofertas_disciplinas(0, true);

    $show_polos = $saas->get_config('polo_mapping') != 'no_polo';
    $polos_oc = $show_polos ? $saas->get_polos_by_oferta_curso() : array();

    foreach($ofertas_cursos AS $ocid=>$oc) {
        if(isset($ofertas_disciplinas_oc[$ocid]) || ($show_polos && !empty($polos_oc[$ocid]))) {
            $selected_ocs[$ocid] = $oc;
        }
    }
} else {
    $selected_ocs[$ocid] = $ofertas_cursos[$ocid];
}

if($list) {
    echo "\nLista de ofertas de curso passíveis de exportação:\n";
    foreach($selected_ocs AS $id=>$oc) {
        echo "    {$id} - {$oc->nome} ({$oc->ano}/{$oc->periodo})\n";
    }
    echo "\n";
} else {
    echo 'Início: ' . date('d/m/Y H:i:s') . "\n";
    $time_start = microtime(true);

    try {
        list($count_errors, $errors, $count_sent_users, $count_sent_ods, $count_sent_polos) =
            $saas->send_data($selected_ocs, $details, false);
    } catch (Exception $e){
        var_dump($e->getMessage());
        exit;
    }


    $time_end = microtime(true);
    $execution_time = ($time_end - $time_start);
    if($execution_time <= 60) {
        $msg = round($execution_time, 1) . ' segundos';
    } else {
        $msg = round($execution_time/60, 2) . ' minutos';
    }
    echo "\nFim: " . date('d/m/Y H:i:s') . "\n";
    echo 'Tempo da exportação: ' . $msg. "\n";
    echo 'Chamadas de WS = ' . var_export($saas->count_ws_calls, true) . "\n";
    foreach($saas->count_ws_calls AS $wstype => $count) {
        echo "\t{$wstype}:\t{$count}\n";
    }

    echo "\nOfertas de disciplinas exportadas = {$count_sent_ods}\n";
    echo "Polos exportados = {$count_sent_polos}\n";
    foreach($count_sent_users AS $r=>$count) {
        echo get_string($r.'s', 'report_saas_export') . " exportados = {$count}\n";
    }
    echo "\n";

    if($count_errors > 0) {
        $n = min($count_errors, 3);
        echo "HOUVE {$count_errors} ERROS ao exportar os dados. Os primeiros {$n} estão listados abaixo:\n";
        for($i=0; $i < $n; $i++) {
            echo "     - {$errors[$i]}\n";
        }
    }

}
