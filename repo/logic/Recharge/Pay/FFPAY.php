<?php
namespace Las\Pay;
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;



class FFPAY extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();       // 数据初始化
        $this->basePost();        // 发送请求
        $this->parseRE();         // 处理结果
    }

    //组装数组
    public function initParam(){
        $this->parameter['MerchantCode']  = $this->partnerID;         //商户编码
        $this->parameter['BankCode']       = $this->payType; //付款方式
        $this->parameter['Amount']      = intval($this->money/100).'.00';             //订单金额
        $this->parameter['OrderId']     = $this->orderID;           //订单编号
        $this->parameter['NotifyUrl']     = $this->notifyUrl;         //通知URL
        $this->parameter['OrderDate'] = time()*1000;  //订单生成的机器 IP
        $this->parameter['Ip'] = $this->data['client_ip'];  //订单生成的机器 IP

        $this->parameter['Sign'] = $this->currentMd5('Key=');            //校验码
        $this->parameter['ReturnUrl']     = $this->returnUrl;         //返回URL
    }

    //处理结果
    public function parseRE(){
        $re =  json_decode($this->re,true);
        if (isset($re['data']['data']['info']) && $re['data']['data']['info']) {
            if($re['data']['data']['type'] == 'string') {
                $this->parameter['method'] = 'POST';
                $re['data']['data']['info'] = $this->jumpURL.'?method=HTML&html='.urlencode(base64_encode($re['data']['data']['info']));
            }
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['data']['data']['info'];
        } else {
            $this->return['code'] =  886;
            $this->return['msg'] = 'FFPAY:' . $re['resultMsg'];
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
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
            'order_number' => $param['OrderId'],
            'third_order'  => $param['OutTradeNo'],
            'third_money'  => $param['Amount']*100,
            'error'        => ''
        ];

        if($param['Status'] != 1) {
            $res['error'] = '订单未支付';
            return $res;
        }
        $config = Recharge::getThirdConfig($param['OrderId']);
        $sign = $param['Sign'];
        unset($param['Sign']);
        unset($param['Remark']);
        $this->parameter = $param;
        $this->key = $config['key'];
        if(strtolower($sign) ==  strtolower($this->currentMd5('Key='))){
            $res['status']  = 1;
        }else{
            $res['error'] = '该订单验签不通过或已完成';
        }
        return  $res;
    }

}
