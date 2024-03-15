<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/3/29
 * Time: 11:28
 */

namespace Logic\Admin;

use Utils\Admin\Action;
use Utils\Encrypt;
use Logic\Admin\AdminToken;
use Logic\Admin\AdminAuth;
use Lib\Exception\BaseException;

class BaseController extends Action
{
    protected $needAuth = true;

    protected $playLoad = [
        'uid' => 0, // 0 匿名用户
        'rid' => 0, //role id, 0 默认权限
        'type' => 1, // 1 普通用户; 2 平台用户
        'nick' => '',
        'ip' => '0.0.0.0',
        'client_id' => '',
    ];
    protected $adminToken;

    protected $lotteryToken = "do3e28ae0e6";

    public function init($ci)
    {
        parent::init($ci);
        $this->adminToken = new AdminToken($this->ci);
        $this->before();
    }


    /**
     * 校验token
     * @throws BaseException
     */
    public function verifyToken()
    {
        $this->playLoad = $this->adminToken->verifyToken();
    }

    /**
     * 获取管理员姓名
     */
    public function getAdminUserName(){
        $data=(array)\DB::table('admin')
            ->select('name')
            ->where('id','=',$this->playLoad['uid'])
            ->get()
            ->first();
        return $data['name'];
    }

    public function getRequestDir() {
        $ver = ['v1','v2','v3','v4'];
        $dir =  explode('/', $this->request->getUri()->getPath());
        $res = [];
        foreach ($dir as $v) {
            if($v == $ver) continue;
            if (!is_numeric($v)) {//排除id值的put方法和patch方法
                $res[] = $v;
            }
        }
        return implode('/',$res);
    }

    /**
     * 校验权限
     */
    public function authorize()
    {
        $role_id = $this->playLoad['rid'];
        if($role_id == 0 || $role_id == 1){
            return true;
        }
        $dir = $this->getRequestDir();//获取请求地址
        $allow = \DB::table('admin_role_auth')->where('method', $this->request->getMethod())
            ->where('path', $dir)->value('id');
//        if(!$allow)
//            return true;
        $auth = \DB::table('admin_role')->where('id', $role_id)->value('auth');

        if(empty($allow) || empty($auth) || !in_array($allow, explode(',', $auth))) {
            $newResponse = $this->response->withStatus(401);
            $newResponse = $newResponse->withJson([
                'state' => -1,
                'message' => '您无权限操作，请联系管理员添加',
                'ts' => time(),
            ]);
            throw new BaseException($this->request, $newResponse);
        }

        return true;
    }

    public function checkID($id)
    {
        if (empty($id)) {
            $newResponse = $this->response->withStatus(400);
            $newResponse = $newResponse->withJson([
                'state' => -1,
                'message' => 'id不能为空',
                'ts' => time(),
            ]);
            throw new BaseException($this->request, $newResponse);
        }

        if (is_numeric($id) && is_int($id + 0) && ($id + 0) > 0) {
            return true;
        }

        $newResponse = $this->response->withStatus(400);
        $newResponse = $newResponse->withJson([
            'state' => -1,
            'message' => 'id必须为正整数',
            'ts' => time(),
        ]);
        throw new BaseException($this->request, $newResponse);
    }

    public function makePW($password)
    {
        $salt = Encrypt::salt();

        return [md5(md5($password) . $salt), $salt];
    }

    /**
     * 【管理员角色】角色权限设置（不同客服角色对会员各个资料详细权限控制）：
     *  真实姓名（只显示姓/显示全名/修改姓名）、银行卡号（显示全部/显示部分）、通讯资料隐藏/显示（比如邮箱、QQ、微信号等）
     *
     * @param array $data
     * @param int|null $roleId
     * @return array
     */
    public function roleControlFilter(array &$data, int $roleId = null)
    {
        static $names, $cards, $privacies, $rid = null;

        if ($names == null) {
            $names = ['truename', 'accountname'];
        }
        if ($cards == null) {
            $cards = ['card', 'idcard'];
        }
        if ($privacies == null) {
            $privacies = ['email', 'mobile', 'qq', 'weixin', 'skype', 'telephone'];
        }
        if ($rid == null) {
            if (!$roleId) {
                $roleId = $this->playLoad['rid'];
//                $roleId = 1;

            }
            $controls = (new AdminAuth($this->ci))->getMemberControls($roleId);
//            print_r($controls);exit;
        }

        if (isset($controls)) {
            foreach ($data as $key => &$item) {
                if (is_array($item)) {
                    $item = $this->roleControlFilter($item, $roleId);
                } else {
                    // 如果无姓名权限，真实姓名只显示姓
                    if (in_array($key, $names, true)) {
                        if (!$controls['true_name'] && mb_strlen($item)) {
                            $item = strpos($item, ' ') !== false ? explode(' ', $item)[0] . ' ***' : mb_substr($item, 0,
                                    mb_strlen($item) == strlen($item) ? 2 : 1) . '**';
                        }
                    }
                    // 如果无卡号权限，显示两边的部份
                    if (in_array($key, $cards, true)) {
                        if (!$controls['bank_card'] && is_numeric($item)) {
                            $card = trim(chunk_split($item, 4, ' '));
                            $cardChunk = explode(' ', $card);
                            $first = array_shift($cardChunk);
                            $last = array_pop($cardChunk);
                            $item = $first . '****' . $last;
                        }
                    }
                    // 如果无个人信息权限，只显示一部份
                    if (in_array($key, $privacies, true)) {
                        if (!$controls['address_book']) {
                            if (in_array($key, ['email', 'skype']) && strlen($item)) {
                                $item = '***' . strrchr($item, '@');
                            } else {
                                if (strlen($item) > 4) {
                                    $item = substr($item, 0, 2) . '***' . substr($item, -2, 2);
                                } elseif (strlen($item)) {
                                    $item = substr($item, 0, 1) . '**' . substr($item, -1, 1);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 校验请求的TOKEN
     */
    public function verifyLotteryToken()
    {
        $token = $this->request->getHeaderLine('token');
        $lotteryToken = $this->lotteryToken.date("Ymd");
        if ( ($token != $lotteryToken) || empty($token) ) {
            $newResponse = $this->response->withStatus(400);
            $newResponse = $newResponse->withJson([
                'state' => -1,
                'message' => '参数不对',
                'ts' => time(),
            ]);
            throw new BaseException($this->request, $newResponse);
        }
    }

    public function encrypt(string $data)
    {
        $key = $this->lotteryToken . date('Ymd');
        $encrypt = new Encrypt($key);
        return $encrypt->encrypt($data);
    }


    public function __destruct()
    {
        // TODO: Implement __destruct() method.

    }

}