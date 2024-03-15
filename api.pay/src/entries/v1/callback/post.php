<?php

use Utils\Www\Action;

/**
 * 第三方支付回调入口   金额统一为分操作
 */
return new class() extends Action
{
    public function run()
    {
        $thirdParam = \Logic\Define\CallBack::get();
        if (strtolower($this->request->getMethod()) == 'post')
            if(isset($thirdParam['param']) && $thirdParam['param'] == 'json'){
                $str = $this->request->getParams();
                $log = json_encode($str);
            }else {
                $log = $str = file_get_contents('php://input');  //不管啥格式统一用二进制流接收
            }
        else {
            $str = $this->request->getParams();  //不管啥格式统一用二进制流接收
            $log = json_encode($str);
        }
        \Logic\Recharge\Recharge::addLogByTxt(['third' => CALLBACK, 'date' => date('Y-m-d H:i:s'), 'data' => $log], 'log_callback');
        \Logic\Recharge\Recharge::logger($this, ['third' => CALLBACK, 'date' => date('Y-m-d H:i:s'), 'data' => $log], 'log_callback');
        if ($thirdParam && $str) {

            switch ($thirdParam['param']) {
                case 'json' :
                    //由于是二进制流接收，所以需要这样写
                    $data = $str;
                    break;
                case 'xml':
                    $data = \Utils\Utils::parseXML($str);
                    break;
                default:
                    $data = $str;
            }
            $desc = '';
            $re = ['flag' => 0,'order_number' => ''];
            if ($data) {
                $pay = new Logic\Recharge\Recharge($this->ci);
                if (!$pay->existThirdClass(CALLBACK)) {
                    $desc = '未有该第三方:' . CALLBACK . '类，请技术核查';
                } else {
                    $re = $pay->returnVerify($data);
                    $desc = $re['flag'] == 2 ? '不在IP白名单内(不允加钱)' : (isset($re['msg']) ? $re['msg'] : '');
                }
            }
            //写入回调日志表
            $logs = [
                'order_number' => $re['order_number'],
                'desc' => $desc,
                'callurl' => $this->request->getUri()->getPath() . DIRECTORY_SEPARATOR . strtolower($this->request->getMethod()),
                'content' => $log,
                'ip' => \Utils\Utils::RSAEncrypt(\Utils\Client::getIp()),
            ];
            \Logic\Recharge\Recharge::addLogBySql($logs, 'log_callback');
            //进入队列失败   说明代码或配置有误 定时器定时跑
            if ($re['flag'] == 0) {
                $url = $this->request->getUri()->getScheme() . '://' . $this->request->getUri()->getHost() . $this->request->getUri()->getPath();
                $repeat = [
                    'url' => $url,
                    'method' => $this->request->getMethod(),
                    'content' => $log,
                ];
                \Logic\Recharge\Recharge::addLogBySql($repeat, 'log_callback_failed');
            }
        }
        if ($thirdParam) {
            if (!isset($re) || empty($re['order_number'])) {
                echo 'fail: 回调参数不完整';
            } elseif ($re['flag'] == 2){
                echo '当前IP未在白名单内,禁止访问:' . \Utils\Client::getIp();
            } else {
                echo $thirdParam['return'];
            }
            die();
        }else
            return false;
    }
};
