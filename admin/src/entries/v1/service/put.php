<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/7/18
 * Time: 18:57
 */

use Logic\Admin\BaseController;


/**
 * 编辑客户（厅主）
 */
return new class extends BaseController
{
    const TITLE = '编辑客户（厅主）';
    const DESCRIPTION = '编辑客户（厅主）';
    const HINT = '';
    const QUERY = [

    ];
    const TYPE = 'text/json';
    const PARAMs = [
        'name' => 'string(required)#用户名',
        'password' => 'string(required)#密码',
        'password_confirm' => 'string(required)#确认密码'
    ];
    const SCHEMAs = [
        200 => [
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id)
    {

        (new \Lib\Validate\Admin\ServiceSet())->paramsCheck('update', $this->request, $this->response);
        $params = $this->request->getParams();
        $httpStr = substr($params['link'],0,5); //截取前面七位字符用来判断是否为http://字符
        if ($httpStr!=='https' && $httpStr !=="http:") $params['link'] = 'http://'.$params['link'];
        $serviceModel = new \Model\Service\ServiceSet();
        $res = $serviceModel::where('id',$id)->update($params);
        if (!$res) {
            return $this->lang->set(0);
        } else {
            return $this->lang->set(0);
        }
    }
};