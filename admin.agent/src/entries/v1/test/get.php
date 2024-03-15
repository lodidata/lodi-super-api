<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/7/2
 * Time: 10:56
 */

use Logic\Admin\BaseController;
return new class extends BaseController {

    //前置方法 检查权限等
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run (){


        $params = $this->request->getParams();


        echo 777;
        print_r($this->request->getParams());exit;

    }

};