<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 11:42
 */

use Logic\Admin\BaseController;
use Lib\Validate\BaseValidate;
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
        (new BaseValidate(
            [
                'user_prefix'=>'require',
                'game_type'=>'require',
                'start_date'=>'require',
                'end_date'=>'require',
            ]
        ))->paramsCheck('',$this->request,$this->response);
        $params = $this->request->getParams();
        $class = 'Logic\Game\Third\\'.$params['game_type'];
        if (!class_exists($class)) {
            return false;
        }
        $class = new $class($this->ci);
        $end_date = $params['end_date'] . ' 23:59:59';
        $data = $class->queryHotOrder($params['user_prefix'],$params['start_date'],$end_date,$class::$queryOrderParams);
        return $data;
    } 

};