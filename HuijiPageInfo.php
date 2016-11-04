<?php
use MediaWiki\MediaWikiServices;
class HuijiPageInfo extends ContextSource {
	private $mTitle;
	public function __construct($pageId, $context){
		$this->id = $pageId;
		$this->mTitle = Title::newFromID($pageId);
		if ($title != null){
			$this->page = new WikiPage($this->mTitle);
			$this->setContext( $context );
			$this->context = $context;			
		}

	}
	public function pageScore(){
		global $wgContLang;
		if ($this->title == null){
			return 0;
		}
		$pageCounts = $this->pageCounts( $this->page );
		Hooks::run( 'HuijiPageInfo', [ $this->page, &$pageCounts ] );
		if ( $this->page->isCountable() ){
			$revScore = round($pageCounts['revision'] / 1000)
						+round( $pageCounts['authors']/5 )
						+round( $pageCounts['recent_edits'] / 100 )
						+round( $pageCounts['recent_authors'] );
			$temScore = round($pageCounts['transclusion']['from']
						+$pageCounts['transclusion']['to']);

			$watScore = round( $pageCounts['watchers'] /5)
						+round( $pageCounts['visitingWatchers'] );
			$lenScore = round( $pageCounts['length'] / 300);
			$comScore = $pageCounts['comma'];
			$filScore = round($pageCounts['files']/2);
			$score = $revScore + $temScore + $watScore + $lenScore + $comScore + $filScore;
			if (isset($pageCounts['averageRating']) && isset($pageCounts['ratingCount']) && $pageCounts['ratingCount'] > 0){
				$score += round($score*0.2*($pageCounts['averageRating']-3));
			}
			if ($score > 2000){
				$score = 2000;
			}
			$score = round(sqrt(($score - 2000)/20 + 100)*10);
		} else {
			return 0;
		}
		return $score;	

	}
		/**
	 * Returns page counts that would be too "expensive" to retrieve by normal means.
	 *
	 * @param WikiPage|Article|Page $page
	 * @return array
	 */
	protected function pageCounts( Page $page ) {
		$fname = __METHOD__;
		$config = $this->context->getConfig();

		return ObjectCache::getMainWANInstance()->getWithSetCallback(
			self::getCacheKey( $page->getTitle(), $page->getLatest() ),
			WANObjectCache::TTL_WEEK,
			function ( $oldValue, &$ttl, &$setOpts ) use ( $page, $config, $fname ) {
				$title = $page->getTitle();
				$id = $title->getArticleID();

				$dbr = wfGetDB( DB_REPLICA );
				$dbrWatchlist = wfGetDB( DB_REPLICA, 'watchlist' );
				$setOpts += Database::getCacheSetOptions( $dbr, $dbrWatchlist );

				$watchedItemStore = WatchedItemStore::getDefaultInstance();

				$result = [];
				$result['watchers'] = $watchedItemStore->countWatchers( $title );

				if ( $config->get( 'ShowUpdatedMarker' ) ) {
					$updated = wfTimestamp( TS_UNIX, $page->getTimestamp() );
					$result['visitingWatchers'] = $watchedItemStore->countVisitingWatchers(
						$title,
						$updated - $config->get( 'WatchersMaxAge' )
					);
				}

				// Total number of edits
				$edits = (int)$dbr->selectField(
					'revision',
					'COUNT(*)',
					[ 'rev_page' => $id ],
					$fname
				);
				$result['edits'] = $edits;

				// Total number of distinct authors
				if ( $config->get( 'MiserMode' ) ) {
					$result['authors'] = 0;
				} else {
					$result['authors'] = (int)$dbr->selectField(
						'revision',
						'COUNT(DISTINCT rev_user_text)',
						[ 'rev_page' => $id ],
						$fname
					);
				}

				// "Recent" threshold defined by RCMaxAge setting
				$threshold = $dbr->timestamp( time() - $config->get( 'RCMaxAge' ) );

				// Recent number of edits
				$edits = (int)$dbr->selectField(
					'revision',
					'COUNT(rev_page)',
					[
						'rev_page' => $id,
						"rev_timestamp >= " . $dbr->addQuotes( $threshold )
					],
					$fname
				);
				$result['recent_edits'] = $edits;

				// Recent number of distinct authors
				$result['recent_authors'] = (int)$dbr->selectField(
					'revision',
					'COUNT(DISTINCT rev_user_text)',
					[
						'rev_page' => $id,
						"rev_timestamp >= " . $dbr->addQuotes( $threshold )
					],
					$fname
				);

				// // Subpages (if enabled)
				// if ( MWNamespace::hasSubpages( $title->getNamespace() ) ) {
				// 	$conds = [ 'page_namespace' => $title->getNamespace() ];
				// 	$conds[] = 'page_title ' .
				// 		$dbr->buildLike( $title->getDBkey() . '/', $dbr->anyString() );

				// 	// Subpages of this page (redirects)
				// 	$conds['page_is_redirect'] = 1;
				// 	$result['subpages']['redirects'] = (int)$dbr->selectField(
				// 		'page',
				// 		'COUNT(page_id)',
				// 		$conds,
				// 		$fname
				// 	);

				// 	// Subpages of this page (non-redirects)
				// 	$conds['page_is_redirect'] = 0;
				// 	$result['subpages']['nonredirects'] = (int)$dbr->selectField(
				// 		'page',
				// 		'COUNT(page_id)',
				// 		$conds,
				// 		$fname
				// 	);

				// 	// Subpages of this page (total)
				// 	$result['subpages']['total'] = $result['subpages']['redirects']
				// 		+ $result['subpages']['nonredirects'];
				// }

				// Counts for the number of transclusion links (to/from)
				if ( $config->get( 'MiserMode' ) ) {
					$result['transclusion']['to'] = 0;
				} else {
					$result['transclusion']['to'] = (int)$dbr->selectField(
						'templatelinks',
						'COUNT(tl_from)',
						[
							'tl_namespace' => $title->getNamespace(),
							'tl_title' => $title->getDBkey()
						],
						$fname
					);
				}

				$result['transclusion']['from'] = (int)$dbr->selectField(
					'templatelinks',
					'COUNT(*)',
					[ 'tl_from' => $title->getArticleID() ],
					$fname
				);

				$result['length'] = $title->getLength();

				$result['comma'] =  substr_count($page->getContent()->getNativeData(), '，');

				$result['files'] = (int)$dbr->selectField(
					'imagelinks',
					'COUNT(*)',
					[ 'il_from' => $title->getArticleID() ],
					$fname
				);

				return $result;
			}
		);
	}
	protected function getImagesCount() {
		$request = array(
			'action' => 'query',
			'prop' => 'pageimages',
			'piprop' => 'name',
			'pageids' => implode( '|', $pageIds ),
			'pilimit' => 'max',
		);
		if ( $size ) {
			$request['piprop'] = 'thumbnail';
			$request['pithumbsize'] = $size;
		}
		$api = new ApiMain( new FauxRequest( $request ) );
		$api->execute();	
		$res = (array)$api->getResult()->getResultData( array( 'query', 'pages' ),
				array( 'Strip' => 'base' ) );	

	}
	/**
	 * Returns the name that goes in the "<h1>" page title.
	 *
	 * @return string
	 */
	protected function getPageTitle() {
		return $this->msg( 'pageinfo-title', $this->getTitle()->getPrefixedText() )->text();
	}


	/**
	 * Returns the description that goes below the "<h1>" tag.
	 *
	 * @return string
	 */
	protected function getDescription() {
		return '';
	}

	/**
	 * @param Title $title
	 * @param int $revId
	 * @return string
	 */
	protected static function getCacheKey( Title $title, $revId ) {
		return wfMemcKey( 'huijiinfoaction', md5( $title->getPrefixedText() ), $revId, InfoAction::VERSION );
	}
}