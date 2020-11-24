<?php

namespace App\Http\Controllers\Weixin;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;
use DB;
use App\Models\Goods;

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
        $goods_id = $request->get('goods_id');

        $goods = Goods::select('goods_name','shop_price','goods_img','goods_number','goods_thumb')->find($goods_id);

        $cart = Cart::where(['goods_id'=>$goods_id])->first();
        return $cart;
    }


    //添加用户
    public function adduser(){
        echo '<pre>';$_GET;echo '</pre>';
    }
}
