<?php

use Logic\Game\GameApi;
use Utils\Game\Action;

return new class extends Action
{
    const TITLE = "PNG游戏玩家记录推送";
    const TAGS = '游戏';
    const DESCRIPTION = "PNG游戏回调";

    public function run()
    {
        $messages = $this->request->getParam('Messages');
        GameApi::addElkLog(['Messages' => $messages], 'PNG');
        $result = true;
        $orders = [];
        if (!empty($messages)) {
            foreach ($messages as $message) {
                if ($message['MessageType'] == 4) {
                    $orders[] = $message;
                }
                /*switch ($message['MessageType']) {
                    case 1:
                        $result = $this->CasinoPlayerLogin($message);
                        break;
                    case 2:
                        $result = $this->CasinoPlayerLogout($message);
                        break;
                    case 3:
                        $result = $this->CasinoTransactionReserve($message);
                        break;
                    case 4:
                        $result = $this->CasinoTransactionReleaseOpen($message);
                        break;
                    case 5:
                        $result = $this->CasinoTransactionReleaseClosed($message);
                        break;
                    case 6:
                        $result = $this->CasinoJackpotRelease($message);
                        break;
                    case 7:
                        $result = $this->CasinoFreegameEnd($message);
                        break;
                }
                if(!$result){
                    break;
                }*/
            }
            if (!empty($orders)) {
                $gameClass = new Logic\Game\Third\PNG($this->ci);
                $result = $gameClass->updateOrder($orders);
            }
        }
        if ($result) {
            return $this->response->withStatus(200)
                ->withHeader('Content-Type', 'application/json')
                ->withJson([
                    'state' => 0,
                    'ts' => date('Y-m-d\TH:i:s', time()),
                ]);
        } else {
            return $this->response->withStatus(401)
                ->withHeader('Content-Type', 'application/json')
                ->withJson([
                    'state' => -2,
                    'message' => 'error',
                    'ts' => date('Y-m-d\TH:i:s', time()),
                ]);
        }

    }

    /**
     * CasinoPlayerLogin（赌场玩家登录） 1
     */
    private function CasinoPlayerLogin($message)
    {
        return true;
    }

    /**
     * CasinoPlayerLogout（赌场玩家登出） 2
     */
    private function CasinoPlayerLogout($message)
    {
        return true;
    }

    /**
     * CasinoTransactionReserve（赌场交易保留） 3
     */
    private function CasinoTransactionReserve($message)
    {
        return true;
    }

    /**
     * CasinoTransactionReleaseOpen（赌场交易释放开启） 4
     */
    private function CasinoTransactionReleaseOpen($message)
    {
        $gameClass = new Logic\Game\Third\PNG($this->ci);
        return $gameClass->updateOrder($message);
    }

    /**
     * CasinoTransactionReleaseClosed（赌场交易释放关闭）5
     */
    private function CasinoTransactionReleaseClosed($message)
    {
        return true;
    }

    /**
     * CasinoJackpotRelease（赌场彩池释放） 6
     */
    private function CasinoJackpotRelease($message)
    {
        return true;
    }

    /**
     * CasinoFreegameEnd（赌场免费游戏结束）7
     */
    private function CasinoFreegameEnd($message)
    {
        return true;
    }
};