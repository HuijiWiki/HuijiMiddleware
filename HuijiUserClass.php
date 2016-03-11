<?php
class HuijiUser {
	protected $mUser;
	private static $userCache;
	const CACHE_MAX = 1000;
	function __construct(){
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
	};
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
			return $uuf->checkUserUserFollow($$this->mUser, $target);
		} else {
			return false;
		}
	}
	public function getFollowerCount(){
		return UserUserFollow::getFollowerCount( $this->mUser );
	}
	public function getFollowingCount(){
		return UserUserFollow::getFollowingCount( $this->mUser );
	}
	public function getSiteFollowingCount(){
		return UserUserFollow::getFollowingCount( $this->mUser );
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
			return $uuf->addUserUserFollow($$this->mUser, $target);
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
			return $uuf->deleteUserUserFollow($$this->mUser, $target);
		} else {
			return false;
		}			
	}
	public function getAvatar($size = 'l'){
		return new wAvatar($this->mUser->getId(), $size);
	}
}