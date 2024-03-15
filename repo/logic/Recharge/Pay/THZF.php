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
use Utils\Curl;

/**
 * 菠萝mi支付
 * Class GT
 * @package Logic\Recharge\Pay
 */
class THZF extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        //$this->re = Curl::commonPost($this->payUrl,$this->cacertURL,$this->parameter);
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $money = $this->money / 100;
        $this->parameter = array(
            'mch_id' => $this->partnerID,
            'money' => intval($money),
            'order_no' => $this->orderID,
            'notifyurl' => $this->notifyUrl,
        );
        $this->sort = false;
        $this->parameter['sign'] = $this->currentMd5();
        $tmp = [
            'remark' => 'GOODS_'.time(),
            'paytype' => $this->payType,
     //       'get_code' => 1,
            'returnurl' => urlencode($this->returnUrl),
        ];
        $this->parameter['notifyurl'] = urlencode($this->notifyUrl);
        $this->parameter = array_merge($this->parameter,$tmp);
    }

    //返回参数
    public function parseRE()
    {

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->payUrl.'?'.$this->arrayToURL();
 /*       $re = json_decode($this->re, true);
        //网银与快捷
        if (isset($re['status']) && $re['status'] == 200) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['url'];
        } else {
            $msg = $re['msg'] ?? "未知异常";
            $this->return['code'] = 886;
            $this->return['msg'] = 'THZF:' . $msg;
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
 */

    }

    //签名验证
    public function returnVerify($result)
    {
        $res = [
            'status' => 0,
            'order_number' => $result['order_no'],
            'third_order' => $result['sdpayno'] ?? '',
            'third_money' => $result['money'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($result['order_no']);
        if (!$config ) {
            $res['status'] = 0;
            $res['error'] = '没有该订单或已完成';
        }elseif($result['status'] != 1) {
            $res['status'] = 0;
            $res['error'] = '未支付';
        }else {
            $this->parameter = [
                'mch_id' => $config['partner_id'],
                'status' => $result['status'],
                'sdpayno' =>$result['sdpayno'],
                'order_no' => $result['order_no'],
                'money' => $result['money'],
                'paytype' => $result['paytype'],
            ];
            $this->sort = false;
            $this->key = $config['key'];
            if(strtolower($result['sign']) == strtolower($this->currentMd5())) {
                $res['status'] = 1;
            }else {
                $res['status'] = 0;
                $res['error'] = '验签失败';
            }
        }
        return $res;
    }

}