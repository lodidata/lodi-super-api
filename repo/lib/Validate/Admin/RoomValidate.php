<?php
/**
 * author      : ben <956841620@qq.com>
 * createTime  : 2018/4/09 23:25
 * description :
 */

namespace Lib\Validate\Admin;

use Lib\Validate\BaseValidate;
use Model\Admin\Lottery;
use Model\Admin\Hall;

class RoomValidate extends BaseValidate
{
    // 验证规则
    protected $rule = [

        "lottery_id"=>"require|unique:room_notice,lottery_id|checkLotteryById",
        "hall_id"=>"require|checkHallById",
        "title"=>"require|max:50",
        "content"=>"require|max:1024",
        "sleep_time"=>"require|egt:30",
    ];
    protected $field = [
        'lottery_id'   =>    '彩种',
        'hall_id'   =>    '大厅',
        'title'   =>    '标题',
        'content'   =>    '内容',
        'sleep_time'   =>    '间隔时间',

    ];

    protected $message = [
        'lottery_id.unique'   =>    '该彩种公告已存在',
        'lottery_id.checkLotteryById'   =>    '该彩种不存在',
        'hall_id.checkHallById'   =>    '该彩种大厅不存在',
        'sleep_time.egt'   =>    '间隔时间不能小于30S',
    ];

    protected $scene = [
        'post' => [
            'lottery_id', 'hall_id', 'title', 'content', 'sleep_time',
        ],
        'patch' => [
            "lottery_id"=>"require|unique:room_notice,lottery_id^id|checkLotteryById",
            'hall_id', 'title', 'content', 'sleep_time',
        ],

    ];

    protected function checkLotteryById($value){

        return Lottery::getById($value) ? true : false;
    }

    protected function checkHallById($value){

        $halls = explode(',', $value);

        foreach($halls as $hall_id){
            if(!Hall::getById($hall_id)){
                return false;
            }
        }
        return true;
    }


}