<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/8/1
 * Time: 17:54
 */

namespace Model\Service;


use Illuminate\Database\Eloquent\Model;

class ServiceConfig extends Model
{
    protected $table = 'service_config'; //表名
    protected $primaryKey = 'id'; //主键

}