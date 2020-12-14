<?php

/*
	PLEASE NOTE THAT HOSTING CURRENTLY USES EACCELERATOR
	AND IT DOES NOT SUPPORT LAMBDA FUNCTION CALLBACKS
*/

function autoload ($class) {
	$class = str_replace('Custom\\', '', $class);
	
	$path = dirname(__FILE__).'/classes/'.$class.'.php';
	
	if (file_exists($path)) {
		require $path;
	}
}

// custom app autoloader
spl_autoload_register('autoload');
