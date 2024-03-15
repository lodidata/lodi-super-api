<?php
namespace Logic\Recharge\Traits;

use Utils\Curl;

trait RechargeCustomer{

    //  请求业务平台依据订单号添加金额
    public function creaseCustomer($order_number,$money){
        $customer_id = \DB::table('order')->where('order_number', '=', $order_number)->value('customer_id');
        if(!$customer_id){
            return;
        }
        $notify = \DB::table('customer_notify')->where('customer_id', $customer_id)->where('status', 'enabled')->pluck('admin_notify');
        if ($notify && count($notify) > 0) {
            $customer_url = array_random($notify->toArray()) . DIRECTORY_SEPARATOR . $this->ci->get('settings')['customer']['dir'];
            $method = $this->ci->get('settings')['customer']['method'];
            $param = ['order_number' => $order_number, 'money' => $money];
            $param['sign'] = md5(http_build_query($param) . $this->ci->get('settings')['app']['tid'] . $this->ci->get('settings')['app']['app_secret']);
            if ($method == 'POST')
                $res = Curl::post($customer_url, null, $param);
            else
                $res = Curl::get($customer_url . '?' . http_build_query($param));
            if ($res) {
                $res = json_decode($res, true);
                if (isset($res['data']))
                    $res = $res['data'];
                $update = [];
                switch ($res['opt']) {
                    //OK  已加钱
                    case 1:
                        $update['status'] = 'paid';
                        break;
                    //OK  客户已补单加钱
                    case 2:
                        $update['status'] = 'paid';
                        $update['desc'] = '|' . '客户已补单加钱';
                        break;
                    //Error   加钱失败继续进入队列
                    case 3:
//                        \Utils\MQServer::send('recharge_callback', ['order_number' => $order_number, 'money' => $money]);
                        break;
                    //Error   加钱失败不再进入队列
                    case 4:
                        $update['status'] = 'failed';
                        $update['desc'] = '|' . $res['msg'];
                        break;
                }
                if ($update && count($update)>0) {
                    // 锁定
                    try {
                        $update['desc'] = $update['desc'] ?? '';
                        $this->db->getConnection()->beginTransaction();
                        \DB::table('success_tmp')->where('order_number', '=', $order_number)->delete();  //该订单成功到达此通知客户，应删掉
                        $order_id = \DB::table('order')->where('order_number', '=', $order_number)->value('id');
                        \DB::table('order')->where('id', $order_id)->lockForUpdate()->first();
                        \DB::table('order')->where('order_number', $order_number)->where('status', '=', 'pending')
                            ->update(['status' => $update['status'], 'desc' => \DB::raw("concat(`desc`,'{$update['desc']}')")]);
                        $this->db->getConnection()->commit();
                    } catch (\Exception $e) {
                        $this->db->getConnection()->rollback();
                    }
                    self::logger($this, $update, 'order');
                }
            }
        }
    }
}