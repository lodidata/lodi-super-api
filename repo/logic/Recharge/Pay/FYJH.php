<?php
namespace Las\Pay;
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class FYJH extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();       // 数据初始化
        $this->parseRE();         // 处理结果
    }

    //组装数组
    public function initParam(){
        $this->parameter['cid']  = $this->partnerID;         //商户编码
        $this->parameter['uid']     = $this->randStr();                    //备注
        $this->parameter['time'] = time();  //订单生成的机器 IP
        $this->parameter['amount']      = $this->money/100;             //订单金额
        $this->parameter['order_id']     = $this->orderID;           //订单编号
        $this->parameter['ip'] = $this->data['client_ip'];  //订单生成的机器 IP
        $this->sort = false;
        $str = $this->arrayToURL();
        $this->parameter['sign'] = base64_encode(hash_hmac('sha1', $str, $this->key, true));            //校验码
        if($this->data['action']) {
            $this->parameter['type']       = $this->data['action']; //付款方式
        }
        if($this->data['bank_data']) {
            $this->parameter['tflag']       = $this->data['bank_data']; //付款方式
        }
    }

    //处理结果
    public function parseRE(){
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->data['return_type'];
        $this->return['str'] = $this->payUrl.'?'.$this->arrayToURL();
        $this->re = $this->return;
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
            'order_number' => $param['order_id'],
            'third_order'  => $param['order_id'],
            'third_money'  => $param['amount'],
            'error'        => ''
        ];

        if($param['status'] !=  'verified'){
            $res['error'] = '订单未支付';
            return $res;
        }

        $config = Recharge::getThirdConfig($param['order_id']);
        $ms = 'order_id='.$param['order_id'].'&amount=' . $param['amount'] .'&verified_time='.$param['verified_time'];
        $ms = base64_encode(hash_hmac('sha1', $ms, $config['key'], true));
        if(strtolower($param['qsign']) ==  strtolower($ms)){
            $res['status']  = 1;
        }else{
            $res['error'] = '该订单验签不通过或已完成';
        }
        return  $res;
    }

}
