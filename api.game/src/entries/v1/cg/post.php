<?php

use Logic\Game\GameApi;
use Utils\Game\Action;

return new class extends Action
{
    const TITLE = "CG用户授权回调";
    const TAGS = '游戏';
    const DESCRIPTION = "";
    const QUERY = [
        'version'  => 'string(required) #1.0，此为固定值',
        'channelId' => 'string(required) #此为前面启动游戏 URL 中所带入的 channelId',
        'data' => "string(required) #加密后的结果",
    ];

    public function run()
    {
        $gameClass = new Logic\Game\Third\CG($this->ci);
        $config = $gameClass->initConfigMsg('CG');

        $error = 0;
        $token = [];
        $data = [];
        $params = $this->request->getParams();
        if (!isset($params['channelId']) || $params['channelId'] != $config['cagent']) {
            $error = 112;//平台(渠道号)错误
        }else{
            $data = $gameClass->aes256CbcDecrypt($params['data'], $config);
            $data = json_decode($data, true);
            $token = $data['token'] ? json_decode($data['token'], true) : '';
            if (empty($token) || !isset($token['accountId']) || empty($token['accountId']) || !isset($token['user_id']) || empty($token['user_id'])) {
                $error = 110;
            } else {
                $tid = intval(ltrim($token['accountId'], 'game'));
                $www_notify = \DB::table('customer_notify')
                    ->where('customer_id', $tid)
                    ->where('status', 'enabled')
                    ->value('www_notify');
                if (empty($www_notify)) {
                    $error = 3; //系统错误
                } else {
                    //推送消息
                    $url = rtrim($www_notify, '/') . '/game/third/cg';
                    $api_verify_token = $this->ci->get('settings')['app']['api_verify_token'];
                    $api_token = $api_verify_token.date("Ymd");
                    $res = \Utils\Curl::post($url, '', $params, '', true, ['api-token:'.$api_token]);
                    if ($res['status'] == 200) {
                        $data2 = $gameClass->aes256CbcDecrypt($res['content'], $config);
                        $res_data = json_decode($data2, true);
                        $error = $res_data['errorCode'];
                    } else {
                        $error = 3;
                    }
                }
            }
        }

        $msg = [
            'channelId' => $config['cagent'],
            'accountId' => $token['accountId'] ?? '',
            'nickName' => $token['accountId'] ?? '',
            'errorCode' => $error
        ];
        $return_data = $gameClass->aes256CbcEncrypt($msg, $config);
        $params['data'] = urlencode($params['data']);
        $params['aes_data'] = $data;
        $this->logger->debug('CG:verifyToken', ['params' => $params, 'return' => $msg, 'aes_return' => urlencode($return_data)]);
        GameApi::addElkLog(['verifyToken' => $params, 'return' => $msg, 'aes_return' => urlencode($return_data)], 'CG');
        echo $return_data;
        die;
    }

};