<?php
use Utils\Shop\Action;

//手机号码校验
return new class extends Action {
    public function run() {
        $phoneCode = $this->request->getQueryParam('telphone_code', '+86');
        $mobile = $this->request->getQueryParam('telphone');
        //手机号
        if(!empty($mobile)){
            $len=strlen($phoneCode);
            $phoneCode=substr($phoneCode,1,$len);
            if($phoneCode=='86'){
                if(!preg_match("/^1[3456789]{1}\d{9}$/",$mobile)){
                    return $this->lang->set(140);
                }

                if(strlen($mobile)>11){
                    return $this->lang->set(141);
                }
                if(strlen($mobile)<11){
                    return $this->lang->set(141);
                }
            }

            if(!preg_match("/^\d*$/",$mobile)){
                return $this->lang->set(140);
            }

            if(strlen($mobile)>15){
                return $this->lang->set(143);
            }
        }else{
            return $this->lang->set(886, ['手机号码不能为空']);
        }
//        if(DB::table('user')->where('name', $mobile)->count() > 0){
//            return $this->lang->set(144);
//        }

        return $this->lang->set(0);
    }
};