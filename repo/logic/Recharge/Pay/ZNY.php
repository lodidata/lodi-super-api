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

class ZNY extends BASES
{
    //与第三方交互
    public function start()
    {
        $money = $this->money;
        if (strpos($money, '.')) {
            $str = explode('.', $money);
            if (substr($str[1], -1) != 0) {
                $this->initParam();
                $this->basePost();
//                $this->curlPost();
                $this->parseRE();

            } else {
                $this->return['code'] = 63;
                $this->return['msg'] = 'ZNY:' . '充值金额格式不正确，下单金额末位不得为0';
                $this->return['way'] = '';
                $this->return['str'] = '';
                return;
            }
        } else {
            $this->initParam();
            $this->parseRE();
        }
    }

    //初始化参数
    public function initParam()
    {
        $this->parameter = array(
            'uid' => $this->partnerID,
            'orderid' => $this->orderID,
            'istype' => $this->data['bank_data'],
            'price' => $this->money / 100,
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl
        );
        $this->parameter['key'] = $this->sytMd5($this->parameter);
    }

    public function sytMd5($pieces)
    {
        $signArr['istype'] = $this->data['bank_data'];
        $signArr['notify_url'] = $pieces['notify_url'];
        $signArr['orderid'] = $pieces['orderid'];
        $signArr['price'] = $pieces['price'];
        $signArr['return_url'] = $pieces['return_url'];
        $signArr['token'] = $this->key;
        $signArr['uid'] = $pieces['uid'];
        $strSign = '';
        foreach ($signArr as $item) {
            $strSign .= $item;
        }
        $strSign = strtolower(md5($strSign));
        return $strSign;
    }


    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if ($re['code'] == 200) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'success';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['data']['qrcode'];
            $this->return['real_money'] = $re['data']['realprice'];
            if (strstr($this->return['str'], '.COM')) {
                $strArr = explode('.COM', $this->return['str']);
                $str = strtolower($strArr[0]) . ".com" . $strArr[1];
                $this->return['str'] = $str;
            }

        } else {
            $this->return['code'] = $re['code'] ?? 1;
            $this->return['msg'] = 'ZNY:' . $re['msg'] ?? '请求错误';
            $this->return['way'] = $this->data['bank_data'];
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($parameters)
    {
        $res = [
            'status' => 1,
            'order_number' => $parameters['orderid'],
            'third_order' => $parameters['ordno'],
            'third_money' => 0,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['orderid']);

        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '未有该订单';
        }
        $strArr['orderid'] = $parameters['orderid'];
        $strArr['ordno'] = $parameters['ordno'];
        $strArr['price'] = $parameters['price'];
        $strArr['realprice'] = $parameters['realprice'];
        $strArr['key'] = $config['key'];
        $strSign = '';
        foreach ($strArr as $item) {
            $strSign .= $item;
        }

        $strSign = strtolower(md5($strSign));
        $result = $strSign == $parameters['key'];

        if ($result) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }
}