<?php
namespace Logic\Recharge\Traits;

trait RechargeCallbackFailed{
    public static function callbackFailedRepeat($stime = null,$etime = null){
        $stime = $stime ? : date('Y-m-d H:i:s',strtotime(" -12 minute"));
        $etime = $etime ? : date('Y-m-d H:i:s',time());
//        print_r($stime);echo '---------',$etime.'         '.date('Y-m-d H:i:s').PHP_EOL;return;
        $callback = \DB::table('log_callback_failed')->where('status',1)->whereBetween('created',[$stime,$etime])->get();
        if($callback){
            $callback = $callback->toArray();
            $ids = [];
            foreach ($callback as $val){
                array_push($ids,$val->id);
                if($val->method == 'GET'){
                    $param = json_decode($val->content,true);
                    $url = $val->url.'?'.http_build_query($param);
                    \Utils\Curl::get($url);
                }else{
                    \Utils\Curl::commonPost($val->url,null,$val->content);
                }
            }
            \DB::table('log_callback_failed')->whereIn('id',$ids)->update(['status'=>0]);
        }
    }

    public function notifyCustomer(){
        $data = \DB::table('success_tmp')->get(['order_number', 'money'])->toArray();  //防止进程卡或者挂
        if($data){
            foreach ($data as $val){
                $this->creaseCustomer($val->order_number,$val->money);
            }
        }
    }
}