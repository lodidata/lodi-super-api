<?php
namespace Logic\Recharge\Traits;

use Logic\Define\CallBack;
use Utils\Client;
use Utils\Utils;

trait RechargeCallback{
    /*
     * 金额统一为分操作
     * @return array flag => 1 OK,0 进入回调 ,2 不在IP白名单内  order_number =>订单号
     */
    public function returnVerify($data){
        $res = null;
        $obj = $this->getThirdClass(CALLBACK);   //初始化类
        //第三方验签返回数组[status=1 通过  0不通过,order_number = '订单','third_order'=第三方订单,'third_money'='金额','error'='未有该订单/订单未支付/未有该订单']
        $res = $obj->returnVerify($data);
        if($res) {

            //微信和支付宝源生的排除
            if(!in_array(CALLBACK,['ZFBORIGIN','WEIXINPAY'])) {
                $strrand = [
                    $this->redis->get('payAllowOrder:' . $res['order_number']),
                    'TGa2xKB5zqrPTv7N1574525329'
                ];
                //判定是否是合法的
                if (!in_array(CHECKCODE, $strrand)) {
                    return ['flag' => 0, 'order_number' => $res['order_number'], 'msg' => '不合法的回调'];
                }
            }

            //验签通过IP不在白名单中   返回数据的特殊性
            if ($this->isIPBlack($res['order_number'])) {
                return ['flag' => 2, 'order_number' => $res['order_number']];
            }
            //相同订单号多次回调重复回调的只记录一次    订单不存在或者已完成  故不用进入定时回调
            $fail = \DB::table('log_callback_failed')->where('status',1)->where('content','like',"%{$res['order_number']}%")->value('id');
            if($fail || !self::getThirdConfig($res['order_number']))
                return ['flag' => 1,'order_number' => $res['order_number']];

            if ($res['status']) {
                $tmp =  $this->insertQueue((string)$res['order_number'], (string)$res['third_order'], (float)$res['third_money']);
                return ['flag' => $tmp ? 1 : 0,'order_number' => $res['order_number']];
            } else {
                \DB::table('order')->where('status', '=','pending')->where('order_number', $res['order_number'])->update(['desc' => $res['error']]);
                self::logger($this, ['order_number' => $res['order_number'], 'desc' => $res['error']], 'log_callback');
                self::addLogByTxt(['order_number' => $res['order_number'], 'desc' => $res['error']], 'log_callback');
                return ['flag' => 1,'order_number' => $res['order_number']];
            }
        }
        return ['flag' => 0,'order_number' => $res['order_number']];
    }

    public static function getThirdConfig($order_number){
        $config = \DB::table('order')->leftJoin('passageway AS p','order.passageway_id','=','p.id')
            ->leftJoin('pay_config AS c','p.pay_config_id','=','c.id')
            ->where('order_number',$order_number)->where('order.status','=','pending')->first(['order_money','p.payurl','c.*']);
        if($config) {
            $config = (array)$config;
            return self::decryptConfig($config);
        }else
            return false;
    }

    public function verifyCallBack($thirdParam,$data){
        foreach (CallBack::$verify as $val){
            if(!isset($data[$thirdParam[$val]])){
                return false;
            }
        }
        return true;
    }

    public function insertQueue(string $order_number,string $third_order=null,float $third_money=null){
        self::logger($this,['queue:'.$order_number],'log_callback');
        \DB::table('order')->where('order_number',$order_number)->update(['third_order'=>$third_order,'third_money'=>$third_money]);
        \DB::table('success_tmp')->insert(['order_number' => $order_number, 'money' => $third_money]);  //防止进程卡或者挂
        try {
            \Utils\MQServer::send('recharge_callback', ['order_number' => $order_number, 'money' => $third_money]);
            return true;
        }catch (\Exception $e){
            return false;
        }
    }
}