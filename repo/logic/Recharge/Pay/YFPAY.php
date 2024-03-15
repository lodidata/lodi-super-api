<?php
/**
 * (友付)KPay支付  kay
 * Created by PhpStorm.
 * User: shuidong
 * Date: 2018/12/24
 * Time: 19:18
 */
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

class YFPAY extends BASES
{

    /**
     * 生命周期
     */
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    /**
     * 提交参数组装
     */
    public function initParam()
    {
        $this->parameter = [
            'version' => '2.0',
            'charset' => 'UTF-8',
            'spid' => $this->partnerID,
            'spbillno' => $this->orderID,
            'tranAmt' => $this->money ,
            'backUrl' => $this->returnUrl,
            'notifyUrl' => $this->notifyUrl,
            'productName' => 'Goods'.time(),
        ];
        if(strpos($this->payUrl,'quickPay')==false){
            $this->parameter['payType']=$this->payType;
        }

        //秘钥存入 token字段中
        $this->parameter['sign'] = strtoupper($this->currentMd5('key='));
        $this->parameter['signType'] = 'MD5';
    }



    /**
     * 组装前端数据,输出结果
     */
    public function parseRE()
    {
        if($this->showType == 'code'){
            $param = $this->toXml($this->parameter);
            $this->_curl($param);
            $re = $this->parseXML($this->re);
            if(isset($re['codeImgUrl'])){
                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] = $this->showType;
                $this->return['str'] = $re['codeUrl'];
            }else {
                $this->return['code'] = 65;
                $this->return['msg'] = 'YFPAY:'.$re['retmsg'] ?? '';
                $this->return['way'] = $this->showType;
                $this->return['str'] = '';
            }
        }else {
            $param['req_data'] = $this->tinyXml();
            $this->parameter = $param;
            foreach ($this->parameter as &$item) {
                $item = urlencode($item);
            }
            $this->parameter = $this->arrayToURL();
            $this->parameter .= '&url=' . $this->payUrl;
            $this->parameter .= '&method=POST';

            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $this->jumpURL . '?' . $this->parameter;
        }
    }

    public function _curl($xmlData){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->payUrl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type:text/xml; charset=utf-8"));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);//post提交的数据包
        $response = curl_exec($ch);
        $this->curlError = curl_error($ch);
        $this->re = $response;
    }

    public function tinyXml(){
        $x = '<xml>';
        foreach ($this->parameter as $k=>$v){
            $x .= '<'.$k.'>'.$v.'</'.$k.'>';
        }
        $x .= '</xml>';
        return $x;
    }

    /**
     * 回调验证处理
     * 获取接口返回数据，再次验证
     */
    public function returnVerify($parameters)
    {
        global $app;
        $parameters = $app->getContainer()->request->getParams();
        unset($parameters['s']);
        $res = [
            'order_number' => $parameters['spbillno'],
            'third_order' => $parameters['transactionId'],
            'third_money' => $parameters['payAmt'],
            'status'=>1,
            'error'=>''
        ];
        $config = Recharge::getThirdConfig($parameters['spbillno']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }
        if ($parameters['result'] != 'pay_success'){
            $res['status'] = 0;
            $res['error'] = '支付订单状态未成功';
            return $res;
        }
        $sign = $parameters['sign'];
        unset($parameters['sign']);
        unset($parameters['signType']);
        $this->parameter = $parameters;
        $this->key = $config['key'];
        if (strtoupper($sign) != strtoupper($this->currentMd5('key='))) {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
            return $res;
        }
        return $res;
    }
}