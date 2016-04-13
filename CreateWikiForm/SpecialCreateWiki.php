<?php   
/**
* uploadfiles
*/
class SpecialCreateWiki extends SpecialPage{
    
    function __construct(){
        parent::__construct( 'CreateWiki' );
    }

    function getGroupName() {
        return 'wiki';
    }

    public function execute( $params ) {
        global $wgUser, $wgLocalFileRepo, $wgContLang;
        // Set the page title, robot policies, etc.
        // $this->setHeaders();
        $out = $this->getOutput();
        $output = '';
         $out->addModuleStyles('ext.HuijiMiddleware.createwiki.css');
         $out->addModules( array(
             'ext.HuijiMiddleware.createwiki.js'
             )
         );
        /**
         * Redirect Non-logged in users to Login Page
         * $login = SpecialPage::getTitleFor( 'Userlogin' );
          *$login->getFullURL( 'returnto=Special:SystemGiftList' )
         * It will automatically return them to the ViewSystemGifts page
         */
        
        $login = SpecialPage::getTitleFor( 'Userlogin' );
        if ( $wgUser->getID() == 0 || $wgUser->getName() == '' ) {
            $output .= '请先<a class="login-in" data-toggle="modal" data-target=".user-login">登录</a>或<a href="'.$login->getFullURL( 'type=signup' ).'">创建用户</a>。';
            $out->addHTML( $output );
            return false;
        }

        $output .= '<form class="create-wiki-form" action="/SiteMaintenance/WikiSite/welcome.php" method="post">
            <div class="form-group">
                <h2 class="heading">创建信息</h2>
                <div class="controls">
                  <input type="text" id="name" class="floatLabel" name="wikiname" placeholder="如某某某中文维基，九个字以内为宜">
                  <label for="name" class="">站点名称</label>
                </div>
                <div class="controls">
                  <input type="text" id="url" class="floatLabel" name="domainprefix" placeholder="XXX.huiji.wiki, 前缀至少3个英文字母。">
                  <label for="url">域名前缀</label>
                </div>
                <div class="controls">
                    <input type="text" id="code" class="floatLabel" name="inv" placeholder="请联系support@huiji.wiki索取">
                    <label for="code">邀请码</label>
                </div>
                <div class="controls">
                    <i class="fa fa-sort"></i>
                    <select class="floatLabel" name="type" id="type">
                    <option></option>
                    <option>影视</option>
                    <option>游戏</option>
                    <option>动漫</option>
                    <option>文学</option>
                    <option>明星</option>
                    <option>生活</option>
                    <option>教育</option>
                    <option>商业</option>
                    <option>自媒体</option>
                    <option>其他</option>
                    </select>
                    <label for="type" class="wiki-type">类型</label>
                </div>
                <div class="controls">
                    <textarea name="description" class="floatLabel" id="description"></textarea>
                    <label for="descriptions" class="">站点描述</label>
                    <div class="create-wiki-radio">
                        <div>
                            <input type="radio" name="manifest" id="optionsRadios1" value="internal" checked>
                            安装预设模板（推荐）
                        </div>
                        <div>
                            <input type="radio" name="manifest" id="optionsRadios2" value="empty">
                            空白维基
                        </div>
                    </div>
                    <div class="button create-wiki-submit">提交</div>
                </div>
              </div>
        </form>';
        $out->addHTML( $output );
 

    }



}

?>
