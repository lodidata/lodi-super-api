<?php
return new class extends Logic\Admin\BaseController {
    const TITLE = '回调IP白名单';
    const QUERY = [
        'ids' => 'string(optional) #相应客户的ID  以逗号隔开',
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
        ],
    ];
    public function run() {
        $customer_ids = $this->request->getParam('ids');
        try {
            if ($customer_ids) {
                $tmp = explode(',', $customer_ids);
                \DB::connection('pay')->table('callback_ip_switch')->update(['switch' => 0]);
                foreach ($tmp as $v) {
                    $id = \DB::connection('pay')->table('callback_ip_switch')
                        ->where('customer_id', $v)->where('channel_id', 0)->value('id');
                    if ($id) {
                        \DB::connection('pay')->table('callback_ip_switch')
                            ->where('id', $id)->update(['switch' => 1]);
                    } else {
                        \DB::connection('pay')->table('callback_ip_switch')
                            ->where('id', $id)->insertGetId(['customer_id' => $v, 'switch' => 1, 'channel_id' => 0]);
                    }
                }
            }else{
                \DB::connection('pay')->table('callback_ip_switch')->update(['switch' => 0]);
            }
            return $this->lang->set(0);
        }catch (\Exception $e) {
            return $this->lang->set(-2);
        }
    }
};