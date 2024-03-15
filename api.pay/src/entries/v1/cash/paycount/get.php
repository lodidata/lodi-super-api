<?php

use Utils\Www\Action;

return new class extends Action
{

    const TITLE = '判断是否操作level_online表';
    const DESCRIPTION = '判断是否操作level_online表';
    const HINT = '';
    const QUERY = [
    ];
    const TYPE = 'text/json';

    public function run()
    {

        $id = $this->request->getParam('id');
        $pay_config_id = DB::table('passageway')
            ->where('id', $id)
            ->select('pay_config_id')
            ->get()
            ->first();
        $pay_config_id = (array)$pay_config_id;
        $count = DB::table('passageway')
            ->where('pay_config_id', $pay_config_id)
            ->where('status', 'enabled')
            ->count();

        $result = [];
        if ($count > 0) {
            $result['name'] = '';
            $result['res'] = false;
        } else {
            $name =  (array)DB::table('pay_config')
                ->leftJoin('pay_channel', 'channel_id', '=', 'pay_channel.id')
                ->where('pay_config.id', $pay_config_id)
                ->select('pay_channel.name')
                ->get()
                ->first();
            $result['name'] = $name['name'];
            $result['res'] = true;
        }
        return $result;
    }

};