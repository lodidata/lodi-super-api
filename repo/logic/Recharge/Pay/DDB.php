<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 多的保
 * @author wangheng
 */


class DDB extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        //网银 暂时未接
        $this->basePost();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter = array(
            'merchant_code'=>$this->partnerID,
            'service_type'=>$this->payType,//固定值：alipay_scan 或 weixin_scan或qq_scan
            'notify_url'=>$this->notifyUrl,
            'interface_version'=>'V3.3',//固定值：V3.3
            'client_ip'=>'127.0.0.1',
            //业务参数
            'order_no' => $this->orderID,
            'order_time' => date('Y-m-d H:i:s'),
            'order_amount' => $this->money/100,  //金额为元
            'product_name' => '-',
            'extra_return_param' => 'goods'
        );
        $temp = array(
            'sign_type' => 'RSA-S',
            'sign' => $this->currentOpenssl(),
        );
        $this->parameter = array_merge($this->parameter,$temp);
    }

    public function parseRE(){
        $re = $this->parseXML($this->re);
        if($re["response"]['result_code'] == 0){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re["response"]['qrcode'];
            $this->return['money'] = $re["response"]['order_amount'];
        }else{
            $msg = isset($re["response"]['result_desc']) ? $re["response"]['result_desc'] : $re["response"]['resp_desc'];
            $this->return['code'] = 886;
            $this->return['msg'] = 'DDB:'.$msg;
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
            $this->return['money'] = $this->money;
        }
    }

    /**
     * 返回地址验证
     *
     * @param
     * @return boolean
     */
    //[status=1 通过  0不通过,
    //order_number = '订单',
    //'third_order'=第三方订单,
    //'third_money'='金额',
    //'error'='未有该订单/订单未支付/未有该订单']
    public function returnVerify($parameters) {
        $res = [
            'status' => 0,
            'order_number' => $parameters['order_no'],
            'third_order' => $parameters['trade_no'],
            'third_money' => $parameters['order_amount'] * 100,
            'error' => '',
        ];
        if($parameters['trade_status'] == 'SUCCESS'){
            $config = Recharge::getThirdConfig($parameters['order_no']);
            if($this->verifyData($parameters,$config['pub_key'])){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';

        return $res;
    }
    /**
     * 返回地址验证(同步)
     *
     * @param
     * @return boolean
     */
    public function verifyData($input,$cert) {
        $merchant_code	= $input["merchant_code"];

        $interface_version = $input["interface_version"];

        $sign_type = $input["sign_type"];

        $dinpaySign = base64_decode($input["sign"]);

        $notify_type = $input["notify_type"];

        $notify_id = $input["notify_id"];

        $order_no = $input["order_no"];

        $order_time = $input["order_time"];

        $order_amount = $input["order_amount"];

        $trade_status = $input["trade_status"];

        $trade_time = $input["trade_time"];

        $trade_no = $input["trade_no"];

        $bank_seq_no = $input["bank_seq_no"];

        $extra_return_param = $input["extra_return_param"];

        $orginal_money = $input["orginal_money"];//原始订单金额


/////////////////////////////   参数组装  /////////////////////////////////
        /**
        除了sign_type dinpaySign参数，其他非空参数都要参与组装，组装顺序是按照a~z的顺序，下划线"_"优先于字母
         */

        $signStr = "";

        if($bank_seq_no != ""){
            $signStr = $signStr."bank_seq_no=".$bank_seq_no."&";
        }

        if($extra_return_param != ""){
            $signStr = $signStr."extra_return_param=".$extra_return_param."&";
        }

        $signStr = $signStr."interface_version=".$interface_version."&";

        $signStr = $signStr."merchant_code=".$merchant_code."&";

        $signStr = $signStr."notify_id=".$notify_id."&";

        $signStr = $signStr."notify_type=".$notify_type."&";

        $signStr = $signStr."order_amount=".$order_amount."&";

        $signStr = $signStr."order_no=".$order_no."&";

        $signStr = $signStr."order_time=".$order_time."&";

        $signStr = $signStr."orginal_money=".$orginal_money."&";//原始订单金额

        $signStr = $signStr."trade_no=".$trade_no."&";

        $signStr = $signStr."trade_status=".$trade_status."&";

        $signStr = $signStr."trade_time=".$trade_time;
        $cert = openssl_get_publickey($cert);
        if (openssl_verify($signStr,$dinpaySign,$cert,OPENSSL_ALGO_MD5)) {
            if ($trade_status == 'SUCCESS') {
                return true;
            }
            return false;
        } else {
            return false;
        }
    }
}
