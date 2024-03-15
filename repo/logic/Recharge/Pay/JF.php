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
 * 捷付
 * Class GT
 * @package Logic\Recharge\Pay
 */
class JF extends BASES
{

    protected $params;

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
        $this->params = array(
            'amount' => $this->money / 100,
            'platform' => 'PC',
            'note' => 'GOODS',
            'service_type' => $this->data['bank_data'],
            'merchant_user' => $this->partnerID,
            'merchant_order_no' => $this->orderID,
            'risk_level' => 1,
            'callback_url' => $this->notifyUrl,
        );
        //json格式化后在去掉反斜线
        $ex = stripslashes(json_encode($this->params));

        //使用支付后台公钥 加密json data
        $ret_e = $this->encrypt($ex, $this->pubKey);

        $sign = $this->sign($ret_e);

        $this->parameter['merchant_code'] = $this->partnerID;
        $this->parameter['data'] = $ret_e;
        $this->parameter['sign'] = $sign;
    }

    public function sign($data, $code = 'base64')
    {
        $ret = false;
        if (openssl_sign($data, $ret, $this->key, OPENSSL_ALGO_SHA1)) {
            $ret = $this->_encode($ret, $code);
        }
        return $ret;
    }

    /**
     * 加密
     *
     * @param string 明文
     * @param string 密文編碼（base64/hex/bin）
     * @param int 填充方式(所以目前僅支持OPENSSL_PKCS1_PADDING)
     * @return string 密文
     */
    public function encrypt($data, $code = 'base64', $padding = OPENSSL_PKCS1_PADDING)
    {
        $ret = false;
        if (!$this->_checkPadding($padding, 'en')) $this->_error('padding error');
        $tmpCode = "";


        //明文过长分段加密
        foreach (str_split($data, 117) as $chunk) {
            openssl_public_encrypt($chunk, $encryptData, $this->pubKey, $padding);
            $tmpCode .= $encryptData;
            $ret = base64_encode($tmpCode);

        }
        return $ret;
    }

    /**
     * 解密
     *
     * @param string 密文
     * @param string 密文編碼（base64/hex/bin）
     * @param int 填充方式（OPENSSL_PKCS1_PADDING / OPENSSL_NO_PADDING）
     * @param bool 是否翻轉明文（When passing Microsoft CryptoAPI-generated RSA cyphertext, revert the bytes in the block）
     * @return string 明文
     */
    public function decrypt($data, $code = 'base64', $padding = OPENSSL_PKCS1_PADDING, $rev = false)
    {
        $ret = false;
        $data = $this->_decode($data, $code);
        if (!$this->_checkPadding($padding, 'de')) return false;
        if ($data !== false) {
            $enArray = str_split($data, 128);
            foreach ($enArray as $va) {
                openssl_private_decrypt($va, $decryptedTemp, $this->key);//私钥解密
                $ret .= $decryptedTemp;
            }

        } else {
            echo "<br>解密失敗<br>" . $data;
        }
        return $ret;
    }


    /**
     * 检查填充类型
     * 加密只支持PKCS1_PADDING
     * 解密支持PKCS1_PADDING和NO_PADDING
     *
     * $padding int 填充模式(OPENSSL_PKCS1_PADDING,OPENSSL_NO_PADDING ...etc.)
     * $type string 加密en/解密de
     * $ret bool
     */
    private function _checkPadding($padding, $type)
    {
        if ($type == 'en') {
            switch ($padding) {
                case OPENSSL_PKCS1_PADDING:
                    $ret = true;
                    break;
                default:
                    $ret = false;
            }
        } else {
            switch ($padding) {
                case OPENSSL_PKCS1_PADDING:
                case OPENSSL_NO_PADDING:
                    $ret = true;
                    break;
                default:
                    $ret = false;
            }
        }
        return $ret;
    }


    private function _encode($data, $code)
    {
        switch (strtolower($code)) {
            case 'base64':
                $data = base64_encode('' . $data);
                break;
            case 'hex':
                $data = bin2hex($data);
                break;
            case 'bin':
            default:
        }
        return $data;
    }

    private function _decode($data, $code)
    {
        switch (strtolower($code)) {
            case 'base64':
                $data = base64_decode($data);
                break;
            case 'hex':
                $data = $this->_hex2bin($data);
                break;
            case 'bin':
            default:
        }
        return $data;
    }

    private function _hex2bin($hex = false)
    {
        $ret = $hex !== false && preg_match('/^[0-9a-fA-F]+$/i', $hex) ? pack("H*", $hex) : false;
        return $ret;
    }


    //返回参数
    public function parseRE()
    {
        $J = json_decode($this->re, true);
        $tmp_str = $J['data'];
        $re = $this->decrypt($tmp_str);
        $result = json_decode($re, true);

        if (isset($J['status']) && $J['status'] == '1') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $result['transaction_url'];
        } else {
            $msg = $J['error_code'] ?? "未知异常";
            $this->return['code'] = 886;
            $this->return['msg'] = 'JF:' . $msg;
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }

    }

    //签名验证
    public function returnVerify($pieces)
    {
        $sign = $pieces['sign'];

        $key = \DB::table('pay_config')
            ->where('partner_id', '=', $pieces['merchant_code'])
            ->value('key');
        $this->key = $key;

        //解密
        $re = $this->decrypt($pieces['data']);
        $data = json_decode($re, true);

        $res = [
            'status' => 1,
            'order_number' => $data['merchant_order_no'],
            'third_order' => $data['trans_id'],
            'third_money' => $data['amount'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($data['merchant_order_no']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }


        if (self::verifySign($sign, $pieces['data'], $config['pub_key'])) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    //call back 验签
    public function verifySign($sign, $data, $pub_key)
    {
        $tmp_sign = openssl_verify($data, base64_decode(($sign)), $pub_key);   //平台公钥验签
        return $tmp_sign;
    }


}