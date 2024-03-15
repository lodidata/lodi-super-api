<?php
/**
 * Created by PhpStorm.
 * User: tyleryang
 * Date: 2018/3/22
 * Time: 12:22
 */
namespace Model;
// use Illuminate\Support\Facades\DB;

class Customer extends \Illuminate\Database\Eloquent\Model {
    
    const DEFAULT_CURRENCY = 'CNY';

    protected $table = 'customer';

    public $timestamps = false;

    protected $fillable = [
                           'id',
                           'name',
                           'customer',
                           'website',
                        ];

    public static function boot() {

//        parent::boot();
//
//        static::creating(function ($obj) {
//            $obj->last_ip = \Utils\Client::getIp();
//            $obj->currency = self::DEFAULT_CURRENCY;
//            $obj->comment = $obj->comment.'的主钱包';
//            $obj->name = '主钱包';
//            $obj->uuid = \DB::raw('uuid()');
//            $obj->balance = 0;
//            $obj->balance_before = 0;
//        });
    }
}