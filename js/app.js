$(document).ready(function(){
    $('.saas-bt-close').click(function() {
    	window.location.reload();
    });

    $('.moodle_bt').click(function(uid) {
    	$('.select_moodle_course').click(function(id) {
	   		$.post("save_mapping.php",
		    {
		      uid:uid.target.getAttribute('id'),
		      id:id.target.getAttribute('id'),
		      mapping_type:'one_to_one'
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
});