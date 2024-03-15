<?php
// 引入鉴权类
use Qiniu\Auth;
// 引入上传类
use Qiniu\Storage\UploadManager;

use OSS\OssClient;
use OSS\Core\OssException;

use Utils\Www\Action;
return new class extends Action {


    public function run() {
        if (empty($_FILES) || !isset($_FILES['file'])) {
            return $this->lang->set(10010);
        }
        $settings = $this->ci->get('settings')['upload'];
        $res = '';
        $fileName = $this->getUploadName($_FILES['file']);
        foreach ($settings['dsn'] as $obj => $config) {
            $temp = $this->$obj($config, $_FILES['file'], $fileName);
            if ($settings['useDsn'] == $obj) {
                $res = $temp;
            }
        }
       // $this->remve($_FILES['file']);
        return empty($res) ? $this->lang->set(10015) : $res;
    }

    /**
     * 七牛上传
     * @param  [type] $config [description]
     * @param  [type] $file   [description]
     * @return [type]         [description]
     */
    protected function qiniu($config, $file, $fileName) {
        // 构建鉴权对象
        $auth = new Auth($config['accessKey'], $config['secretKey']);
        // 生成上传 Token
        $token = $auth->uploadToken($config['bucket']);
        $key = $config['dir'].'/'.$fileName;
        // 初始化 UploadManager 对象并进行文件的上传。
        $uploadMgr = new UploadManager();
        // 调用 UploadManager 的 putFile 方法进行文件的上传。
        list($ret, $err) = $uploadMgr->putFile($token, $key, $file['tmp_name']);
        if ($err !== null) {
            return $this->lang->set(15, [], [], ['error' => 'qiniu:'.print_r($err, true)]);
        } else {
            return $this->lang->set(0, [], ['url' => $config['domain'].'/'.$key]);
        }
    }

    /**
     * 阿里云OSS上传
     * @param  [type] $config [description]
     * @return [type]         [description]
     */
    protected function oss($config, $file, $fileName) {
        try {
            $ossClient = new OssClient($config['accessKeyId'], $config['accessKeySecret'], $config['endpoint']);
        } catch (OssException $e) {
            return $this->lang->set(15, [], [], ['error' => 'oss":'.$e->getMessage()]);
        }

        try {
            $object = $config['dir'].'/'.$fileName;
            $content = file_get_contents($file['tmp_name']);
            $ossClient->putObject($config['bucket'], $object, $content);
            return $this->lang->set(0, [], ['url' => $config['domain'].'/'.$object]);
        } catch (OssException $e) {
            return $this->lang->set(15, [], [], ['error' => 'oss":'.$e->getMessage()]);
        }
    }

    /**
     * 取得上传后文件名称
     * @param  [type] $fileName [description]
     * @return [type]           [description]
     */
    protected function getUploadName($file) {
        $temp = explode('.', $file['name']);
        $fileExt = strtolower(end($temp));
        return md5(time().mt_rand(0, 999999)).'.'.$fileExt;
    }

    /**
     * 移除临时文件
     * @param  [type] $file [description]
     * @return [type]       [description]
     */
    protected function remove($file) {
        @unlink($file['tmp_name']);
    }
};
