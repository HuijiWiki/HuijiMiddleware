<?php
$wgHooks['NewRevisionFromEditComplete'][] = 'EventEmitter::onNewRevisionFromEditComplete';
$wgHooks['ArticleDelete'][] = 'EventEmitter::onArticleDelete';
$wgHooks['ArticleUndelete'][] = 'EventEmitter::onArticleUndelete';
$wgHooks['Comment::add'][] = "EventEmitter::onCommentAdd";
$wgHooks['Comment::delete'][] = "EventEmitter::onCommentDelete";
$wgHooks['Comment::like'][] = "EventEmitter::onCommentLike";
$wgHooks['Comment::dislike'][] = "EventEmitter::onCommentDislike";
$wgHooks['BlogPage::post'][] = "EventEmitter::onBlogPost";
$wgHooks['BlogPage::delete'][] = "EventEmitter::onBlogDelete";
$wgHooks['PollNY::create'][] = "EventEmitter::onPollCreate";
$wgHooks['PollNY::vote'][] = "EventEmitter::onPollVote";
$wgHooks['VoteNY::vote'][] = "EventEmitter::onRatingVote";
$wgHooks['SocialProfile::giftSend'][] = "EventEmitter::onGiftSend";
$wgHooks['SocialProfile::achievementSend'][] =  "EventEmitter::onAchievementSend";
$wgHooks['SocialProfile::messageReceived'][] = "EventEmitter::onMessageReceived";
$wgHooks['SocialProfile::followUser'][] = "EventEmitter::onFollowUser";
$wgHooks['SocialProfile::unfollowUser'][] = "EventEmitter::onUnfollowUser";
$wgHooks['SocialProfile::followSite'][] = "EventEmitter::onFollowSite";
$wgHooks['SocialProfile::unfollowSite'][] = "EventEmitter::onUnfollowSite";
$wgHooks['BeforePageDisplay'][] = "EventEmitter::onBeforePageDisplay";

class EventEmitter{

	/**
	 * Called when a new edit is made
	 */
	public static function onNewRevisionFromEditComplete( $article, Revision $rev, $baseID, User $user ) {
		global $wgHuijiPrefix;

		//format payload

		$payload = [
			'user' => $user->getId(),
			'site' => $wgHuijiPrefix,
			'time' => time(),
			'extra' => [
				'category' => '',
				'bot' => '',
				'new' => '',
				'title' => '',
				//...
			]
		];

		//Send playload to event queue

	}
	/**
	 * Called when an article is deleted
	 */
	public static function onArticleDelete( WikiPage &$article, User &$user, &$reason, &$error ) {

	}
	/**
	 * Called when an article is restored after deletion
	 */
	public static function onArticleUndelete( Title $title, $create, $comment, $oldPageId ) { 

	}

	/**
	 * Called when a comment is added
	 */
	public static function onCommentAdd( Comment $comment, $commentId, $pageId ){

	}
	/**
	 *  Called when a comment is deleted
	 */
	public static function onCommentDelete( Comment $comment, $commentId, $pageId ){

	}

	/**
	 * Called when a comment is liked
	 */
	public static function onCommentLike( Comment $comment, User $voter, $currentScore ){

	}

	/**
	 * Called when a comment is disliked
	 */
	public static function onCommentDislike( Comment $comment, User $voter, $currentScore ){

	}

	/**
	 * Called when a blog is posted
	 */
	public static function onBlogPost( WikiPage $wikiPage, User $user, $content ){

	}
	/**
	 * Called when a blog is deleted
	 */
	public static function onBlogDelete( Article $article, User $user ){

	}
	/**
	 * Called when a poll is created
	 * @param $specialpage should not be needed
	 * @param $pollId, used to get poll info
	 * @param $pollQuestions String, The title of the poll 
	 * @param $maxChoiceAllowed int, number of choices can be made at once.
	 */
	public static function onPollCreate( $specialpage, $pollId, $pollQuestions, $maxChoicesAllowed ){

	}
	/**
	 * Called when a poll is voted by others
	 */
	public static function onPollVote( $pageId, $pollId, array $choiceIds ){

	}
	/**
	 * Called when a rating is made
	 */
	public static function onRatingVote( Vote $vote, $voteValue, $pageId ){

	}
	/**
	 * Called when a gift is send
	 * @param $giftId defines general gift type.
	 * @param $ugId used to retrieve the info about this particular gift, who, when, how etc.
	 */	
	public static function onGiftSend( $receiverId, $senderId, $giftId , $ugId, $message ){

	} 

	/**
	 * Called when an achievement is send 
	 */
	public static function onAchievementSend( int $receiverId, int $giftId, int $sgId, $description ){

	}

	/**
	 * Called when a message is left
	 */
	public static function onMessageReceived( int $senderId, int $receiverId, $message ){

	}

	/**
	 * Called when someone follows another user
	 */
	public static function onFollowUser( User $follower, User $followee){

	}

	/**
	 *  Called when some unfollows another user
	 */
	public static function onUnfollowUser( User $exfollower, User $exfollowee){

	}
	/**
	 * Called when some follows a site
	 *
	 */
	public static function onFollowSite( User $follower, $prefix){

	}
	/**
	 * Called when someone unfollows a site
	 *
	 */
	public static function onUnfollowSite( User $exfollower, $prefix){
		
	}

	/**
	 * Called when every page is rendered, but not necessarily viewed due to caching layers
	 * if we want to collect viewer data, we have to do it in javascript.
	 */
	public static function onBeforePageDisplay(OutputPage &$out, Skin &$skin){
		$out->addModules(['ext.HuijiMiddleware.eventemitter']);
	}

}