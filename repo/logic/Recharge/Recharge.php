<?php

namespace Logic\Recharge;

use Logic\Recharge\Traits\RechargeCallback;
use Logic\Recharge\Traits\RechargeCallbacks;
use Logic\Recharge\Traits\RechargeCallbackFailed;
use Logic\Recharge\Traits\RechargeCustomer;
use Logic\Recharge\Traits\RechargeLog;
use Logic\Recharge\Traits\RechargePay;
use Utils\Client;
use Utils\Curl;
use Utils\Utils;

class Recharge extends \Logic\Logic
{
    use RechargePay;
    use RechargeLog;
    use RechargeCallback;
    use RechargeCallbacks;
    use RechargeCallbackFailed;
    use RechargeCustomer;

    private $next = false;
    public $ThirdPayClass = 'Logic\Recharge\Pay';
    public $ThirdPayClass2 = 'Logic\Recharge\Defray';

    public static $sceneType = [
        'scene' => [
            'wx' => '微信',
            'alipay' => '支付宝',
            'unionpay' => '银联',
            'qq' => 'QQ',
            'jd' => '京东',
            'ysf' => '云闪付',
        ],
        'type' => [
            'h5' => 'WAP',
            'code' => '扫码',
            'quick' => '快捷',
            'js' => '公共号',
            'sdk' => 'SDK',
        ],
    ];

    public static $payDir = [
        'gameSwitch' => ['dir' => 'game/manager', 'method' => 'PATCH'],  //修改总开关
        'gameM' => ['dir' => 'game/manager', 'method' => 'PUT'],  //修改维护时间
        'banksAdd' => ['dir' => 'banks', 'method' => 'POST'],  //新增银行信息
        'banksUpdate' => ['dir' => 'banks', 'method' => 'PUT'],  //修改银行信息
        'banksStatus' => ['dir' => 'banks', 'method' => 'PATCH'],  //修改银行信息状态
        'banksDel' => ['dir' => 'banks', 'method' => 'DELETE'],  //删除银行信息
        'putClearStatus' => ['dir' => 'quota/clear', 'method' => 'PATCH'],  //修改rpt_order_amount表的清算状态
        'postQuota' => ['dir' => 'quota', 'method' => 'POST'],  //新增客户额度列表
        'putQuota' => ['dir' => 'quota', 'method' => 'PUT'],  //修改客户额度列表
        'updateImgNotify' => ['dir' => 'imgnotify', 'method' => 'PUT']//修改图片域名
    ];


    public function allowNext()
    {
        return $this->next;
    }

    public function getThirdClass($third_code)
    {
        $className = "$this->ThirdPayClass\\" . $third_code;
        if (class_exists($className)) {
            $obj = new $className;   //初始化类
            return $obj;
        } else
            return false;
    }

    public function getThirdClass2($third_code)
    {
        $className = "{$this->ThirdPayClass2}\\" . $third_code;
        if (class_exists($className)) {
            $obj = new $className;   //初始化类
            return $obj;
        } else
            return false;
    }

    public function existThirdClass($third_code)
    {
        $className = "$this->ThirdPayClass\\" . $third_code;
        if (class_exists($className)) {
            return true;
        } else
            return false;
    }

    public function existThirdClass2($third_code)
    {
        $className = "{$this->ThirdPayClass2}\\" . $third_code;
        if (class_exists($className)) {
            return true;
        } else
            return false;
    }

    //IP验证   true  不在IP白名单   false  在IP白名单
    public function isIPBlack($order_number)
    {
        $ip = Utils::RSAEncrypt(Client::getIp());
        $config = \DB::table('order')->leftJoin('passageway AS p', 'order.passageway_id', '=', 'p.id')
            ->leftJoin('pay_config AS c', 'p.pay_config_id', '=', 'c.id')
            ->where('order_number', $order_number)->first(['c.customer_id', 'c.channel_id']);

        if (!$config)
            return false;

        $config = (array)$config;
        $customer_id = $config['customer_id'];
        $channel_id = $config['channel_id'];

        $ip_switch = \DB::table('callback_ip_switch')->where('customer_id', $customer_id)->where('channel_id', 0)->value('switch');
        if ($ip_switch) {  //开启IP回调限制
            $re = \DB::table('callback_ip_white')->where('ip', $ip)->where('channel_id', $channel_id)->first(['customer_id', 'channel_id']);
            if ($re) {
                return false;
            }
            \DB::table('order')->where('status', '=', 'pending')
                ->where('order_number', $order_number)
                ->update(['desc' => '未在回调IP白名单之内', 'callback_ip' => $ip]);
            return true;
        }
        \DB::table('order')->where('status', '=', 'pending')
            ->where('order_number', $order_number)
            ->update(['desc' => 'IP回调白名单未开启', 'callback_ip' => $ip]);
        return false;
    }


    //获取所有游戏厅主后台的地址
    public static function paySiteUrl()
    {
        $pay_site = \DB::table('customer_notify')
            ->leftJoin('customer', 'customer_notify.customer_id', '=', 'customer.id')
            ->select(['customer_notify.*', 'customer.customer'])
            ->where('status', '=', 'enabled')
            ->where('type', '=', 'game')
            ->get()
            ->toArray();
        $siteUrl = [];
        foreach ($pay_site as $key => $item) {
            $item = (array)$item;
            $siteUrl[$key] = $item['admin_notify'] . '/';
        }
        return $siteUrl;
    }

    //获取所有游戏厅主后台的地址
    public static function paySiteUrlByCustomer($customer)
    {
        $pay_site = \DB::table('customer_notify')
            ->leftJoin('customer', 'customer_notify.customer_id', '=', 'customer.id')
            ->select(['customer_notify.*', 'customer.customer'])
            ->where('status', '=', 'enabled')
            ->where('type', '=', 'game')
            ->where('customer.customer', '=', $customer)
            ->get()
            ->toArray();
        $siteUrl = [];
        foreach ($pay_site as $key => $item) {
            $item = (array)$item;
            $siteUrl[$key] = $item['admin_notify'] . '/';
        }
        return $siteUrl;
    }

    //获取所有彩票和游戏厅主后台的地址
    public static function paySiteUrlLottery()
    {
        $pay_site = \DB::table('customer_notify')
            ->leftJoin('customer', 'customer_notify.customer_id', '=', 'customer.id')
            ->select(['customer_notify.*', 'customer.customer'])
            ->where('status', '=', 'enabled')
            ->where('customer_notify.customer_id', '<>', 0)
            ->get()
            ->toArray();
        $siteUrl = [];
        foreach ($pay_site as $key => $item) {
            $item = (array)$item;
            $siteUrl[$key] = $item['admin_notify'] . '/';
        }
        return $siteUrl;
    }

    //请求厅主后台对应的业务逻辑接口
    public static function requestPaySit(string $action, string $type = null, array $data = [], array $urlParam = [])
    {
        global $app;
        if ($type == 'all') {
            $siteUrl = self::paySiteUrlLottery();
        } else {
            $siteUrl = self::paySiteUrl();
        }
        if (!$siteUrl) {
            return [];
        }
        if (in_array($action, array_keys(self::$payDir))) {
            $api_verify_token = $app->getContainer()->get('settings')['app']['api_verify_token'];
            $header = ['api-token:'.$api_verify_token.date("Ymd")];
            foreach ($siteUrl as $item) {
                $url = $item . self::$payDir[$action]['dir'];
                if ($urlParam) {
                    $url .= '/' . implode('/', $urlParam);
                }
                switch (self::$payDir[$action]['method']) {
                    case 'GET':
                        $res = Curl::get($url . '?' . http_build_query($data), null, false, $header);
                        self::addElkLog(['url' => $url . '?' . http_build_query($data), 'result' => $res]);
                        break;
                    default :
                        $res = Curl::post($url, null, $data, self::$payDir[$action]['method'], false, $header);
                        self::addElkLog(['url' => $url, 'json' => $data, 'method' => self::$payDir[$action]['method'], 'result' => $res]);
                }
            }
        }

        return true;
    }

    //请求厅主后台对应的业务逻辑接口
    public static function requestPaySitByCustomer(string $action, string $type = null, array $data = [], array $urlParam = [])
    {
        $siteUrl = self::paySiteUrlByCustomer($data['customer']);
        if (!$siteUrl) {
            return [];
        }
        if (in_array($action, array_keys(self::$payDir))) {
            foreach ($siteUrl as $item) {
                $url = $item . self::$payDir[$action]['dir'];
                if ($urlParam) {
                    $url .= '/' . implode('/', $urlParam);
                }
                switch (self::$payDir[$action]['method']) {
                    case 'GET':
                        $res = Curl::get($url . '?' . http_build_query($data));
                        self::addElkLog(['url' => $url . '?' . http_build_query($data), 'result' => $res]);
                        break;
                    default :
                        $res = Curl::post($url, null, $data, self::$payDir[$action]['method']);
                        self::addElkLog(['url' => $url, 'json' => $data, 'method' => self::$payDir[$action]['method'], 'result' => $res]);
                }
            }
        }
        return true;
    }

    public static function decryptConfig($config)
    {
        foreach ($config as $key => &$val) {
            if (in_array($key, ['app_id', 'app_secret', 'key', 'pub_key', 'token'])) {
                $val = Utils::XmcryptString($val, false);
            }
        }
        return $config;
    }

    public static function encryptConfig($config)
    {
        foreach ($config as $key => &$val) {
            if (in_array($key, ['app_id', 'app_secret', 'key', 'pub_key', 'token'])) {
                $val = Utils::XmcryptString($val);
            }
        }
        return $config;
    }

    /**
     * LOG日志
     * @param $data
     * @param string $path
     * @param string $file
     */
    public static function addElkLog($data, $path = 'sync', $file = 'request'){
        $file_path = LOG_PATH.'/'.$path;
        if (!is_dir($file_path) && !mkdir($file_path, 0777, true)) {
            $path = '';
        }else{
            $path.='';
        }
        $file = $file_path.'/'.$file.'-'.date('Y-m-d').'.log';
        $stream = @fopen($file, "aw+");
        $str = '[ ' . date('Y-m-d H:i:s'). ' ] ' .json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
        @fwrite($stream, $str);
        @fclose($stream);
    }
}