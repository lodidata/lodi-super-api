<?php
/**
 * Created by PhpStorm.
 * User: Nico
 * Date: 2018/6/29
 * Time: 11:42
 */

use Logic\Admin\BaseController;
use Model\Service\ServiceSet;

/*
 * 客户（客服）信息查询
 *
 * */
return new class extends BaseController
{

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
        200 => [
            'id' => 'int #id',
            //'name' => 'string  #名字',
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];


    public function run()
    {

        (new \Lib\Validate\Admin\ServiceSet())->paramsCheck('get',$this->request,$this->response);
        $params = $this->request->getParams();

        $serviceModel = new ServiceSet();

        $query = $serviceModel::select('id','name','access_way','link');

        $query = isset($params['name']) && !empty($params['name']) ? $query->where('name','like', '%' . $params['name'] . '%') : $query ;

        $attributes['total'] = $query->count();
        $attributes['number'] = $params['page'];
        $attributes['size'] = $params['page_size'];
        if(!$attributes['total'])
            return [];

        $result = $query->orderBy('created_at','desc')->forpage($params['page'],$params['page_size'])->get()->toArray();
        return $this->lang->set(0,[],$result,$attributes);
    }

};