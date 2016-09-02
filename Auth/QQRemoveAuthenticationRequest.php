<?php
/**
 * QQRemoveAuthenticationRequest implementation
 */
namespace QQLogin\Auth;
use QQLogin\QQUser;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthManager;
/**
 * Implementation of an AuthenticationReuqest that is used to remove a
 * connection between a QQ account and a local wiki account.
 */
class QQRemoveAuthenticationRequest extends AuthenticationRequest {
	private $QQId = null;
	public function __construct( $QQId ) {
		$this->QQId = $QQId;
	}
	public function getUniqueId() {
		return parent::getUniqueId() . ':' . $this->QQId;
	}
	public function getFieldInfo() {
		return [ $this->QQId => [
			'type' => 'button',
			'label' => 'Remove QQ',
			'help' => null
		]];
	}
	/**
	 * Returns the QQ ID, that should be removed from the valid
	 * credentials of the user.
	 *
	 * @return String
	 */
	public function getQQId() {
		return $this->QQId;
	}
	public function describeCredentials() {
		$openid = $this->QQId;
		return [
			'provider' => wfMessage( 'qqlogin-auth-service-name' ),
			'account' =>
				new \RawMessage( '$1', [ $openid ] ),
		];
	}
}