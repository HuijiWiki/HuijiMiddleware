<?php 
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
	public function getRankings(){
		$yesterday = date('Y-m-d',strtotime('-1 days'));
		$rank = AllSitesInfo::getAllSitesRankData( '', $yesterday );
        if (empty($rank)) {
          $rank = AllSitesInfo::getAllSitesRankData( '', date('Y-m-d',strtotime('-2 days')) );
        }
		return $rank;
	}
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
	public function getSitePrefixes($showHidden){
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