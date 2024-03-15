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
 * 菠萝mi支付
 * Class GT
 * @package Logic\Recharge\Pay
 */
class BLM extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $money = $this->money / 100;
        $this->parameter = array(
            'shopAccountId' => $this->partnerID,
            'shopUserId' => rand(1,88888),
            'amountInString' => sprintf('%.2f', $money),
            'shopNo' => $this->orderID,
            'payChannel' => $this->payType,
        );
        $tmp = [
            'shopCallbackUrl' => $this->notifyUrl,
            'returnUrl' => $this->returnUrl,
            'target' => 3,
        ];
        $this->sort = false;
        $this->parameter['sign'] = $this->md5();
        $this->parameter = array_merge($this->parameter,$tmp);
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        //网银与快捷
        if (isset($re['code']) && $re['code'] == '0') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['page_url'];
        } else {
            $msg = $re['message'] ?? "未知异常";
            $this->return['code'] = 886;
            $this->return['msg'] = 'BLM:' . $msg;
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }

    }

    //签名验证
    public function returnVerify($result)
    {
        $res = [
            'status' => 0,
            'order_number' => $result['shop_no'],
            'third_order' => $result['order_no'],
            'third_money' => $result['money'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($result['shop_no']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单或已完成';
        }elseif($result['status'] != 0) {
            $res['status'] = 0;
            $res['error'] = '未支付';
        }else {
            $tmp = [
                'shopAccountId' => $config['partner_id'],
                'user_id' => $result['user_id'],
                'trade_no' =>$result['trade_no'],
                'key' => $config['key'],
                'money' => $result['money'],
                'type' => $result['type'],
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