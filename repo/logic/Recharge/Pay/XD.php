<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/10/18
 * Time: 13:56
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;



/**
 * 鑫达支付
 * Class XD
 * @package Logic\Recharge\Pay
 */

class XD extends BASES
{
    //与第三方交互
    public function start(){
        $this->initParam();
        //$this->basePost();
        $this->parseRE();
    }

    //初始化参数
    public function initParam(){
        $this->parameter = [
            'pay_memberid' => $this->partnerID,
            'pay_orderid' => $this->orderID,
            'pay_amount' => $this->money/100 ,
            'pay_applydate' => date('Y-m-d H:i:s'),
            'pay_bankcode' => $this->data['bank_code'] ? : 'KFTzfb',
            'pay_notifyurl' => $this->notifyUrl,
            'pay_callbackurl' => $this->returnUrl,
        ];

        $sign = $this->_getSign($this->parameter, $this->key);

        $this->parameter = array_merge($this->parameter, [
            'pay_md5sign' => $sign,
            'tongdao' => $this->payType,
            //'pay_reserved3' => $this->orderID
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
    private function _getSign(array $input, string $keys) {
        ksort($input);
        reset($input);
        $md5str = "";
        foreach ($input as $key => $val) {
            $md5str = $md5str . $key . "=>" . $val . "&";
        }


        $sign = strtoupper(md5($md5str. "key=" . $keys));

        return $sign;
    }


    //返回参数
    public function parseRE(){

        $this->parameter['pay_md5sign'] = urlencode($this->parameter['pay_md5sign']);
        $this->parameter = $this->arrayToURL();
        $this->parameter .= '&url=' . $this->payUrl;
        $this->parameter .= '&method=POST';
        $str = $this->jumpURL.'?'.$this->parameter;
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $str;
    }

    //签名验证
    public function returnVerify($parameters) {
        $res = [
            'status' => 1,
            'order_number' => $parameters['orderid'],
            'third_order' => $parameters['orderid'],
            'third_money' => $parameters['amount'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['orderid']);

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


        $temp = array( // 返回字段
            "memberid" => $parameters["memberid"],      // 商户ID
            "orderid" => $parameters["orderid"],      // 订单号
            "amount" => $parameters["amount"],      // 交易金额
            "datetime" => $parameters["datetime"],      // 交易时间
            "returncode" => $parameters["returncode"],
        );


        ksort($temp);

        reset($temp);

        $md5str = "";
        foreach ($temp as $key => $val) {
            $md5str = $md5str . $key . "=>" . $val . "&";
        }


        $sign = strtoupper(md5($md5str. "key=" . $config['key']));


        return $sign == $parameters['sign'] ? true : false;

    }

}