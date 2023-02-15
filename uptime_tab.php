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

chdir('../../');
include_once('./include/auth.php');
include_once('./lib/snmp.php');
include_once('./plugins/uptime/functions.php');


set_default_action();

$selectedTheme = get_selected_theme();

switch (get_request_var('action')) {
        case 'ajax_hosts':

                $sql_where = '';

                get_allowed_ajax_hosts (false, 'applyFilter', $sql_where);

                break;

        default:
		general_header();
		uptime_display_form();
		if (get_request_var('host_id')) {
			uptime_display_events(get_request_var('host_id'));
		}
		bottom_footer();
                break;
}


function uptime_display_form() {
	global $config;
	
	print get_md5_include_js($config['base_path'].'/plugins/uptime/uptime.js');

	$host_where = '';

	html_start_box('<strong>Uptime</strong>', '100%', '', '3', 'center', '');
?>

	<tr>
 	 <td>
  	  <form name="form_uptime" action="uptime_tab.php">
   		<table width="30%" cellpadding="0" cellspacing="0">
    		<tr class="navigate_form">
     		<td>
		       <?php print html_host_filter(get_request_var('host_id'), 'applyFilter', $host_where);?>

     		<td>
<!--      			<input type='submit' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'> -->
      			<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __('Clear');?>' title='<?php print __esc('Clear Filters');?>'> 
     		</td>
    		</tr>
  		</table>
 	</form>
       </td>
     <tr><td>
<?php

}

