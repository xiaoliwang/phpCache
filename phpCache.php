<?php
declare(strict_types=1);

namespace xiaoliwang\extensions\phpCache;

use xiaoliwang\extensions\phpCache\drivers\filesDriver;

class phpCache{
	
	private $config = [];
	private $instance;
	
	public function __construct(string $storage = "files", array $config = []){
		$this->instance = new filesDriver();
	}
	
	public function __call($name, $args){
		return call_user_func_array([$this->instance, $name], $args);
	}
}

spl_autoload_register(function($class){
	$filename = str_ireplace('xiaoliwang\\extensions\\phpCache\\', '', $class) . '.php';
	include($filename);
});

$b = new phpCache();
$b->set('a', 'b');
