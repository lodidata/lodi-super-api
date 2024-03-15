<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/7/18
 * Time: 17:03
 */
namespace Model\Service;

class ServiceSet extends \Illuminate\Database\Eloquent\Model{

    protected $table = 'service_set'; //表名
    protected $primaryKey = 'id'; //主键
    protected $fillable = ['name','access_way','link'];
    public $timestamps = false;

    /**
     * 避免转换时间戳为时间字符串
     * @param \DateTime|int $value
     * @return false|int
     * @author dividez
     */
    public function fromDateTime($value){
        return strtotime(parent::fromDateTime($value));
    }

    /**
     * 获取当前时间
     *
     * @return int
     */
    public function freshTimestamp() {
        return time();
    }
}
