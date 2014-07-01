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
 *  file: command/index.php
 *  version: 1.3
 *  coder: Simone De Gregori
 *
 */

// common include
include($_SERVER['HOME'].'/app/config/config.php');
// -- REWORK NEEDED --
if (isset($_GET['cmd']) && $_GET['cmd'] != '') {

    if ( !$mpd ) {
	
        echo 'Error Connecting to MPD daemon ';
		
    } else {
           
		// debug
		// runelog('MPD command: ',$_GET['cmd']);
		if ($_GET['cmd'] === 'renderui') {
			ui_update($redis,$mpd);
		} else if ($_GET['cmd'] === 'wifiscan') {
			wrk_control($redis,'newjob', $data = array( 'wrkcmd' => 'wificfg', 'action' => 'scan' ));
			echo 'wlan scan queued';
			die;
		} else {
			sendMpdCommand($mpd,$_GET['cmd']);
		}
            
        // debug
        // runelog('--- [command/index.php] --- CLOSE MPD SOCKET <<< (1) ---','');
		$response = readMpdResponse($mpd);
        closeMpdSocket($mpd);
		echo $response;
	}
	
} else {
	
	echo 'MPD COMMAND INTERFACE<br>';
	echo 'INTERNAL USE ONLY<br>';
	echo 'hosted on runeaudio.local:82';
	
}
