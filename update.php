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
//  | update.php                                                             |
//  +------------------------------------------------------------------------+

//error_reporting(-1);
//ini_set('display_errors', 'On');

ini_set('max_execution_time', 0);
//$updateStage = $_GET["updateStage"];
require_once('include/initialize.inc.php');
require_once('include/cache.inc.php');

/* header("Connection: close", true);
ob_start ();
header("Content-Length: 0", true);
header("Location: update_progress.php", true);
ob_end_flush();
flush();
session_write_close(); */
//fastcgi_finish_request(); 

ignore_user_abort(true);

//exit();




$cfg['menu'] = 'config';

$action = getpost('action');

$flag	= (int) getpost('flag');

if		(PHP_SAPI == 'cli')					cliUpdate();
elseif	($action == 'update')				update();
elseif	($action == 'imageUpdate')			imageUpdate($flag);
elseif	($action == 'saveImage')			saveImage($flag);
elseif	($action == 'selectImageUpload')	selectImageUpload($flag);
elseif	($action == 'imageUpload')			imageUpload($flag);
else	message(__FILE__, __LINE__, 'error', '[b]Unsupported input value for[/b][br]action');
exit();





//  +------------------------------------------------------------------------+
//  | Update                                                                 |
//  +------------------------------------------------------------------------+
function update() {

	global $cfg, $db, $lastGenre_id, $getID3, $dirsCounter, $filesCounter, $curFilesCounter, $curDirsCounter;
	authenticate('access_admin', false, true);
	
	
	require_once('getid3/getid3/getid3.php');
	require_once('include/play.inc.php'); // Needed for mpdUpdate()
	
	$cfg['cli_update'] = false;
	$startTime = new DateTime();
	
	$path = $cfg['media_dir'];
	$curFilesCounter = 0;
	$curDirsCounter = 0;
	$dirsCounter = 0;
	$filesCounter = 0;
	$prevDirsCounter = 0;
	$prevFilesCounter = 0;
	$dirs = array();
	/* $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
	foreach($objects as $name){
		if ($name->isDir()) {
			++$dirsCounter;
		}
		else {
			++$filesCounter;
		}
	} */
	
	//mysql_query('DELETE FROM genre');
	//$lastGenre_id = 1;
	
	// formattedNavigator
	$nav			= array();
	$nav['name'][]	= 'Configuration';
	$nav['url'][]	= 'config.php';
	$nav['name'][]	= 'Update';
	require_once('include/header.inc.php');
?>
<table width="100%" cellspacing="0" cellpadding="0" class="border">
<tr class="header">
	<td class="space"></td>
	<td class="update_text">Update</td>
	<td>Progress</td>
	<td class="space"></td>
</tr>
<tr class="line"><td colspan="4"></td></tr>
<tr class="odd">
	<td></td>
	<td>Structure &amp; image:</td>
	<td><span id="structure"></span></td>
	<td></td>
</tr>
<tr class="even">
	<td></td>
	<td>File info:</td>
	<td><span id="fileinfo"></span></td>
	<td></td>
</tr>
<tr class="odd">
	<td></td>
	<td>Cleanup:</td>
	<td><span id="cleanup"></span></td>
	<td></td>
</tr>
<tr class="even">
	<td></td>
	<td>Update time:</td>
	<td><span id="updateTime"></span></td>
	<td></td>
</tr>
</table>
<script>
	hideSpinner();
	window.setInterval(function() {
		show_update_progress();
	}, 500);
	
	function show_update_progress() {
		$.ajax({
			type: "POST",
			url: "ajax-update-progress.php",
			dataType : 'json',
			success : function(json) {
				var s = json['structure_image'];
				if (s.indexOf("fa-spin") > -1) {
					if (!$("#structure").hasClass("fa-spin"))
						$("#structure").html(json['structure_image']);
				}
				else
					$("#structure").html(json['structure_image']);
				
				s = json['file_info'];
				if (s.indexOf("fa-spin") > -1) {
					if (!$("#fileinfo").hasClass("fa-spin"))
						$("#fileinfo").html(json['file_info']);
				}	
				else
					$("#fileinfo").html(json['file_info']);
				
				s = json['cleanup'];
				if (s.indexOf("fa-spin") > -1) {
					if (!$("#cleanup").hasClass("fa-spin"))
						$("#cleanup").html(json['cleanup']);
				}
				else
					$("#cleanup").html(json['cleanup']);
				
				$("#updateTime").html(json['update_time']);
				
			}
		});
	}
	
	</script>
	
	<?php
	
	@ob_flush();
	flush();
	
	$cfg['footer'] = 'dynamic';
	require('include/footer.inc.php');
	
	$getID3 = new getID3;
	//initial settings for getID3:
	include 'include/getID3init.inc.php';
	
	
	$result = mysql_query('SELECT * FROM update_progress');
	
	if (mysql_num_rows($result)==0) {	
			mysql_query('INSERT INTO update_progress (update_status, structure_image, file_info, cleanup, update_time, last_update)
						VALUES ("0", "", "", "", "", "")');
			$update_status = 0;
		} 
		else {
			$row=mysql_fetch_assoc($result);
			$update_status=$row["update_status"];
		}
	
	if ($update_status <> 1) {
		
		mysql_query("update update_progress set 
			update_status = 1,
			structure_image = '',
			file_info = '',
			cleanup = '',
			update_time = '',
			last_update = 'Update in progress..'");
		
		//@ob_flush();
		//flush();
		
		mysql_query("update update_progress set 
			structure_image = 'Requesting MPD update...'");
		
		mpdUpdate();
		
		//@ob_flush();
		//flush();
		
		mysql_query("update update_progress set 
			structure_image = '<i class=\"fa fa-cog larger icon-selected fa-spin\"></i>'");
		
		
		// Short sleep to prevent update problems with a previous update process that has not stopped yet.
		sleep(1);
		
		$cfg['new_escape_char_hash']	= hmacmd5(print_r($cfg['escape_char'], true), file_get_contents(NJB_HOME_DIR . 'update.php'));
		$cfg['force_filename_update']	= ($cfg['new_escape_char_hash'] != $cfg['escape_char_hash']) ? true : false;

		$cfg['force_filename_update'] = false;
		
		
		
		if ($cfg['image_size'] != NJB_IMAGE_SIZE || $cfg['image_quality'] != NJB_IMAGE_QUALITY) {
			mysql_query('TRUNCATE TABLE bitmap');
			mysql_query('UPDATE server SET value = "' . mysql_real_escape_string(NJB_IMAGE_SIZE) . '" WHERE name = "image_size" LIMIT 1');
			mysql_query('UPDATE server SET value = "' . mysql_real_escape_string(NJB_IMAGE_QUALITY) . '" WHERE name = "image_quality" LIMIT 1');
		}
		
		mysql_query('UPDATE album SET updated = 0');
		mysql_query('UPDATE track SET updated = 0');
		mysql_query('UPDATE bitmap SET updated = 0');
		mysql_query('UPDATE genre SET updated = 0');
		mysql_query('UPDATE album_id SET updated = 0');
		//mysql_query('TRUNCATE album_id');
		$query = mysql_query('SELECT MAX(CAST(genre_id AS UNSIGNED)) AS last_genre_id FROM genre');
		$rsGenre = mysql_fetch_assoc($query);
		if ($rsGenre['last_genre_id'] > 0) {
			$lastGenre_id = ($rsGenre['last_genre_id'] + 1);
			}
		else {
			$lastGenre_id = 1;
		}
		
		$cfg['timer'] = 0; // force update
	
		//recursiveScanCount_add2table($cfg['media_dir']);
		recursiveScanCount($cfg['media_dir']);
		
		/* $result = mysql_query("update update_progress set 
			update_status = 0,
			update_time = '" . $updateTime . "',
			last_update = '" . date('Y-m-d, H:i:s')   . "'
			");
		exit(); */
		
		recursiveScan($cfg['media_dir']);
		//exit();
		
		mysql_query('UPDATE update_progress SET	structure_image = "<div class=\'out\'><div class=\'in\' style=\'width: 200px\'></div></div> 100%"');
		
		sleep(1);
		
		mysql_query('DELETE FROM album WHERE NOT updated');
		mysql_query('DELETE FROM track WHERE NOT updated');
		mysql_query('DELETE FROM bitmap WHERE NOT updated');
		mysql_query('DELETE FROM genre WHERE NOT updated');
		
		
		mysql_query('UPDATE server SET value = "' . mysql_real_escape_string($cfg['new_escape_char_hash']) . '" WHERE name = "escape_char_hash" LIMIT 1');
			
		$no_image = mysql_num_rows(mysql_query('SELECT album_id FROM bitmap WHERE flag = 0'));
		/* if ($no_image > 0)	{
			mysql_query('update update_progress set 
			structure_image = "<a href=\'update.php?action=imageUpdate&amp;flag=0\'><img src=\'' . $cfg['img'] . 'small_image.png\' alt=\'\' class=\'small space\'>Update ' . $no_image . (($no_image == 1) ? ' image' : ' images') . ' from internet</a>"');
		} */
		
		if ($no_image > 0)	{
			mysql_query('update update_progress set 
			structure_image = "<a href=\'statistics.php?action=noImageFront\'>No image for ' . $no_image . (($no_image == 1) ? ' folder' : ' folers') . '</a>"');
		}
		else {
			mysql_query('update update_progress set 
			structure_image = "<i class=\"fa fa-check icon-ok \"></i> "');
		}
		// @ob_flush();
		// flush();
		
		mysql_query('update update_progress set 
			file_info = "<i class=\"fa fa-cog larger icon-selected fa-spin\"></i>"');
		
		$cfg['timer'] = 0; // force update
		
		
		fileInfo();
		
		mysql_query('UPDATE update_progress SET	file_info = "<div class=\'out\'><div class=\'in\' style=\'width: 200px\'></div></div> 100%"');
		
		sleep(1);
		
		$error = mysql_num_rows(mysql_query('SELECT error FROM track WHERE error != ""'));
		if ($error > 0)	{
			mysql_query('update update_progress set 
			file_info = "<a href=\'statistics.php?action=fileError\'><i class=\"fa fa-minus-circle icon-nok\"></i> ' . $error . (($error == 1) ? ' error' : ' errors') . '</a>"');
		}
		else {
			mysql_query('update update_progress set 
			file_info = "<i class=\"fa fa-check icon-ok\"></i> "');
		}
		
		// @ob_flush();
		// flush();
		
		mysql_query('update update_progress set 
			cleanup = "<i class=\"fa fa-cog larger icon-selected fa-spin\"></i> "');
		
		databaseCleanup();
		
		// @ob_flush();
		// flush();
		
		mysql_query('update update_progress set 
			cleanup = "<i class=\"fa fa-check icon-ok\"></i> "');
		
		$stopTime = new DateTime();
		
		$updateTime = $stopTime->diff($startTime);
		
		$updateTime = $updateTime->h . 'h ' . $updateTime->i . 'm ' . $updateTime->s . 's';
		
		$result = mysql_query("update update_progress set 
			update_status = 0,
			update_time = '" . $updateTime . "',
			last_update = '" . date('Y-m-d, H:i:s')   . "'
			");
	}
	else {
		$structure_image=$row["structure_image"];
		echo '<script type="text/javascript"> document.getElementById(\'structure\').innerHTML=" ' . $structure_image . '";</script>' . "\n";
		
		$file_info=$row["file_info"];
		echo '<script type="text/javascript"> document.getElementById(\'fileinfo\').innerHTML=" ' . $file_info . '";</script>' . "\n";
		
		$cleanup=$row["cleanup"];
		echo '<script type="text/javascript"> document.getElementById(\'cleanup\').innerHTML=" ' . $cleanup . '";</script>' . "\n";
	
		$update_time=$row["update_time"];
		echo '<script type="text/javascript"> document.getElementById(\'updateTime\').innerHTML=" ' . $update_time . '";</script>' . "\n";
	}
	$cfg['footer'] = 'close';
	require('include/footer.inc.php');
}


//  +------------------------------------------------------------------------+
//  | Recursive scan                                                         |
//  +------------------------------------------------------------------------+
function recursiveScan($dir) {
	global $cfg, $db;
	$album_id	= '';
	$file		= array();
	$filename	= array();
	
	$entries = @scandir($dir) or message(__FILE__, __LINE__, 'error', '[b]Failed to open directory:[/b][br]' . $dir . '[list][*]Check media_dir value in the config.inc.php file[*]Check file permission[/list]');
	foreach ($entries as $entry) {
		if ($entry[0] != '.' && !in_array($entry, array('lost+found', 'Temporary Items', 'Network Trash Folder', 'System Volume Information', 'RECYCLER', '$RECYCLE.BIN'))) {
			if (is_dir($dir . $entry . '/'))
				recursiveScan($dir . $entry . '/');
			else {
				$extension = substr(strrchr($entry, '.'), 1);
				$extension = strtolower($extension);
				if (in_array($extension, $cfg['media_extension'])) {
					$file[] 	= $dir . $entry;
					$filename[] = substr($entry, 0, -strlen($extension) - 1);
				}
				elseif ($extension == 'id')
					$album_id = substr($entry, 0, -3);
			}
		}
	}
	if (count($file) > 0) {
		mysql_query("UPDATE album_id SET 
		updated		= '1'
		WHERE 
		path		= '" . mysql_real_escape_string($dir) . "'
		LIMIT 1");
		
		if (mysql_affected_rows($db) == 0) {
			if ($album_id == '') $album_id = base_convert(uniqid(), 16, 36);
			$album_add_time = time();
			mysql_query("INSERT INTO album_id (album_id, path, album_add_time, updated) VALUES
			('" . mysql_real_escape_string($album_id) . "','" . mysql_real_escape_string($dir) . "','" . $album_add_time . "','1')");
		}
		else {
			$ids = mysql_query("SELECT album_id, album_add_time FROM album_id WHERE
			path = '" . mysql_real_escape_string($dir) . "' LIMIT 1");
			$row = mysql_fetch_assoc($ids);
			$album_id = $row["album_id"];
			$album_add_time = $row["album_add_time"];
		}
		
		fileStructure($dir, $file, $filename, $album_id, $album_add_time);
	}
}


//  +------------------------------------------------------------------------+
//  | Recursive scan - count directories                                     |
//  +------------------------------------------------------------------------+

function recursiveScanCount_add2table($dir) {
	global $cfg, $db, $dirsCounter, $filesCounter, $dirs;
	$album_id	= '';
	$file		= '';
	//$filename	= '';
	
	$entries = @scandir($dir) or message(__FILE__, __LINE__, 'error', '[b]Failed to open directory:[/b][br]' . $dir . '[list][*]Check media_dir value in the config.inc.php file[*]Check file permission[/list]');
	foreach ($entries as $entry) {
		if ($entry[0] != '.' && !in_array($entry, array('lost+found', 'Temporary Items', 'Network Trash Folder', 'System Volume Information', 'RECYCLER', '$RECYCLE.BIN'))) {
			if (is_dir($dir . $entry . '/')) {
				recursiveScanCount_add2table($dir . $entry . '/');
				}
			else {
				
				if (!in_array($dir, $dirs)) {
					$dirs[] = $dir;
					$dirsCounter = count($dirs);
					mysql_query("UPDATE update_progress SET 
					structure_image = 'Counting directories: " . $dirsCounter . "'");
				}
				$extension = substr(strrchr($entry, '.'), 1);
				$extension = strtolower($extension);
				if ($extension == 'id')	{
					$album_id = substr($entry, 0, -3);
					$file = $dir . $entry;
					$album_add_time = filemtime($dir . $entry);
					mysql_query("INSERT INTO album_id (album_id, path, album_add_time, updated) VALUES
					('" . mysql_real_escape_string($album_id) . "','" . mysql_real_escape_string($dir) . "', '" . $album_add_time . "', '1')");
				}
			}
		}
	}
}


//  +------------------------------------------------------------------------+
//  | Recursive scan - count directories                                     |
//  +------------------------------------------------------------------------+


function recursiveScanCount($dir) {
	global $cfg, $db, $dirsCounter, $filesCounter, $dirs;
	$album_id	= '';
	$file		= array();
	$filename	= array();
	
	$entries = @scandir($dir) or message(__FILE__, __LINE__, 'error', '[b]Failed to open directory:[/b][br]' . $dir . '[list][*]Check media_dir value in the config.inc.php file[*]Check file permission[/list]');
	foreach ($entries as $entry) {
		if ($entry[0] != '.' && !in_array($entry, array('lost+found', 'Temporary Items', 'Network Trash Folder', 'System Volume Information', 'RECYCLER', '$RECYCLE.BIN'))) {
			if (is_dir($dir . $entry . '/')) {
				recursiveScanCount($dir . $entry . '/');
				}
			else {
				if (!in_array($dir, $dirs)) {
					$dirs[] = $dir;
					$dirsCounter = count($dirs);
					mysql_query("UPDATE update_progress SET 
					structure_image = 'Counting directories: " . $dirsCounter . "'");
				}
			}
		}
	}
}


//  +------------------------------------------------------------------------+
//  | File structure                                                         |
//  +------------------------------------------------------------------------+
Function fileStructure($dir, $file, $filename, $album_id, $album_add_time) {
	global $cfg, $db, $lastGenre_id, $getID3, $dirsCounter, $filesCounter, $curFilesCounter, $curDirsCounter, $prevDirsCounter;
	
	/* unused because of writing ID into album_id table 
	if ($album_id == '') {
		$album_id = base_convert(uniqid(), 16, 36);
		$album_add_time = time();
		if (file_put_contents($dir . $album_id . '.id', '') === false)
			message(__FILE__, __LINE__, 'error', '[b]Failed to write file:[/b][br]' . $dir . $album_id . '.id[list][*]Check file/directory permission.[/list]');
	}
	elseif (preg_match('#^[a-z0-9]{10,11}$#', $album_id) == false)
		message(__FILE__, __LINE__, 'error', '[b]This is not a valid id:[/b][br]' . $dir . $album_id . '.id[list][*]Remove this id and update again.[/list]');
	else
		$album_add_time = filemtime($dir . $album_id . '.id');
	 */
		
	// Also needed for track update!
	$discs 			= 1;
	$disc_digits	= 0;
	$track_digits	= 0;
	
	$year				= NULL;
	$month				= NULL;
	$artist 			= 'Unknown AlbumArtist';
	$aGenre 			= 'Unknown Genre';
	$album_dr			= NULL;
	
	
	if ($cfg['name_source'] != 'tags') {
		if (preg_match('#^(0{0,1}1)(0{1,3}1)+\.\s+.+#', $filename[0], $match) && preg_match('#^(\d{' . strlen($match[1] . $match[2]) . '})+\.\s+.+#', $filename[count($filename)-1])) {
			// Multi disc
			$disc_digits	= strlen($match[1]);
			$track_digits	= strlen($match[2]);
			preg_match('#^(\d{' . $disc_digits . '})\d{' . $track_digits . '}+\.\s+#', $filename[count($filename)-1], $match);
			$discs = $match[1];
		}
		elseif (preg_match('#^(\d{2,4})+\.\s+.+#', $filename[0], $match)) {
			// Single disc
			$track_digits	= strlen($match[1]);
		}
			
		$temp				= decodeEscapeChar($dir);
		$temp   			= explode('/', $temp);
		$n					= count($temp);
		
		$artist_alphabetic 	= $temp[$n - 3];
		$artist 			= $artist_alphabetic;
		$album				= $temp[$n - 2];
		
		if (preg_match('#^(\d{4})\s+-\s+(.+)#', $album, $match)) {
	    $year	= $match[1];
		$album	= $match[2];
		}
		elseif (preg_match('#^(\d{4})(0[1-9]|1[012])\s+-\s+(.+)#', $album, $match)) {
			$year	= $match[1];
			$month	= $match[2];
			$album	= $match[3];
		}	
	}
	
	
	if ($cfg['name_source'] == 'tags') {
		
		$ThisFileInfo = $getID3->analyze($file[0]);
		getid3_lib::CopyTagsToComments($ThisFileInfo); 
		if (isset($ThisFileInfo['comments']['albumartist'][0])) $artist = $ThisFileInfo['comments']['albumartist'][0];
		elseif (isset($ThisFileInfo['comments']['band'][0])) $artist = $ThisFileInfo['comments']['band'][0];
		//elseif (isset($ThisFileInfo['comments']['albumartist'][0])) $artist = $ThisFileInfo['comments']['albumartist'][0];
		
		if ($artist == 'Unknown AlbumArtist') {
			//if (isset($ThisFileInfo['comments']['artist'][1])) $artist = $ThisFileInfo['comments']['artist'][1];
			if (isset($ThisFileInfo['comments']['artist'][0])) $artist = $ThisFileInfo['comments']['artist'][0];
			//elseif (isset($ThisFileInfo['id3v2']['comments']['artist'][0])) $artist = $ThisFileInfo['id3v2']['comments']['artist'][0];
			//elseif (isset($ThisFileInfo['ape']['comments']['artist'][0])) $artist = $ThisFileInfo['ape']['comments']['artist'][0];		
		};
		
		$artist_alphabetic	= $artist;
		
		if (isset($ThisFileInfo['comments']['year'][0])) $year = $ThisFileInfo['comments']['year'][0];
		elseif (isset($ThisFileInfo['comments']['date'][0])) $year = $ThisFileInfo['comments']['date'][0];
		
		if (preg_match('#[1][9][0-9]{2}|[2][0-9]{3}#', $year, $match)) {
				$year	= $match[0];
		}
		
		if (isset($ThisFileInfo['comments']['album dynamic range'][0])) $album_dr = $ThisFileInfo['comments']['album dynamic range'][0];
		elseif (isset($ThisFileInfo['tags']['id3v2']['text']['ALBUM DYNAMIC RANGE'])) $album_dr = $ThisFileInfo['tags']['id3v2']['text']['ALBUM DYNAMIC RANGE'];
		
		if (isset($ThisFileInfo['comments']['genre'][0])) $aGenre = $ThisFileInfo['comments']['genre'][0];
		
		if ((strpos(strtolower($dir), strtolower($cfg['misc_tracks_folder'])) === false) && (strpos(strtolower($dir), strtolower($cfg['misc_tracks_misc_artists_folder'])) === false)) {
			
			
			//if (isset($ThisFileInfo['comments']['album'][1])) $album = $ThisFileInfo['comments']['album'][1];
			if (isset($ThisFileInfo['comments']['album'][0])) $album = $ThisFileInfo['comments']['album'][0];
			//elseif (isset($ThisFileInfo['id3v2']['comments']['album'][0])) $album = $ThisFileInfo['id3v2']['comments']['album'][0];
			//elseif (isset($ThisFileInfo['ape']['comments']['album'][0])) $album = $ThisFileInfo['ape']['comments']['album'][0];
			else $album = 'Unknown Album Title';
			
			
		}
		elseif (strpos(strtolower($dir), strtolower($cfg['misc_tracks_folder'])) !== false) {
			$year = NULL;
			$album = $cfg['misc_tracks_folder'] . $artist;
			/* if (strtolower(basename($dir)) == strtolower($cfg['misc_tracks_folder'])) 
				$album = $cfg['misc_tracks_folder'] . $artist;				
			else
				$album = basename($dir); */
			
			
		}
		elseif (strpos(strtolower($dir), strtolower($cfg['misc_tracks_misc_artists_folder'])) !== false) {
			$artist = 'Various Artists';
			$artist_alphabetic	= $artist;
			$aGenre = NULL;
			$year = NULL;
			if (strtolower(basename($dir)) == strtolower($cfg['misc_tracks_misc_artists_folder'])) 
				$album = $cfg['misc_tracks_misc_artists_folder'];
			else
				$album = basename($dir);
		}
		
		$result = mysql_query('SELECT genre_id FROM genre WHERE genre="' . mysql_real_escape_string($aGenre) . '"');
		$row=mysql_fetch_assoc($result);
		$aGenre_id=$row["genre_id"];
		
		if (mysql_num_rows($result)==0) {	
			mysql_query('INSERT INTO genre (genre_id, genre, updated)
						VALUES ("' . mysql_real_escape_string($lastGenre_id) . '",
								"' . mysql_real_escape_string($aGenre) . '",
								1)');
			$aGenre_id = $lastGenre_id;
			++$lastGenre_id;
			
		} else {		
				$result = mysql_query("update genre set 
				updated = 1
				WHERE genre = '". mysql_real_escape_string($aGenre) ."';"); 
		}

		
	}
	
	//
	// Update/Insert album information on the end of this function to be able to include image_id
	//
	
	++$curDirsCounter; 	
	if ($cfg['cli_update'] == false && ((microtime(true) - $cfg['timer']) * 1000) > $cfg['update_refresh_time'] && ($curDirsCounter/$dirsCounter > ($prevDirsCounter/$dirsCounter + 0.005))) {
		
		$prevDirsCounter = $curDirsCounter;
		
		mysql_query('update update_progress set 
			structure_image = "<div class=\'out\'><div class=\'in\' style=\'width:' . html(floor($curDirsCounter/$dirsCounter * 200)) . 'px\'></div></div> ' . html(floor($curDirsCounter/$dirsCounter * 100)) . '%"');
	
		
		// @ob_flush(); 
		// flush();
		 
		$cfg['timer'] = microtime(true);
	}
	
	if ($cfg['cli_update'] && $cfg['cli_silent_update'] == false)
		echo $artist_alphabetic  . ' - ' . $album . "\n";
	
		
	// Track update
	$disc		= 1;
	$number		= NULL;
	
	for ($i=0; $i < count($filename); $i++) {
		$relative_file = substr($file[$i], strlen($cfg['media_dir']));
		
		mysql_query('UPDATE track SET
			updated				= 1
			WHERE album_id		= "' . mysql_real_escape_string($album_id) . '"
			AND relative_file	= BINARY "' . mysql_real_escape_string($relative_file) . '"
			LIMIT 1');
		if ($cfg['force_filename_update'] || mysql_affected_rows($db) == 0)
			{
			$temp = decodeEscapeChar($filename[$i]);
			if ($cfg['name_source'] != 'tags') {
				//if (preg_match('#^(\d{' . $disc_digits . '})(\d{' . $track_digits . '})\s+-\s+(.+)#', $temp, $match)) {
				if (preg_match('#^(\d{' . $disc_digits . '})(\d{' . $track_digits . '})+\.\s+(.+)#', $temp, $match)) {	
					if ($disc_digits > 0) {
						// Multiple disc
						$disc		= $match[1];
						$number		= $match[2];
					}
					else {
						// Single disc
						$number		= $match[2];
					}
					$temp = $match[3]; // Strip disc and track number
				}
				if (preg_match('#^(.+?)\s+-\s+(.+?)(?:\s+Ft\.\s+(.+))?$#i', $temp, $match)) {
					$track_artist	= $match[1];
					$title			= $match[2];
					$featuring		= (isset($match[3])) ? $match[3] : '';
				}  
				elseif (preg_match('#^(.+?)(?:\s+Ft\.\s+(.+))?$#i', $temp, $match)) {
					$track_artist	= $artist;
					$title			= $match[1];
					$featuring		= (isset($match[2])) ? $match[2] : '';
				}
				else {
					$track_artist	= '*** UNSUPPORTED FILENAME FORMAT ***';
					$title			= '(' . $filename[$i] . ')';
					$featuring		= '';
				}
			}	
			
			if ($cfg['name_source'] == 'tags') {
				$ThisFileInfo = $getID3->analyze($file[$i]);
	
				getid3_lib::CopyTagsToComments($ThisFileInfo);
				//$number = $ThisFileInfo['comments']['tracknumber'][0];
				
				if (isset($ThisFileInfo['comments']['tracknumber'][0])) $number = $ThisFileInfo['comments']['tracknumber'][0];
				elseif (isset($ThisFileInfo['comments']['track_number'][0])) $number = $ThisFileInfo['comments']['track_number'][0];
				elseif (isset($ThisFileInfo['comments']['track'][0])) $number = $ThisFileInfo['comments']['track'][0];
				
				//if (isset($ThisFileInfo['comments']['artist'][1])) $track_artist = $ThisFileInfo['comments']['artist'][1];
				if (isset($ThisFileInfo['comments']['artist'][0])) $track_artist = $ThisFileInfo['comments']['artist'][0];
				//elseif (isset($ThisFileInfo['id3v2']['comments']['artist'][0])) $track_artist = $ThisFileInfo['id3v2']['comments']['artist'][0];
				//elseif (isset($ThisFileInfo['ape']['comments']['artist'][0])) $track_artist = $ThisFileInfo['ape']['comments']['artist'][0];
				else $track_artist = 'Unknown Artist';
				
				//if (isset($ThisFileInfo['comments']['title'][1])) $title = $ThisFileInfo['comments']['title'][1];
				if (isset($ThisFileInfo['comments']['title'][0])) $title = $ThisFileInfo['comments']['title'][0];
				//elseif (isset($ThisFileInfo['id3v2']['comments']['title'][0])) $title = $ThisFileInfo['id3v2']['comments']['title'][0];
				//elseif (isset($ThisFileInfo['ape']['comments']['title'][0])) $title = $ThisFileInfo['ape']['comments']['title'][0];
				else $album = 'Unknown Title';
				
				$featuring	= '';
			}
			
			if (mysql_affected_rows($db) == 0)
				mysql_query('INSERT INTO track (artist, featuring, title, relative_file, disc, number, album_id, updated)
					VALUES ("' . mysql_real_escape_string($track_artist) . '",
					"' . mysql_real_escape_string($featuring) . '",
					"' . mysql_real_escape_string($title) . '",
					"' . mysql_real_escape_string($relative_file) . '",
					' . (int) $disc . ',
					' . ((is_null($number)) ? 'NULL' : (int) $number) . ',
					"' . mysql_real_escape_string($album_id) . '",
					1)');
			else
				mysql_query('UPDATE track SET
					artist				= "' . mysql_real_escape_string($track_artist) . '",
					featuring			= "' . mysql_real_escape_string($featuring) . '",
					title				= "' . mysql_real_escape_string($title) . '",
					relative_file		= "' . mysql_real_escape_string($relative_file) . '",
					disc				= ' . (int) $disc . ',
					number				= ' . ((is_null($number)) ? 'NULL' : (int) $number) . ',
					album_id			= "' . mysql_real_escape_string($album_id) . '",
					updated				= 1
					WHERE album_id		= "' . mysql_real_escape_string($album_id) . '"
					AND relative_file	= BINARY "' . mysql_real_escape_string($relative_file) . '"
					LIMIT 1');
		}
	}
	
			
	// Image update
	$image = NJB_HOME_DIR . 'image/no_image.png';
	$flag = 0; // No image
	$misc_tracks = false;
	
	if		(is_file($dir . $cfg['image_front'] . '.jpg')) { $image = $dir . $cfg['image_front'] . '.jpg'; $flag = 3; /* Stored image */ }
	elseif	(is_file($dir . $cfg['image_front'] . '.png')) { $image = $dir . $cfg['image_front'] . '.png'; $flag = 3; /* Stored image */ }
	elseif ((strpos(strtolower($dir), strtolower($cfg['misc_tracks_folder'])) !== false) || (strpos(strtolower($dir), strtolower($cfg['misc_tracks_misc_artists_folder'])) !== false)){
		$image = NJB_HOME_DIR . 'image/misc_image.jpg';
		$flag = 3; /* Stored image */
		$misc_tracks = true;
	}
	elseif	($cfg['image_read_embedded']) {
		
		
		$getID3->analyze($file[0]);
		
		if (isset($ThisFileInfo['error']) == false &&
			isset($ThisFileInfo['comments']['picture'][0]['image_mime']) &&
			isset($ThisFileInfo['comments']['picture'][0]['data']) &&
			($ThisFileInfo['comments']['picture'][0]['image_mime'] == 'image/jpeg' || $ThisFileInfo['comments']['picture'][0]['image_mime'] == 'image/png')) {
				if ($ThisFileInfo['comments']['picture'][0]['image_mime'] == 'image/jpeg')	$image = NJB_HOME_DIR . 'tmp/' . $cfg['image_front'] . '.jpg';
				if ($ThisFileInfo['comments']['picture'][0]['image_mime'] == 'image/png')	$image = NJB_HOME_DIR . 'tmp/' . $cfg['image_front'] . '.png';
				if (file_put_contents($image, $ThisFileInfo['comments']['picture'][0]['data']) === false)
					message(__FILE__, __LINE__, 'error', '[b]Failed to wtite image to:[/b][br]' . $image);
				$flag = 0; // No image
		}
					
		unset($getID3);
	}
	$relative_dir = substr($dir, strlen($cfg['media_dir']));
	
	if		(is_file($dir . $cfg['image_front'] . '.jpg')) 	$image_front = $relative_dir . $cfg['image_front'] . '.jpg';
	elseif	(is_file($dir . $cfg['image_front'] . '.png'))	$image_front = $relative_dir . $cfg['image_front'] . '.png';
	elseif ($misc_tracks)									$image_front = $image;
	else													$image_front = '';
	
	if		(is_file($dir . $cfg['image_back'] . '.jpg'))	$image_back = $relative_dir . $cfg['image_back'] . '.jpg';
	elseif	(is_file($dir . $cfg['image_back'] . '.png'))	$image_back = $relative_dir . $cfg['image_back'] . '.png';
	else													$image_back = '';

	
	$filesize	= filesize($image);
	$filemtime	= filemtime($image);
	
	$query	= mysql_query('SELECT filesize, filemtime, image_id, flag FROM bitmap WHERE album_id = "' . mysql_real_escape_string($album_id) . '"');
	$bitmap	= mysql_fetch_assoc($query);
	
	if ($bitmap['filesize'] == $filesize && filemtimeCompare($bitmap['filemtime'], $filemtime)) {
		mysql_query('UPDATE bitmap SET
			image_front			= "' . mysql_real_escape_string($image_front) . '",
			image_back			= "' . mysql_real_escape_string($image_back) . '",
			updated				= 1
			WHERE album_id		= "' . mysql_real_escape_string($album_id) . '"
			LIMIT 1');
		$image_id = $bitmap['image_id'];
	}
	else {
		$imagesize = @getimagesize($image) or message(__FILE__, __LINE__, 'error', '[b]Failed to read image information from:[/b][br]' . $image);
		$image_id = (($flag == 3) ? $album_id : 'no_image');
		$image_id .= '_' . base_convert(NJB_IMAGE_SIZE * 100 + NJB_IMAGE_QUALITY, 10, 36) . base_convert($filemtime, 10, 36) . base_convert($filesize, 10, 36);
		
		if ($bitmap['filemtime'])
			mysql_query('UPDATE bitmap SET
				image				= "' . mysql_real_escape_string(resampleImage($image)) . '",
				filesize			= ' . (int) $filesize . ',
				filemtime			= ' . (int) $filemtime . ',
				flag				= ' . (int) $flag . ',
				image_front			= "' . mysql_real_escape_string($image_front) . '",
				image_back			= "' . mysql_real_escape_string($image_back) . '",
				image_front_width	= ' . ($flag == 3 ? $imagesize[0] : 0) . ',
				image_front_height	= ' . ($flag == 3 ? $imagesize[1] : 0) . ',
				image_id			= "' . mysql_real_escape_string($image_id) . '",
				updated				= 1
				WHERE album_id	= "' . mysql_real_escape_string($album_id) . '"
				LIMIT 1');
		else
			mysql_query('INSERT INTO bitmap (image, filesize, filemtime, flag, image_front, image_back, image_front_width, image_front_height, image_id, album_id, updated)
				VALUES ("' . mysql_real_escape_string(resampleImage($image)) . '",
				' . (int) $filesize . ',
				' . (int) $filemtime . ',
				' . (int) $flag . ',
				"' . mysql_real_escape_string($image_front) . '",
				"' . mysql_real_escape_string($image_back) . '",
				' . ($flag == 3 ? $imagesize[0] : 0) . ',
				' . ($flag == 3 ? $imagesize[1] : 0) . ',
				"' . mysql_real_escape_string($image_id) . '",
				"' . mysql_real_escape_string($album_id) . '",
				1)');
	}
	
	
	mysql_query('UPDATE album SET
		artist_alphabetic	= "' . mysql_real_escape_string($artist_alphabetic) . '",
		artist				= "' . mysql_real_escape_string($artist) . '",
		album				= "' . mysql_real_escape_string($album) . '",
		year				= ' . ((is_null($year)) ? 'NULL' : (int) $year) . ',
		month				= ' . ((is_null($month)) ? 'NULL' : (int) $month) . ',
		discs				= ' . (int) $discs . ',
		image_id			= "' . mysql_real_escape_string($image_id) . '",
		genre_id				= "' . mysql_real_escape_string($aGenre_id) .'",
		updated				= 1,
		album_dr			= ' . ((is_null($album_dr)) ? 'NULL' : (int) $album_dr) . '
		WHERE album_id		= "' . mysql_real_escape_string($album_id) . '"
		LIMIT 1');
	if (mysql_affected_rows($db) == 0)
		mysql_query('INSERT INTO album (artist_alphabetic, artist, album, year, month, genre_id, album_add_time, discs, image_id, album_id, updated, album_dr)
			VALUES (
			"' . mysql_real_escape_string($artist_alphabetic) . '",
			"' . mysql_real_escape_string($artist) . '",
			"' . mysql_real_escape_string($album) . '",
			' . ((is_null($year)) ? 'NULL' : (int) $year) . ',
			' . ((is_null($month)) ? 'NULL' : (int) $month) . ',
			' . (int) $aGenre_id . ',
			' . (int) $album_add_time . ',
			' . (int) $discs . ',
			"' . mysql_real_escape_string($image_id) . '",
			"' . mysql_real_escape_string($album_id) . '",
			1,
			' . ((is_null($album_dr)) ? 'NULL' : (int) $album_dr) . ')');
	// Close getID3				
	unset($getID3);
}




//  +------------------------------------------------------------------------+
//  | File info                                                              |
//  +------------------------------------------------------------------------+
function fileInfo() {
	global $cfg, $db, $dirsCounter, $filesCounter, $curFilesCounter, $curDirsCounter, $prevDirsCounter, $prevFilesCounter;
	
	$year = NULL;
	$dr = NULL;
	// Initialize getID3
	$getID3 = new getID3;
	//initial settings for getID3:
	include 'include/getID3init.inc.php';
	
	/* 
	// Force update all tracks on new getID3() or netjukebox update.php version. 
	$new_getid3_hash = hmacmd5($getID3->version(), file_get_contents(NJB_HOME_DIR . 'update.php'));
	if ($new_getid3_hash != $cfg['getid3_hash']) {
		mysql_query('UPDATE track SET filemtime = 0 WHERE 1');
		mysql_query('UPDATE server SET value = "' . mysql_real_escape_string($new_getid3_hash) . '" WHERE name = "getid3_hash" LIMIT 1');
	}
	 */
	
	$updated = false;
	$query = mysql_query('SELECT relative_file, filesize, filemtime, album_id FROM track WHERE updated ORDER BY relative_file');
	$filesCounter = mysql_num_rows($query);
	while ($track = mysql_fetch_assoc($query)) {
		++$curFilesCounter;
		$file = $cfg['media_dir'] . $track['relative_file'];
		
		if (is_file($file) == false)
			message(__FILE__, __LINE__, 'error', '[b]Failed to read file:[/b][br]' . $file . '[list][*]Update again[*]Check file permission[/list]');
		
		$filemtime = filemtime($file);
		$filesize = filesize($file);
		$force_filename_update = false;
		
		if ($filesize != $track['filesize'] || filemtimeCompare($filemtime, $track['filemtime']) == false || $force_filename_update) {
			
			if ($cfg['cli_update'] == false && ((microtime(true) - $cfg['timer']) * 1000) > $cfg['update_refresh_time'] && ($curFilesCounter/$filesCounter > ($prevFilesCounter/$filesCounter + 0.005))) {
				$prevFilesCounter = $curFilesCounter;
				
				mysql_query('update update_progress set 
				file_info = "<div class=\'out\'><div class=\'in\' style=\'width:' . html(floor($curFilesCounter/$filesCounter * 200)) . 'px\'></div></div> ' . html(floor($curFilesCounter/$filesCounter * 100)) . '%"');
				
				// echo '<script type="text/javascript"> document.getElementById(\'fileinfo\').innerHTML="<div class=\'out\'><div class=\'in\' style=\'width:' . $curFilesCounter/$filesCounter * 200 . 'px\'></div></div> ' . html(floor($curFilesCounter/$filesCounter * 100)) . '%";</script>' . "\n";
				
				// @ob_flush();
				// flush();
				
				$cfg['timer'] = microtime(true);
				$updated = true;
			}
			if ($cfg['cli_update'] && $cfg['cli_silent_update'] == false)
				echo $file . "\n";
					
						
			$ThisFileInfo = $getID3->analyze($file);
			getid3_lib::CopyTagsToComments($ThisFileInfo);
			//print $ThisFileInfo['id3v1']['genre'];
			$mime_type					= (isset($ThisFileInfo['mime_type'])) ? $ThisFileInfo['mime_type'] : 'application/octet-stream';
			$miliseconds				= (isset($ThisFileInfo['playtime_seconds'])) ? round($ThisFileInfo['playtime_seconds'] * 1000) : 0;
			$audio_bitrate				= 0;
			$audio_bits_per_sample		= 0;
			$audio_sample_rate			= 0;
			$audio_channels				= 0;
			$audio_lossless				= 0;
			$audio_compression_ratio	= 0;
			$audio_dataformat			= '';
			$audio_encoder 				= '';
			$audio_bitrate_mode			= '';
			$audio_profile				= '';
			$video_dataformat			= '';
			$video_codec				= '';
			$video_resolution_x			= 0;
			$video_resolution_y			= 0;
			$video_framerate			= 0;
			$track_id					= $track['album_id'] . '_' . fileId($file);
			$error						= (isset($ThisFileInfo['error'])) ? implode('<br>', $ThisFileInfo['error']) : '';
			
			if (isset($ThisFileInfo['comments']['albumartist'][0])) 
				$artist = $ThisFileInfo['comments']['albumartist'][0];
			elseif (isset($ThisFileInfo['comments']['band'][0]))
				$artist = $ThisFileInfo['comments']['band'][0];
			//elseif (isset($ThisFileInfo['ape']['comments']['albumartist'][0])) 
			//	$artist = $ThisFileInfo['ape']['comments']['albumartist'][0];
			else
				$artist = 'Unknown AlbumArtist';
			
			//if (isset($ThisFileInfo['comments']['artist'][1])) 
			//	$track_artist = $ThisFileInfo['comments']['artist'][1];
			if (isset($ThisFileInfo['comments']['artist'][0])) 
				$track_artist = $ThisFileInfo['comments']['artist'][0]; 
			/* elseif	(isset($ThisFileInfo['id3v2']['comments']['artist'][0]))
				$track_artist = $ThisFileInfo['id3v2']['comments']['artist'][0]; 
			elseif (isset($ThisFileInfo['ape']['comments']['artist'][0])) 
				$track_artist = $ThisFileInfo['ape']['comments']['artist'][0]; */
			else 
				$track_artist = 'Unknown TrackArtist';
			
			//if (isset($ThisFileInfo['comments']['title'][1])) 
			//	$title = $ThisFileInfo['comments']['title'][1];
			if (isset($ThisFileInfo['comments']['title'][0])) 
				$title = $ThisFileInfo['comments']['title'][0]; 
			/* elseif (isset($ThisFileInfo['id3v2']['comments']['title'][0])) 
				$title = $ThisFileInfo['id3v2']['comments']['title'][0];
			elseif(isset($ThisFileInfo['ape']['comments']['title'][0])) 
				$title = $ThisFileInfo['ape']['comments']['title'][0]; */
			else
				$title = 'Unknown Title';
			
			
			if (isset($ThisFileInfo['comments']['genre'][0]))
				$genre = $ThisFileInfo['comments']['genre'][0];
			/* elseif (isset($ThisFileInfo['id3v2']['comments']['genre'][0])) 
				$genre = $ThisFileInfo['id3v2']['comments']['genre'][0];
			elseif (isset($ThisFileInfo['ape']['comments']['genre'][0])) 
				$genre = $ThisFileInfo['ape']['comments']['genre'][0]; */
			else
				$genre = 'Unknown Genre';
			
			$a = array_values($ThisFileInfo['comments']['comment']);
			if (isset($a[0]))
				$comment = $a[0];
			/* elseif (isset($ThisFileInfo['id3v2']['comments']['comment'][0]))
				$comment = $ThisFileInfo['id3v2']['comments']['comment'][0];
			elseif (isset($ThisFileInfo['ape']['comments']['comment'][0])) 
				$comment = $ThisFileInfo['ape']['comments']['comment'][0]; */
			else 
				$comment = '';
			
			if (isset($ThisFileInfo['comments']['year'][0])) $year = $ThisFileInfo['comments']['year'][0];
			elseif (isset($ThisFileInfo['comments']['date'][0])) $year = $ThisFileInfo['comments']['date'][0];
			
			if (preg_match('#[1][9][0-9]{2}|[2][0-9]{3}#', $year, $match)) {
				$year	= $match[0];
			}
		
			if (isset($ThisFileInfo['comments']['dynamic range'][0])) $dr = $ThisFileInfo['comments']['dynamic range'][0];
			elseif (isset($ThisFileInfo['tags']['id3v2']['text']['DYNAMIC RANGE'])) $dr = $ThisFileInfo['tags']['id3v2']['text']['DYNAMIC RANGE'];
			
			
			/* if (isset($ThisFileInfo['comments']['date'][0])) $year = $ThisFileInfo['comments']['date'][0];
			elseif (isset($ThisFileInfo['comments']['year'][0])) $year = $ThisFileInfo['comments']['year'][0]; */

			if (isset($ThisFileInfo['audio']['dataformat'])) {
				$audio_dataformat = $ThisFileInfo['audio']['dataformat'];
				$audio_encoder = (isset($ThisFileInfo['audio']['encoder'])) ? $ThisFileInfo['audio']['encoder'] : 'Unknown encoder';
				
				if (isset($ThisFileInfo['mpc']['header']['profile']))			$audio_profile = $ThisFileInfo['mpc']['header']['profile'];
				if (isset($ThisFileInfo['aac']['header']['profile_text']))		$audio_profile = $ThisFileInfo['aac']['header']['profile_text'];
				
				if (empty($ThisFileInfo['audio']['lossless']) == false) {
					$audio_lossless = 1;
					if (empty($ThisFileInfo['audio']['compression_ratio']) == false) {
						if ($ThisFileInfo['audio']['compression_ratio'] == 1)
						$audio_profile = 'Lossless';
						else $audio_profile = 'Lossless compression';
					}
					else $audio_profile = 'Lossless';
				}
				
				if (isset($ThisFileInfo['audio']['compression_ratio']))			$audio_compression_ratio = $ThisFileInfo['audio']['compression_ratio'];
				if (isset($ThisFileInfo['audio']['bitrate_mode']))				$audio_bitrate_mode = $ThisFileInfo['audio']['bitrate_mode'];
				if (isset($ThisFileInfo['audio']['bitrate']))					$audio_bitrate = $ThisFileInfo['audio']['bitrate'];
				if (!$audio_profile)											$audio_profile = $audio_bitrate_mode . ' ' . round($audio_bitrate / 1000, 1) . '  kbps';
			
				$audio_bits_per_sample	= (isset($ThisFileInfo['audio']['bits_per_sample'])) ? $ThisFileInfo['audio']['bits_per_sample'] : 16;
				$audio_sample_rate		= (isset($ThisFileInfo['audio']['sample_rate'])) ? $ThisFileInfo['audio']['sample_rate'] : 44100;
				$audio_channels			= (isset($ThisFileInfo['audio']['channels'])) ? $ThisFileInfo['audio']['channels'] : 2;
				$audio_bitrate			= round($audio_bitrate); // integer in database					
			}
			if (isset($ThisFileInfo['video']['dataformat'])) {
				$video_dataformat = $ThisFileInfo['video']['dataformat'];
				$video_codec = (isset($ThisFileInfo['video']['codec'])) ? $ThisFileInfo['video']['codec'] : 'Unknown codec';
				
				if (isset($ThisFileInfo['video']['resolution_x']))		$video_resolution_x	= $ThisFileInfo['video']['resolution_x'];
				if (isset($ThisFileInfo['video']['resolution_y']))		$video_resolution_y	= $ThisFileInfo['video']['resolution_y'];
				if (isset($ThisFileInfo['video']['frame_rate']))		$video_framerate	= $ThisFileInfo['video']['frame_rate'] . ' fps';
			}
	
			mysql_query('UPDATE track SET
				mime_type					= "' . mysql_real_escape_string($mime_type) . '",
				filesize					= ' . (int) $filesize . ',
				filemtime					= ' . (int) $filemtime . ',
				miliseconds					= ' . (int) $miliseconds . ',
				audio_bitrate				= ' . (int) $audio_bitrate . ',
				audio_bits_per_sample		= ' . (int) $audio_bits_per_sample . ',
				audio_sample_rate			= ' . (int) $audio_sample_rate . ',
				audio_channels				= ' . (int) $audio_channels . ',
				audio_lossless				= ' . (int) $audio_lossless . ',
				audio_compression_ratio		= ' . (float) $audio_compression_ratio . ',			
				audio_dataformat			= "' . mysql_real_escape_string($audio_dataformat) . '",
				audio_encoder 				= "' . mysql_real_escape_string($audio_encoder) . '",
				audio_profile				= "' . mysql_real_escape_string($audio_profile) . '",
				video_dataformat			= "' . mysql_real_escape_string($video_dataformat) . '",
				video_codec					= "' . mysql_real_escape_string($video_codec) . '",
				video_resolution_x			= ' . (int) $video_resolution_x . ',
				video_resolution_y			= ' . (int) $video_resolution_y . ',
				video_framerate				= ' . (int) $video_framerate . ',
				error						= "' . mysql_real_escape_string($error) . '",
				track_id					= "' . mysql_real_escape_string($track_id) . '",
				genre			= "' . mysql_real_escape_string($genre) . '",
				title			= "' . mysql_real_escape_string($title) . '",
				artist			= "' . mysql_real_escape_string($track_artist) . '",
				comment			= "' . mysql_real_escape_string($comment) . '",
				track_artist			= "' . mysql_real_escape_string($track_artist) . '",
				year			= ' . ((is_null($year)) ? 'NULL' : (int) $year) . ',
				dr		= ' . ((is_null($dr)) ? 'NULL' : (int) $dr) . '
				WHERE relative_file 		= BINARY "' . mysql_real_escape_string($track['relative_file']) . '"');
			}
		if ($updated && ((microtime(true) - $cfg['timer']) * 1000) > 500) {
			echo '<script type="text/javascript">document.getElementById(\'fileinfo\').innerHTML=\'<img src="' . $cfg['img'] . 'small_animated_progress.gif" alt="" class="small">\';</script>' . "\n";
			@ob_flush();
			flush();
			$updated = false;
		}
	
		if ($cfg['name_source'] != 'tags') {
			
			$result = mysql_query('SELECT genre_id FROM genre WHERE genre="' . mysql_real_escape_string($genre) . '"');
			if (mysql_num_rows($result)==0) {	
				mysql_query('INSERT INTO genre (genre_id, genre)
							VALUES ("' . mysql_real_escape_string($lastGenre_id) . '",
									"' . mysql_real_escape_string($genre) . '")');
				$aGenre_id = $lastGenre_id;
				++$lastGenre_id;
				
			} else {
					$row=mysql_fetch_assoc($result);
					$genre_id=$row["genre_id"];
			}	
		}
	
	}
	// Close getID3				
	unset($getID3);
}




//  +------------------------------------------------------------------------+
//  | File identification                                                    |
//  +------------------------------------------------------------------------+
function fileId($file) {
	$filesize = filesize($file);
	
	if ($filesize > 5120) {
		$filehandle	= @fopen($file, 'rb') or message(__FILE__, __LINE__, 'error', '[b]Failed to open file:[/b][br]' . $file . '[list][*]Check file permission[/list]');
		fseek($filehandle, round(0.5 * $filesize - 2560 - 1));
		$data = fread($filehandle, 5120);
		$data .= $filesize;
		fclose($filehandle);
	}
	else
		$data = @file_get_contents($file) or message(__FILE__, __LINE__, 'error', '[b]Failed to open file:[/b][br]' . $file . '[list][*]Check file permission[/list]');
	
	$crc32 = dechex(crc32($data));
	return str_pad($crc32, 8, '0', STR_PAD_LEFT);
}




//  +------------------------------------------------------------------------+
//  | Database cleanup                                                       |
//  +------------------------------------------------------------------------+
function databaseCleanup() {
	global $cfg, $db;
	// Clean up database
	mysql_query('DELETE FROM session WHERE idle_time = 0 AND create_time < ' . (int) (time() - 600));
	mysql_query('DELETE FROM random WHERE create_time < ' . (int) (time() - 3600));
	mysql_query('DELETE FROM share_download WHERE expire_time < ' . (int) time());
	mysql_query('DELETE FROM share_stream WHERE expire_time < ' . (int) time());
	mysql_query('DELETE share_download
		FROM share_download LEFT JOIN album
		ON share_download.album_id = album.album_id
		WHERE album.album_id IS NULL');
	mysql_query('DELETE share_stream
		FROM share_stream LEFT JOIN album
		ON share_stream.album_id = album.album_id
		WHERE album.album_id IS NULL');
	mysql_query('DELETE counter
		FROM counter LEFT JOIN album
		ON counter.album_id = album.album_id
		WHERE album.album_id IS NULL');
	mysql_query('DELETE counter
		FROM counter LEFT JOIN user
		ON counter.user_id = user.user_id
		WHERE user.user_id IS NULL');
	mysql_query('DELETE FROM favoriteitem WHERE track_id NOT IN (SELECT track_id FROM track) AND stream_url = ""');
	
	// Delete unavailable files from cache
	cacheCleanup();
	
	// Optimize tables
	$list	= array();
	$query	= mysql_query('SHOW TABLES');
	while ($table = mysql_fetch_row($query))
		$list[] = $table[0];
	$list = implode(', ', $list);
	mysql_query('OPTIMIZE TABLE ' . $list);
}




//  +------------------------------------------------------------------------+
//  | Image update                                                           |
//  +------------------------------------------------------------------------+
function imageUpdate($flag) {
	global $cfg, $db;
	authenticate('access_admin');
	
	$size				= get('size');
	$artistSearch		= post('artist');
	$albumSearch		= post('album');
	$image_service_id	= (int) post('image_service_id');
	
	if (in_array($size, array('50', '100', '200'))) {
		mysql_query('UPDATE session
			SET thumbnail_size	= ' . (int) $size . '
			WHERE sid			= BINARY "' . mysql_real_escape_string($cfg['sid']) . '"');
	}
	else
		$size = $cfg['thumbnail_size'];
		
	if (isset($cfg['image_service_name'][$image_service_id]) == false)
		message(__FILE__, __LINE__, 'error', '[b]Unsupported input value for[/b][br]image_service_id');
	
	// flag 0 = No image
	// flag 1 = Skipped
	// flag 2 = Skipped not updated in this run
	// flag 3 = Stored image
	// flag 9 = Update one image by album_id, Needed for redirect to saveImage() (store as flag 1 or 3 in database)
	
	if ($flag == 2) {
		mysql_query('UPDATE bitmap SET flag = 2 WHERE flag = 1');
		$flag = 1;
	}
	if ($flag == 1) {
		$query = mysql_query('SELECT album.artist, album.album, album.album_id
			FROM album, bitmap
			WHERE bitmap.flag = 2
			AND bitmap.album_id = album.album_id
			ORDER BY album.artist_alphabetic, album.album');
	}
	elseif ($flag == 0) {
		$query = mysql_query('SELECT album.artist, album.album, album.album_id
			FROM album, bitmap
			WHERE bitmap.flag = 0
			AND bitmap.album_id = album.album_id
			ORDER BY album.artist_alphabetic, album.album');
	}
	elseif ($flag == 9 && $cfg['album_update_image']) {
		$album_id = getpost('album_id');
		$query = mysql_query('SELECT album.artist, album.artist_alphabetic, album.album, album.image_id, album.album_id,
			bitmap.flag, bitmap.image_front_width, bitmap.image_front_height
			FROM album, bitmap
			WHERE album.album_id = "' . mysql_real_escape_string($album_id) . '"
			AND bitmap.album_id = album.album_id');
	}
	else
		message(__FILE__, __LINE__, 'error', '[b]Error internet image update[/b][br]Unsupported flag set');
	
	
	$album = mysql_fetch_assoc($query);
	if ($album == '') {
		header('Location: ' . NJB_HOME_URL . 'config.php');
		exit();
	}
		
	if ($artistSearch == '' && $albumSearch == '') {
		// Remove (...) [...] {...} from the end
		$artistSearch	= preg_replace('#^(.+?)(?:\s*\(.+\)|\s*\[.+\]|\s*{.+})?$#', '$1', $album['artist']);
		$albumSearch	= preg_replace('#^(.+?)(?:\s*\(.+\)|\s*\[.+\]|\s*{.+})?$#', '$1', $album['album']);
	}
	
	$responce_url			= array();
	$responce_pixels		= array();
	$responce_resolution	= array();
	$responce_squire		= array();
	
	$url = $cfg['image_service_url'][$image_service_id];
	$url = str_replace('%artist', rawurlencode(iconv(NJB_DEFAULT_CHARSET, $cfg['image_service_charset'][$image_service_id], $artistSearch)), $url);
	$url = str_replace('%album', rawurlencode(iconv(NJB_DEFAULT_CHARSET, $cfg['image_service_charset'][$image_service_id], $albumSearch)), $url);
	
	if ($cfg['image_service_process'][$image_service_id] == 'amazon') {
		// Amazon web services
		if (function_exists('hash_hmac') == false)
		 	message(__FILE__, __LINE__, 'error', '[b]Missing hash_hmac function[/b][br]For the Amazone Web Service the hash_hmac function is required.');

		$url = str_replace('%awsaccesskeyid', rawurlencode($cfg['image_AWSAccessKeyId']), $url);
		$url = str_replace('%associatetag', rawurlencode($cfg['image_AWSAssociateTag'] ), $url);
		$url = str_replace('%timestamp', rawurlencode(gmdate('Y-m-d\TH:i:s\Z')), $url);
		
		$url_array = parse_url($url);
		
		// Sort on query key
		$query = $url_array['query'];
		$query = explode('&', $query);
		sort($query);
		$query = implode('&', $query);
		
		$signature = 'GET' . "\n";
		$signature .= $url_array['host'] . "\n";
		$signature .= $url_array['path'] . "\n";
		$signature .= $query;
		$signature = rawurlencode(base64_encode(hash_hmac('sha256', $signature, $cfg['image_AWSSecretAccessKey'], true)));
		
		// $url = $url_array['scheme'] . '://' . $url_array['host'] . $url_array['path'] . '?' . $query;
		$url .= '&Signature=' . $signature;
		$xml = @simplexml_load_file($url) or message(__FILE__, __LINE__, 'error', '[b]Failed to open XML file:[/b][br]' . $url);
				
		foreach ($xml->Items->Item as $item) {
			if (@$item->LargeImage->URL && @$item->LargeImage->Width && @$item->LargeImage->Height) {
				$responce_url[]			= $item->LargeImage->URL;
				$responce_pixels[]		= $item->LargeImage->Width * $item->LargeImage->Height;
				$responce_resolution[]	= $item->LargeImage->Width . ' x ' . $item->LargeImage->Height;
				$responce_squire[]		= ($item->LargeImage->Width/$item->LargeImage->Height > 0.95 && $item->LargeImage->Width/$item->LargeImage->Height < 1.05) ? true : false;
				
			}
		}
	}
	elseif ($cfg['image_service_process'][$image_service_id] == 'lastfm') {
		// Last.fm web services
		$url = str_replace('%api_key', rawurlencode($cfg['image_lastfm_api_key']), $url);
		$xml = @simplexml_load_file($url) or message(__FILE__, __LINE__, 'error', '[b]Failed to open XML file:[/b][br]' . $url);
			
		foreach ($xml->album->image as $image) {
			$imagesize = @getimagesize($image);
			$width = $imagesize[0];
			$height = $imagesize[1];

			$responce_url[]			= $image;
			$responce_pixels[]		= $width * $height;
			$responce_resolution[]	= $width . 'x' . $height;
			$responce_squire[]		= ($width/$height > 0.95 && $width/$height < 1.05) ? true : false;
		}
	}	
	else {
		// Regular expression
		$content = @file_get_contents($url) or message(__FILE__, __LINE__, 'error', '[b]Failed to open url:[/b][br]' . $url);
		
		if (preg_match_all($cfg['image_service_process'][$image_service_id], $content, $match)) {
			foreach ($match[1] as $key => $image) {
				if ($cfg['image_service_urldecode'][$image_service_id])
					$image = rawurldecode($image);
				$extension = substr(strrchr($image, '.'), 1);
				$extension = strtolower($extension);
				if (!in_array($extension, array('gif', 'bmp'))) {
					if (isset($match[2][$key]) && isset($match[3][$key])) {
						$width = $match[2][$key];
						$height = $match[3][$key];
					}
					else {
						$imagesize = @getimagesize($image);
						$width = $imagesize[0];
						$height = $imagesize[1];
					}
					$responce_url[]			= $image;
					$responce_pixels[]		= $width * $height;
					$responce_resolution[]	= $width . 'x' . $height;
					$responce_squire[]		= ($width/$height > 0.95 && $width/$height < 1.05) ? true : false;
				}
			}
		}
	}
	
	// squire images first:
	array_multisort($responce_squire, SORT_DESC, $responce_pixels, SORT_DESC, $responce_url, $responce_resolution);
		
	$colombs = floor((cookie('netjukebox_width') - 20) / ($size + 10));
	$max_images = count($responce_squire) + 2; // n + "no image available" + "upload"
	if (isset($album['flag']) && $album['flag'] == 3)
		$max_images += 1; // Current image
		
	if ($flag == 9) {
		$cfg['menu'] = 'media';
		// formattedNavigator
		$nav			= array();
		$nav['name'][]	= 'Media';
		$nav['url'][]	= 'index.php';
		$nav['name'][]	= $album['artist_alphabetic'];
		$nav['url'][]	= 'index.php?action=view2&amp;artist=' . rawurlencode($album['artist_alphabetic']);
		$nav['name'][]	= $album['album'];
		$nav['url'][]	= 'index.php?action=view3&amp;album_id=' . rawurlencode($album_id);
		$nav['name'][]	= 'Update image';
	}
	else {
		// formattedNavigator
		$nav			= array();
		$nav['name'][]	= 'Configuration';
		$nav['url'][]	= 'config.php';
		$nav['name'][]	= 'Update image';
	}
	
	require_once('include/header.inc.php');
?>
<form action="update.php" method="post">
		<input type="hidden" name="action" value="imageUpdate">
		<input type="hidden" name="flag" value="<?php echo $flag; ?>">
		<input type="hidden" name="album_id" value="<?php if (isset($album_id)) echo $album_id; ?>">
<table cellspacing="0" cellpadding="0" class="border">
<tr class="header">
	<td colspan="<?php echo $colombs + 2; ?>">
	<!-- begin table header -->
	<table width="100%" cellspacing="0" cellpadding="0">
	<tr class="header">
		<td class="space"></td>
		<td><?php echo html($album['artist']) . ' - ' . html($album['album']); ?></td>
		<td align="right">
			<!-- Brake image tag to prevent space -->
			<a href="update.php?action=imageUpdate<?php if (isset($album_id)) echo '&amp;album_id=' . $album_id; ?>&amp;flag=<?php echo $flag; ?>&amp;size=50"><img src="<?php echo $cfg['img']; ?>small_header_image50_<?php echo ($size == '50') ? 'on' : 'off'; ?>.png" alt="" class="small align"></a><a href="update.php?action=imageUpdate<?php if (isset($album_id)) echo '&amp;album_id=' . $album_id; ?>&amp;flag=<?php echo $flag; ?>&amp;size=100"><img src="<?php echo $cfg['img']; ?>small_header_image100_<?php echo ($size == '100') ? 'on' : 'off'; ?>.png" alt="" class="small align"></a><a href="update.php?action=imageUpdate<?php if (isset($album_id)) echo '&amp;album_id=' . $album_id; ?>&amp;flag=<?php echo $flag; ?>&amp;size=200"><img src="<?php echo $cfg['img']; ?>small_header_image200_<?php echo ($size == '200') ? 'on' : 'off'; ?>.png" alt="" class="small align"></a>
		</td>
	</tr>
	</table>
	<!-- end table header -->
	</td>
</tr>
<tr class="line"><td colspan="<?php echo $colombs + 2; ?>"></td></tr>
<tr class="odd smallspace"><td colspan="<?php echo $colombs + 2; ?>"></td></tr>
<?php
	for ($i=0; $i < ceil($max_images / $colombs); $i++) {
		$class = ($i & 1) ? 'even' : 'odd';
?>
<tr class="<?php echo $class; ?>">
	<td class="smallspace">&nbsp;</td>
<?php
		for ($j=1; $j <= $colombs; $j++) { ?>
	<td width="<?php echo $size + 10; ?>" height="<?php echo $size + 10; ?>" align="center">
	<span id="image<?php echo $i * $colombs + $j; ?>"><img src="image/transparent.gif" alt="" width="<?php echo $size; ?>" height="<?php echo $size; ?>" class="align"></span>
	</td>
<?php
		} ?>
	<td class="smallspace">&nbsp;</td>
</tr>
<?php
	} ?>
<tr class="<?php echo $class; ?> smallspace"><td colspan="<?php echo $colombs + 2; ?>"></td></tr>
<tr class="line"><td colspan="<?php echo $colombs + 2; ?>"></td></tr>
<tr class="footer">
	<td colspan="<?php echo $colombs + 2; ?>">
	<!-- begin table footer -->
	<table cellspacing="0" cellpadding="0">
	<tr class="footer smallspace"><td colspan="6"></td></tr>
	<tr class="footer">
		<td class="space"></td>
		<td>Artist:</td>
		<td class="space"></td>
		<td><input type="text" name="artist" value="<?php echo html($artistSearch); ?>" class="edit"></td>
		<td class="textspace"></td>
		<td>		
		<select name="image_service_id">
<?php
	foreach ($cfg['image_service_name'] as $key => $value)
		echo "\t\t" . '<option value="' . $key . '"' . (($image_service_id == $key) ? ' selected' : ''). '>' . html($value) . '</option>' . "\n"; ?>
		</select>		
		</td>
	</tr>
	<tr class="footer smallspace"><td colspan="6"></td></tr>
	<tr class="footer">
		<td></td>		
		<td>Album:</td>
		<td></td>
		<td><input type="text" name="album" value="<?php echo html($albumSearch); ?>" class="edit"></td>
		<td></td>
		<td><input type="image" src="<?php echo $cfg['img']; ?>button_small_search.png"></td>
	</tr>
	<tr class="footer smallspace">
		<td colspan="10"></td>
	</tr>
	</table>
	<!-- end table footer -->
	</td>
</tr>
</table>
</form>
<?php
	$cfg['footer'] = 'dynamic';
	require('include/footer.inc.php');

	$i = 0;
	if (isset($album['flag']) && $album['flag'] == 3) {
		// Show current image
		$i++;
		$mouseover = ' onMouseOver="return overlib(\\\'' . $album['image_front_width'] . ' x ' . $album['image_front_height'] . '\\\', CAPTION, \\\'Current image:&nbsp;\\\');" onMouseOut="return nd();"';
		$url = '<a href="index.php?action=view3&amp;album_id=' . rawurlencode($album_id) . '"' . $mouseover . '><img src="image.php?image_id=' . $album['image_id'] . '" alt="" width="' . $size . '" height="' . $size . '" class="align"><\/a>';
		echo '<script type="text/javascript">document.getElementById(\'image' . $i . '\').innerHTML=\'' . $url . '\';</script>' . "\n";
	}
	
	foreach ($responce_url as $key => $image) {
		$i++;
		$mouseover = ' onMouseOver="return overlib(\\\'' . html($responce_resolution[$key]) . '\\\');" onMouseOut="return nd();"';
		$url = '<a href="update.php?action=saveImage&flag=' . $flag . '&amp;album_id=' . $album['album_id'] . '&amp;image=' . rawurlencode($image) . '&amp;sign=' . $cfg['sign'] . '"' . $mouseover . '><img src="image.php?image=' . rawurlencode($image) . '" alt="" width="' . $size . '" height="' . $size . '" class="align"><\/a>';
		echo '<script type="text/javascript">document.getElementById(\'image' . $i . '\').innerHTML=\'' . $url . '\';</script>' . "\n";
	}
	
	$i++;
	$mouseover = ' onMouseOver="return overlib(\\\'No image\\\');" onMouseOut="return nd();"';
	$url = '<a href="update.php?action=saveImage&amp;flag=' . $flag . '&amp;album_id=' . $album['album_id'] . '&amp;image=noImage&amp;sign=' . $cfg['sign'] . '"' . $mouseover . '><img src="image/no_image.png" alt="" width="' . $size . '" height="' . $size . '" class="align"><\/a>';
	echo '<script type="text/javascript">document.getElementById(\'image' . $i . '\').innerHTML=\'' . $url . '\';</script>' . "\n";
	$i++;
	
	$mouseover = ' onMouseOver="return overlib(\\\'Upload\\\');" onMouseOut="return nd();"';
	$url = '<a href="update.php?action=selectImageUpload&amp;flag=' . $flag . '&amp;album_id=' . $album['album_id'] . '"' . $mouseover . '><img src="skin/' . rawurlencode($cfg['skin']) . '/img/large_upload.png" alt="" width="' . $size . '" height="' . $size . '" class="align"><\/a>';
	echo '<script type="text/javascript">document.getElementById(\'image' . $i . '\').innerHTML=\'' . $url . '\';</script>' . "\n";
	
	$cfg['footer'] = 'close';
	require('include/footer.inc.php');
}




//  +------------------------------------------------------------------------+
//  | Save image                                                             |
//  +------------------------------------------------------------------------+
function saveImage($flag_flow) {
	global $cfg, $db;
	authenticate('access_admin', false, true);
	
	$source = get('image');
	$album_id = get('album_id');
	
	$query		= mysql_query('SELECT relative_file FROM track WHERE album_id = "' . mysql_real_escape_string($album_id) . '"');
	$track		= mysql_fetch_assoc($query);
	$image_dir	= $cfg['media_dir'] . $track['relative_file'];
	$image_dir	= substr($image_dir, 0, strrpos($image_dir, '/') + 1);
	
	if ($track == false)
		message(__FILE__, __LINE__, 'error', '[b]Error[/b][br]album_id not found in database');
	
	if ($source == 'noImage') {
		$image = NJB_HOME_DIR . 'image/no_image.png';
		if (is_file($image_dir . $cfg['image_front'] . '.jpg') && @unlink($image_dir . $cfg['image_front'] . '.jpg') == false)
			message(__FILE__, __LINE__, 'error', '[b]Failed to delete file:[/b][br]' . $image_dir . $cfg['image_front'] . '.jpg');
		if (is_file($image_dir . $cfg['image_front'] . '.png') && @unlink($image_dir . $cfg['image_front'] . '.png') == false)
			message(__FILE__, __LINE__, 'error', '[b]Failed to delete file:[/b][br]' . $image_dir . $cfg['image_front'] . '.png');
		
		$flag = 1; // Skipped (or Delete)		
	}
	else {
		$imagesize = @getimagesize($source) or message(__FILE__, __LINE__, 'error', '[b]Save image error[/b][br]Unsupported file.');
		if ($imagesize[2] == IMAGETYPE_JPEG) {
			$image = $image_dir . $cfg['image_front'] . '.jpg';
			$delete = $image_dir . $cfg['image_front'] . '.png';
		}
		elseif ($imagesize[2] == IMAGETYPE_PNG) {
			$image = $image_dir . $cfg['image_front'] . '.png';
			$delete = $image_dir . $cfg['image_front'] . '.jpg';
		}
		else
			message(__FILE__, __LINE__, 'error', '[b]Save image error[/b][br]Unsupported file.');

		if (copy($source, $image) == false)
			message(__FILE__, __LINE__, 'error', '[b]Failed to copy[/b][br]from: ' . $source . '[br]to: ' . $image);
		if (is_file($delete) && @unlink($delete) == false)
			message(__FILE__, __LINE__, 'error', '[b]Failed to delete file:[/b][br]' . $delete);
		
		$flag = 3; // Stored image
	}
	
	$filemtime	= filemtime($image);
	$filesize	= filesize($image);
	$imagesize	= @getimagesize($image) or message(__FILE__, __LINE__, 'error', '[b]Failed to read image information from:[/b][br]' . $image);
	$image_id	= (($flag == 3) ? $album_id : 'no_image');
	$image_id	.= '_' . base_convert(NJB_IMAGE_SIZE * 100 + NJB_IMAGE_QUALITY, 10, 36) . base_convert($filemtime, 10, 36) . base_convert($filesize, 10, 36); 
	 
	$relative_image = substr($image, strlen($cfg['media_dir']));
	mysql_query('UPDATE bitmap SET
		image				= "' . mysql_real_escape_string(resampleImage($image)) . '",
		filesize			= ' . (int) $filesize . ',
		filemtime			= ' . (int) $filemtime . ',
		flag				= ' . (int) $flag . ',
		image_front			= "' . ($flag == 3 ? mysql_real_escape_string($relative_image) : '') . '",
		image_front_width	= ' . ($flag == 3 ? $imagesize[0] : 0) . ',
		image_front_height	= ' . ($flag == 3 ? $imagesize[1] : 0) . ',
		image_id			= "' . mysql_real_escape_string($image_id) . '"
		WHERE album_id		= "' . mysql_real_escape_string($album_id) . '"');
		
	mysql_query('UPDATE album SET
		image_id			= "' . mysql_real_escape_string($image_id) . '"
		WHERE album_id		= "' . mysql_real_escape_string($album_id) . '"');
	
	if ($flag_flow == 9) {
		header('Location: ' . NJB_HOME_URL . 'index.php?action=view3&album_id=' . $album_id);
		exit();
	}
	else
		imageUpdate($flag_flow);
}




//  +------------------------------------------------------------------------+
//  | Select image upload                                                    |
//  +------------------------------------------------------------------------+
function selectImageUpload($flag) {
	global $cfg, $db;
	authenticate('access_admin');
	
	$album_id = get('album_id');
	
	$query = mysql_query('SELECT artist, artist_alphabetic, album, album_id
		FROM album
		WHERE album_id = "' . mysql_real_escape_string($album_id) . '"');
	$album = mysql_fetch_assoc($query);
	
	if ($album == false)
		message(__FILE__, __LINE__, 'error', '[b]Error[/b][br]album_id not found in database');
	
	if ($flag == 0 || $flag == 1) {
		$cancel = 'update.php?action=imageUpdate&amp;flag=' . rawurlencode($flag);
		// formattedNavigator
		$nav			= array();
		$nav['name'][]	= 'Configuration';
		$nav['url'][]	= 'config.php';
		$nav['name'][]	= 'Update image';
		$nav['url'][]	= 'update.php?action=imageUpdate&amp;flag=' . rawurlencode($flag);
		$nav['name'][]	= 'Upload';
	}
	elseif ($flag == 9 && $cfg['album_update_image']) {
		$cfg['menu'] = 'media';
		$cancel = 'index.php?action=view3&amp;album_id=' . rawurlencode($album_id);
		// formattedNavigator
		$nav			= array();
		$nav['name'][]	= 'Media';
		$nav['url'][]	= 'index.php';
		$nav['name'][]	= $album['artist_alphabetic'];
		$nav['url'][]	= 'index.php?action=view2&amp;artist=' . rawurlencode($album['artist_alphabetic']);
		$nav['name'][]	= $album['album'];
		$nav['url'][]	= 'index.php?action=view3&amp;album_id=' . rawurlencode($album_id);
		$nav['name'][]	= 'Update image';
		$nav['url'][]	= 'update.php?action=imageUpdate&amp;flag=9&amp;album_id=' . rawurlencode($album_id);
		$nav['name'][]	= 'Upload';
	}
	else
		message(__FILE__, __LINE__, 'error', '[b]Error internet image update[/b][br]Unsupported flag set');
	
	require_once('include/header.inc.php');
?>
<form action="update.php" method="post" enctype="multipart/form-data">
		<input type="hidden" name="action" value="imageUpload">
		<input type="hidden" name="flag" value="<?php echo $flag; ?>">
		<input type="hidden" name="album_id" value="<?php echo html($album_id); ?>">
		<input type="hidden" name="sign" value="<?php echo html($cfg['sign']); ?>">
<table cellspacing="0" cellpadding="0" class="border">
<tr class="header">
	<td></td>
	<td colspan="3">Upload</td>
	<td></td>
</tr>
<tr class="odd">
	<td class="space"></td>
	<td>Front image:</td>
	<td class="textspace"></td>
	<td><input type="file" name="image_front"></td>
	<td class="space"></td>
</tr>
<tr class="even">
	<td class="space"></td>
	<td>Back image:</td>
	<td class="textspace"></td>
	<td><input type="file" name="image_back"></td>
	<td class="space"></td>
</tr>
</table>
<br>
<input type="image" src="<?php echo $cfg['img']; ?>button_upload.png" class="space">&nbsp;
<a href="<?php echo $cancel; ?>"><img src="<?php echo $cfg['img']; ?>button_cancel.png" alt="" class="align"></a>
</form>
<?php
	require_once('include/footer.inc.php');
}




//  +------------------------------------------------------------------------+
//  | Image upload                                                           |
//  +------------------------------------------------------------------------+
function imageUpload($flag_flow) {
	global $cfg, $db;
	authenticate('access_admin', false, true);
	
	if (ini_get('file_uploads') == false)
		message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]File uploads disabled in the php.ini.');
	
	if ($_FILES['image_front']['error'] == UPLOAD_ERR_NO_FILE && $_FILES['image_back']['error'] == UPLOAD_ERR_NO_FILE)
		message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]There is no file uploaded');
	
	if ($_FILES['image_front']['error'] != UPLOAD_ERR_OK && $_FILES['image_front']['error'] != UPLOAD_ERR_NO_FILE) {
		if ($_FILES['image_front']['error'] == UPLOAD_ERR_INI_SIZE)			message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]The file is larger than the value set in php.ini for upload_max_file');
		elseif ($_FILES['image_front']['error'] == UPLOAD_ERR_PARTIAL)		message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]The file is not fully uploaded');
		elseif ($_FILES['image_front']['error'] == UPLOAD_ERR_NO_TMP_DIR)	message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]PHP, the directory for the temporary file not found');
		elseif ($_FILES['image_front']['error'] == UPLOAD_ERR_CANT_WRITE)	message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]PHP could not write the temporary file');
		else																message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]Error code: ' . $_FILES['image_front']['error']);
	}
	
	if ($_FILES['image_back']['error'] != UPLOAD_ERR_OK && $_FILES['image_back']['error'] != UPLOAD_ERR_NO_FILE) {
		if ($_FILES['image_back']['error'] == UPLOAD_ERR_INI_SIZE)			message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]The file is larger than the value set in php.ini for upload_max_file');
		elseif ($_FILES['image_back']['error'] == UPLOAD_ERR_PARTIAL)		message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]The file is not fully uploaded');
		elseif ($_FILES['image_back']['error'] == UPLOAD_ERR_NO_TMP_DIR)	message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]PHP, the directory for the temporary file not found');
		elseif ($_FILES['image_back']['error'] == UPLOAD_ERR_CANT_WRITE)	message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]PHP could not write the temporary file');
		else																message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]Error code: ' . $_FILES['image_back']['error']);
	}
	
	$album_id	= post('album_id');
	$query		= mysql_query('SELECT relative_file FROM track WHERE album_id = "' . mysql_real_escape_string($album_id) . '"');
	$track		= mysql_fetch_assoc($query);
	$image_dir	= $cfg['media_dir'] . $track['relative_file'];
	$image_dir	= substr($image_dir, 0, strrpos($image_dir, '/') + 1);
	
	if ($track == false)
		message(__FILE__, __LINE__, 'error', '[b]Error[/b][br]album_id not found in database');
	
	if ($_FILES['image_front']['error'] == UPLOAD_ERR_OK)
		{
		$imagesize = @getimagesize($_FILES['image_front']['tmp_name']) or message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]Unsupported file.');
		if ($imagesize[2] == IMAGETYPE_JPEG) {
			$image = $image_dir . $cfg['image_front'] . '.jpg';
			$delete = $image_dir . $cfg['image_front'] . '.png';
		}
		elseif ($imagesize[2] == IMAGETYPE_PNG) {
			$image = $image_dir . $cfg['image_front'] . '.png';
			$delete = $image_dir . $cfg['image_front'] . '.jpg';
		}
		else
			message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]Unsupported file.');
		
		if (copy($_FILES['image_front']['tmp_name'], $image) == false)
			message(__FILE__, __LINE__, 'error', '[b]Failed to copy[/b][br]from: ' . $_FILES['image_front']['tmp_name'] . '[br]to: ' . $image);
		if (is_file($delete) && @unlink($delete) == false)
			message(__FILE__, __LINE__, 'error', '[b]Failed to delete file:[/b][br]' . $delete);
		
		$flag		= 3; // stored
		$filemtime	= filemtime($image);
		$filesize	= filesize($image);
		$image_id	= $album_id . '_' . base_convert(NJB_IMAGE_SIZE * 100 + NJB_IMAGE_QUALITY, 10, 36) . base_convert($filemtime, 10, 36) . base_convert($filesize, 10, 36);
				
		$relative_image = substr($image, strlen($cfg['media_dir']));
		mysql_query('UPDATE bitmap SET
			image				= "' . mysql_real_escape_string(resampleImage($image)) . '",
			filesize			= ' . (int) $filesize . ',
			filemtime			= ' . (int) $filemtime . ',
			flag				= ' . (int) $flag . ',
			image_front			= "' . mysql_real_escape_string($relative_image) . '",
			image_front_width	= ' . (int) $imagesize[0] . ',
			image_front_height	= ' . (int) $imagesize[1] . ',
			image_id			= "' . mysql_real_escape_string($image_id) . '"
			WHERE album_id		= "' . mysql_real_escape_string($album_id) . '"');
		
		mysql_query('UPDATE album SET
			image_id			= "' . mysql_real_escape_string($image_id) . '"
			WHERE album_id		= "' . mysql_real_escape_string($album_id) . '"');		
	}
	
	if ($_FILES['image_back']['error'] == UPLOAD_ERR_OK) {
		$imagesize = @getimagesize($_FILES['image_back']['tmp_name']) or message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]Unsupported file.');
		if ($imagesize[2] == IMAGETYPE_JPEG) {
			$image = $image_dir . $cfg['image_back'] . '.jpg';
			$delete = $image_dir . $cfg['image_back'] . '.png';
		}
		elseif ($imagesize[2] == IMAGETYPE_PNG) {
			$image = $image_dir . $cfg['image_back'] . '.png';
			$delete = $image_dir . $cfg['image_back'] . '.jpg';
		}
		else message(__FILE__, __LINE__, 'error', '[b]Upload error[/b][br]Unsupported file.');
		
		if (copy($_FILES['image_back']['tmp_name'], $image) == false)
			message(__FILE__, __LINE__, 'error', '[b]Failed to copy[/b][br]from: ' . $_FILES['image_back']['tmp_name'] . '[br]to: ' . $image);
		if (is_file($delete) && @unlink($delete) == false)
			message(__FILE__, __LINE__, 'error', '[b]Failed to delete file:[/b][br]' . $delete);
		
		$relative_image = substr($image, strlen($cfg['media_dir']));
		mysql_query('UPDATE bitmap SET
			image_back			= "' . mysql_real_escape_string($relative_image) . '"
			WHERE album_id		= "' . mysql_real_escape_string($album_id) . '"');
	}
	
	if ($flag_flow == 9) {
		header('Location: ' . NJB_HOME_URL . 'index.php?action=view3&album_id=' . $album_id);
		exit();
	}
	else
		imageUpdate($flag_flow);
}




//  +------------------------------------------------------------------------+
//  | Resample image                                                         |
//  +------------------------------------------------------------------------+
Function resampleImage($image, $size = NJB_IMAGE_SIZE) {
	$extension = strtolower(substr(strrchr($image, '.'), 1));
		
	if		($extension == 'jpg')	$src_image = @imageCreateFromJpeg($image)	or message(__FILE__, __LINE__, 'error', '[b]Failed to resample image:[/b][br]' . $image);
	elseif	($extension == 'png')	$src_image = @imageCreateFromPng($image)	or message(__FILE__, __LINE__, 'error', '[b]Failed to resample image:[/b][br]' . $image);
	else																		message(__FILE__, __LINE__, 'error', '[b]Failed to resample image:[/b][br]Unsupported extension.');
	
	if ($extension == 'jpg' && imageSX($src_image) == $size && imageSY($src_image) == $size) {
		$data = @file_get_contents($image) or message(__FILE__, __LINE__, 'error', '[b]Failed to open file:[/b][br]' . $image);
	}
	elseif (imageSY($src_image) / imageSX($src_image) <= 1) {
		// Crops from left and right to get a squire image.
		$sourceWidth		= imageSY($src_image);
		$sourceHeight		= imageSY($src_image);
		$sourceX			= round((imageSX($src_image) - imageSY($src_image)) / 2);
		$sourceY			= 0;
	}
	else {
		// Crops from top and bottom to get a squire image.
		$sourceWidth		= imageSX($src_image);
		$sourceHeight		= imageSX($src_image);
		$sourceX			= 0;
		$sourceY			= round((imageSY($src_image) - imageSX($src_image)) / 2);
	}
	if (isset($sourceWidth)) {
		$dst_image = ImageCreateTrueColor($size, $size);
		imageCopyResampled($dst_image, $src_image, 0, 0, $sourceX, $sourceY, $size, $size, $sourceWidth, $sourceHeight);
		ob_start();
		imageJpeg($dst_image, NULL, NJB_IMAGE_QUALITY); 
		$data = ob_get_contents();
		ob_end_clean();
		imageDestroy($dst_image);
	}
	
	imageDestroy($src_image);
	return $data;
}



//  +------------------------------------------------------------------------+
//  | Resample image                                                         |
//  +------------------------------------------------------------------------+
Function noResampleImage($image, $size = NJB_IMAGE_SIZE) {
	$extension = strtolower(substr(strrchr($image, '.'), 1));
		
	if		($extension == 'jpg')	$src_image = @imageCreateFromJpeg($image)	or message(__FILE__, __LINE__, 'error', '[b]Failed to resample image:[/b][br]' . $image);
	elseif	($extension == 'png')	$src_image = @imageCreateFromPng($image)	or message(__FILE__, __LINE__, 'error', '[b]Failed to resample image:[/b][br]' . $image);
	else																		message(__FILE__, __LINE__, 'error', '[b]Failed to resample image:[/b][br]Unsupported extension.');
	
	$data = @file_get_contents($image) or message(__FILE__, __LINE__, 'error', '[b]Failed to open file:[/b][br]' . $image);
	
	return $data;
}
?>
