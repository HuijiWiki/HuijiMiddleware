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
	private $defaultColor;
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

	public function getCirrusSearchInterwikiSource(){
		global $wgHuijiPrefix;
		$rankings = $this->getRankings();
		$wgCirrusSearchInterwikiSources = [];
		foreach($rankings as $site){
			$prefix = $site['site_prefix'];
        	$wgCirrusSearchInterwikiSources[$prefix] = WikiSite::DbIdFromPrefix($prefix);
        	$wgCirrusSearchWikiToNameMap[$prefix] = WikiSite::DbIdFromPrefix($prefix);
        	if ($site['site_rank'] > 10){
        		break;
        	}
		}		
		unset($wgCirrusSearchInterwikiSources[$wgHuijiPrefix]);
		return $wgCirrusSearchInterwikiSources;
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

	public function getSiteDefaultColor(){
		$this->defaultColor = array(
		                        "main-base" => "#333",
		                        "bg" => "#fff",
		                        "bg-inner" => "#fff",
		                        "a" => "#428bca",
		                        "sub-bg" => "#f6f8f8",
		                        "sub-a" => '#333',
		                        "modal" => "#222",
		                        "brand-default" => "#ffffff",
		                        "brand-primary" => "#337ab7",
		                        "brand-success" => "#5cb85c",
		                        "brand-info" => "#5bc0de",
		                        "brand-warning" => "#f0ad4e",
		                        "brand-danger" => "#d9534f",
		                        "well" => "#f5f5f5",
		                        "btn-v-padding" => "6px",
		                        "btn-h-padding" => "12px",
		                        "detail-bg" => "false",
		                        "detail-inner-bg" => "false",
		                        "detail-color" => "false",
		                        "detail-h1" => "false",
		                        "detail-h2" => "false",
		                        "detail-h3" => "false",
		                        "detail-h4" => "false",
		                        "detail-h5" => "false",
		                        "detail-a" => "false",
		                        "detail-new" => "false",
		                        "detail-border" => "false",
		                        "detail-secondary" => "false",
		                        "detail-toc-a" => "false",
		                        "detail-toc-a-hover" => "false",
		                        "detail-sub-a" => "false",
		                        "detail-sub-bg" => "false",
		                        "detail-sub-a-hover-bg" => "false",
		                        "detail-sub-site-count" => "false",
		                        "detail-contentsub" => "false",
		                        "detail-bottom-bg" => "false",
		                        "detail-bottom-color" => "false",
		                        "detail-quote-bg" => "false",
		                        "detail-quote-color" => "false",
		                        "detail-quote-a" => "false",
		                        "detail-quote-border" => "false",
		                        "detail-wikitable-bg" => "false",
		                        "detail-wikitable-color" => "false",
		                        "detail-wikitable-a" => "false",
		                        "detail-wikitable-border" => "false",
		                        "detail-wikitable-th-bg" => "false",
		                        "detail-infobox-bg" => "false",
		                        "detail-infobox-color" => "false",
		                        "detail-infobox-a" => "false",
		                        "detail-infobox-border" => "false",
		                        "detail-infobox-title-bg" => "false",
		                        "detail-infobox-title-a" => "false",
		                        "detail-infobox-title-color" => "false",
		                        "detail-infobox-item-title-bg" => "false",
		                        "detail-infobox-item-title-color" => "false",
		                        "detail-infobox-header-a" => "false",
		                        "detail-infobox-item-label-bg" => "false",
		                        "detail-infobox-item-label-color" => "false",
		                        "detail-infobox-item-label-a" => "false",
		                        "detail-infobox-item-label-border" => "false",
		                        "detail-infobox-item-detail-bg" => "false",
                                "detail-infobox-item-detail-color" => "false",
                                "detail-infobox-item-detail-a" => "false",
                                "detail-infobox-item-detail-border" => "false",
		                        "detail-navbox-bg" => "false",
		                        "detail-navbox-color" => "false",
		                        "detail-navbox-a" => "false",
		                        "detail-navbox-border" => "false",
		                        "detail-navbox-title-bg" => "false",
		                        "detail-navbox-title-color" => "false",
		                        "detail-navbox-title-a" => "false",
		                        "detail-navbox-group-bg" => "false",
		                        "detail-navbox-group-color" => "false",
		                        "detail-navbox-group-a" => "false",
		                        "detail-navbox-list-bg" => "false",
		                        "detail-navbox-list-color" => "false",
		                        "detail-navbox-list-a" => "false",
		                        "detail-navbox-list-new" => "false",
                                "detail-navbox-list-odd-bg" => "false",
                                "detail-navbox-list-even-bg" => "false",
		                        "detail-navbox-abovebelow-bg" => "false",
		                        "detail-navbox-abovebelow-color" => "false",
		                        "detail-navbox-abovebelow-a" => "false",
		                        "detail-vote-score-bg" => "false",
		                        "detail-vote-score-color" => "false",
		                        "detail-vote-star" => "false",
		                        "detail-vote-active-star" => "false",
                                "detail-vote-color" => "false",
		                    );
		return $this->defaultColor;
	}
	
}
?>
