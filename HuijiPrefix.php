<?php 
/***
 * A help class to translate huijiprefix and the actual site name.
 */
class HuijiPrefix{
	/**
	 * Check if a prefix is part of huiji.
	 * @param string: prefix
	 * @return bool
	 */
	public static function hasPrefix( $prefix ){
		$result = self::getAllPrefix(true);
		if (in_array($prefix, $result)){
			return true;
		} else {
			return false;
		}
	}
	/**
	 * get site name by prefix, if exist this prefix return site name, else return this prefix
	 * @param  string $prefix site prefix ex:'lotr'
	 * @return string         [description]
	 */
	public static function prefixToSiteName( $prefix ){
		global $wgMemc;
		$key = wfForeignMemcKey( 'huiji','','prefixToSiteName', $prefix );
		$data = $wgMemc->get($key);
		if ($data != ''){
			return $data;
		} else {
			$dbr = wfGetDB( DB_SLAVE );
			$s = $dbr->selectRow(
				'domain',
				array( 'domain_id', 'domain_name' ),
				array(
					'domain_prefix' => $prefix,
				),
				__METHOD__
			);
			if ( $s !== false ) {
				$wgMemc->set($key, $s->domain_name);
				return $s->domain_name;
			}else{
				return $prefix;
			}			
		}
	}
	/**
	 * get site name with <a>
	 * @param  string $prefix site's prefix
	 * @return string <a></a>
	 */
	public static function prefixToSiteNameAnchor( $prefix ){
		return "<a href=\"".self::prefixToUrl($prefix)."\">".self::prefixToSiteName($prefix)."</a>";
	}
	/**
	 * get site url by prefix
	 * @param  string $prefix site prefix
	 * @return string site url
	 */
	public static function prefixToUrl( $prefix ){
		global $wgHuijiSuffix;
		return 'http://'.$prefix.$wgHuijiSuffix.'/';
	}
	/**
	 * get random prefix
	 * @return string
	 */
	public static function getRandomPrefix(){
		
		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->select(
			'domain',
			array( 'domain_prefix' ),
			array( 'domain_status' => 0, ),
 			__METHOD__
		);
		if ( $s !== false ) {
			$max = $dbr->numRows($s);
			$rng = rand(0, $max-1);
			$dbr->dataSeek($s, $rng);
			return $dbr->fetchObject($s)->domain_prefix;
			
		}else{
			return '';
		}
	}
	/**
	 * get all prefix
	 * @param  boolean $showHidden if true get all prefix(include hidden prefix), else get those no hidden prefix
	 * @return array
	 */
	static function getAllPrefix($showHidden = false){
		return self::getAllPrefixes($showHidden);
	}
	static function getAllPrefixes($showHidden = false){
		$dbr = wfGetDB( DB_SLAVE );
                $where = $showHidden?array():array( 'domain_status' => 0 );
		$res = $dbr->select(
			'domain',
			array( 'domain_prefix' ),
			$where,
			__METHOD__
		);
		if( $res !== false ){
			foreach ($res as $value) {
				$result[] = $value->domain_prefix;
			}
			return $result;
		}else{
			return '';
		}
	}
	static function getDBNames(){
		$prefixes = self::getAllPrefixes(true);
		$out = [];
		foreach ($prefixes as $value) {
			$out[] = WikiSite::DbIdFromPrefix($value);
		}
		return $out;
	}
	/**
	 * get those hiddend prefix
	 * @return array
	 */
	static function getHiddenPrefixes(){
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'domain',
			array( 'domain_prefix' ),
			array( 'domain_status' => 1 ),
			__METHOD__
		);
		if( $res !== false ){
			foreach ($res as $value) {
				$result[] = $value->domain_prefix;
			}
			return $result;
		}else{
			return '';
		}
	}
}
