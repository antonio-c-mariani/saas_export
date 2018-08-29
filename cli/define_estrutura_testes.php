<?php

define('CLI_SCRIPT', true);
error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/lib.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params(
        array('help'     => false,
              'do'       => false,
              'update'   => false,
             ),
        array('h' => 'help',
              'd' => 'do',
              'u' => 'update',
             ));

$update_user_data = !empty($options['update']);
$really_do = !empty($options['do']);

if ($options['help'] || !$really_do) {
        echo "
        Define estrutura de testes:
          \$ php {$argv[0]} [options]

        Options:
        -h, --help                  Mostra este auxílio
        -d, --do                    Realmente definir estrutura de testes (serão criados usuários e cursos no Moodle)
        -u, --update                Atualiza dados de todos usuários, em particular o email e a senha

        Exemplos:
           \$ php {$argv[0]} -d -u

        \n";
        exit;
}

$dados = array(
    array('categoria' => array('TÉCNICO EM ELETROTÉCNICA', 'FORTALEZA', '1º Período'),
          'cursos'    => array('[2017/2] - 287264 - AMBIENTAÇÃO EM EAD',
                               '[2017/2] - 287265 - DESENHO TÉCNICO',
                               '[2017/2] - 287266 - ELETRICIDADE CA',
                               '[2017/2] - 287267 - ELETRICIDADE CC',
                               '[2017/2] - 287268 - ELETROMAGNETISMO',
                               '[2017/2] - 287269 - INFORMÁTICA APLICADA',
                               '[2017/2] - 287270 - SEGURANÇA EM INSTALAÇÕES E SERVIÇOS EM ELETRICIDADE',
                               ),
          'alunos'    => 61,
         ),
    array('categoria' => array('TÉCNICO EM ELETROTÉCNICA', 'FORTALEZA', '2º Período'),
          'cursos'    => array('[2018/1] - 304539 - ELETRÔNICA ANALÓGICA',
                               '[2018/1] - 304540 - ELETRÔNICA DIGITAL',
                               '[2018/1] - 304541 - INSTALAÇÕES ELÉTRICAS PREDIAIS E INDUSTRIAIS',
                               '[2018/1] - 304542 - INSTRUMENTAÇÃO E MEDIDAS ELÉTRICAS',
                               '[2018/1] - 304543 - MÁQUINAS ELÉTRICAS',
                               ),
          'alunos'    => 42,
         ),
    array('categoria' => array('TÉCNICO EM ELETROTÉCNICA', 'PACAJÚS', '1º Período'),
          'cursos'    => array('[2017/2] - 287271 - AMBIENTAÇÃO EM EAD',
                               '[2017/2] - 287272 - DESENHO TÉCNICO',
                               '[2017/2] - 287273 - ELETRICIDADE CA',
                               '[2017/2] - 287274 - ELETRICIDADE CC',
                               '[2017/2] - 287275 - ELETROMAGNETISMO',
                               '[2017/2] - 287276 - INFORMÁTICA APLICADA',
                               '[2017/2] - 287277 - SEGURANÇA EM INSTALAÇÕES E SERVIÇOS EM ELETRICIDADE',
                               ),
          'alunos'    => 62,
         ),
    array('categoria' => array('TÉCNICO EM ELETROTÉCNICA', 'PACAJÚS', '2º Período'),
          'cursos'    => array('[2018/1] - 304544 - ELETRÔNICA ANALÓGICA',
                               '[2018/1] - 304545 - ELETRÔNICA DIGITAL',
                               '[2018/1] - 304546 - INSTALAÇÕES ELÉTRICAS PREDIAIS E INDUSTRIAIS',
                               '[2018/1] - 304547 - INSTRUMENTAÇÃO E MEDIDAS ELÉTRICAS',
                               '[2018/1] - 304548 - MÁQUINAS ELÉTRICAS',
                               ),
          'alunos'    => 48,
         ),
    array('categoria' => array('TÉCNICO EM INFORMÁTICA', 'CAMPOS SALES', '1º Período'),
          'cursos'    => array('[2017/2] - 287278 - AMBIENTAÇÃO EM EDUCAÇÃO A DISTÂNCIA',
                               '[2017/2] - 287279 - ARQUITETURA DE COMPUTADORES',
                               '[2017/2] - 287280 - FUNDAMENTOS DE INFORMÁTICA',
                               '[2017/2] - 287282 - LÓGICA DE PROGRAMAÇÃO',
                               '[2017/2] - 287284 - SISTEMAS OPERACIONAIS',
                               '[2017/2] - 287285 - SOFTWARES UTILITÁRIOS',
                               ),
          'alunos'    => 56,
         ),
    array('categoria' => array('TÉCNICO EM INFORMÁTICA', 'CAMPOS SALES', '2º Período'),
          'cursos'    => array('[2018/1] - 304518 - EMPREENDEDORISMO',
                               '[2018/1] - 304519 - ÉTICA PROFISSIONAL',
                               '[2018/1] - 304520 - FUNDAMENTOS DE DESENVOLVIMENTO WEB',
                               '[2018/1] - 304521 - INSTALAÇÃO E MANUTENÇÃO DE COMPUTADORES',
                               '[2018/1] - 304522 - REDES DE COMPUTADORES',
                               '[2018/1] - 304523 - SEGURANÇA DA INFORMAÇÃO',
                               '[2018/1] - 304524 - SUPORTE AO USUÁRIO',
                               ),
          'alunos'    => 47,
         ),
    array('categoria' => array('TÉCNICO EM INFORMÁTICA', 'CAUCAIA', '1º Período'),
          'cursos'    => array('[2017/2] - 287294 - AMBIENTAÇÃO EM EDUCAÇÃO A DISTÂNCIA',
                               '[2017/2] - 287295 - ARQUITETURA DE COMPUTADORES',
                               '[2017/2] - 287296 - FUNDAMENTOS DE INFORMÁTICA',
                               '[2017/2] - 287298 - LÓGICA DE PROGRAMAÇÃO',
                               '[2017/2] - 287300 - SISTEMAS OPERACIONAIS',
                               '[2017/2] - 287301 - SOFTWARES UTILITÁRIOS',
                               ),
          'alunos'    => 56,
         ),
    array('categoria' => array('TÉCNICO EM INFORMÁTICA', 'CAUCAIA', '2º Período'),
          'cursos'    => array('[2018/1] - 304525 - EMPREENDEDORISMO',
                               '[2018/1] - 304526 - ÉTICA PROFISSIONAL',
                               '[2018/1] - 304527 - FUNDAMENTOS DE DESENVOLVIMENTO WEB',
                               '[2018/1] - 304528 - INSTALAÇÃO E MANUTENÇÃO DE COMPUTADORES',
                               '[2018/1] - 304529 - REDES DE COMPUTADORES',
                               '[2018/1] - 304530 - SEGURANÇA DA INFORMAÇÃO',
                               '[2018/1] - 304531 - SUPORTE AO USUÁRIO',
                               ),
          'alunos'    => 51,
         ),
    array('categoria' => array('TÉCNICO EM INFORMÁTICA', 'PACAJÚS', '1º Período'),
          'cursos'    => array('[2017/2] - 287286 - AMBIENTAÇÃO EM EDUCAÇÃO A DISTÂNCIA',
                               '[2017/2] - 287287 - ARQUITETURA DE COMPUTADORES',
                               '[2017/2] - 287288 - FUNDAMENTOS DE INFORMÁTICA',
                               '[2017/2] - 287290 - LÓGICA DE PROGRAMAÇÃO',
                               '[2017/2] - 287292 - SISTEMAS OPERACIONAIS',
                               '[2017/2] - 287293 - SOFTWARES UTILITÁRIOS',
                               ),
          'alunos'    => 60,
         ),
    array('categoria' => array('TÉCNICO EM INFORMÁTICA', 'PACAJÚS', '2º Período'),
          'cursos'    => array('[2018/1] - 304532 - EMPREENDEDORISMO',
                               '[2018/1] - 304533 - ÉTICA PROFISSIONAL',
                               '[2018/1] - 304534 - FUNDAMENTOS DE DESENVOLVIMENTO WEB',
                               '[2018/1] - 304535 - INSTALAÇÃO E MANUTENÇÃO DE COMPUTADORES',
                               '[2018/1] - 304536 - REDES DE COMPUTADORES',
                               '[2018/1] - 304537 - SEGURANÇA DA INFORMAÇÃO',
                               '[2018/1] - 304538 - SUPORTE AO USUÁRIO',
                               ),
          'alunos'    => 52,
         ),
    array('categoria' => array('TÉCNICO EM REDES DE COMPUTADORES', 'FORTALEZA', '1º Período'),
          'cursos'    => array('[2017/2] - 287259 - AMBIENTAÇÃO EM EDUCAÇÃO A DISTÂNCIA',
                               '[2017/2] - 287260 - ARQUITETURA DE COMPUTADORES',
                               '[2017/2] - 287261 - COMUNICAÇÃO DE DADOS',
                               '[2017/2] - 287262 - ELETRICIDADE PARA INFORMÁTICA',
                               '[2017/2] - 287263 - SISTEMAS OPERACIONAIS',
                               ),
          'alunos'    => 50,
         ),
    array('categoria' => array('TÉCNICO EM REDES DE COMPUTADORES', 'FORTALEZA', '2º Período'),
          'cursos'    => array('[2018/1] - 304506 - ADMINISTRAÇÃO DE SISTEMAS OPERACIONAIS',
                               '[2018/1] - 304507 - GERENCIAMENTO DE REDES',
                               '[2018/1] - 304508 - PROJETO DE REDES',
                               '[2018/1] - 304509 - REDES DE COMPUTADORES',
                               '[2018/1] - 304510 - SEGURANÇA DE REDES',
                               ),
          'alunos'    => 57,
         ),
    array('categoria' => array('TÉCNICO EM SEGURANÇA DO TRABALHO', 'FORTALEZA', '1º Período'),
          'cursos'    => array('[2017/2] - 287238 - AMBIENTAÇÃO EM EAD',
                               '[2017/2] - 287239 - DESENHO TÉCNICO',
                               '[2017/2] - 287240 - INFORMÁTICA BÁSICA',
                               '[2017/2] - 287241 - LEGISLAÇÃO E NORMAS TÉCNICAS E SMS',
                               '[2017/2] - 287242 - PORTUGUÊS INSTRUMENTAL',
                               '[2017/2] - 287243 - SEGURANÇA NA ELETROTÉCNICA',
                               '[2017/2] - 287244 - SEGURANÇA PORTUÁRIA E AQUAVIÁRIA',
                               '[2017/2] - 298263 - SEGURANÇA NA CONSTRUÇÃO NAVAL',
                               ),
          'alunos'    => 59,
         ),
    array('categoria' => array('TÉCNICO EM SEGURANÇA DO TRABALHO', 'FORTALEZA', '2º Período'),
          'cursos'    => array('[2018/1] - 304511 - COMBATE E PREVENÇÃO A SINISTROS E ÁREAS CLASSIFICADAS',
                               '[2018/1] - 304512 - INSPEÇÃO DE RISCOS',
                               '[2018/1] - 304513 - MÁQUINAS E EQUIPAMENTOS',
                               '[2018/1] - 304514 - MEDICINA DO TRABALHO',
                               '[2018/1] - 304515 - SEGURANÇA DO TRABALHO',
                               '[2018/1] - 304516 - SEGURANÇA NA INDÚSTRIA',
                               '[2018/1] - 304517 - SEGURANÇA NA INDÚSTRIA DA CONSTRUÇÃO CIVIL',
                               ),
          'alunos'    => 47,
         ),
    );


echo "\nDefinindo estrutura de testes para o plugin do SAAS\n";

$role_tutor_presencial = 'tutor_presencial';
saas_create_role($role_tutor_presencial, 'Tutor Presencial');

$role_tutor_distancia = 'tutor_distancia';
saas_create_role($role_tutor_distancia, 'Tutor Distância');

saas_create_user_custom_field('cpf', 'CPF');

foreach ($dados as $dad) {
    $cat_pai = '';
    $catid = 0;
    $catid_curso = 0;
    foreach ($dad['categoria'] as $cat) {
        $catid = saas_create_category($cat, $catid);
        if (empty($catid_curso)) {
           $catid_curso = $catid;
        }
    }

    foreach ($dad['cursos'] as $fullname) {
        if (preg_match('/.* - ([0-9]{6}) - .*/', $fullname, $matches)) {
            $shortname = $matches[1];
            $courseid = saas_create_course($fullname, $shortname, $catid);

            $username = "p{$shortname}";
            $userid = saas_create_user($username, "Professor {$shortname}", $update_user_data);
            saas_user_custom_data($userid, 'cpf', rand(11111111111, 99999999999));
            saas_enrol_user($username, $shortname, 'editingteacher');

            $username = "tp{$shortname}";
            $userid = saas_create_user($username, "Tutor Presencial {$shortname}", $update_user_data);
            saas_user_custom_data($userid, 'cpf', rand(11111111111, 99999999999));
            saas_enrol_user($username, $shortname, $role_tutor_presencial);

            $username = "td{$shortname}";
            $userid = saas_create_user($username, "Tutor Distância {$shortname}", $update_user_data);
            saas_user_custom_data($userid, 'cpf', rand(11111111111, 99999999999));
            saas_enrol_user($username, $shortname, $role_tutor_distancia);

            $assid = saas_create_assignment($shortname, 'Tarefa 1');
            $asscm = get_coursemodule_from_instance('assign', $assid);
            $assctx = context_module::instance($asscm->id);
            $ass = new assign($assctx, $asscm, null);

            $n = $dad['alunos'];
            for ($i=1; $i <= $n; $i++) {
                $username = "e{$i}-{$catid_curso}";
                $userid = saas_create_user($username, "Estudante {$username}", $update_user_data);
                saas_user_custom_data($userid, 'cpf', rand(11111111111, 99999999999));
                saas_enrol_user($username, $shortname, 'student');

                $grade = $ass->get_user_grade($userid, true);
                $grade->grader = 1;
                $grade->grade = rand(10, 100);
                $res = $ass->update_grade($grade);
            }
        } else {
            throw new Exception("Não localizado shortname no curso: '{$fullname}'");
        }
        echo '.';
    }

}
