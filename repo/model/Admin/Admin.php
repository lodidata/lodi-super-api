<?php
/**
 * Created by PhpStorm.
 * User: tyleryang
 * Date: 2018/3/22
 * Time: 12:22
 */

namespace Model\Admin;

use Illuminate\Database\Eloquent\SoftDeletes;

class Admin extends \Illuminate\Database\Eloquent\Model {
//    use SoftDeletes;

    protected $table = 'admin'; //表名
    protected $primaryKey = 'id'; //主键

    /**
     * 避免转换时间戳为时间字符串
     *
     * @param \DateTime|int $value
     *
     * @return false|int
     * @author dividez
     */
    public function fromDateTime($value)
    {
        return strtotime(parent::fromDateTime($value));
    }

    /**
     * 获取当前时间
     *
     * @return int
     */
    public function freshTimestamp()
    {
        return time();
    }

//    public function getTruenameAttribute($value)
//    {
//
//        $select = ['停用', '启用'];
//        return array_key_exists($value, $select) ? $select[$value] : '停用';
//    }

    public function getNickAttribute($value)
    {
        return empty($value) ? 'anonymity' : $value;
    }
}