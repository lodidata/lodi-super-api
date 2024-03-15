<?php
use Utils\Shop\Action;

return new class extends Action {
    const TITLE = "POST 商品购买";
    const TYPE = "text/json";

    public function run() {
        $verify = $this->auth->verfiyToken();
        if (!$verify->allowNext()) {
            return $verify;
        }
        (new \Lib\Validate\BaseValidate([
            'goods_id' => 'require|integer',
            'account' => 'require',
            're_account' => 'require|confirm:account',
        ]))->paramsCheck('', $this->request, $this->response);
        return (new \Logic\Shop\User($this->ci))->buy($this->auth->getUserId(), $this->request->getParams());
    }
};