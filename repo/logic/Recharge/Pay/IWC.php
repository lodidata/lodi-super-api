<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/19
 * Time: 10:09
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

class IWC extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->basePost();
        $this->parseRE();
        //$this->doPay();
    }

    //初始化参数
    public function initParam()
    {
        $this->parameter = [
            //基本参数
            'fxpay' => $this->data['bank_data'],
            'fxid' => $this->partnerID,
            'fxddh' => $this->orderID,
            'fxfee' => $this->money/100,
            'fxip' => $this->data['client_ip'],
            'fxnotifyurl' => $this->notifyUrl,
            'fxdesc' => 'GOODS',
            'fxbackurl' => $this->returnUrl
        ];
        $this->parameter['fxsign'] = urlencode($this->signStr($this->parameter, $this->key));
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if ($re['status']==1) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'success';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['payurl'];
        } else {
            $this->return['code'] = 1;
            $this->return['msg'] = 'IWC:' . $re['error'] ?? '请求错误';
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }

    }


    //签名验证
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 1,
            'order_number' => $pieces['fxddh'],
            'third_order' => $pieces['fxorder'],
            'third_money' => $pieces['fxfee']*100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['fxddh']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '订单不存在';
        }
        $strSign=md5($pieces['fxstatus'].$pieces['fxid'].$pieces['fxddh'].$pieces['fxfee']. $config['key']);
        $result = $strSign == $pieces['fxsign'];

        if ($result) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }

        return $res;
    }

    public function signStr($pieces, $key)
    {
        $signStr=md5($pieces['fxid'].$pieces['fxddh'].$pieces['fxfee'].$pieces['fxnotifyurl'].$key);
        return $signStr;
    }

}