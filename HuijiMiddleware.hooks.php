<?php
/**
 * Hooks for Example extension
 *
 * @file
 * @ingroup Extensions
 */

class HuijiHooks {
	public static function onSpecialSearchResultsPrepend( $specialSearch, $output, $term ) { 
		global $wgServer, $wgSitename;
		$globalSearch = SpecialPage::getTitleFor('GlobalSearch');
		$url = htmlspecialchars( $globalSearch->getFullURL("key={$term}") ); 
		$link = "<a href=\"{$url}\">".wfMessage('global-search-link')->params($term)->text()."</a>";
		$globalSearchNotice = wfMessage('global-search-notice')->params( $wgSitename, $link )->text();
		$output->addHtml('<p class="global-search-notice">'.$globalSearchNotice.'</p>');
		return true;
	}

	public static function addTables( $updater ) {
		$dbt = $updater->getDB()->getType();
		$file = __DIR__ . "/../VoteNY/vote.$dbt";
		if ( file_exists( $file ) ) {
			$updater->addExtensionUpdate( array( 'addTable', 'Vote', $file, true ) );
		} else {
			throw new MWException( "VoteNY does not support $dbt." );
		}
		$file = __DIR__ ."/../PollNY/poll.sql";
		$updater->addExtensionUpdate( array( 'addTable', 'poll_choice', $file, true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'poll_question', $file, true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'poll_user_vote', $file, true ) );
		return true;
	}

	public static function onGetDoubleUnderscoreIDs( &$doubleUnderscoreIDs ) {
        $doubleUnderscoreIDs[] = 'norec';
        $doubleUnderscoreIDs[] = 'rec';
        return true;
    }
	public static function onOpenSearchUrls( &$urls ) {
		global $wgHuijiPrefix;
		if ($wgHuijiPrefix = 'www' || $wgHuijiPrefix = 'zs.test'){
			$urls = array(array(
				'type' => 'text/html',
				'method' => 'get',
				'template' => SpecialPage::getTitleFor('GlobalSearch')->getCanonicalURL( 'key={searchTerms}' )
			));
		}
		
	}

   public static function onRegisterMagicWords( &$magicWordsIds ) {
	   // Add the following to a wiki page to see how it works:
	   //  {{MYWORD}}
   	   $magicWordsIds[] = 'numberoffollowers';
   	   $magicWordsIds[] = 'numberoffollowers:r';
	   $magicWordsIds[] = 'numberofalledits';
	   $magicWordsIds[] = 'numberofallarticles';
	   $magicWordsIds[] = 'numberofallactiveusers';
	   $magicWordsIds[] = 'numberofallfiles';
	   $magicWordsIds[] = 'numberofallsites';
	   $magicWordsIds[] = 'sitedescription';

	   return true;
   }
 
	public static function onParserGetVariableValueSwitch( &$parser, &$cache, &$magicWordId, &$ret ) {
			global $wgHuijiPrefix;
		    // Return value and cache should match. Cache is used to save
	    // additional call when it is used multiple times on a page.
	   	$huijiStats = Huiji::getInstance()->getStats();
	    if ( $magicWordId === 'numberofalledits' ) {
	        $ret = $cache['numberofalledits'] = $huijiStats['edits'];
	    }
	    if ( $magicWordId === 'numberofallarticles' ) {
	        $ret = $cache['numberofallarticles'] = $huijiStats['pages'];
	    }
	    if ( $magicWordId === 'numberofallactiveusers' ) {
	        $ret = $cache['numberofallactiveusers'] = $huijiStats['users'];
	    }
	    if ( $magicWordId === 'numberofallfiles' ) {
	        $ret = $cache['numberofallfiles'] = $huijiStats['files'];
	    }
	    if ( $magicWordId === 'numberofallsites' ) {
	        $ret = $cache['numberofallsites'] = $huijiStats['sites'];
	    }
	    if ( $magicWordId === 'numberoffollowers' ) {
	    	$site = WikiSite::newFromPrefix($wgHuijiPrefix);
	    	$stats = $site->getStats();
	    	$ret = $cache['numberoffollowers'] = $stats['followers'];
	    }
	    if ( $magicWordId === 'numberoffollowers:r' ) {
	    	$site = WikiSite::newFromPrefix($wgHuijiPrefix);
	    	$stats = $site->getStats(false);
	    	$ret = $cache['numberoffollowers'] = $stats['followers'];
	    }
	    if ( $magicWordId === 'sitedescription') {
	    	$site = WikiSite::newFromPrefix($wgHuijiPrefix);
	    	$ret = $cache['sitedescription'] = $site->getDescription();
	    }

	    return true;
	}

	public static function onSetupParserFunction( &$parser ){
		$parser->setFunctionHook( 'css', 'HuijiHooks::renderObseleteParserFunction' );
	}
	public static function renderObseleteParserFunction($parser, $stuff){
		return array( "<!-- #CSS is obselete and no longer supported, please remove it -->", 'noparse' => true, 'isHTML' => true );
	}

	public static function onAPIQuerySiteInfoGeneralInfo($api, &$data){
		global $wgHuijiPrefix, $wgDefaultSiteProperty;
		if (class_exists("TransSite")){
			$site = TransSite::newFromPrefix($wgHuijiPrefix);
		} else {
			$site = WikiSite::newFromPrefix($wgHuijiPrefix);
		}
		$data['group'] = $site->getGroup();
		$data['prefix'] = $site->getPrefix();
		$data['avatar'] = array(
							"l" => $site->getAvatar('l')->getAvatarUrlPath(),
							"ml" => $site->getAvatar('ml')->getAvatarUrlPath(),
							"m" => $site->getAvatar('m')->getAvatarUrlPath(),
							"s" => $site->getAvatar('s')->getAvatarUrlPath(),
							);
		foreach($wgDefaultSiteProperty as $key => $property){
			$data['property'][$key] = $site->getProperty($key);
		}

	}
	public static function onAPIQuerySiteInfoStatisticsInfo(&$data){
		global $wgHuijiPrefix;
		$site = WikiSite::newFromPrefix($wgHuijiPrefix);
		
		$stats = $site->getStats(false);
		$data['followers'] = (int)$stats['followers'];
		$data['rating'] = $site->getRating();
		$data['score'] = (int)$site->getScore();
		$data['donate'] = (int)$site->getDonationSum();
		$month = date('Y-m', time());
		$month -= 1;
		$data['donategoalmet'] = (bool)($site->hasMetDonationGoal($month)==false)?"false":"true"; 

	}
	public static function onGetPreferences( $user, &$preferences ){
		// $preferences['usecodemirror'] = array(
		// 	'type' => 'toggle',
		// 	'label-message' => 'usecodemirror-pref',
		// 	'section' => "editing/advancedediting"
		// );
		$preferences['showeditcss'] = array(
			'type' => 'toggle',
			'label-message' => 'showeditcss-pref',
			'section' => "rendering/advancedrendering"
		);
		$preferences['showeditjs'] = array(
			'type' => 'toggle',
			'label-message' => 'showeditjs-pref',
			'section' => "rendering/advancedrendering"
		);
	// $preferences['mypref'] = array(
	// 	'type' => 'toggle',
	// 	'label-message' => 'tog-mypref', // a system message
	// 	'section' => 'personal/info',
	// );
		return true;
	}



}
