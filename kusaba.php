<?php
	// already installed, thanks
	/*if (file_exists("install.php")) {
		die('You are seeing this message because either you haven\'t ran the install file yet, and can do so <a href="install.php">here</a>, or already have, and <strong>must delete it</strong>.');
	}*/
	
	// check if user is requesting info
	$isInfo = isset($_GET['info']);
	
	// if it is not info page, we don't need db
	/*if (!$isInfo) {
		$preconfig_db_unnecessary = true;
	}*/
	
	// take in config
	require_once 'config.php';
	
	// menu is always dynamic... hence
	//$menufile = (KU_STATICMENU) ? 'menu.html' : 'menu.php';
	
	// they expire naturally
	//header("Expires: Mon, 1 Jan 2030 05:00:00 GMT");
	
	// headers template path
	$headMetaPath = KU_TEMPLATEDIR.'/includes/headMeta.html';
?>
<!doctype html>
<html>
<head>
	<?php readfile($headMetaPath); ?>
	
	<title><?php echo KU_NAME; ?></title>
	
	<link rel="shortcut icon" href="/favicon.ico">
	
	<link rel="stylesheet" href="/custom/css/common.css">
	
	<?php if ($isInfo): ?>
		<style>
			body {text-align:center; background-color:#eef2ff; color:#000; padding:20px 0}
			body > h1 {margin:0; font-weight:400; color:#af0a0f}
			body > table {margin:20px auto}
			body > table td {border:1px solid #b7c5d9; padding:5px 10px}
			body > a {color:#34345c; text-decoration:none}
			body > a:hover {color:#d00}
		</style>
	<?php else: ?>
		<link rel="stylesheet" href="/custom/css/board.css">
		<link id="sitestyle" rel="stylesheet" href="/custom/css/board_burichan.css">
		<link id="darkmode" rel="stylesheet" href="/custom/css/board_nachthexe.css" disabled="">
	<?php endif; ?>
</head>
<body class="<?php echo $isInfo ? '' : 'sidebar' ?>">
	<?php if ($isInfo): ?>
		<?php
			// module count is always zero
			//require KU_ROOTDIR . 'inc/functions.php';
			
			// get the count
			$bans = $tc_db->GetOne('SELECT COUNT(`id`) FROM `banlist`');
			$wordfilters = $tc_db->GetOne('SELECT COUNT(`id`) FROM `wordfilter`');
			
			/*$modules = modules_list();
			$moduleslist = 'None';
			if (count($modules) > 0) {
				$moduleslist = implode(', ', $modules);
			}*/
		?>
		
		<h1>General Info</h1>
		
		<table class="text-left">
			<tr>
				<td><b>Version</b></td>
				<td>kusaba x <?php echo KU_VERSION; ?></td>
			</tr>
			<tr>
				<td><b>Active Bans</b></td>
				<td><?php echo $bans; ?></td>
			</tr>
			<tr>
				<td><b>Wordfilters</b></td>
				<td><?php echo $wordfilters; ?></td>
			</tr>
			<tr>
				<td><b>Modules Loaded</b></td>
				<td>None</td>
			</tr>
		</table>
		
		<a href="https://github.com/helma-dev/kusaba-helma">GitHub</a>
	<?php else: ?>
		<a id="sidebar-toggle" href="javascript:void(0)" class="visible-xs-block text-center">
			<i class="icon icon-menu-hamburger"></i>
		</a>
		
		<?php
			// set sideload, and load pages
			$_GET['mode'] = 'sideload';
			include 'menu.php';
			include 'news.php';
			
			// get jquery
			$bodyJqueryPath = KU_TEMPLATEDIR.'/includes/bodyJquery.html';
			readfile($bodyJqueryPath);
		?>
		
		<script src="/custom/js/board_lib.js"></script>
		<script src="/custom/js/board.js"></script>
	<?php endif; ?>
</body>
</html>
