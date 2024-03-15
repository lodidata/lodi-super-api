<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/9/14
 * Time: 11:44
 */

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
//use QL\QueryList;
class CFB extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->doPay();
    }

    //组装数组
    public function initParam(){

        //请求数据

        $this->parameter['version'] = '4.1';                                       //版本号
        $this->parameter['app_id'] = $this->partnerID;                            //商户APP_ID
        $this->parameter['pay_type'] = $this->payType;                                         //充值渠道
        $this->parameter['nonce_str'] = $this->createStr();                         //随机字符串
        $this->parameter['sign_type'] = 'MD5';                                     //签名类型
        $this->parameter['body'] = 'xinyunbao';                                    //商品描述
        $this->parameter['out_trade_no'] = $this->orderID;                     //商户订单号
        $this->parameter['fee_type'] = 'CNY';                                      //标价币种
        $this->parameter['total_fee'] = (int)sprintf('%.2f',$this->money);                               //支付金额  单位：分
        $this->parameter['return_url'] = $this->returnUrl;                    //跳转地址
        $this->parameter['notify_url'] = $this->notifyUrl;                    //回调地址
        $this->parameter['system_time'] = date('YmdHis', time());         //交易时间
        $this->parameter['sign'] = $this->createSign($this->parameter, $this->key);         //签名
        if($this->payType == 5){
            $this->parameter['quick_user_id'] = 1230000456;
        }


    }



    protected function doPay(){

        $this->postJson($this->payUrl,$this->parameter);
        $re = json_decode($this->re,true);

        //payType == 5  银联
        if($this->payType == 5){
            if($re['return_code'] === true && $re['result_code'] === true){
                $this->return['code'] = 0;
                $this->return['msg'] = 'success';
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = $re['code_url'];
                return ;
            }else{
                $this->return['code'] = 886;
                $this->return['msg'] = isset($re['err_code_des']) && !empty($re['err_code_des']) ? $re['err_code_des'] : 'CFB:'.$re['err_code'] ;;
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = '';
                return ;
            }

            //4.0版本返回表单
            if (!preg_match('/\<form/', $this->re)) {
//                {
//                    "return_code":true,
//    "result_code":false,
//    "err_code":"BELOW_MIN_QUOTA",
//    "err_code_des":"低于最小限额",
//    "app_id":"inbf12fd9e59564033971516df96a6d9a8",
//    "nonce_str":"xHrGJzqY79Za4MOLWfbS8Ky3vkgUFpVN",
//    "sign":"AB4F577568D98776EF25190188AB6519"
//}
                $res = json_decode($this->re,true);
                $this->return['code'] = 23;
//                $this->return['msg'] = isset($res['err_code_des']) ? 'CFB：支付错误:'.$res['err_code_des'] : 'CFB：支付错误: 请联系客服' ;
                $this->return['msg'] = $this->re ;
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = null;
                return;
            }
            preg_match('/action=\'([^\']+)\'/', $this->re, $action);
            preg_match_all('/<input type=\"hidden\" name=\'([^\']+)\' value=\'([^\']+)\'\/>/', $this->re, $matches);
            $action = $action[1];
            $data = [];
            foreach ($matches[1] as $index => $match) {
                $data[$match] = $matches[2][$index];
            }

            $this->parameter = $data;
            $this->parameter = $this->arrayToURL();
            $this->parameter .= '&url=' . $action;
            $this->parameter .= '&method=POST';

            $url = $this->jumpURL . '?' . $this->parameter;

            $this->return['code'] = 0;
            $this->return['msg'] = 'success';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $url;
        }else{

            if($this->showType == 'code'){

                if (isset($re['return_code']) && $re['return_code'] === true && $re['result_code'] === true) {
                    $this->return['code'] = 0;
                    $this->return['msg'] = 'SUCCESS';
                    $this->return['way'] = $this->showType;
                    $this->return['str'] = $re['code_url'];
                } else {
                    $this->return['code'] = 886;
                    $this->return['msg'] = isset($re['err_code_des']) && !empty($re['err_code_des']) ? $re['err_code_des'] : 'CFB:'.$re['err_code'] ;
                    $this->return['way'] = $this->showType;
                    $this->return['str'] = '';
                }
            }else{

                $this->parameter['method'] = 'GET';

                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] = $this->showType;
                $this->return['str'] = $re['code_url'];
            }

        }


    }

    /**
     * post请求发送json数据
     * @param $url    string
     * @param $data   array
     * @return mixed
     */
    protected function postJson($url, $data)
    {
        $data = json_encode($data,true);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8', 'Content-Length:' . strlen($data)]);
        $res = curl_exec($curl);
        $this->curlError = curl_error($curl);
        curl_close($curl);
        $this->re = $res;
        return $res;
    }

    /*
     * 生成随机字符串
     */
    protected  function createStr()
    {
        $nstr = 'WERTYUIOPASDFGHJKLZXCVBNM1234567890qwertyuiopasdfghjklzxcvbnm';
        $nonce_str = substr(str_shuffle($nstr),0,32);
        return $nonce_str;
    }

    /**
     * 生成签名
     * @param $data
     * @param $app_secret
     * @return string
     */
    protected function createSign($data, $app_secret)
    {
        $sign = MD5('app_id='.$data['app_id'].'&nonce_str='.$data['nonce_str'].'&out_trade_no='.$data['out_trade_no'].'&sign_type='.$data['sign_type'].'&total_fee='.$data['total_fee'].'&version='.$data['version'].'&key='.$app_secret);
        return strtoupper($sign);
    }

    public  function returnVerify($params) {

        $res = [
            'status' => 0,
            'order_number' => $params['out_trade_no'],//outorderno
            'third_order'  => $params['transaction_no'],
            'third_money'  => $params['real_total_fee'],
            'error' => ''
        ];
        $config = Recharge::getThirdConfig($params['out_trade_no']);

        $return_code = $params['return_code'] ? $params['return_code'] : '';
        $result_code = $params['result_code'] ? $params['result_code'] : '';
        $trade_state = $params['trade_state'] ? $params['trade_state'] : '';
        $sign = $params['sign'] ? $params['sign'] : '';
        if($trade_state == 'SUCCESS'){          //交易状态成功
            $app_secret = $config['key'];
            //签名数据
            $data = [];
            $data['app_id'] = $params['app_id'];
            $data['nonce_str'] = $params['nonce_str'];
            $data['out_trade_no'] = $params['out_trade_no'];
            $data['sign_type'] = 'MD5';
            $data['total_fee'] = $params['total_fee'];
            $data['version'] = '4.1';
            $signStr = $this->createSign($data, $app_secret);
            if($sign == $signStr) {
                if($params['return_code'] == true && empty($params['return_msg'])){
                    $res['status']  = 1;
                }else{
                    $res['error'] = isset($params['err_code_des']) && !empty($params['err_code_des']) ? $params['err_code_des'] : '第三方异常：'.$params['err_code'];
                }
            }else{
                $res['error'] = '该订单验签不通过或已完成';
            }
        }

        return  $res;
    }

//2：微信扫码 4：支付宝扫码 5：快捷支付 6：QQ扫码 7：微信公众号支付 8：网关支付 9：银联扫码 10：京东支付
//
//11：微信H5内支付 12：微信H5支付 14：百度钱包 15：通联H5支付 17：京东H5 20：支付宝H5 21：QQH5
}