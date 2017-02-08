<?php

//require_once("httpProducer.php");
use MediaWiki\Logger\LoggerFactory;
class EMJob extends Job {
	
        public function __construct( $title, $params ) {
        	$this->logger = LoggerFactory::getInstance( 'emJob' );	
		$this->logger->debug("constructor", ['title'=>$title,'params'=>$params]);
                // Replace synchroniseThreadArticleData with an identifier for your job.
                parent::__construct( 'emJob', $title, $params );
                // echo 'asdf';

                // die();
        }

        /**
         * Execute the job
         *
         * @return bool
         */
        public function run() {	
                switch ($this->params[0]) {
                        case 'em_edit':
                        	return $this->emEdit( $this->title, $this->params[1], $this->params[2],$this->params[3],$this->params[4]);
				break;
			case 'em_comment':
				return $this->emComment($this->title, $this->params[1],$this->params[2],$this->params[3]);
				break;
			case 'em_poll':
				return $this->emPoll($this->title, $this->params[1],$this->params[2],$this->params[3],$this->params[4]);
				break;
			case 'em_rate':
				return $this->emRate($this->title, $this->params[1],$this->params[2],$this->params[3]);
				break;
                        default:
                                # code...
                                break;
                 }
	}


	public function emRate($title, $user, $voteValue, $timestamp){
		global  $wgHuijiPrefix, $wgSitename;
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
        $page_title = $title->getText();
        //page id
        $page_id = $title->getArticleID();
        //page namespace
        $page_ns = $title->getNamespace();
		//page score
		$huijiPageInfo = new HuijiPageInfo($page_id, RequestContext::getMain());
		$page_score = $huijiPageInfo->pageScore();
	
		//vote value
		$vote_value = $voteValue;
	

		$data = array(
			'action_type' => 'rate',
                        'user_name' =>  $user_name,
                        'user_id' => $user_id,
                        'user_isBot'=>$user_isBot,
                        'site_prefix' => $wgHuijiPrefix,
                        'site_name' => $wgSitename,
                	'page_id' => $page_id,
                        'page_title'=> $page_title,
                        'page_ns'=> $page_ns,		
			'page_score'=> $page_score,
			'action_timestamp' =>	$timestamp,
			'rate_value'=> $vote_value,
                );

                HttpProducer::getIns()->process($user_id.'_'.$wgHuijiPrefix.'_'.$page_id,"rate",json_encode($data, JSON_UNESCAPED_UNICODE));
	




	}



	public function emPoll($title, $user, $poll, $choice, $timestamp){

		global  $wgHuijiPrefix, $wgSitename;
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
		$page_title = $title->getText();
                //page id
                $page_id = $title->getArticleID();
                //page namespace
                $page_ns = $title->getNamespace();
		//page score
		$huijiPageInfo = new HuijiPageInfo($page_id, RequestContext::getMain());
		$page_score = $huijiPageInfo->pageScore();


		$poll_question = $poll['question'];
		$poll_id = $poll['id'];
		$poll_choice = $choice;		
		
		$data = array(
			'action_type' => 'poll',
                        'user_name' =>  $user_name,
                        'user_id' => $user_id,
                        'user_isBot'=>$user_isBot,
                        'site_prefix' => $wgHuijiPrefix,
                        'site_name' => $wgSitename,
                        'page_id' => $page_id,
			'page_title'=> $page_title,
			'page_ns'=> $page_ns,
			'page_score'=> $page_score,
                        'action_timestamp' => $timestamp,
			'poll_question'=> $poll_question,
			'poll_id'=> $poll_id,
			'poll_choice' => $poll_choice,
                );

		HttpProducer::getIns()->process($user_id.'_'.$wgHuijiPrefix.'_'.$page_id.'_'.$poll_id,"poll",json_encode($data, JSON_UNESCAPED_UNICODE));
	}



	public function emComment($title, $user, $comment, $timestamp){
		global $wgHuijiPrefix, $wgSitename,$wgIsProduction;
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
                $page_title = $title->getText();
                //page id
                $page_id = $title->getArticleID();
                //page namespace
                $page_ns = $title->getNamespace();

                $huijiPageInfo = new HuijiPageInfo($page_id, RequestContext::getMain());
                $page_score = $huijiPageInfo->pageScore();

		 
		$comment_text = $comment->text;
		$comment_id = $comment->id;

		$data = array(
			'action_type' => 'comment',
                        'user_name' =>  $user_name,
                        'user_id' => $user_id,
                        'user_isBot'=>$user_isBot,
                        'site_prefix' => $wgHuijiPrefix,
                        'site_name' => $wgSitename,
                        'page_title' =>$page_title,
                        'page_id' => $page_id,
                        'page_ns' => $page_ns,
			'page_score'=> $page_score,
                        'action_timestamp' => $timestamp,
			'comment_text'=> $comment_text,
			'comment_id'=> $comment_id
                );


                HttpProducer::getIns()->process($user_id.'_'.$wgHuijiPrefix.'_'.$page_id.'_'.$comment_id,"comment",json_encode($data, JSON_UNESCAPED_UNICODE));
	}


	public function emEdit( $title, Revision $revId, User $user, $ip, $userAgent){
       		global $wgHuijiPrefix, $wgSitename,$wgIsProduction;

//        	if($wgIsProduction == false) return;

        	if($revId == null || $title == null || $user == null) return;
        $rev = Revision::newFromId($revId);
		//content
		$content = $rev->getContent(Revision::RAW);
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
                $page_title = $title->getText();
		//page id
                $page_id = $title->getArticleID();
		//page namespace
                $page_ns = $title->getNamespace();
		//page isNew
		$page_isNew = $rev->getPrevious() == null ? true : false;
		//timestamp
		$timestamp = $rev->getTimestamp();		
		//page revId
		$page_revId = $rev->getId();	
		//page score
		$huijiPageInfo = new HuijiPageInfo($page_id, RequestContext::getMain());
		$page_score = $huijiPageInfo->pageScore();

		//client ip
	      //  $client_ip = isset($_SERVER[ 'HTTP_X_FORWARDED_FOR' ]) ? $_SERVER[ 'HTTP_X_FORWARDED_FOR' ] : "";
		//client userAgent
              //  $client_userAgent = isset($_SERVER[ 'HTTP_USER_AGENT' ]) ? $_SERVER[ 'HTTP_USER_AGENT' ] : "";
       		//page category
		if($content){
        		$options = $content->getContentHandler()->makeParserOptions( 'canonical' );
        		$output = $content->getParserOutput( $title, $rev->getId(), $options,true);
        		$page_category = array_map( 'strval', array_keys( $output->getCategories() ) );
		}else{
			$page_category = array();
		}		


	        $data = array(
			'action_type' => 'edit',
			'user_name' => $user_name,
                	'user_id' => $user_id,
			'user_isBot'=>$user_isBot,
                	'site_prefix' => $wgHuijiPrefix,
                	'site_name' => $wgSitename,
                	'page_title' =>$page_title,
                	'page_id' => $page_id,
               	 	'page_ns' => $page_ns,
			'edit_category' => $page_category,
			'edit_isNew' => $page_isNew,
			'edit_revId' => $page_revId,
			'page_score' => $page_score,			
			'action_timestamp' => $timestamp,
			'timestamp'=> $timestamp,
	                'client_ip'=> $ip,
                	'client_userAgent' => $userAgent
		); 
	

		HttpProducer::getIns()->process($user_id.'_'.$wgHuijiPrefix.'_'.$page_id,"edit",json_encode($data, JSON_UNESCAPED_UNICODE));
	}
}

