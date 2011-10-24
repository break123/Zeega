/*

	ux.js
	
	the stuff in here should primarily be jQuery stuff that needs to be done after the dom is loaded
	Creating buttons, hovers, tabs, and other stuff here


*/

//	stuff that has to happen after the js fully loads

function initUX(){
	initHeaderUX();


}

$(document).ready(function() {
	

	$('.project-delete').click(function(){if(confirm('Delete Project?')){
		var id =$(this).data('id'); 
		$('.project-'+id).fadeOut(); 
		$.ajax({
				url: window.URL_PREFIX+'projects/'+id,
				type: 'DELETE',
				success: function(){
				console.log('done');
			}
		});
		}
	});
	
	$('#new-project').click(function(){
		console.log('creating new project');
		$.ajax({
				url: window.URL_PREFIX+'playgrounds/'+$('.playground-title').data('id')+'/project',
				type: 'POST',
				success: function(data){
				window.location=window.URL_PREFIX+'playground/'+$('.playground-title').data('short')+'/project/'+data;
			}
		});

	});

});