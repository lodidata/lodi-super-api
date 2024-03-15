<?php
/**
 *易支付
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class NEWYIZF extends BASES
{

    /**
     * 生命周期
     */
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    /**
     * 提交参数组装
     */
    public function initParam()
    {
        $this->parameter = [
            'APPID' => $this->partnerID,
            'username' => $this->data['app_id'],
            'time' => time()*100,
        ];
        
        $this->parameter['sign'] = $this->_sign($this->parameter,$this->key);
        $this->parameter['choose'] = $this->payType; //bank_data
        $this->parameter['money'] = $this->money/100;
        $this->parameter['order_num'] = $this->orderID;
    }

    /**
     * 组装前端数据,输出结果，使用go.php方法，自动post到支付
     */
    public function parseRE()
    {
        $this->parameter = $this->arrayToURL();

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->payUrl . '?' . $this->parameter;
    }

    /**
     * 回调验证处理
     * 获取接口返回数据，再次验证
     */
    public function returnVerify($parameters)
    {
        $res = [
            'order_number' => $parameters['order_num'],
            'third_order' => $parameters['user_order'],
            'third_money' => $parameters['money']*100,
            'status'=>1,
            'error'=>''
        ];
        $config = Recharge::getThirdConfig($parameters['order_num']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }
        if ($parameters['status'] != 1){
            $res['status'] = 0;
            $res['error'] = '支付订单状态未成功';
            return $res;
        }
        $tmp = [
            'APPID' => $config['partner_id'],
            'username' => $config['app_id'],
            'time' => $parameters['time'],
        ];
        $sign = $this->_sign($tmp,$config['key']);
        if (strtoupper($sign) !== strtoupper($parameters['sign'])) {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
            return $res;
        }
        return $res;
    }

    /**
     * 生成sign
     */
    private function _sign($params,$tkey)
    {
        $string = '';
        foreach ($params as $k => $v) {
            if ($v != '' && $v != null && $k != 'sign') {
                $string = $string . $k . '=' . $v . '&';
            }
        }
        $string = rtrim($string,'&');
        $sign_str = $string.$tkey;
        $sign = md5($sign_str);
        return $sign;
    }

    /**
     * 回调后进行业务判断
     * @param $params
     * @param $conf
     * @param $reques_params
     * @return bool
     */
    public function returnVail($params,$tkey)
    {
        $return_sign = $params['sign'];
        $sign = $this->_sign($params,$tkey);
        if ($sign != $return_sign){
            return false;
        }
        return true;
    }
}