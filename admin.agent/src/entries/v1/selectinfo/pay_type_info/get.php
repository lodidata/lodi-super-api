<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/7/4
 * Time: 14:02
 */

/**
 * 支付类型下拉框
 */
return new class extends \Logic\Admin\BaseController
{
    const TITLE = 'GET 支付类型下拉框';
    const DESCRIPTION = '支付类型下拉框';
    const HINT = '';
    const QUERY = [
        'channel_id'=>'支付渠道id（channel_id）'
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];
    public function run(){
        $types=[
            'wx'=>'微信',
            'alipay'=>'支付宝',
            'unionpay'=>'银联',
            'qq'=>'QQ',
            'jd'=>'京东',
        ];
        $ways=[
            'js'=>'公共号',
            'quick'=>'快捷',
            'h5'=>'H5',
            'code'=>'扫码',
        ];
        $channel_id=$this->request->getParam('channel_id');
        $this->checkID($channel_id);
        $type =DB::connection('pay')
            ->table('passageway_config')
            ->where('channel_id',$channel_id)
            ->get()->toArray();
        $data = [];
        foreach ($type as $key=>$item) {
            $item = (array)$item;
            $tmp['id'] = $item['id'];
            $tmp['scene'] = $item['scene'];
            $tmp['name'] = $types[$item['scene']].'--'.$ways[$item['show_type']];
            $data[] = $tmp;
        }
        return $data;
    }

};