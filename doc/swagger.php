 <?php
require __DIR__ . '/../repo/vendor/autoload.php';

class SGR {

    protected $files = [];

    protected $common = '[后端文档地址](http://192.168.10.57:9999/admin-master.html)'.PHP_EOL
                        ;

    public function initWWWApi() {
        $base = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => '主后台API',
                'description' => '唐朝 API适用于移动端和PC端  '.PHP_EOL
                .'文档接口参数类型 [string, integer, array, boolen, object, number]  '.PHP_EOL
                .'APP请求时请带上header头 [sv, av, vv, av] pl平台 mm 手机型号 av app版本 sv 系统版本  uuid 唯一标识  '.PHP_EOL
                .'请求类型Content-type: application/json  '.PHP_EOL
                .'需要权限验证的接口请带上Authorization头, 接口状态返回401时请跳转登录  '.PHP_EOL
                .$this->common
               ,
                'version' => '2.0.0',
            ],
            // 'basePath' => "/v2",
            'servers' => [
                0 => [
                    'url' => 'http://www-api2.ftdev.com/v2',
                    'description' => '内网开发环境，请指定host',
                ],
                1 => [
                    'url' => 'http://www-api2.sayahao.com/v2',
                    'description' => '外网测试环境',
                ],
            ],
            'paths' => [
            ],
        ];

        $project = __DIR__.'/../api.www/src/entries/v2';
        $this->showdir($project);
        // print_r($this->files);
        $this->fetch($base);
        return $base;
    }

    public function initAdminApi() {
        $base = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => '主后台 后端API',
                'description' => '唐朝 API适用于后端  '.PHP_EOL
                .'文档接口参数类型 [string, integer, array, boolen, object, number]  '.PHP_EOL
                .'APP请求时请带上header头 [sv, av, vv, av] pl平台 mm 手机型号 av app版本 sv 系统版本  uuid 唯一标识  '.PHP_EOL
                .'请求类型Content-type: application/json  '.PHP_EOL
                .'需要权限验证的接口请带上Authorization头, 接口状态返回401时请跳转登录  '.PHP_EOL
                 .$this->common
               ,
                'version' => '2.0.0',
            ],
            // 'basePath' => "/v2",
            'servers' => [
                0 => [
                    'url' => 'http://pay-admin.ftdev.com/v2',
                    'description' => '内网开发环境，请指定host',
                ],
                1 => [
                    'url' => 'http://pay-admin.sayahao.com/v2',
                    'description' => '外网测试环境',
                ],
            ],
            'paths' => [
            ],
        ];

        $project = __DIR__.'/../admin/src/entries/v1';
        $this->showdir($project);
        $this->fetch($base);
        return $base;
    }

    public function initReBuild() {
        $base = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => '重构文档说明',
                'description' => "1. 统一所有金额前端输入输出单位，均为分 （前端首页中奖金额、 提现金额、 钱包金额）  \n\t
                                  2. APP请求时请带上header头 [sv, av, vv, av] pl平台 mm 手机型号 av app版本 sv 系统版本  uuid 唯一标识  \n\t
                                  3. token 续期， header code 返回401时并且state值等于99时请更换token, socketio服务需要重新连接。  \n\t
                                  4. 需要发送手机短信和邮箱时均要提前请求图片验证码。  \n\t
                                  5. state返回值如无特别说明请以http code判断请求是否正确。  \n\t
                                  6. 统一使用后端返回提示作为前端错误或成功提示。  \n\t
                                  7. 代码地址迁移。 \n\t
                                  8. socketio 服务器地址读取 get start/config 读取，在用户有登录状态后连接。\n\t

                    代码地址迁移列表：  \n\t
                        '/active/list/get.php' => '/user/active/list/get.php' \n \t
                        '/active/get.php' => '/user/active/get.php' \n \t
                        '/active/post.php' => '/user/active/post.php' \n \t
                        '/active/types/get.php' => '/user/active/types/get.php' \n \t
                        '/app/cookie/get.php' => '/game/sb/cookie/get.php', \n \t
                        '/app/homepage/get.php' => '/block/home/app/prize/get.php' \n \t
                        '/app/hot/get.php' => '/block/home/app/hot/get.php' \n \t
                        '/app/lottery/get.php' => '/block/lottery/app/last/get.php' \n \t
                        '/app/third/get.php' => '/game/third/list/get.php', // ??? \n \t
                        '/app/shelves/android/get.php' => '/app/shelves/android/get.php', \n \t
                        '/app/shelves/ios/get.php' => '/app/shelves/ios/get.php', \n \t
                        '/app/shelves/enterprise/get.php' => '/app/shelves/enterprise/get.php', \n \t
                        '/app/shelves/peizhi/get.php' => '/app/shelves/peizhi/get.php', \n \t
                        '/banks/get.php' => '/block/condition/banks/get.php' \n \t
                        '/banner/get.php' => '/block/home/app/banner/get.php' \n \t
                        '/configset/get.php' => '/start/config/get.php' \n \t
                        '/egame/list/get.php' => '/block/home/pc/egame/get.php' \n \t
                        '/game/third/get.php' => '/game/third/app/get.php' \n \t
                        '/home/live/get.php' => '/block/home/pc/live/get.php' \n \t
                        '/home/menu/get.php' => '/block/home/pc/menu/get.php' \n \t
                        '/home/get.php' => '/block/home/pc/get.php' \n \t
                        '/lottery/historylist/get.php' => '/game/lottery/history/get.php' \n \t
                        '/lottery/nextperiods/get.php' => '/game/lottery/nextperiods/get.php' \n \t
                        '/lottery/play/chase/get.php' => '/game/lottery/chase/get.php' \n \t
                        '/lottery/play/chase/info/get.php' => '/game/lottery/chase/info/get.php' \n \t
                        '/lottery/play/chase/stop/patch.php' => '/game/lottery/chase/patch.php' \n \t
                        '/lottery/play/info/get.php' => '/game/lottery/info/get.php' \n \t
                        '/lottery/play/order/post.php' => '/game/lottery/order/post.php' \n \t
                        '/lottery/play/pusher/io/history/get.php' => '/game/lottery/chat/history/get.php' \n \t
                        '/lottery/play/records/get.php' => '/game/lottery/order/get.php' \n \t
                        '/lottery/play/records/state/get.php' => '/block/condition/lottery/state/get.php' \n \t
                        '/lottery/play/struct/get.php' => '/game/lottery/struct/get.php' \n \t
                        '/lottery/simplelist/get.php' => '/game/lottery/simple/get.php' \n \t
                        '/lottery/trade/eleven/get.php' => '/game/lottery/trade/eleven/get.php' \n \t
                        '/lottery/trade/k3/get.php' => '/game/lottery/trade/k3/get.php' \n \t
                        '/lottery/trade/sc/get.php' => '/game/lottery/trade/sc/get.php' \n \t
                        '/lottery/trade/ssc/get.php' => '/game/lottery/trade/ssc/get.php' \n \t
                        '/lottery/trade/get.php' => '/game/lottery/trade/get.php' \n \t
                        '/message/list/get.php' => '/user/message/get.php' \n \t
                        '/message/status/put.php' => '/user/message/put.php' \n \t
                        '/message/delete.php' => '/user/message/delete.php' \n \t
                        '/notice/list/get.php' => '/user/notice/get.php' \n \t
                        '/notice/h5list/get.php' => '/user/notice/app/get.php' \n \t
                        '/pc28/hall/get.php' => '/game/lottery/hall/get.php' \n \t
                        '/record/from/get.php' => '/block/condition/capital/get.php' \n \t
                        '/record/fromsearch/get.php' => '/user/capital/get.php' \n \t
                        '/service/3th/get.php' => '/service/3th/get.php' \n \t
                        '/service/common/get.php' => '/service/common/get.php' \n \t
                        '/service/handle/get.php' => '/service/handle/get.php' \n \t
                        '/service/types/get.php' => '/block/condition/service/types/get.php' \n \t
                        '/user/addbank/put.php' => '/user/bank/put.php' \n \t
                        '/user/agent/list/get.php' => '/user/agent/get.php' \n \t
                        '/user/agent/post.php' => '/user/agent/post.php' \n \t
                        '/user/agent/market/get.php' => '/user/agent/market/get.php' \n \t
                        '/user/agent/market/put.php' => '/user/agent/market/put.php' \n \t
                        '/user/auth/login/post.php' => '/user/auth/login/post.php' \n \t
                        '/user/auth/loginwx/post.php' => '/user/auth/loginwx/post.php' \n \t
                        '/user/auth/logout/get.php' => '/user/auth/logout/get.php' \n \t
                        '/user/auth/get.php' => '/user/auth/get.php' \n \t
                        '/user/avatar/get.php' => '/user/avatar/get.php' \n \t
                        '/user/avatar/patch.php' => '/user/avatar/patch.php' \n \t
                        '/user/bank/h5add/get.php' => '/user/bank/get.php' \n \t
                        '/user/baseinfo/get.php' => '/user/baseinfo/get.php' \n \t
                        '/user/baseinfo/post.php' => '/user/baseinfo/post.php' \n \t
                        '/user/bindbank/get.php' => '/user/bank/bind/get.php' \n \t
                        '/room/get.php' => '/game/lottery/hall/room/get.php' \n \t
                        '/chat/publish/post.php' => '/service/pusherio/publish/post.php' \n \t
                        '/user/checkmobile/get.php' => '/user/auth/register/checkmobile/get.php' \n \t
                        '/user/deletebank/delete.php' => '/user/bank/delete.php' \n \t
                        '/user/email/post.php' => '/user/safety/email/post.php' \n \t
                        '/user/email/patch.php' => '/user/safety/email/patch.php' \n \t
                        '/user/exchange/post.php' => '/user/wallet/exchange/post.php' \n \t
                        '/user/idcard/get.php' => '/user/safety/idcard/get.php' \n \t
                        '/user/idcard/post.php' => '/user/safety/idcard/post.php' \n \t
                        '/user/mobilecode/post.php' => '/user/safety/mobilecode/post.php' \n \t
                        '/user/newreg/get.php' => '/user/auth/register/config/get.php' \n \t
                        '/user/openverification/get.php' => '/user/safety/openverification/get.php' \n \t
                        '/user/password/patch.php' => '/user/auth/password/patch.php' \n \t
                        '/user/profile/get.php' => '/user/profile/get.php' \n \t
                        '/user/register/get.php' => '/user/auth/register/get.php' \n \t
                        '/user/register/post.php' => '/user/auth/register/post.php' \n \t
                        '/user/resetpwd/get.php' => '/user/auth/forget/get.php' \n \t
                        '/user/resetpwd/post.php' => '/user/auth/forget/post.php' \n \t
                        '/user/resetpwd/put.php' => '/user/auth/forget/put.php' \n \t
                        '/user/resetpwd/patch.php' => '/user/auth/forget/patch.php' \n \t
                        '/user/safety/get.php' => '/user/safety/get.php' \n \t
                        '/user/sendregmsg/post.php' => '/user/auth/register/mobliecode/post.php' \n \t
                        '/user/trytoplay/get.php' => '/user/auth/trytoplay/get.php' \n \t
                        '/user/trytoplay/post.php' => '/user/auth/trytoplay/post.php' \n \t
                        '/user/verification/get.php' => '/user/safety/verification/get.php' \n \t
                        '/user/verification/patch.php' => '/user/safety/verification/patch.php' \n \t
                        '/user/wallet/get.php' => '/user/wallet/get.php' \n \t
                        '/user/withdrawpwd/put.php' => '/user/safety/withdrawpwd/put.php' \n \t
                        '/user/withdrawpwd/patch.php' => '/user/safety/withdrawpwd/patch.php' \n \t
                        '/vcode/get.php' => '/block/condition/captcha/image/get.php' \n \t
                        '/wallet/newrecharge/get.php' => '/wallet/newrecharge/get.php' \n \t
                        '/wallet/paytype/offlines/get.php' => '/user/wallet/paytype/offlines/get.php' \n \t
                        '/wallet/paytype/onlines/get.php' => '/user/wallet/paytype/onlines/get.php' \n \t
                        '/wallet/recharge/active/get.php' => '/user/wallet/recharge/active/get.php' \n \t
                        '/wallet/recharge/bank/get.php' => '/user/wallet/recharge/bank/get.php' \n \t
                        '/wallet/recharge/offlines/get.php' => '/user/wallet/recharge/offlines/get.php' \n \t
                        '/wallet/recharge/offlines/put.php' => '/user/wallet/recharge/offlines/put.php' \n \t
                        '/wallet/recharge/onlines/get.php' => '/user/wallet/recharge/onlines/get.php' \n \t
                        '/wallet/recharge/onlines/put.php' => '/user/wallet/recharge/onlines/put.php' \n \t
                        '/wallet/recovery/get.php' => '/user/wallet/exchange/recovery/get.php' \n \t
                        '/wallet/withdraw/get.php' => '/user/wallet/withdraw/get.php' \n \t
                        '/wallet/withdraw/put.php' => '/user/wallet/withdraw/put.php' \n \t
                        '/wallet/get.php' => '/user/wallet/get.php' \n \t
                        '/wallet/third/get.php' => '/user/wallet/third/get.php' \n \t
                       \n \t  \n \t
                        '/user/agent/info/get.php' => '/user/agent/info/get.php' \n \t
                        '/h5/homepage/get.php' => '/block/home/app/get.php' \n \t
                        '/lottery/play/odds/get.php' => '/game/lottery/odds/get.php' \n \t
                        '/thirdType/get.php' => '/game/third/type/get.php' \n \t
                        '/user/telphone/post.php' => '/user/safety/bindmobile/post.php' \n \t
                        '/order/third/get.php' => '/game/third/order/get.php' \n \t
                        '/order/type/get.php' => '/game/third/order/type/get.php' \n \t
                        '/service/handle/put.php' => '/service/handle/put.php' \n \t
                        '/user/chat/publish/post.php' => '/user/chat/publish/post.php' \n \t
                        '/user/chat/publish/post.php' => '/user/chat/publish/post.php' \n \t
                        '/lottery/play/betlist/post.php' => '/game/lottery/play/betlist/post.php' \n\t
                       \n\t
                          \n\t
                    移除项目   \n\t
                    '/app/down/get.php', // 合并至start/config/get.php    \n\t
                    '/app/saba/get.php', // 删除    \n\t
                    '/lottery/miss/get.php', // 删除    \n\t
                    '/lottery/report/get.php', // 删除    \n\t
                    '/user/password/post.php', // 删除    \n\t
                    '/user/question/get.php', // 删除    \n\t
                    '/lottery/play/appchase/get.php', // 合并至 /game/lottery/chase/get.php    \n\t
                    '/set/withdraw/get.php', // 合并至 start/config/get.php    \n\t
                    '/lottery/play/pusher/io/auth/get.php', // 删除    \n\t
                    '/lottery/play/pusher/io/info/get.php', // 删除 合并到start/config/get.php    \n\t
                    '/lottery/play/pusher/io/publish/post.php', // 删除 合并到全局消息发送接口    \n\t
                    '/user/login/post.php', // 合并至 user/auth/login/post.php    \n\t
                    '/user/logout/get.php', // 合并至 user/auth/logout/get.php    \n\t
                    '/wallet/fee/get.php', // 删除    \n\t
                    '/wallet/newrecharge/limit/get.php', // 合并到start/config/get.php    \n\t
                    '/wallet/newrecharge/get.php', // 删除    \n\t
                    '/wallet/refresh/get.php', // 合并至/user/wallet/get.php    \n\t
                    '/user/auth/post.php.bak', // 删除    \n\t
                ".$this->common
               ,
                'version' => '2.0.0',
            ],
            // 'basePath' => "/v2",
            'servers' => [
                0 => [
                    'url' => 'http://www-api2.ftdev.com/v2',
                    'description' => '内网开发环境，请指定host',
                ],
                1 => [
                    'url' => 'http://www-api2.sayahao.com/v2',
                    'description' => '外网测试环境',
                ],
            ],
            'paths' => [
                'tests'=> [
                    'get' => [
                        'summary' => 'test',
                        'description' => 'test',
                        'tags' => [
                            0 => 'test',
                        ],
                        'responses' => [
                            "200" => [
                                'description' => '操作成功',
                                'content' => ['application/json' => []],
                            ],
                        ],
                    ],
                ]
            ],
        ];
        return $base;
    }

    public function fetch(&$base) {
        foreach ($this->files as $file) {
            $params = explode('/',$file);
            if(in_array('v2',$params)){
                $url = explode('/v2/', $file)[1];
            }else{
                $url = explode('/v1/', $file)[1];
            }
            $method = explode('.', basename($url))[0];
            $suffix = explode('.', $url)[1];
            if($suffix != 'php') {
                continue;
            }

            // if (strpos($url, 'appchase') === false) {
            //     continue;
            // }

            $url = dirname($url);
            try {
                $v = require $file;
                $refl = new ReflectionClass($v);
                $data = $refl->getConstants();
                $url = '/'.$url;
                $pathStruct = $this->struct(
                                $url, 
                                $method, 
                                isset($data['TITLE']) ? $data['TITLE'] : '', 
                                isset($data['DESCRIPTION']) ? $data['DESCRIPTION'] : '', 
                                isset($data['HINT']) ? $data['HINT'] : '', 
                                isset($data['PARAMs']) ? $data['PARAMs'] : '', 
                                isset($data['QUERY']) ? $data['QUERY'] : '', 
                                isset($data['SCHEMAs']) ? $data['SCHEMAs'] : '');
                if (!isset($base['paths'][$url])) {
                    $base['paths'][$url] = [];
                }
                $base['paths'][$url][$method] = $pathStruct[$method];
            } catch (\Exception $e) {
                
            }

            // break;
        }
    }

    public function showdir($path){
        $dh = opendir($path);//打开目录
        while(($d = readdir($dh)) != false) {
            //逐个文件读取，添加!=false条件，是为避免有文件或目录的名称为0
            if($d=='.' || $d == '..') {//判断是否为.或..，默认都会有
                continue;
            }

            $file = $path.'/'.$d;
            if (is_file($file)) {
                $this->files[] =  $file;
            }
            if(is_dir($path.'/'.$d)) {//如果为目录
                $this->showdir($path.'/'.$d);//继续读取该目录下的目录或文件
            }
        }
    }

    public function struct($url, $method, $title, $description, $hint, $params, $query, $responses) {

        $tag = (explode('/', $url))[1] ?? '';
        if (isset((explode('/', $url))[2]) && in_array((explode('/', $url))[2][0], range('a', 'z'))) {
            $tag2 = $tag . '-' . (explode('/', $url))[2];
        } else {
            $tag2 = $tag;
        }

        if (isset((explode('/', $url))[3]) && in_array((explode('/', $url))[3][0], range('a', 'z'))) {
            $tag2 = $tag2 . '-' . (explode('/', $url))[3];
        } else {
            // $tag2 = $tag2;
        }

        if (isset((explode('/', $url))[4]) && in_array((explode('/', $url))[4][0], range('a', 'z'))) {
            $tag2 = $tag2 . '-' . (explode('/', $url))[4];
        } else {
            // $tag2 = $tag2;
        }

        $pathStruct = [
            $method => [
                'summary' => strval($title),
                'description' => strval($description),
                'tags' => [
                    0 => $tag,
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' =>
                        ['schema' => $this->schema($params)],
                    ],
                ],
                'responses' => [
                    "200" => [
                        'description' => '操作成功',
                        'content' => ['application/json' => ['schema' => $this->schema($responses, 'responses')]],
                    ],
                ],
            ],
        ];

        if ($method == 'get' || empty($pathStruct[$method]['requestBody']['content']['application/json']['schema'])) {
            unset($pathStruct[$method]['requestBody']);
        }

        if (empty($pathStruct[$method]['responses']['200']['content']['application/json']['schema'])) {
            unset($pathStruct[$method]['responses']['200']['content']);
        }

        if ($method == 'get' || $method == 'delete') {
            if (!empty($query)) {
                $pathStruct[$method]['parameters'] = $this->query($query);
            }

        }
        return $pathStruct;
    }

    public function schema($data, $tt = 'responses') {
        if (empty($data) || !is_array($data)) {
            return ['type' => 'object'];
        }

        $output = ['type' => 'object'];
        if (isset($data[0])) {
            if (!isset($data[0][0])) {
                $res = $this->schema($data[0]);
                // if (!empty($res)) {
                $output = ['type' => 'array', 'items' => $res];
                // }
            }
        } else {
            $output['properties'] = [];
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $res = $this->schema($value);
                    // if (!empty($res)) {
                    $output['properties'][$key] = $res;
                    // }
                    continue;
                }
                $vs = explode('#', $value);
                if (isset($vs[1])) {
                    $type = trim(current(explode('(', $vs[0])));
                    $type = $type == 'int' ? 'integer' : $type;
                    $type = $type == 'enum' ? 'string' : $type;
                    $type = $type == 'set' ? 'string' : $type;
                    if (in_array($type, ['string', 'integer', 'array', 'boolen', 'object', 'number'])) {
                        $output['properties'][$key] = ['type' => $type, 'example' => $type . ' ' . $vs[1]];
                    } else {
                        $output['properties'][$key] = ['type' => 'object'];
                    }
                } else {
                    $output['properties'][$key] = ['type' => 'string', 'example' => $value];
                }
            }
        }
        return $output;
    }

    public function query($data) {
        $data = isset($data[0]) ? $data[0] : $data;
        $output = [];
        if (!empty($data) && is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    // print_r($data);
                    // print_r($value);
                    continue;
                }
                $vs = explode('#', $value);
                $bool = false;
                $typeWithrIsRquire = explode('(', $vs[0]);//int(optional) #类型(1=h5,其他都表示pc)
                if (isset($typeWithrIsRquire[1]))
                    $bool = 'required' == substr($typeWithrIsRquire[1],0,-1) ? true : false ;
                $type = $typeWithrIsRquire[0];
                if (isset($vs[1])) {
                    $type = $type == 'int' ? 'integer' : $type;
                    $type = $type == 'enum' ? 'string' : $type;
                    $type = $type == 'set' ? 'string' : $type;

//                    $type = trim(current(explode('(', $vs[0])));
                    if (in_array($type, ['string', 'integer', 'array', 'boolen', 'object', 'number'])) {
                        $output[] = [
                            'name' => $key,
                            'in' => 'path',
                            'required' => $bool,
                            'description' => $vs[1],
                            'schema' => [
                                'type' => $type,
                            ],
                        ];
                    } else {
                        $output[] = [
                            'name' => $key,
                            'in' => 'path',
                            'required' => $bool,
                            'description' => $vs[1],
                            'schema' => [
                                'type' => 'string',
                            ],
                        ];
                    }
                } else {
                    $output[] = [
                        'name' => $key,
                        'in' => 'path',
                        'required' => $bool,
                        'description' => $value,
                        'schema' => [
                            'type' => 'string',
                        ],
                    ];
                }
            }
        }
        return $output;

    }
}


//$sgr = new SGR();
//$data = $sgr->initWWWApi();
//$content = yaml_emit($data, YAML_UTF8_ENCODING, YAML_ANY_BREAK);
//$content = substr($content, 3, strlen($content) - 7);
//file_put_contents(__DIR__.'/../../www-api.swagger.yaml', $content);
$sgr = new SGR();
$data = $sgr->initAdminApi();
$content = yaml_emit($data, YAML_UTF8_ENCODING, YAML_ANY_BREAK);
$content = substr($content, 3, strlen($content) - 7);
file_put_contents(__DIR__.'/../../admin-master.swagger.yaml', $content);
//$sgr = new SGR();
//$data = $sgr->initReBuild();
//$content = yaml_emit($data, YAML_UTF8_ENCODING, YAML_ANY_BREAK);
//$content = substr($content, 3, strlen($content) - 7);
//file_put_contents(__DIR__.'/../../rebuild.swagger.yaml', $content);
