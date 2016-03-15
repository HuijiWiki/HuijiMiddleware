<?php
/**
 * Wiki Site Object
 */
class WikiSite extends Site{
	protected $mId;
	protected $mType;
	protected $mDsp;
	protected $mLang;
	protected $mStatus;
	protected $mFounder;
	protected $mPrefix;
	protected $mName;
	protected $mDate;
	protected $cache;
	protected $mFollowers;
	protected $mSiteRating;
	const CACHE_MAX = 1000;
	private static $siteCache;

	function __construct() {
		$this->cache = wfGetCache( CACHE_ANYTHING );	
		// if (HuijiPrefix::hasPrefix($prefix)){
		// 	$this->mPrefix = $prefix;					
		// } else {
		// 	$this->mPrefix = '';
		// }
		// $this->cache = wfGetCache( CACHE_ANYTHING );	
		// $this->loadFromRow();
		// $siteCache = self::getSiteCache();
		// $siteCache->set($this->mPrefix, $this);
	}
	private static function getSiteCache() {
        if ( self::$siteCache == null ) {
            self::$siteCache = new HashBagOStuff( [ 'maxKeys' => self::CACHE_MAX ] );
		}
		return self::$siteCache;
	}
	public static function newFromPrefix( $prefix ){
		$siteCache = self::getSiteCache();
		$site = $siteCache->get($prefix);
		if ( $site != '' ){
			return $site;
		} else {
			$site = new WikiSite();
			$site->setPrefix($prefix);
			$site->loadFromRow();
			$siteCache->set($prefix, $site);
			return $site;
		}

	}
	public static function NameFromPrefix( $prefix ){
		return HuijiPrefix::prefixToSiteName($this->mPrefix);
	}
	public static function DbNameFromPrefix( $prefix ){
		$dashPrefix = str_replace('.', '_', $prefix);
		if ($prefix == 'www'){
			return 'huiji_home';
		} else {
			return 'huiji_sites-'.$dashPrefix;
		}
	}
    /**
     * the site's sysop/staff
     * @return array user's avater
     */
    private static function getSiteManager( $prefix,$group ){
        $data = self::getSiteManagerCache( $prefix,$group );
        if ( $data != '' ) {
            wfDebug( "Got sitemanagers from cache\n" );
            return $data;
        } else {
            return self::getSiteManagerDB( $prefix,$group );
        }
    }

    private static function getSiteManagerCache( $prefix,$group ){
        global $wgMemc;
        $key = wfForeignMemcKey('huiji','', 'user_group', 'sitemanager', $prefix,$group );
        $data = $wgMemc->get( $key );
        if ( $data != '' ) {
            return $data;
        }
    }

    private static function getSiteManagerDB( $prefix,$group ){
        global $wgMemc;
        $key = wfForeignMemcKey('huiji','', 'user_group', 'sitemanager', $prefix,$group );
        $dbr = wfGetDB( DB_SLAVE );
        $data = array();
        $res = $dbr->select(
            'user_groups',
            array(
                'ug_user'
            ),
            array(
                'ug_group' => $group
            ),
            __METHOD__
        );
        if($res){
            foreach ($res as $value) {
                $data[] = $value->ug_user;
            }
            $wgMemc->set( $key, $data, 60*60*24 );
            return $data;
        }

    }
	protected function loadFromRow(){
			$dbr = wfGetDB( DB_SLAVE );
			$s = $dbr->selectRow(
				'domain',
				array( 
					'domain_id', 
					'domain_name', 
					'domain_type', 
					'domain_dsp', 
					'domain_lang', 
					'domain_status', 
					'domain_founder_id', 
					'domain_date' ),
				array(
					'domain_prefix' => $this->mPrefix,
				),
				__METHOD__
			);
			if ( $s !== false ) {
				$this->mId = $s->domain_id;
				$this->mType = $s->domain_type;
				$this->mDsp = $s->domain_dsp;
				$this->mLang = $s->domain_lang;
				$this->mStatus = $s->domain_status;
				$this->mFounder = User::newFromId($s->domain_founder_id);
				$this->mName = $s->domain_name;
				$this->mDate = $s->domain_date;
			}
	}
	private function getCustomKey($name){
		return wfForeignMemcKey('huiji', '', 'WikiSite', $name, $this->mPrefix);
	}
	public function exists(){
		return $this->mPrefix != '';

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
	public function isFollowedBy($user){
		$usf = new UserSiteFollow();
		return $usf->checkUserSiteFollow( $user, $this->mPrefix);
	}
	public function getUrl(){
		if ($this->mPrefix === ''){
			return '';
		}
		return HuijiPrefix::prefixToUrl($this->mPrefix);
	}
	public function getName(){
		if ($this->mPrefix === ''){
			return '';
		}
		if ($this->mName != ''){
			return $this->mName;
		}
		// Trust HuijiPrefix Cache
		$this->mName = HuijiPrefix::prefixToSiteName($this->mPrefix);
		return $this->mName;
	}
	public function getStats( $formatted = true ){

		$key = $this->getCustomKey('getStats');
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
				$this->cache->set($key, $res, 24 * 60 * 60);
			}
				
		}
		if ( $formatted ) { 
			foreach($res as $key=>$value){
				$res[$key] = HuijiFunctions::format_nice_number($value);
			}
		}
		return $res;
	}
	public function getEditCount( $fromTime, $toTime ){
		$ueb = new UserEditBox();
		return $ueb->getSiteEditCount(-1, $this->mPrefix, $fromTime, $toTime );
	}
	public function getViewCount( $fromTime, $toTime ){
		$ueb = new UserEditBox();
		return $ueb->getSiteViewCount(-1, $this->mPrefix, $fromTime, $toTime );
	}
	public function getEditedUserCount( $fromTime , $toTime ){
		//TODO wait for new Interface
		// $ueb = new UserEditBox();
		// return $ueb->getSiteEditUserCount( $fromTime, $toTime );
	}
	public function getFollowers( $expanded = false ){
		if (!$this->mFollowers){
			// return $this->mFollowers;
			$usf = new UserSiteFollow();
			$this->mFollowers = $usf->getSiteFollowers($this->mPrefix);
			$siteCache = self::getSiteCache();
			$siteCache->set($this->mPrefix, $this);
			//return $this->mFollowers;
		}
		if (!$expanded){
			return $this->mFollowers;
		} else {
			$request = array();
			foreach ($this->mFollowers as $value) {
				$u_name = $value['user_name'];
				$temp['user'] = $u_name;
				// $temp['user'] = User::getEffectiveGroups($user);
				$userPage = Title::makeTitle( NS_USER, $u_name );
				$userPageURL = htmlspecialchars( $userPage->getFullURL() );
				$temp['userUrl'] = $userPageURL;
				$user_id = User::idFromName($u_name);
				$stats = new UserStats( $user_id, $u_name );
				$stats_data = $stats->getUserStats();
				$user_level = new UserLevel( $stats_data['points'] );
				$temp['level'] = $user_level->getLevelName();
				$avatar = new wAvatar( $user_id, 'm' );
				$temp['url'] = $avatar->getAvatarURL();
				$tuser = User::newFromName($u_name);
				$temp['count'] = UserStats::getSiteEditsCount($tuser,$this->mPrefix);

				// if(in_array($u_name, $follower)){
				// 	$is_follow = 'Y';
				// }else{
				// 	$is_follow = 'N';
				// }
				// $temp['is_follow'] = $is_follow;
				
				$request[] = $temp;
	 		}
	 		foreach ($request as $key => $value) {
				$count[$key] = $value['count'];
			}
			array_multisort($count, SORT_DESC, $request); 
			return $request;
		}
	}
	public function getAvatar($size){
		if ($this->mPrefix === ''){
			return null;
		}
		if (class_exists(wSiteAvatar)){
			return new wSiteAvatar($this->mPrefix, $size);
		} else {
			return null;
		}

	}
	public function getGroup(){
		return 'wiki';
	}
	//TODO
	public function getDescription(){
		if ($this->mPrefix === ''){
			return '';
		}
		return $this->mDsp;
	}
	public function getType(){
		if ($this->mPrefix === ''){
			return '';
		}		
		return $this->mType;
	}
	public function getFounder(){
		if ($this->mPrefix === ''){
			return '';
		}		
		return $this->mFounder;		
	}
	public function getId(){
		if ($this->mPrefix === ''){
			return '';
		}		
		return $this->mId;
	}
	public function getLang(){
		if ($this->mPrefix === ''){
			return '';
		}		
		return $this->mLang;		
	}
	public function getDate(){
		if ($this->mPrefix === ''){
			return '';
		}		
		return $this->mDate;				
	}
	public function getBestRank(){
		$yesterday = date('Y-m-d',strtotime('-1 days'));
		$key = $this->getCustomKey('getBestRank'.$yesterday);
		$data = $this->cache->get($key);
		if ($data != ''){
			return $data;
		} else {
			$rank = AllSitesInfo::getSiteBestRank($this->mPrefix);
			return $rank;
			$this->cache->set($key, $rank, 48 * 60 * 60);
		}
		return $rank;
	}
	public function getUserEditsCountOnSite( $user ){
		return UserStats::getSiteEditsCount( $user, $this->mPrefix );
	}
	//TODO: Setters
	protected function setPrefix($prefix){
		$this->mPrefix = $prefix;
	}

	//TODO: getAdmins
	public function getUsersFromGroup($group){
		$ums = self::getSiteManager( $this->mPrefix, $group );
		$result = array();
		$count = array();
		foreach( $ums as $value ){
            $user = User::newFromId( $value );
            if ( !($user->isAllowed('bot')) ) {
                $usersys['user_name'] = $user->getName();
                $usersys['count'] = $this->getUserEditsCountOnSite($user);
                $userPage = Title::makeTitle( NS_USER, $user->getName() );
                $usersys['url'] = htmlspecialchars( $userPage->getFullURL() );
                $avatar = new wAvatar( $value, 'm' );
                $usersys['avatar'] = $avatar->getAvatarURL();
                $result[] = $usersys;
            }
        }
        foreach ($result as $key => $value) {
            $count[$key] = $value['count'];
        }
        array_multisort($count, SORT_DESC, $result);
        return $result;
	}
	public function getRating(){
		$key = $this->getCustomKey('getRating');
		$r = $this->cache->get($key);
		if ($r){
			return $r;
		}	
		$dbr = wfGetDB(DB_SLAVE);
		$s = $dbr->selectRow(
			'site_best_rank',
			array(
				'site_rating'
			)
			array(
				'site_prefix' => $site->mPrefix,
			),
			__METHOD__
		);
		if ($s != ''){
			$this->cache->set($key, $s->site_rating);
			return $s->site_rating;
		}
		$this->cache->set($key, 'NA');
		return 'NA';

	}
	public function advanceRating(){
		$now = $this->getRating();
		$key = $this->getCustomKey('getRating');
		if ($now = 'E'){
			$after = 'D';
		} 
		if ($now = 'D'){
			$after = 'C';
		} 
		if ($now = 'C'){
			$after = 'B';
		} 
		if ($now = 'B'){
			$after = 'A';
		} 
		if ($now = 'A'){
			$after = 'S';
		} 
		$dbw = wfGetDB(DB_MASTER);
		$s = $dbw->upsert(
			'site_best_rank',
			array(
				'site_rating' => $after,
			),
			array(
				'site_prefix' => $this->mPrefix,
			),
			__METHOD__
		);
		$this->cache->set($key, $after);
		return $after;

	}
	public function getPotentialRating(){
		// $stats = $this->getStats(false);
		// return $stats['articles'];
		$stats = $this->getStats(false);
		if (empty($this->getBestRank())){
			return 'NA';
		}
		if (($this->getBestRank() == 1) and ($stats['articles'] > 500) and ($stats['users'] > 50)){
			$this->mSiteRating = 'A';
		} else {
			if (($this->getBestRank() <= 3) and ($stats['articles'] > 100) and ($stats['users'] > 10)){
				$this->mSiteRating = 'B';
			} else {
				if ( ($this->getBestRank() <= 10) and ($stats['articles'] > 20) and ($stats['users'] > 4) ){
					$this->mSiteRating = 'C';
				}
				else {
					if ($this->getBestRank() <= 50){
						$this->mSiteRating = 'D';
					}	else {
						$this->mSiteRating = 'E';
					}

				}					
			}					
		}
		return $this->mSiteRating;
	}

} 
?>