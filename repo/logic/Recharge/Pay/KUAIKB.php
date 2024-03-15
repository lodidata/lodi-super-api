<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/10/15
 * Time: 15:44
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class KUAIKB extends BASES
{

    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    //组装数组
    public function initParam()
    {

        //请求数据
        $this->parameter = array(
            "MemberID" => $this->partnerID, //商户号
            "TerminalID" => $this->data['terminal'],
            "InterfaceVersion" => '4.0',
            "KeyType" => "1",
            "PayID" => $this->payType,
            "TradeDate" => strval(date("YmdHis",time())),
            "TransID" => $this->orderID,
            "OrderMoney" => $this->money, //支付金额 单位分
            "ReturnUrl" => $this->notifyUrl, //异步回调 , 支付结果以异步为准
            "PageUrl" => $this->returnUrl, //同步回调 不作为最终支付结果为准，请以异步回调为准
            "NoticeType" => "1",
        );
        $this->parameter["Signature"] = $this->getSign($this->parameter, $this->key); //加密
    }

    //返回参数
    public function parseRE(){
        $this->parameter = $this->arrayToURL();
        $this->parameter .= '&url=' . $this->payUrl;
        $this->parameter .= '&method=POST';

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->jumpURL . '?' . $this->parameter;
    }



    public function getSign($arr, $key)
    {
        if(isset($arr['PageUrl'])) {
            $MARK = "|";
            $signStr =  $arr["MemberID"].$MARK.$arr["PayID"].$MARK.$arr["TradeDate"].$MARK.$arr["TransID"].$MARK.$arr["OrderMoney"].$MARK.$arr["PageUrl"].$MARK.$arr["ReturnUrl"].$MARK.$arr["NoticeType"].$MARK.$key;
        } else {
            $MARK = "~|~";
            $signStr ='MemberID='.$arr['MemberID'].$MARK.'TerminalID='.$arr['TerminalID'].$MARK.'TransID='.$arr['TransID'].$MARK.'Result='.$arr['Result'].$MARK.'ResultDesc='.$arr['ResultDesc'].$MARK.'FactMoney='.$arr['FactMoney'].$MARK.'AdditionalInfo='.$arr['AdditionalInfo'].$MARK.'SuccTime='.$arr['SuccTime'].$MARK.'Md5Sign='.$key;
        }
        return md5($signStr);

    }


    public function returnVerify($params)
    {
//        var_dump($params);exit;

        $res = [
            'status' => 0,
            'order_number' => $params['TransID'],//outorderno
            'third_order' => $params['TransID'],
            'third_money' => $params['FactMoney'],
            'error' => ''
        ];


        $config = Recharge::getThirdConfig($params['TransID']);
        $returnSign = $params['Md5Sign'];
        unset($params['Md5Sign']);
        $mysign = $this->getSign($params, $config['key']);
        if ($returnSign == $mysign) {
            if ($params['Result'] == '1') {//支付成功
                $res['status'] = 1;
            } else { //支付失败
                $res['error'] = '支付失败';
                echo 'fail';
            }
        } else {
            $res['error'] = '该订单验签不通过或已完成';
        }

        return $res;

    }

}