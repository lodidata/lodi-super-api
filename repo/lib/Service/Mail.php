<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/4/27
 * Time: 15:49
 */

namespace Lib\Service;

//use PHPMailer;

include_once __DIR__.'/../../vendor/phpmailer/phpmailer/class.phpmailer.php';
include_once __DIR__.'/../../vendor/phpmailer/phpmailer/PHPMailerAutoload.php';
class Mail
{

    const MANIFEST = [
        'mail.config.add'    => 'core',
        'mail.config.update' => 'core',
        'mail.config.info'   => 'core',
        'template.list'      => 'common',
        'template.update'    => 'common',
        'template.info'      => 'common'
    ];


    private static $instance;

    //标识符，被标识符包裹的内容为需要替换的地方
    public $identifier = '%';

    protected $module;

    private $config;
    private $mailer;

    public function __construct($con = [])
    {

        if (empty($this->mailer)) {
            $this->mailer = new \PHPMailer();
        }

        if(empty($this->config)){
            $this->config = $this->getConfig($con);
        }
    }
    private function __clone(){

     }

     static public function getInstance(){
                     //判断$instance是否是Uni的对象
                 //没有则创建
         if (!self::$instance instanceof self) {
                     self::$instance = new self();
         }
         return self::$instance;

     }


    /**
     * 获取配置邮件配置信息（平台和厅主通用）
     *
     * @return array
     * @todo 动态读取配置数据
     */
    public function getConfig($con = [])
    {
        $config = $con ? $con : \DB::table('mail_config')
                            ->selectRaw('mailhost,mailport,mailname,mailpass,mailaddress,is_ssl')
                            ->first();
        return (array) $config;
    }


    /**
     * 发送普通邮件
     *
     * @param array $data 参数数组，包含接收用户，邮件标题，邮件内容等等
     * 示例：$data = [
     * 'users'=>[
     * [
     * 'mail'=>'wjy@szxlzkj.com',
     * 'name'=>'wjy',
     *
     * ]
     * ],
     * 'hyper_text'=>1,
     * 'title'=>'测试',
     * 'content'=>'<p>车市坚实的回房间黑科技<br>sdfsdfsdf</p>'
     *
     * ];
     * @return (PHPMailer->ErrorInfo) or true;
     */
    public function sendMail(array $data)
    {
        return $this->smtpSend($data);
    }

    /**
     * 发送单个用户
     * @param array $data
     * @return bool
     * @throws \phpmailerException
     */
    public function sendOneUser($data=[])
    {

        $config = $this->config;
        $mail   = $this->mailer;
//        print_r($config);exit;

        $config['fromname'] = isset($config['fromname']) && !empty($config['fromname']) ? $config['fromname'] : $config['mailaddress'];
//        $mail               = $this->getmailer();
        // 禁止输出调试信息
        $mail->SMTPDebug    = 0;
        $mail->isSMTP();
        $host = $config['mailhost'];
        if (stripos($host, '@gmail') !== false) {
            date_default_timezone_set('Etc/UTC');
            $mail->Host = gethostbyname('smtp.gmail.com');
        } else {
            $mail->Host = $host;
        }

        $mail->SMTPAuth   = true;
        // fixme 好像现在只有gmail支持tls？
        $mail->SMTPSecure = $config['is_ssl'] ? 'ssl' : (stripos($host, 'gmail') !== false ? 'tls' : null);
        $mail->Port       = $config['mailport'];
        $mail->Username   = $config['mailname'];
        $mail->Password   = $config['mailpass'];
        $mail->setFrom($config['mailaddress'], $config['fromname']);


        $mail->addAddress($data['email'], $data['name']);

        if ($data['hyper_text']) {
            $mail->isHTML(true);
        }
        $mail->Subject = $data['title'];
        $mail->Body    = $data['content'];

        if (!$mail->send()) {
            print_r($mail->ErrorInfo);
            return false;
        } else {
            echo "发送成功";
            return true;
        }
    }

    /**
     * 发送模板邮件
     *
     * @param array  $users 接收人二维数组 示例： [['mail'=>'111@qq.com','name'=>'111'],['mail'=>'222@qq.com','name'=>'222']]
     * @param array  $replaces 替换内容键值对,键为模板邮件中的索引， 示例：['name'=>'姓名','company'=>'***公司']
     * @param string $code 模板code
     * @return @return (PHPMailer->ErrorInfo) or true or false;
     */
    public function sendTMail(array $users, array $replaces, string $code)
    {
        $maildate = $this->_('template.info', ['code' => $code]);

        if (!$maildate) {
            return false;
        }

        foreach ($replaces as $key => $replace) {
            $maildate['content'] = str_replace("%$key%", $replace, $maildate['content']);
        }

        $data = [
            'users'      => $users,
            'hyper_text' => $maildate['hyper_text'],
            'title'      => $maildate['title'],
            'content'    => $maildate['content'],
        ];

        return $this->smtpSend($data);
    }

    /**
     * smtp普通邮件
     *
     * @param array $data 参数数组，包含接收用户，邮件标题，邮件内容等等
     * 示例：
     * $data = [
     * 'users'=>[
     * [
     * 'mail'=>'111@qq.com',
     * 'name'=>'111',
     * ]
     * ],
     * 'hyper_text'=>1,
     * 'title'=>'测试',
     * 'content'=>'<p>车市坚实的回房间黑科技<br>sdfsdfsdf</p>'
     *
     * ];
     * @return (PHPMailer->ErrorInfo) or true;
     */
    public function smtpSend(array $data)
    {
        $config = $this->config;
        $mail   = $this->mailer;

//        $config['fromname'] = $config['fromname'] ? $config['fromname'] : $config['mailaddress'];
//        $mail               = $this->getmailer();
        // 禁止输出调试信息
        $mail->Charset = 'UTF-8';
        $mail->SMTPDebug    = 0;
        $mail->isSMTP();
        $host = $config['mailhost'];
        if (stripos($host, '@gmail') !== false) {
            date_default_timezone_set("Asia/Shanghai");
//            date_default_timezone_set('Etc/UTC');
            $mail->Host = gethostbyname('smtp.gmail.com');
        } else {
            $mail->Host = $host;
        }

        $mail->SMTPAuth   = true;
        // fixme 好像现在只有gmail支持tls？
        $mail->SMTPSecure = $config['is_ssl'] ? 'ssl' : (stripos($host, 'gmail') !== false ? 'tls' : null);
        $mail->Port       = $config['mailport'];
        $mail->Username   = $config['mailname'];
        $mail->Password   = $config['mailpass'];
        $mail->setFrom($config['mailaddress'], $config['mailaddress']);

        foreach ($data['users'] as $user) {
            $mail->addAddress($user['mail'], $user['name']);
        }
        if ($data['hyper_text']) {
            $mail->isHTML(true);
        }
        $mail->Subject = '=?UTF-8?B?' . base64_encode($data['title']) . '?=';
//        $mail->Subject = $data['title'];
        $mail->Body    = $data['content'];

        if (!$mail->send()) {
            return $mail->ErrorInfo;
        } else {
            return true;
        }
    }





    /**
     * 邮件模板列表
     *
     * @param array $params 包含页码和条数信息，['page'=>1,'page_size'=>2,];
     * @return 参数错误缺失返回 FALSE 正确返回数据集
     */
    public function templateList(array $params)
    {
        if (!isset ($params ['page']) || !isset ($params ['page_size'])) {
            return false;
        }

        return $this->_('template.list', null, [
            $params
        ]);
    }

    /**
     * 邮件模板编辑
     *
     * @param int $id 数据ID
     * @param     int $$hyper_text 超文本格式(0否，1是)
     * @param     int string $title 标题
     * @param     int string $content 内容
     * @return 参数错误缺失返回 FALSE 正确返回数据集
     */
    public function templateUpdate(int $id, int $hyper_text, string $title, string $content)
    {
        return $this->_('template.update', [
            'id'         => $id,
            'hyper_text' => $hyper_text,
            'title'      => $title,
            'content'    => $content,
            'time'       => time()
        ]);
    }

    /**
     * 邮件配置详情
     */
    public function mailConfigInfo()
    {
        return $this->_('mail.config.info');
    }

    /**
     * 新增/编辑公司邮件服务器配置
     *
     * @params array $params [
     * 'hall_id'=>'1',
     * 'fromname'=>'大发娱乐888',
     * 'mailhost'=>'SMTP服务器地址',
     * 'mailport'=>'SMTP服务器端口',
     * 'mailname'=>'服务器登录账号',
     * 'mailpass'=>'密码',
     * 'mailaddress'=>'电子邮箱地址',
     * 'verification'=>'是否需要身份验证',
     * 'is_ssl'=>'是否SSL加密',
     * ];
     * @return int
     */
    public function mailConfigEdit($params)
    {
        $data = $this->_('mail.config.info');
        if ($data == null) {
            return $this->mailConfigAdd($params);
        } else {
            return $this->mailConfigUpdate($params);
        }
    }

    /**
     * 添加公司邮件服务器配置
     *
     * @params array $params [
     * 'hall_id'=>'1',
     * 'fromname'=>'大发娱乐888',
     * 'mailhost'=>'SMTP服务器地址',
     * 'mailport'=>'SMTP服务器端口',
     * 'mailname'=>'服务器登录账号',
     * 'mailpass'=>'密码',
     * 'mailaddress'=>'电子邮箱地址',
     * 'verification'=>'是否需要身份验证',
     * 'is_ssl'=>'是否SSL加密',
     * ];
     * @return int
     */
    public function mailConfigAdd($params)
    {
        $params['created'] = time();
        $params['updated'] = $params['created'];

        return $this->_('mail.config.add', null, [$params]);
    }

    /**
     * 平台
     * 更新公司邮件服务器配置
     *
     * @params array $params [
     * 'mailhost'=>'SMTP服务器地址',
     * 'mailport'=>'SMTP服务器端口',
     * 'mailname'=>'服务器登录账号',
     * 'mailpass'=>'密码',
     * 'mailaddress'=>'电子邮箱地址',
     * 'verification'=>'是否需要身份验证',
     * 'is_ssl'=>'是否SSL加密',
     * ];
     * @return int
     */
    public function mailConfigUpdate(array $params)
    {
        $params['updated'] = time();

        return $this->_('mail.config.update', null, [$params]);
    }

}