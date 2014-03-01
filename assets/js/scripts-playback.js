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
 *  file: scripts-playback.js
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
    halt: 0,
    volume: null,
    currentDBpos: new Array(0,0,0,0,0,0,0,0,0,0,0),
    browsemode: 'file',
    DBentry: new Array('', '', ''),
    visibility: 'visible',
    DBupdate: 0,
    stepVolumeInt: 0,
    stepVolumeDelta: 0
};

jQuery(document).ready(function($){ 'use strict';

    // INITIALIZATION
    // ----------------------------------------------------------------------------------------------------
   
    // first connection with MPD daemon
    // backendRequest( GUI.state );
	backendRequest2();
    // force UI rendering (backend-call)
	sendCmd( 'renderui' );

    // first GUI update
    updateGUI( GUI.json );
    getDB('filepath', GUI.currentpath, GUI.browsemode);
    $.pnotify.defaults.history = false;

    // hide "connecting" layer
    if (GUI.state != 'disconnected') {
      $('#loader').hide();
    }

	
    // BUTTONS
    // ----------------------------------------------------------------------------------------------------
    
	// playback
    $('.btn-cmd').click(function( e ){
        var el = $(this)
        var dataCmd = el.data('cmd');
        var cmd;
        // stop
        if (dataCmd == 'stop') {
            el.addClass('btn-primary');
            $('#play').removeClass('btn-primary');
            refreshTimer(0, 0, 'stop')
            window.clearInterval(GUI.currentKnob);
            $('.playlist').find('li').removeClass('active');
            $('#total').html('');
        }
        // play/pause
        else if (dataCmd == 'play') {
            //if (json['currentsong'] != null) {
                if (GUI.state == 'play') {
                    cmd = 'pause';
                    $('#countdown-display').countdown('pause');
                } else if (GUI.state == 'pause') {
                    cmd = 'play';
                    $('#countdown-display').countdown('resume');
                } else if (GUI.state == 'stop') {
                    cmd = 'play';
                    $('#countdown-display').countdown({since: 0, compact: true, format: 'MS'});
                }
                //$(this).find('i').toggleClass('fa fa-play').toggleClass('fa fa-pause');
                window.clearInterval(GUI.currentKnob);
                sendCmd(cmd);
                // console.log('sendCmd(' + cmd + ');');
                return;
            // } else {
                // $(this).addClass('btn-primary');
                // $('#stop').removeClass('btn-primary');
                // $('#time').val(0).trigger('change');
                // $('#countdown-display').countdown({since: 0, compact: true, format: 'MS'});
            // }
        }
        // previous/next
        else if (dataCmd == 'previous' || dataCmd == 'next') {
            GUI.halt = 1;
            // console.log('GUI.halt (next/prev)= ', GUI.halt);
            $('#countdown-display').countdown('pause');
            window.clearInterval(GUI.currentKnob);
        }
        // step volume control
        else if ( el.hasClass('btn-volume') ) {
          	var vol;
			var knobvol = parseInt($('#volume').val());
            if (GUI.volume === null ) {
                GUI.volume = knobvol;
            }
            if (dataCmd == 'volumedn' && parseInt(GUI.volume) > 0) {
                vol = parseInt(GUI.volume) - 1;
                GUI.volume = vol;
                $('#volumemute').removeClass('btn-primary');
            } else if (dataCmd == 'volumeup' && parseInt(GUI.volume) < 100) {
                vol = parseInt(GUI.volume) + 1;
                GUI.volume = vol;
                $('#volumemute').removeClass('btn-primary');
            } else if (dataCmd == 'volumemute') {
                if (knobvol !== 0 ) {
                    GUI.volume = knobvol;
                    el.addClass('btn-primary');
                    vol = 0;
                } else {
                    el.removeClass('btn-primary');
                    setvol(GUI.volume);
                }
            }
            // console.log('volume = ', GUI.volume);
            sendCmd('setvol ' + vol);
            return;
        }

        // toggle buttons
        if ( el.hasClass('btn-toggle') ) {
            cmd = dataCmd + (el.hasClass('btn-primary')? ' 0':' 1');
            el.toggleClass('btn-primary');
        // send command
        } else {
            cmd = dataCmd;
        }
        sendCmd(cmd);
        // console.log('sendCmd(' + cmd + ');');
    });
	
	$('#volume-step-dn').on({
		mousedown : function () {
			volumeStepCalc('dn');
		},
		mouseup : function () {
			volumeStepSet();
		}
	});
	
	$('#volume-step-up').on({
		mousedown : function () {
			volumeStepCalc('up');
		},
		mouseup : function () {
			volumeStepSet();
		}
	});
	
	function volumeStepCalc(direction) {
		var i = 0;
		var way = direction;
		GUI.volume = parseInt($('#volume').val());
		var volumeStep = function volumeStepCycle(way){
			i++;
			if (direction == 'up') {
				GUI.stepVolumeDelta = parseInt(GUI.volume) + i;
			} else if (direction == 'dn') {
				GUI.stepVolumeDelta = parseInt(GUI.volume) - i;
			}
			// console.log('GUI.stepVolumeDelta = ', GUI.stepVolumeDelta);
			$('#volume').val(GUI.stepVolumeDelta).trigger('change');
		}
		volumeStep();
		// console.log('GUI.volume = ', GUI.volume);
		
		GUI.stepVolumeInt = window.setInterval(function() {
			volumeStep();
		}, 200);
	}
	function volumeStepSet() {
		window.clearInterval(GUI.stepVolumeInt);
		setvol(GUI.stepVolumeDelta);
		// console.log('set volume to = ', GUI.stepVolumeDelta);
	}
	
	
    // KNOBS
    // ----------------------------------------------------------------------------------------------------
    
	// playback progressing
    $('.playbackknob').knob({
        inline: false,
		    change : function (value) {
            if (GUI.state != 'stop') {
				// console.log('GUI.halt (Knobs)= ', GUI.halt);
				window.clearInterval(GUI.currentKnob)
				//$('#time').val(value);
				// console.log('click percent = ', value);
				// add command
			} else $('#time').val(0);
        },
        release : function (value) {
			if (GUI.state != 'stop') {
				// console.log('release percent = ', value);
				GUI.halt = 1;
				// console.log('GUI.halt (Knobs2)= ', GUI.halt);
				window.clearInterval(GUI.currentKnob);
				var seekto = Math.floor((value * parseInt(GUI.json['time'])) / 1000);
				sendCmd('seek ' + GUI.json['song'] + ' ' + seekto);
				// console.log('seekto = ', seekto);
				$('#time').val(value);
				$('#countdown-display').countdown('destroy');
				$('#countdown-display').countdown({since: -seekto, compact: true, format: 'MS'});
			}
        },
        cancel : function () {
            // console.log('cancel : ', this);
        },
        draw : function () {}
    });

    // volume knob
    $('.volumeknob').knob({
        change : function (value) {
            //setvol(value);  // disabled until perfomance issues are solved (mouse wheel is not working now)
        },
        release : function (value) {
            setvol(value);
        },
        cancel : function () {
            // console.log('cancel : ', this);
        },
		draw : function () {
            // "tron" case
            if(this.$.data('skin') == 'tron') {

                var a = this.angle(this.cv)  // Angle
                    , sa = this.startAngle          // Previous start angle
                    , sat = this.startAngle         // Start angle
                    , ea                            // Previous end angle
                    , eat = sat + a                 // End angle
                    , r = true;

                this.g.lineWidth = this.lineWidth;

                this.o.cursor
                    && (sat = eat - 0.05)
                    && (eat = eat + 0.05);

                if (this.o.displayPrevious) {
                    ea = this.startAngle + this.angle(this.value);
                    this.o.cursor
                        && (sa = ea - 0.1)
                        && (ea = ea + 0.1);
                    this.g.beginPath();
                    this.g.strokeStyle = this.previousColor;
                    this.g.arc(this.xy, this.xy, this.radius - this.lineWidth, sa, ea, false);
                    this.g.stroke();
                }

                this.g.beginPath();
                this.g.strokeStyle = r ? this.o.fgColor : this.fgColor ;
                this.g.arc(this.xy, this.xy, this.radius - this.lineWidth, sat, eat, false);
                this.g.stroke();

                this.g.lineWidth = 2;
                this.g.beginPath();
                this.g.strokeStyle = this.o.fgColor;
                this.g.arc(this.xy, this.xy, this.radius - this.lineWidth + 10 + this.lineWidth * 2 / 3, 0, 20 * Math.PI, false);
                this.g.stroke();

                return false;
            }
        }
    });


    // PLAYING QUEUE
    // ----------------------------------------------------------------------------------------------------

    var playlist = $('.playlist');
    
	// click on playlist entry
    playlist.on('click', '.pl-entry', function() {
        var pos = $('.playlist .pl-entry').index(this);
        var cmd = 'play ' + pos;
        sendCmd(cmd);
        GUI.halt = 1;
        // console.log('GUI.halt (playlist)= ', GUI.halt);
        playlist.find('li').removeClass('active');
        $(this).parent().addClass('active');
    });

    // click on playlist actions
    playlist.on('click', '.pl-action', function(event) {
        event.preventDefault();
        var pos = playlist.find('.pl-action').index(this);
        var cmd = 'trackremove&songid=' + pos;
        var path = $(this).parent().data('path');
        // recover datapath
		notify('remove', '');
        sendPLCmd(cmd);
    });

    // on ready playlist tab
    $('a', '#open-panel-dx').click(function(){
		if ($('#open-panel-dx').hasClass('active')) {
			 var current = parseInt(GUI.json['song']);
			 console.log('CURRENT = ', current);
			 customScroll('pl', current, 500);
		}
	})
	.on('shown.bs.tab', function (e) {
		var current = parseInt(GUI.json['song']);
        customScroll('pl', current, 0);
    });

	// open Library tab
    $('#open-library').click(function(){
        $('#open-panel-dx').removeClass('active');
        $('#open-panel-sx').addClass('active');
    });

	// Queue on the fly filtering
    $('#pl-filter').keyup(function(){
        $.scrollTo(0 , 500);
        var filter = $(this).val(), count = 0;
        $('li', '#playlist-entries').each(function(){
            var el = $(this);
			if (el.text().search(new RegExp(filter, 'i')) < 0) {
                el.hide();
            } else {
                el.show();
                count++;
            }
        });
        var numberItems = count;
        var s = (count == 1) ? '' : 's';
		if (filter !== '') {
			$('#pl-filter-results').removeClass('hide').html('<i class="fa fa-times sx"></i> <span class="visible-xs">back</span><span class="hidden-xs">' + (+count) + ' result' + s + ' for "<span class="keyword">' + filter + '</span>"</span>');
        } else {
			$('#pl-filter-results').addClass('hide').html('');
        }
    });
	
	// close filter results
	$('#pl-filter-results').click(function(){
		getPlaylistCmd(GUI.json);
    });
	
	
    // LIBRARY
    // ----------------------------------------------------------------------------------------------------
	
    // on ready Library tab
    $('a', '#open-panel-sx').click(function(){
		if ($('#open-panel-sx').hasClass('active')) {
			 customScroll('db', GUI.currentDBpos[GUI.currentDBpos[10]], 500);
		}
	})	
	.on('shown.bs.tab', function (e) {
		customScroll('db', GUI.currentDBpos[GUI.currentDBpos[10]], 0);
    });
    
    var db = $('.database');
	
    // click on Library home block
    $('.home-block').click(function(){
		var path = $(this).data('path');
		// console.log('CLICKED! ', path);
		++GUI.currentDBpos[10];
		getDB('filepath', path, GUI.browsemode, 0);
    });
	
    // click on Library list entry
    db.on('click', '.db-browse', function(e) {
        var el = $(this);
        db.find('li').removeClass('active');
        el.parent().addClass('active');
        if ( !el.hasClass('sx') ) {
            var path = el.parent().data('path');
			if ( el.hasClass('db-folder') ) {
                //GUI.currentDBpos[GUI.currentDBpos[10]] = $('.database .db-entry').index(this);
                var entryID = el.parent().attr('id');
                entryID = entryID.replace('db-','');
                GUI.currentDBpos[GUI.currentDBpos[10]] = entryID;
                ++GUI.currentDBpos[10];
                // console.log('getDB path = ', path);
                getDB('filepath', path, GUI.browsemode, 0);
            }
        }
    });

    // browse level up
    $('#db-level-up').click(function(){
		--GUI.currentDBpos[10];
		var path = GUI.currentpath;
		if (GUI.currentDBpos[10] === 0) {
			path = '';
		} else {
			var cutpos = path.lastIndexOf('/');
			path = cutpos !=-1 ? path.slice(0,cutpos):'';
		}
		// console.log('PATH =', path);
		getDB('filepath', path, GUI.browsemode, 1);
    });
    
    db.on('dblclick', '.db-song', function() {
        db.find('li').removeClass('active');
        $(this).parent().addClass('active');
        var path = $(this).parent().data('path');
        // console.log('doubleclicked path = ', path);
        getDB('addplay', path);
        notify('add', path);
    });

    // click on ADD button
    db.on('click', '.db-action', function() {
        var path = $(this).parent().attr('data-path');
        GUI.DBentry[0] = path;
        // console.log('getDB path = ', GUI.DBentry);
    });

	// close search results
	$('#db-search-results').click(function(){
		$(this).addClass('hide')
		$('#db-level-up').removeClass('hide');
		getDB('filepath', GUI.currentpath);
    });

	// context dropdown menu
    $('a', '.context-menu').click(function(){
        var dataCmd = $(this).data('cmd');
        var path = GUI.DBentry[0];
        GUI.DBentry[0] = '';
        if (dataCmd == 'add') {
            getDB('add', path);
            notify('add', path);
        }
        if (dataCmd == 'addplay') {
            getDB('addplay', path);
            notify('add', path);
        }
        if (dataCmd == 'addreplaceplay') {
            getDB('addreplaceplay', path);
            notify('addreplaceplay', path);
        }
        if (dataCmd == 'update') {
            getDB('update', path);
            notify('update', path);
        }
    });

    // browse mode menu
    $('a', '.browse-mode').click(function(){
        $('.browse-mode').removeClass('active');
        $(this).parent().addClass('active').closest('.dropdown').removeClass('open');
        var browsemode = $(this).find('span').html();
        GUI.browsemode = browsemode.slice(0,-1);
        $('#browse-mode-current').html(GUI.browsemode);
        getDB('filepath', '', GUI.browsemode);
        // console.log('Browse mode set to: ', GUI.browsemode);
    });
	
	
	// GENERAL
    // ----------------------------------------------------------------------------------------------------
	
	// scroll buttons
    $('#db-firstPage').click(function(){
        $.scrollTo(0 , 500);
    });
    $('#db-prevPage').click(function(){
        var scrolloffset = '-=' + $(window).height() + 'px';
        $.scrollTo(scrolloffset , 500);
    });
    $('#db-nextPage').click(function(){
        var scrolloffset = '+=' + $(window).height() + 'px';
        $.scrollTo(scrolloffset , 500);
    });
    $('#db-lastPage').click(function(){
        $.scrollTo('100%', 500);
    });

    $('#pl-firstPage').click(function(){
        $.scrollTo(0 , 500);
    });
    $('#pl-prevPage').click(function(){
        var scrollTop = $(window).scrollTop();
        var scrolloffset = scrollTop - $(window).height();
        $.scrollTo(scrolloffset , 500);
    });
    $('#pl-nextPage').click(function(){
        var scrollTop = $(window).scrollTop();
        var scrolloffset = scrollTop + $(window).height();
        $.scrollTo(scrolloffset , 500);
    });
    $('#pl-lastPage').click(function(){
        $.scrollTo('100%', 500);
    });
	
	// open tab from external link
    var url = document.location.toString();
	console.log('url = ', url);
    if ( url.match('#') ) {
		$('#menu-bottom a[href="/#' + url.split('#')[1] + '"]').tab('show');
    }
    // do not scroll with HTML5 history API
    $('#menu-bottom a').on('shown', function (e) {
        if(history.pushState) {
            history.pushState(null, null, e.target.hash);
        } else {
            window.location.hash = e.target.hash; //Polyfill for old browsers
        }
    });
	
	// tooltips
    if( $('.ttip').length ){
        $('.ttip').tooltip();
    }

});

