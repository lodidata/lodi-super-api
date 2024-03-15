<?php
use Utils\Work\Action;
use Lib\Validate\BaseValidate;

/**
 * 提交微信公众号
 */
return new class extends Action{

    public function run($id){
        if(empty($id)){
            return $this->lang->set(10);
        }
        $validate = new BaseValidate([
            'account_name'   => 'require',
            'app_id'    => 'require',
            'app_secret' => 'require',
        ], [
            'account_name'    => '公众号名称不能为空',
            'app_id'    => 'APPID不能为空',
            'app_secret' => 'APPSECRET不能为空',
        ]);
        $validate->paramsCheck('', $this->request, $this->response);

        $data = $this->request->getParams();
        $w_data = \DB::table('wechat')->where('id', '!=', $id)->where('app_id', $data['app_id'])->first();
        if(!empty($w_data)){
            return $this->lang->set(886, ['app_id已存在']);
        }

        \DB::table('wechat')->where('id', '=', $id)->update($data);
        $this->lang->set(0);
    }

};