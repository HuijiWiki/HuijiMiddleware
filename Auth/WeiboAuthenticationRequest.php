<?php
/**
 * WeiboAuthenticationRequest implementation
 */
namespace WeiboLogin\Auth;
use WeiboLogin\WeiboUser;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ButtonAuthenticationRequest;
/**
 * Implements a WeiboAuthenticationRequest by extending a ButtonAuthenticationRequest
 * and describes the credentials used/needed by this AuthenticationRequest.
 */
class WeiboAuthenticationRequest extends ButtonAuthenticationRequest {
	public $rememberMe = true;
	public function __construct( \Message $label, \Message $help ) {
		parent::__construct(
			WeiboPrimaryAuthenticationProvider::WEIBOLOGIN_BUTTONREQUEST_NAME,
			$label,
			$help,
			true
		);
	}
	public function getFieldInfo() {
		if ( $this->action === AuthManager::ACTION_REMOVE ) {
			return [];
		}
		return parent::getFieldInfo();
	}
}