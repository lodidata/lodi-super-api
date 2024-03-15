<?php
namespace Model;

class CommonGameAccount extends \Illuminate\Database\Eloquent\Model {

    protected $connection = 'common';

    protected $table = 'game_account';

    public $timestamps = false;

    protected $fillable = [
                        'id',
                        'hall_id',
                        'hall_account',
                        'partner_id',
                        'partner_name',
                        'admin_account',
                        'admin_password',
                        'api_account',
                        'api_password',
                        'api_key',
                        'status',
                        'comment',
                        'created',
                        'updated',
                        ];
                          
    public static function boot() {

        parent::boot();

        static::creating(function ($obj) {
            $obj->created = time();
            $obj->updated = time();
        });

        // static::updating(function ($obj) {
        //     $obj->update = time();
        // });
    }  
}


