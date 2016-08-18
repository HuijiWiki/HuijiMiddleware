<?php
/**
 * QQPrimaryAuthenticationProvider implementation
 */
namespace QQLogin\Auth;
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
 * Implements a primary authentication provider to authenticate an user using a QQ account where
 * this user has access, too. On beginning of the authentication, the provider maybe redirects the
 * user to an external authentication provider (QQ) to authenticate and permit the access to
 * the data of the foreign account, before it actually authenticates the user.
 */
class QQPrimaryAuthenticationProvider extends AbstractPrimaryAuthenticationProvider {
	/** Session inside of the auth session data where the original redirect URL is saved */
	const RETURNURL_SESSION_KEY = 'QQLoginReturnToUrl';
	const RETURNURL_AUTHACTION_KEY = 'QQAuthAction';
	/** Token salt for CSRF token used by QQLogin when a user gets
	 * redirected from QQ */
	const TOKEN_SALT = 'QQPrimaryAuthenticationProvider:redirect';
	/** Name of the button of the QQAuthenticationRequest */
	const QQLOGIN_BUTTONREQUEST_NAME = 'qqlogin';
	public function beginPrimaryAuthentication( array $reqs ) {
		return $this->beginQQAuthentication( $reqs, self::QQLOGIN_BUTTONREQUEST_NAME );
	}
	public function continuePrimaryAuthentication( array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			QQServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'qqlogin-error-no-authentication-workflow' )
			);
		}
		$plus = $this->getAuthenticatedQQPlusFromRequest( $request );
		if ( $plus instanceof AuthenticationResponse ) {
			return $plus;
		}
		try {
			$huijiUser = \HuijiUser::newFromOpenId($plus['id'], 'qq');
			if ( $huijiUser ) {
				$this->persistSessions($huijiUser->getUser());
				return AuthenticationResponse::newPass( $huijiUser->getName() );
			} else {
				//Auto create instead of auto link.
				$i = '';
				
				if (!User::isUsableName( $plus[ 'nickname' ] ) ){
					$plus['nickname'] = "QQUser".time();
				}
				$ret = $plus['nickname'];
				while ( User::createNew($ret) == null ){
					print_r($ret);
					if ($i == ''){
						$i = 1;
					}
					$i ++;
					$ret = $plus['nickname']."$i";
				}
				$wgUser = User::newFromName($ret);
				// $this->manager->autoCreateUser( $wgUser, AuthManager::AUTOCREATE_SOURCE_SESSION );
				$this->persistSessions($wgUser);
				$this->connectWithQQ($wgUser, $plus['id']);
				/* Save Avatar, age and Gender */
				if ($plus['gender'] == '男'){
					$wgUser->setOption('gender', 'male');
				} elseif ($plus['gender'] == '女'){
					$wgUser->setOption('gender', 'female');
				}
				$avatar = new CropAvatar(
			  		$plus['figureurl_qq_1'],
			  		null,
			  		null,  
			      	true
			  	);
				return AuthenticationResponse::newPass( $ret );
				// $resp = AuthenticationResponse::newPass( null );
				// $resp->linkRequest = new QQUserInfoAuthenticationRequest( $plus );
				// $resp->createRequest = $resp->linkRequest;
				// return $resp;
			}
		} catch ( Exception $e ) {
			print_r($e->getMessage());
			die();
			return AuthenticationResponse::newFail(
				wfMessage( 'qqlogin-generic-error', $e->getMessage() )
			);
		}
	}
	public function getAuthenticationRequests( $action, array $options ) {
		switch ( $action ) {
			case AuthManager::ACTION_LOGIN:
				return [ new QQAuthenticationRequest(
					wfMessage( 'qqlogin' ),
					wfMessage( 'qqlogin-loginbutton-help' )
				) ];
				break;
			case AuthManager::ACTION_LINK:
				// TODO: Probably not the best message currently.
				return [ new QQAuthenticationRequest(
					wfMessage( 'qqlogin-form-merge' ),
					wfMessage( 'qqlogin-link-help' )
				) ];
				break;
			case AuthManager::ACTION_REMOVE:
				$user = User::newFromName( $options['username'] );
				if ( !$user || !$this->hasConnectedQQAccount( $user ) ) {
					return [];
				}
				$openId = $this->getOpenIdFromUser( $user );
				$reqs = [new QQRemoveAuthenticationRequest( $openId )];
				return $reqs;
				break;
			case AuthManager::ACTION_CREATE:
				// TODO: ACTION_CREATE doesn't really need all
				// the things provided by inheriting
				// ButtonAuthenticationRequest, so probably it's better
				// to create it's own Request
				return [ new QQAuthenticationRequest(
					wfMessage( 'qqlogin-create' ),
					wfMessage( 'qqlogin-link-help' )
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
			return $this->hasConnectedQQAccount( $user );
		}
		return false;
	}
	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req, $checkData = true
	) {
		if (
			get_class( $req ) === QQRemoveAuthenticationRequest::class &&
			$req->action === AuthManager::ACTION_REMOVE
		) {
			$user = User::newFromName( $req->username );
			$id = $this->getOpenIdFromUser($user);

			if ( $user != null  && $req->getQQId() == $id ) {
				return StatusValue::newGood();
			} else {
				return StatusValue::newFatal( wfMessage( 'qqlogin-change-account-not-linked' ) );
			}
		}
		if (
			get_class( $req ) === QQUserInfoAuthenticationRequest::class &&
			$req->action === AuthManager::ACTION_CHANGE
		) {
			$user = User::newFromName( $req->username );
			$potentialUser = $this->getUserFromOpenId( $req->userInfo['id'] );
			if ( $potentialUser && !$potentialUser->equals( $user ) ) {
				return StatusValue::newFatal( 'qqlogin-link-other1' );
			} elseif ( $potentialUser ) {
				return StatusValue::newFatal( 'qqlogin-link-same' );
			}
			if ( $user ) {
				return StatusValue::newGood();
			}
		}
		return StatusValue::newGood( 'ignored' );
	}
	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		if (
			get_class( $req ) === QQRemoveAuthenticationRequest::class &&
			$req->action === AuthManager::ACTION_REMOVE
		) {
			$user = User::newFromName( $req->username );
			$this->terminateQQConnection( $user, $req->getQQId() );
		}
		if (
			get_class( $req ) === QQUserInfoAuthenticationRequest::class &&
			$req->action === AuthManager::ACTION_CHANGE
		) {
			$user = User::newFromName( $req->username );
			$this->connectWithQQ( $user, $req->userInfo['id'] );
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
			QQUserInfoAuthenticationRequest::class );
		if ( $request ) {
			if ( $this->isOpenIdFree( $request->userInfo['id'] ) ) {
				$resp = AuthenticationResponse::newPass();
				$resp->linkRequest = $request;
				return $resp;
			}
		}
		return $this->beginQQAuthentication( $reqs, self::QQLOGIN_BUTTONREQUEST_NAME );
	}
	public function continuePrimaryAccountCreation( $user, $creator, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			QQServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'qqlogin-error-no-authentication-workflow' )
			);
		}
		$plus = $this->getAuthenticatedQQPlusFromRequest( $request );
		if ( $plus instanceof AuthenticationResponse ) {
			return $plus;
		}
		try {
			$userInfo = $plus;
			$isQQIdFree = $this->isOpenIdFree( $userInfo['id'] );
			if ( $isQQIdFree ) {
				$resp = AuthenticationResponse::newPass();
				$resp->linkRequest = new QQUserInfoAuthenticationRequest( $userInfo );
				return $resp;
			}
			return AuthenticationResponse::newFail( wfMessage( 'qqlogin-link-other' ) );
		} catch ( Exception $e ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'qqlogin-generic-error', $e->getMessage() )
			);
		}
	}
	public function finishAccountCreation( $user, $creator, AuthenticationResponse $response ) {
		$userInfo = $response->linkRequest->userInfo;
		$this->persistSessions($user);
			/* Save Avatar, age and Gender */
		if ($userInfo['gender'] == '男'){
			$wgUser->setOption('gender', 'male');
		} elseif ($userInfo['gender'] == '女'){
			$wgUser->setOption('gender', 'female');
		}
		$avatar = new CropAvatar(
	  		$userInfo['figureurl_qq_1'],
	  		null,
	  		null,  
	      	true
	  	);
		$this->connectWithQQ( $user, $userInfo['id'] );
		return null;
	}
	public function beginPrimaryAccountLink( $user, array $reqs ) {
		return $this->beginQQAuthentication( $reqs, self::QQLOGIN_BUTTONREQUEST_NAME );
	}
	public function continuePrimaryAccountLink( $user, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			QQServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'qqlogin-error-no-authentication-workflow' )
			);
		}
		$plus = $this->getAuthenticatedQQPlusFromRequest( $request );
		try {
			$userInfo = $plus;
			$openId = $userInfo['id'];
			$potentialUser = HuijiUser::newFromOpenId( $openId, 'qq' );
			if ( $potentialUser && !$potentialUser->getUser()->equals( $user ) ) {
				return AuthenticationResponse::newFail( wfMessage( 'qqlogin-link-other' ) );
			} elseif ( $potentialUser ) {
				return AuthenticationResponse::newFail( wfMessage( 'qqlogin-link-same' ) );
			} else {
				$result = $this->connectWithQQ( $user, $openId );
				if ( $result ) {
					return AuthenticationResponse::newPass();
				} else {
					// TODO: Better error message
					return AuthenticationResponse::newFail( new \RawMessage( 'Database error' ) );
				}
			}
		} catch ( Exception $e ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'qqlogin-generic-error', $e->getMessage() )
			);
		}
	}
	/**
	 * Handler for a primary authentication, which currently begins. Checks, if the Authentication
	 * request can be handled by QQLogin and, if so, returns an AuthenticationResponse that
	 * redirects to the external authentication site of QQ, otherwise returns an abstain response.
	 * @param array $reqs
	 * @param $buttonAuthenticationRequestName
	 * @return AuthenticationResponse
	 */
	private function beginQQAuthentication( array $reqs, $buttonAuthenticationRequestName ) {
		global $wgHuijiPrefix, $wgHuijiSuffix;
		$req = QQAuthenticationRequest::getRequestByName( $reqs, $buttonAuthenticationRequestName );
		if ( !$req ) {
			return AuthenticationResponse::newAbstain();
		}
		$this->manager->setAuthenticationSessionData( self::RETURNURL_SESSION_KEY, $req->returnToUrl );
		$this->manager->setAuthenticationSessionData( self::RETURNURL_AUTHACTION_KEY, $req->action );
		$this->manager->getRequest()->getSession()->persist();
		return AuthenticationResponse::newRedirect( [
			new QQServerAuthenticationRequest()
		], $this->createAuthUrl() );
	}
	/**
	 * Returns an instance of QQ_Client, which is set up for the use in an authentication workflow.
	 *
	 * @return \QQ_Client
	 */
	public function createAuthUrl() {
		global $wgHuijiPrefix;
		$appid = \Confidential::$qq_app_id;
		$url = "https://graph.qq.com/oauth2.0/authorize";
		$url .= "?response_type=code";
		$url .= "&client_id={$appid}";
		$url .= "&redirect_uri=http://www.huiji.wiki/wiki/special:callbackqq?site=".$wgHuijiPrefix."%26sid=".$this->manager->getRequest()->getSession()->getId();
		$url .= "&scope=get_user_info";
		$url .= "&state=".$this->manager->getRequest()->getSession()->getToken( self::TOKEN_SALT )->toString();
		// $url .= "&state=".$this->manager->getRequest()->getSession()->getId();
		return $url;
	}
	/**
	 * Creates a new authenticated QQ Plus Service from a QQServerAuthenticationRequest.
	 *
	 * @param $request
	 * @return QQ_Service_Plus|AuthenticationResponse
	 */
	private function getAuthenticatedQQPlusFromRequest( QQServerAuthenticationRequest
		$request
	) {
		if ( !$request->accessToken || $request->errorCode ) {
			switch ( $request->errorCode ) {
				case 'access_denied':
					return AuthenticationResponse::newFail( wfMessage( 'qqlogin-access-denied'
						) );
					break;
				default:
					return AuthenticationResponse::newFail( wfMessage(
						'qqlogin-generic-error', $request->errorCode ? $request->errorCode :
						'unknown' ) );
			}
		}
		$plus = new \QqSdk();
		$open_id = $plus->get_open_id($request->accessToken);
		$info = $plus->get_user_info($request->accessToken, $open_id['openid'], \Confidential::$qq_app_id);
		$info['id'] = $open_id['openid'];
		return $info;
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
		return \HuijiUser::newFromOpenId($openId, 'qq');		
	}
	private function hasConnectedQQAccount($user){
		$huijiUser = \HuijiUser::newFromUser($user);
		return 	$huijiUser->hasLinkedAccount('qq');
	}
	private function getOpenIdFromUser($user){
		$huijiUser = \HuijiUser::newFromUser($user);
		$ids = $huijiUser->getOpenIds();
		return $ids['qq'];				
	}
	private function terminateQQConnection($user, $openId){
		$huijiUser = \HuijiUser::newFromUser($user);
		$huijiUser->unlinkAccount($openId, 'qq');
	}
	private function connectWithQQ( $user, $openId){
		$huijiUser = \HuijiUser::newFromUser($user);
		$huijiUser->linkAccount($openId, 'qq');
		return true;
	}
	private function isOpenIdFree( $openId ){
		return \HuijiUser::isOpenIdFree($openId, 'qq');
	}
}