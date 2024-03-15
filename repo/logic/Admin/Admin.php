<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/3/27
 * Time: 15:59
 */

namespace Logic\Admin;

use Slim\Container;
use Model\AdminUser;
use Model\UserLog;
use Model\Label;

class Admin extends \Logic\Logic{

    public function test(){

        return $this->lang->set(10011);
    }
    /**
     * 用户名、密码匹配
     *
     * @param $user
     * @param $password
     * @return int -1 用户名或密码错误 -2 账号被停用 -3 用户名或密码错误
     */
    public function matchUser($user,$password)
    {
        $user = Admin::where('status', '1')
            ->where('username', $user)
            ->find(1)
            ->toArray();

        if (is_array($user)) {

            if ($user['password'] != md5(md5($password) . $user['salt'])) {
                return $this->lang->set(10046);
            }
        }
        return $user;
    }

}
