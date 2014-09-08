jQuery.noConflict();
jQuery( document ).ready(function( $ ) {

    //Recarrega a página ao fechar a Modal (fix this)
    $('.saas-bt-close').click(function() {
        window.location.reload();
    });

    //Cria a tooltip sobre os cursos moodle.
    $(".select_moodle_course").mouseenter(function() {
        $(this).tooltip('show');
        $(this).css('cursor', 'pointer');
    });

    //Destroi a tooltip quando o mouse sai de cima do curso moodle.
    $(".select_moodle_course").mouseleave(function() {
        $(this).tooltip('destroy');
    });

    //Desfaz um mapeamento.
    $('.delete_bt').click(function(element) {
        $.post("delete_mapping.php",
        {
            uid:element.target.getAttribute('uid'),
            id:element.target.getAttribute('id'),
            action:'delete_one'
        })
        .done(function() {
            window.location.reload();
        });
    });

    //Desfaz mais de uma mapeamento.
    $('.delete_many_offers_bt').click(function(element) {
        $.post("delete_mapping.php",
        {
            id:element.target.getAttribute('id'),
            uid:-1,
            action:'delete_many_offers'
        }).done(function() {
            window.location.reload();
        });
    });

    //Controles da Modal da árvore de categorias do Moodle.
    $('.moodle_map_bt').click(function(saas) {

        saas.preventDefault();

        //Mostra a modal
        $('#cursos_moodle_modal').modal('show');

        //Adiciona o nome da oferta de disciplina que está sendo mapeada na modal.
        $('<h4> Oferta de Disciplina: ' +saas.target.getAttribute('od_nome')+ '</h4>').insertAfter('.modal_cursos_moodle_title');
        $('<h4> Oferta de Curso: ' +saas.target.getAttribute('oc_nome')+ '</h4>').insertAfter('.modal_cursos_moodle_title');

        //Define inicialmente as categorias que estão abertas
        $('.tree li:has(ul)').addClass('parent_li');
        $('.parent_li').css('display', 'none');
        $('.category-root').css('display', 'list-item');
        var primeiro_nivel = $('.category-root').children('ul');
        primeiro_nivel.children('li').show();

        //Expande e contrai as categorias.
        $('.tree li.parent_li > span').on('click', function (e) {
            var children = $(this).siblings('ul').children('li');

            if (children.is(':visible')) {
                children.hide('fast');
            } else {
                children.show('fast');
                children.find('li').hide('fast');
            }
        });

        $('.select_moodle_course').click(function(moodle) {
            var uid_saas = saas.target.getAttribute('id');
            var id_moodle = moodle.target.getAttribute('id');

            if (uid_saas && id_moodle) {
                $.post("save_mapping.php",
                    {
                      uid:uid_saas,
                      id:id_moodle
                    },
                    function(data,status){
                        window.location.reload();
                    }
                );
            }
        });
        return false;
    });

    $('.saas_map_bt').click(function(saas) {

        saas.preventDefault();

        var oc_uid = saas.target.getAttribute('id_da_modal');
        $('#' + oc_uid).modal('show');
        
        $('.saas-bt-save').click(function(moodle) {
            var lista_de_ofertas = $('#'+oc_uid + ' .lista_de_ofertas');
            var checkbox = lista_de_ofertas.children('.od_checkbox');
            
            $.each(checkbox, function(key, chk) {
                if(chk.checked) {
                    //Finish this, create a <tr>.
                    //$('<h4> Oferta de Curso </h4>').insertAfter('.tr' + oc_uid);
                }
            });
        });

        return false;
    });

});
