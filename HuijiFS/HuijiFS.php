<?php
/**
 * File System Operations
 */
interface HuijiFS {
	/**
	 * All path are relative to /
	 */
	/**
	 * get file content
	 */
	public function get($path);
	/**
	 * put file content
	 */
	public function put($path, $content);
	/**
	 * remove file 
	 */
	public function unlink($path);
	/**
	 * check if file exists
	 */
	public function exists($path);
	/**
	 * copy a file from one place to another
	 */
	public function copy($from, $to);
	/**
	 * move a file from one place to another
	 */
	public function rename($from, $to);
	/**
	 * append file content if file exisits. otherwise create a new file with content.
	 */
	public function append($path, $content);
}
?>