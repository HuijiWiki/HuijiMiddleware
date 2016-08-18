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
	private $WeiboId = null;
	public function __construct( $WeiboId ) {
		$this->WeiboId = $WeiboId;
	}
	public function getUniqueId() {
		return parent::getUniqueId() . ':' . $this->WeiboId;
	}
	public function getFieldInfo() {
		return [];
	}
	/**
	 * Returns the Weibo ID, that should be removed from the valid
	 * credentials of the user.
	 *
	 * @return String
	 */
	public function getWeiboId() {
		return $this->WeiboId;
	}
	public function describeCredentials() {
		$openid = $this->WeiboId;
		return [
			'provider' => wfMessage( 'Weibologin-auth-service-name' ),
			'account' =>
				new \RawMessage( '$1', [ $openid ] ),
		];
	}
}