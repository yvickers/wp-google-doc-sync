(function( $ ) {
	'use strict';

	/**
	 * All of the code for your Dashboard-specific JavaScript source
	 * should reside in this file.
	 *
	 * Note that this assume you're going to use jQuery, so it prepares
	 * the $ function reference to be used within the scope of this
	 * function.
	 *
	 * From here, you're able to define handlers for when the DOM is
	 * ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * Or when the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and so on.
	 *
	 * Remember that ideally, we should not attach any more than a single DOM-ready or window-load handler
	 * for any particular page. Though other scripts in WordPress core, other plugins, and other themes may
	 * be doing this, we should try to minimize doing that in our own work.
	 */

	$(function(){
		$(".js-tabs").tabs();

		$(".js-repeater-add").click(function(){
			var template = $("#"+$(this).data('template')),
				template_html = template.html(),
				repeater = $('.'+$(this).data('count'));

			template_html = replaceAll(template_html,'{{count}}',repeater.length);
			var templateVars = $(this).data('templateVars');
			for(var i in templateVars){
				template_html = replaceAll(template_html,'{{'+i+'}}',templateVars[i]);
			}

			if(repeater.length > 0){
				repeater.last().after(template_html);
			}else{
				$(this).before(template_html);
			}
		});

		$(".js-process-button").click(function(){
			$(this).attr('disabled','disabled');
			$(this).append('&nbsp;<i class="fa fa-spinner fa-pulse"></i>');
		});
	});

function replaceAll(string, find, replace) {
	return string.replace(new RegExp(escapeRegExp(find), 'g'), replace);
}

function escapeRegExp(string) {
	return string.replace(/([.*+?^=!:${}()|\[\]\/\\])/g, "\\$1");
}

})( jQuery );
