<?php

function cleanUri ($uri, $request) {
	if ($uri == null) {
		$request->set404();
	}
	
	if (substr($uri, -1) == '/') {
		$uri .= 'board.html';
	}

	$path = KU_ROOTDIR.'/'.$uri;
	if (!file_exists($path)) {
		$request->set404();
	}
	
	return $path;
}

// ========================================

$request = new \Custom\Request();
$template = new \Custom\Template(KU_TEMPLATEDIR);
$config = new \Custom\Config(KU_ROOTDIR);

$uri = null;
$path = null;

$enable = $config->get('protectEnable');
$password = $config->get('protectPassword');
$duration = $config->get('protectDuration');

if (!empty($_POST['password']) && !empty($_POST['uri'])) {
	$uri = $_POST['uri'];
	$path = cleanUri($uri, $request);
	
	if ($_POST['password'] !== $password) {
		$template->assign('error', 'Incorrect password');
	}
	else {
		$request->setCookie('verify', hash('md5', $password), $duration);
		$template->assign('verified', true);
	}
}
else {
	$uri = $request->getServer('REQUEST_URI');
	$path = cleanUri($uri, $request);
	
	if ($enable === 'true') {
		$cookie = $request->getCookie('verify');
		if ($cookie === hash('md5', $password)) {
			readfile($path);
			exit();
		}
		else if ($cookie !== null) {
			$request->unsetCookie('verify');
			$template->assign('error', 'Session expired');
		}
	}
	else {
		readfile($path);
		exit();
	}
}

$template->assign('uri', $uri);
$template->render('password.tpl');
