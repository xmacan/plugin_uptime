<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2019-2024 Petr Macek                                      |
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

function plugin_uptime_install () {
	api_plugin_register_hook('uptime', 'poller_bottom', 'uptime_poller_bottom', 'setup.php');
	api_plugin_register_hook('uptime', 'host_edit_bottom', 'uptime_host_edit_bottom', 'setup.php');
	api_plugin_register_hook('uptime', 'rrd_graph_graph_options', 'uptime_rrd_graph_graph_options', 'setup.php');
	api_plugin_register_hook('uptime', 'top_header_tabs', 'uptime_show_tab', 'setup.php');
	api_plugin_register_hook('uptime', 'top_graph_header_tabs', 'uptime_show_tab', 'setup.php');

	api_plugin_register_realm('uptime', 'setup.php,uptime.php,uptime_tab.php', 'Plugin uptime - view', 1);

	uptime_setup_database();
}


function uptime_setup_database() {
	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false,'auto_increment' => true);
	$data['columns'][] = array('name' => 'host_id', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'uptime', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'timestamp', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'state', 'type' => 'varchar(1)', 'NULL' => false);
	$data['columns'][] = array('name' => 'info', 'type' => 'varchar(100)', 'NULL' => false);

	$data['primary'] = 'id';
	$data['type'] = 'InnoDB';
	$data['comment'] = 'uptime data';
	api_plugin_db_table_create ('uptime', 'plugin_uptime_data', $data);
}


function plugin_uptime_uninstall () {
	if (sizeof(db_fetch_assoc("SHOW TABLES LIKE 'plugin_uptime_data'")) > 0 ) {
		db_execute("DROP TABLE `plugin_uptime_data`");
	}
}


function plugin_uptime_version() {
	global $config;

	$info = parse_ini_file($config['base_path'] . '/plugins/uptime/INFO', true);
	return $info['info'];
}


function plugin_uptime_check_config() {
	return true;
}


function uptime_show_tab() {
	global $config;

	if (api_user_realm_auth('uptime.php')) {
		$cp = false;

		if (basename($_SERVER['PHP_SELF']) == 'uptime_tab.php') {
			$cp = true;
		}
		print '<a href="' . $config['url_path'] . 'plugins/uptime/uptime_tab.php"><img src="' . $config['url_path'] .
			'plugins/uptime/images/tab_uptime' . ($cp ? '_down': '') . '.gif" alt="uptime" align="absmiddle" border="0"></a>';
	}
}


function seconds_to_time($secs) {
	$dt = new DateTime('@' . $secs, new DateTimeZone('UTC'));
	return ($dt->format('z') . "d " . $dt->format('G') . "h " . $dt->format('i') . "m " . $dt->format('s') . "s" );
}

function uptime_rrd_graph_graph_options($data) {
	global $config;

	$host_id = db_fetch_cell_prepared('SELECT host_id FROM graph_local WHERE id = ?', 
		array ($data['graph_id']));
	$restarts = db_fetch_assoc_prepared ("SELECT timestamp FROM plugin_uptime_data WHERE
		host_id = ? AND state = 'R' AND timestamp BETWEEN ? AND ? ORDER BY timestamp desc",
		array($host_id, $data['start'], $data['end']));

	$count = 0;
	foreach ($restarts as $restart) {
		if ($count < 20) {
			$data['txt_graph_items'] .= RRD_NL . "VRULE:" . $restart['timestamp'] . "#000000:" . RRD_NL;
		}
		$count++;
	}

	if ($count > 0) {
		$data['graph_opts'] .=  'COMMENT:" Device was restarted ' . $count . ' x - black vert. line! \\n"' . RRD_NL;
		if ($count > 20) {
			$data['graph_opts'] .=  'COMMENT:" Displaying only last 20 restarts \\n"' . RRD_NL;
		}
	}

	return $data;
}


function uptime_poller_bottom() {
	global $config;

	$start = microtime(true);
	$poller_interval = read_config_option("poller_interval");

/*
states:
O - polling ok
U - device up
R - was restarted
F - failed polls
N - new device added
D - went down

*/

	// select all hosts which has snmp enabled
	$hosts = db_fetch_assoc ("SELECT id, snmp_sysUpTimeInstance AS uptime
		FROM host
		WHERE disabled != 'on' AND availability_method IN (1,2,5,6)");

	$count = cacti_sizeof($hosts);

	if ($count > 0) {
		foreach ($hosts as $host) {

			// remove ms
 			$host['uptime'] = $host['uptime']/100;

			$old = db_fetch_row_prepared ('SELECT id, uptime, state, timestamp
				FROM plugin_uptime_data
				WHERE host_id = ?
				ORDER BY id DESC LIMIT 1',
				array($host['id']));

			if (!$old) { // adding new device
				db_execute_prepared("INSERT INTO plugin_uptime_data
					(host_id, uptime, timestamp, state, info)
					VALUES
					(?, ? , unix_timestamp(),'N', concat('New device added ', now()))",
					array ($host['id'], $host['uptime']));

				if ($host['uptime'] > 0) { // counting last restart

					db_execute_prepared("INSERT INTO plugin_uptime_data
						(host_id, uptime, timestamp, state, info)
						VALUES
						(?, ?, ?, 'R', 'New device added, counting uptime back')",
						array ($host['id'], $host['uptime'], (time() - $host['uptime']))); 

					continue;
				} else { // new device is down or failed poll
					db_execute_prepared("INSERT INTO plugin_uptime_data
						(host_id, uptime, timestamp, state, info)
						VALUES
						(?, 0, unix_timestamp(), 'D', 'New device added, it is down now or failed poll')",
						array ($host['id']));
					continue;
				}
			}


			if ($host['uptime'] < $old['uptime']) {
				db_execute_prepared("INSERT INTO plugin_uptime_data 
					(host_id,uptime,timestamp,state,info) 
					VALUES 
					( ? , ? , unix_timestamp(), 'R', 'Device restart, uptime was " . seconds_to_time($old['uptime']) . "')",
					array ($host['id'], $host['uptime']));
				continue;
			}

			if ($host['uptime'] == 0 && $old['uptime'] > 0) { // went down
				db_execute_prepared("INSERT INTO plugin_uptime_data
					(host_id,uptime,timestamp,state,info)
					VALUES
					( ? ,0, unix_timestamp(), 'D', 'Device went down, uptime was " . seconds_to_time($old['uptime']) . "')",
					array($host['id']));

				continue;
			}

			if ($host['uptime'] == 0 && $old['uptime'] == 0) { // down or failed poll continue
				if ($old['state'] != 'D') { // failed polls continue

					db_execute_prepared("INSERT INTO plugin_uptime_data
						(host_id, uptime, timestamp, state, info)
						VALUES
						(? ,? , unix_timestamp(),'D', 'Device down, uptime was 0')",
						array ($host['id'], $host['uptime']));

					continue;
				}
			}


			if ($host['uptime'] > 0 && $host['uptime'] == $old['uptime']) { // failed polls
				if ($old['state'] == 'F') { // failed polls continue
					continue;
				} else {
					db_execute_prepared("INSERT INTO plugin_uptime_data
						(host_id, uptime, timestamp, state, info)
						VALUES
						(? ,? , unix_timestamp(),'F', 'Failed poll')",
						array ($host['id'], $host['uptime']));
					continue;
				}
			}

			if ($host['uptime'] > $old['uptime']) {
				if ($old['state'] == 'F') { // failed polls finished
					db_execute_prepared("INSERT INTO plugin_uptime_data 
						(host_id,uptime,timestamp,state,info) 
						VALUES ( ? , ? , unix_timestamp(),'O', 'Polling is ok')",
						array ($host['id'], $host['uptime']));
					continue;
				} elseif ($old['state'] == 'D') {
					db_execute_prepared("INSERT INTO plugin_uptime_data
						(host_id,uptime,timestamp,state,info) 
						VALUES ( ? ,? , unix_timestamp(),'U', 'Device is up')",
						array ($host['id'], $host['uptime']));
						
					continue;
				} elseif ($old['state'] == 'U') { // only update uptime
					db_execute_prepared("UPDATE plugin_uptime_data
						SET uptime = ?, timestamp = unix_timestamp(),
						info = 'Only updating uptime'
						WHERE host_id = ? AND id = ?",
						array ($host['uptime'], $host['id'], $old['id']));

					continue;

				} else { // going up
					db_execute_prepared("INSERT INTO plugin_uptime_data
						(host_id,uptime,timestamp,state,info) 
						VALUES ( ? ,? , unix_timestamp(),'U', 'Device is up')",
						array ($host['id'], $host['uptime']));

					continue;
				}
			}
		}
	} // we have some hosts

	$end = microtime(true);
	cacti_log('PLUGIN UPTIME STATS: Duration: ' . round($end-$start,2) .', Hosts: ' . $count);
}


function uptime_host_edit_bottom() {
	if (!api_user_realm_auth('setup.php')) {
		print __('Permission denied', 'uptime') . '<br/><br/>';
		return false;
	}

	include_once('./plugins/uptime/functions.php');
	print '<br/><br/>';
	uptime_display_events(get_request_var('id'));
}


