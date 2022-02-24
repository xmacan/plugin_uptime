<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2021-2022 Petr Macek                                      |
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

      	if (!in_array($host_id, uptime_get_allowed_devices ($_SESSION['sess_user_id'], true)))    {
      		print 'You haven\'t  permission for ' . $host_id;
      		return false;
      	}

	if (basename($_SERVER['PHP_SELF']) == 'uptime_tab.php') {
        	$limit = 1000;
        	$text= '';
	} else {
		$limit = 20;
		$text = '(last 20 events)';
	}

        html_start_box('<strong>Uptime/restart history ' . $text . '</strong>', '100%', '', '3', 'center', '');
        print "<tr class='tableHeader'><th>Date</th><th>Flag</th><th>Info</th></tr>";

        $records = db_fetch_assoc_prepared ('SELECT * FROM plugin_uptime_data WHERE host_id = ? ORDER BY id DESC LIMIT ' . $limit,
                array($host_id));


	if (cacti_sizeof ($records) > 0) {
	
        	$i = 0;
        	foreach ($records as $record)   {
                	$i++;
                	form_alternate_row("x$i", true);
                	print "<td class='nowrap'>" . date('Y-m-d H:i',$record['timestamp']) . '</td>';
                	print "<td class='nowrap'>" . $record['state'] . '</td>';
                	print "<td class='nowrap'>" . $record['info'] . '</td>';
                	form_end_row();
        	}

        	$count = db_fetch_cell_prepared ("SELECT count(*) FROM host WHERE disabled != 'on' AND id = ? AND  availability_method IN (1,2,5,6)",
                	array($host_id));

        	if ($count == 0)        {
                	print '<br/><b>For uptime plugin you have to choose any SNMP availability option</b><br/>';
        	}
	} else {
		print '<tr><td colspan=3>No data</td></tr>';
	}

        html_end_box(false);
}

