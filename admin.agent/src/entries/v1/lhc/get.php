<?php
/**
 * Created by PhpStorm.
 * User: Nico
 * Date: 2018/7/27
 * Time: 15:35
 */


use Logic\Admin\BaseController;

/*
 * 客户（客服）信息查询
 *
 * */
return new class extends BaseController
{

    const TITLE = 'GET 六合彩列表当前彩期';
    const DESCRIPTION = '六合彩列表当前彩期';
    const HINT = '';
    const QUERY = [
        'page' => '页码',
        'page_size' => '每页大小',
        'start_time' => 'string(optional)  #开售时间',
        'end_time'=> 'string(optional)  #封盘时间',
        'open_time'=>'string(optional)  #开奖时间',
        'lottery_number'=>'string(optional)  #当前期号',
        'status' => 'string (optional) #状态 enable:启用,disable:停用 '
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
            'id' => 'int #id',
            //'name' => 'string  #名字',
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];


    public function run()
    {

        $data = DB::connection('common')->table('lottery_info')->where('lottery_type',52)->where('period_code', '')->orderBy('lottery_number','desc')->first();
        $result = [];
        $result[0]['id'] = $data->id;
        $result[0]['lottery_number'] = $data->lottery_number;
        $result[0]['start_time'] = date('Y-m-d H:i:s', $data->start_time);
        $result[0]['open_time'] = date('Y-m-d', $data->end_time).' 21:33:00';
        $result[0]['end_time'] = date('Y-m-d H:i:s', $data->end_time);



        $attributes['total'] = 1;
        $attributes['number'] = 1;
        $attributes['size'] =20;
        if (!$attributes['total'])
            return [];

        return $this->lang->set(0, [], $result, $attributes);
    }

};