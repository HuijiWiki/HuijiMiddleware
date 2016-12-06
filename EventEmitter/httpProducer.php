<?php
//包含工具类
include("EventUtil.php");
/*
 * 消息发布者者
 */
final class HttpProducer
{
    private static $_instance = null;
    //签名
    private static $signature = "Signature";
    //在MQ控制台创建的Producer ID
    private static $producerid = "ProducerID";
    //阿里云身份验证码
       private static $aks = "AccessKey";
    //配置信息
       private static $configs = null;
    //构造函数
    function __construct()
    {
            //读取配置信息
            $this::$configs = parse_ini_file("/var/confidential/config.ini");
    }

    public static function getIns(){//能过公开的getIns从内部获得一个对象

      if(empty(self::$_instance)){//如果对象不存在，就创建一个对象，并返回
         self::$_instance = new HttpProducer();
      }
       return self::$_instance;
    }

    //计算md5
    private function md5($str)
    {
        return md5($str);
    }
    //发布消息流程
    public function process($key,$tag,$data) 
    {
        //打印配置信息
//        var_dump($this::$configs);
        //获取Topic
        $topic = $this::$configs["Topic"];
        //获取保存Topic的URL路径
        $url = $this::$configs["URL"];
        //读取阿里云访问码
        $ak = $this::$configs["Ak"];
        //读取阿里云密钥
        $sk = $this::$configs["Sk"];
        //读取Producer ID
        $pid = $this::$configs["ProducerID"];
        //HTTP请求体内容
        $body = $data;
        $newline = "\n";
        //构造工具对象
        $util = new EventUtil();

        //计算时间戳
   	$date = time()*1000;
    	//POST请求url
   	$postUrl = $url."/message/?topic=".$topic."&time=".$date."&tag=".$tag."&key=".$key;
    	//签名字符串
   	$signString = $topic.$newline.$pid.$newline.$this->md5($body).$newline.$date;
    	//计算签名
    	$sign = $util->calSignatue($signString,$sk);
    	//初始化网络通信模块
    	$ch = curl_init();
    	//构造签名标记
    	$signFlag = $this::$signature.":".$sign;
    	//构造密钥标记
   	$akFlag = $this::$aks.":".$ak;
   	//标记
   	$producerFlag = $this::$producerid.":".$pid;
   	//构造HTTP请求头部内容类型标记
   	$contentFlag = "Content-Type:application/json;charset=UTF-8";
 
    	//构造HTTP请求头部
    	$headers = array(
		$signFlag,
		$akFlag,
		$producerFlag,
		$contentFlag,
   	 );
   	//设置HTTP头部内容
    	curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
   	//设置HTTP请求类型,此处为POST
   	curl_setopt($ch,CURLOPT_CUSTOMREQUEST,"POST");
    	//设置HTTP请求的URL
        curl_setopt($ch,CURLOPT_URL,$postUrl);
       	//设置HTTP请求的body
       	curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
     	//构造执行环境
      	ob_start();
      	//开始发送HTTP请求
      	curl_exec($ch);
      	//获取请求应答消息
     	$result = ob_get_contents();
      	//清理执行环境
      	ob_end_clean();
    	//打印请求应答结果
    	//var_dump($result);
    	//关闭连接
    	curl_close($ch);
    }
}
//构造消息发布者
//$producer = new HttpProducer();
//启动消息发布者
//  HttpProducer::getIns()->process("dfdfd","http","http");
?>
