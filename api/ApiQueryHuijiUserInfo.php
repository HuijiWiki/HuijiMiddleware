<?php
/**
 * Query module to get infomation about the currently logged-in user
 *
 * @ingroup API 
 */
class ApiQueryHuijiUserInfo extends ApiQueryUserInfo {
	const WL_UNREAD_LIMIT = 1000;

	private $params = [];
	private $props = [];

	public function execute() {
		$this->params = $this->extractRequestParams();
		$result = $this->getResult();

		if ( !is_null( $this->params['prop'] ) ) {
			$this->prop = array_flip( $this->params['prop'] );
		}

		$r = $this->getCurrentUserInfo();
		$result->addValue( 'query', $this->getModuleName(), $r );
	}
	/**
	 * Query user info based on filters.
	 * 
	 * 
	 */
	protected function getCurrentUserInfo() {
		$user = $this->getUser();
		$huijiUser = HuijiUser::newFromUser($user);
		$vals = [];
		$vals['id'] = intval( $user->getId() );
		$vals['name'] = $user->getName();

		if ( $user->isAnon() ) {
			$vals['anon'] = true;
		}

		if ( isset( $this->prop['blockinfo'] ) && $user->isBlocked() ) {
			$vals = array_merge( $vals, self::getBlockInfo( $user->getBlock() ) );
		}

		if ( isset( $this->prop['hasmsg'] ) ) {
			$vals['messages'] = $user->getNewtalk();
		}

		if ( isset( $this->prop['groups'] ) ) {
			$vals['groups'] = $user->getEffectiveGroups();
			ApiResult::setArrayType( $vals['groups'], 'array' ); // even if empty
			ApiResult::setIndexedTagName( $vals['groups'], 'g' ); // even if empty
		}

		if ( isset( $this->prop['implicitgroups'] ) ) {
			$vals['implicitgroups'] = $user->getAutomaticGroups();
			ApiResult::setArrayType( $vals['implicitgroups'], 'array' ); // even if empty
			ApiResult::setIndexedTagName( $vals['implicitgroups'], 'g' ); // even if empty
		}

		if ( isset( $this->prop['rights'] ) ) {
			// User::getRights() may return duplicate values, strip them
			$vals['rights'] = array_values( array_unique( $user->getRights() ) );
			ApiResult::setArrayType( $vals['rights'], 'array' ); // even if empty
			ApiResult::setIndexedTagName( $vals['rights'], 'r' ); // even if empty
		}

		if ( isset( $this->prop['changeablegroups'] ) ) {
			$vals['changeablegroups'] = $user->changeableGroups();
			ApiResult::setIndexedTagName( $vals['changeablegroups']['add'], 'g' );
			ApiResult::setIndexedTagName( $vals['changeablegroups']['remove'], 'g' );
			ApiResult::setIndexedTagName( $vals['changeablegroups']['add-self'], 'g' );
			ApiResult::setIndexedTagName( $vals['changeablegroups']['remove-self'], 'g' );
		}

		if ( isset( $this->prop['options'] ) ) {
			$vals['options'] = $user->getOptions();
			$vals['options'][ApiResult::META_BC_BOOLS] = array_keys( $vals['options'] );
		}

		if ( isset( $this->prop['preferencestoken'] ) ) {
			$p = $this->getModulePrefix();
			$this->setWarning(
				"{$p}prop=preferencestoken has been deprecated. Please use action=query&meta=tokens instead."
			);
		}
		if ( isset( $this->prop['preferencestoken'] ) &&
			!$this->lacksSameOriginSecurity() &&
			$user->isAllowed( 'editmyoptions' )
		) {
			$vals['preferencestoken'] = $user->getEditToken( '', $this->getMain()->getRequest() );
		}

		if ( isset( $this->prop['editcount'] ) ) {
			// use intval to prevent null if a non-logged-in user calls
			// api.php?format=jsonfm&action=query&meta=userinfo&uiprop=editcount
			$vals['editcount'] = intval( $user->getEditCount() );
		}

		if ( isset( $this->prop['ratelimits'] ) ) {
			$vals['ratelimits'] = $this->getRateLimits();
		}

		if ( isset( $this->prop['realname'] ) &&
			!in_array( 'realname', $this->getConfig()->get( 'HiddenPrefs' ) )
		) {
			$vals['realname'] = $user->getRealName();
		}

		if ( $user->isAllowed( 'viewmyprivateinfo' ) ) {
			if ( isset( $this->prop['email'] ) ) {
				$vals['email'] = $user->getEmail();
				$auth = $user->getEmailAuthenticationTimestamp();
				if ( !is_null( $auth ) ) {
					$vals['emailauthenticated'] = wfTimestamp( TS_ISO_8601, $auth );
				}
			}
		}

		if ( isset( $this->prop['registrationdate'] ) ) {
			$regDate = $user->getRegistration();
			if ( $regDate !== false ) {
				$vals['registrationdate'] = wfTimestamp( TS_ISO_8601, $regDate );
			}
		}

		if ( isset( $this->prop['acceptlang'] ) ) {
			$langs = $this->getRequest()->getAcceptLang();
			$acceptLang = [];
			foreach ( $langs as $lang => $val ) {
				$r = [ 'q' => $val ];
				ApiResult::setContentValue( $r, 'code', $lang );
				$acceptLang[] = $r;
			}
			ApiResult::setIndexedTagName( $acceptLang, 'lang' );
			$vals['acceptlang'] = $acceptLang;
		}

		if ( isset( $this->prop['unreadcount'] ) ) {
			$store = MediaWikiServices::getInstance()->getWatchedItemStore();
			$unreadNotifications = $store->countUnreadNotifications(
				$user,
				self::WL_UNREAD_LIMIT
			);

			if ( $unreadNotifications === true ) {
				$vals['unreadcount'] = self::WL_UNREAD_LIMIT . '+';
			} else {
				$vals['unreadcount'] = $unreadNotifications;
			}
		}

		if ( isset( $this->prop['centralids'] ) ) {
			$vals += self::getCentralUserInfo(
				$this->getConfig(), $this->getUser(), $this->params['attachedwiki']
			);
		}

		if ( isset( $this->prop['designation']) ){
			$vals['designation'] = $huijiUser->getDesignation(false, true);
		}
		if ( isset( $this->prop['avatar']) ){
			$vals['avatar'] = array(
								"l" => $huijiUser->getAvatar( 'l' )->getAvatarUrlPath(),
								"ml" => $huijiUser->getAvatar( 'ml' )->getAvatarUrlPath(),
								"m" => $huijiUser->getAvatar( 'm' )->getAvatarUrlPath(),
								"s" => $huijiUser->getAvatar( 's' )->getAvatarUrlPath(),
								);
		}
		if ( isset( $this->prop['gender']) ){
			$vals['gender'] = $huijiUser->getOption('gender');
		}		
		if ( isset( $this->prop['status']) ){
			$us = new UserStatus($user);
			$vals['status'] = $us->getStatus();
		}
		if ( isset( $this->prop['birthday']) ){
			$us = new UserStatus($user);
			$vals['birthday'] = $us->getBirthday();
		}		
		if ( isset( $this->prop['city']) ){
			$us = new UserStatus($user);
			$vals['city'] = $us->getCity();
		}	
		if ( isset( $this->prop['province']) ){
			$us = new UserStatus($user);
			$vals['province'] = $us->getProvince();
		}	
		if ( isset( $this->prop['followingcount']) ){
			$vals['followingcount']= $huijiUser->getFollowingUsersCount();
		}	
		if ( isset( $this->prop['followercount']) ){
			$vals['followercount'] = $huijiUser->getFollowerCount();
		}	
		if ( isset( $this->prop['followingsitescount']) ){
			$vals['followingsitescount'] = $huijiUser->getFollowingSitesCount();
		}
		if ( isset( $this->prop['stats']) ){
			$vals['stats'] = $huijiUser->getStats();
		}	
		if ( isset( $this->prop['level']) ){
			$vals['level']= $huijiUser->getLevel()->getLevelNumber();
		}
		if ( isset( $this->prop['followingsites'])) {
			$vals['followingsites'] = $huijiUser->getFollowingSites( true );
		}
		return $vals;
	}

	public function getAllowedParams() {
		return [
			'prop' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
					'blockinfo',
					'hasmsg',
					'groups',
					'implicitgroups',
					'rights',
					'changeablegroups',
					'options',
					'preferencestoken',
					'editcount',
					'ratelimits',
					'email',
					'realname',
					'acceptlang',
					'registrationdate',
					'unreadcount',
					'centralids',
					'designation',
					'avatar',
					'gender',
					'status',
					'province',
					'city',
					'birthday',
					'followingcount',
					'followercount',
					'stats',
					'level',
					'followingsites'
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [
					'unreadcount' => [
						'apihelp-query+userinfo-paramvalue-prop-unreadcount',
						self::WL_UNREAD_LIMIT - 1,
						self::WL_UNREAD_LIMIT . '+',
					],
				],
			],
			'attachedwiki' => null,
		];
	}

}
