<?php
/**
 * 恒通支付
 * Author: Taylor 2019-03-13
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class HTZF extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();//数据初始化
        $this->parseRE();//处理结果
    }

    //组装数组
    public function initParam(){
        $this->parameter['customer'] = $this->partnerID;//商户id，由恒通商户系统分配
        if(!empty($this->data['bank_data'])){//银联快捷的没有这项
            $this->parameter['banktype'] = $this->data['bank_data'];//支付类型或银行类型
            $this->parameter['israndom'] = 'Y';//启用订单风控保护规则
        }
        $this->parameter['amount'] = $this->money/100;//单位元，保留两位小数
        $this->parameter['orderid'] = $this->orderID;//商户系统订单号
        $this->parameter['asynbackurl'] = $this->notifyUrl;//异步通知过程的返回地址
        $this->parameter['request_time'] = date('YmdHis');//系统请求时间
        $this->parameter['synbackurl'] = $this->returnUrl;//同步通知过程的返回地址
        $this->parameter['attach'] = 'VIP'.$this->orderID;//备注信息
        $this->parameter['sign'] = $this->sign($this->parameter, $this->key);//签名
    }

    //生成签名
    public function sign($data, $key){
        if(!empty($data['banktype'])){
            $ms = "customer={$data['customer']}&banktype={$data['banktype']}&amount={$data['amount']}&orderid={$data['orderid']}&asynbackurl={$data['asynbackurl']}&request_time={$data['request_time']}&key={$key}";
        }else{
            $ms = "customer={$data['customer']}&amount={$data['amount']}&orderid={$data['orderid']}&asynbackurl={$data['asynbackurl']}&request_time={$data['request_time']}&key={$key}";
        }
        return md5($ms);
    }

    //处理结果
    public function parseRE(){
        //使用表单提交的方式
        $this->parameter['url'] = $this->payUrl;
        $this->parameter['method'] = 'POST';

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->jumpURL . '?' . $this->arrayToURL();
    }

    /*
     * $resArray 第三方通知数组
     * $deposit  系统流水信息
     * $vender   授权信息
     * */
    public function returnVerify($pieces) {
        $res = [
            'status' => 1,
            'order_number' => $pieces['orderid'],//商户订单号
            'third_order' => $pieces['systemorderid'],//第三方的支付订单号
            'third_money' => $pieces['payamount'] * 100,//支付金额为元
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['orderid']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if($pieces['result'] != '1'){
            $res['status'] = 0;
            $res['error'] = '支付失败';
        }
        if (self::retrunVail($pieces, $config['key'])) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    //验签
    public function retrunVail($array, $signKey)
    {
        $sys_sign = $array['sign'];
        $my_sign = md5("orderid={$array['orderid']}&result={$array['result']}&amount={$array['amount']}&systemorderid={$array['systemorderid']}&completetime={$array['completetime']}&key={$signKey}");
        return $my_sign == $sys_sign;
    }
}
