<?php

namespace Custom;

class Config {
	private $path;
	private $instance;
	
	public function __construct ($root) {
		$this->path = $root.'/custom/php/config.json';
		
		$instanceStr = file_get_contents($this->path);
		$this->instance = json_decode($instanceStr, true);
		
		unset($instanceStr);
	}
	
	public function get ($key) {
		if (!empty($this->instance[$key])) {
			return $this->instance[$key];
		}
		else {
			return null;
		}
	}
	
	public function set ($key, $value) {
		$this->instance[$key] = $value;
	}
	
	public function getInstance () {
		return $this->instance;
	}
	
	public function setInstance ($array) {
		$this->instance = $array;
	}
	
	public function save () {
		file_put_contents($this->path, json_encode($this->instance));
	}
	
}
