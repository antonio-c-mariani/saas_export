<?php

function teste_get_ofertas_cursos() {
    $objs = array();
    for($i=1; $i <= 5; $i++) {
        $obj = new stdClass();
        $obj->uid = 'ofcurso' . $i;
        $obj->nome = 'Oferta Curso ' . $i;
        $obj->ano = '2014';
        $obj->periodo = 1;
        $objs[] = $obj;
    }
    return $objs;
}

function teste_get_ofertas_disciplinas() {
    $objs = array();

    $obj = new stdClass();
    $obj->uid = 'od1';
    $obj->disciplina_nome = 'Grafos';
    $obj->oferta_curso_uid = 'ofcurso1';
    $obj->inicio = '12/03/2014';
    $obj->fim = '12/05/2014';
    $obj->ano = '2014';
    $objs[] = $obj;

    $obj = new stdClass();
    $obj->uid = 'od2';
    $obj->disciplina_nome = 'POO';
    $obj->oferta_curso_uid = 'ofcurso1';
    $obj->inicio = '13/03/2014';
    $obj->fim = '13/05/2014';
    $obj->ano = '2014';
    $objs[] = $obj;

    $obj = new stdClass();
    $obj->uid = 'od3';
    $obj->disciplina_nome = 'Redes';
    $obj->oferta_curso_uid = 'ofcurso1';
    $obj->inicio = '14/03/2014';
    $obj->fim = '14/05/2014';
    $obj->ano = '2014';
    $objs[] = $obj;

    $obj = new stdClass();
    $obj->uid = 'od4';
    $obj->disciplina_nome = 'Redes';
    $obj->oferta_curso_uid = 'ofcurso2';
    $obj->inicio = '15/03/2014';
    $obj->fim = '15/05/2014';
    $obj->ano = '2014';
    $objs[] = $obj;

    $obj = new stdClass();
    $obj->uid = 'od5';
    $obj->disciplina_nome = 'Chuva';
    $obj->oferta_curso_uid = 'ofcurso5';
    $obj->inicio = '16/03/2014';
    $obj->fim = '16/05/2014';
    $obj->ano = '2014';
    $objs[] = $obj;

    $obj = new stdClass();
    $obj->uid = 'od6';
    $obj->disciplina_nome = 'Trovão';
    $obj->oferta_curso_uid = 'ofcurso5';
    $obj->inicio = '17/03/2014';
    $obj->fim = '17/05/2014';
    $obj->ano = '2014';
    $objs[] = $obj;

    return $objs;
}

function teste_get_polos() {
    $objs = array();
    for($i=1; $i <= 10; $i++) {
        $obj = new stdClass();
        $obj->uid = 'pl' . $i;
        $obj->nome = 'Polo ' . $i;
        $objs[] = $obj;
    }
    return $objs;
}

?>
