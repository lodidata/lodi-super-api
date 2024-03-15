<?php

use Utils\Game\Action;
use Logic\Game\GameApi;

return new class extends Action {
    const TITLE = "NG游戏玩家余额验证回调";
    const TAGS = '游戏';
    const DESCRIPTION = "";
    const QUERY = [

    ];

    public function run()
    {
        $return = [
            'data' => null,
            'error' => null
        ];

        $gameClass = new Logic\Game\Third\NG($this->ci);
        $config = $gameClass->initConfigMsg('NG');

        $params = $this->request->getParams();
        if (!isset($params['data']['playerToken']) || !isset($params['data']['gameCode']) || !isset($params['data']['brandCode']) || !isset($params['data']['groupCode'])) {
            $return['error'] = [
                'code' => 1034,
                'message' => '无效请求'
            ];
        }

        if (!isset($_SERVER['HTTP_X_SIGNATURE']) || $_SERVER['HTTP_X_SIGNATURE'] != $this->generateSignature($params['data'], $config['key'])) {
            $return['error'] = [
                'code' => 1200,
                'message' => '签名校验错误'
            ];
        }

        if (isset($params['dataHash']) && $params['dataHash'] != $this->hashSha256($params['data'])) {
            $return['error'] = [
                'code' => 1200,
                'message' => '数据校验错误'
            ];
        }

        $lobby = json_decode($config['lobby'], true);

        if ($params['data']['brandCode'] != $lobby['brandCode'] || $params['data']['groupCode'] != $lobby['groupCode']) {
            $return['error'] = [
                'code' => 1204,
                'message' => '无效运营商'
            ];
        } else {
            $tid = intval(ltrim($params['data']['playerToken'], 'game'));
            $www_notify = \DB::table('customer_notify')
                ->where('customer_id', $tid)
                ->where('status', 'enabled')
                ->value('www_notify');
            if (empty($www_notify)) {
                $return['error'] = [
                    'code' => 1200,
                    'message' => '内部服务器错误'
                ];
            } else {
                //推送消息
                $url = rtrim($www_notify, '/').'/game/third/ng/verifysession';
                $api_verify_token = $this->ci->get('settings')['app']['api_verify_token'];
                $api_token = $api_verify_token . date("Ymd");
                $res = \Utils\Curl::post($url, '', $params, '', true, ['api-token:' . $api_token]);
                if ($res['status'] == 200) {
                    $return = json_decode($res['content'], true);
                } else {
                    $return['error'] = [
                        'code' => 1200,
                        'message' => '内部服务器错误2'
                    ];
                }
            }
        }

        $this->logger->debug('ng:VerifySession', ['VerifySession' => $params, 'return' => $return]);
        GameApi::addElkLog(['VerifySession' => $params, 'return' => $return], 'NG');

        return $this->response->withStatus(200)
            ->withHeader('Content-Type', 'application/json')
            ->withJson($return);
    }

    /**
     * 生成signature
     * @param array $data
     * @throws \Exception
     */
    public function generateSignature($data, $key)
    {
        return hash_hmac('sha256', utf8_encode(json_encode($data)), utf8_encode($key), false);
    }

    public function hashSha256($data)
    {
        return hash("sha256", utf8_encode(json_encode($data)));
    }

};