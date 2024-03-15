<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/8/1
 * Time: 17:35
 */
namespace Logic\Service;


use Http\Client\Request;
use Logic\Logic;
use Model\Service\ServiceConfig;

class Manage extends Logic
{
    const IM_TOKEN = 'im_token';
    const IM_DEFAULT_EXPIRE = 3600 * 24;

    /**
     * 获取token
     */
    public function getToken(){
        $token = $this->redis->get(self::IM_TOKEN);
        if (empty($token)){

        }
    }




}