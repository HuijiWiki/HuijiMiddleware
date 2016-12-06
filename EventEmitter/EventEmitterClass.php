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
$wgHooks['SocialProfile::advancement'][] = "EventEmitter::onAdvancement";


require_once("httpProducer.php");
use MediaWiki\Logger\LoggerFactory;
class EventEmitter{

	/**
	 * Called when a new edit is made
	 */
	public static function onNewRevisionFromEditComplete( $article, Revision $rev, $baseID, User $user ) {
		
		//client ip
		$client_ip = isset($_SERVER[ 'HTTP_X_FORWARDED_FOR' ]) ? $_SERVER[ 'HTTP_X_FORWARDED_FOR' ] : "";
                //client userAgent
                $client_userAgent = isset($_SERVER[ 'HTTP_USER_AGENT' ]) ? $_SERVER[ 'HTTP_USER_AGENT' ] : "";

		$params = ['em_edit',$rev, $user, $client_ip, $client_userAgent] ;
		//$params = ['123'];

	        $job = new EMJob( $article->getTitle(), $params);
        	JobQueueGroup::singleton()->push( $job ); // mediawiki >= 1.21  

 	//	wfErrorLog(json_encode($data),"/var/log/mediawiki/SocialProfile.log");	
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
		global  $wgUser, $wgHuijiPrefix, $wgSitename;
		if($comment == null) return;
		$title = $comment->page->title;
		$user = $wgUser;	
               
		$params = ['em_comment', $user, $comment] ;
                $job = new EMJob( $title, $params);
                JobQueueGroup::singleton()->push( $job ); 	
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
		global  $wgUser, $wgHuijiPrefix, $wgSitename;
		if($pageId == null || $pollId == null) return;
		$user = $wgUser;	
                //user name 
                $user_name = $user->getName();
                //use id
                $user_id = $user->getId();
                //user is bot
                $user_isBot = $user->isAllowed('bot');
                //site prefix
                $site_prefix = $wgHuijiPrefix;
                //site name
                $site_name = $wgSitename;
                //page title

                //page id
                $page_id = $pageId;

		//poll
		$p = new Poll();
		$poll = $p->getPoll();		 
		$poll_question = $poll['question'];
		//$poll_id = $poll['id'];
		$poll_id = $pollId;
		$poll_choice = $choiceIds;		
		
		$timestamp = $poll['timestamp'];
		
		

		$data = array(
                        'user_name' =>  $user_name,
                        'user_id' => $user_id,
                        'user_isBot'=>$user_isBot,
                        'site_prefix' => $wgHuijiPrefix,
                        'site_name' => $wgSitename,
                        'page_id' => $page_id,
                        'timestamp' => $timestamp,
			'poll_question'=> $poll_question,
			'poll_id'=> $poll_id,
			'poll_choice' => $poll_choice,
                );

                HttpProducer::getIns()->process($user_id.'_'.$wgHuijiPrefix.'_'.$page_id.'_'.$poll_id,"poll",json_encode($data, JSON_UNESCAPED_UNICODE));
	}
	/**
	 * Called when a rating is made
	 */
	public static function onRatingVote( Vote $vote, $voteValue, $pageId ){
		global  $wgUser, $wgHuijiPrefix, $wgSitename;
		if($pageId == null || $pollId == null) return;
		$user = $wgUser;	
                //user name 
                $user_name = $user->getName();
                //use id
                $user_id = $user->getId();
                //user is bot
                $user_isBot = $user->isAllowed('bot');
                //site prefix
                $site_prefix = $wgHuijiPrefix;
                //site name
                $site_name = $wgSitename;
                //page title

                //page id
                $page_id = $pageId;

		
		//vote value
		$vote_value = $voteValue;
	
		$data = array(
                        'user_name' =>  $user_name,
                        'user_id' => $user_id,
                        'user_isBot'=>$user_isBot,
                        'site_prefix' => $wgHuijiPrefix,
                        'site_name' => $wgSitename,
                        'page_id' => $page_id,
                //        'timestamp' => $timestamp,
			'vote_value'=> $vote_value,
                );

                HttpProducer::getIns()->process($user_id.'_'.$wgHuijiPrefix.'_'.$page_id,"rate",json_encode($data, JSON_UNESCAPED_UNICODE));
		

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
	public static function onAchievementSend($receiverId, $giftId, $sgId, $description ){

	}

	/**
	 * Called when a message is left
	 */
	public static function onMessageReceived($senderId, $receiverId, $message ){

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
		//$out->addModules(['ext.HuijiMiddleware.eventemitter']);
	}

	/**
	 * Called when a user levels up
	 */
	public static function onAdvancement( $userId, $level ){

	}

}
