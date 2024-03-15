<?php
/**
 * 第三方支付配置编辑
 * @author Taylor 2019-03-12
 */
use Lib\Validate\BaseValidate;
use Logic\Admin\BaseController;

return new class extends BaseController
{
    const TITLE = '第三方支付配置编辑';
    const PARAMs = [
        'channel_id' => 'string(required)#通道名称id',
        'scene' => 'string(required)#场景，wx,alipay,unionpay,qq,jd,ysf',
        'action' => 'string(required)#该通道第三方方法',
        'payurl' => 'string(required)#支付调用地址',
        'bank_data' => 'string(required)#银行参数',
        'link_data' => 'string(required)#链接参数',
        'return_type' => 'string(required)#跳转方式，code,jump,url,sdk',
        'show_type' => 'string(required)#显示方式，js,quick,h5,code,sdk',
        'field' => 'string(required)#备用字段',
        'sort' => 'string(required)#排序'
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
            'channel_id' => 'require',
            'scene' => 'require|in:wx,alipay,unionpay,qq,jd,ysf',
            'payurl' => 'require',
            'bank_data' => 'require',
            'return_type' => 'require|in:code,jump,url,sdk',
            'show_type' => 'require|in:js,quick,h5,code,sdk',
        ]))->paramsCheck('', $this->request, $this->response);

        $params = $this->request->getParams();
        $pass_data = DB::connection('pay')->table('passageway_config')
            ->where('id', '<>', $id)->where('channel_id', $params['channel_id'])
            ->where('bank_data',$params['bank_data'])
            ->where('return_type',$params['return_type']) //不同模式唯一，如h5 扫码 sdk
            ->where('scene', $params['scene'])
//            ->where('show_type', $params['show_type'])
            ->first();
        if($pass_data){
            return $this->lang->set(886, ["{$pass_data->channel_id}已存在通道 {$params['scene']} bank_data:{$params['bank_data']} type:{$params['return_type']}"]);
        }
        $data['channel_id'] = $params['channel_id'];//通道名称
        $data['scene'] = $params['scene'];//场景，wx,alipay,unionpay,qq,jd,ysf
        $data['action'] = $params['action'];//该通道第三方方法
        $data['payurl'] = $params['payurl'];//支付调用地址
        $data['bank_data'] = $params['bank_data'];//银行参数
        $data['link_data'] = $params['link_data'];//链接参数
        $data['return_type'] = $params['return_type'];//跳转方式，code,jump,url,sdk
        $data['show_type'] = $params['show_type'];//显示方式，js,quick,h5,code
        $data['field'] = $params['field'];//备用字段
        $result = DB::connection('pay')->table('passageway_config')->where('id', $id)->update($data);
        if ($result !== false) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};