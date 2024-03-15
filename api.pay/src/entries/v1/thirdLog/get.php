<?php
use Utils\Www\Action;
use Logic\ApiPay;

/**
 * 回调日志查询
 */
return new class extends Action {

    const TITLE       = '回调日志查询';
    const DESCRIPTION = '接口';
    const HINT        = '';
    const TYPE        = null;
    const QUERY       = [
        'order_number'      => 'int(required)   #模糊搜索订单号',
    ];
    const SCHEMAs     = [
        "200" => ''
    ];

    public function run(){

        $result = array(
            'state' => 0,
            'message' => 'Fail',
            'data' => array()
        );

        $req = $_GET;   //获取get请求参数

        $order_number = $req['order_number'] ?? null;

        if(!$order_number){
            $result['message'] = '订单号不能为空!';
            return $result;
        }

        $rs = $this->getPayArray($order_number);

        if($rs){
            $result['state'] = 0;
            $result['message'] = 'OK';
            $result['data'] = $rs;
        }

        return $result;
    }

    //查询数据
    public function getPayArray($order_number = null){
        $re = DB::table('log_callback')->select('*')->where('order_number','=', $order_number)->get()->toArray();
        if($re)
            return $re;
        $re = DB::table('log_callback')->select('*')->where('content','like', "%".$order_number."%")->get()->toArray();
        if($re)
            return $re;
        else
            return [];
    }
};