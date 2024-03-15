<?php
use Logic\Admin\BaseController;
use Lib\Validate\BaseValidate;
//use Logic\Admin\Log;

return new class() extends BaseController{
    const QUERY       = [
        'id' => 'int(optional) #修改厅主设置的第三方支付（通过uri传参）'
    ];
    const PARAMs      = [
      //  'pay_id'         => 'int(required) #支付接口ID/支付渠道，见另一接口',
     //   'name'           => 'string(required) #商户名称',
       // 'app_id'         => 'string(required) #商户编号/应用ID，第三方支付商提供的id',
       // 'app_secret'     => 'string(optional) #应用密钥',
       // 'pay_scene'      => 'enum[wx,alipay,unionpay,qq,tz](required) #使用场景，wx 微信, alipay 支付宝, unionpay 银联, qq qq扫描支付, tz 出款下发',
        'levels'         => 'string(optional) #会员等级，多个level id通过逗号分隔',
      //  'terminal'       => 'string(optional) #终端号，由第三方支付商提供',
        'comment'     => 'string #备注',
        'url_notify'     => 'string #累计停用金额',
        'url_return'     => 'string #累计停用金额',
        'money_stop'     => 'int #累计停用金额',
        'money_day_stop' => 'int #日停用金额',
        'sort'           => 'int #排序',
        'status'         => 'int #是否开启，1 是，0 否'
    ];
    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];
    
    public function run($id = ''){
        $this->checkID($id);
        $param = $this->request->getParams();
        $validate = new BaseValidate([
            'min_money' => 'number',
            'max_money' => 'number',
            'money_day_stop' => 'number',
            'money_stop' => 'number',
//            'levels' => 'string',
            'status' => 'require',
        ]);
        $validate->paramsCheck('', $this->request, $this->response);
        if ($param['status']) {
            $status = 'enabled';
        } else {
            $status = 'default';
        }
        $param['status'] = $status;
        $param['id'] = $id;
        $param['active_rule'] = isset($param['active_rule']) && is_array($param['active_rule']) ? json_encode($param['active_rule']) : '';
        /*--------操作日志代码-------*/
        $datas = [];
        $data = Logic\Shop\Recharge::requestPaySit('getPayList');
        foreach ($data['data'] as $key=>$datum) {
            if($datum['id']==$id){
                $datas = $data['data'][$key];
            }
        }
        if($datas) {
            Logic\Shop\Recharge::requestPaySit('updatePayMsg', $param);
        }
        return $this->lang->set(0);
    }
};
