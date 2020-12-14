<?php

namespace Custom;

class Request {
	public $isAjax = false;
	
	public function __construct () {
		if (
			!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
			strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
		) {
			$this->isAjax = true;
		}
	}
	
	public function getServer ($var) {
		if (!empty($_SERVER[$var])) {
			return $_SERVER[$var];
		}
		else {
			return null;
		}
	}
	
	public function getCookie ($var) {
		if (isset($_COOKIE[$var])) {
			return $_COOKIE[$var];
		}
		else {
			return null;
		}
	}
	
	public function setCookie ($name, $value, $expire = 0) {
		$domain = '.'.$_SERVER['HTTP_HOST'];
		$expire = time() + (int)$expire;
		
		setcookie($name, $value, $expire, '/', $domain, false, true);
	}
	
	public function unsetCookie ($name) {
		setcookie($name, '', 1, '/', '', false, true);
	}
	
	public function set404 () {
		header('HTTP/1.1 303 See Other');
		header('Location: /404.html');
		exit();
	}
	
	public function redirect ($path) {
		header('HTTP/1.1 303 See Other');
		header('Location: '.$path);
		exit();
	}
	
}
