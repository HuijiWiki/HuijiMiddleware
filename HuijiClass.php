<?php 
/**
 * Huiji Class to get some all site status
 */
class Huiji{
	private static $isUnavailable = FALSE;
	private static $instance = NULL;
	protected $cache = NULL;
	protected $mAllPrefixes;
	protected $mNonHiddenPrefixes;

	private function __construct(){
		$this->cache = wfGetCache( CACHE_ANYTHING );
	}
	static function getInstance(){
		if (FALSE === self::$isUnavailable){
			if (NULL === self::$instance ){
				self::$instance = new Huiji();
			}
			// self::$isUnavailable = TRUE;
			return self::$instance;
		} else {
			return NULL;
		}
	}
	/**
	 * Generate a trade number based on the given type.
	 */
	public function getTradeNo($type){
		HuijiFunctions::
		$key = wfForeignMemcKey('huiji', '', 'Huiji', 'getTradeNo', $type);
		if (HuijiFunctions::addLock($key, 1000, 1000)){
			$no = $this->cache->get($key);
			if ($no != ''){
				++$no;
				$this->cache->set($key, $no);
				HuijiFunctions::releaseLock($key);
				return $type.$no;
			} else {
				$no = microtime(TRUE) * 1000;
				$this->cache->set($key, $no);
				HuijiFunctions::releaseLock($key);
				return $type.$no;
			}
		}
		return false;
	}
	/**
	 * get all site rank before today
	 * @return array site rank array
	 */
	public function getRankings(){
		$yesterday = date('Y-m-d',strtotime('-1 days'));
		$rank = AllSitesInfo::getAllSitesRankData( '', $yesterday );
        if (empty($rank)) {
          $rank = AllSitesInfo::getAllSitesRankData( '', date('Y-m-d',strtotime('-2 days')) );
        }
		return $rank;
	}
	/**
	 * count all site edits,files,pages,sites,users,
	 * @return array 
	 */
	public function getStats(){
		$key = wfForeignMemcKey('huiji', '', 'Huiji', 'getStats');
		$stats = $this->cache->get($key);
		if ($stats != ''){
			return $stats;
		} else {
			$stats['edits'] = AllSitesInfo::getAllSiteEditCount();
			$stats['files'] = AllSitesInfo::getAllUploadFileCount();
			$stats['pages'] = AllSitesInfo::getAllPageCount();
			$stats['sites'] = AllSitesInfo::getSiteCountNum();
			$stats['users'] = AllSitesInfo::getUserCountNum();
			$this->cache->set($key, $stats, 24*60*60);
		}
		return $stats;
	}
	/**
	 * get all site prefix 
	 * @param  boolean $showHidden if false, just show the usual prefix,otherwise show all prefix(include the site be hidden)
	 * @return array
	 */
	public function getSitePrefixes($showHidden = false){
		if ($showHidden){
			if ($this->mAllPrefixes==''){
				$this->mAllPrefixes = HuijiPrefix::getAllPrefixes($showHidden);
			}
			return $this->mAllPrefixes;
		} else {
			if ($this->mNonHiddenPrefixes==''){
				$this->mNonHiddenPrefixes = HuijiPrefix::getAllPrefixes($showHidden);
			}
			return $this->mNonHiddenPrefixes;
		}
	}
	
}
?>