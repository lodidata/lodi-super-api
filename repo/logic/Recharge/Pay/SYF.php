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
 * 随意付
 * Class GT
 * @package Logic\Recharge\Pay
 */
class SYF extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {

        $money = $this->money / 100;
        $this->parameter = array(
            'shopAccountId' => $this->partnerID,
            'shopUserId' => '1',
            'amountInString' => sprintf("%.2f", $money),
            'payChannel' => $this->payType,
            'shopNo' => $this->orderID,
            'shopCallbackUrl' => $this->notifyUrl,
            'returnUrl' => $this->returnUrl
        );
        $this->parameter['target'] = 1;
        $this->parameter['sign'] = urlencode($this->sytMd5New($this->parameter));
//        print_r($this->parameter);die;
    }

    public function sytMd5New($pieces)
    {
        unset($pieces['shopCallbackUrl']);
        unset($pieces['returnUrl']);
        unset($pieces['shopNo']);
        unset($pieces['target']);
        $pieces['key'] = $this->key;
        $singStr = implode($pieces);
        return md5($singStr);
    }

    //返回参数
    public function parseRE()
    {
            $this->parameter = $this->arrayToURL();
            $this->parameter .= '&url=' . $this->payUrl . '2';
            $this->parameter .= '&method=POST';
            $str = $this->jumpURL . '?' . $this->parameter;
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $str;

    }

    //签名验证
    public function returnVerify($pieces)
    {
        $pieces = (array)json_decode(file_get_contents('php://input'), true);
        $res = [
            'status' => 1,
            'order_number' => $pieces['shop_no'],
            'third_order' => $pieces['trade_no'],
            'third_money' => $pieces['money'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['shop_no']);
//        print_r($config);
//        print_r($config);exit;
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if (self::retrunVail($pieces, $config)) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    public function retrunVail($pieces, $config)
    {
        $arr['shop_id '] = $config['partner_id'];
        $arr['user_id'] = $pieces['user_id'];
        $arr['order_no'] = $pieces['order_no'];
        $arr['sing_key'] = $config['key'];
        $arr['money'] = $pieces['money'];
        $arr['type'] = $pieces['type'];
        return $pieces['sign'] == $this->createVerifyString($arr);
    }

    function createVerifyString($data)
    {
        $sign = '';
        foreach ($data AS $key => $val) {
            $sign .= $val;
        }
        return md5($sign);
    }

    function httpspost($url, $data)
    {
        $curlData = $this->JSON($data);

        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
//    curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, 'SSLv3');
//        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $curlData); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8', 'Content-Length:' . strlen($curlData)));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $tmpInfo = curl_exec($curl); // 执行操作
//        if (curl_errno($curl)) {
//            echo 'Errno'.curl_error($curl);//捕抓异常
//        }
        curl_close($curl); // 关闭CURL会话
        return $tmpInfo; // 返回数据，json格式
    }

    function JSON($array)
    {
        $json = json_encode($array);
        return urldecode($json);
    }
}