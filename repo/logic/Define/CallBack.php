<?php

namespace Logic\Define;

/**
 * Class Lang
 * 统一文本返回类
 * @package Logic\Define
 */
class CallBack {

    /*  某为值test则不验证该字段是否存在
     * 相应第三方回调配置，以供验签
     * @mode 模式（current普通模式，encrypt加密模式）  encrypt模式任何验证请在相应的第三方内部验证包括订单状态等
     * @order_number 第三方对应订单号字段
     * @param 接收第三方值的方式（以便解析）：json  xml           （GET请求的与加密原封不动的传）
     * @return 给第三方返回标识
     */
    private static $third = [
        'ESD' => ['mode'=>'current','order_number'=>'shoporderId','param'=>'json','return'=>'SUCCESS'],
        'JHBOSS' => ['mode'=>'current','order_number'=>'ordernumber','param'=>'json','return'=>'ok'],
    ];

    public static $verify = [
        'order_number'
    ];
    /**
     * 赋值
     * @param       $state
     * @param array $stateParams
     * @param array $data
     *
     * @return $this
     */

    public static function get() {
        $third = \DB::table('pay_channel')->where('code',CALLBACK)->first(['id','return','param','order_number','mode']);
        if($third)
            return (array)$third;
        else
            return false;
    }


}