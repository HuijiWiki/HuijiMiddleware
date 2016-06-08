<?php
/**
 * File System Operations
 * The default root of DiskFS is /var/www/virtual
 */
class DiskFS implements HuijiFS{
	const VIRTUAL_ROOT = "/var/www/virtual";
	private static $mInstance;
	private function __construct(){
	}
	public static function getInstance(){
        if (self::$mInstance != null){
            return self::$mInstance;
        } else {
            self::$mInstance = new DiskFS();
            return self::$mInstance;
        }


	}

	public function get($path){
		$fullpath = self::VIRTUAL_ROOT . "/$path";
		return file_get_contents($fullpath);

	};
	public function put($path, $content){
		$fullpath = self::VIRTUAL_ROOT . "/$path";
		$parts = explode('/', $fullpath);
        $file = array_pop($parts);
        $dir = '';
        foreach($parts as $part)
            if(!is_dir($dir .= "/$part")) mkdir($dir);
        file_put_contents("$dir/$file", $content);
		return true;
		// return file_put_contents($fullpath, $content);
	}
	public function unlink($path){
		$fullpath = self::VIRTUAL_ROOT . "/$path";
		return unlink($fullpath);		
	}
	public function exists($path){
		$fullpath = self::VIRTUAL_ROOT . "/$path";
		return file_exists($fullpath);

	}
	public function copy($from, $to){
		$fullpathFrom = self::VIRTUAL_ROOT . "/$from";
		$fullpathTo = self::VIRTUAL_ROOT . "/$to";
		return copy($fullpathFrom, $fullpathTo);
	}
	public function rename($from, $to){
		$fullpathFrom = self::VIRTUAL_ROOT . "/$from";
		$fullpathTo = self::VIRTUAL_ROOT . "/$to";		
		return copy($fullpathFrom, $fullpathTo);
	}
	public function append($path, $content){
		$fullpath = self::VIRTUAL_ROOT . "/$path";
		$parts = explode('/', $fullpath);
        $file = array_pop($parts);
        $dir = '';
        foreach($parts as $part)
            if(!is_dir($dir .= "/$part")) mkdir($dir);
        file_put_contents("$dir/$file", $content, FILE_APPEND);
	}
}
?>