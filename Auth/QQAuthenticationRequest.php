<?php
/**
 * QQAuthenticationRequest implementation
 */
namespace QQLogin\Auth;
use QQLogin\QQUser;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ButtonAuthenticationRequest;
/**
 * Implements a QQAuthenticationRequest by extending a ButtonAuthenticationRequest
 * and describes the credentials used/needed by this AuthenticationRequest.
 */
class QQAuthenticationRequest extends ButtonAuthenticationRequest {
	public $rememberMe = true;
	public function __construct( \Message $label, \Message $help ) {
		parent::__construct(
			QQPrimaryAuthenticationProvider::QQLOGIN_BUTTONREQUEST_NAME,
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