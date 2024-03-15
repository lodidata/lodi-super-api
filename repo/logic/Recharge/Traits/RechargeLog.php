<?php
namespace Logic\Recharge\Traits;

trait RechargeLog{

    private static $logDir =  ROOT_PATH.'/data/logs/php';
    public static $logs = [
        'log_request_third' => 'request_third.txt',
        'log_callback' => 'pay_callback.txt',
        'log_callback_failed' => 'log_callback_failed.txt',
        'order' => 'order_log.txt',
        'pay_error' => 'pay_error_log.txt',
    ];
    //添加数据库日志
    public static function addLogBySql($data,$table){
        \DB::table($table)->insert($data);
    }
    //添加文本日志
    public static function addLogByTxt($data,$table){
        $stream = fopen(self::$logDir.'/'.self::$logs[$table], "aw+");
        $str = '';
        foreach ($data as $key=>$val){
            $str .= $key.':'.$val.' ';
        }
        $str .= "\r\n";
        fwrite($stream, $str);
        fclose($stream);
    }

    public static function addLog($data,$table){
        self::addLogBySql($data,$table);
        self::addLogByTxt($data,$table);
    }

    public static function logger($obj,$data,$table){
        $obj->logger->info($table, $data);
    }
}