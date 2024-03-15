<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/7/6
 * Time: 10:55
 */

use Logic\Admin\BaseController;
use Lib\Validate\Admin\AdminValidate;
use Model\Admin\Admin;
return new class extends Logic\Admin\BaseController{

    const TITLE = '新建管理员';
    const DESCRIPTION = '新建管理员';
    const HINT = '';
    const QUERY = [
        'name' => 'string(optional) #用户名',
        'status' => 'integer(optional) #状态 0,停用，1，启用',
    ];
    const TYPE = 'text/json';
    const PARAMs = [

    ];
    const SCHEMAs = [
        200 => [
            'name' => 'string#用户名',
            'status' => 'integer#状态',
            'created_at' => 'string#创建时间'
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run(){

        (new AdminValidate())->paramsCheck('get',$this->request,$this->response);
        $params = $this->request->getParams();

        $adminModel = new Admin();

        $query = $adminModel::selectRaw('id,name,status,created_at')->where('id','<>',1);

        $query = isset($params['name']) && !empty($params['name']) ? $query->where('name',$params['name']) : $query ;
        $query = isset($params['status']) && is_numeric($params['name']) ? $query->where('status',$params['status']) : $query ;

        $attributes['total'] = $query->count();
        $attributes['number'] = $params['page'];
        $attributes['size'] = $params['page_size'];
        if(!$attributes['total'])
            return [];

        $result = $query->orderBy('created_at','desc')->forpage($params['page'],$params['page_size'])->get()->toArray();
        return $this->lang->set(0,[],$result,$attributes);
    }
};