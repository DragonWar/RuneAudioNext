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
 *  file: coverart.php
 *  version: 1.3
 *
 */
 
require_once('Zend/Media/Flac.php');
// direct output bypass template system
$tplfile = 0;
// fetch MPD status
$status = _parseStatusResponse(MpdStatus($mpd));
$curTrack = getTrackInfo($mpd,$status['song']);
if (isset($curTrack[0]['Title'])) {
$status['currentartist'] = $curTrack[0]['Artist'];
$status['currentsong'] = $curTrack[0]['Title'];
$status['currentalbum'] = $curTrack[0]['Album'];
$status['fileext'] = parseFileStr($curTrack[0]['file'],'.');
}
$currentpath = "/mnt/MPD/".findPLposPath($status['song'],$mpd);

// debug
runelog("MPD current path",$currentpath);

$flac = new Zend_Media_Flac($currentpath);

function getLastFMCover($status) {
$cover_url = ui_lastFM_coverart($status['currentartist'],$status['currentalbum'],$_SESSION['lastfm_apikey']);
$ch = curl_init($cover_url);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$image = curl_exec($ch);
curl_close($ch);
if (!empty($image)) {
 return $image;
} else {
 return false;
}
}

if ($flac->hasMetadataBlock(Zend_Media_Flac::PICTURE)) {
		
		// debug
		runelog("coverart match: embedded",'');
		//Extract picture from file
		header('Content-Type: ' . $flac->getPicture()->getMimeType());
		echo $flac->getPicture()->getData();


    } else if ($image = getLastFMCover($status)) {
		// debug
		runelog("coverart match: lastfm",'');
		header('Content-Type: ' .mime_content_type($image));
		echo $image;

	} else {
    
	// debug
	runelog("coverart match: cover-default",'');
	$image = '/var/www/assets/images/cover-default.png';
    header('Content-Type: ' .mime_content_type($image));
    readfile($image);
	
}
?>