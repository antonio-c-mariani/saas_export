$(document).ready(function(){
   
    $('.saas-bt-close').click(function() {
    	window.location.reload();
    });
    
    $('.delete_bt').click(function(element) {	
	   	$.post("delete_mapping.php",
		{
			uid:element.target.getAttribute('uid'),
			id:element.target.getAttribute('id')
		})
        .done(function() {
			var div_to_delete = '#' + element.target.getAttribute('id') + '-' + element.target.getAttribute('uid');
			$('div').remove(div_to_delete);
		});
    });
    
    $('.moodle_map_bt').click(function(saas) {
    	$('.select_moodle_course').click(function(moodle) {
	   		$.post("save_mapping.php",
		    {
		      uid:saas.target.getAttribute('id'),
		      id:moodle.target.getAttribute('id')
		    },
		    function(data,status){
		      window.location.reload();
		    });
   		});

    	$('#cursos_moodle_modal').modal('show');
		
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

    $('.saas_map_bt').click(function(saas) {
    	$('.select_saas_offer').click(function(moodle) {
	   		$.post("save_mapping.php",
		    {
		      uid:saas.target.getAttribute('id'),
		      id:moodle.target.getAttribute('id')
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