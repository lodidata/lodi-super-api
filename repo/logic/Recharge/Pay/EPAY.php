<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/10/18
 * Time: 15:01
 */

namespace Logic\Recharge\Pay;


use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;


class EPAY extends Bases
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
            'command' => $this->data['action'],
            'serverCode' => 'ser2001',
            'merchNo' => $this->partnerID,
            'version' => '2.0',
            'charset' => 'utf-8',
            'currency' => 'CNY',
            'reqIp' => Client::getClientIp(),
            'reqTime' => date('Ymdhis', time()),
            'signType' => 'MD5',
            'payType' => $this->payType,
            'cOrderNo' => $this->orderID,
            'amount' => $this->money / 100,
            'goodsName' => 'goods',
            'goodsNum' => 1,
            'goodsDesc' => 'goods_desc',
            'memberId' => $this->partnerID,
            'notifyUrl' => $this->notifyUrl
        ];


        $sign = $this->_getSign($this->parameter, $this->key);

        $this->parameter = array_merge($this->parameter, [
            'sign' => $sign,
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

        $sign = md5(urldecode(http_build_query($input)) . '&key=' . $key);

        return $sign;
    }


    //返回参数
    public function parseRE(){
        $result = json_decode($this->re, true);

        if (!isset($result['command'])) {
            $this->return['code'] = 23;
            $this->return['msg'] = 'XD: ' . $result['message'];
            $this->return['way'] = $this->data['return_type'];;
            $this->return['str'] = '';

            return;
        }

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->data['return_type'];;
        $this->return['str'] = $result['payUrl'];
    }

    //签名验证
    public function returnVerify($parameters) {
        $res = [
            'status' => 1,
            'order_number' => $parameters['cOrderNo'],
            'third_order' => $parameters['pOrderNo'],
            'third_money' => $parameters['amount'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['cOrderNo']);

        if(!$config){
            $res['status']=0;
            $res['error']='未有该订单';
        }else{
            $result=$this->returnVail($parameters,$config);
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

        $temp = $parameters;
        unset($temp['sign']);
        ksort($temp);
        $sign = md5(http_build_query($temp).'key='.$config['key']);

        return $sign == $parameters['sign'] ? true : false;
    }

}