<?php

namespace Las\Pay;
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 广州银商支付
 * @author Lion
 */


class GZYS extends BASES {

    static function instantiation(){
        return new GZYS();
    }
    //与第三方交互
    public function start(){
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){

       $this->parameter = array(
            'bb'     => '1.0',                         // 默认1.0
            'shid'   => $this->partnerID,              // 商户编号
            'ddh'    => $this->orderID,                // 商户订单号
            'je'     => str_replace(',', '', number_format($this->money/100, 2)),  //付款金额,
            'zftd'   => $this->data['bank_data'],      // 支付通道
            'ybtz'   => $this->notifyUrl,              // 异步通知URL
            'tbtz'   => $this->returnUrl,              // 同步跳转URL
            'ddmc'   => 'vip',                         // 订单名称
            'ddbz'   => 'vip'.$this->orderID,          // 订单备注
            'bankId' => '',                            // 银行id
        );

        $this->parameter['sign'] = $this->sign();      // md5签名串

    }

    public function sign(){

        $str = 'shid='.$this->parameter['shid'].'&bb='.$this->parameter['bb'].'&zftd='.$this->parameter['zftd'].'&ddh='.$this->parameter['ddh'].'&je='.$this->parameter['je'].'&ddmc='.$this->parameter['ddmc'].'&ddbz='.$this->parameter['ddbz'].'&ybtz='.$this->parameter['ybtz'].'&tbtz='.$this->parameter['tbtz'].'&'.$this->key;

        return md5($str);
    }
    public function parseRE(){

        $strArray = explode("href=", $this->re);
        if($strArray[1]){
            $strArray = explode(">支付宝手机用户点击支付", $strArray[1]);
            if($strArray[0]){
                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] = $this->showType;
                $str = str_replace('"','',$strArray[0]);
                $this->return['str'] = str_replace("HTTPS", "https", $str);
            }else{
                $this->return['code'] = 886;
                $this->return['msg'] = 'GZYS: 通道异常2';
                $this->return['way'] = $this->showType;
                $this->return['str'] = '';
            }
        }else{
            $this->return['code'] = 886;
            $this->return['msg'] = 'GZYS: 通道异常1';
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }

    }

    /**
     * 返回地址验证
     *
     * @param
     * @return boolean
     */
    public function returnVerify($parameters)
    {
        $res = [
            'status' => 0,
            'order_number' => $parameters['ddh'],
            'third_order' => $parameters['ddh'],
            'third_money' => '',
            'error' => ''
        ];

        $config = Recharge::getThirdConfig($parameters['ddh']);
        $str = 'status='.$parameters['status'].'&shid='.$parameters['shid'].'&bb='.$parameters['bb'].'&zftd='.$parameters['zftd'].'&ddh='.$parameters['ddh'].'&je='.$parameters['je'].'&ddmc='.$parameters['ddmc'].'&ddbz='.$parameters['ddbz'].'&ybtz='.$parameters['ybtz'].'&tbtz='.$parameters['tbtz'].'&'.$config['key'];

        $sign = strtolower(md5($str));
        $tenpaySign = strtolower($parameters['sign']);
        if($sign == $tenpaySign){
            $res['status']  = 1;
        }else{
            $res['error'] = '该订单验签不通过或已完成';
        }
        return  $res;

    }
}
