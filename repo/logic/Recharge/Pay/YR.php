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

class YR extends BASES
{
    //与第三方交互
    public function start()
    {
        $money = $this->money;
        if (isset($money) && is_numeric($money) && !strpos($money, '.')) {
            $this->initParam();
            $this->basePost();
            $this->parseRE();
        } else {
            $this->return['code'] = 1;
            $this->return['msg'] = 'YR:请输入整数';
            $this->return['way'] = $this->data['bank_data'];
            $this->return['str'] = '';
        }
    }

    //初始化参数
    public function initParam()
    {
        $this->parameter = array(
            'goodsname' => 'GOODS-YR',
            'istype' => $this->data['bank_data'],
            'notify_url' => $this->notifyUrl,
            'orderid' => $this->orderID,
            'orderuid' => '',
            'price' => $this->money/100,
            'return_url' => $this->returnUrl,
            'uid' => $this->partnerID,
        );
        $this->parameter['key'] = $this->sytMd5($this->parameter);
    }

    public function sytMd5($pieces)
    {
        $uid = $pieces['uid'];
        unset($pieces['uid']);
        $str = strtolower(md5(implode($pieces) . $this->key . $uid));
        return $str;
    }


    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if ($re['code'] == 1) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'success';
            $this->return['way'] = $this->showType;
//            $this->return['str'] = strtolower($re['data']['qrcode']);
            $str = $re['data']['qrcode'];
            $this->return['str'] = str_replace("HTTPS", "https", $str);
            $this->return['real_money'] = $re['data']['realprice'];
        } else {
            $this->return['code'] = 1;
            $this->return['msg'] = 'YR:' . $re['msg'];
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
            'third_order' => $parameters['transaction_id'],
            'third_money' => 0,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['orderid']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '未有该订单';
        }
        $result = $this->returnVail($parameters, $config);
//        var_dump($result);
//        die;
        if ($result) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    /**
     * 验证签名
     * orderid + orderuid + platform_trade_no + price + realprice + token
     * 按参数名字母升序排序。把参数值拼接在一起。做md5-32位加密，取字符串小写。得到key。
     */
    public function returnVail($pieces, $config)
    {

        $strArr['orderid'] = $pieces['orderid'];
        $strArr['orderuid'] = $pieces['orderuid'];
        $strArr['platform_trade_no'] = $pieces['platform_trade_no'];
        $strArr['price'] = $pieces['price'];
        $strArr['realprice'] = $pieces['realprice'];
        $strArr['key'] = $config['key'];
        $strSign = '';
        foreach ($strArr as $item) {
            $strSign .= $item;
        }
        $strSign = strtolower(md5($strSign));
        return $strSign == $pieces['key'];
    }
}