<?php

use Utils\Game\Action;
use Logic\Game\GameApi;

return new class extends Action
{
    const TITLE = "bsg游戏玩家验证回调";
    const TAGS = '游戏';
    const DESCRIPTION = "";
    const QUERY = [];

    public function run()
    {
        $params = $this->request->getParams();

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT");
        $return = [
            'EXTSYSTEM' => [
                'REQUEST'  => [
                    'TOKEN' => $params['token'],
                    'HASH'  => $params['hash']
                ],
                'TIME'     => date('Y-m-d H:i:s'),
                'RESPONSE' => []
            ]
        ];
        date_default_timezone_set($default_timezone);

        GameApi::addElkLog(['VerifySession' => $params], 'BSG');
        if (!isset($params['token'])  || empty($params['hash']) || !@hex2bin($params['token'])) {
            $return['EXTSYSTEM']['RESPONSE'] = [
                    'CODE' => 399,
                    'RESULT' => 'Internal Error'
            ];
        } else {
            list($tid, $uid) = explode('-', @hex2bin($params['token']));
            if (!$tid) {
                $return['EXTSYSTEM']['RESPONSE'] = [
                    'CODE' => 399,
                    'RESULT' => 'SYSTEM Error1'
                ];
            } else {
                $www_notify = \DB::table('customer_notify')
                                 ->where('customer_id', $tid)
                                 ->where('status', 'enabled')
                                 ->value('www_notify');
                if (empty($www_notify)) {
                    $this->logger->error('BSG/VerifySession error 未找到tid:' . $tid);
                    $return['EXTSYSTEM']['RESPONSE'] = [
                        'CODE' => 399,
                        'RESULT' => 'SYSTEM Error2'
                    ];
                } else {
                    //推送消息
                    $url = rtrim($www_notify, '/') . '/game/third/bsg/VerifySession';
                    $api_verify_token = $this->ci->get('settings')['app']['api_verify_token'];
                    $api_token = $api_verify_token . date("Ymd");
                    $res = \Utils\Curl::post($url, '', $params, '', true, ['api-token:' . $api_token]);
                    if ($res['status'] == 200) {
                        $return = json_decode($res['content'],true);
                    } else {
                        $this->logger->error('BSG/VerifySession error', $res);
                        $return['EXTSYSTEM']['RESPONSE'] = [
                            'CODE' => 399,
                            'RESULT' => 'SYSTEM Error3'
                        ];
                    }
                }
            }
        }
        $return=\Utils\Utils::data_to_xml($return);
        $this->logger->debug('BSG:VerifySession', ['VerifySession' => $params, 'return' => $return]);
        GameApi::addElkLog(['VerifySession' => $params, 'return' => $return], 'BSG');
        return $this->response->withStatus(200)
                              ->withHeader('Content-Type', 'application/xml')
                              ->write($return);
    }
};