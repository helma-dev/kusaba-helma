<?php
/*
 * This file is part of kusaba.
 *
 * kusaba is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * kusaba is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * kusaba; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */
/**
 * Manage menu
 *
 * Loaded when a user visits manage.php
 *
 * @package kusaba
 */

session_start();

require 'config.php';
require KU_ROOTDIR . 'lib/dwoo.php';
require KU_ROOTDIR . 'inc/functions.php';
require KU_ROOTDIR . 'inc/classes/manage.class.php';

// no need for styles
//$dwoo_data->assign('styles', explode(':', KU_MENUSTYLES));

$manage_class = new Manage();

// prepare user object
$user = array(
	'isValid' => $manage_class->ValidateSession(true),
	'username' => '',
	'password' => '',
	'isAdmin' => false,
	'isMod' => false,
	'isJanitor' => false,
	'reportCount' => 0,
	'boards' => array()
);

// if user is valid
if ($user['isValid']) {
	// username and pass
	$user['username'] = $_SESSION['manageusername'];
	$user['password'] = md5_encrypt($_SESSION['manageusername'], KU_RANDOMSEED);
	
	// set rights
	if ($manage_class->CurrentUserIsAdministrator()) {
		$user['isAdmin'] = true;
	}
	else if ($manage_class->CurrentUserIsModerator()) {
		$user['isMod'] = true;
	}
	else {
		$user['isJanitor'] = true;
	}
	
	// get report count
	if ($user['isAdmin'] || $user['isMod']) {
		$reportCount = $tc_db->GetAll('SELECT HIGH_PRIORITY COUNT(`id`) FROM `reports` WHERE `cleared`="0"');
		$currentUser['reportCount'] = $reportCount[0][0];
	}
	
	// get boards
	/*
		it is suppose to work for mods as well, but since the sql query is very
		inefficient and puzzling, i removed it for mods
	*/
	if ($user['isAdmin']) {
		$boardList = $tc_db->GetAll('SELECT HIGH_PRIORITY `name` FROM `boards` ORDER BY `name`');
		
		foreach ($boardList as $board) {
			$user['boards'][] = $board['name'];
		}
	}
	
	/*
	$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` ORDER BY `name`");
	$i = 0;
	if ($manage_class->CurrentUserIsAdministrator()) {
		$tpl_links .= '
			<h2 class="toggle toggle-icon" data-target="#section-mboards">All Boards</h2>
			<ul id="section-mboards" class="list" hidden>
		';
		
		foreach ($resultsboard as $lineboard) {
			$board = $lineboard['name'];
			$i++;
			$tpl_links .= '<li><a class="text-bold" href="'.$board.'">/'.$board.'/</a></li>';
		}
	}
	else {
		$tpl_links .= '
			<h2 class="toggle toggle-icon" data-target="#section-mboards">Moderating Boards</h2>
			<ul id="section-mboards" class="list" hidden>
		';
		
		foreach ($resultsboard as $lineboard) {
			$board = $lineboard['name'];
			if ($manage_class->CurrentUserIsModeratorOfBoard($board, $_SESSION['manageusername'])) {
			$i++;
			$tpl_links .= '<li><a class="text-bold" href="'.$board.'">/'.$board.'/</a></li>';
			}
		}
	}
	$tpl_links .= '<li><span>'.$i.' Board(s)</span></li></ul>';
	*/
}

$dwoo_data->assign('user', $user);
$dwoo->output(KU_TEMPLATEDIR.'/manage_menu.tpl', $dwoo_data);
