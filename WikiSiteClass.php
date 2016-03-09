<?php
/**
 * Wiki Site Object
 */
class WikiSite extends Site{
	protected $mPrefix;
	protected $mSiteName;
	protected $cache;

	function __construct($prefix) {
		if (HuijiPrefix::hasPrefix($prefix)){
			$this->mPrefix = $prefix;					
		} else {
			$this->mPrefix = '';
		}
		$this->cache = wfGetCache( CACHE_ANYTHING );	

	}
	private function getCustomKey($name){
		return wfForeignMemcKey('huiji', '', 'WikiSite', $name, $this->mPrefix);
	}
	public function exists(){
		return $this->mPrefix === '';

	}
	public function getPrefix(){
		return $this->mPrefix;
	}
	public function isLocal(){
		global $wgHuijiPrefix;
		if ($this->mPrefix == $wgHuijiPrefix){
			return true;
		} else {
			return false;
		}
	}
	public function getUrl(){
		if ($this->mPrefix === ''){
			return '';
		}
		return HuijiPrefix::prefixToUrl($this->mPrefix);
	}
	public function getSiteName(){
		if ($this->mPrefix === ''){
			return '';
		}
		if ($this->mSiteName != ''){
			return $this->mSiteName;
		}
		$this->mSiteName = HuijiPrefix::prefixToSiteName($this->mPrefix);
		return $this->mSiteName;
	}
	public function getSiteStats( $formatted = true ){

		$key = $this->getCustomKey('getSiteStats');
		$data = $this->cache->get($key);
		if ($data != ''){
			$res = $data;
		} else {
			if ($this->mPrefix === ''){
				$res['followers'] = 0;
				$res['articles'] = 0;
				$res['users'] = 0;
				$res['pages'] = 0;
				$res['edits'] = 0;
				$res['files'] = 0;	
			} else {
				$res = array();
				$res['followers'] = UserSiteFollow::getFollowerCount( $this->mPrefix );
				$arr = AllSitesInfo::getPageInfoByPrefix($this->mPrefix);
				$res['articles'] = $arr['totalArticles'];
				$res['users'] = $arr['totalUsers'];
				$res['pages'] = $arr['totalPages'];
				$res['edits'] = $arr['totalEdits'];
				$res['files'] = $arr['totalImages'];	
			}
			$this->cache->set($key, $res, 24 * 60 * 60);	
		}
		if ( $formatted ) { 
			foreach($res as $key=>$value){
				$res[$key] = HuijiFunctions::format_nice_number($value);
			}
		}
		return $res;
	}
	public function getSiteAvatar($size){
		if ($this->mPrefix === ''){
			return null;
		}
		if (class_exists(wSiteAvatar)){
			return new wSiteAvatar($this->mPrefix, $size);
		} else {
			return null;
		}

	}
	public function getType(){
		return 'wiki';
	}
	//TODO
	public function getDescription(){

	}
	public function getBestRank(){
		$yesterday = date('Y-m-d',strtotime('-1 days'));
		$key = $this->getCustomKey('getBestRank'.$yesterday);
		$data = $this->cache->get($key);
		if ($data != ''){
			return $data;
		} else {
			$rank = getSiteBestRank($this->mPrefix);
			$this->cache->set($key, $rank, 48 * 60 * 60);
		}
		return $rank;
	}




} 
?>