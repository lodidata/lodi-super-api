<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 *
 * 众信云
 * @author Lion
 */
class ZXY extends BASES {

    static function instantiation(){
        return new ZXY();
    }

    //与第三方交互
    public function start(){
        if (!$this->verifyMoney()) {
            return;
        }
        $this->initParam();       // 数据初始化
        $this->basePost();        // POST请求
        $this->parseRE();         // 处理结果
    }

    //校验充值金额
    public function verifyMoney(){

        if($this->money/100 < 1000){
            if($this->money/100%10 > 0){
                $this->return['code'] = 88;
                $this->return['msg'] = '该通道金额小于1000时必须为10的倍数';
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = '';
                return false;
            }
        }elseif($this->money/100 >= 1000){
            if($this->money/100%100 > 0){
                $this->return['code'] = 88;
                $this->return['msg'] = '该通道金额大于等于1000时必须为100的倍数';
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = '';
                return false;
            }
        }
        return true;
    }
    //组装数组
    public function initParam(){

        $this->parameter['pay_memberid']    = $this->partnerID;                     //平台分配商户号
        $this->parameter['pay_orderid']     = $this->orderID;                       //订单号唯一, 字符长度20
        $this->parameter['pay_amount']      = $this->money/100;                     //商品金额
        $this->parameter['pay_applydate']   = date("Y-m-d H:i:s",time());  //时间格式：2016-12-26 18:18:18
        $this->parameter['pay_bankcode']    = $this->data['bank_data'];            //银行编码
        $this->parameter['pay_notifyurl']   = $this->notifyUrl;                    //服务端通知
        $this->parameter['pay_callbackurl'] = $this->returnUrl;                    //页面跳转通知
        $this->parameter["pay_md5sign"]     = $this->sign();                       //签名
        $this->parameter['pay_attach']      = "1234|456";                          //附加字段
        $this->parameter['pay_productname'] = 'VIP基础服务';                       //商品名称

    }

    //生成签名
    public function sign(){
        ksort($this->parameter);
        $md5str = "";
        foreach ($this->parameter as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $this->key));
        return $sign;
    }

    //处理结果
    public function parseRE(){

        $this->parameter['pay_md5sign']  = urlencode($this->parameter['pay_md5sign']) ;
        $this->parameter = $this->arrayToURL($this->parameter);
        $this->parameter .= '&url=' . $this->payUrl;
        $this->parameter .= '&method=POST';
        $str = $this->jumpURL.'?'.$this->parameter;
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->data['return_type'];
        $this->return['str'] = $str;
    }

    //回调数据校验
    /* DATA $parameters
     * RETURN $res
     * */
    public function returnVerify($parameters = array()) {
        $res = [
            'status' => 0,
            'order_number' => $parameters['orderid'],
            'third_order' => $parameters['transaction_id'],
            'third_money' => $parameters["amount"]*100,
            'error' => '',
        ];

        $returnArray = array( // 返回字段
            "memberid"       => $parameters["memberid"],       // 商户ID
            "orderid"        => $parameters["orderid"],        // 订单号
            "amount"         => $parameters["amount"],         // 交易金额
            "datetime"       => $parameters["datetime"],       // 交易时间
            "transaction_id" => $parameters["transaction_id"], // 支付流水号
            "returncode"     => $parameters["returncode"],
        );

        $config = Recharge::getThirdConfig($parameters['orderid']);
        $md5key = $config['key'];
        ksort($returnArray);
        reset($returnArray);
        $md5str = "";
        foreach ($returnArray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $md5key));

        if($sign == $parameters["sign"]){
            $res['status']=1;
        }else{
            $res['error']='验签失败！';
        }
        return $res;

    }

}
