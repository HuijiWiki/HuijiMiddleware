<?php
/**
 * WeiboUserInfoAuthenticationRequest implementation
 */
namespace WeiboLogin\Auth;
use WeiboLogin\WeiboUser;
use MediaWiki\Auth\AuthenticationRequest;
/**
 * An AUthenticationRequest that holds Weibo user information.
 */
class WeiboUserInfoAuthenticationRequest extends AuthenticationRequest {
	public $required = self::OPTIONAL;
	/** @var Weibo_Service_Plus_Person|array An array of infos (provided by Weibo)
	 * about a user. */
	public $userInfo;
	public function __construct( $userInfo ) {
		$this->userInfo = $userInfo;
	}
	public function getFieldInfo() {
		return [];
	}
	public function describeCredentials() {
		$WeiboUser = $this->userInfo['nickname'];
		return [
			'provider' => wfMessage( 'weibologin-auth-service-name' ),
			'account' =>
				$WeiboUser ? new \RawMessage( '$1', [ $WeiboUser ] ) :
					wfMessage( 'weibologin-auth-service-unknown-account' )
		];
	}
}