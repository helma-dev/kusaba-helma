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
 * Manage panel frameset
 *
 * Tells the browser to load the menu and main page
 *
 * @package kusaba
 */
// ========================================

	// not really needed, we could figure out the template dir
	//$preconfig_db_unnecessary = true;
	//require 'config.php';
	
	// get the template dir
	$root = dirname(__FILE__);
	$headMetaPath = $root.'/dwoo/templates/includes/headMeta.html';
	
	// they expire naturally
	//header("Expires: Mon, 1 Jan 2030 05:00:00 GMT");
	
	// preload to cache bust
	$preload = array(
		'/custom/css/common.css',
		'/custom/css/manage.css',
		'/custom/js/manage.js'
	);
	
	$preloadHtml = '<script type="text/plain" src="'.
		implode('"></script><script type="text/plain" src="', $preload).
		'"></script>';
?>
<!doctype html>
<html lang="en">
<head>
	<?php readfile($headMetaPath); ?>
	<meta name="robots" content="noindex">
	
	<title>Manage Boards</title>
	
	<link rel="shortcut icon" href="/favicon.ico">
	<link rel="stylesheet" href="/custom/css/frame.css">
</head>
<body>
	<div class="frame-wrapper">
		<div class="frame-left">
			<iframe src="manage_menu.php" frameborder="0" name="manage_menu"></iframe>
		</div>
		<div class="frame-right">
			<iframe src="manage_page.php" frameborder="0" name="manage_page"></iframe>
		</div>
	</div>
	
	<?php echo $preloadHtml; ?>
</body>
</html>
