<?php

/**
 * @author Oleg Isaev (PandCar)
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 * @version 1.0.0
 */

// Папка расположения кэша
$path_cache = __DIR__ .'/cache';


$callback = function($path)
{
	$content = file_get_contents($path);
	
	$data = json_decode($content, true);
	
	if ($data['time'] < time()) {
		unlink($path);
	}
};

RecursiveFileTraversal::run($path_cache, $callback);


class RecursiveFileTraversal
{
	protected static $callback;
	
	public static function run($path, $callback)
	{
		if (! is_callable($callback)) {
			return false;
		}
		
		self::$callback = $callback;
		
		self::process($path);
		
		return true;
	}
	
	protected static function process($combat_path)
	{
		if ($handle = opendir($combat_path))
		{
			while (false !== ($entry = readdir($handle)))
			{
				$file_path = $combat_path.'/'.$entry;
				
				if ($entry == '.' || $entry == '..') {
					continue;
				}
				
				if (is_dir($file_path)) {
					self::process($file_path);
					continue;
				}
				
				call_user_func(self::$callback, $file_path);
			}
			
			closedir($handle);
		}
	}
}
