<?php

namespace Model\Admin;

use Illuminate\Database\Eloquent\Model;

class AdminLog extends Model {
    public $timestamps = false;
    protected $table = 'admin_logs';

    public static function getById($id) {
        return self::find($id);
    }

}