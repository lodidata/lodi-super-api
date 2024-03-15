<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class STG东亚体育
 */
class STG extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_stg';
    protected $game_type = 'STG';
    /**
     * @var string 随机加密码
     */
    private $stg_key = '5hwv80YFKGJnP1fk';

    /**
     * 检查接口状态
     * @return bool
     */
    public function checkStatus()
    {
        return true;
    }

    /**
     * 同步第三方游戏订单
     * 拉单延迟5分钟，最大拉单区间30天
     * @return bool
     * @throws \Exception
     */
    public function synchronousData()
    {
        return true;
    }

    /**
     * 更新订单表
     * @param array $data
     * @return bool
     */
    public function updateOrder($data)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        $delOrderNumber = [];
        $insertOrderNumber = [];
        unset($data['OrderBets']);

        //子注单
        foreach ($data['OrderBetStakes'] as $sub) {
            //未结算注单，不更新
            if (!in_array($sub['StakeStatus'], [2, 3, 4])) {
                $delOrderNumber[] = $sub['OrderNumber'];
                continue;
            }
            if (in_array($sub['OrderNumber'], $delOrderNumber)) {
                continue;
            }
            //需要更新的注单
            $insertOrderNumber[$sub['OrderNumber']][] = [
                'BetNumber' => $sub['BetNumber'],
                'StakeName_en' => $sub['StakeName_en'],
                'BetStakeAmount' => $sub['BetStakeAmount'],
                'EventDate' => $sub['EventDate'],
                'StakeStatus' => $sub['StakeStatus'],
                'EventName_en' => $sub['EventName_en'],
                'SportName_en' => $sub['SportName_en'],
            ];
            unset($sub);
        }

        //总注单
        foreach ($data['Orders'] as $order) {
            if (in_array($order['OrderNumber'], $delOrderNumber)) {
                continue;
            }
            date_default_timezone_set("Etc/GMT-4");
            $FillDate = strtotime($order['FillDate']);
            $PayOutFillDate = strtotime($order['PayOutFillDate']);
            $DateUpdated = strtotime($order['DateUpdated']);
            date_default_timezone_set($default_timezone);

            $ClientID = @dechex($order['PartnerClientID']);
            $tid = substr($ClientID, 0, 2);
            $user_id = substr($ClientID, 2);
            if ($tid == 0 || $user_id == 0) {
                continue;
            }

            $insertData[] = [
                'tid' => $tid,
                'user_id' => $user_id,
                'OrderNumber' => (string)$order['OrderNumber'],
                'ClientID' => $order['PartnerClientID'],
                'FillDate' => date('Y-m-d H:i:s', $FillDate),
                'PayOutFillDate' => date('Y-m-d H:i:s', $PayOutFillDate),
                'DateUpdated' => date('Y-m-d H:i:s', $DateUpdated),
                'Amount' => $order['Amount'],
                'WinAmount' => $order['WinAmount'],
                'UsedAmount' => $order['UsedAmount'],
                'profit' => bcsub($order['WinAmount'], $order['Amount'], 0),
                'BetDetail' => json_encode($insertOrderNumber[(string)$order['OrderNumber']]),
            ];
        }
        if (empty($insertData)) {
            return true;
        }
        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);

    }

    /**
     * 订单校验
     * @return bool
     * @throws \Exception
     */
    public function synchronousCheckData()
    {
        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        return [];
    }

    /**
     * 游戏统计
     * @param null $date 日期
     * @return bool
     */
    public function queryOperatesOrder($date = null)
    {
        $data = [
            'username' => 'ClientID',
            'bet' => 'Amount',
            'win' => 'WinAmount',
            'profit' => 'profit',
            'gameDate' => 'DateUpdated'
        ];
        return $this->rptOrdersMiddleDay($date, $this->orderTable, $this->game_type, $data, false);
    }

    public function queryHotOrder($user_prefix, $startTime, $endTime, $args = [])
    {
        return [];
    }

    public function queryLocalOrder($user_prefix, $start_time, $end_time, $page = 1, $page_size = 500)
    {
        return [];
    }

    /**
     * 按分钟检索事务
     * @param $stime
     * @param $etime
     * @param bool $is_redis
     * @return bool
     */
    public function orderByTime($stime, $etime, $is_redis = false)
    {
        return true;
    }

    /**
     * 按小时拉取
     * @param $stime
     * @param $etime
     * @return bool
     */
    public function orderByHour($stime, $etime)
    {
        return $this->orderByTime($stime, $etime);
    }


    /**
     * 生成sign
     * @param string $method 请求方法
     * @param array $params 参数
     * @param array $md5Keys 加密字段
     * @param string $key 密钥
     * @return string
     */
    public function Signature($method, $params, $md5Keys, $key)
    {
        $str = 'method' . $method;
        foreach ($md5Keys as $k) {
            $str .= $k . $params[$k];
        }
        $sign = md5($str . $key);
        return $sign;
    }

    /**
     * 错误信息
     * @param $code
     * @return mixed|string
     */
    public function getErrorMessage($code)
    {
        $message = [
            0 => 'Success',
            20 => 'CurrencyNotExists',
            22 => 'ClientNotFound (ClientId does not exist)',
            37 => 'WrongToken',
            46 => 'TransactionAlreadyExists',
            64 => 'TransactionNotExists',
            70 => 'PartnerNotFound',
            71 => 'LowBalance',
            500 => 'InternalServerError',
            1013 => 'InvalidInputParameters',
            1016 => 'InvalidSignature',
        ];

        return $message[$code] ?? 'unknown';
    }
}
