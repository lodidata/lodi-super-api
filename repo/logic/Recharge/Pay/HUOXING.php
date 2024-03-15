<?php
/**
 * Author: Taylor 2019-06-19
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 * 火星支付对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class HUOXING extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $arr = array(
            'pay_memberid' => $this->partnerID,//商户id
            'pay_orderid' => $this->orderID,//商户系统订单号
            'pay_amount' => $this->money/100,//订单金额，单位为：元
            'pay_applydate'=> date("Y-m-d H:i:s"),//订单标题
            'pay_bankcode'=> $this->data['bank_data'],//支付方式
            'pay_notifyurl' => $this->notifyUrl,//下行异步通知的地址，需要以http(s)://开头且没有任何参数
            'pay_callbackurl'=>$this->returnUrl,//页面跳转返回地址
        );
        $sign = $this->sign($arr, $this->key);//32位小写MD5签名值
        $arr['pay_md5sign'] = $sign;
//        $arr['pay_return'] = 1;
        $arr['pay_productname'] = 'VIP:'.$this->orderID;
        $this->parameter = $arr;
    }

    //生成支付签名
    public function sign($arr, $md5key)
    {
        ksort($arr);
        $md5str = "";
        foreach ($arr as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        return strtoupper(md5($md5str . "key=" . $md5key));
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
    }

    //异步回调的签名验证
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 1,
            'order_number' => $pieces['orderid'],//商户系统内部订单号
            'third_order' => $pieces['transaction_id'],//系统流水号
            'third_money' => $pieces['real_amount'] * 100,//订单金额，系统是元，需要转为分
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['orderid']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if($pieces['returncode'] != '00'){
            $res['status'] = 0;
            $res['error'] = '支付失败';
        }
        if (self::retrunVail($pieces, $config['key'])) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    //验签
    public function retrunVail($responseData, $md5key)
    {
        $returnArray = array( // 返回字段
            "memberid" => $responseData["memberid"], // 商户ID
            "orderid" =>  $responseData["orderid"], // 订单号
            "amount" =>  $responseData["amount"], // 交易金额
            "datetime" =>  $responseData["datetime"], // 交易时间
            "transaction_id" =>  $responseData["transaction_id"], // 支付流水号
            "returncode" => $responseData["returncode"],
            "real_amount" => $responseData["real_amount"]//实际金额
        );
        ksort($returnArray);
        reset($returnArray);
        $md5str = "";
        foreach ($returnArray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $md5key));
        if ($sign == $responseData["sign"]) {
            return true;
        }else{
            return false;
        }
    }
}