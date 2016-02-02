<?php
declare(strict_types=1);

namespace xiaoliwang\extensions\phpCache;

class phpCache{
	
	private $instance;
	private $config = [
		'files' => [
			'path' => '',
			'securityKey' => 'auto',
			'default_chmod' => 0777
		],
		'redis' => [
			'host' => '127.0.0.1',
			'port' => 6379,
			'password' => '',
			'database' => '',
		]
	];
	
	public function __construct(string $storage = 'files', array $config = []){
		$class_path = 'xiaoliwang\extensions\phpCache\drivers\\' . $storage;
		$class_path .= 'Driver';
		if(!class_exists($class_path))
			throw new \Exception('THIS STORAGE IS NOT EXISTS!');
		$config = $config + $this->config[$storage];
		$this->instance = new $class_path($config);
	}
	
	public function setConfig(string $key, string $value): bool{
		$config = &$this->instance->config;
		if (array_key_exists($key, $config)) {
			$config[$key] = $value;
			return true;
		} else {
			return false;
		}
	}
	
	public function  getConfig(string $key): string {
		$config = $this->instance->config;
		if (array_key_exists($key, $config)) {
			return $config[$key];
		} else {
			return '';
		}
	}
	
	public function __call($name, $args){
		return call_user_func_array([$this->instance, $name], $args);
	}
	
	public function __get(string $name){
		return $this->get($name);
	}
	
	public function __set(string $keyword, string $value): bool{
		return $this->set($keyword, $value);
	}
}
/*
spl_autoload_register(function($class){
	$filename = str_ireplace('xiaoliwang\\extensions\\phpCache\\', '', $class) . '.php';
	include($filename);
});
*/