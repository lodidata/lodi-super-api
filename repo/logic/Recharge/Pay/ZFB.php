<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;

/**
 *
 * 支付宝
 * @author Lion
 */
class ZFB extends BASES {

    const PRODUCT_CODE = 'FAST_INSTANT_TRADE_PAY';
    const CHARSET = 'UTF-8';
    const VERSION = '1.0';
    const SIGNTYPE = 'RSA2';
    const METHOD = 'alipay.trade.page.pay';
    const FORMAT = 'json';

    static function instantiation()
    {
        return new ZFB();
    }

    //与第三方交互
    public function start()
    {
        $this->initParam();  // 数据初始化
        $this->basePost();  // POST请求
        $this->parseRE();  // 处理结果
    }

    //组装数组
    public function initParam()
    {
        $bizConTent = [
            'product_code' => self::PRODUCT_CODE,  //销售产品码，与支付宝签约的产品码名称。 注：目前仅支持FAST_INSTANT_TRADE_PAY
            'out_trade_no' => $this->orderID,  //商户订单号，64个字符以内、可包含字母、数字、下划线；需保证在商户端不重复
            'total_amount' => sprintf('%.2f', $this->money),  //订单总金额，单位为元，精确到小数点后两位，取值范围[0.01,100000000]
            'subject'      => $this->orderID,  //订单标题
        ];

        $this->parameter = [
            'app_id'      => $this->partnerID,
            'notify_url'  => $this->notifyUrl,  //支付宝服务器主动通知商户服务器里指定的页面http/https路径。
            'return_url'  => $this->returnUrl,  //同步返回地址，HTTP/HTTPS开头字符串
            'timestamp'   => date("Y-m-d H:i:s", time()),  //发送请求的时间，格式"yyyy-MM-dd HH:mm:ss"
            'version'     => self::VERSION,  //调用的接口版本，固定为：1.0
            'method'      => self::METHOD,  //接口名称
            'sign_type'   => self::SIGNTYPE,  //商户生成签名字符串所使用的签名算法类型，目前支持RSA2和RSA，推荐使用RSA2
            'charset'     => self::CHARSET,  //请求使用的编码格式，如utf-8,gbk,gb2312等
            'format'      => self::FORMAT,
            'biz_content' => json_encode($bizConTent),
        ];

        //签名
        $this->parameter["sign"] = $this->generateSign($this->parameter, self::SIGNTYPE);

    }


    /**
     * 支付宝签名
     *
     * @param $params
     * @param string $signType
     *
     * @return string
     */
    public function generateSign($params, $signType = "RSA2")
    {
        return $this->sign($this->getSignContent($params), $signType);
    }

    /**
     * 拼接字符串
     *
     * @param $params
     *
     * @return string
     */
    public function getSignContent($params)
    {
        ksort($params);

        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {

                // 转换成目标字符集
                $v = $this->characet($v, self::CHARSET);
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }

        unset ($k, $v);
        return $stringToBeSigned;
    }


    //此方法对value做urlencode
    public function getSignContentUrlencode($params)
    {
        ksort($params);

        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {

                // 转换成目标字符集
                $v = $this->characet($v, "UTF-8");

                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . urlencode($v);
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . urlencode($v);
                }
                $i++;
            }
        }
        unset ($k, $v);
        return $stringToBeSigned;
    }

    /**
     * 转换字符集编码
     *
     * @param $data
     * @param $targetCharset
     *
     * @return string
     */
    function characet($data, $targetCharset)
    {
        if (!empty($data)) {
            if (strcasecmp(self::CHARSET, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, self::CHARSET);
                //				$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }
        return $data;
    }

    /**
     * 校验$value是否非空
     *  if not set ,return true;
     *    if is null , return true;
     **/
    protected function checkEmpty($value)
    {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;

        return false;
    }


    /**
     * 私钥签名
     *
     * @param $data
     * @param string $signType
     *
     * @return string
     */
    protected function sign($data, $signType = "RSA2")
    {
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
               wordwrap($this->key, 64, "\n", true) .
               "\n-----END RSA PRIVATE KEY-----";

        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($data, $sign, $res);
        }


        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * 加密方法
     *
     * @param string $str
     *
     * @return string
     */
    function encrypt($str, $screct_key)
    {
        //AES, 128 模式加密数据 CBC
        $screct_key = base64_decode($screct_key);
        $str = trim($str);
        $str = $this->addPKCS7Padding($str);
        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC), 1);
        $encrypt_str = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $screct_key, $str, MCRYPT_MODE_CBC);
        return base64_encode($encrypt_str);
    }

    /**
     * 填充算法
     *
     * @param string $source
     *
     * @return string
     */
    function addPKCS7Padding($source)
    {
        $source = trim($source);
        $block = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);

        $pad = $block - (strlen($source) % $block);
        if ($pad <= $block) {
            $char = chr($pad);
            $source .= str_repeat($char, $pad);
        }
        return $source;
    }


    //处理结果
    public function parseRE()
    {
        $this->parameter = $this->getSignContentUrlencode($this->parameter);
        $this->parameter .= '&url=' . $this->payUrl;
        $this->parameter .= '&method2=POST';

        $str = $this->jumpURL . '?' . $this->parameter;
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->data['return_type'];
        $this->return['str'] = $str;
    }

    //回调数据校验
    /* DATA $parameters
     * RETURN $res
     * */
    public function returnVerify($parameters = [])
    {
        $res = [
            'status'       => 0,
            'order_number' => $parameters['out_trade_no'],
            'third_order'  => $parameters['trade_no'],
            'third_money'  => $parameters["total_amount"],
            'error'        => '',
        ];

        $result = $this->rsaCheckV1($parameters);

        if ($result) {
            $res['status'] = 1;
        } else {
            $res['error'] = '验签失败！';
        }
        return $res;

    }

    /** rsaCheckV1 & rsaCheckV2
     *  验证签名
     *  在使用本方法前，必须初始化AopClient且传入公钥参数。
     *  公钥是否是读取字符串还是读取文件，是根据初始化传入的值判断的。
     **/
    public function rsaCheckV1($params, $signType = 'RSA2')
    {
        $sign = $params['sign'];
        $params['sign_type'] = null;
        $params['sign'] = null;
        return $this->verify($this->getSignContent($params), $sign, $signType);
    }


    /**
     * 异步验签
     *
     * @param $data
     * @param $sign
     * @param string $signType
     *
     * @return bool
     */
    function verify($data, $sign, $signType = 'RSA2')
    {
        $res = "-----BEGIN PUBLIC KEY-----\n" .
               wordwrap($this->pubKey, 64, "\n", true) .
               "\n-----END PUBLIC KEY-----";


        //调用openssl内置方法验签，返回bool值
        if ("RSA2" == $signType) {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
        } else {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res);
        }

        return $result;
    }
}
