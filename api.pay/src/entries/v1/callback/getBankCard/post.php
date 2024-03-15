<?php

use Utils\Www\Action;

/**
 * 第三方支付回调入口   金额统一为分操作
 */
return new class() extends Action
{
    public function run()
    {
        //{"Success":true, "Code":1, "Message":"xx", ”Account”:”6222564898745698123”, ”Bank”:”农业银
        //行”,””””””””””RealName””:”” 张三”,” SubBranch”:”金寨路支行“ ”Sign”:”b35d8e7f7221d82937689f5ac56a8 d7
        //b” }
        $params = $this->request->getParams();
        $log = json_encode($params);
        $res = [
            'Success' => false,
            'Code' => 0,
            'Message' => '接收参数异常',
            'Account' => '',
            'Bank' => '',
            'RealName' => '',
            'SubBranch' => '',
            'Sign' => '',
        ];
        $newResponse = $this->response->withStatus(200);
        if(!isset($params['UserName']) || !isset($params['OrderNum']) || !isset($params['Sign'])) {
            $newResponse = $newResponse->withJson($res);
            throw new \Lib\Exception\BaseException($this->request, $newResponse);
        }

        //日志
        \Logic\Recharge\Recharge::addLog(['order_number' => $params['OrderNum'], 'callurl' => '', 'content' => $log], 'log_callback');
        $order = (array)\DB::table('order')->where('order_number', '=', $params['OrderNum'])->first();
        if(!$order){
            $res['Message'] = '查无此订单';
            $newResponse = $newResponse->withJson($res);
            throw new \Lib\Exception\BaseException($this->request, $newResponse);
        }
        $temp =  (array)\DB::table('passageway')->find($order['passageway_id']);
        $config = (array)\DB::table('pay_config')->find($temp['pay_config_id']);

        $keys = explode(',',$config['key']);
        $keyB = $keys[1];
        $formData=$this->buildFormData(['OrderNum'=>$params['OrderNum'],'UserName'=>$params['UserName']]);
        $sign=md5(sprintf("%s%s",$formData,$keyB));
        if(strcmp($sign,$_POST['Sign'])!=0){
            $res['Message'] = '验签失败';
            $newResponse = $newResponse->withJson($res);
            throw new \Lib\Exception\BaseException($this->request, $newResponse);
        }

        $notify = \DB::table('notify')->where('customer_id', $order['customer_id'])->where('status', 'enabled')->pluck('admin_notify')->toArray();
        if($n = rtrim(current($notify),'/')) {
            $res = \Utils\Curl::get($n . '/funds/accountNumber?' . http_build_query($params));
            $res = json_decode($res,true);
            if(isset($res['data']) && $res['data']){
                $banks = current($res['data']);
                $res = [
                    'Success' => true,
                    'Code' => 1,
                    'Message' => 'ok',
                    'Account' => $banks['card'],
                    'Bank' => $banks['bank_name'],
                    'RealName' => $banks['name'],
                    'SubBranch' => $banks['address'],
                ];
                $res['Sign'] = md5("{$res['Account']}{$res['Bank']}{$res['RealName']}{$res['SubBranch']}{$keyB}");;
                $newResponse = $newResponse->withJson($res);
            }else{
                $res['Message'] = '未配置收款账号';
                $newResponse = $newResponse->withJson($res);
            }
        }
        throw new \Lib\Exception\BaseException($this->request, $newResponse);
    }

    function buildFormData($data) {
        if(!is_array($data)) return "";
        $result="";
        foreach ($data as $k=>$v) {
            $result.=sprintf("%s=%s&",$k,$v);
        }
        $result=rtrim($result,"&");
        return $result;
    }
};
