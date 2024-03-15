<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 云宝
 * @author Nico
 */

class YB extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->parseRE();
    }

    //组装数组
    public function initParam(){

        $this->parameter = array(
            //基本参数
            'version'=>'4.1',
            'app_id'=>$this->partnerID,
            'pay_type'=>$this->data['bank_data'],
            'nonce_str'=>time(),
            'sign_type'=>'MD5',
            'body'=>'yb-goods',
            'out_trade_no' => $this->orderID,
            'fee_type'=> 'CNY',
            'total_fee' => $this->money,
            'return_url'=> $this->returnUrl,
            'notify_url'=> $this->notifyUrl,
            'system_time'=> Date('YmdHis', time()),
        );
        if ($this->parameter['pay_type'] == 5) $this->parameter['quick_user_id'] = $this->orderID;
        $this->parameter['sign'] = $this->sytMd5($this->parameter);

    }

    //json post
    function curlPost($referer=null){
        $ch=curl_init();
        curl_setopt($ch,CURLOPT_URL,$this->payUrl);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$referer);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($referer)
        ));
        $response = curl_exec($ch);
        $this->curlError = curl_error($ch);
        $this->re = $response;
        
    }

    //加密方式
    public function sytMd5($pieces){
        $sign_arr['app_id'] = $pieces['app_id'];
        $sign_arr['nonce_str'] = $pieces['nonce_str'];
        $sign_arr['out_trade_no'] = $pieces['out_trade_no'];
        $sign_arr['sign_type'] = $pieces['sign_type'];
        $sign_arr['total_fee'] = $pieces['total_fee'];
        $sign_arr['version'] = $pieces['version'];
        $string='';
        foreach ($sign_arr as $keys=>$value){
            if($value !='' && $value!=null){
                $string=$string.$keys.'='.$value.'&';
            }
        }
        $string=$string.'key='. $this->key;
        $sign=strtoupper(md5($string));

        return $sign;
    }

    public function parseRE(){
        if ($this->parameter['pay_type'] == 5){
            $this->post();
            if (strpos($this->re,'form') === false) {
                $re = json_decode($this->re, true);
                if (isset($re['return_code']) && $re['result_code']) {
                    $this->return['code'] = 0;
                    $this->return['msg'] = 'SUCCESS';
                    $this->return['way'] = $this->data['return_type'];
                    $this->return['str'] = $re['code_url'];
                } else {
                    $re['err_code_des'] = $re['err_code_des'] ?? '通道异常';
                    $this->return['code'] =  5;
                    $this->return['msg'] = 'YB:' . $re['err_code_des'];
                    $this->return['way'] = $this->data['return_type'];
                    $this->return['str'] = '';
                }
            }else {
                preg_match_all("/name='(.+)'\s+.*value='(.*)'/iU",$this->re,$match);
                preg_match("/action='(.+)'\s+.*method=/iU",$this->re,$url);
                $url = $url[1] ?? '';
                $this->parameter = array();
                if(isset($match[1])&&isset($match[2])){
                    foreach ($match[1] as $key=>$val){
                        $this->parameter[$val] = urlencode($match[2][$key]);
                    }
                    $this->parameter['url'] = $url;
                    $this->parameter['method'] = 'POST';
                }
                $str = '';
                if($url && count($this->parameter) > 3){
                    $str = $this->jumpURL.'?'.$this->arrayToURL();
                }
                $mes = 'SUCCESS';
                $code = 0;
                if (empty($str)) {
                    $mes = '第三方支付异常';
                    $code = 5;
                }
                $this->return['code'] = $code;
                $this->return['msg'] = $mes;
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = $str;
            }
        }else{
            $json = json_encode($this->parameter);
            $this->curlPost($json);
            $re = json_decode($this->re, true);
            if ($re['return_code'] && $re['result_code']) {
                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = $re['code_url'];
            } else {
                $re['err_code_des'] = $re['err_code_des'] ?? '通道异常';
                $this->return['code'] =  5;
                $this->return['msg'] = 'YB:' . $re['err_code_des'];
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = '';
            }
        }
    }

    /**
     * @param $pieces
     * @return $res
     * 验证签名
     */
    public function returnVerify($pieces) {

        $res = [
            'status' => 0,
            'order_number' => $pieces['out_trade_no'],
            'third_order' => $pieces['transaction_no'],
            'third_money' => $pieces['total_fee'],
            'error' => '',
        ];

        $sign_arr['app_id'] = $pieces['app_id'];
        $sign_arr['nonce_str'] = $pieces['nonce_str'];
        $sign_arr['out_trade_no'] = $pieces['out_trade_no'];
        $sign_arr['sign_type'] = 'MD5';
        $sign_arr['total_fee'] = $pieces['total_fee'];
        $sign_arr['version'] = '4.1';
        $string='';
        foreach ($sign_arr as $key=>$value){
            if($value !='' && $value !=null && $key !='sign'){
                $string=$string.$key.'='.$value.'&';
            }
        }
        $config = Recharge::getThirdConfig($pieces['out_trade_no']);

        $string=$string.'key='. $config['pub_key'];

        $mySign=strtoupper(md5($string));

        if( $pieces['sign']  == $mySign){
            $res['status']  = 1;
        }else{
            $res['error'] = '该订单验签不通过或已完成';
        }
        return  $res;
    }
}
