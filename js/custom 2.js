/* --- A place where you can add your own code -- */

$(document).on("click", "#button-1985", function(e) {
	e.preventDefault();
	$('#button-1984').trigger("click");
});
