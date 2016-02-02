<?php
declare(strict_types=1);

namespace xiaoliwang\extensions\phpCache\drivers;

use xiaoliwang\extensions\phpCache\{driverAbs, driverImp};

class redisDriver extends driverAbs implements driverImp
{
	const NAME = 'redis';
	private static $instance;
	
	public function checkDriver(): bool{
		if (class_exists('Redis')) {
			return true;
		} else {
			return false;
		}
	}
	
	public function __construct(array $config = []){
		parent::__construct($config);
		if (!$this->checkdriver()) {
			throw new \Exception('CAN\'T USE THIS DRIVER ' . self::NAME . ' FOR YOUR WEBSITE!
			YOU MUST INSTALL PECL REDIS EXTENSION TO ENABLE IT');
		}
		self::$instance = new \Redis();
	}
	
	public function connect(){
		$config = $this->config;
		if (!self::$instance->connect($config['host'], $config['port'], 1)) {
			throw new \Exception('CAN\'T CONNECT TO THE SERVER');
		}
		if ($config['password']) {
			self::$instance->auth($config['password']);
		}
		if ($config['database']) {
			self::$instance->select($config['database']);
		}
	}
	
	function driverSet(string $keyword, $value, int $time, bool $not_exist): bool{
		return false;
	}
	
	function driverGet(string $keyword){
		return false;
	}
	
	function driverDelete(string $keyword): bool{
		return false;
	}
	
	function driverClean(): bool{
		return false;
	}
	
}