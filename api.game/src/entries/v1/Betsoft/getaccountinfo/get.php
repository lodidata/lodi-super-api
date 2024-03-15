<?php

use Utils\Game\Action;
use Logic\Game\GameApi;

return new class extends Action
{
    const TITLE = "bsg游戏验证用户";
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
                    'USERID' => $params['userId'],
                    'HASH'  => $params['hash']
                ],
                'TIME'     => date('Y-m-d H:i:s'),
                'RESPONSE' => []
            ]
        ];
        date_default_timezone_set($default_timezone);


        GameApi::addElkLog(['getaccountinfo' => $params], 'BSG');
        if (!isset($params['userId'])  || empty($params['hash'])) {
            $return['EXTSYSTEM']['RESPONSE'] = [
                'CODE' => 399,
                'RESULT' => 'Internal Error'
            ];
        } else {
            $tid=intval(ltrim($params['userId'], 'game'));
            if (!$tid) {
                $return['EXTSYSTEM']['RESPONSE'] = [
                    'CODE' => 399,
                    'RESULT' => 'Internal Error1'
                ];
            } else {
                $www_notify = \DB::table('customer_notify')
                                 ->where('customer_id', $tid)
                                 ->where('status', 'enabled')
                                 ->value('www_notify');
                if (empty($www_notify)) {
                    $this->logger->error('BSG/getaccountinfo error 未找到tid:' . $tid);
                    $return['EXTSYSTEM']['RESPONSE'] = [
                        'CODE' => 399,
                        'RESULT' => 'Internal Error2'
                    ];
                } else {
                    //推送消息
                    $url = rtrim($www_notify, '/') . '/game/third/bsg/getaccountinfo';
                    $api_verify_token = $this->ci->get('settings')['app']['api_verify_token'];
                    $api_token = $api_verify_token . date("Ymd");
                    $res = \Utils\Curl::post($url, '', $params, '', true, ['api-token:' . $api_token]);
                    if ($res['status'] == 200) {
                        $return = json_decode($res['content'],true);
                    } else {
                        $this->logger->error('BSG/getaccountinfo error', $res);
                        $return['EXTSYSTEM']['RESPONSE'] = [
                            'CODE' => 399,
                            'RESULT' => 'Internal Error3'
                        ];
                    }
                }
            }
        }
        $return=\Utils\Utils::data_to_xml($return);
        $this->logger->debug('BSG:getaccountinfo', ['getaccountinfo' => $params, 'return' => $return]);
        GameApi::addElkLog(['getaccountinfo' => $params, 'return' => $return], 'BSG');
        return $this->response->withStatus(200)
                              ->withHeader('Content-Type', 'application/xml')
                              ->write($return);
    }
};