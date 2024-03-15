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
 * 菠萝支付
 * Class GT
 * @package Logic\Recharge\Pay
 */
class BL extends BASES
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
            'price' => sprintf('%.2f', $money),
            'order_id' => $this->orderID,
            'mark' => 'GOODS',
            'notify_url' => $this->notifyUrl,
            'app_id' => $this->partnerID,
            'time' => strtotime('now')
        );
        $this->parameter['sign'] = urlencode($this->sytMd5New($this->parameter,$this->key));
        $this->parameter['type'] = $this->data['bank_data'];
    }

    public function sytMd5New($pieces,$key)
    {
        ksort($pieces);
        $md5str = "";
        foreach ($pieces as $keyVal => $val) {
            $md5str = $md5str . $keyVal . "=" . $val . "&";
        }
        $md5str = $md5str . "key=" .$key ;
        $sign = md5(strtoupper($md5str));
        return $sign;
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        //网银与快捷
        if (isset($re['Status']) && $re['Status'] == '1') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['Result']['payurl'];
        } else {
            $msg = $re['Message'] ?? "未知异常";
            $this->return['code'] = 886;
            $this->return['msg'] = 'BL:' . $msg;
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }

    }

    //签名验证
    public function returnVerify($pieces)
    {

        $result = json_decode($pieces['return_type'], true);

        $res = [
            'status' => 1,
            'order_number' => $result['order_id'],
            'third_order' => $result['api_id'],
            'third_money' => $result['price'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($result['order_id']);
//        print_r($config);
//        print_r($config);exit;
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        $sign = $result['sign'];
        unset($result['sign']);
        if (self::retrunVail($sign, $result,$config['key'])) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    public function retrunVail($sign,$pieces,$key)
    {
        return $sign == $this->sytMd5New($pieces,$key);
    }

}