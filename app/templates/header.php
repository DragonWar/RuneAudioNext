<head>
	<meta charset="utf-8">
	<title>RuneAudio - RuneUI</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0 user-scalable=no">
	<?php if ($this->section == 'settings'): ?>
	<link href="<?=$this->asset('/css/bootstrap-fileupload.min.css')?>" rel="stylesheet">
	<?php endif ?>
	<link rel="stylesheet" href="<?=$this->asset('/css/runeui.css')?>">
	<!-- dev only -->
	<!--<link rel="stylesheet/less" href="assets/less/runeui.less">-->
	<?php if ($this->section == 'index'): ?>
	<!--<link href="assets/css/test.css" rel="stylesheet">-->
	<?php endif ?>
	<!-- /dev only -->
    <link rel="icon" type="image/x-icon" href="<?=$this->asset('/img/favicon.ico')?>">
    <!-- HTML5 shim, for IE6-8 support of HTML5 elements. All other JS at the end of file. -->
    <!--[if lt IE 9]>
      <script src="assets/js/html5shiv.js"></script>
    <![endif]-->
</head>
<?php if (empty($this->uri(1)) OR ($this->uri(1) == 'playback')): ?>
<body id="section-index">
<?php else: ?>
<body id="section-<?=$this->section?>">
<?php endif ?>
<!--
/*
 * Copyright (C) 2013 RuneAudio Team
 * http://www.runeaudio.com
 *
 * RuneUI
 * copyright (C) 2013 – Andrea Coiutti (aka ACX) & Simone De Gregori (aka Orion)
 *
 * RuneOS
 * copyright (C) 2013 – Carmelo San Giovanni (aka Um3ggh1U) & Simone De Gregori (aka Orion)
 *
 * RuneAudio website and logo
 * copyright (C) 2013 – ACX webdesign (Andrea Coiutti)
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
 * RuneUI version: 1.3
 * RuneOS version: 0.3-beta
 */
-->
<div id="menu-top" class="ui-header ui-bar-f ui-header-fixed slidedown" data-position="fixed" data-role="header" role="banner">
	<div class="dropdown">
		<a class="dropdown-toggle" id="menu-settings" role="button" data-toggle="dropdown" data-target="#" href="<?=$this->uri(1)?>">MENU <i class="fa fa-th-list dx"></i></a>
		<ul class="dropdown-menu" role="menu" aria-labelledby="menu-settings">
			<li class="<?=$this->uri(1,'','active')?>"><a href="/"><i class="fa fa-play sx"></i> Playback</a></li>
			<li class="<?=$this->uri(1,'sources','active')?>"><a href="/sources/"><i class="fa fa-folder-open sx"></i> Sources</a></li>
			<li class="<?=$this->uri(1,'mpd-config','active')?>"><a href="/mpd-config/"><i class="fa fa-cogs sx"></i> MPD</a></li>
			<li class="<?=$this->uri(1,'net-config','active')?>"><a href="/net-config/"><i class="fa fa-sitemap sx"></i> Network</a></li>
			<li class="<?=$this->uri(1,'settings','active')?>"><a href="/settings/"><i class="fa fa-wrench sx"></i> Settings</a></li>
			<li class="<?=$this->uri(1,'help','active')?>"><a href="/help/"><i class="fa fa-question-circle sx"></i> Help</a></li>
			<li class="<?=$this->uri(1,'credits','active')?>"><a href="/credits/"><i class="fa fa-trophy sx"></i> Credits</a></li>
			<li><a href="#poweroff-modal" data-toggle="modal"><i class="fa fa-power-off sx"></i> Turn off</a></li>
		</ul>
	</div>
	<div class="playback-controls">	
		<button id="previous" class="btn btn-default btn-cmd" title="Previous" data-cmd="previous"><i class="fa fa-step-backward"></i></button>
		<button id="stop" class="btn btn-default btn-cmd" title="Stop" data-cmd="stop"><i class="fa fa-stop"></i></button>
		<button id="play" class="btn btn-default btn-cmd" title="Play/Pause" data-cmd="play"><i class="fa fa-play"></i></button>
		<button id="next" class="btn btn-default btn-cmd" title="Next" data-cmd="next"><i class="fa fa-step-forward"></i></button>
	</div>
	<a class="home" href="/"><img src="<?=$this->asset('/img/logo.png')?>" class="logo" alt="RuneAudio"></a>
</div>
<div id="menu-bottom" class="ui-footer ui-bar-f ui-footer-fixed slidedown" data-position="fixed" data-role="footer"  role="banner">
	<ul>
		<li id="open-panel-sx"><a href="<?php if (empty($this->uri(1)) OR ($this->uri(1) == 'playback')): ?>/<?php else: ?>/playback/<?php endif ?>#panel-sx" class="open-panel-sx" data-toggle="tab"><i class="fa fa-music sx"></i> Library</a></li>
		<li id="open-playback" <?=$this->uri(1,'','class="active"')?>><a href="<?php if (empty($this->uri(1)) OR ($this->uri(1) == 'playback')): ?>/<?php else: ?>/playback/<?php endif ?>#playback" class="close-panels" data-toggle="tab"><i class="fa fa-play sx"></i> Playback</a></li>
		<li id="open-panel-dx"><a href="<?php if (empty($this->uri(1)) OR ($this->uri(1) == 'playback')): ?>/<?php else: ?>/playback/<?php endif ?>#panel-dx" class="open-panel-dx" data-toggle="tab"><i class="fa fa-list sx"></i> Queue</a></li>
		
		
	</ul>
</div>