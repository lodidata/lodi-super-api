<?php
/**
 * Created by PhpStorm.
 * User: tyleryang
 * Date: 2018/3/22
 * Time: 16:30
 */

namespace Model;

class CommonLotteryInfo extends \Illuminate\Database\Eloquent\Model {

    protected $table = 'lottery_info';

    public $timestamps = false;
    
    protected $connection = 'common';

    protected $fillable = ['lottery_number',
                            'lottery_name',
                            'pid',
                            'lottery_type',
                            'start_time',
                            'end_time',
                            'catch_time',
                            'official_time',
                            'period_code',
                            'period_result',
                            'n1',
                            'n2',
                            'n3',
                            'n4',
                            'n5',
                            'n6',
                            'n7',
                            'n8',
                            'n9',
                            'n10',
                            'sell_status',
                            'open_status',
                            'state',
                        ];

}
