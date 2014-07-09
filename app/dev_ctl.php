<?php 
/*
 * Copyright (C) 2013-2014 RuneAudio Team
 * http://www.runeaudio.com
 *
 * RuneUI
 * copyright (C) 2013-2014 - Andrea Coiutti (aka ACX) & Simone De Gregori (aka Orion)
 *
 * RuneOS
 * copyright (C) 2013-2014 - Simone De Gregori (aka Orion) & Carmelo San Giovanni (aka Um3ggh1U)
 *
 * RuneAudio website and logo
 * copyright (C) 2013-2014 - ACX webdesign (Andrea Coiutti)
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with RuneAudio; see the file COPYING.  If not, see
 * <http://www.gnu.org/licenses/gpl-3.0.txt>.
 *
 *  file: app/dev_ctl.php
 *  version: 1.3
 *  coder: Simone De Gregori
 *
 */

// inspect POST
if (isset($_POST)) {
	// ----- DEV MODE -----
	if (isset($_POST['mode'])) {
		if ($_POST['mode']['dev']['enable'] == 1) {
			// create worker job (start udevil)
			$redis->get('dev') == 1 || $redis->set('dev', 1);
			$redis->get('debug') == 1 || $redis->set('debug', 1);
		} else {
			// create worker job (stop udevil)
			$redis->get('dev') == 0 || $redis->set('dev', 0);
		}
	// ----- DEBUG -----
		if ($_POST['mode']['debug']['enable'] == 1) {
			// create worker job (start udevil)
			$redis->get('debug') == 1 || $redis->set('debug', 1);
		} else {
			// create worker job (stop udevil)
			$redis->get('debug') == 0 || $redis->set('debug', 0);
		}
	}
	// ----- OPCACHE -----
	if (isset($_POST['opcache'])) {
		if ($_POST['opcache']['enable'] == 1) {
			// create worker job (enable php opcache)
			$redis->get('opcache') == 1 || $jobID[] = wrk_control($redis,'newjob', $data = array( 'wrkcmd' => 'opcache', 'action' => 'enable' ));
		} else {
			// create worker job (disable php opcache)
			$redis->get('opcache') == 0 || $jobID[] = wrk_control($redis,'newjob', $data = array( 'wrkcmd' => 'opcache', 'action' => 'disable' ));
		}	
	}
	if (isset($_POST['syscmd'])) {
			// ----- BLANK PLAYERID -----
			if (isset($_POST['syscmd']['blankplayerid'])) $jobID[] = wrk_control($redis,'newjob', $data = array( 'wrkcmd' => 'blankplayerid' ));
			// ----- CLEARIMG -----
			if (isset($_POST['syscmd']['clearimg'])) $jobID[] = wrk_control($redis,'newjob', $data = array( 'wrkcmd' => 'clearimg' ));
			// ----- CHECK FS PERMISSIONS -----
			if (isset($_POST['syscmd']['syschmod'])) $jobID[] = wrk_control($redis,'newjob', $data = array( 'wrkcmd' => 'syschmod' ));
			// ----- RESTART MPD -----
			if (isset($_POST['syscmd']['mpdrestart'])) $jobID[] = wrk_control($redis,'newjob', $data = array( 'wrkcmd' => 'mpdrestart' ));
			// ----- RESET NET CONFIG -----
			if (isset($_POST['syscmd']['netconfreset'])) $jobID[] = wrk_control($redis,'newjob', $data = array( 'wrkcmd' => 'netconfreset' ));
			// ----- RESET MPD CONFIG -----
			if (isset($_POST['syscmd']['netconfreset'])) $jobID[] = wrk_control($redis,'newjob', $data = array( 'wrkcmd' => 'netconfreset' ));
			// ----- RESTART PHP-FPM -----
			if (isset($_POST['syscmd']['phprestart'])) $jobID[] = wrk_control($redis,'newjob', $data = array( 'wrkcmd' => 'clearimg' ));
			// ----- RESTART WORKERS -----
			if (isset($_POST['syscmd']['wrkrestart'])) $jobID[] = wrk_control($redis,'newjob', $data = array( 'wrkcmd' => 'wrkrestart', 'args' => $_POST['syscmd']['wrkrestart'] ));
	}
}
waitSyWrk($redis,$jobID);
$template->debug = $redis->get('debug');
$template->playerid = $redis->get('playerid');
$template->opcache = $redis->get('opcache');
// debug
// var_dump($template->dev);
// var_dump($template->debug);
// var_dump($template->opcache);
