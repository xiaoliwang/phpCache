<?php
declare(strict_types=1);

namespace xiaoliwang\extensions\phpCache\drivers;

use xiaoliwang\extensions\phpCache\driverAbs;
use xiaoliwang\extensions\phpCache\driverImp;

class filesDriver extends driverAbs implements driverImp{
	
	const NAME = 'FILES';
	
	public function checkDriver() : bool{
		return is_writable($this->getPath()) ? 
			true : false;
	}
	
	public function __construct(){
		if (!$this->checkdriver()) {
			throw new \Exception('CAN\'T USE THIS DRIVER ' . self::NAME . ' FOR YOUR WEBSITE!');
		}
	}
	
	public function driverSet(string $keyword, $value, int $time = 0, bool $not_exist = false): bool{
		$file_path = $this->getFilePath($keyword);
		$temp_path = $file_path . ".tmp";
		
		$now = time();
		$expiring_time = $time ? $now + $time : 0;
		$object = [
			'value' => $value,
			'time' => $now,
			'expired' => $time,
			'expiring_time' => $expiring_time
		];
		$data = $this->encode($object);
		
		$to_write = true;
		
		if ($not_exist && @file_exists($file_path)) {
			$content = file_get_contents($file_path);
			$old = $this->decode($content);
			$to_write = false;
			if ($this->isExpired($old)) {
				$to_write = true;
			}
		}
		
		$written = false;
		if ($to_write) {
			try {
				$written = @file_put_contents($temp_path, $data, LOCK_EX | LOCK_NB)
					&& @rename($temp_path, $file_path);
			} catch (\Exception $e) {
				$written = false;
			}
		}
		
		return $written;
	}
	
	public function driverGet(string $keyword, bool $all_keys = false) {
		$file_path = $this->getFilePath($keyword, true);
		if (!@file_exists($file_path)) {
			return null;
		}
		
		$content = file_get_contents($file_path);
		$object = $this->decode($content);
		if($this->isExpired($object)) {
			@unlink($file_path);
			return null;
		}
		if ($all_keys) {
			return $object;
		} else {
			return $object['value'] ?? null;
		}
	}
	
	public function driverDelete(string $keyword): bool {
		$file_path = $this->getFilePath($keyword, true);
		if (file_exists($file_path) && @unlink($file_path)) {
			return true;
		} else {
			return false;
		}
	}
	
	public function driverClean(): bool{
		$path = $this->getPath();
		return $this->dirClear($path);
	}
	
	public function driverExists(string $keyword): bool{
		$file_path = $this->getFilePath($keyword);
		if (@file_exists($file_path)) {
			$content = file_get_contents($file_path);
			$object = $this->decode($content);
			if ($this->isExpired($object)) {
				@unlink($file_path);
				return false;
			} else {
				return true;
			}
		}
		return false;
	}
	
	public function driverGc(){
		$path = $this->getPath();
		return $this->dirGc($path);
	}
	
	private function dirGc(string $path): array{
		$res = [
			'size' => 0,
			'info' => [
				'Total[bytes]' => 0,
				'Expired[bytes]' => 0,
				'Current[bytes]' => 0
			]
		];
		if (!($dir = @opendir($path))) {
			throw new \Exception('CAN\'T READ PATH:' . $path);
		}
		
		$total = 0;
		$removed = 0;
		//avoiding any directory entry whose name evaluates to FALSE will stop the loop
		while (false !== ($file = readdir($dir))) {
			if ($file === '.' || $file === '..') {
				continue;
			}
			$file_path = $path . '/' . $file;
			if (is_dir($file_path)) {
				$sub_res = $this->dirGc($file_path);
				$total += $sub_res['info']['Total[bytes]'];
				$removed += $sub_res['info']['Expired[bytes]'];
				unset($sub_res);
			} else {
				$size = @filesize($file_path);
				$object = $this->decode(file_get_contents($file_path));
				if ($this->isExpired($object)) {
					@unlink($file_path);
					$removed += $size;
				}
				$total += $size;
			}
		}
		
		$res['size'] = $total - $removed;
		$res['info'] = [
			'Total[bytes]' => $total,
			'Expired[bytes]' => $removed,
			'Current[bytes]' => $res['size']
		];
		
		return $res;
		 
	}
	
	private function dirClear(string $path): bool{
		if (!($dir = @opendir($path))) {
			throw new \Exception('CAN\'T READ PATH:' . $path);
		}
		
		$cleared = true;
		//avoiding any directory entry whose name evaluates to FALSE will stop the loop 
		while (false !== ($file = readdir($dir))) {
			if ($file === '.'|| $file === '..') {
				continue;
			}
			$file_path = $path . '/' . $file;
			if (is_dir($file_path)) {
				$cleared = $cleared && $this->dirClear($file_path);
			} else {
				$cleared = $cleared && @unlink($file_path);
			}
		}
		closedir($dir);
		return $cleared && @rmdir($path);
	}
	
	private function encodeFilename(string $keyword): string{
		return trim(trim(preg_replace('/[^a-z,A-Z,0-9]+/', '_', $keyword), '_'));
	}
	
	private function getFilePath(string $keyword, bool $escape = false): string{
		$path = $this->getPath();
		$filename = $this->encodeFilename($keyword);
		
		$folder = substr($filename, 0, 2);
		$path = $path . '/' . $folder;
		
		if (!$escape) {
			if (!@file_exists($path)) {
				@mkdir($path, $this->setChmodAuto(), true);
			}
			
			if (!@is_dir($path) && !@is_writable($path)) {
				throw new \Exception('DIRECTORY ' . $full_path . 'IS NOT WRITABLE!!');
			}
		}
		
		$file_path = $path . "/" . $filename . '.cache';
		return $file_path;
	}
	
	private function isExpired(array $object): bool {
		$expiring_time = $object['expiring_time'] ?? 1;
		return $expiring_time && (time() >= $expiring_time);
	}
}