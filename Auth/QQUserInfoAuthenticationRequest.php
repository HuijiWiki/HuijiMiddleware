<?php
/**
 * QQUserInfoAuthenticationRequest implementation
 */
namespace QQLogin\Auth;
use QQLogin\QQUser;
use MediaWiki\Auth\AuthenticationRequest;
/**
 * An AUthenticationRequest that holds QQ user information.
 */
class QQUserInfoAuthenticationRequest extends AuthenticationRequest {
	public $required = self::OPTIONAL;
	/** @var QQ_Service_Plus_Person|array An array of infos (provided by QQ)
	 * about a user. */
	public $userInfo;
	public function __construct( $userInfo ) {
		$this->userInfo = $userInfo;
	}
	public function getFieldInfo() {
		return [];
	}
	public function describeCredentials() {
		$QQUser = $this->userInfo['nickname'];
		return [
			'provider' => wfMessage( 'qqlogin-auth-service-name' ),
			'account' =>
				$QQUser ? new \RawMessage( '$1', [ $QQUser ] ) :
					wfMessage( 'qqlogin-auth-service-unknown-account' )
		];
	}
}