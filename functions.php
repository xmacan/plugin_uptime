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
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/





function uptime_get_allowed_devices($user_id, $array = false) {

	$x  = 0;
	$us = read_user_setting('hide_disabled', false, false, $user_id);

	if ($us == 'on') {
		set_user_setting('hide_disabled', '', $user_id);
	}

	$allowed = get_allowed_devices('', 'null', -1, $x, $user_id);

	if ($us == 'on') {
		set_user_setting('hide_disabled', 'on', $user_id);
	}

	if (cacti_count($allowed)) {
		if ($array) {
			return(array_column($allowed, 'id'));
		}
		return implode(',', array_column($allowed, 'id'));
	} else {
		return false;
	}
}


function uptime_display_events($host_id) {

	$states = array(
		'O' => 'Polling ok',
		'U' => 'Device up',
		'R' => 'Was restarted',
		'F' => 'Failed polls',
		'N' => 'New device added',
		'D' => 'Went down',
	);

	request_validation();

	$sql_limit = '';
	$sql_order = 'ORDER BY id DESC ';


	if (!in_array($host_id, uptime_get_allowed_devices ($_SESSION['sess_user_id'], true)))    {
		print 'You haven\'t  permission for ' . $host_id;
		return false;
	} else {
		$_SESSION['plugin_uptime_host_id'] = $host_id;
	}

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} elseif (get_request_var('rows')) {
		$rows = get_filter_request_var('rows');
	} else {
		$rows = 50;
	}

	$sql_limit = ' LIMIT ' . ($rows*((int)get_request_var('page')-1)) . ',' . $rows;

	$results = db_fetch_assoc_prepared ("SELECT * FROM plugin_uptime_data
		WHERE host_id = ?
		$sql_order
		$sql_limit",
		array($host_id));

	$total_rows = db_fetch_cell_prepared('SELECT COUNT(id)
		FROM plugin_uptime_data
		WHERE host_id = ?',
		array($host_id));

	$oldest = db_fetch_cell_prepared('SELECT FROM_UNIXTIME(timestamp) FROM plugin_uptime_data
		WHERE host_id = ?
		ORDER BY id LIMIT 1',
		array($host_id));

	$restarts = db_fetch_cell_prepared("SELECT COUNT(*) FROM plugin_uptime_data
		WHERE id = ? AND state = 'R' AND timestamp >= NOW() - INTERVAL 100 DAY",
		array($host_id));

	$last_restart = db_fetch_cell_prepared("SELECT FROM_UNIXTIME(timestamp) FROM plugin_uptime_data
		WHERE id = ? AND state = 'R' ORDER BY id DESC LIMIT 1",
		array($host_id));

	$top_uptime = db_fetch_cell_prepared('SELECT MAX(uptime) FROM plugin_uptime_data
		WHERE host_id = ?',
		array($host_id));

	$top_uptime = get_daysfromtime($top_uptime);

	print '<b>';
	print 'Oldest record: ' . $oldest . ' / ';
	print 'Number of restart in last 100 days: ' . $restarts . ' / ';
	print 'Last restart: ' . $last_restart . ' / ';
	print 'Top uptime: ' . $top_uptime;
	print '</b><br/><br/>';

	$display_text = array(
		'timestamp' => array(
			'display' => __('Date', 'servcheck'),
			'sort'    => 'ASC'
		),
		'state' => array(
			'display' => __('State', 'servcheck'),
			'sort'    => 'ASC'
		),
		'uptime' => array(
			'display' => __('Uptime', 'servcheck'),
			'sort'    => 'ASC'
		),
		'info' => array(
			'display' => __('Info', 'servcheck'),
			'sort'    => 'ASC'
		),
	);

	$columns = cacti_sizeof($display_text);

	if (basename($_SERVER['PHP_SELF']) == 'uptime_tab.php') {
		$nav = html_nav_bar(htmlspecialchars(basename($_SERVER['PHP_SELF'])) . "?host_id=$host_id", MAX_DISPLAY_PAGES, (int)get_request_var('page'), $rows, $total_rows, $columns, 'Uptime/restart history', 'page', 'main');
	}

	form_start(htmlspecialchars(basename($_SERVER['PHP_SELF'])), 'chk');

	if (basename($_SERVER['PHP_SELF']) == 'uptime_tab.php') {
		print $nav;
	}

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 0);

	$i = 0;

	if (cacti_sizeof($results)) {
		foreach ($results as $result)   {
			$i++;
			form_alternate_row("x$i", true);
			print "<td class='nowrap'>" . date('Y-m-d H:i',$result['timestamp']) . '</td>';
			print "<td class='nowrap'>" . $states[$result['state']] . '</td>';
			print "<td class='nowrap'>" . get_daysfromtime($result['uptime']) . '</td>';
			print "<td class='nowrap'>" . $result['info'] . '</td>';
			form_end_row();
		}

	} else {
		print "<tr class='tableRow'><td colspan='" . $columns . "'><em>" . __('Empty', 'servcheck') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (basename($_SERVER['PHP_SELF']) == 'uptime_tab.php') {
		if (cacti_sizeof($results)) {
			print $nav;
		}
	}
}


function request_validation() {

	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'timestamp',
			'options' => array('options' => 'sanitize_search_string')
		),
			'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_uptime_data');
}

