<?php

/**
 *  全名-法拉利支付
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class FLLZF extends BASES
{

    /**
     * 生命周期
     */
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    /**
     * 接入方有收银台，需要在接入方填入金额和充值方式后，以参数的形式传递给支付接口
     * */
    public function initParam() {



        $param = array(
            "uid"            =>      $this->partnerID,               // 用户ID
            "price"          =>      sprintf('%.2f', $this->money / 100), //保留两位小数
            "paytype"        =>      $this->payType,             // 充值方式 支付宝:13  微信:1
            "notify_url"     =>      $this->notifyUrl,        // 充值成功后的回调地址
            "return_url"     =>      $this->returnUrl,        // 充值成功后返回的地址,暂时不用
            "user_order_no"  =>      $this->orderID,             // 订单号，保证唯一, 用于对账的时候使用
        );

        $sign = $this->getSign($param, $this->key);

        $param['tm']	= date("Y-m-d H:i:s",time());
        $param["sign"] = $sign;

        $this->parameter = $param;
    }

    /**
     * 组装前端数据,输出结果，使用go.php方法，自动post到支付
     */
    public function parseRE()
    {
        foreach ($this->parameter as &$item) {
            $item = urlencode($item);
        }
        $this->parameter = $this->arrayToURL();
        $this->parameter .= '&url=' . $this->payUrl;
        $this->parameter .= '&method=POST';

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->jumpURL . '?' . $this->parameter;

        $this->re = $this->return;
    }

    /**
     * 回调验证处理
     * 获取接口返回数据，再次验证
     */
    public function returnVerify($parameters)
    {
        global $app;
        $parameters = $app->getContainer()->request->getParams();
        unset($parameters['s']);

        if(!isset($parameters['user_order_no'])){
            $res['status'] = 0;
            $res['error'] = '非法数据';
            return $res;
        }

        $res = [
            'order_number' => $parameters['user_order_no'],
            'third_order' => $parameters['orderno'],
            'third_money' => $parameters['price'] * 100,
            'status' => 0,
            'error' => ''
        ];

        $config = Recharge::getThirdConfig($parameters['user_order_no']);

        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }

        $result = $this->returnVail($parameters, $config['key']);
        if (!$result) {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
            return $res;
        }
        $res['status'] = 1;

        return $res;
    }


    /**
     * 加密数据
     */
    private function getSign($param, $paySecret) {
        $string = '';

        foreach($param as $value)
        {
            $string .= $value;
        }
        return md5($string.$paySecret);
    }


    /**
     * 回调后进行业务判断
     * @param $params
     * @param $conf
     * @param $reques_params
     * @return bool
     */
    public function returnVail($params, $tkey)
    {
        $return_sign = $params['sign'];

        unset($params['sign']);
        unset($params['attach']);
        $sign_data = [
            'user_order_no' => $params['user_order_no'],
            'orderno' => $params['orderno'],
            'tradeno' => $params['tradeno'],
            'price' => $params['price'],
            'realprice' => $params['realprice'],
        ];
        $string = '';

        foreach($sign_data as $value)
        {
            $string .= $value;
        }
        $sign = md5($string.$tkey);
        if ($sign != $return_sign) {
            return false;
        }
        return true;
    }
}
