<?php
/**
 * WeiboRemoveAuthenticationRequest implementation
 */
namespace WeiboLogin\Auth;
use WeiboLogin\WeiboUser;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthManager;
/**
 * Implementation of an AuthenticationReuqest that is used to remove a
 * connection between a Weibo account and a local wiki account.
 */
class WeiboRemoveAuthenticationRequest extends AuthenticationRequest {
	private $weiboId = null;
	public function __construct( $WeiboId ) {
		$this->weiboId = $WeiboId;
	}
	public function getUniqueId() {
		return parent::getUniqueId() . ':' . $this->weiboId;
	}
	public function getFieldInfo() {
		return [ $this->weinoId => [
			'type' => 'button',
			'label' => 'unlink-account-weibo',
			'help' => 'unlink-accounts-weibo-help'
		]];
	}
	/**
	 * Returns the Weibo ID, that should be removed from the valid
	 * credentials of the user.
	 *
	 * @return String
	 */
	public function getWeiboId() {
		return $this->weiboId;
	}
	public function describeCredentials() {
		$openid = $this->WeiboId;
		return [
			'provider' => wfMessage( 'weibologin-auth-service-name' ),
			'account' =>
				new \RawMessage( '$1', [ $openid ] ),
		];
	}
}
