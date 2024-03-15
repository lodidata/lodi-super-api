<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 11:42
 */

use Lib\Validate\Admin\BankValidate;
use Lib\Validate\Admin\CustomerValidate;
use Logic\Admin\BaseController;

/**
 * 修改银行信息
 */
return new class extends BaseController
{
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id = null)
    {
        $this->checkID($id);
        (new BankValidate())->paramsCheck('put', $this->request, $this->response);

        $arr = [
            'name', 'h5_logo', 'status', 'code', 'shortname'
        ];

        //判断是否有相同数据
        $repeat = DB::table('bank')->where('id','<>',$id);
        foreach ($arr as $item) {
            $data[$item] = $this->request->getParam($item);
            if(in_array($item,[ 'name', 'code'])){
                $repeat->where($item, $this->request->getParam($item));
            }
        }
        $repeat->count();
        if ($repeat->count() > 0) {
            return $this->lang->set(8);
        } else {
        $result = DB::table('bank')->where('id', '=', $id)->update($data);
        if ($result !== false) {
            $res = \Logic\Recharge\Recharge::requestPaySit("banksUpdate",'all', ['data' => $data, 'id' => $id]);
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
        }
    }

};