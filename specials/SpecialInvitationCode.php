<?php
/**
 * HelloWorld SpecialPage for Example extension
 *
 * @file
 * @ingroup Extensions
 */

class SpecialInvitationCode extends SpecialPage {

	/**
	 * Initialize the special page.
	 */
	public function __construct() {
		// A special page should at least have a name.
		// We do this by calling the parent class (the SpecialPage class)
		// constructor method with the name as first and only parameter.
		require_once ('/var/www/html/Invitation.php');
		parent::__construct( 'InvitationCode' );
	}

	/**
	 * Shows the page to the user.
	 * @param string $sub: The subpage string argument (if any).
	 *  [[Special:HelloWorld/subpage]].
	 */
	public function execute( $sub ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// Set the page title, robot policies, etc.
		$this->setHeaders();
		// If the user doesn't have the required 'SendToFollowers' permission, display an error
		if ( !$user->isAllowed( 'getinvitationcode' ) ) {
			$out->permissionRequired( 'getinvitationcode' );
			return;
		}
		$out->setPageTitle( $this->msg( 'huijimiddleware-invitationcode' ) );
		// Is the database locked?
		if ( wfReadOnly() ) {
			$out->readOnlyPage();
			return false;
		}
		// Blocked through Special:Block? No access for you!
		if ( $user->isBlocked() ) {
			$out->blockedPage( false );
			return false;
		}

		// Parses message from .i18n.php as wikitext and adds it to the
		// page output.
		$num = $request->getVal( 'num' );
		if ($num == ''){
			$num = 1;
		}
		Invitation::generateInvCode($num);
		$code = Invitation::getInvList($num);
		$out->addHtml($code);			
		

		// $out->addWikiMsg( 'huijimiddleware-helloworld-intro' );
	}

	protected function getGroupName() {
		return 'other';
	}
}
