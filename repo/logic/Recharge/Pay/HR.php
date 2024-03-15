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
use Psr\Http\Message\ServerRequestInterface;

/**
 * 华人支付
 * Class GT
 * @package Logic\Recharge\Pay
 */
class HR extends BASES
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
        $this->parameter = array(
            'merchant_no' => $this->partnerID,
            'total_fee' => $this->money,
            'pay_num' => $this->orderID,
            'today' => date('Ymd'),
        );
        $this->parameter['sign'] = urlencode(strtoupper(md5(implode('',$this->parameter).$this->key)));
        $this->parameter['trade_type'] = $this->payType;
        unset($this->parameter['today']);
        $this->parameter['notifyurl'] = urlencode($this->notifyUrl);
        $this->parameter['return_url'] = urlencode($this->returnUrl);
    }

    //返回参数
    public function parseRE()
    {
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->payUrl.'?'.$this->arrayToURL();
    }

    //签名验证
    public function returnVerify($result)
    {
        $res = [
            'status' => 0,
            'order_number' => $result['mcOrderid'],
            'third_order' => $result['out_trade_no'],
            'third_money' => $result['amount'],
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($result['mcOrderid']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单或已完成';
        }elseif($result['return_code'] != 10000 || $result['trade_result'] != 'success') {
            $res['status'] = 0;
            $res['error'] = '未支付';
        }else {
            $tmp = [
                'merchant_no' => $config['partner_id'],
                'out_trade_no' =>$result['out_trade_no'],
                'mcOrderid' => $result['mcOrderid'],
                'amount' => $result['amount'],
                'key' => $config['key'],
            ];
            if(strtolower($result['sign']) == strtolower(md5(implode('',$tmp)))) {
                $res['status'] = 1;
            }else {
                $res['status'] = 0;
                $res['error'] = '验签失败';
            }
        }
        return $res;
    }

}