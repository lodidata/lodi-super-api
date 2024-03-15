<?php


namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;
use Psr\Http\Message\ServerRequestInterface;

/**
 *
 * Class 奇然支付
 * @package Logic\Recharge\Pay
 */
class QRZF extends BASES
{
    protected $param;

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
            'uid' => $this->partnerID,
            'price' => sprintf('%.2f',$this->money/100),
            'order_id' => $this->orderID,
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl,
        );
        $signStr = $this->parameter['uid'].$this->parameter['price'].$this->parameter['order_id'].$this->parameter['notify_url'].$this->parameter['return_url'].$this->key;
//        var_dump($this->parameter);
//        print_r($signStr);exit;
        $this->parameter['sign'] = md5($signStr);
    }


    //返回参数
    public function parseRE()
    {
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->payUrl . '?' . $this->arrayToURL();
    }

    //签名验证
    public function returnVerify($result)
    {
        global $app;
        $result = $app->getContainer()->request->getParams();
        if (!isset($result['order_id']) || !isset($result['price']))
        {
            return false;
        }
        $res = [
            'status' => 0,
            'order_number' => $result['order_id'],
            'third_order' => $result['order_id'],
            'third_money' => $result['price']*100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($result['order_id']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }
        $signStr = $result['order_id'].$result['price'].$result['txnTime'].$config['key'];
        if ($result['sign'] == md5($signStr)) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

}