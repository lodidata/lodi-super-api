<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 云计费 捷汇
 * @author lzx
 *  支付类型 QQ扫码 qqpayqr  微信扫码 wechatqr 支付宝扫码 alipayqr 快捷 quickpay
 */

class YJF extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->get();
        $this->parseRE();
    }

    //组装数组
    public function initParam(){
        $uid = mt_rand(100000,99999999999);
        $custormId = $uid."_".md5($this->partnerID."|".$this->key."|".$uid);
        // 需要加入签名的字段
        $this->parameter = array(
            //基本参数
            'P_UserId'=>$this->partnerID,
            'P_OrderId'=>$this->orderID,
            'P_FaceValue'=>$this->money/100,
            'P_ChannelId' => "1",
            'P_SDKVersion' => "3.1.4",
            'P_RequestType' => $this->data['action'],//客户端类型,决定收银台样式,主要区分web/wap, 0:web,1:wap,2:iOS,3:Andriod
        );
        $this->parameter['P_PostKey'] = md5(implode('|',array_merge($this->parameter,['SalfStr' => $this->key])));
        $temp = array(
            'P_Payway'=>$this->payType,
            'P_CustormId'=>$custormId,
            'P_Subject' => '-',
        );
        $this->parameter = array_merge($temp,$this->parameter);
    }

    public function parseRE(){
        $re = json_decode($this->re,true);
        if(isset($re['flag']) && $re['flag'] == 1){
            $temp =  strstr($re['code_url'],'<USER>');
            $t = str_replace($temp,'',$re['code_url']);
            $url = $t.urlencode($temp);
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $url;
        }else{
            $this->return['code'] = 886;
            $this->return['msg'] = 'YJF:'.$re['err_msg'];
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
    //[status=1 通过  0不通过,
    //order_number = '订单',
    //'third_order'=第三方订单,
    //'third_money'='金额',
    //'error'='未有该订单/订单未支付/未有该订单']
    public function returnVerify($parameters) {
        $res = [
            'status' => 0,
            'order_number' => $parameters['P_OrderId'],
            'third_order' => $parameters['P_SMPayId'],
            'third_money' => $parameters['P_PayMoney'] * 100,
            'error' => '',
        ];

        if($parameters['P_ErrCode'] == 0){
            $config = Recharge::getThirdConfig($parameters['P_OrderId']);
            if($this->verifyData($parameters,$config['pub_key'])){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';
        return $res;
    }

    public function verifyData($input_arr,$key){
        $orderId = $input_arr['P_OrderId'];//商户系统的订单ID，唯一
        $restate = $input_arr['P_ErrCode'];
        $ovalue = $input_arr['P_PayMoney'];
        $P_UserId = $input_arr['P_UserId'];
        $P_SMPayId = $input_arr['P_SMPayId'];
        $P_FaceValue = $input_arr['P_FaceValue'];
        $P_ChannelId = $input_arr['P_ChannelId'];
        $P_PostKey = $input_arr['P_PostKey'];

        //生成签名
        $sign = md5($P_UserId."|".$orderId."|".$P_SMPayId."|".$P_FaceValue."|".$P_ChannelId ."|".$key);
        //校验签名
        if( $sign == $P_PostKey ){
            return true;
        }
        return false;
    }
}
