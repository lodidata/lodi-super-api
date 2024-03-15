<?php
use Utils\Work\Action;

/**
 * 落地页需要的
 */
return new class extends Action{

    public function run(){
        if(!$_REQUEST){
            $_REQUEST = json_decode(file_get_contents('php://input'),true);
        }
        $action = $_REQUEST['action'];
        try{
            echo $this->$action();
        }catch (\Exception $e){
            print_r($e->getMessage());
            echo 'error';
        }
        die();
    }

    //后台编辑已修改为POST方式
    function modifySign(){
        $res = ['code' => 0,'msg'=>'修改成功'];
//        if($this->isSignLogin() && isset($_REQUEST['customer']) && isset($_REQUEST['way']) && in_array($_REQUEST['way'],['superSign','businessSign'])){
        if($this->isSignLogin() && isset($_REQUEST['customer'])){
            $customer = $_REQUEST['customer'];
            if(empty($customer)){
                return 'error';
            }
            $customer_data = \DB::table('sign')->where('code', $customer)->first();
            if(empty($customer_data)){
                return 'error';
            }
            $before_data = [
//                'way'=>$customer_data->way,//签名类型
                'channel'=>$customer_data->channel,//渠道列表
                'page' => $customer_data->page,//页面代码
                'android_down' => $customer_data->android_down,//安卓下载方式，open下载, tg下载
                'ios_down' => $customer_data->ios_down,//IOS下载方式，open下载, tg下载, empty无
                'ios_open' => $customer_data->ios_open,//苹果商城下载状态，open打开,close关闭
                'ios_app_name' => $customer_data->ios_app_name,//苹果商城应用名称
                'ios_app_url' => $customer_data->ios_app_url,//苹果商城应用地址
                'ios_app_icon' => $customer_data->ios_app_icon,//苹果商城应用图标地址
            ];
            $after_data = [
//                'way'=>$_REQUEST['way'],//签名类型
                'channel'=>isset($_REQUEST['channel']) ? $_REQUEST['channel'] : '',//渠道列表
                'page' => isset($_REQUEST['page']) ? $_REQUEST['page'] : '',//页面代码
                'android_down' => isset($_REQUEST['android_down']) ? $_REQUEST['android_down'] : 'open',//安卓下载方式，open下载, tg下载
                'ios_down' => isset($_REQUEST['ios_down']) ? $_REQUEST['ios_down'] : 'empty',//IOS下载方式，open下载, tg下载, empty无
                'ios_open' => isset($_REQUEST['ios_open']) ? $_REQUEST['ios_open'] : 'close',//苹果商城下载状态，open打开,close关闭
                'ios_app_name' => isset($_REQUEST['ios_app_name']) ? $_REQUEST['ios_app_name'] : '',//苹果商城应用名称
                'ios_app_url' => isset($_REQUEST['ios_app_url']) ? $_REQUEST['ios_app_url'] : '',//苹果商城应用地址
                'ios_app_icon' => isset($_REQUEST['ios_app_icon']) ? $_REQUEST['ios_app_icon'] : '',//苹果商城应用图标地址
            ];

            //superSign超级签，businessSign企业签
//            \DB::table('sign')->where('code',$_REQUEST['customer'])->update([
//                'way'=>$_REQUEST['way'],
//                'channel'=>isset($_REQUEST['channel']) ? $_REQUEST['channel'] : '',
//                'page'=>isset($_REQUEST['page']) ? $_REQUEST['page'] : '',
//            ]);
            \DB::table('sign')->where('code',$_REQUEST['customer'])->update($after_data);
            \DB::table('sign_log')->insert([
                'admin'=>$this->redis->get('SignUserLoginToken:'.$_REQUEST['token']),
                'code'=>$customer_data->code,
                'name'=>$customer_data->name,
                'log'=>json_encode(['before_data'=>$before_data, 'after_data'=>$after_data]),
                'ip'=>\Utils\Client::getIp(),
            ]);
            return json_encode($res);
        }
        return 'error';
    }
};