<?php
/**
 * 大栋支付: Taylor 2019-06-15
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 大栋支付对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class DADONG extends BASES
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
            'merchantNo' => $this->partnerID,//商户ID
            'merchantOrderNo' => $this->orderID,//商户订单号
            'merchantReqTime' => date('YmdHis'),//请求时间
            'orderAmount' => sprintf("%.2f", $this->money / 100),//交易金额，单位：元，保留两位小数
            'tradeSummary' => 'VIP:'.$this->orderID,//交易摘要
            'payModel' => $this->data['action'] ? $this->data['action'] : 'Direct',//支付模式
            'payType' => $this->data['bank_data'],//支付渠道
            'cardType' => 'DEBIT',//银行卡类型
            'userTerminal' => 'Phone',//用户终端
            'userIp' => $this->data['client_ip'],//付款人IP
            'backNoticeUrl' => $this->notifyUrl,//异步通知地址
        );
        $this->parameter['sign'] = md5($this->arrayToURL() . $this->key);//32位小写MD5签名值
    }

    //生成支付签名
    public function sytMd5($array, $signKey)
    {
//        $str = md5($array['uid'].$signKey.$array['money'].$array['channel'].$array['post_url'].$array['return_url'].$array['order_id'].$array['order_uid'].$array['goods_name']);
        $str = md5($array['uid'].$signKey.$array['money'].$array['channel'].$array['post_url'].$array['return_url'].$array['order_id']);
        return $str;
    }


    //返回参数
    public function parseRE()
    {
        $this->basePost();
        //{"code":"SUCCESS","msg":"\u6210\u529f","sign":"00cdab4986efdf611492c411b5666acd","biz":{"platformOrderNo":"P20190616150224987361","payUrl":"https:\/\/gate.ddzf01.com\/page\/autoredirect\/P20190616150224987361"}}
        $re = json_decode($this->re,true);
        if (isset($re['biz']['payUrl'])) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;//jump跳转或code扫码
            $this->return['str'] = $re['biz']['payUrl'];
        } else {
            $this->return['code'] = 8;
            $this->return['msg'] = 'DADONG:' . (isset($re['msg']) ? $re['msg'] : '');
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {
        global $app;
        $result = $app->getContainer()->request->getParams();
        unset($result['s']);
        //支付成功才会回调
        $res = [
            'status' => 1,
            'order_number' => $result['biz']['merchantOrderNo'],
            'third_order' => $result['biz']['platformOrderNo'],//第三方的支付订单号
            'third_money' => $result['biz']['orderAmount'] * 100,//支付金额为元，保留两位小数
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($res['order_number']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if ($result['code'] != 'SUCCESS') {
            $res['status'] = 0;
            $res['error'] = '未支付';
            return $res;
        }
        if ($result['sign'] == md5($this->arrayToURLALL($result['biz']) . $config['key'])) {
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
        $sys_sign = $array['key'];
        $my_sign = md5($signKey.$array['trade_no'].$array['order_id'].$array['channel'].$array['money'].$array['remark'].$array['order_uid'].$array['goods_name']);
        return $my_sign == $sys_sign;
    }
}