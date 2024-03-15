<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Requests;
/**
 * 币宝支付
 * @author viva
 */

class BB extends BASES {

    private $config = [];

    //与第三方交互
    public function start(){

        $this->initParam();
//        $this->basePost();
        if(!$this->payUrl)
            $this->payUrl = 'http://opoutox.gosafepp.com';
        $this->parseRE();
    }
    //组装数组
    public function initParam(){

//        MerCode  Y  String  商户号
//Timestamp  N  Int  UNIX 时间戳
//UserName  Y  String  会员名称
//Type  Y  Int  请求类型 0-查看订单，1-买币，2-卖币
//Coin  N  String  币种 如：BCB,DC,USDX
//Amount  N  String  卖/买币数量,如果指定，则资金托管将根据数量进行限
//定
//OrderNum  Y  String  商户定义的唯一订单编号,长度不超过 32 位，数字货
//币交易订单变动通知，以 OrderNum 为准。
//PayMethods  N  String  商户限制的支付方式，多个用英文逗号隔开，取值范
//围 :bankcard: 银 行 卡 ,aliPay: 支 付 宝 ,weChatpay: 微
//信,payPal:PayPal
//Key  Y  String  验证码(需全小写)，組成方式如下:Key=A+B+C(验证码
//組合方式)
//A= 无意义字串长度 X 位(币宝后台可配置)
//B=MD5(MerCode + UserName +Type + OrderNum + KeyB
//    + YYYYMMDD)
//C=无意义字串长度 X 位(币宝后台可配置)
//YYYYMMDD 为北京时间(GMT+8)(20150320
        $this->parameter = array(
            'MerCode'    => $this->partnerID,
            'PayMethods'    => $this->data['bank_data'],
            'UserName'    => time(),
            'Type'    => $this->data['bank_data'],
            'Coin'	    => 'DC',
            'orderNum'	    => $this->orderID,
            'Amount'     => ($this->money)/100,
            'callBackUrl'   => $this->notifyUrl,
            'frontBackUrl'  => $this->returnUrl,

        );

        $keys = explode(',',$this->key);
        $this->config['DesKey'] = $this->pubKey;
        $this->config['KeyB'] = $keys[1];
        $this->config['HeadNum'] = $keys[0];
        $this->config['TailNum'] = $keys[2];
        $this->config['MerCode'] = $this->partnerID;
        $this->config['Url'] = $this->payUrl;
        $this->config['UserName'] = uniqid();
    }

    private function addUser(){
        $data=[
            'MerCode'=>['signed'=>true],
            'TimeStamp'=>['signed'=>false],
            'UserName'=>['signed'=>true]
        ];
        $res = $this->Request($data,'addUser');
        return $res;
    }


    private function getAddress(){

        $data=[
            'MerCode'=>['signed'=>true],
            'TimeStamp'=>['signed'=>false],
            'UserType'=>['signed'=>true,'value'=>1],//1会员，2商户
            'UserName'=>['signed'=>false],
            'CoinCode'=>['signed'=>true,'value'=>'DC']
        ];
        $res = $this->Request($data,'getAddress');
        return $res;
    }


    private function login(){

        $orderNo = $this->orderID;
        $data=[
            'MerCode'=>['signed'=>true],
            'TimeStamp'=>['signed'=>false],
            'UserName'=>['signed'=>true],
            'Type'=>['signed'=>true,'value'=>1],
            'Coin'=>['signed'=>false,'value'=>'DC'],
            'Amount'=>['signed'=>false,'value'=>($this->money)/100],
            'OrderNum '=>['signed'=>true,'value'=>$orderNo],
            'PayMethods '=>['signed'=>false,'value'=>$this->data['bank_data']]
        ];
        $res = $this->Request($data,'login');
        return $res;
        $result = json_decode($res->body,true);
        if($result['Success'] === true){
            $url = $result['Data']['Url'] .'/'.$result['Data']['Token'];
        }
        echo $url;exit;
    }

    private function Request($data,$apiName)
    {
        $config = $this->config;
        $desKey=$config['DesKey'];
        $keyB=$config['KeyB'];
        $headNum=$config['HeadNum'];
        $tailNum=$config['TailNum'];
        $merCode=$config['MerCode'];
        $url="${config['Url']}/api/${merCode}/coin/${apiName}";
//        $userName=$config['userName'];
        $timestamp=$this->getTimestamp();
        $newData=array();
        $signData=array();
        foreach($data as $k=>$v) {
            if(array_key_exists($k,$config)){
                if(array_key_exists('value',$data[$k])){
                    $newData[$k]=$data[$k]['value'];
                }
                else{
                    $newData[$k]=$config[$k];
                }
            }
            else{
                if($k=='TimeStamp'){
                    $newData['TimeStamp']=$timestamp;
                }
                else {
                    $newData[$k]=$data[$k]['value'];
                }
            }
            if($data[$k]['signed']){
                array_push($signData,$newData[$k]);
            }
        }
        array_push($signData,$keyB);
        $formData=$this->buildFormData($newData);
        $param=$this->encrypt($formData,$desKey);
        $key=$this->getKey($headNum,$tailNum,$signData);
        $postData=[
            "param"=>$param,
            "key"=>$key
        ];
        $res = Requests::post($url,array(),$postData);
        return json_decode($res->body,true);
    }
    
    public function parseRE(){

        $step1 = $this->addUser();//{"Success":true,"Code":1,"Message":"操作成功"}
        if($step1['Success'] === true){
            $step2 = $this->getAddress();
            if($step2['Success'] === true){
                $step3 = $this->login();
                if($step2['Success'] === true){
                    $this->return['code'] = 0;
                    $this->return['msg']  = 'SUCCESS';
                    $this->return['way']  = $this->data['return_type'];
                    $this->return['str']  = $step3['Data']['Url'] .'/'.$step3['Data']['Token'];;
                }else{
                    $this->return['code'] = 886;
                    $this->return['msg']  = 'BB:'.$step3['Message'];
                    $this->return['way']  = $this->data['return_type'];
                    $this->return['str']  = '';
                }
            }else{
                $this->return['code'] = 886;
                $this->return['msg']  = 'BB:'.$step2['Message'];
                $this->return['way']  = $this->data['return_type'];
                $this->return['str']  = '';
            }
        }else{
            $this->return['code'] = 886;
            $this->return['msg']  = 'BB:'.$step1['Message'];
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = '';
        }

        $this->re = $this->return;

//        $step2 = $this->getAddress();
//        $step3 = $this->login();


//        $re = json_decode($this->re,true);
//        if($re['resultCode'] === '00'){

    }

    /**
     * 返回地址验证
     *
     * @param
     * @return boolean
     */

    public function returnVerify($params){
        global $app;
        $params = $app->getContainer()->request->getParams();
        unset($params["s"]);

        if(!isset($params['OrderNum'])){
            $result['status'] = 0;
            $result['error'] = '非法数据';
            return $result;
        }

        $result = [
            'status' => 0,
            'order_number' => $params['OrderNum'],
            'third_order'  => isset($params['OrderId']) ? $params['OrderId']:$params['OrderNum'],
            'third_money'  => $params['LegalAmount'] * 100,//实际付款
            'error' => ''
        ];

        if( $params['State1'] != 2 || $params['State2'] != 2){
            $result['error'] = "支付未完成";
            $result['status'] = 0;
            return  $result;
        }

        $config = Recharge::getThirdConfig($params['OrderNum']);
        $keys = explode(',',$config['key']);
        $keyB = $keys[1];

        if (!$keyB) {
            $result['error'] = '密钥配置错误';
            $result['status'] = 0;
            return  $result;
        }

        $return_sign = $params['Sign'];
        //不参与签名字段
        unset($params['FinishTime']);
        unset($params['Sign']);
        ksort($params);
        $formData=$this->buildFormData($params);
        $sign_str = sprintf("%s%s", $formData, $keyB);
        $sign=md5($sign_str);

        if($sign == $return_sign){
            $result['status']  = 1;
        } else {
            $result['error']  = '签名错误';
            $result['status'] = 0;
        }

        return $result;

    }


    public function buildFormData($data) {
        if(!is_array($data)) return "";
        $result="";
        foreach ($data as $k=>$v) {
            $result.=sprintf("%s=%s&",$k,$v);
        }
        $result=rtrim($result,"&");
        return $result;
    }

    public function getTimestamp() {
        return strval(time());
    }

    private function getKey($headNum, $tailNum, $keyData) {
        $nowTime=date("Ymd",time());
        $sign=md5(sprintf("%s%s",join("",$keyData),$nowTime));
        $key=sprintf("%s%s%s",$this->getRandLetter($headNum),$sign,$this->getRandLetter($tailNum));
        return $key;
    }

    private function getRandLetter($length) {
        $letters = "0123456789abcdefghijklmnopqrstuvwxyz";
        $length=min(strlen($letters),$length);
        $result=substr(str_shuffle($letters),0,$length);
        return $result;
    }

    private function encrypt($data,$key) {
        $result=openssl_encrypt($data,"DES-CBC",$key,OPENSSL_RAW_DATA, $key);
        return strtoupper(bin2hex($result));
    }

    private function decrypt($data,$key) {
        $data=hex2bin(strtolower($data));
        $result=openssl_decrypt($data,"DES-CBC",$key,OPENSSL_RAW_DATA, $key);
        return $result;
    }

}
