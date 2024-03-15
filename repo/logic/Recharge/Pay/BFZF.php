<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Curl;

/**
 * @author viva
 */

class BFZF extends BASES {

    private $iv = "87654321";
    //与第三方交互
    public function start(){

        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter = array(
            'branchId'    => $this->data['app_id'],
            'merCode'	    => $this->partnerID,
            'bizType'	    => $this->data['bank_data'],
            'settType'	    => 'T1',
            'orderId'	    => $this->orderID,
            'transDate'	    => date('Ymd'),
            'transTime'	    => date('His'),
            'transAmt'	    => $this->money,
            'subject'	    => 'GOODS',
            'returnUrl'   => $this->notifyUrl,
            'notifyUrl'  => $this->returnUrl,
        );
        $this->parameter['signature'] = $this->currentMd5();
        $str = json_encode($this->parameter,JSON_UNESCAPED_UNICODE);
        $this->parameter = urlencode(urlencode($this->encrypt3DES($str,$this->data['app_secret'])));
    }
    
    public function parseRE(){
        $re = json_decode($this->re,true);
        if(isset($re['qrCodeURL']) && $re['qrCodeURL']){
            $this->return['code'] = 0;
            $this->return['msg']  = 'SUCCESS';
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = $re['qrCodeURL'];
        }else{
            $this->return['code'] = 886;
            $this->return['msg']  = 'BFZF:'.$re['respMsg'];
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = '';
        }
    }

    public function encrypt3DES($input,$key,$base64=true){
        $size = 8;
        $tst =new self();
        $input = /*self::*/$tst->pkcs5_pad($input,$size);
        $encryption_descriptor = mcrypt_module_open(MCRYPT_3DES,'','cbc','');
        mcrypt_generic_init($encryption_descriptor, substr($key,0,24), $this->iv);//这里截取KEY前24位，可直接$key
        $data = mcrypt_generic($encryption_descriptor,$input);
        mcrypt_generic_deinit($encryption_descriptor);
        mcrypt_module_close($encryption_descriptor);
        return base64_encode($data);
    }

    private function pkcs5_pad($text,$blocksize){
        $pad = $blocksize-(strlen($text)%$blocksize);
        return $text.str_repeat(chr($pad),$pad);
    }

    public function decrypt3DES($crypt,$key,$base64 = true) {
        $crypt = base64_decode($crypt);
        $encryption_descriptor = mcrypt_module_open(MCRYPT_3DES, '', 'cbc', '');
        mcrypt_generic_init($encryption_descriptor, substr($key,0,24), $this->iv);
        $decrypted_data = mdecrypt_generic($encryption_descriptor, $crypt);
        mcrypt_generic_deinit($encryption_descriptor);
        mcrypt_module_close($encryption_descriptor);
        $decrypted_data = self::pkcs5_unpad($decrypted_data);
        return rtrim($decrypted_data);
    }
    private function pkcs5_unpad($text){
        $pad = ord($text{strlen($text)-1});
        if ($pad > strlen($text)) return false;
        if (strspn($text, chr($pad), strlen($text) - $pad) != $pad)
            return false;
        return substr($text, 0, -1 * $pad);
    }
    
    /**
     * 返回地址验证
     *
     * @param
     * @return boolean
     */
    public function returnVerify($param) {
        //解密
        $input = [];
        $app_secret = \DB::table('pay_config')->where('channel_id',52)->pluck('app_secret')->toArray();
        foreach ($app_secret as $v) {
            $input = $this->decrypt3DES(urldecode($param['data']),$v);
            if($input)
                break;
        }
        if($input){
            $input = json_decode($input,true);
        }
        $res = [
            'status' => 0,
            'order_number' => $input['orderId'] ?? '',
            'third_order'  => $input['flowId'] ?? '',
            'third_money'  => $input['transAmt'] ?? '',
            'error'        => '',
        ];
        if($input) {
            $config = Recharge::getThirdConfig($input['orderId']);
            if (!$config) {
                $res['error'] = '订单已完成或不存在';
            } else if ($input['transCode'] != '00') {
                $res['error'] = '该订单未支付';
            } else {
                $this->parameter = $input;
                unset($this->parameter['signature']);
                $this->key = $config['pub_key'];
                $sign = $this->currentMd5();
                if ($sign == $input['signature']) {
                    $res['status'] = 1;
                } else {
                    $res['error'] = '该订单验签不通过';
                }
            }
        }
        return $res;
    }
}
