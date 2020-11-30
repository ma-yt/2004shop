<?php

namespace App\Http\Controllers\Weixin;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;
use DB;
use App\Models\Goods;
use App\Models\XcxLogin;
use Illuminate\Support\Facades\Redis;

class ApiController extends Controller
{
    public function test(){
        $data =DB::table('ecs_goods')->get()->toArray();
        return $data;
    }

    //商品列表
    public function glist(Request $request){
        $page_size = $request->get('size');
        $goods = Goods::select('goods_id','goods_name','shop_price','goods_img','goods_number')->take('10')->paginate($page_size);
        $response = [
            'error'=>0,
            'msg'=>'ok',
            'data'=>[
                'list'=>$goods->items()
            ]
        ];
        return $response;
    }

    //详情页
    public function detail(Request $request){
        $goods_id = $request->get('goods_id');
        $detail = Goods::select('goods_id','goods_name','shop_price','goods_img','goods_number','goods_thumb')->where('goods_id',$goods_id)->first()->toArray();
        $array=[
            'goods_id'=>$detail['goods_id'],
            'goods_imgs'=>explode(",",$detail['goods_thumb']),
            'goods_name'=>$detail['goods_name'],
            'shop_price'=>$detail['shop_price'],
        ];
//        $response = [
//            'error'=>0,
//            'msg'=>'ok',
//            'data'=>[
//                'list'=>$detail
//            ]
//        ];
        return $array;
    }

    //添加购物车
    public function cart(Request $request){
        $goods_id = $request->post('goods_id');
        $goods_number = $request->post('goods_number');
        $uid = $_SERVER['uid'];

        //查询商品价格
        $shop_price = Goods::find($goods_id)->shop_price;

        //判断购物车中商品是否存在
        $goods = Cart::where(['uid'=>$uid,'goods_id'=>$goods_id])->first();
        if($goods){  //增加数量
            Goods::where(['goods_id'=>$goods_id])->update(['goods_number'=>$goods_number]);
            $response = [
                'errno'=>0,
                'msg'=>'ok'
            ];
        }else{
            //将商品存储购物车表 或 redis
            $info = [
                'goods_id'=>$goods_id,
                'user_id'=>$uid,
                'goods_number'=>1,
                'add_time'=>time(),
                'cart_price'=>$shop_price
            ];

            $cart = Cart::insertGetId($info);
            if($cart){
                $response = [
                    'errno'=>0,
                    'msg'=>'ok'
                ];
            }else{
                $response = [
                    'errno'=>50002,
                    'msg'=>'加入购物车失败'
                ];
            }
        }
        return $response;
    }


    //购物车列表
    public function cartlist(){
        $uid = $_SERVER['uid'];
        $goods = Cart::where(['user_id'=>$uid])->get();
        if($goods)      //购物车有商品
        {
            $goods = $goods->toArray();
            foreach($goods as $k=>&$v)
            {
                $g = Goods::find($v['goods_id']);
                $v['goods_name'] = $g->goods_name;
                $v['goods_img']=explode(",",$g['goods_img']);
            }
        }else{          //购物车无商品
            $goods = [];
        }

        //echo '<pre>';print_r($goods);echo '</pre>';die;
        $response = [
            'errno' => 0,
            'msg'   => 'ok',
            'data'  => [
                'list'  => $goods
            ]
        ];

        return $response;
    }

    //添加用户
    public function adduser(){
        echo '<pre>';$_GET;echo '</pre>';
    }


    //用户个人中心登录
    public function userlogin(Request $request){
        //接收code
        //$code = $request->get('code');
        $token = $request->get('token');

        //获取用户信息
        $userinfo = json_decode(file_get_contents("php://input"), true);
        $redis_login_hash = 'h:xcx:login:' . $token;
            $openid = Redis::hget($redis_login_hash, 'openid');         //用户OpenID

        $u0 = XcxLogin::where(['openid' => $openid])->first();
//        dd($u0->update_time);
        if($u0->update_time == 0){     // 未更新过资料
            //因为用户已经在首页登录过 所以只需更新用户信息表
            $u_info = [
                'nickname'=>$userinfo['u']['nickName'],
                'sex'=>$userinfo['u']['gender'],
                'language'=>$userinfo['u']['language'],
                'city'=>$userinfo['u']['city'],
                'province'=>$userinfo['u']['province'],
                'country'=>$userinfo['u']['country'],
                'headimgurl'=>$userinfo['u']['avatarUrl'],
                'update_time'   => time()
            ];
            XcxLogin::where('openid',$u0->openid)->update($u_info);
        }

        $response = [
            'errno' => 0,
            'msg' => 'ok',
        ];

        return $response;
    }

    //添加收藏
    public function addfav(Request $request){
        $goods_id = $request->get('goods_id');
//        $token = $request->get('token');

        //加入收藏redis有序集合
        $uid = 2345;
        $key = 'xcx:add-fav'.$uid;   //用户收藏商品的有序集合
        Redis::zadd($key,time(),$goods_id);  //将商品id加入有序集合并给排序值
        $response = [
            'errno'=>0,
            'msg'=>'ok'
        ];

        return $response;
    }


//    //小程序你首页登录
    public function homeLogin(Request $request)
    {
        //接收code
        $code = $request->get('code');

        //使用code
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid=' . env('WX_XCX_APPID') . '&secret=' . env('WX_XCX_APPSECRET') . '&js_code=' . $code . '&grant_type=authorization_code';
        $data = json_decode(file_get_contents($url), true);
        //自定义登录状态
        if (isset($data['errcode']))     //有错误
        {
            $response = [
                'errno' => 50001,
                'msg' => '登录失败',
            ];

        } else {              //成功
            $openid = $data['openid'];          //用户OpenID
            //判断新用户 老用户
            $u = XcxLogin::where(['openid' => $openid])->first();
            if ($u) {
                // TODO 老用户
                $uid = $u->id;
                //更新用户信息

            } else {
                // TODO 新用户
                $u_info = [
                    'openid' => $openid,
                    'add_time' => time(),
                    'type' => 3        //小程序
                ];

                $uid = XcxLogin::insertGetId($u_info);
            }

            //生成token
            $token = sha1($data['openid'] . $data['session_key'] . mt_rand(0, 999999));
            //保存token
            $redis_login_hash = 'xcx_token:' . $token;

            $login_info = [
                'uid' => $uid,
                'user_name' => "",
                'login_time' => date('Y-m-d H:i:s'),
                'login_ip' => $request->getClientIp(),
                'token' => $token,
                'openid'    => $openid
            ];

            //保存登录信息
            Redis::hMset($redis_login_hash, $login_info);
            // 设置过期时间
            Redis::expire($redis_login_hash, 7200);

            $response = [
                'errno' => 0,
                'msg' => 'ok',
                'data' => [
                    'token' => $token
                ]
            ];
        }
        return $response;

    }

    //购物车删除
    public function cartdel(Request $request){
        $goods_id = $request->post('goods');
        $goods_arr = explode(',',$goods_id);

        $res = Cart::whereIn('goods_id',$goods_arr)->delete();
        if($res){    //删除成功走if
           $response = [
               'errno'=>0,
               'msg'=>'ok'
           ];
        }else{      //删除失败走else
            $response = [
                'errno'=>50002,
                'msg'=>'删除失败'
            ];
        }
        return $response;
    }
}
