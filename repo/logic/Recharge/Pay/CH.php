<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 畅汇
 * @author viva
 */

class CH extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->sort = false;
        if(strstr($this->payType , 'WAP') || $this->payType == 'OnlinePay' || $this->payType == 'Nocard_H5'){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->parameter['hmac'] = urlencode($this->parameter['hmac']);
            $this->return['str'] = $this->payUrl .= '?'.$this->arrayToURL();
            return;
        }
        $this->get();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter = array(
            'p0_Cmd'     => "Buy",# 支付请求，固定值"Buy"
            'p1_MerId'	 => $this->partnerID,				 		#测试使用
            'p2_Order'   => $this->orderID,
            'p3_Cur'	 => "CNY",
            'p4_Amt'     => $this->money/100 .'',
            'p5_Pid'	 => $this->orderID,
            'p6_Pcat'	 => 'goods',
            'p7_Pdesc'	 => 'goods_desc',
            'p8_Url'     => $this->notifyUrl,
            'pa_FrpId'   => $this->payType,
            'pi_Url'     => $this->returnUrl,
        );
        if($this->payType == 'OnlinePay')
            $this->parameter['pg_BankCode'] = $this->data['bank_code'];
        $this->parameter['hmac'] = $this->HmacMd5($this->parameter,$this->key);
    }

    public function parseRE(){
        $re = json_decode($this->re,true);
        if(isset($re['r1_Code']) && $re['r1_Code'] == 1){
            $this->return['code'] = 0;
            $this->return['msg']  = 'SUCCESS';
            $this->return['way']  = $this->showType;
            $this->return['str']  = $re['r3_PayInfo'];
        }else{
            $this->return['code'] = 886;
            $this->return['msg']  = 'CH:'.$re['r7_Desc'];
            $this->return['way']  = $this->showType;
            $this->return['str']  = '';
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
    public function returnVerify($parameters)
    {
        $res = [
            'status' => 0,
            'order_number' => $parameters['r6_Order'],
            'third_order'  => $parameters['r2_TrxId'],
            'third_money'  => $parameters['r3_Amt'] * 100,
            'error'        => '',
        ];

        if ($parameters['r1_Code'] == 1) {
            $config = Recharge::getThirdConfig($parameters['r6_Order']);
            if ($this->verifyData($parameters, $config['pub_key'])) {
                $res['status'] = 1;
            } else
                $res['error'] = '该订单验签不通过或已完成';
        } else
            $res['error'] = '该订单未支付';

        return $res;
    }

    public function verifyData($parameters,$key) {
        $tenpaySign = strtolower($parameters['hmac']);
        unset($parameters['hmac']);
        $sign = $this->HmacMd5($parameters,$key);
        return $sign == $tenpaySign;
    }


}






