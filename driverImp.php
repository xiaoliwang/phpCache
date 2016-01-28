<?php
declare(strict_types=1);

namespace xiaoliwang\extensions\phpCache;

interface driverImp{
	
	function __construct();
	
	function checkDriver(): bool;
	
	function driverSet(string $keyword, $value, int $time, bool $not_exist): bool;
	
	function driverGet(string $keyword);
	
	function driverDelete(string $keyword): bool;
	
	function driverClean(): bool;
}