<?php
/**
 * QQServerAuthenticationRequest implementation
 */
namespace QQLogin\Auth;
use MediaWiki\Auth\AuthenticationRequest;
/**
 * Implements a QQServerAuthenticationRequest that holds the data returned by a
 * redirect from QQ into the authentication workflow.
 */
class QQServerAuthenticationRequest extends AuthenticationRequest {
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
				'label' => wfMessage( 'QQlogin-param-error-label' ),
				'help' => wfMessage( 'QQlogin-param-error-help' ),
				'optional' => true,
			],
			'code' => [
				'type' => 'string',
				'label' => wfMessage( 'QQlogin-param-code-label' ),
				'help' => wfMessage( 'QQlogin-param-code-help' ),
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