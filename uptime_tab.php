<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2021-2024 Petr Macek                                      |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | https://github.com/xmacan/                                              |
 | https://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

chdir('../../');
include_once('./include/auth.php');
include_once('./lib/snmp.php');
include_once('./plugins/uptime/functions.php');

set_default_action();

$selectedTheme = get_selected_theme();

if (get_request_var('clear')) {
	unset($_SESSION['plugin_uptime_host_id']);
}

switch (get_request_var('action')) {
	case 'ajax_hosts':

		$sql_where = '';
		get_allowed_ajax_hosts (false, 'applyFilter', $sql_where);

		break;

	default:
		general_header();
		uptime_display_form();

		if (get_filter_request_var('host_id')) {
			uptime_display_events(get_request_var('host_id'));
		} elseif (isset($_SESSION['plugin_uptime_host_id'])) {
			uptime_display_events($_SESSION['plugin_uptime_host_id']);
		} else {
			uptime_stats();
		}
		bottom_footer();

		break;
}


function uptime_display_form() {
	global $config;

	print get_md5_include_js($config['base_path'].'/plugins/uptime/uptime.js');

	if (get_filter_request_var('host_id')) {
		$host_id = get_filter_request_var('host_id');
	} else if (isset($_SESSION['plugin_uptime_host_id'])) {
		$host_id = $_SESSION['plugin_uptime_host_id'];
	} else {
		$host_id = -1;
	}

	$host_where = '';

	form_start(html_escape(basename($_SERVER['PHP_SELF'])), 'form_uptime');

	html_start_box('<strong>Uptime</strong>', '100%', '', '3', 'center', '');

	print "<tr class='even noprint'>";
	print "<td>";
	print "<table class='filterTable'>";
	print "<tr>";

	print html_host_filter($host_id, 'applyFilter', $host_where, false, true);

	print '<td>';
	print '<input type="submit" class="ui-button ui-corner-all ui-widget" id="refresh" value="' . __('Go') . '" title="' . __esc('Find') . '">';
	print '<input type="button" class="ui-button ui-corner-all ui-widget" id="clear" value="' . __('Clear') . '" title="' . __esc('Clear Filters') . '">';
	print '</td>';
	print '</tr>';
	print '</table>';

	form_end(false);

	html_end_box();
}

function uptime_stats() {
	echo 'tady budou statistiky';
}





