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

/**
 * 创富
 * Class CF
 * @package Logic\Recharge\Pay
 */
class CF extends BASES
{
    //与第三方交互
    public function start(){
        $this->initParam();
        if(!$this->payUrl)
            $this->payUrl = 'http://localhost/GateWay/ReceiveBank.aspx';
//        $this->basePost();
        $this->parseRE();
    }

    //初始化参数
    public function initParam(){
        $this->parameter = array(
            'p0_Cmd'             => "Buy",# 支付请求，固定值"Buy"
            'p1_MerId'			 => $this->partnerID,				 		#测试使用
            'p2_Order'			 => $this->orderID,
            'p3_Amt'             => $this->money/100 .'',
            'p4_Cur'		     => "CNY",
            'p5_Pid'			 => $this->orderID,
            'p6_Pcat'		     => 'goods',
            'p7_Pdesc'		     => 'goods_desc',
            'p8_Url'             => $this->notifyUrl,
            'p9_SAF'             => '0',
            'pa_MP'              => $this->orderID,
            'pd_FrpId'           => $this->data['bank_data'],
            'pr_NeedResponse'    => '1',
        );
        $this->parameter['hmac'] = self::HmacMd5($this->parameter,$this->key);
        $this->parameter['url'] = $this->payUrl;
        $this->parameter['method'] = 'POST';
    }

    public function parseRE(){
        //该支付第三方直接生成二维码
//        echo $this->jumpURL.'?'.$this->arrayToURL();exit;
        $this->return['code'] = 0;
        $this->return['msg']  = 'SUCCESS';
        $this->return['way']  = $this->showType;;
        $this->return['str']  = $this->jumpURL.'?'.$this->arrayToURL();
    }

    //签名验证
    public function returnVerify($parameters) {
        $res = [
            'status'       => 1,
            'order_number' => $parameters['r6_Order'],
            'third_order'  => $parameters['r2_TrxId'],
            'third_money'  => 0,
            'error'        => '',
        ];
        $config = Recharge::getThirdConfig($parameters['r6_Order']);

        if(!$config){
            $res['status'] = 0;
            $res['error']  = '未有该订单';
        }
        $result = $this->returnVail($parameters,$config);
        if($result){
            $res['status'] = 1;
        }else{
            $res['status'] = 0;
            $res['error']  = '验签失败！';
        }
        return $res;
    }

    public function returnVail($input,$config){
        $data['p1_MerId']	= $this->partnerID;
        $data['r0_Cmd']	    = $input['r0_Cmd'];
        $data['r1_Code']	= $input['r1_Code'];
        $data['r2_TrxId']	= $input['r2_TrxId'];
        $data['r3_Amt']		= $input['r3_Amt'];
        $data['r4_Cur']		= $input['r4_Cur'];
        $data['r5_Pid']		= $input['r5_Pid'];
        $data['r6_Order']	= $input['r6_Order'];
        $data['r7_Uid']		= $input['r7_Uid'];
        $data['r8_MP']		= $input['r8_MP'];
        $data['r9_BType']	= $input['r9_BType'];
        $hmac		        = $input['hmac'];
        $sign               = self::HmacMd5($data,$config['pub_key']);
        return $hmac == $sign;
    }

    public  function HmacMd5($data,$cert) {

        $str = implode('',$data);
        $key = iconv("GB2312","UTF-8",$cert);
        $str = iconv("GB2312","UTF-8",$str);

        $b = 64; // byte length for md5
        if (strlen($key) > $b) {
            $key = pack("H*",md5($key));
        }
        $key    = str_pad($key, $b, chr(0x00));
        $ipad   = str_pad('', $b, chr(0x36));
        $opad   = str_pad('', $b, chr(0x5c));
        $k_ipad = $key ^ $ipad ;
        $k_opad = $key ^ $opad;

        $sign = md5($k_opad . pack("H*",md5($k_ipad . $str)));

        return $sign;
    }
}