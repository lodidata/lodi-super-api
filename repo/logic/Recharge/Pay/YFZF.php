<?php
/**
 * Author: Taylor 2019-02-16
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 友付对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class YFZF extends BASES
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
            'cid' => $this->partnerID,//商家ID
            'uid' => 0,//商家用户ID
            'time' => time(),//操作的时间，UNIX时间戳秒值
            'amount' => sprintf("%.2f", $this->money / 100),//充值的金额，单位元 ，最多可带两位小数点
            'order_id' => $this->orderID,
            'ip' => $this->data['client_ip'],
//            'category' => 'remit',//银行卡转账
//            'category' => 'qrcode',//二维码存款
//            'from_bank_flag' => $this->data['bank_data'],
        );
        $this->parameter['sign'] = $this->sytMd5($this->parameter, $this->key);//32位小写MD5签名值
//        $this->parameter['qsign'] = $this->sytMd5($this->parameter, $this->key);//32位小写MD5签名值
    }

    //生成支付签名
    public function sytMd5($data, $signKey)
    {
//        $str = "cid={$data['cid']}&uid={$data['uid']}&time={$data['time']}&amount={$data['amount']}&order_id={$data['order_id']}";
        $str = "cid={$data['cid']}&uid={$data['uid']}&time={$data['time']}&amount={$data['amount']}&order_id={$data['order_id']}&ip={$data['ip']}";
        $this->parameter['str'] = $str;
        return base64_encode(hash_hmac('sha1', $str, $signKey, true));
    }


    //返回参数
    public function parseRE()
    {
        if(in_array($this->data['bank_data'], ['ALIPAY', 'WebMM', 'QQPAY'])){
            $reqdata = $this->parameter['str'] . "&type=qrcode&tflag={$this->data['bank_data']}&sign=" . $this->parameter['sign'];
        }else{
            $reqdata = $this->parameter['str'] . "&type={$this->data['bank_data']}&sign=" . $this->parameter['sign'];
        }
        $url =  $this->payUrl . "?" . $reqdata;
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->data['return_type'];//jump跳转或code扫码
        $this->return['str'] = $url;
    }

    //签名验证
    public function returnVerify($pieces)
    {
        //{"order_no":"2019011716585431742","user_id":"0","shop_no":"201901171658538093","money":"1.00","type":"bank","date":"2019-01-17 16:58:54","trade_no":"2019011716585431742","status":0,"sign":"76606b1caa23d9dd312cb365c97161ac"}
        $res = [
            'status' => 1,
            'order_number' => $pieces['order_id'],//商户订单号
            'third_order' => $pieces['order_id'],//第三方的支付订单号
            'third_money' => $pieces['amount'],//支付金额为分
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['order_id']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if($pieces['status'] != 'verified'){
            $res['status'] = 0;
            $res['error'] = '支付失败';
        }
        if (self::retrunVail($pieces, $config)) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    //验签
    public function retrunVail($pieces, $config)
    {
        $str = "order_id={$pieces['order_id']}&amount={$pieces['amount']}&verified_time={$pieces['verified_time']}";
        $my_sign = base64_encode(hash_hmac('sha1', $str, $config['key'], true));
        return $my_sign == $pieces['qsign'];
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