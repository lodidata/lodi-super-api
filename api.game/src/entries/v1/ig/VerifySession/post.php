<?php

use Utils\Game\Action;
use Logic\Game\GameApi;

return new class extends Action
{
    const TITLE = "IG游戏玩家验证回调";
    const TAGS = '游戏';
    const DESCRIPTION = "";
    const QUERY = [
        'trace_id' => 'string(required) #请求的唯一标识符（GUID）URL 参数',
        'operator_token' => 'string(required) #运营商独有的身份识别',
        'secret_key' => 'string(required) #PGSoft 与运营商之间共享密码',
        'operator_player_session' => "string(required) #运营商系统生成的令牌",
        'ip' => "string() #玩家 IP 地址",
        "custom_parameter" => 'string() #URL scheme18中的operator_param 值',
        'game_id' => 'string() #游戏的独有代码',
    ];

    public function run()
    {
        $return = [
            'data' => null,
            'error' => null
        ];
        $params = $this->request->getParams();
        GameApi::addElkLog(['VerifySession' => $params], 'IG');
        if (!isset($params['operator_token']) || !isset($params['secret_key']) || !isset($params['custom_parameter']) || empty($params['custom_parameter']) || !@hex2bin($params['custom_parameter'])) {
            $msg = [
                'error' => [
                    'code' => 701,
                    'message' => 'custom_parameter exception'
                ],
                'data' => null,
            ];
        } else {
            list($tid, $uid) = explode('-', @hex2bin($params['custom_parameter']));
            $tid = intval($tid);
            if (!$tid) {
                $msg = [
                    'error' => [
                        'code' => 701,
                        'message' => 'custom_parameter exception'
                    ],
                    'data' => null,
                ];
            } else {
                $gameClass = new Logic\Game\Third\IG($this->ci);
                $config = $gameClass->initConfigMsg('IG');
                if ($params['operator_token'] != $config['key'] || $params['secret_key'] != $config['pub_key']) {
                    $return['error'] = [
                        'code' => 1006,
                        'message' => '无效运营商'
                    ];
                } else {
                    $www_notify = \DB::table('customer_notify')
                        ->where('customer_id', $tid)
                        ->where('status', 'enabled')
                        ->value('www_notify');
                    if (empty($www_notify)) {
                        $this->logger->error('IG/VerifySession error 未找到tid:' . $tid);
                        $return['error'] = [
                            'code' => 900,
                            'message' => '内部服务器错误'
                        ];
                    } else {
                        //推送消息
                        $url = rtrim($www_notify, '/') . '/game/third/ig/VerifySession';
                        $api_verify_token = $this->ci->get('settings')['app']['api_verify_token'];
                        $api_token = $api_verify_token . date("Ymd");
                        $res = \Utils\Curl::post($url, '', $params, '', true, ['api-token:' . $api_token]);
                        if ($res['status'] == 200) {
                            $return = json_decode($res['content'], true);
                        } else {
                            $this->logger->error('IG/VerifySession error', $res);
                            $return['error'] = [
                                'code' => 900,
                                'message' => '内部服务器错误2'
                            ];
                        }
                    }
                }
            }
        }
        $this->logger->debug('pg:VerifySession', ['VerifySession' => $params, 'return' => $return]);
        GameApi::addElkLog(['VerifySession' => $params, 'return' => $return], 'IG');
        return $this->response->withStatus(200)
            ->withHeader('Content-Type', 'application/json')
            ->withJson($return);

    }
};