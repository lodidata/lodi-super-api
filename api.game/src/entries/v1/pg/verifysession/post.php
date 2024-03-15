<?php

use Utils\Game\Action;
use Logic\Game\GameApi;

return new class extends Action {
    const TITLE = "PG游戏玩家验证回调";
    const TAGS = '游戏';
    const DESCRIPTION = "";
    const QUERY = [
        'trace_id'  => 'string(required) #请求的唯一标识符（GUID）URL 参数',
        'operator_token' => 'string(required) #运营商独有的身份识别',
        'secret_key'    => 'string(required) #PGSoft 与运营商之间共享密码',
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
        list($tid, $uid) = explode('-', $params['custom_parameter'] ?? '');
        $tid = intval($tid);
        if($tid == 0 || !isset($params['custom_parameter']) || empty($params['custom_parameter']) || !isset($params['operator_token']) || !isset($params['secret_key'])){
            $return['error'] = [
                'code' => "1034",
                'message' => '无效请求'
            ];
        }else{
            $gameClass = new Logic\Game\Third\PG($this->ci);
            $config = $gameClass->initConfigMsg('PG');
            if ($params['operator_token'] != $config['cagent'] || $params['secret_key'] != $config['des_key']){
                $return['error'] = [
                    'code' => "1204",
                    'message' => '无效运营商'
                ];
            }else{
                $www_notify = \DB::table('customer_notify')
                    ->where('customer_id', $tid)
                    ->where('status', 'enabled')
                    ->value('www_notify');
                if(empty($www_notify)){
                    $return['error'] = [
                        'code' => "1200",
                        'message' => '内部服务器错误'
                    ];
                }else{
                    //推送消息
                    $url = rtrim($www_notify, '/').'/game/third/pg/verifysession';
                    $api_verify_token = $this->ci->get('settings')['app']['api_verify_token'];
                    $api_token = $api_verify_token.date("Ymd");
                    $res = \Utils\Curl::post($url, '', $params, '', true, ['api-token:'.$api_token]);
                    if($res['status'] == 200){
                        $return = json_decode($res['content'], true);
                    }else{
                        $return['error'] = [
                            'code' => "1200",
                            'message' => '内部服务器错误'
                        ];
                    }
                }
            }
        }

        $this->logger->debug('pg:VerifySession', ['VerifySession' => $params, 'return' => $return]);
        GameApi::addElkLog(['VerifySession' => $params, 'return' => $return], 'PG');
        return $this->response->withStatus(200)
            ->withHeader('Content-Type', 'application/json')
            ->withJson($return);
    }

};