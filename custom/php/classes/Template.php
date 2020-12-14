<?php

namespace Custom;

class Template {
	private $instance;
	private $variables;
	private $templatePath;
	
	public function __construct ($templatePath) {
		$this->instance = new \Dwoo();
		$this->variables = new \Dwoo_Data();
		$this->templatePath = $templatePath;
	}
	
	public function assign ($key, $value) {
		$this->variables->assign($key, $value);
	}
	
	public function render ($template) {
		$template = new \Dwoo_Template_File($this->templatePath.'/'.$template);
		$this->instance->output($template, $this->variables);
	}
	
}
