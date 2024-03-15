<?php
/**
 * Created by PhpStorm.
 * User: tyleryang
 * Date: 2018/3/22
 * Time: 16:30
 */

namespace Model;

class CommonOrder3th extends \Illuminate\Database\Eloquent\Model {

    protected $table = 'order_3th';

    public $timestamps = false;
    
    protected $connection = 'common';

    protected $fillable = [
                            'id',
                            'user_id',
                            'user_name',
                            'find',
                            'order_type',
                            'type_name',
                            'type_id',
                            'game_id',
                            'game_name',
                            'bet_type',
                            'bet_content',
                            'date',
                            'order_number',
                            'money',
                            'valid_money',
                            'prize',
                            'win_loss',
                            'send_date',
                            'result',
                            'tableId',
                            'origin',
                            'extra',
                            '3th_order_number',
                            'tid',
                            'status',
                            'rake_back',
                    ];
}

