<?php
use Utils\Shop\Action;

/**
 * 商品信息
 */
return new class extends Action{
    public function run($id=0){
        if(empty($id)){
            return $this->lang->set(886, ['id不能为空']);
        }
        $data = DB::table('goods')->where('status', 1)->where('id', $id)
            ->select(['id','detail'])->first();
        if(empty($data)){
            return $this->lang->set(886, ['数据不存在']);
        }
        $data = (array)$data;
        if(!empty($data['detail'])){
            $data['detail'] = json_decode($data['detail'], true);
        }
        return $this->lang->set(0, [], $data);
    }
};