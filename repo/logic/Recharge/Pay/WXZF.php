<?php
namespace Las\Pay;
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 *
 * 微讯支付
 * @author Lion
 */
class WXZF extends BASES {

    static function instantiation(){
        return new WXZF();
    }

    private $_returnUrl = '';

    //与第三方交互
    public function start(){
        $this->initParam();       // 数据初始化
        $this->parseRE();         // 处理结果
    }

    //组装数组
    public function initParam(){

        $this->parameter['version']    = '1.0';                    //版本号 固定值
        $this->parameter['customerid'] = $this->partnerID;         //商户号
        $this->parameter['sdorderno']  = $this->orderID;           //商户订单号   不超过30
        $this->parameter['total_fee']  = str_replace(',', '', number_format($this->money/100, 2)); //付款金额
        $this->parameter['paytype']    = $this->data['bank_data']; //支付类型
        $this->parameter['bankcode']   = '';                       //银行编号 网银直连不可为空，其他支付方式可为空
        $this->parameter['notifyurl']  = $this->notifyUrl;         //异步通知
        $this->parameter['returnurl']  = $this->returnUrl;         //同步
        $this->parameter['remark']     = '1';                      //附加参数  按参数 返回不可超过30字  可为空
        $this->parameter['sign']       = $this->sign();            //签名

        $str = http_build_query($this->parameter);
        $this->_returnUrl = $this->payUrl.'?'.$str;
 
    }

    //生成签名
    public function sign(){

        $ms = 'version=1.0&'.'customerid='.$this->partnerID.'&total_fee='.str_replace(',', '', number_format($this->money/100, 2)).'&sdorderno='.$this->orderID.'&notifyurl='.$this->notifyUrl.'&returnurl='.$this->returnUrl.'&'.$this->key;
        return md5($ms);

    }

    //处理结果
    public function parseRE(){

        $this->return['code'] = 0;
        $this->return['msg']  = 'SUCCESS';
        $this->return['way']  = $this->data['return_type'];
        $this->return['str']  = $this->_returnUrl;

    }

    //回调数据校验
    /*
     * $resArray 第三方通知数组
     * $deposit  系统流水信息
     * $vender   授权信息
     * */
    public function returnVerify($parameters = array()) {

        $res = [
            'status' => 0,
            'order_number' => $parameters['sdorderno'],
            'third_order'  => $parameters['sdpayno'],
            'third_money'  => $parameters['total_fee']*100,
            'error'        => ''
        ];

        $config = Recharge::getThirdConfig($parameters['sdorderno']);
        $str = 'customerid='.$parameters['customerid'].'&status='.$parameters['status'].'&sdpayno='.$parameters['sdpayno'].'&sdorderno='.$parameters['sdorderno'].'&total_fee='.$parameters['total_fee'].'&paytype='.$parameters['paytype'].'&'.$config['key'];
        $sign = strtolower(md5($str));
        $tenpaySign = strtolower($parameters['sign']);
        if($sign == $tenpaySign){
            $res['status']  = 1;
        }else{
            $res['error'] = '该订单验签不通过或已完成';
        }

        return  $res;
    }

}
