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

/**
 * 云支付
 * Class YZF
 * @package Logic\Recharge\Pay
 */
class YUNZF extends BASES
{
    //与第三方交互
    public function start(){
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    //初始化参数
    public function initParam(){
        $this->parameter = [
            'AgentID' => $this->partnerID,
            'OrderNo' => $this->orderID,
            'ClientIp' => Client::getClientIp(),
            'GoodsName' => 'YUNZF_GOODS',
            'PayMoney' => $this->money ,
            'PayModel' => $this->data['bank_data'],
            'NotifyUrl' => $this->notifyUrl,
            'ReturnUrl' => $this->returnUrl,
        ];

        $sign = $this->_getSign($this->parameter, $this->key);

        $this->parameter = array_merge($this->parameter, [
            'Sign' => $sign,
        ]);
    }

    /**
     * 创建签名字符串
     *
     * @access private
     *
     * @param array $input 参与签名参数
     * @param string $key 密钥
     *
     * @return string 签名字符串
     */
    private function _getSign(array $input, string $key) {
        ksort($input);

        $sign = md5(urldecode(http_build_query($input)) . '&Key=' . $key);

        return $sign;
    }


    //返回参数
    public function parseRE(){
        $result = json_decode($this->re, true);
        if (!$result || $result['status'] != '10000' || empty($result['data'])) {
            $this->return['code'] = 23;
            $this->return['msg'] = 'YUNZF: ' . $result['msg'];
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';

            return;
        }

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $result['data'];
    }

    //签名验证
    public function returnVerify($parameters) {
        $res = [
            'status' => 1,
            'order_number' => $parameters['data']['AgentOrder'],
            'third_order' => $parameters['data']['OrderNo'],
            'third_money' => 0,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['data']['AgentOrder']);

        if(!$config){
            $res['status']=0;
            $res['error']='未有该订单';
        }else{
            $result=$this->returnVail($parameters['data'],$config);
            if($result){
                $res['status']=1;
            }else{
                $res['status']=0;
                $res['error']='验签失败！';
            }
        }
        return $res;
    }

    public function returnVail($parameters,$config){
        $resu=md5('PayMoney=' . $parameters['PayMoney'] . '&SettleMoney=' . $parameters['SettleMoney'] . '&OrderNo=' . $parameters['OrderNo'] . '&AgentOrder=' . $parameters['AgentOrder'] . '&Key=' . $config['key']) == $parameters['Sign'];
        return $resu;
    }
}