<?php
//  +------------------------------------------------------------------------+
//  | O!MPD, Copyright � 2015 Artur Sierzant		                         |
//  | http://www.ompd.pl                                             		 |
//  |                                                                        |
//  |                                                                        |
//  | netjukebox, Copyright � 2001-2012 Willem Bartels                       |
//  |                                                                        |
//  | http://www.netjukebox.nl                                               |
//  | http://forum.netjukebox.nl                                             |
//  |                                                                        |
//  | This program is free software: you can redistribute it and/or modify   |
//  | it under the terms of the GNU General Public License as published by   |
//  | the Free Software Foundation, either version 3 of the License, or      |
//  | (at your option) any later version.                                    |
//  |                                                                        |
//  | This program is distributed in the hope that it will be useful,        |
//  | but WITHOUT ANY WARRANTY; without even the implied warranty of         |
//  | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the          |
//  | GNU General Public License for more details.                           |
//  |                                                                        |
//  | You should have received a copy of the GNU General Public License      |
//  | along with this program.  If not, see <http://www.gnu.org/licenses/>.  |
//  +------------------------------------------------------------------------+




//  +------------------------------------------------------------------------+
//  | playlist.php                                                           |
//  +------------------------------------------------------------------------+
require_once('include/initialize.inc.php');
$cfg['menu'] = 'playlist';

authenticate('access_playlist');
require_once('include/header.inc.php');
require_once('include/play.inc.php');


if ($cfg['player_type'] == NJB_HTTPQ) {
	$hash		= httpq('gethash');
	$listpos	= httpq('getlistpos');
	$file		= httpq('getplaylistfilelist', 'delim=*');
	$file		= str_replace('\\', '/', $file);
	$file		= explode('*', $file);
	$listlength	= (empty($file[0])) ? 0 : count($file);
	$volume		= true;
	$max_volume	= 255;
		
	// Get relative directory based on $cfg['media_share']
	foreach ($file as $i => $value)	{
		if (strtolower(substr($file[$i], 0, strlen($cfg['media_share']))) == strtolower($cfg['media_share']))
			$file[$i] = substr($file[$i], strlen($cfg['media_share']));
	}
}
elseif ($cfg['player_type'] == NJB_MPD)	{
	$status 		= mpd('status');
	$listpos		= isset($status['song']) ? $status['song'] : 0;
	$file			= mpd('playlist');
	$hash			= md5(implode('<seperation>', $file));
	$listlength		= $status['playlistlength'];
	$volume			= (isset($status['volume']) == false || $status['volume'] == -1) ? false : true;
	$max_volume		= 100;	
}
elseif ($cfg['player_type'] == NJB_VLC)
	message(__FILE__, __LINE__, 'warning', '[b]videoLAN playlist not supported yet[/b]');
else
	message(__FILE__, __LINE__, 'error', '[b]Player not supported[/b]');


$featuring = false;
for ($i=0; $i < $listlength && !$featuring; $i++) {
	if($cfg['media_dir_alternative_path'] !== '' && $file[$i][0] === '/') {
		$file[$i] = substr(
			$file[$i],
			strlen($cfg['media_dir_alternative_path'])
		);
	}
	
	$query = mysql_query('SELECT featuring FROM track WHERE featuring != "" AND relative_file = "' . mysql_real_escape_string($file[$i]) . '"');
	if (mysql_fetch_row($query)) $featuring = true;
}
if (count($file) == 0) {
	message(__FILE__, __LINE__, 'warning', '[b]Playlist is empty[/b][br][br]
	[url=index.php]Add[/url] some music!');
	require_once('include/footer.inc.php');
	exit;
}
?>

<!-- info + control -->
<div id="info_area">
<div id="image_container">
	<div id="cover-spinner">
		<img src="image/loader.gif" alt="">
	</div>
	<div id="image">
		<a href="index.php"><img id="image_in" src="image/transparent.gif" alt=""></a>
	</div>
</div>

<div class="pl-track-info-right">
<div class="pl-track-info" id="pl-track-info">
	<div class="pl-track-title"><span id="track_number" class="pl-track-number">&nbsp;</span><span id="title">&nbsp;</span></div>
	<div class="pl-fld-name">track title</div>
	<div class="pl-track-artist"><span id="artist">&nbsp;</span></div>
	<div class="pl-fld-name">track artist</div>
	<div class="pl-track-artist"><span id="album">&nbsp;</span></div>
	<div class="pl-fld-name">album</div>
	<div class="pl-track-artist"><span id="genre">&nbsp;</span></div>
	<div class="pl-fld-name">genre</div>
	<div class="pl-track-artist"><span id="year">&nbsp;</span></div>
	<div class="pl-fld-name">year</div>
	
	<!-- <div class="pl-fld-name">file info</div> -->
	<div class="pl-track-artist"><span id="lyrics">&nbsp;</span></div>
	<div class="pl-fld-name">search</div>
	
	<div class="pl-track-favorites"><span id="favorites">&nbsp;</span></div>
	<div class="pl-fld-name">add to favorites</div>
	
	
</div>

<div class="pl-track-info" id="pl-track-info-narrow" style="text-align: center;">
	<div>
		<span class="pl-track-number" id="track_number1">&nbsp;</span><span id="title1" class="pl-track-title">&nbsp;</span>
	</div>
	<div class="pl-file-info">
		by <span class="pl-track-artist" id="artist1">&nbsp;</span>
		from <span class="pl-track-artist" id="album1">&nbsp;</span>
	</div>
	<div class="pl-file-info">
		<span class="pl-track-artist" id="genre1">&nbsp;</span> &bull; 
		<span class="pl-track-artist" id="year1">&nbsp;</span> &bull; 
		<span class="pl-track-artist"><span id="lyrics1">&nbsp;</span></span> &bull;
		<span class="pl-track-favorites"><span id="favorites1">&nbsp;</span></span> 
	</div>
	
	
</div>


<!-- begin controll bar -->

<div class="media_control">

<div class="playlist_indicator"><div>
		<span class="playlist_status_off" name="time" id="time" style="text-align: right; padding-right:1px;"></span>
		<div id="track-progress" class="out pointer" style="display:inline-block;" onClick="ajaxRequest('play.php?action=seekImageMap&amp;dx=' + this.clientWidth + '&amp;x=' + getRelativeX(event, this) + '&amp;menu=playlist', evaluatePlaytime);">
			<div id="bar-indicator"></div>
			<div id="timebar" style="width: 0px; overflow: hidden;" class="in"></div>
			
		</div>
		<span class="playlist_status_off" name="tracktime" id="tracktime" style="text-align: left; padding-left: 1px; display: inline;"></span>
	</div>
</div>	
<div id="parameters">&nbsp;</div>	
<div class="control-row">
	<div class="playlist_button"><div class="playlist_status_off" name="shuffle" id="shuffle" onclick="javascript:ajaxRequest('play.php?action=toggleShuffle&amp;menu=playlist', evaluateShuffle);">
		<span class="typcn typcn-arrow-shuffle cb-typcn"></span>
	</div></div>


	<?php
	if ($cfg['player_type'] == NJB_MPD && version_compare($cfg['mpd_version'], '0.16.0', '>=')) { 
	?>	
	<!--
	<div class="playlist_button"><div class="playlist_status_off" name="gain" id="gain" onclick="javascript:ajaxRequest('play.php?action=loopGain&amp;menu=playlist', evaluateGain);">
		<span id="gain_text" class="gain">gain<br>off</span>
	</div></div>
	-->
	<?php
			} /* End replay gain */ 
	
	?>	

	<div class="playlist_button"><div class="playlist_status_off" name="previous" id="previous" onclick="javascript:ajaxRequest('play.php?action=prev&amp;menu=playlist');">
		<i class="fa fa-fast-backward sign-ctrl"></i>
	</div></div>
	
	<div class="playlist_button"><div class="playlist_status_off" name="play" id="play" onclick="javascript:ajaxRequest('play.php?action=play&amp;menu=playlist', evaluateIsplaying);">
		<i class="fa fa-play sign-ctrl"></i>
	</div></div>
	
	<!--
	<div class="playlist_button"><div class="playlist_status_off" name="pause" id="pause" onclick="javascript:ajaxRequest('play.php?action=pause&amp;menu=playlist', evaluateIsplaying);">
		<i class="fa fa-pause sign-ctrl"></i>
	</div></div>
	-->
	
	
	<div class="playlist_button" style="display: none;"><div class="playlist_status_off" name="stop" id="stop" onclick="javascript:ajaxRequest('play.php?action=stop&amp;menu=playlist', evaluateIsplaying);">
		<i class="fa fa-stop sig"></i>
	</div></div>
	
	<div class="playlist_button" style=""><div class="playlist_status_off" name="next" id="next" onclick="javascript:ajaxRequest('play.php?action=next&amp;menu=playlist', evaluateIsplaying);">
		<i class="fa fa-fast-forward sign-ctrl"></i>
	</div></div>
	
	<div class="playlist_button"><div class="playlist_status_off" name="repeat" id="repeat" onclick="javascript:ajaxRequest('play.php?action=toggleRepeat&amp;menu=playlist', evaluateRepeat);">
		<span class="typcn typcn-arrow-repeat cb-typcn"></span>
	</div></div>
</div>

</div>
</div>
<!-- end controll bar -->
<div id="" style="clear:both;height:0px;"></div>
</div>
<!-- end info + controll -->

<?php
// calculate the portion which should be rendered
$min_index = 0;
$max_index = $cfg['current_playlist_max_displayed_items'];
$string_listlength =  $listlength . ' Tracks';
if($listlength > $cfg['current_playlist_max_displayed_items']) {
	if(($listlength - $cfg['current_playlist_max_displayed_items']) < $listpos ) {
		$min_index = $listlength - $cfg['current_playlist_max_displayed_items'];
		$max_index = $cfg['current_playlist_max_displayed_items'];
	} else {
		$min_index = $listpos;
		$max_index = $listpos + $cfg['current_playlist_max_displayed_items'];
	}
	$string_listlength = 'showing '.($min_index+1) . '-' . ($max_index+1) . ' of ' . $listlength . ' Tracks';
}

?>
<div id="playlist">
<!--
<span  class="playlist-title">Play list</span><span class="hidePL">&nbsp;(hide)</span>
-->
<span  class="playlist-title">Playlist <span class="pl-track-number">(<?php echo $string_listlength; ?>)</span></span>
<table cellspacing="0" cellpadding="0" class="border">
<tr class="header">
	<td class="small_cover"></td>
	<td class="play-indicator"></td>
	<td class="trackNumber">#</td>
	<td class="time">Title</td>
	<td class="time">Artist</td>
	<td class="time pl-genre">Genre</td>
	<td><?php if ($featuring) echo'Featuring'; ?></td><!-- optional featuring -->
	<td<?php if ($featuring) echo' class="textspace"'; ?>></td>
	<td class="time pl-year">Year</td>
	<td class="time">Time</td>
	<td class="iconDel"></td><!-- optional delete -->
	<td class="space right"></td>
</tr>
<?php

$playtime = array();
$track_id = array();
for ($i=0; $i < $listlength; $i++)
	{
		
	if($i < $min_index || $i > $max_index) {
		// TODO: display 'show more'-links in frontend to ajax-load a bunch of not rendered playlistitems at begin and end of current playlist
		// or a pagination for current playlist?
		// TODO: reload playlist when pressing "play-next-button" to assure the current track will be displayed
		continue;
	}
	$query = mysql_query('
			SELECT
				track.title,
				track.artist,
				track.relative_file,
				track.track_artist,
				track.featuring,
				track.miliseconds,
				track.track_id,
				track.genre AS genre_id,
				genre.genre AS genre_string,
				track.audio_dataformat,
				track.audio_bits_per_sample,
				track.audio_sample_rate,
				track.album_id,
				track.number,
				track.track_id,
				track.year as trackYear
			FROM track, genre
			WHERE track.genre=genre.genre_id
			  AND track.relative_file_hash= "' . pathhash($file[$i]) . '"
			'
	);
	$table_track = mysql_fetch_assoc($query);
	$playtime[] = (int) $table_track['miliseconds'];
	$track_id[] = (string) $table_track['track_id'];
	$genre_id[(int) $table_track['genre_id']] = (string) $table_track['genre_string'];
	$number[] = (string) $table_track['number'];
	if (!isset($table_track['artist'])) {
		$table_track['artist']	= $file[$i];
		$table_track['title']	= 'Unknown';
	}
	$query2 = mysql_query('SELECT album, year, image_id FROM album WHERE album_id="' . $table_track['album_id'] . '"');
	$image_id = mysql_fetch_assoc($query2);
	$table_track['title'] = ($table_track['title'] == '')
		? basename($table_track['relative_file'])
		: $table_track['title'];
		
	$image_id['album'] = ($image_id['album'] == '')
		? basename(dirname($table_track['relative_file']))
		: $image_id['album'];
?>
<tr class="<?php if ($i == $listpos) echo 'select'; else echo ($i & 1) ? 'even mouseover' : 'odd mouseover'; ?>" id="track<?php echo $i; ?>" style="display:table-row;">
	
	<td class="small_cover">
	<a id="track<?php echo $i; ?>_image" href="javascript:ajaxRequest('play.php?action=playIndex&amp;index=<?php echo $i ?>&amp;menu=playlist', evaluateListpos);"><img src="image.php?image_id=<?php echo $image_id['image_id'] ?>&track_id=<?php echo $table_track['track_id'] ?>" alt="" width="100%" height="100%"></a></td>
	
	<!--<td class="play-indicator">
	<a class="play-indicator" id="track<?php echo $i; ?>_play" style="<?php if ($i == $listpos) echo 'visibility: visible;'; else echo 'visibility: hidden;'; ?>" href="javascript:ajaxRequest('play.php?action=playIndex&amp;index=<?php echo $i ?>&amp;menu=playlist', evaluateListpos);">
	<canvas width="30px" height="30px">
	</a></td>
	-->
	
	<td class="play-indicator">
	<div id="track<?php echo $i; ?>_play" style="<?php if ($i == $listpos) echo 'visibility: visible;'; else echo 'visibility: hidden;'; ?>" onclick="javascript:ajaxRequest('play.php?action=playIndex&amp;index=<?php echo $i ?>&amp;menu=playlist', evaluateListpos);">
			<img src="skin/ompd_default/img/playing.gif">
			
			<!--<i id="track<?php echo $i; ?>_play_indicator" class="fa fa-play-circle-o"></i>
			-->
	</div>
	</td>
	
	<td class="trackNumber"><a class="trackNumber" href="javascript:ajaxRequest('play.php?action=playIndex&amp;index=<?php echo $i ?>&amp;menu=playlist', evaluateListpos);" id="track<?php echo $i; ?>_number"><div class="trackNumber"><?php echo html($table_track['number']); ?></div></a></td>
	
	<td class="time"><a href="javascript:ajaxRequest('play.php?action=playIndex&amp;index=<?php echo $i ?>&amp;menu=playlist', evaluateListpos);" id="track<?php echo $i; ?>_title"><div class="playlist_title"><?php echo html($table_track['title']) ?></div>
		<div class="playlist_title_album"><?php echo $image_id['album'] ?></div>
	</a></td>
	
	<td class="time">
	<?php
	$artist = '';
		$exploded = multiexplode($cfg['artist_separator'],$table_track['track_artist']);
		$l = count($exploded);
		if ($l > 1) {
			for ($j=0; $j<$l; $j++) {
				$artist = $artist . '<a href="index.php?action=view2&amp;artist=' . rawurlencode($exploded[$j]) . '">' . html($exploded[$j]) . '</a>';
				if ($j != $l - 1) $artist = $artist . '<a href="index.php?action=view2&amp;artist=' . rawurlencode($table_track['track_artist']) . '&amp;order=year"><span class="artist_all">&</span></a>';
			}
			echo $artist;
		}
		else {
			echo '<a href="index.php?action=view2&amp;artist=' . rawurlencode($table_track['track_artist']) . '&amp;order=year">' . html($table_track['track_artist']) . '</a>';
		}
	?>
	</td>

	<td class="time pl-genre">
	<a href="index.php?action=view2&order=artist&sort=asc&genre_id=<?php echo $table_track['genre_id'] ?>"><?php echo $table_track['genre_string'] ?></a>
	</td>
	
	<td><?php if (isset($table_track['featuring'])) echo html($table_track['featuring']); ?></td>
	
	<td></td>
	<?php
	$year	= ((is_null($image_id['year'])) ? (string) $table_track['trackYear'] : (string) $image_id['year']);
	?>
	<td class="time pl-year">
	<a href="index.php?action=view2&order=artist&sort=asc&year=<?php echo $year ?>"><?php echo $year ?></a>
	</td>
	
	<td class="time"><?php if (isset($table_track['miliseconds'])) echo formattedTime($table_track['miliseconds']); ?></td>
	
	<td id="track<?php echo $i; ?>_delete" class="iconDel" <?php if ($cfg['access_play']) 
	echo 'onclick="javascript:showSpinner();ajaxRequest(\'play.php?action=deleteIndex&amp;index=' . $i . '&amp;menu=playlist\',evaluateListpos);"'; ?>><i class="fa fa-times-circle sign"></i></td>
	
	
	<td></td>
</tr>
<tr class="line"><td colspan="12"></td></tr>
<?php
	}
?>
</table>
</div> <!-- playlist -->

<script type="text/javascript">
<!--

var previous_hash			= '<?php echo $hash; ?>';
var previous_listpos		= <?php echo $listpos; ?>;
var previous_isplaying		= -1; // force update
var previous_repeat			= -1;
var previous_shuffle		= -1;
var previous_gain			= -1;
var previous_miliseconds	= -1;
//var previous_volume			= -1;
var playtime				= <?php echo safe_json_encode($playtime); ?>;
var track_id				= <?php echo safe_json_encode($track_id); ?>;
var timer_id				= 0;
var timer_function			= 'ajaxRequest("play.php?action=playlistStatus&menu=playlist", evaluateStatus)';
var timer_delay				= 1000;
var list_length				= <?php echo $listlength;?>;
//console.trace();




function hidePL() {
	//document.getElementById('playlist').style.left = window.innerWidth;
	//document.getElementById('playlist').style.width = 0;
	//document.getElementById('playlist').style.visibility = "hidden";
	window.scrollTo(0,0);
}

function showPL() {
	//document.getElementById('playlist').style.left = 0;
	//document.getElementById('playlist').style.visibility = "visible";
	//document.getElementById('playlist').style.width = w - 40;
	window.scrollTo(0,window.innerHeight);
}

function deletePLitem(data) {

	var idx = parseInt(data.index);
	//var idx = parseInt(idx2del);
	console.log ("idx: %s", idx);
	
	var row2del = document.getElementById('track' + idx);
	var newId = Date.parse(new Date());
	row2del.id = 'track' + newId;
	
	//$('#track' + newId).fadeOut(700, function(){ $('#track' + newId).remove();});
	row2del.parentNode.removeChild(row2del);
	//row2del.style.display='none';
	
	list_length = list_length-1;
	var i = idx+1;
	//console.log("i= %s", i);
	//console.log("list_length= %s", list_length);
	
	for (i; i<=list_length; i++) {
		var j = i-1;
		document.getElementById('track' + i).id = 'track' + j;
		document.getElementById('track' + i + '_image').id = 'track' + j + '_image';
		document.getElementById('track' + i + '_number').id = 'track' + j + '_number';
		document.getElementById('track' + i + '_title').id = 'track' + j + '_title';
		document.getElementById('track' + i + '_delete').id = 'track' + j + '_delete';
		
		
		
		var oldClassName = document.getElementById('track' + j).className;
		var t = oldClassName.search('even');
		//console.log("className: %s, 'even' pos: %s", oldClassName, t);
		document.getElementById('track' + j).className = ((oldClassName.search('select') == 0 ) ? 'select' : ((oldClassName.search('even') < 0 ) ? 'even mouseover' : 'odd mouseover'));
		
		var newHref = 'javascript:ajaxRequest(\'play.php?action=playIndex&amp;index=' + j + '&amp;menu=playlist\', evaluateListpos);';
		
		document.getElementById('track' + j + '_image').href = newHref;
		document.getElementById('track' + j + '_number').href = newHref;
		document.getElementById('track' + j + '_title').href = newHref;
		document.getElementById('track' + j + '_delete').innerHTML='<a href="javascript:ajaxRequest(\'play.php?action=deleteIndex&amp;index=' + j + '&amp;menu=playlist\',deletePLitem);"><span class="typcn typcn-delete" style="font-size: 30px; color: #555555;"><span></a>';
		
	}
	resizeImgContainer();

}

function initialize() {
	ajaxRequest('play.php?action=playlistTrack&track_id=' + track_id[<?php echo $listpos; ?>] + '&menu=playlist', evaluateTrack);
	ajaxRequest('play.php?action=playlistStatus&menu=playlist', evaluateStatus);
}


function evaluateStatus(data) {
	// data.hash, data.miliseconds, data.listpos, data.volume
	// data.isplaying, data.repeat, data.shuffle, data.gain
	if (previous_hash != data.hash) {
		//window.location.href="<?php echo NJB_HOME_URL ?>playlist.php";
		location.reload(false);
		//window.location.href = window.location.href;
		//history.go();
	}
	data.max = playtime[data.listpos];
	evaluateListpos(data.listpos);
	evaluatePlaytime(data);
	evaluateRepeat(data.repeat);
	evaluateShuffle(data.shuffle);
	evaluateIsplaying(data.isplaying, data.listpos);
	evaluateVolume(data.volume);
	evaluateGain(data.gain);
	/* var tb = $('#timebar');
	var tbi = $('#bar-indicator');
	var p = tb.offset();
	var tbiTop = p.top - 2;
	var tbiLeft = p.left + tb.width() - 4;
	tbi.offset({top: tbiTop, left: tbiLeft});
	tbi.css("visibility","visible");
	tbi.show(); */
	
}


function evaluateListpos(listpos) {
	if (previous_listpos != listpos) {
		document.getElementById('track' + previous_listpos).className = (previous_listpos & 1) ? 'even mouseover' : 'odd mouseover';
		document.getElementById('track' + listpos).className = 'select';
		document.getElementById('track' + listpos + '_play').style.visibility = 'visible';
		document.getElementById('track' + previous_listpos + '_play').style.visibility  = 'hidden';
		document.getElementById('time').innerHTML = formattedTime(0);
		document.getElementById('timebar').style.width = 0;
		ajaxRequest('play.php?action=playlistTrack&track_id=' + track_id[listpos] + '&menu=playlist', evaluateTrack);
		previous_miliseconds = 0;
		previous_listpos = listpos;
	}
	//resizeImgContainer();
}


function evaluatePlaytime(data) {
	// data.miliseconds, data.max, ....
	if (previous_miliseconds != data.miliseconds) {
		document.getElementById('time').innerHTML = formattedTime(data.miliseconds);
		var width_ = 0;
		var progress_bar_width = document.getElementById('track-progress').clientWidth;
		
		if (data.max > 0)	width_ = Math.round(data.miliseconds / data.max * progress_bar_width);
		if (width_ > progress_bar_width)	width_ = progress_bar_width;
		
		//document.getElementById('timebar').style.width = width_;
		$('#timebar').width(width_);
		previous_miliseconds = data.miliseconds;
	}
}


function evaluateVolume_old(volume) {
	if (previous_volume != volume && volume >= 0) {
		// Volume
		var volume_percentage	= Math.round(100 * volume / <?php echo $max_volume; ?>);
		var width				= Math.round(200 * volume / <?php echo $max_volume; ?>);
		document.getElementById('volume').innerHTML = volume_percentage + '%';
		document.getElementById('volumeimage').src = '<?php echo $cfg['img']; ?>playlist_bar_on.png';
		document.getElementById('volumebar').style.width = width;
		previous_volume = volume;
	}
	if (previous_volume != volume && volume < 0) {
		// Mute volume
		var mute_volume = -1 * volume;
		var volume_percentage	= Math.round(100 * mute_volume / <?php echo $max_volume; ?>);
		var width				= Math.round(200 * mute_volume / <?php echo $max_volume; ?>);
		document.getElementById('volume').innerHTML = 'mute';
		document.getElementById('volumeimage').src = '<?php echo $cfg['img']; ?>playlist_bar_off.png';
		document.getElementById('volumebar').style.width = width;
		previous_volume = volume;
	}
}


function evaluateIsplaying(isplaying, idx) {
	if (previous_isplaying != isplaying) {
		if (isplaying == 0) {
			// stop
			$("#time").removeClass();
			$("#time").addClass("playlist_status_off");
			$("#play").removeClass();
			$("#play").addClass("playlist_status_off");
			$("#play").html('<i class="fa fa-play sign-ctrl"></i>');
			$("#play").attr("onclick","javascript:ajaxRequest('play.php?action=play&amp;menu=playlist', evaluateIsplaying);");
			$('#track' + idx + '_play').hide();
			document.getElementById('time').innerHTML = formattedTime(0);
			document.getElementById('timebar').style.width = 0;
			previous_miliseconds = 0;
		}
		else if (isplaying == 1) {
			// play
			$("#time").removeClass();
			$("#time").addClass("playlist_status_off");
			$("#play").html('<i class="fa fa-pause sign-ctrl"></i>');
			$("#play").removeClass();
			//$("#play").addClass("playlist_status_on");
			$("#play").addClass("playlist_status_off");
			$("#play").attr("onclick","javascript:ajaxRequest('play.php?action=pause&amp;menu=playlist', evaluateIsplaying);");
			$('#track' + idx + '_play').show();
		}
		else if (isplaying == 3) {
			// pause
			$("#time").removeClass();
			$("#time").addClass("blink_me playlist_status_off");
			$("#play").html('<i class="fa fa-play sign-ctrl"></i>');
			$("#play").removeClass();
			//$("#play").addClass("blink_me playlist_status_on");
			$("#play").addClass("playlist_status_off");
			$("#play").attr("onclick","javascript:ajaxRequest('play.php?action=play&amp;menu=playlist', evaluateIsplaying);");
			$('#track' + idx + '_play').hide();
		}
		previous_isplaying = isplaying
	}
}


function evaluateRepeat(repeat) {
	if (previous_repeat != repeat) {
		if (repeat == 0) document.getElementById('repeat').className = 'playlist_status_off';
		if (repeat == 1) document.getElementById('repeat').className = 'playlist_status_on';
		previous_repeat = repeat;
	}
}


function evaluateShuffle(shuffle) {
	if (previous_shuffle != shuffle) {
		if (shuffle == 0) document.getElementById('shuffle').className = 'playlist_status_off';
		if (shuffle == 1) document.getElementById('shuffle').className = 'playlist_status_on';
		previous_shuffle = shuffle;
	}
}


function evaluateGain(gain) {
	if (previous_gain != gain) {
		document.getElementById('gain').className="playlist_status_on";
		if (gain == 'off')		{document.getElementById('gain_text').innerHTML = 'gain: off';
		document.getElementById('gain').className="playlist_status_off";}
		if (gain == 'album')	document.getElementById('gain_text').innerHTML = 'gain: album';
		if (gain == 'auto')		document.getElementById('gain_text').innerHTML = 'gain: auto';
		if (gain == 'track')	document.getElementById('gain_text').innerHTML = 'gain: track';
		previous_gain = gain;
		
	}
}

function setFavorite(data) {
	if (data.action == "add") {
		$("i[id^='favorite_star']").removeClass("fa fa-star-o").addClass("fa fa-star");
	}
	else if (data.action == "remove") {
		$("i[id^='favorite_star']").removeClass("fa fa-star").addClass("fa fa-star-o");
	}
}


function _evaluateFavorite(data) {
	if (data.inFavorite) {
		$("i[id^='favorite_star']").removeClass("fa fa-star-o").addClass("fa fa-star");
	}
	else {
		$("i[id^='favorite_star']").removeClass("fa fa-star").addClass("fa fa-star-o");
	}
}

function evaluateTrack(data) {
	// data.artist, data.title, data.album, data.by, data.album_id, data.image_id
	$("#cover-spinner").show();
	var s = Math.floor(data.miliseconds / 1000);  
	var m = Math.floor(s / 60);  
	s = s % 60;
	if (s < 10) s = '0' +  s;
	
	//console.log ("test");
	
	document.getElementById('tracktime').innerHTML = m + ':' + s;
	artist = '';
	l = data.track_artist.length;
	if (l>1) {
		for (i=0; i<l; i++) {
			artist = artist + '<a href="index.php?action=view2&order=artist&sort=asc&artist=' + encodeURIComponent(data.track_artist_url[i]) + '">' + data.track_artist[i] + '</a>';
			if (i!=l-1) {
			artist = artist + '<a href="index.php?action=view2&order=artist&sort=asc&artist=' + data.track_artist_url_all + '"><span class="artist_all">&</span></a>'
			}
		}
	} 
	else {
		artist = '<a href="index.php?action=view2&order=artist&sort=asc&artist=' + encodeURIComponent(data.track_artist_url[0]) + '">' + data.track_artist[0] + '</a>';
	}
	document.getElementById('artist1').innerHTML = document.getElementById('artist').innerHTML = artist;
	//document.getElementById('artist1').innerHTML = document.getElementById('artist').innerHTML = '<a href="index.php?action=view2&order=artist&sort=asc&artist=' + data.track_artist_url + '">' + data.track_artist + '</a>'; 
	document.getElementById('track_number1').innerHTML = document.getElementById('track_number').innerHTML = data.number;
	if (data.other_track_version) {
		document.getElementById('title1').innerHTML = document.getElementById('title').innerHTML =  '<a href="index.php?action=view3all&title=' + data.title + '">' + data.title + '</a>';
	}
	else {
		document.getElementById('title1').innerHTML = document.getElementById('title').innerHTML =  data.title;
	}
	
	document.getElementById('album1').innerHTML = document.getElementById('album').innerHTML = '<a href="index.php?action=view3&album_id=' + data.album_id + '">' + data.album + '</a>'; 
	if (data.year) document.getElementById('year1').innerHTML = document.getElementById('year').innerHTML = '<a href="index.php?action=view2&order=artist&sort=asc&year=' + data.year + '">' + data.year + '</a>';
	else document.getElementById('year1').innerHTML = document.getElementById('year').innerHTML = '&nbsp;';
	
	if (data.genre) document.getElementById('genre1').innerHTML = document.getElementById('genre').innerHTML = '<a href="index.php?action=view2&order=artist&sort=asc&&genre_id=' + data.genre_id + '">' + data.genre + '</a>';
	else document.getElementById('genre1').innerHTML = document.getElementById('genre').innerHTML = '';
	
	var rel_file = encodeURIComponent(data.relative_file);
	//console.log ("rel_file=" + rel_file);
	var params = data.audio_dataformat + '&nbsp;&bull;&nbsp;' + data.audio_bits_per_sample + 'bit - ' + data.audio_sample_rate/1000 + 'kHz&nbsp;&bull;&nbsp;' + data.audio_profile;
	//if (data.dr) params = params + '&nbsp;&bull;&nbsp;DR=' + data.dr;
	params = params + '&nbsp;&bull;<a href="getid3/demos/demo.browse.php?filename=<?php echo $cfg['media_dir']; ?>' + rel_file + '" onClick="showSpinner();">&nbsp;<i class="fa fa-info-circle"></i>&nbsp;file details</a>';
	
	document.getElementById('parameters').innerHTML = params;
	
	document.getElementById('lyrics1').innerHTML = document.getElementById('lyrics').innerHTML = '<a href="ridirect.php?query_type=lyrics&q=' + data.track_artist + '+' + data.title_core + '" target="_blank"><i class="fa fa-search"></i>&nbsp;Lyrics</a>'; 
	
	if (data.inFavorite) {
		document.getElementById('favorites').innerHTML = document.getElementById('favorites1').innerHTML = '<i id="favorite_star" class="fa fa-star"></i>'; 
	}
	else {
		document.getElementById('favorites').innerHTML = document.getElementById('favorites1').innerHTML = '<i id="favorite_star" class="fa fa-star-o"></i>'; 
	}
	
	
	/* document.getElementById('info1').innerHTML = document.getElementById('info').innerHTML = '<a href="getid3/demos/demo.browse.php?filename=<?php echo $cfg['media_dir']; ?>' + data.relative_file + '"><i class="fa fa-info-circle"></i></a>';  */
	
	$("i[id^='favorite_star']").unbind("click");
	
	$("i[id^='favorite_star']").click(function() {
		var action = '';
		if ($("i[id^='favorite_star']").attr('class') == 'fa fa-star-o') {
			action = 'add';
			}
		else {
			action = 'remove';
		}
		ajaxRequest('ajax-favorite.php?action=' + action + '&track_id=' + data.track_id, setFavorite);
		
	});
	
	
	/*if (data.album_id) document.getElementById('image').innerHTML = '<a href="index.php?action=view3&album_id=' + data.album_id + '"><img id="image_in" src="' + data.image_front + '" alt="" onMouseOver="return overlib(\'Go to album\');" onMouseOut="return nd();"><\/a>';
	else document.getElementById('image').innerHTML = '<img src="<?php echo $cfg['img']; ?>large_file_not_found.png" alt="" width="100" height="100">';
	*/
	/*
	if (data.album_id) document.getElementById('image').innerHTML = '<a href="index.php?action=view3&album_id=' + data.album_id + '"><img id="image_in" src="image.php?image_id=' + data.image_id + '&quality=hq" alt="" onMouseOver="return overlib(\'Go to album\');" onMouseOut="return nd();"><\/a>';
	else document.getElementById('image').innerHTML = '<img id="image_in" src="<?php echo $cfg['img']; ?>large_file_not_found.png" alt="">';
	*/
	
	if (data.album_id) {
		$("#image_in").attr("src","image.php?image_id=" + data.image_id + "&quality=hq&track_id=" + data.track_id);
		$("#image a").attr("href","index.php?action=view3&album_id=" + data.album_id);
		
	}
	else document.getElementById('image').innerHTML = '<img id="image_in" src="<?php echo 'image/'; ?>large_file_not_found.png" alt="">';
	$("#cover-spinner").hide();
	
	/* document.getElementById('track_number1').innerHTML = document.getElementById('track_number').innerHTML; 
	document.getElementById('artist1').innerHTML = document.getElementById('artist').innerHTML;
	document.getElementById('album1').innerHTML = document.getElementById('album').innerHTML;
	document.getElementById('title1').innerHTML = document.getElementById('title').innerHTML;
	document.getElementById('genre1').innerHTML = document.getElementById('genre').innerHTML;
	document.getElementById('year1').innerHTML = document.getElementById('year').innerHTML;
	document.getElementById('lyrics1').innerHTML = document.getElementById('lyrics').innerHTML;
	 */
	
	changeTileSizeInfo();
	resizeImgContainer();
	
	/* var im = "image.php?image_id=" + data.image_id
	
	$('#bgTemp').remove();
	
	//$('head').append('<style id="bgTemp">#back-ground:before{background-image:url(' + im + ') !important;}</style>');
	
	$("#back-ground-img").attr("src","image.php?image_id=" + data.image_id + "&quality=hq");
	 */
}



$(document).ready(function() {
	
				resizeImgContainer();
				
				$('.showPL').click(function(){

					$('html, body').animate({
						scrollTop: ($(".select").offset().top - $("#fixedMenu").height())
					}, 1000);

				 });

				$('.hidePL').click(function(){

					$('html, body').animate({
						scrollTop: $(".overlib").offset().top
					}, 1000);

				 });
				
				//resizeCover();
				
				$('#pl-track-info-narrow').bind("DOMSubtreeModified",function() {
					//resizeImgContainer();
				});
				
				$(window).resize(function() {
					//resizeCover();
					resizeImgContainer();
				});
				 
});

/* function _resizeCover() {
var mql = window.matchMedia("all and (min-width: 639px)");
var h=window.innerHeight;
var w=window.innerWidth;
console.log ("w: %s / h: %s", w , h);

document.getElementById("image_container").style.width = "";
document.getElementById("image_container").style.height = "";

//var infoH = document.getElementById("pl-track-info-right").style.height;
var h1 = h - 105; 
var h2=document.getElementById("pl-track-info").clientHeight;


//console.log ("pl-track-info: %s", h2);
if ((w > h1) && (h1<(w*0.45)) && w > 639) {
		console.log ("h2<h1 %s %s", h2, h1);
		document.getElementById("image_container").style.width = h1;
		document.getElementById("image_container").style.height = h1;
		}
	else {
	document.getElementById("image_container").style.width = "";
	document.getElementById("image_container").style.height = "";
	}

// if (h>w) {document.getElementById("image_container").style.width = "100%";
	// }

console.log ("h2>h1 %s %s", h2, h1);
} */



</script>
<?php
require_once('include/footer.inc.php');
?>
