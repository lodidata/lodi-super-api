<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/* 恒立信支付
*/

class HLXZF extends BASES
{
    /**
     * 生命周期
     */
    public function start()
    {
        $this->initParam();
        $this->post();
        $this->parseRE();
    }

    /**
     * 初始化参数
     */
    public function initParam()
    {
        $this->parameter = [
            'merchantNo' => $this->partnerID,
            'orderAmount' => (string)($this->money / 100),
            'merchantOrderNo' => $this->orderID,
            'notifyUrl' => $this->notifyUrl,
            'timestamp' => (string)$this->msectime(),
            'payWay' => $this->payType,
            'clientIp' => $this->data['client_ip'],
        ];
        $this->parameter['sign'] = $this->_sign($this->parameter, $this->key);
    }

    //返回当前的毫秒时间戳
    function msectime()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }

    /**
     * 生成sign
     */
    private function _sign($pieces, $tkey)
    {
        ksort($pieces);
        $string = [];
        foreach ($pieces as $key => $val) {
            $string[] = $key . '=' . $val;
        }
        $params = join('&', $string);
        $sign_str = $params . $tkey;
        $sign = md5($sign_str);
        return $sign;
    }

    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if ($re['status'] == 1) {
            $para = [
                'merchantNo' => $this->partnerID,
                'tradeId' => (string)$re['data'],
                'timestamp' => (string)$this->msectime(),
            ];
            $para['sign'] = $this->_sign($para, $this->key);
            $this->parameter = $para;
            $this->payUrl = $this->data['app_secret'];
            $this->post();
            $re = json_decode($this->re, true);
            if ($re['status'] == 1) {
                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] = $this->showType;
                $this->return['str'] = $re['data'];
            } else {
                $this->return['code'] = 24;
                $this->return['msg'] = 'HLXZF：'.$re['msg'];
                $this->return['way'] = $this->showType;
                $this->return['str'] = '';
            }
        } else {
            $this->return['code'] = 23;
            $this->return['msg'] = 'HLXZF：'.$re['msg'];
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    public function returnVerify($data)
    {
        //获取post请求的body数据
        $str = file_get_contents('php://input');

        global $app;
        $data = $app->getContainer()->request->getParams();
        unset($data['s']);

        $config = Recharge::getThirdConfig($data['merchantOrderNo']);
        //AES解密
        $des = self::decrypt($str, $config['pub_key'], $config['pub_key']);
        //必须符合 JSON 格式，不能在最后出现逗号，不能使用单引号
        $des = preg_replace('/[\x00-\x1F\x80-\x9F]/u', '', trim($des));

        $data = json_decode($des, true);

        $res = [
            'status' => 0,
            'order_number' => $data['data']['merchantOrderNo'],
            'third_order' => $data['data']['platformOrderNo'],
            'third_money' => $data['data']['orderAmount'] * 100,
            'error' => '',
        ];

        if (!$config) {
            $res['error'] = '订单号不存在';
            return $res;
        }
        if ($data['status'] != 1) {
            $res['error'] = '未支付';
            return $res;
        }

        $res['status'] = 1;
        return $res;
    }

    //解密 AES/CBC/PKCS5Padding
    public function decrypt($encryptStr, $iv, $key)
    {
        $module = @mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, $iv);
        @mcrypt_generic_init($module, $key, $iv);
        $encryptedData = base64_decode($encryptStr);
        $encryptedData = @mdecrypt_generic($module, $encryptedData);
        return $encryptedData;
    }

}