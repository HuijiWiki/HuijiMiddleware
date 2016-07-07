<?php
class ApiQueryHuijiUsers extends ApiQueryUsers {
	private $tokenFunctions, $prop;

	/**
	 * Properties whose contents does not depend on who is looking at them. If the usprops field
	 * contains anything not listed here, the cache mode will never be public for logged-in users.
	 * @var array
	 */
	protected static $publicProps = [
		// everything except 'blockinfo' which might show hidden records if the user
		// making the request has the appropriate permissions
		'groups',
		'implicitgroups',
		'rights',
		'editcount',
		'registration',
		'emailable',
		'gender',
		'centralids',
		'cancreate',
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
	];


	/**
	 * Get an array mapping token names to their handler functions.
	 * The prototype for a token function is func($user)
	 * it should return a token or false (permission denied)
	 * @deprecated since 1.24
	 * @return array Array of tokenname => function
	 */
	protected function getTokenFunctions() {
		// Don't call the hooks twice
		if ( isset( $this->tokenFunctions ) ) {
			return $this->tokenFunctions;
		}

		// If we're in a mode that breaks the same-origin policy, no tokens can
		// be obtained
		if ( $this->lacksSameOriginSecurity() ) {
			return [];
		}

		$this->tokenFunctions = [
			'userrights' => [ 'ApiQueryUsers', 'getUserrightsToken' ],
		];
		Hooks::run( 'APIQueryUsersTokens', [ &$this->tokenFunctions ] );

		return $this->tokenFunctions;
	}

	public function execute() {
		$params = $this->extractRequestParams();

		if ( !is_null( $params['prop'] ) ) {
			$this->prop = array_flip( $params['prop'] );
		} else {
			$this->prop = [];
		}

		$users = (array)$params['users'];
		$goodNames = $done = [];
		$result = $this->getResult();
		// Canonicalize user names
		foreach ( $users as $u ) {
			$n = User::getCanonicalName( $u );
			if ( $n === false || $n === '' ) {
				$vals = [ 'name' => $u, 'invalid' => true ];
				$fit = $result->addValue( [ 'query', $this->getModuleName() ],
					null, $vals );
				if ( !$fit ) {
					$this->setContinueEnumParameter( 'users',
						implode( '|', array_diff( $users, $done ) ) );
					$goodNames = [];
					break;
				}
				$done[] = $u;
			} else {
				$goodNames[] = $n;
			}
		}

		$result = $this->getResult();

		if ( count( $goodNames ) ) {
			$this->addTables( 'user' );
			$this->addFields( User::selectFields() );
			$this->addWhereFld( 'user_name', $goodNames );

			$this->showHiddenUsersAddBlockInfo( isset( $this->prop['blockinfo'] ) );

			$data = [];
			$res = $this->select( __METHOD__ );
			$this->resetQueryParams();

			// get user groups if needed
			if ( isset( $this->prop['groups'] ) || isset( $this->prop['rights'] ) ) {
				$userGroups = [];

				$this->addTables( 'user' );
				$this->addWhereFld( 'user_name', $goodNames );
				$this->addTables( 'user_groups' );
				$this->addJoinConds( [ 'user_groups' => [ 'INNER JOIN', 'ug_user=user_id' ] ] );
				$this->addFields( [ 'user_name', 'ug_group' ] );
				$userGroupsRes = $this->select( __METHOD__ );

				foreach ( $userGroupsRes as $row ) {
					$userGroups[$row->user_name][] = $row->ug_group;
				}
			}

			foreach ( $res as $row ) {
				// create user object and pass along $userGroups if set
				// that reduces the number of database queries needed in User dramatically
				if ( !isset( $userGroups ) ) {
					$user = User::newFromRow( $row );
				} else {
					if ( !isset( $userGroups[$row->user_name] ) || !is_array( $userGroups[$row->user_name] ) ) {
						$userGroups[$row->user_name] = [];
					}
					$user = User::newFromRow( $row, [ 'user_groups' => $userGroups[$row->user_name] ] );
				}
				$huijiUser = HuijiUser::newFromUser($user);
				$name = $user->getName();

				$data[$name]['userid'] = $user->getId();
				$data[$name]['name'] = $name;

				if ( isset( $this->prop['editcount'] ) ) {
					$data[$name]['editcount'] = $user->getEditCount();
				}

				if ( isset( $this->prop['registration'] ) ) {
					$data[$name]['registration'] = wfTimestampOrNull( TS_ISO_8601, $user->getRegistration() );
				}

				if ( isset( $this->prop['groups'] ) ) {
					$data[$name]['groups'] = $user->getEffectiveGroups();
				}

				if ( isset( $this->prop['implicitgroups'] ) ) {
					$data[$name]['implicitgroups'] = $user->getAutomaticGroups();
				}

				if ( isset( $this->prop['rights'] ) ) {
					$data[$name]['rights'] = $user->getRights();
				}
				if ( $row->ipb_deleted ) {
					$data[$name]['hidden'] = true;
				}
				if ( isset( $this->prop['blockinfo'] ) && !is_null( $row->ipb_by_text ) ) {
					$data[$name]['blockid'] = (int)$row->ipb_id;
					$data[$name]['blockedby'] = $row->ipb_by_text;
					$data[$name]['blockedbyid'] = (int)$row->ipb_by;
					$data[$name]['blockedtimestamp'] = wfTimestamp( TS_ISO_8601, $row->ipb_timestamp );
					$data[$name]['blockreason'] = $row->ipb_reason;
					$data[$name]['blockexpiry'] = $row->ipb_expiry;
				}

				if ( isset( $this->prop['emailable'] ) ) {
					$data[$name]['emailable'] = $user->canReceiveEmail();
				}

				if ( isset( $this->prop['gender'] ) ) {
					$gender = $user->getOption( 'gender' );
					if ( strval( $gender ) === '' ) {
						$gender = 'unknown';
					}
					$data[$name]['gender'] = $gender;
				}

				if ( isset( $this->prop['centralids'] ) ) {
					$data[$name] += ApiQueryUserInfo::getCentralUserInfo(
						$this->getConfig(), $user, $params['attachedwiki']
					);
				}

				if ( isset( $this->prop['designation']) ){
					$data[$name]['designation'] = $huijiUser->getDesignation(false, true);
				}
				if ( isset( $this->prop['avatar']) ){
					$data[$name]['avatar'] = array(
										"l" => $huijiUser->getAvatar( 'l' )->getAvatarUrlPath(),
										"ml" => $huijiUser->getAvatar( 'ml' )->getAvatarUrlPath(),
										"m" => $huijiUser->getAvatar( 'm' )->getAvatarUrlPath(),
										"s" => $huijiUser->getAvatar( 's' )->getAvatarUrlPath(),
										);
				}
				if ( isset( $this->prop['status']) ){
					$us = new UserStatus($user);
					$data[$name]['status'] = $us->getStatus();
				}
				if ( isset( $this->prop['birthday']) ){
					$us = new UserStatus($user);
					$data[$name]['birthday'] = $us->getBirthday();
				}		
				if ( isset( $this->prop['city']) ){
					$us = new UserStatus($user);
					$data[$name]['city'] = $us->getCity();
				}	
				if ( isset( $this->prop['province']) ){
					$us = new UserStatus($user);
					$data[$name]['province'] = $us->getProvince();
				}	
				if ( isset( $this->prop['followingcount']) ){
					$data[$name]['followingcount']= $huijiUser->getFollowingUsersCount();
				}	
				if ( isset( $this->prop['followercount']) ){
					$data[$name]['followercount'] = $huijiUser->getFollowerCount();
				}	
				if ( isset( $this->prop['followingsitescount']) ){
					$data[$name]['followingsitescount'] = $huijiUser->getFollowingSitesCount();
				}
				if ( isset( $this->prop['stats']) ){
					$data[$name]['stats'] = $huijiUser->getStats();
				}	
				if ( isset( $this->prop['level']) ){
					$data[$name]['level']= $huijiUser->getLevel()->getLevelNumber();
				}	
				if ( isset( $this->prop['followingsites'])) {
					$vals['followingsites'] = $huijiUser->getFollowingSites( true );
				}			
				if ( isset( $this->prop['context'] ) ){
					if ($this->getUser()->isLoggedIn()){
						$data[$name]['context']['followedbyme'] = ($huijiUser->isFollowedBy($this->getUser()))?"true":"false";  
						$me = HuijiUser::newFromUser($this->getUser());
						$iAmFollowing = $me->getFollowingUsers();
						$heIsFollowing = $huijiUser->getFollowingUsers();
						$bothfollowing = [];
						if($heIsFollowing != null){
							foreach ($heIsFollowing as $someUser) {
								//Php is awesome...
								if(array_search(
									$someUser['user_name'], 
									array_column($iAmFollowing, 'user_name') 
								) ){
									$bothfollowing[] = $someUser['user_name']; 
								}
							}
						}
						$data[$name]['context']['bothfollowing'] = $bothfollowing;
						$data[$name]['context']['alsofollowing'] = UserStatus::getFollowingFollowsUser($huijiUser->getName(), $me->getName());
						$data[$name]['context']['followingsites'] = $huijiUser->getFollowingSites( true, $this->getUser() );
					} else {
						$data[$name]['context'] = false;
					}
				}

				if ( !is_null( $params['token'] ) ) {
					$tokenFunctions = $this->getTokenFunctions();
					foreach ( $params['token'] as $t ) {
						$val = call_user_func( $tokenFunctions[$t], $user );
						if ( $val === false ) {
							$this->setWarning( "Action '$t' is not allowed for the current user" );
						} else {
							$data[$name][$t . 'token'] = $val;
						}
					}
				}
			}
		}

		$context = $this->getContext();
		// Second pass: add result data to $retval
		foreach ( $goodNames as $u ) {
			if ( !isset( $data[$u] ) ) {
				$data[$u] = [ 'name' => $u ];
				$urPage = new UserrightsPage;
				$urPage->setContext( $context );
				$iwUser = $urPage->fetchUser( $u );

				if ( $iwUser instanceof UserRightsProxy ) {
					$data[$u]['interwiki'] = true;

					if ( !is_null( $params['token'] ) ) {
						$tokenFunctions = $this->getTokenFunctions();

						foreach ( $params['token'] as $t ) {
							$val = call_user_func( $tokenFunctions[$t], $iwUser );
							if ( $val === false ) {
								$this->setWarning( "Action '$t' is not allowed for the current user" );
							} else {
								$data[$u][$t . 'token'] = $val;
							}
						}
					}
				} else {
					$data[$u]['missing'] = true;
					if ( isset( $this->prop['cancreate'] ) && !$this->getConfig()->get( 'DisableAuthManager' ) ) {
						$status = MediaWiki\Auth\AuthManager::singleton()->canCreateAccount( $u );
						$data[$u]['cancreate'] = $status->isGood();
						if ( !$status->isGood() ) {
							$data[$u]['cancreateerror'] = $this->getErrorFormatter()->arrayFromStatus( $status );
						}
					}
				}
			} else {
				if ( isset( $this->prop['groups'] ) && isset( $data[$u]['groups'] ) ) {
					ApiResult::setArrayType( $data[$u]['groups'], 'array' );
					ApiResult::setIndexedTagName( $data[$u]['groups'], 'g' );
				}
				if ( isset( $this->prop['implicitgroups'] ) && isset( $data[$u]['implicitgroups'] ) ) {
					ApiResult::setArrayType( $data[$u]['implicitgroups'], 'array' );
					ApiResult::setIndexedTagName( $data[$u]['implicitgroups'], 'g' );
				}
				if ( isset( $this->prop['rights'] ) && isset( $data[$u]['rights'] ) ) {
					ApiResult::setArrayType( $data[$u]['rights'], 'array' );
					ApiResult::setIndexedTagName( $data[$u]['rights'], 'r' );
				}
			}

			$fit = $result->addValue( [ 'query', $this->getModuleName() ],
				null, $data[$u] );
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'users',
					implode( '|', array_diff( $users, $done ) ) );
				break;
			}
			$done[] = $u;
		}
		$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'user' );
	}

	public function getAllowedParams() {
		$ret = [
			'prop' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
					'blockinfo',
					'groups',
					'implicitgroups',
					'rights',
					'editcount',
					'registration',
					'emailable',
					'gender',
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
					'followingsites',
					'context',
					// When adding a prop, consider whether it should be added
					// to self::$publicProps
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'attachedwiki' => null,
			'users' => [
				ApiBase::PARAM_TYPE => 'user',
				ApiBase::PARAM_ISMULTI => true
			],
			'token' => [
				ApiBase::PARAM_DEPRECATED => true,
				ApiBase::PARAM_TYPE => array_keys( $this->getTokenFunctions() ),
				ApiBase::PARAM_ISMULTI => true
			],
		];
		// if ( !$this->getConfig()->get( 'DisableAuthManager' ) ) {
		// 	$ret['prop'][ApiBase::PARAM_TYPE][] = 'cancreate';
		// }
		return $ret;
	}

}

