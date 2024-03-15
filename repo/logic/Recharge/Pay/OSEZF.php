<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 168支付
 * @author wuhuatao
 */
class OSEZF extends BASES
{

    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    //组装数组
    public function initParam()
    {
        $this->parameter = [
            //基本参数
            'time'       => time(),  // 时间戳
            'paytype'    => $this->payType,  // 支付类型:alipay:支付宝扫码，alipaywap：支付宝h5
            'money'      => str_replace(',', '', number_format($this->money / 100, 2)),  // 单位元（人民币）,两位小数点
            'mchNo'      => $this->partnerID,  // 商户id,由系统分配
            'remark'     => 'osezf',  // 商户备注
            'tradeno'    => $this->orderID,  // 商户系统订单号
            'notify_url' => $this->notifyUrl,  // 回调地址
            'returnurl'  => $this->returnUrl,  // 成功返回地址
        ];

        $this->parameter['sign'] = $this->createSign($this->parameter, $this->key);
    }

    public function parseRE()
    {
        $re = json_decode($this->re, true);

        if ($re['code'] != '200') {
            $msg = $re['return_msg'] ?? '';
            $this->return['code'] = 886;
            $this->return['msg'] = 'DYB:' . $msg;
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';

            return;
        }

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;

        if ($this->data['return_type'] == 'code' && $this->data['show_type'] == 'code') {
            $this->return['str'] = $re['s_code_url'];
        } else {
            $this->return['str'] = $re['data']['payurl'];
        }
    }

    /**
     * 返回地址验证
     *
     * @param
     *
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
            'status'       => 0,
            'order_number' => $parameters['tradeno'],
            'third_order'  => $parameters['orderid'],
            'third_money'  => $parameters['money'] * 100,
            'error'        => '',
        ];

        if ($parameters['status'] == 'success') {
            $config = Recharge::getThirdConfig($parameters['tradeno']);

            if ($this->verifyData($parameters, $config['key'])) {
                $res['status'] = 1;
            } else {
                $res['error'] = '该订单验签不通过或已完成';
            }
        } else {
            $res['error'] = '该订单未支付或者支付失败';
        }

        return $res;
    }

    public function verifyData($parameters, $key)
    {
        $signPars = "";
        ksort($parameters);
        foreach ($parameters as $k => $v) {
            if ("sign" != $k && "" != $v) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $signPars .= "key=" . $key;
        $sign = strtolower(md5((string)$signPars));
        $tenpaySign = $parameters['sign'];
        return $sign == $tenpaySign;

    }

    /**
     * 生成密钥
     */
    public function createSign($parameters, $key)
    {
        $signPars = "";
        ksort($parameters);
        foreach ($parameters as $k => $v) {
            if ("sign" != $k && "" != $v) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $signPars .= "key=" . $key;
        $sign = strtolower(md5((string)$signPars));
        return $sign;
    }
}
