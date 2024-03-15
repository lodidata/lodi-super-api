<?php
/**
 *   自有
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class PDD extends BASES
{

    /**
     * 生命周期
     */
    public function start()
    {
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    /**
     * 提交参数组装
     */
    public function initParam()
    {
        $this->parameter = [
            'merchantOrderNumber' => (string)$this->orderID,
            'amount' => intval($this->money),
            'merchantId' => (string)$this->partnerID,
            'returnUrl' => (string)$this->notifyUrl,
        ];
        $str = '{';
        foreach ($this->parameter as $key => $val){
            if(in_array($key,['amount'])){
                $str .= '"' . $key .'":' . $val . ',';
            }else {
                $str .= '"' . $key . '":"' . $val . '",';
            }
        }
        $str = rtrim($str,',');
        $str .='}';

        //秘钥存入 token字段中
        $this->parameter['sign'] = (string)strtoupper(md5($str));

        $this->parameter['type'] = intval($this->payType);

    }


    /**
     * 组装前端数据,输出结果
     */
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['data']) && $re['data']) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['data'];
        } else {
            $this->return['code'] = 55;
            $this->return['msg'] = 'PDD:' . $re['msg'];
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    /**
     * 回调验证处理
     * 获取接口返回数据，再次验证
     */
    public function returnVerify($data)
    {
        $res = [
            'status' => 1,
            'order_number' => $data['orderNumber'],
            'third_order' => $data['orderNumber'],
            'third_money' => $data['amount'],
            'error' => '',
        ];

        $config = Recharge::getThirdConfig($data['orderNumber']);

        //无此订单
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '订单号不存在';

            return $res;
        }

        //校验sign
        $sign = $data['sign'];
        $tmp = [
            "amount" => $data['amount'],
            "orderNumber" => $data['orderNumber'],
            "merchantId" => $config['partner_id'],
        ];
        $str = '{';
        foreach ($tmp as $key => $val){
            if($key == 'amount'){
                $str .= '"' . $key .'":' . $val . ',';
            }else {
                $str .= '"' . $key . '":"' . $val . '",';
            }
        }
        $str = rtrim($str,',');
        $str .='}';
        if (strtoupper($sign) != strtoupper(md5($str))) {
            $res['status'] = 0;
            $res['error'] = '签名验证失败';
        }
        return $res;
    }


}