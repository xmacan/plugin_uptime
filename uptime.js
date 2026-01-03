function applyFilter() {
	strURL = 'uptime_tab.php' +
		'?host_id=' + $('#host_id').val() +
		'&header=false';

	loadPageNoHeader(strURL);
}

function clearFilter() {
	strURL = 'uptime_tab.php?clear=1&header=false';
	loadPageNoHeader(strURL);
}

$(function() {
	$('#clear').unbind().on('click', function() {
		clearFilter();
	});

	$('#form_uptime').unbind().on('submit', function(event) {
		event.preventDefault();
		applyFilter();
	});
});




