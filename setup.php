<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2019-2020 Petr Macek                                      |
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

function plugin_uptime_install ()	{
        api_plugin_register_hook('uptime', 'poller_bottom', 'uptime_poller_bottom', 'setup.php');
	api_plugin_register_hook('uptime', 'host_edit_bottom', 'uptime_host_edit_bottom', 'setup.php');
	api_plugin_register_hook('uptime', 'rrd_graph_graph_options', 'uptime_rrd_graph_graph_options', 'setup.php');
	api_plugin_register_realm('uptime', 'setup.php,', 'Plugin uptime - view', 1);
	uptime_setup_database();
}


function uptime_setup_database()	{
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


function plugin_uptime_uninstall ()	{
	if (sizeof(db_fetch_assoc("SHOW TABLES LIKE 'plugin_uptime_data'")) > 0 )	{
                db_execute("DROP TABLE `plugin_uptime_data`");
        }
}


function plugin_uptime_version()	{
	global $config;

	$info = parse_ini_file($config['base_path'] . '/plugins/uptime/INFO', true);
	return $info['info'];
}


function plugin_uptime_check_config () {
	return true;
}

function seconds_to_time($secs)	{
    $dt = new DateTime('@' . $secs, new DateTimeZone('UTC'));
    return ($dt->format('z') . "d " . $dt->format('G') . "h " . $dt->format('i') . "m " . $dt->format('s') . "s" );
}

function uptime_rrd_graph_graph_options ($data) {
	global $config;

	$host_id = db_fetch_cell_prepared('SELECT host_id FROM graph_local WHERE id = ?', array ($data['graph_id'])); 
	$restarts = db_fetch_assoc_prepared ("SELECT timestamp FROM plugin_uptime_data WHERE
		host_id = ? AND state = 'R' AND timestamp BETWEEN ? AND ? ORDER BY timestamp", 
			array($host_id, $data['start'], $data['end']));

	$count = 0;
	foreach ($restarts as $restart)	{
		$data['txt_graph_items'] .= RRD_NL . "VRULE:" . $restart['timestamp'] . "#000000:" . RRD_NL;
		$count++;
	}

	if ($count > 0)	{
		$data['graph_opts'] .=  'COMMENT:" Device was restarted ' . $count . ' x - black vert. line! \\n"' . RRD_NL;
	}

	return $data;
}


function uptime_poller_bottom () {
	global $config;
	
	$start = microtime(true);
	$poller_interval = read_config_option("poller_interval");

	$xold = db_fetch_assoc ('SELECT host_id, uptime, state, timestamp FROM plugin_uptime_data WHERE 
		id IN (select max(id) AS id FROM  plugin_uptime_data GROUP BY host_id)');
	foreach ($xold as $one)	{
		$old[$one['host_id']]['uptime'] = $one['uptime'];
		$old[$one['host_id']]['state'] = $one['state'];
		$old[$one['host_id']]['timestamp'] = $one['timestamp'];
    	}
	
	$hosts = db_fetch_assoc ("SELECT id, snmp_sysUpTimeInstance AS uptime FROM host WHERE disabled != 'on' AND availability_method IN (1,2,5,6)");
	$count = cacti_sizeof($hosts);
    
	if ($count > 0)	{
		foreach ($hosts as $host)	{
			$hid = $host['id'];
			$help_time = $host['uptime'];
			$host['uptime'] = $host['uptime']/100;	// remove ms


			if (isset($old[$hid]))	{ // older record exists

				if ($old[$hid]['state'] == 'N')	{
					if ($host['uptime'] == 0)	{  // down or failed polls
						db_execute_prepared("INSERT INTO plugin_uptime_data (host_id,uptime,timestamp,state,info) 
							VALUES ( ? ,0,unix_timestamp(),'D', 'New device is down')",
							array($host['id']));
					}
					else	{	// new device and has uptime
						db_execute_prepared("INSERT INTO plugin_uptime_data (host_id,uptime,timestamp,state,info) 
							VALUES ( ? , ? , unix_timestamp() - ? ,'R', 'New device is up, counting uptime back')",
							array ($host['id'], $host['uptime'], $host['uptime'])); 

					}
				}
                                elseif ($host['uptime'] == 0)   {
                                        if ($old[$hid]['uptime'] > 0)   { // ->D
                                                db_execute_prepared("INSERT INTO plugin_uptime_data (host_id,uptime,timestamp,state,info)
                                                        VALUES ( ? ,0, unix_timestamp(), 'D', 'Device went down, uptime was " . seconds_to_time($old[$hid]['uptime']) . "')",
							array($host['id']));
					}
					else	{	//still down?
					
					
					}
				}
				elseif ($host['uptime'] > 0 && $host['uptime'] < $poller_interval && $old[$hid]['uptime'] < $poller_interval )	{	
					// special case - more restarts in short time
						db_execute_prepared("INSERT INTO plugin_uptime_data (host_id,uptime,timestamp,state,info) 
							VALUES ( ? , ? , unix_timestamp(), 'R', 'Device restart (maybe moretimes), uptime was " . seconds_to_time($old[$hid]['uptime']) . "')",
							array ($host['id'], $host['uptime']));

					
						db_execute_prepared("INSERT INTO plugin_uptime_data (host_id,uptime,timestamp,state,info) 
							VALUES ( ? ,? , unix_timestamp(),'U', 'Device is up')",
							array ($host['id'], $host['uptime']));
		
                                }
                                else	{	// host uptime > 0

					if ($host['uptime'] == $old[$hid]['uptime'] )	{ // failed polls

						if ($old[$hid]['state'] == 'F')	{
					    	// nothing
						}
						
						if ($old[$hid]['state'] != 'F')	{
							db_execute_prepared("INSERT INTO plugin_uptime_data (host_id,uptime,timestamp,state,info) 
							VALUES ( ? , ? , unix_timestamp(),'F', 'Failed poll')",
							array ($host['id'], $host['uptime']));
								
						}
					}

					if ($host['uptime'] > $old[$hid]['uptime'])	{ // is up
						if ($old[$hid]['state'] == 'F')	{
							db_execute_prepared("INSERT INTO plugin_uptime_data (host_id,uptime,timestamp,state,info) 
								VALUES ( ? , ? , unix_timestamp(),'U', 'Polling is ok')",
								array ($host['id'], $host['uptime']));

						}
					}

					if ($old[$hid]['state'] == "D" || $old[$hid]['uptime'] == 0)	{	// D->U
						db_execute_prepared("INSERT INTO plugin_uptime_data (host_id,uptime,timestamp,state,info) 
							VALUES ( ? , ? , unix_timestamp(),'R', 'Device is up after longer down state')",
							array ($host['id'], $host['uptime']));


						db_execute_prepared("INSERT INTO plugin_uptime_data (host_id,uptime,timestamp,state,info) 
						VALUES ( ? , ?, unix_timestamp(),'U', 'Device went up')",
						array ($host['id'],$host['uptime'])); 

					}

					if ($host['uptime'] < $old[$hid]['uptime'] && $old[$hid]['uptime'])	{	// restart in one poller cycle
						db_execute_prepared("INSERT INTO plugin_uptime_data (host_id,uptime,timestamp,state,info) 
							VALUES ( ? , ? , unix_timestamp(),'R', 'Device restart, uptime was " . seconds_to_time($old[$hid]['uptime']) . "')",
							array ($host['id'], $host['uptime']));

					
						db_execute_prepared("INSERT INTO plugin_uptime_data (host_id,uptime,timestamp,state,info) 
							VALUES ( ? ,? , unix_timestamp(),'U', 'Device is up')",
							array ($host['id'], $host['uptime']));

					}
                                }

			}
			else	{	// without any old records = new device
				db_execute_prepared("INSERT INTO plugin_uptime_data (host_id,uptime,timestamp,state,info) 
					VALUES ( ? , ? , unix_timestamp(),'N', concat('New device added ', now()))",
					array ($host['id'], $host['uptime']));
			}
		}
	}

	$end = microtime(true);
	cacti_log('PLUGIN UPTIME STATS: Duration: ' . round($end-$start,2) .', Hosts: ' . $count);
}


function uptime_host_edit_bottom ()	{
	if (!api_user_realm_auth('setup.php')) {
    		print __('Permission denied', 'uptime') . '<br/><br/>';
                return false;
	}

	print '<br/><br/>';
	html_start_box('<strong>Uptime history</strong>', '100%', '', '3', 'center', '');
	print "<tr class='tableHeader'><th>Date</th><th>Flag</th><th>Info</th></tr>";

        $records = db_fetch_assoc_prepared ('SELECT * FROM plugin_uptime_data WHERE host_id = ? ORDER BY id',
		array(get_request_var('id')));
    
	$i = 0;
	foreach ($records as $record)	{
    		$i++;
                form_alternate_row("x$i", true);
        	print "<td class='nowrap'>" . date('Y-m-d H:i',$record['timestamp']) . '</td>';
        	print "<td class='nowrap'>" . $record['state'] . '</td>';
        	print "<td class='nowrap'>" . $record['info'] . '</td>';		
		form_end_row();
	}

	$count = db_fetch_cell_prepared ("SELECT count(*) FROM host WHERE disabled != 'on' AND id = ? AND  availability_method IN (1,2,5,6)",
		array(get_request_var('id')));

	if ($count == 0)	{
		print '<br/><b>For uptime plugin you have to choose any SNMP availability option</b><br/>';
	}


	html_end_box(false);
}

