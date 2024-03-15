<?php
use Logic\Admin\BaseController;

//获取后台客服链接
return new class extends BaseController {
    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];

    public function run($nodeId=701) {
        $data = [
            'thirdPartyId' => $this->playLoad['uid'],
            'nickname' => $this->playLoad['nick'],
            'nodeId' => (int)$nodeId,
        ];
        $str = '{';
        foreach ($data as $key => $val){
            if(in_array($key,['nodeId','thirdPartyId'])){
                $str .= '"' . $key .'":' . $val . ',';
            }else {
                $str .= '"' . $key . '":"' . $val . '",';
            }
        }
        $str = rtrim($str,',');
        $str .='}';
        $data['sign'] = strtoupper(md5($str));
        $href = 'https://kf.willled.com/service/login';
//        $href = 'https://kf.gztonban.com/service/login';
        $res = $this->curlPost($href, $data);
        $data = json_decode($res,true);
        if(isset($data['data']['rand'])){
            return ['url' =>'http://admin.baowangys.com/#/serviceWorkbench?rand='.$data['data']['rand']];
        }else{
            return $this->lang->set(11020,[$res]);
        }
    }

    public function curlPost($url,$data){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        return $response;
    }
};