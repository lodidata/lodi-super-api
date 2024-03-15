<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * blf支付
 * @author viva
 */
class BLFZF extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter = array(
            //基本参数
            'goodsname' => $this->money/100,//订单金额，单位为：元
            'istype' => $this->payType,//接口类型
            'notify_url' => $this->notifyUrl,
            'orderid' => $this->orderID,
            'orderuid' => $this->data['app_id'], //商户账号名
            'uid' => $this->partnerID,//必填项，商户号
        );
        $this->parameter['key'] = $this->sign($this->parameter,$this->key);
    }

    public function parseRE(){
        $re = json_decode($this->re, true);

        if($re['code'] == '1'){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['data']['pay_url'];
        }else{
            $msg =  $re['retRemark'];
            $this->return['code'] = 886;
            $this->return['msg'] = 'BLFZF:'.$msg;
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
        $this->re = $this->return;

    }

    public function returnVerify($data) {

        global $app;
        $data = $app->getContainer()->request->getParams();
        unset($data['s']);

        if(!isset($data['orderid'])){
            $res['status'] = 0;
            $res['error'] = '非法数据';
            return $res;
        }

        $res = [
            'status' => 0,
            'order_number' => $data['orderid'],
            'third_order' => $data['platform_trade_no'],
            'third_money' => $data['price'] * 100,
            'error' => '',
        ];

        $config = Recharge::getThirdConfig($data['orderid']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }

        if (!$this->returnVail($data, $config['pub_key'])) {
            $res['status'] = 0;
            $res['error'] = '签名验证失败';
            return $res;
        }

        $res['status'] = 1;
        return $res;
    }

    public function returnVail($data,$key) {
        $returnSign = $data['key'];
        $sign = md5($data['orderid'].$data['orderuid'].$data['platform_trade_no'].$data['price'].$key);
        return strtolower($sign) == $returnSign;

    }

    function sign($params, $signKey)//签名方式
    {
        $sign = md5($params['goodsname'].$params['istype'].$params['notify_url'].$params['orderid'].$params['orderuid'].$signKey.$params['uid']);
        return strtolower($sign);
    }
}
