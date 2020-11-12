<?php

namespace App\Http\Controllers\Weixin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use GuzzleHttp\Client;
use App\Models\User_info;
class IndexController extends Controller
{

    public function index()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];

        $token = env('WX_TOKEN');
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );

        if( $tmpStr == $signature ){
            echo $_GET['echostr'];
        }else{
            echo '123';
        }
    }


    public function event()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];

        $token = env('WX_TOKEN');
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );

        if( $tmpStr == $signature ){
           //1、接收数据
            $xml_data = file_get_contents("php://input");
            //记录日志
            file_put_contents('wx_event.log',$xml_data);
//            echo "";
//            die;
            //2、把xml文本转换成为php的对象或数组
            $data = simplexml_load_string($xml_data,'SimpleXMLElement',LIBXML_NOCDATA);
//            file_put_contents('a.txt',$xml_data);die;
            if($data->MsgType=="event"){
                if($data->Event=="subscribe"){
                    $accesstoken = $this->gettoken();
                    $openid = $data->FromUserName;
                    $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$accesstoken."&openid=".$openid."&lang=zh_CN";
                    $user = file_get_contents($url);
                    $res = json_decode($user,true);
                    if(isset($res['errcode'])){
                        file_put_contents('wx_event.log',$res['errcode']);
                    }else{
                        $user_id = User_info::where('openid',$openid)->first();
                        if($user_id){
                            $user_id->subscribe=1;
                            $user_id->save();
                            $contentt = "感谢再次关注";
                        }else{
                            $res = [
                                'subscribe'=>$res['subscribe'],
                                'openid'=>$res['openid'],
                                'nickname'=>$res['nickname'],
                                'sex'=>$res['sex'],
                                'city'=>$res['city'],
                                'country'=>$res['country'],
                                'province'=>$res['province'],
                                'language'=>$res['language'],
                                'headimgurl'=>$res['headimgurl'],
                                'subscribe_time'=>$res['subscribe_time'],
                                'subscribe_scene'=>$res['subscribe_scene']

                            ];
                            User_info::insert($res);
                            $contentt = "欢迎老铁关注";

                        }

                    }
                    echo $this->responseMsg($data,$contentt);

                }
                //取消关注
                if($data->Event=='unsubscribe'){
                    $user_id->subscribe=0;
                    $user_id->save();
                }
            }
            //天气
            if($data->MsgType=="text"){
                $city = urlencode(str_replace("天气:","",$data->Content));
                $key = "e2ca2bb61958e6478028e72b8a7a8b60";
                $url = "http://apis.juhe.cn/simpleWeather/query?city=".$city."&key=".$key;
                $tianqi = file_get_contents($url);
                //file_put_contents('tianqi.txt',$tianqi);
                $res = json_decode($tianqi,true);
                $content="";
                if($res['error_code']==0){
                    $today = $res['result']['realtime'];
                    $content .= "查询天气的城市:".$res['result']['city']."\n";
                    $content .= "天气详细情况".$today['info']."\n";
                    $content .= "温度".$today['temperature']."\n";
                    $content .= "湿度".$today['humidity']."\n";
                    $content .= "风向".$today['direct']."\n";
                    $content .= "风力".$today['power']."\n";
                    $content .= "空气质量指数".$today['aqi']."\n";

                    //获取一个星期的天气
                    $future = $res['result']['future'];
                    foreach($future as $k=>$v){
                        $content .= "日期:".date("Y-m-d",strtotime($v['date'])).$v['temperature'].",";
                        $content .= "天气:".$v['weather']."\n";
                    }
                }else{
                    $content = "你查寻的天气失败，请输入正确的格式:天气、城市";
                }
                file_put_contents("tianqi.txt",$content);

                echo $this->responseMsg($data,$content);

            }
        }//else{
          //  echo "";
       // }
    }


    public function gettoken(){

        $key = "AccessToken";

        $token = Redis::get($key);

        if(!$token){
            echo "没有缓存";
//            $stream_opts = [
//                "ssl" => [
//                    "verify_peer"=>false,
//                    "verify_peer_name"=>false,
//                ]
//            ];
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".env('WX_APPID')."&secret=".env('WX_APPSECRET');

//            $client = new Client();   //实例化客户端
//            $response = $client->request('GET',$url,['verify'=>false]);    //发起请求并接受响应
//            $json_str = $response->getBody();   //服务器的响应数据
//            echo $json_str;

//            $token=file_get_contents($url,false,stream_context_create($stream_opts));
            $token=file_get_contents($url);

            $tok = json_decode($token,true);
            $token = $tok['access_token'];
            Redis::set($key,$token);
            Redis::expire($key,3600);
        }
        return $token;
    }

    //关注回复
    public function responseMsg($array,$Content){
                $ToUserName = $array->FromUserName;
                $FromUserName = $array->ToUserName;
                $CreateTime = time();
                $MsgType = "text";

                $text = "<xml>
                  <ToUserName><![CDATA[%s]]></ToUserName>
                  <FromUserName><![CDATA[%s]]></FromUserName>
                  <CreateTime>%s</CreateTime>
                  <MsgType><![CDATA[%s]]></MsgType>
                  <Content><![CDATA[%s]]></Content>
                </xml>";
                echo sprintf($text,$ToUserName,$FromUserName,$CreateTime,$MsgType,$Content);
    }

    //上传素材
    public function guzzle2(){
        $access_token = $this->gettoken();
        $type = "image";
        $url = "https://api.weixin.qq.com/cgi-bin/media/upload?access_token=".$access_token."&type=".$type;
        //使用guzzle发送get请求
        $client = new Client();   //实例化客户端
        $response = $client->request('POST',$url,[
            'verify' => false,
            'multipart' => [
                [
                    'name'=> 'media',   //上传文件的路径
                    'contents' => fopen('iphone.jpg','r'),   //上传文件的路径
                ],

            ]
        ]);    //发起请求并接受响应
        $data = $response->getBody();
        echo $data;
    }


    //创建自定义菜单
    public function menu(){
        $menu = [
                 "button"=>[
                         [
                              "type"=>"click",
                              "name"=>"获取天气",
                              "key"=>"V1001_TODAY_MUSIC"
                         ],
                         [
                             "name"=>"商城",
                             "sub_button"=>[
                                 [
                                     "type"=>"view",
                                     "name"=>"京东好货",
                                     "url"=>"http://www.jd.com"
                                 ],
                                 [
                                     "type"=>"view",
                                     "name"=>"商城",
                                     "url"=>"http://laravel.mayatong.top"
                                 ]
                             ]
                         ],
                         [
                               "name"=>"菜单",
                               "sub_button"=>[
                                   [
                                       "type"=>"view",
                                       "name"=>"搜索",
                                       "url"=>"http://www.baidu.com/"
                                    ],
                                    [
                                       "type"=>"click",
                                       "name"=>"赞一下我们",
                                       "key"=>"V1001_GOOD"
                                    ]
                               ]
                         ]]
            ];
        $access_token = $this->gettoken();
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$access_token;
        $client = new Client();  //实例化客户端
        $response = $client->request('POST',$url,[   //发起请求并且接受响应
            'verify'=>false,
            'body'=>json_encode($menu,JSON_UNESCAPED_UNICODE)
        ]);
        $res = $response->getBody();   //响应服务器的数据
        echo $res;
    }

    //素材下载
    public function media(){
        $xml = file_get_contents("php://input");
//        file_put_contents('wx_event.log',$xml);die;
        $obj = simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA);
        $media_id = $obj->MediaId;
        $access_token = $this->gettoken();
        $url = "https://api.weixin.qq.com/cgi-bin/media/get?access_token=".$access_token."&media_id=".$media_id;
        $res = file_get_contents($url);
        file_put_contents('123.jpg',$res);
    }
}
