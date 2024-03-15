<?php

namespace Logic\Admin;

use Logic\Set\Datas;
use Model\Admin\AdminUser;
use Logic\Admin\Cache\AdminRedis;
use Model\Admin\AdminUserRoleAuth;
use Logic\Define\CacheKey;
use Lib\Exception\BaseException;

class AdminAuth extends \Logic\Logic {
    const PREFIX_ADMIN = 'admin.cache.manager.';
    const ADMIN_USER = self::PREFIX_ADMIN . 'admin_user:';

    const KEY_ADMIN_USER = 'admin_user_cache';
    const KEY_REFRESH_TOKEN = 'admin_refresh_token';
    const KEY_ROLE_AUTH = 'admin_role_auth';
    const KEY_ROLE_AUTH_FLAT = 'admin_role_auth_flat';
    const KEY_ROLE_MEMBER_CONTROL = 'admin_role_member_control';

    const DEFAULT_EXPIRE = 3600 * 24 * 15;

    protected $adminRedisLogic;

    // 是否需要判断权限
    protected $needAuth = true;

    public function __construct($ci) {
        parent::__construct($ci);
        //$this->adminRedisLogic = new AdminRedis($ci);
    }

    /**
     * 权限鉴定
     */
    public function authorize($rid, $flat = false) {
        return true;

        /**
         * @TODO:权限校验
         */
        if (!function_exists('getallheaders')) {
            function getallheaders() {
            }
        }
        $routes = $this->getRoutes($rid, $flat);
        $action = strtolower($this->request->getMethod());
        $origin = $_SERVER['HTTP_X_REQUEST_URI'] ?? getallheaders()['X-Request-Uri'] ?? null;
        if ($origin) {
            $route = $origin == '/' ? '/' : trim(parse_url($origin, PHP_URL_PATH), '/');
            if ($route) {
                if (isset($routes[$route])) {

                    $actions = $routes[$route];
                    $in = true;
                    switch ($action) {
                        // 删除权限
                        case 'delete':
                            $in = in_array('delete', $actions);
                            break;
                        // 查询权限
                        case 'get':
                            $in = in_array('fetch', $actions);
                            break;
                        // 添加权限
                        case 'post':
                            $in = in_array('insert', $actions);
                            break;
                        // 修改权限
                        case 'patch':
                            $in = in_array('update', $actions);
                            break;
                        // 添加/修改权限
                        case 'put':
                            // 区分insert update
                            /**
                             * 这里routes可能存在两种情况。如下:
                             *  # 添加/修改银行账户
                             *  PUT /cash/bank/find/?id\d  /cash/bank/find/me
                             *  # 修改银行账户状态
                             *  PATCH /cash/bank/find/?id\d  /cash/bank/find
                             * 这里pattern相同，array_column处理时返回的array只会保存最后一个，如上述，只会保存patch的记录，
                             * ['/cash/bank/find/?id\d'=>'/cash/bank/find']
                             * 如此就无法取到put真正需要的entry：put路径/cash/bank/find/me。如果RouteMap以entry为key，也同样会遇到这样的情况。
                             * 因此我们使用下面的方法：
                             * method+entry使key变得唯一
                             */
                            $entry = $this->context->entry;
                            $method = $entry->action;
                            $Map = array_filter(array_map(function ($route) use ($method) {
                                // 使用空格分隔比较合适，避免与url常用ascii字冲突
                                if (in_array($method, $route->method)) {
                                    return $method . ' ' . $route->entry . ' ' . $route->pattern;
                                }

                                return null;
                            }, $this->routes), function ($e) use ($entry, $method) {
                                return stripos($e, $method . ' ' . $entry->index) !== false;
                            });
                            $Map = explode(' ', array_pop($Map));
                            $Pattern = array_pop($Map);
                            // 修改
                            if (preg_match('~(/?|/*)~i', $Pattern)) {
                                $in = in_array('update', $actions);
                            } else {
                                $in = in_array('insert', $actions);
                            }
                            break;
                    }
                    if (!$in) {
                        $newResponse = $this->response->withStatus(405);
                        throw new BaseException($this->request, $newResponse);
                    }
//                            $this->X405();
//                            quit();
                }
            }
        }


    }


    /**
     * 获取管理员初始化权限
     * todo: 改为前端节点配置
     *
     * @param string $name
     *
     * @return mixed
     */
    public function authOrigin($name = '../../config/adminroutes.json') {
        return json_decode(file_get_contents($name), true);
    }

    /**
     * @param int $roleId
     * @param bool $flat
     * @return bool|array
     */
    public function getAuths($roleId)
    {
        if(empty($roleId)) return [];
        $auths = explode(',', \DB::table('admin_role')->where('id', $roleId)->value('auth'));
        //获取三级菜单对应的pid
        $pid1 = \DB::table('admin_role_auth')->whereIn('id', $auths)->distinct()->pluck('pid')->toArray();
        //获取二级菜单对应的pid
        $pid2 = \DB::table('admin_role_auth')->whereIn('id', $pid1)->distinct()->pluck('pid')->toArray();
        //一级菜单和二级菜单的id合并
        return array_unique(array_merge($pid1, $pid2));
    }

    /**
     * 根据用户id保存token
     */
    public function saveAdminWithToken($uid, $token = '', $ttl = 3600) {
        $key = CacheKey::$perfix['adminCacheToken'] . $uid;
        $this->redis->setex($key, $ttl, $token);

    }

    /**
     * 刷新用户访问时间
     */
    public function refreshAdminToken($uid, $ttl = 3600) {
        $key = CacheKey::$perfix['adminCacheToken'] . $uid;
        $this->redis->expire($key, $ttl);
    }

    /**
     * 根据用户id和token检验token是否有效
     *
     * @param $uid
     * @param string $token
     *
     * @throws BaseException
     */
    public function checkAdminWithToken($uid, $token = '') {
        $key = CacheKey::$perfix['adminCacheToken'] . $uid;
        if ($token !== $this->redis->get($key)) {
            $newResponse = createRsponse($this->response, 401, 10041, '该账号已在别处登录，请重新登录！');
            throw new BaseException($this->request, $newResponse);
        }
    }

    /**
     * 刷新token
     *
     * @param $refreshToken
     * @param $accessToken
     * @param $expire
     *
     * @return boolean
     */
    public function saveRefreshToken($refreshToken, $accessToken, $expire = self::DEFAULT_EXPIRE) {
        $cache = $this->redis;
        $key = self::KEY_REFRESH_TOKEN . ':' . $refreshToken;
        $cache->set($key, $accessToken);
        $cache->expire($key, $expire);

    }

    /**
     * 删除 token
     *
     * @param $uid
     */
    public function removeToken($uid) {
        $key = CacheKey::$perfix['adminCacheToken'] . $uid;
        $this->redis->del($key);
    }

}
