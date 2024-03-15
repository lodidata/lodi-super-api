<?php

use Utils\Game\Action;
use Logic\Game\GameApi;

return new class extends Action {
    const TITLE = "STG游戏获取用户信息";
    const TAGS = 'STG游戏';
    const DESCRIPTION = "";
    const QUERY = [
        'PartnerId'  => 'int(required) #Digitain系统中合作伙伴的标识符',
        'TimeStamp' => 'string(required) #时间',
        'Token'    => 'string(required) #Token',
        'Signature' => "string(required) #加密",
    ];
    public function run()
    {
        $gameClass = new Logic\Game\Third\STG($this->ci);
        $config = $gameClass->initConfigMsg('STG');

        $method = 'GetUserInfo';
        $return = [];
        $code = '';
        $params = $this->request->getParams();
        if(!isset($params['Token'])  || empty($params['Token']) || !isset($params['TimeStamp'])  || empty($params['TimeStamp']) || !isset($params['Signature'])  || empty($params['Signature'])  || !isset($params['PartnerId'])  || empty($params['PartnerId'])) {
            $code = 1013;
        }elseif($params['PartnerId'] != $config['cagent']) {
            $code = 70;
        }else{
            $tid = intval(ltrim(@hex2bin($params['Token']), 'game'));
            if($tid==0){
                $code = 37;
            }else{
                $sign = $params['Signature'];
                $md5Keys = ['PartnerId','TimeStamp','Token'];
                if ($sign != $gameClass->Signature($method, $params, $md5Keys, $config['key'])){
                    $code = 1016;
                }else{
                    $www_notify = \DB::table('customer_notify')
                        ->where('customer_id', $tid)
                        ->where('status', 'enabled')
                        ->value('www_notify');
                    if(empty($www_notify)){
                        $code = 37;
                    }else{
                        //推送消息
                        $url = rtrim($www_notify, '/').'/game/third/igsports/'.$method;
                        $api_verify_token = $this->ci->get('settings')['app']['api_verify_token'];
                        $api_token = $api_verify_token.date("Ymd");
                        $res = \Utils\Curl::post($url, '', $params, '', true, ['api-token:'.$api_token]);
                        if($res['status'] == 200){
                            $return = json_decode($res['content'], true);
                        }else{
                            $code = 500;
                        }
                    }
                }
            }
        }
        if(empty($return)){
            $return = [
                'ResponseCode' => $code,
                "Description" => $gameClass->getErrorMessage($code),
                'TimeStamp' => time(),
                'Token' => $params['Token'],
                'ClientId' => 0,
                'CurrencyId' => 'PHP',
                'FirstName' => '',
                'LastName' => '',
                'Gender' => 0,
                'BirthDate' => '',
                'TerritoryId' => '',
                'AvailableBalance' => 0
            ];
            $md5Keys = ['ResponseCode','Description','TimeStamp','Token', 'ClientId', 'CurrencyId', 'FirstName', 'LastName', 'Gender', 'BirthDate'];
            $return['Signature'] = $gameClass->Signature($method, $return, $md5Keys, $config['key']);
        }

        GameApi::addElkLog(['method' => $method, 'params' => $params, 'return' => $return], 'STG');
        return $this->response->withStatus(200)
            ->withHeader('Content-Type', 'application/json')
            ->withJson($return);
    }

};