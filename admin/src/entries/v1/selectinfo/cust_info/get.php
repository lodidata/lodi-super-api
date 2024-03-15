<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 11:42
 */

use Logic\Admin\BaseController;

/**
 * 客户查询
 */
return new class extends BaseController
{
//    const STATE = \API::REVIEW;
    const TITLE = 'GET 客户查询';
    const DESCRIPTION = '客户查询';
    const HINT = '';
    const QUERY = [
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
            'id' => 'int(required) #id',
            'name' => 'string  #名字',
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        $msg =  DB::table('customer AS a')
            //->leftJoin('callback_ip_switch AS b','a.id','=','b.customer_id')
            ->select('a.id', 'a.name')
            ->get()->toArray();
        foreach ($msg as &$v){
            $v->callback_switch = 1;
        }
        return $this->lang->set(0, [], $msg);
    }

};