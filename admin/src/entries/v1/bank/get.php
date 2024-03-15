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
 * 银行卡信息查询
 */
return new class extends BaseController
{
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {

        $params = $this->request->getParams();
        $sql = DB::table('bank')
            ->select(['id','code','status','name','shortname','h5_logo','created','updated']);

        $sql = isset($params['name']) && !empty($params['name']) ? $sql->where('name', $params['name']) : $sql;

        $total = $sql->count();
        $msg = $sql->forPage($params['page'], $params['page_size'])->orderBy('id','desc')->get()->toArray();
        return $this->lang->set(0, [], $msg, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total]);
    } 

};