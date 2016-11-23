<?php

//require_once("httpProducer.php");
use MediaWiki\Logger\LoggerFactory;
class EMJob extends Job {
	
        public function __construct( $title, $params ) {
        	$logger = LoggerFactory::getInstance( 'emJob' );	
			$logger->debug("constructor", ['title'=>$title,'params'=>$params]);
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
                       	// Load data from $this->params and $this->title
			//	$revId = $this->params[1];
			//	print_r($revId);
			//	$rev = Revision::loadFromId(wfGetDB('DB_SLAVE') , $revId);
			//	print_r($rev);
			//	die();
                                return $this->emEdit( $this->title, $this->params[1], $this->params[2],$this->params[3],$this->params[4]);
				break;
                        default:
                                # code...
                                break;
                 }
	}

	public function emEdit( $title, Revision $rev, User $user, $ip, $userAgent){
       		global $wgHuijiPrefix, $wgSitename,$wgIsProduction;

//        	if($wgIsProduction == false) return;
        	if($rev == null || $title == null || $user == null) return;

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
				
		
		$huijiPageInfo = new HuijiPageInfo($page_id, RequestContext::getMain());
		$score = $huijiPageInfo->pageScore();

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
			'user_name' => $user_name,
                	'user_id' => $user_id,
			'user_isBot'=>$user_isBot,
                	'site_prefix' => $wgHuijiPrefix,
                	'site_name' => $wgSitename,
                	'page_title' =>$page_title,
                	'page_id' => $page_id,
               	 	'page_ns' => $page_ns,
			'page_category' => $page_category,
			'page_isNew' => $page_isNew,
			//'timestamp' => isset($_SERVER[ 'REQUEST_TIME' ]) ? $_SERVER[ 'REQUEST_TIME' ] : "",
			'timestamp' => $timestamp,
			'score' => $score,			
	                'client_ip'=> $ip,
                	'client_userAgent' => $userAgent,
		); 
	

		HttpProducer::getIns()->process($wgHuijiPrefix.'_'.$page_id,"edit",json_encode($data, JSON_UNESCAPED_UNICODE));
	}
}

