<?php
use Logic\Admin\BaseController;
use Utils\Encrypt;

/**
 * 手机号码解密
 */
return new class extends BaseController
{

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        $file = $this->request->getUploadedFiles();
        if (!isset($file['file']) || empty($file['file'])) {
            return $this->lang->set(886, ['请上传文件']);
        }
        $file_obj = $file['file'];
//        if($file_obj->getClientMediaType() != 'text/csv'){
//            return $this->lang->set(886, ['请上传csv格式的文件'.$file_obj->getClientMediaType()]);
//        }
        if(stripos($file_obj->getClientFilename(), '.csv') === false){
            return $this->lang->set(886, ['请上传csv格式的文件'.$file_obj->getClientMediaType()]);
        }
//        var_dump($file_obj->getClientMediaType(), $file_obj->getClientFilename(), $file_obj->getStream());exit;
        //提取表头，查看需要解密的字段
        $user_data = file($file['file']->file);
        $header = trim($user_data[0]);//表头
        $arr = explode(",", $header);
        $pos = false;
        foreach ($arr as $k=>$val){
            if(stripos($val, 'descrypt') !== false){
                $pos = $k;
            }
        }
        if($pos === false){
            return $this->lang->set(886, ['没有需要解密标识的表头列']);
        }
        $headerlist = $arr;
        unset($user_data[0]);

        // 'app_key' => 'a94f231119afengh', // 双向加密密钥，长度16字节(字符可以是多字节)。一旦写入不可更改
        // 'app_key' => 'a94f231119a1lhgj', // 双向加密密钥，长度16字节(字符可以是多字节)。一旦写入不可更改
        // 'app_key' => 'a94f231119a1wyzs', // 双向加密密钥，长度16字节(字符可以是多字节)。一旦写入不可更改
        // 'app_key' => 'a94f231119a1wwys', // 网易商投 双向加密密钥，长度16字节(字符可以是多字节)。一旦写入不可更改
        $encrypt = new Encrypt('a94f231119a1xmcp');//熊猫密钥
//        $encrypt = new Encrypt('a94f231119afengh');//凤凰密钥
//        $encrypt = new Encrypt('a94f231119a10dag');//大g 密钥
//        $encrypt = new Encrypt('a94f231119a1xycp');//幸运cp
//        $encrypt = new Encrypt('a94f231119a10ytx');//赢天下
//        $encrypt = new Encrypt('a94f231119a1wyyb');//网易易盈宝和信安易盈宝
        $tmp = [];
        foreach ($user_data as $k=>$data) {
            $data = trim($data);
            $parr = explode(",", $data);
            $parr[$pos] = trim($parr[$pos]);
            if(!empty($parr[$pos])){
                $parr[$pos] = $encrypt->decrypt($parr[$pos]);
            }
            foreach ($parr as $key=>$pdata){
                $tmp[$headerlist[$key]] = $pdata;
            }
            $csv_str[] = $tmp;
            unset($tmp);
        }
//        $csv_file = date('Ymd').'-'.$file_obj->getClientFilename().'.csv';
//        file_put_contents($csv_file, $csv_str);
        return $this->lang->set(0, [], $csv_str);
    }
};