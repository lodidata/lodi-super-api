<?php
namespace Logic\Recharge\Traits;

use Utils\Curl;

trait RechargeCallbacks{
    protected $callbacksApi = '/api/Game/RechargeById';
    /*
     * 金额统一为分操作
     * @return array flag => 1 OK,0 进入回调 ,2 不在IP白名单内  order_number =>订单号
     */
    public function callbackNotify($data,$config){
        $obj = $this->getThirdClass2(CALLBACK);   //初始化类
        //第三方验签返回数组
        //[status=1 通过  0不通过,
        //uid = '用户ID',      若没有可传0
        //order_number = '订单',
        //deal_number=第三方订单,
        //'money'='支付金额',       各自类中统一为 -- 分，
        //'error'='未有该订单/订单未支付/未有该订单']
        $res = $obj->callbacVerify($data,$config);
        if(!$res) {
            return ['error'=>'query timeout'];
        }
        if($res['status'] != 1) {
            return $res;
        }
        $timestamp = (int)(microtime(true) * 1000);
        $param['Param'] = [
            'ID' => $res['uid'],  //
            'Gold' => $res['money'],
            'Desc' => 'Success',
            'BillNo' => $res['order_number'],
        ];
        $sign='';
        if(isset($config['app_id'])){
            $sign = md5($config['app_id'].';'.$this->callbacksApi.';'.json_encode($param).';'.$timestamp);
        }

        $url = $config['notify_url'].$this->callbacksApi.'?ts='.$timestamp.'&sign='.$sign;
//        $re = Curl::post($url, '', $param);
//        $re = json_decode($re, true);
        \DB::table('success_tmp2')->updateOrInsert(['order_number' => $res['order_number'], 'appid' => $config['app_id'] ] , [
            'order_number' => $res['order_number'],
            'appid' => isset($config['app_id']) ?$config['app_id']:'',
            'notify_url' => $url,
            'param' => json_encode($param),
            'status' => $res['status'],
            'error' => $res['error'],
        ]);
        return $res;
    }

    public function notifyCustomer2(){
        $data = \DB::table('success_tmp2')->where('status',1)->get()->toArray();
        foreach ($data as $val) {
            $param = json_decode($val->param,true);
            $re = Curl::post($val->notify_url, '', $param);
            $re = json_decode($re, true);
            if(!$re) continue;
            if($re['State'] == 1) {
                \DB::table('success_tmp2')->delete($val->id);
            }else {
                \DB::table('success_tmp2')->where('id',$val->id)->update(['status' => 0,'error'=>$re['ErrMes']]);
            }
        }
    }

}