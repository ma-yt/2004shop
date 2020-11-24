<?php

namespace App\Http\Controllers\Weixin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\XcxLogin;

class XcxController extends Controller
{
    public function xcxlogin(Request $request){
        $u = json_decode(file_get_contents('php://input'),true);
        $code = $request->code;
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid=".env('WX_XCX_APPID')."&secret=".env('WX_XCX_APPSECRET')."&js_code=".$code."&grant_type=authorization_code";
        $res = json_decode(file_get_contents($url),true);

        //自定义登录状态
        if(isset($res['errcode'])){      //有错误
            $response = [
                'err'=>50001,
                'msg'=>'登录失败',
            ];
        }else{      //成功
//            if(empty(XcxLogin::where('openid',$res['openid'])->first())){
//                $openid=["openid"=>$res["openid"]];
//                XcxLogin::insert($openid);
//            }
            $openid = $res['openid'];    //用户openid
            //判断新用户 老用户
            $user = XcxLogin::where(['openid'=>$openid])->first();
            if($user){
                //TODO  老用户
            }else{
                $user_info = [
                    'openid'=>$openid,
                    'nickname'=>$u['u']['nickName'],
                    'sex'=>$u['u']['gender'],
                    'language'=>$u['u']['language'],
                    'city'=>$u['u']['city'],
                    'province'=>$u['u']['province'],
                    'country'=>$u['u']['country'],
                    'headimgurl'=>$u['u']['avatarUrl'],
                    'add_time'=>time(),
                ];
                XcxLogin::insertGetId($user_info);
            }

            $token = sha1($res['openid'] . $res['session_key'].mt_rand(0,9999));
            //保存token
            $key = 'xcx_token:'.$token;
            Redis::set($key,time());
            Redis::expire($key,7200);
            $response = [
                'err'=>0,
                'msg'=>'ok',
                'data'=>[
                    'token'=>$token
                ]
            ];
        }
        return $response;
    }
}
