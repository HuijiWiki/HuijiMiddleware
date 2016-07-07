<?php
class FeedbackApi extends ApiBase {

    public function execute() {
        $fb = $this->getMain()->getVal( 'feedback' );
        $user = $this->getUser();
        $subject = "用户bug反馈";
        $body = json_decode($fb);
        if ($user !=  ''){
            if ($body->note != ''){
                $trueSubject = $user->getName().":".$body->note;
            } else {
                $trueSubject = $user->getName().":".$body->note;
            }
        } else {
            if ($body->note != ''){
                $trueSubject = $body->note;
            } else {
                $trueSubject = $subject;
            }
        }
        $fs = wfGetFS(FS_OSS);
        $time = microtime();
        $id = HuijiFunctions::getTradeNo('FB');
        $fs->put("Feedback/$id.html", $body->html);
        $data = str_replace('data:image/png;base64,', '', $body->img);
        $data = str_replace(' ', '+', $data);
         
        $data = base64_decode($data);
        $source_img = imagecreatefromstring($data);
        imagepng($source_img, "/tmp/$id.png", 3);
        $fs->put("Feedback/$id.png", file_get_contents("/tmp/$id.png"));
        imagedestroy($source_img);
        $trueBody = $this->getSection('Issue');
        $trueBody .= $body->note;
        $trueBody .= $this->getSection('Cookie');
        foreach ($_COOKIE as $key => $value) {
           $trueBody .= $key. ":".$value.";".PHP_EOL;
        }
        $trueBody .= $this->getSection('URL');
        $trueBody .= $body->url;
        $trueBody .= $this->getSection('源代码');
        $trueBody .= "http://fs.huijiwiki.com/Feedback/$id.html";
        $trueBody .= $this->getSection('Agent');
        foreach( $body->browser as $key=>$value ){
            if (is_array($value)){
                $trueBody .= $key. ":" .implode(", ",$value).";".PHP_EOL;
            } else {
                $trueBody .= $key. ":" .$value.";".PHP_EOL;
            }
            
        }
        $trueBody .= $this->getSection('来源');
        $trueBody .= $body->referer;
        $trueBody .= $this->getSection('分辨率');
        $trueBody .= $body->w . "X" .$body->h;
        $trueBody .= $this->getSection('截图');
        $trueBody .= "http://fs.huijiwiki.com/Feedback/$id.png";
        
        $res = UserMailer::send(new MailAddress('xigu+4v9xc1lkbgfsjexbgigj@boards.trello.com', 'trello', 'trello'), new MailAddress("no-reply@huiji.wiki"), $trueSubject, $trueBody);

        $this->getResult()->addValue( null, $this->getModuleName(), $res );
    }

    
    private function getSection($section){
    	return PHP_EOL.PHP_EOL."$section".PHP_EOL."=====================".PHP_EOL.PHP_EOL;
    }

    public function getAllowedParams() {
        return array(
            'feedback' => array(
                ApiBase::PARAM_REQUIRED => true,
                ApiBase::PARAM_TYPE => 'string'
            ),
        );
    }
}