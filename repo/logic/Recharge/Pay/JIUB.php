<?php
namespace Las\Pay;
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 *
 * 98支付
 * @author Lion
 */
class JIUB extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();       // 数据初始化
        $this->get();        // 发送请求
        $this->parseRE();         // 处理结果
    }

    //组装数组
    public function initParam(){
        $this->parameter['amount']      = $this->money;             //订单金额
        $this->parameter['returnUrl']     = $this->returnUrl;         //返回URL
        $this->parameter['appUserId']     = $this->randStr();                    //备注
        $this->parameter['info']         = 'goods';                    //备注
        $this->parameter['extra']     = $this->orderID;           //订单编号
        $this->parameter['notifyUrl']     = $this->notifyUrl;         //通知URL
        $this->parameter['type']       = $this->data['bank_data']; //付款方式
        $this->parameter['cid']  = $this->partnerID;         //商户编码
        $this->parameter['ip'] = $this->data['client_ip'];  //订单生成的机器 IP
        $this->parameter['time'] = time();  //订单生成的机器 IP
        $this->parameter['sign'] = md5($this->parameter['time'].$this->parameter['cid'].$this->parameter['type'].$this->parameter['amount'].$this->key);            //校验码
    }

    //处理结果
    public function parseRE(){
        $re =  json_decode($this->re,true);
        if ($re['code'] == 0 && $re['payUrl']) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['payUrl'];
        } else {
            $this->return['code'] =  886;
            $this->return['msg'] = 'JIUB:' . $re['message'];
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }
    public function randStr(int $length = 6, string $chars = '1234567890') {
        $chars_length = strlen($chars);

        $string = '';
        while ($length--) {
            $string .= substr($chars, mt_rand(0, $chars_length - 1), 1);
        }

        return $string;
    }

    //回调数据校验
    /*
     * $resArray 第三方通知数组
     * $deposit  系统流水信息
     * $vender   授权信息
     * */
    public function returnVerify($param = array()) {
        $res = [
            'status' => 0,
            'order_number' => $param['extra'],
            'third_order'  => $param['orderId'],
            'third_money'  => $param['amount'],
            'error'        => ''
        ];

        $config = Recharge::getThirdConfig($param['extra']);
        $ms = $param['time'] . $param['cid'] .$param['type'] .$param['orderId'] .$param['amount'] .  $config['key'];
        if(strtolower($param['sign']) ==  strtolower(md5($ms))){
            $res['status']  = 1;
        }else{
            $res['error'] = '该订单验签不通过或已完成';
        }
        return  $res;
    }

}
