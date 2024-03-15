<?php

namespace Logic\Admin;

use Model\Admin\Admin;
use Utils\Client;
use Illuminate\Database\Capsule\Manager as Capsule;
use Lib\Exception\BaseException;
use DB;

/**
 * json web token
 * 保证web api验证信息不被篡改
 *
 * @auth *^-^*<dm>
 * @copyright XLZ CO.
 * @package
 * @date 2017/5/3 8:59
 */
class AdminToken extends \Logic\Logic {

    const KEY = 'this is secret use for jwt';

    const EXPIRE = 3600 * 72;

    protected $Db;

    protected $adminAuth;

    protected $playLoad = [
        'uid'       => 0, // 0 匿名用户
        'rid'       => 0, //role id, 0 默认权限
        'type'      => 1, // 1 普通用户; 2 平台用户
        'nick'      => '',
        'ip'        => '0.0.0.0',
        'client_id' => '',
    ];

    public function __construct($ci) {
        parent::__construct($ci);
        $this->Db = new Capsule();
        $this->Db->setFetchMode(\PDO::FETCH_ASSOC);
        $this->adminAuth = new AdminAuth($ci);
    }

    /**
     * 创建token
     *
     * @param array $data
     * @param string $publicKey
     * @param float|int $ext
     * @param string $digital
     *
     * @return mixed
     */
    public function createToken(array $data = [], $publicKey = self::KEY, $ext = self::EXPIRE, $digital = '') {
        $user = Admin::where('name', $data['name'])->first(['id', 'status', 'password', 'name', 'truename']);

        if (empty($user)) {
            return $this->lang->set(4);
        }

        if ($user['status'] != 1) {
            return $this->lang->set(5);
        }

        if (!password_verify($data['password'], $user['password'])) {
            return $this->lang->set(4);
        }

        unset($user['password']);

        $val = (new \Logic\Captcha\Captcha($this->ci))->validateImageCode($data['token'], $data['code']);
        if (!$val) {
            return $this->lang->set(121);
        }

        $role = DB::table('admin_role_relation')->where('uid', $user['id'])->value('rid');
        // 如果缺少role，则为0
        $user['role'] = $role ?? 0;

        $userData = [
            'uid'  => self::fakeId($user['id'], $digital),
            'role' => self::fakeId(intval($user['role']), $digital),
            'nick' => $data['name'],
            'ip'   => Client::getIp(),//'192.168.10.171'
            'mac'  => Client::ClientId(),
        ];

        // 1、生成header
        $header = ['alg' => "HS256", 'typ' => "JWT"];
        $header = base64_encode(json_encode($header));

        // 2、生成payload
        $payload = base64_encode(json_encode(array_merge(["iss" => "lxz", "exp" => time() + $ext], $userData)));

        // 3、生成Signature
        $signature = hash_hmac('sha256', $header . '.' . $payload, $publicKey, false);
        $token = $header . '.' . $payload . '.' . $signature;

        $routes = $this->adminAuth->getAuths(intval($user['role']));

        $this->adminAuth->saveAdminWithToken($user['id'], $token, $ext);

        DB::table('admin')->where('id', $user['id'])->update(['last_login_ip' => $userData['ip'], 'last_login_time' => time()]);

        // 4、返回结果
        return $this->lang->set(1, [], ['token' => $token, 'info' => $user, 'route'=> $routes]);
    }

    public function verifyToken() {
        if (!$this->playLoad['rid'] || !$this->playLoad['uid']) {
            $config = $this->ci->get('settings')['jsonwebtoken'];
            $this->getToken($config['public_key']);
        }

        return $this->playLoad;
    }

    public function remove($uid) {
        $this->adminAuth->removeToken($uid);
    }

    protected function getToken($publicKey = self::KEY) {
        $header = $this->request->getHeaderLine('Authorization');
        $config = $this->ci->get('settings')['jsonwebtoken'];

        // 判断header是否携带token信息
        if (!$header) {
            $newResponse = createRsponse($this->response, 401, 10041, '缺少验证信息！');
            throw new BaseException($this->request, $newResponse);
        }

        $token = substr($header, 7);

        if ($token && $data = $this->decode($token, $publicKey)) {
            $uid = $this->originId($data['uid'], $config['uid_digital']);
            $key = \Logic\Set\SetConfig::SET_GLOBAL;
            $cache = json_decode($this->redis->get($key), true);
            $login_check = true;

            $login_check = isset($cache['base']['Duplicate_LoginCheck']) ? $cache['base']['Duplicate_LoginCheck'] : $login_check;
            if ($login_check) {
                $this->adminAuth->checkAdminWithToken($uid, $token);
            }

            $role = $this->originId($data['role'] ?? 0, $config['uid_digital']);
            $nick = $data['nick'];
            $this->playLoad = array_merge($this->playLoad, ['rid' => $role, 'uid' => $uid, 'nick' => $nick, 'ip' => Client::getIp()]);
            $GLOBALS['playLoad'] = $this->playLoad;
        } else {
            $newResponse = createRsponse($this->response, 401, 10041, '验证信息不合法！');
            throw new BaseException($this->request, $newResponse);
        }
    }


    /**
     * @param $token
     * @param string $publicKey
     *
     * @return mixed|null
     * @throws BaseException
     */
    protected function decode($token, $publicKey = self::KEY) {
        if (substr_count($token, '.') != 2) {
            return null;
        }

        list($header, $payload, $signature) = explode('.', $token, 3);
        $_payload = json_decode(base64_decode($payload, true), true);

        if (hash_hmac('sha256', $header . '.' . $payload, $publicKey, false) != $signature) {
            $newResponse = createRsponse($this->response, 401, 10041, '验证不通过！');
            throw new BaseException($this->request, $newResponse);
        }

        // 是否过期
        if ($_payload['exp'] <= time()) {
            $newResponse = createRsponse($this->response, 401, 10041, '登录超时！');
            throw new BaseException($this->request, $newResponse);
        }

        return $_payload;
    }

    /**
     * @param string $publicKey
     *
     * @return null
     * @throws BaseException
     */
    public function getPayload($publicKey = self::KEY) {
        $token = $this->getToken($publicKey);
        if (!$token) {
            return null;
        }

        return $token['payload'];
    }

    /**
     * 伪uid
     *
     * @param int $uid
     * @param int $digital
     *
     * @return int
     */
    public static function fakeId(int $uid, int $digital) {
        return ~$digital - $uid;
    }

    /**
     * 原uid
     *
     * @param int $fakeId
     * @param int $digital
     *
     * @return int
     */
    public function originId($fakeId, $digital) {
        return ~($fakeId + $digital);
    }
}
