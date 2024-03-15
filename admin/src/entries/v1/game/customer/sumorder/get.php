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
    protected $beforeActionList = [
       // 'verifyToken', 'authorize',
    ];

    public function run()
    {
        (new BaseValidate(
            [
                'game_type'=>'require',
                'start_date'=>'require',
//                'end_date'=>'require',
            ]
        ))->paramsCheck('',$this->request,$this->response);
        $params = $this->request->getParams();
        $class = 'Logic\Game\Third\\'.strtoupper($params['game_type']);
        if (!class_exists($class)) {
            return false;
        }
        $class = new $class($this->ci);
        $start_date = $params['start_date'] . ' 00:00:00';
        $end_date = $params['start_date'] . ' 23:59:59';
        $data = $class->querySumOrder($start_date,$end_date);
        $data = empty($data) ? ['bet'=>0,'valid_bet'=>0,'win_loss'=>0] : $data;
        return $data;
    } 

};