<?php
/**
 * Created by PhpStorm.
 * User: Nico
 * Date: 2018/7/27
 * Time: 16:23
 */

use Logic\Admin\BaseController;

/**
 * 修改封盘时间
 */
return new class extends BaseController
{
    const TITLE = '六合彩当前彩期封盘时间修改';
    const DESCRIPTION = '六合彩当前彩期封盘时间修改';
    const HINT = '';
    const QUERY = [

    ];
    const TYPE = 'text/json';
    const PARAMs = [
        'id' => 'string(required)彩期记录id',
        'end_time' => 'string(required)#封盘时间',
    ];
    const SCHEMAs = [
        200 => [
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        $params = $this->request->getParams();
        $endTime = strtotime($params['end_time'] . ' 21:30:00');

        if (!isset($params['id'])) return $this->lang->set(10);

        /* 非法时间值. 设置的时间值不能小于当前时间值*/
        if ($endTime < time()) {
            return $this->lang->set(886, ['设置的封盘时间值不能小于当前时间值']);
        }

        $id = $params['id'];

        $lotteryInfo = DB::connection('common')->table('lottery_info')->where('id', $id)->select(['lottery_type', 'lottery_number', 'end_time'])->first();

        if (!$lotteryInfo) {
            return $this->lang->set(10);
        }

        /* 非法时间值. 本次设置的时间值不能小于上次设置的时间值*/
        if ($endTime < $lotteryInfo->end_time) {
            return $this->lang->set(10);
        }


        DB::connection('common')->table('lottery_info')->where('id', $id)->update(['end_time' => $endTime]);

        return $this->lang->set(0);


    }
};