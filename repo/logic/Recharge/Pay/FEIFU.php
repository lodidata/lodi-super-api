<?php
/**
 * 飞付: Taylor 2019-02-25
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 飞付对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class FEIFU extends BASES
{
    private $httpCode = '';

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
            'chid' => $this->partnerID,//商户号
            'chddh' => $this->orderID,//商户订单号
            'chbody' => 'VIP'.$this->orderID,//商品名称
            'chfee' => $this->money,//支付金额，请求的价格(单位：分)
            'chnotifyurl' => $this->notifyUrl,//异步通知地址
            'chbackurl' => $this->returnUrl,//同步通知地址
            'chpay' => $this->data['bank_data'],//请求支付的类型
            'chip' => $this->data['client_ip'],//用户支付时设备的IP地址
        );
        $this->parameter['chsign'] = $this->sytMd5($this->parameter, $this->key);//32位小写MD5签名值
    }

    //生成支付签名
    public function sytMd5($data, $userkey)
    {
        return md5($data['chid'].$data['chddh'].$data['chfee'].$data['chnotifyurl'].$userkey);
    }


    //返回参数
    public function parseRE()
    {
        //使用表单提交的方式
        $this->parameter['url'] = $this->payUrl;
        $this->parameter['method'] = 'POST';

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->jumpURL . '?' . $this->arrayToURL();
    }

    //签名验证
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 1,
            'order_number' => $pieces['chddh'],//商户订单号
            'third_order' => $pieces['chorder'],//第三方的支付订单号
            'third_money' => $pieces['chfee'],//支付金额为分
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['chddh']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if($pieces['chstatus'] != 1){
            $res['status'] = 0;
            $res['error'] = '支付失败';
        }
        if (self::retrunVail($pieces, $config['key'])) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    //验签
    public function retrunVail($array, $signKey)
    {
        $sys_sign = $array['chsign'];
        $my_sign = md5($array['chstatus'].$array['chid'].$array['chddh'].$array['chfee'].$signKey);
        return $my_sign == $sys_sign;
    }

    /**
     * PHP发送Json对象数据
     *
     * @param $url 请求url
     * @param $jsonStr 发送的json字符串
     * @return string
     */
    function httpspost($url, $param) {
        if (empty($url) || empty($param)) {
            return false;
        }
        $param = http_build_query($param);
        try {
            $ch = curl_init();//初始化curl
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $data = curl_exec($ch);//运行curl
            $this->httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!$data) {
                $this->re = "请求出错：url={$url},param={$param}";
            }
            $this->re = $data;
            return $data;
        } catch (\Exception $e) {
            $this->re = $e->getMessage();
        }
    }
}