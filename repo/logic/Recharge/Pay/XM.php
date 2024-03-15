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
 * 小米支付
 * Class GT
 * @package Logic\Recharge\Pay
 */
class XM extends BASES
{
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
        $this->parameter = array(
            'action' => $this->data['bank_data'],
            'txnamt' => $this->money,
            'merid' => $this->partnerID,
            'orderid' => $this->orderID,
            'backurl' => $this->notifyUrl
        );
        if($this->data['bank_data']=='WxH5'){
            $this->parameter['ip']=$this->data['client_ip'];
        }
        $this->parameter=$this->sytMd5($this->parameter);
    }

    public function sytMd5($pieces)
    {
        $jsonData = json_encode($pieces);

        //输出Base64字符串
        $base64Data = base64_encode($jsonData);
        //拼接待签名字符
        $signData = $base64Data . $this->key;
        //签名
        $sign = md5($signData);
        //发送请求得到结果
        $requestData = [
            'req' => $base64Data,
            'sign' => $sign
        ];
        return $requestData;
    }


    //返回参数
    public function parseRE()
    {
        $res = json_decode($this->re,true);
        $src = base64_decode($res['resp']);
        $re = json_decode($src, true);

        if(isset($re['respcode']) && $re['respcode']== '00') {
            $this->return['code'] = 0;
            $this->return['msg']  = 'SUCCESS';
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = $re['formaction'];
        }else {
            $this->return['code'] = 886;
            $this->return['msg']  = 'XM:'.$re['respmsg'];
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = $re['formaction'];
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {
        $sign = $pieces['sign'];
        $resp = $pieces['resp'];

        $src = base64_decode($resp);
        $data = json_decode($src, true);


        $res = [
            'status' => 1,
            'order_number' => $data['orderid'],
            'third_order' => isset($data['queryid']) ?? '',
            'third_money' => $data['txnamt'] ,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($data['orderid']);

        $mySign = md5($resp . $config['key']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if ($sign == $mySign) {
            if ('0000' == $data['resultcode'] || '1002' == $data['resultcode']) {
                $res['status'] = 1;
            } else {
                $res['status'] = 0;
                $res['error'] = '支付失败！';
            }
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }

        return $res;
    }

}