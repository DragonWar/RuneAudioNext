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
 *  file: app/libs/runeaudio.php
 *  version: 1.3
 *
 */
 
// Predefined MPD Response messages
define("MPD_GREETING", "OK MPD 0.18.0\n");

function openMpdSocket($path) {
$sock = socket_create(AF_UNIX, SOCK_STREAM, 0);
$connection = socket_connect($sock, $path);
	if ($connection) {
	runelog("[1][".$sock."]\t>>>>>> OPEN MPD SOCKET <<<<<<\t\t\t",'');
	return $sock;
	} else {
	runelog("[1][".$sock."]\t>>>>>> MPD SOCKET ERROR: ".socket_last_error($sock)." <<<<<<\t\t\t",'');
	// ui_notify('MPD sock: '.$sock.'','socket error = '.socket_last_error($sock));
	return false;
	}
}

function closeMpdSocket($sock) {
sendMpdCommand($sock,"close");
socket_close($sock);
// debug
runelog("[0][".$sock."]\t<<<<<< CLOSE MPD SOCKET >>>>>>\t\t\t",'');
}

function sendMpdCommand($sock,$cmd) {
	if ($cmd == 'cmediafix') {
		socket_write($sock, 'pause\n', strlen('pause\n'));
		usleep(500000);
		socket_write($sock, 'pause\n', strlen('pause\n'));
	} else {
		$cmd = $cmd."\n";
		socket_write($sock, $cmd, strlen($cmd));		
	}
runelog("MPD COMMAND: (socket=".$sock.")",$cmd);
//ui_notify('COMMAND GIVEN','CMD = '.$cmd,'','.9');
}

function readMpdResponse($sock) {
$output = "";
while($resp = socket_read($sock, 32768)) {
   $output .= $resp;
   if ((strpos($output, "OK\n") !== false) OR (strpos($output, "ACK") !== false)) break;
}
runelog("socket_read: buffer length ".strlen($output),$output);
return str_replace(MPD_GREETING,'',$output);
// return $output;
}

function sendMpdIdle($sock) {
//sendMpdCommand($sock,"idle player,playlist"); 
sendMpdCommand($sock,"idle"); 
$response = readMpdResponse($sock);
return true;
}

function monitorMpdState($sock) {
	if (sendMpdIdle($sock)) {
	$status = _parseStatusResponse(MpdStatus($sock));
	return $status;
	}
}

function getTrackInfo($sock,$songID) {
			// set currentsong, currentartis, currentalbum
			sendMpdCommand($sock,"playlistinfo ".$songID);
			$track = readMpdResponse($sock);
			return _parseFileListResponse($track);
}

function getPlayQueue($sock) {
sendMpdCommand($sock,"playlistinfo");
$playqueue = readMpdResponse($sock);
//return _parseFileListResponse($playqueue);
return $playqueue; 
}

function getTemplate($template) {
return str_replace("\"","\\\"",implode("",file($template)));
}

function getMpdOutputs($mpd) {
sendMpdCommand($mpd,"outputs");
$outputs= readMpdResponse($mpd);
return $outputs;
}

function getLastFMauth($redis) {
$lastfmauth = $redis->hGetAll('lastfm');
return $lastfmauth;
}

function setLastFMauth($redis,$lastfm) {
$redis->hSet('lastfm','user',$lastfm->user);
$redis->hSet('lastfm','pass',$lastfm->pass);
}

function echoTemplate($template) {
echo $template;
}

function searchDB($sock,$querytype,$query) {
	switch ($querytype) {
	case "filepath":
		if (isset($query) && !empty($query)){
		sendMpdCommand($sock,"lsinfo \"".html_entity_decode($query)."\"");
		break;
		} else {
		sendMpdCommand($sock,"lsinfo");
		break;
		}
	case "album":
	case "artist":
	case "title":
	case "file":
		sendMpdCommand($sock,"search ".$querytype." \"".html_entity_decode($query)."\"");
	break;
	case "globalrandom":
		sendMpdCommand($sock,"listall");
	break;
	}
	
//$response =  htmlentities(readMpdResponse($sock),ENT_XML1,'UTF-8');
//$response = htmlspecialchars(readMpdResponse($sock));
$response = readMpdResponse($sock);
return _parseFileListResponse($response);
}

function remTrackQueue($sock,$songpos) {
$datapath = findPLposPath($songpos,$sock);
sendMpdCommand($sock,"delete ".$songpos);
$response = readMpdResponse($sock);
return $datapath;
}

function addQueue($sock,$path) {
$fileext = parseFileStr($path,'.');
	if ($fileext == 'm3u' OR $fileext == 'pls') {
		sendMpdCommand($sock,"load \"".html_entity_decode($path)."\"");
	} else {
		sendMpdCommand($sock,"add \"".html_entity_decode($path)."\"");
	}
}

class globalRandom extends Thread {
	// mpd status
	public $status;
	
    public function __construct($status){
		$this->status = $status;
    }
	
    public function run() {
		$mpd = openMpdSocket('/run/mpd.sock');
			if ($this->status['consume'] == 0 OR $this->status['random'] == 0) {
			sendMpdCommand($mpd,'consume 1');
			sendMpdCommand($mpd,'random 1');
			}
			$path = randomSelect($mpd);
			if ($path) {
				addQueue($mpd,$path);
				runelog("global random call",$path);
				ui_notify('Global Random Mode', htmlentities($path,ENT_XML1,'UTF-8').' added to current Queue');
			}
		closeMpdSocket($mpd);
    }
}

function randomSelect($sock) {
$songs = searchDB($sock,'globalrandom');
srand((float) microtime() * 10000000);
$randkey = array_rand($songs);
return $songs[$randkey]['file'];
}

function MpdStatus($sock) {
sendMpdCommand($sock,"status");
$status= readMpdResponse($sock);
return $status;
}

// create JS like Timestamp
function jsTimestamp() {
$timestamp = round(microtime(true) * 1000);
return $timestamp;
}

function songTime($sec) {
$minutes = sprintf('%02d', floor($sec / 60));
$seconds = sprintf(':%02d', (int) $sec % 60);
return $minutes.$seconds;
}

function phpVer() {
$version = phpversion();
return substr($version, 0, 3); 
}

function sysCmd($syscmd) {
exec($syscmd." 2>&1", $output);
runelog('sysCmd($str)',$syscmd);
runelog('sysCmd() output:',$output);
return $output;
}

function getMpdDaemonDetalis() {
$cmd = sysCmd('id -u mpd');
$details['uid'] = $cmd[0];
$cmd = sysCmd('id -g mpd');
$details['gid'] = $cmd[0];
$cmd = sysCmd('pgrep -u mpd');
$details['pid'] = $cmd[0];
return $details;
}

// format Output for "playlist"
function _parseFileListResponse($resp) {
		if ( is_null($resp) ) {
			return NULL;
		} else {
			$plistArray = array();
			$plistLine = strtok($resp,"\n");
			// $plistFile = "";
			$plCounter = -1;
			while ( $plistLine ) {
				// TODO: testing!!! (synology @eaDir garbage filtering)
				// list ( $element, $value ) = explode(": ",$plistLine);
				if (!strpos($plistLine,'@eaDir')) list ( $element, $value ) = explode(': ',$plistLine);
				if ( $element === 'file' OR $element === 'playlist') {
					$plCounter++;
					// $plistFile = $value;
					$plistArray[$plCounter][$element] = $value;
					$plistArray[$plCounter]['fileext'] = parseFileStr($value,'.');
				} else if ( $element === 'directory' ) {
					$plCounter++;
					// record directory index for further processing
					$dirCounter++;
					// $plistFile = $value;
					$plistArray[$plCounter]['directory'] = $value;
				} else {
					$plistArray[$plCounter][$element] = $value;
					$plistArray[$plCounter]['Time2'] = songTime($plistArray[$plCounter]['Time']);
				}

				$plistLine = strtok("\n");
			} 
				// reverse MPD list output
				// if (isset($dirCounter) && isset($plistArray[0]["file"]) ) {
				// $dir = array_splice($plistArray, -$dirCounter);
				// $plistArray = $dir + $plistArray;
				// }
		}
		return $plistArray;
	}

// format Output for "status"
function _parseStatusResponse($resp) {
		if ( is_null($resp) ) {
			return NULL;
		} else {
			$plistArray = array();
			$plistLine = strtok($resp,"\n");
			$plistFile = "";
			$plCounter = -1;
			while ( $plistLine ) {
				list ( $element, $value ) = explode(": ",$plistLine);
				$plistArray[$element] = $value;
				$plistLine = strtok("\n");
			} 
			// "elapsed time song_percent" added to output array
			 $time = explode(":", $plistArray['time']);
			 if ($time[0] != 0) {
			 $percent = round(($time[0]*100)/$time[1]);	
			 } else {
			 	$percent = 0;
			 }
			 $plistArray["song_percent"] = $percent;
			 $plistArray["elapsed"] = $time[0];
			 $plistArray["time"] = $time[1];

			 // "audio format" output
			 	$audio_format = explode(":", $plistArray['audio']);
				switch ($audio_format[0]) {
					case '48000':
					case '96000':
					case '192000':
					$plistArray['audio_sample_rate'] = rtrim(rtrim(number_format($audio_format[0]),0),',');
					break;
					
					case '44100':
					case '88200':
					case '176400':
					case '352800':
					$plistArray['audio_sample_rate'] = rtrim(number_format($audio_format[0],0,',','.'),0);
					break;
				}
			 // format "audio_sample_depth" string
			 	$plistArray['audio_sample_depth'] = $audio_format[1];
			 // format "audio_channels" string
			 	if ($audio_format[2] === "2") $plistArray['audio_channels'] = "Stereo";
			 	if ($audio_format[2] === "1") $plistArray['audio_channels'] = "Mono";
			 	// if ($audio_format[2] > 2) $plistArray['audio_channels'] = "Multichannel";

		}
		return $plistArray;
	}

function _parseOutputsResponse($input,$active) {
		if ( is_null($input) ) {
				return NULL;
		} else {
			$response = preg_split("/\r?\n/", $input);
			$outputs = array();
			$linenum = 0;
			$i = -1;
			foreach($response as $line) {
				if ($linenum % 3 == 0) {
				$i++;
				} 
			if (!empty($line)) {
			$value = explode(':',$line);
			$outputs[$i][$value[0]] = trim($value[1]);
				if (isset($active)) {
					if ($value[0] == 'outputenabled' && $outputs[$i][$value[0]] == 1) {
					$active = $i;
					}
				}
			} else {
			unset($outputs[$i]);
			}
			$linenum++;
			}
		}
	if (isset($active)) {
	return $active;
	} else {
	return $outputs;
	}
}
	
// get file extension
function parseFileStr($strFile,$delimiter) {
$pos = strrpos($strFile, $delimiter);
$str = substr($strFile, $pos+1);
return $str;
}

function OpCacheCtl($basepath,$action){
if ($action === 'prime') $cmd = 'opcache_compile_file';
if ($action === 'reset') $cmd = 'opcache_invalidate';
	if (is_file($basepath)) {
		if (parseFileStr($basepath,'.') === 'php' && $basepath != '/srv/http/command/cachectl.php' ) $cmd ($basepath);
	}
	elseif(is_dir($basepath)) {
		$scan = glob(rtrim($basepath,'/').'/*');
		foreach($scan as $index=>$path) {
			OpCacheCtl($path,$action);
		}
	}
}

function sessionSQLite($sessionsdb) {
require_once(APP.'libs/vendor/SqliteSessionHandler/SqliteSessionHandler.php');
$handler = new kafene\SqliteSessionHandler($sessionsdb);
	if (session_set_save_handler($handler, true)) {
		return true;
	} else {
		return false;
	}
}

function cfgdb_connect($dbpath) {
	if ($dbh  = new PDO($dbpath)) {
	return $dbh;
	} else {
		echo "cannot open the database";
	return false;
	}
}


function cfgdb_read($table,$dbh,$param=null,$id=null) {
	if (empty($param)) {
		$querystr = "SELECT * FROM ".$table;
	} elseif (!empty($id)) {
		$querystr = "SELECT * FROM ".$table." WHERE id='".$id."'";
	} elseif ($param == 'mpdconf'){
		$querystr = "SELECT param,value_player FROM cfg_mpd WHERE value_player!=''";
	} elseif ($param == 'mpdconfdefault') {
		$querystr = "SELECT param,value_default FROM cfg_mpd WHERE value_default!=''";
	} elseif ($table == 'cfg_plugins') {
		$querystr = "SELECT * FROM ".$table." WHERE name='".$param['plugin_name']."' AND param='".$param['plugin_param']."'";
	} else {
		$querystr = 'SELECT value from '.$table.' WHERE param="'.$param.'"';
	}
	//debug
	runelog('cfgdb_read('.$table.',dbh,'.$param.','.$id.')',$querystr);
	$result = sdbquery($querystr,$dbh);
	return $result;
}

function cfgdb_update($table,$dbh,$key,$value) {
switch ($table) {
	case 'cfg_engine':
	$querystr = "UPDATE ".$table." SET value='".$value."' where param='".$key."'";
	break;
	
	case 'cfg_lan':
	$querystr = "UPDATE ".$table." SET dhcp='".$value['dhcp']."', ip='".$value['ip']."', netmask='".$value['netmask']."', gw='".$value['gw']."', dns1='".$value['dns1']."', dns2='".$value['dns2']."' where name='".$value['name']."'";
	break;
	
	case 'cfg_mpd':
	$querystr = "UPDATE ".$table." set value_player='".$value."' where param='".$key."'";
	break;
	
	case 'cfg_wifisec':
	$querystr = "UPDATE ".$table." SET ssid='".$value['ssid']."', security='".$value['encryption']."', password='".$value['password']."' where id=1";
	break;
	
	case 'cfg_source':
	$value = (array) $value;
	$querystr = "UPDATE ".$table." SET name='".$value['name']."', type='".$value['type']."', address='".$value['address']."', remotedir='".$value['remotedir']."', username='".$value['username']."', password='".$value['password']."', charset='".$value['charset']."', rsize='".$value['rsize']."', wsize='".$value['wsize']."', options='".$value['options']."', error='".$value['error']."' where id=".$value['id'];
	break;
	
	case 'cfg_plugins':
	$querystr = "UPDATE ".$table." SET value='".$value['value']."' where param='".$key."' AND name='".$value['plugin_name']."'";
	break;
}
//debug
runelog('cfgdb_update('.$table.',dbh,'.$key.','.$value.')',$querystr);
	if (sdbquery($querystr,$dbh)) {
	return true;
	} else {
	return false;
	}
}

function cfgdb_write($table,$dbh,$values) {
$querystr = "INSERT INTO ".$table." VALUES (NULL, ".$values.")";
//debug
runelog('cfgdb_write('.$table.',dbh,'.$values.')',$querystr);
	if (sdbquery($querystr,$dbh)) {
	return true;
	} else {
	return false;
	}
}

function cfgdb_delete($table,$dbh,$id) {
if (!isset($id)) {
$querystr = "DELETE FROM ".$table;
} else {
$querystr = "DELETE FROM ".$table." WHERE id=".$id;
}
//debug
runelog('cfgdb_delete('.$table.',dbh,'.$id.')',$querystr);
	if (sdbquery($querystr,$dbh)) {
	return true;
	} else {
	return false;
	}
}

function sdbquery($querystr,$dbh) {
	$query = $dbh->prepare($querystr);
	if ($query->execute()) {
			$result = array();
			  $i = 0;
				  foreach ($query as $value) {
					$result[$i] = $value;
					$i++;
				  }
		$dbh = null;
		if (empty($result)) {
		return true;
		} else {
		return $result;
		}
	} else {
	 return false;
	}
}

function redisDatastore($redis,$action) {

	switch ($action) {
			
			case 'reset':
			// kernel profile
			$redis->set('orionprofile', 'RuneAudio');

			// player features
			$redis->set('hostname', 'runeaudio');
			$redis->set('ntpserver', 'pool.ntp.org');
			$redis->hSet('airplay','enable', 1);
			$redis->hSet('airplay','name', 'runeaudio');
			$redis->set('udevil', 1);
			$redis->set('coverart', 1);
			$redis->set('playmod', 0);
			$redis->set('ramplay', 0);
			$redis->set('scrobbling_lastfm', 0);
			$redis->set('cmediafix', 0);
			$redis->set('globalrandom', 0);
			$redis->set('globalrandom_lock', 0);

			// plugins api-keys
			$redis->set('lastfm_apikey', 'ba8ad00468a50732a3860832eaed0882');
			$redis->hSet('jamendo', 'clientid', '5f3ed86c');
			$redis->hSet('jamendo', 'secret', '1afcdcb13eb5ce8f6e534ac4566a3ab9');
			$redis->hSet('dirble', 'apikey', '134aabbce2878ce0dbfdb23fb3b46265eded085b');

			// internal config hash control
			$redis->set('mpdconfhash', '');
			$redis->set('netconfhash', '');
			$redis->set('mpdconf_advanced', 0);
			$redis->set('netconf_advanced', 0);

			// developer parameters
			$redis->set('dev', 0);
			$redis->set('debug', 0);
			$redis->set('opcache', 1);

			// HW platform data
			$redis->set('playerid', '');
			$redis->set('hwplatform', '');
			$redis->set('hwplatformid', '');

			// player control
			$redis->set('ao', 1);
			$redis->set('volume', 0);
			$redis->set('pl_length', 0);
			$redis->set('nextsongid', 0);
			$redis->set('lastsongid', 0);
			
			// acards_details database
			$redis->hSet('acards_details','snd_rpi_iqaudio_dac','{"extlabel":"IQaudIO Pi-DAC","mixer_numid":"1","mixer_control":"Playback Digital","type":"i2s"}');
			$redis->hSet('acards_details','snd_rpi_hifiberry_dac','{"extlabel":"I&#178;S &#8722; (HiFiBerry DAC)","hwplatformid":"01","type":"i2s"}');
			$redis->hSet('acards_details','snd_rpi_hifiberry_digi','{"extlabel":"I&#178;S &#8722; (HiFiBerry Digi)","hwplatformid":"01","type":"i2s"}');
			$redis->hSet('acards_details','XMOS USB Audio 2.0','{"extlabel":"XMOS AK4399 USB-Audio DAC","mixer_numid":"3","mixer_control":"XMOS Clock Selector","type":"usb"}');
			$redis->hSet('acards_details','wm8731-audio','{"extlabel":"Utilite Analog Out","mixer_numid":"1","mixer_control":"Master","hwplatformid":"05","type":"integrated"}');
			$redis->hSet('acards_details','imx-spdif','{"extlabel":"Utilite Coax SPDIF Out","hwplatformid":"05","type":"integrated"}');
			$redis->hSet('acards_details','imx-hdmi-soc','{"extlabel":"Utilite HDMI Out","hwplatformid":"05","type":"integrated"}');
			break;
			
			case 'check':
			// kernel profile
			$redis->get('orionprofile') || $redis->set('orionprofile', 'RuneAudio');

			// player features
			$redis->get('hostname') || $redis->set('hostname', 'runeaudio');
			$redis->get('ntpserver') || $redis->set('ntpserver', 'pool.ntp.org');
				// TODO: remove this line // check old control value
				if ($redis->get('airplay')) $redis->del('airplay');
			$redis->hGet('airplay','enable') || $redis->hSet('airplay','enable', 1);
			$redis->hGet('airplay','name') || $redis->hSet('airplay','name', 'runeaudio');
			$redis->get('udevil') || $redis->set('udevil', 1);
			$redis->get('coverart') || $redis->set('coverart', 1);
			$redis->get('playmod') || $redis->set('playmod', 0);
			$redis->get('ramplay') || $redis->set('ramplay', 0);
			$redis->get('scrobbling_lastfm') || $redis->set('scrobbling_lastfm', 0);
			$redis->get('cmediafix') || $redis->set('cmediafix', 0);
			$redis->get('globalrandom') || $redis->set('globalrandom', 0);
			$redis->get('globalrandom_lock') || $redis->set('globalrandom_lock', 0);

			// plugins api-keys
			$redis->get('lastfm_apikey') || $redis->set('lastfm_apikey', 'ba8ad00468a50732a3860832eaed0882');
			$redis->hGet('jamendo', 'clientid') || $redis->hSet('jamendo', 'clientid', '5f3ed86c');
			$redis->hGet('jamendo', 'secret') || $redis->hSet('jamendo', 'secret', '1afcdcb13eb5ce8f6e534ac4566a3ab9');
			$redis->hGet('dirble','apikey') || $redis->hSet('dirble', 'apikey', '134aabbce2878ce0dbfdb23fb3b46265eded085b');

			// internal config hash control
			$redis->get('mpdconfhash') || $redis->set('mpdconfhash', '');
			$redis->get('netconfhash') || $redis->set('netconfhash', '');
			$redis->get('mpdconf_advanced') || $redis->set('mpdconf_advanced', 0);
			$redis->get('netconf_advanced') || $redis->set('netconf_advanced', 0);

			// developer parameters
			$redis->get('dev') || $redis->set('dev', 0);
			$redis->get('debug') || $redis->set('debug', 0);
			$redis->get('opcache') || $redis->set('opcache', 1);

			// HW platform data
			$redis->get('playerid') || $redis->set('playerid', '');
			$redis->get('hwplatform') || $redis->set('hwplatform', '');
			$redis->get('hwplatformid') || $redis->set('hwplatformid', '');

			// player control
			$redis->get('ao') || $redis->set('ao', 1);
			$redis->get('volume') || $redis->set('volume', 0);
			$redis->get('pl_length') || $redis->set('pl_length', 0);
			$redis->get('nextsongid') || $redis->set('nextsongid', 0);
			$redis->get('lastsongid') || $redis->set('lastsongid', 0);	
			break;
	}
	
}

// Ramplay functions
function rp_checkPLid($id,$mpd) {
$_SESSION['DEBUG'] .= "rp_checkPLid:$id |";
sendMpdCommand($mpd,'playlistid '.$id);
$response = readMpdResponse($mpd);
echo "<br>debug__".$response;
echo "<br>debug__".stripos($response,'MPD error');
	if (stripos($response,'OK')) {
	return true;
	} else {
	return false;
	}
}

//<< TODO: join with findPLposPath
function rp_findPath($id,$mpd) {
//$_SESSION['DEBUG'] .= "rp_findPath:$id |";
sendMpdCommand($mpd,'playlistid '.$id);
$idinfo = _parseFileListResponse(readMpdResponse($mpd));
$path = $idinfo[0]['file'];
//$_SESSION['DEBUG'] .= "Path:$path |";
return $path;
}

//<< TODO: join with rp_findPath()
function findPLposPath($songpos,$mpd) {
//$_SESSION['DEBUG'] .= "rp_findPath:$id |";
sendMpdCommand($mpd,'playlistinfo '.$songpos);
$idinfo = _parseFileListResponse(readMpdResponse($mpd));
$path = $idinfo[0]['file'];
//$_SESSION['DEBUG'] .= "Path:$path |";
return $path;
}

function rp_deleteFile($id,$mpd) {
$_SESSION['DEBUG'] .= "rp_deleteFile:$id |";
	if (unlink(rp_findPath($id,$mpd))) {
	return true;
	} else {
	return false;
	}
}

function rp_copyFile($id,$mpd) {
$_SESSION['DEBUG'] .= "rp_copyFile: $id|";
$path = rp_findPath($id,$mpd);
$song = parseFileStr($path,"/");
$realpath = "/mnt/".$path;
$ramplaypath = "/dev/shm/".$song;
$_SESSION['DEBUG'] .= "rp_copyFilePATH: $path $ramplaypath|";
	if (copy($realpath, $ramplaypath)) {
	$_SESSION['DEBUG'] .= "rp_addPlay:$id $song $path $pos|";
	return $path;
	} else {
	return false;
	}
}

function rp_updateFolder($mpd) {
$_SESSION['DEBUG'] .= "rp_updateFolder: |";
sendMpdCommand($mpd,"update ramplay");
}

function rp_addPlay($path,$mpd,$pos) {
$song = parseFileStr($path,"/");
$ramplaypath = "ramplay/".$song;
$_SESSION['DEBUG'] .= "rp_addPlay:$id $song $path $pos|";
addQueue($mpd,$ramplaypath);
sendMpdCommand($mpd,'play '.$pos);
}

function rp_clean() {
$_SESSION['DEBUG'] .= "rp_clean: |";
recursiveDelete('/dev/shm/');
}

function recursiveDelete($str){
	if(is_file($str)){
		return @unlink($str);
		// TODO: add search path in playlist and remove from playlist
	}
	elseif(is_dir($str)){
		$scan = glob(rtrim($str,'/').'/*');
		foreach($scan as $index=>$path){
			recursiveDelete($path);
		}
	}
}

function pushFile($filepath) {
	if (file_exists($filepath)) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename='.basename($filepath));
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($filepath));
		ob_clean();
		flush();
		readfile($filepath);
		return true;
	} else {
		return false;
	}
}

// check if mpd.conf or interfaces was modified outside
function hashCFG($action,$redis) {
	switch ($action) {
		
		case 'check_net':
		// --- CODE REWORK NEEDED ---
		$hash = md5_file('/etc/netctl/eth0');
		if ($redis->get('netconfhash') !== $hash) {
		    $redis->set('netconf_advanced', 1);
		    return false;
		} else {
		    $redis->set('netconf_advanced', 0);
		}
		break;
		
		case 'check_mpd':
		$hash = md5_file('/etc/mpd.conf');
		if ($redis->get('mpdconfhash') !== $hash) {
		    $redis->set('mpdconf_advanced', 1);
		    return false;
		} else {
		    $redis->set('mpdconf_advanced', 0);
		}
		break;
		
		case 'hash_net':
		$hash = md5_file('/etc/network/interfaces');
		playerSession('write',$db,'netconfhash',$hash); 
		break;
		
		case 'hash_mpd':
		$hash = md5_file('/etc/mpd.conf');
		playerSession('write',$db,'mpdconfhash',$hash); 
		break;
		
	} 
return true;
}


function runelog($title,$data = null) {
// Connect to Redis backend
$store = new Redis();
$store->connect('127.0.0.1', 6379);
$debug_level = $store->get('debug');
	if ($debug_level !== '0') {
	    if(is_array($data) OR is_object($data)) {
			foreach($data as $line) {
			error_log('[debug='.$debug_level.'] ### '.$title.' ###  '.$line,0);
			}
	    } else {
			error_log('[debug='.$debug_level.'] ### '.$title.' ###  '.$data,0);
	    }
	}
$store->close();
}


function debug_data($redis) {
		$output .= "\n";
		$output .= "###### System info ######\n";
		$output .=  file_get_contents('/proc/version');
		$output .= "\n";
		$output .=  "system load:\t".file_get_contents('/proc/loadavg');
		$output .= "\n";
		$output .= "HW platform:\t".$redis->get('hwplatform')." (".$redis->get('hwplatformid').")\n";
		$output .= "\n";
		$output .= "playerID:\t".$redis->get('playerid')."\n";
		$output .= "\n";
		$output .= "\n";
		$output .= "###### Audio backend ######\n";
		$output .=  file_get_contents('/proc/asound/version');
		$output .= "\n";
		$output .= "Card list: (/proc/asound/cards)\n";
		$output .= "--------------------------------------------------\n";
		$output .=  file_get_contents('/proc/asound/cards');
		$output .= "\n";
		$output .= "ALSA interface #0: (/proc/asound/card0/pcm0p/info)\n";
		$output .= "--------------------------------------------------\n";
		$output .=  file_get_contents('/proc/asound/card0/pcm0p/info');
		$output .= "\n";
		$output .= "ALSA interface #1: (/proc/asound/card1/pcm0p/info)\n";
		$output .= "--------------------------------------------------\n";
		$output .=  file_get_contents('/proc/asound/card1/pcm0p/info');
		$output .= "\n";
		$output .= "interface #0 stream status: (/proc/asound/card0/stream0)\n";
		$output .= "--------------------------------------------------------\n";
		$streaminfo = file_get_contents('/proc/asound/card0/stream0');
		if (empty($streaminfo)) {
		$output .= "no stream present\n";
		} else {
		$output .= $streaminfo;
		}
		$output .= "\n";
		$output .= "interface #1 stream status: (/proc/asound/card1/stream0)\n";
		$output .= "--------------------------------------------------------\n";
		$streaminfo = file_get_contents('/proc/asound/card1/stream0');
		if (empty($streaminfo)) {
		$output .= "no stream present\n";
		} else {
		$output .= $streaminfo;
		}
		$output .= "\n";
		$output .= "\n";
		$output .= "###### Kernel module snd_usb_audio settings ######\n";
		$output .= "\n";
		$sndusbinfo = sysCmd('systool -v -m snd_usb_audio');
		$output .= implode("\n",$sndusbinfo)."\n\n";
		$output .= "###### Kernel optimization parameters ######\n";
		$output .= "\n";
		$output .= "hardware platform:\t".$redis->get('hwplatform')."\n";
		$output .= "current orionprofile:\t".$redis->get('orionprofile')."\n";
		$output .= "\n";
		// 		$output .=  "kernel scheduler for mmcblk0:\t\t".((empty(file_get_contents('/sys/block/mmcblk0/queue/scheduler'))) ? "\n" : file_get_contents('/sys/block/mmcblk0/queue/scheduler'));
		$output .=  "kernel scheduler for mmcblk0:\t\t".file_get_contents('/sys/block/mmcblk0/queue/scheduler');
		$output .=  "/proc/sys/vm/swappiness:\t\t".file_get_contents('/proc/sys/vm/swappiness');
		$output .=  "/proc/sys/kernel/sched_latency_ns:\t".file_get_contents('/proc/sys/kernel/sched_latency_ns');
		#$output .=  "/proc/sys/kernel/sched_rt_period_us:\t".file_get_contents('/proc/sys/kernel/sched_rt_period_us');
		#$output .=  "/proc/sys/kernel/sched_rt_runtime_us:\t".file_get_contents('/proc/sys/kernel/sched_rt_runtime_us');
		$output .= "\n";
		$output .= "\n";
		$output .= "###### Filesystem mounts ######\n";
		$output .= "\n";
		$output .=  file_get_contents('/proc/mounts');
		$output .= "\n";
		$output .= "\n";
		$output .= "###### mpd.conf ######\n";
		$output .= "\n";
		$output .= file_get_contents('/etc/mpd.conf');
		$output .= "\n";
		$output .= "\n";
		$output .= "###### PHP backend ######\n";
		$output .= "\n";
		$output .= "php version:\t".phpVer()."\n";
		$output .= "debug level:\t".$redis->get('debug')."\n";
		$output .= "\n";
		$output .= "\n";
		// $output .= "###### SESSION ######\n";
		// $output .= "\n";
		// $output .= "STATUS:\t\t".session_status()."\n";
		// $output .= "ID:\t\t".session_id()."\n"; 
		// $output .= "SAVE PATH:\t".session_save_path()."\n";
		// $output .= "\n";
		// $output .= "\n";
		// $output .= "###### SESSION DATA ######\n";
		// $output .= "\n";
		// $output .= print_r($_SESSION);	
		$output .= "Page created ".round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']),3)." seconds. ";
		$output .= "\n";
		$output .= "\n";
return $output;
}

function waitSyWrk($redis,$jobID) {
	if (is_array($jobID)){	
		foreach ($jobID as $job) {
			do {
				usleep(650000);
			} while ($redis->sIsMember('w_lock', $job));
		}
	} else if (!empty($jobID)) {
		do {
		usleep(650000);
		} while ($redis->sIsMember('w_lock', $jobID));
	}
}

function wrk_control($redis,$action,$data) {
// accept $data['action'] $data['args'] from controller 
	switch ($action) {
		case 'newjob':
			// generate random jobid
			$jobID = wrk_jobID();
			$wjob = array( 
				'wrkcmd' => $data['wrkcmd'],
				'action' => $data['action'],
				'args' => $data['args']
			);
			$redis->hSet('w_queue', $jobID, json_encode($wjob));
			runelog('wrk_control data:', $redis->hGet('w_queue', $jobID));
		break;	
	}
// debug
runelog('[wrk] wrk_control($redis,'.$action.','.$data.') jobID=', $jobID);
return $jobID;
}

// search a string in a file and replace with another string the whole line.
function wrk_replaceTextLine($file,$inputArray,$strfind,$strrepl,$linelabel,$lineoffset) {
	runelog('wrk_replaceTextLine($file,$inputArray,$strfind,$strrepl,$linelabel,$lineoffset)','');
	runelog('wrk_replaceTextLine $file',$file);
	runelog('wrk_replaceTextLine $strfind',$strfind);
	runelog('wrk_replaceTextLine $strrepl',$strrepl);
	runelog('wrk_replaceTextLine $linelabel',$linelabel);
	runelog('wrk_replaceTextLine $lineoffset',$lineoffset);
	if (!empty($file)) {
	$fileData = file($file);
	} else {
	$fileData = $inputArray;
	}
	$newArray = array();
	if (isset($linelabel) && isset($lineoffset)) {
	$linenum = 0;
	}
	foreach($fileData as $line) {
		if (isset($linelabel) && isset($lineoffset)) {
		$linenum++;
			if (preg_match('/'.$linelabel.'/', $line)) {
			$lineindex = $linenum;
			runelog('line index match! $line',$lineindex);
			}
			if ((($lineindex+$lineoffset)-$linenum)==0) {
			  if (preg_match('/'.$strfind.'/', $line)) {
				$line = $strrepl."\n";
				runelog('internal loop $line',$line);
			  }
			}
		} else {
		  if (preg_match('/'.$strfind.'/', $line)) {
			$line = $strrepl."\n";
			runelog('replaceall $line',$line);
		  }
		}
	  $newArray[] = $line;
	}
	return $newArray;
}

// make device TOTALBACKUP (with switch DEV copy all /etc)
function wrk_backup($bktype) {
	if ($bktype == 'dev') {
	$filepath = "/run/totalbackup_".date('Y-m-d').".tar.gz";
	$cmdstring = "tar -czf ".$filepath." /var/lib/mpd /boot/cmdline.txt /var/www /etc";
	} else {
	$filepath = "/run/backup_".date('Y-m-d').".tar.gz";
	$cmdstring = "tar -czf ".$filepath." /var/lib/mpd /etc/mpd.conf /var/www/db/player.db";
	}
	
sysCmd($cmdstring);
return $filepath;
}


function wrk_opcache($action,$redis) {
	switch ($action) {
		case 'prime':
			if ($redis->get('opcache') == 1) {
			$ch = curl_init('http://localhost/command/cachectl.php?action=prime');
			curl_exec($ch);
			curl_close($ch);
			}
		runelog('wrk_opcache ', $action);
		break;
		
		case 'forceprime':
			$ch = curl_init('http://localhost/command/cachectl.php?action=prime');
			curl_exec($ch);
			curl_close($ch);
		runelog('wrk_opcache ', $action);
		break;
		
		case 'reset':
			$ch = curl_init('http://localhost/command/cachectl.php?action=reset');
			curl_exec($ch);
			curl_close($ch);
		runelog('wrk_opcache ', $action);
		break;
		
		case 'enable':
		wrk_opcache('reset');
		// opcache.ini
		$file = '/etc/php/conf.d/opcache.ini';
		$newArray = wrk_replaceTextLine($file,'','opcache.enable','opcache.enable=1','zend_extension',1);
		// Commit changes to /etc/php/conf.d/opcache.ini
		$fp = fopen($file, 'w');
		fwrite($fp, implode("",$newArray));
		fclose($fp);
		runelog('wrk_opcache ', $action);
		break;
		
		case 'disable':
		wrk_opcache('reset');
		// opcache.ini
		// -- REWORK NEEDED --
		$file = '/etc/php/conf.d/opcache.ini';
		$newArray = wrk_replaceTextLine($file,'','opcache.enable','opcache.enable=0','zend_extension',1);
		// Commit changes to /etc/php/conf.d/opcache.ini
		$fp = fopen($file, 'w');
		fwrite($fp, implode("",$newArray));
		fclose($fp);
		runelog('wrk_opcache ', $action);
		break;
	}
}

function wrk_netconfig($redis,$action,$args = null) {
$updateh = 0;
	switch ($action) {
		case 'setnics':
			// flush nics Redis hash table
			$redis->del('nics');
			$interfaces = sysCmd("ip addr |grep \"BROADCAST,\" |cut -d':' -f1-2 |cut -d' ' -f2");
			foreach ($interfaces as $interface) {
				$ip = sysCmd("ip addr list ".$interface." |grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
				$netmask = sysCmd("ip addr list ".$interface." |grep \"inet \" |cut -d' ' -f6|cut -d/ -f2");
				if (isset($netmask[0])) {
					$netmask = netmask($netmask[0]);
				} else {
					unset($netmask);
				}
				$gw = sysCmd("route -n |grep \"0.0.0.0\" |grep \"UG\" |cut -d' ' -f10");
				$dns = sysCmd("cat /etc/resolv.conf |grep \"nameserver\" |cut -d' ' -f2");
				$speed = sysCmd("ethtool ".$interface." | grep -i speed | cut -d':' -f2");
				if (empty(sysCmd("iwlist ".$interface." scan 2>&1 | grep \"Interface doesn't support scanning : Network is down\""))) {
					$redis->hSet('nics', $interface , json_encode(array('ip' => $ip[0], 'netmask' => $netmask, 'gw' => $gw[0], 'dns1' => $dns[0], 'dns2' => $dns[1], 'speed' => $speed[0],'wireless' => 0)));
				} else {
					$redis->hSet('nics', $interface , json_encode(array('ip' => $ip[0], 'netmask' => $netmask, 'gw' => $gw[0], 'dns1' => $dns[0], 'dns2' => $dns[1], 'speed' => $speed[0],'wireless' => 1)));
				}
			}
		break;
		
		case 'getnics':
 			foreach ($redis->hGetAll('nics') as $interface => $details) {
				$interfaces[$interface] = json_decode($details);
			}
			return $interfaces;
		break;
		
		case 'writecfg':
			// ArchLinux netctl config for wired ethernet
			if ($args->dhcp === '1') {
				// DHCP configuration
				$nic = "Description='".$args->name." dhcp connection'\n";
				$nic .= "Interface=".$args->name."\n";
				$nic .= "Connection=ethernet\n";
				$nic .= "ForceConnect=yes\n";
				$nic .= "SkipNoCarrier=yes\n";
				$nic .= "IP=dhcp\n";
				// write current network config
				$redis->set($args->name,json_encode(array( 'name' => $args->name, 'dhcp' => $args->dhcp )));
			} else {
				// STATIC configuration
				$nic = "Description='".$args->name." static configuration'\n";
				$nic .= "Interface=".$args->name."\n";
				$nic .= "Connection=ethernet\n";
				$nic .= "AutoWired=yes\n";
				$nic .= "ForceConnect=yes\n";
				$nic .= "SkipNoCarrier=yes\n";
				$nic .= "IP=static\n";
				$nic .= "Address=('".$args->ip."/".$args->netmask."')\n";
				$nic .= "Gateway='".$args->gw."'\n";
				if (!empty($args->dns2)) {
					$nic .= "DNS=('".$args->dns1."' '".$args->dns2."')\n";
				} else {
					$nic .= "DNS=('".$args->dns1."')\n";
				}
				// write current network config
				$redis->set($args->name,json_encode($args));
			}
			$fp = fopen('/etc/netctl/'.$args->name, 'w');
			fwrite($fp, $nic);
			fclose($fp);
			$updateh = 1;
		break;
		
		case 'manual':
			$file = '/etc/netctl/'.$args['name'];
			$fp = fopen($file, 'w');
			fwrite($fp, $args['config']);
			fclose($fp);
			$updateh = 1;
		break;
		
		case 'reset':
			wrk_netconfig($redis,'setnics');
			$args = new stdClass;
			$args->dhcp = '1';
			$args->name = 'eth0';
			wrk_netconfig($redis,'writecfg',$args);
			$updateh = 1;
		break;
		
	}

	if ($updateh === 1) {
		// activate configuration (RuneOS)
		sysCmd('mpc stop');
		sysCmd('netctl stop '.$args->name);
		sysCmd('ip addr flush dev '.$args->name);
		sysCmd('netctl reenable '.$args->name);
		if ($args->dhcp === '1') {
		// dhcp configuration
			// $cmd = 'systemctl enable ifplugd@'.$args->name;
			$cmd = "ln -s '/usr/lib/systemd/system/ifplugd@.service' '/etc/systemd/system/multi-user.target.wants/ifplugd@".$args->name.".service'";
			sysCmd($cmd);
			sysCmd('systemctl daemon-reload');
		} else {
		// static configuration
			// $cmd = 'systemctl disable ifplugd@'.$args->name;
			$cmd = "rm '/etc/systemd/system/multi-user.target.wants/ifplugd@".$args->name.".service'";
			sysCmd($cmd);
			sysCmd('systemctl daemon-reload');
			sysCmd('killall dhcpcd');
			sysCmd('killall ifplugd');
		}
		sysCmd('netctl start '.$args->name);
		sysCmd('systemctl restart mpd');
	}
// update hash if necessary
$updateh === 0 || $redis->set($args->name.'_hash',md5_file('/etc/netctl/'.$args->name));
}

function wrk_restore($backupfile) {
$path = "/run/".$backupfile;
$cmdstring = "tar xzf ".$path." --overwrite --directory /";
	if (sysCmd($cmdstring)) {
		recursiveDelete($path);
	}
}

function wrk_jobID() {
$jobID = md5(uniqid(rand(), true));
return $jobID;
}

function wrk_checkStrSysfile($sysfile,$searchstr) {
$file = stripcslashes(file_get_contents($sysfile));
// debug
runelog('wrk_checkStrSysfile('.$sysfile.','.$searchstr.')',$searchstr);
	if (strpos($file, $searchstr)) {
	return true;
	} else {
	return false;
	}
}

function wrk_cleanDistro($redis) {
runelog('function CLEAN DISTRO invoked!!!','');
// remove mpd.db
sysCmd('systemctl stop mpd');
redisDatastore($redis,'reset');
sleep(1);
sysCmd('rm /var/lib/mpd/mpd.db');
sysCmd('rm /var/lib/mpd/mpdstate');
// reset /var/log/*
sysCmd('rm -f /var/log/*');
// reset /var/log/nginx/*
sysCmd('rm -f /var/log/nginx/*');
// reset /var/log/atop/*
sysCmd('rm -f /var/log/atop/*');
// reset /var/log/old/*
sysCmd('rm -f /var/log/old/*');
// reset /var/log/samba/*
sysCmd('rm -rf /var/log/samba/*');
// reset /root/ logs
sysCmd('rm -rf /root/.*');
// delete .git folder
sysCmd('rm -rf /var/www/.git');
// switch smb.conf to 'production' state
sysCmd('rm /var/www/_OS_SETTINGS/etc/samba/smb.conf');
sysCmd('ln -s /var/www/_OS_SETTINGS/etc/samba/smb-prod.conf /var/www/_OS_SETTINGS/etc/samba/smb.conf');
// switch nginx.conf to 'production' state
sysCmd('systemctl stop nginx');
sysCmd('rm /etc/nginx/nginx.conf');
sysCmd('ln -s /var/www/_OS_SETTINGS/etc/nginx/nginx-prod.conf /etc/nginx/nginx.conf');
sysCmd('systemctl start nginx');
// reset /var/log/runeaudio/*
sysCmd('rm -f /var/log/runeaudio/*');
// rest mpd.conf
wrk_mpdconf($redis,'reset');
// restore default player.db
sysCmd('cp /var/www/db/player.db.default /var/www/db/player.db');
sysCmd('chmod 777 /var/www/db/player.db');
sysCmd('poweroff');
}

function wrk_audioOutput($redis,$action,$args = null) {
	switch ($action) {
		case 'refresh':
			$redis->del('acards');
			// $acards = sysCmd("cat /proc/asound/cards | grep : | cut -d '[' -f 2 | cut -d ']' -f 1");
			$acards = sysCmd("cat /proc/asound/cards | grep : | cut -d '[' -f 2 | cut -d ':' -f 2");
			runelog('/proc/asound/cards',$acards);
			$i = 0;
			foreach ($acards as $card) {
				$card = explode(' - ', $card);
				// $description = sysCmd("cat /proc/asound/cards | grep : | cut -d ':' -f 2 | cut -d ' ' -f 4-20");
				$card = $card[1];
				runelog('wrk_audioOutput cart string: ', $card);
				$description = sysCmd("aplay -l -v | grep \"\[".$card."\]\"");
				$desc = array();
				$subdeviceid = explode(':',$description[0]);
				$subdeviceid = explode(',',trim($subdeviceid[1]));
				$subdeviceid = explode(' ',trim($subdeviceid[1]));
				$data['device'] = 'hw:'.$i.','.$subdeviceid[1];
				if ($redis->hGet('acards_details',$card)) {
					$details = json_decode($redis->hGet('acards_details',$card));
					if (isset($details->mixer_control)) {
						$volsteps = sysCmd("amixer -c ".$i." get \"".$details->mixer_control."\" | grep Limits | cut -d ':' -f 2 | cut -d ' ' -f 4,6");
						$volsteps = explode(' ',$volsteps[0]);
						$data['volmin'] = $volsteps[0];
						$data['volmax'] = $volsteps[1];
						$data['mixer_device'] = "hw:".$details->mixer_numid;
						$data['mixer_control'] = $details->mixer_control;
					}
					$data['extlabel'] = $details->extlabel;
				} 
				$data['name'] = $card;
				$data['type'] = 'alsa';
				$data['system'] = trim($description[0]);
				$redis->hSet('acards',$card,json_encode($data));
				$i++;
			}
		break;
		
		case 'setdetails':
			$redis->hSet('acards_labels',$args['card'],json_encode($args['details']));
		break;
	}
}

function wrk_i2smodule($redis,$args) {
sysCmd('mpc stop').usleep(300000);
	switch ($args) {
		case 'none':
			sysCmd('rmmod snd_soc_iqaudio_dac').usleep(300000);
			sysCmd('rmmod snd_soc_hifiberry_digi').usleep(300000);
			sysCmd('rmmod snd_soc_hifiberry_dac').usleep(300000);
			sysCmd('rmmod snd_soc_wm8804').usleep(300000);
			sysCmd('rmmod snd_soc_pcm512x').usleep(300000);
			sysCmd('rmmod snd_soc_pcm5102a');
		break;
		
		case 'berrynos':
			sysCmd('modprobe bcm2708_dmaengine').usleep(300000);
			sysCmd('modprobe snd_soc_wm8804').usleep(300000);
			sysCmd('modprobe snd_soc_bcm2708_i2s').usleep(300000);
			sysCmd('modprobe snd_soc_pcm5102a').usleep(300000);
			sysCmd('modprobe snd_soc_hifiberry_dac');
		break;		
		
		case 'berrynosmini':
			sysCmd('modprobe bcm2708_dmaengine').usleep(300000);
			sysCmd('modprobe snd_soc_wm8804').usleep(300000);
			sysCmd('modprobe snd_soc_bcm2708_i2s').usleep(300000);
			sysCmd('modprobe snd_soc_pcm5102a').usleep(300000);
			sysCmd('modprobe snd_soc_hifiberry_dac');
		break;
				
		case 'hifiberrydac':
			sysCmd('modprobe bcm2708_dmaengine').usleep(300000);
			sysCmd('modprobe snd_soc_wm8804').usleep(300000);
			sysCmd('modprobe snd_soc_bcm2708_i2s').usleep(300000);
			sysCmd('modprobe snd_soc_pcm5102a').usleep(300000);
			sysCmd('modprobe snd_soc_hifiberry_dac');
		break;
				
		case 'hifiberrydigi':
			sysCmd('modprobe bcm2708_dmaengine').usleep(300000);
			sysCmd('modprobe snd_soc_wm8804').usleep(300000);
			sysCmd('modprobe snd_soc_bcm2708_i2s').usleep(300000);
			sysCmd('modprobe snd_soc_pcm5102a').usleep(300000);
			sysCmd('modprobe snd_soc_hifiberry_digi');
		break;
				
		case 'iqaudiopidac':
			sysCmd('modprobe bcm2708_dmaengine').usleep(300000);
			sysCmd('modprobe snd_soc_wm8804').usleep(300000);
			sysCmd('modprobe snd_soc_bcm2708_i2s').usleep(300000);
			sysCmd('modprobe snd_soc_pcm512x').usleep(300000);
			sysCmd('modprobe snd_soc_iqaudio_dac');
		break;
				
		case 'raspi2splay3':
			sysCmd('modprobe bcm2708_dmaengine').usleep(300000);
			sysCmd('modprobe snd_soc_wm8804').usleep(300000);
			sysCmd('modprobe snd_soc_bcm2708_i2s').usleep(300000);
			sysCmd('modprobe snd_soc_pcm5102a').usleep(300000);
			sysCmd('modprobe snd_soc_hifiberry_dac');
		break;
	}
$redis->set('i2smodule',$args);
wrk_mpdconf($redis,'refresh');
}

function wrk_mpdconf($redis,$action,$args = null) {
// set mpd.conf file header
$header = "###################################\n";
$header .= "# Auto generated mpd.conf file\n";
$header .= "# please DO NOT edit it manually!\n";
$header .= "# Use RuneUI MPD config section\n";
$header .= "###################################\n";
$header .= "\n";
	switch ($action) {
		case 'reset':
			// default MPD config
				$redis->hSet('mpdconf','zeroconf_enabled','yes');
				$redis->hSet('mpdconf','zeroconf_name','runeaudio');
				$redis->hSet('mpdconf','log_level','verbose');
				$redis->hSet('mpdconf','bind_to_address','any');
				$redis->hSet('mpdconf','port','6600');
				$redis->hSet('mpdconf','max_connections','20');
				$redis->hSet('mpdconf','user','mpd');
				$redis->hSet('mpdconf','db_file','/var/lib/mpd/mpd.db');
				$redis->hSet('mpdconf','sticker_file','/var/lib/mpd/sticker.sql');
				$redis->hSet('mpdconf','log_file','/var/log/runeaudio/mpd.log');
				$redis->hSet('mpdconf','pid_file','/var/run/mpd/pid');
				$redis->hSet('mpdconf','music_directory','/mnt/MPD');
				$redis->hSet('mpdconf','playlist_directory','/var/lib/mpd/playlists');
				$redis->hSet('mpdconf','state_file','/var/lib/mpd/mpdstate');
				$redis->hSet('mpdconf','follow_outside_symlinks','yes');
				$redis->hSet('mpdconf','follow_inside_symlinks','yes');
				$redis->hSet('mpdconf','auto_update','no');
				$redis->hSet('mpdconf','filesystem_charset','UTF-8');
				$redis->hSet('mpdconf','id3v1_encoding','UTF-8');
				$redis->hSet('mpdconf','volume_normalization','no');
				$redis->hSet('mpdconf','audio_buffer_size','2048');
				$redis->hSet('mpdconf','buffer_before_play','20%');
				$redis->hSet('mpdconf','gapless_mp3_playback','yes');
				$redis->hSet('mpdconf','mixer_type','software');
				$redis->hSet('mpdconf','curl','yes');
				$redis->hSet('mpdconf','ffmpeg','no');
				wrk_mpdconf($redis,'writecfg');
		break;
		
		case 'writecfg':
			$mpdcfg = $redis->hGetAll('mpdconf');
			$output = null;
			// --- general settings ---
			foreach ($mpdcfg as $param => $value) {	
				if ($param === 'audio_output_interface' OR $param === 'dsd_usb') {
					continue;
				}
				
				if ($param === 'mixer_type') {
					if ($value === 'software' OR $value === 'hardware') {
						$redis->set('volume', 1);
						if ($value === 'hardware') break;
					} else {
						$redis->set('volume', 0);
					}
				} 
				
				if ($param === 'log_level' && $value === 'none') {
					$redis->hDel('mpdconf','log_file');
					continue;
				}
				
				if ($param === 'log_level' && $value !== 'none') {
					$redis->hSet('mpdconf','log_file','/var/log/runeaudio/mpd.log');
				}					
				
				if ($param === 'user' && $value === 'mpd') {
					$output .= $param." \t\"".$value."\"\n";
					$output .= "group \t\"audio\"\n";
					continue;
				}

				if ($param === 'user' && $value === 'root') {
					$output .= $param." \t\"".$value."\"\n";
					$output .= "group \t\"root\"\n";
					continue;
				} 
				
				if ($param === 'bind_to_address') {
					$output .= "bind_to_address \"/run/mpd.sock\"\n";
				} 
			
				if ($param === 'ffmpeg') {
					// --- decoder plugin ---
					$output .="\n";
					$output .="decoder {\n";
					$output .="plugin \t\"ffmpeg\"\n";
					$output .="enabled \"".$value."\"\n";
					$output .="}\n";
				continue;
				} 
				
				if ($param === 'curl') {
				// --- input plugin ---
				$output .="\n";
				$output .="input {\n";
				$output .="plugin \t\"curl\"\n";
					if ($redis->hget('proxy','enable') === '1') {
						$output .="proxy \t\"".($redis->hget('proxy','host'))."\"\n";
						if ($redis->hget('proxy','user') !== '') {
							$output .="proxy_user \t\"".($redis->hget('proxy','user'))."\"\n"; 
							$output .="proxy_password \t\"".($redis->hget('proxy','pass'))."\"\n"; 
						}
					}
				$output .="}\n";
				continue;
				}
				$output .= $param." \t\"".$value."\"\n";
			}
			$output = $header.$output;	
			// --- audio output ---
			$acards = $redis->hGetAll('acards');
			foreach ($acards as $card) {
				$card= json_decode($card);
				$output .="\n";
				$output .="audio_output {\n";
				$output .="name \t\t\"".$card->name."\"\n";
				$output .="type \t\t\"".$card->type."\"\n";
				$output .="device \t\t\"".$card->device."\"\n";
				if (isset($card->mixer_device)) $output .="mixer_device \t\"".$card->mixer_device."\"\n";
				if (isset($card->mixer_control)) $output .="mixer_control \t\"".$card->mixer_control."\"\n";
				$output .="auto_resample \t\"no\"\n";
				$output .="auto_format \t\"no\"\n";
				if ($redis->get('ao') === $card->name) $output .="enabled \t\"yes\"\n";
				$output .="}\n";
			}
			$output .="\n";
			// debug
			// runelog($output);
			// write mpd.conf file
			$fh = fopen('/etc/mpd.conf', 'w');
			fwrite($fh, $output);
			fclose($fh);
			// update hash
			$redis->set('mpdconfhash',md5_file('/etc/mpd.conf'));
		break;
		
		case 'update':
			foreach ($args as $param => $value) {
				$redis->hSet('mpdconf',$param,$value);
			}
			wrk_mpdconf($redis,'writecfg');
		break;
		
		case 'switchao':
			$redis->set('ao',$args);
			wrk_mpdconf($redis,'writecfg');
			wrk_shairport($redis,$args);
			syscmd('mpc enable only "'.$args.'"');
		break;
		
		case 'refresh':
			wrk_audioOutput($redis,'refresh');
			wrk_mpdconf($redis,'writecfg');
			wrk_mpdconf($redis,'restart');
		break;
		
		case 'restart':
			sysCmd('systemctl restart mpd');
			// restart mpdscribble
			if ($redis->get('scrobbling_lastfm') === '1') {
			sysCmd('systemctl restart mpdscribble');
			}
		break;
	}
}

function wrk_shairport($redis,$ao,$name = null) {
if (!isset($name)) {
$name = $redis->hGet('airplay','name');
}
$acard = json_decode($redis->hget('acards',$ao));
runelog('acard details: ',$acard);
$file = '/usr/lib/systemd/system/shairport.service';
$newArray = wrk_replaceTextLine($file,'','ExecStart','ExecStart=/usr/local/bin/shairport -w --name='.$name.' --on-start=/var/www/command/airplay.sh --on-stop=/var/www/command/airplay.sh -o alsa -- -d '.$acard->device);
runelog('shairport.service :',$newArray);
// Commit changes to /usr/lib/systemd/system/shairport.service
$fp = fopen($file, 'w');
fwrite($fp, implode("",$newArray));
fclose($fp);
// update systemd
sysCmd('systemctl daemon-reload');
	if ($redis->hGet('airplay','enable') === '1') {
		runelog('restart shairport');
		sysCmd('systemctl restart shairport');
	}
}

function wrk_sourcemount($db,$action,$id) {
	switch ($action) {
		
		case 'mount':
			$dbh = cfgdb_connect($db);
			$mp = cfgdb_read('cfg_source',$dbh,'',$id);
			$mpdproc = getMpdDaemonDetalis();
			sysCmd("mkdir \"/mnt/MPD/NAS/".$mp[0]['name']."\"");
			if ($mp[0]['type'] == 'cifs') {
			// smb/cifs mount
			$auth = 'guest';
			if (!empty($mp[0]['username'])) {
				$auth = "username=".$mp[0]['username'].",password=".$mp[0]['password'];
			}
				$mountstr = "mount -t cifs \"//".$mp[0]['address']."/".$mp[0]['remotedir']."\" -o ".$auth.",sec=ntlm,uid=".$mpdproc['uid'].",gid=".$mpdproc['gid'].",rsize=".$mp[0]['rsize'].",wsize=".$mp[0]['wsize'].",iocharset=".$mp[0]['charset'].",".$mp[0]['options']." \"/mnt/MPD/NAS/".$mp[0]['name']."\"";
			} else {
				// nfs mount
				$mountstr = "mount -t nfs -o rsize=".$mp[0]['rsize'].",wsize=".$mp[0]['wsize'].",".$mp[0]['options']." \"".$mp[0]['address'].":/".$mp[0]['remotedir']."\" \"/mnt/MPD/NAS/".$mp[0]['name']."\"";
			}
			// debug
			runelog('mount string',$mountstr);
			$sysoutput = sysCmd($mountstr);
			// -- REWORK NEEDED --
			runelog('system response',var_dump($sysoutput));
			if (empty($sysoutput)) {
				if (!empty($mp[0]['error'])) {
				$mp[0]['error'] = '';
				cfgdb_update('cfg_source',$dbh,'',$mp[0]);
				}
			$return = 1;
			} else {
			sysCmd("rmdir \"/mnt/MPD/NAS/".$mp[0]['name']."\"");
			$mp[0]['error'] = implode("\n",$sysoutput);
			cfgdb_update('cfg_source',$dbh,'',$mp[0]);
			$return = 0;
			}	
		break;
		
		case 'mountall':
		$dbh = cfgdb_connect($db);
		$mounts = cfgdb_read('cfg_source',$dbh);
		foreach ($mounts as $mp) {
			if (!wrk_checkStrSysfile('/proc/mounts',$mp['name']) ) {
			$return = wrk_sourcemount($db,'mount',$mp['id']);
			}
		}
		$dbh = null;
		break;
		
	}
return $return;
}

function wrk_sourcecfg($db,$action,$args) {
runelog('wrk_sourcecfg($db,'.$action.')');
	switch ($action) {

		case 'add':
		$dbh = cfgdb_connect($db);
		unset($args->id);
		// format values string
		$values = null;
		foreach ($args as $key => $value) {
			if ($key == 'error') {
			$values .= "'".SQLite3::escapeString($value)."'";
			// debug
			runelog('wrk_sourcecfg($db,$queueargs) case ADD scan $values',$values);
			} else {
			$values .= "'".SQLite3::escapeString($value)."',";
			// debug
			runelog('wrk_sourcecfg($db,$queueargs) case ADD scan $values',$values);
			}
		}
		// debug
		runelog('wrk_sourcecfg($db,$queueargs) complete $values string',$values);
		// write new entry
		cfgdb_write('cfg_source',$dbh,$values);
		$newmountID = $dbh->lastInsertId();
		$dbh = null;
		if (wrk_sourcemount($db,'mount',$newmountID)) {
		$return = 1;
		} else {
		$return = 0;
		}
		break;
	
		case 'edit':
		$dbh = cfgdb_connect($db);
		$mp = cfgdb_read('cfg_source',$dbh,'',$args->id);
		cfgdb_update('cfg_source',$dbh,'',$args);
		sysCmd('mpc stop');
		usleep(500000);
		sysCmd("umount -f \"/mnt/MPD/NAS/".$mp[0]['name']."\"");
			if ($mp[0]['name'] != $args->name) {
			sysCmd("rmdir \"/mnt/MPD/NAS/".$mp[0]['name']."\"");
			sysCmd("mkdir \"/mnt/MPD/NAS/".$args->name."\"");
			}
		if (wrk_sourcemount($db,'mount',$args->id)) {
		$return = 1;
		} else {
		$return = 0;
		}
		runelog('wrk_sourcecfg(edit) exit status',$return);
		$dbh = null;
		break;
	
		case 'delete':
		$dbh = cfgdb_connect($db);
		$mp = cfgdb_read('cfg_source',$dbh,'',$args->id);
		sysCmd('mpc stop');
		usleep(500000);
		sysCmd("umount -f \"/mnt/MPD/NAS/".$mp[0]['name']."\"");
		sleep(3);
		sysCmd("rmdir \"/mnt/MPD/NAS/".$mp[0]['name']."\"");
		if (cfgdb_delete('cfg_source',$dbh,$args->id)) {
		$return = 1;
		} else {
		$return = 0;
		}
		$dbh = null;
		break;
	
		case 'reset': 
		$dbh = cfgdb_connect($db);
		$source = cfgdb_read('cfg_source',$dbh);
			foreach ($source as $mp) {
			runelog('wrk_sourcecfg() internal loop $mp[name]',$mp['name']);
			sysCmd('mpc stop');
			usleep(500000);
			sysCmd("umount -f \"/mnt/MPD/NAS/".$mp['name']."\"");
			sysCmd("rmdir \"/mnt/MPD/NAS/".$mp['name']."\"");
			}
		if (cfgdb_delete('cfg_source',$dbh)) {
		$return = 1;
		} else {
		$return = 0;
		}
		$dbh = null;
		break;
		
	}

return $return;
}

function wrk_getHwPlatform() {
$file = '/proc/cpuinfo';
	$fileData = file($file);
	foreach($fileData as $line) {
		if (substr($line, 0, 8) == 'Hardware') {
			$arch = trim(substr($line, 11, 50));
			// debug
			runelog('wrk_getHwPlatform() /proc/cpu string',$arch);
				switch($arch) {
					
					// RaspberryPi
					case 'BCM2708':
					$arch = '01';
					break;
					
					// UDOO
					case 'SECO i.Mx6 UDOO Board':
					$arch = '02';
					break;
					
					// CuBox
					case 'Marvell Dove (Flattened Device Tree)':
					$arch = '03';
					break;
					
					// BeagleBone Black
					case 'Generic AM33XX (Flattened Device Tree)':
					$arch = '04';
					break;					
					
					// Utilite Standard
					case 'Compulab CM-FX6':
					$arch = '05';
					break;
					
					default:
					$arch = '--';
					break;
				}
		}
	}
if (!isset($arch)) {
$arch = '--';
}
return $arch;
}

function wrk_setHwPlatform($redis) {
$arch = wrk_getHwPlatform();
runelog('arch= ',$arch);
$playerid = wrk_playerID($arch);
$redis->set('playerid', $playerid);
runelog('playerid= ',$playerid);
// register platform into database
	switch($arch) {
		case '01':
		$redis->set('hwplatform','RaspberryPi');
		$redis->set('hwplatformid',$arch);
		break;
		
		case '02':
		$redis->set('hwplatform','UDOO');
		$redis->set('hwplatformid',$arch);
		break;
		
		case '03':
		$redis->set('hwplatform','CuBox');
		$redis->set('hwplatformid',$arch);
		break;
		
		case '04':
		$redis->set('hwplatform','BeagleBone Black');
		$redis->set('hwplatformid',$arch);
		break;
		
		case '05':
		$redis->set('hwplatform','Utilite Standard');
		$redis->set('hwplatformid',$arch);
		break;
		
		default:
		$redis->set('hwplatform','unknown');
		$redis->set('hwplatformid',$arch);
	}
}

function wrk_playerID($arch) {
// $playerid = $arch.md5(uniqid(rand(), true)).md5(uniqid(rand(), true));
$playerid = $arch.md5_file('/sys/class/net/eth0/address');
return $playerid;
}

function wrk_sysChmod() {
sysCmd('chmod -R 777 /var/www/db');
sysCmd('chmod a+x /var/www/command/orion_optimize.sh');
sysCmd('chmod 777 /run');
sysCmd('chmod 777 /run/sess*');
sysCmd('chmod a+rw /etc/mpd.conf');
sysCmd('chmod a+rw /etc/mpdscribble.conf');
}

function wrk_sysEnvCheck($arch,$install) {
	if ($arch == '01' OR $arch == '02' OR $arch == '03' OR $arch == '04' ) {
	 // /etc/rc.local
	 $a = '/etc/rc.local';
	 $b = '/var/www/_OS_SETTINGS/etc/rc.local';
	 if (md5_file($a) != md5_file($b)) {
	 sysCmd('cp '.$b.' '.$a);
	 }
	 
	 // /etc/samba/smb.conf
	 $a = '/etc/samba/smb.conf';
	 $b = '/var/www/_OS_SETTINGS/etc/samba/smb.conf';
	 if (md5_file($a) != md5_file($b)) {
	 sysCmd('cp '.$b.' '.$a.' ');
	 }
	 // /etc/nginx.conf
	 $a = '/etc/nginx/nginx.conf';
	 $b = '/var/www/_OS_SETTINGS/etc/nginx/nginx.conf';
	 if (md5_file($a) != md5_file($b)) {
	 sysCmd('cp '.$b.' '.$a.' ');
	 // stop nginx
	 sysCmd('killall -9 nginx');
	 // start nginx
	 sysCmd('nginx');
	 }
	 // /etc/php5/cli/php.ini
	 $a = '/etc/php5/cli/php.ini';
	 $b = '/var/www/_OS_SETTINGS/etc/php5/cli/php.ini';
	 if (md5_file($a) != md5_file($b)) {
	 sysCmd('cp '.$b.' '.$a.' ');
	 $restartphp = 1;
	 }
	 // /etc/php5/fpm/php-fpm.conf
	 $a = '/etc/php5/fpm/php-fpm.conf';
	 $b = '/var/www/_OS_SETTINGS/etc/php5/fpm/php-fpm.conf';
	 if (md5_file($a) != md5_file($b)) {
	 sysCmd('cp '.$b.' '.$a.' ');
	 $restartphp = 1;
	 }
	 // /etc/php5/fpm/php.ini
	 $a = '/etc/php5/fpm/php.ini';
	 $b = '/var/www/_OS_SETTINGS/etc/php5/fpm/php.ini';
	 if (md5_file($a) != md5_file($b)) {
	 sysCmd('cp '.$b.' '.$a.' ');
	 $restartphp = 1;
	 }
	 
		if ($install == 1) {
		 // remove autoFS for NAS mount
		 sysCmd('cp /var/www/_OS_SETTINGS/etc/auto.master /etc/auto.master');
		 sysCmd('rm /etc/auto.nas');
		 sysCmd('systemctl restart autofs');
		 // /etc/php5/mods-available/apc.ini
		 sysCmd('cp /var/www/_OS_SETTINGS/etc/php5/mods-available/apc.ini /etc/php5/mods-available/apc.ini');
		 // /etc/php5/fpm/pool.d/ erase
		 sysCmd('rm /etc/php5/fpm/pool.d/*');
		 // /etc/php5/fpm/pool.d/ copy
		 sysCmd('cp /var/www/_OS_SETTINGS/etc/php5/fpm/pool.d/* /etc/php5/fpm/pool.d/');
		 $restartphp = 1;
		}
		
	 // /etc/php5/fpm/pool.d/command.conf
	 $a = '/etc/php5/fpm/pool.d/command.conf';
	 $b = '/var/www/_OS_SETTINGS/etc/php5/fpm/pool.d/command.conf';
	 if (md5_file($a) != md5_file($b)) {
	 sysCmd('cp '.$b.' '.$a.' ');
	 $restartphp = 1;
	 }
	 // /etc/php5/fpm/pool.d/db.conf
	 $a = '/etc/php5/fpm/pool.d/db.conf';
	 $b = '/var/www/_OS_SETTINGS/etc/php5/fpm/pool.d/db.conf';
	 if (md5_file($a) != md5_file($b)) {
	 sysCmd('cp '.$b.' '.$a.' ');
	 $restartphp = 1;
	 }
	 // /etc/php5/fpm/pool.d/display.conf
	 $a = '/etc/php5/fpm/pool.d/display.conf';
	 $b = '/var/www/_OS_SETTINGS/etc/php5/fpm/pool.d/display.conf';
	 if (md5_file($a) != md5_file($b)) {
	 sysCmd('cp '.$b.' '.$a.' ');
	 $restartphp = 1;
	 }
		// (RaspberryPi arch)
		if ($arch == '01') {
			$a = '/boot/cmdline.txt';
			$b = '/var/www/_OS_SETTINGS/boot/cmdline.txt';
			if (md5_file($a) != md5_file($b)) {
			sysCmd('cp '.$b.' '.$a.' ');
			// /etc/fstab
			$a = '/etc/fstab';
			$b = '/var/www/_OS_SETTINGS/etc/fstab_raspberry';
			if (md5_file($a) != md5_file($b)) {
				sysCmd('cp '.$b.' '.$a.' ');
				$reboot = 1;
				}
			}
		}
		if (isset($restartphp) && $restartphp == 1) {
		sysCmd('service php5-fpm restart');
		}
		if (isset($reboot) && $reboot == 1) {
		sysCmd('reboot');
		}
	}	
}

function wrk_NTPsync($ntpserver) {
// debug
runelog('NTP SERVER',$ntpserver);
	if (sysCmd('ntpdate '.$ntpserver)) {
		return $ntpserver;
	} else {
		return false;
	}
}

function wrk_changeHostname($db,$newhostname,$redis) {
// change system hostname
sysCmd('hostnamectl set-hostname '.$newhostname);
// restart avahi-daemon
sysCmd('systemctl restart avahi-daemon');
// reconfigure MPD
sysCmd('systemctl stop mpd');
$dbh = cfgdb_connect($db);
cfgdb_update('cfg_mpd',$dbh,'zeroconf_name',$newhostname);
$dbh = null;
wrk_mpdconf('/etc',$db,$redis);
// restart MPD
sysCmd('systemctl start mpd');
// restart SAMBA << TODO: use systemd!!!
sysCmd('killall -HUP smbd && killall -HUP nmbd');
// TODO: restart MiniDLNA
}

function alsa_findHwMixerControl($cardID) {
$cmd = "amixer -c ".$cardID." |grep \"mixer control\"";
$str = sysCmd($cmd);
$hwmixerdev = substr(substr($str[0], 0, -(strlen($str[0]) - strrpos($str[0], "'"))), strpos($str[0], "'")+1);
return $hwmixerdev;
}

function ui_notify($title = null, $text, $type = null ) {
	$output = array( 'title' => $title, 'text' => $text, 'type' => $type);
	ui_render('notify',json_encode($output));
}

function ui_notify_async($title, $text, $jobID = null, $icon = null, $opacity = null, $hide = null) {
$fork_pid = pcntl_fork();
	if (!$fork_pid) {
	runelog('fork PID: ', posix_getpid());
			if (isset($jobID)) {
				$redisT = new Redis();
				$redisT->connect('127.0.0.1', 6379);
				if (!($redisT->sIsMember('w_lock', $jobID))) {
						usleep(800000);
				} else {
					do {
						runelog('(ui_notify_async) inside while lock wait cicle',$jobID);
						usleep(600000);
					} while ($redisT->sIsMember('w_lock', $jobID));
				}
				$redisT->close();
			} else {
			usleep(650000);
			}
		// $output = json_encode(array( 'title' => $title, 'text' => $text, 'icon' => $icon, 'opacity' => $opacity, 'hide' => $hide ));
		$output = json_encode(array( 'title' => htmlentities($title,ENT_XML1,'UTF-8'), 'text' => htmlentities($text,ENT_XML1,'UTF-8') ));
		runelog('notify JSON string: ', $output);
		ui_render('notify',$output);
		exit(0);
	} else {
	runelog('parent PID: ', posix_getpid());
	pcntl_waitpid($fork_pid, $status, WNOHANG|WUNTRACED);
	runelog('child status: ', $status);
	}
}

function ui_status($mpd,$status) {
	$curTrack = getTrackInfo($mpd,$status['song']);
	if (isset($curTrack[0]['Title'])) {
		$status['currentartist'] = $curTrack[0]['Artist'];
		$status['currentsong'] = htmlentities($curTrack[0]['Title'],ENT_XML1,'UTF-8');
		$status['currentalbum'] = $curTrack[0]['Album'];
		$status['fileext'] = parseFileStr($curTrack[0]['file'],'.');
	} else {
		$path = parseFileStr($curTrack[0]['file'],'/');
		$status['fileext'] = parseFileStr($curTrack[0]['file'],'.');
		$status['currentartist'] = "";
		// $status['currentsong'] = $song;
		if (!empty($path)){
			$status['currentalbum'] = $path;
		} else {
			$status['currentalbum'] = '';
		}
	}
$status['radioname'] = $curTrack[0]['Name'];
return $status;
}

function ui_lastFM_coverart($artist,$album,$lastfm_apikey,$proxy) {
if (!empty($album)) {
$url = "http://ws.audioscrobbler.com/2.0/?method=album.getinfo&api_key=".$lastfm_apikey."&artist=".urlencode($artist)."&album=".urlencode($album)."&format=json";
unset($artist);
} else {
$url = "http://ws.audioscrobbler.com/2.0/?method=artist.getinfo&api_key=".$lastfm_apikey."&artist=".urlencode($artist)."&format=json";
$artist = 1;
}
// debug
//echo $url;
// $ch = curl_init($url);
// curl_setopt($ch, CURLOPT_HEADER, 0);
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// $response = curl_exec($ch);
// curl_close($ch);
// $output = json_decode($response,true);
$output = json_decode(curlGet($url,$proxy),true);

// debug
runelog('coverart lastfm query URL',$url);
// debug++
// echo "<pre>";
// print_r($output);
// echo "</pre>";

// key [3] == extralarge last.fm image
// key [4] == mega last.fm image
	if(isset($artist)) {
	runelog('coverart lastfm query URL',$output['artist']['image'][3]['#text']);
	return $output['artist']['image'][3]['#text'];
	} else {
	runelog('coverart lastfm query URL',$output['album']['image'][3]['#text']);
	return $output['album']['image'][3]['#text'];
	}
}

// push UI update to NGiNX channel
function ui_render($channel,$data) {
curlPost('http://127.0.0.1/pub?id='.$channel,$data);
}

function ui_mpd_response($mpd,$notify = null) {
runelog('ui_mpd_response invoked','');
$response = json_encode(readMpdResponse($mpd));
// --- TODO: check this condition
if (strpos($response, "OK") && isset($notify)) {
runelog('send UI notify: ', $notify);
	ui_notify($notify['title'], $notify['text']);
	}
echo $response;
}

function curlPost($url,$data,$proxy = null) {
$ch = curl_init($url);
if (isset($proxy)) {
$proxy['user'] === '' || curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['user'].':'.$proxy['pass']);
curl_setopt($ch, CURLOPT_PROXY, $proxy['host']);
//runelog('cURL proxy HOST: ',$proxy['host']);
//runelog('cURL proxy USER: ',$proxy['user']);
//runelog('cURL proxy PASS: ',$proxy['pass']);
}
 curl_setopt($ch, CURLOPT_TIMEOUT, 2);
 curl_setopt($ch, CURLOPT_POST, 1);
 curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
 curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
 curl_setopt($ch, CURLOPT_HEADER, 0);  // DO NOT RETURN HTTP HEADERS
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  // RETURN THE CONTENTS OF THE CALL
 $response = curl_exec($ch);
 curl_close($ch);
return $response;
}

function curlGet($url,$proxy = null) {
$ch = curl_init($url);
if ($proxy['enable'] === '1') {
$proxy['user'] === '' || curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['user'].':'.$proxy['pass']);
curl_setopt($ch, CURLOPT_PROXY, $proxy['host']);
// runelog('cURL proxy HOST: ',$proxy['host']);
// runelog('cURL proxy USER: ',$proxy['user']);
// runelog('cURL proxy PASS: ',$proxy['pass']);
}
curl_setopt($ch, CURLOPT_TIMEOUT, 2);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
curl_close($ch);
return $response;
}

function netmask($bitcount) {
$netmask = str_split(str_pad(str_pad('', $bitcount, '1'), 32, '0'), 8);
foreach ($netmask as &$element) $element = bindec($element);
return join('.', $netmask);
}
