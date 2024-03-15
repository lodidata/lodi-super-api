<?php
namespace Logic\Shop\Traits;

use Utils\Curl;
use Utils\Client;

trait RechargePayWebsite {
    public static $payDir = [
        'pay'           => ['dir' => 'recharge', 'method' => 'GET'],  //发起支付请求
        'onlineChannel' => ['dir' => 'recharge/passageway', 'method' => 'GET'], //获取厅主支付通道
        'getPayList'    => ['dir' => 'cash/3th', 'method' => 'GET'], //后台获取厅主第三方通道
        'getChannel'    => ['dir' => 'cash/channel', 'method' => 'GET'], //后台获取厅主第三方类型
        'updateStatus'  => ['dir' => 'cash/3th', 'method' => 'PATCH'], //厅主更新第三方通道状态
        'updatePayMsg'  => ['dir' => 'cash/3th', 'method' => 'PUT'], //厅主更新第三方通道信息
        'postPayMsg'    => ['dir' => 'cash/3th', 'method' => 'POST'], //厅主更新第三方通道信息
        'callbackMsg'   => ['dir' => 'thirdLog', 'method' => 'GET'], //厅主查询回调信息
        'payCount'      => ['dir' => 'cash/paycount', 'method' => 'GET'], //查询当前停用的支付是否所有渠道全部关闭
        'getIpWhite'     => ['dir' => 'recharge/ipwhite', 'method' => 'GET'], //后台获取通道的IP白名单
        'updateIpWhite'     => ['dir' => 'recharge/ipwhite', 'method' => 'PATCH'], //后台获取通道的IP白名单
        'addIpWhite'     => ['dir' => 'recharge/ipwhite', 'method' => 'POST'], //后台获取通道的IP白名单
        'deleteIpWhite'     => ['dir' => 'recharge/ipwhite', 'method' => 'DELETE'], //后台获取通道的IP白名单
    ];

    public static function paySiteUrl() {
        $pay_site = \DB::table('pay_site_config')->orderBy('response_time', 'asc')->first();
        $pay_site = (array)$pay_site;

        return $pay_site['payrequest'] . '://' . $pay_site['host'] . DIRECTORY_SEPARATOR . $pay_site['customer'] . DIRECTORY_SEPARATOR;
    }

    public static function requestPaySit(string $action, array $data = [], array $urlParam = []) {
        $secret = \DB::table('pay_site_config')->value('app_secret');

        if (in_array($action, array_keys(self::$payDir)) && $secret) {
            $url = self::paySiteUrl() . self::$payDir[$action]['dir'];
            $data['sign'] = md5(http_build_query($data) . $secret);

            if ($urlParam) {
                $url .= DIRECTORY_SEPARATOR . implode('/', $urlParam);
            }

            switch (self::$payDir[$action]['method']) {
                case 'GET':
                    $res = Curl::get($url . '?' . http_build_query($data));
                    break;
                default :
                    $res = Curl::post($url, null, $data, self::$payDir[$action]['method']);
            }

            if ($res) {
                return json_decode($res, true);
            }
        }
        return [];
    }

    public function rechargePaySite($order_number, $money, $third, $return_url, $pay_code) {
        $param = ['order_number' => $order_number, 'money' => $money, 'third' => $third, 'return_url' => $return_url, 'back_code' => $pay_code, 'client_ip' => Client::getIp()];
        return self::requestPaySit('pay', $param);
    }

    public function getOnlineChannel($type = null) {
        $param = ['type' => $type];
        return self::requestPaySit('onlineChannel', $param);
    }
}
