<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/7/3
 * Time: 16:35
 */

/**
 * 修改支付类型信息信息
 */
return new class extends \Logic\Admin\BaseController
{
    const TITLE = 'PUT 修改支付类型信息';
    const DESCRIPTION = '修改支付类型信息';
    const HINT = '';
    const QUERY = [
    ];
    const TYPE = 'text/json';
    const PARAMs = [
        'passageway_config_id' => 'integer(required)#支付渠道类型支付渠道类型',
        'customer_id' => 'integer(required)#支付渠道类型客户ID',
        'pay_config_id' => 'integer(required)#支付渠道类型支付渠道id',
        'payurl'=>"string(required)#第三方地址",
        'isStatus' => 'string(optional )#支付渠道类型是否同步（同步：isTrue;不同步：isNo）'
    ];
    const SCHEMAs = [
        200 => [
        ]
    ];

    public function run($id)
    {
        $this->checkID($id);
        (new \Lib\Validate\Admin\PayValidate())->paramsCheck('put_type', $this->request, $this->response);
        $params = $this->request->getParams();

        /*  name值的拼接  start  */
        $arrScene = \Logic\Recharge\Recharge::$sceneType['scene'];
        $arrType = \Logic\Recharge\Recharge::$sceneType['type'];
        $passageway = (array)DB::connection('pay')
            ->table('passageway_config')->find($params['passageway_config_id']);
        if($passageway){
            //判断是否已经存在
//            $p = DB::connection('pay')
//                ->table('passageway')
//                ->where('id','!=',$id)
//                ->where('pay_config_id',$params['pay_config_id'])
//                ->where('bank_data',$passageway['bank_data'])
//                ->where('return_type',$passageway['return_type']) //不同模式唯一，如h5 扫码 sdk
//                ->where('scene',$passageway['scene'])
////                ->where('show_type',$passageway['show_type'])
//                ->count();
//            if($p <= 0){
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
                $data['payurl'] = $params['payurl'];
                $data['bank_data'] = $passageway['bank_data'];
                $data['link_data'] = $passageway['link_data'];
                $data['return_type'] = $passageway['return_type'];
                $data['show_type'] = $passageway['show_type'];
                $re = DB::connection('pay')
                    ->table('passageway')
                    ->where('id',$id)
                    ->update($data);
                //同步更新该渠道的所有支付请求地址，包括模板
                if($params['isStatus'] == 'isTrue'){
                    $conf_ids = DB::connection('pay')
                        ->table('pay_config')
                        ->where('channel_id',$passageway['channel_id'])->pluck('id');
                    DB::connection('pay')
                        ->table('passageway')
                        ->whereIn('pay_config_id',$conf_ids)
                        ->where('scene',$passageway['scene'])
                        ->where('show_type',$passageway['show_type'])
                        ->update(['payurl'=>$params['payurl']]);
                    DB::connection('pay')
                        ->table('passageway_config')
                        ->where('id',$params['passageway_config_id'])
                        ->update(['payurl'=>$params['payurl']]);
                }
                if($re)
                    return $this->lang->set(0);
                return $this->lang->set(-2);
//            }
//            return $this->lang->set(14);
        }
        return $this->lang->set(13);
    }
};