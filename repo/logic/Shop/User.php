<?php
namespace Logic\Shop;
use Respect\Validation\Validator as V;
use DB;

/**
 * 用户模块
 */
class User extends \Logic\Logic {
    protected $userId = 0;
    protected $username = '';
    protected $password = '';

    /**
     * 手机号码注册
     *
     * @param  $username     用户名即手机号码
     * @param  $password     密码
     * @param  $telCode      短信验证码
     * @param  $telphoneCode 区号
     */
    public function registerByMobile($username, $password, $telCode, $telphoneCode) {
//        return $this->lang->set(0, [], ['uid' => 1]);

        $this->userId = 0;
        $this->username = $username;
        $this->password = $password;

        if ($telphoneCode != '+86') {
            $validator = $this->validator->validate(compact(['username', 'password', 'telCode', 'telphoneCode']), [
                'username'      => V::mobile()->setName('手机号码'),
                'password'      => V::password()->setName('密码'),
//                'telCode'       => V::captchaTextCode()->setName('短信验证码'),
                'telCode'       => V::captchaTextCode()->setName('图形验证码'),
                'telphoneCode' => V::telephoneCode()->setName('区号'),
            ]);
        } else {
            $validator = $this->validator->validate(compact(['username', 'password', 'telCode', 'telphoneCode']), [
                'username'      => V::chinaMobile()->setName('手机号码'),
                'password'      => V::password()->setName('密码'),
//                'telCode'       => V::captchaTextCode()->setName('短信验证码'),
                'telCode'       => V::captchaTextCode()->setName('图形验证码'),
                'telphoneCode' => V::telephoneCode()->setName('区号'),
            ]);
        }

        if (!$validator->isValid()) {
            return $validator;
        }

        // 验证账号是否已注册
        $count = DB::table('user')->where('name', $username)->count();
        if ( $count > 0) {
            return $this->lang->set(107, [], [], ['count' => $count, 'username' => $username]);
        }

        // 验证手机验证码
//        $captcha = new \Logic\Captcha\Captcha($this->ci);
//        if (!$captcha->validateTextCode($telphoneCode . $username, $telCode)) {
//            return $this->lang->set(106, [], [], ['mobile' => $telphoneCode . $username]);
//        }
        // 验证短信验证码
        $captcha = new \Logic\Captcha\Captcha($this->ci);
        if (!$captcha->validateImageCode($this->request->getParam('token'), $telCode)) {
            return $this->lang->set(105, [], [], ['imagecode' => $telCode.'-' . $username]);
        }

        try {
            $this->db->getConnection()->beginTransaction();
            if ($this->db->getConnection()->transactionLevel()) {
                // 创建钱包表
                $walletId = DB::table('funds')->insertGetId([
                    'uuid'    => \DB::raw('uuid()'),
                    'name'    => '主钱包',
                    'balance' => 0,
                    'balance_before' => 0,
                    'comment' => $username . '的主钱包',
                ]);

                //获取来源   android  若渠道名CHANNELNAME为gf  则是标识 APPLICATIONID 是唯一，否则  渠道名就是唯一
                $origins = ['pc'=>1, 'h5'=>2, 'ios'=>3, 'android'=>4];
                $origin = isset($this->request->getHeaders()['HTTP_PL']) && is_array($this->request->getHeaders()['HTTP_PL']) ? current($this->request->getHeaders()['HTTP_PL']) : '';
                $origin_memo = isset($this->request->getHeaders()['HTTP_CHANNELNAME']) && is_array($this->request->getHeaders()['HTTP_CHANNELNAME']) ? current($this->request->getHeaders()['HTTP_CHANNELNAME']) : '';
                if($origin_memo == 'gf'){
                    $origin_memo = isset($this->request->getHeaders()['HTTP_APPLICATIONID']) && is_array($this->request->getHeaders()['HTTP_APPLICATIONID']) ? current($this->request->getHeaders()['HTTP_APPLICATIONID']) : '';
                }

                // 创建账号
                $salt = $this->getGenerateChar(6);
                $userId = DB::table('user')->insertGetId([
                    'wallet_id'     => $walletId,//钱包id对应funds.id
                    'name'           => $username,//手机号码
                    'telphone_code' => $telphoneCode,//电话区号
                    'salt'           => $salt,
                    'password'      => $this->getPasword($password, $salt),
                    'ip'             => \Utils\Client::getIp(),
                    'origin'        => isset($origins[$origin]) ? $origins[$origin] : 0,
                    'origin_memo'   => $origin_memo,
                ]);
                $this->userId = $userId;

                DB::table('user_data')->insertGetId([
                    'user_id' => $userId,
                    'total_bet' => 0,
                ]);

                // 写入注册日志
                DB::table('user_logs')->insertGetId([
                    'user_id'   => $userId,
                    'name'      => $username,
                    'platform'  => isset($origins[$origin]) ? $origins[$origin] : 0,
                    'log_value' => '注册成功！',
                    'status'    => 1,
                    'log_type'  => 8,
                    'log_ip'    => \Utils\Client::getIp(),
                    'domain'    => str_replace(['https', 'http'], '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''),
                ]);

                // 提交事务
                $this->db->getConnection()->commit();
                return $this->lang->set(0, [], ['uid' => $userId]);
            }
        } catch (\Exception $e) {
            $this->db->getConnection()->rollback();
            die($e->getMessage());
        }
        return $this->lang->set(108);
    }

    /**
     * @param 生成随机字符串
     * @return string|unknown
     */
    public function getGenerateChar($length = 6, $chars = null) {
        // 密码字符集，可任意添加你需要的字符
        $chars = $chars ?? "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:?|";
        $password = "";
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $password;
    }

    /**
     * 获取密码
     * @param $password
     * @param $salt
     * @return string
     */
    public function getPasword($password, $salt) {
        return md5(md5($password) . $salt);
    }

    /**
     * 手机号码注册
     *
     * @param  $username     用户名即手机号码
     * @param  $password     密码
     * @param  $telCode      短信验证码
     * @param  $telphoneCode 区号
     */
    public function forget($username, $password, $telCode, $telphoneCode) {
        $this->userId = 0;
        $this->username = $username;
        $this->password = $password;

        if ($telphoneCode != '+86') {
            $validator = $this->validator->validate(compact(['username', 'password', 'telCode', 'telphoneCode']), [
                'username'      => V::mobile()->setName('手机号码'),
                'password'      => V::password()->setName('新密码'),
                'telCode'       => V::captchaTextCode()->setName('短信验证码'),
                'telphoneCode' => V::telephoneCode()->setName('区号'),
            ]);
        } else {
            $validator = $this->validator->validate(compact(['username', 'password', 'telCode', 'telphoneCode']), [
                'username'      => V::chinaMobile()->setName('手机号码'),
                'password'      => V::password()->setName('新密码'),
                'telCode'       => V::captchaTextCode()->setName('短信验证码'),
                'telphoneCode' => V::telephoneCode()->setName('区号'),
            ]);
        }

        if (!$validator->isValid()) {
            return $validator;
        }

        // 验证账户是否存在
        $user_data = DB::table('user')->where('name', $username)->first();
        if (empty($user_data)) {
            return $this->lang->set(51);
        }

        // 验证手机验证码
        $captcha = new \Logic\Captcha\Captcha($this->ci);
        if (!$captcha->validateTextCode($telphoneCode . $username, $telCode)) {
            return $this->lang->set(106, [], [], ['mobile' => $telphoneCode . $username]);
        }

        try {
            $this->db->getConnection()->beginTransaction();
            if ($this->db->getConnection()->transactionLevel()) {
                //获取来源   android  若渠道名CHANNELNAME为gf  则是标识 APPLICATIONID 是唯一，否则  渠道名就是唯一
                $origins = ['pc'=>1, 'h5'=>2, 'ios'=>3, 'android'=>4];
                $origin = isset($this->request->getHeaders()['HTTP_PL']) && is_array($this->request->getHeaders()['HTTP_PL']) ? current($this->request->getHeaders()['HTTP_PL']) : '';

                // 更新账号
                $salt = $this->getGenerateChar(6);
                DB::table('user')->where('id', $user_data->id)->update([
                    'salt'           => $salt,
                    'password'      => $this->getPasword($password, $salt),
                ]);
                $this->userId = $user_data->id;

                // 写入注册日志
                DB::table('user_logs')->insertGetId([
                    'user_id'   => $user_data->id,
                    'name'      => $username,
                    'platform'  => isset($origins[$origin]) ? $origins[$origin] : 0,
                    'log_value' => '修改登录密码成功！',
                    'status'    => 1,
                    'log_type'  => 5,
                    'log_ip'    => \Utils\Client::getIp(),
                    'domain'    => str_replace(['https', 'http'], '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''),
                ]);

                // 提交事务
                $this->db->getConnection()->commit();
                return $this->lang->set(0);
            }
        } catch (\Exception $e) {
            $this->db->getConnection()->rollback();
            die($e->getMessage());
        }
        return $this->lang->set(108);
    }

    public function getInfo($user_id){
        $data = \DB::table('user')->leftJoin('funds', 'user.wallet_id', '=', 'funds.id')
            ->where('user.id', $user_id)->select(['user.wallet_id', 'user.name', 'funds.balance'])->first();
        return (array)$data;
    }

    //商品购买
    public function buy($user_id, $data){
        $goods = DB::table('goods')->where('id', $data['goods_id'])->first();
        if(empty($goods)){
            return $this->lang->set(886, ['商品信息不存在']);
        }
        $goods = (array)$goods;
        $user = DB::table('user')->where('id', $user_id)->first();
        if(empty($user)){
            return $this->lang->set(86);
        }
        $user = (array)$user;
//        $user_data = $this->getInfo($user_id);
//        if($user_data['balance'] < $goods['price']){
//            return $this->lang->set(20);
//        }
        // 扣除钱包 和 生成流水
        try {
            $totalMoney = $goods['price'];
            $this->db->getConnection()->beginTransaction();
            $funds = \DB::table('funds')->where('id', $user['wallet_id'])->lockForUpdate()->first();
            $funds = (array)$funds;
            if ($funds['balance'] < $totalMoney) {
                $this->db->getConnection()->rollback();
                return $this->lang->set(69, [], [], ['fu' => $funds['balance'], 'total' => $totalMoney]);
            }

            $order_number = $this->generateOrderNumber();
            FundsDealLog::create([
                "user_id"           => $user_id,
                "username"          => $user['name'],
                "order_number"      => $order_number,
                "deal_type"         => FundsDealLog::TYPE_GOODS_BUY,
                "deal_category"     => FundsDealLog::CATEGORY_COST,
                "deal_money"        => $totalMoney,
                "balance"           => $funds['balance'] - $totalMoney,
                "memo"              => $this->getOrderLogStr($goods['customer_name'], $goods['id'], $goods['name'], $order_number),
            ]);
            //获取来源
            $origins = ['pc' => 1, 'h5' => 2, 'ios' => 3, 'android' => 4];
            $origin = isset($this->request->getHeaders()['HTTP_PL']) && is_array($this->request->getHeaders()['HTTP_PL']) ? current($this->request->getHeaders()['HTTP_PL']) : '';
            DB::table('goods_order')->insertGetId([
                "user_id"       => $user_id,
                "user_name"      => $user['name'],
                "order_number"  => $order_number,
                "goods_id"      => $goods['id'],
                "goods_name"      => $goods['name'],
                "customer"      => $goods['customer_name'],
                "customer_account" => $data['account'],
                "order_money" => $goods['price'],
                "origin" => $origins[$origin],
            ]);
            // 扣除钱包金额
            (new Wallet($this->ci))->crease($user['wallet_id'], -$totalMoney);
            $this->db->getConnection()->commit();
        } catch (\Exception $e) {
            $this->db->getConnection()->rollback();
            return $this->lang->set(84, [], [], ['error' => $e->getMessage()]);
        }
//        return $this->lang->set(145);
        return $this->lang->set(0);
    }

    /**
     * 拼接字符串
     */
    protected function getOrderLogStr($customer, $goodsId, $goodsName, $orderNumber) {
        //拼接备注
        return '购买:' . $customer . '-' . $goodsId . '-' . $goodsName . '-' . $orderNumber;
    }

    public function generateOrderNumber($rand = 999999999, $length = 9) {
        return intval(date('ndhis')) . str_pad(mt_rand(1, $rand), $length, '0', STR_PAD_LEFT);
    }
}

