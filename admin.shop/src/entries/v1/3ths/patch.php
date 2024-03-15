<?php
use Logic\Admin\BaseController;
//use Logic\Admin\Log;

return new class() extends BaseController{
    const TITLE = '更改第三方支付';
    const DESCRIPTION = '目前只支持更改状态';
    const QUERY = [
        'id' => 'int #第三方支付id'
    ];
    const PARAMs = [
        'status' => 'int(required) #状态, 1:启用， 0:停用',
    ];
    //前置方法
    protected $beforeActionList = [
        'verifyToken', 'authorize'
    ];

    public function run($id = null){
        $this->checkID($id);
        $status = $this->request->getParam('status');
        $rs = Logic\Shop\Recharge::requestPaySit('updateStatus', ['status' => $status], [$id]);

        /*================================日志操作代码=================================*/
//        if ($status==0) {
//            $new_sta="停用";
//            $old_sta="启用";
//        }else{
//            $old_sta="停用";
//            $new_sta="启用";
//        }
//        $data = Logic\Recharge\Recharge::requestPaySit('getPayList',  ['id'=>$id]);
//        foreach ($data['data'] as $key=>$datum) {
//            if($datum['id']==$id){
//                $datas=$data['data'][$key];
//            }
//        }
//        if(isset($datas)) {
//            (new Log($this->ci))->create(null, null, Log::MODULE_CASH, '第三方支付', '第三方支付', $new_sta, 1, "支付名称:{$datas['name']}/渠道名称:{$datas['channel']}/支付类型:{$datas['type']}//状态[$old_sta]更改为[$new_sta]");
//        }
        return $this->lang->set(0);
    }
};
