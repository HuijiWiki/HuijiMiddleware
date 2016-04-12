<?php
class HuijiUser {
	protected $mUser;
	protected $mFollowingSites;
	protected $mFollowingUsers;
	protected $mFollowers;
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
	private static function getUserCache() {
        if ( self::$userCache == null ) {
            self::$userCache = new HashBagOStuff( [ 'maxKeys' => self::CACHE_MAX ] );
		}
		return self::$userCache;
	}
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
	public static function newFromId($userId){
		$cache = self::getUserCache();
		$u = $cache->get($userId);
		if ($u){
			return $u;
		}
		$u = new HuijiUser();
		$u->mUser = User::NewFromId($userId);
		return $u;
	}
	public static function newFromName($userName){
		$cache = self::getUserCache();
		$u = $cache->get(User::IdFromName($userName));
		if ($u){
			return $u;
		}
		$u = new HuijiUser();
		$u->mUser = User::NewFromName($userName);
		return $u;
	}
	public function getUser(){
		return $this->$mUser;
	}
	public function isFollowedBy( $user ){
		$uuf = new UserUserFollow();
		return $uuf->checkUserUserFollow($user, $this->mUser);
	}
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
	public function getFollowerCount(){
		//Trust Cache
		if ($this->mFollowers != ''){
			return count($this->mFollowers);
		}
		return UserUserFollow::getFollowerCount( $this->mUser );
	}
	public function getFollowingUsersCount(){
		if ($this->mFollowingUsers != ''){
			return count($this->mFollowingUsers);
		}
		return UserUserFollow::getFollowingCount( $this->mUser );
	}
	public function getFollowingSitesCount(){
		if ($this->mFollowingSites != ''){
			return count($this->mFollowingSites);
		}
		return UserSiteFollow::getFollowingCount( $this->mUser );
	}
	public function follow($target){
		if ( $target instanceof Site ){
			if (!$target->exists()){
				return false;
			}
			$usf = new UserSiteFollow();
			return $usf->addUserSiteFollow($this->mUser, $target->getPrefix());
		} elseif( $target instanceof User ) {
			if ($target->getId() == 0){
				return false;
			} 
			if ($target->getId() == $this->mUser->getId()){
				return false;
			}
			$uuf = new UserUserFollow();
			return $uuf->addUserUserFollow($this->mUser, $target);
		} else {
			return false;
		}		
	}
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
	public function getFollowers(){
		$uuf = new UserUserFollow();
		$this->mFollowers = $uuf->getFollowList($this->mUser, 2);
		$cache = self::getUserCache();
		$cache->set($this->mUser->getId(), $this);
		return $this->mFollowers;

	}
	public function getFollowingUsers(){
		$uuf = new UserUserFollow();
		$this->mFollowingUsers = $uuf->getFollowList($this->mUser, 1);
		//$this->mFollowingUsers = UserUserFollow::getFollowedByUser($this->mUser);
		$cache = self::getUserCache();
		$cache->set($this->mUser->getId(), $this);	
		return $this->mFollowingUsers;

	}
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
	public function getAvatar($size = 'l'){
		return new wAvatar($this->mUser->getId(), $size);
	}
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
	public function getLevel(){
		$stats = $this->getStats();
		return new UserLevel($stats['points']);
	}
	public function hasUserGift($giftId){
		$ug = new UserGifts($this->mUser->getName());
		$hasUserGift = $ug->doesUserHaveGiftOfTheSameGiftType($this->mUser->getId(), $giftId);
		return $hasUserGift;
	}
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
	public function getProfile(){
		$us = new UserStatus($this->mUser);
		$json = $us->getAll();
		$obj = json_decode($json);
		return (array)$obj;
	}
	//if is_open==0 select all
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