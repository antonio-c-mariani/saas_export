<?php

function teste_get_ofertas_cursos() {
    $objs = array();
    for($i=1; $i <= 5; $i++) {
        $nome_oferta = new stdClass(); 
        $nome_oferta->nome = 'Oferta Curso ' . $i;
        $obj = new stdClass();
        $obj->uid = 'ofcurso' . $i;
        $obj->curso =  $nome_oferta;
        $obj->ano = '2014';
        $obj->periodo = 1;
        $objs[] = $obj;
    }
    return json_encode($objs);
}

function teste_get_ofertas_disciplinas($oferta_curso_uid) {
    $nome_oferta = new stdClass(); 
    $obj1 = new stdClass();
    $obj1->uid = 'od1';
    $nome_oferta->nome = 'Grafos';
    $obj1->disciplina = $nome_oferta;
    $obj1->inicio = '12/03/2014';
    $obj1->fim = '12/05/2014';

    $nome_oferta = new stdClass();    
    $obj2 = new stdClass();
    $obj2->uid = 'od2';
    $nome_oferta->nome = 'POO';
    $obj2->disciplina = $nome_oferta;
    $obj2->inicio = '13/03/2014';
    $obj2->fim = '13/05/2014';

    $nome_oferta = new stdClass();
    $obj3 = new stdClass();
    $obj3->uid = 'od3';
    $nome_oferta->nome = 'Redes';
    $obj3->disciplina = $nome_oferta;
    $obj3->inicio = '14/03/2014';
    $obj3->fim = '14/05/2014';

    $nome_oferta = new stdClass();
    $obj4 = new stdClass();
    $obj4->uid = 'od4';
    $nome_oferta->nome = 'Redes';
    $obj4->disciplina = $nome_oferta;
    $obj4->inicio = '15/03/2014';
    $obj4->fim = '15/05/2014';

    $nome_oferta = new stdClass();
    $obj5 = new stdClass();
    $obj5->uid = 'od5';
    $nome_oferta->nome = 'Matemática Discreta';
    $obj5->disciplina = $nome_oferta;
    $obj5->inicio = '16/03/2014';
    $obj5->fim = '16/05/2014';
    
    $nome_oferta = new stdClass();
    $obj6 = new stdClass();
    $obj6->uid = 'od6';
    $nome_oferta->nome = 'Teoria da Computação';
    $obj6->disciplina = $nome_oferta;
    $obj6->inicio = '17/03/2014';
    $obj6->fim = '17/05/2014';
    
    $nome_oferta = new stdClass();
    $obj7 = new stdClass();
    $obj7->uid = 'od7';
    $nome_oferta->nome = 'POO2';
    $obj7->disciplina = $nome_oferta;
    $obj7->inicio = '17/03/2014';
    $obj7->fim = '17/05/2014';
    
    $array1 = array($obj1, $obj2);
    $array2 = array($obj3, $obj4);
    $array3 = array($obj5);
    $array4 = array($obj6);
    $array5 = array($obj7);
    
    $objs = array('ofcurso1' => $array1, 
                  'ofcurso2' => $array2,
                  'ofcurso3' => $array3,
                  'ofcurso4' => $array4,
                  'ofcurso5' => $array5);

    return json_encode($objs[$oferta_curso_uid]);
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