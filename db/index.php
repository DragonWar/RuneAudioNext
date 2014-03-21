<?php
/*
 * Copyright (C) 2013-2014 RuneAudio Team
 * http://www.runeaudio.com
 *
 * RuneUI
 * copyright (C) 2013-2014 - Andrea Coiutti (aka ACX) & Simone De Gregori (aka Orion)
 *
 * RuneOS
 * copyright (C) 2013-2014 - Carmelo San Giovanni (aka Um3ggh1U) & Simone De Gregori (aka Orion)
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
 *  file: db/index.php
 *  version: 1.3
 *
 */
 
// common include
include($_SERVER['HOME'].'/app/config/config.php');
ini_set('display_errors',1);
error_reporting('E_ALL');

if (isset($_GET['cmd']) && !empty($_GET['cmd'])) {

        if ( !$mpd ) {
        echo 'Error Connecting to MPD daemon ';
		
		}  else {
				
				switch ($_GET['cmd']) {
				
				case 'filepath':
					if (isset($_POST['path'])) {
					echo json_encode(searchDB($mpd,'filepath',$_POST['path']));
					} else {
					echo json_encode(searchDB($mpd,'filepath'));
					}
				break;

				case 'playlist':
					echo getPlayQueue($mpd);
				break;

				case 'add':
					if (isset($_POST['path'])) {
					echo json_encode(addQueue($mpd,$_POST['path']));
					}
				break;
				
				case 'addplay':
					if (isset($_POST['path'])) {
					$status = _parseStatusResponse(MpdStatus($mpd));
					$pos = $status['playlistlength'] ;
					addQueue($mpd,$_POST['path']);
					// -- REWORK NEEDED -- tempfix for analog/hdmi out of raspberrypi (should be integrated with sendMpdCommand() function)
						if ($redis->get('hwplatformid') == '01' && ($redis->get('ao') == 2 OR $redis->get('ao') == 3)) {
							$cmdstr = "pause";
							sendMpdCommand($mpd,$cmdstr);
							closeMpdSocket($mpd);
							usleep(500000);
							$mpd = openMpdSocket(DAEMONIP, 6600) ;
							$cmdstr = $_GET['cmd'];
							sendMpdCommand($mpd,$cmdstr);
						} else {
							sendMpdCommand($mpd,'play '.$pos);
						}
					echo json_encode(readMpdResponse($mpd));
					}
				break;

				case 'addreplaceplay':
					if (isset($_POST['path'])) {
					sendMpdCommand($mpd,'clear');
					addQueue($mpd,$_POST['path']);
					// -- REWORK NEEDED -- tempfix for analog/hdmi out of raspberrypi (should be integrated with sendMpdCommand() function)
						if ($redis->get('hwplatformid') == '01' && ($redis->get('ao') == 2 OR $redis->get('ao') == 3)) {
							$cmdstr = "pause";
							sendMpdCommand($mpd,$cmdstr);
							closeMpdSocket($mpd);
							usleep(500000);
							$mpd = openMpdSocket(DAEMONIP, 6600) ;
							$cmdstr = $_GET['cmd'];
							sendMpdCommand($mpd,$cmdstr);
						} else {
							sendMpdCommand($mpd,'play');
						}
					echo json_encode(readMpdResponse($mpd));
					}
				break;
				
				case 'update':
					if (isset($_POST['path'])) {
					sendMpdCommand($mpd,"update \"".html_entity_decode($_POST['path'])."\"");
					echo json_encode(readMpdResponse($mpd));
					}
				break;
				
				// case 'trackremove':
					// if (isset($_GET['songid'])) {
					// echo json_encode(remTrackQueue($mpd,$_GET['songid']));
					// }
				// break;
				
				case 'search':
					if (isset($_POST['query']) && isset($_GET['querytype'])) {
					echo json_encode(searchDB($mpd,$_GET['querytype'],$_POST['query']));
					}
				break;
				
				case 'dirble':
				$proxy = $redis->hGetall('proxy');
					$apikey = $redis->hGet('dirble','apikey');
					$dirbleBase = 'http://api.dirble.com/v1/';
					if (isset($_POST['querytype'])) {
						// if ($_POST['querytype'] === 'amountStation') {
						if ($_POST['querytype'] === 'amountStation') {
						$dirble = json_decode(curlGet($dirbleBase.'amountStation/apikey/'.$apikey,$proxy));
						echo $dirble->amount;
						}
						// Get primaryCategories
						if ($_POST['querytype'] === 'categories' OR $_POST['querytype'] === 'primaryCategories' ) {
						echo curlGet($dirbleBase. $_POST['querytype'].'/apikey/'.$apikey,$proxy);
						}
						// Get childCategories by primaryid
						if ($_POST['querytype'] === 'childCategories' && isset($_POST['args'])) {
						echo curlGet($dirbleBase.'childCategories/apikey/'.$apikey.'/primaryid/'.$_POST['args'],$proxy);
						}
						// Get station by ID
						if ($_POST['querytype'] === 'stations' && isset($_POST['args'])) {
						echo curlGet($dirbleBase.'stations/apikey/'.$apikey.'/id/'.$_POST['args'],$proxy);
						}
						// Search radio station
						if ($_POST['querytype'] === 'search' && isset($_POST['args'])) {
						echo curlGet($dirbleBase.'search/apikey/'.$apikey.'/search/'.$_POST['args'],$proxy);
						}
						// Get stations by continent
						if ($_POST['querytype'] === 'continent' && isset($_POST['args'])) {
						echo curlGet($dirbleBase.'continent/apikey'.$apikey.'/continent/'.$_POST['args'],$proxy);
						}
						// Get stations by country
						if ($_POST['querytype'] === 'country' && isset($_POST['args'])) {
						echo curlGet($dirbleBase.'country/apikey'.$apikey.'/country/'.$_POST['args'],$proxy);
						}
						// Add station
						if ($_POST['querytype'] === 'addstation' && isset($_POST['args'])) {
						// input array $_POST['args'] = array('name' => 'value', 'streamurl' => 'value', 'website' => 'value', 'country' => 'value', 'directory' => 'value') 
						echo curlPost($dirbleBase.'station/apikey/'.$apikey, $_POST['args'],$proxy);
						}
						
					}
				break;
				
				case 'jamendo':
				$apikey = $redis->hGet('jamendo','clientid');
				$proxy = $redis->hGetall('proxy');
						if ($_POST['querytype'] === 'radio') {
						$jam_channels = json_decode(curlGet('http://api.jamendo.com/v3.0/radios/?client_id='.$apikey.'&format=json&limit=200',$proxy));
							foreach ($jam_channels->results as $station) {
								$channel = json_decode(curlGet('http://api.jamendo.com/v3.0/radios/stream?client_id='.$apikey.'&format=json&name='.$station->name,$proxy));
								$station->stream = $channel->results[0]->stream;
							}
						// TODO: implementare cache canali jamendo su Redis
						// $redis->hSet('jamendo','ch_cache',json_encode($jam_channels));
						// echo $redis->hGet('jamendo','ch_cache');
						echo json_encode($jam_channels);
						}
						if ($_POST['querytype'] === 'radio' && !empty($_POST['args'])) {
						echo curlGet('http://api.jamendo.com/v3.0/radios/stream?client_id='.$apikey.'&format=json&name='.$_POST['args'],$proxy);
						}
				break;
				
				
				case 'test':
				$proxy = $redis->hGetall('proxy');
				print_r($proxy);
				break;
				}
				
		closeMpdSocket($mpd);
		}

} else {

echo 'MPD DB INTERFACE<br>';
echo 'INTERNAL USE ONLY<br>';
echo 'hosted on runeaudio.local:81';
}
?>

