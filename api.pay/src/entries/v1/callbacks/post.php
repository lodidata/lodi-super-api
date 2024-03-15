<?php

use Utils\Www\Action;

/**
 * 第三方支付回调入口   金额统一为分操作
 */
return new class() extends Action
{
    //$id  为客户ID
    public function run($customer_id)
    {
        $thirdParam = \Logic\Define\CallBack::get();
        $data = $this->request->getParams();  //不管啥格式统一用二进制流接收
        if(!$data) {
            $log = $data = file_get_contents('php://input');  //不管啥格式统一用二进制流接收
        }else {
            $log = json_encode($data);
        }
        \Logic\Recharge\Recharge::addLogByTxt(['third' => CALLBACK, 'date' => date('Y-m-d H:i:s'), 'data' => $log], 'log_callback');
        \Logic\Recharge\Recharge::logger($this, ['third' => CALLBACK, 'date' => date('Y-m-d H:i:s'), 'data' => $log], 'log_callback');

        if ($thirdParam && $data) {

            $pay = new Logic\Recharge\Recharge($this->ci);
            if (!$pay->existThirdClass2(CALLBACK)) {
                $desc = '未有该第三方:' . CALLBACK . '类，请技术核查';
            } else {
                $config = (array)\DB::table('pay_config')
                    ->where('customer_id',$customer_id)
                    ->where('channel_id',$thirdParam['id'])
                    ->first();
                $config['notify_url'] = \DB::table('notify')->where('customer_id',$customer_id)->value('www_notify');
                $re = $pay->callbackNotify($data,$config);
                $desc = $re['error'];
            }
            //写入回调日志表
            $logs = [
                'order_number' => $customer_id.'_'.CALLBACK.'_'.$re['order_number'],
                'desc' => $desc,
                'callurl' => $this->request->getUri()->getPath() . DIRECTORY_SEPARATOR . strtolower($this->request->getMethod()),
                'content' => $log,
                'ip' => \Utils\Utils::RSAEncrypt(\Utils\Client::getIp()),
            ];
            \Logic\Recharge\Recharge::addLogBySql($logs, 'log_callback');
        }
        if ($thirdParam) {
            if($thirdParam['return'] != 'callback'){//回调
                echo $thirdParam['return'];
                die();
            }else{
//                返回给第三方的异步回调报文需要处理
                $obj = $pay->getThirdClass2(CALLBACK);
                $return = $obj->callback($data, $config);
                exit($return);
            }
        }else
            return false;
    }
};
