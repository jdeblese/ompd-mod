<?php
//  +------------------------------------------------------------------------+
//  | O!MPD, Copyright © 2015 Artur Sierzant		                         |
//  | http://www.ompd.pl           		                                     |
//  |                                                                        |
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


error_reporting(-1);
ini_set("display_errors", 1);
require_once('include/initialize.inc.php');


global $cfg, $db;

$offset = is_numeric($_POST['offset']) ? $_POST['offset'] : die();
$postnumbers = is_numeric($_POST['number']) ? $_POST['number'] : die();


$run = mysql_query("SELECT * FROM album WHERE 1 LIMIT ".$postnumbers." OFFSET ".$offset);


while($row = mysql_fetch_array($run)) {
	
	draw_tile(100, $row);
	
	//$content = $row['album'];
	
	//echo '<h1><a href="'.$row['artist'].'">'.$row['artist'].'</a></h1><hr />';
	//echo '<p>'.$content.'...</p><hr />';

};

?>