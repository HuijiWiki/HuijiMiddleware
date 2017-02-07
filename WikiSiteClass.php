<?php
/**
 * WikiSite:get one site's detail info 
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
		global $wgMemCachedServers;
        if ( self::$siteCache == null ) {
            self::$siteCache = new MemcachedPhpBagOStuff( [
            	'servers' => $wgMemCachedServers,
            	'debug' => false,
            	'persistent' => false,
            	'timeout' => 1, 
            	'connect_timeout' => 1,
            	'maxKeys' => self::CACHE_MAX 

            ] );
		}
		return self::$siteCache;
	}
	public static function newFromDb(){

	}
	/**
	 * get site object by site prefix
	 * @param  string $prefix site prefix such as 'lotr'、'asoiaf'..
	 * @return object         site object
	 */
	public static function newFromPrefix( $prefix ){
		$siteCache = self::getSiteCache();
		$site = $siteCache->get($prefix);
		if ( $site != '' && !empty($site->getId())){
			return $site;
		} else {
			$site = new WikiSite();
			$site->setPrefix($prefix);
			$siteCache->set($prefix, $site);
			return $site;
		}

	}
	/**
	 * get site name by site prefix
	 * @param string $prefix site prefix such as 'lotr'
	 * @return string site name
	 */
	public static function NameFromPrefix( $prefix ){
		return HuijiPrefix::prefixToSiteName($this->mPrefix);
	}
	/**
	 * get site DB by $prefix
	 * @param string $prefix 'www' or others
	 * @return string site DB name
	 */
	public static function DbIdFromPrefix( $prefix ){
		if ($prefix === 'www'){
			return 'huiji_home';
		} else {
			$dashPrefix = self::tableNameFromPrefix( $prefix );
			return 'huiji_sites-'.$dashPrefix;
		}
	}
	/**
	 * get site DB by $prefix
	 * @param string $prefix 'www' or others
	 * @return string site DB name
	 */
	public static function prefixFromDbId( $dbId ){
		if ($dbId === "huiji_home"){
			return 'www';
		} else {
			$str = str_replace("huiji_sites-", '', $dbId);
			if (substr($str, strlen($str) - 1, strlen($str)) === "_" ){
				$str = substr($str, 0, strlen($str)-1);
			}
			return str_replace("_", ".", $str);
		}
	}
	/**
	 * get site table by $prefix
	 * @param string $prefix 'www' or others
	 * @return string site table name
	 */
	public static function tableNameFromPrefix($prefix){
		if ($prefix == 'www'){
			return '';
		}
		$site = WikiSite::newFromPrefix($prefix);
		if ($site->getId() > 180){
			return str_replace('.', '_', $prefix).'_';
		} else {
			return str_replace('.', '_', $prefix);
		}
	}
    /**
     * the site's sysop/staff
     * @param string $prefix
     * @param string $group user group
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
			global $wgDBprefix, $wgDBname;
			$dbr = wfGetDB( DB_SLAVE,'', 'huiji' );
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
	/**
	 * check this site is exists
	 * @return blooen 
	 */
	public function exists(){
		return $this->mPrefix != '';

	}
	/**
	 * get this site prefix
	 * @return string site's prefix
	 */
	public function getPrefix(){
		return $this->mPrefix;
	}
	/**
	 * check the site is Local site use gloabl $wgHuijiPrefix
	 * @return boolean
	 */
	public function isLocal(){
		global $wgHuijiPrefix;
		if ($this->mPrefix == $wgHuijiPrefix){
			return true;
		} else {
			return false;
		}
	}
	/**
	 * check user is following this site
	 * @param  object $user user object
	 * @return boolean
	 */
	public function isFollowedBy($user){
		$usf = new UserSiteFollow();
		return $usf->checkUserSiteFollow( $user, $this->mPrefix);
	}
	/**
	 * get site's host
	 * @return string site's host (including the tail /).
	 */
	public function getUrl(){
		if ($this->mPrefix === ''){
			return '';
		}
		return HuijiPrefix::prefixToUrl($this->mPrefix);
	}

	/**
	 * get API endpoint
	 * @return string API endpoint
	 */
	public function getApi(){
		return $this->getUrl()."api.php";
	}
	/**
	 * get site's name
	 * @return string site's name
	 */
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
	/**
	 * get site stats is $formatted is true, those number will be fortamtted
	 * @param  boolean $formatted
	 * @return array    the site stats
	 */
	public function getStats( $formatted = true ){

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
				
		if ( $formatted ) { 
			foreach($res as $key=>$value){
				$res[$key] = HuijiFunctions::format_nice_number($value);
			}
		}
		return $res;
	}
	/**
	 * get the site's some days edit count
	 * @param  sting $fromTime  '2016-01-01'
	 * @param  string $toTime   '2016-02-02'
	 * @return array
	 */
	public function getEditCount( $fromTime, $toTime ){
		$ueb = new UserEditBox();
		return $ueb->getSiteEditCount(-1, $this->mPrefix, $fromTime, $toTime );
	}
	/**
	 * get the site's some days view count
	 * @param  sting $fromTime  '2016-01-01'
	 * @param  string $toTime   '2016-02-02'
	 * @return array
	 */
	public function getViewCount( $fromTime, $toTime ){
		$ueb = new UserEditBox();
		return $ueb->getSiteViewCount(-1, $this->mPrefix, $fromTime, $toTime );
	}
	/**
	 * get the site's some days edit user count
	 * @param  sting $fromTime  '2016-01-01'
	 * @param  string $toTime   '2016-02-02'
	 * @return array
	 */
	public function getEditedUserCount( $fromTime , $toTime ){
		//TODO wait for new Interface
		// $ueb = new UserEditBox();
		// return $ueb->getSiteEditUserCount( $fromTime, $toTime );
	}
	/**
	 * get site followers
	 * @param  boolean $expanded if true return user detail info,eles return simple info
	 * @return array
	 */
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
				$temp['userId'] = $user_id;
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
	/**
	 * get site's avatar
	 * @param  sting $size avatar size such as s,m,l
	 * @return object object(wSiteAvatar)
	 */
	public function getAvatar($size){
		if ($this->mPrefix === ''){
			return null;
		}
		if ($size == ''){
			$size = 'm';
		}
		if (class_exists('wSiteAvatar')){
			return new wSiteAvatar($this->mPrefix, $size);
		} else {
			return null;
		}

	}
	/**
	 * site's group
	 * @return string always wiki
	 */
	public function getGroup(){
		if (class_exists('HuijiTrans')){
			return 'trans';
		}
		return 'wiki';
	}
	/**
	 * get site's description
	 * @return string
	 */
	public function getDescription(){
		if ($this->mPrefix === ''){
			return '';
		}
		if ($this->mDsp != ''){
			return $this->mDsp;
		}
		$siteCache = self::getSiteCache();
		$this->loadFromRow();
		$siteCache->set($this->mPrefix, $this);
		return $this->mDsp;
		
	}
	/**
	 * get site's type
	 * @return string
	 */
	public function getType(){
		if ($this->mPrefix === ''){
			return '';
		}	
		if ($this->mType!= ''){
			return $this->mType;
		}
		$siteCache = self::getSiteCache();
		$this->loadFromRow();
		$siteCache->set($this->mPrefix, $this);
		return $this->mType;
	}
	/**
	 * get site's founder
	 * @return object user info
	 */
	public function getFounder(){
		if ($this->mPrefix === ''){
			return '';
		}
		if ($this->mFounder != ''){
			return $this->mFounder;
		} else {
			$siteCache = self::getSiteCache();
			$this->loadFromRow();
			$siteCache->set($this->mPrefix, $this);			
		}
		return $this->mFounder;		
	}
	/**
	 * get site's id
	 * @return inter site's id
	 */
	public function getId(){
		if ($this->mPrefix === ''){
			return '';
		}
		if ( !empty($this->mId) ){
			return $this->mId;			
		}		
		$siteCache = self::getSiteCache();
		$this->loadFromRow();
		$siteCache->set($this->mPrefix, $this);	
		return $this->mId;
	}
	/**
	 * get site's language
	 * @return string
	 */
	public function getLang(){
		if ($this->mPrefix === ''){
			return '';
		}
		if ($this->mLang != ''){
			return $this->mLang;
		}
		$siteCache = self::getSiteCache();
		$this->loadFromRow();
		$siteCache->set($this->mPrefix, $this);		
		return $this->mLang;		
	}
	/**
	 * get site's create date
	 * @return string 0000-00-00 00:00:00
	 */
	public function getDate(){
		if ($this->mPrefix === ''){
			return '';
		}
		if ($this->mDate != ''){
			return $this->mDate;
		}
		$siteCache = self::getSiteCache();
		$this->loadFromRow();
		$siteCache->set($this->mPrefix, $this);			
		return $this->mDate;				
	}
	/**
	 * get site's best rank
	 * @return string
	 */
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
	/**
	 * get user on this site edit count
	 * @param object $user user object
	 * @return string
	 */
	public function getUserEditsCountOnSite( $user ){
		return UserStats::getSiteEditsCount( $user, $this->mPrefix );
	}
	/**
	 * set site prefix
	 * @param string $prefix ex:'lotr'
	 */
	protected function setPrefix($prefix){
		$this->mPrefix = $prefix;
	}

	/**
	 * get site's user group 
	 * @param  string $group group name
	 * @return array
	 */
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
	/**
	 * get site's rating
	 * @return string 'A'/'B'/'C'..
	 */
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
			),
			array(
				'site_prefix' => $this->mPrefix,
			),
			__METHOD__
		);
		if ($s != ''){
			$this->cache->set($key, $s->site_rating);
			return $s->site_rating;
		}
		//Since 2016.08.31, lowest rating is E.
		$this->cache->set($key, 'E');
		return 'E';

	}
	/**
	 * up site's rating
	 * @return string rating after change'A'/'B'/'C'..
	 */
	public function advanceRating(){
		$now = $this->getRating();
		$key = $this->getCustomKey('getRating');
		if ($now == 'NA'){
			$after = 'E';
		}
		if ($now == 'E'){
			$after = 'D';
		} 
		if ($now == 'D'){
			$after = 'C';
		} 
		if ($now == 'C'){
			$after = 'B';
		} 
		if ($now == 'B'){
			$after = 'A';
		} 
		if ($now == 'A'){
			$after = 'S';
		} 
		$dbw = wfGetDB(DB_MASTER);
		$s = $dbw->update(
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
	/**
	 * set site's rating
	 * @return string rating after change'A'/'B'/'C'..
	 */
	public function getPotentialRating(){
		// $stats = $this->getStats(false);
		// return $stats['articles'];
		$stats = $this->getStats(false);
		if (empty($this->getBestRank())){
			return 'NA';
		}
		if (($this->getBestRank() >= 10) and ($stats['articles'] > 1000) and ($stats['followers'] > 250) and ($stats['edits'] > 10000 ) ){
			$this->mSiteRating = 'A';
		} else {
			if ( ($stats['articles'] > 200) and ($stats['followers'] > 50) and ($stats['edits'] > 2000) ){
				$this->mSiteRating = 'B';
			} else {
				if ( ($stats['articles'] > 40) and ($stats['followers'] > 10) ){
					$this->mSiteRating = 'C';
				}
				else {
					if ( ($stats['articles'] > 10) ){
						$this->mSiteRating = 'D';
					}	else {
						$this->mSiteRating = 'E';
					}

				}					
			}					
		}
		return $this->mSiteRating;
	}
	/**
	 * getProperty
	 */
	public function getProperty($name){
		global $wgDefaultSiteProperty;
		$key = wfForeignMemcKey('huiji','', 'site_properties', 'getProperty', $this->mPrefix, $name );
		$result = $this->cache->get($key);
		if ($result){
			return $result;
		} else {
			$dbr = wfGetDB(DB_REPLICA, '', 'huiji');
			$s = $dbr->selectRow(
				'site_properties',
				'site_value',
				array(
					'site_id' => $this->getId(),
					'site_property' => $name,
				),
				__METHOD__
			);
			if (!empty($s)){
				$this->cache->set($key, $s->site_value);
				return $s->site_value;
			} else {
				$this->cache->set($key, $wgDefaultSiteProperty[$name]);
				return $wgDefaultSiteProperty[$name];
			}
		}

	}
	/**
	 * setProperty
	 */
	public function setProperty($name, $value){
		$key = wfForeignMemcKey('huiji','', 'site_properties', 'getProperty', $this->mPrefix, $name );
		$dbw = wfGetDB(DB_MASTER);
		$s = $dbw->upsert(
			'site_properties',
			array(
				'site_id' => $this->getId(),
				'site_property' => $name,
				'site_value' => $value,
			),
			array(
				'site_id' => $this->getId(),
				'site_property' => $name,
			),
			array(
				'site_value' => $value,
			),
			__METHOD__
		);
		$this->cache->set($key, $value);
	}
	/**
	 * get site's score from tb site_rank
	 * @return string site's score 
	 */
	public function getScore(){
		$key = wfForeignMemcKey('huiji','', 'site_rank', 'getScore', $this->mPrefix );
		$data = $this->cache->get($key);
		if ($data != ''){
			return $data;
		}
		$dbr = wfGetDB(DB_SLAVE);
		$s = $dbr->selectRow(
			'site_rank',
			array(
				'site_score'
			),
			array(
				'site_prefix' => $this->mPrefix,
				'site_rank_date' => date('Y-m-d',strtotime("-1 day")),
			),
			__METHOD__
		);
		if ( $s !== false ) {
			$res = $s->site_score;
		}
		$this->cache->set($key, $res, 60*60*24);
		return $res;
	}
	/**
	 * get Total amount of doantion received by this site.
	 * @return int
	 *
	 **/
	public function getDonationSum(){
		$siteArr = UserDonation::getDonationRankByPrefix($this->mPrefix, null);
		$sum = array_sum($siteArr);
		return $sum;
	}

	public function getDonationStub(){
		$month = date("Y-m", time());
		$siteArr = UserDonation::getDonationRankByPrefix($this->mPrefix, $month);
		$sum = array_sum($siteArr);
		if ($sum < $this->getDonationGoal($month)){
			$str = $sum.'/'.$this->getDonationGoal($month);
		} else {
			$str = $sum;
		}
		return $str;
	}

	/**
	 * check site is reach DonationGoal
	 * @param  string  $month ex: '2016-06'
	 * @return boolean        if reached return true; else return false
	 */
	public function hasMetDonationGoal( $month ){
		$key = wfForeignMemcKey( 'huiji', '' , 'site_month_donate_goal', $this->mPrefix, $month );
		$data = $this->cache->get( $key );
		if ( $data == null ) {
			$donateResult = UserDonation::getDonationInfoByPrefix( $this->mPrefix, $month );
			$monthDonate = array_sum($donateResult);
			$this->cache->set( $key, $monthDonate );
		}else{
			$monthDonate = $data;
		}
		
       	$goalDonate = $this->getDonationGoal( $month );

        if ( $monthDonate < $goalDonate ) {
        	return false;
        }else{
        	return true;
        }
	}
	/**
	 * get donation goal 
	 * @param  string  $month ex: '2016-06'
	 * @return int number of yuan needed
	 */
	public function getDonationGoal($month){

		$key = wfForeignMemcKey( 'huiji', '' , 'monthlyDonationGoal', $this->mPrefix, $month );
		$data = $this->cache->get($key);
		if ($data == null ){
			$day = new DateTime($month);
			$day->modify('first day of last month');
			$viewership = StatProvider::getStatsPerSite('view', $this->mPrefix, null, $day->format('Y-m-d'), $day->modify('last day of this month')->format('Y-m-d') );
			$goal = round($viewership * 0.0005);
			if ($goal < 5){
				$goal = 5;
			}	
			$this->cache->set($key, $goal);
			return $goal;
		} else {
			return $data;
		}
	}

} 
class RatingCompare{
	public static $NA = -1;
	public static $E = 0;
	public static $D = 1;
	public static $C = 2;
	public static $B = 3;
	public static $A = 4;
	public static $S = 5;

}
?>
