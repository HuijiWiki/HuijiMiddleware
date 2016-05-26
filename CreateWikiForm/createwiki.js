/**
 * Created by huiji-001 on 2016/3/28.
 */

(function ($) {
    function floatLabel(inputType) {
        $(inputType).each(function () {
            var $this = $(this);
            $this.focus(function () {
                $this.next().addClass('active');
            });
            $this.blur(function () {
                if ($this.val() === '' || $this.val() === 'blank') {
                    $this.next().removeClass();
                }
            });
        });
    }
    floatLabel('.floatLabel');
}(jQuery));

function checkCallback(data,selector){
    data = JSON.parse(data);
    if(data.result == 'success'){
        selector.find('.form-control-feedback').remove();
        selector.append('<span class="glyphicon glyphicon-ok form-control-feedback success" aria-hidden="true"></span>');
    }else{
        selector.find('.form-control-feedback').remove();
        selector.append('<span class="glyphicon glyphicon-remove form-control-feedback warning" aria-hidden="true"></span>');
        mw.notification.notify(data.message,{tag:'error'})
    }
}
var createWiki = {

}
$(function(){
    var dataObj = new Object();
    var params = new Object();
    var flag = 0,step = 0;
    setTimeout(function(){
        mw.notification.autoHideSeconds = 1;
    },500);
    $('.create-wiki-form #name').blur(function(){
        var selector = $(this).parent();
        var val = $(this).val();
        $.ajax({
            url:'http://www.huiji.wiki/SiteMaintenance/WikiSite/AjaxCheckFunctions.php',
            data:{action:'checkDomainName',name:val},
            type:'post',
            format: 'json',
            success: function(data){
                checkCallback(data,selector);
            }
        })
    });
    $('.create-wiki-form #url').blur(function(){
        var selector = $(this).parent();
        var val = $(this).val().replace('.huiji.wiki','');
        var self = $(this);
        $.ajax({
            url:'http://www.huiji.wiki/SiteMaintenance/WikiSite/AjaxCheckFunctions.php',
            data:{action:'checkPrefixUrl',prefix:val},
            type:'post',
            format: 'json',
            success: function(data){
                var res = JSON.parse(data);
                checkCallback(data,selector);
                if(res.result == 'success'){
                    self.val(val+'.huiji.wiki');
                }
            }
        })
    });
    $('.create-wiki-form #code').blur(function(){
        var selector = $(this).parent();
        var val = $(this).val();
        $.ajax({
            url:'http://www.huiji.wiki/SiteMaintenance/WikiSite/AjaxCheckFunctions.php',
            data:{action:'inviteCode',inviteCode:val},
            type:'post',
            format: 'json',
            success: function(data){
                checkCallback(data,selector);
            }
        })
    });

    function onOpen(evt,websocket,dataObj) {
        console.log("Connected to WebSocket server.");
        websocket.send(JSON.stringify(dataObj));
        setTimeout(function(){
            websocket.close();
        },48000000)
    }
    function onClose(evt) {
        if(flag!=1&&step!=6){
            $('.creating-message').text('连接超时，请重新创建');
        }
        console.log("Disconnected");
    }

    function onMessage(evt,websocket) {
        var data = JSON.parse(evt.data);
        console.log(data);
        step = data.message.step;
        if(data.action == 'create'){
            var $progress = $('.create-progress'), $bar = $('.progress__bar'), $text = $('.progress__text'), percent = 0, orange = 30, yellow = 55, green = 85;
            percent = data.message.percent;
            $text.find('em').text(percent + '%');
            if (percent >= 100) {
                percent = 100;
                $progress.addClass('progress--complete');
                $bar.addClass('progress__bar--blue');
                $text.find('em').text('100%');
            } else {
                if (percent >= green) {
                    $bar.addClass('progress__bar--green');
                } else if (percent >= yellow) {
                    $bar.addClass('progress__bar--yellow');
                } else if (percent >= orange) {
                    $bar.addClass('progress__bar--orange');
                }
            }
            $bar.css({ width: percent + '%' });
//            $('.creating-message').text(data.message.extra);
            if(data.message.step == 1){
                $('.create-wiki-progress-wrap').css('background','rgba(100,0,255,.9)');
                if(data.status == 'success') {
                    $('.step1').removeClass('plan-active').addClass('plan-complete').text('用户验证成功');
                    $('.step2').removeClass('plan-coming').addClass('plan-active').text('创建站点中').append('<i class="fa fa-refresh  fa-1x fa-spin"></i>');
                    $('.step3').removeClass('plan-wait').addClass('plan-coming');
                }else{
                    $('.step1').text('用户验证失败');
                }
            }else if(data.message.step == 2){
                $('.create-wiki-progress-wrap').css('background','rgba(0,18,255,.9)');
                if(data.status == 'success') {
                    $('.step1').removeClass('plan-complete').addClass('plan-ago');
                    $('.step2').removeClass('plan-active').addClass('plan-complete').text('创建站点成功');
                    $('.step3').removeClass('plan-coming').addClass('plan-active').text('更新站点中').append('<i class="fa fa-refresh  fa-1x fa-spin"></i>');
                    $('.step4').removeClass('plan-wait').addClass('plan-coming');
                }else{
                    $('.step2').text('创建站点失败');
                }
            }else if(data.message.step == 3){
                $('.create-wiki-progress-wrap').css('background','rgba(0,147,255,.9)');
                if(data.status == 'success') {
                    $('.step2').removeClass('plan-complete').addClass('plan-ago');
                    $('.step3').removeClass('plan-active').addClass('plan-complete').text('更新站点成功');
                    $('.step4').removeClass('plan-coming').addClass('plan-active').text('提升权限中').append('<i class="fa fa-refresh  fa-1x fa-spin"></i>');
                    $('.step5').removeClass('plan-wait').addClass('plan-coming');
                }else{
                    $('.step3').text('更新站点失败');
                }
            }else if(data.message.step == 4){
                $('.create-wiki-progress-wrap').css('background','rgba(0,190,255,.9)');
                if(data.status == 'success') {
                    $('.step3').removeClass('plan-complete').addClass('plan-ago');
                    $('.step4').removeClass('plan-active').addClass('plan-complete').text('提升权限成功');
                    $('.step5').removeClass('plan-coming').addClass('plan-active').text('搬运模板中').append('<i class="fa fa-refresh  fa-1x fa-spin"></i>');
                    $('.step6').removeClass('plan-wait').addClass('plan-coming');
                }else{
                    $('.step3').removeClass('plan-complete').addClass('plan-ago');
                    $('.step4').removeClass('plan-active').addClass('plan-complete').text('提升权限失败');
                    $('.step5').removeClass('plan-coming').addClass('plan-active').text('搬运模板中').append('<i class="fa fa-refresh  fa-1x fa-spin"></i>');
                    $('.step6').removeClass('plan-wait').addClass('plan-coming');
                    flag = 4;
                }
            }else if(data.message.step == 5){
                $('.create-wiki-progress-wrap').css('background','rgba(34, 181, 114, 0.9)');
                if(data.status == 'success') {
                    $('.step4').removeClass('plan-complete').addClass('plan-ago');
                    $('.step5').removeClass('plan-active').addClass('plan-complete').text('搬运模板成功');
                    $('.step6').removeClass('plan-coming').addClass('plan-active').text('启动搜索中').append('<i class="fa fa-refresh  fa-1x fa-spin"></i>');
                }else{
                    $('.step3').text('搬运模板失败');
                }
            }else if(data.message.step == 6){
                $('.create-wiki-progress-wrap').css('background','rgba(34, 181, 34, 0.9)');
                if(data.status == 'success') {
                    $('.step5').hide();
                    $('.step6').text('启动搜索成功');
                }else{
                    $('.step5').hide();
                    $('.step6').text('启动搜索失败');
                    flag = 6;
                }
                if(flag==4){
                    $('.creating-message').append('<p>提升创建者站点权限失败，但不影响站点使用，请创建者联系support@huiji.wiki帮助。<a href="http://'+$('.create-wiki-form #url').val()+'">点击这里</a>跳转至您创建的站点></p>');
                }else if(flag==6){
                    $('.creating-message').append('<p>搜索功能开启失败，但不影响站点使用，请创建者联系support@huiji.wiki帮助。<a href="http://'+$('.create-wiki-form #url').val()+'">点击这里</a>跳转至您创建的站点></p>');
                }else if(flag==0){
                    window.location.href = 'http://'+$('.create-wiki-form #url').val();
                }
                websocket.close();
            }
        }
        if(data.status == 'fail'&&data.action == 'create'&&([1,2,3,5].indexOf(data.message.step))>=0){
            $('.create-wiki-progress-wrap').css('background','rgba(255, 0, 0, 0.9)');
            $('.creating-message').append('<p>创建站点失败，请重新创建（激活码未失效），或请创建者联系support@huiji.wiki帮助。</p>');
        }

    }
    function onError(evt) {
        console.log('Error occured: ' + evt.data);
    }
    $('body').append('<div class="create-wiki-progress-wrap"><div class="create-progress progress--active"><b class="progress__bar"><span class="progress__text">已完成: <em>0%</em></span></b></div>' +
        '<div class="creating-message">' +
        '<div class="plan step1 plan-active">用户验证中<i class="fa fa-refresh  fa-1x fa-spin"></i></div>' +
        '<div class="plan step2 plan-coming">即将进行创建站点...</div>' +
        '<div class="plan step3 plan-wait">即将进行更新站点...</div>' +
        '<div class="plan step4 plan-wait">即将进行提升权限...</div>' +
        '<div class="plan step5 plan-wait">即将进行搬运模板...</div>' +
        '<div class="plan step6 plan-wait">即将进行启动搜索...</div>' +
        '</div></div>');
    var option = 'internal';
    $('.create-wiki-radio>div').click(function(){
        $(this).find('input').get(0).checked = true;
        option = $(this).find('input').val();
    });
    $('.create-wiki-submit').click(function(){
        params.userName = mw.config.get('wgUserName');
        params.userId = mw.config.get('wgUserId');
        params.domainName = $('.create-wiki-form #name').val();
        params.domainPrefix = $('.create-wiki-form #url').val().replace('.huiji.wiki','');
        params.invitationCode = $('.create-wiki-form #code').val();
        params.domainType = $('.create-wiki-form #type').val();
        params.domainDescription = $('.create-wiki-form #description').val()!=''?$('.create-wiki-form #description').val():'\"\"';
        params.manifestName = option;
        dataObj.action = 'create';
        dataObj.target = 'wikisite';
        dataObj.params = params;
        if(mw.config.get("wgUserName")==null){
            $('.user-login').modal();
            return;
        }
        if($('.form-control-feedback.warning').length>0){
            mw.notification.notify('请修改错误信息',{tag:'error'});
            return;
        }
        for(var a in params){
            if(params[a] == ''&&a!='domainDescription'){
                mw.notification.notify('前四项不可为空',{tag:'error'});
                return;
            }
        }
        $('.create-wiki-progress-wrap').css('top','50px');
        if(WebSocket){
            var websocket = new WebSocket('ws://www.huiji.wiki:4000');
            websocket.onopen = function (evt) { onOpen(evt,websocket,dataObj) };
            websocket.onclose = function (evt) { onClose(evt) };
            websocket.onmessage = function (evt) { onMessage(evt,websocket) };
            websocket.onerror = function (evt) { onError(evt) };
        }else{
            var sitename = params.domainPrefix;
            $('.create-wiki-form #url').val(sitename);
            $('.create-wiki-form').submit();
        }

    })
});
