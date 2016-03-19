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
		echo $file;
		if ( file_exists( $file ) ) {
			$updater->addExtensionUpdate( array( 'addTable', 'Vote', $file, true ) );
		} else {
			throw new MWException( "VoteNY does not support $dbt." );
		}
		$file = __DIR__ ."/../PollNY/poll.sql";
		echo $file;
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
	   $magicWordsIds[] = 'numberofalledits';
	   $magicWordsIds[] = 'numberofallarticles';
	   $magicWordsIds[] = 'numberofallactiveusers';
	   $magicWordsIds[] = 'numberofallfiles';
	   $magicWordsIds[] = 'numberofallsites';

	   return true;
   }
 
   public static function onParserGetVariableValueSwitch( &$parser, &$cache, &$magicWordId, &$ret ) {
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
 
        return true;
   }


}
