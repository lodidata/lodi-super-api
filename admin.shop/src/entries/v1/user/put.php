<?php
use Lib\Validate\BaseValidate;
use Logic\Admin\BaseController;

/**
 * 修改用户密码
 */
return new class extends BaseController{
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id = null){
        $this->checkID($id);
        (new BaseValidate([
                'password'=>'require|length:6,20',
                'repassword'=>'require|confirm:password'
            ], []
        ))->paramsCheck('',$this->request,$this->response);

        $params = $this->request->getParams();
        $password = trim($params['password']);
        if (preg_match("/[ '.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/", $password)) {
            return createRsponse($this->response, 400, 10, '密码不能含有标点符号及特殊字符');
        }

        $user = new \Logic\Shop\User($this->ci);
        $salt = $user->getGenerateChar(6);
        $password = $user->getPasword($password, $salt);

        $res = DB::table('user')->where('id', $id)->update([
            'salt'      => $salt,
            'password'  => $password,
        ]);
        if(!$res)
            return $this->lang->set(-2);
        return $this->lang->set(0);
    }
};