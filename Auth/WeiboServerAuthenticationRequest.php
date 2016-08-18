<?php
/**
 * WeiboServerAuthenticationRequest implementation
 */
namespace WeiboLogin\Auth;
use MediaWiki\Auth\AuthenticationRequest;
/**
 * Implements a WeiboServerAuthenticationRequest that holds the data returned by a
 * redirect from Weibo into the authentication workflow.
 */
class WeiboServerAuthenticationRequest extends AuthenticationRequest {
	/**
	 * Verification code provided by the server. Needs to be sent back in the last leg of the
	 * authorization process.
	 * @var string
	 */
	public $accessToken;
	/**
	 * An error code returned in case of Authentication failure
	 * @var string
	 */
	public $errorCode;
	public function getFieldInfo() {
		return [
			'error' => [
				'type' => 'string',
				'label' => wfMessage( 'Weibologin-param-error-label' ),
				'help' => wfMessage( 'Weibologin-param-error-help' ),
				'optional' => true,
			],
			'code' => [
				'type' => 'string',
				'label' => wfMessage( 'Weibologin-param-code-label' ),
				'help' => wfMessage( 'Weibologin-param-code-help' ),
				'optional' => true,
			],
		];
	}
	/**
	 * Load data from query parameters in an OAuth return URL
	 * @param array $data Submitted data as an associative array
	 * @return AuthenticationRequest|null
	 */
	public function loadFromSubmission( array $data ) {
		if ( isset( $data['authAction'])){
			$this->authAction = $data['authAction'];
		}
		if ( isset( $data['code'] ) ) {
			$this->accessToken = $data['code'];
			return true;
		}
		return false;
	}
}