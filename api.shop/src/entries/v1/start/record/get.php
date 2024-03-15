<?php
use Utils\Shop\Action;

//购买记录走马灯
return new class extends Action {
    public function run() {
        $goods = DB::table('goods')->where('status', 1)->select(['name'])->get();
        if($goods){
            $goods = $goods->toArray();
            $rand = range(0, 9);
            $phone = [130, 131, 132, 133, 134, 135, 136, 137, 138, 139, 155, 156, 157, 158, 159, 188];
            foreach ($rand as $val){
                $data[$val] = $phone[array_rand($phone)].'****'.rand(1111, 9999).'购买了'.$goods[array_rand($goods)]->name;
            }
            return $this->lang->set(0, [], $data);
        }else{
            return $this->lang->set(0);
        }
    }
};