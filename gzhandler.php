<?php

// show 404 if anything goes wrong
function show404 () {
	header('HTTP/1.1 303 See Other');
	header('Location: /404.html');
	exit();
}

// ========================================

// get variables ready
$root = dirname(__FILE__);
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// check path
if ($path === false) {
	show404();
}

// strip reverse dots and add root to path
$path = $root.str_replace('..', '', $path);

// change to board index if first page
if (substr($path, -1) == '/') {
	$path .= 'board.html';
}

// if file doesn't exists or not the right file type
if (!file_exists($path) || !preg_match('/\.(html|css|js)$/', $path, $match)) {
	show404();
}

// get type and file mod time
$type = $match[1];
$filetime = filemtime($path);

// not modifed
if (
	isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && 
	strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $filetime
) {
	header('HTTP/1.0 304 Not Modified');
	exit();
}

// start gzhandler
if (!ob_start('ob_gzhandler')) {
	ob_start();
}
header('Vary: Accept-Encoding');

// set content type
if ($type == 'js') {
	header('Content-Type: text/javascript; charset=utf-8');
}
else {
	header('Content-Type: text/'.$type.'; charset=utf-8');
}

// expiry headers
header_remove('ETag');
if ($type != 'html') {
	header('Cache-Control: public, max-age=604800');
	header('Expires: '.gmdate('D, d M Y H:i:s', time() + 604800).' GMT');
}

header('Last-Modified: '.gmdate('D, d M Y H:i:s', $filetime).' GMT');

// read and exit
readfile($path);
ob_end_flush();
