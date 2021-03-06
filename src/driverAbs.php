<?php
declare(strict_types=1);

namespace xiaoliwang\extensions\phpCache;

abstract class driverAbs{
	
	public $config = [];
	private static $arr_paths = [];	//多个缓存实例公用一个路径时，不需要额外创建
	private $path;
	
	public function __construct(array $config = []){
		$this->config = $config;
	}
	
	public function set(string $keyword, $value, int $time = 0): bool{
		$time = $time > 0 ? $time : 0;
		return $this->driverSet($keyword, $value, $time);
	}
	
	public function setnx(string $keyword, $value, int $time = 0): bool{
		$time = $time > 0 ? $time : 0;
		return $this->driverSet($keyword, $value, $time, true);
	}
	
	public function increament(string $keyword, int $step = 1): int{
		if (method_exists($this, 'driverIncrement')) {
			return $this->driverIncrement($keyword);
		}
		$object = $this->driverGet($keyword, true);
		if ($object) {
			if (!is_int($object['value'])) {
				throw new \Exception('ERR value is not an integer or out of range');
			}
			$value = $object['value'] + $step;
			$time = $object['expiring_time'] ? $object['expiring_time'] - time(): 0;
			$this->driverSet($keyword, $value, $time);
			return $value;
		} else {
			$this->driverSet($keyword, 1, 0);
			return 1;
		}
	}
	
	public function decrement(string $keyword, int $step = 1): int{
		if (method_exists($this, 'driverDecrement')) {
			return $this->driverIncrement($keyword);
		}
		$object = $this->driverGet($keyword, true);
		if ($object) {
			if (!is_int($object['value'])) {
				throw new \Exception('ERR value is not an integer or out of range');
			}
			$value = $object['value'] - $step;
			$time = $object['expiring_time'] ? $object['expiring_time'] - time(): 0;
			$this->driverSet($keyword, $value, $time);
			return $value;
		} else {
			$this->driverSet($keyword, -1, 0);
			return -1;
		}
	}
	
	public function get(string $keyword) {
		return $this->driverGet($keyword);
	}
	
	public function delete(string $keyword): bool{
		return $this->driverDelete($keyword);
	}
	
	public function clean(): bool{
		return $this->driverClean();
	}
	
	public function exists(string $keyword): bool{
		if (method_exists($this, 'driverExists')) {
			return $this->driverExists($keyword);
		}
		$data = $this->get($keyword);
		return is_null($data) ? false : true;
	}
	
	public function ttl(string $keyword): int{
		if (method_exists($this, 'driverTTL')) {
			return $this->driverTTL($keyword);
		}
		$object = $this->driverGet($keyword, true);
		if ($object) {
			$expiring_time = $object['expiring_time'];
			return $expiring_time ? $object['expiring_time'] - time() :
				0;
		} else {
			return -1;
		}
	}
	
	public function expireat(string $keyword, $unixtime): bool{
		$object = $this->driverGet($keyword, true);
		$time = $unixtime- time();
		if ($object && ($time > 0)) {
			$value = $object['value'];
			return $this->driverSet($keyword, $value, $time);
		} else {
			return false;
		}
	}
	
	public function expire(string $keyword, int $time): bool{
		$object = $this->driverGet($keyword, true);
		if ($object) {
			$value = $object['value'];
			return $this->driverSet($keyword, $value, $time);
		} else {
			return false;
		}
	}
	
	public function touch(string $keyword, int $time): bool{
		$object = $this->driverGet($keyword, true);
		if ($object) {
			$value = $object['value'];
			$time = $object['expiring_time'] - time() + $time;
			return $this->driverSet($keyword, $value, $time);
		} else {
			return false;
		}
	}
	
	public function persist(string $keyword): bool{
		$object = $this->driverGet($keyword, true);
		if ($object) {
			$value = $object['value'];
			return $this->driverSet($keyword, $value, 0);
			return true;
		} else {
			return false;
		}
	}
	
	public function rename(string $keyword, string $newkeyword): bool{
		$object = $this->driverGet($keyword, true);
		if ($object) {
			$this->driverDelete($keyword);
			$value = $object['value'];
			$time = $object['expiring_time'] - time();
			return $this->driverSet($newkeyword, $value, $time);
		} else {
			return false;
		}
	}
	
	public function renamenx(string $keyword, string $newkeyword): bool{
		$object = $this->driverGet($keyword, true);
		if ($object) {
			$value = $object['value'];
			$time = $object['expiring_time'] - time();
			if ($this->driverSet($newkeyword, $value, $time, true)) {
				$this->driverDelete($keyword);
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	public function deleteMulti(array $list = []){
		foreach ($list as $keyword) {
			$this->delete($keyword);
		}
	}
	
	protected function getPath(): string{
		if ($this->path) {
			return $this->path;
		}
		
		if (!array_key_exists('path', $this->config) || !$this->config['path']){
			$path = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir')
				: sys_get_temp_dir();
			$this->config['path'] = $path;
		} else {
			$path = $this->config['path'];
		}
		
		$securityKey = $this->config['securityKey'] ?? '';
		
		if (!$securityKey || $securityKey === 'auto') {
			$securityKey = $_SERVER['HTTP_HOST'] ?? 'default';
		}
		
		$full_path = $path . '/' . self::cleanFileName($securityKey) . '/';
		$full_pathx = md5($full_path);
		
		if (!array_key_exists($full_pathx, self::$arr_paths) || !self::$arr_paths[$full_pathx]) {
			if(!@file_exists($full_path) || !@is_writable($full_path)) {
				@mkdir($full_path, $this->setChmodAuto(), true);
			}
			if (!@is_dir($full_path) || !@is_writable($full_path)) {
				throw new \Exception('DIRECTORY ' . $full_path . 'IS NOT WRITABLE!!');
			}
			self::$arr_paths[$full_pathx] = true;
		}
		
		$this->path = realpath($full_path);
		return $this->path;
	}
	
	protected function setChmodAuto(){
		if (!array_key_exists('default_chmod', $this->config) || !$this->config['default_chmod']) {
			return 0777;
		} else {
			return $this->config['default_chmod'];
		}
	}
	
	protected function encode($data){
		return serialize($data);
	}
	
	protected function decode($data){
		$u_data = @unserialize($data);
		return $u_data ? $u_data : $data;
	}
	
	private function cleanFileName(string $filename): string{
		$regex = ['/[\(\)\[\]\{\}\<\>\/\\\,\;\:\'\"\?\&\$\#\=\~\*\|]/', '/\.$/', '/^\./'];
		$replace = ['-', '', ''];
		return preg_replace($regex, $replace, $filename);
	}
	
}