<?php
use OSS;
/**
 * File System Operations
 */
class OssFS implements HuijiFS{
	protected $ossClient;
	private function __construct() {
		global $wgOssEndpoint;
		$accessKeyId = Confidential::$aliyunKey;
        $accessKeySecret = Confidential::$aliyunSecret;
        $endpoint = $wgOssEndpoint;
        try {
            $this->ossClient = new Oss\OssClient($accessKeyId, $accessKeySecret, $endpoint);
        } catch (Oss\OssException $e) {
            wfDebug($e->getMessage());
        }
	}
    private static $mInstance;
    public static function getInstance(){
        if (self::$mInstance != null){
            return self::$mInstance;
        } else {
            self::$mInstance = new OssFS();
            return self::$mInstance;
        }

    }
	public function get($path){
		global $wgOssFSBucket;
		$bucket = $wgOssFSBucket;
        try {
            $content = $this->ossClient->getObject($bucket, $path);
        } catch (Oss\OssException $e) {
            // print $e->getMessage();
            wfDebug($e->getMessage());
            return null;
        }
		return $content;

	}
	public function put($path, $content){
		global $wgOssFSBucket;
		$bucket = $wgOssFSBucket;;
		try {
            $content = $this->ossClient->putObject($bucket, $path, $content);
        } catch (Oss\OssException $e) {
            // print $e->getMessage();
            wfDebug($e->getMessage());
            return false;
        }	
		return true;
	}
	public function unlink($path){
		global $wgOssFSBucket;
		$bucket = $wgOssFSBucket;
		try {
            $content = $this->ossClient->deleteObject($bucket, $path);
        } catch (Oss\OssException $e) {
            // print $e->getMessage();
            wfDebug($e->getMessage());
            return false;
        }
		return true;		
	}
	public function exists($path){
		global $wgOssFSBucket;
		$bucket = $wgOssFSBucket;
		try {
            $doesExists = $this->ossClient->doesObjectExist($bucket, $path);
        } catch (Oss\OssException $e) {
            // print $e->getMessage();
            wfDebug($e->getMessage());
            return null;
        }
		return $doesExists;

	}
	public function copy($from, $to){
		global $wgOssFSBucket;
		$bucket = $wgOssFSBucket;
		try {
            $doesExists = $this->ossClient->copyObject($bucket, $from, $bucket, $to);
        } catch (Oss\OssException $e) {
            wfDebug($e->getMessage());
            return false;
        }
		return true;
	}
    public function rename($from, $to){
        $ret = $this->copy($from, $to);
        if ($ret == false){
            return false;
        }
        $ret = $this->unlink($from);
        return $ret;
    }
    public function append($path, $content){
        $ret = $this->get($path, $content);
        if ($ret == ''){
            return $this->put($path, $content);
        }
        $ret2 = $this->put($path, $content.$ret);
        return $ret2;        
    }
}