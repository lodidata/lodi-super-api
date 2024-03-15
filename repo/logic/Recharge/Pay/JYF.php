<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * Created by PhpStorm.
 * User: Nico
 * Date: 2018/5/15
 * Time: 11:22
 * 天天乐彩 极易付
 */

class JYF extends BASES
{


    //与第三方交互
    public function start(){
        $this->initParam();
        if(!$this->payUrl)
            $this->payUrl = 'http://upay.95018.com/cgi-bin/v2.0/api_ali_pay_apply.cgi';

        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter = array(
            'spid' => $this->partnerID,
            'notify_url' => $this->notifyUrl,
            'sp_billno'=> $this->orderID,
            'spbill_create_ip'=>'127.0.0.1',
            'pay_type' => $this->data['bank_data'],
            'tran_time'=>Date('YmdHis', $this->data->order_time),
            'tran_amt'=>$this->money,
            'cur_type'=>'CNY',
            'interface_version'	=> 'V3.1',
            'item_name' => 'goods'
        );

        if ($this->parameter['pay_type'] == 800207){
            $this->parameter['sp_userid'] = $this->partnerID;
            $this->parameter['money'] = $this->money;
            $this->parameter['cur_type'] = 1;
            $this->parameter['memo'] = 'goods';
            $this->parameter['card_type'] = 1;
            $this->parameter['bank_segment'] =  $_REQUEST['pay_code'] ?? '1004';;
            $this->parameter['channel'] = 1;
            $this->parameter['return_url'] = $this->returnUrl;
            $this->parameter['spbillno'] = $this->orderID;
            $this->parameter['user_type'] = 1;
            $this->parameter['encode_type'] = 'MD5';
            unset($this->parameter['spbill_create_ip']);
            unset($this->parameter['tran_time']);
            unset($this->parameter['tran_amt']);
            unset($this->parameter['interface_version']);
            unset($this->parameter['item_name']);
            unset($this->parameter['pay_type']);
        }elseif ($this->parameter['pay_type'] == '800209'){
            $this->parameter['pc_userid']=$this->partnerID;
            $this->parameter['pay_show_url']=$this->returnUrl;

        }elseif ($this->parameter['pay_type'] == 'Wx_scan:800201'){
            $this->parameter['bank_mch_name'] = 'abc';
            $this->parameter['bank_mch_id'] = '2018051';
            $this->parameter['pay_type'] = '800201';
            $this->parameter['pay_show_url']=$this->returnUrl;
            $this->parameter['out_channel']='qqpay';
            unset($this->parameter['interface_version']);
        }elseif ($this->parameter['pay_type'] == 'Yl_h5:800209'){
            $this->parameter['bank_mch_id'] = '2018051';
            $this->parameter['pay_type'] = '800209';
            $this->parameter['pay_show_url'] = $this->returnUrl;
        }


        if (isset($this->parameter['encode_type'])){
            $this->parameter['sign'] = $this->md5Base($this->parameter,false);
        }else{
            $this->parameter['sign'] = $this->md5Base($this->parameter,true);
        }



    }
    public function parseRE(){

        if (isset($this->parameter['encode_type'])){
            $this->parameter['sign'] = urlencode($this->parameter['sign']);
            $this->parameter = $this->arrayToURL();
            $this->parameter .= '&url=' . $this->payUrl;
            $this->parameter .= '&method=POST';
            $str = $this->jumpURL.'?'.$this->parameter;
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $str;
        }else{
            $this->basePost();
            $re = $this->xmlToArray($this->re);
            if($re['retcode'] == 00){
                $jump = isset($re['jump_url']) ? $re['jump_url'] : $re['qrcode'];
                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = $jump;
            }else{
                $this->return['code'] = 886;
                $this->return['msg'] = 'JYF:'.$re['retmsg'];
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = '';
            }
        }

    }

    public function md5Base($pieces,$isStrtoupper = true){
        ksort($pieces);
        $string='';
        foreach ($pieces as $keys=>$value){
            if($value !='' && $value!=null){
                $string=$string.$keys.'='.$value.'&';
            }
        }
        $string=$string.'key='. $this->key;
        if ($isStrtoupper){
            $string=strtoupper(md5($string));
        }else{
            $string=strtolower(md5($string));
        }
        return $string;
    }


    public  function returnVerify($parameters)
    {
        $res = [
            'status' => 0,
            'order_number' => isset($parameters['sp_billno']) ?  $parameters['sp_billno'] : $parameters['spbillno'],
            'third_order' => $parameters['listid'],
            'third_money' => $parameters['tran_amt'],
            'error' => '',
        ];
        if($parameters['retcode'] == '00'){
            $config = Recharge::getThirdConfig(isset($parameters['sp_billno']) ?  $parameters['sp_billno'] : $parameters['spbillno']);
            $sign_status = $this->signVerify($parameters,$parameters['sign'],$config['pub_key']);
            if($sign_status){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';

        return $res;
    }

    /**
     * 返回地址验证
     *
     * @param
     * @return boolean
     */
    public function signVerify($pieces,$sign,$cert) {
        ksort($pieces);
        $string='';
        foreach ($pieces as $key=>$value){
            if($value !='' && $value !=null && $key !='sign' && $key!='retcode' && $key!='retmsg'){
                $string=$string.$key.'='.$value.'&';
            }
        }
        $string=$string.'key='. $cert;
        $mySign=md5($string);
        return $sign == $mySign;


    }


    //将XML转为array
    public function xmlToArray($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }




}