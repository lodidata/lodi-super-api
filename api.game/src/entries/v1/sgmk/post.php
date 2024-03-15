<?php

use Logic\Game\GameApi;
use Utils\Game\Action;

return new class extends Action
{
    const TITLE = "SGMK用户授权回调";
    const TAGS = '游戏';
    const DESCRIPTION = "";
    const QUERY = [
        'acctId' => 'string(required) #游戏玩家 ID',
        'token' => 'string(required) #token',
        'language' => 'string() #语言',
        'merchantCode' => "string(required) #标识商户 ID",
        'serialNo' => "string(required) #用于标识消息的序列，由调用者生成",
    ];
    /**
     * 错误代码
     * @var array
     */
    protected $codes = [
        '0' => 'Success',
        '1' => 'System Error',
        '3' => 'Service Inaccessible',
        '100' => 'Request Timeout',
        '101' => 'Call Limited',
        '104' => 'Request Forbidden',
        '105' => 'Missing Parameters',
        '106' => 'Invalid Parameters',
        '107' => 'Duplicated Serial NO.',
        '108' => 'Merchant Key Error',
        '110' => 'Record ID Not Found',
        '10113' => 'Merchant Not Found',
        '112' => 'API Call Limited',
        '113' => 'Invalid Acct ID Acct ID',
        '118' => 'Invalid Format Parse Json Data Failed',
        '50099' => 'Acct Exist',
        '50100' => 'Acct Not Found',
        '50101' => 'Acct Inactive',
        '50102' => 'Acct Locked',
        '50103' => 'suspend Acct Suspend',
        '50104' => 'Token Validation Failed',
        '50110' => 'Insufficient Balance',
        '50111' => 'Exceed Max Amount',
        '50112' => 'Currency Invalid Deposit/withdraw',
        '50113' => 'Amount Invalid Deposit/withdraw',
        '50115' => 'Date Format Invalid',
        '10104' => 'Password Invalid',
        '30003' => 'Bet Setting Incomplete',
        '10103' => 'Acct Not Found',
        '10105' => 'Acct Status Inactived',
        '10110' => 'Acct Locked',
        '10111' => 'suspend Acct Suspend',
        '11101' => 'BET INSUFFICIENT BALANCE',
        '11102' => 'Bet Draw Stop Bet',
        '11103' => 'BET TYPE NOT OPEN',
        '11104' => 'BET INFO INCOMPLETE',
        '11105' => 'BET ACCT INFO INCOMPLETE',
        '11108' => 'BET REQUEST INVALID',
        '12001' => 'BET SETTING INCOMPLETE',
        '1110801' => 'BET REQUEST INVALID MAX',
        '1110802' => 'BET REQUEST INVALID MIN',
        '1110803' => 'BET REQUEST INVALID TOTALBET',
        '50200' => 'GAME CURRENCY NOT ACTIVE',
    ];

    public function run()
    {
        $params = $this->request->getParams();
        if (empty($params['acctId'])) {
            return $this->response->withStatus(200)
                ->withHeader('Content-Type', 'application/json')
                ->withJson(['code' => '113', 'msg' => 'AcctID不正确']);
        }
        $gameClass = new Logic\Game\Third\SGMK($this->ci);
        $config = $gameClass->initConfigMsg('SGMK');
        $return = [
            'code' => 1,
        ];
        if (!isset($params['acctId']) || !isset($params['token']) || empty($params['merchantCode'])) {
            $return = [
                'code' => 106,
            ];
        } elseif ($params['merchantCode'] != $config['cagent']) {
            $return = [
                'code' => 50099,
            ];
        } else {
            $tid = intval(ltrim($params['acctId'], 'game'));
            $www_notify = \DB::table('customer_notify')
                ->where('customer_id', $tid)
                ->where('status', 'enabled')
                ->value('www_notify');
            if (empty($www_notify)) {
                $return = [
                    'code' => 50100,
                ];
            } else {
                //推送消息
                $url = rtrim($www_notify, '/') . '/game/third/sgmk';
                $api_verify_token = $this->ci->get('settings')['app']['api_verify_token'];
                $api_token = $api_verify_token.date("Ymd");
                $res = \Utils\Curl::post($url, '', $params, '', true, ['api-token:'.$api_token]);
                if ($res['status'] == 200) {
                    $return = json_decode($res['content'], true);
                } else {
                    $return = [
                        'code' => 1
                    ];
                }
            }
        }

        $return['msg'] = $this->codes[$return['code']];
        $return['merchantCode'] = $config['cagent'];
        $return['serialNo'] = str_replace('.', '', sprintf('%.6f', microtime(TRUE)));
        $this->logger->debug('sgmk:authorize', ['authorize' => $params, 'return' => $return]);
        GameApi::addElkLog(['authorize' => $params, 'return' => $return], 'SGMK');
        return $this->response->withStatus(200)
            ->withHeader('Content-Type', 'application/json')
            ->withJson($return);
    }

};