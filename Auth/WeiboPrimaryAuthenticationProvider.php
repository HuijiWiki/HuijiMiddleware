<?php
/**
 * WeiboPrimaryAuthenticationProvider implementation
 */
namespace WeiboLogin\Auth;
use Exception;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Session\SessionManager;
use User;
use HuijiUser;
use SpecialPage;
use StatusValue;
use CropAvatar;
/**
 * Implements a primary authentication provider to authenticate an user using a Weibo account where
 * this user has access, too. On beginning of the authentication, the provider maybe redirects the
 * user to an external authentication provider (Weibo) to authenticate and permit the access to
 * the data of the foreign account, before it actually authenticates the user.
 */
class WeiboPrimaryAuthenticationProvider extends AbstractPrimaryAuthenticationProvider {
	/** Session inside of the auth session data where the original redirect URL is saved */
	const RETURNURL_SESSION_KEY = 'WeiboLoginReturnToUrl';
	const RETURNURL_AUTHACTION_KEY = 'WeiboAuthAction';
	/** Token salt for CSRF token used by WeiboLogin when a user gets
	 * redirected from Weibo */
	const TOKEN_SALT = 'WeiboPrimaryAuthenticationProvider:redirect';
	/** Name of the button of the WeiboAuthenticationRequest */
	const WEIBOLOGIN_BUTTONREQUEST_NAME = 'weibologin';
	public function beginPrimaryAuthentication( array $reqs ) {
		return $this->beginWeiboAuthentication( $reqs, self::WEIBOLOGIN_BUTTONREQUEST_NAME );
	}
	public function continuePrimaryAuthentication( array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			WeiboServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'weibologin-error-no-authentication-workflow' )
			);
		}
		$plus = $this->getAuthenticatedWeiboPlusFromRequest( $request );
		if ( $plus instanceof AuthenticationResponse ) {
			return $plus;
		}
		try {
			$huijiUser = \HuijiUser::newFromOpenId($plus['id'], 'weibo');
			if ( $huijiUser ) {
				$this->persistSessions($huijiUser->getUser());
				return AuthenticationResponse::newPass( $huijiUser->getName() );
			} else {
				//Auto create instead of auto link.
				$i = '';
				
				if (!User::isUsableName( $plus[ 'screen_name' ] ) ){
					$plus['screen_name'] = "WeiboUser".time();
				}
				$ret = $plus['screen_name'];
				while ( User::createNew($ret) == null ){
					print_r($ret);
					if ($i == ''){
						$i = 1;
					}
					$i ++;
					$ret = $plus['screen_name']."$i";
				}
				$wgUser = User::newFromName($ret);
				// $this->manager->autoCreateUser( $wgUser, AuthManager::AUTOCREATE_SOURCE_SESSION );
				$this->persistSessions($wgUser);
				$this->connectWithWeibo($wgUser, $plus['id']);
				/* Save Avatar, age and Gender */
				if ($plus['gender'] == 'm'){
					$wgUser->setOption('gender', 'male');
				} elseif ($plus['gender'] == 'f'){
					$wgUser->setOption('gender', 'female');
				}
				$avatar = new CropAvatar(
			  		$plus['avatar_large'],
			  		null,
			  		null,  
			      	true
			  	);
				return AuthenticationResponse::newPass( $ret );
				// $resp = AuthenticationResponse::newPass( null );
				// $resp->linkRequest = new WeiboUserInfoAuthenticationRequest( $plus );
				// $resp->createRequest = $resp->linkRequest;
				// return $resp;
			}
		} catch ( Exception $e ) {
			print_r($e->getMessage());
			die();
			return AuthenticationResponse::newFail(
				wfMessage( 'weibologin-generic-error', $e->getMessage() )
			);
		}
	}
	public function getAuthenticationRequests( $action, array $options ) {
		switch ( $action ) {
			case AuthManager::ACTION_LOGIN:
				return [ new WeiboAuthenticationRequest(
					wfMessage( 'weibologin' ),
					wfMessage( 'weibologin-loginbutton-help' )
			) ];
				break;
			case AuthManager::ACTION_LINK:
				// TODO: Probably not the best message currently.
				return [ new WeiboAuthenticationRequest(
					wfMessage( 'weibologin-form-merge' ),
					wfMessage( 'weibologin-link-help' )
				) ];
				break;
			case AuthManager::ACTION_REMOVE:
				$user = User::newFromName( $options['username'] );
				if ( !$user || !$this->hasConnectedWeiboAccount( $user ) ) {
					return [];
				}
				$openId = $this->getOpenIdFromUser( $user );
				$reqs = [new WeiboRemoveAuthenticationRequest( $openId )];
				return $reqs;
				break;
			case AuthManager::ACTION_CREATE:
				// TODO: ACTION_CREATE doesn't really need all
				// the things provided by inheriting
				// ButtonAuthenticationRequest, so probably it's better
				// to create it's own Request
				return [ new WeiboAuthenticationRequest(
					wfMessage( 'weibologin-create' ),
					wfMessage( 'weibologin-link-help' )
				) ];
				break;
			default:
				return [];
		}
	}
	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		return false;
	}
	public function testUserCanAuthenticate( $username ) {
		$user = \User::newFromName( $username );
		if ( $user ) {
			return $this->hasConnectedWeiboAccount( $user );
		}
		return false;
	}
	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req, $checkData = true
	) {
		if (
			get_class( $req ) === WeiboRemoveAuthenticationRequest::class &&
			$req->action === AuthManager::ACTION_REMOVE
		) {
			$user = User::newFromName( $req->username );
			$id = $this->getOpenIdFromUser($user);

			if ( $user != null  && $req->getWeiboId() == $id ) {
				return StatusValue::newGood();
			} else {
				return StatusValue::newFatal( wfMessage( 'weibologin-change-account-not-linked' ) );
			}
		}
		if (
			get_class( $req ) === WeiboUserInfoAuthenticationRequest::class &&
			$req->action === AuthManager::ACTION_CHANGE
		) {
			$user = User::newFromName( $req->username );
			$potentialUser = $this->getUserFromOpenId( $req->userInfo['id'] );
			if ( $potentialUser && !$potentialUser->equals( $user ) ) {
				return StatusValue::newFatal( 'weibologin-link-other' );
			} elseif ( $potentialUser ) {
				return StatusValue::newFatal( 'weibologin-link-same' );
			}
			if ( $user ) {
				return StatusValue::newGood();
			}
		}
		return StatusValue::newGood( 'ignored' );
	}
	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		if (
			get_class( $req ) === WeiboRemoveAuthenticationRequest::class &&
			$req->action === AuthManager::ACTION_REMOVE
		) {
			$user = User::newFromName( $req->username );
			$this->terminateWeiboConnection( $user, $req->getWeiboId() );
		}
		if (
			get_class( $req ) === WeiboUserInfoAuthenticationRequest::class &&
			$req->action === AuthManager::ACTION_CHANGE
		) {
			$user = User::newFromName( $req->username );
			$this->connectWithWeibo( $user, $req->userInfo['id'] );
		}
	}
	public function providerNormalizeUsername( $username ) {
		return null;
	}
	public function accountCreationType() {
		return self::TYPE_LINK;
	}
	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			WeiboUserInfoAuthenticationRequest::class );
		if ( $request ) {
			if ( $this->isOpenIdFree( $request->userInfo['id'] ) ) {
				$resp = AuthenticationResponse::newPass();
				$resp->linkRequest = $request;
				return $resp;
			}
		}
		return $this->beginWeiboAuthentication( $reqs, self::WeiboLOGIN_BUTTONREQUEST_NAME );
	}
	public function continuePrimaryAccountCreation( $user, $creator, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			WeiboServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'weibologin-error-no-authentication-workflow' )
			);
		}
		$plus = $this->getAuthenticatedWeiboPlusFromRequest( $request );
		if ( $plus instanceof AuthenticationResponse ) {
			return $plus;
		}
		try {
			$userInfo = $plus;
			$isWeiboIdFree = $this->isOpenIdFree( $userInfo['id'] );
			if ( $isWeiboIdFree ) {
				$resp = AuthenticationResponse::newPass();
				$resp->linkRequest = new WeiboUserInfoAuthenticationRequest( $userInfo );
				return $resp;
			}
			return AuthenticationResponse::newFail( wfMessage( 'weibologin-link-other' ) );
		} catch ( Exception $e ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'weibologin-generic-error', $e->getMessage() )
			);
		}
	}
	public function finishAccountCreation( $user, $creator, AuthenticationResponse $response ) {
		$userInfo = $response->linkRequest->userInfo;
		$this->persistSessions($user);
			/* Save Avatar, age and Gender */
		if ($userInfo['gender'] == 'f'){
			$wgUser->setOption('gender', 'male');
		} elseif ($userInfo['gender'] == 'm'){
			$wgUser->setOption('gender', 'female');
		}
		$avatar = new CropAvatar(
	  		$userInfo['avatar_large'],
	  		null,
	  		null,  
	      	true
	  	);
		$this->connectWithWeibo( $user, $userInfo['id'] );
		return null;
	}
	public function beginPrimaryAccountLink( $user, array $reqs ) {
		return $this->beginWeiboAuthentication( $reqs, self::WEIBOLOGIN_BUTTONREQUEST_NAME );
	}
	public function continuePrimaryAccountLink( $user, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			WeiboServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'weibologin-error-no-authentication-workflow' )
			);
		}
		$plus = $this->getAuthenticatedWeiboPlusFromRequest( $request );
		try {
			$userInfo = $plus;
			$openId = $userInfo['id'];
			$potentialUser = HuijiUser::newFromOpenId( $openId, 'weibo' );
			if ( $potentialUser && !$potentialUser->getUser()->equals( $user ) ) {
				return AuthenticationResponse::newFail( wfMessage( 'weibologin-link-other' ) );
			} elseif ( $potentialUser ) {
				return AuthenticationResponse::newFail( wfMessage( 'weibologin-link-same' ) );
			} else {
				$result = $this->connectWithWeibo( $user, $openId );
				if ( $result ) {
					return AuthenticationResponse::newPass();
				} else {
					// TODO: Better error message
					return AuthenticationResponse::newFail( new \RawMessage( 'Database error' ) );
				}
			}
		} catch ( Exception $e ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'weibologin-generic-error', $e->getMessage() )
			);
		}
	}
	/**
	 * Handler for a primary authentication, which currently begins. Checks, if the Authentication
	 * request can be handled by WeiboLogin and, if so, returns an AuthenticationResponse that
	 * redirects to the external authentication site of Weibo, otherwise returns an abstain response.
	 * @param array $reqs
	 * @param $buttonAuthenticationRequestName
	 * @return AuthenticationResponse
	 */
	private function beginWeiboAuthentication( array $reqs, $buttonAuthenticationRequestName ) {
		global $wgHuijiPrefix, $wgHuijiSuffix;
		$req = WeiboAuthenticationRequest::getRequestByName( $reqs, $buttonAuthenticationRequestName );
		if ( !$req ) {
			return AuthenticationResponse::newAbstain();
		}
		$this->manager->setAuthenticationSessionData( self::RETURNURL_SESSION_KEY, $req->returnToUrl );
		$this->manager->setAuthenticationSessionData( self::RETURNURL_AUTHACTION_KEY, $req->action );
		$this->manager->getRequest()->getSession()->persist();
		return AuthenticationResponse::newRedirect( [
			new WeiboServerAuthenticationRequest()
		], $this->createAuthUrl() );
	}
	/**
	 * Returns an instance of Weibo_Client, which is set up for the use in an authentication workflow.
	 *
	 * @return \Weibo_Client
	 */
	public function createAuthUrl() {
		global $wgHuijiPrefix;
		$appid = \Confidential::$weibo_app_id;
		$url = "https://api.weibo.com/oauth2/authorize";
		$url .= "?response_type=code";
		$url .= "&client_id={$appid}";
		$url .= "&redirect_uri=http://huijiwiki.com/wiki/special:callbackweibo?site=".$wgHuijiPrefix."%26sid=".$this->manager->getRequest()->getSession()->getId();
		// $url .= "&scope=get_user_info";
		$url .= "&state=".$this->manager->getRequest()->getSession()->getToken( self::TOKEN_SALT )->toString();
		// $url .= "&state=".$this->manager->getRequest()->getSession()->getId();
		return $url;
	}
	/**
	 * Creates a new authenticated Weibo Plus Service from a WeiboServerAuthenticationRequest.
	 *
	 * @param $request
	 * @return Weibo_Service_Plus|AuthenticationResponse
	 */
	private function getAuthenticatedWeiboPlusFromRequest( WeiboServerAuthenticationRequest
		$request
	) {
		if ( !$request->accessToken || $request->errorCode ) {
			switch ( $request->errorCode ) {
				case 'access_denied':
					return AuthenticationResponse::newFail( wfMessage( 'weibologin-access-denied'
						) );
					break;
				default:
					return AuthenticationResponse::newFail( wfMessage(
						'weibologin-generic-error', $request->errorCode ? $request->errorCode :
						'unknown' ) );
			}
		}
		
		$c = new \SaeTClientV2( \Confidential::$weibo_app_id , \Confidential::$weibo_app_secret , $request->accessToken );
		$info = $c->get_uid();
		$uid = $info['uid'];
		$user_info = $c->show_user_by_id( $uid );
		$user_info['id'] = $uid;
		return $user_info;
	}
	private function persistSessions($who){
		global $wgUser;
		$wgUser = $who;
		$session = SessionManager::getGlobalSession();
		$session->persist();
		$wgUser->touch();
		$wgUser->setCookies(null, null, true);
	}
	private function getUserFromOpenId($openId){
		return \HuijiUser::newFromOpenId($openId, 'weibo');		
	}
	private function hasConnectedWeiboAccount($user){
		$huijiUser = \HuijiUser::newFromUser($user);
		return 	$huijiUser->hasLinkedAccount('weibo');
	}
	private function getOpenIdFromUser($user){
		$huijiUser = \HuijiUser::newFromUser($user);
		$ids = $huijiUser->getOpenIds();
		return $ids['weibo'];				
	}
	private function terminateWeiboConnection($user, $openId){
		$huijiUser = \HuijiUser::newFromUser($user);
		$huijiUser->unlinkAccount($openId, 'weibo');
	}
	private function connectWithWeibo( $user, $openId){
		$huijiUser = \HuijiUser::newFromUser($user);
		$huijiUser->linkAccount($openId, 'weibo');
		return true;
	}
	private function isOpenIdFree( $openId ){
		return \HuijiUser::isOpenIdFree($openId, 'weibo');
	}
}