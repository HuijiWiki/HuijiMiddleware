<?php
class FeedbackApi extends ApiBase {

    public function execute() {
    	// $subject = $this->getMain()->getVal( 'subject' );
    	// $body = $this->getMain()->getVal( 'body' );
    	// $cookie = $this->getMain()->getVal( 'cookie' );
    	// $browserinfo = $this->getMain()->getVal( 'browserinfo' );
    	// $environment = $this->getMain()->getVal( 'environment' );
    	// $currentpagesource = $this->getMain()->getVal( 'currentpagesource' );
    	$imageData = $this->getMain()->getVal( 'data' );
        $user = $this->getUser();
        $subject = "用户bug反馈";
        $body = json_decode($imageData);
        if ($user !=  ''){
            if ($body[0]->Issue != ''){
                $trueSubject = $user->getName().":".$body[0]->Issue;
            } else {
                $trueSubject = $user->getName().":".$subject;
            }
        } else {
            if ($body[0]->Issue != ''){
                $trueSubject = $body[0]->Issue;
            } else {
                $trueSubject = $subject;
            }
        }
        $fs = wfGetFS(FS_OSS);
        $time = microtime();
        $id = HuijiFunctions::getTradeNo('FB');
        $fs->put("Feedback/$id.html", $body[3]->source);
        $data = str_replace('data:image/png;base64,', '', $body[1]);
        $data = str_replace(' ', '+', $data);
         
        $data = base64_decode($data);
        $source_img = imagecreatefromstring($data);
        imagepng($source_img, "/tmp/$id.png", 3);
        $fs->put("Feedback/$id.png", file_get_contents("/tmp/$id.png"));
        imagedestroy($source_img);
        $trueBody = $this->getSection('Issue');
        $trueBody .= $body[0]->Issue;
        $trueBody .= $this->getSection('Cookie');
        foreach ($_COOKIE as $key => $value) {
           $trueBody .= $key. ":".$value.";".PHP_EOL;
        }
        $trueBody .= $this->getSection('URL');
        $trueBody .= $body[2]->url;
        $trueBody .= $this->getSection('源代码');
        $trueBody .= "http://fs.huijiwiki.com/Feedback/$id.html";
        $trueBody .= $this->getSection('Agent');
        $trueBody .= $body[4]->agent;
        $trueBody .= $this->getSection('来源');
        $trueBody .= $body[5]->referer;
        $trueBody .= $this->getSection('分辨率');
        $trueBody .= $body[6]->w . "X" .$body[6]->h;
        $trueBody .= $this->getSection('截图');
        $trueBody .= "http://fs.huijiwiki.com/Feedback/$id.png";


        // wfDebug($imageData);
        // $trueBody = $body[0]->issue;
        // $
        // $trueBody .= $this->getSection("cookie");
        // $trueBody .= $cookie;
        // $trueBody .= $this->getSection("browserinfo");
        // $trueBody .= $browserinfo;
        // $trueBody .= $this->getSection("environment");
        // $trueBody .= $environment;
        // $trueBody .= $this->getSection("currentpagesource");
        // $trueBody .= $currentpagesource;
        // $trueBody .= $this->getSection("imageData");
        // $trueBody .= $imageData;

        // if (
        //     $user->isBlocked() ||
        //     !$user->isAllowed( 'edit' ) ||
        //     wfReadOnly()
        // ) {
        //     return true;
        // }
        //$imageData = substr($imageData, 0, 50);
        
        $res = UserMailer::send(new MailAddress('xigu+4v9xc1lkbgfsjexbgigj@boards.trello.com', 'trello', 'trello'), new MailAddress("no-reply@huiji.wiki"), $trueSubject, $trueBody);

        $this->getResult()->addValue( null, $this->getModuleName(), $res );
    }

    
    private function getSection($section){
    	return PHP_EOL.PHP_EOL."$section".PHP_EOL."=====================".PHP_EOL.PHP_EOL;
    }

    public function getAllowedParams() {
        return array(
            'data' => array(
                ApiBase::PARAM_REQUIRED => true,
                ApiBase::PARAM_TYPE => 'string'
            )
        );
    }
}