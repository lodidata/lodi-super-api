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
 * 麦橙支付
 * Class MC
 * @package Logic\Recharge\Pay
 */
class MC extends BASES
{
    protected $param;

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
        $this->param = array(
            'merchantId' => $this->partnerID,
            'tradeAmt' => $this->money,
            'withdrawType' => '0',
            'accessPayNo' => $this->orderID,
            'goodsName' => 'GOODS',
            'payNotifyUrl' => $this->notifyUrl,
            'frontBackUrl'=>$this->returnUrl
        );
        $this->param['tradeType'] = $this->data['bank_data'];
        $this->param['sign'] = $this->sytMd5New($this->param, $this->key);

        $jsonStr = json_encode($this->param,JSON_UNESCAPED_UNICODE);

        $this->parameter['data'] = $this->encrypt($jsonStr);
        $this->parameter['accessId'] = $this->data['app_id'];
    }

    /**
     * 加密方法
     * AES128 算法
     * ECB_MODE 模式
     * @return string //加密后字符串
     */
    final private function encrypt($jsonStr)
    {
        $pad = 16-(strlen($jsonStr)%16);
        $input =$jsonStr.str_repeat(chr($pad),$pad);
        return bin2hex(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, substr( $this->key, 0, 16 ), $input, MCRYPT_MODE_ECB));
    }

    /**
     * 解密方法
     * AES128 算法
     * ECB_MODE 模式
     * @return string //解密后字符串
     */
    final private function getDecrypt($str,$key)
    {
        $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, substr( $key, 0, 16 ), hex2bin($str), MCRYPT_MODE_ECB);
        $padSize   = ord(substr($decrypted, -1));
        return substr($decrypted, 0, $padSize * -1);
    }

    public function sytMd5New($pieces, $key)
    {
        ksort($pieces);
        $md5str = "";
        foreach ($pieces as $keyVal => $val) {
            $md5str = $md5str . $keyVal . "=" . $val . "&";
        }
        $md5str = $md5str . "key=" . $key;
        $sign = strtoupper(md5($md5str));
        return $sign;
    }

    //返回参数
    public function parseRE()
    {
        $resultStr = $this -> getDecrypt($this->re,$this->key);
        $re=json_decode($resultStr, JSON_UNESCAPED_UNICODE);
        if (isset($re['code']) && $re['code'] == '0') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['htmlUrl'];
        } else {
            $msg = $re['msg'] ?? "未知异常";
            $this->return['code'] = 886;
            $this->return['msg'] = 'MC:' . $msg;
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }

    }

    //签名验证
    public function returnVerify($input)
    {
        $config = Recharge::getThirdConfig($input['accessPayNo']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }

        $resultStr = $this -> getDecrypt($input['data'], $config['key']);
        $result=json_decode($resultStr, JSON_UNESCAPED_UNICODE);
        $res = [
            'status' => 1,
            'order_number' => $result['accessPayNo'],
            'third_order' => $result['payNo'],
            'third_money' => $result['tradeAmt'],
            'error' => '',
        ];

        $sign = $result['sign'];
        unset($result['sign']);
        if (self::retrunVail($sign, $result, $config['key'])) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    public function retrunVail($sign, $pieces, $key)
    {
        return $sign == $this->sytMd5New($pieces, $key);
    }

}