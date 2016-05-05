<?php
Class HuijiFunctions {
    /* atomic lock with memecached */
    static function addLock( $lockKey, $timeout = 0, $lockExpireTime = 120 ){
         $startTime = microtime(TRUE);
         $cache = wfGetCache( CACHE_ANYTHING );
         $key = wfMemcKey( 'huijimiddleware', 'huijifunctions', 'addLock', $lockKey);
         while(!$cache->add( $key, 1, $lockExpireTime )){
             $now = microtime(TRUE);
             if ( ($now - $startTime) >= $timeout ){
                 return false;
             }
             usleep(1000);
         }
         return true;              
    }
    static function releaseLock($lockKey){
         $cache = wfGetCache( CACHE_ANYTHING );
         $key = wfMemcKey( 'huijimiddleware', 'huijifunctions', 'addLock', $lockKey);
         return $cache->delete($key);
    }
	/**
	 * The following four functions are borrowed
	 * from includes/wikia/GlobalFunctionsNY.php
	 */
	static function dateDiff( $date1, $date2 ) {
		$dtDiff = $date1 - $date2;
		$totalDays = intval( $dtDiff / ( 24 * 60 * 60 ) );
		$totalSecs = $dtDiff - ( $totalDays * 24 * 60 * 60 );
		$dif['mo'] = intval( $totalDays / 30 );
		$dif['d'] = $totalDays;
		$dif['h'] = $h = intval( $totalSecs / ( 60 * 60 ) );
		$dif['m'] = $m = intval( ( $totalSecs - ( $h * 60 * 60 ) ) / 60 );
		$dif['s'] = $totalSecs - ( $h * 60 * 60 ) - ( $m * 60 );
		return $dif;
	}
	static function getTimeOffset( $time, $timeabrv, $timename ) {
		global $wgUser;
		$timeStr = ''; // misza: initialize variables, DUMB FUCKS!
		if( $time[$timeabrv] > 0 ) {
			// Give grep a chance to find the usages:
			// comments-time-days, comments-time-hours, comments-time-minutes, comments-time-seconds, comments-time-months
			if ($timeabrv == 's') {
				$timeStr = '一个普朗克时间';
			}else{
				$timeStr = wfMessage( "comments-time-{$timename}", $time[$timeabrv] )->text();
			}	
		}
		// if( $timeStr ) {
		// 	$timeStr .= ' ';
		// }
		return $timeStr;
	}
	/**
	 * get time ago
	 * @param  int $time timestamp
	 * @return string
	 */
	static function getTimeAgo( $time ) {
		$timeArray = self::dateDiff( time(), $time );
		$timeStr = '';
		$timeStrMo = self::getTimeOffset( $timeArray, 'mo', 'months' );
		$timeStrD = self::getTimeOffset( $timeArray, 'd', 'days' );
		$timeStrH = self::getTimeOffset( $timeArray, 'h', 'hours' );
		$timeStrM = self::getTimeOffset( $timeArray, 'm', 'minutes' );
		$timeStrS = self::getTimeOffset( $timeArray, 's', 'seconds' );
		if ( $timeStrMo ) {
			$timeStr = $timeStrMo;
		} else {
			$timeStr = $timeStrD;
			if( $timeStr < 2 ) {
				$timeStr .= $timeStrH;
				$timeStr .= $timeStrM;
				if( !$timeStr ) {
					$timeStr .= $timeStrS;
				}
			}
		}
		if( !$timeStr ) {
			$timeStr = '一个普朗克时间';
		}
		return $timeStr;
	}
	/**
	 * Makes sure that link text is not too long by changing too long links to
	 * <a href=#>http://www.abc....xyz.html</a>
	 *
	 * @param $matches Array
	 * @return String: shortened URL
	 */
	public static function cutCommentLinkText( $matches ) {
		$tagOpen = $matches[1];
		$linkText = $matches[2];
		$tagClose = $matches[3];
		$image = preg_match( "/<img src=/i", $linkText );
		$isURL = ( preg_match( '%^(?:http|https|ftp)://(?:www\.)?.*$%i', $linkText ) ? true : false );
		if( $isURL && !$image && strlen( $linkText ) > 30 ) {
			$start = substr( $linkText, 0, ( 30 / 2 ) - 3 );
			$end = substr( $linkText, strlen( $linkText ) - ( 30 / 2 ) + 3, ( 30 / 2 ) - 3 );
			$linkText = trim( $start ) . wfMessage( 'ellipsis' )->plain() . trim( $end );
		}
		return $tagOpen . $linkText . $tagClose;
	}
	/**
	 * Simple spam check -- checks the supplied text against MediaWiki's
	 * built-in regex-based spam filters
	 *
	 * @param $text String: text to check for spam patterns
	 * @return Boolean: true if it contains spam, otherwise false
	 */
	public static function isSpam( $text ) {
		global $wgSpamRegex, $wgSummarySpamRegex;
		$retVal = false;
		// Allow to hook other anti-spam extensions so that sites that use,
		// for example, AbuseFilter, Phalanx or SpamBlacklist can add additional
		// checks
		wfRunHooks( 'Comments::isSpam', array( &$text, &$retVal ) );
		if ( $retVal ) {
			// Should only be true here...
			return $retVal;
		}
		// Run text through $wgSpamRegex (and $wgSummarySpamRegex if it has been specified)
		if ( $wgSpamRegex && preg_match( $wgSpamRegex, $text ) ) {
			return true;
		}
		if ( $wgSummarySpamRegex && is_array( $wgSummarySpamRegex ) ) {
			foreach ( $wgSummarySpamRegex as $spamRegex ) {
				if ( preg_match( $spamRegex, $text ) ) {
					return true;
				}
			}
		}
		return $retVal;
	}
	/**
	 * Checks the supplied text for links
	 *
	 * @param $text String: text to check
	 * @return Boolean: true if it contains links, otherwise false
	 */
	public static function haveLinks( $text ) {
		$linkPatterns = array(
			'/(https?)|(ftp):\/\//',
			'/=\\s*[\'"]?\\s*mailto:/',
		);
		foreach ( $linkPatterns as $linkPattern ) {
			if ( preg_match( $linkPattern, $text ) ) {
				return true;
			}
		}
		return false;
	}
	/**
	 * Blocks comments from a user
	 *
	 * @param int $userId User ID of the guy whose comments we want to block
	 * @param mixed $userName User name of the same guy
	 */
	public function blockUser( $userId, $userName ) {
		$dbw = wfGetDB( DB_MASTER );
		wfSuppressWarnings(); // E_STRICT bitching
		$date = date( 'Y-m-d H:i:s' );
		wfRestoreWarnings();
		$dbw->insert(
			'Comments_block',
			array(
				'cb_user_id' => $this->getUser()->getId(),
				'cb_user_name' => $this->getUser()->getName(),
				'cb_user_id_blocked' => $userId,
				'cb_user_name_blocked' => $userName,
				'cb_date' => $date
			),
			__METHOD__
		);
		$dbw->commit();
	}
	/**
	 * Fetches the list of blocked users from the database
	 *
	 * @param int $userId User ID for whom we're getting the blocks(?)
	 * @return array List of comment-blocked users
	 */
	static function getBlockList( $userId ) {
		$blockList = array();
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'Comments_block',
			'cb_user_name_blocked',
			array( 'cb_user_id' => $userId ),
			__METHOD__
		);
		foreach ( $res as $row ) {
			$blockList[] = $row->cb_user_name_blocked;
		}
		return $blockList;
	}
	static function isUserCommentBlocked( $userId, $userIdBlocked ) {
		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->selectRow(
			'Comments_block',
			array( 'cb_id' ),
			array(
				'cb_user_id' => $userId,
				'cb_user_id_blocked' => $userIdBlocked
			),
			__METHOD__
		);
		if ( $s !== false ) {
			return true;
		} else {
			return false;
		}
	}
	/**
	 * Deletes a user from your personal comment-block list.
	 *
	 * @param int $userId Your user ID
	 * @param int $userIdBlocked User ID of the blocked user
	 */
	public static function deleteBlock( $userId, $userIdBlocked ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'Comments_block',
			array(
				'cb_user_id' => $userId,
				'cb_user_id_blocked' => $userIdBlocked
			),
			__METHOD__
		);
		$dbw->commit();
	}
	/**
	 * Sort threads ascending
	 *
	 * @param $x
	 * @param $y
	 * @return int
	 */
	public static function sortAsc( $x, $y ) {
		// return -1  -  x goes above y
		// return  1  -  x goes below y
		// return  0  -  order irrelevant (only when x == y)
		if ( $x[0]->timestamp < $y[0]->timestamp ) {
			return -1;
		} else {
			return 1;
		}
	}
	/**
	 * Sort threads descending
	 *
	 * @param $x
	 * @param $y
	 * @return int
	 */
	public static function sortDesc( $x, $y ) {
		// return -1  -  x goes above y
		// return  1  -  x goes below y
		// return  0  -  order irrelevant (only when x == y)
		if ( $x[0]->timestamp > $y[0]->timestamp ) {
			return -1;
		} else {
			return 1;
		}
	}
	/**
	 * Sort threads by score
	 *
	 * @param $x
	 * @param $y
	 */
	public static function sortScore( $x, $y ) {
		// return -1  -  x goes above y
		// return  1  -  x goes below y
		// return  0  -  order irrelevant (only when x == y)
		if ( $x[0]->currentScore > $y[0]->currentScore ) {
			return -1;
		} else {
			return 1;
		}
	}
	/**
	 * Sort COMMENTS (not threads) by score
	 *
	 * @param $x
	 * @param $y
	 */
	public static function sortCommentScore( $x, $y ) {
		// return -1  -  x goes above y
		// return  1  -  x goes below y
		// return  0  -  order irrelevant (only when x == y)
		if ( $x->currentScore > $y->currentScore ) {
			return -1;
		} else {
			return 1;
		}
	}
	/**
	 * Sort the comments purely by the time, from earliest to latest
	 *
	 * @param $x
	 * @param $y
	 * @return int
	 */
	public static function sortTime( $x, $y ) {
		// return -1  -  x goes above y
		// return  1  -  x goes below y
		// return  0  -  order irrelevant (only when x == y)
		if ( $x->timestamp == $y->timestamp ) {
			return 0;
		} elseif ( $x->timestamp < $y->timestamp ) {
			return -1;
		} else {
			return 1;
		}
	}
	#    Output easy-to-read numbers
    #    by james at bandit.co.nz
    static function format_nice_number($n) {
        // first strip any formatting;
        $n = (0+str_replace(",","",$n));
        
        // is this a number?
        if(!is_numeric($n)) return false;
        
        // now filter it;
        if($n>1000000000000) return round(($n/1000000000000),1).'T';
        else if($n>1000000000) return round(($n/1000000000),1).'B';
        else if($n>1000000) return round(($n/1000000),1).'M';
        else if($n>1000) return round(($n/1000),1).'K';
        
        return number_format($n);
    }
	/**
	 * Convert all '@' character to user page link. Max ping allowed is 20.
	 * @param String $text: The message to be converted.
	 * 
	 * @return String : the converted message.
	 */
	public static function preprocessText( $message ) {
		// convert '@' to wiki link;
		$text = $message;
		$matches = array();
        $t = preg_match_all('/\\@(.+?)\\b/us', $text, $matches);
        if ( isset ($matches[1]) ){
            $i = 0;
            while ( isset($matches[1][$i]) ){
                $atWho = User::newFromName( $matches[1][$i] );
                if ( !$atWho || $atWho->isAnon() ) {
                	$i++; 
                    continue;
             	}
                $text = str_replace( '@'.$matches[1][$i], '@[[User:'.$matches[1][$i].'|'.$matches[1][$i].']]', $text );             
            	$i++; 
            }
        }
        return $text;
	}
	/**
	 * Analyses a PostRevision to determine which users are mentioned.
	 *
	 * @param String $text The text where to find mentioned user array.
	 * @param \Title $title
	 * @return User[] Array of User objects.
	 */
	public static function getMentionedUsers($text) {
		// At the moment, it is not possible to get a list of mentioned users from HTML
		//  unless that HTML comes from Parsoid. But VisualEditor (what is currently used
		//  to convert wikitext to HTML) does not currently use Parsoid.
		$mentions = self::getMentionedUsersFromWikitext( $text );
		// in the future if we want to add a filter, we can add it here.
		// $notifyUsers = $this->filterMentionedUsers( $mentions, $post, $title );
		return $mentions;
	}
	/**
	 * Examines a wikitext string and finds users that were mentioned
	 * @param  string $wikitext
	 * @return array Array of User objects
	 */
	public static function getMentionedUsersFromWikitext( $wikitext ) {
		global $wgParser;
		$title = Title::newMainPage(); // Bogus title used for parser
		$options = new \ParserOptions;
		$options->setTidy( true );
		$options->setEditSection( false );
		$output = $wgParser->parse( $wikitext, $title, $options );
		$links = $output->getLinks();
		if ( ! isset( $links[NS_USER] ) || ! is_array( $links[NS_USER] ) ) {
			// Nothing
			return array();
		}
		$users = array();
		foreach ( $links[NS_USER] as $dbk => $page_id ) {
			$user = User::newFromName( $dbk );
			if ( !$user || $user->isAnon() ) {
				continue;
			}
			$users[$user->getId()] = $user;
			// If more than 20 users are being notified this is probably a spam/attack vector.
			// Don't send any mention notifications
			if ( count( $users ) > 20 ) {
				return array();
			}
		}
		return $users;
	}
	/* Get Real User Ip (By pass proxy) */	
	public static function getIp() {
		//Just get the headers if we can or else use the SERVER global
		if ( function_exists( 'apache_request_headers' ) ) {
			$headers = apache_request_headers();
		} else {
			$headers = $_SERVER;
		}
		//Get the forwarded IP if it exists
		if ( array_key_exists( 'X-Forwarded-For', $headers ) && filter_var( $headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$the_ip = $headers['X-Forwarded-For'];
		} elseif ( array_key_exists( 'HTTP_X_FORWARDED_FOR', $headers ) && filter_var( $headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )
		) {
			$the_ip = $headers['HTTP_X_FORWARDED_FOR'];
		} else {
			
			$the_ip = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
		}
		return $the_ip;
	}
}
?>
