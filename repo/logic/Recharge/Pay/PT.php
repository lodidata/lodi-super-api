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
 * 平头支付
 * Class PT
 * @package Logic\Recharge\Pay
 */
class PT extends BASES
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
        $this->parameter = array(
            'appId' => $this->partnerID,
            'orderId' => $this->orderID,
            'feeType' => 0,
            'totalFee' => $this->money,
            'payType' => $this->data['bank_data'],
            'notifyUrl' => $this->notifyUrl,
            'returnUrl' => $this->returnUrl
        );
        if($this->parameter['payType']=='gateway'){
            $this->parameter['bankCode']=$this->data['bank_code'];
        }
        $this->parameter['sign'] = $this->sytMd5New($this->parameter,$this->key);
    }

    public function sytMd5New($pieces,$key)
    {
        ksort($pieces);
        $md5str = "";
        foreach ($pieces as $keyVal => $val) {
            $md5str = $md5str . $keyVal . "=" . $val . "&";
        }
        $md5str = $md5str . "key=" .$key ;
        $sign = strtoupper(md5($md5str));
        return $sign;
    }

    //返回参数
    public function parseRE()
    {
        if($this->data['bank_data']=='gateway' || $this->data['bank_data']=='alipayHtml'){
            $this->return['str'] = 'http://pay-api.zypaymet.com/go.php' .'?method=HTML&html='.urlencode(base64_encode($this->re));
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = 'jump';
        }else{
            $re = json_decode($this->re, true);
            //网银与快捷
            if (isset($re['code']) && $re['code'] == '200') {
                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = $re['qrcode'];
            } else {
                $msg = $re['msg'] ?? "未知异常";
                $this->return['code'] = 886;
                $this->return['msg'] = 'PT:' . $msg;
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = '';
            }
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 1,
            'order_number' => $pieces['orderId'],
            'third_order' => $pieces['outerOrderId'],
            'third_money' => $pieces['totalFee'] ,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['orderId']);

        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        $sign = $pieces['sign'];
        if (self::retrunVail($sign, $pieces,$config['key'])) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    public function retrunVail($sign,$pieces,$key)
    {
        $md5str='outerOrderId='.$pieces['outerOrderId'].'&'.'orderId='.$pieces['orderId'].'&'.'key='.$key;
        $new_sign = strtoupper(md5($md5str));
        return $sign == $new_sign;
    }

}