<?php
/**
 * 支付通道编辑
 * @author Taylor 2019-03-11
 */
use Lib\Validate\BaseValidate;
use Logic\Admin\BaseController;

return new class extends BaseController
{
    const TITLE = '支付通道编辑';
    const PARAMs = [
        'name' => 'string(required)#通道名称',
        'code' => 'string(required)#通道代码',
        'rule' => 'string(required)#支付规则 0：不限，1：整数，2：小数，3:固定金额，4：整百',
        'moneys' => 'string(required)#金额取值范围(某些第三方固定值，用,隔开)',
        'return' => 'string(required)#给第三方返回标识',
        'param' => 'string(required)#接收第三方值的方式（以便解析）：other, get, xml, json',
        'order_number' => 'string(required)#对应的第三方订单下标',
        'mode' => 'string(required)#接收的参数模式 current普通模式，encrypt加密模式',
        'desc' => 'string(required)#描述'
    ];
    const SCHEMAs = [
        200 => []
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id)
    {
        $this->verifyToken();
        if(empty($id)){
            return $this->lang->set(886, ['id不能为空']);
        }
        (new BaseValidate([
            'name' => 'require',
            'code' => 'require',
            'rule' => 'require|in:0,1,2,3,4',
            'return' => 'require',
            'param' => 'require|in:json,other,get,xml',
            'order_number' => 'require',
            'mode' => 'require|in:current,encrypt',
            'desc' => 'require',
        ]))->paramsCheck('', $this->request, $this->response);

        $params = $this->request->getParams();
//        $data['admin_uid'] = $this->playLoad['uid'];
//        $data['admin_name'] = $this->playLoad['nick'];
        $data['name'] = $params['name'];
        $data['code'] = strtoupper($params['code']);
//        $pay_data = DB::connection('pay')->table('pay_channel')->where('id', '<>', $id)->where('code', $data['code'])->first();
//        if($pay_data){
//            return $this->lang->set(886, ["{$pay_data->name}已存在{$data['code']}"]);
//        }
        $data['rule'] = $params['rule'];//支付规则 0：不限，1：整数，2：小数，3:固定金额，4：整百
        $data['moneys'] = $params['moneys'];//金额取值范围(某些第三方固定值，用,隔开)
        $data['return'] = $params['return'];//给第三方返回标识
        $data['param'] = $params['param'];//接收第三方值的方式（以便解析）：other, get, xml, json
        $data['order_number'] = $params['order_number'];//对应的第三方订单下标
        $data['mode'] = $params['mode'];//接收的参数模式 current普通模式，encrypt加密模式
        $data['desc'] = $params['desc'];//描述
        $status = DB::connection('pay')->table('pay_channel')->where('id', $id)->update($data);
        if($status !== false){
            return $this->lang->set(0);
        }else{
            return $this->lang->set(-2);
        }
    }
};