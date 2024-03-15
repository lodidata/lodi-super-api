<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/10/15
 * Time: 15:44
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class NHH extends BASES
{
    //你会红支付
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->_checkResult();
    }

    //组装数组
    public function initParam()
    {
        $post = [
            'merchant_no' => $this->partnerID,
            'amount' => $this->money/100,
            'request_no' => $this->orderID,
            'request_time' => time(),
            'nonce_str' => mt_rand(time(),time()+rand()),
            'notify_url' => $this->notifyUrl,
//            'account_type' => '1',
            'pay_channel' => $this->payType,
            'ip_addr' => $this->data['client_ip'],
            'return_url' => $this->returnUrl,
            //'bankname' => $params['bankname'],
        ];

        $post['sign'] = $this->sign($post, $this->key);
        //请求数据
        $this->curlPost($this->payUrl, $post);

    }

    public function returnVerify($params)
    {

        $res = [
            'status' => 0,
            'order_number' => $params['data']['request_no'],//outorderno
            'third_order' => $params['data']['request_no'],
            'third_money' => $params['data']['amount'] * 100,
            'error' => ''
        ];
        if($params['success'] === true && $params['data']['status'] == 3){

            $config = Recharge::getThirdConfig($params['data']['request_no']);
            $returnSign = $params['data']['sign'];
            unset($params['sign']);
            $mysign = $this->sign($params['data'], $config['key']);
            if ($returnSign == $mysign) {
                    $res['status'] = 1;
                } else {
                $res['error'] = '该订单验签不通过或已完成';
            }
        }else{
            $res['error'] = '支付失败';
        }

        return $res;

    }

    private function _checkResult()
    {
        $this->return['code'] = 886;
        $this->return['msg'] = 'NHH:网络异常';
        $this->return['way'] = $this->showType;
        $this->return['str'] = '';
//        var_dump($this->re);
        if($this->re){
            $re = json_decode($this->re,true);
            if ($re["success"] === true && $re['data']['status'] == 2) {

                $this->return['code'] = 0;
                $this->return['msg'] = 'success';
                $this->return['way'] = 'jump';//扫码返回url，直接跳转，统一jump
                $this->return['url'] = $re['data']['bank_url'];
                $this->return['str'] = $re['data']['bank_url'];
                $this->parameter['method'] = 'GET';

            } else {
                $this->return['code'] = 886;
                $this->return['msg'] = isset($re['data']['message']) && !empty($re['data']['message']) ? $re['data']['message'] : 'NHH:第三方通道未知错误';
                $this->return['way'] = $this->showType;
                $this->return['str'] = '';
                return;
            }
        }else{
            $this->return['msg'] = $this->curlError;
            $this->return['str'] = $this->curlError;
        }
    }

    /**
     *
     * @param array $params
     * @param string $key
     * @return boolean
     */
    public  function checkSign($params, $key, $signName='sign')
    {
        if (!isset($params[$signName])) return false;
        //echo "\n", $params['sign'], '======', self::sign($params, $key), "\n";exit;
        return strtoupper($params[$signName]) == self::sign($params, $key, $signName);
    }

    /**
     *
     * @param array $params
     * @param string $key
     * @return string
     */
    public static function sign($params, $key, $signName = 'sign')
    {
        if (isset($params[$signName])) unset($params[$signName]);
        $arr = array_diff($params, ['']);
        ksort($arr);
        $str = urldecode(http_build_query($arr)).'&key='.$key;
        //echo "\n", $str,"\n";
        return strtoupper(md5($str));
    }

    /**
     * 通过curl 提交POST数据
     * @param unknown $uri
     * @param unknown $post
     * @param string $dataType
     * @param number $errno
     * @param string $error
     * @return boolean|mixed
     */
    public  function curlPost($uri, $post, $dataType='text', &$errno=0, &$error='', $cookie_jar='', $header='')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST,true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if ($cookie_jar) {
            curl_setopt($ch,CURLOPT_COOKIEFILE, $cookie_jar);//发送cookie文件
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_jar);  //保存cookie信息
        }
        if ($dataType == 'text') {
            $data = http_build_query($post);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            if ($header) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            }
        } elseif ($dataType == 'xml') {
            $data = http_build_query($post);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-type: text/xml',
                'Content-Length: ' . strlen($data)
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            $data = json_encode($post);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json;charset=UTF-8',
                'Content-Length: ' . strlen($data)
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $this->re = curl_exec($ch);
        if ($errno = curl_errno($ch)) {
            $this->curlError = curl_error($ch);
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return $this->re;
    }
}