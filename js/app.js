$(document).ready(function(){
   
    $('.saas-bt-close').click(function() {
    	window.location.reload();
    });
    
    $('.delete_bt').click(function(element) {	
	   	$.post("delete_mapping.php",
		{
			uid:element.target.getAttribute('uid'),
			id:element.target.getAttribute('id'),
			action:'delete_one'
		})
        .done(function() {
			window.location.reload();
			//Por enquanto está redirecionando a página.	
        	/*
			var div_to_delete = '#' + element.target.getAttribute('id') + '-' + element.target.getAttribute('uid');
			var parent_div = $(div_to_delete).parent().parent();
			
			$('div').remove(div_to_delete);
			
			if (parent_div.children().is(':empty')) {
				parent_div.children().html('<div><button id="2" type="button" class="btn btn-default btn-xs moodle_map_bt">Adicionar</button></div>');
			}
			*/ 
		});
    });

    $('.delete_many_offers_bt').click(function(element) {	
	   	$.post("delete_mapping.php",
		{
			id:element.target.getAttribute('id'),
			uid:-1,
			action:'delete_many_offers'
		})
        .done(function() {
			window.location.reload();
		});
    });


    
    $('.moodle_map_bt').click(function(saas) {
    	$('.select_moodle_course').click(function(moodle) {
	   		var uid_saas = saas.target.getAttribute('id');
	   		var id_moodle = moodle.target.getAttribute('id');
	   		
	   		if (!uid_saas) {
	   			uid_saas = -1;
	   		}
	   		
	   		if (!id_moodle) {
	   			id_moodle = -1;
	   		}

	   		$.post("save_mapping.php",
		    {
		      uid:uid_saas,
		      id:id_moodle
		    },
		    function(data,status){
		      window.location.reload();
		    });
   		});

    	$('#cursos_moodle_modal').modal('show');
    	$('<h4> Oferta: ' +saas.target.getAttribute('oferta')+ '</h4>').insertAfter('.modal_cursos_moodle_title');
		
		$('.tree li:has(ul)').addClass('parent_li').find(' > span').attr('title', 'Collapse this branch');
		$('.tree li.parent_li > span').on('click', function (e) {
	  		var children = $(this).parent('li.parent_li').find(' > ul > li');

	    	if (children.is(":visible")) {
	        	children.hide('fast');
	        	$(this).attr('title', 'Expand this branch').find(' > i').addClass('icon-plus-sign').removeClass('icon-minus-sign');
	    	} else {
	        	children.show('fast');
	        	$(this).attr('title', 'Collapse this branch').find(' > i').addClass('icon-minus-sign').removeClass('icon-plus-sign');
	    	}
        });  
    });

    $('.saas_map_bt').click(function(moodle) {
    	$('.select_saas_offer').click(function(saas) {
	   		var uid_saas = saas.target.getAttribute('id');
	   		var id_moodle = moodle.target.getAttribute('id');
	   		
	   		if (!uid_saas) {
	   			uid_saas = -1;
	   		}
	   		
	   		if (!id_moodle) {
	   			id_moodle = -1;
	   		}

	   		$.post("save_mapping.php",
		    {
		      uid:uid_saas,
		      id:id_moodle
		    },
		    function(data,status){
		      window.location.reload();
		    });
   		});

    	$('#ofertas_saas_modal').modal('show');
		
		$('.tree li:has(ul)').addClass('parent_li').find(' > span').attr('title', 'Collapse this branch');
		$('.tree li.parent_li > span').on('click', function (e) {
	  		var children = $(this).parent('li.parent_li').find(' > ul > li');

	    	if (children.is(":visible")) {
	        	children.hide('fast');
	        	$(this).attr('title', 'Expand this branch').find(' > i').addClass('icon-plus-sign').removeClass('icon-minus-sign');
	    	} else {
	        	children.show('fast');
	        	$(this).attr('title', 'Collapse this branch').find(' > i').addClass('icon-minus-sign').removeClass('icon-plus-sign');
	    	}
        });  
    });
});