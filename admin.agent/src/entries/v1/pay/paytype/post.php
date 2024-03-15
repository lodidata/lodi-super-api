<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/7/3
 * Time: 16:35
 */

use Illuminate\Support\Facades\Schema;

/**
 * 新增支付类型
 */
return new class extends \Logic\Admin\BaseController
{
    const TITLE = 'POST 新增支付类型';
    const DESCRIPTION = '新增支付类型';
    const HINT = '';
    const QUERY = [

    ];
    const TYPE = 'text/json';
    const PARAMs = [
        'customer_id' => 'integer(required)#客户ID',
        'pay_config_id' => 'integer(required)#支付id',
        'passageway_config_id' => 'integer(required)#通模板配置ID',
    ];
    const SCHEMAs = [
        200 => [
        ]
    ];

    public function run()
    {
        $arrScene = [
            'wx' => '微信',
            'alipay' => '支付宝',
            'unionpay' => '银联',
            'qq' => 'QQ',
            'jd' => '京东',
        ];
        $arrType = [
            'h5' => 'WAP',
            'code' => '扫码',
            'quick' => '快捷',
            'js' => '公共号',
        ];
        (new \Lib\Validate\Admin\PayValidate())->paramsCheck('post_type',$this->request,$this->response);
        $params = $this->request->getParams();
        $passageway = (array)DB::connection('pay')
            ->table('passageway_config')->find($params['passageway_config_id']);
        if($passageway){
            //判断是否已经存在
            $p = DB::connection('pay')
                ->table('passageway')
                ->where('pay_config_id',$params['pay_config_id'])
                ->where('scene',$passageway['scene'])
                ->where('show_type',$passageway['show_type'])
                ->count();
            if($p <= 0){
                $cus_name = DB::connection('pay')
                    ->table('customer')->where('id',$params['customer_id'])->value('name');
                $channel_name = DB::connection('pay')
                    ->table('pay_channel')->where('id',$passageway['channel_id'])->value('name');
                //整理数据插入通道表
                $data['pay_config_id'] = $params['pay_config_id'];
                $data['customer_id'] = $params['customer_id'];
                $data['scene'] = $passageway['scene'];
                $data['action'] = $passageway['action'];
                $data['name'] = $channel_name.$arrScene[$passageway['scene']] . $arrType[$passageway['show_type']].'('.$cus_name.')';
                $data['status'] = 'disabled';
                $data['payurl'] = $passageway['payurl'];
                $data['bank_data'] = $passageway['bank_data'];
                $data['link_data'] = $passageway['link_data'];
                $data['return_type'] = $passageway['return_type'];
                $data['show_type'] = $passageway['show_type'];
                $re = DB::connection('pay')
                    ->table('passageway')
                    ->insertGetId($data);
                if($re)
                    return $this->lang->set(0);
                return $this->lang->set(-2);
            }
            return $this->lang->set(14);
        }
        return $this->lang->set(13);

    }
};