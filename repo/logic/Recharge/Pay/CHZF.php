<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/19
 * Time: 10:09
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class CHZF extends BASES
{
    private $bank_name = [
        '102'=>'工商银行',
        '103'=>'农业银行',
        '104'=>'中国银行',
        '105'=>'建设银行',
        '302'=>'中信银行',
        '303'=>'光大银行',
        '304'=>'华夏银行',
        '306'=>'广发银行',
        '307'=>'平安银行',
        '308'=>'招商银行',
        '309'=>'兴业银行',
        '310'=>'浦发银行',
        '313'=>'北京银行',
        '403'=>'邮储银行',
    ];
    //与第三方交互
    public function start()
    {
        $money = $this->money;
        if (is_int((int)$money/100)) {
            $this->initParam();
            $this->basePost();
            $this->parseRE();

        } else {
            $this->return['code'] = 63;
            $this->return['msg'] = 'CHZF:' . '充值金额格式不正确，必须为正整数！';
            $this->return['way'] = '';
            $this->return['str'] = '';
            return;
        }
    }

    //初始化参数
    public function initParam()
    {
        $this->parameter = array(
            'unique_id' => $this->partnerID,
            'order_number' => $this->orderID,
            'price' => $this->money,//单位是分
            'notice_url' => $this->notifyUrl
        );

        //var_dump($this->data);
        if ($this->data['show_type'] == 'h5' && $this->data['scene']=='unionpay') {
            $this->parameter['return_url'] = $this->returnUrl;
            $this->parameter['cardname'] = $this->bank_name[$this->data['bank_code']] ?? '';
            $this->parameter['bank_code'] =  $this->data['bank_code'];
            $this->parameter['channelid'] = 1;
            $this->parameter['card_type'] = 1;
        }
       else  if ($this->data['show_type'] == 'h5'|| $this->data['show_type'] == 'quick') {
            $this->parameter['return_url'] = $this->returnUrl;
        }

        $this->parameter['sign'] = $this->sytMd5($this->parameter);
    }

    public function sytMd5($pieces)
    {
        ksort($pieces);
        $sign = urldecode(http_build_query($pieces)) . '&key=' . $this->key;
        $strSign = md5($sign);
        return $strSign;
    }


    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if ($re['code'] == 200) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'success';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['data'];

        } else {
            $this->return['code'] = $re['code'] ?? 1;
            $this->return['msg'] = 'CHZF:' . $re['msg'] ?? '请求错误';
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($parameters)
    {
//        var_dump($parameters);die;
        $res = [
            'status' => $parameters['status'],
            'order_number' => $parameters['orderNum'],
            'third_order' => $parameters['orderNum'],
            'third_money' => $parameters['price'],
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['orderNum']);

        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '未有该订单';
        }

        $strArr['key'] = $config['key'];
        $strArr['price'] = $parameters['price'];
        $strArr['orderNum'] = $parameters['orderNum'];
        $strSign = '';
        foreach ($strArr as $item) {
            $strSign .= $item;
        }

        $strSign = md5($strSign);
        $result = $strSign == $parameters['token'];

        if ($result) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }
}