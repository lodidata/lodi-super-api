<?php
namespace Logic\Shop;
use Logic\Define\CacheKey;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Parser;
use Hashids\Hashids;
use DB;

/**
 * 权限模块
 */
class Auth extends \Logic\Logic {
    protected $sign = 'qp sign';
    //token过期时间
    protected $expiration = 30 * 86400;
    protected $userId = 0;
    //更换token
    protected $expirationRadio = 0.1;
    //平台
    protected $platforms = ['pc', 'h5', 'android', 'ios'];
    //默认平台
    protected $defaultPlatform = 'pc';
    //自动token续期
    protected $autoRefreshToken = true;
    //登录组
    protected $platformGroups = [
        'pc'      => 1,
        'h5'      => 1,
        'android' => 2,
        'ios'     => 2,
    ];
    //登录来源对应
    protected $platformIds = [
        'pc'      => 1,
        'h5'      => 2,
        'ios'     => 3,
        'android' => 4,
    ];

    //获取userId;
    public function getUserId(){
        return $this->userId;
    }

    //验证是否登录
    public function verfiyToken(){
        $authorization = isset($this->request->getHeaders()['HTTP_AUTHORIZATION']) ? current($this->request->getHeaders()['HTTP_AUTHORIZATION']) : ($this->request->getQueryParam('token') ?? '');

        if (empty($authorization)) {
            return $this->lang->set(11);
        }

        try {
            $token = (new Parser())->parse($authorization);
            $uid = $token->getClaim('uid');
            $plGid = $token->getClaim('plGid');
            $userLoginId = $token->getClaim('loginId');
            $createTime = $token->getClaim('time');
        } catch (\Exception $e) {
            $uid = 0;
        }

        // 取不出UID
        if (empty($uid)) {
            return $this->lang->set(58);
        }

        // 取缓存
        $loginId = $this->redis->get(CacheKey::$perfix['token'] . '_' . $plGid . '_' . $uid);
        if (empty($loginId)) {
            return $this->lang->set(59);
        }

        // 判断登录ID是否一致
        if ($userLoginId != $loginId || $this->getCurrentPlatformGroupId() != $plGid) {
            return $this->lang->set(162);
        }

        $user = DB::table('user')->where('id', $uid)->first();
        if (!$user || $user->state == 0) {
            return $this->lang->set(3001);
        }

        //Token 无效
        if (!$token->verify(new Sha256(), $this->sign) || $token->isExpired()) {
            return $this->lang->set(59);
        }

        // 更换token
        if (time() - $createTime > $this->expiration * $this->expirationRadio) {
            list($token, $loginId, $time, $socketToken) = $this->getToken($uid);
            return $this->lang->set(99, [], [
                'token'         => $token,
                'expiration'    => $time + $this->expiration,
                'socketToken'   => $socketToken,
                'socketLoginId' => $loginId,
            ]);
        }

        // 刷新最后登录时间
        $this->redis->hset(CacheKey::$perfix['userOnlineLastTime'], $uid, time());

        // 刷新token
        $this->redis->expire(CacheKey::$perfix['token'] . '_' . $plGid . '_' . $uid, $this->expiration);
        $this->userId = $uid;

        return $this->lang->set(0, [], ['uid' => $uid]);
    }

    /**
     * 登录接口
     *
     * @param string $username
     * @param string $password
     * @param int $loginType 登录类型  0：普通登录 1：微信登录 2：注册自动登录
     *
     * @return array
     */
    public function login($username, $password, $loginType = 0){
        $user = DB::table('user')->where('name', '=', $username)->first();

        if (empty($user)) {
            return $this->lang->set(51);
        }
        $user = (array)$user;

        // 密码错误
        if (!$this->verifyPass($user['password'], $password, $user['salt'], 0)) {
            DB::table('user_logs')->insertGetId([
                'user_id'   => $user['id'],
                'name'      => $user['name'],
                'log_value' => '登录失败',
                'status'    => 0,
                'log_type'  => 1,
                'platform'  => isset($_SERVER['HTTP_PL']) ? isset($this->platformIds[$_SERVER['HTTP_PL']]) ? $this->platformIds[$_SERVER['HTTP_PL']] : 0 : 0,
                'log_ip'    => \Utils\Client::getIp(),
                'domain'    => str_replace(['https', 'http'], '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''),
            ]);
            // 登录密码错误次数判断
            $pwdError = $this->pwdErrorLimit($user['id']);
            $remainTimes = $pwdError['limit'] - $pwdError['times'];
            if ($remainTimes > 0) {
                return $this->lang->set(52, [$pwdError['times'], $remainTimes]);
            } else {
                return $this->lang->set(53, [$pwdError['limit']]);
            }
        }

        // 状态定义列表
        $states = [0 => 54, 2 => 55, 3 => 55, 4 => 56];

        // 判断会员状态
        if (isset($states[$user['state']])) {
            return $this->lang->set($states[$user['state']]);
        }

        return $this->baseLogin($user, $loginType);
    }

    /**
     * 基础登录接口
     */
    protected function baseLogin($user, $loginType = 0){
        //判断登录类型
        $loginType = [0 => '普通模式', 1 => '微信登录', 2 => '注册自动登录'][$loginType];

        // 创建token
        list($token, $loginId, $time, $socketToken) = $this->getToken($user['id']);

        // 写入登录成功日志
        DB::table('user_logs')->insert([
            'user_id'   => $user['id'],
            'name'      => $user['name'],
            'log_value' => '登录成功（' . $loginType . '）',
            'status'    => 1,
            'log_type'  => 1,
            'platform'  => isset($_SERVER['HTTP_PL']) ? isset($this->platformIds[$_SERVER['HTTP_PL']]) ? $this->platformIds[$_SERVER['HTTP_PL']] : 0 : 0,
            'log_ip'    => \Utils\Client::getIp(),
            'domain'    => str_replace(['https', 'http'], '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''),
        ]);

        DB::table('user')->where('id', $user['id'])->update([
            'last_login' => date('Y-m-d H:i:s'),
            'login_ip'   => \Utils\Client::getIp(),
        ]);

        //清除登录错误记录
        $this->redis->del(CacheKey::$perfix['pwdErrorLimit'] . '_' . $user['id']);
        $this->userId = $user['id'];

        return $this->lang->set(0, [],[
            'auth' => [
                'token'         => $token,
                'expiration'    => time() + $this->expiration,
                'socketToken'   => $socketToken,
                'socketLoginId' => $loginId,
                'uuid'          => md5($this->sign . $user['id']),
            ],
        ]);
    }

    /**
     * 退出登录（踢出登录）
     *
     * @param  [type] $uid [description]
     * @param  [type] $plGid  平台ID
     *
     */
    public function logout($uid, $plGid = null){
        if (empty($uid)) {
            $verify = $this->verfiyToken();
            if ($verify->allowNext()) {
                $uid = $this->getUserId();
            } else {
                return $this->lang->set(60);
            }
        }

        $platformGroupsValues = $plGid === null ? array_unique(array_values($this->platformGroups)) : ((array)$plGid);
        foreach ($platformGroupsValues as $plGids) {
            $this->redis->hset(CacheKey::$perfix['userOnlineLastTime'], $uid, time() - 86400);
            $this->redis->del(CacheKey::$perfix['token'] . '_' . $plGids . '_' . $uid);
        }
        return $this->lang->set(0);
    }

    /**
     * 登录密码错误次数限制
     * @param $user_id 用户id
     * @param $user_type 用户类型(会员:user,代理:agent)
     *
     * @return array(times:已输错次数,limit:最大错误次数)
     */
    protected function pwdErrorLimit($uid){
        $name = CacheKey::$perfix['pwdErrorLimit'] . '_' . $uid;
        $limit = 5;
        //有效期 从错误开始 24小时
        $secends = 3600 * 24;
        $errorTimes = $this->redis->get($name);

        if ($errorTimes >= 0) {
            if ($errorTimes > $limit) {
                return ['times' => $errorTimes, 'limit' => $limit];
            }

            $this->redis->incr($name);
            if ($this->redis->get($name) >= $limit) {
                //停用账号
                DB::table('user')->where('id', '=', $uid)->update(['state' => 0]);
            }
        } else {
            $this->redis->setex($name, $secends, 1);
        }
        return ['times' => $this->redis->get($name), 'limit' => $limit];
    }

    /**
     * 验证当前用户密码
     * @param string $current 当前密码
     * @param string $password 原密码
     * @param string $salt 散列码
     * @param int $vtype = 1 为自动登录，不验证密码
     */
    public function verifyPass($current, $password, $salt, $vtype = 0){
        return $vtype ? true : $current == md5(md5($password) . $salt);
    }

    /**
     * 创建token
     *
     * @param $userId
     *
     * @return array
     * @throws \Interop\Container\Exception\ContainerException
     */
    protected function getToken($userId, $trial_status = ''){
        $now = time();
        $loginId = uniqid();

        $plGid = $this->getCurrentPlatformGroupId();
        $token = (new Builder())->setIssuer('tc3ga.com')
            ->setAudience('tc3ga.com')
            ->setId('token', true)
            ->setIssuedAt($now)
            ->setNotBefore(60 + $now)
            ->setExpiration($this->expiration + $now)
            ->set('uid', $userId)
            // ->set('client', $origin)    //客户端
            ->set('loginId', $loginId)//唯一ID
            ->set('plGid', $plGid)// 登录平台组ID
            ->set('time', $now)
            ->sign(new Sha256(), $this->sign)
            ->getToken()
            ->__toString();

        $appId = $this->ci->get('settings')['pusherio']['app_id'];
        $appSecret = $this->ci->get('settings')['pusherio']['app_secret'];

        // 写入token
        $this->redis->setex(CacheKey::$perfix['token'] . '_' . $plGid . '_' . $userId, $this->expiration, $loginId);
        $hashids = new Hashids($appId . $appSecret, 8, 'abcdefghijklmnopqrstuvwxyz');
        return [$token, $loginId, $now, $hashids->encode($userId)];
    }

    /**
     * 获取登录平台配置
     * @return [type] [description]
     */
    public function getPlatformGroups(){
        return $this->platformGroups;
    }

    /**
     * 获取当前登录平台ID
     * @return [type] [description]
     */
    public function getCurrentPlatformGroupId(){
        $pl = $this->getCurrentPlatform();
        return in_array($pl, $this->platforms) ? $this->platformGroups[$pl] : $this->platformGroups[$this->defaultPlatform];
    }

    /**
     * 判断是否移动端请求
     * @return boolean [description]
     */
    public function isMobilePlatform()
    {
        $pl = $this->getCurrentPlatform();
        return in_array($pl, ['h5', 'android', 'ios']) ? true : false;
    }

    /**
     * 获取当前登录平台
     * @return [type] [description]
     */
    public function getCurrentPlatform(){
        return isset($this->request->getHeaders()['HTTP_PL']) ? current(
            $this->request->getHeaders()['HTTP_PL']
        ) : $this->defaultPlatform;
    }
}

