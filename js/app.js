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

    //Controles da Modal da árvore de categorias do Moodle.
    $('.add_map_bt').click(function(saas) {

        saas.preventDefault();
        //Não permite que a modal feche ao receber um click fora da área da modal, e desativa as teclas do teclado.
        $('#cursos_moodle_modal').modal({
            backdrop: 'static',
            keyboard: false
	    });

        //Mostra a modal
        $('#cursos_moodle_modal').modal('show');

        //Adiciona o nome da oferta de disciplina que está sendo mapeada na modal.
        $('<h5 style="margin-left: 2em;"> Oferta de disciplina: <font color="darkblue">' +saas.target.getAttribute('od_nome')+ '</font></h5>').insertAfter('.modal_cursos_moodle_title');
        $('<h5 style="margin-left: 2em;"> Oferta de curso: <font color="darkblue">' +saas.target.getAttribute('oc_nome')+ '</font></h5>').insertAfter('.modal_cursos_moodle_title');

        //Define inicialmente as categorias que estão abertas
        $('.tree li:has(ul)').addClass('parent_li');
        $('.category-root').css('display', 'list-item');
        $('.folder-close').children('ul').children('li').hide();

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
            var group_map_id = saas.target.getAttribute('id');
            var courseid= moodle.target.getAttribute('id');
            if (group_map_id && courseid) {
                $.post("save_mapping.php", { group_map_id:group_map_id, courseid:courseid },
                    function(data,status){
                        window.location.reload();
                    }
                );
            }
        });
        return false;
    });

});
