<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 11:42
 */

use Lib\Validate\Admin\CustomerValidate;
use Logic\Admin\BaseController;

/**
 * 客户信息查询
 */
return new class extends BaseController
{
//    const STATE = \API::REVIEW;
    const TITLE = 'GET 客户信息查询';
    const DESCRIPTION = '客户信息查询';
    const HINT = '';
    const QUERY = [
        'page' => '页码',
        'page_size' => '每页大小',
        'name' => 'string(optional)  #名字',
        'status' => 'string (optional) #状态 enable:启用,disable:停用 '
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        [
            'id' => 'int #id',
            'name' => 'string  #名字',
            'admin_notify' => 'string(required)#admin回调地址',
            'www_notify' => 'string(required)#www回调地址',
            'status' => 'string  #状态 enable:启用,disable:停用 '
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {

        $params = $this->request->getParams();
        (new CustomerValidate())->paramsCheck('get', $this->request, $this->response);

        $sql = DB::table('customer_notify as notify')
            ->join('customer', 'customer_id', '=', 'customer.id')
            ->select('notify.id', 'customer_id', 'admin_notify', 'www_notify', 'status', 'sort', 'notify.updated', 'notify.created', 'customer.name');

        $sql = isset($params['name']) && !empty($name) ? $sql->where('name', $name) : $sql;
        $sql = isset($params['status']) && !empty($name) ? $sql->where('status', $name) : $sql;

        $total = $sql->count();
        $msg = $sql->orderBy('notify.created', 'desc')->forPage($params['page'], $params['page_size'])->get()->toArray();
        return $this->lang->set(0, [], $msg, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total]);
    } 

};