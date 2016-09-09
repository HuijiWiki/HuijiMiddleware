<?php
/**
 * HuijiUser calss
 */
class HuijiUser {
	protected $mUser;
	protected $mFollowingSites;
	protected $mFollowingUsers;
	protected $mFollowers;
	protected $mDesignation;
	protected $mDesignationPrefix;
	protected $mDesignationSuffix;
	protected $mDesignationPrefixPlaintext;
	protected $mDesignationSuffixPlaintext;
	private static $userCache;
	const CACHE_MAX = 1000;
	function __construct(){
	}
	/* Decorator functions */
    public function __call($method, $args) {
        return call_user_func_array(array($this->mUser, $method), $args);
    }

    public function __get($key) {
        return $this->mUser->$key;
    }

    public function __set($key, $val) {
        return $this->mUser->$key = $val;
    }
    /**
     * get User from Cache
     * @return object
     */
	private static function getUserCache() {
        if ( self::$userCache == null ) {
            self::$userCache = new HashBagOStuff( [ 'maxKeys' => self::CACHE_MAX ] );
		}
		return self::$userCache;
	}
	/**
	 * get user object by userObj
	 * @param  object $user
	 * @return object user object
	 */
	public static function newFromUser($user){
		$cache = self::getUserCache();
		$u = $cache->get($user->getId());
		if ($u){
			return $u;
		}
		$u = new HuijiUser();
		$u->mUser = $user;
		return $u;
	}
	/**
	 * get user object by userId
	 * @param  int $userId
	 * @return object user object
	 */
	public static function newFromId($userId){
		$cache = self::getUserCache();
		$u = $cache->get($userId);
		if ($u){
			return $u;
		}
		$u = new HuijiUser();
		$u->mUser = User::NewFromId($userId);
		if ($u->mUser == null){
			return null;
		}
		return $u;
	}
	/**
	 * get user object by userName
	 * @param  string $userName
	 * @return object user object
	 */
	public static function newFromName($userName){
		$cache = self::getUserCache();
		$u = $cache->get(User::IdFromName($userName));
		if ($u){
			return $u;
		}
		$u = new HuijiUser();
		$u->mUser = User::NewFromName($userName);
		if ($u->mUser == null){
			return null;
		}
		return $u;
	}
	/**
	 * initiate a user instance based on the open id from 
	 * external oauth services, such as qq.
	 * @param string $openId
	 * @param string $type
	 * @return mixed false if there is not user correspond to 
	 *                id. Otherwise return a HuijiUser object.
	 */
	public static function newFromOpenId($openId, $type){
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'oauth',
			array(
				'user_id'
			),
			array(
				'open_id' => $openId,
				'o_type' => $type
			),
			__METHOD__
		);
		if ($res){
			foreach($res as $value){
				return self::newFromId($value->user_id);
			}
		}
		return false;
	}


	/**
	 * determine if this user has an linked account of given type.
	 * @param string $type
	 * @return boolean
	 */
	public function hasLinkedAccount($type){
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'oauth',
			array(
				'open_id'
			),
			array(
				'user_id' => $this->mId,
				'o_type' => $type
			),
			__METHOD__
		);
		if ($res){
			foreach($res as $value){
				return true;
			}
		}
		return false;	
	}

	/**
	 * get an array of openIds attached to this user.
	 * @return an associate array with type as key.
	 *
	 */
	public function getOpenIds(){
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(
			'oauth',
			array(
				'open_id',
				'o_type'
			),
			array(
				'user_id' => $this->mId,
			),
			__METHOD__
		);
		$ret = [];
		if ($res){
			foreach ($res as $value) {
				$ret[$value->o_type] = $value->open_id;
			}
		}
		return $ret;
	}
	/**
	 * Remove linked account in database
	 * @param string $openId the given id.
	 * @param string $type
	 */
	public function unlinkAccount($openId, $type){
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->delete(
			'oauth',
			array(
				'open_id' => $openId,
				'o_type' => $type,
				'user_id' => $this->mId,
			),
			__METHOD__
		);
	}
	/**
	 * add linked account in database
	 * @param string $openId the given id.
	 * @param string $type
	 */
	public function linkAccount($openId, $type){
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->insert(
			'oauth',
			array(
				'open_id' => $openId,
				'o_type' => $type,
				'user_id' => $this->mId,
			),
			__METHOD__
		);
	}
	/**
	 * determine if there is an associate user account in database
	 * @param string $openId the given id.
	 * @param string $type
	 */
	public static function isOpenIdFree( $openId, $type ){
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'oauth',
			array(
				'user_id'
			),
			array(
				'open_id' => $openId,
				'o_type' => $type
			),
			__METHOD__
		);
		if ($res){
			foreach($res as $value){
				return false;
			}
		}
		return true;	
	}
	/**
	 * get current user obj
	 * @return object user object
	 */
	public function getUser(){
		return $this->mUser;
	}
	/**
	 * check target user is following current user
	 * @param  object  $user target user
	 * @return boolean
	 */
	public function isFollowedBy( $user ){
		$uuf = new UserUserFollow();
		return ($uuf->checkUserUserFollow($user, $this->mUser)!==false);
	}
	/**
	 * check current is following target user
	 * @param  object  $target
	 * @return boolean 
	 */
	public function isFollowing( $target ){
		if ( $target instanceof Site ){
			$usf = new UserSiteFollow();
			return $usf->checkUserSiteFollow($this->mUser, $target->getPrefix());
		} elseif( $target instanceof User ) {
			$uuf = new UserUserFollow();
			return $uuf->checkUserUserFollow($this->mUser, $target);
		} else {
			return false;
		}
	}
	/**
	 * get total number of current user's follower
	 * @return string the number
	 */
	public function getFollowerCount(){
		//Trust Cache
		if ($this->mFollowers != ''){
			return count($this->mFollowers);
		}
		return UserUserFollow::getFollowerCount( $this->mUser );
	}
	/**
	 * get current user Following Users Count number 
	 * @return string the number
	 */
	public function getFollowingUsersCount(){
		if ($this->mFollowingUsers != ''){
			return count($this->mFollowingUsers);
		}
		return UserUserFollow::getFollowingCount( $this->mUser );
	}
	/**
	 * get current user Following sites Count number 
	 * @return string the number
	 */
	public function getFollowingSitesCount(){
		if ($this->mFollowingSites != ''){
			return count($this->mFollowingSites);
		}
		return UserSiteFollow::getFollowingCount( $this->mUser );
	}
	/**
	 * add follow user or site
	 * @param  object $target user object or site object
	 * @return boolean
	 */
	public function follow($target){
		if ( $target instanceof Site ){
			if (!$target->exists()){
				return false;
			}
			$usf = new UserSiteFollow();
			return $usf->addUserSiteFollow($this->mUser, $target->getPrefix());
		} elseif( $target instanceof User ) {
			// if ($target->getId() == 0){
			// 	return false;
			// } 
			// if ($target->getId() == $this->mUser->getId()){
			// 	return false;
			// }
			$uuf = new UserUserFollow();
			return $uuf->addUserUserFollow($this->mUser, $target);
		} else {
			return false;
		}		
	}
	/**
	 * delete follow user or site
	 * @param  object $target user object or site object
	 * @return boolean
	 */
	public function unfollow($target){
		if ( $target instanceof Site ){
			$usf = new UserSiteFollow();
			return $usf->deleteUserSiteFollow($this->mUser, $target->getPrefix());
		} elseif( $target instanceof User ) {
			$uuf = new UserUserFollow();
			return $uuf->deleteUserUserFollow($this->mUser, $target);
		} else {
			return false;
		}			
	}
	/**
	 * get users who follow the current user
	 * @return array
	 */
	public function getFollowers(){
		if ($this->mFollowers != ''){
			return $this->mFollowers;
		}		
		$uuf = new UserUserFollow();
		$this->mFollowers = $uuf->getFollowList($this->mUser, 2);
		$cache = self::getUserCache();
		$cache->set($this->mUser->getId(), $this);
		return $this->mFollowers;

	}
	/**
	 * get the current user's following users
	 * @return array
	 */
	public function getFollowingUsers(){
		if ($this->mFollowingUsers != ''){
			return $this->mFollowingUsers;
		}
		$uuf = new UserUserFollow();
		$this->mFollowingUsers = $uuf->getFollowList($this->mUser, 1);
		//$this->mFollowingUsers = UserUserFollow::getFollowedByUser($this->mUser);
		$cache = self::getUserCache();
		$cache->set($this->mUser->getId(), $this);	
		return $this->mFollowingUsers;

	}
	/**
	 * get user Following Sites
	 * @param  boolean $expanded      if true, return detail info about the sites 
	 * @param  object  $viewPointUser if this is not null, get this user's following site
	 * @return array   sites info
	 */
	public function getFollowingSites($expanded = false, $viewPointUser = null){
		$this->mFollowingSites = UserSiteFollow::getFullFollowedSites($this->mUser);
		$cache = self::getUserCache();
		$cache->set($this->mUser->getId(), $this);
		if (!$expanded){
			return $this->mFollowingSites;
		} else {
			if ($viewPointUser != null){
				$viewPointHuijiUser = self::newFromUser($viewPointUser);
				$vSites = $viewPointHuijiUser->getFollowingSites();
				return UserSiteFollow::sortFollowedSiteWithDetails($this->mUser, $this->mFollowingSites, $vSites);
			} else {
				return UserSiteFollow::sortFollowedSiteWithDetails($this->mUser, $this->mFollowingSites, null);
			}
		}
	}
	/**
	 * get user avatar
	 * @param  string $size avatar 's' for small, 'm' for medium, 'ml' for medium-large and 'l' for large
	 * @return object      avatar object
	 */
	public function getAvatar($size = 'l'){
		return new wAvatar($this->mUser->getId(), $size);
	}
	/**
	 * get user stats
	 * @param  string $prefix if $prefix is null, return this user stats of all sites, else return the prefix stats
	 * @return array
	 */
	public function getStats( $prefix='' ){
		$statsObj = new UserStats($this->mUser->getId(), $this->mUser->getName());
		if ( $prefix === '' ) {
			return $statsObj->getUserStats();
		}else{
			$result = array();
			$result['edits'] = $statsObj->getSiteEditsCount( $this->mUser, $prefix );
			return $result;
		}
		
		// getSiteEditsCount( $user, $prefix )
	}
	/**
	 * get user level
	 * @return object
	 */
	public function getLevel(){
		$stats = $this->getStats();
		return new UserLevel($stats['points']);
	}
	/**
	 * check the current user is have this gift
	 * @param  inter  $giftId gift's id
	 * @return boolean 
	 */
	public function hasUserGift($giftId){
		$ug = new UserGifts($this->mUser->getName());
		$hasUserGift = $ug->doesUserHaveGiftOfTheSameGiftType($this->mUser->getId(), $giftId);
		return $hasUserGift;
	}
	/**
	 * check the current user is have this system gift
	 * @param  inter  $giftId gift's id
	 * @return boolean 
	 */
	public function hasSystemGift($giftId){
		$usg = new UserSystemGifts($this->mUser->getName());
		$hasSystemGift = $ug->doesUserHaveGiftOfTheSameGiftType($this->mUser->getId(), $giftId);	
		return $hasSystemGift;	
	}
	// public function sendUserGift($userIdTo, $giftId, $type, $message){
	// 	$ug = new UserGifts($this->mUser->getName());
	// 	$ug->sendGift($userIdTo, $giftId, $type, $message);
	// }
	// public function sendSystemGift( $giftId ){
	// 	$usg = new UserSystemGifts($this->mUser->getName());
	// 	$usg->sendSystemGift( $giftId );

	// }
	/**
	 * get user profile info
	 * @return array
	 */
	public function getProfile(){
		$us = new UserStatus($this->mUser);
		$json = $us->getAll();
		$obj = json_decode($json);
		return (array)$obj;
	}
	/** 
	 * get aggregate Desigantion from cache
	 * @param boolean default to false. If true, split the desination in an array with two element, prefix and suffix.
	 * @return string/array desination;
	 *
	 */
	public function getDesignation($splited = false, $plaintext = false){
		global $wgMemc;
		if ($this->mUser == ''){
			return '';
		}
		if ($this->mDesignation !== null){
			if ($splited){
				return array($this->mDesignationPrefix, $this->mDesignationSuffix);
			}
			return $this->mDesignation;
		} 
		$cache = self::getUserCache();
		$dbr = wfGetDB(DB_SLAVE);
		$key = wfForeignMemcKey('huiji', '', 'user_title', 'system_gift', $this->mUser->getId());
		$prefixResult = $wgMemc->get($key);
		if ($prefixResult == ''){
			$prefixResult = [];
			$row = $dbr->select(
					'user_title',
					array(
						'title_content',
					),
					array(
						'title_from' => 'system_gift',
						'user_to_id' => $this->mUser->getId(),
						'is_open' => '2'
					),
					__METHOD__
				);
			if ( $row ) {
				foreach ($row as $key => $value) {
					$prefixResult[] = $value->title_content;
				}
			}
			$wgMemc->set($key, $prefixResult, 60 * 60 * 24 * 90);
		}
		$key2 = wfForeignMemcKey('huiji', '', 'user_title', 'gift', $this->mUser->getId());
		$suffixResult = $wgMemc->get($key2);
		if ($suffixResult == ''){
			$suffixResult = [];
			$row = $dbr->select(
					'user_title',
					array(
						'title_content',
					),
					array(
						'title_from' => 'gift',
						'user_to_id' => $this->mUser->getId(),
						'is_open' => '2'
					),
					__METHOD__
				);
			if ( $row ) {
				foreach ($row as $key => $value) {
					$suffixResult[] = $value->title_content;
				}
			}
			$wgMemc->set($key2, $suffixResult, 60 * 60 * 24 * 90);
		}
		$prefix = implode(',', $prefixResult );
		$suffix = implode(',', $suffixResult );
		if (count($prefixResult) > 0){
			$this->mDesignationPrefixPlaintext = htmlspecialchars($prefix);
			$this->mDesignationPrefix = '<span class="hidden-xs hidden-sm designation-prefix">'.htmlspecialchars($prefix).'</span>';
			$this->mDesignation .= $this->mDesignationPrefix;
		}
		if ( count($suffixResult) > 0 ){
			$this->mDesignationSuffixPlaintext = htmlspecialchars($suffix);
			$this->mDesignationSuffix = '<span class="hidden-xs hidden-sm designation-suffix">'.htmlspecialchars($suffix).'</span>';
			$this->mDesignation .= $this->mDesignationSuffix;
		}
		$this->mDesignation .= $this->mUser->getName();
		$cache->set($this->mUser->getId(), $this);
		if ($splited && !$plaintext){
			return array($this->mDesignationPrefix, $this->mDesignationSuffix);
		}
		if ($splited && $plaintext){
			return array($this->mDesignationPrefixPlaintext, $this->mDesignationSuffixPlaintext);
		}
		if (!$splited && $plaintext){
			return $this->mDesignationPrefixPlaintext.$this->mDesignationSuffixPlaintext.$this->mUser->getName();
		}
		return $this->mDesignation;
	}

	/**
	 * get User Designation (Don't use this externally, cuz there is no cache)
	 * @param  string $title_from the title from(gift or system_gift)
	 * @param  string $is_open    if is_open==0 select all, else select is_open=2
	 * @return array
	 */
	public function getUserDesignation( $title_from, $is_open='1' ){
		$dbr = wfGetDB( DB_SLAVE );
		$params = $result = array();
		if ( $is_open != 0 ) {
			$params = array(
					'is_open' => $is_open,
					'user_to_id' => $this->mUser->getId(),
					'title_from' => $title_from
				);
		}else{
			$params = array(
					'user_to_id' => $this->mUser->getId(),
					'title_from' => $title_from
				);
		}
		$row = $dbr -> select(
				'user_title',
				array(
					'ut_id',
					'gift_id',
					'title_content',
					'is_open' 
				),
				$params,
				__METHOD__
			);
		if ( $row ) {
			foreach ($row as $key => $value) {
				$result[] = array(
						'ut_id' => $value->ut_id,
						'gift_id' => $value->gift_id,
						'title_content' => $value->title_content,
						'is_open' => $value->is_open,
						'title_from' => $title_from,
					);
			}
		}
		return $result;
	}

}
