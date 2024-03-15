<?php
use Utils\Work\Action;
use Qiniu\Auth;
use Qiniu\Http\Client;

/**
 * 签名
 */
return new class extends Action{

    public function run(){
        if(!$_REQUEST){
            $_REQUEST = json_decode(file_get_contents('php://input'),true);
        }
        $action = $_REQUEST['action'];
        try{
            echo $this->$action();
        }catch (\Exception $e){
            print_r($e->getMessage());
            echo 'error';
        }
        die();
    }

    //已丢弃，相关功能移到post里面
    function modifySign(){
        $res = ['code' => 0,'msg'=>'修改成功'];
        if($this->isSignLogin() && isset($_REQUEST['customer']) && isset($_REQUEST['way']) && in_array($_REQUEST['way'],['superSign','businessSign'])){
            $customer = $_REQUEST['customer'];
            if(empty($customer)){
                return 'error';
            }
            $customer_data = \DB::table('sign')->where('code', $customer)->first();
            if(empty($customer_data)){
                return 'error';
            }
            $before_data = [
                'way'=>$customer_data->way,//签名类型
                'channel'=>$customer_data->channel,//渠道列表
            ];
            $after_data = [
                'way'=>$_REQUEST['way'],//签名类型
                'channel'=>isset($_REQUEST['channel']) ? $_REQUEST['channel'] : '',//渠道列表
            ];

            //superSign超级签，businessSign企业签
            \DB::table('sign')->where('code',$_REQUEST['customer'])->update([
                'way'=>$_REQUEST['way'],
                'channel'=>isset($_REQUEST['channel']) ? $_REQUEST['channel'] : '',
            ]);
            \DB::table('sign_log')->insert([
                'admin'=>$this->redis->get('SignUserLoginToken:'.$_REQUEST['token']),
                'code'=>$customer_data->code,
                'name'=>$customer_data->name,
                'log'=>json_encode(['before_data'=>$before_data, 'after_data'=>$after_data]),
                'ip'=>\Utils\Client::getIp(),
            ]);
            return json_encode($res);
        }
        return 'error';
    }

    //日志
    function getSignLog(){
        if($this->isSignLogin()){
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
            $page_size = isset($_REQUEST['page_size']) ? $_REQUEST['page_size'] : 20;
            $query = \DB::table('sign_log');
            $attributes['total'] = $query->count();
            $attributes['number'] = $page;
            $attributes['size'] = $page_size;
            $result = $query->orderBy('id','desc')->forpage($page, $page_size)->get()->toArray();
            return json_encode(['attributes'=>$attributes, 'data'=>$result, 'msg'=>'操作成功', 'status'=>200]);
        }
        return 'error';
    }

    //签名列表
    function getSign(){
        //superSign超级签，businessSign企业签
        $data = \DB::table('sign')->get()->toArray();
        $res = [];
        foreach ($data as $val){
            $val = (array)$val;
            $res[$val['code']] = $val;
        }
        if(isset($_REQUEST['customer'])){
            return json_encode($res[$_REQUEST['customer']]);
        }else{
            if($this->isSignLogin()){
                return json_encode($res);
            }
            return 'error';
        }
    }

    function login()
    {
        $name = $_REQUEST['userName'];
        $pwd = $_REQUEST['password'];
        if(empty($name) || empty($pwd)){
            return json_encode(['login' => false, 'token' => '']);
        }
        $customer_data = \DB::table('sign_admin')->where('username', $name)->first();
        if (!empty($customer_data) && $customer_data->userpwd == $pwd) {  //登陆成功
            $token = md5($name.$pwd.time());
            $this->redis->setex('SignUserLogin:'.$name, 24*60*60, $token);
            $this->redis->setex('SignUserLoginToken:'.$token, 24*60*60, $name);
            \DB::table('sign_admin')->where('username', $name)->update(['last_login'=>date('Y-m-d H:i:s')]);
            return json_encode(['login' => true, 'token' => $token]);
        } else {//登陆失败
            return json_encode(['login' => false, 'token' => '']);
        }
    }

    function sendMail(){
        //图片验证码判定
        $imgCode = $this->redis->get(\Logic\Define\CacheKey::$perfix['authVCode'] . $_REQUEST['token']);
        if($imgCode != $_REQUEST['code']){
            return json_encode(['code' => -1,'msg'=>'验证码错误请']);
        }
        //特殊容易封，退邮件，所以上次发送过的模板不能连续两次
        $tmp_id = $this->redis->get('LastSendEmailTmplate:');
        $sign = (array)\DB::table('sign')->where('code', $_REQUEST['customer'])->first();
        $configs = \DB::table('mail_config')->whereIn('use', [$_REQUEST['customer'],'ALL'])->get()->toArray();
        $config = (array)array_random($configs);
        $templates = \DB::table('mail_template')
            ->where('id','!=',$tmp_id)
            ->where('scene', $config['id'])
            ->get()->toArray();
        $template = (array)array_random($templates);
        if(!$config || !$config || !isset($_REQUEST['email']) || !$sign){
            return json_encode(['code' => -1,'msg'=>'请稍后再试']);
        }
        $this->redis->setex('LastSendEmailTmplate:', 24*60*60, $template['id']);
        $mail = new \Lib\Service\Mail($config);
        $subject = str_replace('{{name}}',$sign['name'], $template['subject']);
        $content = str_replace('{{down_url}}',$sign['down_url'], $template['desc']);
        $data = [
            'users' => [[
                'mail'=> $_REQUEST['email'],
                'name'=> substr($_REQUEST['email'],0,strpos($_REQUEST['email'],'@')),
            ]],
            'hyper_text'=>1,
            'title'=> $subject,
            'content'=> $content,
        ];
        $log = [
            'use' => $_REQUEST['customer'] ?? 'ALL',
            'scene' => $config['id'],
            'from' => $config['mailname'] ? $config['mailname'] : $config['mailaddress'],
            'to' => $_REQUEST['email'],
            'subject' => $subject,
            'content' => $content,
            'created' => time(),
        ];
        $res = $mail->sendMail($data);
        $log['error'] = $res;
        if($res == 1){
            $log['status'] = 'success';
            \DB::table('mail_send_log')->insert($log);
            return json_encode(['code' => 1,'msg'=>'发送成功']);
        }
        $log['status'] = 'fail';
        \DB::table('mail_send_log')->insert($log);
        return json_encode(['code' => -1,'msg'=>'请稍后再试']);
    }

    /**
     * 获取图形验证码
     * @return string[]
     */
    public function getImageCode() {
        $length = 4;
        $img = new \Utils\ValidateCode();
        $im = $img->create($length);
        $code = $img->getCode();
        ob_start();
        imagepng($im);
        $imageData = base64_encode(ob_get_clean());
        $base64Image = 'data:image/png;base64,' . chunk_split($imageData);
        $token = md5(sha1(uniqid(\Logic\Define\CacheKey::$perfix['authVCode'])));
        $this->redis->setex(\Logic\Define\CacheKey::$perfix['authVCode'] . $token, 180, $code);
        return json_encode(['token' => $token, 'images' => $base64Image]);
    }

    //获取七牛token
    public function getQiniuToken(){
        if($this->isSignLogin()){
//            $qiniu = $this->ci->settings['upload']['dsn']['qiniu'];
            $qiniu = [
                'accessKey' => 'jflIMMkzIbnF7rfM-NJy05rz_ZvdNtu3iAC9cqXQ',
                'secretKey' => 'hzg_ethuo94jzFt8p7h1Rz8YL102yUZQat_3kliI',
                'bucket' => 'bg',
            ];//download.tgqc9.com的七牛云配置
            $auth = new Auth($qiniu['accessKey'], $qiniu['secretKey']);

            $expires = 3600;
            $policy  = null;
            $upToken = $auth->uploadToken($qiniu['bucket'], null, $expires, $policy, true);
            return $upToken;
        }
        return 'error';
    }
};