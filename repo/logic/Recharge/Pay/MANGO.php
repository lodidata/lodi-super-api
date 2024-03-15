<?php
/**
 * 芒果支付
 * @author Taylor 2019-05-07
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class MANGO extends BASES
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
     * 提交参数组装
     */
    public function initParam()
    {
        $this->returnUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/return_url.php';

        $pub_params = [
            'pay_memberid' => (string)$this->partnerID,//商户号
            'pay_orderid' => (string)$this->orderID,//商户订单号
            'pay_applydate' => date('Y-m-d H:i:s'),//支付时间
            'pay_user_id' => rand(1, 100000),//支付用户id
            'pay_ip' => $this->data['client_ip'],//支付IP
            'pay_bankcode' => (string)$this->payType,//银行编码，bank_data
            'pay_notifyurl' => $this->notifyUrl,//异步接受支付结果通知的回调地址
            'pay_callbackurl' => $this->returnUrl,//页面跳转返回地址
            'pay_amount' => $this->money / 100,//商品金额，元（精确小数点后两位）
        ];
        $pub_params['pay_md5sign'] = $this->_sign($pub_params, $this->key);
        $pub_params['pay_productname'] = 'VIP:'.$this->orderID;
        $this->parameter = $pub_params;
        //var_dump($this->parameter);exit();
    }

    /**
     * 组装前端数据,输出结果，使用go.php方法，自动post到支付
     */
    public function parseRE()
    {
        //{"status":"error","msg":"通道:单笔交易金额[101.00/20000.00]","data":[]}
        //使用表单提交的方式
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
    }

    /**
     * 生成sign
     */
    private function _sign($pieces, $tkey)
    {
        ksort($pieces);
        $string = [];
        foreach ($pieces as $key=>$val)
        {
            $string[] = $key.'='.$val;
        }
        $params = join('&',$string);
        $sign_str = $params.'&key='.$tkey;
        $sign = md5($sign_str);
        return strtoupper($sign);
    }

    /**
     * 回调验证处理
     * 获取接口返回数据，再次验证
     */
    public function returnVerify($data)
    {
        $res = [
            'status' => 0,//默认支付失败
            'order_number' => $data['orderid'],//商户订单号
            'third_order' => $data['transaction_id'],//系统订单号
            'third_money' => $data['amount']*100,//支付金额
            'error' => '',
        ];

        if ($data['returncode'] != '00') {
            $res['error'] = '支付订单状态未成功';
            return $res;
        }

        $config = Recharge::getThirdConfig($res['order_number']);
        if (!$config) {
            $res['error'] = '该订单不存在';
            return $res;
        }

        if ($this->returnVail($data, $config['key']) === false) {
            $res['error'] = '签名验证失败';
            return $res;
        }
        $res['status'] = 1;//支付成功
        return $res;
    }

    /**
     * 签名校验
     * @param $params
     * @param $tkey
     */
    public function returnVail($params, $tkey)
    {
        //验签字段
        $s_params = [
            "memberid" => $params["memberid"], // 商户ID
            "orderid" =>  $params["orderid"], // 订单号
            "amount" =>  $params["amount"], // 交易金额
            "datetime" =>  $params["datetime"], // 交易时间
            "transaction_id" => $params["transaction_id"], // 支付流水号
            "returncode" => $params["returncode"] //订单状态
        ];
        ksort($s_params);
        reset($s_params);
        $md5str = "";
        foreach ($s_params as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $tkey));
        if ($sign != $params['sign']){
            return false;
        }
        return true;
    }
}