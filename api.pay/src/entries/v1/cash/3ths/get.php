<?php

use Utils\Www\Action;

/**
 * 第三方支付详情
 */
return new class extends Action
{

    const TITLE = '第三方支付详情';
    const DESCRIPTION = '第三方支付详情列表';
    const HINT = '';
    const QUERY = [
    ];
    const TYPE = 'text/json';

    public function run()
    {
        $id = $_GET['id'];
        $info = $_GET['info'];
        $reData = $this->getPayArray($id, $info);
        return $reData;
    }

    //查询数据
    public function getPayArray($id, $info = array())
    {

        $data = DB::table('passageway')
            ->where('passageway.id', '=', $id)
            ->get()
            ->first();

        $data=(array)$data;
        $dd = array_slice($data, 0, 8);
        $dd['levels'] = $info[0]['level'];
        $dd2 = array_slice($data, 8);
        $data = array_merge($dd, $dd2);

        if($data['status']=='default'){
            $data['status']=0;
        }else{
            $data['status']=1;
        }

        $data['id']='8888'.$data['id'];
        return $data;

    }

};