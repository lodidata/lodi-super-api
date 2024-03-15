<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 *  金猪支付
 * Class GT
 * @package Logic\Recharge\Pay
 */
class JZZF extends BASES
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
            'mer_id' => $this->partnerID,//商户id
            'order_no' => $this->orderID,//商户系统订单号
            'amount' => $this->money / 100,//订单金额，单位为：元
            'apply_time' => date("Y-m-d H:i:s"),//订单标题
            'gateway_id' => $this->data['bank_data'],//支付方式
            'notify' => $this->notifyUrl,//下行异步通知的地址，需要以http(s)://开头且没有任何参数
            'callback' => $this->returnUrl,//页面跳转返回地址
            'version' => '01',//页面跳转返回地址
        );
        $sign = $this->sign($arr, $this->key);//32位小写MD5签名值
        $arr['sign'] = $sign;
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
        $this->parameter .= '&url=' . urlencode($this->payUrl);
        $this->parameter .= '&method=POST';

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->jumpURL . '?' . $this->parameter;

        //给re赋值，记录到数据库
        $this->re = $this->return;
    }

    //异步回调的签名验证
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 0,
            'order_number' => $pieces['order_no'],//商户系统内部订单号
            'third_order' => $pieces['order_no'],//系统流水号
            'third_money' => $pieces['amount'] * 100,//订单金额，系统是元，需要转为分
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['order_no']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }
        if ($pieces['code'] != '1') {
            $res['status'] = 0;
            $res['error'] = '支付失败';
            return $res;
        }
        if (self::verifySign($pieces, $pieces['sign'], $config['key'])) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }
// 数据格式化
    function dataFormat($args){
        $signPars = "";
        ksort($args);
        foreach ($args as $k => $v) {
            if ("" != $v && "sign" != $k) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        return $signPars;
    }

// 新建签名
    function createSign($args, $key)
    {
        $signPars = self::dataFormat($args);
        $signPars .= "key=" . $key; //key秘钥
        $sign = md5($signPars);
        $sign = strtoupper($sign); //转为大写 //strtolower小写
        return $sign; //最终的签名
    }

// 验证签名
    function verifySign($data,$sign,$key)
    {
        if (self::createSign($data, $key) == $sign)
        {
            return True;
        } else {
            return False;
        }
    }
}