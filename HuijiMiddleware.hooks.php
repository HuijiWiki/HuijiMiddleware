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
	
	public static function onOpenSearchUrls( &$urls ) {
		global $wgHuijiPrefix;
		if ($wgHuijiPrefix = 'www' || $wgHuijiPrefix = 'zs.test'){
			$urls[] = array(
				'type' => 'text/html',
				'method' => 'get',
				'template' => SpecialPage::getTitleFor('GlobalSearch')->getCanonicalURL( 'key={searchTerms}' )
			);
		}
		
	}

}
