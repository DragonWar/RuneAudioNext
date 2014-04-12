/*
 * Copyright (C) 2013 RuneAudio Team
 * http://www.runeaudio.com
 *
 * RuneUI
 * copyright (C) 2013 – Andrea Coiutti (aka ACX) & Simone De Gregori (aka Orion)
 *
 * RuneOS
 * copyright (C) 2013 – Carmelo San Giovanni (aka Um3ggh1U)
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
 *  file: scripts-configuration.js
 *  version: 1.1
 *
 */

 
 
// Global GUI Array
// ----------------------------------------------------------------------------------------------------
var GUI = {
	json: 0,
	cmd: 'status',
	playlist: null,
	currentsong: null,
	currentalbum: null,
	currentknob: null,
	state: '',
	currentpath: '',
	volume: null,
	currentDBpos: [0,0,0,0,0,0,0,0,0,0,0],
	browsemode: 'file',
	plugin: '',
	DBentry: ['', '', ''],
	visibility: 'visible',
	DBupdate: 0,
	stepVolumeInt: 0,
	stepVolumeDelta: 0,
	stream: ''
};

jQuery(document).ready(function($){ 'use strict';
	
	// INITIALIZATION
	// ----------------------------------------------------------------------------------------------------
	 
	// first connection with MPD daemon
	// open UI rendering channel;
	displayChannel();
	
	// first GUI update
	updateGUI();
	
	// hide "connecting" layer
	if (GUI.state != 'disconnected') {
		$('#loader').hide();
	}
	
	// PNotify init options
	$.pnotify.defaults.styling = 'bootstrap3';
	$.pnotify.defaults.history = false;
	$.pnotify.defaults.styling = 'fontawesome';
	// open notify channel
	notifyChannel();
	
	
	// BUTTONS
	// ----------------------------------------------------------------------------------------------------
	
	// playback buttons
	$('.btn-cmd').click(function(){
		var el = $(this);
		commandButton(el);
	});

	
	// COMMON
	// ----------------------------------------------------------------------------------------------------
	
	// Bootstrap-select
	$('.selectpicker').selectpicker();
	

	// DATABASE
	// ----------------------------------------------------------------------------------------------------
	
	if( $('#section-sources').length ){
	
		// enable/disable CIFS auth section
		if ($('#mount-type').val() == 'nfs') {
			$('#mount-cifs').children('.disabler').removeClass('hide');
		}						
		$('#mount-type').change(function(){		  
			if ($(this).val() == 'cifs') {
				$('#mount-cifs').children('.disabler').addClass('hide');
			}
			else {
				$('#mount-cifs').children('.disabler').removeClass('hide');
			}															
		});
		
		// enable/disable CIFS user and password fields
		$('#nas-guest').change(function(){
			if ($(this).prop('checked')) {
				//console.log('checked');
				$('#mount-auth').children('.disabler').removeClass('hide');
			} else {
				//console.log('unchecked');
				$('#mount-auth').children('.disabler').addClass('hide');
			}													  
		});
		
		// show advanced options
		$('#nas-advanced').change(function(){
			if ($(this).prop('checked')) {
				$('#mount-advanced-config').removeClass('hide');
			} else {
				$('#mount-advanced-config').addClass('hide');
			}													  
		});
		
		$('#show-mount-advanced-config').click(function(e){
			e.preventDefault();
			if ($(this).hasClass('active')) {
				$('#mount-advanced-config').toggleClass('hide');
				$(this).removeClass('active');
				$(this).find('i').removeClass('fa fa-minus-circle').addClass('fa fa-plus-circle');
				$(this).find('span').html('show advanced options');
			} else {
				$('#mount-advanced-config').toggleClass('hide');
				$(this).addClass('active');
				$(this).find('i').removeClass('fa fa-plus-circle').addClass('fa fa-minus-circle');
				$(this).find('span').html('hide advanced options');
			}
		});
	}
	
	
	// NETWORK
	// ----------------------------------------------------------------------------------------------------
	
	if($('#section-network').length){
		var netManualConf = $('#network-manual-config');
		// show/hide static network configuration based on select value
		if ($('#dhcp').val() == '0') {
			netManualConf.removeClass('hide');
		}						
		$('#dhcp').change(function(){		  
			if ($(this).val() == '0') {
				netManualConf.removeClass('hide');
			}
			else {
				netManualConf.addClass('hide');
			}															
		});
	}
	
	
	// SETTINGS
	// ----------------------------------------------------------------------------------------------------
	
	if( $('#section-settings').length ){
	
		// show/hide Last.fm user/pass form  
		$('#scrobbling-lastfm').change(function(){
			if ($(this).prop('checked')) {
				$('#lastfmAuth').removeClass('hide');
			} else {
				$('#lastfmAuth').addClass('hide');
			}													  
		});
	}
	
	
	// MPD
	// ----------------------------------------------------------------------------------------------------
	
	// confirm manual data
	if( $('.manual-edit-confirm').length ){
		$(this).find('.btn-primary').click(function(){
			$('#mpdconf_editor').show().removeClass('hide');
			$(this).hide();
		});
	}
	
});