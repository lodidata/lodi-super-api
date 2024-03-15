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
//    protected $beforeActionList = [
//        'verifyToken', 'authorize',
//    ];

    public function run()
    {

        $params = $this->request->getParams();
        $sql = \DB::table('customer');

        $sql = isset($params['id']) && !empty($params['id']) ? $sql->where('id', $params['id']) : $sql;

        $total = $sql->count();
        $msg = $sql->forPage($params['page'], $params['page_size'])->get()->toArray();
        return $this->lang->set(0, [], $msg, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total]);
    } 

};